<?php
/**
 * Loading speed: the hero (LCP) image first, small stylesheets inline.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Editor\Preview;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Settings_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * uncoder_wb_settings['performance']:
 * - lcp (default on): the first image of a page's first section is its likely Largest Contentful
 *   Paint. Found when the page is saved (Document assets "lcp"): a background image is preloaded
 *   from <head> with high priority (per device when they differ), an Image widget loads eagerly
 *   with fetchpriority="high" instead of lazily.
 * - inline_css (default off): stylesheets under 16 KB (the Design System and each page's CSS) are printed
 *   inline, saving render-blocking requests; bigger files stay cacheable links.
 * - lazy_bg (default on): background images of sections below the first one load when they come near the
 *   screen (class uncoder-lazy-bg; a one-line head script turns the rule on, so without JavaScript every
 *   background shows as usual).
 */
final class Performance {

	public const INLINE_MAX = 16384;

	public function register(): void {
		add_action( 'wp_head', array( $this, 'preload' ), 1 );
	}

	/**
	 * @return array{lcp:bool, inline_css:bool, lazy_bg:bool}
	 */
	public static function get(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$raw    = is_array( $stored ) && is_array( $stored['performance'] ?? null ) ? $stored['performance'] : array();
		return self::sanitize( $raw );
	}

	/**
	 * @param array<string,mixed> $raw Raw values.
	 * @return array{lcp:bool, inline_css:bool, lazy_bg:bool}
	 */
	public static function sanitize( array $raw ): array {
		return array(
			'lcp'        => ! array_key_exists( 'lcp', $raw ) || ! empty( $raw['lcp'] ),
			'inline_css' => ! empty( $raw['inline_css'] ),
			'lazy_bg'    => ! array_key_exists( 'lazy_bg', $raw ) || ! empty( $raw['lazy_bg'] ),
		);
	}

	/**
	 * The page's LCP candidate from its first section: an image widget or a background image.
	 *
	 * @param array<int, array<string,mixed>> $elements Tree.
	 * @return array<string,mixed>|null
	 */
	public static function detect( array $elements ): ?array {
		$first = null;
		foreach ( $elements as $node ) {
			if ( empty( $node['disabled'] ) ) {
				$first = $node;
				break;
			}
		}
		if ( ! $first ) {
			return null;
		}
		$found = null;
		$visit = static function ( array $node, int $depth ) use ( &$visit, &$found ): void {
			if ( $found || $depth > 4 || ! empty( $node['disabled'] ) ) {
				return;
			}
			$s    = (array) ( $node['settings'] ?? array() );
			$type = (string) ( $node['type'] ?? '' );
			if ( 'container' === $type && is_array( $s['background'] ?? null ) && in_array( $s['background']['type'] ?? 'classic', array( '', 'classic' ), true ) ) {
				$images = array();
				foreach ( Breakpoints::devices() as $device ) {
					$image = $s['background'][ 'image' . Breakpoints::suffix( $device ) ] ?? null;
					if ( is_array( $image ) && ! empty( $image['url'] ) ) {
						$images[ $device ] = esc_url_raw( (string) $image['url'] );
					}
				}
				if ( $images ) {
					$found = array(
						'type'   => 'bg',
						'images' => $images,
					);
					return;
				}
			}
			if ( 'image' === $type && ( ! empty( $s['image']['id'] ) || ! empty( $s['image']['url'] ) ) && 'lazy' !== ( $s['loading'] ?? '' ) ) {
				$found = array(
					'type' => 'img',
					'id'   => (string) ( $node['id'] ?? '' ),
				);
				return;
			}
			foreach ( (array) ( $node['children'] ?? array() ) as $child ) {
				$visit( (array) $child, $depth + 1 );
			}
		};
		$visit( $first, 0 );
		return $found;
	}

