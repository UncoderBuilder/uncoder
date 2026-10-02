<?php
/**
 * Data migrations between plugin versions.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * 1.1.0 — prefixes became "uncoder": CSS variables --unc-* → --uncoder-*, classes unc-* → uncoder-*,
 * data-unc-* → data-uncoder-*.
 * 1.3.0 — lighter markup (PLAN.md §19): elements are addressed by their class (#uncoder-{id} and
 * [data-id="{id}"] → .uncoder-{id}); containers print only non-default modifiers (--flex, --full gone).
 * 1.2.0 — one element per widget, like Bricks (PLAN.md §17): .uncoder-el-{id} → #uncoder-{id},
 * .uncoder-w-{type} → .uncoder-{type}, .uncoder-con… → .uncoder-container…, SVG icons .uncoder-icon →
 * .uncoder-svg, the button style .uncoder-button → .uncoder-btn.
 *
 * Stored content is rewritten once on upgrade, and anything still using the old names later (an old
 * export file, an undo snapshot, an AI client that learned the old names) is converted when it is saved —
 * except names that now mean something else (.uncoder-button, .uncoder-icon), which convert only once.
 */
final class Migrations {

	/** Root block of each widget before 1.2.0 (inside .uncoder-w-{type}); the root is now the element. */
	private const ROOTS = array(
		'accordion' => 'uncoder-accordion',
		'alert' => 'uncoder-alert',
		'animated-headline' => 'uncoder-ah',
		'archive-description' => 'uncoder-archive-description',
		'archive-title' => 'uncoder-archive-title',
		'author-box' => 'uncoder-author-box',
		'blockquote' => 'uncoder-blockquote',
		'breadcrumbs' => 'uncoder-breadcrumbs',
		'button' => 'uncoder-button',
		'call-to-action' => 'uncoder-cta',
		'carousel' => 'uncoder-carousel',
		'code-highlight' => 'uncoder-code',
		'countdown' => 'uncoder-countdown',
		'counter' => 'uncoder-counter',
		'divider' => 'uncoder-divider',
		'featured-image' => 'uncoder-featured-image',
		'flip-box' => 'uncoder-flip-box',
		'form' => 'uncoder-form',
		'google-maps' => 'uncoder-google-maps',
		'heading' => 'uncoder-heading',
		'hotspot' => 'uncoder-hotspot',
		'icon' => 'uncoder-icon-wrap',
		'icon-box' => 'uncoder-icon-box',
		'icon-list' => 'uncoder-icon-list',
		'image' => 'uncoder-image',
		'image-box' => 'uncoder-image-box',
		'image-carousel' => 'uncoder-image-carousel|uncoder-carousel',
		'image-compare' => 'uncoder-image-compare',
		'image-gallery' => 'uncoder-image-gallery',
		'login' => 'uncoder-login',
		'logo-grid' => 'uncoder-logo-grid',
		'loop-filter' => 'uncoder-loop-filter',
		'loop-grid' => 'uncoder-loop-grid',
		'lottie' => 'uncoder-lottie',
		'marquee' => 'uncoder-marquee',
		'nav-menu' => 'uncoder-nav-menu',
		'off-canvas' => 'uncoder-offcanvas',
		'post-comments' => 'uncoder-post-comments',
		'post-content' => 'uncoder-post-content',
		'post-excerpt' => 'uncoder-post-excerpt',
		'post-info' => 'uncoder-post-info',
		'post-navigation' => 'uncoder-post-nav',
		'post-title' => 'uncoder-post-title',
		'posts' => 'uncoder-posts',
		'price-list' => 'uncoder-price-list',
		'price-table' => 'uncoder-price-table',
		'progress-bar' => 'uncoder-progress',
		'reading-progress' => 'uncoder-reading-progress',
		'scheme-switch' => 'uncoder-scheme-switch',
		'search-form' => 'uncoder-search',
		'share-buttons' => 'uncoder-share',
		'shortcode' => 'uncoder-shortcode',
		'site-logo' => 'uncoder-site-logo',
		'site-tagline' => 'uncoder-site-tagline',
		'site-title' => 'uncoder-site-title',
		'social-icons' => 'uncoder-social-icons',
		'spacer' => 'uncoder-spacer',
		'star-rating' => 'uncoder-star-rating',
		'steps' => 'uncoder-steps',
		'table' => 'uncoder-table',
		'table-of-contents' => 'uncoder-toc',
		'tabs' => 'uncoder-tabs',
		'team-member' => 'uncoder-team-member',
		'testimonial' => 'uncoder-testimonial',
		'testimonial-carousel' => 'uncoder-testimonial-carousel|uncoder-carousel',
		'text-editor' => 'uncoder-text',
		'timeline' => 'uncoder-timeline',
		'video' => 'uncoder-video',
	);

