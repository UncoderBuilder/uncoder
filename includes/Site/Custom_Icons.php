<?php
/**
 * Custom icon sets (Design System → Custom icons).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * An IcoMoon or Fontello download (zip), or SVG files, become an icon library next to the bundled ones: the
 * glyphs are turned into inline SVG (the same format as assets/data/icons/*.json), so pages never load an icon
 * font. Stored as uploads/uncoder/icons/{id}.json; the list lives in the option uncoder_wb_icon_sets.
 *
 * Every shape is rebuilt from validated numbers and path data (nothing from the upload is echoed as markup).
 */
final class Custom_Icons {

	public const OPTION   = 'uncoder_wb_icon_sets';
	private const MAX     = 3000;
	private const MAX_ZIP = 20 * MB_IN_BYTES;
	/** Most bytes read out of one zip (all entries together). */
	private const MAX_UNZIPPED = 64 * MB_IN_BYTES;
	private const PATH_RE = '/^[MmZzLlHhVvCcSsQqTtAa0-9eE.,+\-\s]+$/';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$can = static fn() => current_user_can( 'manage_options' );
		register_rest_route(
			Rest::NS,
			'/icon-sets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn() => new WP_REST_Response( self::index() ),
					'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload' ),
					'permission_callback' => $can,
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/icon-sets/(?P<id>custom-[a-z0-9\-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete' ),
				'permission_callback' => $can,
			)
		);
	}

	/** @return array{dir:string, url:string}|null */
	private static function dir(): ?array {
		$uploads = Utils::uploads();
		return $uploads ? array(
			'dir' => $uploads['dir'] . '/icons',
			'url' => $uploads['url'] . '/icons',
		) : null;
	}

	/**
	 * Library entries, like assets/data/icons/index.json plus `custom` and `url` (the set file).
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function index(): array {
		$list = get_option( self::OPTION, array() );
		$dir  = self::dir();
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) || ! $dir ) {
				continue;
			}
			$file = $dir['dir'] . '/' . $entry['id'] . '.json';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			$out[] = array(
				'id'      => (string) $entry['id'],
				'title'   => (string) $entry['title'],
				'group'   => __( 'Custom', 'uncoder' ),
				'count'   => (int) $entry['count'],
				'viewBox' => (string) $entry['viewBox'],
				'mode'    => 'fill',
				'custom'  => true,
				'source'  => (string) ( $entry['source'] ?? '' ),
				'url'     => $dir['url'] . '/' . $entry['id'] . '.json?ver=' . filemtime( $file ),
			);
		}
		return $out;
	}

	/** Absolute path of a custom set file ('' when unknown). */
	public static function path( string $id ): string {
		$dir = self::dir();
		if ( ! $dir || ! preg_match( '/^custom-[a-z0-9\-]+$/', $id ) ) {
			return '';
		}
		$file = $dir['dir'] . '/' . $id . '.json';
		return is_readable( $file ) ? $file : '';
	}

	public function upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$list  = array();
		foreach ( (array) ( $files['file'] ?? array() ) as $key => $value ) {
			// One file or file[] (several SVGs).
			if ( is_array( $value ) ) {
				foreach ( $value as $i => $v ) {
					$list[ $i ][ $key ] = $v;
				}
			} else {
				$list[0][ $key ] = $value;
			}
		}
		$list = array_values( array_filter( $list, static fn( $f ) => ! empty( $f['tmp_name'] ) && is_uploaded_file( $f['tmp_name'] ) && UPLOAD_ERR_OK === (int) ( $f['error'] ?? 1 ) ) );
		if ( ! $list ) {
			return new WP_Error( 'uncoder_icons', __( 'Choose an IcoMoon or Fontello zip, or SVG files.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$title = trim( sanitize_text_field( (string) $request->get_param( 'title' ) ) );
		if ( 1 === count( $list ) && preg_match( '/\.zip$/i', (string) $list[0]['name'] ) ) {
			if ( (int) $list[0]['size'] > self::MAX_ZIP ) {
				return new WP_Error( 'uncoder_icons', __( 'The zip is too large (20 MB at most).', 'uncoder' ), array( 'status' => 400 ) );
			}
			$set = self::parse_zip( (string) $list[0]['tmp_name'] );
			if ( '' === $title ) {
				$title = ucwords( str_replace( array( '-', '_' ), ' ', (string) preg_replace( '/\.zip$/i', '', basename( (string) $list[0]['name'] ) ) ) );
			}
		} else {
			$svgs = array();
			foreach ( $list as $f ) {
				if ( preg_match( '/\.svg$/i', (string) $f['name'] ) && (int) $f['size'] < MB_IN_BYTES ) {
					$svgs[ (string) $f['name'] ] = (string) file_get_contents( $f['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				}
			}
			$set = self::from_svg_files( $svgs );
		}
		if ( is_wp_error( $set ) ) {
			return $set;
		}
		if ( ! $set['icons'] ) {
			return new WP_Error( 'uncoder_icons', __( 'No icons were found in this upload.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$dir = self::dir();
		if ( ! $dir || ! wp_mkdir_p( $dir['dir'] ) ) {
			return new WP_Error( 'uncoder_fs', __( 'The uploads folder is not writable.', 'uncoder' ), array( 'status' => 500 ) );
		}
		// `append`: add the icons to an existing custom set (an icon with the same name is replaced).
		$append = (string) $request->get_param( 'append' );
		$entry  = '' !== $append && '' !== self::path( $append ) ? self::merge( $append, $set ) : self::save( '' !== $title ? $title : __( 'My icons', 'uncoder' ), $set );
		// The names of the icons this upload added, so a picker can select them.
		$entry['added'] = array_keys( $set['icons'] );
		return new WP_REST_Response( $entry );
	}

	/**
	 * Adds a parsed upload to an existing custom set.
	 *
	 * @param array{viewBox:string, icons:array<string,mixed>, source:string} $set Parsed upload.
	 * @return array<string,mixed> Index entry.
	 */
	private static function merge( string $id, array $set ): array {
		$file  = self::path( $id );
		$data  = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data  = is_array( $data ) ? $data : array();
		$box   = (string) ( $data['viewBox'] ?? $set['viewBox'] );
		$icons = is_array( $data['icons'] ?? null ) ? $data['icons'] : array();
		foreach ( $set['icons'] as $name => $icon ) {
			$body = is_array( $icon ) ? (string) $icon[0] : (string) $icon;
			$own  = is_array( $icon ) ? (string) $icon[1] : $set['viewBox'];
			// Each icon keeps its own viewBox when it differs from the set's.
			$icons[ (string) $name ] = $own === $box ? $body : array( $body, $own );
		}
		$icons = array_slice( $icons, 0, self::MAX, true );
		file_put_contents( $file, (string) wp_json_encode( array( 'viewBox' => $box, 'mode' => 'fill', 'icons' => $icons ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$list = (array) get_option( self::OPTION, array() );
		foreach ( $list as &$entry ) {
			if ( is_array( $entry ) && ( $entry['id'] ?? '' ) === $id ) {
				$entry['count'] = count( $icons );
			}
		}
		unset( $entry );
		update_option( self::OPTION, array_values( $list ), false );
		foreach ( self::index() as $entry ) {
			if ( $entry['id'] === $id ) {
				return $entry;
			}
		}
		return array( 'id' => $id );
	}

	/**
	 * @param array{viewBox:string, icons:array<string,mixed>, source:string} $set Parsed set.
	 * @return array<string,mixed> Index entry.
	 */
	private static function save( string $title, array $set ): array {
		$dir = (array) self::dir();
		$base = 'custom-' . ( sanitize_title( $title ) ?: 'icons' );
		$id   = $base;
		$list = (array) get_option( self::OPTION, array() );
		$ids  = array_column( $list, 'id' );
		for ( $n = 2; in_array( $id, $ids, true ); $n++ ) {
			$id = $base . '-' . $n;
		}
		file_put_contents( $dir['dir'] . '/' . $id . '.json', (string) wp_json_encode( array( 'viewBox' => $set['viewBox'], 'mode' => 'fill', 'icons' => $set['icons'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$list[] = array(
			'id'      => $id,
			'title'   => $title,
			'count'   => count( $set['icons'] ),
			'viewBox' => $set['viewBox'],
			'source'  => $set['source'],
		);
		update_option( self::OPTION, array_values( $list ), false );
		foreach ( self::index() as $entry ) {
			if ( $entry['id'] === $id ) {
				return $entry;
			}
		}
		return array( 'id' => $id );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id   = (string) $request['id'];
		$file = self::path( $id );
		if ( '' !== $file ) {
			wp_delete_file( $file );
		}
		$list = array_values( array_filter( (array) get_option( self::OPTION, array() ), static fn( $e ) => is_array( $e ) && ( $e['id'] ?? '' ) !== $id ) );
		update_option( self::OPTION, $list, false );
		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	/* ------------------------------------------------------------------ Parsing */

	/**
	 * @return array{viewBox:string, icons:array<string,mixed>, source:string}|WP_Error
	 */
	private static function parse_zip( string $file ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new WP_Error( 'uncoder_icons', __( 'This server cannot open zip files.', 'uncoder' ), array( 'status' => 500 ) );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			return new WP_Error( 'uncoder_icons', __( 'This zip file cannot be opened.', 'uncoder' ), array( 'status' => 400 ) );
		}
		// Everything read from the zip together stays under a budget (a small zip can unpack to gigabytes).
		$budget = self::MAX_UNZIPPED;
		$read   = static function ( string $pattern ) use ( $zip, &$budget ): array {
			$out = array();
			for ( $i = 0; $i < $zip->numFiles && count( $out ) < self::MAX; $i++ ) {
				$name = (string) $zip->getNameIndex( $i );
				$stat = $zip->statIndex( $i );
				if ( ! preg_match( $pattern, $name ) || false !== strpos( $name, '__MACOSX' ) || ! $stat || $stat['size'] >= 8 * MB_IN_BYTES || $stat['size'] > $budget ) {
					continue;
				}
				// Read no more than the declared size (a forged header cannot unpack more).
				$data = $zip->getFromIndex( $i, (int) $stat['size'] + 1 );
				if ( ! is_string( $data ) || strlen( $data ) > (int) $stat['size'] ) {
					continue;
				}
				$budget      -= strlen( $data );
				$out[ $name ] = $data;
			}
			return $out;
		};
		$selection = $read( '#(^|/)selection\.json$#i' );
		$fontello  = $read( '#(^|/)config\.json$#i' );
		$all_svg   = $read( '#\.svg$#i' );
		$fonts     = array_filter( $all_svg, static fn( $svg ) => false !== stripos( $svg, '<font' ) );
		$svgs      = array_filter( $all_svg, static fn( $svg ) => false === stripos( $svg, '<font' ) );
		$zip->close();

		if ( $selection ) {
			return self::from_icomoon( (string) reset( $selection ) );
		}
		if ( $fonts ) {
			$names = array();
			if ( $fontello ) {
				$config = json_decode( (string) reset( $fontello ), true );
				foreach ( (array) ( $config['glyphs'] ?? array() ) as $glyph ) {
					if ( isset( $glyph['code'], $glyph['css'] ) ) {
						$names[ (int) $glyph['code'] ] = (string) $glyph['css'];
					}
				}
			}
			return self::from_svg_font( (string) reset( $fonts ), $names, $fontello ? 'fontello' : 'font' );
		}
		if ( $svgs ) {
			return self::from_svg_files( $svgs );
		}
		return new WP_Error( 'uncoder_icons', __( 'No selection.json (IcoMoon), SVG font (Fontello) or SVG files in this zip.', 'uncoder' ), array( 'status' => 400 ) );
	}

	private static function name( string $raw, array $taken ): string {
		$name = sanitize_title( $raw );
		$name = '' !== $name ? $name : 'icon';
		$base = $name;
		for ( $n = 2; isset( $taken[ $name ] ); $n++ ) {
			$name = $base . '-' . $n;
		}
		return $name;
	}

	private static function path_ok( string $d ): bool {
		return '' !== trim( $d ) && strlen( $d ) < 60000 && (bool) preg_match( self::PATH_RE, $d );
	}

	private static function num( $v ): string {
		return is_numeric( $v ) ? (string) ( 0 + $v ) : '0';
	}

	/**
	 * IcoMoon selection.json: icons[].icon.paths (+ width), properties.name; height = em square.
	 *
	 * @return array{viewBox:string, icons:array<string,mixed>, source:string}
	 */
	private static function from_icomoon( string $json ): array {
		$data   = json_decode( $json, true );
		$height = (int) ( $data['height'] ?? 1024 );
		$height = $height > 0 ? $height : 1024;
		$icons  = array();
		foreach ( array_slice( (array) ( $data['icons'] ?? array() ), 0, self::MAX ) as $item ) {
			$paths = (array) ( $item['icon']['paths'] ?? array() );
			$body  = '';
			foreach ( $paths as $d ) {
				if ( is_string( $d ) && self::path_ok( $d ) ) {
					$body .= '<path d="' . esc_attr( $d ) . '"/>';
				}
			}
			if ( '' === $body ) {
				continue;
			}
			$name           = self::name( (string) ( $item['properties']['name'] ?? $item['icon']['tags'][0] ?? 'icon' ), $icons );
			$width          = (int) ( $item['icon']['width'] ?? $height );
			$icons[ $name ] = $width && $width !== $height ? array( $body, '0 0 ' . $width . ' ' . $height ) : $body;
		}
		return array(
			'viewBox' => '0 0 ' . $height . ' ' . $height,
			'icons'   => $icons,
			'source'  => 'icomoon',
		);
	}

	/**
	 * SVG font (Fontello, IcoMoon's fonts/*.svg): glyphs are drawn upside down on the baseline, so each path is
	 * flipped back inside a viewBox of its advance width × the em square.
	 *
	 * @param array<int,string> $names Glyph names by code point (Fontello config.json).
	 * @return array{viewBox:string, icons:array<string,mixed>, source:string}|WP_Error
	 */
	private static function from_svg_font( string $svg, array $names, string $source ) {
		$xml = self::xml( $svg );
		if ( ! $xml ) {
			return new WP_Error( 'uncoder_icons', __( 'The SVG font could not be read.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$xml->registerXPathNamespace( 's', 'http://www.w3.org/2000/svg' );
		$font   = $xml->xpath( '//s:font | //font' );
		$face   = $xml->xpath( '//s:font-face | //font-face' );
		$em     = (float) ( $face ? (string) $face[0]['units-per-em'] : 1000 ) ?: 1000.0;
		$ascent = (float) ( $face && '' !== (string) $face[0]['ascent'] ? (string) $face[0]['ascent'] : $em * 0.85 );
		$adv    = (float) ( $font ? (string) $font[0]['horiz-adv-x'] : $em ) ?: $em;
		$icons  = array();
		foreach ( array_slice( (array) $xml->xpath( '//s:glyph | //glyph' ), 0, self::MAX ) as $glyph ) {
			$d = (string) $glyph['d'];
			if ( ! self::path_ok( $d ) ) {
				continue;
			}
			$unicode = (string) $glyph['unicode'];
			$code    = '' !== $unicode ? mb_ord( html_entity_decode( $unicode, ENT_QUOTES | ENT_XML1, 'UTF-8' ), 'UTF-8' ) : 0;
			$raw     = $names[ $code ] ?? ( (string) $glyph['glyph-name'] ?: ( $code ? 'u' . dechex( (int) $code ) : 'icon' ) );
			$name    = self::name( $raw, $icons );
			$w       = (float) ( '' !== (string) $glyph['horiz-adv-x'] ? (string) $glyph['horiz-adv-x'] : $adv ) ?: $em;
			$body    = '<path transform="matrix(1 0 0 -1 0 ' . self::num( $ascent ) . ')" d="' . esc_attr( $d ) . '"/>';
			$icons[ $name ] = abs( $w - $em ) > 0.5 ? array( $body, '0 0 ' . self::num( $w ) . ' ' . self::num( $em ) ) : $body;
		}
		return array(
			'viewBox' => '0 0 ' . self::num( $em ) . ' ' . self::num( $em ),
			'icons'   => $icons,
			'source'  => $source,
		);
	}

	/**
	 * Separate SVG files: each file one icon (its file name), shapes rebuilt from their geometry.
	 *
	 * @param array<string,string> $files File name => SVG source.
	 * @return array{viewBox:string, icons:array<string,mixed>, source:string}
	 */
	private static function from_svg_files( array $files ): array {
		$icons = array();
		$first = '';
		foreach ( array_slice( $files, 0, self::MAX, true ) as $file => $svg ) {
			$xml = self::xml( $svg );
			if ( ! $xml ) {
				continue;
			}
			$box = (string) $xml['viewBox'];
			if ( ! preg_match( '/^\s*-?[\d.]+[\s,]+-?[\d.]+[\s,]+[\d.]+[\s,]+[\d.]+\s*$/', $box ) ) {
				$w   = (float) $xml['width'];
				$h   = (float) $xml['height'];
				$box = $w && $h ? '0 0 ' . $w . ' ' . $h : '0 0 24 24';
			}
			$box    = trim( (string) preg_replace( '/[\s,]+/', ' ', $box ) );
			$stroke = 'none' === strtolower( (string) $xml['fill'] ) && '' !== (string) $xml['stroke'];
			$body   = self::shapes( $xml, 0 );
			if ( '' === $body ) {
				continue;
			}
			if ( $stroke ) {
				$sw   = is_numeric( (string) $xml['stroke-width'] ) ? (string) $xml['stroke-width'] : '2';
				$body = '<g fill="none" stroke="currentColor" stroke-width="' . self::num( $sw ) . '" stroke-linecap="round" stroke-linejoin="round">' . $body . '</g>';
			}
			$first        = '' !== $first ? $first : $box;
			$name         = self::name( (string) preg_replace( '/\.svg$/i', '', basename( (string) $file ) ), $icons );
			$icons[ $name ] = $box === $first ? $body : array( $body, $box );
		}
		return array(
			'viewBox' => '' !== $first ? $first : '0 0 24 24',
			'icons'   => $icons,
			'source'  => 'svg',
		);
	}

	/** Geometry of an SVG tree as clean markup: shapes with numeric attributes only, groups with transforms. */
	private static function shapes( \SimpleXMLElement $node, int $depth ): string {
		if ( $depth > 8 ) {
			return '';
		}
		$out   = '';
		$attrs = array(
			'circle'   => array( 'cx', 'cy', 'r' ),
			'ellipse'  => array( 'cx', 'cy', 'rx', 'ry' ),
			'rect'     => array( 'x', 'y', 'width', 'height', 'rx', 'ry' ),
			'line'     => array( 'x1', 'y1', 'x2', 'y2' ),
			'polygon'  => array(),
			'polyline' => array(),
			'path'     => array(),
			'g'        => array(),
		);
		foreach ( $node->children() as $child ) {
			$tag = strtolower( $child->getName() );
			if ( ! isset( $attrs[ $tag ] ) ) {
				continue;
			}
			// Invisible helpers (a transparent bounding box) are left out.
			if ( 'none' === strtolower( (string) $child['fill'] ) && '' === (string) $child['stroke'] && 'g' !== $tag ) {
				continue;
			}
			$a = '';
			foreach ( $attrs[ $tag ] as $name ) {
				if ( '' !== (string) $child[ $name ] ) {
					$a .= ' ' . $name . '="' . self::num( (string) $child[ $name ] ) . '"';
				}
			}
			if ( 'path' === $tag ) {
				$d = (string) $child['d'];
				if ( ! self::path_ok( $d ) ) {
					continue;
				}
				$a .= ' d="' . esc_attr( $d ) . '"';
			}
			if ( 'polygon' === $tag || 'polyline' === $tag ) {
				$points = (string) $child['points'];
				if ( ! preg_match( '/^[0-9eE.,+\-\s]+$/', $points ) ) {
					continue;
				}
				$a .= ' points="' . esc_attr( trim( $points ) ) . '"';
			}
			foreach ( array( 'fill-rule', 'clip-rule' ) as $rule ) {
				if ( in_array( (string) $child[ $rule ], array( 'evenodd', 'nonzero' ), true ) ) {
					$a .= ' ' . $rule . '="' . (string) $child[ $rule ] . '"';
				}
			}
			$transform = (string) $child['transform'];
			if ( '' !== $transform && preg_match( '/^(\s*(matrix|translate|scale|rotate|skewX|skewY)\([0-9eE.,+\-\s]+\)\s*)+$/', $transform ) ) {
				$a .= ' transform="' . esc_attr( trim( $transform ) ) . '"';
			}
			$out .= 'g' === $tag ? ( '' !== ( $inner = self::shapes( $child, $depth + 1 ) ) ? '<g' . $a . '>' . $inner . '</g>' : '' ) : '<' . $tag . $a . '/>';
		}
		return $out;
	}

	private static function xml( string $svg ): ?\SimpleXMLElement {
		// No DTDs or entities (billion laughs / external entities).
		if ( '' === $svg || false !== stripos( $svg, '<!DOCTYPE' ) || false !== stripos( $svg, '<!ENTITY' ) ) {
			$svg = (string) preg_replace( '/<!DOCTYPE[^>]*(\[[^\]]*\])?>/is', '', $svg );
			if ( false !== stripos( $svg, '<!ENTITY' ) ) {
				return null;
			}
		}
		$prev = libxml_use_internal_errors( true );
		$xml  = simplexml_load_string( $svg, 'SimpleXMLElement', LIBXML_NONET );
		libxml_use_internal_errors( $prev );
		return $xml instanceof \SimpleXMLElement ? $xml : null;
	}
}
