<?php
/**
 * Theme builder templates, popups and saved sections for the admin screens.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Editor\Editor;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Popups\Popups;
use Uncoder\Builder\Theme\Conditions;
use Uncoder\Builder\Theme\Theme_Builder;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /templates, GET /templates/meta, GET/POST/DELETE /templates/{id},
 * POST /templates/{id}/duplicate, POST /templates/{id}/restore.
 */
final class Templates_Controller {

	/** Types placed on the site by display conditions (the others are used by reference). */
	public const CONDITIONAL = array( 'header', 'footer', 'single-post', 'single-page', 'single', 'archive', 'search-results', 'error-404', 'popup' );

	/** Conditions given to a new template when none are provided. */
	public const DEFAULT_CONDITIONS = array(
		'header'         => array( array( 'type' => 'include', 'rule' => 'general' ) ),
		'footer'         => array( array( 'type' => 'include', 'rule' => 'general' ) ),
		'single-post'    => array( array( 'type' => 'include', 'rule' => 'singular', 'post_type' => 'post' ) ),
		'single-page'    => array( array( 'type' => 'include', 'rule' => 'singular', 'post_type' => 'page' ) ),
		'single'         => array(),
		'archive'        => array( array( 'type' => 'include', 'rule' => 'archive' ) ),
		'search-results' => array( array( 'type' => 'include', 'rule' => 'search' ) ),
		'error-404'      => array( array( 'type' => 'include', 'rule' => 'not_found' ) ),
		'popup'          => array( array( 'type' => 'include', 'rule' => 'general' ) ),
	);

	private const STATUSES = array( 'publish', 'draft', 'private', 'pending', 'future' );

