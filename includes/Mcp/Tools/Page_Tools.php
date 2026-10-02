<?php
/**
 * Page & document tools: list, read, create, update, precise edits, audit.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Renderer;
use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Mcp\Call;
use Uncoder\Builder\Mcp\Registry;
use Uncoder\Builder\Mcp\Settings;
use Uncoder\Builder\Mcp\Snapshots;
use Uncoder\Builder\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The tools an AI uses most.
 */
final class Page_Tools {

	private const ELEMENTS_SCHEMA = array(
		'type'        => 'array',
		'description' => 'Element tree: [{ "type": "container", "settings": {…}, "children": [ { "type": "heading", "settings": { "title": "…" } } ] }]. See get_build_guide("recipes").',
		'items'       => array( 'type' => 'object' ),
	);

	public function register( Registry $r ): void {
		$ro = array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false );

		$r->add(
			array(
				'name'        => 'list_pages',
				'title'       => 'List pages',
				'description' => 'Pages and posts with their id, status, URL, template and whether they are built with Uncoder.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_type'    => array( 'type' => 'string', 'description' => 'page (default), post, any, or a custom post type.' ),
						'search'       => array( 'type' => 'string' ),
						'status'       => array( 'type' => 'string', 'enum' => array( 'any', 'publish', 'draft', 'private', 'pending' ) ),
						'builder_only' => array( 'type' => 'boolean' ),
						'limit'        => array( 'type' => 'integer', 'description' => 'Max 100 (default 50).' ),
						'page'         => array( 'type' => 'integer' ),
					),
				),
				'callback'    => array( $this, 'list_pages' ),
			)
		);

		$r->add(
			array(
				'name'        => 'get_page',
				'title'       => 'Get page',
				'description' => 'Reads a page, post or template. format "outline" (default) is a compact text tree with element ids; "tree" returns the full JSON elements (optionally only the subtree of element_id).',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array( 'type' => 'integer' ),
						'format'     => array( 'type' => 'string', 'enum' => array( 'outline', 'tree' ) ),
						'element_id' => array( 'type' => 'string', 'description' => 'Return only this element (with children).' ),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'get_page' ),
			)
		);

		$r->add(
			array(
				'name'        => 'create_page',
				'title'       => 'Create page',
				'description' => 'Creates a page (or post/CPT item) built with Uncoder from an element tree. Draft by default. Settings are validated; invalid keys/values are reported so you can fix and retry. Returns the id, URLs and an outline.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array( 'type' => 'string' ),
						'elements'  => self::ELEMENTS_SCHEMA,
						'status'    => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'pending', 'private' ) ),
						'post_type' => array( 'type' => 'string', 'description' => 'page (default) or another post type enabled for Uncoder.' ),
						'slug'      => array( 'type' => 'string' ),
						'parent'    => array( 'type' => 'integer', 'description' => 'Parent page id.' ),
						'template'  => array( 'type' => 'string', 'enum' => array( 'uncoder-full-width', 'uncoder-canvas', 'default' ), 'description' => 'Page template (default uncoder-full-width: theme header/footer + full-width content; uncoder-canvas: blank page).' ),
						'page_settings' => array( 'type' => 'object', 'description' => '{ "hide_title": true, "background": "#fff" }' ),
						'strict'    => array( 'type' => 'boolean', 'description' => 'Fail on any validation error (default false: invalid parts are dropped and reported).' ),
					),
					'required'   => array( 'title' ),
				),
				'callback'    => array( $this, 'create_page' ),
			)
		);

		$r->add(
			array(
				'name'        => 'update_page',
				'title'       => 'Update page',
				'description' => 'Replaces the whole element tree and/or updates title, status, slug, template or page settings. For small changes use edit_elements instead.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'            => array( 'type' => 'integer' ),
						'elements'      => self::ELEMENTS_SCHEMA,
						'title'         => array( 'type' => 'string' ),
						'status'        => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'pending', 'private' ) ),
						'slug'          => array( 'type' => 'string' ),
						'template'      => array( 'type' => 'string' ),
						'page_settings' => array( 'type' => 'object' ),
						'strict'        => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'update_page' ),
			)
		);

		$r->add(
			array(
				'name'        => 'edit_elements',
				'title'       => 'Edit elements',
				'description' => 'Precise, atomic edits by element id. Operations run in order; if one fails nothing is saved. ops: update {id, settings (merged; null deletes), replace?, label?}, insert {parent_id|null, position:"start"|"end"|index, or before/after:id, element|elements}, delete {id}, move {id, parent_id, position|before|after}, duplicate {id}, replace {id, element}, wrap {ids, settings?}, set_dynamic {id, key, tag, options?}, unset_dynamic {id, key}. See get_build_guide("editing").',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array( 'type' => 'integer', 'description' => 'Page/post/template id.' ),
						'operations' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						'dry_run'    => array( 'type' => 'boolean', 'description' => 'Validate without saving.' ),
					),
					'required'   => array( 'id', 'operations' ),
				),
				'callback'    => array( $this, 'edit_elements' ),
			)
		);

		$r->add(
			array(
				'name'        => 'find_elements',
				'title'       => 'Find elements',
				'description' => 'Search a document for elements by type, text or setting key.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'integer' ),
						'type'        => array( 'type' => 'string' ),
						'text'        => array( 'type' => 'string', 'description' => 'Case-insensitive text contained in any text setting.' ),
						'has_setting' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'find_elements' ),
			)
		);

		$r->add(
			array(
				'name'        => 'validate_elements',
				'title'       => 'Validate elements',
				'description' => 'Checks an element tree without saving it: returns the normalized tree and every problem found.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array( 'elements' => self::ELEMENTS_SCHEMA ),
					'required'   => array( 'elements' ),
				),
				'callback'    => static function ( array $a ) {
					list( $tree, $errors ) = Helpers::normalize_tree( $a['elements'] );
					return array(
						'valid'   => ! $errors,
						'errors'  => $errors,
						'outline' => Helpers::outline( $tree ),
					);
				},
			)
		);

		$r->add(
			array(
				'name'        => 'duplicate_page',
				'title'       => 'Duplicate page',
				'description' => 'Copies a page/post/template (design, page settings and template) into a new draft.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'title' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'duplicate_page' ),
			)
		);

		$r->add(
			array(
				'name'        => 'delete_page',
				'title'       => 'Delete page',
				'description' => 'Moves a page/post/template to the trash (recoverable from WordPress). Requires confirm: true.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'confirm' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'delete_page' ),
			)
		);

		$r->add(
			array(
				'name'        => 'publish_page',
				'title'       => 'Publish page',
				'description' => 'Publishes a draft page/post/template (or sets another status).',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'private', 'pending' ) ),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'publish_page' ),
			)
		);

		$r->add(
			array(
				'name'        => 'render_page',
				'title'       => 'Render page',
				'description' => 'Renders the page server-side and returns its visible text structure (headings, paragraphs, links, images with alt) so you can proofread the result, plus preview URLs.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'render_page' ),
			)
		);

		$r->add(
			array(
				'name'        => 'audit_page',
				'title'       => 'Audit page',
				'description' => 'Accessibility, SEO, responsive and content checks with element ids and fix hints: heading order, h1 count, alt text, contrast, empty/placeholder links, leftover default copy, mobile layout risks, nesting depth, fonts.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'callback'    => array( new Audit(), 'run' ),
			)
		);

		$r->add(
			array(
				'name'        => 'undo_last_change',
				'title'       => 'Undo last change',
				'description' => 'Reverts the most recent change made through MCP (for a given page id, or your latest change anywhere). The undo itself can be undone.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'description' => 'Page/template id (optional).' ),
						'snapshot' => array( 'type' => 'string', 'description' => 'A specific snapshot id returned by a previous tool call.' ),
					),
				),
				'callback'    => static function ( array $a, Call $call ) {
					$snapshot = (string) ( $a['snapshot'] ?? '' );
					if ( '' === $snapshot ) {
						$snapshot = Snapshots::latest( absint( $a['id'] ?? 0 ) );
					}
					if ( '' === $snapshot ) {
						return new WP_Error( 'none', 'There is no change to undo.' );
					}
					$result = Snapshots::restore( $snapshot );
					if ( ! is_wp_error( $result ) ) {
						$call->summary   = 'Restored ' . $snapshot;
						$call->object_id = (int) ( $result['restored'] ?? 0 );
						if ( ! empty( $result['undo_snapshot'] ) ) {
							$call->snapshot = (string) $result['undo_snapshot'];
						}
					}
					return $result;
				},
			)
		);

		$r->add(
			array(
				'name'        => 'list_snapshots',
				'title'       => 'List snapshots',
				'description' => 'Saved versions of a page taken before each AI change (id, time, tool, client). Restore one with undo_last_change {snapshot}.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'callback'    => static function ( array $a ) {
					$post = Helpers::editable_post( $a['id'] );
					if ( is_wp_error( $post ) ) {
						return $post;
					}
					$list = get_post_meta( $post->ID, '_uncoder_wb_snapshots', true );
					return array(
						'snapshots' => array_map(
							static fn( $s ) => array(
								'snapshot' => $s['id'],
								'time'     => gmdate( 'c', (int) $s['time'] ),
								'tool'     => $s['tool'],
								'client'   => $s['client'] ?? '',
							),
							is_array( $list ) ? $list : array()
						),
					);
				},
			)
		);
	}

	/* ------------------------------------------------------------------ Callbacks */

	public function list_pages( array $a ): array {
		$type   = sanitize_key( (string) ( $a['post_type'] ?? 'page' ) );
		$status = (string) ( $a['status'] ?? 'any' );
		$args   = array(
			'post_type'      => 'any' === $type ? array_values( array_diff( Plugin::instance()->documents()->post_types(), array( Post_Types::TEMPLATE ) ) ) : $type,
			'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'private', 'pending', 'future' ) : $status,
			'posts_per_page' => min( 100, max( 1, (int) ( $a['limit'] ?? 50 ) ) ),
			'paged'          => max( 1, (int) ( $a['page'] ?? 1 ) ),
			's'              => (string) ( $a['search'] ?? '' ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( ! empty( $a['builder_only'] ) ) {
			$args['meta_key']   = Utils::META_MODE; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = 'builder'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = Helpers::post_info( $post );
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
	public function get_page( array $a ) {
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$doc      = Plugin::instance()->documents()->get( $post->ID );
		$elements = $doc->elements();
		if ( ! empty( $a['element_id'] ) ) {
			$node = Tree::find( $elements, (string) $a['element_id'] );
			if ( ! $node ) {
				return new WP_Error( 'not_found', 'No element with id ' . $a['element_id'] . ' in this document.' );
			}
			$elements = array( $node );
		}
		$out = array( 'page' => Helpers::post_info( $post ) );
		if ( ! $doc->is_builder() ) {
			$out['note'] = 'This item is not built with Uncoder yet. Creating elements with update_page converts it (existing classic/block content is kept only in revisions).';
		}
		$out['page_settings'] = (object) $doc->page_settings();
		if ( 'tree' === ( $a['format'] ?? 'outline' ) ) {
			$out['elements'] = $elements;
		} else {
			$out['outline'] = '' !== Helpers::outline( $elements ) ? Helpers::outline( $elements ) : '(empty)';
			$out['hint']    = 'Use format "tree" (optionally with element_id) for full settings.';
		}
		if ( Post_Types::TEMPLATE === $post->post_type ) {
			$out['conditions'] = get_post_meta( $post->ID, Utils::META_CONDS, true );
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_page( array $a, Call $call ) {
		$type = sanitize_key( (string) ( $a['post_type'] ?? 'page' ) );
		if ( ! in_array( $type, Plugin::instance()->documents()->post_types(), true ) || Post_Types::TEMPLATE === $type ) {
			return new WP_Error( 'invalid', sprintf( 'Post type "%s" is not enabled for Uncoder. Use create_template for theme templates.', $type ) );
		}
		$pto    = get_post_type_object( $type );
		$status = (string) ( $a['status'] ?? 'draft' );
		if ( ! current_user_can( $pto->cap->create_posts ) ) {
			return new WP_Error( 'forbidden', 'You cannot create items of this type.' );
		}
		if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
			$status = 'pending';
			$call->warn( 'You cannot publish: saved as pending review.' );
		}
		list( $tree, $errors ) = Helpers::normalize_tree( $a['elements'] ?? array() );
		if ( $errors && ! empty( $a['strict'] ) ) {
			return Helpers::errors_to_wp_error( 'The elements have problems (strict mode, nothing was created).', $errors );
		}
		$postarr = array(
			'post_type'   => $type,
			'post_title'  => sanitize_text_field( (string) $a['title'] ),
			'post_status' => $status,
		);
		if ( ! empty( $a['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $a['slug'] );
		}
		if ( ! empty( $a['parent'] ) && is_post_type_hierarchical( $type ) ) {
			$postarr['post_parent'] = absint( $a['parent'] );
		}
		$id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$doc    = Plugin::instance()->documents()->get( (int) $id );
		$result = $doc->save( $tree, array( 'content_fallback' => true ) );
		if ( ! empty( $a['page_settings'] ) && is_array( $a['page_settings'] ) ) {
			$doc->save_page_settings( $a['page_settings'] );
		}
		if ( 'page' === $type ) {
			$template = (string) ( $a['template'] ?? 'uncoder-full-width' );
			update_post_meta( (int) $id, '_wp_page_template', in_array( $template, array( 'uncoder-full-width', 'uncoder-canvas', 'default' ), true ) ? $template : 'uncoder-full-width' );
		}
		$call->object_id = (int) $id;
		$call->summary   = sprintf( 'Created %s "%s" (%d elements)', $type, $postarr['post_title'], count( Tree::ids( $result['elements'] ) ) );
		foreach ( array_merge( $errors, $result['errors'] ) as $e ) {
			$call->warn( $e );
		}
		return array(
			'id'      => (int) $id,
			'created' => Helpers::post_info( get_post( (int) $id ) ),
			'outline' => Helpers::outline( $result['elements'] ),
			'next'    => 'Review with render_page / audit_page, refine with edit_elements, then publish_page.',
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_page( array $a, Call $call ) {
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$call->snapshot_post( $post->ID );
		$doc     = Plugin::instance()->documents()->get( $post->ID );
		$outline = null;
		if ( array_key_exists( 'elements', $a ) ) {
			list( $tree, $errors ) = Helpers::normalize_tree( $a['elements'] );
			if ( $errors && ! empty( $a['strict'] ) ) {
				return Helpers::errors_to_wp_error( 'The elements have problems (strict mode, nothing was saved).', $errors );
			}
			$result  = $doc->save( $tree, array( 'content_fallback' => true ) );
			$outline = Helpers::outline( $result['elements'] );
			foreach ( array_merge( $errors, $result['errors'] ) as $e ) {
				$call->warn( $e );
			}
		}
		$update = array( 'ID' => $post->ID );
		if ( isset( $a['title'] ) ) {
			$update['post_title'] = sanitize_text_field( (string) $a['title'] );
		}
		if ( isset( $a['slug'] ) ) {
			$update['post_name'] = sanitize_title( (string) $a['slug'] );
		}
		if ( isset( $a['status'] ) ) {
			$status = (string) $a['status'];
			$pto    = get_post_type_object( $post->post_type );
			if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
				$status = 'pending';
				$call->warn( 'You cannot publish: set to pending review.' );
			}
			$update['post_status'] = $status;
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( wp_slash( $update ) );
		}
		if ( isset( $a['template'] ) && 'page' === $post->post_type ) {
			// Same list as the editor: the theme's page templates and Uncoder's own.
			$template = sanitize_text_field( (string) $a['template'] );
			$allowed  = array_merge( array( '', 'default', 'uncoder-canvas', 'uncoder-full-width' ), array_keys( wp_get_theme()->get_page_templates( $post, $post->post_type ) ) );
			if ( in_array( $template, $allowed, true ) ) {
				update_post_meta( $post->ID, '_wp_page_template', '' === $template ? 'default' : $template );
			} else {
				$call->warn( sprintf( 'Unknown page template "%s" (kept the current one). Use default, uncoder-full-width, uncoder-canvas or a template of the theme.', $template ) );
			}
		}
		if ( ! empty( $a['page_settings'] ) && is_array( $a['page_settings'] ) ) {
			$doc->save_page_settings( $a['page_settings'] );
		}
		$call->summary = sprintf( 'Updated "%s"', get_the_title( $post->ID ) );
		$out           = array( 'page' => Helpers::post_info( get_post( $post->ID ) ) );
		if ( null !== $outline ) {
			$out['outline'] = $outline;
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function edit_elements( array $a, Call $call ) {
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$doc     = Plugin::instance()->documents()->get( $post->ID );
		$map     = Doc_Map::from_tree( $doc->elements() );
		$errors  = array();
		$changed = array();
		$created = array();
		$ops     = (array) $a['operations'];
		if ( count( $ops ) > 200 ) {
			return new WP_Error( 'too_many', 'At most 200 operations per call.' );
		}

		foreach ( array_values( $ops ) as $i => $op ) {
			$res = $this->apply_op( $map, is_array( $op ) ? $op : array(), $i );
			if ( is_wp_error( $res ) ) {
				$errors[] = sprintf( 'operations[%d] (%s): %s', $i, (string) ( $op['op'] ?? '?' ), $res->get_error_message() );
				$data     = $res->get_error_data();
				if ( is_array( $data ) && ! empty( $data['details'] ) ) {
					foreach ( array_slice( (array) $data['details'], 0, 15 ) as $d ) {
						$errors[] = '    ' . $d;
					}
				}
				continue;
			}
			$changed = array_merge( $changed, $res['changed'] ?? array() );
			if ( ! empty( $res['created'] ) ) {
				$created[ $i ] = $res['created'];
			}
		}
		if ( $errors ) {
			return Helpers::errors_to_wp_error( 'No changes were saved because some operations failed:', $errors );
		}
		$tree = $map->to_tree();
		if ( ! empty( $a['dry_run'] ) ) {
			return array(
				'dry_run' => true,
				'valid'   => true,
				'outline' => Helpers::outline( $tree ),
			);
		}
		$call->snapshot_post( $post->ID );
		$result = $doc->save( $tree, array( 'content_fallback' => true ) );
		foreach ( $result['errors'] as $e ) {
			$call->warn( $e );
		}
		$call->summary = sprintf( '%d operation(s) on "%s"', count( $ops ), get_the_title( $post->ID ) );
		$total         = count( Tree::ids( $result['elements'] ) );
		return array(
			'saved'       => true,
			'page'        => array( 'id' => $post->ID, 'title' => get_the_title( $post->ID ), 'url' => get_permalink( $post->ID ) ),
			'changed_ids' => array_values( array_unique( $changed ) ),
			'created_ids' => (object) $created,
			'outline'     => $total <= 120 ? Helpers::outline( $result['elements'] ) : '(large page: use get_page with element_id to inspect)',
		);
	}

	/**
	 * @param array<string,mixed> $op Operation.
	 * @return array<string,mixed>|WP_Error
	 */
	private function apply_op( Doc_Map $map, array $op, int $i ) {
		$type = (string) ( $op['op'] ?? $op['type'] ?? '' );
		$id   = isset( $op['id'] ) ? (string) $op['id'] : '';
		$need = static function () use ( $map, $id ) {
			return $map->has( $id ) ? null : new WP_Error( 'not_found', sprintf( 'Element "%s" not found. Use get_page (outline) for ids.', $id ) );
		};

		switch ( $type ) {
			case 'update':
				if ( $e = $need() ) {
					return $e;
				}
				$node  = $map->nodes[ $id ];
				$patch = is_array( $op['settings'] ?? null ) ? $op['settings'] : array();
				$merged = ! empty( $op['replace'] ) ? $patch : Helpers::merge_settings( (array) ( $node['settings'] ?? array() ), $patch );
				$check = $this->validate_node( array_merge( $node, array( 'settings' => $merged ) ) );
				if ( is_wp_error( $check ) ) {
					return $check;
				}
				$map->nodes[ $id ]['settings'] = $check['settings'];
				if ( array_key_exists( 'label', $op ) ) {
					if ( is_string( $op['label'] ) && '' !== $op['label'] ) {
						$map->nodes[ $id ]['label'] = sanitize_text_field( $op['label'] );
					} else {
						unset( $map->nodes[ $id ]['label'] );
					}
				}
				if ( array_key_exists( 'disabled', $op ) ) {
					if ( $op['disabled'] ) {
						$map->nodes[ $id ]['disabled'] = true;
					} else {
						unset( $map->nodes[ $id ]['disabled'] );
					}
				}
				return array( 'changed' => array( $id ) );

			case 'insert':
				$target = $this->target( $map, $op );
				if ( is_wp_error( $target ) ) {
					return $target;
				}
				$raw = isset( $op['elements'] ) && is_array( $op['elements'] ) ? $op['elements'] : ( isset( $op['element'] ) ? array( $op['element'] ) : array() );
				if ( ! $raw ) {
					return new WP_Error( 'invalid', 'Provide "element" or "elements".' );
				}
				$nodes = $this->prepare_nodes( $map, $raw, $target['parent'] );
				if ( is_wp_error( $nodes ) ) {
					return $nodes;
				}
				$ids = $map->insert( $target['parent'], $target['index'], $nodes );
				return array( 'changed' => $ids, 'created' => $ids );

			case 'delete':
				if ( $e = $need() ) {
					return $e;
				}
				$parent = $map->parent_of( $id );
				if ( $parent && null !== $this->nested( $map, $parent ) ) {
					return new WP_Error( 'invalid', 'This container is an item of a nested widget: remove the item from the widget\'s repeater instead (update the parent with fewer rows and delete this container in the same call).' );
				}
				$map->remove( $id );
				return array( 'changed' => array( $id ) );

			case 'move':
				if ( $e = $need() ) {
					return $e;
				}
				$target = $this->target( $map, $op );
				if ( is_wp_error( $target ) ) {
					return $target;
				}
				if ( null !== $target['parent'] && ( $target['parent'] === $id || $map->is_descendant( $target['parent'], $id ) ) ) {
					return new WP_Error( 'invalid', 'Cannot move an element inside itself.' );
				}
				if ( null === $target['parent'] && ! $this->is_container( $map->nodes[ $id ]['type'] ) ) {
					return new WP_Error( 'invalid', 'Only containers can sit at the top level. Move the widget into a container.' );
				}
				$map->move( $id, $target['parent'], $target['index'] );
				return array( 'changed' => array( $id ) );

			case 'duplicate':
				if ( $e = $need() ) {
					return $e;
				}
				$copy = $map->to_tree( array( $id ) );
				$copy = $this->strip_ids( $copy );
				$ids  = $map->insert( $map->parent_of( $id ), $map->index_of( $id ) + 1, $copy );
				return array( 'changed' => $ids, 'created' => $ids );

			case 'replace':
				if ( $e = $need() ) {
					return $e;
				}
				$parent = $map->parent_of( $id );
				$index  = $map->index_of( $id );
				$nodes  = $this->prepare_nodes( $map, array( $op['element'] ?? array() ), $parent, array( $id ) );
				if ( is_wp_error( $nodes ) ) {
					return $nodes;
				}
				$map->remove( $id );
				if ( empty( $nodes[0]['id'] ) || $map->has( (string) $nodes[0]['id'] ) ) {
					$nodes[0]['id'] = $id;
				}
				$ids = $map->insert( $parent, $index, $nodes );
				return array( 'changed' => $ids );

			case 'wrap':
				$ids = array_values( array_filter( array_map( 'strval', (array) ( $op['ids'] ?? array() ) ) ) );
				if ( ! $ids ) {
					return new WP_Error( 'invalid', 'Provide "ids" (siblings to wrap).' );
				}
				foreach ( $ids as $w ) {
					if ( ! $map->has( $w ) ) {
						return new WP_Error( 'not_found', 'Element ' . $w . ' not found.' );
					}
				}
				$parent = $map->parent_of( $ids[0] );
				foreach ( $ids as $w ) {
					if ( $map->parent_of( $w ) !== $parent ) {
						return new WP_Error( 'invalid', 'All wrapped elements must have the same parent.' );
					}
				}
				$wrapper = $this->prepare_nodes( $map, array( array( 'type' => 'container', 'settings' => is_array( $op['settings'] ?? null ) ? $op['settings'] : array() ) ), $parent );
				if ( is_wp_error( $wrapper ) ) {
					return $wrapper;
				}
				$index       = min( array_map( array( $map, 'index_of' ), $ids ) );
				list( $wid ) = $map->insert( $parent, $index, $wrapper );
				foreach ( $ids as $n => $w ) {
					$map->move( $w, $wid, $n );
				}
				return array( 'changed' => array_merge( array( $wid ), $ids ), 'created' => array( $wid ) );

			case 'set_dynamic':
				if ( $e = $need() ) {
					return $e;
				}
				$key     = (string) ( $op['key'] ?? '' );
				$el      = Plugin::instance()->elements()->get( (string) $map->nodes[ $id ]['type'] );
				$control = $el ? $el->get_control( $key ) : null;
				if ( ! $control || empty( $control['dynamic'] ) ) {
					return new WP_Error( 'invalid', sprintf( 'Setting "%s" does not accept dynamic data.', $key ) );
				}
				$def = Plugin::instance()->tags()->sanitize(
					array(
						'tag'      => $op['tag'] ?? '',
						'options'  => $op['options'] ?? array(),
						'before'   => $op['before'] ?? '',
						'after'    => $op['after'] ?? '',
						'fallback' => $op['fallback'] ?? '',
					)
				);
				if ( null === $def ) {
					return new WP_Error( 'invalid', 'Unknown dynamic tag. Call list_dynamic_tags.' );
				}
				$map->nodes[ $id ]['dynamic'][ $key ] = $def;
				return array( 'changed' => array( $id ) );

			case 'unset_dynamic':
				if ( $e = $need() ) {
					return $e;
				}
				unset( $map->nodes[ $id ]['dynamic'][ (string) ( $op['key'] ?? '' ) ] );
				if ( empty( $map->nodes[ $id ]['dynamic'] ) ) {
					unset( $map->nodes[ $id ]['dynamic'] );
				}
				return array( 'changed' => array( $id ) );
		}
		return new WP_Error( 'invalid', 'Unknown op. Use update, insert, delete, move, duplicate, replace, wrap, set_dynamic or unset_dynamic.' );
	}

	/**
	 * Resolves the target parent + index of an insert/move.
	 *
	 * @return array{parent:?string, index:int}|WP_Error
	 */
	private function target( Doc_Map $map, array $op ) {
		foreach ( array( 'before', 'after' ) as $rel ) {
			if ( ! empty( $op[ $rel ] ) ) {
				$sib = (string) $op[ $rel ];
				if ( ! $map->has( $sib ) ) {
					return new WP_Error( 'not_found', sprintf( '"%s" element %s not found.', $rel, $sib ) );
				}
				return array(
					'parent' => $map->parent_of( $sib ),
					'index'  => $map->index_of( $sib ) + ( 'after' === $rel ? 1 : 0 ),
				);
			}
		}
		$parent = array_key_exists( 'parent_id', $op ) && null !== $op['parent_id'] && '' !== $op['parent_id'] ? (string) $op['parent_id'] : null;
		if ( null !== $parent ) {
			if ( ! $map->has( $parent ) ) {
				return new WP_Error( 'not_found', sprintf( 'parent_id %s not found.', $parent ) );
			}
			if ( ! $this->is_container( $map->nodes[ $parent ]['type'] ) ) {
				return new WP_Error( 'invalid', sprintf( '%s is a %s widget, not a container. Insert before/after it or into its parent container.', $parent, $map->nodes[ $parent ]['type'] ) );
			}
		}
		$list  = $map->children_of( $parent );
		$pos   = $op['position'] ?? $op['index'] ?? 'end';
		$index = 'start' === $pos ? 0 : ( is_numeric( $pos ) ? (int) $pos : count( $list ) );
		return array(
			'parent' => $parent,
			'index'  => $index,
		);
	}

	private function is_container( string $type ): bool {
		$el = Plugin::instance()->elements()->get( $type );
		return $el && $el->is_container();
	}

	private function nested( Doc_Map $map, string $id ): ?array {
		$el = Plugin::instance()->elements()->get( (string) ( $map->nodes[ $id ]['type'] ?? '' ) );
		return $el ? $el->nested() : null;
	}

	/**
	 * Validates new nodes for insertion (normalize mode, fresh ids, top-level wrapping).
	 *
	 * @param array<int, mixed> $raw Raw nodes.
	 * @param string[]          $allow_ids Ids that may be reused (replace).
	 * @return array<int, array<string,mixed>>|WP_Error
	 */
	private function prepare_nodes( Doc_Map $map, array $raw, ?string $parent, array $allow_ids = array() ) {
		$reserved = array_diff( $map->ids(), $allow_ids );
		$tree     = new Tree( 'normalize' );
		$tree->reserve_ids( $reserved );
		$nodes = $tree->process( $raw );
		if ( $tree->errors ) {
			return Helpers::errors_to_wp_error( 'Invalid element(s).', $tree->errors );
		}
		if ( null === $parent ) {
			foreach ( $nodes as $k => $node ) {
				if ( ! $this->is_container( $node['type'] ) ) {
					$nodes[ $k ] = array(
						'type'     => 'container',
						'settings' => array(),
						'children' => array( $node ),
					);
				}
			}
		}
		return $nodes;
	}

	/**
	 * @return array<string,mixed>|WP_Error Node with normalized settings.
	 */
	private function validate_node( array $node ) {
		$el = Plugin::instance()->elements()->get( (string) $node['type'] );
		if ( ! $el ) {
			return new WP_Error( 'invalid', 'Unknown element type.' );
		}
		$errors   = array();
		$settings = Plugin::instance()->controls()->process_settings( (array) $node['settings'], $el->get_controls(), 'normalize', $errors, 'settings.' );
		if ( $errors ) {
			return Helpers::errors_to_wp_error( 'Invalid settings.', $errors );
		}
		$node['settings'] = $settings;
		return $node;
	}

	/**
	 * @param array<int, array<string,mixed>> $tree Tree.
	 * @return array<int, array<string,mixed>>
	 */
	private function strip_ids( array $tree ): array {
		foreach ( $tree as &$node ) {
			unset( $node['id'] );
			foreach ( $node['settings'] ?? array() as $k => $v ) {
				if ( is_array( $v ) && isset( $v[0]['_id'] ) ) {
					foreach ( $node['settings'][ $k ] as &$row ) {
						$row['_id'] = Utils::generate_id();
					}
					unset( $row );
				}
			}
			if ( ! empty( $node['children'] ) ) {
				$node['children'] = $this->strip_ids( $node['children'] );
			}
		}
		return $tree;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function find_elements( array $a ) {
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$elements = Plugin::instance()->documents()->get( $post->ID )->elements();
		$type     = sanitize_key( (string) ( $a['type'] ?? '' ) );
		$text     = strtolower( trim( (string) ( $a['text'] ?? '' ) ) );
		$setting  = (string) ( $a['has_setting'] ?? '' );
		$found    = array();
		Tree::walk(
			$elements,
			static function ( $node, $parent ) use ( $type, $text, $setting, &$found ) {
				if ( '' !== $type && $node['type'] !== $type ) {
					return true;
				}
				$s = (array) ( $node['settings'] ?? array() );
				if ( '' !== $setting && ! array_key_exists( $setting, $s ) ) {
					return true;
				}
				if ( '' !== $text ) {
					$hay = strtolower( wp_strip_all_tags( (string) wp_json_encode( $s ) ) );
					if ( false === strpos( $hay, $text ) ) {
						return true;
					}
				}
				$found[] = array(
					'id'          => $node['id'],
					'parent_id'   => '' === $parent ? null : $parent,
					'description' => Helpers::describe( $node ),
				);
				return count( $found ) < 100;
			}
		);
		return array(
			'count'    => count( $found ),
			'elements' => $found,
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function duplicate_page( array $a, Call $call ) {
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$pto = get_post_type_object( $post->post_type );
		if ( ! $pto || ! current_user_can( $pto->cap->create_posts ) ) {
			return new WP_Error( 'forbidden', 'You cannot create items of this type.' );
		}
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $post->post_type,
					'post_title'   => sanitize_text_field( (string) ( $a['title'] ?? $post->post_title . ' (copy)' ) ),
					'post_status'  => 'draft',
					'post_content' => $post->post_content,
					'post_parent'  => $post->post_parent,
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		foreach ( array( Utils::META_DATA, Utils::META_MODE, Utils::META_PAGE, Utils::META_TYPE, Utils::META_TPL, '_wp_page_template' ) as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( '' !== $value && null !== $value ) {
				update_post_meta( (int) $id, $key, is_string( $value ) ? wp_slash( $value ) : $value );
			}
		}
		Plugin::instance()->documents()->get( (int) $id )->regenerate();
		$call->object_id = (int) $id;
		$call->summary   = 'Duplicated #' . $post->ID;
		return array( 'created' => Helpers::post_info( get_post( (int) $id ) ) );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function delete_page( array $a, Call $call ) {
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( Settings::get( 'confirm_destructive' ) && empty( $a['confirm'] ) ) {
			return new WP_Error( 'confirm', sprintf( 'This moves "%s" (#%d) to the trash. Ask the user to confirm, then call again with confirm: true.', get_the_title( $post ), $post->ID ) );
		}
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return new WP_Error( 'forbidden', 'You cannot delete this item.' );
		}
		wp_trash_post( $post->ID );
		$call->object_id = $post->ID;
		$call->summary   = 'Trashed "' . $post->post_title . '"';
		return array(
			'trashed' => $post->ID,
			'note'    => 'Recoverable from the WordPress trash for 30 days.',
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function publish_page( array $a, Call $call ) {
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$status = (string) ( $a['status'] ?? 'publish' );
		$pto    = get_post_type_object( $post->post_type );
		if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
			return new WP_Error( 'forbidden', 'You cannot publish this item. Set status "pending" to submit it for review.' );
		}
		$call->snapshot_post( $post->ID );
		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => $status,
			)
		);
		$call->summary = sprintf( '"%s" → %s', $post->post_title, $status );
		return array( 'page' => Helpers::post_info( get_post( $post->ID ) ) );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function render_page( array $a ) {
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$doc = Plugin::instance()->documents()->get( $post->ID );
		// Pages render as themselves; templates with sample content (a real post, archive or search).
		$preview = \Uncoder\Builder\Theme\Preview_Context::resolve( $post->ID );
		$scope   = new \Uncoder\Builder\Theme\Preview_Context();
		$scope->apply( $preview );
		$renderer = new Renderer( $post->ID, false, null === $preview['query'] ? (int) $preview['post'] : 0 );
		$html     = $renderer->render_document( $doc->elements() );
		$scope->restore();
		$out = array(
			'page'       => Helpers::post_info( $post ),
			'preview'    => get_preview_post_link( $post ),
			'html_bytes' => strlen( $html ),
			'text'       => self::text_outline( $html ),
		);
		if ( (int) $preview['post'] !== $post->ID || null !== $preview['query'] ) {
			$out['sample_content'] = null !== $preview['query'] ? 'Rendered with a sample query: ' . wp_json_encode( $preview['query'] ) : 'Rendered with sample post #' . $preview['post'] . ' ("' . get_the_title( (int) $preview['post'] ) . '"). Set page_settings.preview_post to choose another.';
		}
		return $out;
	}

	/**
	 * Turns rendered HTML into a readable outline: headings (#), paragraphs, list items, links, images.
	 */
	public static function text_outline( string $html ): string {
		$html = preg_replace( '#<(script|style|svg|noscript)[^>]*>.*?</\1>#is', ' ', $html );
		$html = preg_replace_callback(
			'#<h([1-6])[^>]*>(.*?)</h\1>#is',
			static fn( $m ) => "\n" . str_repeat( '#', (int) $m[1] ) . ' ' . trim( wp_strip_all_tags( $m[2] ) ) . "\n",
			(string) $html
		);
		$html = preg_replace_callback(
			'#<img[^>]*>#i',
			static function ( $m ) {
				$alt = preg_match( '/alt="([^"]*)"/i', $m[0], $a ) ? $a[1] : null;
				return ' [image' . ( null === $alt ? ' — NO ALT' : ( '' === $alt ? ' — decorative (empty alt)' : ': ' . html_entity_decode( $alt ) ) ) . '] ';
			},
			(string) $html
		);
		$html = preg_replace_callback(
			'#<a[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is',
			static fn( $m ) => ' [' . trim( wp_strip_all_tags( $m[2] ) ) . '](' . $m[1] . ') ',
			(string) $html
		);
		$html = preg_replace( '#<li[^>]*>#i', "\n• ", (string) $html );
		$html = preg_replace( '#</(p|div|section|li|figure|blockquote|header|footer|ul|ol)>#i', "\n", (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES );
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		$text = preg_replace( "/\n\s*\n+/", "\n", (string) $text );
		return trim( mb_substr( (string) $text, 0, 12000 ) );
	}
}
