<?php
/**
 * Product meta widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Widget;
use Uncoder\Builder\Woo\Product_Context;

defined( 'ABSPATH' ) || exit;

/**
 * SKU, categories and tags of the product. Keeps WooCommerce's classes (its variation script updates the SKU) and
 * the woocommerce_product_meta_start / _end actions, where extensions such as Brands add their lines.
 */
class Product_Meta extends Woo_Widget {

	public function name(): string {
		return 'product-meta';
	}

	public function title(): string {
		return __( 'Product Meta', 'uncoder' );
	}

	public function icon(): string {
		return 'tags';
	}

	public function keywords(): array {
		return array( 'meta', 'sku', 'category', 'categories', 'tags', 'product', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The SKU, categories and tags of the product.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Meta', 'uncoder' ) ) );
		$this->add_control( 'show_sku', array( 'type' => 'switch', 'label' => __( 'SKU', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'show_categories', array( 'type' => 'switch', 'label' => __( 'Categories', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'show_tags', array( 'type' => 'switch', 'label' => __( 'Tags', 'uncoder' ), 'default' => true ) );
		$this->add_control(
			'layout',
			array(
				'type'    => 'choose',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'stacked',
				'options' => array(
					'stacked' => array( 'label' => __( 'One per line', 'uncoder' ), 'icon' => 'rows-3' ),
					'inline'  => array( 'label' => __( 'In a row', 'uncoder' ), 'icon' => 'columns-3' ),
				),
			)
		);
		$this->add_control( 'sku_label', array( 'type' => 'text', 'label' => __( 'SKU label', 'uncoder' ), 'placeholder' => __( 'SKU:', 'uncoder' ), 'condition' => array( 'show_sku' => true ) ) );
		$this->add_control( 'categories_label', array( 'type' => 'text', 'label' => __( 'Categories label', 'uncoder' ), 'placeholder' => __( 'Category:', 'uncoder' ), 'condition' => array( 'show_categories' => true ) ) );
		$this->add_control( 'tags_label', array( 'type' => 'text', 'label' => __( 'Tags label', 'uncoder' ), 'placeholder' => __( 'Tag:', 'uncoder' ), 'condition' => array( 'show_tags' => true ) ) );
		$this->add_control( 'separator', array( 'type' => 'text', 'label' => __( 'Between terms', 'uncoder' ), 'placeholder' => ', ' ) );
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_meta', array( 'label' => __( 'Meta', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between lines', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_control( 'label_heading', array( 'type' => 'heading', 'label' => __( 'Labels', 'uncoder' ) ) );
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-product-meta__label' ) );
		$this->add_control( 'label_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-product-meta__label' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'value_heading', array( 'type' => 'heading', 'label' => __( 'Values', 'uncoder' ) ) );
		$this->add_group( 'value_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-product-meta__value' ) );
		$this->add_control( 'value_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-product-meta__value, {{WRAPPER}} .uncoder-product-meta__value a' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'link_hover_color', array( 'type' => 'color', 'label' => __( 'Link hover color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-product-meta__value a:hover' => 'color: {{VALUE}}' ) ) );
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$product = $this->product( $s, $ctx );
		if ( ! $product ) {
			$this->placeholder( $ctx );
			return;
		}
		$sep = (string) ( $s['separator'] ?? '' );
		$sep = '' !== $sep ? $sep : ', ';
		$html = Product_Context::capture(
			$product,
			function () use ( $s, $product, $sep ) {
				do_action( 'woocommerce_product_meta_start' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
				if ( ! empty( $s['show_sku'] ) && wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( 'variable' ) ) ) {
					$sku = (string) $product->get_sku();
					echo $this->line( 'sku_wrapper', $this->label( $s, 'sku_label', __( 'SKU:', 'uncoder' ) ), '<span class="sku">' . esc_html( '' !== $sku ? $sku : __( 'N/A', 'uncoder' ) ) . '</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
				}
				foreach ( array( 'categories' => array( 'product_cat', 'posted_in', __( 'Category:', 'uncoder' ), __( 'Categories:', 'uncoder' ) ), 'tags' => array( 'product_tag', 'tagged_as', __( 'Tag:', 'uncoder' ), __( 'Tags:', 'uncoder' ) ) ) as $key => $def ) {
					if ( empty( $s[ 'show_' . $key ] ) ) {
						continue;
					}
					$terms = get_the_terms( $product->get_id(), $def[0] );
					if ( ! is_array( $terms ) || ! $terms ) {
						continue;
					}
					$links = array();
					foreach ( $terms as $term ) {
						$url     = get_term_link( $term );
						$links[] = is_string( $url ) ? '<a href="' . esc_url( $url ) . '" rel="tag">' . esc_html( $term->name ) . '</a>' : esc_html( $term->name );
					}
					echo $this->line( $def[1], $this->label( $s, $key . '_label', 1 === count( $terms ) ? $def[2] : $def[3] ), implode( esc_html( $sep ), $links ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
				}
				do_action( 'woocommerce_product_meta_end' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
			}
		);
		if ( '' === trim( $html ) ) {
			$this->placeholder( $ctx, __( 'This product has no SKU, categories or tags to show.', 'uncoder' ) );
			return;
		}
		$layout = 'inline' === ( $s['layout'] ?? '' ) ? 'inline' : 'stacked';
		echo '<div class="' . esc_attr( $this->root_class( 'product_meta uncoder-product-meta--' . $layout ) ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts and extension output of WooCommerce's meta actions.
	}

	/**
	 * Label text: the widget's, else the default.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function label( array $s, string $key, string $default ): string {
		$text = trim( (string) ( $s[ $key ] ?? '' ) );
		return '' !== $text ? $text : $default;
	}

	/**
	 * One meta line (escaped label, escaped value HTML).
	 */
	private function line( string $class, string $label, string $value ): string {
		return '<span class="uncoder-product-meta__item ' . esc_attr( $class ) . '"><span class="uncoder-product-meta__label">' . esc_html( $label ) . '</span> <span class="uncoder-product-meta__value">' . $value . '</span></span>';
	}
}
