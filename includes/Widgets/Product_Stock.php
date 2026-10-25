<?php
/**
 * Product stock widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Widget;
use Uncoder\Builder\Woo\Product_Context;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's stock status of the product ("12 in stock", "Out of stock", "Available on backorder").
 */
class Product_Stock extends Woo_Widget {

	public function name(): string {
		return 'product-stock';
	}

	public function title(): string {
		return __( 'Product Stock', 'uncoder' );
	}

	public function icon(): string {
		return 'package-check';
	}

	public function keywords(): array {
		return array( 'stock', 'inventory', 'availability', 'in stock', 'out of stock', 'product', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The stock status of the product: how many are left, out of stock or available on backorder.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Stock', 'uncoder' ) ) );
		$this->add_control(
			'in_stock_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Text when in stock', 'uncoder' ),
				'placeholder' => __( 'In stock', 'uncoder' ),
				'description' => __( 'Shown when WooCommerce does not track the stock of the product (it then prints nothing). Leave empty to show nothing.', 'uncoder' ),
			)
		);
		$this->add_control(
			'icons',
			array(
				'type'  => 'switch',
				'label' => __( 'Icons', 'uncoder' ),
			)
		);
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_stock', array( 'label' => __( 'Stock', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .stock' ) );
		$this->add_control(
			'in_color',
			array(
				'type'      => 'color',
				'label'     => __( 'In stock', 'uncoder' ),
				'selectors' => array( self::W . ' .stock.in-stock' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'out_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Out of stock', 'uncoder' ),
				'selectors' => array( self::W . ' .stock.out-of-stock' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'backorder_color',
			array(
				'type'      => 'color',
				'label'     => __( 'On backorder', 'uncoder' ),
				'selectors' => array( self::W . ' .stock.available-on-backorder' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'icons' => true ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-stock-icon: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$product = $this->product( $s, $ctx );
		if ( ! $product ) {
			$this->placeholder( $ctx );
			return;
		}
		$html = Product_Context::with_product( $product, static fn() => (string) wc_get_stock_html( $product ) );
		if ( '' === trim( $html ) ) {
			$text = trim( (string) ( $s['in_stock_text'] ?? '' ) );
			if ( '' === $text || ! $product->is_in_stock() ) {
				$this->placeholder( $ctx, __( 'WooCommerce does not track the stock of this product, so there is nothing to show. Add a text for "in stock" to show one.', 'uncoder' ) );
				return;
			}
			$html = '<p class="stock in-stock">' . esc_html( $text ) . '</p>';
		}
		echo '<div class="' . esc_attr( $this->root_class( ! empty( $s['icons'] ) ? 'uncoder-product-stock--icons' : '' ) ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's stock HTML / escaped text.
	}
}
