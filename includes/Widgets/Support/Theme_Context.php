<?php
/**
 * Shared helpers for theme-builder widgets (current post, global post switching, samples).
 *
 * Not a widget: the widget registry only scans includes/Widgets/*.php.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Render_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless helpers used by the site / post / archive widgets.
 */
final class Theme_Context {

	/**
	 * The post dynamic widgets refer to: the queried post or the loop item.
	 * Returns null for Uncoder templates themselves (a template is never "the content").
	 */
	public static function post( Render_Context $ctx ): ?\WP_Post {
		$id   = $ctx->post_id ? $ctx->post_id : (int) get_the_ID();
		$post = $id ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || Post_Types::TEMPLATE === $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * Runs a callback with the global $post switched to $post, then restores the previous post.
	 *
	 * @param callable $callback Callback.
	 * @return mixed Callback result.
	 */
	public static function with_post( \WP_Post $post, callable $callback ) {
		$previous        = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
		setup_postdata( $post );
		try {
			return $callback();
		} finally {
			$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $previous instanceof \WP_Post ) {
				setup_postdata( $previous );
			}
		}
	}

	/**
	 * Date format options shared by date-aware widgets.
	 *
	 * @return array<string,string>
	 */
	public static function date_formats(): array {
		return array(
			''       => __( 'Site default', 'uncoder' ),
			'F j, Y' => 'March 6, 2026',
			'M j, Y' => 'Mar 6, 2026',
			'j F Y'  => '6 March 2026',
			'Y-m-d'  => '2026-03-06',
			'm/d/Y'  => '03/06/2026',
			'd/m/Y'  => '06/03/2026',
			'd.m.Y'  => '06.03.2026',
			'human'  => __( 'Time ago', 'uncoder' ),
			'custom' => __( 'Custom', 'uncoder' ),
		);
	}

	/**
	 * Formats a post date (published or modified) with a date_formats() key.
	 */
	public static function post_date( ?\WP_Post $post, string $format, string $custom = '', bool $modified = false, bool $time = false ): string {
		if ( 'custom' === $format ) {
			$format = '' !== trim( $custom ) ? $custom : '';
		}
		$default = (string) get_option( $time ? 'time_format' : 'date_format' );
		if ( ! $post ) {
			if ( 'human' === $format ) {
				/* translators: %s: human time difference, e.g. "2 hours". */
				return sprintf( __( '%s ago', 'uncoder' ), human_time_diff( time() - 2 * DAY_IN_SECONDS, time() ) );
			}
			return wp_date( '' !== $format ? $format : $default );
		}
		if ( 'human' === $format ) {
			$stamp = $modified ? (int) get_post_modified_time( 'U', true, $post ) : (int) get_post_time( 'U', true, $post );
			/* translators: %s: human time difference, e.g. "2 hours". */
			return sprintf( __( '%s ago', 'uncoder' ), human_time_diff( $stamp, time() ) );
		}
		$format = '' !== $format ? $format : $default;
		if ( $time ) {
			return (string) ( $modified ? get_the_modified_time( $format, $post ) : get_the_time( $format, $post ) );
		}
		return (string) ( $modified ? get_the_modified_date( $format, $post ) : get_the_date( $format, $post ) );
	}

	/**
	 * Plain text of post HTML: blocks and shortcodes removed, block boundaries kept as spaces.
	 */
	public static function plain_text( string $html ): string {
		if ( function_exists( 'excerpt_remove_blocks' ) ) {
			$html = excerpt_remove_blocks( $html );
		}
		$html = strip_shortcodes( $html );
		$html = (string) preg_replace( '#<br\s*/?>|</(p|h[1-6]|li|dt|dd|div|blockquote|figcaption|pre|td|th|section|article)>#i', ' ', $html );
		$text = wp_strip_all_tags( $html, true );
		return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
	}

	/**
	 * Estimated reading time in minutes.
	 */
	public static function reading_minutes( \WP_Post $post, int $wpm = 200 ): int {
		$text  = self::plain_text( (string) $post->post_content );
		$words = '' === $text ? 0 : count( (array) preg_split( '/\s+/u', $text ) );
		return max( 1, (int) ceil( $words / max( 50, $wpm ) ) );
	}

	/**
	 * Image size options (registered sizes + full).
	 *
	 * @return array<string,string>
	 */
	public static function image_sizes(): array {
		$out = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			$out[ $size ] = ucwords( str_replace( array( '_', '-' ), ' ', $size ) );
		}
		$out['full'] = __( 'Full', 'uncoder' );
		return $out;
	}

	/**
	 * Public taxonomies as options.
	 *
	 * @return array<string,string>
	 */
	public static function taxonomies(): array {
		$out = array(
			'category' => __( 'Categories', 'uncoder' ),
			'post_tag' => __( 'Tags', 'uncoder' ),
		);
		if ( did_action( 'init' ) ) {
			foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
				if ( 'post_format' !== $tax->name ) {
					$out[ $tax->name ] = $tax->labels->name;
				}
			}
		}
		return $out;
	}

	/**
	 * Absolute URL of the current request (for redirects back to the same page).
	 */
	public static function current_url(): string {
		if ( is_singular() ) {
			$url = get_permalink( (int) get_queried_object_id() );
			if ( $url ) {
				return $url;
			}
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $uri || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return home_url( '/' );
		}
		$home = wp_parse_url( home_url() );
		if ( empty( $home['host'] ) ) {
			return home_url( '/' );
		}
		$origin = ( is_ssl() ? 'https' : ( $home['scheme'] ?? 'http' ) ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . (int) $home['port'] : '' );
		return esc_url_raw( $origin . '/' . ltrim( $uri, '/' ) );
	}

	/**
	 * Sample content used by editor placeholders.
	 *
	 * @return array<string,string>
	 */
	public static function sample(): array {
		return array(
			'title'    => __( 'Designing calmer interfaces for busy people', 'uncoder' ),
			'excerpt'  => __( 'A short summary of the article appears here. It gives readers a clear idea of what the post covers and why it is worth their time.', 'uncoder' ),
			'author'   => __( 'Jane Cooper', 'uncoder' ),
			'bio'      => __( 'Jane writes about product design, accessibility and the small details that make software feel effortless.', 'uncoder' ),
			'category' => __( 'Design', 'uncoder' ),
			'tag'      => __( 'Accessibility', 'uncoder' ),
			'archive'  => __( 'Category: Design', 'uncoder' ),
			'desc'     => __( 'Articles, case studies and notes about product design and the craft of building for the web.', 'uncoder' ),
			'prev'     => __( 'What we learned from a year of remote workshops', 'uncoder' ),
			'next'     => __( 'A practical guide to accessible colour palettes', 'uncoder' ),
		);
	}
}
