<?php
/**
 * Autocomplete sources for editor selects (posts, terms, users, menus, templates, post types).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET /lookup/{source}?search=&ids=
 */
final class Lookup_Controller {

	public function register_routes(): void {
		register_rest_route(
			Rest::NS,
			'/lookup/(?P<source>[a-z_\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'lookup' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
	}

	public function lookup( WP_REST_Request $request ): WP_REST_Response {
		$source = (string) $request['source'];
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$ids    = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ) );
		$type   = sanitize_key( (string) $request->get_param( 'type' ) );
		$out    = array();

		// "templates-{type}" (e.g. templates-loop-item) = templates of one type, for selects that cannot pass ?type=.
		// The full list starts with an empty choice so optional template selects can be cleared.
		if ( 0 === strpos( $source, 'templates-' ) && isset( Post_Types::TEMPLATE_TYPES[ substr( $source, 10 ) ] ) ) {
			$type   = substr( $source, 10 );
			$source = 'templates';
			if ( ! $ids && '' === $search ) {
				$out[] = array(
					'value' => '',
					'label' => __( '— None —', 'uncoder' ),
				);
			}
		}

		switch ( $source ) {
			case 'posts':
			case 'popups':
			case 'templates':
				$args = array(
					'post_type'      => 'posts' === $source ? ( '' !== $type && post_type_exists( $type ) && is_post_type_viewable( $type ) ? $type : 'any' ) : Post_Types::TEMPLATE,
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'posts_per_page' => 20,
					's'              => $search,
					'orderby'        => 'title',
					'order'          => 'ASC',
				);
				if ( 'popups' === $source ) {
					$args['meta_query'] = array( array( 'key' => Utils::META_TYPE, 'value' => 'popup' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				} elseif ( 'templates' === $source && '' !== $type ) {
					$args['meta_query'] = array( array( 'key' => Utils::META_TYPE, 'value' => $type ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					if ( 'section' === $type ) {
						// Templates without a type are sections (Document::type()).
						$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'OR',
							$args['meta_query'][0],
							array(
								'key'     => Utils::META_TYPE,
								'compare' => 'NOT EXISTS',
							),
						);
					}
				}
				if ( $ids ) {
					$args['post__in']       = $ids;
					$args['posts_per_page'] = count( $ids );
					unset( $args['s'] );
				}
				foreach ( get_posts( $args ) as $post ) {
					if ( ! current_user_can( 'read_post', $post->ID ) ) {
						continue;
					}
					$out[] = array(
						'value' => (string) $post->ID,
						'label' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) . ( 'publish' !== $post->post_status ? ' (' . $post->post_status . ')' : '' ),
						'type'  => $post->post_type,
						'url'   => Post_Types::TEMPLATE === $post->post_type ? '' : (string) get_permalink( $post ),
					);
				}
				break;
			case 'terms':
				$taxonomies = get_taxonomies( array( 'public' => true ) );
				$args       = array(
					'taxonomy'   => array_values( $taxonomies ),
					'hide_empty' => false,
					'number'     => 30,
					'search'     => $search,
				);
				$terms = get_terms( $args );
				foreach ( is_array( $terms ) ? $terms : array() as $term ) {
					$out[] = array(
						'value' => $term->taxonomy . ':' . $term->term_id,
						'label' => $term->name . ' (' . $term->taxonomy . ')',
					);
				}
				break;
			case 'users':
				if ( ! current_user_can( 'list_users' ) ) {
					break;
				}
				foreach ( get_users( array( 'search' => '*' . $search . '*', 'number' => 20, 'fields' => array( 'ID', 'display_name' ) ) ) as $user ) {
					$out[] = array(
						'value' => (string) $user->ID,
						'label' => $user->display_name,
					);
				}
				break;
			case 'menus':
				foreach ( wp_get_nav_menus() as $menu ) {
					$out[] = array(
						'value' => (string) $menu->term_id,
						'label' => $menu->name,
					);
				}
				break;
			case 'post_types':
				foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
					if ( Post_Types::TEMPLATE === $pt->name || 'attachment' === $pt->name ) {
						continue;
					}
					$out[] = array(
						'value' => $pt->name,
						'label' => $pt->labels->singular_name,
					);
				}
				break;
			case 'taxonomies':
				foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
					$out[] = array(
						'value' => $tax->name,
						'label' => $tax->labels->singular_name,
					);
				}
				break;
		}
		return new WP_REST_Response( $out );
	}
}
