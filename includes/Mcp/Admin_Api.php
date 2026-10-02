<?php
/**
 * REST API behind the "AI & MCP" admin screen (cookie + nonce auth, manage_options).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Install;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * /mcp-admin/status, /keys, /grants, /log, /settings, /snapshots.
 */
final class Admin_Api {

	public function register_routes(): void {
		$admin = static fn() => current_user_can( 'manage_options' );
		$ns    = 'uncoder/v1';
		register_rest_route( $ns, '/mcp-admin/status', array( 'methods' => 'GET', 'callback' => array( $this, 'status' ), 'permission_callback' => $admin ) );
		register_rest_route(
			$ns,
			'/mcp-admin/keys',
			array(
				array( 'methods' => 'GET', 'callback' => static fn() => new WP_REST_Response( Tokens::list_keys() ), 'permission_callback' => $admin ),
				array( 'methods' => 'POST', 'callback' => array( $this, 'create_key' ), 'permission_callback' => static fn() => current_user_can( Install::MCP_CAP ) ),
			)
		);
		register_rest_route( $ns, '/mcp-admin/keys/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( $this, 'revoke_key' ), 'permission_callback' => $admin ) );
		register_rest_route( $ns, '/mcp-admin/grants', array( 'methods' => 'GET', 'callback' => static fn() => new WP_REST_Response( Tokens::list_grants() ), 'permission_callback' => $admin ) );
		register_rest_route( $ns, '/mcp-admin/grants/revoke', array( 'methods' => 'POST', 'callback' => array( $this, 'revoke_grant' ), 'permission_callback' => $admin ) );
		register_rest_route(
			$ns,
			'/mcp-admin/log',
			array(
				array( 'methods' => 'GET', 'callback' => array( $this, 'log' ), 'permission_callback' => $admin ),
				array(
					'methods'             => 'DELETE',
					'callback'            => static function () {
						Audit_Log::clear();
						return new WP_REST_Response( array( 'cleared' => true ) );
					},
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$ns,
			'/mcp-admin/settings',
			array(
				array( 'methods' => 'GET', 'callback' => static fn() => new WP_REST_Response( Settings::all() ), 'permission_callback' => $admin ),
				array( 'methods' => 'POST', 'callback' => static fn( WP_REST_Request $r ) => new WP_REST_Response( Settings::save( (array) $r->get_json_params() ) ), 'permission_callback' => $admin ),
			)
		);
		register_rest_route( $ns, '/mcp-admin/undo', array( 'methods' => 'POST', 'callback' => array( $this, 'undo' ), 'permission_callback' => $admin ) );
	}

	public function status(): WP_REST_Response {
		$roles = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			$roles[] = array(
				'slug'    => $slug,
				'name'    => translate_user_role( $role['name'] ),
				'allowed' => in_array( $slug, (array) Settings::get( 'allowed_roles' ), true ),
			);
		}
		return new WP_REST_Response(
			array(
				'endpoint'   => OAuth::resource(),
				'issuer'     => OAuth::issuer(),
				'metadata'   => OAuth::resource_metadata_url(),
				'protocols'  => Server::PROTOCOLS,
				'tools'      => count( Registry::instance()->all() ),
				'tool_names' => array_keys( Registry::instance()->all() ),
				'stats'      => Audit_Log::stats( 24 ),
				'settings'   => Settings::all(),
				'roles'      => $roles,
				'scopes'     => Tokens::scope_labels(),
				'https'      => is_ssl() || 0 === strpos( home_url(), 'https://' ),
				'app_passwords' => function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available(),
				'abilities'  => function_exists( 'wp_register_ability' ),
				'bridge'     => 'npx -y @uncoder/mcp --url ' . OAuth::resource() . ' --key <API_KEY>',
			)
		);
	}

	public function create_key( WP_REST_Request $request ): WP_REST_Response {
		$body   = (array) $request->get_json_params();
		// A connection link is a key made to be pasted as one URL (?token=…); only links are accepted in URLs.
		$link   = ! empty( $body['link'] );
		$name   = sanitize_text_field( (string) ( $body['name'] ?? ( $link ? 'Connection link' : 'API key' ) ) );
		$scopes = Tokens::clean_scopes( (array) ( $body['scopes'] ?? Tokens::DEFAULT_SCOPES ) );
		if ( ! current_user_can( 'manage_options' ) ) {
			$scopes = array_values( array_diff( $scopes, array( 'site' ) ) );
		}
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			$scopes = array_values( array_diff( $scopes, array( 'design' ) ) );
		}
		if ( ! in_array( 'read', $scopes, true ) ) {
			$scopes[] = 'read';
		}
		$days = min( 3650, absint( $body['expires_days'] ?? 90 ) ); // 0 = never; at most 10 years (fits a DATETIME).
		list( $secret, $id ) = Tokens::create(
			array(
				'type'    => $link ? 'link' : 'api_key',
				'user_id' => get_current_user_id(),
				'name'    => '' !== $name ? $name : ( $link ? 'Connection link' : 'API key' ),
				'scopes'  => $scopes,
				'ttl'     => $days > 0 ? $days * DAY_IN_SECONDS : 0,
			)
		);
		if ( $id <= 0 ) {
			return new WP_REST_Response( array( 'message' => __( 'The API key could not be saved. Check that the plugin tables exist (deactivate and activate Uncoder).', 'uncoder' ) ), 500 );
		}
		Audit_Log::add(
			array(
				'method'  => 'admin/key',
				'status'  => 'ok',
				'summary' => ( $link ? 'Connection link created: ' : 'API key created: ' ) . $name,
			)
		);
		return new WP_REST_Response(
			array(
				'id'     => $id,
				'secret' => $secret,
				'url'    => $link ? add_query_arg( 'token', $secret, rest_url( 'uncoder/v1/mcp' ) ) : '',
				'note'   => $link ? 'Copy this link now: it is shown only once.' : 'Copy this key now: it is shown only once.',
			),
			201
		);
	}

	public function revoke_key( WP_REST_Request $request ): WP_REST_Response {
		Tokens::revoke( (int) $request['id'] );
		return new WP_REST_Response( array( 'revoked' => true ) );
	}

	public function revoke_grant( WP_REST_Request $request ): WP_REST_Response {
		$body = (array) $request->get_json_params();
		Tokens::revoke_client( sanitize_text_field( (string) ( $body['client_id'] ?? '' ) ), absint( $body['user_id'] ?? 0 ) );
		return new WP_REST_Response( array( 'revoked' => true ) );
	}

	public function log( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			Audit_Log::query(
				array(
					'limit'    => $request->get_param( 'limit' ),
					'offset'   => $request->get_param( 'offset' ),
					'tool'     => sanitize_key( (string) $request->get_param( 'tool' ) ),
					'status'   => sanitize_key( (string) $request->get_param( 'status' ) ),
					'since_id' => $request->get_param( 'since_id' ),
				)
			)
		);
	}

	/**
	 * Restores a snapshot taken before an AI change (from the activity log "Undo" link).
	 */
	public function undo( WP_REST_Request $request ): WP_REST_Response {
		$body     = (array) $request->get_json_params();
		$snapshot = sanitize_text_field( (string) ( $body['snapshot'] ?? '' ) );
		$result   = Snapshots::restore( $snapshot );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		Audit_Log::add(
			array(
				'method'  => 'admin/undo',
				'status'  => 'ok',
				'summary' => 'Restored snapshot ' . $snapshot,
			)
		);
		return new WP_REST_Response( $result );
	}
}
