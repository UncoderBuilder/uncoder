<?php
/**
 * Product price widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Widget;
use Uncoder\Builder\Woo\Product_Context;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's price of the product (sale prices, ranges, tax suffix and the filters extensions add).
 */
class Product_Price extends Woo_Widget {

	public function name(): string {
		return 'product-price';
	}

	public function title(): string {
		return __( 'Product Price', 'uncoder' );
	}

	public function icon(): string {
		return 'badge-dollar-sign';
	}

	public function keywords(): array {
		return array( 'price', 'product', 'sale', 'cost', 'woocommerce', 'shop' );
	}

	public function description(): string {
		return __( 'The product price as WooCommerce shows it: regular and sale price, price ranges of variable products, tax suffix.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Price', 'uncoder' ) ) );
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}; --uncoder-price-justify: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'stacked',
			array(
				'type'  => 'switch',
				'label' => __( 'Sale price on its own line', 'uncoder' ),
				'ai'    => 'Puts the crossed-out regular price and the sale price on separate lines.',
			)
		);
		$this->add_control(
			'sale_first',
			array(
				'type'  => 'switch',
				'label' => __( 'Sale price first', 'uncoder' ),
			)
		);
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_price', array( 'label' => __( 'Price', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .price' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::W . ' .price' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control( 'sale_heading', array( 'type' => 'heading', 'label' => __( 'Sale price', 'uncoder' ) ) );
		$this->add_group( 'sale_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .price ins' ) );
		$this->add_control(
			'sale_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::W . ' .price ins' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control( 'regular_heading', array( 'type' => 'heading', 'label' => __( 'Regular price (crossed out)', 'uncoder' ) ) );
		$this->add_group( 'regular_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .price del' ) );
		$this->add_control(
			'regular_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::W . ' .price del' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between prices', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-price-gap: {{VALUE}}' ),
			)
		);
		$this->add_control( 'suffix_heading', array( 'type' => 'heading', 'label' => __( 'Tax suffix', 'uncoder' ) ) );
		$this->add_group( 'suffix_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .price .woocommerce-price-suffix' ) );
		$this->add_control(
			'suffix_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::W . ' .price .woocommerce-price-suffix' => 'color: {{VALUE}}' ),
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
		$html = Product_Context::with_product( $product, static fn() => (string) $product->get_price_html() );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			$this->placeholder( $ctx, __( 'This product has no price.', 'uncoder' ) );
			return;
		}
		$mods = ( ! empty( $s['stacked'] ) ? ' uncoder-product-price--stacked' : '' ) . ( ! empty( $s['sale_first'] ) ? ' uncoder-product-price--sale-first' : '' );
		/** This filter is documented in woocommerce/templates/single-product/price.php */
		$class = (string) apply_filters( 'woocommerce_product_price_class', 'price' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
		$class = implode( ' ', array_unique( array_merge( array( 'price' ), preg_split( '/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY ) ) ) );
		echo '<div class="' . esc_attr( $this->root_class( $mods ) ) . '"><p class="' . esc_attr( $class ) . '">' . $html . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's price HTML.
	}
}
