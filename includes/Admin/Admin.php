<?php
/**
 * wp-admin integration: menu and the React admin app.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Admin;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Fonts;
use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Install;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * One top-level "Uncoder" menu; every screen is a route of the same React app.
 */
final class Admin {

	public const SLUG = 'uncoder';

	/** @var array<string, array{0:string,1:string}> slug => [ title, capability ] */
	private array $pages = array();

	/** Screens that moved into another one: old slug => new slug + in-screen section. */
	private const MOVED = array(
		'uncoder-popups'   => array( 'uncoder-templates', 'popup' ),
		'uncoder-sections' => array( 'uncoder-templates', 'section' ),
		'uncoder-fonts'    => array( 'uncoder-design-system', 'fonts' ),
		'uncoder-kit'      => array( 'uncoder-design-system', '' ),
		'uncoder-code'     => array( 'uncoder-settings', 'code' ),
	);

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		// Runs where WordPress would say "not allowed" for a slug that no longer exists (menu.php, before admin_init).
		add_action( 'admin_page_access_denied', array( $this, 'redirect_moved' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . UNCODER_WB_BASENAME, array( $this, 'plugin_links' ) );
		add_action( 'admin_init', array( $this, 'maybe_flush' ) );
	}

	public function menu(): void {
		// Six screens: popups and saved sections live in the Theme Builder, custom fonts in the Design System,
		// custom code in Settings. Theme Builder opens for edit_posts so authors reach their saved sections;
		// theme parts inside it still need edit_theme_options (enforced by the templates API too).
		$this->pages = array(
			'uncoder'             => array( __( 'Home', 'uncoder' ), 'edit_posts' ),
			'uncoder-templates'   => array( __( 'Theme Builder', 'uncoder' ), 'edit_posts' ),
			'uncoder-design-system' => array( __( 'Design System', 'uncoder' ), 'edit_theme_options' ),
			'uncoder-submissions' => array( __( 'Submissions', 'uncoder' ), 'edit_pages' ),
			'uncoder-ai'          => array( __( 'AI & MCP', 'uncoder' ), 'manage_options' ),
			'uncoder-settings'    => array( __( 'Settings', 'uncoder' ), 'manage_options' ),
		);
		// The starter-site library arrives with the paid plans (Licence::enabled()), right after Home.
		if ( \Uncoder\Builder\Licence\Licence::enabled() && \Uncoder\Builder\Site\White_Label::can_manage() ) {
			$this->pages = array_slice( $this->pages, 0, 1, true ) + array( 'uncoder-starters' => array( __( 'Starter Sites', 'uncoder' ), 'manage_options' ) ) + array_slice( $this->pages, 1, null, true );
		}
		// The agency's private cloud library, right after Starter sites (Site\Cloud_Library).
		if ( \Uncoder\Builder\Licence\Licence::enabled() && \Uncoder\Builder\Site\Cloud_Library::offered() ) {
			$at          = array_search( 'uncoder-starters', array_keys( $this->pages ), true );
			$at          = false === $at ? 1 : $at + 1;
			$this->pages = array_slice( $this->pages, 0, $at, true ) + array( 'uncoder-cloud' => array( __( 'Cloud Library', 'uncoder' ), 'edit_posts' ) ) + array_slice( $this->pages, $at, null, true );
		}
		// Page checks (and the Agency's branded reports), right before Submissions (Site\Page_Checks).
		if ( \Uncoder\Builder\Licence\Licence::enabled() ) {
			$at          = array_search( 'uncoder-submissions', array_keys( $this->pages ), true );
			$at          = false === $at ? count( $this->pages ) : $at;
			$this->pages = array_slice( $this->pages, 0, $at, true ) + array( 'uncoder-checks' => array( __( 'Page Checks', 'uncoder' ), 'edit_pages' ) ) + array_slice( $this->pages, $at, null, true );
		}
		if ( \Uncoder\Builder\Site\Handoff::restricts() ) {
			// A handed-over site: the client keeps Home and Submissions; the design screens stay with the builders.
			$this->pages = array_intersect_key( $this->pages, array_flip( array( 'uncoder', 'uncoder-submissions' ) ) );
		}
		$unread = current_user_can( 'edit_pages' ) ? \Uncoder\Builder\Forms\Store::unread_count() : 0;

		// Right under the Dashboard (2), before anything else (Jetpack uses 3, the first separator is 4); a string, so
		// WordPress keeps the fraction.
		add_menu_page(
			\Uncoder\Builder\Core\Brand::name(),
			\Uncoder\Builder\Core\Brand::name(),
			'edit_posts',
			self::SLUG,
			array( $this, 'render' ),
			\Uncoder\Builder\Core\Brand::menu_icon(),
			'2.1'
		);
		foreach ( $this->pages as $slug => $page ) {
			$label = $page[0];
			if ( 'uncoder-submissions' === $slug && $unread > 0 ) {
				$label .= ' <span class="awaiting-mod count-' . $unread . '"><span class="pending-count" aria-hidden="true">' . number_format_i18n( $unread ) . '</span><span class="screen-reader-text">'
					/* translators: %s: number of unread form submissions. */
					. esc_html( sprintf( _n( '%s unread', '%s unread', $unread, 'uncoder' ), number_format_i18n( $unread ) ) ) . '</span></span>';
			}
			add_submenu_page( self::SLUG, $page[0] . ' ‹ ' . \Uncoder\Builder\Core\Brand::name(), $label, $page[1], $slug, array( $this, 'render' ) );
		}
	}

