<?php
/**
 * Font catalog and delivery.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Knows the Google Fonts and Fontshare catalogs (bundled JSON), custom fonts and how to load them.
 */
final class Fonts {

	public const SYSTEM = array(
		'system-ui'  => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
		'sans-serif' => 'ui-sans-serif, system-ui, sans-serif',
		'serif'      => 'ui-serif, Georgia, Cambria, "Times New Roman", serif',
		'monospace'  => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
	);

	/** @var array<string, array{c:string,w:string[]}>|null */
	private static ?array $catalog = null;

	/** @var array<string, array{c:string,w:string[],s:string}>|null */
	private static ?array $fontshare = null;

	/**
	 * @return array<string, array{c:string,w:string[]}> family => [category, weights]
	 */
	public static function catalog(): array {
		if ( null === self::$catalog ) {
			self::$catalog = array();
			$file          = UNCODER_WB_PATH . 'assets/data/google-fonts.json';
			if ( is_readable( $file ) ) {
				$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( is_array( $data ) ) {
					self::$catalog = $data;
				}
			}
		}
		return self::$catalog;
	}

	public static function is_google( string $family ): bool {
		return isset( self::catalog()[ $family ] );
	}

	/**
	 * Fontshare families that are not Google Fonts (tools/fontshare-catalog.mjs): family => [category, weights,
	 * italics, slug]. Free for commercial use (ITF Free Font License or SIL OFL), served by Fontshare's CSS API.
	 *
	 * @return array<string, array{c:string,w:string[],s:string,i?:int}>
	 */
	public static function fontshare(): array {
		if ( null === self::$fontshare ) {
			self::$fontshare = array();
			$file            = UNCODER_WB_PATH . 'assets/data/fontshare-fonts.json';
			if ( is_readable( $file ) ) {
				$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( is_array( $data ) ) {
					self::$fontshare = $data;
				}
			}
		}
		return self::$fontshare;
	}

	public static function is_fontshare( string $family ): bool {
		return isset( self::fontshare()[ $family ] ) && ! self::is_google( $family );
	}

	/**
	 * One Fontshare `f[]=` parameter: the slug and its weights, each italic as weight + 1 (Fontshare's code),
	 * e.g. `f[]=supreme@400,401,700,701`. Twin of fontshareFamilyParam() in src/shared/fonts-url.ts.
	 *
	 * @param string   $family  Family.
	 * @param string[] $weights Weights.
	 */
	public static function fontshare_param( string $family, array $weights ): string {
		$info  = self::fontshare()[ $family ] ?? array();
		$codes = array();
		foreach ( array_values( array_unique( array_map( 'strval', $weights ) ) ) as $w ) {
			$codes[] = $w;
			if ( ! empty( $info['i'] ) ) {
				$codes[] = (string) ( (int) $w + 1 );
			}
		}
		return 'f[]=' . rawurlencode( (string) ( $info['s'] ?? sanitize_title( $family ) ) ) . '@' . implode( ',', $codes );
	}

	/**
	 * Family => category for every known font (Google + custom), as the TS engine needs it.
	 *
	 * @return array<string, array{c:string}>
	 */
	public static function categories(): array {
		$out = array();
		foreach ( self::catalog() + self::fontshare() + \Uncoder\Builder\Site\Custom_Fonts::catalog() + \Uncoder\Builder\Site\Adobe_Fonts::catalog() as $family => $data ) {
			$out[ $family ] = array( 'c' => $data['c'] );
		}
		return $out;
	}

	public static function category( string $family ): string {
		$custom = \Uncoder\Builder\Site\Custom_Fonts::get( $family );
		$c      = $custom ? $custom['c'] : ( self::catalog()[ $family ]['c'] ?? ( self::fontshare()[ $family ]['c'] ?? ( \Uncoder\Builder\Site\Adobe_Fonts::catalog()[ $family ]['c'] ?? '' ) ) );
		switch ( $c ) {
			case 'serif':
				return 'serif';
			case 'monospace':
				return 'monospace';
			case 'handwriting':
				return 'cursive';
			default:
				return 'sans-serif';
		}
	}

	/**
	 * CSS font-family value for a family name.
	 */
	public static function css_stack( string $family ): string {
		$family = trim( $family );
		if ( 0 === strpos( $family, 'var(' ) ) {
			return Utils::css_value( $family );
		}
		if ( isset( self::SYSTEM[ $family ] ) ) {
			return self::SYSTEM[ $family ];
		}
		$clean = preg_replace( '/[^A-Za-z0-9 \-_.]/', '', $family );
		return '"' . $clean . '", ' . self::category( $clean );
	}

