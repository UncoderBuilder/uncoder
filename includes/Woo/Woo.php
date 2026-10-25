<?php
/**
 * WooCommerce integration.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Woo;

use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Theme\Theme_Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps WooCommerce in charge of commerce on pages Uncoder lays out: a product template runs inside WooCommerce's
 * single-product lifecycle (notices, product classes, structured data, the before / after actions extensions use),
 * product galleries get the theme features they need, and the editor canvas loads WooCommerce's scripts so variation
 * forms and galleries work while designing. Does nothing without WooCommerce.
 */
final class Woo {

	public function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'after_setup_theme', array( $this, 'theme_support' ), 20 );
		add_filter( 'uncoder_wb/theme/render_body', array( $this, 'protected_product' ), 10, 3 );
		add_action( 'uncoder_wb/theme/before_body', array( $this, 'before_body' ), 10, 3 );
		add_action( 'uncoder_wb/theme/after_body', array( $this, 'after_body' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'store_assets_only' ), 30 );
		add_action( 'wp_enqueue_scripts', array( $this, 'canvas_assets' ), 40 );
		add_filter( 'uncoder_wb/preview/mount', array( $this, 'canvas_mount' ), 20, 2 );
		add_filter( 'body_class', array( $this, 'canvas_body_class' ) );
		// A page holding the Cart, Checkout or My Account widget is that WooCommerce page for WooCommerce too (its
		// scripts, redirects and checks), wherever the shop's settings point.
		add_filter( 'woocommerce_is_cart', fn( $is ) => $is || $this->page_uses( 'cart' ) );
		add_filter( 'woocommerce_is_checkout', fn( $is ) => $is || $this->page_uses( 'checkout' ) );
		add_filter( 'woocommerce_is_account_page', fn( $is ) => $is || $this->page_uses( 'my-account' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'fragments' ) );
		add_action( 'uncoder_wb/dynamic_tags/register', array( Tags::class, 'register' ) );
		add_action( 'wp_print_styles', array( $this, 'style_order' ), PHP_INT_MAX );
		add_action( 'template_redirect', array( $this, 'template_redirect' ), 9 );
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
	}

	/**
	 * Uncoder never reads or writes orders itself and leaves payment to WooCommerce's own checkout: it works with the
	 * order storage tables (HPOS) and with the cart and checkout blocks, so WooCommerce doesn't list it as incompatible.
	 */
	public function declare_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', UNCODER_WB_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', UNCODER_WB_FILE, true );
		}
	}

	/**
	 * A page whose Cart, Checkout or My Account widget makes it that WooCommerce page is not cached, as WooCommerce
	 * does for the pages set in its settings (a cached checkout would show one visitor's details to the next).
	 */
	public function template_redirect(): void {
		if ( $this->page_uses( 'cart' ) || $this->page_uses( 'checkout' ) || $this->page_uses( 'my-account' ) ) {
			wc_nocache_headers();
		}
	}

	/**
	 * WooCommerce's stylesheets print before the Design System and the WooCommerce widgets' styles, on every theme.
	 * Classic themes do that by themselves (the page, and Uncoder's styles, come after the head); block themes render
	 * the page before wp_head(), so Uncoder's styles would be queued first and the Design System's WooCommerce button
	 * rules, which tie with WooCommerce's own, would lose. Runs as the head prints its styles: only what WooCommerce
	 * still has enqueued then becomes a dependency (a theme that dequeued a stylesheet keeps it off).
	 */
	public function style_order(): void {
		$styles = wp_styles();
		$wc     = array_values(
			array_filter(
				array( 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'woocommerce-blocktheme' ),
				static fn( string $handle ): bool => wp_style_is( $handle, 'enqueued' )
			)
		);
		if ( ! $wc ) {
			return;
		}
		foreach ( array( 'uncoder-kit', 'uncoder-w-woo' ) as $handle ) {
			if ( isset( $styles->registered[ $handle ] ) ) {
				$styles->registered[ $handle ]->deps = array_values( array_unique( array_merge( $styles->registered[ $handle ]->deps, $wc ) ) );
			}
		}
	}

	/**
	 * Settings → Performance → "Load WooCommerce files only on store pages": on a page without anything from the store
	 * (no WooCommerce page, no WooCommerce widget, Mini Cart included, no WooCommerce shortcode or block in its
	 * content), WooCommerce's stylesheets and cart scripts stay out, and with them jQuery when nothing else needs it.
	 * Order attribution keeps running on every page (it remembers where a visitor came from). Runs after Uncoder has
	 * queued the page's documents (Frontend::enqueue, priority 20).
	 */
	public function store_assets_only(): void {
		if ( ! \Uncoder\Builder\Site\Performance::get()['woo_store_only'] || is_admin() || wp_style_is( 'uncoder-w-woo', 'enqueued' ) ) {
			return;
		}
		if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
			return;
		}
		$preview = Plugin::instance()->module( 'preview' );
		if ( $preview instanceof \Uncoder\Builder\Editor\Preview && $preview->active() ) {
			return;
		}
		$store_code = '/\[(products?|product_page|product_categor|add_to_cart|woocommerce_|shop_messages)|<!-- wp:woocommerce\//';
		$post       = is_singular() ? get_queried_object() : null;
		if ( $post instanceof \WP_Post && preg_match( $store_code, $post->post_content ) ) {
			return;
		}
		// Uncoder documents (the page and its templates) keep their shortcodes in their data, not in the post content.
		foreach ( \Uncoder\Builder\Core\Assets::documents() as $doc_id ) {
			if ( preg_match( $store_code, (string) get_post_meta( $doc_id, \Uncoder\Builder\Core\Utils::META_DATA, true ) ) ) {
				return;
			}
		}
		// Search results can list products.
		if ( is_search() ) {
			return;
		}
		foreach ( array( 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'woocommerce-blocktheme' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
		foreach ( array( 'wc-add-to-cart', 'woocommerce', 'wc-cart-fragments' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
		/**
		 * Fires on a page without anything from the store, after WooCommerce's files were taken off: a site can take
		 * off its WooCommerce extensions' files there too (a payment plugin's buttons script, for instance).
		 */
		do_action( 'uncoder_wb/woo/non_store_page' );
	}

	/**
	 * The Mini Cart widget's item count and subtotal as cart fragments: WooCommerce's script swaps them in after
	 * adding to the cart and on cached pages.
	 *
	 * @param array<string,string> $fragments Fragments by selector.
	 * @return array<string,string>
	 */
	public function fragments( $fragments ): array {
		$fragments = (array) $fragments;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$fragments['span.uncoder-mini-cart__count']    = \Uncoder\Builder\Widgets\Mini_Cart::count_html( (int) WC()->cart->get_cart_contents_count() );
			$fragments['span.uncoder-mini-cart__subtotal'] = \Uncoder\Builder\Widgets\Mini_Cart::subtotal_html( (string) WC()->cart->get_cart_subtotal() );
			$fragments['span.uncoder-mini-cart__label']    = \Uncoder\Builder\Widgets\Mini_Cart::label_html( (int) WC()->cart->get_cart_contents_count() );
		}
		return $fragments;
	}

	/**
	 * Whether the page being viewed (a singular page) holds a widget: in its own Uncoder content, in the body template
	 * showing it, or in a template either of them embeds.
	 */
	private function page_uses( string $widget ): bool {
		static $types = null;
		if ( null === $types ) {
			if ( ! did_action( 'wp' ) || is_admin() || ! is_singular() ) {
				return false;
			}
			$types = self::doc_types( (int) get_queried_object_id() );
			$theme = Theme_Builder::instance();
			$body  = $theme ? (int) $theme->resolve_body()[0] : 0;
			if ( $body ) {
				$types = array_merge( $types, self::doc_types( $body ) );
			}
		}
		return in_array( $widget, $types, true );
	}

	/**
	 * Element types of an Uncoder document and of the templates it embeds (Template widgets, two levels deep).
	 *
	 * @return string[]
	 */
	private static function doc_types( int $doc_id, int $depth = 0 ): array {
		$doc = Plugin::instance()->documents()->get( $doc_id );
		if ( ! $doc || ! $doc->is_builder() ) {
			return array();
		}
		$elements = $doc->elements();
		$types    = Tree::types( $elements );
		if ( $depth < 2 && in_array( 'template', $types, true ) ) {
			Tree::walk(
				$elements,
				static function ( $node ) use ( &$types, $doc_id, $depth ) {
					$embedded = 'template' === ( $node['type'] ?? '' ) ? absint( $node['settings']['template_id'] ?? 0 ) : 0;
					if ( $embedded && $embedded !== $doc_id ) {
						$types = array_merge( $types, self::doc_types( $embedded, $depth + 1 ) );
					}
				}
			);
		}
		return array_values( array_unique( $types ) );
	}

	/**
	 * With Uncoder's product or shop templates in use, the theme supports WooCommerce: WooCommerce runs themes that do
	 * not declare it in a fallback mode that rewrites product pages, the shop and the category archives around the
	 * templates (the product's content becomes a whole product page, the Reviews tab goes, the shop stays a plain page).
	 * A product gallery built with Uncoder zooms, opens in the lightbox and slides, whatever the theme declares (the
	 * theme's choices stay as they are when it already declares a feature).
	 */
	public function theme_support(): void {
		$index = (array) get_option( Theme_Builder::INDEX_OPTION, array() );
		if ( empty( $index['single-product'] ) && empty( $index['product-archive'] ) ) {
			return;
		}
		if ( ! current_theme_supports( 'woocommerce' ) ) {
			add_theme_support( 'woocommerce' );
		}
		if ( empty( $index['single-product'] ) ) {
			return;
		}
		foreach ( array( 'wc-product-gallery-zoom', 'wc-product-gallery-lightbox', 'wc-product-gallery-slider' ) as $feature ) {
			if ( ! current_theme_supports( $feature ) ) {
				add_theme_support( $feature );
			}
		}
	}

	/**
	 * Whether a body template shows a product page: a product template, or a general single template a product falls
	 * back to (it gets the product's element, notices and structured data all the same).
	 */
	private static function product_page( string $type, int $post_id ): bool {
		return $post_id && ( 'single-product' === $type || ( 'single' === $type && 'product' === get_post_type( $post_id ) ) );
	}

	/**
	 * A password-protected product shows WooCommerce's password form instead of its layout.
	 *
	 * @param bool $render Print the template.
	 */
	public function protected_product( $render, string $type, int $post_id ): bool {
		if ( self::product_page( $type, $post_id ) && post_password_required( $post_id ) ) {
			echo get_the_password_form( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core form markup.
			return false;
		}
		return (bool) $render;
	}

	/**
	 * Opens WooCommerce's single product: notices and the "before" action, then the product's element with its
	 * classes (type, stock, sale…), which WooCommerce's and extensions' styles and scripts look for. A template with
	 * a Woo Notices widget shows the notices there instead of above the product.
	 */
	public function before_body( string $type, int $post_id, int $template_id = 0 ): void {
		$product = self::product_page( $type, $post_id ) ? wc_get_product( $post_id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's global for the product page.
		$own_notices        = $template_id && self::uses_widget( $template_id, 'woo-notices' );
		$priority           = $own_notices ? has_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices' ) : false;
		if ( false !== $priority ) {
			remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', (int) $priority );
		}
		do_action( 'woocommerce_before_single_product' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
		if ( false !== $priority ) {
			add_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', (int) $priority );
		}
		echo '<div id="product-' . (int) $post_id . '" class="' . esc_attr( implode( ' ', wc_get_product_class( '', $product ) ) ) . '">';
	}

	/**
	 * Closes the product's element, adds its Product structured data (WooCommerce prints it in the footer) and runs
	 * the "after" action.
	 */
	public function after_body( string $type, int $post_id ): void {
		$product = self::product_page( $type, $post_id ) ? wc_get_product( $post_id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		echo '</div>';
		if ( isset( WC()->structured_data ) && is_callable( array( WC()->structured_data, 'generate_product_data' ) ) ) {
			WC()->structured_data->generate_product_data( $product );
		}
		do_action( 'woocommerce_after_single_product' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
	}

	/**
	 * The editor canvas loads WooCommerce's single product scripts (variation forms, the gallery), which WooCommerce
	 * itself only loads on product pages.
	 */
	public function canvas_assets(): void {
		$preview = Plugin::instance()->module( 'preview' );
		if ( ! $preview || ! is_callable( array( $preview, 'active' ) ) || ! $preview->active() ) {
			return;
		}
		foreach ( array( 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general' ) as $style ) {
			if ( wp_style_is( $style, 'registered' ) ) {
				wp_enqueue_style( $style );
			}
		}
		self::enqueue_gallery();
		foreach ( array( 'wc-add-to-cart-variation', 'wc-add-to-cart' ) as $script ) {
			if ( wp_script_is( $script, 'registered' ) ) {
				wp_enqueue_script( $script );
			}
		}
	}

	/**
	 * Type of the template open in the editor canvas ('' outside the canvas).
	 */
	private function canvas_type(): string {
		$preview = Plugin::instance()->module( 'preview' );
		if ( ! $preview || ! is_callable( array( $preview, 'active' ) ) || ! $preview->active() ) {
			return '';
		}
		$doc = Plugin::instance()->documents()->get( (int) $preview->post_id() );
		return $doc ? (string) $doc->type() : '';
	}

	/**
	 * The canvas of a product template sits in the product's element, as the template does on the site (see
	 * before_body()), so WooCommerce's ".woocommerce div.product …" styles apply while designing too.
	 *
	 * @param string $html Canvas mount point.
	 */
	public function canvas_mount( $html, $id ): string {
		if ( 'single-product' !== $this->canvas_type() ) {
			return (string) $html;
		}
		// The product the canvas previews with (the template's "Preview with" choice, else the latest product).
		$preview = Plugin::instance()->module( 'preview' );
		$post    = $preview ? (int) \Uncoder\Builder\Theme\Preview_Context::resolve( (int) $preview->post_id() )['post'] : 0;
		$product = $post && 'product' === get_post_type( $post ) ? wc_get_product( $post ) : Product_Context::sample();
		$classes = $product instanceof \WC_Product ? wc_get_product_class( '', $product ) : array( 'product', 'type-product' );
		return '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $html . '</div>';
	}

	/**
	 * Body classes of WooCommerce's pages on the canvas of product and shop templates.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function canvas_body_class( $classes ): array {
		$type = $this->canvas_type();
		if ( 'single-product' === $type ) {
			$classes = array_merge( (array) $classes, array( 'woocommerce', 'woocommerce-page', 'single-product' ) );
		} elseif ( 'product-archive' === $type ) {
			$classes = array_merge( (array) $classes, array( 'woocommerce', 'woocommerce-page', 'woocommerce-shop' ) );
		}
		return array_values( array_unique( (array) $classes ) );
	}

	/**
	 * WooCommerce's product gallery scripts (and the lightbox markup), as the site supports them. WooCommerce loads
	 * them on classic themes' product pages only; a Product Images widget elsewhere (a block theme, a landing page,
	 * the editor) asks for them.
	 */
	public static function enqueue_gallery(): void {
		$scripts = array(
			'wc-zoom'                  => 'wc-product-gallery-zoom',
			'wc-flexslider'            => 'wc-product-gallery-slider',
			'wc-photoswipe-ui-default' => 'wc-product-gallery-lightbox',
		);
		foreach ( $scripts as $handle => $feature ) {
			if ( current_theme_supports( $feature ) ) {
				self::enqueue_asset( $handle );
			}
		}
		if ( current_theme_supports( 'wc-product-gallery-lightbox' ) ) {
			self::enqueue_asset( 'photoswipe-default-skin', 'style' );
			if ( function_exists( 'woocommerce_photoswipe' ) && ! has_action( 'wp_footer', 'woocommerce_photoswipe' ) ) {
				add_action( 'wp_footer', 'woocommerce_photoswipe' );
			}
		}
		self::enqueue_asset( 'wc-single-product' );
	}

	/**
	 * Queues one of WooCommerce's scripts or stylesheets for a widget. WooCommerce registers them on
	 * wp_enqueue_scripts, and block themes render the page before that (see Theme\Shell::page()): until then the
	 * request waits for the registration. A handle WooCommerce never registers (turned off) is left out.
	 *
	 * @param string $kind "script" or "style".
	 */
	public static function enqueue_asset( string $handle, string $kind = 'script' ): void {
		$queue = static function () use ( $handle, $kind ): void {
			if ( 'style' === $kind ? wp_style_is( $handle, 'registered' ) : wp_script_is( $handle, 'registered' ) ) {
				'style' === $kind ? wp_enqueue_style( $handle ) : wp_enqueue_script( $handle );
			}
		};
		if ( did_action( 'wp_enqueue_scripts' ) || is_admin() ) {
			$queue();
			return;
		}
		add_action( 'wp_enqueue_scripts', $queue, 20 );
	}

	/**
	 * Whether a document uses a widget.
	 */
	private static function uses_widget( int $doc_id, string $widget ): bool {
		return in_array( $widget, self::doc_types( $doc_id ), true );
	}

	/**
	 * WooCommerce widgets, left out of the Insert panel while WooCommerce is not active (their elements still render
	 * and save).
	 *
	 * @return string[]
	 */
	public static function unavailable_widgets(): array {
		if ( class_exists( 'WooCommerce' ) ) {
			return array();
		}
		$names = array();
		foreach ( Plugin::instance()->elements()->all() as $name => $element ) {
			if ( 'woo' === $element->category() ) {
				$names[] = (string) $name;
			}
		}
		return $names;
	}
}
