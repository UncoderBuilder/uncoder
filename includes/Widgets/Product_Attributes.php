<?php
/**
 * Product attributes widget ("Additional information").
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Widgets\Support\Woo_Widget;
use Uncoder\Builder\Woo\Product_Context;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's table of product attributes, weight and dimensions (wc_display_product_attributes()), the content
 * of the "Additional information" tab.
 */
class Product_Attributes extends Woo_Widget {

	public function name(): string {
		return 'product-attributes';
	}

	public function title(): string {
		return __( 'Additional Information', 'uncoder' );
	}

	public function icon(): string {
		return 'table-properties';
	}

	public function keywords(): array {
		return array( 'attributes', 'additional information', 'specifications', 'weight', 'dimensions', 'product', 'woocommerce' );
	}

	public function description(): string {
		return __( 'A table of the product attributes, weight and dimensions (WooCommerce\'s "Additional information").', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Additional information', 'uncoder' ) ) );
		$this->add_control( 'heading', array( 'type' => 'text', 'label' => __( 'Heading', 'uncoder' ), 'placeholder' => __( 'None', 'uncoder' ) ) );
		$this->add_control(
			'heading_tag',
			array(
				'type'      => 'select',
				'label'     => __( 'Heading tag', 'uncoder' ),
				'default'   => 'h2',
				'options'   => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
				'condition' => array( 'heading!' => '' ),
			)
		);
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_table', array( 'label' => __( 'Table', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'label_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Label column width', 'uncoder' ),
				'size_units' => array( '%', 'px' ),
				'selectors'  => array( self::W . ' .shop_attributes th' => 'width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'cell_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Cell padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( self::W . ' .shop_attributes :is(th, td)' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_control( 'border_color', array( 'type' => 'color', 'label' => __( 'Row lines', 'uncoder' ), 'selectors' => array( self::W . ' .shop_attributes :is(th, td)' => 'border-color: {{VALUE}}' ) ) );
		$this->add_control( 'stripe_color', array( 'type' => 'color', 'label' => __( 'Striped rows', 'uncoder' ), 'selectors' => array( self::W . ' .shop_attributes tr:nth-child(even) :is(th, td)' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'label_heading', array( 'type' => 'heading', 'label' => __( 'Labels', 'uncoder' ) ) );
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .shop_attributes th' ) );
		$this->add_control( 'label_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( self::W . ' .shop_attributes th' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'value_heading', array( 'type' => 'heading', 'label' => __( 'Values', 'uncoder' ) ) );
		$this->add_group( 'value_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .shop_attributes td' ) );
		$this->add_control( 'value_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( self::W . ' .shop_attributes td' => 'color: {{VALUE}}' ) ) );
		$this->end_section();

		$this->start_section( 'style_heading', array( 'label' => __( 'Heading', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'heading!' => '' ) ) );
		$this->add_group( 'heading_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-product-attributes__heading' ) );
		$this->add_control( 'heading_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-product-attributes__heading' => 'color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'heading_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space below', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-product-attributes__heading' => 'margin-bottom: {{VALUE}}' ),
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
		$table = Product_Context::capture( $product, static fn() => wc_display_product_attributes( $product ) );
		if ( '' === trim( wp_strip_all_tags( $table ) ) ) {
			$this->placeholder( $ctx, __( 'This product has no attributes, weight or dimensions to list.', 'uncoder' ) );
			return;
		}
		$heading = trim( (string) ( $s['heading'] ?? '' ) );
		$tag     = Utils::tag( $s['heading_tag'] ?? 'h2', Utils::HEADING_TAGS, 'h2' );
		echo '<div class="' . esc_attr( $this->root_class() ) . '">'
			. ( '' !== $heading ? '<' . $tag . ' class="uncoder-product-attributes__heading">' . esc_html( $heading ) . '</' . $tag . '>' : '' )
			. $table . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, escaped heading, WooCommerce template.
	}
}
