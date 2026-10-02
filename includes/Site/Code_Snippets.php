<?php
/**
 * Custom code: HTML / JS / CSS snippets printed in the head, after <body> or before </body>.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Editor\Preview;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Rest;
use Uncoder\Builder\Rest\Templates_Controller;
use Uncoder\Builder\Theme\Conditions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Snippets live in the uncoder_wb_snippets option (not autoloaded). Code is printed as written, so
 * only users with unfiltered_html (administrators on single sites) can read or change snippets; the
 * MCP server has no access to them. Snippets use the Theme Builder display conditions and never run
 * inside the editor canvas.
 */
final class Code_Snippets {

	public const OPTION    = 'uncoder_wb_snippets';
	public const LOCATIONS = array( 'head', 'body_open', 'footer' );

	/** @var array<int, array<string,mixed>>|null */
	private ?array $matched = null;

	public function register(): void {
		add_action( 'wp_head', fn() => $this->print( 'head' ), 999 );
		add_action( 'wp_body_open', fn() => $this->print( 'body_open' ), 1 );
		add_action( 'wp_footer', fn() => $this->print( 'footer' ), 999 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	public static function all(): array {
		$list = get_option( self::OPTION, array() );
		return is_array( $list ) ? array_values( array_filter( $list, 'is_array' ) ) : array();
	}

	/**
	 * @param array<int, array<string,mixed>> $list Snippets.
	 */
	private static function store( array $list ): void {
		usort( $list, static fn( $a, $b ) => ( (int) $a['priority'] <=> (int) $b['priority'] ) ?: strcmp( (string) $a['name'], (string) $b['name'] ) );
		update_option( self::OPTION, array_values( $list ), false );
	}

	private function print( string $location ): void {
		$preview = Plugin::instance()->module( 'preview' );
		if ( is_admin() || is_feed() || ( $preview instanceof Preview && $preview->active() ) ) {
			return;
		}
		if ( null === $this->matched ) {
			$this->matched = array_values(
				array_filter(
					self::all(),
					static fn( $s ) => ! empty( $s['enabled'] ) && '' !== trim( (string) ( $s['code'] ?? '' ) ) && null !== Conditions::match( (array) ( $s['conditions'] ?? array() ) )
				)
			);
		}
		foreach ( $this->matched as $snippet ) {
			if ( $location === $snippet['location'] ) {
				// Stored by a user with unfiltered_html and printed as written, like a theme's header.php.
				// Analytics / marketing code waits for cookie consent when the banner is on.
				echo "\n" . Consent::gate( (string) $snippet['code'], (string) ( $snippet['category'] ?? 'necessary' ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * @param array<string,mixed> $raw Raw snippet from the client.
	 * @param array<string,mixed> $old Existing snippet (update).
	 * @return array<string,mixed>|WP_Error
	 */
	public static function sanitize( array $raw, array $old = array() ) {
		$name = sanitize_text_field( (string) ( $raw['name'] ?? $old['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'uncoder_invalid', __( 'Give the snippet a name.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$location = (string) ( $raw['location'] ?? $old['location'] ?? 'head' );
		if ( ! in_array( $location, self::LOCATIONS, true ) ) {
			return new WP_Error( 'uncoder_invalid', __( 'Unknown location.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$conditions = $old['conditions'] ?? array( array( 'type' => 'include', 'rule' => 'general' ) );
		if ( array_key_exists( 'conditions', $raw ) ) {
			$errors     = array();
			$conditions = Conditions::sanitize( $raw['conditions'], $errors );
			if ( $errors ) {
				return new WP_Error( 'uncoder_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
			}
		}
		$code = array_key_exists( 'code', $raw ) ? (string) $raw['code'] : (string) ( $old['code'] ?? '' );
		if ( strlen( $code ) > 200000 ) {
			return new WP_Error( 'uncoder_invalid', __( 'The code is too long (200 KB at most).', 'uncoder' ), array( 'status' => 400 ) );
		}
		return array(
			'id'         => (string) ( $old['id'] ?? substr( wp_generate_uuid4(), 0, 8 ) ),
			'name'       => $name,
			'location'   => $location,
			'priority'   => max( 1, min( 100, (int) ( $raw['priority'] ?? $old['priority'] ?? 10 ) ) ),
			'enabled'    => (bool) ( $raw['enabled'] ?? $old['enabled'] ?? true ),
			'category'   => in_array( $raw['category'] ?? $old['category'] ?? 'necessary', array( 'necessary', 'analytics', 'marketing' ), true ) ? (string) ( $raw['category'] ?? $old['category'] ?? 'necessary' ) : 'necessary',
			'conditions' => $conditions,
			'code'       => $code,
			'modified'   => gmdate( 'c' ),
			'author'     => get_current_user_id(),
		);
	}

	/**
	 * @param array<string,mixed> $s Snippet.
	 * @return array<string,mixed>
	 */
	public static function present( array $s ): array {
		$conditions = array();
		foreach ( (array) ( $s['conditions'] ?? array() ) as $c ) {
			$labels = Templates_Controller::id_labels( $c );
			if ( $labels ) {
				$c['labels'] = $labels;
			}
			$c['label']   = Templates_Controller::condition_label( $c );
			$conditions[] = $c;
		}
		$author = get_userdata( (int) ( $s['author'] ?? 0 ) );
		return array_merge(
			$s,
			array(
				'conditions' => $conditions,
				'summary'    => Templates_Controller::summary( $conditions ),
				'authorName' => $author ? $author->display_name : '',
			)
		);
	}

	public function routes(): void {
		$can = array( self::class, 'can_manage' );
		register_rest_route(
			Rest::NS,
			'/snippets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn() => new WP_REST_Response( array_map( array( self::class, 'present' ), self::all() ) ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $can,
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/snippets/(?P<id>[a-f0-9]{8})',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $can,
				),
			)
		);
	}

	public function create( WP_REST_Request $request ) {
		$body    = (array) $request->get_json_params();
		$snippet = self::sanitize( $body );
		if ( is_wp_error( $snippet ) ) {
			return $snippet;
		}
		$list   = self::all();
		$list[] = $snippet;
		self::store( $list );
		return new WP_REST_Response( self::present( $snippet ), 201 );
	}

	public function update( WP_REST_Request $request ) {
		$id   = (string) $request['id'];
		$list = self::all();
		foreach ( $list as $i => $old ) {
			if ( ( $old['id'] ?? '' ) === $id ) {
				$snippet = self::sanitize( (array) $request->get_json_params(), $old );
				if ( is_wp_error( $snippet ) ) {
					return $snippet;
				}
				$list[ $i ] = $snippet;
				self::store( $list );
				return new WP_REST_Response( self::present( $snippet ) );
			}
		}
		return new WP_Error( 'uncoder_not_found', __( 'Snippet not found.', 'uncoder' ), array( 'status' => 404 ) );
	}

	public function delete( WP_REST_Request $request ) {
		$id   = (string) $request['id'];
		$list = self::all();
		$left = array_values( array_filter( $list, static fn( $s ) => ( $s['id'] ?? '' ) !== $id ) );
		if ( count( $left ) === count( $list ) ) {
			return new WP_Error( 'uncoder_not_found', __( 'Snippet not found.', 'uncoder' ), array( 'status' => 404 ) );
		}
		self::store( $left );
		return new WP_REST_Response( array( 'deleted' => $id ) );
	}
}
