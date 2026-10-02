<?php
/**
 * Front-end asset registration and per-document enqueueing.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Only the CSS/JS of widgets actually present on the page is loaded.
 */
final class Assets {

	private static bool $base_done = false;

	/** @var array<int,bool> */
	private static array $docs_done = array();

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_handles' ), 1 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'register_handles' ), 1 );
	}

	public static function url( string $relative ): string {
		return UNCODER_WB_URL . 'assets/build/' . ltrim( $relative, '/' );
	}

	public static function path( string $relative ): string {
		return UNCODER_WB_PATH . 'assets/build/' . ltrim( $relative, '/' );
	}

	public static function ver( string $relative ): string {
		$file = self::path( $relative );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $file ) ) {
			return (string) filemtime( $file );
		}
		return UNCODER_WB_VERSION;
	}

	public static function register_handles(): void {
		if ( wp_style_is( 'uncoder-frontend', 'registered' ) ) {
			return;
		}
		wp_register_style( 'uncoder-frontend', self::url( 'frontend/frontend.css' ), array(), self::ver( 'frontend/frontend.css' ) );
		$kit    = Plugin::instance()->kit()->css_file();
		$inline = $kit && ! is_admin() ? \Uncoder\Builder\Site\Performance::inline_contents( $kit['path'] ) : null;
		if ( null !== $inline ) {
			wp_register_style( 'uncoder-kit', false, array( 'uncoder-frontend' ), $kit['ver'] ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_add_inline_style( 'uncoder-kit', $inline );
		} elseif ( $kit ) {
			wp_register_style( 'uncoder-kit', $kit['url'], array( 'uncoder-frontend' ), $kit['ver'] );
		}
		wp_register_script(
			'uncoder-runtime',
			self::url( 'frontend/runtime.js' ),
			array(),
			self::ver( 'frontend/runtime.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script(
			'uncoder-runtime',
			'window.UncoderWBConfig=' . wp_json_encode(
				array(
					'rest'        => esc_url_raw( rest_url( 'uncoder/v1/' ) ),
					'breakpoints' => Breakpoints::export(),
					'lightbox'    => self::lightbox_config(),
					'i18n'        => array(
						'close' => __( 'Close', 'uncoder' ),
						'next'  => __( 'Next', 'uncoder' ),
						'prev'  => __( 'Previous', 'uncoder' ),
					),
				)
			) . ';',
			'before'
		);
		foreach ( self::modules() as $module ) {
			wp_register_script(
				'uncoder-m-' . $module,
				self::url( 'frontend/modules/' . $module . '.js' ),
				array( 'uncoder-runtime' ),
				self::ver( 'frontend/modules/' . $module . '.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/**
	 * Available front-end JS modules.
	 *
	 * @return string[]
	 */
	public static function modules(): array {
		static $modules = null;
		if ( null === $modules ) {
			$modules = array();
			foreach ( (array) glob( self::path( 'frontend/modules/*.js' ) ) as $file ) {
				$modules[] = basename( (string) $file, '.js' );
			}
		}
		return $modules;
	}

	/**
	 * Available per-widget CSS files.
	 *
	 * @return string[]
	 */
	public static function widget_styles(): array {
		static $styles = null;
		if ( null === $styles ) {
			$styles = array();
			foreach ( (array) glob( self::path( 'frontend/widgets/*.css' ) ) as $file ) {
				$styles[] = basename( (string) $file, '.css' );
			}
		}
		return $styles;
	}

	/**
	 * Design System → Lightbox, for the lightbox module.
	 *
	 * @return array<string,mixed>
	 */
	private static function lightbox_config(): array {
		$kit = Plugin::instance()->kit();
		return array(
			'auto'     => (bool) $kit->setting( 'lightbox_auto', true ),
			'bg'       => Utils::sanitize_color( (string) $kit->setting( 'lightbox_bg', '' ) ),
			'ui'       => Utils::sanitize_color( (string) $kit->setting( 'lightbox_ui', '' ) ),
			'caption'  => (bool) $kit->setting( 'lightbox_caption', true ),
			'counter'  => (bool) $kit->setting( 'lightbox_counter', true ),
			'download' => (bool) $kit->setting( 'lightbox_download', false ),
			'i18n'     => array(
				'download' => __( 'Download', 'uncoder' ),
				'viewer'   => __( 'Image viewer', 'uncoder' ),
			),
		);
	}

	public static function enqueue_base(): void {
		if ( self::$base_done ) {
			return;
		}
		self::$base_done = true;
		self::register_handles();
		wp_enqueue_style( 'uncoder-frontend' );
		wp_enqueue_style( 'uncoder-kit' );
		\Uncoder\Builder\Site\Custom_Fonts::attach( 'uncoder-frontend' );
		wp_enqueue_script( 'uncoder-runtime' );
		Fonts::enqueue( Plugin::instance()->kit()->fonts() );
	}

	public static function enqueue_module( string $name ): void {
		if ( in_array( $name, self::modules(), true ) ) {
			wp_enqueue_script( 'uncoder-m-' . $name );
		}
	}

	public static function enqueue_widget_style( string $name ): bool {
		if ( ! in_array( $name, self::widget_styles(), true ) ) {
			return false;
		}
		$handle = 'uncoder-w-' . $name;
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style( $handle, self::url( 'frontend/widgets/' . $name . '.css' ), array( 'uncoder-frontend', 'uncoder-kit' ), self::ver( 'frontend/widgets/' . $name . '.css' ) );
		}
		wp_enqueue_style( $handle );
		return true;
	}

	/**
	 * Enqueues everything a document (and documents it references) needs.
	 */
	public static function enqueue_document( Document $doc ): void {
		$id = $doc->id();
		if ( isset( self::$docs_done[ $id ] ) ) {
			return;
		}
		self::$docs_done[ $id ] = true;
		self::enqueue_base();

		// Widget base styles first: the document's generated CSS must come after them so user
		// settings win over widget defaults with equal specificity.
		$assets = $doc->assets();
		$deps   = array( 'uncoder-kit' );
		foreach ( array_unique( array_merge( $assets['styles'], $assets['types'] ) ) as $style ) {
			if ( self::enqueue_widget_style( $style ) ) {
				$deps[] = 'uncoder-w-' . $style;
			}
		}

		$css    = $doc->css();
		$inline = ! empty( $css['url'] ) && ! empty( $css['path'] ) ? \Uncoder\Builder\Site\Performance::inline_contents( $css['path'] ) : null;
		if ( null !== $inline ) {
			$css = array(
				'ver'    => $css['ver'],
				'inline' => $inline,
			);
		}
		if ( ! empty( $css['url'] ) ) {
			wp_enqueue_style( 'uncoder-doc-' . $id, $css['url'], $deps, $css['ver'] );
		} elseif ( ! empty( $css['inline'] ) ) {
			wp_register_style( 'uncoder-doc-' . $id, false, $deps, $css['ver'] ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_style( 'uncoder-doc-' . $id );
			wp_add_inline_style( 'uncoder-doc-' . $id, $css['inline'] );
		}
		foreach ( $assets['scripts'] as $script ) {
			self::enqueue_module( $script );
		}
		Fonts::enqueue( $assets['fonts'] );

		foreach ( $assets['refs'] as $ref ) {
			// Embedded templates in the visitor's language (WPML / Polylang), as Template_Embed renders them.
			$ref     = \Uncoder\Builder\Site\Multilingual::translate_id( (int) $ref );
			$ref_doc = Plugin::instance()->documents()->get( (int) $ref );
			if ( $ref_doc && $ref_doc->is_builder() ) {
				self::enqueue_document( $ref_doc );
			}
		}
	}

	/**
	 * Late enqueue (a document rendered after wp_head, e.g. through a shortcode): styles print in the footer.
	 */
	public static function enqueue_late( Document $doc ): void {
		self::enqueue_document( $doc );
	}

	/**
	 * Everything, for the editor canvas.
	 */
	public static function enqueue_all(): void {
		self::enqueue_base();
		\Uncoder\Builder\Site\Adobe_Fonts::enqueue_all();
		foreach ( self::widget_styles() as $style ) {
			self::enqueue_widget_style( $style );
		}
		foreach ( self::modules() as $module ) {
			self::enqueue_module( $module );
		}
	}
}
