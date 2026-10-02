<?php
/**
 * Editor preferences of the current user, and the element manager (widget usage, widgets turned off).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /me/prefs: per-user editor preferences (user meta), so they follow the user to any browser.
 * GET /elements/usage: how many times each widget is used across builder pages and templates (admins).
 * Widgets turned off site-wide live in uncoder_wb_settings['disabled_widgets'] (Settings → Elements): they
 * leave the Insert panel; pages that already use them keep rendering them.
 */
final class Prefs_Controller {

	public const META = 'uncoder_wb_prefs';

	/** Preference => default. */
	public const DEFAULTS = array(
		'favorites'   => array(),
		'autoPanels'  => true,
		'handles'     => true,
		'hints'       => true,
		// Id of the newest admin announcement (assets/data/updates.json) this user has opened.
		'newsSeen'    => '',
	);

	public function register_routes(): void {
		register_rest_route(
			Rest::NS,
			'/me/prefs',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn() => new WP_REST_Response( self::get() ),
					'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/elements/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'usage' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/** @return array<string,mixed> Preferences of the current user, with defaults. */
	public static function get( int $user = 0 ): array {
		$user   = $user ? $user : get_current_user_id();
		$stored = $user ? get_user_meta( $user, self::META, true ) : array();
		return self::sanitize( is_array( $stored ) ? $stored : array(), self::DEFAULTS );
	}

	/**
	 * @param array<string,mixed> $raw  Incoming values.
	 * @param array<string,mixed> $base Values kept for keys $raw does not carry.
	 * @return array<string,mixed>
	 */
	private static function sanitize( array $raw, array $base ): array {
		$out = array();
		foreach ( self::DEFAULTS as $key => $default ) {
			$value = array_key_exists( $key, $raw ) ? $raw[ $key ] : ( $base[ $key ] ?? $default );
			if ( 'favorites' === $key ) {
				$names = array_values( array_unique( array_filter( array_map( static fn( $n ) => is_string( $n ) ? sanitize_key( $n ) : '', (array) $value ) ) ) );
				$out[ $key ] = array_slice( $names, 0, 40 );
			} elseif ( is_string( $default ) ) {
				$out[ $key ] = is_string( $value ) ? substr( sanitize_key( $value ), 0, 64 ) : '';
			} else {
				$out[ $key ] = (bool) $value;
			}
		}
		return $out;
	}

	public function save( WP_REST_Request $request ): WP_REST_Response {
		$body  = (array) $request->get_json_params();
		$prefs = self::sanitize( $body, self::get() );
		update_user_meta( get_current_user_id(), self::META, $prefs );
		return new WP_REST_Response( $prefs );
	}

	/**
	 * Widget use counts: every builder document (pages, posts, templates) in any status but trash.
	 */
	public function usage(): WP_REST_Response {
		$plugin = Plugin::instance();
		$ids    = get_posts(
			array(
				'post_type'      => $plugin->documents()->post_types(),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 2000,
				'fields'         => 'ids',
				'meta_key'       => Utils::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$counts = array();
		$pages  = array();
		foreach ( $ids as $id ) {
			$doc = $plugin->documents()->get( (int) $id );
			if ( ! $doc ) {
				continue;
			}
			$seen = array();
			self::count( $doc->elements(), $counts, $seen );
			foreach ( array_keys( $seen ) as $type ) {
				$pages[ $type ] = ( $pages[ $type ] ?? 0 ) + 1;
			}
		}
		$out = array();
		foreach ( $plugin->elements()->all() as $name => $element ) {
			$out[ $name ] = array(
				'uses'  => (int) ( $counts[ $name ] ?? 0 ),
				'pages' => (int) ( $pages[ $name ] ?? 0 ),
			);
		}
		return new WP_REST_Response(
			array(
				'documents' => count( $ids ),
				'usage'     => $out,
			)
		);
	}

	/**
	 * @param array<int, array<string,mixed>> $nodes  Tree.
	 * @param array<string,int>               $counts Uses per type.
	 * @param array<string,bool>              $seen   Types found in this document.
	 */
	private static function count( array $nodes, array &$counts, array &$seen ): void {
		foreach ( $nodes as $node ) {
			$type = (string) ( $node['type'] ?? '' );
			if ( '' !== $type ) {
				$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
				$seen[ $type ]   = true;
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				self::count( $node['children'], $counts, $seen );
			}
		}
	}

	/** @return string[] Widgets turned off in Settings → Elements. */
	public static function disabled_widgets(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$list   = is_array( $stored ) && is_array( $stored['disabled_widgets'] ?? null ) ? $stored['disabled_widgets'] : array();
		return array_values( array_filter( array_map( 'strval', $list ) ) );
	}

	/**
	 * @param mixed $raw Names.
	 * @return string[] Known widget names (never the container).
	 */
	public static function sanitize_disabled( $raw ): array {
		$known = array_keys( Plugin::instance()->elements()->all() );
		$out   = array();
		foreach ( (array) $raw as $name ) {
			$name = is_string( $name ) ? sanitize_key( $name ) : '';
			if ( '' !== $name && 'container' !== $name && in_array( $name, $known, true ) ) {
				$out[] = $name;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
