<?php
/**
 * Edit with Uncoder in the admin bar.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Editor;

use Uncoder\Builder\Core\Brand;
use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Renderer;
use Uncoder\Builder\Site\Role_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * "Edit with Uncoder" in the front-end admin bar opens a menu of every Uncoder document on the page: the page
 * itself, its header and footer, theme templates, popups, loop items and mega menus. WordPress usually draws the
 * admin bar at the top of the body, before most of them have rendered, so the menu is filled from a list printed
 * at the end of the page (assets/build/wp/admin-bar.js).
 */
final class Admin_Bar {

	public const NODE = 'uncoder-edit';

	/** Whether the node was added on this request (the list is printed only then). */
	private bool $added = false;

	public function register(): void {
		add_action( 'admin_bar_menu', array( $this, 'node' ), 80 );
		add_action( 'wp_enqueue_scripts', array( $this, 'style' ) );
		add_action( 'wp_footer', array( $this, 'documents' ), 1001 );
	}

	private static function enabled(): bool {
		return ! is_admin() && is_user_logged_in() && is_admin_bar_showing() && Role_Manager::can_use();
	}

	/** The viewed post or page, when it can be opened in Uncoder. */
	private static function main_id(): int {
		$id = is_singular() ? (int) get_queried_object_id() : 0;
		return $id && Editor::can_edit( $id ) ? $id : 0;
	}

	public function node( \WP_Admin_Bar $bar ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$main        = self::main_id();
		$this->added = true;
		$chevron     = '<svg class="uncoder-ab__chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>';
		$bar->add_node(
			array(
				'id'    => self::NODE,
				'title' => Brand::mark( 15 ) . '<span class="uncoder-ab__label">' . esc_html__( 'Edit with Uncoder', 'uncoder' ) . '</span>' . $chevron,
				// Without the script the link still opens the page itself (or the Theme Builder on archives).
				'href'  => $main ? Editor::url( $main ) : admin_url( 'admin.php?page=uncoder-templates' ),
				'meta'  => array( 'class' => 'uncoder-ab' ),
			)
		);
		// A child makes WordPress build the dropdown; admin-bar.js replaces it with the documents.
		$bar->add_node(
			array(
				'id'     => self::NODE . '-docs',
				'parent' => self::NODE,
				'title'  => esc_html__( 'Loading…', 'uncoder' ),
				'meta'   => array( 'class' => 'uncoder-ab__placeholder' ),
			)
		);
	}

	public function style(): void {
		if ( self::enabled() ) {
			wp_enqueue_style( 'uncoder-admin-bar', UNCODER_WB_URL . 'assets/build/wp/admin-bar.css', array( 'admin-bar' ), UNCODER_WB_VERSION );
		}
	}

	/**
	 * Menu order: the viewed page, the header, page templates, the footer, then popups, mega menus, sections and
	 * loop items, then other posts whose content is on the page (search results, archives). Render order within.
	 */
	private static function rank( string $type, bool $main, bool $template ): int {
		if ( $main ) {
			return 0;
		}
		if ( ! $template ) {
			return 5;
		}
		switch ( $type ) {
			case 'header':
				return 1;
			case 'footer':
				return 3;
			case 'popup':
			case 'mega-menu':
			case 'section':
			case 'loop-item':
				return 4;
			default:
				return 2;
		}
	}

	/**
	 * The documents of this page view, for admin-bar.js.
	 */
	public function documents(): void {
		if ( ! $this->added ) {
			return;
		}
		$main = self::main_id();
		$ids  = ( $main ? array( $main => (string) get_post_type( $main ) ) : array() ) + Renderer::rendered();
		$docs = array();
		foreach ( $ids as $id => $type ) {
			$post = get_post( (int) $id );
			if ( ! $post || 'trash' === $post->post_status || ! Editor::can_edit( (int) $id ) ) {
				continue;
			}
			$template = Post_Types::TEMPLATE === $post->post_type;
			$object   = get_post_type_object( $post->post_type );
			$status   = get_post_status_object( $post->post_status );
			$docs[]   = array(
				'id'      => (int) $id,
				'title'   => '' !== trim( $post->post_title ) ? $post->post_title : __( '(no title)', 'uncoder' ),
				'type'    => $template ? (string) $type : 'content',
				// The badge: the template type ("Header", "Popup", "Single post") or the post type ("Page", "Post").
				'label'   => $template ? ( Post_Types::TEMPLATE_TYPES[ $type ] ?? __( 'Section', 'uncoder' ) ) : ( $object ? $object->labels->singular_name : __( 'Page', 'uncoder' ) ),
				'url'     => Editor::url( (int) $id ),
				'current' => (int) $id === $main,
				'status'  => 'publish' !== $post->post_status && $status ? $status->label : '',
				'rank'    => self::rank( (string) $type, (int) $id === $main, $template ),
			);
		}
		// PHP 8 sorts stably: documents of the same rank keep the order they rendered in.
		usort( $docs, static fn( array $a, array $b ): int => $a['rank'] <=> $b['rank'] );
		foreach ( $docs as &$doc ) {
			$doc['also'] = 5 === $doc['rank'];
			unset( $doc['rank'] );
		}
		unset( $doc );
		$links = array();
		if ( current_user_can( 'edit_posts' ) ) {
			$links[] = array(
				'icon'  => 'templates',
				'label' => __( 'Theme Builder', 'uncoder' ),
				'url'   => admin_url( 'admin.php?page=uncoder-templates' ),
			);
		}
		if ( current_user_can( 'edit_theme_options' ) ) {
			$links[] = array(
				'icon'  => 'design',
				'label' => __( 'Design System', 'uncoder' ),
				'url'   => admin_url( 'admin.php?page=uncoder-design-system' ),
			);
		}
		$data = array(
			'docs'  => $docs,
			'links' => $links,
			// Clear Cache (administrators): rebuilds Uncoder's CSS and purges page caches (Site\Cache).
			'clear' => current_user_can( 'manage_options' ) ? array(
				'url'   => rest_url( \Uncoder\Builder\Rest\Rest::NS . '/settings/clear-cache' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			) : null,
			'i18n'  => array(
				/* translators: %s: document title, e.g. "Header". */
				'edit'     => __( 'Edit %s with Uncoder', 'uncoder' ),
				'clear'    => __( 'Clear Cache', 'uncoder' ),
				'clearing' => __( 'Clearing…', 'uncoder' ),
				'cleared'  => __( 'Cache cleared', 'uncoder' ),
				'failed'   => __( 'Could not clear the cache', 'uncoder' ),
			),
		);
		wp_print_inline_script_tag( 'window.UncoderAdminBar=' . wp_json_encode( $data ) . ';' );
		wp_print_script_tag(
			array(
				'src'   => add_query_arg( 'ver', UNCODER_WB_VERSION, UNCODER_WB_URL . 'assets/build/wp/admin-bar.js' ),
				'defer' => true,
			)
		);
	}
}
