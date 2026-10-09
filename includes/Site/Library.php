<?php
/**
 * The starter-site library (Uncoder → Library → Starter sites): the catalogue from uncoderbuilder.com, and one-click import.
 * Free starters download without a key; Pro starters need a licence with the "library" feature (Licence\Licence).
 * Importing downloads the kit, unpacks it like an uploaded site kit (Site_Kit::stage_zip()) and hands the preview to
 * the usual import step, so the owner chooses what to bring in before anything changes.
 *
 *   GET  uncoder/v1/library               the catalogue, each starter marked locked or not
 *   POST uncoder/v1/library/stage { slug } the kit, downloaded and unpacked: the Site Kit preview ({ token, … })
 *
 * Part of the paid plans' launch (Licence::enabled()): until then neither the screen nor these routes exist.
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

final class Library {

	private const CACHE = 'uncoder_wb_library';

	public function register(): void {
		if ( Licence::enabled() ) {
			add_action( 'rest_api_init', array( $this, 'routes' ) );
		}
	}

	public function routes(): void {
		$admin = array( White_Label::class, 'can_manage' );
		register_rest_route( Rest::NS, '/library', array( 'methods' => 'GET', 'callback' => array( $this, 'catalog' ), 'permission_callback' => $admin ) );
		register_rest_route(
			Rest::NS,
			'/library/stage',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'stage' ),
				'permission_callback' => $admin,
				'args'                => array( 'slug' => array( 'type' => 'string', 'required' => true ) ),
			)
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public function catalog( WP_REST_Request $request ) {
		$data = $request->get_param( 'refresh' ) ? false : get_transient( self::CACHE );
		if ( ! is_array( $data ) ) {
			$res = wp_remote_get( Licence::server() . 'library/starters', array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				return new WP_Error( 'uncoder_library_offline', __( 'The starter-site library could not be reached. Check your connection and try again.', 'uncoder' ), array( 'status' => 502 ) );
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( ! is_array( $data ) || ! is_array( $data['starters'] ?? null ) ) {
				return new WP_Error( 'uncoder_library_offline', __( 'The starter-site library sent an answer Uncoder could not read.', 'uncoder' ), array( 'status' => 502 ) );
			}
			set_transient( self::CACHE, $data, HOUR_IN_SECONDS );
		}
		$unlocked = Licence::allows( 'library' );
		$starters = array();
		foreach ( $data['starters'] as $s ) {
			if ( ! is_array( $s ) || empty( $s['slug'] ) ) {
				continue;
			}
			$s['locked'] = 'free' !== ( $s['tier'] ?? 'pro' ) && ! $unlocked;
			$starters[]  = $s;
		}
		return new WP_REST_Response(
			array(
				'starters' => $starters,
				'updated'  => $data['updated'] ?? null,
				'plan'     => Licence::plan(),
				'pricing'  => Licence::PRICING,
				'licence'  => admin_url( 'admin.php?page=uncoder-settings#licence' ),
			)
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public function stage( WP_REST_Request $request ) {
		$slug = sanitize_key( (string) $request->get_param( 'slug' ) );
		$key  = (string) ( Licence::data()['key'] ?? '' );
		$res  = wp_remote_post(
			Licence::server() . 'library/download',
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
				'body'    => (string) wp_json_encode(
					array(
						'slug'     => $slug,
						'site_url' => home_url( '/' ),
						'key'      => $key,
						'env'      => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
						'version'  => defined( 'UNCODER_WB_VERSION' ) ? UNCODER_WB_VERSION : '',
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'uncoder_library_offline', __( 'The starter-site library could not be reached. Check your connection and try again.', 'uncoder' ), array( 'status' => 502 ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || empty( $body['url'] ) ) {
			$code = is_array( $body ) ? (string) ( $body['code'] ?? '' ) : '';
			$map  = array(
				'needs_licence' => __( 'This starter site comes with Pro and Agency. Add a licence under Settings → Licence.', 'uncoder' ),
				'expired'       => __( 'Your licence has expired. Renew it to import Pro starter sites; free ones still work.', 'uncoder' ),
				'disabled'      => __( 'Your licence is no longer active.', 'uncoder' ),
				'not_activated' => __( 'This site is not activated on your licence. Activate it again under Settings → Licence.', 'uncoder' ),
			);
			$msg  = $map[ $code ] ?? ( is_array( $body ) && ! empty( $body['message'] ) ? (string) $body['message'] : __( 'The starter site could not be downloaded. Try again in a moment.', 'uncoder' ) );
			return new WP_Error( 'uncoder_library_' . ( '' !== $code ? $code : 'failed' ), $msg, array( 'status' => 403 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = download_url( (string) $body['url'], 300 );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'uncoder_library_download', __( 'The download stopped before the end. Try again.', 'uncoder' ), array( 'status' => 502 ) );
		}
		if ( ! empty( $body['sha256'] ) && ! hash_equals( (string) $body['sha256'], (string) hash_file( 'sha256', $tmp ) ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'uncoder_library_download', __( 'The downloaded starter site was damaged on the way. Try again.', 'uncoder' ), array( 'status' => 502 ) );
		}
		$staged = ( new Site_Kit() )->stage_zip( $tmp );
		wp_delete_file( $tmp );
		return $staged;
	}
}
