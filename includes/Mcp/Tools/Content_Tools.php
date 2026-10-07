<?php
/**
 * Regular WordPress content: posts (block editor content), terms, content types.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Seo;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Mcp\Call;
use Uncoder\Builder\Mcp\Registry;
use Uncoder\Builder\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * list_posts, get_post, create_post, update_post, list_terms, create_term, list_content_types.
 */
final class Content_Tools {

	public function register( Registry $r ): void {
		$ro     = array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false );
		$fields = array(
			'title'          => array( 'type' => 'string' ),
			'content'        => array( 'type' => 'string', 'description' => 'HTML or Markdown. Stored as block editor blocks.' ),
			'excerpt'        => array( 'type' => 'string' ),
			'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'pending', 'private', 'future' ) ),
			'date'           => array( 'type' => 'string', 'description' => 'Publish date "YYYY-MM-DD HH:MM" (site time); future + status "future" schedules it.' ),
			'slug'           => array( 'type' => 'string' ),
			'categories'     => array( 'type' => 'array', 'description' => 'Category names or ids; missing names are created.', 'items' => array( 'type' => array( 'string', 'integer' ) ) ),
			'tags'           => array( 'type' => 'array', 'description' => 'Tag names.', 'items' => array( 'type' => array( 'string', 'integer' ) ) ),
			'terms'          => array( 'type' => 'object', 'description' => 'Other taxonomies: {"product_cat": ["Shoes"]}.' ),
			'featured_image' => array( 'type' => 'integer', 'description' => 'Attachment id (upload_media / list_media).' ),
			'seo'            => array( 'type' => 'object', 'description' => '{"title":…, "description":…} (same as set_seo_meta).' ),
		);

