<?php
/**
 * Checkout widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Page_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's checkout ([woocommerce_checkout]): billing and shipping details, the order review and the payment
 * methods with "Place order", in two columns or one. Also the order-received ("thank you") and order-pay pages,
 * which WooCommerce shows on the checkout page.
 */
class Checkout extends Woo_Page_Widget {

	private const FORM = self::W . ' form.checkout';

	public function name(): string {
		return 'checkout';
	}

	public function title(): string {
		return __( 'Checkout', 'uncoder' );
	}

	public function icon(): string {
		return 'credit-card';
	}

	public function keywords(): array {
		return array( 'checkout', 'payment', 'billing', 'shipping', 'order', 'place order', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The checkout: billing and shipping details, the order summary and payment. Place it on the checkout page.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Checkout', 'uncoder' ) ) );
		$this->woo_notice();
		$this->add_responsive_control(
			'layout',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Order summary', 'uncoder' ),
				'options'              => array(
					'side'    => array( 'label' => __( 'Beside the details', 'uncoder' ), 'icon' => 'columns-2' ),
					'stacked' => array( 'label' => __( 'Below the details', 'uncoder' ), 'icon' => 'rows-2' ),
				),
				'selectors_dictionary' => array(
					'side'    => '--uncoder-checkout-cols: minmax(0, 1fr) var(--uncoder-checkout-side); --uncoder-checkout-side-col: 2; --uncoder-checkout-details-row: 2 / span 2; --uncoder-checkout-heading-row: 2; --uncoder-checkout-review-row: 3; --uncoder-checkout-details-space: 0px',
					'stacked' => '--uncoder-checkout-cols: minmax(0, 1fr); --uncoder-checkout-side-col: 1; --uncoder-checkout-details-row: auto; --uncoder-checkout-heading-row: auto; --uncoder-checkout-review-row: auto; --uncoder-checkout-details-space: var(--uncoder-checkout-gap)',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'description'          => __( 'Empty: beside on desktops, below on tablets and phones.', 'uncoder' ),
			)
		);
		$this->add_responsive_control(
			'side_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Summary width', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-checkout-side: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-checkout-gap: {{VALUE}}' ),
			)
		);
		$this->add_control( 'sticky', array( 'type' => 'switch', 'label' => __( 'Summary stays in view while scrolling', 'uncoder' ), 'default' => true ) );
		$this->end_section();

		$this->start_section( 'style_headings', array( 'label' => __( 'Headings and labels', 'uncoder' ), 'tab' => 'style' ) );
		$headings = self::W . ' :is(.woocommerce-billing-fields > h3, .woocommerce-additional-fields > h3, #ship-to-different-address, #order_review_heading)';
		$this->add_group( 'heading_typography', array( 'type' => 'typography', 'label' => __( 'Heading typography', 'uncoder' ), 'selector' => $headings ) );
		$this->add_control( 'heading_color', array( 'type' => 'color', 'label' => __( 'Heading color', 'uncoder' ), 'selectors' => array( $headings => 'color: {{VALUE}}' ) ) );
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Label typography', 'uncoder' ), 'selector' => self::FORM . ' .form-row label' ) );
		$this->add_control( 'label_color', array( 'type' => 'color', 'label' => __( 'Label color', 'uncoder' ), 'selectors' => array( self::FORM . ' .form-row label' => 'color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'field_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between fields', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-checkout-field-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'fields_note',
			array(
				'type'  => 'notice',
				'label' => __( 'Fields and buttons follow the Design System\'s form and button styles (Styles → Buttons).', 'uncoder' ),
			)
		);
		$this->end_section();

		$review = self::W . ' #order_review';
		$this->start_section( 'style_summary', array( 'label' => __( 'Order summary', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control( 'summary_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-checkout-summary-bg: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'summary_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $review => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'summary_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => $review ) );
		$this->add_responsive_control(
			'summary_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $review => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'summary_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $review . ' table.shop_table :is(th, td)' ) );
		$this->add_control( 'summary_line', array( 'type' => 'color', 'label' => __( 'Lines', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-checkout-line: {{VALUE}}' ) ) );
		$this->add_control( 'payment_heading', array( 'type' => 'heading', 'label' => __( 'Payment methods', 'uncoder' ) ) );
		$this->add_control( 'payment_box_background', array( 'type' => 'color', 'label' => __( 'Description background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-payment-box-bg: {{VALUE}}' ) ) );
		$this->add_group( 'payment_label_typography', array( 'type' => 'typography', 'label' => __( 'Method typography', 'uncoder' ), 'selector' => self::W . ' #payment ul.payment_methods li > label' ) );
		$this->end_section();

		$button = self::W . ' #payment #place_order';
		$this->start_section( 'style_button', array( 'label' => __( 'Place order button', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $button ) );
		$this->add_control( 'button_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $button => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $button => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_background', array( 'type' => 'color', 'label' => __( 'Hover background', 'uncoder' ), 'selectors' => array( $button . ':is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $button => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function modifiers( array $s ): string {
		return ! empty( $s['sticky'] ) ? 'uncoder-checkout--sticky' : '';
	}

	protected function live( array $s ): string {
		return (string) \WC_Shortcodes::checkout( array() );
	}

	protected function sample( array $s, Render_Context $ctx ): string {
		$products = self::sample_products( 2 );
		$fields   = '';
		$country  = WC()->countries ? WC()->countries->get_base_country() : 'US';
		$address  = WC()->countries ? WC()->countries->get_address_fields( $country, 'billing_' ) : array();
		foreach ( $address as $key => $field ) {
			$field['return'] = true;
			$fields         .= woocommerce_form_field( $key, $field, 'billing_country' === $key ? $country : '' );
		}
		$notes = woocommerce_form_field(
			'order_comments',
			array(
				'type'        => 'textarea',
				'class'       => array( 'notes' ),
				'label'       => __( 'Order notes', 'uncoder' ),
				'placeholder' => __( 'Notes about your order, e.g. special notes for delivery.', 'uncoder' ),
				'return'      => true,
			)
		);

		$rows  = '';
		$total = 0.0;
		foreach ( $products as $i => $product ) {
			$qty    = 0 === $i ? 2 : 1;
			$price  = (float) wc_get_price_to_display( $product ) * $qty;
			$total += $price;
			$rows  .= '<tr class="cart_item"><td class="product-name">' . esc_html( $product->get_name() ) . '&nbsp;<strong class="product-quantity">&times;&nbsp;' . (int) $qty . '</strong></td><td class="product-total">' . self::amount( $price ) . '</td></tr>';
		}
		$methods = '';
		$first   = true;
		foreach ( WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array() as $gateway ) {
			if ( 'yes' !== $gateway->enabled ) {
				continue;
			}
			$id       = esc_attr( $gateway->id );
			$methods .= '<li class="wc_payment_method payment_method_' . $id . '"><input id="payment_method_' . $id . '" type="radio" class="input-radio" name="payment_method" value="' . $id . '"' . ( $first ? ' checked' : '' ) . '>'
				. '<label for="payment_method_' . $id . '">' . esc_html( wp_strip_all_tags( $gateway->get_title() ) ) . '</label>'
				. ( $first && $gateway->get_description() ? '<div class="payment_box payment_method_' . $id . '"><p>' . esc_html( wp_strip_all_tags( $gateway->get_description() ) ) . '</p></div>' : '' ) . '</li>';
			$first    = false;
		}
		if ( '' === $methods ) {
			$methods = '<li class="woocommerce-notice woocommerce-notice--info woocommerce-info">' . esc_html__( 'No payment method is enabled yet (WooCommerce → Settings → Payments).', 'uncoder' ) . '</li>';
		}

		$coupon = wc_coupons_enabled() ? '<div class="woocommerce-form-coupon-toggle"><div class="woocommerce-info" role="status">' . esc_html__( 'Have a coupon?', 'uncoder' ) . ' <a href="#" class="showcoupon">' . esc_html__( 'Click here to enter your code', 'uncoder' ) . '</a></div></div>' : '';
		return '<div class="woocommerce"><div class="woocommerce-notices-wrapper"></div>' . $coupon
			. '<form name="checkout" method="post" class="checkout woocommerce-checkout" action="#" inert>'
			. '<div class="col2-set" id="customer_details"><div class="col-1"><div class="woocommerce-billing-fields"><h3>' . esc_html__( 'Billing details', 'uncoder' ) . '</h3><div class="woocommerce-billing-fields__field-wrapper">' . $fields . '</div></div></div>'
			. '<div class="col-2"><div class="woocommerce-additional-fields"><h3>' . esc_html__( 'Additional information', 'uncoder' ) . '</h3><div class="woocommerce-additional-fields__field-wrapper">' . $notes . '</div></div></div></div>'
			. '<h3 id="order_review_heading">' . esc_html__( 'Your order', 'uncoder' ) . '</h3>'
			. '<div id="order_review" class="woocommerce-checkout-review-order"><table class="shop_table woocommerce-checkout-review-order-table"><thead><tr><th class="product-name">' . esc_html__( 'Product', 'uncoder' ) . '</th><th class="product-total">' . esc_html__( 'Subtotal', 'uncoder' ) . '</th></tr></thead>'
			. '<tbody>' . $rows . '</tbody><tfoot><tr class="cart-subtotal"><th>' . esc_html__( 'Subtotal', 'uncoder' ) . '</th><td>' . self::amount( $total ) . '</td></tr><tr class="order-total"><th>' . esc_html__( 'Total', 'uncoder' ) . '</th><td><strong>' . self::amount( $total ) . '</strong></td></tr></tfoot></table>'
			. '<div id="payment" class="woocommerce-checkout-payment"><ul class="wc_payment_methods payment_methods methods">' . $methods . '</ul>'
			. '<div class="form-row place-order"><button type="button" class="button alt" name="woocommerce_checkout_place_order" id="place_order">' . esc_html__( 'Place order', 'uncoder' ) . '</button></div></div></div>'
			. '</form></div>';
	}
}