	/**
	 * One css2 `family=` parameter. Families with an optical-size axis also load it (browsers then use the
	 * display cut for big headings, e.g. Inter Display) — `family=Inter:opsz,wght@14..32,400;14..32,600`.
	 * Families with italics load them too (`ital` axis), so <em> and italic styles use the real italic instead
	 * of a slanted copy; browsers only download the italic files when italic text is on the page.
	 * Twin of googleFamilyParam() in src/shared/fonts-url.ts (the editor canvas).
	 *
	 * @param string          $family  Family.
	 * @param string|string[] $weights Weight list or a "min..max" range.
	 */
	public static function family_param( string $family, $weights ): string {
		$name   = str_replace( ' ', '+', $family );
		$info   = self::catalog()[ $family ] ?? array();
		$opsz   = $info['o'] ?? null;
		// Each weight once: Google answers 400 Bad Request (for the whole stylesheet) to a repeated tuple.
		$list   = is_string( $weights ) ? array( $weights ) : array_values( array_unique( array_map( 'strval', $weights ) ) );
		$axes   = array();
		$tuples = $list;
		if ( is_array( $opsz ) && 2 === count( $opsz ) ) {
			$range  = (int) $opsz[0] . '..' . (int) $opsz[1];
			$tuples = array_map( static fn( $w ) => $range . ',' . $w, $tuples );
			$axes[] = 'opsz';
		}
		if ( ! empty( $info['i'] ) ) {
			// Google wants every upright tuple first (ital 0), then the italic ones (ital 1).
			$tuples = array_merge( array_map( static fn( $t ) => '0,' . $t, $tuples ), array_map( static fn( $t ) => '1,' . $t, $tuples ) );
			array_unshift( $axes, 'ital' );
		}
		$axes[] = 'wght';
		return 'family=' . $name . ':' . implode( ',', $axes ) . '@' . implode( ';', $tuples );
	}

	/**
	 * Enqueues fonts collected from generated CSS (family => weights).
	 *
	 * @param array<string, string[]> $fonts Fonts.
	 */
	public static function enqueue( array $fonts ): void {
		$delivery = Plugin::instance()->kit()->setting( 'font_delivery', 'google' );
		if ( 'none' === $delivery || ! $fonts ) {
			return;
		}
		// Adobe Fonts project families: Adobe's stylesheet (they are not Google fonts, so skipped below).
		\Uncoder\Builder\Site\Adobe_Fonts::enqueue_for( array_map( 'strval', array_keys( $fonts ) ) );
		$families  = array();
		$fontshare = array();
		foreach ( $fonts as $family => $weights ) {
			// An uploaded font with the same name replaces the Google (or Fontshare) one.
			if ( \Uncoder\Builder\Site\Custom_Fonts::get( $family ) ) {
				continue;
			}
			if ( self::is_fontshare( $family ) ) {
				$available = self::fontshare()[ $family ]['w'];
				$wanted    = array_values( array_intersect( array_map( 'strval', $weights ? $weights : array( '400' ) ), $available ) );
				if ( ! $wanted ) {
					$wanted = in_array( '400', $available, true ) ? array( '400' ) : array( $available[0] );
				}
				sort( $wanted );
				$fontshare[ $family ] = $wanted;
				continue;
			}
			if ( ! self::is_google( $family ) ) {
				continue;
			}
			$available = self::catalog()[ $family ]['w'] ?? array( '400' );
			$requested = array_map( 'strval', $weights ? $weights : array( '400' ) );
			$axis      = self::catalog()[ $family ]['v'] ?? null;
			// Variable fonts: in-between weights (e.g. 650, 750) need the axis range, not static instances.
			if ( is_array( $axis ) && array_diff( $requested, $available ) ) {
				$families[ $family ] = (int) $axis[0] . '..' . (int) $axis[1];
				continue;
			}
			$wanted = array_values( array_intersect( $requested, $available ) );
			if ( ! $wanted ) {
				$wanted = in_array( '400', $available, true ) ? array( '400' ) : array( $available[0] );
			}
			sort( $wanted );
			$families[ $family ] = $wanted;
		}
		if ( $fontshare ) {
			ksort( $fontshare );
			$params = array();
			foreach ( $fontshare as $family => $weights ) {
				$params[] = self::fontshare_param( $family, $weights );
			}
			self::enqueue_url( 'https://api.fontshare.com/v2/css?' . implode( '&', $params ) . '&display=swap', $delivery );
		}
		if ( ! $families ) {
			return;
		}
		ksort( $families );
		$parts = array();
		foreach ( $families as $family => $weights ) {
			$parts[] = self::family_param( $family, $weights );
		}
		self::enqueue_url( 'https://fonts.googleapis.com/css2?' . implode( '&', $parts ) . '&display=swap', $delivery );
	}