		$r->add(
			array(
				'name'        => 'list_posts',
				'title'       => 'List posts',
				'description' => 'Blog posts (or any post type) with id, title, status, date, URL, categories and excerpt.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'description' => 'Default post.' ),
						'search'    => array( 'type' => 'string' ),
						'status'    => array( 'type' => 'string', 'enum' => array( 'any', 'publish', 'draft', 'pending', 'private', 'future' ) ),
						'category'  => array( 'type' => 'string', 'description' => 'Category slug.' ),
						'limit'     => array( 'type' => 'integer', 'description' => 'Max 100 (default 20).' ),
						'page'      => array( 'type' => 'integer' ),
					),
				),
				'callback'    => array( $this, 'list_posts' ),
			)
		);

		$r->add(
			array(
				'name'        => 'get_post',
				'title'       => 'Get post',
				'description' => 'A regular post with its content (HTML), excerpt, terms, featured image and SEO meta. Pages built with Uncoder are read with get_page.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'get_post' ),
			)
		);

		$r->add(
			array(
				'name'        => 'create_post',
				'title'       => 'Create post',
				'description' => 'Creates a blog post (or item of another post type) with regular editor content — for articles, news, case studies. Draft by default. Use create_page for designed pages.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array_merge( array( 'post_type' => array( 'type' => 'string', 'description' => 'Default post.' ) ), $fields ),
					'required'   => array( 'title' ),
				),
				'callback'    => array( $this, 'create_post' ),
			)
		);

		$r->add(
			array(
				'name'        => 'update_post',
				'title'       => 'Update post',
				'description' => 'Updates a post: any of title, content (replaces it), excerpt, status, date, slug, categories/tags (replace), featured image, SEO.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array_merge( array( 'id' => array( 'type' => 'integer' ) ), $fields ),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'update_post' ),
			)
		);

		$r->add(
			array(
				'name'        => 'list_terms',
				'title'       => 'List terms',
				'description' => 'Categories, tags or terms of another taxonomy with id, slug, count and parent.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy' => array( 'type' => 'string', 'description' => 'Default category.' ),
						'search'   => array( 'type' => 'string' ),
						'limit'    => array( 'type' => 'integer' ),
					),
				),
				'callback'    => array( $this, 'list_terms' ),
			)
		);

		$r->add(
			array(
				'name'        => 'create_term',
				'title'       => 'Create term',
				'description' => 'Creates a category, tag or other taxonomy term (returns the existing one if the name exists).',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy'    => array( 'type' => 'string', 'description' => 'Default category.' ),
						'name'        => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'parent'      => array( 'type' => 'integer' ),
					),
					'required'   => array( 'name' ),
				),
				'callback'    => array( $this, 'create_term' ),
			)
		);

		$r->add(
			array(
				'name'        => 'list_content_types',
				'title'       => 'List content types',
				'description' => 'Public post types and taxonomies (with which ones can be designed with Uncoder) — useful for theme templates and query settings.',
				'scope'       => 'read',
				'annotations' => $ro,
				'callback'    => array( $this, 'content_types' ),
			)
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_posts( array $a ) {
		$type = sanitize_key( (string) ( $a['post_type'] ?? 'post' ) );
		$pto  = get_post_type_object( $type );
		if ( ! $pto || ( ! is_post_type_viewable( $type ) && ! current_user_can( $pto->cap->edit_posts ) ) ) {
			return new WP_Error( 'invalid', 'Unknown post type "' . $type . '". Call list_content_types.' );
		}
		$status = (string) ( $a['status'] ?? 'any' );
		$args   = array(
			'post_type'      => $type,
			'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : $status,
			'posts_per_page' => min( 100, max( 1, (int) ( $a['limit'] ?? 20 ) ) ),
			'paged'          => max( 1, (int) ( $a['page'] ?? 1 ) ),
			's'              => (string) ( $a['search'] ?? '' ),
		);
		if ( ! empty( $a['category'] ) ) {
			$args['category_name'] = sanitize_title( (string) $a['category'] );
		}
		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) || ( 'publish' === $post->post_status && is_post_type_viewable( $type ) ) ) {
				$items[] = $this->summary( $post );
			}
		}
		return array(
			'total' => (int) $query->found_posts,
			'items' => $items,
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_post( array $a ) {
		$post = get_post( absint( $a['id'] ) );
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'not_found', 'No editable post with id ' . absint( $a['id'] ) . '.' );
		}
		$out            = $this->summary( $post );
		$out['content'] = Utils::is_builder_post( $post->ID ) ? '(built with Uncoder: read it with get_page)' : $post->post_content;
		$out['seo']     = Seo::get( $post->ID );
		return $out;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_post( array $a, Call $call ) {
		$type = sanitize_key( (string) ( $a['post_type'] ?? 'post' ) );
		$pto  = get_post_type_object( $type );
		if ( ! $pto || Post_Types::TEMPLATE === $type || 'attachment' === $type || ! $pto->show_ui ) {
			return new WP_Error( 'invalid', 'Unknown or unsupported post type "' . $type . '". Call list_content_types.' );
		}
		if ( ! current_user_can( $pto->cap->create_posts ) ) {
			return new WP_Error( 'forbidden', 'You cannot create items of this type.' );
		}
		$postarr = array(
			'post_type'   => $type,
			'post_title'  => sanitize_text_field( (string) $a['title'] ),
			'post_status' => 'draft',
		);
		$error   = $this->apply_fields( $postarr, $a, $pto, $call );
		if ( $error ) {
			return $error;
		}
		$id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$this->apply_after( (int) $id, $a, $call );
		$call->object_id = (int) $id;
		$call->summary   = sprintf( 'Created %s "%s"', $type, $postarr['post_title'] );
		return array(
			'id'      => (int) $id,
			'created' => $this->summary( get_post( (int) $id ) ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_post( array $a, Call $call ) {
		$post = get_post( absint( $a['id'] ) );
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'not_found', 'No editable post with id ' . absint( $a['id'] ) . '.' );
		}
		if ( Post_Types::TEMPLATE === $post->post_type ) {
			return new WP_Error( 'invalid', 'Use update_template for theme templates.' );
		}
		if ( isset( $a['content'] ) && Utils::is_builder_post( $post->ID ) ) {
			return new WP_Error( 'invalid', 'This item is built with Uncoder; change its content with edit_elements / update_page.' );
		}
		$pto     = get_post_type_object( $post->post_type );
		$postarr = array( 'ID' => $post->ID );
		if ( isset( $a['title'] ) ) {
			$postarr['post_title'] = sanitize_text_field( (string) $a['title'] );
		}
		$error = $this->apply_fields( $postarr, $a, $pto, $call );
		if ( $error ) {
			return $error;
		}
		if ( ! isset( $a['status'] ) ) {
			unset( $postarr['post_status'] );
		}
		wp_save_post_revision( $post->ID );
		$result = wp_update_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->apply_after( $post->ID, $a, $call );
		$call->object_id = $post->ID;
		$call->summary   = 'Updated "' . get_the_title( $post->ID ) . '"';
		return array(
			'post' => $this->summary( get_post( $post->ID ) ),
			'note' => 'The previous version is kept as a WordPress revision.',
		);
	}

	/**
	 * Fills post fields shared by create/update. Returns an error or null.
	 *
	 * @param array<string,mixed> $postarr Post array (by reference).
	 */
	private function apply_fields( array &$postarr, array $a, \WP_Post_Type $pto, Call $call ): ?WP_Error {
		if ( isset( $a['content'] ) ) {
			$content = (string) $a['content'];
			if ( Markdown::looks_like_markdown( $content ) ) {
				$content = Markdown::to_html( $content );
			}
			$content                 = current_user_can( 'unfiltered_html' ) ? $content : wp_kses_post( $content );
			$postarr['post_content'] = Markdown::to_blocks( $content );
		}
		if ( isset( $a['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_textarea_field( (string) $a['excerpt'] );
		}
		if ( isset( $a['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $a['slug'] );
		}
		if ( isset( $a['date'] ) ) {
			$time = strtotime( (string) $a['date'] );
			if ( false === $time ) {
				return new WP_Error( 'invalid', 'date must look like "2025-10-01 09:00".' );
			}
			$postarr['post_date']     = gmdate( 'Y-m-d H:i:s', $time );
			$postarr['post_date_gmt'] = get_gmt_from_date( $postarr['post_date'] );
			$postarr['edit_date']     = true;
		}
		$status = (string) ( $a['status'] ?? 'draft' );
		if ( in_array( $status, array( 'publish', 'private', 'future' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
			$status = 'pending';
			$call->warn( 'You cannot publish: saved as pending review.' );
		}
		$postarr['post_status'] = $status;
		return null;
	}

	private function apply_after( int $id, array $a, Call $call ): void {
		$type = get_post_type( $id );
		if ( isset( $a['categories'] ) && is_object_in_taxonomy( $type, 'category' ) ) {
			wp_set_post_terms( $id, $this->term_ids( (array) $a['categories'], 'category', $call ), 'category' );
		}
		if ( isset( $a['tags'] ) && is_object_in_taxonomy( $type, 'post_tag' ) ) {
			wp_set_post_terms( $id, $this->term_ids( (array) $a['tags'], 'post_tag', $call ), 'post_tag' );
		}
		foreach ( (array) ( $a['terms'] ?? array() ) as $tax => $terms ) {
			$tax = sanitize_key( (string) $tax );
			if ( ! is_object_in_taxonomy( $type, $tax ) ) {
				$call->warn( sprintf( 'Taxonomy "%s" does not apply to %s.', $tax, $type ) );
				continue;
			}
			wp_set_post_terms( $id, $this->term_ids( (array) $terms, $tax, $call ), $tax );
		}
		if ( isset( $a['featured_image'] ) ) {
			$img = absint( $a['featured_image'] );
			if ( 0 === $img ) {
				delete_post_thumbnail( $id );
			} elseif ( \Uncoder\Builder\Core\Media::is_image( $img ) ) {
				set_post_thumbnail( $id, $img );
			} else {
				$call->warn( 'featured_image must be an image attachment id.' );
			}
		}
		if ( ! empty( $a['seo'] ) && is_array( $a['seo'] ) ) {
			Seo::set( $id, isset( $a['seo']['title'] ) ? (string) $a['seo']['title'] : null, isset( $a['seo']['description'] ) ? (string) $a['seo']['description'] : null );
		}
	}

	/**
	 * Resolves names/ids to term ids, creating missing names when allowed.
	 *
	 * @param array<int, string|int> $terms Terms.
	 * @return int[]
	 */
	private function term_ids( array $terms, string $taxonomy, Call $call ): array {
		$ids = array();
		$tax = get_taxonomy( $taxonomy );
		foreach ( $terms as $term ) {
			if ( is_int( $term ) || ctype_digit( (string) $term ) ) {
				if ( term_exists( (int) $term, $taxonomy ) ) {
					$ids[] = (int) $term;
				}
				continue;
			}
			$name     = sanitize_text_field( (string) $term );
			$existing = term_exists( $name, $taxonomy );
			if ( $existing ) {
				$ids[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
				continue;
			}
			if ( ! $tax || ! current_user_can( $tax->cap->assign_terms ) || ( $tax->hierarchical && ! current_user_can( $tax->cap->edit_terms ) ) ) {
				$call->warn( sprintf( 'Cannot create %s "%s".', $taxonomy, $name ) );
				continue;
			}
			$created = wp_insert_term( $name, $taxonomy );
			if ( ! is_wp_error( $created ) ) {
				$ids[] = (int) $created['term_id'];
			}
		}
		return $ids;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_terms( array $a ) {
		$taxonomy = sanitize_key( (string) ( $a['taxonomy'] ?? 'category' ) );
		$tax      = get_taxonomy( $taxonomy );
		// Private taxonomies (menus, internal ones) only for users who may manage their terms.
		if ( ! $tax || ( ! $tax->public && ! current_user_can( $tax->cap->manage_terms ) ) ) {
			return new WP_Error( 'invalid', 'Unknown taxonomy "' . $taxonomy . '". Call list_content_types.' );
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'search'     => (string) ( $a['search'] ?? '' ),
				'number'     => min( 200, max( 1, (int) ( $a['limit'] ?? 100 ) ) ),
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		return array(
			'taxonomy' => $taxonomy,
			'items'    => array_map(
				static fn( $t ) => array(
					'id'     => (int) $t->term_id,
					'name'   => $t->name,
					'slug'   => $t->slug,
					'count'  => (int) $t->count,
					'parent' => (int) $t->parent,
					'url'    => get_term_link( $t ),
				),
				$terms
			),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_term( array $a, Call $call ) {
		$taxonomy = sanitize_key( (string) ( $a['taxonomy'] ?? 'category' ) );
		$tax      = get_taxonomy( $taxonomy );
		if ( ! $tax ) {
			return new WP_Error( 'invalid', 'Unknown taxonomy "' . $taxonomy . '".' );
		}
		if ( ! current_user_can( $tax->cap->edit_terms ) ) {
			return new WP_Error( 'forbidden', 'You cannot create terms in ' . $taxonomy . '.' );
		}
		$name     = sanitize_text_field( (string) $a['name'] );
		$existing = term_exists( $name, $taxonomy, absint( $a['parent'] ?? 0 ) ?: null );
		if ( $existing ) {
			$term = get_term( (int) ( is_array( $existing ) ? $existing['term_id'] : $existing ), $taxonomy );
			return array(
				'term'   => array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				),
				'reused' => true,
			);
		}
		$args = array(
			'description' => sanitize_textarea_field( (string) ( $a['description'] ?? '' ) ),
		);
		if ( ! empty( $a['slug'] ) ) {
			$args['slug'] = sanitize_title( (string) $a['slug'] );
		}
		if ( ! empty( $a['parent'] ) && $tax->hierarchical ) {
			$args['parent'] = absint( $a['parent'] );
		}
		$created = wp_insert_term( $name, $taxonomy, $args );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$term          = get_term( (int) $created['term_id'], $taxonomy );
		$call->summary = sprintf( 'Created %s "%s"', $taxonomy, $name );
		return array(
			'term' => array(
				'id'   => (int) $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'url'  => get_term_link( $term ),
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function content_types(): array {
		$builder = Plugin::instance()->documents()->post_types();
		$types   = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pto ) {
			if ( 'attachment' === $pto->name ) {
				continue;
			}
			$types[] = array(
				'name'        => $pto->name,
				'label'       => $pto->labels->name,
				'hierarchical' => (bool) $pto->hierarchical,
				'has_archive' => (bool) $pto->has_archive,
				'taxonomies'  => get_object_taxonomies( $pto->name ),
				'builder'     => in_array( $pto->name, $builder, true ),
				'count'       => (int) ( wp_count_posts( $pto->name )->publish ?? 0 ),
			);
		}
		$taxonomies = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$taxonomies[] = array(
				'name'         => $tax->name,
				'label'        => $tax->labels->name,
				'hierarchical' => (bool) $tax->hierarchical,
				'post_types'   => (array) $tax->object_type,
			);
		}
		return array(
			'post_types' => $types,
			'taxonomies' => $taxonomies,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function summary( \WP_Post $post ): array {
		$out = array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'type'     => $post->post_type,
			'status'   => $post->post_status,
			'date'     => get_post_time( 'Y-m-d H:i', false, $post ),
			'url'      => get_permalink( $post ),
			'excerpt'  => post_password_required( $post ) && ! current_user_can( 'edit_post', $post->ID ) ? '(password protected)' : wp_trim_words( '' !== $post->post_excerpt ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30 ),
			'builder'  => Utils::is_builder_post( $post->ID ),
		);
		$thumb = get_post_thumbnail_id( $post );
		if ( $thumb ) {
			$out['featured_image'] = array(
				'id'  => (int) $thumb,
				'url' => wp_get_attachment_image_url( $thumb, 'large' ),
			);
		}
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) {
			if ( ! $tax->public ) {
				continue;
			}
			$terms = get_the_terms( $post, $tax->name );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$out['terms'][ $tax->name ] = wp_list_pluck( $terms, 'name' );
			}
		}
		return $out;
	}
}
