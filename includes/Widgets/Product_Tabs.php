<?php
/**
 * Product tabs widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Document;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Parts;
use Uncoder\Builder\Woo\Product_Context;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's product tabs (Description, Additional information, Reviews and the tabs extensions add through
 * woocommerce_product_tabs) in the Tabs widget's markup: horizontal, vertical or accordion per breakpoint, with all
 * of its styling. The tabs come from the final, sorted filter result; each panel runs the tab's own callback.
 */
class Product_Tabs extends Tabs {

	use Woo_Parts;

	/** Tab controls that need rows of their own (pictures, badges, descriptions), which product tabs do not have. */
	private const UNUSED_CONTROLS = array( 'tabs', 'switch', 'icon_position', 'description_show', 'image_only' );

	private const UNUSED_SECTIONS = array( 'style_description', 'style_badge', 'style_icon' );

	/** Product Tabs rendered so far in this request (see render()). */
	private static int $rendered = 0;

	/** Products whose reviews this request has printed: id => the prefix of their ids ('' = WooCommerce's own). */
	private static array $reviews_for = array();

	public function name(): string {
		return 'product-tabs';
	}

	public function title(): string {
		return __( 'Product Tabs', 'uncoder' );
	}

	public function category(): string {
		return 'woo';
	}

	public function keywords(): array {
		return array( 'tabs', 'description', 'reviews', 'additional information', 'product', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The product tabs: description, additional information, reviews and any tab added by extensions. Horizontal, vertical or accordion.', 'uncoder' );
	}

	public function nested(): ?array {
		return null;
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	public function frontend_styles(): array {
		return array( 'tabs', 'woo', 'star-rating' );
	}

	public function frontend_scripts(): array {
		return array( 'tabs', 'woo' );
	}

	public function preset(): array {
		return array( 'layout_mobile' => 'accordion' );
	}

	protected function register_controls(): void {
		parent::register_controls();
		foreach ( self::UNUSED_CONTROLS as $key ) {
			$this->remove_control( $key );
		}
		foreach ( self::UNUSED_SECTIONS as $section ) {
			$this->remove_section( $section );
		}
		$this->update_control(
			'deep_link',
			array(
				'default'     => true,
				'description' => __( 'Opens the Reviews tab from the rating\'s review link and from links to #reviews.', 'uncoder' ),
			)
		);

		$this->start_section( 'content_product_tabs', array( 'label' => __( 'Product tabs', 'uncoder' ) ) );
		$this->add_control( 'show_description', array( 'type' => 'switch', 'label' => __( 'Description', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'show_additional_information', array( 'type' => 'switch', 'label' => __( 'Additional information', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'show_reviews', array( 'type' => 'switch', 'label' => __( 'Reviews', 'uncoder' ), 'default' => true ) );
		$this->add_control(
			'panel_headings',
			array(
				'type'        => 'switch',
				'label'       => __( 'Headings inside the panels', 'uncoder' ),
				'description' => __( 'WooCommerce repeats "Description" and "Additional information" as a heading inside their panels.', 'uncoder' ),
			)
		);
		$this->add_control(
			'extensions_note',
			array(
				'type'  => 'notice',
				'label' => __( 'Tabs added by extensions (size guides, FAQs…) appear here too. A tab shows only when the product has content for it.', 'uncoder' ),
			)
		);
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_reviews', array( 'label' => __( 'Reviews', 'uncoder' ), 'tab' => 'style' ) );
		$reviews = '{{WRAPPER}}.woocommerce :is(#reviews, .woocommerce-Reviews)';
		$this->add_group( 'reviews_typography', array( 'type' => 'typography', 'label' => __( 'Text typography', 'uncoder' ), 'selector' => $reviews . ' .description' ) );
		$this->add_control( 'reviews_author_color', array( 'type' => 'color', 'label' => __( 'Author color', 'uncoder' ), 'selectors' => array( $reviews . ' .woocommerce-review__author' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'reviews_date_color', array( 'type' => 'color', 'label' => __( 'Date color', 'uncoder' ), 'selectors' => array( $reviews . ' .woocommerce-review__published-date' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'reviews_star_color', array( 'type' => 'color', 'label' => __( 'Star color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-star-marked: {{VALUE}}' ) ) );
		$this->add_control( 'reviews_divider', array( 'type' => 'color', 'label' => __( 'Lines between reviews', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-review-line: {{VALUE}}' ) ) );
		$this->add_control( 'reviews_avatar', array( 'type' => 'switch', 'label' => __( 'Avatars', 'uncoder' ), 'default' => true ) );
		$this->end_section();
	}

	/**
	 * WooCommerce's tabs for the product, as the filter leaves them (sorted by priority).
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<string, array<string,mixed>>
	 */
	private function tabs( \WC_Product $product, array $s ): array {
		/** This filter is documented in woocommerce/templates/single-product/tabs/tabs.php */
		$tabs = (array) apply_filters( 'woocommerce_product_tabs', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
		foreach ( array( 'description', 'additional_information', 'reviews' ) as $key ) {
			if ( empty( $s[ 'show_' . $key ] ) ) {
				unset( $tabs[ $key ] );
			}
		}
		return array_filter( $tabs, static fn( $tab ) => is_array( $tab ) && isset( $tab['callback'] ) && is_callable( $tab['callback'] ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$product = $this->product( $s, $ctx );
		if ( ! $product || $product->get_id() === $ctx->doc_id ) {
			$this->placeholder( $ctx );
			return;
		}
		$no_heading = static fn() => '';
		if ( empty( $s['panel_headings'] ) ) {
			add_filter( 'woocommerce_product_description_heading', $no_heading, 20 );
			add_filter( 'woocommerce_product_additional_information_heading', $no_heading, 20 );
		}
		$no_avatar = static fn() => false;
		if ( empty( $s['reviews_avatar'] ) ) {
			add_filter( 'option_show_avatars', $no_avatar, 20 );
		}
		try {
			$html = Product_Context::capture(
				$product,
				function () use ( $s, $ctx, $product ) {
					$tabs = $this->tabs( $product, $s );
					if ( ! $tabs ) {
						$this->placeholder( $ctx, __( 'This product has no description, attributes or reviews to show in tabs.', 'uncoder' ) );
						return;
					}
					// The review form's star picker and its "choose a rating" check are WooCommerce's single product
					// script, which WooCommerce itself loads on product pages only.
					if ( isset( $tabs['reviews'] ) && ! $ctx->editor ) {
						\Uncoder\Builder\Woo\Woo::enqueue_asset( 'wc-single-product' );
					}
					// WooCommerce's ids ("tab-title-reviews", which review links and deep links use) go to the first
					// Product Tabs of the page; another one (a copy for phones) gets ids of its own.
					$prefix = self::$rendered++ ? sanitize_html_class( $ctx->element_id ) . '-' : '';
					$rows   = array();
					foreach ( $tabs as $key => $tab ) {
						$key    = sanitize_key( (string) $key );
						$rows[] = array(
							'title'  => wp_kses_post( (string) apply_filters( 'woocommerce_product_' . $key . '_tab_title', $tab['title'] ?? $key, $key ) ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WooCommerce hook.
							'anchor' => $prefix . 'tab-title-' . $key,
							'_id'    => $key,
							'key'    => $key,
							'tab'    => $tab,
						);
					}
					// Titles come from WooCommerce, so they are not editable on the canvas (no inline-edit attributes).
					$plain         = clone $ctx;
					$plain->editor = false;
					$this->render_tabs(
						$s,
						$plain,
						$rows,
						function ( int $i ) use ( $rows, $ctx, $product ): string {
							// WooCommerce's panel classes, which theme and site CSS often target (not "panel wc-tab":
							// WooCommerce's tab script would hide those).
							return '<div class="uncoder-product-tabs__panel woocommerce-Tabs-panel woocommerce-Tabs-panel--' . esc_attr( $rows[ $i ]['key'] ) . '">' . $this->panel( $rows[ $i ]['key'], $rows[ $i ]['tab'], $ctx, $product ) . '</div>';
						}
					);
				}
			);
		} finally {
			remove_filter( 'woocommerce_product_description_heading', $no_heading, 20 );
			remove_filter( 'woocommerce_product_additional_information_heading', $no_heading, 20 );
			remove_filter( 'option_show_avatars', $no_avatar, 20 );
		}
		if ( '' === trim( $html ) ) {
			return;
		}
		// The Tabs markup's root takes the WooCommerce classes (merged with the widget's own by the renderer).
		echo preg_replace( '/^<div class="uncoder-tabs/', '<div class="' . esc_attr( $this->root_class() ) . ' uncoder-tabs', ltrim( $html ), 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the Tabs markup and WooCommerce's tab templates.
	}

	/**
	 * A tab's content.
	 *
	 * @param array<string,mixed> $tab Tab definition.
	 */
	private function panel( string $key, array $tab, Render_Context $ctx, \WC_Product $product ): string {
		if ( 'reviews' === $key ) {
			return $this->reviews_panel( $tab, $ctx, $product );
		}
		return $this->tab_html( $key, $tab, $ctx );
	}

	/**
	 * The Reviews tab. A page holds a product's reviews once (one form, with the ids WooCommerce's review script,
	 * styles and extensions look for: "reviews", "respond", "rating"…). Another Product Tabs of the same product (a
	 * copy for phones) gets an empty slot the woo script moves the reviews into while that copy is the one on screen.
	 * The reviews of a second product on the page keep their ids apart with a prefix (the woo script gives their form
	 * its star picker). On a page view only: the editor, previews and REST renders show each widget's reviews.
	 *
	 * @param array<string,mixed> $tab Tab definition.
	 */
	private function reviews_panel( array $tab, Render_Context $ctx, \WC_Product $product ): string {
		$page_view = ! $ctx->editor && ! Document::$static_render && did_action( 'template_redirect' ) && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! wp_doing_ajax();
		if ( ! $page_view ) {
			return $this->tab_html( 'reviews', $tab, $ctx );
		}
		$id = $product->get_id();
		if ( isset( self::$reviews_for[ $id ] ) ) {
			return self::reviews_slot( $id, self::$reviews_for[ $id ], '' );
		}
		$prefix                   = self::$reviews_for ? sanitize_html_class( $ctx->element_id ) . '-' : '';
		self::$reviews_for[ $id ] = $prefix;
		$html                     = $this->tab_html( 'reviews', $tab, $ctx );
		if ( '' !== $prefix ) {
			$html = self::prefix_ids( $html, $prefix );
		}
		return self::reviews_slot( $id, $prefix, '<div class="uncoder-product-tabs__reviews" data-uncoder-reviews="' . (int) $id . '">' . $html . '</div>' );
	}

	/**
	 * A place for a product's reviews block, with a link to the block for when it sits in another copy on screen.
	 */
	private static function reviews_slot( int $product_id, string $prefix, string $block ): string {
		return '<div class="uncoder-product-tabs__reviews-slot" data-uncoder-reviews-slot="' . (int) $product_id . '">'
			. '<p class="uncoder-product-tabs__reviews-link"><a href="#' . esc_attr( $prefix . 'reviews' ) . '">' . esc_html__( 'Read the reviews', 'uncoder' ) . '</a></p>'
			. $block . '</div>';
	}

	/**
	 * Every id in the markup with a prefix, and the attributes that point at them (for, aria-*, "#" links).
	 */
	private static function prefix_ids( string $html, string $prefix ): string {
		if ( ! preg_match_all( '/\sid=(["\'])([^"\']+)\1/', $html, $found ) ) {
			return $html;
		}
		$map = array();
		foreach ( array_unique( $found[2] ) as $id ) {
			$map[ $id ] = $prefix . $id;
		}
		return (string) preg_replace_callback(
			'/\s(id|for|aria-labelledby|aria-describedby|aria-controls|href)=(["\'])([^"\']*)\2/',
			static function ( array $m ) use ( $map ): string {
				if ( 'href' === $m[1] ) {
					$target = 0 === strpos( $m[3], '#' ) ? substr( $m[3], 1 ) : '';
					return '' !== $target && isset( $map[ $target ] ) ? ' href=' . $m[2] . '#' . $map[ $target ] . $m[2] : $m[0];
				}
				$ids = preg_split( '/\s+/', trim( $m[3] ) );
				return ' ' . $m[1] . '=' . $m[2] . implode( ' ', array_map( static fn( $id ) => $map[ $id ] ?? $id, (array) $ids ) ) . $m[2];
			},
			$html
		);
	}

	/**
	 * Output of a tab's WooCommerce callback. Reviews render outside a single product page too (in the editor, on a
	 * landing page), where WordPress would skip the comments template, and always with WooCommerce's reviews template
	 * (WooCommerce supplies it only to themes that declare WooCommerce support; WordPress would fall back to its
	 * deprecated default comments template).
	 *
	 * @param array<string,mixed> $tab Tab definition.
	 */
	private function tab_html( string $key, array $tab, Render_Context $ctx ): string {
		global $withcomments;
		$previous     = $withcomments;
		$withcomments = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below; lets comments_template() run outside is_singular().
		$reviews      = static function ( $template ) {
			$own = function_exists( 'wc_locate_template' ) ? wc_locate_template( 'single-product-reviews.php' ) : '';
			return 'product' === get_post_type() && $own && file_exists( $own ) ? $own : $template;
		};
		if ( 'reviews' === $key ) {
			add_filter( 'comments_template', $reviews, 99 );
		}
		$level = ob_get_level();
		ob_start();
		try {
			call_user_func( $tab['callback'], $key, $tab );
			$html = (string) ob_get_clean();
		} finally {
			remove_filter( 'comments_template', $reviews, 99 );
			$withcomments = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
		}
		if ( 'reviews' === $key && $ctx->editor ) {
			// No review form submissions from the editor canvas.
			$html = str_replace( '<form ', '<form inert ', $html );
		}
		return $html;
	}
}
