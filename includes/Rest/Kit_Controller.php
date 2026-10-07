<?php
/**
 * Design System endpoints.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use Uncoder\Builder\Plugin;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /kit, GET /kit/snapshots, POST /kit/restore.
 */
final class Kit_Controller {

	public function register_routes(): void {
		$can = static fn() => current_user_can( 'edit_theme_options' );
		register_rest_route(
			Rest::NS,
			'/kit',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $can,
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/kit/snapshots',
			array(
				'methods'             => 'GET',
				'callback'            => static fn() => new WP_REST_Response( Plugin::instance()->kit()->snapshots() ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			Rest::NS,
			'/kit/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'restore' ),
				'permission_callback' => $can,
			)
		);
	}

	public function get(): WP_REST_Response {
		return new WP_REST_Response( Plugin::instance()->kit()->export() );
	}

	/**
	 * Body: { kit: {...full or partial...}, replace?: bool }
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {
		$body   = $request->get_json_params();
		$kit    = Plugin::instance()->kit();
		$data   = is_array( $body['kit'] ?? null ) ? $body['kit'] : array();
		$errors = array();
		$clean  = $kit->sanitize( $data, 'sanitize', $errors );
		if ( ! empty( $body['replace'] ) ) {
			$kit->snapshot( __( 'Before editor save', 'uncoder' ) );
			$kit->save( array_merge( $kit->all(), $clean ) );
		} else {
			$kit->update( $clean, __( 'Before editor save', 'uncoder' ) );
		}
		return new WP_REST_Response(
			array(
				'kit'      => $kit->export(),
				'warnings' => $errors,
			)
		);
	}

	public function restore( WP_REST_Request $request ): WP_REST_Response {
		$id = sanitize_text_field( (string) ( $request->get_json_params()['id'] ?? '' ) );
		$ok = Plugin::instance()->kit()->restore( $id );
		return new WP_REST_Response(
			array(
				'restored' => $ok,
				'kit'      => Plugin::instance()->kit()->export(),
			)
		);
	}
}
