<?php
/**
 * Dashboard data for the admin home screen.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Editor\Editor;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Theme\Theme_Builder;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET /overview (counts + recent builder pages), POST /overview/page (new builder page).
 */
final class Overview_Controller {

	private const STATUSES = array( 'publish', 'draft', 'private', 'pending', 'future' );

	public function register_routes(): void {
		register_rest_route(
			Rest::NS,
			'/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/overview/page',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_page' ),
				'permission_callback' => static function () {
					$pto = get_post_type_object( 'page' );
					return $pto && current_user_can( $pto->cap->create_posts );
				},
			)
		);
	}

	/**
	 * Builder-enabled content post types (templates excluded).
	 *
	 * @return string[]
	 */
	private function content_types(): array {
		return array_values( array_diff( Plugin::instance()->documents()->post_types(), array( Post_Types::TEMPLATE ) ) );
	}

	public function overview(): WP_REST_Response {
		$types = $this->content_types();

		$count = 0;
		$items = array();
		if ( $types ) {
			// "editable" over several post types checks a capability no role has (edit_others_multiple_post_types), so it
			// would show everyone only their own pages: only users who cannot edit others' posts get that filter.
			$others = array_filter( $types, static fn( string $t ): bool => ( $o = get_post_type_object( $t ) ) && current_user_can( $o->cap->edit_others_posts ) );
			$query  = new WP_Query(
				array(
					'post_type'              => $types,
					'post_status'            => self::STATUSES,
					'posts_per_page'         => 8,
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'meta_key'               => Utils::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'             => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'update_post_term_cache' => false,
				) + ( count( $others ) === count( $types ) ? array() : array( 'perm' => 'editable' ) )
			);
			$count = (int) $query->found_posts;
			foreach ( $query->posts as $post ) {
				$pto     = get_post_type_object( $post->post_type );
				$items[] = array(
					'id'        => $post->ID,
					'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
					'type'      => $post->post_type,
					'typeLabel' => $pto ? $pto->labels->singular_name : $post->post_type,
					'status'    => $post->post_status,
					'modified'  => Templates_Controller::modified_iso( $post ),
					'editUrl'   => current_user_can( 'edit_post', $post->ID ) ? Editor::url( $post->ID ) : '',
					'viewUrl'   => (string) get_permalink( $post ),
				);
			}
		}

		$active = 0;
		$popups = 0;
		$index  = Theme_Builder::instance() ? Theme_Builder::instance()->index() : array();
		foreach ( $index as $type => $entries ) {
			if ( 'popup' === $type ) {
				$popups += count( (array) $entries );
			} else {
				$active += count( (array) $entries );
			}
		}
		$templates = 0;
		$counts    = wp_count_posts( Post_Types::TEMPLATE );
		foreach ( self::STATUSES as $status ) {
			$templates += (int) ( $counts->$status ?? 0 );
		}
		$sections = count(
			get_posts(
				array(
					'post_type'      => Post_Types::TEMPLATE,
					'post_status'    => self::STATUSES,
					'posts_per_page' => 500,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => Utils::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => 'section', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);

		$unread = null;
		if ( current_user_can( 'edit_pages' ) ) {
			global $wpdb;
			$table  = $wpdb->prefix . 'uncoder_wb_submissions';
			$unread = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, 'unread' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		return new WP_REST_Response(
			array(
				'pages'           => $count,
				'recent'          => $items,
				'templates'       => $templates,
				'activeTemplates' => $active,
				'activePopups'    => $popups,
				'sections'        => $sections,
				'live'            => array(
					'header' => ! empty( $index['header'] ),
					'footer' => ! empty( $index['footer'] ),
				),
				'unread'          => $unread,
				'postTypes'       => $types,
			)
		);
	}

	/**
	 * Body: { title? }. Creates a draft page in builder mode and returns its editor URL.
	 */
	public function create_page( WP_REST_Request $request ) {
		$body  = $request->get_json_params();
		$title = is_array( $body ) ? sanitize_text_field( (string) ( $body['title'] ?? '' ) ) : '';
		if ( ! in_array( 'page', Plugin::instance()->documents()->post_types(), true ) ) {
			return new WP_Error( 'uncoder_disabled', __( 'Uncoder is not enabled for pages. Enable it in Uncoder → Settings.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => 'page',
					'post_title'  => '' !== $title ? $title : __( 'Untitled', 'uncoder' ),
					'post_status' => 'draft',
					'post_author' => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'uncoder_create_failed', $id->get_error_message(), array( 'status' => 500 ) );
		}
		return new WP_REST_Response(
			array(
				'id'      => (int) $id,
				'editUrl' => Editor::url( (int) $id ),
			),
			201
		);
	}
}
