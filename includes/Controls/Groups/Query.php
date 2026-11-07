<?php
/**
 * Posts query group (used by Posts, Loop Grid, Loop Carousel, Portfolio…).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Groups;

use Uncoder\Builder\Controls\Group_Type;
use Uncoder\Builder\Core\Css\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * { source, post_type, posts_per_page, offset, orderby, order, include_ids, exclude_ids, terms, authors,
 *   meta_key, meta_value, ignore_sticky, exclude_current, avoid_duplicates, product_filter }.
 * meta_key / meta_value: only posts whose custom field has that value (public fields only; "!value" for "is not").
 * WooCommerce products: product_filter (featured, sale, best_selling, top_rated, in_stock), ordering by price,
 * sales or rating, and WooCommerce's catalog visibility (hidden products, "hide out of stock items").
 */
class Query extends Group_Type {

	public function name(): string {
		return 'query';
	}

	public function fields( array $control ): array {
		return array(
			'source'           => array(
				'type'    => 'select',
				'label'   => __( 'Source', 'uncoder' ),
				'options' => array(
					'posts'   => __( 'Custom query', 'uncoder' ),
					'current' => __( 'Current query (archives)', 'uncoder' ),
					'related' => __( 'Related to current post', 'uncoder' ),
					'manual'  => __( 'Manual selection', 'uncoder' ),
				),
			),
			'post_type'        => array( 'type' => 'select', 'label' => __( 'Post type', 'uncoder' ), 'options_dynamic' => true, 'options' => array() ),
			'product_filter'   => array(
				'type'      => 'select',
				'label'     => __( 'Products', 'uncoder' ),
				'options'   => array(
					''             => __( 'All products', 'uncoder' ),
					'featured'     => __( 'Featured', 'uncoder' ),
					'sale'         => __( 'On sale', 'uncoder' ),
					'best_selling' => __( 'Best selling', 'uncoder' ),
					'top_rated'    => __( 'Top rated', 'uncoder' ),
					'in_stock'     => __( 'In stock', 'uncoder' ),
				),
				'condition' => array( 'post_type' => 'product' ),
			),
			'posts_per_page'   => array( 'type' => 'number', 'label' => __( 'Items', 'uncoder' ), 'min' => 1, 'max' => 100 ),
			'offset'           => array( 'type' => 'number', 'label' => __( 'Offset', 'uncoder' ), 'min' => 0, 'max' => 1000 ),
			'orderby'          => array(
				'type'    => 'select',
				'label'   => __( 'Order by', 'uncoder' ),
				'options' => array(
					'date'          => 'Date',
					'modified'      => 'Last modified',
					'title'         => 'Title',
					'menu_order'    => 'Menu order',
					'comment_count' => 'Comments',
					'rand'          => 'Random',
					'post__in'      => 'Manual order',
					'price'         => 'Price (products)',
					'popularity'    => 'Sales (products)',
					'rating'        => 'Rating (products)',
				),
			),
			'order'            => array( 'type' => 'select', 'label' => __( 'Order', 'uncoder' ), 'options' => array( 'desc' => 'Descending', 'asc' => 'Ascending' ) ),
			'include_ids'      => array( 'type' => 'multiselect', 'label' => __( 'Include', 'uncoder' ), 'source' => 'posts' ),
			'exclude_ids'      => array( 'type' => 'multiselect', 'label' => __( 'Exclude', 'uncoder' ), 'source' => 'posts' ),
			'terms'            => array( 'type' => 'multiselect', 'label' => __( 'Terms (taxonomy:id)', 'uncoder' ), 'source' => 'terms' ),
			'authors'          => array( 'type' => 'multiselect', 'label' => __( 'Authors', 'uncoder' ), 'source' => 'users' ),
			'meta_key'         => array(
				'type'        => 'text',
				'label'       => __( 'Custom field', 'uncoder' ),
				'placeholder' => 'consultant',
				'description' => __( 'Only posts whose custom field (this key) has the value below.', 'uncoder' ),
			),
			'meta_value'       => array(
				'type'        => 'text',
				'label'       => __( 'Field value', 'uncoder' ),
				'placeholder' => 'nadia-faulkner',
				'description' => __( 'Start with ! for "is not": !filled.', 'uncoder' ),
			),
			'ignore_sticky'    => array( 'type' => 'switch', 'label' => __( 'Ignore sticky posts', 'uncoder' ) ),
			'exclude_current'  => array( 'type' => 'switch', 'label' => __( 'Exclude current post', 'uncoder' ) ),
			'avoid_duplicates' => array( 'type' => 'switch', 'label' => __( 'Avoid duplicates', 'uncoder' ) ),
		);
	}

