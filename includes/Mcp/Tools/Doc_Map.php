<?php
/**
 * Normalized (id-indexed) view of an element tree for precise edits.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Same model as the editor store: nodes[id] = node without children + parent + children ids.
 */
final class Doc_Map {

	/** @var array<string, array<string,mixed>> */
	public array $nodes = array();

	/** @var string[] */
	public array $root = array();

	/**
	 * @param array<int, array<string,mixed>> $tree Tree.
	 */
	public static function from_tree( array $tree ): Doc_Map {
		$map       = new self();
		$map->root = $map->add_nodes( $tree, null );
		return $map;
	}

	/**
	 * @param array<int, array<string,mixed>> $nodes Nodes.
	 * @return string[]
	 */
	private function add_nodes( array $nodes, ?string $parent ): array {
		$ids = array();
		foreach ( $nodes as $node ) {
			$id = (string) ( $node['id'] ?? '' );
			if ( ! Utils::is_valid_id( $id ) || isset( $this->nodes[ $id ] ) ) {
				do {
					$id = Utils::generate_id();
				} while ( isset( $this->nodes[ $id ] ) );
			}
			$children = (array) ( $node['children'] ?? array() );
			unset( $node['children'] );
			$node['id']            = $id;
			$node['_parent']       = $parent;
			$node['_children']     = array();
			$this->nodes[ $id ]    = $node;
			$this->nodes[ $id ]['_children'] = $this->add_nodes( $children, $id );
			$ids[]                 = $id;
		}
		return $ids;
	}

	/**
	 * @param string[]|null $ids Ids (default root).
	 * @return array<int, array<string,mixed>>
	 */
	public function to_tree( ?array $ids = null ): array {
		$out = array();
		foreach ( null === $ids ? $this->root : $ids as $id ) {
			if ( ! isset( $this->nodes[ $id ] ) ) {
				continue;
			}
			$node = $this->nodes[ $id ];
			$kids = $node['_children'];
			unset( $node['_parent'], $node['_children'] );
			$el = Plugin::instance()->elements()->get( (string) $node['type'] );
			if ( $el && ( $el->is_container() || null !== $el->nested() ) ) {
				$node['children'] = $this->to_tree( $kids );
			}
			$out[] = $node;
		}
		return $out;
	}

	public function has( string $id ): bool {
		return isset( $this->nodes[ $id ] );
	}

	/**
	 * @return string[] Reference to the child list of a parent (null = root).
	 */
	public function &children_of( ?string $parent ): array {
		if ( null === $parent ) {
			return $this->root;
		}
		return $this->nodes[ $parent ]['_children'];
	}

	public function parent_of( string $id ): ?string {
		return $this->nodes[ $id ]['_parent'] ?? null;
	}

	public function index_of( string $id ): int {
		$list = $this->children_of( $this->parent_of( $id ) );
		$i    = array_search( $id, $list, true );
		return false === $i ? -1 : (int) $i;
	}

	public function is_descendant( string $id, string $of ): bool {
		$cur = $this->parent_of( $id );
		while ( null !== $cur ) {
			if ( $cur === $of ) {
				return true;
			}
			$cur = $this->parent_of( $cur );
		}
		return false;
	}

	/**
	 * @param array<int, array<string,mixed>> $nodes Tree nodes (already validated).
	 * @return string[] Inserted ids.
	 */
	public function insert( ?string $parent, int $index, array $nodes ): array {
		$ids  = $this->add_nodes( $nodes, $parent );
		$list = &$this->children_of( $parent );
		$index = max( 0, min( $index, count( $list ) ) );
		array_splice( $list, $index, 0, $ids );
		return $ids;
	}

	public function remove( string $id ): void {
		if ( ! $this->has( $id ) ) {
			return;
		}
		$list = &$this->children_of( $this->parent_of( $id ) );
		$i    = array_search( $id, $list, true );
		if ( false !== $i ) {
			array_splice( $list, (int) $i, 1 );
		}
		$this->drop( $id );
	}

	private function drop( string $id ): void {
		foreach ( $this->nodes[ $id ]['_children'] ?? array() as $child ) {
			$this->drop( $child );
		}
		unset( $this->nodes[ $id ] );
	}

	public function move( string $id, ?string $parent, int $index ): void {
		$from = &$this->children_of( $this->parent_of( $id ) );
		$i    = array_search( $id, $from, true );
		if ( false !== $i ) {
			array_splice( $from, (int) $i, 1 );
			if ( $this->parent_of( $id ) === $parent && $i < $index ) {
				--$index;
			}
		}
		unset( $from );
		$to    = &$this->children_of( $parent );
		$index = max( 0, min( $index, count( $to ) ) );
		array_splice( $to, $index, 0, array( $id ) );
		$this->nodes[ $id ]['_parent'] = $parent;
	}

	/**
	 * @return string[] All ids.
	 */
	public function ids(): array {
		return array_keys( $this->nodes );
	}

	public function depth( string $id ): int {
		$d   = 0;
		$cur = $this->parent_of( $id );
		while ( null !== $cur ) {
			++$d;
			$cur = $this->parent_of( $cur );
		}
		return $d;
	}
}