	/** Set while the one-time upgrade runs: also converts names that changed meaning. */
	private static bool $upgrade = false;

	/**
	 * Runs the migrations between $from (the stored schema version) and the current one.
	 */
	public static function run( string $from ): void {
		if ( '' !== $from && version_compare( $from, '1.1.0', '<' ) ) {
			self::uncoder_prefix();
		}
		if ( '' !== $from && version_compare( $from, '1.2.0', '<' ) ) {
			self::$upgrade = true;
			self::rewrite_stored();
			self::$upgrade = false;
		} elseif ( '' !== $from && version_compare( $from, '1.3.0', '<' ) ) {
			self::rewrite_stored();
		}
		if ( '' !== $from && version_compare( $from, '1.4.0', '<' ) ) {
			self::legacy_conditions();
		}
	}

	/**
	 * 1.4.0 — the flat display rules (_show_*) become rule sets in _conditions (Element_Conditions),
	 * in stored trees and autosaves.
	 */
	private static function legacy_conditions(): void {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s) AND meta_value LIKE %s", Utils::META_DATA, '_uncoder_wb_autosave', '%\\_show\\_%' )
		);
		$walk = static function ( array $nodes ) use ( &$walk ): array {
			foreach ( $nodes as $i => $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				if ( is_array( $node['settings'] ?? null ) ) {
					$nodes[ $i ]['settings'] = Element_Conditions::from_legacy( $node['settings'] );
				}
				if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
					$nodes[ $i ]['children'] = $walk( $node['children'] );
				}
			}
			return $nodes;
		};
		// A tree is JSON: {"elements":[…]} or a plain list.
		$convert = static function ( string $json ) use ( $walk ): ?string {
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				return null;
			}
			$data = isset( $data['elements'] ) && is_array( $data['elements'] ) ? array_merge( $data, array( 'elements' => $walk( $data['elements'] ) ) ) : $walk( $data );
			return (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		};
		foreach ( (array) $rows as $row ) {
			$value = maybe_unserialize( $row->meta_value );
			if ( is_string( $value ) ) {
				$new = $convert( $value );
			} elseif ( is_array( $value ) && is_string( $value['elements'] ?? null ) ) {
				$tree = $convert( $value['elements'] );
				$new  = null === $tree ? null : array_merge( $value, array( 'elements' => $tree ) );
			} else {
				$new = null;
			}
			if ( null !== $new && $new !== $value ) {
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $new ) ), array( 'meta_id' => (int) $row->meta_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		wp_cache_flush();
	}

	/**
	 * Converts old prefixes anywhere in a value. Only CSS variable names change in ordinary settings
	 * (they can only be ours); custom CSS also gets its class selectors converted, custom code only
	 * dot-prefixed class selectors — plain text and URLs are never touched.
	 *
	 * @param mixed  $value Value.
	 * @param string $mode  auto|css|code (auto picks css for *custom_css* keys and code for "code").
	 * @param bool   $json  Also look inside JSON-encoded strings (stored meta only; never user text).
	 * @return mixed
	 */
	public static function legacy( $value, string $mode = 'auto', string $key = '', bool $json = false ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::legacy( $v, $mode, is_string( $k ) ? $k : $key, $json );
			}
			return $value;
		}
		if ( ! is_string( $value ) || false === strpos( $value, 'unc' ) ) {
			return $value;
		}
		// JSON stored inside a meta value (element data, autosaves): convert inside, keep the encoding.
		$first = ltrim( $value )[0] ?? '';
		if ( $json && ( '[' === $first || '{' === $first ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return (string) wp_json_encode( self::legacy( $decoded, $mode, '', true ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
		}
		$effective = 'auto' === $mode ? ( false !== strpos( $key, 'custom_css' ) ? 'css' : ( 'code' === $key ? 'code' : 'value' ) ) : $mode;
		return self::convert( $value, $effective );
	}

	private static function convert( string $s, string $mode ): string {
		$s = str_replace( '--unc-', '--uncoder-', $s );
		if ( 'css' === $mode ) {
			$s = str_replace( 'data-unc-', 'data-uncoder-', $s );
			$s = (string) preg_replace( '/(?<![A-Za-z0-9_\-])unc-/', 'uncoder-', $s );
			$s = (string) preg_replace( '/(?<![A-Za-z0-9_\-])unc(?![A-Za-z0-9_\-])/', 'uncoder', $s );
		} elseif ( 'code' === $mode ) {
			$s = (string) preg_replace( '/\.unc(?=[\-\s{,:.\[>+~)])/', '.uncoder', $s );
			$s = str_replace( 'data-unc-', 'data-uncoder-', $s );
		}
		return 'value' === $mode ? $s : self::markup( $s );
	}

	/**
	 * 1.2.0 class names in CSS and code (see the class comment).
	 */
	private static function markup( string $s ): string {
		if ( false === strpos( $s, 'uncoder-' ) ) {
			return $s;
		}
		if ( self::$upgrade ) {
			$s = (string) preg_replace( '/(?<![A-Za-z0-9_\-])uncoder-button(?=--|__|(?![A-Za-z0-9_\-]))/', 'uncoder-btn', $s );
			$s = (string) preg_replace( '/(?<![A-Za-z0-9_\-])uncoder-icon(?=--|(?![A-Za-z0-9_\-]))/', 'uncoder-svg', $s );
		}
		$s = str_replace( array( 'uncoder-heading__title--centered', 'uncoder-heading__title' ), array( 'uncoder-heading--centered', 'uncoder-heading' ), $s );
		$s = (string) preg_replace( '/(?<![A-Za-z0-9_\-])uncoder-button-wrap(?![A-Za-z0-9_\-])/', 'uncoder-button', $s );
		$s = (string) preg_replace( '/\.uncoder-el-([a-z][a-z0-9]{2,31})(?![A-Za-z0-9_\-])/', '#uncoder-$1', $s );
		$s = (string) preg_replace( '/\.uncoder-el(?![A-Za-z0-9_\-])/', '[data-id]', $s );
		foreach ( self::ROOTS as $type => $root ) {
			$s = (string) preg_replace( '/\.uncoder-w-' . preg_quote( $type, '/' ) . '\s*>?\s*\.(' . $root . ')(?=--|[^A-Za-z0-9_\-]|$)/', '.uncoder .$1', $s );
		}
		$s = (string) preg_replace( '/\.uncoder-w-([a-z0-9\-]+)/', '.uncoder-$1', $s );
		$s = (string) preg_replace( '/\.uncoder-w(?![A-Za-z0-9_\-])/', '.uncoder', $s );
		$s = (string) preg_replace( '/(?<![A-Za-z0-9_\-])uncoder-con(?=--|__|(?![A-Za-z0-9_\-]))/', 'uncoder-container', $s );
		// 1.3.0: the element's class replaces its generated id and data-id; default modifiers are gone.
		$s = (string) preg_replace( '/#uncoder-([a-z][a-z0-9]{2,31})(?![A-Za-z0-9_\-])/', '.uncoder-$1', $s );
		$s = (string) preg_replace( '/\[data-id=(["\']?)([a-z][a-z0-9]{2,31})\1\]/', '.uncoder-$2', $s );
		$s = (string) preg_replace( '/\.uncoder-container--flex(?![A-Za-z0-9_\-])/', '.uncoder-container:not(.uncoder-container--grid)', $s );
		return (string) preg_replace( '/\.uncoder-container--full(?![A-Za-z0-9_\-])/', '.uncoder-container:not(.uncoder-container--boxed)', $s );
	}

	private static function uncoder_prefix(): void {
		self::rewrite_stored();
	}

	/**
	 * Rewrites every stored Uncoder value (post meta and options) once, then lets CSS rebuild.
	 */
	private static function rewrite_stored(): void {
		global $wpdb;
		$skip = array( Utils::META_CSS, '_uncoder_wb_css_inline', '_uncoder_wb_rev' );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value LIKE %s", $wpdb->esc_like( '_uncoder_wb_' ) . '%', '%unc%' )
		);
		foreach ( (array) $rows as $row ) {
			if ( in_array( $row->meta_key, $skip, true ) ) {
				continue;
			}
			$old = maybe_unserialize( $row->meta_value );
			$new = self::legacy( $old, 'auto', '', true );
			if ( $new !== $old ) {
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $new ) ), array( 'meta_id' => (int) $row->meta_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		wp_cache_flush();

		$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s", $wpdb->esc_like( 'uncoder_wb_' ) . '%', '%unc%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $options as $name ) {
			if ( 0 === strpos( $name, '_transient' ) ) {
				continue;
			}
			$old = get_option( $name );
			$new = self::legacy( $old, 'auto', '', true );
			if ( $new !== $old ) {
				update_option( $name, $new );
			}
		}

		// Stored CSS files carry the old names: they rebuild on their next request (signature changes with
		// Generator::REVISION); the kit file is rebuilt now.
		$stored = get_option( Kit::OPTION, null );
		if ( is_array( $stored ) ) {
			\Uncoder\Builder\Plugin::instance()->kit()->save( $stored );
		}
		// Every stored stylesheet carries the old class and variable names: rebuild them all now.
		\Uncoder\Builder\Plugin::instance()->documents()->regenerate_all();
	}
}
