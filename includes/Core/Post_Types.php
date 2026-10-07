<?php
/**
 * Registers the template post type and builder meta.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * `uncoder_template` stores theme parts, popups, sections, mega menus and loop items.
 */
final class Post_Types {

	public const TEMPLATE = 'uncoder_template';

	public const TEMPLATE_TYPES = array(
		'header'         => 'Header',
		'footer'         => 'Footer',
		'single-post'    => 'Single post',
		'single-page'    => 'Single page',
		'single'         => 'Single (custom post type)',
		'archive'        => 'Archive',
		'search-results' => 'Search results',
		'error-404'      => '404 page',
		'popup'          => 'Popup',
		'mega-menu'      => 'Mega menu',
		'loop-item'      => 'Loop item',
		'section'        => 'Section',
	);

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'wp_post_revision_meta_keys', array( $this, 'revision_meta_keys' ) );
		add_action( 'template_redirect', array( $this, 'protect_templates' ) );
		add_action( 'before_delete_post', array( $this, 'cleanup' ) );
		add_action( 'uncoder_wb_daily', array( $this, 'daily' ) );
		add_filter( 'map_meta_cap', array( $this, 'template_caps' ), 10, 4 );
	}

	/**
	 * Template types that change the whole site (everything except reusable sections) need the theme
	 * capability, whichever path edits them: the editor, REST, MCP, undo or wp-admin.
	 */
	public static function needs_theme_caps( string $type ): bool {
		return '' !== $type && 'section' !== $type;
	}

	/**
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id User.
	 * @param mixed[]  $args    Args (post id first).
	 * @return string[]
	 */
	public function template_caps( $caps, $cap, $user_id, $args ): array {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'publish_post' ), true ) || empty( $args[0] ) ) {
			return (array) $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post || self::TEMPLATE !== $post->post_type ) {
			return (array) $caps;
		}
		if ( self::needs_theme_caps( (string) get_post_meta( $post->ID, Utils::META_TYPE, true ) ) ) {
			$caps[] = 'edit_theme_options';
		}
		return (array) $caps;
	}

	public function register_post_type(): void {
		register_post_type(
			self::TEMPLATE,
			array(
				'labels'              => array(
					'name'          => __( 'Uncoder Templates', 'uncoder' ),
					'singular_name' => __( 'Uncoder Template', 'uncoder' ),
					'add_new_item'  => __( 'Add New Template', 'uncoder' ),
					'edit_item'     => __( 'Edit Template', 'uncoder' ),
				),
				'public'              => false,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'rewrite'             => false,
				'query_var'           => self::TEMPLATE,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'author', 'revisions', 'thumbnail' ),
			)
		);
	}

	/**
	 * Keeps builder data in revisions (WP 6.4+).
	 *
	 * @param string[] $keys Keys.
	 * @return string[]
	 */
	public function revision_meta_keys( array $keys ): array {
		$keys[] = Utils::META_DATA;
		$keys[] = Utils::META_PAGE;
		return $keys;
	}

	/**
	 * Templates are never public pages: only editors may open them (for the canvas preview).
	 */
	public function protect_templates(): void {
		if ( is_singular( self::TEMPLATE ) && ! current_user_can( 'edit_post', (int) get_queried_object_id() ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	public function cleanup( int $post_id ): void {
		$uploads = Utils::uploads();
		if ( $uploads ) {
			wp_delete_file( $uploads['dir'] . '/css/doc-' . $post_id . '.css' );
		}
	}

	public function daily(): void {
		/**
		 * Daily maintenance hook (log pruning, token expiry…).
		 */
		do_action( 'uncoder_wb/daily' );
	}
}