	public function group_css( array $value, array $control, string $selector, Rules $rules ): void {}

	protected function declarations( array $value, string $device, array $control ): array {
		return array();
	}

	/**
	 * Builds WP_Query args from a query value.
	 *
	 * @param array<string,mixed> $q Query group value.
	 * @return array<string,mixed>|null Null means "use the main query".
	 */
	public static function to_wp_query_args( array $q, int $current_post = 0 ): ?array {
		$source = $q['source'] ?? 'posts';
		if ( 'current' === $source ) {
			return null;
		}
		$post_type = sanitize_key( (string) ( $q['post_type'] ?? 'post' ) );
		// Only post types visitors can view are listed (never Uncoder templates, blocks or plugin-internal types).
		$type_obj = '' !== $post_type ? get_post_type_object( $post_type ) : null;
		if ( ! $type_obj || ! is_post_type_viewable( $type_obj ) || \Uncoder\Builder\Core\Post_Types::TEMPLATE === $post_type ) {
			$post_type = 'post';
		}
		$args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 100, (int) ( $q['posts_per_page'] ?? 6 ) ) ),
			'orderby'             => (string) ( $q['orderby'] ?? 'date' ),
			'order'               => 'asc' === ( $q['order'] ?? 'desc' ) ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => ! empty( $q['ignore_sticky'] ),
			'no_found_rows'       => false,
		);
		if ( ! empty( $q['offset'] ) ) {
			$args['offset'] = (int) $q['offset'];
		}
		if ( 'manual' === $source ) {
			$ids               = array_map( 'absint', (array) ( $q['include_ids'] ?? array() ) );
			$args['post__in']  = $ids ? $ids : array( 0 );
			$args['orderby']   = 'post__in';
			$args['post_type'] = 'any';
		} elseif ( ! empty( $q['include_ids'] ) ) {
			$args['post__in'] = array_map( 'absint', (array) $q['include_ids'] );
		}
		$exclude = array_map( 'absint', (array) ( $q['exclude_ids'] ?? array() ) );
		if ( ! empty( $q['exclude_current'] ) && $current_post ) {
			$exclude[] = $current_post;
		}
		if ( $exclude ) {
			$args['post__not_in'] = array_values( array_unique( $exclude ) );
		}
		if ( ! empty( $q['authors'] ) ) {
			$args['author__in'] = array_map( 'absint', (array) $q['authors'] );
		}
		$tax_query = array();
		foreach ( (array) ( $q['terms'] ?? array() ) as $term ) {
			$parts = explode( ':', (string) $term );
			if ( 2 === count( $parts ) && taxonomy_exists( $parts[0] ) ) {
				$tax_query[ $parts[0] ][] = absint( $parts[1] );
			}
		}
		// One custom field condition: equal to the value, or (with a leading "!") not equal; private fields never.
		$meta_key = sanitize_key( (string) ( $q['meta_key'] ?? '' ) );
		if ( '' !== $meta_key && ! is_protected_meta( $meta_key, 'post' ) ) {
			$value = trim( (string) ( $q['meta_value'] ?? '' ) );
			$not   = 0 === strpos( $value, '!' );
			$value = sanitize_text_field( $not ? substr( $value, 1 ) : $value );
			// "Is not" keeps the posts that do not have the field at all.
			$args['meta_query'] = $not // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				? array(
					'relation' => 'OR',
					array(
						'key'     => $meta_key,
						'value'   => $value,
						'compare' => '!=',
					),
					array(
						'key'     => $meta_key,
						'compare' => 'NOT EXISTS',
					),
				)
				: array(
					array(
						'key'   => $meta_key,
						'value' => $value,
					),
				);
		}
		if ( 'related' === $source && $current_post ) {
			foreach ( get_object_taxonomies( get_post_type( $current_post ) ) as $tax ) {
				$ids = wp_get_post_terms( $current_post, $tax, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $ids ) && $ids && is_taxonomy_hierarchical( $tax ) ) {
					$tax_query[ $tax ] = array_merge( $tax_query[ $tax ] ?? array(), $ids );
				}
			}
			$args['post__not_in'][] = $current_post;
			$args['post_type']      = get_post_type( $current_post );
		}
		if ( $tax_query ) {
			$args['tax_query'] = array( 'relation' => 'related' === $source ? 'OR' : 'AND' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			foreach ( $tax_query as $tax => $ids ) {
				$args['tax_query'][] = array(
					'taxonomy' => $tax,
					'field'    => 'term_id',
					'terms'    => array_values( array_unique( $ids ) ),
				);
			}
		}
		if ( in_array( $args['orderby'], array( 'price', 'popularity', 'rating' ), true ) && 'product' !== $args['post_type'] ) {
			$args['orderby'] = 'date';
		}
		if ( 'product' === $args['post_type'] && function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			$args = self::product_args( $args, $q );
		}
		/**
		 * Filters the WP_Query arguments built from a query control.
		 *
		 * @param array $args WP_Query args.
		 * @param array $q    Query control value.
		 */
		return apply_filters( 'uncoder_wb/query/args', $args, $q );
	}

	/**
	 * WooCommerce rules for product queries: catalog visibility, the product filter and ordering by price, sales or
	 * rating. Ordering reads WooCommerce's own meta (_price, total_sales, _wc_average_rating), so nothing is added to
	 * other queries of the page.
	 *
	 * @param array<string,mixed> $args WP_Query args.
	 * @param array<string,mixed> $q    Query control value.
	 * @return array<string,mixed>
	 */
	private static function product_args( array $args, array $q ): array {
		$filter     = (string) ( $q['product_filter'] ?? '' );
		$visibility = wc_get_product_visibility_term_ids();
		$hidden     = array( (int) ( $visibility['exclude-from-catalog'] ?? 0 ) );
		if ( 'in_stock' === $filter || 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$hidden[] = (int) ( $visibility['outofstock'] ?? 0 );
		}
		$hidden = array_values( array_filter( $hidden ) );
		$rules  = $hidden ? array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => $hidden,
				'operator' => 'NOT IN',
			),
		) : array();
		if ( 'featured' === $filter && ! empty( $visibility['featured'] ) ) {
			$rules[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( (int) $visibility['featured'] ),
			);
		}
		// Keep the terms already asked for (an OR list for related products) as one condition beside these.
		$existing          = $args['tax_query'] ?? array();
		$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $existing ? array( $existing ) : array(), $rules ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query

		if ( 'sale' === $filter ) {
			$on_sale          = array_map( 'intval', wc_get_product_ids_on_sale() );
			$on_sale          = isset( $args['post__in'] ) ? array_intersect( (array) $args['post__in'], $on_sale ) : $on_sale;
			$args['post__in'] = $on_sale ? array_values( $on_sale ) : array( 0 );
		}

		$orderby = (string) $args['orderby'];
		if ( 'best_selling' === $filter ) {
			$orderby = 'popularity';
		} elseif ( 'top_rated' === $filter ) {
			$orderby = 'rating';
		}
		if ( in_array( $orderby, array( 'price', 'popularity', 'rating' ), true ) ) {
			$direction = 'price' === $orderby ? (string) $args['order'] : ( 'best_selling' === $filter || 'top_rated' === $filter ? 'DESC' : (string) $args['order'] );
			// Sorted on WooCommerce's product lookup table, as its shop sorting is (see product_order_clauses()).
			$args['uncoder_wb_product_order'] = array( $orderby, strtoupper( $direction ) );
			$args['orderby']                  = array( 'date' => 'DESC' );
			if ( ! has_filter( 'posts_clauses', array( self::class, 'product_order_clauses' ) ) ) {
				add_filter( 'posts_clauses', array( self::class, 'product_order_clauses' ), 10, 2 );
			}
		}
		return $args;
	}

	/**
	 * Price, sales and rating order from WooCommerce's product lookup table (one row per product): variable and
	 * grouped products have several "_price" meta rows, and a product without a sales or rating meta row would drop
	 * out of a meta_key query. Price: the lowest price going up, the highest going down, as WooCommerce sorts.
	 *
	 * @param array<string,string> $clauses Query clauses.
	 * @return array<string,string>
	 */
	public static function product_order_clauses( $clauses, $query ): array {
		$clauses = (array) $clauses;
		$order   = $query instanceof \WP_Query ? $query->get( 'uncoder_wb_product_order' ) : null;
		if ( ! is_array( $order ) || 2 !== count( $order ) ) {
			return $clauses;
		}
		global $wpdb;
		list( $by, $dir ) = array_values( $order );
		$dir              = 'ASC' === $dir ? 'ASC' : 'DESC';
		$columns          = array(
			'price'      => 'ASC' === $dir ? 'min_price' : 'max_price',
			'popularity' => 'total_sales',
			'rating'     => 'average_rating',
		);
		if ( ! isset( $columns[ $by ] ) || empty( $wpdb->wc_product_meta_lookup ) ) {
			return $clauses;
		}
		$clauses['join']   .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} uncoder_wb_lookup ON {$wpdb->posts}.ID = uncoder_wb_lookup.product_id ";
		$then               = 'rating' === $by ? "uncoder_wb_lookup.rating_count {$dir}, " : '';
		$clauses['orderby'] = "uncoder_wb_lookup.{$columns[ $by ]} {$dir}, {$then}{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
		return $clauses;
	}
}
