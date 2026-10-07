<?php
/**
 * Front-end output for builder documents.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Frontend;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the_content of builder posts, adds page templates and the template shortcode.
 */
final class Frontend {

	public const PAGE_TEMPLATES = array(
		'uncoder-canvas'     => 'Uncoder Canvas',
		'uncoder-full-width' => 'Uncoder Full Width',
	);

	/** @var array<int,bool> */
	private array $rendering = array();

	public function register(): void {
		add_filter( 'the_content', array( $this, 'the_content' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'template_include', array( $this, 'page_template' ), 90 );
		add_action( 'init', array( $this, 'register_page_templates' ) );
		add_shortcode( 'uncoder_template', array( $this, 'shortcode' ) );
		add_action( 'wp_head', array( $this, 'js_flag' ), 1 );
		add_filter( 'render_block_core/post-title', array( $this, 'hide_block_title' ), 10, 3 );
		add_filter( 'the_title', array( $this, 'hide_classic_title' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_site_features' ), 21 );
		add_action( 'wp_footer', array( $this, 'back_to_top' ), 30 );
	}

	/**
	 * Site-wide features from the Design System settings (smooth scrolling is CSS only).
	 */
	public function enqueue_site_features(): void {
		if ( Plugin::instance()->kit()->setting( 'back_to_top', false ) ) {
			Assets::enqueue_base();
			Assets::enqueue_module( 'back-to-top' );
		}
		// Image links anywhere (post content, WordPress galleries) open in the lightbox.
		if ( Plugin::instance()->kit()->setting( 'lightbox_auto', true ) && ! is_admin() && ! is_feed() ) {
			Assets::enqueue_base();
			Assets::enqueue_module( 'lightbox' );
		}
	}

	/**
	 * Back-to-top button (Design System → settings.back_to_top). Works without JavaScript as a plain #top link.
	 */
	public function back_to_top(): void {
		$kit = Plugin::instance()->kit();
		if ( ! $kit->setting( 'back_to_top', false ) || is_admin() || is_feed() ) {
			return;
		}
		$side = 'left' === $kit->setting( 'back_to_top_position', 'right' ) ? 'left' : 'right';
		printf(
			'<a href="#" class="uncoder-back-to-top uncoder-back-to-top--%1$s" data-uncoder-js="back-to-top" aria-label="%2$s">%3$s</a>',
			esc_attr( $side ),
			esc_attr__( 'Back to top', 'uncoder' ),
			\Uncoder\Builder\Core\Icons::render( 'arrow-up', array( 'width' => '20', 'height' => '20' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG.
		);
	}

	private function title_hidden( int $post_id ): bool {
		if ( ! $post_id || ! is_singular() || (int) get_queried_object_id() !== $post_id || ! Utils::is_builder_post( $post_id ) ) {
			return false;
		}
		$page = get_post_meta( $post_id, Utils::META_PAGE, true );
		return is_array( $page ) && ! empty( $page['hide_title'] );
	}

	/**
	 * "Hide page title" for block themes (core/post-title of the queried post).
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Block.
	 * @param object $instance Block instance.
	 */
	public function hide_block_title( $content, $block, $instance = null ): string {
		$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();
		return $this->title_hidden( $post_id ) ? '' : (string) $content;
	}

	/**
	 * "Hide page title" for classic themes (the main loop title only).
	 *
	 * @param string $title Title.
	 * @param int    $post_id Post id.
	 */
	public function hide_classic_title( $title, $post_id = 0 ): string {
		// Only the theme's title: widgets that show the title (Post Title, Breadcrumbs…) still get it.
		if ( wp_is_block_theme() || is_admin() || ! in_the_loop() || ! is_main_query() || \Uncoder\Builder\Core\Renderer::rendering() ) {
			return (string) $title;
		}
		return $this->title_hidden( (int) $post_id ) ? '' : (string) $title;
	}

	/**
	 * Marks <html> as JS-enabled before first paint so entrance animations never flash.
	 */
	public function js_flag(): void {
		wp_print_inline_script_tag( "document.documentElement.classList.add('uncoder-js');" . self::color_scheme_script() );
	}

	/**
	 * Dark mode (Design System settings.color_scheme): sets <html data-uncoder-scheme> before anything paints,
	 * from the visitor's saved choice, else light ("toggle") or the device setting ("auto"). In "auto"
	 * it keeps following the device until the visitor picks a scheme with a Color Scheme Switch.
	 */
	public static function color_scheme_script(): string {
		$mode = (string) Plugin::instance()->kit()->setting( 'color_scheme', 'light' );
		if ( ! in_array( $mode, array( 'toggle', 'auto' ), true ) ) {
			return '';
		}
		$auto = 'auto' === $mode ? 'true' : 'false';
		return "(function(d,a){var s,m=window.matchMedia&&matchMedia('(prefers-color-scheme: dark)');"
			. "function get(){try{return localStorage.getItem('uncoder-scheme')}catch(e){return null}}"
			. "function set(){s=get();if(s!=='dark'&&s!=='light'){s=a&&m&&m.matches?'dark':'light'}d.documentElement.setAttribute('data-uncoder-scheme',s)}"
			. "set();if(a&&m&&m.addEventListener){m.addEventListener('change',function(){if(!get()){set()}})}})(document," . $auto . ');';
	}

	private function is_preview(): bool {
		$preview = Plugin::instance()->module( 'preview' );
		return $preview && method_exists( $preview, 'active' ) && $preview->active();
	}

	/**
	 * @param string $content Content.
	 */
	public function the_content( $content ): string {
		$post_id = (int) get_the_ID();
		if ( ! $post_id || ! Utils::is_builder_post( $post_id ) || post_password_required( $post_id ) ) {
			return (string) $content;
		}
		if ( isset( $this->rendering[ $post_id ] ) ) {
			return '';
		}
		if ( $this->is_preview() && (int) get_queried_object_id() === $post_id ) {
			return (string) apply_filters( 'uncoder_wb/preview/mount', '', $post_id );
		}
		$doc = Plugin::instance()->documents()->get( $post_id );
		if ( ! $doc ) {
			return (string) $content;
		}
		$this->rendering[ $post_id ] = true;
		Assets::enqueue_late( $doc );
		// In block themes the content sits in a constrained layout: alignfull lets sections run edge to edge.
		$html = $doc->render(
			array(
				'post_id' => $post_id,
				'class'   => wp_is_block_theme() ? 'alignfull' : '',
			)
		);
		unset( $this->rendering[ $post_id ] );
		return $html;
	}

	public function enqueue(): void {
		$kit = Plugin::instance()->kit();
		if ( ! empty( $kit->get( 'theme' )['enabled'] ) ) {
			Assets::enqueue_base();
		}
		if ( is_singular() ) {
			$id = (int) get_queried_object_id();
			if ( $id && Utils::is_builder_post( $id ) ) {
				$doc = Plugin::instance()->documents()->get( $id );
				if ( $doc ) {
					Assets::enqueue_document( $doc );
				}
			}
		}
		/**
		 * Lets modules (theme builder, popups) enqueue the documents they will print.
		 */
		do_action( 'uncoder_wb/frontend/enqueue' );
	}

	/**
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		if ( ! empty( Plugin::instance()->kit()->get( 'theme' )['enabled'] ) ) {
			$classes[] = 'uncoder-kit';
		}
		if ( Plugin::instance()->kit()->setting( 'smooth_scroll', true ) ) {
			$classes[] = 'uncoder-smooth-scroll';
		}
		if ( is_singular() && Utils::is_builder_post( (int) get_queried_object_id() ) ) {
			$classes[] = 'uncoder-page';
		}
		$template = is_singular() ? get_page_template_slug( (int) get_queried_object_id() ) : '';
		if ( $template && isset( self::PAGE_TEMPLATES[ $template ] ) ) {
			$classes[] = 'uncoder-template-' . str_replace( 'uncoder-', '', $template );
		}
		return $classes;
	}

	/**
	 * A page template slug from an import file when this site has it (Uncoder's own or the theme's), else ''.
	 */
	public static function valid_page_template( string $slug, string $post_type ): string {
		if ( isset( self::PAGE_TEMPLATES[ $slug ] ) || 'default' === $slug ) {
			return $slug;
		}
		return array_key_exists( $slug, wp_get_theme()->get_page_templates( null, $post_type ) ) ? $slug : '';
	}

	public function register_page_templates(): void {
		foreach ( Plugin::instance()->documents()->post_types() as $type ) {
			add_filter(
				'theme_' . $type . '_templates',
				static function ( $templates ) {
					foreach ( self::PAGE_TEMPLATES as $slug => $label ) {
						$templates[ $slug ] = $label;
					}
					return $templates;
				}
			);
		}
	}

	/**
	 * @param string $template Template path.
	 */
	public function page_template( $template ): string {
		if ( ! is_singular() ) {
			return (string) $template;
		}
		$post_id = (int) get_queried_object_id();
		$slug    = get_page_template_slug( $post_id );
		if ( is_singular( \Uncoder\Builder\Core\Post_Types::TEMPLATE ) ) {
			return UNCODER_WB_PATH . 'templates/canvas.php';
		}
		if ( 'uncoder-canvas' === $slug ) {
			return UNCODER_WB_PATH . 'templates/canvas.php';
		}
		if ( 'uncoder-full-width' === $slug ) {
			return UNCODER_WB_PATH . 'templates/full-width.php';
		}
		return (string) $template;
	}

	/**
	 * [uncoder_template id="123"]
	 *
	 * @param array<string,string>|string $atts Attributes.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'uncoder_template' );
		$id   = absint( $atts['id'] );
		if ( ! $id || \Uncoder\Builder\Core\Post_Types::TEMPLATE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		// Only reusable sections are meant for embedding; headers, popups etc. render through the theme builder.
		if ( \Uncoder\Builder\Core\Post_Types::needs_theme_caps( (string) get_post_meta( $id, \Uncoder\Builder\Core\Utils::META_TYPE, true ) ) ) {
			return '';
		}
		$doc = Plugin::instance()->documents()->get( $id );
		if ( ! $doc || isset( $this->rendering[ $id ] ) ) {
			return '';
		}
		$this->rendering[ $id ] = true;
		Assets::enqueue_late( $doc );
		$html = $doc->render();
		unset( $this->rendering[ $id ] );
		return $html;
	}
}
