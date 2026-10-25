<?php
/**
 * Mini cart widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Document;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Widgets\Support\Woo_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * The header cart: an icon with the number of items (and the subtotal) that opens a drawer with WooCommerce's mini
 * cart, or links to the cart page. Count, subtotal and drawer update through WooCommerce's cart fragments, so they
 * stay right on cached pages and after adding to the cart without reloading. On the cart and checkout pages the icon
 * simply links to the cart.
 */
class Mini_Cart extends Woo_Widget {

	public function name(): string {
		return 'mini-cart';
	}

	public function title(): string {
		return __( 'Mini Cart', 'uncoder' );
	}

	public function icon(): string {
		return 'shopping-bag';
	}

	public function keywords(): array {
		return array( 'mini cart', 'cart', 'header cart', 'basket', 'bag', 'cart icon', 'drawer', 'woocommerce' );
	}

	public function description(): string {
		return __( 'A cart icon for the header with the number of items and the subtotal. Opens a cart drawer or goes to the cart page.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'woo' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Mini cart', 'uncoder' ) ) );
		$this->woo_notice();
		$this->add_control( 'icon', array( 'type' => 'icon', 'label' => __( 'Icon', 'uncoder' ), 'default' => array( 'library' => 'lucide', 'value' => 'shopping-bag' ) ) );
		$this->add_control( 'show_count', array( 'type' => 'switch', 'label' => __( 'Number of items', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'hide_empty', array( 'type' => 'switch', 'label' => __( 'Hide the number when the cart is empty', 'uncoder' ), 'default' => true, 'condition' => array( 'show_count' => true ) ) );
		$this->add_control( 'show_subtotal', array( 'type' => 'switch', 'label' => __( 'Subtotal', 'uncoder' ) ) );
		$this->add_control(
			'mode',
			array(
				'type'    => 'choose',
				'label'   => __( 'On click', 'uncoder' ),
				'default' => 'drawer',
				'options' => array(
					'drawer' => array( 'label' => __( 'Open the cart drawer', 'uncoder' ), 'icon' => 'panel-right-open' ),
					'link'   => array( 'label' => __( 'Go to the cart page', 'uncoder' ), 'icon' => 'external-link' ),
				),
			)
		);
		$this->add_control(
			'open_on_add',
			array(
				'type'        => 'switch',
				'label'       => __( 'Open after adding to the cart', 'uncoder' ),
				'default'     => true,
				'description' => __( 'When a product is added without reloading the page.', 'uncoder' ),
				'condition'   => array( 'mode' => 'drawer' ),
			)
		);
		$this->add_control(
			'side',
			array(
				'type'      => 'choose',
				'label'     => __( 'Drawer side', 'uncoder' ),
				'default'   => 'right',
				'options'   => array(
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'panel-left' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'panel-right' ),
				),
				'condition' => array( 'mode' => 'drawer' ),
			)
		);
		$this->add_control( 'drawer_title', array( 'type' => 'text', 'label' => __( 'Drawer title', 'uncoder' ), 'placeholder' => __( 'Your cart', 'uncoder' ), 'condition' => array( 'mode' => 'drawer' ) ) );
		$this->add_control(
			'preview_open',
			array(
				'type'        => 'switch',
				'label'       => __( 'Show the drawer while editing', 'uncoder' ),
				'description' => __( 'Editor only, with sample products, to style the drawer.', 'uncoder' ),
				'condition'   => array( 'mode' => 'drawer' ),
			)
		);
		$this->end_section();

		$toggle = '{{WRAPPER}} .uncoder-mini-cart__toggle';
		$this->start_section( 'style_toggle', array( 'label' => __( 'Icon', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-mini-cart-icon: {{VALUE}}' ),
			)
		);
		$this->add_control( 'icon_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( $toggle => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'icon_hover_color', array( 'type' => 'color', 'label' => __( 'Hover color', 'uncoder' ), 'selectors' => array( $toggle . ':is(:hover, :focus-visible)' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'count_background', array( 'type' => 'color', 'label' => __( 'Number background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-mini-cart__count' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'count_color', array( 'type' => 'color', 'label' => __( 'Number color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-mini-cart__count' => 'color: {{VALUE}}' ) ) );
		$this->add_group( 'subtotal_typography', array( 'type' => 'typography', 'label' => __( 'Subtotal typography', 'uncoder' ), 'condition' => array( 'show_subtotal' => true ), 'selector' => '{{WRAPPER}} .uncoder-mini-cart__subtotal' ) );
		$this->end_section();

		$panel = '{{WRAPPER}} .uncoder-mini-cart__panel';
		$this->start_section( 'style_drawer', array( 'label' => __( 'Drawer', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'mode' => 'drawer' ) ) );
		$this->add_responsive_control(
			'drawer_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px', 'vw', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-mini-cart-width: {{VALUE}}' ),
			)
		);
		$this->add_control( 'drawer_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $panel => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'drawer_text', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $panel => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'overlay_color', array( 'type' => 'color', 'label' => __( 'Page overlay', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-mini-cart__overlay' => 'background-color: {{VALUE}}' ) ) );
		$this->add_group( 'drawer_title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-mini-cart__title' ) );
		$this->add_group( 'drawer_item_typography', array( 'type' => 'typography', 'label' => __( 'Product typography', 'uncoder' ), 'selector' => $panel . ' .woocommerce-mini-cart-item > a:not(.remove)' ) );
		$this->add_responsive_control(
			'drawer_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-mini-cart-pad: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$drawer = 'drawer' === ( $s['mode'] ?? 'drawer' ) && ! $this->on_cart_pages( $ctx );
		return array(
			'data-settings' => $this->json_attr(
				array(
					'miniCart'  => $drawer ? 'drawer' : 'link',
					'openOnAdd' => $drawer && ! empty( $s['open_on_add'] ),
				)
			),
		);
	}

	/**
	 * The cart and checkout pages: the icon links to the cart there.
	 */
	private function on_cart_pages( Render_Context $ctx ): bool {
		return ! $ctx->editor && function_exists( 'is_cart' ) && ( is_cart() || is_checkout() );
	}

	/**
	 * The item count badge (also a cart fragment).
	 */
	public static function count_html( int $count ): string {
		return '<span class="uncoder-mini-cart__count" data-count="' . (int) $count . '" aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span>';
	}

	/**
	 * The subtotal (also a cart fragment).
	 *
	 * @param string $amount Formatted price HTML.
	 */
	public static function subtotal_html( string $amount ): string {
		return '<span class="uncoder-mini-cart__subtotal">' . wp_kses_post( $amount ) . '</span>';
	}

	/**
	 * The toggle's accessible name for the script to copy after cart updates (a cart fragment, so it comes in the
	 * language's own plural form, with or without the count badge).
	 */
	public static function label_html( int $count ): string {
		return '<span class="uncoder-mini-cart__label" data-label="' . esc_attr( self::label( $count ) ) . '" hidden></span>';
	}

	/**
	 * The accessible name of the toggle ("Cart, 2 items").
	 */
	public static function label( int $count ): string {
		/* translators: %s: number of items in the cart. */
		return sprintf( _n( 'Cart, %s item', 'Cart, %s items', $count, 'uncoder' ), number_format_i18n( $count ) );
	}

	/**
	 * Count, subtotal and label of the visitor's cart (zero / empty outside a real visit).
	 *
	 * @return array{0:int,1:string}
	 */
	private function totals( Render_Context $ctx ): array {
		if ( $ctx->editor ) {
			return array( 2, wc_price( 34 ) );
		}
		if ( Document::$static_render || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! function_exists( 'WC' ) || null === WC()->cart ) {
			return array( 0, wc_price( 0 ) );
		}
		return array( (int) WC()->cart->get_cart_contents_count(), (string) WC()->cart->get_cart_subtotal() );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		if ( ! self::active() ) {
			$this->placeholder( $ctx );
			return;
		}
		list( $count, $subtotal ) = $this->totals( $ctx );
		$drawer                   = 'drawer' === ( $s['mode'] ?? 'drawer' ) && ! $this->on_cart_pages( $ctx );
		$uid                      = 'uncoder-mini-cart-' . sanitize_html_class( $ctx->element_id );
		$icon                     = $this->render_icon( $this->has_icon( $s['icon'] ?? null ) ? $s['icon'] : 'shopping-bag', array( 'class' => 'uncoder-mini-cart__svg' ) );
		if ( ! $ctx->editor && ! Document::$static_render ) {
			// WooCommerce loads its cart fragments script only where a cart widget asks for it.
			\Uncoder\Builder\Woo\Woo::enqueue_asset( 'wc-cart-fragments' );
		}

		$toggle = '<a' . Utils::attrs(
			array(
				'class'         => 'uncoder-mini-cart__toggle',
				'href'          => wc_get_cart_url(),
				'aria-label'    => self::label( $count ),
				'aria-haspopup' => $drawer ? 'dialog' : null,
				'aria-expanded' => $drawer ? 'false' : null,
				'aria-controls' => $drawer ? $uid : null,
			)
		) . '><span class="uncoder-mini-cart__icon">' . $icon . ( ! empty( $s['show_count'] ) ? self::count_html( $count ) : '' ) . '</span>' . self::label_html( $count )
			. ( ! empty( $s['show_subtotal'] ) ? self::subtotal_html( $subtotal ) : '' ) . '</a>';

		$panel = '';
		if ( $drawer ) {
			$open  = $ctx->editor && ! empty( $s['preview_open'] );
			$title = trim( (string) ( $s['drawer_title'] ?? '' ) );
			$panel = '<div' . Utils::attrs(
				array(
					'class'      => 'uncoder-mini-cart__drawer uncoder-mini-cart__drawer--' . ( 'left' === ( $s['side'] ?? '' ) ? 'left' : 'right' ) . ( $open ? ' is-open is-preview' : '' ),
					'id'         => $uid,
					'role'       => 'dialog',
					'aria-modal' => 'true',
					'aria-label' => '' !== $title ? $title : __( 'Your cart', 'uncoder' ),
					'hidden'     => ! $open,
				)
			) . '><div class="uncoder-mini-cart__overlay" data-uncoder-close></div><div class="uncoder-mini-cart__panel" tabindex="-1">'
				. '<div class="uncoder-mini-cart__head"><span class="uncoder-mini-cart__title">' . esc_html( '' !== $title ? $title : __( 'Your cart', 'uncoder' ) ) . '</span>'
				. '<button type="button" class="uncoder-mini-cart__close" data-uncoder-close aria-label="' . esc_attr__( 'Close the cart', 'uncoder' ) . '">' . $this->render_icon( 'x' ) . '</button></div>'
				. '<div class="widget_shopping_cart_content">' . ( $ctx->editor ? $this->sample_items() : $this->items() ) . '</div></div></div>';
		}
		$mods = ( ! empty( $s['hide_empty'] ) ? 'uncoder-mini-cart--hide-empty' : '' ) . ( $drawer ? ' uncoder-mini-cart--drawer' : '' );
		echo '<div class="' . esc_attr( $this->root_class( $mods ) ) . '">' . $toggle . $panel . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts, WooCommerce's mini cart template.
	}

	/**
	 * WooCommerce's mini cart for the visitor (on cached pages the cart fragments script refreshes it).
	 */
	private function items(): string {
		if ( Document::$static_render || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! function_exists( 'woocommerce_mini_cart' ) || null === WC()->cart ) {
			return '';
		}
		ob_start();
		woocommerce_mini_cart();
		return (string) ob_get_clean();
	}

	/**
	 * Mini cart markup with sample products, for the editor.
	 */
	private function sample_items(): string {
		$ids   = wc_get_products( array( 'status' => 'publish', 'limit' => 2, 'type' => array( 'simple' ), 'return' => 'ids' ) );
		$items = '';
		$total = 0.0;
		foreach ( array_filter( array_map( 'wc_get_product', (array) $ids ) ) as $product ) {
			$price  = (float) wc_get_price_to_display( $product );
			$total += $price;
			$items .= '<li class="woocommerce-mini-cart-item mini_cart_item"><a href="#" class="remove remove_from_cart_button" aria-label="' . esc_attr__( 'Remove this item', 'uncoder' ) . '">&times;</a>'
				. '<a href="' . esc_url( (string) $product->get_permalink() ) . '">' . $product->get_image( 'woocommerce_thumbnail' ) . esc_html( $product->get_name() ) . '</a>'
				. '<span class="quantity">1 &times; ' . wc_price( $price ) . '</span></li>';
		}
		if ( '' === $items ) {
			return '<p class="woocommerce-mini-cart__empty-message">' . esc_html__( 'No products in the cart.', 'uncoder' ) . '</p>';
		}
		return '<ul class="woocommerce-mini-cart cart_list product_list_widget">' . $items . '</ul>'
			. '<p class="woocommerce-mini-cart__total total"><strong>' . esc_html__( 'Subtotal:', 'uncoder' ) . '</strong> ' . wc_price( $total ) . '</p>'
			. '<p class="woocommerce-mini-cart__buttons buttons"><a href="#" class="button wc-forward">' . esc_html__( 'View cart', 'uncoder' ) . '</a><a href="#" class="button checkout wc-forward">' . esc_html__( 'Checkout', 'uncoder' ) . '</a></p>';
	}
}
