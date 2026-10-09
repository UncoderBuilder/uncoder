<?php
/**
 * Premium section packs (Pro and Agency licences, feature "sections"): every section of the starter sites, from
 * uncoderbuilder.com (Uncoder Cloud, library/sections), inserted from the editor's Insert → Sections.
 *
 *   GET uncoder/v1/sections                  the catalogue (cached for an hour; ?refresh=1), locked ones marked
 *   GET uncoder/v1/sections/{id}?match=1     a section, ready to insert
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
	/** Text styles every Design System has: with match=1 a section uses the site's own. */
	private const STANDARD_STYLES = array( 'display', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'lead', 'body', 'small', 'eyebrow', 'button' );
	private const STANDARD_FONTS  = array( 'heading', 'body' );
	private const MEDIA_BUDGET    = 40;

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
		register_rest_route( Rest::NS, '/sections/(?P<id>[a-z0-9\-]+)', array( 'methods' => 'GET', 'callback' => array( $this, 'section' ), 'permission_callback' => $can ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public function catalog( WP_REST_Request $request ) {
		$data = $request->get_param( 'refresh' ) ? false : get_transient( self::CACHE );
		if ( ! is_array( $data ) ) {
			$res = wp_remote_get( Licence::server() . 'library/sections', array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
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

	/** @return WP_REST_Response|WP_Error */
	public function section( WP_REST_Request $request ) {
		$res = wp_remote_post(
			Licence::server() . 'library/section',
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
				'body'    => (string) wp_json_encode(
					array(
						'id'       => (string) $request['id'],
						'site_url' => home_url( '/' ),
						'key'      => (string) ( Licence::data()['key'] ?? '' ),
						'env'      => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
						'version'  => UNCODER_WB_VERSION,
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'uncoder_sections_offline', __( 'The section library could not be reached. Check your connection and try again.', 'uncoder' ), array( 'status' => 502 ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || ! is_array( $body['section'] ?? null ) ) {
			$code = is_array( $body ) ? (string) ( $body['code'] ?? '' ) : '';
			$map  = array(
				'needs_licence' => __( 'Premium sections come with Pro and Agency. Add a licence under Settings → Licence.', 'uncoder' ),
				'expired'       => __( 'Your licence has expired. Renew it to insert premium sections; the ones you inserted stay.', 'uncoder' ),
				'disabled'      => __( 'Your licence is no longer active.', 'uncoder' ),
				'not_activated' => __( 'This site is not activated on your licence. Activate it again under Settings → Licence.', 'uncoder' ),
			);
			return new WP_Error( 'uncoder_sections_' . ( '' !== $code ? $code : 'failed' ), $map[ $code ] ?? __( 'The section could not be loaded. Try again in a moment.', 'uncoder' ), array( 'status' => 403 ) );
		}
		return new WP_REST_Response( self::prepare( $body['section'], (bool) $request->get_param( 'match' ) ) );
	}

	/**
	 * Adds what the section needs to this site and returns its elements and the Design System additions.
	 *
	 * @param array<string,mixed> $section Section from the library.
	 * @return array<string,mixed>
	 */
	private static function prepare( array $section, bool $match ): array {
		$prefix  = sanitize_key( (string) ( $section['starter']['slug'] ?? 'section' ) );
		$label   = sanitize_text_field( (string) ( $section['starter']['title'] ?? ucfirst( $prefix ) ) );
		$kit     = Plugin::instance()->kit();
		$have_t  = array_flip( array_column( (array) $kit->get( 'typography', array() ), 'id' ) );
		$have_f  = array_flip( array_column( (array) $kit->get( 'fonts', array() ), 'id' ) );
		$can_kit = current_user_can( 'edit_theme_options' );

		// Fonts: the site's heading and body with match=1, else the starter's under "<starter>-<id>".
		$font_map  = array();
		$new_fonts = array();
		foreach ( (array) ( $section['fonts'] ?? array() ) as $f ) {
			$id = sanitize_key( (string) ( $f['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}
			if ( $match && in_array( $id, self::STANDARD_FONTS, true ) && isset( $have_f[ $id ] ) ) {
				continue;
			}
			$new              = $prefix . '-' . $id;
			$font_map[ $id ]  = $new;
			if ( ! isset( $have_f[ $new ] ) ) {
				$new_fonts[] = array(
					'id'     => $new,
					'name'   => $label . ' ' . (string) ( $f['name'] ?? $id ),
					'family' => (string) ( $f['family'] ?? '' ),
				);
			}
		}
		$fonts_in = static function ( $value ) use ( &$fonts_in, $font_map ) {
			if ( is_array( $value ) ) {
				return array_map( $fonts_in, $value );
			}
			return is_string( $value ) && $font_map && false !== strpos( $value, 'var(--uncoder-f-' )
				? preg_replace_callback( '/var\(--uncoder-f-([a-z0-9_\-]+)\)/', static fn( $m ) => isset( $font_map[ $m[1] ] ) ? 'var(--uncoder-f-' . $font_map[ $m[1] ] . ')' : $m[0], $value )
				: $value;
		};

		// Text styles: the same rule.
		$style_map  = array();
		$new_styles = array();
		foreach ( (array) ( $section['presets'] ?? array() ) as $t ) {
			$id = sanitize_key( (string) ( $t['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}
			if ( $match && in_array( $id, self::STANDARD_STYLES, true ) && isset( $have_t[ $id ] ) ) {
				continue;
			}
			$new              = $prefix . '-' . $id;
			$style_map[ $id ] = $new;
			if ( ! isset( $have_t[ $new ] ) ) {
				$new_styles[] = array(
					'id'    => $new,
					'name'  => $label . ' · ' . (string) ( $t['name'] ?? $id ),
					'value' => $fonts_in( (array) ( $t['value'] ?? array() ) ),
				);
			}
		}

		$rename = static function ( $node ) use ( &$rename, $style_map, $fonts_in ) {
			if ( ! is_array( $node ) ) {
				return $fonts_in( $node );
			}
			foreach ( $node as $k => $v ) {
				$node[ $k ] = 'preset' === $k && is_string( $v ) && isset( $style_map[ $v ] ) ? $style_map[ $v ] : $rename( $v );
			}
			return $node;
		};
		$elements = $rename( (array) ( $section['elements'] ?? array() ) );

		// Into the Design System (only what is missing; nothing of the site's own changes).
		$added = array( 'typography' => array(), 'fonts' => array() );
		if ( $can_kit && ( $new_styles || $new_fonts ) ) {
			$errors = array();
			$clean  = $kit->sanitize( array_filter( array( 'typography' => $new_styles, 'fonts' => $new_fonts ) ), 'sanitize', $errors );
			if ( $clean ) {
				$kit->update( $clean, __( 'Before inserting a premium section', 'uncoder' ) );
				$added = array( 'typography' => array_values( (array) ( $clean['typography'] ?? array() ) ), 'fonts' => array_values( (array) ( $clean['fonts'] ?? array() ) ) );
			}
		}

		// Uploaded fonts (not on Google Fonts or Fontshare) the site does not have yet.
		$installed = array();
		$wanted    = array_diff_key( (array) ( $section['uploaded'] ?? array() ), Custom_Fonts::all() );
		if ( $wanted && $can_kit && ! empty( $section['fonts_url'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$dir = trailingslashit( get_temp_dir() ) . 'uncoder-section-fonts-' . wp_generate_password( 8, false, false );
			wp_mkdir_p( $dir );
			foreach ( $wanted as $font ) {
				foreach ( (array) ( $font['faces'] ?? array() ) as $face ) {
					$file = basename( (string) ( $face['file'] ?? '' ) );
					if ( ! preg_match( '/^[a-z0-9_\-]+\.(woff2|woff|ttf|otf)$/', $file ) ) {
						continue;
					}
					$tmp = download_url( (string) $section['fonts_url'] . rawurlencode( $file ), 60 );
					if ( ! is_wp_error( $tmp ) ) {
						rename( $tmp, $dir . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
					}
				}
			}
			$installed = Site_Kit::install_fonts( $wanted, $dir );
			foreach ( (array) glob( $dir . '/*' ) as $f ) {
				wp_delete_file( (string) $f );
			}
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}

		[ $elements, $images, $failed ] = ( new Transfer() )->localize( $elements, self::MEDIA_BUDGET );
		return array(
			'elements'  => ( new Tree( 'sanitize' ) )->process( $elements ),
			'kit'       => $added,
			'fonts'     => $installed,
			'fontFaces' => $installed ? Custom_Fonts::css() : '',
			'custom'    => $installed ? Custom_Fonts::catalog() : null,
			'images'    => $images,
			'failed'    => $failed,
		);
	}
}
