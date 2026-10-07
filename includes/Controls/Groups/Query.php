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
 *   ignore_sticky, exclude_current, avoid_duplicates }.
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
				),
			),
			'order'            => array( 'type' => 'select', 'label' => __( 'Order', 'uncoder' ), 'options' => array( 'desc' => 'Descending', 'asc' => 'Ascending' ) ),
			'include_ids'      => array( 'type' => 'multiselect', 'label' => __( 'Include', 'uncoder' ), 'source' => 'posts' ),
			'exclude_ids'      => array( 'type' => 'multiselect', 'label' => __( 'Exclude', 'uncoder' ), 'source' => 'posts' ),
			'terms'            => array( 'type' => 'multiselect', 'label' => __( 'Terms (taxonomy:id)', 'uncoder' ), 'source' => 'terms' ),
			'authors'          => array( 'type' => 'multiselect', 'label' => __( 'Authors', 'uncoder' ), 'source' => 'users' ),
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
		/**
		 * Filters the WP_Query arguments built from a query control.
		 *
		 * @param array $args WP_Query args.
		 * @param array $q    Query control value.
		 */
		return apply_filters( 'uncoder_wb/query/args', $args, $q );
	}
}
