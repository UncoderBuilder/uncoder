<?php
/**
 * Product short description widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Widget;
use Uncoder\Builder\Woo\Product_Context;

defined( 'ABSPATH' ) || exit;

/**
 * The product's short description, through WooCommerce's woocommerce_short_description filter.
 */
class Product_Short_Description extends Woo_Widget {

	public function name(): string {
		return 'product-short-description';
	}

	public function title(): string {
		return __( 'Short Description', 'uncoder' );
	}

	public function icon(): string {
		return 'text';
	}

	public function keywords(): array {
		return array( 'short description', 'excerpt', 'summary', 'product', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The short description of the product, shown next to the price on product pages.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Short description', 'uncoder' ) ) );
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => Heading::ALIGN,
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_control( 'color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'link_color', array( 'type' => 'color', 'label' => __( 'Link color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} a' => 'color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'paragraph_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Paragraph spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} > * + *' => 'margin-top: {{VALUE}}' ),
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
		// A password-protected product keeps its texts until the password is given (as WordPress keeps excerpts).
		if ( ! $ctx->editor && post_password_required( $product->get_id() ) ) {
			return;
		}
		$html = Product_Context::with_product(
			$product,
			static function () use ( $product ): string {
				$post = get_post( $product->get_id() );
				/** This filter is documented in woocommerce/templates/single-product/short-description.php */
				return (string) apply_filters( 'woocommerce_short_description', $post ? $post->post_excerpt : '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
			}
		);
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			$this->placeholder( $ctx, __( 'This product has no short description.', 'uncoder' ) );
			return;
		}
		echo '<div class="' . esc_attr( $this->root_class( 'woocommerce-product-details__short-description' ) ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output of WooCommerce's filter (wp_kses_post'd excerpt).
	}
}
