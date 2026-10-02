<?php
/**
 * Query loop on a container (Content → Query loop).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Controls\Groups\Query;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * A container with `_loop` on repeats once per item (like Bricks): posts from the Query group, or taxonomy
 * terms. Renderer::render_loop() prints each copy with the item as context (dynamic tags, Theme_Context).
 */
final class Container_Loop {

	public const TERM_ORDERBY = array( 'name', 'count', 'term_order', 'id' );

	/**
	 * The items to repeat over: [ ['post' => WP_Post] | ['term' => WP_Term], … ].
	 *
	 * @param array<string,mixed> $s Container settings (defaults applied).
	 * @return array<int, array<string,mixed>>
	 */
	public static function items( array $s, Render_Context $ctx ): array {
		if ( 'terms' === ( $s['_loop_source'] ?? 'posts' ) ) {
			return self::terms( $s, $ctx );
		}
		$q = is_array( $s['_loop_query'] ?? null ) ? $s['_loop_query'] : array();
		if ( 'current' === ( $q['source'] ?? 'posts' ) ) {
			global $wp_query;
			if ( ! $ctx->editor && $wp_query instanceof \WP_Query && ( $wp_query->is_archive() || $wp_query->is_home() || $wp_query->is_search() ) ) {
				return array_map( static fn( $p ) => array( 'post' => $p ), array_filter( (array) $wp_query->posts, static fn( $p ) => $p instanceof \WP_Post ) );
			}
			// Not an archive request (e.g. a preview): the latest posts stand in.
			$q = array(
				'source'         => 'posts',
				'post_type'      => 'post',
				'posts_per_page' => max( 1, (int) get_option( 'posts_per_page', 10 ) ),
			);
		}
		$current = Theme_Context::post( $ctx );
		$args    = Query::to_wp_query_args( $q, $current ? $current->ID : 0 );
		if ( ! is_array( $args ) ) {
			return array();
		}
		$args['no_found_rows']       = true;
		$args['ignore_sticky_posts'] = $args['ignore_sticky_posts'] ?? true;
		// "Avoid duplicates": skip posts other loops on this page already showed.
		$shown = \Uncoder\Builder\Widgets\Loop_Grid::shown();
		if ( ! empty( $q['avoid_duplicates'] ) && $shown ) {
			$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), $shown ) ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- a handful of ids already on the page.
		}
		$query = new \WP_Query( $args );
		$posts = array_filter( (array) $query->posts, static fn( $p ) => $p instanceof \WP_Post );
		if ( ! $ctx->editor ) {
			\Uncoder\Builder\Widgets\Loop_Grid::mark_shown( array_map( static fn( $p ) => (int) $p->ID, $posts ) );
		}
		return array_map( static fn( $p ) => array( 'post' => $p ), $posts );
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 * @return array<int, array<string,mixed>>
	 */
	private static function terms( array $s, Render_Context $ctx ): array {
		$tax = sanitize_key( (string) ( $s['_loop_taxonomy'] ?? 'category' ) );
		// Only public taxonomies: menus and internal taxonomies must not be listed to visitors.
		if ( ! taxonomy_exists( $tax ) || ! is_taxonomy_viewable( $tax ) ) {
			return array();
		}
		$orderby = (string) ( $s['_loop_terms_orderby'] ?? 'name' );
		$args    = array(
			'taxonomy'   => $tax,
			'number'     => max( 1, min( 100, (int) ( $s['_loop_terms_number'] ?? 12 ) ) ),
			'hide_empty' => ! array_key_exists( '_loop_terms_hide_empty', $s ) || ! empty( $s['_loop_terms_hide_empty'] ),
			'orderby'    => in_array( $orderby, self::TERM_ORDERBY, true ) ? $orderby : 'name',
			'order'      => 'desc' === ( $s['_loop_terms_order'] ?? 'asc' ) ? 'DESC' : 'ASC',
		);
		// "Terms of this post": the categories / tags of the post being shown (or of the loop item).
		if ( 'post' === ( $s['_loop_terms_scope'] ?? 'all' ) ) {
			$post = Theme_Context::post( $ctx );
			if ( ! $post ) {
				return array();
			}
			$args['object_ids'] = $post->ID;
		}
		$terms = get_terms( $args );
		return is_array( $terms ) ? array_values( array_map( static fn( $t ) => array( 'term' => $t ), array_filter( $terms, static fn( $t ) => $t instanceof \WP_Term ) ) ) : array();
	}
}
