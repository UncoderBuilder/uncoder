<?php
/**
 * Premium section packs (Pro and Agency licences, feature "sections"): every section of the starter sites, from
 * the content server (cloud.uncoderbuilder.com, library/sections), inserted from the editor's Insert → Sections.
 *
 *   GET uncoder/v1/sections                  the catalogue (cached for an hour; ?refresh=1), locked ones marked
 *
 * The catalogue is free to browse (locked sections show what Pro brings); inserting and saving a section come with the
 * Pro module (Site\Section_Insert: GET sections/{id}, POST sections/{id}/save).
 *
 * A section comes with the text styles and fonts it uses. They are added to the Design System as "<starter>-<id>"
 * (so packs never collide and nothing of the site's own changes), uploaded fonts are installed, and images are copied
 * into the media library. With match=1 the section takes the site's own heading and body text styles and fonts
 * instead of its starter's (its other styles still come along).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Licence\Licence;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Section_Library {

	private const CACHE   = 'uncoder_wb_sections';
	private const FEATURE = 'sections';

	public function register(): void {
		if ( Licence::enabled() ) {
			add_action( 'rest_api_init', array( $this, 'routes' ) );
		}
	}

	public static function can_use(): bool {
		return current_user_can( 'edit_posts' ) && 'full' === Role_Manager::access();
	}

	public function routes(): void {
		$can = array( self::class, 'can_use' );
		register_rest_route( Rest::NS, '/sections', array( 'methods' => 'GET', 'callback' => array( $this, 'catalog' ), 'permission_callback' => $can ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public function catalog( WP_REST_Request $request ) {
		$data = $request->get_param( 'refresh' ) ? false : get_transient( self::CACHE );
		if ( ! is_array( $data ) ) {
			$res = wp_remote_get( Licence::content_server() . 'library/sections', array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				return new WP_Error( 'uncoder_sections_offline', __( 'The section library could not be reached. Check your connection and try again.', 'uncoder' ), array( 'status' => 502 ) );
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( ! is_array( $data ) || ! is_array( $data['sections'] ?? null ) ) {
				return new WP_Error( 'uncoder_sections_offline', __( 'The section library sent an answer that could not be read.', 'uncoder' ), array( 'status' => 502 ) );
			}
			set_transient( self::CACHE, $data, HOUR_IN_SECONDS );
		}
		$unlocked = Licence::allows( self::FEATURE );
		$sections = array();
		foreach ( $data['sections'] as $s ) {
			if ( is_array( $s ) && ! empty( $s['id'] ) ) {
				$s['locked'] = 'free' !== ( $s['tier'] ?? 'pro' ) && ! $unlocked;
				$sections[]  = $s;
			}
		}
		return new WP_REST_Response(
			array(
				'sections'   => $sections,
				'categories' => $data['categories'] ?? array(),
				'allowed'    => $unlocked,
				'pricing'    => Licence::PRICING,
				'licence'    => admin_url( 'admin.php?page=uncoder-settings#licence' ),
			)
		);
	}
}
