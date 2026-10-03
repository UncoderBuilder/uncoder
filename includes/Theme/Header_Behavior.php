<?php
/**
 * Header behaviour: sticky / reveal-on-scroll-up, transparent overlay, scrolled state, shrink.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Theme;

use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings live in the header template's page settings (keys below). Pages can opt in or out of the
 * transparent header with their own page setting `header_transparent` (yes|no).
 *
 * The behaviour is applied on the location wrapper (the element that is a direct child of the page),
 * because a sticky element can never leave its parent: a sticky container inside the header wrapper
 * would stay put.
 */
final class Header_Behavior {

	public const KEYS = array( 'header_sticky', 'header_transparent', 'header_transparent_color', 'header_transparent_logo', 'header_transparent_logo_white', 'header_transparent_keep_colors', 'header_scrolled_bg', 'header_scrolled_shadow', 'header_scrolled_height', 'header_scroll_offset' );

	/** Alternate logo (attachment id) for the header being rendered while transparent; read by the site-logo widget. */
	public static int $transparent_logo = 0;

	/** @var array<int, array<string,mixed>> Resolved behaviour per header id for this request. */
	private static array $cache = array();

	/**
	 * Cleans header template settings (keys absent from $raw are not returned).
	 *
	 * @param array<string,mixed> $raw Raw settings.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw ): array {
		$out = array();
		if ( array_key_exists( 'header_sticky', $raw ) ) {
			$out['header_sticky'] = in_array( $raw['header_sticky'], array( 'always', 'reveal' ), true ) ? $raw['header_sticky'] : '';
		}
		if ( array_key_exists( 'header_transparent', $raw ) ) {
			$out['header_transparent'] = in_array( $raw['header_transparent'], array( 'all', 'front', 'selected' ), true ) ? $raw['header_transparent'] : '';
		}
		foreach ( array( 'header_transparent_color', 'header_scrolled_bg' ) as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$color       = Plugin::instance()->controls()->get( 'color' )->normalize( $raw[ $key ], array() );
				$out[ $key ] = is_string( $color ) ? $color : '';
			}
		}
		if ( array_key_exists( 'header_transparent_logo', $raw ) ) {
			$logo = $raw['header_transparent_logo'];
			$id   = is_array( $logo ) ? absint( $logo['id'] ?? 0 ) : absint( $logo );
			// Stored like a media control value ({id, url}) so the editor's media picker can show it.
			$out['header_transparent_logo'] = $id && \Uncoder\Builder\Core\Media::is_image( $id )
				? array(
					'id'  => $id,
					'url' => (string) wp_get_attachment_url( $id ),
				)
				: array();
		}
		foreach ( array( 'header_transparent_logo_white', 'header_transparent_keep_colors', 'header_scrolled_shadow' ) as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = (bool) $raw[ $key ];
			}
		}
		if ( array_key_exists( 'header_scrolled_height', $raw ) ) {
			$height = $raw['header_scrolled_height'];
			if ( is_array( $height ) ) {
				// The editor's slider sends { size, unit }.
				$height = isset( $height['size'] ) && '' !== $height['size'] && is_numeric( $height['size'] ) ? $height['size'] . ( is_string( $height['unit'] ?? null ) ? $height['unit'] : 'px' ) : '';
			}
			$size                          = Utils::parse_size( $height );
			$out['header_scrolled_height'] = $size && 'px' === $size['unit'] ? max( 0, min( 300, (int) $size['size'] ) ) : 0;
		}
		if ( array_key_exists( 'header_scroll_offset', $raw ) ) {
			$out['header_scroll_offset'] = max( 0, min( 2000, (int) $raw['header_scroll_offset'] ) );
		}
		return $out;
	}

	/**
	 * Behaviour of a header template for the current request.
	 *
	 * @return array{sticky:string, transparent:bool, settings:array<string,mixed>}
	 */
	public static function resolve( int $header_id ): array {
		if ( isset( self::$cache[ $header_id ] ) ) {
			return self::$cache[ $header_id ];
		}
		$doc    = Plugin::instance()->documents()->get( $header_id );
		$s      = $doc ? $doc->page_settings() : array();
		$sticky = (string) ( $s['header_sticky'] ?? '' );
		$off    = array();
		$from   = '';
		// The generic "Sticky: Top" option on an element of the header: the header sticks from that element, and
		// whatever sits above it (a top bar, an announcement strip) scrolls away (modules/header.ts measures it).
		// On its own it makes the header sticky, with its "Sticky on" devices (e.g. only on tablets and phones).
		$node = $doc ? self::sticky_node( $doc->elements() ) : null;
		if ( $node ) {
			$from = (string) ( $node['id'] ?? '' );
			if ( '' === $sticky ) {
				$sticky = 'always';
				$on     = $node['settings']['_sticky_on'] ?? null;
				if ( is_array( $on ) ) {
					$off = array_values( array_diff( Breakpoints::devices(), $on ) );
				}
			}
		}

		$mode        = (string) ( $s['header_transparent'] ?? '' );
		$page_choice = '';
		if ( is_singular() ) {
			$page        = Plugin::instance()->documents()->get( (int) get_queried_object_id() );
			$page_choice = $page ? (string) ( $page->page_settings()['header_transparent'] ?? '' ) : '';
		}
		$transparent = 'yes' === $page_choice
			|| ( 'no' !== $page_choice && ( 'all' === $mode || ( 'front' === $mode && is_front_page() ) ) );

		self::$cache[ $header_id ] = array(
			'sticky'      => in_array( $sticky, array( 'always', 'reveal' ), true ) ? $sticky : '',
			'sticky_from' => $from,
			'sticky_off'  => $off,
			'transparent' => $transparent,
			'settings'    => $s,
		);
		return self::$cache[ $header_id ];
	}