	/** Old bookmarks and links keep working: the moved screens open their new home at the right section. */
	public function redirect_moved(): void {
		$page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( self::MOVED[ $page ] ) || wp_doing_ajax() ) {
			return;
		}
		[ $to, $section ] = self::MOVED[ $page ];
		wp_safe_redirect( admin_url( 'admin.php?page=' . $to . ( '' !== $section ? '#' . $section : '' ) ), 301 );
		exit;
	}

	/** Lets our stylesheet give the content area of Uncoder screens (only) its paper background. */
	public function body_class( string $classes ): string {
		return '' !== $this->current_page() ? $classes . ' uncoder-ui-admin-screen' : $classes;
	}

	private function current_page(): string {
		$page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 0 === strpos( $page, 'uncoder' ) ? $page : '';
	}

	public function enqueue(): void {
		$page = $this->current_page();
		if ( '' === $page ) {
			return;
		}
		wp_enqueue_style( 'uncoder-admin', Assets::url( 'admin/admin.css' ), array(), Assets::ver( 'admin/admin.css' ) );
		wp_enqueue_script( 'uncoder-admin', Assets::url( 'admin/admin.js' ), array(), Assets::ver( 'admin/admin.js' ), true );
		wp_enqueue_media();
		if ( 'uncoder-design-system' === $page ) {
			// Text style and font previews render with the real fonts.
			Fonts::enqueue( Plugin::instance()->kit()->fonts() );
			\Uncoder\Builder\Site\Custom_Fonts::attach( 'uncoder-admin' );
		}
		wp_add_inline_script(
			'uncoder-admin',
			'window.UncoderAdmin=' . wp_json_encode( $this->config( $page ) ) . ';',
			'before'
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function config( string $page ): array {
		$user = wp_get_current_user();
		return array(
			'page'    => $page,
			'version' => UNCODER_WB_VERSION,
			// Settings → Licence and White-label show only once the paid plans launch (Licence::enabled()), and under
			// white-label "only me" only to the administrator who set it.
			'licensing' => \Uncoder\Builder\Licence\Licence::enabled() && \Uncoder\Builder\Site\White_Label::can_manage(),
			// The starter-site library replaces the built-in starters (Uncoder → Starter Sites, for who may open it).
			'library'   => \Uncoder\Builder\Licence\Licence::enabled(),
			// Branded page reports: the licence covers them (Page Checks shows "Create report" only then).
			'reports'   => \Uncoder\Builder\Licence\Licence::enabled() && \Uncoder\Builder\Site\Page_Checks::can_report() && \Uncoder\Builder\Site\Page_Checks::allowed(),
			'brand'     => \Uncoder\Builder\Site\White_Label::brand(),
			'handoff'   => \Uncoder\Builder\Site\Handoff::notice(),
			'cloud'     => \Uncoder\Builder\Site\Cloud_Library::client_config(),
			'rest'    => array(
				'root'  => esc_url_raw( rest_url( 'uncoder/v1/' ) ),
				'wp'    => esc_url_raw( rest_url( 'wp/v2/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			),
			'urls'    => array(
				'admin'       => admin_url(),
				'site'        => home_url( '/' ),
				'editor'      => admin_url( 'post.php?action=uncoder&post=' ),
				'newPage'     => admin_url( 'post-new.php?post_type=page' ),
				'mcp'         => esc_url_raw( rest_url( 'uncoder/v1/mcp' ) ),
				'assets'      => UNCODER_WB_URL . 'assets/',
				'pages'       => admin_url( 'edit.php?post_type=page' ),
				'profile'     => admin_url( 'profile.php' ),
			),
			'pages'   => array_map( static fn( $p ) => $p[0], $this->pages ),
			'user'    => array(
				'id'   => $user->ID,
				'name' => $user->display_name,
				'caps' => array(
					'manage_options'     => current_user_can( 'manage_options' ),
					'edit_theme_options' => current_user_can( 'edit_theme_options' ),
					'edit_pages'         => current_user_can( 'edit_pages' ),
					'unfiltered_html'    => current_user_can( 'unfiltered_html' ),
					'use_mcp'            => current_user_can( Install::MCP_CAP ),
				),
			),
			'templateTypes' => Post_Types::TEMPLATE_TYPES,
			'site'    => array(
				'name'  => get_bloginfo( 'name' ),
				'theme' => wp_get_theme()->get( 'Name' ),
				'block' => wp_is_block_theme(),
			),
			'kit'     => Plugin::instance()->kit()->export(),
			'prefs'   => array( 'newsSeen' => \Uncoder\Builder\Rest\Prefs_Controller::get()['newsSeen'] ),
			'customFonts' => array_merge( \Uncoder\Builder\Site\Custom_Fonts::catalog(), \Uncoder\Builder\Site\Adobe_Fonts::catalog() ),
		);
	}

	public function render(): void {
		// wp-header-end: WordPress moves admin notices here instead of into the React tree.
		echo '<div class="wrap uncoder-ui-admin-wrap"><hr class="wp-header-end" /><div id="uncoder-ui-admin-root" class="uncoder-ui-admin-root"><div class="uncoder-ui-admin-loading">' . \Uncoder\Builder\Core\Brand::loader( __( 'Loading…', 'uncoder' ), 'md' ) . '</div></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup, label escaped inside.
	}

	/**
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function plugin_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=uncoder-ai' ) ) . '">' . esc_html__( 'Connect AI', 'uncoder' ) . '</a>' );
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=uncoder' ) ) . '">' . esc_html__( 'Dashboard', 'uncoder' ) . '</a>' );
		return $links;
	}

	public function maybe_flush(): void {
		if ( get_option( 'uncoder_wb_flush_rewrite' ) ) {
			delete_option( 'uncoder_wb_flush_rewrite' );
			flush_rewrite_rules();
		}
	}
}
