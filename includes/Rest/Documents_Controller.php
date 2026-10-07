<?php
/**
 * Documents endpoints.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /documents/{id}, POST /documents/{id}/autosave, POST /documents/{id}/lock.
 */
final class Documents_Controller {

	public function register_routes(): void {
		register_rest_route(
			Rest::NS,
			'/documents/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/documents/(?P<id>\d+)/autosave',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'autosave' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/documents/(?P<id>\d+)/lock',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'lock' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/documents/(?P<id>\d+)/state',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'state' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/documents/(?P<id>\d+)/revisions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'revisions' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/documents/(?P<id>\d+)/revisions/(?P<rev>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'revision' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
	}

	/**
	 * Post locking helpers live in wp-admin/includes/post.php (not loaded for REST requests).
	 */
	private static function load_lock_api(): void {
		if ( ! function_exists( 'wp_set_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}
	}

	public function can_edit( WP_REST_Request $request ): bool {
		self::load_lock_api();
		$id = (int) $request['id'];
		return $id > 0 && current_user_can( 'edit_post', $id ) && Plugin::instance()->documents()->is_supported( $id ) && \Uncoder\Builder\Site\Role_Manager::can_use();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function payload( int $id ): array {
		$doc  = Plugin::instance()->documents()->get( $id );
		$post = get_post( $id );
		return array(
			'id'           => $id,
			'title'        => $post->post_title,
			'status'       => $post->post_status,
			'type'         => $post->post_type,
			'docType'      => $doc->type(),
			'modified'     => get_post_modified_time( 'U', true, $post ),
			'rev'          => $doc->rev()['id'],
			'permalink'    => get_permalink( $post ),
			'elements'     => $doc->elements(),
			'pageSettings' => (object) $doc->page_settings(),
		);
	}

	public function get( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->payload( (int) $request['id'] ) );
	}

	/**
	 * Cheap poll for open editors: the document revision stamp and the Design System version.
	 */
	public function state( WP_REST_Request $request ): WP_REST_Response {
		$id  = (int) $request['id'];
		$rev = Plugin::instance()->documents()->get( $id )->rev();
		$res = new WP_REST_Response(
			array(
				'rev'  => $rev['id'],
				'by'   => $rev['by'],
				'self' => $rev['user'] === get_current_user_id() && 'editor' === $rev['by'],
				'time' => $rev['time'],
				'kit'  => Plugin::instance()->kit()->version(),
			)
		);
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/**
	 * Body: { elements, pageSettings?, title?, status?, pageTemplate? }
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		$doc  = Plugin::instance()->documents()->get( $id );
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'uncoder_bad_request', __( 'Invalid JSON body.', 'uncoder' ), array( 'status' => 400 ) );
		}

		$update = array( 'ID' => $id );
		if ( isset( $body['title'] ) && is_string( $body['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $body['title'] );
		}
		if ( isset( $body['status'] ) && is_string( $body['status'] ) ) {
			$status = sanitize_key( $body['status'] );
			if ( ! in_array( $status, array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
				return new WP_Error( 'uncoder_bad_status', __( 'Invalid status.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$pto = get_post_type_object( $post->post_type );
			if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
				$status = 'pending';
			}
			$update['post_status'] = $status;
		}
		if ( isset( $body['pageTemplate'] ) && is_string( $body['pageTemplate'] ) && ! \Uncoder\Builder\Site\Role_Manager::content_only() ) {
			$template = sanitize_text_field( $body['pageTemplate'] );
			$allowed  = array_merge( array( '', 'default' ), array_keys( wp_get_theme()->get_page_templates( $post, $post->post_type ) ), array( 'uncoder-canvas', 'uncoder-full-width' ) );
			if ( in_array( $template, $allowed, true ) ) {
				update_post_meta( $id, '_wp_page_template', '' === $template ? 'default' : $template );
			}
		}

		$content_only = \Uncoder\Builder\Site\Role_Manager::content_only();
		if ( $content_only && array_key_exists( 'elements', $body ) ) {
			$problem = \Uncoder\Builder\Site\Role_Manager::check_content_only( $doc->elements(), $body['elements'] );
			if ( $problem ) {
				return new WP_Error( 'uncoder_content_only', $problem, array( 'status' => 403 ) );
			}
		}
		$result = array( 'errors' => array() );
		if ( array_key_exists( 'elements', $body ) ) {
			$result = $doc->save( $body['elements'], array( 'content_fallback' => true ) );
			if ( false === ( $result['saved'] ?? true ) ) {
				return new WP_Error( 'uncoder_not_saved', implode( ' ', $result['errors'] ), array( 'status' => 422 ) );
			}
		}
		// Page settings (layout, custom CSS, header behaviour) are design: content-only roles keep them.
		if ( ! $content_only && isset( $body['pageSettings'] ) && is_array( $body['pageSettings'] ) ) {
			$doc->save_page_settings( $body['pageSettings'] );
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( wp_slash( $update ) );
		}
		// A real revision including builder meta (WP 6.4+ copies it via wp_post_revision_meta_keys).
		if ( wp_revisions_enabled( get_post( $id ) ) ) {
			wp_save_post_revision( $id );
		}
		wp_set_post_lock( $id );

		$payload             = $this->payload( $id );
		$payload['warnings'] = $result['errors'];
		return new WP_REST_Response( $payload );
	}

	/**
	 * Stores an autosave copy without touching the live document.
	 */
	public function autosave( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request['id'];
		$body = $request->get_json_params();
		if ( is_array( $body ) && isset( $body['elements'] ) && is_array( $body['elements'] ) ) {
			// Sanitized like a save, so a restored autosave can never contain more than the user may write.
			$tree = new \Uncoder\Builder\Core\Tree( 'sanitize' );
			update_post_meta(
				$id,
				'_uncoder_wb_autosave',
				wp_slash(
					array(
						'time'     => time(),
						'user'     => get_current_user_id(),
						'elements' => wp_json_encode( $tree->process( $body['elements'] ) ),
					)
				)
			);
		}
		wp_set_post_lock( $id );
		return new WP_REST_Response( array( 'saved' => time() ) );
	}

	public function lock( WP_REST_Request $request ): WP_REST_Response {
		$id    = (int) $request['id'];
		$owner = wp_check_post_lock( $id );
		if ( $owner ) {
			$user = get_userdata( $owner );
			return new WP_REST_Response(
				array(
					'locked' => true,
					'user'   => $user ? $user->display_name : '',
				)
			);
		}
		wp_set_post_lock( $id );
		return new WP_REST_Response( array( 'locked' => false ) );
	}

	public function revisions( WP_REST_Request $request ): WP_REST_Response {
		$id  = (int) $request['id'];
		$out = array();
		foreach ( wp_get_post_revisions( $id, array( 'posts_per_page' => 30 ) ) as $revision ) {
			$user  = get_userdata( (int) $revision->post_author );
			$out[] = array(
				'id'     => $revision->ID,
				'date'   => get_post_time( 'U', true, $revision ),
				'author' => $user ? $user->display_name : '',
				'isAi'   => (bool) get_metadata( 'post', $revision->ID, '_uncoder_wb_ai_change', true ),
				'count'  => self::count_nodes( self::revision_elements( $revision->ID ) ),
			);
		}
		return new WP_REST_Response( $out );
	}

	/**
	 * One saved version: its element tree (the editor restores it as an undoable change).
	 */
	public function revision( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$rev_id   = (int) $request['rev'];
		$revision = wp_get_post_revision( $rev_id );
		if ( ! $revision || (int) $revision->post_parent !== $id ) {
			return new WP_Error( 'uncoder_no_revision', __( 'That saved version does not exist.', 'uncoder' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response(
			array(
				'id'       => $revision->ID,
				'date'     => get_post_time( 'U', true, $revision ),
				'elements' => self::revision_elements( $revision->ID ),
			)
		);
	}

	/**
	 * Builder data stored with a revision (wp_post_revision_meta_keys, see Post_Types).
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private static function revision_elements( int $revision_id ): array {
		$raw  = get_metadata( 'post', $revision_id, \Uncoder\Builder\Core\Utils::META_DATA, true );
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		return is_array( $data['elements'] ?? null ) ? $data['elements'] : ( is_array( $data ) && isset( $data[0] ) ? $data : array() );
	}

	/**
	 * @param array<int, array<string,mixed>> $elements Tree.
	 */
	private static function count_nodes( array $elements ): int {
		$n = 0;
		foreach ( $elements as $node ) {
			if ( is_array( $node ) ) {
				$n += 1 + self::count_nodes( (array) ( $node['children'] ?? array() ) );
			}
		}
		return $n;
	}
}
