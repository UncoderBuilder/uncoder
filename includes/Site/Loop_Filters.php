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
 * bookmarked and work without JavaScript: uf-{key}-{taxonomy}=slug,slug · uf-{key}-f_{field}=a,b (a custom
 * field, one of) · uf-{key}-min_{field}=40000 / max_ (a number at least / at most) · uf-{key}-s=words ·
 * uf-{key}-sort=title_asc. The key is the grid's CSS ID (or "loop"), which the Loop Filter widget
 * targets. Only public taxonomies of the queried post type, public custom fields and the listed sort orders
 * are accepted. Every filter narrows the others (AND); several choices in one filter widen it (OR).
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
	 * The custom field filters active in the URL for a grid: uf-{key}-f_{field} (one of the values),
	 * uf-{key}-min_{field} / uf-{key}-max_{field} (a number at least / at most). Private fields (a leading
	 * underscore) never filter.
	 *
	 * @return array<int, array{0:string,1:string}> [mode, field] pairs.
	 */
	public static function field_filters( string $key ): array {
		$out    = array();
		$prefix = 'uf-' . $key . '-';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only filtering; names are matched below.
		foreach ( array_keys( $_GET ) as $param ) {
			if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . '(f|min|max)_([a-z0-9_\-]{1,64})$/', (string) $param, $m ) && ! is_protected_meta( $m[2], 'post' ) ) {
				$out[] = array( $m[1], $m[2] );
			}
			if ( count( $out ) >= 10 ) {
				break;
			}
		}
		return $out;
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
		$meta = array();
		foreach ( self::field_filters( $key ) as list( $mode, $field ) ) {
			$values = self::values( $key, $mode . '_' . $field );
			if ( ! $values ) {
				continue;
			}
			if ( 'f' === $mode ) {
				$meta[] = array(
					'key'     => $field,
					'value'   => $values,
					'compare' => 'IN',
				);
			} elseif ( is_numeric( $values[0] ) ) {
				$meta[] = array(
					'key'     => $field,
					'value'   => (float) $values[0],
					'compare' => 'min' === $mode ? '>=' : '<=',
					'type'    => 'NUMERIC',
				);
			}
		}
		if ( $meta ) {
			$existing           = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? array( $args['meta_query'] ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $existing, $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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
