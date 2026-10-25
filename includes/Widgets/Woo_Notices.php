<?php
/**
 * WooCommerce notices widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Document;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's messages ("… has been added to your cart", errors, notes) where the widget sits. On a product page
 * with this widget, WooCommerce's own notices above the product are left out (Woo::before_body()), so they show
 * once, here. The add to cart without reloading also puts its messages here.
 */
class Woo_Notices extends Woo_Widget {

	public function name(): string {
		return 'woo-notices';
	}

	public function title(): string {
		return __( 'Woo Notices', 'uncoder' );
	}

	public function icon(): string {
		return 'message-square-warning';
	}

	public function keywords(): array {
		return array( 'notices', 'messages', 'alerts', 'added to cart', 'errors', 'woocommerce' );
	}

	public function description(): string {
		return __( 'WooCommerce messages such as "added to your cart" and form errors, where you place them.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Notices', 'uncoder' ) ) );
		$this->woo_notice();
		$this->add_control(
			'about',
			array(
				'type'  => 'notice',
				'label' => __( 'The editor shows sample messages to style. On the site the widget is empty until WooCommerce has something to say.', 'uncoder' ),
			)
		);
		$this->end_section();

		$types = array(
			'success' => array( __( 'Success', 'uncoder' ), '.woocommerce-message' ),
			'info'    => array( __( 'Information', 'uncoder' ), '.woocommerce-info' ),
			'error'   => array( __( 'Error', 'uncoder' ), '.woocommerce-error' ),
		);
		$all = implode( ', ', array_map( static fn( $t ) => self::W . ' ' . $t[1], $types ) );
		$this->start_section( 'style_notices', array( 'label' => __( 'Notices', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $all ) );
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $all => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $all => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between notices', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .woocommerce-notices-wrapper' => 'gap: {{VALUE}}' ),
			)
		);
		foreach ( $types as $key => $type ) {
			$sel = self::W . ' ' . $type[1];
			$this->add_control( $key . '_heading', array( 'type' => 'heading', 'label' => $type[0] ) );
			$this->add_control( $key . '_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $sel => 'color: {{VALUE}}' ) ) );
			$this->add_control( $key . '_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $sel => 'background-color: {{VALUE}}' ) ) );
			$this->add_control( $key . '_accent', array( 'type' => 'color', 'label' => __( 'Accent (line and icon)', 'uncoder' ), 'selectors' => array( $sel => '--uncoder-notice-accent: {{VALUE}}' ) ) );
		}
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		if ( ! self::active() ) {
			$this->placeholder( $ctx );
			return;
		}
		if ( $ctx->editor ) {
			// The store's newest product, so the sample reads like one of its own messages.
			$latest  = wc_get_products( array( 'status' => 'publish', 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
			$product = $latest ? $latest[0]->get_name() : __( 'Classic Tee', 'uncoder' );
			$sample  = '<div class="woocommerce-message" role="alert"><a href="#" class="button wc-forward">' . esc_html__( 'View cart', 'uncoder' ) . '</a> '
				/* translators: %s: product name. */
				. esc_html( sprintf( __( '“%s” has been added to your cart.', 'uncoder' ), $product ) ) . '</div>'
				. '<div class="woocommerce-info" role="status">' . esc_html__( 'Have a coupon? Click here to enter your code', 'uncoder' ) . '</div>'
				. '<ul class="woocommerce-error" role="alert"><li>' . esc_html__( 'Please choose product options before adding this product to your cart.', 'uncoder' ) . '</li></ul>';
			echo '<div class="' . esc_attr( $this->root_class() ) . '"><div class="woocommerce-notices-wrapper">' . $sample . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped sample.
			return;
		}
		// Notices live in the visitor's session: never in saved content or API output.
		if ( Document::$static_render || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! function_exists( 'wc_print_notices' ) ) {
			return;
		}
		$notices = (string) wc_print_notices( true );
		// The wrapper stays even when empty: the add to cart without reloading puts its messages in it.
		echo '<div class="' . esc_attr( $this->root_class() ) . '"><div class="woocommerce-notices-wrapper">' . $notices . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's notice templates (wc_kses_notice).
	}
}
