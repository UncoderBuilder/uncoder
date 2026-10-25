<?php
/**
 * Cart widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Page_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's cart page ([woocommerce_cart]): the cart table with quantities, coupon and update, and the totals
 * with the shipping calculator and "Proceed to checkout", side by side or stacked.
 */
class Cart extends Woo_Page_Widget {

	private const TABLE = self::W . ' table.shop_table';

	public function name(): string {
		return 'cart';
	}

	public function title(): string {
		return __( 'Cart', 'uncoder' );
	}

	public function icon(): string {
		return 'shopping-basket';
	}

	public function keywords(): array {
		return array( 'cart', 'basket', 'bag', 'totals', 'coupon', 'checkout', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The shopping cart: products with quantities, coupon, totals and the button to the checkout. Place it on the cart page.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Cart', 'uncoder' ) ) );
		$this->woo_notice();
		$this->add_responsive_control(
			'layout',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Totals', 'uncoder' ),
				'options'              => array(
					'side'    => array( 'label' => __( 'Beside the products', 'uncoder' ), 'icon' => 'columns-2' ),
					'stacked' => array( 'label' => __( 'Below the products', 'uncoder' ), 'icon' => 'rows-2' ),
				),
				'selectors_dictionary' => array(
					'side'    => '--uncoder-cart-cols: minmax(0, 1fr) var(--uncoder-cart-totals); --uncoder-cart-totals-col: 2; --uncoder-cart-totals-span: 2',
					'stacked' => '--uncoder-cart-cols: minmax(0, 1fr); --uncoder-cart-totals-col: 1; --uncoder-cart-totals-span: 1',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'description'          => __( 'Empty: beside on desktops, below on tablets and phones.', 'uncoder' ),
			)
		);
		$this->add_responsive_control(
			'totals_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Totals width', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-cart-totals: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-cart-gap: {{VALUE}}' ),
			)
		);
		$this->add_control( 'quantity_buttons', array( 'type' => 'switch', 'label' => __( '− / + buttons', 'uncoder' ), 'default' => true ) );
		$this->add_control(
			'cross_sells',
			array(
				'type'        => 'switch',
				'label'       => __( 'Cross-sells', 'uncoder' ),
				'description' => __( 'WooCommerce\'s "You may be interested in…" products under the cart. A Products widget showing "Cross-sells of the cart" gives more control.', 'uncoder' ),
			)
		);
		$this->end_section();

		$table = self::TABLE;
		$this->start_section( 'style_table', array( 'label' => __( 'Products table', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'head_typography', array( 'type' => 'typography', 'label' => __( 'Header typography', 'uncoder' ), 'selector' => $table . ' thead th' ) );
		$this->add_control( 'head_color', array( 'type' => 'color', 'label' => __( 'Header color', 'uncoder' ), 'selectors' => array( $table . ' thead th' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'head_background', array( 'type' => 'color', 'label' => __( 'Header background', 'uncoder' ), 'selectors' => array( $table . ' thead th' => 'background-color: {{VALUE}}' ) ) );
		$this->add_group( 'cell_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $table . ' td' ) );
		$this->add_control( 'cell_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $table . ' td' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'link_color', array( 'type' => 'color', 'label' => __( 'Product name color', 'uncoder' ), 'selectors' => array( $table . ' td.product-name a' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'line_color', array( 'type' => 'color', 'label' => __( 'Lines', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-cart-line: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'cell_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Cell padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $table . ' :is(th, td)' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'thumb_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Image size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-cart-thumb: {{VALUE}}' ),
			)
		);
		$this->add_control( 'remove_color', array( 'type' => 'color', 'label' => __( 'Remove (×) color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-cart-remove: {{VALUE}}' ) ) );
		$this->end_section();

		$totals = self::W . ' .cart-collaterals .cart_totals';
		$this->start_section( 'style_totals', array( 'label' => __( 'Totals', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control( 'totals_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-cart-totals-bg: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'totals_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $totals => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'totals_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => $totals ) );
		$this->add_responsive_control(
			'totals_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $totals => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'totals_heading_typography', array( 'type' => 'typography', 'label' => __( 'Heading typography', 'uncoder' ), 'selector' => $totals . ' > h2' ) );
		$this->add_control( 'totals_heading_color', array( 'type' => 'color', 'label' => __( 'Heading color', 'uncoder' ), 'selectors' => array( $totals . ' > h2' => 'color: {{VALUE}}' ) ) );
		$this->add_group( 'total_typography', array( 'type' => 'typography', 'label' => __( 'Total typography', 'uncoder' ), 'selector' => $totals . ' .order-total :is(th, td)' ) );
		$this->end_section();

		$checkout = self::W . ' .wc-proceed-to-checkout a.checkout-button';
		$this->start_section( 'style_button', array( 'label' => __( 'Checkout button', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'button_note',
			array(
				'type'  => 'notice',
				'label' => __( 'The cart\'s buttons follow Design System → Buttons. Change the checkout button here for this widget only.', 'uncoder' ),
			)
		);
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $checkout ) );
		$this->add_control( 'button_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $checkout => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $checkout => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_background', array( 'type' => 'color', 'label' => __( 'Hover background', 'uncoder' ), 'selectors' => array( $checkout . ':is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $checkout => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function modifiers( array $s ): string {
		return empty( $s['cross_sells'] ) ? 'uncoder-cart--no-cross-sells' : '';
	}

	protected function live( array $s ): string {
		$priority = empty( $s['cross_sells'] ) ? has_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' ) : false;
		if ( false !== $priority ) {
			remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display', (int) $priority );
		}
		try {
			return (string) \WC_Shortcodes::cart();
		} finally {
			if ( false !== $priority ) {
				add_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display', (int) $priority );
			}
		}
	}

	protected function sample( array $s, Render_Context $ctx ): string {
		$products = self::sample_products( 2 );
		if ( ! $products ) {
			return '<div class="woocommerce"><div class="wc-empty-cart-message"><div class="cart-empty woocommerce-info" role="status">' . esc_html__( 'Your cart is currently empty.', 'uncoder' ) . '</div></div></div>';
		}
		$rows     = '';
		$subtotal = 0.0;
		foreach ( $products as $i => $product ) {
			$qty       = 0 === $i ? 2 : 1;
			$price     = (float) wc_get_price_to_display( $product );
			$subtotal += $price * $qty;
			$name      = esc_html( $product->get_name() );
			$link      = esc_url( (string) $product->get_permalink() );
			$rows     .= '<tr class="woocommerce-cart-form__cart-item cart_item">'
				/* translators: %s: product name. */
				. '<td class="product-remove"><a role="button" href="#" class="remove" aria-label="' . esc_attr( sprintf( __( 'Remove %s from cart', 'uncoder' ), $product->get_name() ) ) . '">&times;</a></td>'
				. '<td class="product-thumbnail"><a href="' . $link . '">' . $product->get_image( 'woocommerce_thumbnail' ) . '</a></td>'
				. '<td role="rowheader" class="product-name" data-title="' . esc_attr__( 'Product', 'uncoder' ) . '"><a href="' . $link . '">' . $name . '</a></td>'
				. '<td class="product-price" data-title="' . esc_attr__( 'Price', 'uncoder' ) . '">' . self::amount( $price ) . '</td>'
				. '<td class="product-quantity" data-title="' . esc_attr__( 'Quantity', 'uncoder' ) . '">'
				. woocommerce_quantity_input(
					array(
						'input_name'   => 'cart[sample-' . $i . '][qty]',
						'input_value'  => $qty,
						'min_value'    => 0,
						'max_value'    => -1,
						'product_name' => $product->get_name(),
					),
					$product,
					false
				) . '</td>'
				. '<td class="product-subtotal" data-title="' . esc_attr__( 'Subtotal', 'uncoder' ) . '">' . self::amount( $price * $qty ) . '</td></tr>';
		}
		$coupon = wc_coupons_enabled()
			? '<div class="coupon"><label for="coupon_code" class="screen-reader-text">' . esc_html__( 'Coupon:', 'uncoder' ) . '</label> <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="' . esc_attr__( 'Coupon code', 'uncoder' ) . '"> <button type="button" class="button" name="apply_coupon">' . esc_html__( 'Apply coupon', 'uncoder' ) . '</button></div>'
			: '';
		$head = '<thead><tr><th scope="col" class="product-remove"><span class="screen-reader-text">' . esc_html__( 'Remove item', 'uncoder' ) . '</span></th><th scope="col" class="product-thumbnail"><span class="screen-reader-text">' . esc_html__( 'Thumbnail image', 'uncoder' ) . '</span></th>'
			. '<th scope="col" class="product-name">' . esc_html__( 'Product', 'uncoder' ) . '</th><th scope="col" class="product-price">' . esc_html__( 'Price', 'uncoder' ) . '</th><th scope="col" class="product-quantity">' . esc_html__( 'Quantity', 'uncoder' ) . '</th><th scope="col" class="product-subtotal">' . esc_html__( 'Subtotal', 'uncoder' ) . '</th></tr></thead>';
		$form = '<form class="woocommerce-cart-form" action="#" method="post" inert><table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">' . $head . '<tbody>' . $rows
			. '<tr><td colspan="6" class="actions">' . $coupon . '<button type="button" class="button" name="update_cart" disabled>' . esc_html__( 'Update cart', 'uncoder' ) . '</button></td></tr></tbody></table></form>';
		$totals = '<div class="cart-collaterals"><div class="cart_totals"><h2>' . esc_html__( 'Cart totals', 'uncoder' ) . '</h2><table cellspacing="0" class="shop_table shop_table_responsive"><tbody>'
			. '<tr class="cart-subtotal"><th>' . esc_html__( 'Subtotal', 'uncoder' ) . '</th><td data-title="' . esc_attr__( 'Subtotal', 'uncoder' ) . '">' . self::amount( $subtotal ) . '</td></tr>'
			. '<tr class="woocommerce-shipping-totals shipping"><th>' . esc_html__( 'Shipping', 'uncoder' ) . '</th><td data-title="' . esc_attr__( 'Shipping', 'uncoder' ) . '">' . esc_html__( 'Free shipping', 'uncoder' ) . '</td></tr>'
			. '<tr class="order-total"><th>' . esc_html__( 'Total', 'uncoder' ) . '</th><td data-title="' . esc_attr__( 'Total', 'uncoder' ) . '"><strong>' . self::amount( $subtotal ) . '</strong></td></tr>'
			. '</tbody></table><div class="wc-proceed-to-checkout"><a href="#" class="checkout-button button alt wc-forward">' . esc_html__( 'Proceed to checkout', 'uncoder' ) . '</a></div></div></div>';
		return '<div class="woocommerce"><div class="woocommerce-notices-wrapper"></div>' . $form . $totals . '</div>';
	}
}
