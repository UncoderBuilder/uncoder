<?php
/**
 * Helpers shared by MCP tools.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Outline rendering, post lookup, validation messages.
 */
final class Helpers {

	/**
	 * Compact, token-efficient text outline of an element tree.
	 *
	 * @param array<int, array<string,mixed>> $elements Tree.
	 */
	public static function outline( array $elements, int $depth = 0, int $max_depth = 12 ): string {
		$lines = array();
		foreach ( $elements as $node ) {
			$lines[] = str_repeat( '  ', $depth ) . '- ' . self::describe( $node );
			if ( ! empty( $node['children'] ) && $depth < $max_depth ) {
				$lines[] = rtrim( self::outline( (array) $node['children'], $depth + 1, $max_depth ) );
			}
		}
		return implode( "\n", array_filter( $lines, static fn( $l ) => '' !== $l ) );
	}

	/**
	 * @param array<string,mixed> $node Node.
	 */
	public static function describe( array $node ): string {
		$s    = is_array( $node['settings'] ?? null ) ? $node['settings'] : array();
		$type = (string) ( $node['type'] ?? '?' );
		$out  = $type . '#' . ( $node['id'] ?? '' );
		if ( ! empty( $node['label'] ) ) {
			$out .= ' "' . $node['label'] . '"';
		}
		$bits = array();
		if ( 'container' === $type ) {
			if ( 'grid' === ( $s['layout'] ?? '' ) ) {
				$bits[] = 'grid ' . ( $s['grid_columns'] ?? 3 ) . ' cols';
			} else {
				$bits[] = $s['direction'] ?? 'column';
			}
			if ( isset( $s['gap'] ) ) {
				$bits[] = 'gap ' . self::size( $s['gap'] );
			}
			if ( ! empty( $s['background']['color'] ) ) {
				$bits[] = 'bg ' . $s['background']['color'];
			} elseif ( ! empty( $s['background']['image']['url'] ) ) {
				$bits[] = 'bg image';
			}
			if ( isset( $s['width'] ) ) {
				$bits[] = 'width ' . self::size( $s['width'] );
			}
		}
		foreach ( array( 'title', 'text', 'content', 'description', 'caption' ) as $key ) {
			if ( isset( $s[ $key ] ) && is_string( $s[ $key ] ) && '' !== trim( $s[ $key ] ) ) {
				$text   = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $s[ $key ] ) ) );
				$bits[] = '"' . ( mb_strlen( $text ) > 70 ? mb_substr( $text, 0, 67 ) . '…' : $text ) . '"';
				break;
			}
		}
		if ( isset( $s['tag'] ) && 'container' !== $type ) {
			$bits[] = $s['tag'];
		}
		if ( ! empty( $s['image']['url'] ) || ! empty( $s['image']['id'] ) ) {
			$bits[] = 'image' . ( ! empty( $s['image']['id'] ) ? ' #' . $s['image']['id'] : '' );
		}
		if ( ! empty( $s['link']['url'] ) ) {
			$bits[] = '→ ' . $s['link']['url'];
		}
		if ( ! empty( $node['dynamic'] ) ) {
			$bits[] = 'dynamic: ' . implode( ',', array_map( static fn( $k, $d ) => $k . '=' . ( $d['tag'] ?? '' ), array_keys( $node['dynamic'] ), $node['dynamic'] ) );
		}
		$nested = Plugin::instance()->elements()->get( $type );
		if ( $nested && null !== $nested->nested() ) {
			$items = (array) ( $s[ $nested->nested()['items'] ] ?? array() );
			$bits[] = count( $items ) . ' items';
		}
		if ( ! empty( $node['disabled'] ) ) {
			$bits[] = 'DISABLED';
		}
		return $out . ( $bits ? ' (' . implode( ', ', $bits ) . ')' : '' );
	}

	private static function size( $v ): string {
		if ( is_array( $v ) && isset( $v['size'] ) ) {
			return $v['size'] . ( 'custom' === ( $v['unit'] ?? '' ) ? '' : ( $v['unit'] ?? '' ) );
		}
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * A post the current user can edit with the builder.
	 *
	 * @return \WP_Post|WP_Error
	 */
	public static function editable_post( $id ) {
		$id   = absint( $id );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || 'trash' === $post->post_status ) {
			return new WP_Error( 'not_found', sprintf( 'No page/post with id %d. Call list_pages to find ids.', $id ) );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'forbidden', Post_Types::TEMPLATE === $post->post_type ? 'Editing site-wide theme templates requires the edit_theme_options capability.' : 'You are not allowed to edit this item.' );
		}
		// Theme templates change the whole site: the connection needs the "design" permission.
		$ctx = \Uncoder\Builder\Mcp\Context::$current;
		if ( Post_Types::TEMPLATE === $post->post_type && $ctx && ! $ctx->has_scope( 'design' ) ) {
			return new WP_Error( 'forbidden', 'Theme templates need the "design" permission, which this connection was not granted.' );
		}
		if ( ! Plugin::instance()->documents()->is_supported( $id ) ) {
			return new WP_Error( 'unsupported', sprintf( 'Post type "%s" is not enabled for Uncoder (Settings → post types).', $post->post_type ) );
		}
		return $post;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function post_info( \WP_Post $post ): array {
		$info = array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'type'      => $post->post_type,
			'status'    => $post->post_status,
			'slug'      => $post->post_name,
			'url'       => Post_Types::TEMPLATE === $post->post_type ? '' : get_permalink( $post ),
			'edit_url'  => admin_url( 'post.php?action=uncoder&post=' . $post->ID ),
			'modified'  => get_post_modified_time( 'c', false, $post ),
			'builder'   => Utils::is_builder_post( $post->ID ),
		);
		if ( 'page' === $post->post_type ) {
			$tpl                   = get_page_template_slug( $post );
			$info['template']      = $tpl ? $tpl : 'default';
			$info['parent']        = $post->post_parent;
			$info['is_front_page'] = (int) get_option( 'page_on_front' ) === $post->ID;
		}
		if ( Post_Types::TEMPLATE === $post->post_type ) {
			$info['template_type'] = get_post_meta( $post->ID, Utils::META_TYPE, true );
		}
		return $info;
	}

	/**
	 * Normalizes (AI-friendly) and validates a raw tree. Returns [tree, errors].
	 *
	 * @param mixed    $elements Raw.
	 * @param string[] $reserved Ids already used in the target document.
	 * @return array{0: array<int, array<string,mixed>>, 1: string[], 2: string[]} Tree, errors, warnings (repairs made).
	 */
	public static function normalize_tree( $elements, array $reserved = array() ): array {
		$tree = new Tree( 'normalize' );
		$tree->reserve_ids( $reserved );
		$clean = $tree->process( is_array( $elements ) && ! isset( $elements['type'] ) ? $elements : array( $elements ) );
		// Widgets at the top level must live in a container.
		foreach ( $clean as $i => $node ) {
			$type = Plugin::instance()->elements()->get( $node['type'] );
			if ( $type && ! $type->is_container() ) {
				$clean[ $i ] = array(
					'id'       => Utils::generate_id(),
					'type'     => 'container',
					'settings' => array(),
					'children' => array( $node ),
				);
			}
		}
		return array( $clean, $tree->errors, $tree->warnings );
	}

	public static function errors_to_wp_error( string $message, array $errors ): WP_Error {
		return new WP_Error( 'invalid', $message, array( 'details' => $errors ) );
	}

	/**
	 * Finds a node and its parent list position in a tree (by reference).
	 *
	 * @param array<int, array<string,mixed>> $tree Tree.
	 * @return array{list: array<int, array<string,mixed>>, index: int, parent: string|null}|null
	 */
	public static function &locate( array &$tree, string $id, ?string $parent = null ) {
		$null = null;
		foreach ( $tree as $i => &$node ) {
			if ( ( $node['id'] ?? '' ) === $id ) {
				$found = array(
					'list'   => &$tree,
					'index'  => $i,
					'parent' => $parent,
				);
				return $found;
			}
			if ( ! empty( $node['children'] ) ) {
				$res = &self::locate( $node['children'], $id, (string) $node['id'] );
				if ( null !== $res ) {
					return $res;
				}
			}
		}
		return $null;
	}

	/**
	 * Deep-merges settings: null deletes a key; nested group objects merge one level deep.
	 *
	 * @param array<string,mixed> $current Current.
	 * @param array<string,mixed> $patch   Patch.
	 * @return array<string,mixed>
	 */
	public static function merge_settings( array $current, array $patch ): array {
		foreach ( $patch as $key => $value ) {
			if ( null === $value ) {
				unset( $current[ $key ] );
			} elseif ( is_array( $value ) && isset( $current[ $key ] ) && is_array( $current[ $key ] ) && ! isset( $value[0] ) && ! isset( $current[ $key ][0] ) && self::is_group_like( $value ) ) {
				$merged = array_merge( $current[ $key ], $value );
				foreach ( $value as $k => $v ) {
					if ( null === $v ) {
						unset( $merged[ $k ] );
					}
				}
				$current[ $key ] = $merged;
			} else {
				$current[ $key ] = $value;
			}
		}
		return $current;
	}

	/**
	 * Group values (typography, background…) merge; value objects (size/unit, url) replace.
	 */
	private static function is_group_like( array $value ): bool {
		$keys = array_keys( $value );
		$atomic = array( array( 'size', 'unit' ), array( 'url' ), array( 'id', 'url' ), array( 'library', 'value' ) );
		foreach ( $atomic as $shape ) {
			if ( ! array_diff( $keys, array_merge( $shape, array( 'alt', 'external', 'nofollow', 'attributes', 'linked', 'top', 'right', 'bottom', 'left', 'unit', 'size' ) ) ) ) {
				return false;
			}
		}
		return true;
	}
}
