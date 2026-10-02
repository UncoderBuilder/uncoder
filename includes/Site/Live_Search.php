<?php
/**
 * Live search results for the Search Form widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Rest\Rest;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET uncoder/v1/live-search?s=… returns the first matching published, public posts (the same content the
 * theme's search page lists), with title, link, type and optionally a thumbnail and a short excerpt.
 */
final class Live_Search {

	public const MAX = 10;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			Rest::NS,
			'/live-search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					's'         => array(
						'type'     => 'string',
						'required' => true,
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => '',
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 5,
					),
					'image'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'excerpt'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/** Public post types the site search covers. */
	private static function types(): array {
		return array_values( array_diff( get_post_types( array( 'public' => true, 'exclude_from_search' => false ) ), array( 'attachment' ) ) );
	}

	public function search( WP_REST_Request $request ): WP_REST_Response {
		$term = trim( wp_strip_all_tags( (string) $request['s'] ) );
		$term = mb_substr( $term, 0, 100 );
		if ( mb_strlen( $term ) < 2 ) {
			return new WP_REST_Response( array( 'results' => array(), 'total' => 0 ) );
		}
		$types = self::types();
		$want  = sanitize_key( (string) $request['post_type'] );
		$type  = '' !== $want && in_array( $want, $types, true ) ? $want : $types;
		$per   = max( 1, min( self::MAX, (int) $request['per_page'] ) );

		$query = new \WP_Query(
			array(
				's'                   => $term,
				'post_type'           => $type,
				'post_status'         => 'publish',
				'has_password'        => false,
				'posts_per_page'      => $per,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => false,
			)
		);
		$image   = rest_sanitize_boolean( $request['image'] );
		$excerpt = rest_sanitize_boolean( $request['excerpt'] );
		$results = array();
		foreach ( $query->posts as $post ) {
			$object = get_post_type_object( $post->post_type );
			$row    = array(
				'id'    => (int) $post->ID,
				'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
				'url'   => (string) get_permalink( $post ),
				'type'  => $object ? (string) $object->labels->singular_name : '',
			);
			if ( '' === $row['title'] ) {
				$row['title'] = __( '(no title)', 'uncoder' );
			}
			if ( $image ) {
				$row['image'] = (string) get_the_post_thumbnail_url( $post, 'thumbnail' );
			}
			if ( $excerpt ) {
				$text           = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
				$row['excerpt'] = html_entity_decode( wp_trim_words( $text, 16, '…' ), ENT_QUOTES, 'UTF-8' );
			}
			$results[] = $row;
		}
		$args = array( 's' => $term );
		if ( is_string( $type ) ) {
			$args['post_type'] = $type;
		}
		$response = new WP_REST_Response(
			array(
				'results' => $results,
				'total'   => (int) $query->found_posts,
				'all'     => add_query_arg( array_map( 'rawurlencode', $args ), home_url( '/' ) ),
			)
		);
		$response->header( 'Cache-Control', 'public, max-age=120' );
		return $response;
	}
}
