<?php
/**
 * REST API bootstrap (namespace uncoder/v1).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every controller.
 */
final class Rest {

	public const NS = 'uncoder/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'unwrap_body' ), 0, 3 );
	}

	/**
	 * Opens a request body the editor wrapped in base64 ({"uncoder_body": "…"} with the X-Uncoder-Wrapped header),
	 * which it does when a host firewall refused the plain JSON (src/editor/lib/api.ts). Runs before the route and
	 * its permission check, so every endpoint sees the JSON it was sent. Any route: the editor also writes WordPress
	 * core routes. Only for a signed-in user who can edit (REST authentication, the wp_rest nonce included, has run
	 * by now), so an anonymous client cannot use the wrapper to hide a payload from the host's firewall.
	 *
	 * @param mixed            $result  Response to short-circuit with, or null.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function unwrap_body( $result, $server, $request ) {
		if ( null !== $result || ! $request instanceof \WP_REST_Request || '1' !== $request->get_header( 'X-Uncoder-Wrapped' ) ) {
			return $result;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $result;
		}
		$params = $request->get_json_params();
		$body   = is_array( $params ) && 1 === count( $params ) && is_string( $params['uncoder_body'] ?? null ) ? base64_decode( $params['uncoder_body'], true ) : false;
		if ( is_string( $body ) && null !== json_decode( $body ) ) {
			$request->set_body( $body );
		}
		return $result;
	}

	public function routes(): void {
		$controllers = array(
			new Documents_Controller(),
			new Notes_Controller(),
			new Prefs_Controller(),
			new Render_Controller(),
			new Kit_Controller(),
			new Lookup_Controller(),
			new Templates_Controller(),
			new Submissions_Controller(),
			new Settings_Controller(),
			new Overview_Controller(),
		);
		/**
		 * Filters REST controllers (each must have register_routes()).
		 *
		 * @param object[] $controllers Controllers.
		 */
		foreach ( apply_filters( 'uncoder_wb/rest/controllers', $controllers ) as $controller ) {
			$controller->register_routes();
		}
	}
}