	/**
	 * The first element of a header tree set to "Sticky: Top" (depth first), or null.
	 *
	 * @param array<int, array<string,mixed>> $nodes Element tree.
	 * @return array<string,mixed>|null
	 */
	private static function sticky_node( array $nodes ): ?array {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( 'top' === ( $node['settings']['_sticky'] ?? '' ) ) {
				return $node;
			}
			$found = self::sticky_node( is_array( $node['children'] ?? null ) ? $node['children'] : array() );
			if ( $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * Extra render args for the header location wrapper.
	 *
	 * @param array<string,mixed> $args Render args (class, tag…).
	 * @return array<string,mixed>
	 */
	public static function wrapper_args( int $header_id, array $args ): array {
		$b = self::resolve( $header_id );
		$s = $b['settings'];
		if ( '' === $b['sticky'] && ! $b['transparent'] ) {
			return $args;
		}
		$classes = array();
		$vars    = array();
		if ( '' !== $b['sticky'] ) {
			$classes[] = 'uncoder-header--sticky';
			// Devices left out of "Sticky on" scroll normally (the visibility CSS ranges); a transparent header
			// keeps overlaying there instead.
			if ( ! $b['transparent'] ) {
				foreach ( $b['sticky_off'] as $device ) {
					$classes[] = 'uncoder-sticky-off-' . sanitize_html_class( (string) $device );
				}
			}
			if ( 'reveal' === $b['sticky'] ) {
				$classes[] = 'uncoder-header--reveal';
			}
			if ( ! empty( $s['header_scrolled_bg'] ) ) {
				$classes[] = 'uncoder-header--scrolled-bg';
				$vars[]    = '--uncoder-hdr-scrolled-bg:' . $s['header_scrolled_bg'];
			}
			if ( $s['header_scrolled_shadow'] ?? true ) {
				$classes[] = 'uncoder-header--shadow';
			}
			if ( ! empty( $s['header_scrolled_height'] ) ) {
				$classes[] = 'uncoder-header--shrink';
				$vars[]    = '--uncoder-hdr-scrolled-h:' . (int) $s['header_scrolled_height'] . 'px';
			}
		}
		if ( $b['transparent'] ) {
			$classes[] = 'uncoder-header--transparent is-transparent';
			// "Keep colors": the header only overlays the page (a light hero); otherwise its text, menu and icons
			// switch to the transparent text color (white by default) while it is transparent.
			if ( empty( $s['header_transparent_keep_colors'] ) ) {
				$classes[] = 'uncoder-header--recolor';
				$vars[]    = '--uncoder-hdr-t-color:' . ( ! empty( $s['header_transparent_color'] ) ? $s['header_transparent_color'] : '#ffffff' );
			}
			if ( ! empty( $s['header_transparent_logo_white'] ) && empty( $s['header_transparent_logo'] ) ) {
				$classes[] = 'uncoder-header--logo-white';
			}
		}
		$settings = array(
			'sticky'      => $b['sticky'],
			'transparent' => $b['transparent'],
			'offset'      => isset( $s['header_scroll_offset'] ) ? (int) $s['header_scroll_offset'] : 10,
		);
		if ( '' !== $b['sticky'] && '' !== $b['sticky_from'] ) {
			$settings['from'] = $b['sticky_from'];
		}
		$args['class'] = trim( ( $args['class'] ?? '' ) . ' ' . implode( ' ', $classes ) );
		$attrs         = (array) ( $args['attrs'] ?? array() );
		$attrs['data-uncoder-js']   = trim( ( $attrs['data-uncoder-js'] ?? '' ) . ' header' );
		$attrs['data-settings'] = (string) wp_json_encode( $settings );
		if ( $vars ) {
			$attrs['style'] = implode( ';', $vars );
		}
		$args['attrs'] = $attrs;
		return $args;
	}

	/**
	 * Renders a header template with its behaviour (used by every header output path).
	 */
	public static function render( Theme_Builder $builder, int $header_id, array $args ): string {
		$args = self::wrapper_args( $header_id, $args );
		$b    = self::resolve( $header_id );
		if ( '' !== $b['sticky'] || $b['transparent'] ) {
			\Uncoder\Builder\Core\Assets::enqueue_module( 'header' );
		}
		$logo                   = $b['settings']['header_transparent_logo'] ?? array();
		self::$transparent_logo = $b['transparent'] ? absint( is_array( $logo ) ? ( $logo['id'] ?? 0 ) : $logo ) : 0;
		$html                   = $builder->render_template( $header_id, $args );
		self::$transparent_logo = 0;
		return $html;
	}

	/**
	 * Body classes for the current request.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class( array $classes, int $header_id ): array {
		if ( ! $header_id ) {
			return $classes;
		}
		$b = self::resolve( $header_id );
		if ( $b['transparent'] ) {
			$classes[] = 'uncoder-has-transparent-header';
		}
		if ( '' !== $b['sticky'] ) {
			$classes[] = 'uncoder-has-sticky-header';
		}
		return $classes;
	}
}
