<?php
/**
 * Theme builder tools: headers, footers, singles, archives, 404, popups, mega menus, loop items, sections.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Mcp\Call;
use Uncoder\Builder\Mcp\Registry;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Popups\Popups;
use Uncoder\Builder\Theme\Conditions;
use Uncoder\Builder\Theme\Theme_Builder;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * list_templates, create_template, update_template, set_template_conditions, get_conditions_map, configure_popup.
 */
final class Template_Tools {

	/** Conditions applied when none are given. */
	private const DEFAULT_CONDITIONS = array(
		'header'         => array( array( 'type' => 'include', 'rule' => 'general' ) ),
		'footer'         => array( array( 'type' => 'include', 'rule' => 'general' ) ),
		'single-post'    => array( array( 'type' => 'include', 'rule' => 'singular', 'post_type' => 'post' ) ),
		'single-page'    => array( array( 'type' => 'include', 'rule' => 'singular', 'post_type' => 'page' ) ),
		'archive'        => array( array( 'type' => 'include', 'rule' => 'archive' ) ),
		'search-results' => array( array( 'type' => 'include', 'rule' => 'search' ) ),
		'error-404'      => array( array( 'type' => 'include', 'rule' => 'not_found' ) ),
		'popup'          => array( array( 'type' => 'include', 'rule' => 'general' ) ),
	);

	/** Types that are placed by conditions (the others are used by reference). */
	private const CONDITIONAL = array( 'header', 'footer', 'single-post', 'single-page', 'single', 'archive', 'search-results', 'error-404', 'popup' );