	public function register_routes(): void {
		register_rest_route(
			Rest::NS,
			'/templates',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'can_list' ),
					'args'                => array(
						'type' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'can_create' ),
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/templates/meta',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'meta' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'can_edit_item' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_edit_item' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'trash' ),
					'permission_callback' => array( $this, 'can_delete_item' ),
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/templates/(?P<id>\d+)/duplicate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'duplicate' ),
				'permission_callback' => array( $this, 'can_duplicate_item' ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/templates/(?P<id>\d+)/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'restore' ),
				'permission_callback' => array( $this, 'can_delete_item' ),
			)
		);
	}

	/* ------------------------------------------------------------------ Permissions */

	/**
	 * Requested template types (comma separated "type" parameter), validated.
	 *
	 * @return string[]
	 */
	private function requested_types( WP_REST_Request $request ): array {
		$raw   = (string) $request->get_param( 'type' );
		$types = array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) );
		return array_values( array_intersect( $types, array_keys( Post_Types::TEMPLATE_TYPES ) ) );
	}

	/**
	 * Saved sections are content (edit_posts may list them); every other type is theme design.
	 */
	public function can_list( WP_REST_Request $request ): bool {
		$types = $this->requested_types( $request );
		if ( array( 'section' ) === $types ) {
			return current_user_can( 'edit_posts' );
		}
		return current_user_can( 'edit_theme_options' );
	}

	public function can_create( WP_REST_Request $request ): bool {
		$body = $request->get_json_params();
		$type = is_array( $body ) ? sanitize_key( (string) ( $body['type'] ?? '' ) ) : '';
		$pto  = get_post_type_object( Post_Types::TEMPLATE );
		if ( ! $pto || ! current_user_can( $pto->cap->create_posts ) ) {
			return false;
		}
		return 'section' === $type || current_user_can( 'edit_theme_options' );
	}

	/**
	 * The item must be a template the user can edit; non-section templates also need edit_theme_options.
	 * Unknown ids pass for users who could edit templates so the callback can answer 404.
	 */
	public function can_edit_item( WP_REST_Request $request ): bool {
		$post = $this->find( (int) $request['id'], true );
		if ( ! $post ) {
			return current_user_can( 'edit_theme_options' );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return false;
		}
		return 'section' === $this->type_of( $post ) || current_user_can( 'edit_theme_options' );
	}

	public function can_delete_item( WP_REST_Request $request ): bool {
		$post = $this->find( (int) $request['id'], true );
		if ( ! $post ) {
			return current_user_can( 'edit_theme_options' );
		}
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return false;
		}
		return 'section' === $this->type_of( $post ) || current_user_can( 'edit_theme_options' );
	}

	public function can_duplicate_item( WP_REST_Request $request ): bool {
		$pto = get_post_type_object( Post_Types::TEMPLATE );
		return $pto && current_user_can( $pto->cap->create_posts ) && $this->can_edit_item( $request );
	}

	/* ------------------------------------------------------------------ Helpers */

	private function find( int $id, bool $include_trash = false ): ?WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof WP_Post || Post_Types::TEMPLATE !== $post->post_type ) {
			return null;
		}
		if ( ! $include_trash && 'trash' === $post->post_status ) {
			return null;
		}
		return $post;
	}

	private function type_of( WP_Post $post ): string {
		$type = (string) get_post_meta( $post->ID, Utils::META_TYPE, true );
		return isset( Post_Types::TEMPLATE_TYPES[ $type ] ) ? $type : 'section';
	}

	private function not_found(): WP_Error {
		return new WP_Error( 'uncoder_not_found', __( 'Template not found.', 'uncoder' ), array( 'status' => 404 ) );
	}

	/**
	 * Last modification as ISO 8601 UTC (drafts can have an empty GMT date).
	 */
	public static function modified_iso( \WP_Post $post ): string {
		$gmt = (string) $post->post_modified_gmt;
		if ( '' === $gmt || '0000-00-00 00:00:00' === $gmt ) {
			$gmt = get_gmt_from_date( (string) $post->post_modified );
		}
		return gmdate( 'c', (int) strtotime( $gmt . ' UTC' ) );
	}

	private function rebuild_index(): void {
		$builder = Theme_Builder::instance();
		if ( $builder ) {
			$builder->rebuild_index();
		}
	}

	/**
	 * Readable names for the ids a condition refers to.
	 *
	 * @param array<string,mixed> $condition Condition.
	 * @return array<string,string> id => label
	 */
	public static function id_labels( array $condition ): array {
		$ids = array_map( 'intval', (array) ( $condition['ids'] ?? array() ) );
		if ( ! $ids ) {
			return array();
		}
		$rule   = (string) ( $condition['rule'] ?? '' );
		$labels = array();
		foreach ( $ids as $id ) {
			$label = '';
			if ( in_array( $rule, array( 'by_author', 'author' ), true ) ) {
				$user  = get_userdata( $id );
				$label = $user ? $user->display_name : '';
			} elseif ( 'in_term' === $rule || ( 'archive' === $rule && ! empty( $condition['taxonomy'] ) ) ) {
				$term  = get_term( $id );
				$label = $term instanceof \WP_Term ? $term->name : '';
			} else {
				$post  = get_post( $id );
				$label = $post ? html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) : '';
				if ( $post && '' === $label ) {
					$label = __( '(no title)', 'uncoder' );
				}
			}
			/* translators: %d: object id. */
			$labels[ (string) $id ] = '' !== $label ? $label : sprintf( __( 'Deleted #%d', 'uncoder' ), $id );
		}
		return $labels;
	}

	/**
	 * Readable label of one condition, with names instead of ids ("Pages: Pricing, About +2").
	 *
	 * @param array<string,mixed> $c Condition (with labels).
	 */
	public static function condition_label( array $c ): string {
		$rules   = Conditions::rules();
		$rule    = (string) ( $c['rule'] ?? '' );
		$label   = $rules[ $rule ]['label'] ?? $rule;
		$pt_name = '';
		$tx_name = '';
		if ( ! empty( $c['post_type'] ) ) {
			$obj     = get_post_type_object( (string) $c['post_type'] );
			$pt_name = $obj ? $obj->labels->name : (string) $c['post_type'];
		}
		if ( ! empty( $c['taxonomy'] ) ) {
			$tax     = get_taxonomy( (string) $c['taxonomy'] );
			$tx_name = $tax ? $tax->labels->name : (string) $c['taxonomy'];
		}
		switch ( $rule ) {
			case 'general':
				$label = __( 'Entire site', 'uncoder' );
				break;
			case 'singular':
				$label = '' !== $pt_name ? $pt_name : __( 'All singular', 'uncoder' );
				break;
			case 'in_term':
				/* translators: %s: taxonomy name. */
				$label = sprintf( __( 'In %s', 'uncoder' ), '' !== $tx_name ? $tx_name : __( 'Categories', 'uncoder' ) );
				break;
			case 'child_of':
				$label = __( 'Child of', 'uncoder' );
				break;
			case 'by_author':
				$label = __( 'By author', 'uncoder' );
				break;
			case 'archive':
				if ( '' !== $tx_name ) {
					/* translators: %s: taxonomy name. */
					$label = sprintf( __( '%s archive', 'uncoder' ), $tx_name );
				} elseif ( '' !== $pt_name ) {
					/* translators: %s: post type name. */
					$label = sprintf( __( '%s archive', 'uncoder' ), $pt_name );
				} else {
					$label = __( 'All archives', 'uncoder' );
				}
				break;
		}
		if ( ! empty( $c['labels'] ) ) {
			$names  = array_values( (array) $c['labels'] );
			$shown  = array_slice( $names, 0, 2 );
			$more   = count( $names ) - count( $shown );
			$label .= ': ' . implode( ', ', $shown ) . ( $more > 0 ? ' +' . $more : '' );
		}
		return $label;
	}

	/**
	 * Human summary: "Entire site — except Pages: Pricing".
	 *
	 * @param array<int, array<string,mixed>> $conditions Conditions (with label).
	 */
	public static function summary( array $conditions ): string {
		if ( ! $conditions ) {
			return '';
		}
		$includes = array();
		$excludes = array();
		foreach ( $conditions as $c ) {
			if ( 'exclude' === ( $c['type'] ?? 'include' ) ) {
				$excludes[] = (string) $c['label'];
			} else {
				$includes[] = (string) $c['label'];
			}
		}
		$out = $includes ? implode( ' · ', $includes ) : __( 'Nowhere', 'uncoder' );
		if ( $excludes ) {
			/* translators: %s: excluded locations. */
			$out .= ' — ' . sprintf( __( 'except %s', 'uncoder' ), implode( ', ', $excludes ) );
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function present( WP_Post $post ): array {
		$type        = $this->type_of( $post );
		$conditional = in_array( $type, self::CONDITIONAL, true );
		$author      = get_userdata( (int) $post->post_author );
		$doc         = Plugin::instance()->documents()->get( $post->ID );
		$item        = array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'type'        => $type,
			'typeLabel'   => Post_Types::TEMPLATE_TYPES[ $type ] ?? $type,
			'status'      => $post->post_status,
			'modified'    => self::modified_iso( $post ),
			'author'      => $author ? $author->display_name : '',
			'editUrl'     => Editor::url( $post->ID ),
			'previewUrl'  => \Uncoder\Builder\Theme\Template_Preview::url( $post->ID ),
			'canEdit'     => current_user_can( 'edit_post', $post->ID ),
			'canDelete'   => current_user_can( 'delete_post', $post->ID ),
			'elements'    => $doc ? count( Tree::ids( $doc->elements() ) ) : 0,
			'conditional' => $conditional,
			'shortcode'   => '[uncoder_template id="' . $post->ID . '"]',
		);
		if ( $conditional ) {
			$raw   = get_post_meta( $post->ID, Utils::META_CONDS, true );
			$conds = array();
			foreach ( is_array( $raw ) ? $raw : array() as $c ) {
				if ( ! is_array( $c ) ) {
					continue;
				}
				$labels = self::id_labels( $c );
				if ( $labels ) {
					$c['labels'] = $labels;
				}
				$c['label'] = self::condition_label( $c );
				$conds[]    = $c;
			}
			$item['conditions'] = $conds;
			$item['summary']    = self::summary( $conds );
			$item['active']     = 'publish' === $post->post_status && (bool) $conds;
		}
		if ( 'popup' === $type ) {
			$item['popup']    = Popups::settings( $post->ID );
			$item['openLink'] = '#uncoder-popup:open:' . $post->ID;
		}
		// WPML / Polylang: this template's language and its other language versions.
		$languages = \Uncoder\Builder\Site\Multilingual::describe( $post->ID );
		if ( null !== $languages ) {
			$item['languages'] = $languages;
		}
		return $item;
	}

	/* ------------------------------------------------------------------ Callbacks */

	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$types = $this->requested_types( $request );
		$args  = array(
			'post_type'      => Post_Types::TEMPLATE,
			'post_status'    => self::STATUSES,
			'posts_per_page' => 300,
			'no_found_rows'  => true,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'lang'           => '', // Polylang: every language.
		);
		if ( $types ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => Utils::META_TYPE,
					'value'   => $types,
					'compare' => 'IN',
				),
			);
		}
		$items = array();
		foreach ( get_posts( $args ) as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}
			$items[] = $this->present( $post );
		}
		return new WP_REST_Response( $items );
	}

	public function get_item( WP_REST_Request $request ) {
		$post = $this->find( (int) $request['id'] );
		return $post ? new WP_REST_Response( $this->present( $post ) ) : $this->not_found();
	}

	public function meta(): WP_REST_Response {
		$rules = array();
		foreach ( Conditions::rules() as $id => $rule ) {
			$rules[ $id ] = array(
				'label'   => $rule['label'],
				'context' => $rule['context'],
				'fields'  => $rule['fields'],
			);
		}
		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( in_array( $pt->name, array( Post_Types::TEMPLATE, 'attachment' ), true ) ) {
				continue;
			}
			$post_types[] = array(
				'name'         => $pt->name,
				'label'        => $pt->labels->name,
				'singular'     => $pt->labels->singular_name,
				'hierarchical' => (bool) $pt->hierarchical,
				'hasArchive'   => 'post' === $pt->name || (bool) $pt->has_archive,
			);
		}
		$taxonomies = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			if ( 'post_format' === $tax->name ) {
				continue;
			}
			$taxonomies[] = array(
				'name'      => $tax->name,
				'label'     => $tax->labels->name,
				'singular'  => $tax->labels->singular_name,
				'postTypes' => array_values( (array) $tax->object_type ),
			);
		}
		return new WP_REST_Response(
			array(
				'types'         => Post_Types::TEMPLATE_TYPES,
				'conditional'   => self::CONDITIONAL,
				'defaults'      => self::DEFAULT_CONDITIONS,
				'rules'         => $rules,
				'postTypes'     => $post_types,
				'taxonomies'    => $taxonomies,
				'popupDefaults' => Popups::defaults(),
				'canListUsers'  => current_user_can( 'list_users' ),
			)
		);
	}

	/**
	 * Body: { type, title?, conditions?, popup? }. Creates a draft and returns it with its editor URL.
	 */
	public function create( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$type = sanitize_key( (string) ( $body['type'] ?? '' ) );
		if ( ! isset( Post_Types::TEMPLATE_TYPES[ $type ] ) ) {
			return new WP_Error( 'uncoder_invalid_type', __( 'Unknown template type.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$title = sanitize_text_field( (string) ( $body['title'] ?? '' ) );
		if ( '' === $title ) {
			/* translators: %s: template type label. */
			$title = sprintf( __( 'New %s', 'uncoder' ), strtolower( Post_Types::TEMPLATE_TYPES[ $type ] ) );
		}

		$conds = null;
		if ( in_array( $type, self::CONDITIONAL, true ) ) {
			if ( array_key_exists( 'conditions', $body ) ) {
				$errors = array();
				$conds  = Conditions::sanitize( $body['conditions'], $errors );
				if ( $errors ) {
					return new WP_Error( 'uncoder_invalid_conditions', implode( ' ', $errors ), array( 'status' => 400, 'details' => $errors ) );
				}
			} else {
				$conds = self::DEFAULT_CONDITIONS[ $type ] ?? array();
			}
		}
		$popup = null;
		if ( 'popup' === $type ) {
			$errors = array();
			$popup  = Popups::sanitize( is_array( $body['popup'] ?? null ) ? $body['popup'] : array(), $errors );
			if ( $errors ) {
				return new WP_Error( 'uncoder_invalid_popup', implode( ' ', $errors ), array( 'status' => 400, 'details' => $errors ) );
			}
		}

		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => Post_Types::TEMPLATE,
					'post_title'  => $title,
					'post_status' => 'draft',
					'post_author' => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'uncoder_create_failed', $id->get_error_message(), array( 'status' => 500 ) );
		}
		$id = (int) $id;
		update_post_meta( $id, Utils::META_TYPE, $type );
		if ( null !== $conds ) {
			update_post_meta( $id, Utils::META_CONDS, $conds );
		}
		if ( null !== $popup ) {
			update_post_meta( $id, Utils::META_TPL, $popup );
		}
		$doc = Plugin::instance()->documents()->get( $id );
		if ( $doc ) {
			$doc->save( array() );
		}
		$this->rebuild_index();

		return new WP_REST_Response( $this->present( get_post( $id ) ), 201 );
	}

	/**
	 * Body: { title?, status? (publish|draft), conditions?, popup? }. Everything is validated before anything is written.
	 */
	public function update( WP_REST_Request $request ) {
		$post = $this->find( (int) $request['id'] );
		if ( ! $post ) {
			return $this->not_found();
		}
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$type = $this->type_of( $post );

		$update = array( 'ID' => $post->ID );
		if ( isset( $body['title'] ) ) {
			$title = sanitize_text_field( (string) $body['title'] );
			if ( '' === $title ) {
				return new WP_Error( 'uncoder_invalid_title', __( 'The title cannot be empty.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$update['post_title'] = $title;
		}
		if ( isset( $body['status'] ) ) {
			$status = sanitize_key( (string) $body['status'] );
			if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
				return new WP_Error( 'uncoder_invalid_status', __( 'Status must be "publish" or "draft".', 'uncoder' ), array( 'status' => 400 ) );
			}
			$pto = get_post_type_object( Post_Types::TEMPLATE );
			if ( 'publish' === $status && ( ! $pto || ! current_user_can( $pto->cap->publish_posts ) ) ) {
				return new WP_Error( 'uncoder_forbidden', __( 'You cannot publish templates.', 'uncoder' ), array( 'status' => 403 ) );
			}
			$update['post_status'] = $status;
		}

		$conds = null;
		if ( array_key_exists( 'conditions', $body ) ) {
			if ( ! in_array( $type, self::CONDITIONAL, true ) ) {
				return new WP_Error( 'uncoder_not_conditional', __( 'This template type is used by reference and has no display conditions.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$errors = array();
			$conds  = Conditions::sanitize( is_array( $body['conditions'] ) ? $body['conditions'] : array(), $errors );
			if ( $errors ) {
				return new WP_Error( 'uncoder_invalid_conditions', implode( ' ', $errors ), array( 'status' => 400, 'details' => $errors ) );
			}
		}

		$popup = null;
		if ( isset( $body['popup'] ) ) {
			if ( 'popup' !== $type ) {
				return new WP_Error( 'uncoder_not_popup', __( 'Popup settings only apply to popups.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$errors  = array();
			$current = get_post_meta( $post->ID, Utils::META_TPL, true );
			$popup   = Popups::sanitize( is_array( $body['popup'] ) ? $body['popup'] : array(), $errors, is_array( $current ) ? $current : array() );
			if ( $errors ) {
				return new WP_Error( 'uncoder_invalid_popup', implode( ' ', $errors ), array( 'status' => 400, 'details' => $errors ) );
			}
		}

		if ( null !== $conds ) {
			update_post_meta( $post->ID, Utils::META_CONDS, $conds );
		}
		if ( null !== $popup ) {
			update_post_meta( $post->ID, Utils::META_TPL, $popup );
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( wp_slash( $update ), true );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'uncoder_update_failed', $result->get_error_message(), array( 'status' => 500 ) );
			}
		}
		$this->rebuild_index();
		clean_post_cache( $post->ID );

		return new WP_REST_Response( $this->present( get_post( $post->ID ) ) );
	}

	/**
	 * Copies the template (builder data, type, conditions and settings) as a new draft.
	 * Element ids may repeat across documents: generated CSS is scoped per document.
	 */
	public function duplicate( WP_REST_Request $request ) {
		$post = $this->find( (int) $request['id'] );
		if ( ! $post ) {
			return $this->not_found();
		}
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => Post_Types::TEMPLATE,
					/* translators: %s: template title. */
					'post_title'  => sprintf( __( '%s (copy)', 'uncoder' ), $post->post_title ),
					'post_status' => 'draft',
					'post_author' => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'uncoder_create_failed', $id->get_error_message(), array( 'status' => 500 ) );
		}
		$id = (int) $id;
		foreach ( array( Utils::META_DATA, Utils::META_MODE, Utils::META_PAGE, Utils::META_TYPE, Utils::META_CONDS, Utils::META_TPL ) as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( '' === $value || null === $value ) {
				continue;
			}
			update_post_meta( $id, $key, is_string( $value ) ? wp_slash( $value ) : $value );
		}
		$doc = Plugin::instance()->documents()->get( $id );
		if ( $doc && $doc->is_builder() ) {
			$doc->regenerate();
		}
		$this->rebuild_index();

		return new WP_REST_Response( $this->present( get_post( $id ) ), 201 );
	}

	public function trash( WP_REST_Request $request ) {
		$post = $this->find( (int) $request['id'] );
		if ( ! $post ) {
			return $this->not_found();
		}
		if ( ! wp_trash_post( $post->ID ) ) {
			return new WP_Error( 'uncoder_trash_failed', __( 'The template could not be moved to the trash.', 'uncoder' ), array( 'status' => 500 ) );
		}
		$this->rebuild_index();
		return new WP_REST_Response(
			array(
				'trashed' => true,
				'id'      => $post->ID,
			)
		);
	}

	/**
	 * Restores a trashed template with its previous status (the "Undo" of a trash action).
	 */
	public function restore( WP_REST_Request $request ) {
		$post = $this->find( (int) $request['id'], true );
		if ( ! $post || 'trash' !== $post->post_status ) {
			return $this->not_found();
		}
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );
		$result = wp_untrash_post( $post->ID );
		remove_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10 );
		if ( ! $result ) {
			return new WP_Error( 'uncoder_restore_failed', __( 'The template could not be restored.', 'uncoder' ), array( 'status' => 500 ) );
		}
		$this->rebuild_index();
		return new WP_REST_Response( $this->present( get_post( $post->ID ) ) );
	}
}
