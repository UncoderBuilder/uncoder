<?php
/**
 * Live filters for Loop Grid / Posts widgets (Loop Filter widget + URL parameters).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Widgets\Posts;

defined( 'ABSPATH' ) || exit;

/**
 * A grid's filters live in the URL as uf-{key}-{name} parameters, so filtered views can be shared,
 * bookmarked and work without JavaScript: uf-{key}-{taxonomy}=slug,slug · uf-{key}-s=words ·
 * uf-{key}-sort=title_asc. The key is the grid's CSS ID (or "loop"), which the Loop Filter widget
 * targets. Only public taxonomies of the queried post type and the listed sort orders are accepted.
 */
final class Loop_Filters {

	public const SORTS = array(
		'date_desc'     => array( 'date', 'DESC' ),
		'date_asc'      => array( 'date', 'ASC' ),
		'title_asc'     => array( 'title', 'ASC' ),
		'title_desc'    => array( 'title', 'DESC' ),
		'modified_desc' => array( 'modified', 'DESC' ),
		'comments_desc' => array( 'comment_count', 'DESC' ),
		'menu_order'    => array( 'menu_order', 'ASC' ),
	);

	/**
	 * The filter key of a grid: its CSS ID, or "loop".
	 *
	 * @param array<string,mixed> $s Widget settings.
	 */
	public static function key( array $s ): string {
		$key = sanitize_key( (string) ( $s['_css_id'] ?? '' ) );
		return '' !== $key ? $key : 'loop';
	}

	public static function param( string $key, string $name ): string {
		return 'uf-' . $key . '-' . $name;
	}

	/**
	 * Raw value of a filter parameter (string, or list for checkboxes).
	 *
	 * @return string[]
	 */
	public static function values( string $key, string $name ): array {
		$param = self::param( $key, $name );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- public read-only filtering; each item is sanitized below.
		$raw = isset( $_GET[ $param ] ) ? wp_unslash( $_GET[ $param ] ) : null;
		if ( null === $raw ) {
			return array();
		}
		$raw  = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$list = array();
		foreach ( array_slice( $raw, 0, 30 ) as $item ) {
			if ( is_scalar( $item ) ) {
				$item = sanitize_text_field( (string) $item );
				if ( '' !== $item ) {
					$list[] = $item;
				}
			}
		}
		return $list;
	}

	/**
	 * Adds the active filters of a grid to its WP_Query args.
	 *
	 * @param array<string,mixed> $args WP_Query args.
	 * @return array<string,mixed>
	 */
	public static function apply( array $args, string $key ): array {
		$types = (array) ( $args['post_type'] ?? 'post' );
		foreach ( get_object_taxonomies( 'any' === $types[0] ? 'post' : $types, 'objects' ) as $tax ) {
			if ( ! $tax->public ) {
				continue;
			}
			$slugs = array_map( 'sanitize_title', self::values( $key, $tax->name ) );
			if ( $slugs ) {
				$args['tax_query']   = isset( $args['tax_query'] ) ? $args['tax_query'] : array( 'relation' => 'AND' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				$args['tax_query'][] = array(
					'taxonomy' => $tax->name,
					'field'    => 'slug',
					'terms'    => $slugs,
				);
			}
		}
		$search = self::values( $key, 's' );
		if ( $search ) {
			$args['s'] = mb_substr( implode( ' ', $search ), 0, 100 );
		}
		$sort = self::values( $key, 'sort' );
		if ( $sort && isset( self::SORTS[ $sort[0] ] ) ) {
			$args['orderby'] = self::SORTS[ $sort[0] ][0];
			$args['order']   = self::SORTS[ $sort[0] ][1];
		}
		return $args;
	}

	/**
	 * Current URL with some parameters changed ('' or [] removes one); always back to page 1.
	 *
	 * @param array<string, string|string[]> $changes Parameter => value.
	 */
	public static function url( array $changes ): string {
		$url = remove_query_arg( Posts::PAGE_ARG );
		foreach ( $changes as $param => $value ) {
			$value = is_array( $value ) ? implode( ',', $value ) : (string) $value;
			$url   = '' === $value ? remove_query_arg( $param, $url ) : add_query_arg( $param, rawurlencode( $value ), $url );
		}
		// Pretty archive pagination (/page/3/) starts over too.
		return (string) preg_replace( '#/page/\d+/?(?=\?|$)#', '/', $url );
	}
}