	/**
	 * Whether the element being rendered is its page's LCP image (Image widget).
	 */
	public static function is_lcp( Render_Context $ctx ): bool {
		if ( $ctx->editor || ! $ctx->doc_id || '' === $ctx->element_id || ! self::get()['lcp'] ) {
			return false;
		}
		$lcp = self::lcp_of( $ctx->doc_id );
		return $lcp && 'img' === $lcp['type'] && $lcp['id'] === $ctx->element_id;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function lcp_of( int $doc_id ): ?array {
		static $cache = array();
		if ( ! array_key_exists( $doc_id, $cache ) ) {
			$doc               = Plugin::instance()->documents()->get( $doc_id );
			$lcp               = $doc && $doc->is_builder() ? ( $doc->assets()['lcp'] ?? null ) : null;
			$cache[ $doc_id ] = is_array( $lcp ) ? $lcp : null;
		}
		return $cache[ $doc_id ];
	}

	/**
	 * Preloads the hero background image of the current page (per device range when they differ).
	 */
	public function preload(): void {
		if ( self::get()['lazy_bg'] ) {
			// Before any background can start loading: the runtime marks elements as they come near the screen.
			wp_print_inline_script_tag( "document.documentElement.classList.add('uncoder-lazy-bg-on')" );
		}
		if ( ! is_singular() || ! self::get()['lcp'] ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! Utils::is_builder_post( $post_id ) ) {
			return;
		}
		$lcp = self::lcp_of( $post_id );
		if ( ! $lcp || 'bg' !== $lcp['type'] || empty( $lcp['images'] ) ) {
			return;
		}
		foreach ( self::ranges( (array) $lcp['images'] ) as $url => $media ) {
			printf(
				'<link rel="preload" as="image" href="%s" fetchpriority="high"%s />' . "\n",
				esc_url( $url ),
				'' !== $media ? ' media="' . esc_attr( $media ) . '"' : ''
			);
		}
	}

	/**
	 * Which image applies at which widths (desktop-first cascade over the max-width breakpoints).
	 *
	 * @param array<string,string> $images Image URL per device.
	 * @return array<string,string> URL => media query ('' = every width).
	 */
	public static function ranges( array $images ): array {
		$steps = array();
		foreach ( Breakpoints::active() as $device => $bp ) {
			if ( 'max' === ( $bp['direction'] ?? 'max' ) ) {
				$steps[ $device ] = (int) $bp['value'];
			}
		}
		arsort( $steps ); // Widest first: tablet (1024), then mobile (767)…
		$current = $images['desktop'] ?? '';
		$bands   = array();
		$upper   = null; // Upper bound (inclusive) of the band being built; null = no limit.
		$lower   = null;
		foreach ( $steps as $device => $max ) {
			$bands[] = array( $current, $max + 1, $upper );
			$upper   = $max;
			$current = $images[ $device ] ?? $current;
		}
		$bands[] = array( $current, $lower, $upper );

		$by_url = array();
		foreach ( $bands as list( $url, $min, $max ) ) {
			if ( '' === $url ) {
				continue;
			}
			$query = trim( ( null !== $min ? '(min-width: ' . $min . 'px)' : '' ) . ( null !== $min && null !== $max ? ' and ' : '' ) . ( null !== $max ? '(max-width: ' . $max . 'px)' : '' ) );
			$by_url[ $url ][] = $query;
		}
		$out = array();
		foreach ( $by_url as $url => $queries ) {
			$out[ $url ] = in_array( '', $queries, true ) || count( $queries ) === count( $bands ) ? '' : implode( ', ', $queries );
		}
		return $out;
	}

	/**
	 * CSS file contents to print inline instead of linking, or null to keep the link.
	 */
	public static function inline_contents( string $path ): ?string {
		if ( ! self::get()['inline_css'] || ! is_readable( $path ) || filesize( $path ) > self::INLINE_MAX ) {
			return null;
		}
		// The editor canvas swaps these stylesheets live, so it keeps the links.
		$preview = Plugin::instance()->module( 'preview' );
		if ( $preview instanceof Preview && $preview->active() ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- generated CSS file.
		$css = (string) file_get_contents( $path );
		// Relative url() references would break once the CSS moves into the page.
		if ( preg_match( '/url\(\s*[\'"]?(?!data:|https?:|\/)/i', $css ) ) {
			return null;
		}
		return $css;
	}
}
