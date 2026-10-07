<?php
/**
 * Role manager: which roles may use Uncoder, and how much they may change.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Settings_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * uncoder_wb_settings['roles'] = { role: "full" | "content" | "none" } (missing roles have full access,
 * administrators always do). "content" keeps the design fixed: the user can change texts, images and
 * links (Content tab settings) of existing elements, but not add, move, delete, hide or restyle them.
 * Enforced on the server for REST saves and MCP writes; the editor hides what the role cannot do.
 */
final class Role_Manager {

	public const LEVELS = array( 'full', 'content', 'none' );
	private const RANK  = array(
		'none'    => 0,
		'content' => 1,
		'full'    => 2,
	);

	/**
	 * @return array<string,string> role => level (only roles that are restricted).
	 */
	public static function settings(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$map    = is_array( $stored ) && is_array( $stored['roles'] ?? null ) ? $stored['roles'] : array();
		return self::sanitize( $map );
	}

	/**
	 * @param array<string,mixed> $raw Raw map.
	 * @return array<string,string>
	 */
	public static function sanitize( array $raw ): array {
		$roles = wp_roles()->get_names();
		$out   = array();
		foreach ( $raw as $role => $level ) {
			if ( isset( $roles[ $role ] ) && 'administrator' !== $role && in_array( $level, self::LEVELS, true ) && 'full' !== $level ) {
				$out[ (string) $role ] = (string) $level;
			}
		}
		return $out;
	}

	/**
	 * The most permissive level among the user's roles.
	 */
	public static function access( ?\WP_User $user = null ): string {
		$user = $user ?? wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return 'none';
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return 'full';
		}
		$map  = self::settings();
		$best = null;
		foreach ( (array) $user->roles as $role ) {
			$level = $map[ $role ] ?? 'full';
			if ( null === $best || self::RANK[ $level ] > self::RANK[ $best ] ) {
				$best = $level;
			}
		}
		return $best ?? 'full';
	}

	public static function can_use(): bool {
		return 'none' !== self::access();
	}

	public static function content_only(): bool {
		return 'content' === self::access();
	}

	/**
	 * Checks a content-only save: same elements in the same places, only Content-tab settings changed.
	 *
	 * @param array<int, array<string,mixed>> $old Stored (sanitized) tree.
	 * @param mixed                           $new Submitted tree.
	 * @return string|null Problem, or null when the change is allowed.
	 */
	public static function check_content_only( array $old, $new ): ?string {
		$tree  = new Tree( 'sanitize' );
		$clean = $tree->process( is_array( $new ) ? $new : array() );
		$a     = self::flatten( $old );
		$b     = self::flatten( $clean );
		if ( array_keys( $a ) !== array_keys( $b ) ) {
			return __( 'Your role can edit content only: elements cannot be added, removed or moved.', 'uncoder' );
		}
		$elements = Plugin::instance()->elements();
		$suffixes = array_filter( array_map( static fn( $d ) => 'desktop' === $d ? '' : '_' . $d, Breakpoints::devices() ) );
		foreach ( $b as $id => $node ) {
			$before = $a[ $id ];
			if ( $before['type'] !== $node['type'] || $before['parent'] !== $node['parent'] || ! empty( $before['disabled'] ) !== ! empty( $node['disabled'] ) ) {
				return __( 'Your role can edit content only: elements cannot be replaced, moved or hidden.', 'uncoder' );
			}
			$type     = $elements->get( $node['type'] );
			$settings = $node['settings'];
			$previous = $before['settings'];
			foreach ( array_unique( array_merge( array_keys( $settings ), array_keys( $previous ) ) ) as $key ) {
				if ( wp_json_encode( $settings[ $key ] ?? null ) === wp_json_encode( $previous[ $key ] ?? null ) ) {
					continue;
				}
				$base    = preg_replace( '/(' . implode( '|', array_map( 'preg_quote', $suffixes ) ) . ')$/', '', (string) $key );
				$control = $type ? $type->get_control( (string) $base ) : null;
				if ( ! $control || 'content' !== ( $control['tab'] ?? 'content' ) || 0 === strpos( (string) $key, '_' ) ) {
					/* translators: 1: setting label, 2: element name. */
					return sprintf( __( 'Your role can edit content only: “%1$s” of %2$s is a design setting.', 'uncoder' ), $control['label'] ?? $key, $type ? $type->title() : $node['type'] );
				}
			}
		}
		return null;
	}

	/**
	 * @param array<int, array<string,mixed>> $nodes Tree.
	 * @return array<string, array{type:string, parent:string, disabled:bool, settings:array<string,mixed>}> In document order.
	 */
	private static function flatten( array $nodes, string $parent = '' ): array {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) || empty( $node['id'] ) ) {
				continue;
			}
			$out[ (string) $node['id'] ] = array(
				'type'     => (string) ( $node['type'] ?? '' ),
				'parent'   => $parent,
				'disabled' => ! empty( $node['disabled'] ),
				'settings' => is_array( $node['settings'] ?? null ) ? $node['settings'] : array(),
			);
			$out += self::flatten( (array) ( $node['children'] ?? array() ), (string) $node['id'] );
		}
		return $out;
	}
}