	/**
	 * Enqueues a Google Fonts or Fontshare stylesheet, or its self-hosted copy ("local" delivery).
	 */
	private static function enqueue_url( string $url, string $delivery ): void {
		$handle = 'uncoder-fonts-' . substr( md5( $url ), 0, 8 );
		if ( 'local' === $delivery ) {
			// Self-hosted copies: visitors' browsers never contact Google.
			$local = self::local_css( $url );
			if ( $local ) {
				wp_enqueue_style( $handle, $local, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the file name is a content hash.
			}
			return;
		}
		wp_enqueue_style( $handle, $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}

	/**
	 * URL of a locally stored copy of a Google Fonts (or Fontshare) stylesheet, downloading it (and its WOFF2 files)
	 * on first use. Returns null while the download is not available; the page then uses the
	 * fallback font stack and the download is retried after ten minutes.
	 */
	public static function local_css( string $google_url ): ?string {
		$uploads = Utils::uploads();
		if ( ! $uploads ) {
			return null;
		}
		$hash = substr( md5( $google_url ), 0, 16 );
		$file = $uploads['dir'] . '/fonts/' . $hash . '.css';
		if ( file_exists( $file ) ) {
			return $uploads['url'] . '/fonts/' . $hash . '.css';
		}
		$lock = 'uncoder_wb_font_dl_' . $hash;
		if ( get_transient( $lock ) ) {
			return null;
		}
		set_transient( $lock, 1, 10 * MINUTE_IN_SECONDS );
		if ( ! self::download( $google_url, $uploads['dir'] . '/fonts', $file ) ) {
			return null;
		}
		delete_transient( $lock );
		return $uploads['url'] . '/fonts/' . $hash . '.css';
	}

	private static function download( string $google_url, string $dir, string $css_file ): bool {
		$started = microtime( true );
		// A current browser user agent makes Google serve WOFF2 with unicode-range subsets.
		$ua       = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
		$response = wp_safe_remote_get(
			$google_url,
			array(
				'timeout'    => 8,
				'user-agent' => $ua,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$css = (string) wp_remote_retrieve_body( $response );
		// Fontshare lists WOFF2, WOFF and TTF per face (protocol-relative URLs): keep the WOFF2 file only.
		$fontshare = false !== strpos( $google_url, 'api.fontshare.com' );
		if ( $fontshare ) {
			$css = (string) preg_replace( '#,\s*url\([\'"]?//cdn\.fontshare\.com/[^)]+\.(?:woff|ttf)[\'"]?\)\s*format\([^)]*\)#', '', $css );
			$css = (string) preg_replace( '#url\([\'"]?(//cdn\.fontshare\.com/[^)\'"]+\.woff2)[\'"]?\)#', 'url(https:$1)', $css );
		}
		if ( ! preg_match_all( '#url\((https://(?:fonts\.gstatic\.com|cdn\.fontshare\.com)/[^)\s\'"]+\.woff2)\)#', $css, $m ) ) {
			return false;
		}
		if ( ! wp_mkdir_p( $dir . '/files' ) ) {
			return false;
		}
		foreach ( array_unique( $m[1] ) as $font_url ) {
			if ( microtime( true ) - $started > 20 ) {
				return false; // Finish on a later request; files already fetched are kept.
			}
			$name   = md5( $font_url ) . '.woff2';
			$target = $dir . '/files/' . $name;
			if ( ! file_exists( $target ) ) {
				$font = wp_safe_remote_get(
					$font_url,
					array(
						'timeout'             => 8,
						'stream'              => true,
						'filename'            => $target . '.part',
						'limit_response_size' => 2 * MB_IN_BYTES,
						'user-agent'          => $ua,
					)
				);
				if ( is_wp_error( $font ) || 200 !== (int) wp_remote_retrieve_response_code( $font ) || ! filesize( $target . '.part' ) ) {
					wp_delete_file( $target . '.part' );
					return false;
				}
				rename( $target . '.part', $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			}
			$css = str_replace( $font_url, 'files/' . $name, $css );
		}
		$css = ( $fontshare ? "/* Fontshare fonts, self-hosted by Uncoder. Licenses: https://www.fontshare.com/licenses */\n" : "/* Google Fonts, self-hosted by Uncoder. Licenses: https://fonts.google.com/attribution */\n" ) . $css;
		return false !== file_put_contents( $css_file, $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Searches the catalog (used by the editor and MCP).
	 *
	 * @return array<int, array{family:string,category:string,weights:string[]}>
	 */
	public static function search( string $query, int $limit = 30, string $category = '' ): array {
		$query = strtolower( trim( $query ) );
		$out   = array();
		foreach ( self::catalog() + self::fontshare() as $family => $data ) {
			if ( '' !== $category && $data['c'] !== $category ) {
				continue;
			}
			if ( '' === $query || false !== strpos( strtolower( $family ), $query ) ) {
				$out[] = array(
					'family'   => $family,
					'category' => $data['c'],
					'weights'  => $data['w'],
				) + ( isset( $data['s'] ) ? array( 'source' => 'fontshare' ) : array() );
				if ( count( $out ) >= $limit ) {
					break;
				}
			}
		}
		return $out;
	}
}