	public function register( Registry $r ): void {
		$types      = array_keys( Post_Types::TEMPLATE_TYPES );
		$conditions = array(
			'type'        => 'array',
			'description' => 'Where the template applies: [{"type":"include","rule":"general"}] = entire site. Rules: general, singular (+post_type, ids), front_page, posts_page, in_term (+taxonomy, ids), child_of (+ids), by_author (+ids), archive (+post_type | taxonomy, ids), author, date, search, not_found. Add {"type":"exclude",…} to exclude. Defaults per type when omitted (header/footer: entire site; single-post: all posts; error-404: 404 …).',
			'items'       => array( 'type' => 'object' ),
		);
		$popup      = array(
			'type'        => 'object',
			'description' => 'Popup settings: layout (modal|slide_in|bar|fullscreen), position (center|top|bottom|top-left|…), width ("560px"), overlay, overlay_color, background, radius, padding, close_button, close_on_overlay, animation (zoom|fade|slide-up|slide-down|none), triggers {load:{enabled,delay}, scroll:{enabled,percent}, scroll_to:{enabled,selector}, click:{enabled,selector}, exit_intent:{enabled}, inactivity:{enabled,seconds}, page_views:{enabled,count}}, frequency {times, period: session|day|week|month|forever}, devices [desktop,tablet,mobile], visitors (all|logged_in|logged_out), avoid_multiple.',
		);

		$r->add(
			array(
				'name'        => 'list_templates',
				'title'       => 'List templates',
				'description' => 'Theme templates (headers, footers, single/archive/404 layouts, popups, mega menus, loop items, reusable sections) with their conditions and status.',
				'scope'       => 'read',
				'annotations' => array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array( 'type' => 'string', 'enum' => $types ),
					),
				),
				'callback'    => array( $this, 'list_templates' ),
			)
		);

		$r->add(
			array(
				'name'        => 'create_template',
				'title'       => 'Create template',
				'description' => 'Creates a theme template from an element tree. Published templates with conditions go live immediately (a header for the entire site replaces the theme header) — pass status "draft" to stage one. Popups: pass popup settings or call configure_popup; open one from a link "#uncoder-popup:open:{id}". Mega menus: attach with set_mega_menu. Loop items: use in the loop-grid/posts widget. Sections: insert with a "template" widget. See get_build_guide("theme-builder").',
				'scope'       => 'design',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'type'       => array( 'type' => 'string', 'enum' => $types ),
						'title'      => array( 'type' => 'string' ),
						'elements'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'conditions' => $conditions,
						'status'     => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ) ),
						'popup'      => $popup,
						'page_settings' => array(
							'type'        => 'object',
							'description' => 'Template settings. Headers: header_sticky ("" | "always" | "reveal"; to pin only part of the header, set "_sticky":"top" on the row that should stay and the rows above it scroll away), header_scrolled_shadow (bool), header_scrolled_bg (color), header_scrolled_height ("64px" shrink), header_scroll_offset (px), header_transparent ("" | "all" | "front" | "selected"), header_transparent_color, header_transparent_logo ({"id":…}), header_transparent_logo_white (bool), header_transparent_keep_colors (bool: only overlay the page and keep the header colors, for light heroes).',
						),
						'strict'     => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'type', 'title' ),
				),
				'callback'    => array( $this, 'create_template' ),
			)
		);

		$r->add(
			array(
				'name'        => 'update_template',
				'title'       => 'Update template',
				'description' => 'Updates a template: element tree (replaces it), title, status, conditions or popup settings. For small content edits use edit_elements with the template id.',
				'scope'       => 'design',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'elements'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'conditions' => $conditions,
						'status'     => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ) ),
						'popup'      => $popup,
						'page_settings' => array(
							'type'        => 'object',
							'description' => 'Template settings. Headers: header_sticky ("" | "always" | "reveal"; to pin only part of the header, set "_sticky":"top" on the row that should stay and the rows above it scroll away), header_scrolled_shadow (bool), header_scrolled_bg (color), header_scrolled_height ("64px" shrink), header_scroll_offset (px), header_transparent ("" | "all" | "front" | "selected"), header_transparent_color, header_transparent_logo ({"id":…}), header_transparent_logo_white (bool), header_transparent_keep_colors (bool: only overlay the page and keep the header colors, for light heroes).',
						),
						'strict'     => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'update_template' ),
			)
		);

		$r->add(
			array(
				'name'        => 'set_template_conditions',
				'title'       => 'Set template conditions',
				'description' => 'Replaces where a template is displayed. [] removes it from everywhere (it stays as a draft-like unused template).',
				'scope'       => 'design',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array( 'type' => 'integer' ),
						'conditions' => $conditions,
					),
					'required'   => array( 'id', 'conditions' ),
				),
				'callback'    => array( $this, 'set_conditions' ),
			)
		);

		$r->add(
			array(
				'name'        => 'get_conditions_map',
				'title'       => 'Get conditions map',
				'description' => 'Which template is used where: every active template per type with a readable conditions summary, overlaps between templates of the same type, and the condition rules reference.',
				'scope'       => 'read',
				'annotations' => array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'callback'    => array( $this, 'conditions_map' ),
			)
		);

		$r->add(
			array(
				'name'        => 'configure_popup',
				'title'       => 'Configure popup',
				'description' => 'Sets popup behaviour and look (merged into current settings): layout, size, overlay, triggers, frequency, devices, visitors. Conditions decide on which pages the popup is available. To open it only from a button, disable the load trigger and link the button to "#uncoder-popup:open:{id}".',
				'scope'       => 'design',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array( 'type' => 'integer' ),
						'settings'   => $popup,
						'conditions' => $conditions,
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'configure_popup' ),
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function list_templates( array $a ): array {
		$args = array(
			'post_type'      => Post_Types::TEMPLATE,
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => 200,
			'no_found_rows'  => true,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( ! empty( $a['type'] ) ) {
			$args['meta_key']   = Utils::META_TYPE; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = sanitize_key( (string) $a['type'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		$items = array();
		foreach ( get_posts( $args ) as $post ) {
			// Site-wide templates are only listed to users who may edit them (see Post_Types::template_caps()).
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = $this->info( $post );
			}
		}
		return array(
			'types' => Post_Types::TEMPLATE_TYPES,
			'items' => $items,
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_template( array $a, Call $call ) {
		$type = sanitize_key( (string) $a['type'] );
		if ( ! isset( Post_Types::TEMPLATE_TYPES[ $type ] ) ) {
			return new WP_Error( 'invalid', sprintf( 'Unknown template type "%s". Use one of: %s.', $type, implode( ', ', array_keys( Post_Types::TEMPLATE_TYPES ) ) ) );
		}
		$pto = get_post_type_object( Post_Types::TEMPLATE );
		if ( ! $pto || ! current_user_can( $pto->cap->create_posts ) || ! current_user_can( $pto->cap->publish_posts ) ) {
			return new WP_Error( 'forbidden', 'You cannot create theme templates.' );
		}
		if ( Post_Types::needs_theme_caps( $type ) && ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'forbidden', sprintf( '"%s" templates change the whole site and require the edit_theme_options capability (sections do not).', $type ) );
		}
		list( $tree, $errors ) = Helpers::normalize_tree( $a['elements'] ?? array() );
		if ( $errors && ! empty( $a['strict'] ) ) {
			return Helpers::errors_to_wp_error( 'The elements have problems (strict mode, nothing was created).', $errors );
		}

		$conds = null;
		if ( in_array( $type, self::CONDITIONAL, true ) ) {
			if ( array_key_exists( 'conditions', $a ) ) {
				$cond_errors = array();
				$conds       = Conditions::sanitize( $a['conditions'], $cond_errors );
				if ( $cond_errors ) {
					return new WP_Error( 'invalid', 'Invalid conditions (nothing was created).', array( 'details' => $cond_errors ) );
				}
			} else {
				$conds = self::DEFAULT_CONDITIONS[ $type ] ?? array();
				if ( ! $conds ) {
					$call->warn( 'No conditions given: this template is not displayed anywhere yet. Call set_template_conditions.' );
				}
			}
		} elseif ( ! empty( $a['conditions'] ) ) {
			$call->warn( sprintf( '"%s" templates are used by reference, not by conditions; conditions were ignored.', $type ) );
		}

		$status = (string) ( $a['status'] ?? 'publish' );
		$id     = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => Post_Types::TEMPLATE,
					'post_title'  => sanitize_text_field( (string) $a['title'] ),
					'post_status' => 'draft',
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$id = (int) $id;
		update_post_meta( $id, Utils::META_TYPE, $type );
		if ( null !== $conds ) {
			update_post_meta( $id, Utils::META_CONDS, $conds );
		}
		if ( 'popup' === $type ) {
			$popup_errors = array();
			update_post_meta( $id, Utils::META_TPL, Popups::sanitize( (array) ( $a['popup'] ?? array() ), $popup_errors ) );
			$errors = array_merge( $errors, $popup_errors );
		}
		$doc    = Plugin::instance()->documents()->get( $id );
		$result = $doc->save( $tree );
		if ( ! empty( $a['page_settings'] ) && is_array( $a['page_settings'] ) ) {
			$doc->save_page_settings( $a['page_settings'] );
		}
		if ( 'publish' === $status ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
		}
		if ( Theme_Builder::instance() ) {
			Theme_Builder::instance()->rebuild_index();
		}
		foreach ( array_merge( $errors, $result['errors'] ) as $e ) {
			$call->warn( $e );
		}
		$call->object_id = $id;
		$call->summary   = sprintf( 'Created %s template "%s"', $type, get_the_title( $id ) );

		$out = array(
			'id'      => (int) $id,
			'created' => $this->info( get_post( $id ) ),
			'outline' => Helpers::outline( $result['elements'] ),
		);
		$out['next'] = $this->next_hint( $type, $id, $status );
		return $out;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_template( array $a, Call $call ) {
		$post = $this->template( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$type = (string) get_post_meta( $post->ID, Utils::META_TYPE, true );
		if ( isset( $a['status'] ) && 'publish' === $a['status'] && 'publish' !== $post->post_status && ! current_user_can( 'publish_post', $post->ID ) ) {
			return new WP_Error( 'forbidden', 'You cannot publish this template (nothing was saved).' );
		}
		$call->snapshot_post( $post->ID );

		$out = array();
		if ( array_key_exists( 'conditions', $a ) ) {
			$cond_errors = array();
			$conds       = Conditions::sanitize( $a['conditions'], $cond_errors );
			if ( $cond_errors ) {
				return new WP_Error( 'invalid', 'Invalid conditions (nothing was saved).', array( 'details' => $cond_errors ) );
			}
		}
		if ( array_key_exists( 'elements', $a ) ) {
			list( $tree, $errors ) = Helpers::normalize_tree( $a['elements'] );
			if ( $errors && ! empty( $a['strict'] ) ) {
				return Helpers::errors_to_wp_error( 'The elements have problems (strict mode, nothing was saved).', $errors );
			}
			$result         = Plugin::instance()->documents()->get( $post->ID )->save( $tree );
			$out['outline'] = Helpers::outline( $result['elements'] );
			foreach ( array_merge( $errors, $result['errors'] ) as $e ) {
				$call->warn( $e );
			}
		}
		if ( isset( $conds ) ) {
			update_post_meta( $post->ID, Utils::META_CONDS, $conds );
		}
		if ( ! empty( $a['page_settings'] ) && is_array( $a['page_settings'] ) ) {
			Plugin::instance()->documents()->get( $post->ID )->save_page_settings( $a['page_settings'] );
		}
		if ( isset( $a['popup'] ) && 'popup' === $type ) {
			$popup_errors = array();
			$current      = get_post_meta( $post->ID, Utils::META_TPL, true );
			update_post_meta( $post->ID, Utils::META_TPL, Popups::sanitize( (array) $a['popup'], $popup_errors, is_array( $current ) ? $current : array() ) );
			foreach ( $popup_errors as $e ) {
				$call->warn( $e );
			}
		}
		$update = array( 'ID' => $post->ID );
		if ( isset( $a['title'] ) ) {
			$update['post_title'] = sanitize_text_field( (string) $a['title'] );
		}
		if ( isset( $a['status'] ) ) {
			$update['post_status'] = 'publish' === $a['status'] ? 'publish' : 'draft';
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( wp_slash( $update ) );
		}
		if ( Theme_Builder::instance() ) {
			Theme_Builder::instance()->rebuild_index();
		}
		$call->summary   = sprintf( 'Updated %s template "%s"', $type, get_the_title( $post->ID ) );
		$out['template'] = $this->info( get_post( $post->ID ) );
		return $out;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function set_conditions( array $a, Call $call ) {
		$post = $this->template( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$type = (string) get_post_meta( $post->ID, Utils::META_TYPE, true );
		if ( ! in_array( $type, self::CONDITIONAL, true ) ) {
			return new WP_Error( 'invalid', sprintf( '"%s" templates are used by reference, not placed by conditions.', $type ) );
		}
		$errors = array();
		$conds  = Conditions::sanitize( $a['conditions'], $errors );
		if ( $errors ) {
			return new WP_Error( 'invalid', 'Invalid conditions (nothing was saved).', array( 'details' => $errors ) );
		}
		$call->snapshot_post( $post->ID );
		update_post_meta( $post->ID, Utils::META_CONDS, $conds );
		if ( Theme_Builder::instance() ) {
			Theme_Builder::instance()->rebuild_index();
		}
		$call->summary = sprintf( 'Conditions of "%s": %s', get_the_title( $post ), Conditions::summary( $conds ) );
		$out           = array( 'template' => $this->info( get_post( $post->ID ) ) );
		if ( 'publish' !== $post->post_status ) {
			$out['note'] = 'The template is a draft: publish it (update_template status "publish") for the conditions to take effect.';
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function conditions_map() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'forbidden', 'The theme template map requires the edit_theme_options capability.' );
		}
		$tb       = Theme_Builder::instance();
		$overview = $tb ? $tb->overview() : array();
		$overlaps = array();
		foreach ( $overview as $type => $entries ) {
			if ( 'popup' === $type || count( $entries ) < 2 ) {
				continue;
			}
			$seen = array();
			foreach ( $entries as $entry ) {
				foreach ( (array) $entry['conditions'] as $c ) {
					if ( 'include' !== ( $c['type'] ?? 'include' ) ) {
						continue;
					}
					$key = $c['rule'] . '|' . ( $c['post_type'] ?? '' ) . '|' . ( $c['taxonomy'] ?? '' ) . '|' . implode( ',', (array) ( $c['ids'] ?? array() ) );
					if ( isset( $seen[ $key ] ) && $seen[ $key ] !== $entry['id'] ) {
						$overlaps[] = sprintf( '%s: templates #%d and #%d both include "%s"; the most recently modified one wins.', $type, $seen[ $key ], $entry['id'], Conditions::summary( array( $c ) ) );
					}
					$seen[ $key ] = $entry['id'];
				}
			}
		}
		$rules = array();
		foreach ( Conditions::rules() as $id => $rule ) {
			$rules[ $id ] = $rule['label'] . ( $rule['fields'] ? ' (fields: ' . implode( ', ', $rule['fields'] ) . ')' : '' );
		}
		return array(
			'active'      => $overview,
			'overlaps'    => $overlaps,
			'rules'       => $rules,
			'precedence'  => 'Exclusions always win; otherwise the most specific matching rule wins (front_page/not_found > search > child_of/in_term/author > singular/archive > general), then the newest template.',
			'unconfigured' => array_values(
				array_filter(
					array_map(
						fn( $t ) => $t['conditions'] ? null : $t['id'],
						array_filter( $this->list_templates( array() )['items'], static fn( $t ) => in_array( $t['type'], self::CONDITIONAL, true ) )
					)
				)
			),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function configure_popup( array $a, Call $call ) {
		$post = $this->template( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'popup' !== get_post_meta( $post->ID, Utils::META_TYPE, true ) ) {
			return new WP_Error( 'invalid', 'Template #' . $post->ID . ' is not a popup.' );
		}
		if ( ! isset( $a['settings'] ) && ! isset( $a['conditions'] ) ) {
			return new WP_Error( 'invalid', 'Pass settings and/or conditions.' );
		}
		$call->snapshot_post( $post->ID );
		if ( isset( $a['conditions'] ) ) {
			$errors = array();
			$conds  = Conditions::sanitize( $a['conditions'], $errors );
			if ( $errors ) {
				return new WP_Error( 'invalid', 'Invalid conditions (nothing was saved).', array( 'details' => $errors ) );
			}
			update_post_meta( $post->ID, Utils::META_CONDS, $conds );
		}
		$settings = Popups::settings( $post->ID );
		if ( isset( $a['settings'] ) ) {
			$errors   = array();
			$settings = Popups::sanitize( (array) $a['settings'], $errors, $settings );
			update_post_meta( $post->ID, Utils::META_TPL, $settings );
			foreach ( $errors as $e ) {
				$call->warn( $e );
			}
		}
		if ( Theme_Builder::instance() ) {
			Theme_Builder::instance()->rebuild_index();
		}
		$call->summary = 'Configured popup "' . get_the_title( $post ) . '"';
		$triggers      = array_keys( array_filter( (array) $settings['triggers'], static fn( $t ) => ! empty( $t['enabled'] ) ) );
		return array(
			'popup'     => $this->info( get_post( $post->ID ) ),
			'settings'  => $settings,
			'triggers'  => $triggers ? $triggers : array( 'link only' ),
			'open_link' => '#uncoder-popup:open:' . $post->ID,
		);
	}

	/* ---------------------------------------------------------------- Helpers */

	/**
	 * @return \WP_Post|WP_Error
	 */
	private function template( $id ) {
		$post = Helpers::editable_post( $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( Post_Types::TEMPLATE !== $post->post_type ) {
			return new WP_Error( 'invalid', sprintf( '#%d is a %s, not a theme template. Use update_page for pages.', $post->ID, $post->post_type ) );
		}
		return $post;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function info( \WP_Post $post ): array {
		$type  = (string) get_post_meta( $post->ID, Utils::META_TYPE, true );
		$conds = get_post_meta( $post->ID, Utils::META_CONDS, true );
		$conds = is_array( $conds ) ? $conds : array();
		$info  = array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'type'       => $type,
			'status'     => $post->post_status,
			'edit_url'   => admin_url( 'post.php?action=uncoder&post=' . $post->ID ),
			'modified'   => get_post_modified_time( 'c', false, $post ),
			'elements'   => count( Tree::ids( Plugin::instance()->documents()->get( $post->ID )->elements() ) ),
		);
		if ( in_array( $type, self::CONDITIONAL, true ) ) {
			$info['conditions'] = $conds;
			$info['applies_to'] = Conditions::summary( $conds );
			$info['active']     = 'publish' === $post->post_status && (bool) $conds;
		}
		if ( 'popup' === $type ) {
			$info['open_link'] = '#uncoder-popup:open:' . $post->ID;
		}
		return $info;
	}

	private function next_hint( string $type, int $id, string $status ): string {
		$live = 'publish' === $status;
		switch ( $type ) {
			case 'header':
			case 'footer':
				return $live ? 'It is live. Check a page with render_page, or refine with edit_elements (id ' . $id . ').' : 'Publish with update_template {"id":' . $id . ',"status":"publish"} when ready.';
			case 'popup':
				return 'Adjust triggers with configure_popup; open it from a button link "#uncoder-popup:open:' . $id . '".';
			case 'mega-menu':
				return 'Attach it to a top-level menu item with set_mega_menu {"item_id":…, "template_id":' . $id . '}.';
			case 'loop-item':
				return 'Use it as the card template of a posts/loop-grid widget ("loop_template": ' . $id . '). Build it with dynamic tags (post-title, featured-image, post-url…).';
			case 'section':
				return 'Reuse it on pages with the template widget ({"type":"template","settings":{"template_id":' . $id . '}}) or the [uncoder_template id="' . $id . '"] shortcode.';
			default:
				return 'Use dynamic widgets (post-title, post-content, archive-title, posts…) so the layout fills with the current content.';
		}
	}
}
