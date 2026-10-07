<?php
/**
 * Site kits: export a whole Uncoder site as one zip and import it on another site (like Elementor kits).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Seo;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Menus\Mega_Menu;
use Uncoder\Builder\Menus\Menu_Item_Extras;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Popups\Popups;
use Uncoder\Builder\Rest\Rest;
use Uncoder\Builder\Rest\Settings_Controller;
use Uncoder\Builder\Theme\Conditions;
use Uncoder\Builder\Theme\Theme_Builder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Zip layout: manifest.json · design.json (Design System) · templates.json · content.json (pages / posts built
 * with Uncoder) · posts.json (blog posts written in the WordPress editor, with every category) · menus.json · settings.json (no secrets) · snippets.json · fonts.json + fonts/ · media.json +
 * media/{old id}/{file}. Export streams the zip to the admin and deletes it. Import unzips into a private
 * folder, shows what is inside, uploads media in batches (import step "media"), then creates everything else
 * (step "finish") and points every reference (media, templates, popups, menus, links, pages) at the new ids.
 * Only administrators (manage_options) export or import site kits.
 */
final class Site_Kit {

	/** @var int[] Posts written by the running import (never matched as "already here"). */
	private array $written = array();

	public const FORMAT  = 'uncoder-site-kit';
	public const VERSION = 1;
	/** Parts a kit can carry (all optional). */
	public const PARTS = array( 'design', 'templates', 'content', 'posts', 'menus', 'media', 'fonts', 'settings', 'snippets' );
	/** Settings never exported: secrets and site-local switches. */
	private const PRIVATE_SETTINGS = array( 'remove_data', 'roles', 'maintenance', 'form_retention_days' );
	/**
	 * Settings never imported: connections to outside services. A kit carries no keys, and taking an
	 * endpoint or account URL from a file would send this site's saved key there.
	 */
	private const SERVICE_SETTINGS = array( 'ai', 'ai_images', 'integrations', 'captcha' );
	private const SECRET_KEYS      = array( 'secret', 'api_key', 'key', 'token', 'password', 'secret_key', 'private_key' );
	private const MEDIA_BATCH      = 8;
	private const REF_KEYS         = array( 'template_id', 'loop_template', 'alternate_template' );
	private const FONT_EXT         = array( 'woff2', 'woff', 'ttf', 'otf' );
	/** File types a kit's media/ folder may hold (what an export collects); anything else is not unpacked. */
	private const MEDIA_EXT = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'mp4', 'm4v', 'webm', 'ogv', 'mov', 'mp3', 'm4a', 'ogg', 'wav', 'pdf', 'json' );
	/** Limits while unpacking an uploaded kit (zip bombs). */
	private const MAX_ENTRIES   = 20000;
	private const MAX_JSON_SIZE = 64 * MB_IN_BYTES;
	/** Attachment meta: which site / file an imported file came from (a second import reuses it). */
	private const META_SOURCE      = '_uncoder_kit_source';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'admin_post_uncoder_site_kit_download', array( $this, 'download' ) );
	}

	public function routes(): void {
		$admin = static fn() => current_user_can( 'manage_options' );
		register_rest_route( Rest::NS, '/site-kit/summary', array( 'methods' => 'GET', 'callback' => array( $this, 'summary' ), 'permission_callback' => $admin ) );
		register_rest_route( Rest::NS, '/site-kit/export', array( 'methods' => 'POST', 'callback' => array( $this, 'export' ), 'permission_callback' => $admin ) );
		register_rest_route( Rest::NS, '/site-kit/upload', array( 'methods' => 'POST', 'callback' => array( $this, 'upload' ), 'permission_callback' => $admin ) );
		register_rest_route( Rest::NS, '/site-kit/import', array( 'methods' => 'POST', 'callback' => array( $this, 'import' ), 'permission_callback' => $admin ) );
	}

	/* ------------------------------------------------------------------ Shared */

	/** Private working folder for kits (not listed, cleared after a day). */
	private static function dir( string $sub = '' ): string {
		$uploads = wp_upload_dir( null, false );
		$base    = trailingslashit( $uploads['basedir'] ) . 'uncoder/kits';
		if ( ! is_dir( $base ) ) {
			wp_mkdir_p( $base );
		}
		// No listing, no direct access on Apache (2.4 and 2.2 syntax); tokens are random either way.
		if ( ! file_exists( $base . '/index.php' ) ) {
			file_put_contents( $base . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $base . '/.htaccess' ) ) {
			file_put_contents( $base . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return '' === $sub ? $base : $base . '/' . $sub;
	}

	private static function token(): string {
		return strtolower( wp_generate_password( 24, false ) );
	}

	/** Removes kit files older than a day. */
	private static function cleanup(): void {
		foreach ( (array) glob( self::dir() . '/*' ) as $path ) {
			if ( is_string( $path ) && ! in_array( basename( $path ), array( 'index.php', '.htaccess' ), true ) && filemtime( $path ) < time() - DAY_IN_SECONDS ) {
				self::rrmdir( $path );
			}
		}
	}

	private static function rrmdir( string $path ): void {
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
			return;
		}
		foreach ( (array) glob( $path . '/{,.}[!.]*', GLOB_BRACE ) as $child ) {
			if ( is_string( $child ) ) {
				self::rrmdir( $child );
			}
		}
		@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/** Posts and pages built with Uncoder (not templates). */
	private static function content_ids(): array {
		$types = array_values( array_diff( Plugin::instance()->documents()->post_types(), array( Post_Types::TEMPLATE ) ) );
		return get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => array( 'publish', 'draft', 'private', 'future', 'pending' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'orderby'        => 'menu_order ID',
				'order'          => 'ASC',
				'meta_key'       => Utils::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'lang'           => '', // Polylang: every language.
			)
		);
	}

	/** Blog posts written in the WordPress editor (posts built with Uncoder travel in content.json). */
	private static function post_ids(): array {
		return get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'private', 'future', 'pending' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'lang'           => '',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => Utils::META_MODE,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => Utils::META_MODE,
						'value'   => 'builder',
						'compare' => '!=',
					),
				),
			)
		);
	}

	private static function template_ids(): array {
		return get_posts(
			array(
				'post_type'      => Post_Types::TEMPLATE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'lang'           => '',
			)
		);
	}

	/** What an export of this site would contain (Import & Export screen). */
	public function summary(): WP_REST_Response {
		$menus = wp_get_nav_menus();
		return new WP_REST_Response(
			array(
				'templates' => count( self::template_ids() ),
				'content'   => count( self::content_ids() ),
				'posts'     => count( self::post_ids() ),
				'menus'     => is_array( $menus ) ? count( $menus ) : 0,
				'fonts'     => count( Custom_Fonts::all() ),
				'snippets'  => count( (array) get_option( Code_Snippets::OPTION, array() ) ),
				'maxUpload' => wp_max_upload_size(),
			)
		);
	}

	/* ------------------------------------------------------------------ Export */

	/**
	 * Body: { parts: { design, templates, content, posts, menus, media, fonts, settings, snippets } }.
	 */
	public function export( WP_REST_Request $request ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new WP_Error( 'uncoder_no_zip', __( 'This server cannot create zip files (the PHP zip extension is missing).', 'uncoder' ), array( 'status' => 500 ) );
		}
		self::cleanup();
		$parts = array_intersect_key( array_map( 'boolval', (array) $request->get_param( 'parts' ) ), array_flip( self::PARTS ) );
		$files = array();
		$media = array();
		$count = array();

		if ( ! empty( $parts['design'] ) ) {
			$files['design.json'] = Plugin::instance()->kit()->export();
			// The brand travels with the design: logo and site icon (media values, so their files go along).
			$brand = array();
			foreach ( array( 'logo' => self::site_logo_id(), 'icon' => (int) get_option( 'site_icon' ) ) as $key => $att ) {
				if ( $att && wp_get_attachment_url( $att ) ) {
					$brand[ $key ] = array(
						'id'  => $att,
						'url' => (string) wp_get_attachment_url( $att ),
					);
				}
			}
			if ( $brand ) {
				$files['design.json']['brand'] = $brand;
			}
		}
		if ( ! empty( $parts['templates'] ) ) {
			$items = array();
			foreach ( self::template_ids() as $id ) {
				$item = self::export_item( (int) $id );
				if ( $item ) {
					$items[] = $item;
				}
			}
			$files['templates.json'] = $items;
			$count['templates']      = count( $items );
		}
		if ( ! empty( $parts['content'] ) ) {
			$items = array();
			foreach ( self::content_ids() as $id ) {
				$item = self::export_item( (int) $id );
				if ( $item ) {
					$items[] = $item;
				}
			}
			$files['content.json'] = array(
				'items'         => $items,
				'show_on_front' => get_option( 'show_on_front' ),
				'page_on_front' => (int) get_option( 'page_on_front' ),
				'page_for_posts' => (int) get_option( 'page_for_posts' ),
			);
			$count['content']      = count( $items );
		}
		if ( ! empty( $parts['posts'] ) ) {
			$items = array();
			foreach ( self::post_ids() as $id ) {
				$item = self::export_post( (int) $id );
				if ( $item ) {
					$items[] = $item;
				}
			}
			// Every category, not only the posts' ones: loop grids and filters on pages name categories by id.
			$categories = array();
			foreach ( (array) get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $term ) {
				if ( $term instanceof \WP_Term ) {
					$categories[] = array(
						'id'          => (int) $term->term_id,
						'name'        => $term->name,
						'slug'        => $term->slug,
						'description' => $term->description,
						'parent'      => (int) $term->parent,
					);
				}
			}
			$files['posts.json'] = array(
				'items'      => $items,
				'categories' => $categories,
			);
			$count['posts']      = count( $items );
		}
		if ( ! empty( $parts['menus'] ) ) {
			$files['menus.json'] = self::export_menus();
			$count['menus']      = count( $files['menus.json']['menus'] );
		}
		if ( ! empty( $parts['settings'] ) ) {
			$files['settings.json'] = self::strip_secrets( array_diff_key( (array) get_option( Settings_Controller::OPTION, array() ), array_flip( array_merge( self::PRIVATE_SETTINGS, self::SERVICE_SETTINGS ) ) ) );
		}
		if ( ! empty( $parts['snippets'] ) ) {
			$files['snippets.json'] = array_values( (array) get_option( Code_Snippets::OPTION, array() ) );
			$count['snippets']      = count( $files['snippets.json'] );
		}

		$token = self::token();
		$path  = self::dir( $token . '.zip' );
		$zip   = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'uncoder_zip_failed', __( 'The export file could not be created.', 'uncoder' ), array( 'status' => 500 ) );
		}
		// Fonts: their files travel in fonts/.
		if ( ! empty( $parts['fonts'] ) ) {
			$fonts = Custom_Fonts::all();
			$base  = trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'uncoder/fonts/custom/';
			foreach ( $fonts as $font ) {
				foreach ( (array) ( $font['faces'] ?? array() ) as $face ) {
					$file = basename( (string) ( $face['file'] ?? '' ) );
					if ( '' !== $file && is_readable( $base . $file ) ) {
						$zip->addFile( $base . $file, 'fonts/' . $file );
					}
				}
			}
			$files['fonts.json'] = $fonts;
			$count['fonts']      = count( $fonts );
		}
		// Media: every attachment the exported parts use.
		if ( ! empty( $parts['media'] ) ) {
			foreach ( self::attachments_in( $files ) as $att_id ) {
				$file = get_attached_file( $att_id );
				if ( ! $file || ! is_readable( $file ) ) {
					continue;
				}
				$post    = get_post( $att_id );
				$name    = $att_id . '/' . basename( $file );
				$meta    = wp_get_attachment_metadata( $att_id );
				$media[] = array(
					'id'        => $att_id,
					'file'      => $name,
					'url'       => (string) wp_get_attachment_url( $att_id ),
					'title'     => $post ? $post->post_title : '',
					'alt'       => (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true ),
					'caption'   => $post ? $post->post_excerpt : '',
					// SVGs Uncoder generated (placeholders, logos) are recreated the same way on import.
					'generated' => (string) get_post_meta( $att_id, \Uncoder\Builder\Core\Media::META_GENERATED, true ),
					'width'     => (int) ( $meta['width'] ?? 0 ),
					'height'    => (int) ( $meta['height'] ?? 0 ),
				);
				$zip->addFile( $file, 'media/' . $name );
			}
			$files['media.json'] = $media;
			$count['media']      = count( $media );
		}
		$files['manifest.json'] = array(
			'format'   => self::FORMAT,
			'version'  => self::VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => array(
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url( '/' ),
				'uploads' => wp_upload_dir( null, false )['baseurl'],
			),
			'plugin'   => UNCODER_WB_VERSION,
			'parts'    => array_keys( array_filter( $parts ) ),
			'counts'   => $count,
		);
		foreach ( $files as $name => $data ) {
			$zip->addFromString( $name, (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
		}
		$zip->close();
		return new WP_REST_Response(
			array(
				'token'  => $token,
				'size'   => (int) filesize( $path ),
				'counts' => $count,
				// A raw URL (wp_nonce_url() escapes "&" as "&amp;" for HTML, which breaks it as a download link).
				'url'    => add_query_arg(
					array(
						'action'   => 'uncoder_site_kit_download',
						'token'    => $token,
						'_wpnonce' => wp_create_nonce( 'uncoder_site_kit_download' ),
					),
					admin_url( 'admin-post.php' )
				),
			)
		);
	}

	/** Streams a finished export once, then deletes it. */
	public function download(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'uncoder_site_kit_download' ) ) {
			wp_die( esc_html__( 'You cannot download this file.', 'uncoder' ), '', array( 'response' => 403 ) );
		}
		$token = isset( $_GET['token'] ) ? preg_replace( '/[^a-z0-9]/', '', sanitize_key( wp_unslash( $_GET['token'] ) ) ) : ''; // Nonce checked above by check_admin_referer().
		$path  = self::dir( $token . '.zip' );
		if ( '' === $token || ! is_file( $path ) ) {
			wp_die( esc_html__( 'This export has expired. Export again.', 'uncoder' ), '', array( 'response' => 404 ) );
		}
		$name = sanitize_file_name( 'uncoder-site-kit-' . sanitize_title( get_bloginfo( 'name' ) ) . '-' . gmdate( 'Y-m-d' ) . '.zip' );
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $path );
		exit;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function export_item( int $id ): ?array {
		$post = get_post( $id );
		$doc  = $post ? Plugin::instance()->documents()->get( $id ) : null;
		if ( ! $post || ! $doc ) {
			return null;
		}
		$is_template = Post_Types::TEMPLATE === $post->post_type;
		$item        = array(
			'id'            => $id,
			'kind'          => $is_template ? 'template' : 'page',
			'type'          => $is_template ? (string) get_post_meta( $id, Utils::META_TYPE, true ) : $post->post_type,
			'title'         => $post->post_title, // Stored title: get_the_title() adds "Private: " / "Protected: ".
			'slug'          => $post->post_name,
			'status'        => $post->post_status,
			'parent'        => (int) $post->post_parent,
			'menu_order'    => (int) $post->menu_order,
			'excerpt'       => $post->post_excerpt,
			'page_settings' => $doc->page_settings(),
			'elements'      => self::strip_hooks( $doc->elements() ),
		);
		if ( ! $is_template ) {
			$item['template']  = (string) get_post_meta( $id, '_wp_page_template', true );
			$item['thumbnail'] = (int) get_post_thumbnail_id( $id );
			// The page's SEO title and description (Uncoder's own fields, or the active SEO plugin's).
			$seo = Seo::get( $id );
			if ( '' !== $seo['title'] || '' !== $seo['description'] ) {
				$item['seo'] = array(
					'title'       => $seo['title'],
					'description' => $seo['description'],
				);
			}
		}
		$conds = get_post_meta( $id, Utils::META_CONDS, true );
		if ( $is_template && is_array( $conds ) ) {
			$item['conditions'] = $conds;
		}
		$popup = get_post_meta( $id, Utils::META_TPL, true );
		if ( $is_template && is_array( $popup ) ) {
			$item['popup'] = $popup;
		}
		// WPML / Polylang: the language and the other language versions (old ids), linked again on import.
		$lang = Multilingual::language_of( $id );
		if ( '' !== $lang ) {
			$item['lang']         = $lang;
			$item['translations'] = Multilingual::translations( $id );
		}
		return $item;
	}

	/**
	 * A blog post written in the WordPress editor: its text, dates, categories (old ids, mapped on import), tags,
	 * featured image (an attachment id, collected with the media) and SEO title / description.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function export_post( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post ) {
			return null;
		}
		$seo  = Seo::get( $id );
		$tags = wp_get_post_tags( $id, array( 'fields' => 'names' ) );
		return array(
			'id'             => $id,
			'title'          => $post->post_title,
			'slug'           => $post->post_name,
			'status'         => $post->post_status,
			'date'           => $post->post_date,
			'date_gmt'       => $post->post_date_gmt,
			'excerpt'        => $post->post_excerpt,
			'content'        => $post->post_content,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'sticky'         => is_sticky( $id ),
			'categories'     => array_map( 'intval', wp_get_post_categories( $id ) ),
			'tags'           => is_array( $tags ) ? array_values( $tags ) : array(),
			'thumbnail'      => (int) get_post_thumbnail_id( $id ),
			'seo'            => array(
				'title'       => $seo['title'],
				'description' => $seo['description'],
			),
			'meta'           => self::public_meta( $id ),
		);
	}

	/**
	 * A post's own custom fields: public keys (no leading underscore) with one plain value each, e.g. a byline a card
	 * shows through the Custom field tag. Plugin data (protected keys, arrays, objects) stays behind.
	 *
	 * @return array<string,string>
	 */
	private static function public_meta( int $id ): array {
		$out = array();
		foreach ( (array) get_post_meta( $id ) as $key => $values ) {
			$key = (string) $key;
			if ( is_protected_meta( $key, 'post' ) || 1 !== count( (array) $values ) || count( $out ) >= 50 ) {
				continue;
			}
			$value = maybe_unserialize( $values[0] );
			if ( is_scalar( $value ) && strlen( (string) $value ) <= 5000 ) {
				$out[ $key ] = (string) $value;
			}
		}
		return $out;
	}

	/** @return array<string,mixed> */
	private static function export_menus(): array {
		$menus = array();
		foreach ( (array) wp_get_nav_menus() as $menu ) {
			$items = array();
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) ) as $item ) {
				$mega    = Mega_Menu::get( (int) $item->ID );
				$items[] = array(
					'id'        => (int) $item->ID,
					'parent'    => (int) $item->menu_item_parent,
					'title'     => $item->title,
					'type'      => $item->type,
					'object'    => $item->object,
					'object_id' => (int) $item->object_id,
					'slug'      => 'taxonomy' === $item->type && ( $t = get_term( (int) $item->object_id ) ) && ! is_wp_error( $t ) ? $t->slug : '',
					'url'       => $item->url,
					'target'    => $item->target,
					'classes'   => implode( ' ', array_filter( (array) $item->classes ) ),
					'attr'      => $item->attr_title,
					'desc'      => (string) $item->post_content,
					'icon'      => Menu_Item_Extras::icon( (int) $item->ID ),
					'order'     => (int) $item->menu_order,
					'mega'      => $mega,
				);
			}
			$menus[] = array(
				'id'    => (int) $menu->term_id,
				'name'  => $menu->name,
				'slug'  => $menu->slug,
				'items' => $items,
			);
		}
		return array(
			'menus'     => $menus,
			'locations' => array_filter( (array) get_nav_menu_locations() ),
		);
	}

	/**
	 * Form webhook URLs (generic, Slack, Discord) work as passwords for the channel they post to: they stay
	 * on this site. The imported forms keep their other settings.
	 *
	 * @param array<int, array<string,mixed>> $nodes Element tree.
	 * @return array<int, array<string,mixed>>
	 */
	public static function strip_hooks( array $nodes ): array {
		foreach ( $nodes as &$node ) {
			if ( 'form' === ( $node['type'] ?? '' ) && is_array( $node['settings'] ?? null ) ) {
				unset( $node['settings']['webhook_url'], $node['settings']['slack_webhook'], $node['settings']['discord_webhook'] );
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$node['children'] = self::strip_hooks( $node['children'] );
			}
		}
		unset( $node );
		return $nodes;
	}

	/**
	 * @param mixed $value Settings subtree.
	 * @return mixed
	 */
	private static function strip_secrets( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $k => $v ) {
			if ( is_string( $k ) && in_array( strtolower( $k ), self::SECRET_KEYS, true ) ) {
				unset( $value[ $k ] );
				continue;
			}
			$value[ $k ] = self::strip_secrets( $v );
		}
		return $value;
	}

	/**
	 * settings.json of a kit → the sections this site takes over, each cleaned by its module. Unknown keys,
	 * site-local switches (PRIVATE_SETTINGS) and service connections (SERVICE_SETTINGS) are left out.
	 *
	 * @param array<string,mixed> $incoming Settings from the kit.
	 * @return array<string,mixed>
	 */
	private static function import_settings( array $incoming ): array {
		$out = array();
		if ( is_array( $incoming['post_types'] ?? null ) ) {
			$out['post_types'] = array_values( array_filter( array_map( 'sanitize_key', array_map( 'strval', array_filter( $incoming['post_types'], 'is_scalar' ) ) ), 'post_type_exists' ) );
		}
		if ( is_array( $incoming['business'] ?? null ) ) {
			$out['business'] = Schema::sanitize( $incoming['business'] );
		}
		if ( is_array( $incoming['performance'] ?? null ) ) {
			$out['performance'] = Performance::sanitize( $incoming['performance'] );
		}
		if ( is_array( $incoming['consent'] ?? null ) ) {
			// Different banner settings: visitors are asked again (new consent version).
			$current        = Consent::get();
			$clean          = Consent::sanitize( array_merge( $incoming['consent'], array( 'version' => $current['version'] ) ) );
			$out['consent'] = $clean === $current ? $current : Consent::sanitize( $clean, true );
		}
		if ( is_array( $incoming['disabled_widgets'] ?? null ) ) {
			$out['disabled_widgets'] = \Uncoder\Builder\Rest\Prefs_Controller::sanitize_disabled( $incoming['disabled_widgets'] );
		}
		if ( is_array( $incoming['adobe_fonts'] ?? null ) ) {
			$raw      = $incoming['adobe_fonts'];
			$families = array();
			foreach ( is_array( $raw['families'] ?? null ) ? $raw['families'] : array() as $family => $weights ) {
				$family = trim( (string) preg_replace( '/[^A-Za-z0-9 \-_]/', '', (string) $family ) );
				if ( '' !== $family ) {
					$families[ $family ] = array_values( array_filter( array_map( 'strval', (array) $weights ), static fn( $w ) => (bool) preg_match( '/^[1-9]00$/', $w ) ) );
				}
			}
			$project            = strtolower( (string) ( $raw['project'] ?? '' ) );
			$out['adobe_fonts'] = preg_match( '/^[a-z0-9]{5,12}$/', $project ) ? array(
				'project'  => $project,
				'families' => $families,
				'synced'   => (int) ( $raw['synced'] ?? 0 ),
			) : array();
		}
		return $out;
	}

	/**
	 * Attachment ids used anywhere in the exported data: media values ({id, url}), featured images and
	 * upload URLs inside text.
	 *
	 * @param array<string,mixed> $files Exported data.
	 * @return int[]
	 */
	private static function attachments_in( array $files ): array {
		$ids     = array();
		$uploads = wp_upload_dir( null, false )['baseurl'];
		$walk    = static function ( $v ) use ( &$walk, &$ids, $uploads ) {
			if ( is_array( $v ) ) {
				if ( isset( $v['id'], $v['url'] ) && is_numeric( $v['id'] ) && (int) $v['id'] > 0 && is_string( $v['url'] ) ) {
					$ids[ (int) $v['id'] ] = true;
				}
				foreach ( $v as $k => $child ) {
					if ( 'thumbnail' === $k && is_int( $child ) && $child > 0 ) {
						$ids[ $child ] = true;
					}
					$walk( $child );
				}
				return;
			}
			if ( is_string( $v ) && false !== strpos( $v, $uploads ) && preg_match_all( '#' . preg_quote( $uploads, '#' ) . '/[^\s"\'()<>]+\.(?:jpe?g|png|gif|webp|avif|svg|mp4|webm|pdf|json)#i', $v, $m ) ) {
				foreach ( $m[0] as $url ) {
					$id = attachment_url_to_postid( (string) preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $url ) );
					if ( $id ) {
						$ids[ $id ] = true;
					}
				}
			}
		};
		foreach ( $files as $data ) {
			$walk( $data );
		}
		return array_keys( array_filter( $ids ) );
	}

	/* ------------------------------------------------------------------ Import: upload + preview */

	public function upload( WP_REST_Request $request ) {
		self::cleanup();
		$file = $request->get_file_params()['file'] ?? null;
		if ( ! is_array( $file ) || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'uncoder_upload', __( 'The file did not arrive. It may be larger than the server allows.', 'uncoder' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new WP_Error( 'uncoder_no_zip', __( 'This server cannot open zip files (the PHP zip extension is missing).', 'uncoder' ), array( 'status' => 500 ) );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( (string) $file['tmp_name'] ) ) {
			return new WP_Error( 'uncoder_bad_zip', __( 'This is not a zip file.', 'uncoder' ), array( 'status' => 400 ) );
		}
		if ( $zip->numFiles > self::MAX_ENTRIES ) {
			$zip->close();
			return new WP_Error( 'uncoder_bad_kit', __( 'This zip holds too many files to be an Uncoder site kit.', 'uncoder' ), array( 'status' => 400 ) );
		}
		/**
		 * Most bytes an uploaded site kit may unpack to (all files together).
		 *
		 * @param int $bytes Bytes (default 2 GB).
		 */
		$budget = max( 1, (int) apply_filters( 'uncoder_wb/site_kit/max_unzipped', 2 * GB_IN_BYTES ) );
		$token  = self::token();
		$dir    = self::dir( 'import-' . $token );
		wp_mkdir_p( $dir );
		// Extract entry by entry: known names only, no paths outside the folder, only media / font file types
		// (never PHP, .htaccess or other server files), within the size budget.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			$stat = $zip->statIndex( $i );
			$size = is_array( $stat ) ? (int) ( $stat['size'] ?? 0 ) : 0;
			if ( str_ends_with( $name, '/' ) || false !== strpos( $name, '..' ) || false !== strpos( $name, "\0" ) ) {
				continue;
			}
			$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			$json = (bool) preg_match( '#^[a-z]+\.json$#', $name );
			$ok   = ( $json && $size <= self::MAX_JSON_SIZE )
				|| ( preg_match( '#^media/\d+/[^/\\\\]+$#', $name ) && self::media_allowed( basename( $name ) ) )
				|| ( preg_match( '#^fonts/[^/\\\\]+$#', $name ) && in_array( $ext, self::FONT_EXT, true ) );
			if ( ! $ok ) {
				continue;
			}
			$budget -= $size;
			if ( $budget < 0 ) {
				$zip->close();
				self::rrmdir( $dir );
				return new WP_Error( 'uncoder_bad_kit', __( 'This site kit is too large to unpack on this server.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$target = $dir . '/' . $name;
			wp_mkdir_p( dirname( $target ) );
			$stream = $zip->getStream( $name );
			$out    = $stream ? fopen( $target, 'wb' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( $stream && $out ) {
				// Never more than the size the zip declares (a forged header cannot unpack a bomb).
				$written = stream_copy_to_stream( $stream, $out, $size + 1 );
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				if ( false === $written || $written > $size ) {
					wp_delete_file( $target );
				}
			}
			if ( $stream ) {
				fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}
		$zip->close();
		$manifest = self::read( $dir, 'manifest.json' );
		if ( self::FORMAT !== ( $manifest['format'] ?? '' ) || (int) ( $manifest['version'] ?? 0 ) > self::VERSION ) {
			self::rrmdir( $dir );
			return new WP_Error( 'uncoder_bad_kit', __( 'This zip is not an Uncoder site kit (or it comes from a newer version of Uncoder).', 'uncoder' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array_merge( array( 'token' => $token ), $this->preview( $dir, $manifest ) ) );
	}

	/**
	 * Whether a file in the kit's media/ folder may be unpacked: a media type an export carries that this
	 * site accepts as an upload (SVGs only become media when Uncoder generated them, see import_svg()).
	 */
	private static function media_allowed( string $file ): bool {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::MEDIA_EXT, true ) || '' === sanitize_file_name( $file ) ) {
			return false;
		}
		if ( 'svg' === $ext ) {
			return true;
		}
		$type = wp_check_filetype( $file );
		return ! empty( $type['ext'] );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function read( string $dir, string $file ) {
		$raw  = is_readable( $dir . '/' . $file ) ? (string) file_get_contents( $dir . '/' . $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = '' !== $raw ? json_decode( $raw, true ) : null;
		return is_array( $data ) ? $data : array();
	}

	/**
	 * What the kit holds and what already exists here (same template title + type, same page slug, same
	 * menu name), for the conflict choice.
	 *
	 * @param array<string,mixed> $manifest Manifest.
	 * @return array<string,mixed>
	 */
	private function preview( string $dir, array $manifest ): array {
		$templates = array_map(
			fn( $t ) => array(
				'title'  => (string) ( $t['title'] ?? '' ),
				'type'   => (string) ( $t['type'] ?? '' ),
				'exists' => (bool) self::existing_template( (string) ( $t['title'] ?? '' ), (string) ( $t['type'] ?? '' ) ),
			),
			(array) self::read( $dir, 'templates.json' )
		);
		$content   = self::read( $dir, 'content.json' );
		$pages     = array_map(
			fn( $p ) => array(
				'title'  => (string) ( $p['title'] ?? '' ),
				'type'   => (string) ( $p['type'] ?? '' ),
				'exists' => (bool) self::existing_content( (string) ( $p['slug'] ?? '' ), (string) ( $p['type'] ?? '' ), (string) ( $p['title'] ?? '' ) ),
			),
			(array) ( $content['items'] ?? array() )
		);
		$posts     = array_map(
			fn( $p ) => array(
				'title'  => (string) ( $p['title'] ?? '' ),
				'type'   => 'post',
				'exists' => (bool) self::existing_content( (string) ( $p['slug'] ?? '' ), 'post', (string) ( $p['title'] ?? '' ) ),
			),
			(array) ( self::read( $dir, 'posts.json' )['items'] ?? array() )
		);
		$menus     = array_map(
			fn( $m ) => array(
				'name'   => (string) ( $m['name'] ?? '' ),
				'items'  => count( (array) ( $m['items'] ?? array() ) ),
				'exists' => (bool) wp_get_nav_menu_object( (string) ( $m['name'] ?? '' ) ),
			),
			(array) ( self::read( $dir, 'menus.json' )['menus'] ?? array() )
		);
		return array(
			'manifest'  => $manifest,
			'design'    => is_readable( $dir . '/design.json' ),
			'settings'  => is_readable( $dir . '/settings.json' ),
			'snippets'  => count( self::read( $dir, 'snippets.json' ) ),
			'fonts'     => count( self::read( $dir, 'fonts.json' ) ),
			'media'     => count( self::read( $dir, 'media.json' ) ),
			'templates' => $templates,
			'content'   => $pages,
			'posts'     => $posts,
			'menus'     => $menus,
			'homepage'  => ! empty( $content['page_on_front'] ),
		);
	}

	private static function existing_template( string $title, string $type, array $exclude = array() ): int {
		$found = get_posts(
			array(
				'post_type'      => Post_Types::TEMPLATE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'title'          => $title,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post__not_in'   => $exclude, // phpcs:ignore WordPress.DB.SlowDBQuery, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- items this import already wrote.
				'meta_key'       => Utils::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $type, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return (int) ( $found[0] ?? 0 );
	}

	/**
	 * A page / post that is already here: same slug, or (drafts have no slug yet) same title.
	 */
	private static function existing_content( string $slug, string $type, string $title = '', array $exclude = array() ): int {
		if ( ( '' === $slug && '' === $title ) || ! post_type_exists( $type ) ) {
			return 0;
		}
		$args  = array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'draft', 'private', 'future', 'pending' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post__not_in'   => $exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- items this import already wrote.
		);
		$found = get_posts( '' !== $slug ? $args + array( 'name' => $slug ) : $args + array( 'title' => $title ) );
		return (int) ( $found[0] ?? 0 );
	}

	/* ------------------------------------------------------------------ Import: run */

	/**
	 * Body: { token, step: "media"|"finish", parts: {…}, conflicts: "skip"|"replace"|"keep", homepage: bool,
	 * activate: bool }. "media" uploads the next batch and reports progress; "finish" imports the rest.
	 */
	public function import( WP_REST_Request $request ) {
		$token = preg_replace( '/[^a-z0-9]/', '', (string) $request->get_param( 'token' ) );
		$dir   = self::dir( 'import-' . $token );
		if ( '' === $token || ! is_dir( $dir ) ) {
			return new WP_Error( 'uncoder_kit_expired', __( 'This import has expired. Upload the kit again.', 'uncoder' ), array( 'status' => 404 ) );
		}
		$parts = array_intersect_key( array_map( 'boolval', (array) $request->get_param( 'parts' ) ), array_flip( self::PARTS ) );
		$state = self::read( $dir, 'state.json' ) + array(
			'media'    => array(),
			'urls'     => array(),
			'done'     => 0,
			'warnings' => array(),
		);
		if ( 'media' === $request->get_param( 'step' ) ) {
			$list = ! empty( $parts['media'] ) ? self::read( $dir, 'media.json' ) : array();
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$batch  = array_slice( $list, (int) $state['done'], self::MEDIA_BATCH );
			$origin = (string) ( self::read( $dir, 'manifest.json' )['site']['url'] ?? '' );
			foreach ( $batch as $m ) {
				$state['done'] = (int) $state['done'] + 1;
				$src           = $dir . '/media/' . (string) ( $m['file'] ?? '' );
				if ( ! is_readable( $src ) || false !== strpos( (string) $m['file'], '..' ) ) {
					continue;
				}
				// Imported before from the same site (the same file): reuse it instead of adding a copy.
				$source = md5( $origin . '|' . (string) $m['file'] . '|' . (string) filesize( $src ) );
				$known  = get_posts(
					array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'meta_key'       => self::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'     => $source, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				);
				if ( $known && get_attached_file( (int) $known[0] ) && file_exists( (string) get_attached_file( (int) $known[0] ) ) ) {
					$state['media'][ (string) (int) $m['id'] ] = (int) $known[0];
					$state['urls'][ (string) $m['url'] ]       = (string) wp_get_attachment_url( (int) $known[0] );
					$state['reused']                           = (int) ( $state['reused'] ?? 0 ) + 1;
					continue;
				}
				if ( 'svg' === strtolower( pathinfo( $src, PATHINFO_EXTENSION ) ) && ! empty( $m['generated'] ) ) {
					$new = self::import_svg( $src, $m );
					if ( is_wp_error( $new ) ) {
						$state['warnings'][] = sprintf( '%s: %s', basename( $src ), $new->get_error_message() );
					} else {
						update_post_meta( (int) $new, self::META_SOURCE, $source );
						$state['media'][ (string) (int) $m['id'] ] = (int) $new;
						$state['urls'][ (string) $m['url'] ]       = (string) wp_get_attachment_url( $new );
					}
					continue;
				}
				// Sideload a copy (media_handle_sideload moves the file it is given).
				$tmp = wp_tempnam( basename( $src ) );
				copy( $src, $tmp );
				$new = media_handle_sideload(
					array(
						'name'     => basename( $src ),
						'tmp_name' => $tmp,
					),
					0,
					sanitize_text_field( (string) ( $m['title'] ?? '' ) )
				);
				if ( is_wp_error( $new ) ) {
					wp_delete_file( $tmp );
					$state['warnings'][] = sprintf( '%s: %s', basename( $src ), $new->get_error_message() );
					continue;
				}
				if ( ! empty( $m['alt'] ) ) {
					update_post_meta( $new, '_wp_attachment_image_alt', sanitize_text_field( (string) $m['alt'] ) );
				}
				update_post_meta( $new, self::META_SOURCE, $source );
				$state['media'][ (string) (int) $m['id'] ] = (int) $new;
				if ( ! empty( $m['url'] ) ) {
					$state['urls'][ (string) $m['url'] ] = (string) wp_get_attachment_url( $new );
				}
			}
			file_put_contents( $dir . '/state.json', (string) wp_json_encode( $state ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_REST_Response(
				array(
					'done'   => (int) $state['done'],
					'total'  => count( $list ),
					'reused' => (int) ( $state['reused'] ?? 0 ),
				)
			);
		}
		$result = $this->finish( $dir, $parts, $state, (string) $request->get_param( 'conflicts' ), (bool) $request->get_param( 'homepage' ), false !== $request->get_param( 'activate' ) );
		self::rrmdir( $dir );
		return new WP_REST_Response( $result );
	}

	/**
	 * Everything after the media: fonts, Design System, settings, snippets, templates, content, menus, then
	 * the references between them.
	 *
	 * @param array<string,bool>  $parts Parts to import.
	 * @param array<string,mixed> $state Media maps from the "media" step.
	 * @return array<string,mixed>
	 */
	private function finish( string $dir, array $parts, array $state, string $conflicts, bool $homepage, bool $activate ): array {
		$conflicts = in_array( $conflicts, array( 'skip', 'replace', 'keep' ), true ) ? $conflicts : 'skip';
		$manifest  = self::read( $dir, 'manifest.json' );
		$old_home  = (string) ( $manifest['site']['url'] ?? '' );
		$report    = array(
			'created'  => array(), // Every template and page written (new or replaced), with its edit link.
			'added'    => 0,       // New items: templates, pages, menus, snippets (replaced ones are not counted).
			'replaced' => 0,
			'skipped'  => 0,
			'media'    => count( $state['media'] ) - (int) ( $state['reused'] ?? 0 ),
			'reused'   => (int) ( $state['reused'] ?? 0 ),
			'warnings' => (array) $state['warnings'],
			'parts'    => array(),
		);
		$ids       = array(); // Old post id => new post id (templates and content).

		if ( ! empty( $parts['fonts'] ) ) {
			$report['parts'][] = 'fonts';
			$this->import_fonts( $dir, $report );
		}
		if ( ! empty( $parts['design'] ) && is_readable( $dir . '/design.json' ) ) {
			$kit    = Plugin::instance()->kit();
			$errors = array();
			$data   = self::relink( self::read( $dir, 'design.json' ), $state, $ids, $old_home );
			$brand  = (array) ( $data['brand'] ?? array() );
			unset( $data['brand'] );
			$clean = $kit->sanitize( $data, 'sanitize', $errors );
			if ( $clean ) {
				$kit->snapshot( __( 'Before site kit import', 'uncoder' ) );
				$kit->save( array_merge( $kit->all(), $clean ) );
				$report['parts'][] = 'design';
			}
			foreach ( $errors as $e ) {
				$report['warnings'][] = 'Design System: ' . $e;
			}
			// Logo and site icon: taken over when the kit replaces this site's design, or when the site has none.
			$logo = absint( $brand['logo']['id'] ?? 0 );
			if ( $logo && \Uncoder\Builder\Core\Media::is_image( $logo ) && ( 'replace' === $conflicts || ! self::site_logo_id() ) ) {
				set_theme_mod( 'custom_logo', $logo );
				update_option( 'site_logo', $logo );
				$report['brand'][] = 'logo';
			}
			$icon = absint( $brand['icon']['id'] ?? 0 );
			if ( $icon && wp_attachment_is_image( $icon ) && ( 'replace' === $conflicts || ! get_option( 'site_icon' ) ) ) {
				update_option( 'site_icon', $icon );
				$report['brand'][] = 'icon';
			}
		}
		if ( ! empty( $parts['settings'] ) && is_readable( $dir . '/settings.json' ) ) {
			$current = (array) get_option( Settings_Controller::OPTION, array() );
			// Each known section goes through its own sanitizer; keys the kit does not carry stay as they are.
			$merged = array_replace( $current, self::import_settings( self::read( $dir, 'settings.json' ) ) );
			update_option( Settings_Controller::OPTION, $merged );
			$report['parts'][] = 'settings';
		}
		// Custom code is printed as written: only people who may write it import it (switched off either way).
		if ( ! empty( $parts['snippets'] ) && ! Code_Snippets::can_manage() ) {
			$report['warnings'][] = __( 'Custom code was not imported: your account cannot add unfiltered HTML.', 'uncoder' );
			unset( $parts['snippets'] );
		}
		if ( ! empty( $parts['snippets'] ) ) {
			$incoming = self::read( $dir, 'snippets.json' );
			$current  = (array) get_option( Code_Snippets::OPTION, array() );
			$names    = array_map( static fn( $s ) => is_array( $s ) ? (string) ( $s['name'] ?? $s['title'] ?? '' ) : '', $current );
			foreach ( $incoming as $snippet ) {
				if ( ! is_array( $snippet ) ) {
					continue;
				}
				$at = array_search( (string) ( $snippet['name'] ?? $snippet['title'] ?? '' ), $names, true );
				if ( false !== $at && 'skip' === $conflicts ) {
					++$report['skipped'];
					continue;
				}
				// Imported code is sanitized like a new snippet and starts switched off: an admin turns it on
				// after reading it.
				$snippet = Code_Snippets::sanitize( array_merge( $snippet, array( 'enabled' => false ) ) );
				if ( is_wp_error( $snippet ) ) {
					$report['warnings'][] = 'Custom code: ' . $snippet->get_error_message();
					continue;
				}
				$snippet['enabled'] = false;
				if ( false !== $at && 'replace' === $conflicts ) {
					$current[ $at ] = $snippet;
					++$report['replaced'];
				} else {
					$current[] = $snippet;
					++$report['added'];
				}
			}
			update_option( Code_Snippets::OPTION, array_values( $current ) );
			$report['parts'][] = 'snippets';
		}

		// Templates, then content: create or replace, keep the old → new ids.
		$lists = array();
		if ( ! empty( $parts['templates'] ) ) {
			$lists['templates'] = self::read( $dir, 'templates.json' );
		}
		$content = self::read( $dir, 'content.json' );
		if ( ! empty( $parts['content'] ) ) {
			$lists['content'] = (array) ( $content['items'] ?? array() );
		}
		$created_docs = array();
		foreach ( $lists as $part => $items ) {
			$report['parts'][] = $part;
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$new = $this->import_item( $item, $conflicts, $activate, $report );
				if ( $new ) {
					$ids[ (int) ( $item['id'] ?? 0 ) ] = $new;
					$created_docs[ $new ]              = $item;
				}
			}
		}
		// Page parents and featured images, now that every id is known.
		foreach ( $created_docs as $new => $item ) {
			$update = array( 'ID' => $new );
			if ( ! empty( $item['parent'] ) && isset( $ids[ (int) $item['parent'] ] ) ) {
				$update['post_parent'] = $ids[ (int) $item['parent'] ];
			}
			if ( count( $update ) > 1 ) {
				wp_update_post( $update );
			}
			if ( ! empty( $item['thumbnail'] ) && isset( $state['media'][ (string) (int) $item['thumbnail'] ] ) ) {
				set_post_thumbnail( $new, (int) $state['media'][ (string) (int) $item['thumbnail'] ] );
			}
		}

		if ( ! empty( $parts['posts'] ) && is_readable( $dir . '/posts.json' ) ) {
			$report['parts'][] = 'posts';
			$state['terms']    = $this->import_posts( self::read( $dir, 'posts.json' ), $state, $ids, $old_home, $conflicts, $report );
		}

		$menu_map = array();
		if ( ! empty( $parts['menus'] ) ) {
			$report['parts'][] = 'menus';
			$menu_map          = $this->import_menus( self::read( $dir, 'menus.json' ), $ids, $old_home, $conflicts, $report );
		}

		// Languages and translation groups (when this site runs WPML / Polylang with those languages).
		$linked = Multilingual::restore_languages( $created_docs, $ids );
		if ( $linked ) {
			$report['languages'] = $linked;
		}

		// References inside every imported tree: media, templates, popups, menus, links.
		foreach ( array_keys( $created_docs ) as $new ) {
			$doc      = Plugin::instance()->documents()->get( $new );
			$elements = self::relink( $doc->elements(), $state, $ids, $old_home, $menu_map );
			// The plain HTML copy (post_content) is rewritten too: the first save still had the kit's old
			// image and link addresses, and SEO plugins read their images from it. Templates have none.
			$doc->save( $elements, array( 'content_fallback' => true ) );
			$settings = $doc->page_settings();
			if ( $settings ) {
				$doc->save_page_settings( self::relink( $settings, $state, $ids, $old_home ) );
			}
			// Conditions that name imported pages.
			$conds = get_post_meta( $new, Utils::META_CONDS, true );
			if ( is_array( $conds ) && $conds ) {
				update_post_meta( $new, Utils::META_CONDS, self::relink_ids( $conds, $ids ) );
			}
		}

		if ( $homepage && ! empty( $content['page_on_front'] ) && isset( $ids[ (int) $content['page_on_front'] ] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $ids[ (int) $content['page_on_front'] ] );
			if ( ! empty( $content['page_for_posts'] ) && isset( $ids[ (int) $content['page_for_posts'] ] ) ) {
				update_option( 'page_for_posts', $ids[ (int) $content['page_for_posts'] ] );
			}
			$report['homepage'] = true;
		}

		$builder = Theme_Builder::instance();
		if ( $builder ) {
			$builder->rebuild_index();
		}
		Mega_Menu::rebuild_index();
		Plugin::instance()->documents()->regenerate_all();
		return $report;
	}

	/**
	 * @param array<string,mixed> $item   Template or page from the kit.
	 * @param array<string,mixed> $report Report (by reference).
	 */
	private function import_item( array $item, string $conflicts, bool $activate, array &$report ): int {
		$is_template = 'template' === ( $item['kind'] ?? '' );
		$type        = (string) ( $item['type'] ?? '' );
		if ( $is_template ? ! isset( Post_Types::TEMPLATE_TYPES[ $type ] ) : ! in_array( $type, Plugin::instance()->documents()->post_types(), true ) ) {
			/* translators: 1: item title, 2: content type. */
			$report['warnings'][] = sprintf( __( '“%1$s” was skipped: “%2$s” is not available on this site.', 'uncoder' ), (string) ( $item['title'] ?? '' ), $type );
			return 0;
		}
		$title    = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
		// Items this import already wrote never count as "already here" (a kit may hold two items with the same title).
		$existing = $is_template ? self::existing_template( $title, $type, $this->written ) : self::existing_content( sanitize_title( (string) ( $item['slug'] ?? '' ) ), $type, $title, $this->written );
		if ( $existing && 'skip' === $conflicts ) {
			++$report['skipped'];
			return 0;
		}
		$status = in_array( $item['status'] ?? '', array( 'publish', 'draft', 'private' ), true ) ? (string) $item['status'] : 'draft';
		$post   = array(
			'post_type'    => $is_template ? Post_Types::TEMPLATE : $type,
			'post_title'   => '' !== $title ? $title : __( 'Imported', 'uncoder' ),
			'post_status'  => $is_template && ! $activate ? 'draft' : $status,
			'post_excerpt' => sanitize_textarea_field( (string) ( $item['excerpt'] ?? '' ) ),
			'menu_order'   => (int) ( $item['menu_order'] ?? 0 ),
			'post_author'  => get_current_user_id(),
		);
		if ( ! $is_template && ! empty( $item['slug'] ) && ! ( $existing && 'keep' === $conflicts ) ) {
			$post['post_name'] = sanitize_title( (string) $item['slug'] );
		}
		$replace = $existing && 'replace' === $conflicts;
		if ( $replace ) {
			$post['ID'] = $existing;
			$id         = wp_update_post( wp_slash( $post ), true );
		} else {
			$id = wp_insert_post( wp_slash( $post ), true );
		}
		if ( is_wp_error( $id ) || ! $id ) {
			$report['warnings'][] = sprintf( '“%s”: %s', $title, is_wp_error( $id ) ? $id->get_error_message() : 'not created' );
			return 0;
		}
		++$report[ $replace ? 'replaced' : 'added' ];
		$id              = (int) $id;
		$this->written[] = $id;
		update_post_meta( $id, Utils::META_MODE, 'builder' );
		if ( $is_template ) {
			update_post_meta( $id, Utils::META_TYPE, $type );
			$errors = array();
			update_post_meta( $id, Utils::META_CONDS, $activate && isset( $item['conditions'] ) ? Conditions::sanitize( $item['conditions'], $errors ) : array() );
			if ( is_array( $item['popup'] ?? null ) ) {
				update_post_meta( $id, Utils::META_TPL, Popups::sanitize( $item['popup'], $errors ) );
			}
		} elseif ( ! empty( $item['template'] ) && is_string( $item['template'] ) ) {
			$template = \Uncoder\Builder\Frontend\Frontend::valid_page_template( $item['template'], $type );
			if ( '' !== $template ) {
				update_post_meta( $id, '_wp_page_template', $template );
			}
		}
		$doc = Plugin::instance()->documents()->get( $id );
		// Pages and posts also get the plain HTML copy visitors see if the plugin is ever deactivated.
		$doc->save( is_array( $item['elements'] ?? null ) ? $item['elements'] : array(), array( 'content_fallback' => ! $is_template ) );
		if ( is_array( $item['page_settings'] ?? null ) ) {
			$doc->save_page_settings( $item['page_settings'] );
		}
		$seo = ! $is_template && is_array( $item['seo'] ?? null ) ? $item['seo'] : array();
		if ( ! empty( $seo['title'] ) || ! empty( $seo['description'] ) ) {
			Seo::set( $id, ! empty( $seo['title'] ) ? (string) $seo['title'] : null, ! empty( $seo['description'] ) ? (string) $seo['description'] : null );
		}
		$report['created'][] = array(
			'id'       => $id,
			'title'    => $title,
			'kind'     => $is_template ? 'template' : 'page',
			'type'     => $type,
			'replaced' => $replace,
			'edit'     => admin_url( 'post.php?action=uncoder&post=' . $id ),
		);
		return $id;
	}

	/**
	 * Categories (matched by slug, created when missing) and blog posts from posts.json.
	 *
	 * @param array<string,mixed> $data   posts.json.
	 * @param array<string,mixed> $state  Media maps.
	 * @param array<int,int>      $ids    Old post id => new (by reference: the posts are added).
	 * @param array<string,mixed> $report Report (by reference).
	 * @return array<int,int> Old category id => new.
	 */
	private function import_posts( array $data, array $state, array &$ids, string $old_home, string $conflicts, array &$report ): array {
		$terms   = array();
		$pending = array_values( array_filter( (array) ( $data['categories'] ?? array() ), 'is_array' ) );
		$known   = array_map( static fn( $c ) => (int) ( $c['id'] ?? 0 ), $pending );
		// Parents before children: a category waits until its parent (when the kit has it) is placed.
		for ( $pass = 0; $pending && $pass < 10; $pass++ ) {
			foreach ( $pending as $i => $c ) {
				$parent = (int) ( $c['parent'] ?? 0 );
				if ( $parent && in_array( $parent, $known, true ) && ! isset( $terms[ $parent ] ) ) {
					continue;
				}
				unset( $pending[ $i ] );
				$slug = sanitize_title( (string) ( $c['slug'] ?? '' ) );
				$name = sanitize_text_field( (string) ( $c['name'] ?? '' ) );
				if ( '' === $slug && '' === $name ) {
					continue;
				}
				$found = '' !== $slug ? get_term_by( 'slug', $slug, 'category' ) : false;
				if ( $found instanceof \WP_Term ) {
					$terms[ (int) $c['id'] ] = (int) $found->term_id;
					continue;
				}
				$made = wp_insert_term(
					'' !== $name ? $name : $slug,
					'category',
					array(
						'slug'        => $slug,
						'description' => sanitize_textarea_field( (string) ( $c['description'] ?? '' ) ),
						'parent'      => $parent ? (int) ( $terms[ $parent ] ?? 0 ) : 0,
					)
				);
				if ( is_wp_error( $made ) ) {
					// Same name under the same parent: use that category.
					$existing = (int) $made->get_error_data( 'term_exists' );
					if ( $existing ) {
						$terms[ (int) $c['id'] ] = $existing;
					} else {
						$report['warnings'][] = $name . ': ' . $made->get_error_message();
					}
					continue;
				}
				$terms[ (int) $c['id'] ] = (int) $made['term_id'];
			}
		}

		foreach ( (array) ( $data['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$old      = (int) ( $item['id'] ?? 0 );
			$title    = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$slug     = sanitize_title( (string) ( $item['slug'] ?? '' ) );
			$existing = self::existing_content( $slug, 'post', $title, $this->written );
			if ( $existing && 'skip' === $conflicts ) {
				++$report['skipped'];
				$ids[ $old ] = $existing;
				continue;
			}
			$status = in_array( $item['status'] ?? '', array( 'publish', 'draft', 'private', 'future', 'pending' ), true ) ? (string) $item['status'] : 'draft';
			$open   = static fn( $v, string $type ) => in_array( $v, array( 'open', 'closed' ), true ) ? $v : get_default_comment_status( 'post', $type );
			$post   = array(
				'post_type'      => 'post',
				'post_title'     => '' !== $title ? $title : __( 'Imported', 'uncoder' ),
				'post_status'    => $status,
				// The text as written; WordPress filters it on save for users who may not post unfiltered HTML.
				'post_content'   => self::relink_text( (string) ( $item['content'] ?? '' ), $state, $old_home ),
				'post_excerpt'   => sanitize_textarea_field( (string) ( $item['excerpt'] ?? '' ) ),
				'post_author'    => get_current_user_id(),
				'comment_status' => $open( $item['comment_status'] ?? '', 'comment' ),
				'ping_status'    => $open( $item['ping_status'] ?? '', 'pingback' ),
				'post_category'  => array_values( array_filter( array_map( static fn( $c ) => (int) ( $terms[ (int) $c ] ?? 0 ), (array) ( $item['categories'] ?? array() ) ) ) ),
				'tags_input'     => array_values( array_map( 'sanitize_text_field', array_filter( (array) ( $item['tags'] ?? array() ), 'is_string' ) ) ),
			);
			foreach ( array( 'date' => 'post_date', 'date_gmt' => 'post_date_gmt' ) as $key => $field ) {
				if ( is_string( $item[ $key ] ?? null ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $item[ $key ] ) && '0000-00-00 00:00:00' !== $item[ $key ] ) {
					$post[ $field ] = $item[ $key ];
				}
			}
			if ( '' !== $slug && ! ( $existing && 'keep' === $conflicts ) ) {
				$post['post_name'] = $slug;
			}
			$replace = $existing && 'replace' === $conflicts;
			if ( $replace ) {
				$post['ID'] = $existing;
				$id         = wp_update_post( wp_slash( $post ), true );
			} else {
				$id = wp_insert_post( wp_slash( $post ), true );
			}
			if ( is_wp_error( $id ) || ! $id ) {
				$report['warnings'][] = sprintf( '“%s”: %s', $title, is_wp_error( $id ) ? $id->get_error_message() : 'not created' );
				continue;
			}
			$id              = (int) $id;
			$ids[ $old ]     = $id;
			$this->written[] = $id;
			++$report[ $replace ? 'replaced' : 'added' ];
			if ( ! empty( $item['sticky'] ) ) {
				stick_post( $id );
			}
			$thumb = (int) ( $state['media'][ (string) (int) ( $item['thumbnail'] ?? 0 ) ] ?? 0 );
			if ( $thumb ) {
				set_post_thumbnail( $id, $thumb );
			}
			$seo = is_array( $item['seo'] ?? null ) ? $item['seo'] : array();
			if ( ! empty( $seo['title'] ) || ! empty( $seo['description'] ) ) {
				Seo::set( $id, ! empty( $seo['title'] ) ? (string) $seo['title'] : null, ! empty( $seo['description'] ) ? (string) $seo['description'] : null );
			}
			// Custom fields: public keys only, values filtered like post text.
			foreach ( (array) ( $item['meta'] ?? array() ) as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' !== $key && ! is_protected_meta( $key, 'post' ) && is_scalar( $value ) ) {
					update_post_meta( $id, $key, wp_slash( wp_kses_post( (string) $value ) ) );
				}
			}
			$report['created'][] = array(
				'id'       => $id,
				'title'    => $title,
				'kind'     => 'post',
				'type'     => 'post',
				'replaced' => $replace,
				'edit'     => (string) get_edit_post_link( $id, 'raw' ),
			);
		}
		return $terms;
	}

	/**
	 * @param array<string,mixed> $data   menus.json.
	 * @param array<int,int>      $ids    Old post id => new.
	 * @param array<string,mixed> $report Report (by reference).
	 * @return array<int,int> Old menu id => new.
	 */
	private function import_menus( array $data, array $ids, string $old_home, string $conflicts, array &$report ): array {
		$map = array();
		foreach ( (array) ( $data['menus'] ?? array() ) as $menu ) {
			if ( ! is_array( $menu ) ) {
				continue;
			}
			$name     = sanitize_text_field( (string) ( $menu['name'] ?? '' ) );
			$existing = wp_get_nav_menu_object( $name );
			if ( $existing && 'skip' === $conflicts ) {
				$map[ (int) $menu['id'] ] = (int) $existing->term_id;
				++$report['skipped'];
				continue;
			}
			if ( $existing && 'replace' === $conflicts ) {
				foreach ( (array) wp_get_nav_menu_items( $existing->term_id, array( 'post_status' => 'any' ) ) as $old ) {
					wp_delete_post( $old->ID, true );
				}
				$menu_id = (int) $existing->term_id;
				++$report['replaced'];
			} else {
				$menu_id = wp_create_nav_menu( $existing ? $name . ' ' . __( '(imported)', 'uncoder' ) : $name );
				if ( is_wp_error( $menu_id ) ) {
					$report['warnings'][] = $name . ': ' . $menu_id->get_error_message();
					continue;
				}
				++$report['added'];
			}
			$map[ (int) $menu['id'] ] = (int) $menu_id;
			$item_map                 = array();
			$orphans                  = array(); // new item id => old parent id, for children stored before their parent.
			$items                    = (array) ( $menu['items'] ?? array() );
			usort( $items, static fn( $a, $b ) => (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 ) );
			foreach ( $items as $item ) {
				$type = (string) ( $item['type'] ?? 'custom' );
				$args = array(
					'menu-item-title'     => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
					'menu-item-status'    => 'publish',
					'menu-item-parent-id' => (int) ( $item_map[ (int) ( $item['parent'] ?? 0 ) ] ?? 0 ),
					'menu-item-target'    => '_blank' === ( $item['target'] ?? '' ) ? '_blank' : '',
					'menu-item-classes'   => sanitize_text_field( (string) ( $item['classes'] ?? '' ) ),
					'menu-item-attr-title' => sanitize_text_field( (string) ( $item['attr'] ?? '' ) ),
					'menu-item-description' => sanitize_textarea_field( (string) ( $item['desc'] ?? '' ) ),
					'menu-item-position'  => (int) ( $item['order'] ?? 0 ),
				);
				$object_id = (int) ( $item['object_id'] ?? 0 );
				if ( 'post_type' === $type && isset( $ids[ $object_id ] ) ) {
					$args += array( 'menu-item-type' => 'post_type', 'menu-item-object' => sanitize_key( (string) $item['object'] ), 'menu-item-object-id' => $ids[ $object_id ] );
				} elseif ( 'taxonomy' === $type && ! empty( $item['slug'] ) && ( $term = get_term_by( 'slug', (string) $item['slug'], sanitize_key( (string) $item['object'] ) ) ) ) {
					$args += array( 'menu-item-type' => 'taxonomy', 'menu-item-object' => $term->taxonomy, 'menu-item-object-id' => (int) $term->term_id );
				} else {
					// Custom links, and pages that were not in the kit: keep the address (moved to this site).
					$args += array( 'menu-item-type' => 'custom', 'menu-item-url' => esc_url_raw( self::relink_url( (string) ( $item['url'] ?? '' ), $old_home ) ) );
				}
				$new_item = wp_update_nav_menu_item( $menu_id, 0, $args );
				if ( is_wp_error( $new_item ) ) {
					continue;
				}
				$item_map[ (int) $item['id'] ] = (int) $new_item;
				$old_parent                    = (int) ( $item['parent'] ?? 0 );
				if ( $old_parent && ! isset( $item_map[ $old_parent ] ) ) {
					$orphans[ (int) $new_item ] = $old_parent;
				}
				if ( ! empty( $item['icon'] ) ) {
					Menu_Item_Extras::set_icon( (int) $new_item, (string) $item['icon'] );
				}
				if ( is_array( $item['mega'] ?? null ) && ! empty( $item['mega']['template'] ) && isset( $ids[ (int) $item['mega']['template'] ] ) ) {
					update_post_meta( (int) $new_item, Mega_Menu::META, array( 'template' => $ids[ (int) $item['mega']['template'] ], 'width' => (string) ( $item['mega']['width'] ?? 'container' ) ) );
				}
			}
			// A child ordered before its parent: link it now that every item exists.
			foreach ( $orphans as $new_item => $old_parent ) {
				if ( isset( $item_map[ $old_parent ] ) ) {
					update_post_meta( $new_item, '_menu_item_menu_item_parent', (string) $item_map[ $old_parent ] );
				}
			}
		}
		// Theme locations that exist here too.
		$locations = get_nav_menu_locations();
		$theme     = get_registered_nav_menus();
		foreach ( (array) ( $data['locations'] ?? array() ) as $location => $old ) {
			if ( isset( $theme[ $location ], $map[ (int) $old ] ) ) {
				$locations[ $location ] = $map[ (int) $old ];
			}
		}
		set_theme_mod( 'nav_menu_locations', $locations );
		return $map;
	}

	/**
	 * @param array<string,mixed> $report Report (by reference).
	 */
	private function import_fonts( string $dir, array &$report ): void {
		$fonts = self::read( $dir, 'fonts.json' );
		if ( ! $fonts ) {
			return;
		}
		$base = trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'uncoder/fonts/custom/';
		wp_mkdir_p( $base );
		$all = Custom_Fonts::all();
		foreach ( $fonts as $family => $font ) {
			$family = Custom_Fonts::clean_family( $family );
			if ( ! is_array( $font ) || '' === $family ) {
				continue;
			}
			$faces = array();
			foreach ( (array) ( $font['faces'] ?? array() ) as $face ) {
				if ( ! is_array( $face ) ) {
					continue;
				}
				// Same rules as an upload: a generated-looking name, a real font file (read from its signature).
				$file = basename( (string) ( $face['file'] ?? '' ) );
				$src  = $dir . '/fonts/' . $file;
				if ( ! preg_match( '/^[a-z0-9_\-]+\.(woff2|woff|ttf|otf)$/', $file ) || ! is_readable( $src ) || filesize( $src ) > Custom_Fonts::MAX_BYTES ) {
					continue;
				}
				$sniffed = Custom_Fonts::sniff( $src );
				if ( null === $sniffed ) {
					continue;
				}
				$name   = (string) preg_replace( '/\.[a-z0-9]+$/', '', $file ) . '.' . $sniffed[0];
				$weight = trim( (string) ( $face['weight'] ?? '400' ) );
				if ( ! copy( $src, $base . $name ) ) {
					continue;
				}
				$faces[] = array(
					'weight' => preg_match( '/^([1-9]00)(?: ([1-9]00))?$/', $weight ) ? $weight : '400',
					'style'  => 'italic' === ( $face['style'] ?? '' ) ? 'italic' : 'normal',
					'file'   => $name,
					'format' => $sniffed[1],
				);
			}
			if ( ! $faces ) {
				continue;
			}
			$entry = array(
				'c'     => in_array( $font['c'] ?? '', Custom_Fonts::CATEGORIES, true ) ? (string) $font['c'] : 'sans-serif',
				'faces' => $faces,
			);
			if ( in_array( $font['d'] ?? '', Custom_Fonts::DISPLAY, true ) ) {
				$entry['d'] = (string) $font['d'];
			}
			if ( is_string( $font['p'] ?? null ) && preg_match( '/^[1-9]00( [1-9]00)? (normal|italic)$/', $font['p'] ) ) {
				$entry['p'] = $font['p'];
			}
			$all[ $family ] = $entry;
		}
		Custom_Fonts::save_all( $all );
	}

	/**
	 * An Uncoder-generated SVG from the kit, recreated with Media::insert_svg() when it holds nothing active
	 * (no scripts, event handlers, javascript: links, foreign objects or outside references).
	 *
	 * @param array<string,mixed> $m Media entry.
	 * @return int|WP_Error Attachment id.
	 */
	private static function import_svg( string $src, array $m ) {
		$svg = (string) file_get_contents( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		// Only plain shapes (what Uncoder's placeholder / logo generator writes): no animation, links, embeds,
		// scripts, event handlers or outside references.
		if ( ! \Uncoder\Builder\Core\Media::is_plain_svg( $svg ) ) {
			return new WP_Error( 'uncoder_svg', __( 'This SVG was not imported: it contains active content.', 'uncoder' ) );
		}
		return \Uncoder\Builder\Core\Media::insert_svg( $svg, pathinfo( $src, PATHINFO_FILENAME ), (int) ( $m['width'] ?? 0 ), (int) ( $m['height'] ?? 0 ), (string) ( $m['title'] ?? '' ), (string) $m['generated'] );
	}

	/* ------------------------------------------------------------------ References */

	/**
	 * Points a value from the kit at this site: media ({id, url} and upload URLs in text), template / loop
	 * ids, popup links, nav menu ids and links to the old site.
	 *
	 * @param mixed               $value    Tree, settings or Design System.
	 * @param array<string,mixed> $state    Media maps.
	 * @param array<int,int>      $ids      Old post id => new.
	 * @param array<int,int>      $menus    Old menu id => new.
	 * @return mixed
	 */
	private static function relink( $value, array $state, array $ids, string $old_home, array $menus = array() ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['id'], $value['url'] ) && is_numeric( $value['id'] ) && is_string( $value['url'] ) && ! isset( $value['type'] ) ) {
				$new = (int) ( $state['media'][ (string) (int) $value['id'] ] ?? 0 );
				if ( $new ) {
					$value['id']  = $new;
					$value['url'] = (string) wp_get_attachment_url( $new );
					return $value;
				}
				$value['url'] = self::relink_text( $value['url'], $state, $old_home );
				// An id from another site would name an unrelated local file.
				if ( (int) $value['id'] && wp_get_attachment_url( (int) $value['id'] ) !== $value['url'] ) {
					$value['id'] = 0;
				}
				return $value;
			}
			foreach ( $value as $k => $v ) {
				if ( is_string( $k ) && in_array( $k, self::REF_KEYS, true ) && is_numeric( $v ) && isset( $ids[ (int) $v ] ) ) {
					$value[ $k ] = $ids[ (int) $v ];
				} elseif ( in_array( $k, array( 'menu', 'mobile_menu' ), true ) && is_numeric( $v ) && isset( $menus[ (int) $v ] ) ) {
					// The Nav Menu's own menu and its separate phone menu.
					$value[ $k ] = (string) $menus[ (int) $v ];
				} elseif ( 'terms' === $k && is_array( $v ) && ! empty( $state['terms'] ) ) {
					// Query terms ("category:5") follow the imported categories.
					$value[ $k ] = array_map( static fn( $x ) => is_string( $x ) && preg_match( '/^category:(\d+)$/', $x, $m ) && isset( $state['terms'][ (int) $m[1] ] ) ? 'category:' . $state['terms'][ (int) $m[1] ] : $x, $v );
				} elseif ( 'exclude' === $k && is_string( $v ) && 'category' === ( $value['taxonomy'] ?? '' ) && ! empty( $state['terms'] ) ) {
					// Loop Filter: excluded category ids.
					$value[ $k ] = implode( ', ', array_map( static fn( $x ) => (string) ( $state['terms'][ (int) $x ] ?? (int) $x ), array_filter( array_map( 'trim', explode( ',', $v ) ), 'is_numeric' ) ) );
				} elseif ( in_array( $k, array( 'include_ids', 'exclude_ids' ), true ) && is_array( $v ) ) {
					$value[ $k ] = array_map( static fn( $x ) => is_numeric( $x ) && isset( $ids[ (int) $x ] ) ? (string) $ids[ (int) $x ] : $x, $v );
				} else {
					$value[ $k ] = self::relink( $v, $state, $ids, $old_home, $menus );
				}
			}
			return $value;
		}
		if ( is_string( $value ) ) {
			$value = self::relink_text( $value, $state, $old_home );
			// Popup links (#uncoder-popup:open:12) follow the imported popup.
			if ( false !== strpos( $value, '#uncoder-popup:' ) ) {
				$value = (string) preg_replace_callback( '/#uncoder-popup:(open|close|toggle):(\d+)/', static fn( $m ) => '#uncoder-popup:' . $m[1] . ':' . ( $ids[ (int) $m[2] ] ?? $m[2] ), $value );
			}
		}
		return $value;
	}

	/** The site logo attachment (theme mod, or the block themes' site_logo option). */
	private static function site_logo_id(): int {
		$id = (int) get_theme_mod( 'custom_logo' );
		return $id ? $id : (int) get_option( 'site_logo' );
	}

	/**
	 * @param array<string,mixed> $state Media maps.
	 */
	private static function relink_text( string $text, array $state, string $old_home ): string {
		if ( ! empty( $state['urls'] ) ) {
			$text = strtr( $text, (array) $state['urls'] );
		}
		return self::relink_url( $text, $old_home );
	}

	/** Links to the old site's pages become links to this site (same paths). */
	private static function relink_url( string $text, string $old_home ): string {
		$new_home = home_url( '/' );
		if ( '' === $old_home || untrailingslashit( $old_home ) === untrailingslashit( $new_home ) ) {
			return $text;
		}
		return str_replace( untrailingslashit( $old_home ) . '/', $new_home, $text );
	}

	/**
	 * Template conditions that list post ids ("page is …").
	 *
	 * @param array<int|string,mixed> $conds Conditions.
	 * @param array<int,int>          $ids   Old => new.
	 * @return array<int|string,mixed>
	 */
	private static function relink_ids( array $conds, array $ids ): array {
		foreach ( $conds as $i => $c ) {
			if ( is_array( $c ) && isset( $c['ids'] ) && is_array( $c['ids'] ) && in_array( $c['rule'] ?? '', array( 'singular', 'child_of' ), true ) ) {
				$conds[ $i ]['ids'] = array_map( static fn( $x ) => $ids[ (int) $x ] ?? (int) $x, $c['ids'] );
			} elseif ( is_array( $c ) ) {
				$conds[ $i ] = self::relink_ids( $c, $ids );
			}
		}
		return $conds;
	}
}
