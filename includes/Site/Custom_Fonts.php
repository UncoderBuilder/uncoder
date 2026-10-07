<?php
/**
 * Custom (self-hosted) fonts: upload WOFF2 / WOFF / TTF / OTF files and use them like Google Fonts.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Option uncoder_wb_fonts: { "Family": { c: category, d: font-display, p: preloaded variation ("600 italic"),
 * faces: [ { weight, style, file, format } ] } }. A variation (weight + style) may have several formats
 * (WOFF2 + WOFF + TTF…): they become one @font-face with fallbacks, best format first.
 * Files live in uploads/uncoder/fonts/custom/ under generated names; the format is read from the file's
 * signature, never from the uploaded name. @font-face rules are printed after the base stylesheet
 * wherever Uncoder loads (front end, editor canvas, Design System screen).
 */
final class Custom_Fonts {

	public const OPTION     = 'uncoder_wb_fonts';
	public const CATEGORIES = array( 'sans-serif', 'serif', 'display', 'handwriting', 'monospace' );
	public const DISPLAY    = array( 'swap', 'fallback', 'optional', 'block', 'auto' );
	/** Best format first in a src list. */
	private const FORMAT_ORDER = array( 'woff2' => 0, 'woff' => 1, 'truetype' => 2, 'opentype' => 3 );
	public const MAX_BYTES     = 10 * MB_IN_BYTES;
	private const SIGNATURES = array(
		'wOF2'             => array( 'woff2', 'woff2' ),
		'wOFF'             => array( 'woff', 'woff' ),
		"\x00\x01\x00\x00" => array( 'ttf', 'truetype' ),
		'true'             => array( 'ttf', 'truetype' ),
		'OTTO'             => array( 'otf', 'opentype' ),
	);

