<?php
/**
 * Site-level tools: overview, settings, SEO, custom CSS, caches, form submissions.
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
use Uncoder\Builder\Theme\Theme_Builder;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The first tool an AI calls (get_site_overview) plus site-wide settings.
 */
final class Site_Tools {

	public function register( Registry $r ): void {
		$ro = array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false );

		$r->add(
			array(
				'name'        => 'get_site_overview',
				'title'       => 'Get site overview',
				'description' => 'Start here. The site name, theme, front page, pages, menus and locations, theme templates (header/footer/…) with their conditions, popups, Design System summary, active SEO plugin and your permissions. Then read get_build_guide.',
				'scope'       => 'read',
				'annotations' => $ro,
				'callback'    => array( $this, 'overview' ),
			)
		);

		$r->add(
			array(
				'name'        => 'get_site_settings',
				'title'       => 'Get site settings',
				'description' => 'Site title, tagline, front page / posts page, logo, site icon, language, timezone, date format and permalink structure.',
				'scope'       => 'read',
				'annotations' => $ro,
				'callback'    => array( $this, 'get_settings' ),
			)
		);

		$r->add(
			array(
				'name'        => 'update_site_settings',
				'title'       => 'Update site settings',
				'description' => 'Updates title, tagline, front page (front_page_id makes a static page the home page), posts page, logo (logo_id: attachment id, e.g. from generate_logo/upload_media), site icon, posts per page and maintenance / coming-soon mode. The previous values are returned.',
				'scope'       => 'site',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'title'          => array( 'type' => 'string' ),
						'tagline'        => array( 'type' => 'string' ),
						'front_page_id'  => array( 'type' => 'integer', 'description' => 'Page shown as the home page (0 = latest posts).' ),
						'posts_page_id'  => array( 'type' => 'integer', 'description' => 'Page that lists blog posts.' ),
						'logo_id'        => array( 'type' => 'integer', 'description' => 'Attachment id (0 removes the logo).' ),
						'site_icon_id'   => array( 'type' => 'integer', 'description' => 'Square image attachment id, at least 512×512.' ),
						'posts_per_page' => array( 'type' => 'integer' ),
						'business'       => array(
							'type'        => 'object',
							'description' => 'Structured data (schema.org JSON-LD on the home page) about the business: {"enabled":true,"type":"LocalBusiness"|"HomeAndConstructionBusiness"|"ProfessionalService"|"Restaurant"|"Organization"|…,"name","description","phone","email","street","city","region","postal","country" (2 letters),"area" (area served),"price_range" ("$$"),"hours":["Mo-Fr 08:00-17:00","Sa 09:00-13:00"],"same_as":[social profile URLs]}. Only real facts from the user; merged with the current values.',
						),
						'maintenance'    => array(
							'type'        => 'object',
							'description' => 'Close the site to visitors: {"mode":"coming_soon"|"maintenance"|"" (off),"page_id":<Uncoder page shown instead, drafts work>,"access":"logged_in"|"roles","roles":["editor"]}. Maintenance answers HTTP 503 (search engines keep the old results), coming soon 200. Administrators always see the real site. Ask the user before closing a live site.',
							'properties'  => array(
								'mode'    => array( 'type' => 'string', 'enum' => array( '', 'coming_soon', 'maintenance' ) ),
								'page_id' => array( 'type' => 'integer' ),
								'access'  => array( 'type' => 'string', 'enum' => array( 'logged_in', 'roles' ) ),
								'roles'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
							),
						),
					),
				),
				'callback'    => array( $this, 'update_settings' ),
			)
		);

		$r->add(
			array(
				'name'        => 'set_seo_meta',
				'title'       => 'Set SEO meta',
				'description' => 'Sets the SEO title and meta description of a page/post. Writes to Yoast, Rank Math, AIOSEO, SEOPress or The SEO Framework when active; otherwise Uncoder prints the tags itself. Titles ≈ 50–60 characters, descriptions 140–160.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'integer' ),
						'title'       => array( 'type' => 'string', 'description' => 'SEO title ("" clears it).' ),
						'description' => array( 'type' => 'string', 'description' => 'Meta description ("" clears it).' ),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => array( $this, 'set_seo_meta' ),
			)
		);

		$r->add(
			array(
				'name'        => 'set_custom_css',
				'title'       => 'Set custom CSS',
				'description' => 'Custom CSS for one page (id; use "selector" for the page wrapper) or the whole site (no id). Replaces the previous value; get the current CSS with get_custom_css first to append. Prefer element settings; use CSS only for what no control expresses.',
				'scope'       => 'design',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'  => array( 'type' => 'integer', 'description' => 'Page id. Omit for site-wide CSS.' ),
						'css' => array( 'type' => 'string' ),
					),
					'required'   => array( 'css' ),
				),
				'callback'    => array( $this, 'set_custom_css' ),
			)
		);

		$r->add(
			array(
				'name'        => 'get_custom_css',
				'title'       => 'Get custom CSS',
				'description' => 'Current custom CSS of a page (id) or of the site (no id).',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer' ),
					),
				),
				'callback'    => array( $this, 'get_custom_css' ),
			)
		);

		$r->add(
			array(
				'name'        => 'find_replace',
				'title'       => 'Find and replace across the site',
				'description' => 'Replaces text, links or colors in every Uncoder page, post and template you can edit (e.g. a renamed product, a changed phone number, an old domain, a brand color). Matches by setting type, never touching layout values. Runs as a preview (dry_run, the default) listing matches per document; call again with dry_run:false to apply. Every changed document gets a WordPress revision.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'find'       => array( 'type' => 'string' ),
						'replace'    => array( 'type' => 'string' ),
						'scope'      => array( 'type' => 'string', 'enum' => array( 'text', 'links', 'colors', 'all' ), 'description' => 'Default "text".' ),
						'match_case' => array( 'type' => 'boolean' ),
						'dry_run'    => array( 'type' => 'boolean', 'description' => 'Default true: only report matches.' ),
					),
					'required'   => array( 'find', 'replace' ),
				),
				'callback'    => array( $this, 'find_replace' ),
			)
		);

		$r->add(
			array(
				'name'        => 'clear_cache',
				'title'       => 'Clear cache',
				'description' => 'Regenerates Uncoder CSS files (Design System and every page) and purges page caches of common caching plugins (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, WP Fastest Cache, SiteGround, Autoptimize) and the object cache. Use when the front end looks stale.',
				'scope'       => 'site',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'callback'    => array( $this, 'clear_cache' ),
			)
		);

		$r->add(
			array(
				'name'        => 'list_form_submissions',
				'title'       => 'List form submissions',
				'description' => 'Recent submissions of Uncoder forms (contains visitor personal data: only summarize what the user asks for).',
				'scope'       => 'site',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array( 'type' => 'integer', 'description' => 'Only submissions from this page.' ),
						'form_name' => array( 'type' => 'string' ),
						'status'    => array( 'type' => 'string', 'enum' => array( 'unread', 'read', 'spam', 'any' ) ),
						'limit'     => array( 'type' => 'integer', 'description' => 'Max 100 (default 20).' ),
						'page'      => array( 'type' => 'integer' ),
					),
				),
				'callback'    => array( $this, 'list_submissions' ),
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function overview( array $a, Call $call ): array {
		$theme      = wp_get_theme();
		$front_id   = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$posts_page = (int) get_option( 'page_for_posts' );

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 40,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$page_list = array();
		foreach ( $pages as $page ) {
			if ( 'publish' !== $page->post_status && ! current_user_can( 'edit_post', $page->ID ) ) {
				continue;
			}
			$page_list[] = array(
				'id'      => $page->ID,
				'title'   => get_the_title( $page ),
				'status'  => $page->post_status,
				'url'     => get_permalink( $page ),
				'builder' => Utils::is_builder_post( $page->ID ),
				'parent'  => $page->post_parent,
			);
		}
		$counts = wp_count_posts( 'page' );
		$posts  = wp_count_posts( 'post' );

		$kit = Plugin::instance()->kit();
		$tb  = Theme_Builder::instance();

		$popups = get_posts(
			array(
				'post_type'      => Post_Types::TEMPLATE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 30,
				'no_found_rows'  => true,
				'meta_key'       => Utils::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'popup', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$user  = wp_get_current_user();
		$fonts = array_filter( array_column( (array) $kit->get( 'fonts', array() ), 'family', 'id' ) );

		return array(
			'site'        => array(
				'name'       => get_bloginfo( 'name' ),
				'tagline'    => get_bloginfo( 'description' ),
				'url'        => home_url( '/' ),
				'language'   => get_locale(),
				'wordpress'  => get_bloginfo( 'version' ),
				'uncoder'    => UNCODER_WB_VERSION,
				'permalinks' => (string) get_option( 'permalink_structure' ),
				'seo_plugin' => '' !== Seo::plugin() ? Seo::plugin() : 'none (Uncoder prints SEO tags)',
			),
			'theme'       => array(
				'name'        => $theme->get( 'Name' ),
				'block_theme' => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
				'note'        => 'Uncoder header/footer templates replace the theme header/footer; pages use the "uncoder-full-width" template by default.',
			),
			'front_page'  => $front_id ? array( 'id' => $front_id, 'title' => get_the_title( $front_id ) ) : 'latest posts (no static front page — set one with update_site_settings front_page_id)',
			'posts_page'  => $posts_page ? array( 'id' => $posts_page, 'title' => get_the_title( $posts_page ) ) : null,
			'pages'       => array(
				'total' => (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 ) + (int) ( $counts->private ?? 0 ) + (int) ( $counts->pending ?? 0 ),
				'items' => $page_list,
			),
			'posts'       => array( 'published' => (int) ( $posts->publish ?? 0 ) ),
			'menus'       => ( new Menu_Tools() )->menus_summary(),
			'templates'   => $tb ? $tb->overview() : array(),
			'popups'      => array_map(
				static fn( $p ) => array(
					'id'     => $p->ID,
					'title'  => get_the_title( $p ),
					'status' => $p->post_status,
				),
				$popups
			),
			'design'      => array(
				'colors'     => array_column( (array) $kit->get( 'colors', array() ), 'value', 'id' ),
				'fonts'      => $fonts ? $fonts : 'not set (theme fonts) — set them with update_design_system',
				'text_styles' => array_keys( $kit->presets() ),
			),
			'widgets'     => count( Plugin::instance()->elements()->all() ),
			'you'         => array(
				'user'   => $user->display_name,
				'roles'  => $user->roles,
				'scopes' => $call->ctx->scopes,
			),
			'next'        => 'Read get_build_guide (topic "overview"), then set the brand with update_design_system before building pages.',
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function find_replace( array $a, Call $call ) {
		$apply = isset( $a['dry_run'] ) && false === $a['dry_run'];
		// Theme templates need the "design" permission: without it they are left out (not editable for this run).
		$guard = null;
		if ( ! $call->ctx->has_scope( 'design' ) ) {
			$guard = static function ( $caps, $cap, $user_id, $args ) {
				if ( 'edit_post' === $cap && ! empty( $args[0] ) && Post_Types::TEMPLATE === get_post_type( (int) $args[0] ) ) {
					return array( 'do_not_allow' );
				}
				return $caps;
			};
			add_filter( 'map_meta_cap', $guard, 20, 4 );
		}
		try {
			$result = \Uncoder\Builder\Site\Find_Replace::run( (string) ( $a['find'] ?? '' ), (string) ( $a['replace'] ?? '' ), (string) ( $a['scope'] ?? 'text' ), ! empty( $a['match_case'] ), $apply );
		} finally {
			if ( $guard ) {
				remove_filter( 'map_meta_cap', $guard, 20 );
			}
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $apply ) {
			$call->summary = sprintf( 'Replaced %d matches of "%s" in %d documents', $result['matches'], $a['find'], count( $result['documents'] ) );
		} elseif ( $result['matches'] ) {
			$result['next'] = 'Check the samples, then call find_replace again with dry_run:false to apply.';
		}
		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_settings(): array {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $logo_id ) {
			$logo_id = (int) get_option( 'site_logo' );
		}
		$icon_id = (int) get_option( 'site_icon' );
		return array(
			'title'          => get_bloginfo( 'name' ),
			'tagline'        => get_bloginfo( 'description' ),
			'show_on_front'  => get_option( 'show_on_front' ),
			'front_page_id'  => (int) get_option( 'page_on_front' ),
			'posts_page_id'  => (int) get_option( 'page_for_posts' ),
			'posts_per_page' => (int) get_option( 'posts_per_page' ),
			'logo'           => $logo_id ? array( 'id' => $logo_id, 'url' => wp_get_attachment_url( $logo_id ) ) : null,
			'site_icon'      => $icon_id ? array( 'id' => $icon_id, 'url' => wp_get_attachment_url( $icon_id ) ) : null,
			'language'       => get_locale(),
			'timezone'       => wp_timezone_string(),
			'maintenance'    => \Uncoder\Builder\Site\Maintenance::get(),
			'business'       => \Uncoder\Builder\Site\Schema::get(),
			'date_format'    => get_option( 'date_format' ),
			'permalinks'     => (string) get_option( 'permalink_structure' ),
			'search_engines' => (bool) get_option( 'blog_public' ) ? 'visible' : 'discouraged',
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_settings( array $a, Call $call ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'Changing site settings requires an administrator.' );
		}
		$before  = $this->get_settings();
		$changed = array();

		if ( isset( $a['title'] ) ) {
			update_option( 'blogname', sanitize_text_field( (string) $a['title'] ) );
			$changed[] = 'title';
		}
		if ( isset( $a['tagline'] ) ) {
			update_option( 'blogdescription', sanitize_text_field( (string) $a['tagline'] ) );
			$changed[] = 'tagline';
		}
		if ( array_key_exists( 'front_page_id', $a ) ) {
			$id = absint( $a['front_page_id'] );
			if ( $id ) {
				$page = get_post( $id );
				if ( ! $page || 'page' !== $page->post_type ) {
					return new WP_Error( 'invalid', 'front_page_id must be the id of a page.' );
				}
				if ( 'publish' !== $page->post_status ) {
					$call->warn( sprintf( 'Page #%d is %s: publish it (publish_page) or visitors will not see the home page.', $id, $page->post_status ) );
				}
				update_option( 'page_on_front', $id );
				update_option( 'show_on_front', 'page' );
			} else {
				update_option( 'show_on_front', 'posts' );
			}
			$changed[] = 'front_page';
		}
		if ( array_key_exists( 'posts_page_id', $a ) ) {
			$id = absint( $a['posts_page_id'] );
			if ( $id && 'page' !== get_post_type( $id ) ) {
				return new WP_Error( 'invalid', 'posts_page_id must be the id of a page.' );
			}
			if ( $id && $id === (int) get_option( 'page_on_front' ) ) {
				return new WP_Error( 'invalid', 'The posts page cannot be the front page.' );
			}
			update_option( 'page_for_posts', $id );
			$changed[] = 'posts_page';
		}
		if ( array_key_exists( 'logo_id', $a ) ) {
			$id = absint( $a['logo_id'] );
			if ( $id && ! \Uncoder\Builder\Core\Media::is_image( $id ) ) {
				return new WP_Error( 'invalid', 'logo_id must be an image attachment id.' );
			}
			set_theme_mod( 'custom_logo', $id );
			update_option( 'site_logo', $id );
			$changed[] = 'logo';
		}
		if ( array_key_exists( 'site_icon_id', $a ) ) {
			$id = absint( $a['site_icon_id'] );
			if ( $id && ! wp_attachment_is_image( $id ) ) {
				return new WP_Error( 'invalid', 'site_icon_id must be an image attachment id.' );
			}
			update_option( 'site_icon', $id );
			$changed[] = 'site_icon';
		}
		if ( isset( $a['posts_per_page'] ) ) {
			update_option( 'posts_per_page', max( 1, min( 100, absint( $a['posts_per_page'] ) ) ) );
			$changed[] = 'posts_per_page';
		}
		if ( isset( $a['maintenance'] ) && is_array( $a['maintenance'] ) ) {
			$m    = $a['maintenance'];
			$prev = \Uncoder\Builder\Site\Maintenance::get();
			if ( isset( $m['page_id'] ) ) {
				$m['page'] = $m['page_id'];
				if ( $m['page'] && ! Plugin::instance()->documents()->get( absint( $m['page'] ) )->is_builder() ) {
					return new WP_Error( 'invalid', 'maintenance.page_id must be a page built with Uncoder (create_page first).' );
				}
			}
			\Uncoder\Builder\Site\Maintenance::save( array_merge( $prev, array_intersect_key( $m, array_flip( array( 'mode', 'page', 'access', 'roles' ) ) ) ) );
			$changed[] = 'maintenance';
		}
		if ( isset( $a['business'] ) && is_array( $a['business'] ) ) {
			$stored             = get_option( \Uncoder\Builder\Rest\Settings_Controller::OPTION, array() );
			$stored             = is_array( $stored ) ? $stored : array();
			$stored['business'] = \Uncoder\Builder\Site\Schema::sanitize( array_merge( \Uncoder\Builder\Site\Schema::get(), $a['business'] ) );
			update_option( \Uncoder\Builder\Rest\Settings_Controller::OPTION, $stored );
			$changed[] = 'business';
		}
		if ( ! $changed ) {
			return new WP_Error( 'invalid', 'Nothing to update. Pass title, tagline, front_page_id, posts_page_id, logo_id, site_icon_id, posts_per_page, business or maintenance.' );
		}
		$call->summary = 'Site settings: ' . implode( ', ', $changed );
		return array(
			'changed'  => $changed,
			'settings' => $this->get_settings(),
			'previous' => array_intersect_key( $before, array_flip( array( 'title', 'tagline', 'show_on_front', 'front_page_id', 'posts_page_id', 'posts_per_page', 'logo', 'site_icon' ) ) ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function set_seo_meta( array $a, Call $call ) {
		$id   = absint( $a['id'] );
		$post = get_post( $id );
		if ( ! $post || ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'not_found', sprintf( 'No editable post with id %d.', $id ) );
		}
		if ( ! isset( $a['title'] ) && ! isset( $a['description'] ) ) {
			return new WP_Error( 'invalid', 'Pass title and/or description.' );
		}
		$title = isset( $a['title'] ) ? (string) $a['title'] : null;
		$desc  = isset( $a['description'] ) ? (string) $a['description'] : null;
		if ( null !== $desc && '' !== $desc ) {
			$len = mb_strlen( $desc );
			if ( $len < 70 || $len > 170 ) {
				$call->warn( sprintf( 'The description has %d characters; aim for 140–160.', $len ) );
			}
		}
		if ( null !== $title && mb_strlen( $title ) > 65 ) {
			$call->warn( sprintf( 'The title has %d characters; search engines show about 60.', mb_strlen( $title ) ) );
		}
		$saved           = Seo::set( $id, $title, $desc );
		$call->object_id = $id;
		$call->summary   = 'SEO meta for "' . get_the_title( $post ) . '"';
		return array(
			'id'     => $id,
			'seo'    => $saved,
			'stored' => 'uncoder' === $saved['plugin'] ? 'Uncoder (no SEO plugin active)' : $saved['plugin'],
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_custom_css( array $a ) {
		if ( empty( $a['id'] ) ) {
			return array(
				'scope' => 'site',
				'css'   => (string) Plugin::instance()->kit()->get( 'custom_css', '' ),
			);
		}
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$page = Plugin::instance()->documents()->get( $post->ID )->page_settings();
		return array(
			'scope' => 'page',
			'id'    => $post->ID,
			'css'   => (string) ( $page['custom_css'] ?? '' ),
			'hint'  => '"selector" is replaced by the page wrapper class.',
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function set_custom_css( array $a, Call $call ) {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return new WP_Error( 'forbidden', 'Custom CSS requires the unfiltered_html capability (administrators on single sites).' );
		}
		$css = Utils::sanitize_custom_css( (string) $a['css'] );
		if ( empty( $a['id'] ) ) {
			$call->snapshot_kit();
			Plugin::instance()->kit()->update( array( 'custom_css' => $css ), '', false );
			$call->summary = 'Site custom CSS (' . strlen( $css ) . ' bytes)';
			return array(
				'scope' => 'site',
				'bytes' => strlen( $css ),
			);
		}
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$call->snapshot_post( $post->ID );
		$doc = Plugin::instance()->documents()->get( $post->ID );
		$doc->save_page_settings( array_merge( $doc->page_settings(), array( 'custom_css' => $css ) ) );
		$doc->regenerate();
		$call->summary = 'Custom CSS for "' . get_the_title( $post ) . '"';
		return array(
			'scope' => 'page',
			'id'    => $post->ID,
			'bytes' => strlen( $css ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function clear_cache( array $a, Call $call ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'Clearing caches requires an administrator.' );
		}
		$plugin = Plugin::instance();
		$plugin->kit()->write_css();
		$pages = $plugin->documents()->regenerate_all();
		if ( Theme_Builder::instance() ) {
			Theme_Builder::instance()->rebuild_index();
		}
		$purged = array();
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$purged[] = 'WP Rocket';
		}
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$purged[] = 'LiteSpeed';
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$purged[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$purged[] = 'WP Super Cache';
		}
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
			$purged[] = 'WP Fastest Cache';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$purged[] = 'SiteGround';
		}
		if ( class_exists( '\autoptimizeCache' ) && method_exists( '\autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
			$purged[] = 'Autoptimize';
		}
		wp_cache_flush();
		/**
		 * Fires when an AI client asks to clear caches (hook in custom cache purges).
		 */
		do_action( 'uncoder_wb/clear_cache' );
		$call->summary = sprintf( 'Regenerated CSS for %d documents; purged %s', $pages, $purged ? implode( ', ', $purged ) : 'object cache' );
		return array(
			'css_regenerated' => $pages,
			'purged'          => $purged,
			'object_cache'    => true,
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_submissions( array $a ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'Form submissions are visible to administrators only.' );
		}
		$table  = $wpdb->prefix . 'uncoder_wb_submissions';
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $a['post_id'] ) ) {
			$where[]  = 'post_id = %d';
			$params[] = absint( $a['post_id'] );
		}
		if ( ! empty( $a['form_name'] ) ) {
			$where[]  = 'form_name = %s';
			$params[] = sanitize_text_field( (string) $a['form_name'] );
		}
		$status = (string) ( $a['status'] ?? 'any' );
		if ( 'any' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $status );
		}
		$limit  = min( 100, max( 1, (int) ( $a['limit'] ?? 20 ) ) );
		$offset = ( max( 1, (int) ( $a['page'] ?? 1 ) ) - 1 ) * $limit;
		// Only the fixed fragments above are joined into the SQL; the table is %i and every value is a placeholder.
		$list_sql  = 'SELECT id, post_id, element_id, form_name, data, status, created_at FROM %i WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$count_sql = 'SELECT COUNT(*) FROM %i WHERE ' . implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $list_sql is built from fixed fragments only.
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( array( $table ), $params, array( $limit, $offset ) ) ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $count_sql is built from fixed fragments only.
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( array( $table ), $params ) ) );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$data    = json_decode( (string) $row['data'], true );
			$items[] = array(
				'id'      => (int) $row['id'],
				'form'    => $row['form_name'],
				'page'    => (int) $row['post_id'] ? get_the_title( (int) $row['post_id'] ) : '',
				'status'  => $row['status'],
				'date'    => $row['created_at'],
				'fields'  => is_array( $data ) ? $data : array(),
			);
		}
		return array(
			'total' => $total,
			'items' => $items,
		);
	}
}
