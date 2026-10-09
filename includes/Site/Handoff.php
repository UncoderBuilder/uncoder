<?php
/**
 * Client handoff (Agency licence): once a site is handed over, everyone except the agency's builders, the
 * client's administrators included, edits content only — texts, images and links of existing designs
 * (Role_Manager's "content" level, enforced on REST saves and MCP writes). The design screens (Theme Builder,
 * Design System, Settings, AI & MCP) are hidden from them and the routes that change the design refuse them.
 *
 * A guard rail against accidental design changes, not a security boundary: administrators can still manage
 * plugins. Turning handoff on needs the "handoff" feature; once on it stays on when the licence ends, and the
 * builders can always turn it off.
 *
 *   GET/POST uncoder/v1/handoff
 *
 * Option uncoder_wb_handoff: on, builders (user ids with full access), contact (shown to the client), since.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Licence\Licence;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Handoff {

	public const OPTION = 'uncoder_wb_handoff';

	/**
	 * Routes (under uncoder/v1) that change the design, the settings or the site's structure: refused to handed-over
	 * users for anything but GET. Their texts, images and links go through documents/*, which Role_Manager limits to
	 * content changes.
	 */
	private const DESIGN_ROUTES = array( 'kit', 'templates', 'settings', 'snippets', 'custom-fonts', 'adobe-fonts', 'icon-sets', 'transfer', 'site-kit', 'elementor-import', 'starters', 'library', 'find-replace', 'safe-mode', 'rollback', 'mcp-admin', 'overview', 'cloud', 'reports' );

	/** @var array<string,mixed>|null */
	private static $settings = null;

	public function register(): void {
		if ( Licence::enabled() ) {
			add_action( 'rest_api_init', array( $this, 'routes' ) );
		}
		if ( self::on() ) {
			add_filter( 'rest_pre_dispatch', array( self::class, 'guard' ), 5, 3 );
		}
	}

	/** @return array<string,mixed> */
	public static function settings(): array {
		if ( null === self::$settings ) {
			$s              = get_option( self::OPTION, array() );
			self::$settings = is_array( $s ) ? $s : array();
		}
		return self::$settings;
	}

	public static function on(): bool {
		return ! empty( self::settings()['on'] );
	}

	/** @return int[] */
	public static function builders(): array {
		return array_values( array_filter( array_map( 'intval', (array) ( self::settings()['builders'] ?? array() ) ) ) );
	}

	/**
	 * Whether the user (default: the current one) edits content only because the site was handed over.
	 * A builder never is; if no builder is an administrator any more, administrators are not restricted either
	 * (someone must be able to turn handoff off).
	 */
	public static function restricts( ?\WP_User $user = null ): bool {
		if ( ! self::on() ) {
			return false;
		}
		$user = $user ?? wp_get_current_user();
		if ( ! $user || ! $user->exists() || in_array( (int) $user->ID, self::builders(), true ) ) {
			return false;
		}
		if ( user_can( $user, 'manage_options' ) ) {
			foreach ( self::builders() as $id ) {
				if ( user_can( $id, 'manage_options' ) ) {
					return true;
				}
			}
			return false;
		}
		return true;
	}

	/**
	 * What the admin and the editor tell a handed-over user, or null.
	 *
	 * @return array<string,string>|null
	 */
	public static function notice(): ?array {
		if ( ! self::restricts() ) {
			return null;
		}
		return array(
			'by'      => White_Label::active() ? White_Label::name() : '',
			'contact' => (string) ( self::settings()['contact'] ?? '' ),
		);
	}

	/**
	 * Refuses design-changing requests from handed-over users (rest_pre_dispatch).
	 *
	 * @param mixed           $result  Response so far.
	 * @param \WP_REST_Server $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function guard( $result, $server, $request ) {
		if ( null !== $result || 'GET' === $request->get_method() || 'HEAD' === $request->get_method() ) {
			return $result;
		}
		$route = (string) $request->get_route();
		if ( 0 !== strpos( $route, '/' . Rest::NS . '/' ) ) {
			return $result;
		}
		$first = strtok( substr( $route, strlen( '/' . Rest::NS . '/' ) ), '/' );
		if ( ! in_array( $first, self::DESIGN_ROUTES, true ) || ! self::restricts() ) {
			return $result;
		}
		return new WP_Error( 'uncoder_handed_over', self::refusal(), array( 'status' => 403 ) );
	}

	private static function refusal(): string {
		$contact = (string) ( self::settings()['contact'] ?? '' );
		return '' !== $contact
			/* translators: %s: who to contact, e.g. an email address. */
			? sprintf( __( 'This site was handed over: you can change texts, images and links. For design changes, contact %s.', 'uncoder' ), $contact )
			: __( 'This site was handed over: you can change texts, images and links. Design changes are made by the people who built it.', 'uncoder' );
	}

	/* ------------------------------------------------------------------ REST */

	/** Builders manage handoff (and, with "only me" white-label, only its owner). */
	public static function can_manage(): bool {
		return White_Label::can_manage();
	}

	public function routes(): void {
		register_rest_route(
			Rest::NS,
			'/handoff',
			array(
				array( 'methods' => 'GET', 'callback' => array( $this, 'get' ), 'permission_callback' => array( self::class, 'can_manage' ) ),
				array( 'methods' => 'POST', 'callback' => array( $this, 'save' ), 'permission_callback' => array( self::class, 'can_manage' ) ),
			)
		);
	}

	public function get(): WP_REST_Response {
		$s     = self::settings();
		$users = get_users(
			array(
				'capability' => 'edit_posts',
				'orderby'    => 'display_name',
				'number'     => 200,
				'fields'     => array( 'ID', 'display_name', 'user_email' ),
			)
		);
		return new WP_REST_Response(
			array(
				'on'       => self::on(),
				'builders' => self::on() ? self::builders() : array( get_current_user_id() ),
				'contact'  => (string) ( $s['contact'] ?? '' ),
				'since'    => (string) ( $s['since'] ?? '' ),
				'me'       => get_current_user_id(),
				'users'    => array_map(
					static function ( $u ) {
						$user = get_userdata( (int) $u->ID );
						return array(
							'id'    => (int) $u->ID,
							'name'  => (string) $u->display_name,
							'email' => (string) $u->user_email,
							'admin' => $user && user_can( $user, 'manage_options' ),
						);
					},
					$users
				),
				'allowed'  => Licence::allows( 'handoff' ),
				'pricing'  => Licence::PRICING,
			)
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public function save( WP_REST_Request $request ) {
		$p  = (array) $request->get_json_params();
		$on = ! empty( $p['on'] );
		// Turning handoff off is always allowed; turning it on or changing it needs the Agency licence.
		if ( ( $on || ! self::on() ) && ! Licence::allows( 'handoff' ) ) {
			return new WP_Error( 'uncoder_needs_agency', __( 'Client handoff comes with the Agency licence.', 'uncoder' ), array( 'status' => 403 ) );
		}
		$builders = array_map( 'intval', (array) ( $p['builders'] ?? array() ) );
		$builders = array_values( array_unique( array_filter( $builders, static fn( int $id ): bool => $id > 0 && user_can( $id, 'edit_posts' ) ) ) );
		// Whoever saves keeps full access: nobody hands a site over and locks themselves out.
		if ( ! in_array( get_current_user_id(), $builders, true ) ) {
			$builders[] = get_current_user_id();
		}
		$s = array(
			'on'       => $on,
			'builders' => $builders,
			'contact'  => mb_substr( sanitize_text_field( (string) ( $p['contact'] ?? '' ) ), 0, 120 ),
			'since'    => $on ? ( self::on() ? (string) ( self::settings()['since'] ?? '' ) : gmdate( 'c' ) ) : '',
		);
		update_option( self::OPTION, $s, true );
		self::$settings = $s;
		return $this->get();
	}
}