	/** @var array<string, array{c:string, faces:array<int, array<string,string>>}>|null */
	private static ?array $cache = null;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'wp_head', array( $this, 'preload' ), 2 );
	}

	/**
	 * Faces grouped by variation ("400 normal" => faces, best format first).
	 *
	 * @param array<int, array<string,string>> $faces Faces.
	 * @return array<string, array<int, array<string,string>>>
	 */
	private static function variations( array $faces ): array {
		$out = array();
		foreach ( $faces as $face ) {
			$out[ $face['weight'] . ' ' . $face['style'] ][] = $face;
		}
		foreach ( $out as &$list ) {
			usort( $list, static fn( $a, $b ) => ( self::FORMAT_ORDER[ $a['format'] ] ?? 9 ) <=> ( self::FORMAT_ORDER[ $b['format'] ] ?? 9 ) );
		}
		return $out;
	}

	/**
	 * <link rel="preload"> for the variation each family marked (usually the body text weight): the text
	 * renders in the brand font on first paint instead of swapping.
	 */
	public function preload(): void {
		$base = self::dir();
		if ( ! $base || is_admin() ) {
			return;
		}
		foreach ( self::all() as $font ) {
			if ( empty( $font['p'] ) ) {
				continue;
			}
			$face = self::variations( $font['faces'] )[ $font['p'] ][0] ?? null;
			if ( $face ) {
				printf( '<link rel="preload" href="%s" as="font" type="font/%s" crossorigin>' . "\n", esc_url( $base['url'] . '/' . rawurlencode( $face['file'] ) ), esc_attr( 'truetype' === $face['format'] ? 'ttf' : ( 'opentype' === $face['format'] ? 'otf' : $face['format'] ) ) );
			}
		}
	}

	/**
	 * @return array<string, array{c:string, faces:array<int, array<string,string>>}>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = is_array( $stored ) ? $stored : array();
		}
		return self::$cache;
	}

	public static function get( string $family ): ?array {
		return self::all()[ $family ] ?? null;
	}

	/**
	 * Replaces the stored fonts (already validated: see upload() and Site_Kit::import_fonts()).
	 *
	 * @param array<string, array<string,mixed>> $fonts Fonts.
	 */
	public static function save_all( array $fonts ): void {
		ksort( $fonts, SORT_NATURAL | SORT_FLAG_CASE );
		update_option( self::OPTION, $fonts, false );
		self::$cache = $fonts;
	}

	/**
	 * Picker format (like the Google catalog): family => { c, w, custom: true }.
	 *
	 * @return array<string, array{c:string, w:string[], custom:bool}>
	 */
	public static function catalog(): array {
		$out = array();
		foreach ( self::all() as $family => $font ) {
			$weights = array();
			foreach ( $font['faces'] as $face ) {
				// A variable face ("100 900") offers the usual steps inside its range.
				$range = array_map( 'intval', explode( ' ', (string) $face['weight'] ) );
				for ( $w = 100; $w <= 900; $w += 100 ) {
					if ( $w >= $range[0] && $w <= ( $range[1] ?? $range[0] ) ) {
						$weights[] = (string) $w;
					}
				}
			}
			$weights = array_values( array_unique( $weights ) );
			sort( $weights );
			$out[ $family ] = array(
				'c'      => $font['c'],
				'w'      => $weights ? $weights : array( '400' ),
				'custom' => true,
			);
		}
		return $out;
	}

	private static function dir(): ?array {
		$uploads = Utils::uploads();
		return $uploads ? array(
			'dir' => $uploads['dir'] . '/fonts/custom',
			'url' => $uploads['url'] . '/fonts/custom',
		) : null;
	}

	/**
	 * @font-face rules for every uploaded face.
	 */
	public static function css(): string {
		$fonts = self::all();
		$base  = self::dir();
		if ( ! $fonts || ! $base ) {
			return '';
		}
		$css = '';
		foreach ( $fonts as $family => $font ) {
			$name    = str_replace( array( '"', '\\' ), '', (string) $family );
			$display = in_array( $font['d'] ?? '', self::DISPLAY, true ) ? $font['d'] : 'swap';
			foreach ( self::variations( $font['faces'] ) as $list ) {
				$src = array();
				foreach ( $list as $face ) {
					if ( ! isset( self::FORMAT_ORDER[ $face['format'] ?? '' ] ) ) {
						continue;
					}
					$src[] = 'url("' . Utils::css_url( $base['url'] . '/' . rawurlencode( (string) $face['file'] ) ) . '") format("' . $face['format'] . '")';
				}
				if ( ! $src ) {
					continue;
				}
				$css .= '@font-face{font-family:"' . $name . '";src:' . implode( ',', $src ) . ';font-weight:' . preg_replace( '/[^0-9 ]/', '', (string) $list[0]['weight'] ) . ';font-style:' . ( 'italic' === $list[0]['style'] ? 'italic' : 'normal' ) . ';font-display:' . $display . '}';
			}
		}
		return $css;
	}

	/**
	 * Adds the rules to an enqueued stylesheet handle.
	 */
	public static function attach( string $handle ): void {
		$css = self::css();
		if ( '' !== $css ) {
			wp_add_inline_style( $handle, $css );
		}
	}

	/* ------------------------------------------------------------------ REST */

	public function routes(): void {
		$can = static fn() => current_user_can( 'edit_theme_options' );
		register_rest_route(
			Rest::NS,
			'/custom-fonts',
			array(
				'methods'             => 'GET',
				'callback'            => fn() => new WP_REST_Response( self::present() ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			Rest::NS,
			'/custom-fonts/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upload' ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			Rest::NS,
			'/custom-fonts/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update' ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			Rest::NS,
			'/custom-fonts/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save' ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			Rest::NS,
			'/custom-fonts/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	public static function present(): array {
		$base = self::dir();
		$out  = array();
		foreach ( self::all() as $family => $font ) {
			$faces = array();
			foreach ( $font['faces'] as $i => $face ) {
				$path    = $base ? $base['dir'] . '/' . $face['file'] : '';
				$faces[] = array(
					'index'  => $i,
					'file'   => $face['file'],
					'weight' => $face['weight'],
					'style'  => $face['style'],
					'format' => $face['format'],
					'url'    => $base ? $base['url'] . '/' . rawurlencode( $face['file'] ) : '',
					'size'   => $path && file_exists( $path ) ? (int) filesize( $path ) : 0,
				);
			}
			$out[] = array(
				'family'   => (string) $family,
				'category' => $font['c'],
				'display'  => in_array( $font['d'] ?? '', self::DISPLAY, true ) ? $font['d'] : 'swap',
				'preload'  => (string) ( $font['p'] ?? '' ),
				'faces'    => $faces,
			);
		}
		return $out;
	}

	/**
	 * A family name as stored: letters, numbers, spaces, - _ . (at most 60 characters).
	 *
	 * @param mixed $value Raw name.
	 */
	public static function clean_family( $value ): string {
		$family = trim( (string) preg_replace( '/\s+/', ' ', (string) preg_replace( '/[^\p{L}\p{N} \-_.]/u', '', (string) $value ) ) );
		return mb_substr( $family, 0, 60 );
	}

	/**
	 * The real format of a font file from its signature: [ extension, CSS format ], or null when it is not a font.
	 *
	 * @return array{0:string, 1:string}|null
	 */
	public static function sniff( string $path ): ?array {
		$handle = is_readable( $path ) ? fopen( $path, 'rb' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$magic  = $handle ? (string) fread( $handle, 4 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( $handle ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		return self::SIGNATURES[ $magic ] ?? null;
	}

	/**
	 * multipart/form-data: family, category, weight ("400" or a variable range "100 900"), style, file.
	 */
	public function upload( WP_REST_Request $request ) {
		$family = self::clean_family( $request->get_param( 'family' ) );
		if ( '' === $family ) {
			return new WP_Error( 'uncoder_invalid', __( 'Enter a font family name (letters, numbers, spaces, - _ .).', 'uncoder' ), array( 'status' => 400 ) );
		}
		$weight = trim( (string) $request->get_param( 'weight' ) );
		if ( ! preg_match( '/^([1-9]00)(?: ([1-9]00))?$/', $weight, $m ) || ( isset( $m[2] ) && (int) $m[2] <= (int) $m[1] ) ) {
			return new WP_Error( 'uncoder_invalid', __( 'Weight must be 100–900, or a range like "100 900" for a variable font.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$style    = 'italic' === $request->get_param( 'style' ) ? 'italic' : 'normal';
		$category = in_array( $request->get_param( 'category' ), self::CATEGORIES, true ) ? (string) $request->get_param( 'category' ) : 'sans-serif';

		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return new WP_Error( 'uncoder_invalid', __( 'Choose a font file to upload.', 'uncoder' ), array( 'status' => 400 ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error( 'uncoder_invalid', __( 'Font files can be 10 MB at most.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$sniffed = self::sniff( (string) $file['tmp_name'] );
		if ( null === $sniffed ) {
			return new WP_Error( 'uncoder_invalid', __( 'This is not a font file. Upload WOFF2 (best), WOFF, TTF or OTF.', 'uncoder' ), array( 'status' => 400 ) );
		}
		list( $ext, $format ) = $sniffed;

		$base = self::dir();
		if ( ! $base || ! wp_mkdir_p( $base['dir'] ) ) {
			return new WP_Error( 'uncoder_fs', __( 'The uploads folder is not writable.', 'uncoder' ), array( 'status' => 500 ) );
		}
		$name = trim( sanitize_key( remove_accents( str_replace( ' ', '-', $family ) ) ), '-_' ) . '-' . str_replace( ' ', '-', $weight ) . ( 'italic' === $style ? '-italic' : '' ) . '-' . substr( (string) md5_file( (string) $file['tmp_name'] ), 0, 8 ) . '.' . $ext;
		if ( ! move_uploaded_file( (string) $file['tmp_name'], $base['dir'] . '/' . $name ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
			return new WP_Error( 'uncoder_fs', __( 'Could not store the font file.', 'uncoder' ), array( 'status' => 500 ) );
		}

		$fonts = self::all();
		$font  = $fonts[ $family ] ?? array(
			'c'     => $category,
			'faces' => array(),
		);
		// Replace the same variation in the same format; other formats of it stay as fallbacks.
		// replace=0 only adds the file (the font dialog then commits the final set with save()).
		if ( '0' !== (string) $request->get_param( 'replace' ) ) {
			foreach ( $font['faces'] as $i => $face ) {
				if ( $face['weight'] === $weight && $face['style'] === $style && $face['format'] === $format ) {
					self::remove_file( $face['file'] );
					unset( $font['faces'][ $i ] );
				}
			}
		}
		$font['faces'][] = array(
			'weight' => $weight,
			'style'  => $style,
			'file'   => $name,
			'format' => $format,
		);
		$font['faces'] = array_values( $font['faces'] );
		usort( $font['faces'], static fn( $a, $b ) => ( (int) $a['weight'] <=> (int) $b['weight'] ) ?: ( ( ( 'italic' === $a['style'] ) <=> ( 'italic' === $b['style'] ) ) ?: ( ( self::FORMAT_ORDER[ $a['format'] ] ?? 9 ) <=> ( self::FORMAT_ORDER[ $b['format'] ] ?? 9 ) ) ) );
		$fonts[ $family ] = $font;
		self::save_all( $fonts );
		self::changed();
		return new WP_REST_Response( self::present() );
	}

	/**
	 * JSON { family, category?, display?, preload? ("600 italic" or ""), rename? }.
	 */
	public function update( WP_REST_Request $request ) {
		$family = (string) $request->get_param( 'family' );
		$fonts  = self::all();
		if ( ! isset( $fonts[ $family ] ) ) {
			return new WP_Error( 'uncoder_not_found', __( 'Font not found.', 'uncoder' ), array( 'status' => 404 ) );
		}
		if ( in_array( $request->get_param( 'category' ), self::CATEGORIES, true ) ) {
			$fonts[ $family ]['c'] = (string) $request->get_param( 'category' );
		}
		if ( in_array( $request->get_param( 'display' ), self::DISPLAY, true ) ) {
			$fonts[ $family ]['d'] = (string) $request->get_param( 'display' );
		}
		if ( null !== $request->get_param( 'preload' ) ) {
			$preload                 = (string) $request->get_param( 'preload' );
			$fonts[ $family ]['p'] = isset( self::variations( $fonts[ $family ]['faces'] )[ $preload ] ) ? $preload : '';
		}
		$rename = null !== $request->get_param( 'rename' ) ? self::clean_family( $request->get_param( 'rename' ) ) : '';
		if ( '' !== $rename && $rename !== $family ) {
			if ( isset( $fonts[ $rename ] ) ) {
				return new WP_Error( 'uncoder_invalid', __( 'A custom font with that name already exists.', 'uncoder' ), array( 'status' => 400 ) );
			}
			// Files keep their names; only the family (what pickers and CSS use) changes.
			$fonts[ $rename ] = $fonts[ $family ];
			unset( $fonts[ $family ] );
		}
		self::save_all( $fonts );
		self::changed();
		return new WP_REST_Response( self::present() );
	}

	/**
	 * JSON { family, name, category?, display?, preload?, faces: [{ file, weight, style }] } — the font
	 * dialog's final state. Files not listed are deleted; listed files must already belong to the font
	 * (new ones are uploaded first with replace=0). Renames when name differs from family.
	 */
	public function save( WP_REST_Request $request ) {
		$family = (string) $request->get_param( 'family' );
		$fonts  = self::all();
		if ( ! isset( $fonts[ $family ] ) ) {
			return new WP_Error( 'uncoder_not_found', __( 'Font not found.', 'uncoder' ), array( 'status' => 404 ) );
		}
		$name = self::clean_family( $request->get_param( 'name' ) ?? $family );
		if ( '' === $name ) {
			return new WP_Error( 'uncoder_invalid', __( 'Enter a font name (letters, numbers, spaces, - _ .).', 'uncoder' ), array( 'status' => 400 ) );
		}
		if ( $name !== $family && isset( $fonts[ $name ] ) ) {
			return new WP_Error( 'uncoder_invalid', __( 'A custom font with that name already exists.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$font    = $fonts[ $family ];
		$by_file = array();
		foreach ( $font['faces'] as $face ) {
			$by_file[ $face['file'] ] = $face;
		}
		$faces = array();
		$seen  = array();
		foreach ( (array) $request->get_param( 'faces' ) as $row ) {
			$file = is_array( $row ) ? (string) ( $row['file'] ?? '' ) : '';
			if ( ! isset( $by_file[ $file ] ) ) {
				continue;
			}
			$weight = trim( (string) ( $row['weight'] ?? '' ) );
			if ( ! preg_match( '/^([1-9]00)(?: ([1-9]00))?$/', $weight, $m ) || ( isset( $m[2] ) && (int) $m[2] <= (int) $m[1] ) ) {
				return new WP_Error( 'uncoder_invalid', __( 'Weight must be 100–900, or a range like "100 900" for a variable font.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$style  = 'italic' === ( $row['style'] ?? '' ) ? 'italic' : 'normal';
			$format = $by_file[ $file ]['format'];
			$key    = $weight . ' ' . $style . ' ' . $format;
			if ( isset( $seen[ $key ] ) ) {
				/* translators: 1: file format, 2: weight and style, e.g. "700 italic". */
				return new WP_Error( 'uncoder_invalid', sprintf( __( 'Two %1$s files for %2$s: keep one.', 'uncoder' ), strtoupper( $format ), $weight . ' ' . $style ), array( 'status' => 400 ) );
			}
			$seen[ $key ] = true;
			$faces[]      = array(
				'weight' => $weight,
				'style'  => $style,
				'file'   => $file,
				'format' => $format,
			);
			unset( $by_file[ $file ] );
		}
		if ( ! $faces ) {
			return new WP_Error( 'uncoder_invalid', __( 'A font needs at least one file.', 'uncoder' ), array( 'status' => 400 ) );
		}
		foreach ( $by_file as $face ) {
			self::remove_file( $face['file'] );
		}
		usort( $faces, static fn( $a, $b ) => ( (int) $a['weight'] <=> (int) $b['weight'] ) ?: ( ( ( 'italic' === $a['style'] ) <=> ( 'italic' === $b['style'] ) ) ?: ( ( self::FORMAT_ORDER[ $a['format'] ] ?? 9 ) <=> ( self::FORMAT_ORDER[ $b['format'] ] ?? 9 ) ) ) );
		$font['faces'] = $faces;
		if ( in_array( $request->get_param( 'category' ), self::CATEGORIES, true ) ) {
			$font['c'] = (string) $request->get_param( 'category' );
		}
		if ( in_array( $request->get_param( 'display' ), self::DISPLAY, true ) ) {
			$font['d'] = (string) $request->get_param( 'display' );
		}
		if ( null !== $request->get_param( 'preload' ) ) {
			$preload   = (string) $request->get_param( 'preload' );
			$font['p'] = isset( self::variations( $faces )[ $preload ] ) ? $preload : '';
		}
		unset( $fonts[ $family ] );
		$fonts[ $name ] = $font;
		self::save_all( $fonts );
		self::changed();
		return new WP_REST_Response( self::present() );
	}

	/**
	 * JSON { family, index? } — one face, or the whole family without index.
	 */
	public function delete( WP_REST_Request $request ) {
		$family = (string) $request->get_param( 'family' );
		$fonts  = self::all();
		if ( ! isset( $fonts[ $family ] ) ) {
			return new WP_Error( 'uncoder_not_found', __( 'Font not found.', 'uncoder' ), array( 'status' => 404 ) );
		}
		$index = $request->get_param( 'index' );
		if ( null === $index ) {
			foreach ( $fonts[ $family ]['faces'] as $face ) {
				self::remove_file( $face['file'] );
			}
			unset( $fonts[ $family ] );
		} else {
			$index = (int) $index;
			if ( ! isset( $fonts[ $family ]['faces'][ $index ] ) ) {
				return new WP_Error( 'uncoder_not_found', __( 'Font file not found.', 'uncoder' ), array( 'status' => 404 ) );
			}
			self::remove_file( $fonts[ $family ]['faces'][ $index ]['file'] );
			array_splice( $fonts[ $family ]['faces'], $index, 1 );
			if ( ! $fonts[ $family ]['faces'] ) {
				unset( $fonts[ $family ] );
			}
		}
		self::save_all( $fonts );
		self::changed();
		return new WP_REST_Response( self::present() );
	}

	private static function remove_file( string $file ): void {
		$base = self::dir();
		// Stored names are generated ([a-z0-9-] + extension); refuse anything else.
		if ( $base && preg_match( '/^[a-z0-9_\-]+\.(woff2|woff|ttf|otf)$/', $file ) ) {
			wp_delete_file( $base['dir'] . '/' . $file );
		}
	}

	/**
	 * Font stacks and categories are baked into generated CSS: rebuild it.
	 */
	private static function changed(): void {
		Plugin::instance()->kit()->write_css();
		do_action( 'uncoder_wb/custom_fonts/changed' );
	}
}
