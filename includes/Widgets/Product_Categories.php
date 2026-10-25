<?php
/**
 * Product categories widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * A grid of product categories in WooCommerce's own category cards (content-product-cat.php: image, name, product
 * count), styled like the Products widget's cards.
 */
class Product_Categories extends Woo_Widget {

	private const CARD = self::W . ' ul.products li.product-category';

	public function name(): string {
		return 'product-categories';
	}

	public function title(): string {
		return __( 'Product Categories', 'uncoder' );
	}

	public function icon(): string {
		return 'folder-open';
	}

	public function keywords(): array {
		return array( 'categories', 'product categories', 'collections', 'departments', 'shop', 'woocommerce' );
	}

	public function description(): string {
		return __( 'A grid of product categories with their image, name and number of products.', 'uncoder' );
	}

	public function frontend_styles(): array {
		return array( 'woo', 'products' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Categories', 'uncoder' ) ) );
		$this->woo_notice();
		$this->add_control(
			'source',
			array(
				'type'    => 'select',
				'label'   => __( 'Show', 'uncoder' ),
				'default' => 'top',
				'options' => array(
					'top'     => __( 'Top-level categories', 'uncoder' ),
					'all'     => __( 'All categories', 'uncoder' ),
					'manual'  => __( 'Chosen categories', 'uncoder' ),
					'current' => __( 'Subcategories of the category viewed', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'categories',
			array(
				'type'      => 'multiselect',
				'label'     => __( 'Categories', 'uncoder' ),
				'source'    => 'product-categories',
				'condition' => array( 'source' => 'manual' ),
			)
		);
		$this->add_control(
			'limit',
			array(
				'type'        => 'number',
				'label'       => __( 'Number of categories', 'uncoder' ),
				'min'         => 1,
				'max'         => 100,
				'placeholder' => __( 'All', 'uncoder' ),
				'condition'   => array( 'source!' => 'manual' ),
			)
		);
		$this->add_control(
			'orderby',
			array(
				'type'    => 'select',
				'label'   => __( 'Order', 'uncoder' ),
				'default' => 'name',
				'options' => array(
					'name'       => __( 'Name', 'uncoder' ),
					'menu_order' => __( 'Custom order', 'uncoder' ),
					'count'      => __( 'Number of products', 'uncoder' ),
					'id'         => __( 'Newest first', 'uncoder' ),
				),
			)
		);
		$this->add_control( 'hide_empty', array( 'type' => 'switch', 'label' => __( 'Hide empty categories', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'show_count', array( 'type' => 'switch', 'label' => __( 'Number of products', 'uncoder' ), 'default' => true ) );
		$this->end_section();

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_responsive_control(
			'columns',
			array(
				'type'        => 'number',
				'label'       => __( 'Columns', 'uncoder' ),
				'placeholder' => 4,
				'description' => __( 'Empty: 4 on desktops, 3 on tablets, 2 on phones.', 'uncoder' ),
				'min'         => 1,
				'max'         => 8,
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-products-columns: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Column gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-products-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Row gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-products-row-gap: {{VALUE}}' ),
			)
		);
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
				'selectors' => array( self::CARD => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$card = self::CARD;
		$this->start_section( 'style_card', array( 'label' => __( 'Card', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control( 'card_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $card => 'background-color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'card_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( $card => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'card_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => $card ) );
		$this->add_responsive_control(
			'card_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $card => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'card_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => $card ) );
		$this->end_section();

		$this->start_section( 'style_image', array( 'label' => __( 'Image', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-card-image-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'image_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Shape', 'uncoder' ),
				'options'   => array(
					''    => __( 'As uploaded', 'uncoder' ),
					'1/1' => __( 'Square', 'uncoder' ),
					'4/5' => __( 'Portrait 4:5', 'uncoder' ),
					'4/3' => __( 'Landscape 4:3', 'uncoder' ),
					'16/9' => __( 'Wide 16:9', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-card-image-ratio: {{VALUE}}' ),
			)
		);
		$this->add_control( 'image_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-card-image-bg: {{VALUE}}' ) ) );
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Name', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $card . ' .woocommerce-loop-category__title' ) );
		$this->add_control( 'title_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( $card . ' .woocommerce-loop-category__title' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'title_hover_color', array( 'type' => 'color', 'label' => __( 'Hover color', 'uncoder' ), 'selectors' => array( $card . ' a:hover .woocommerce-loop-category__title' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'count_color', array( 'type' => 'color', 'label' => __( 'Count color', 'uncoder' ), 'condition' => array( 'show_count' => true ), 'selectors' => array( $card . ' .woocommerce-loop-category__title .count' => 'color: {{VALUE}}' ) ) );
		$this->end_section();
	}

	/**
	 * Attributes of WooCommerce's [product_categories] shortcode for the settings, or null when there is nothing to
	 * list (subcategories outside a category).
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<string,mixed>|null
	 */
	private function attributes( array $s, Render_Context $ctx ): ?array {
		$source = (string) ( $s['source'] ?? 'top' );
		$atts   = array(
			'limit'      => ! empty( $s['limit'] ) ? (string) (int) $s['limit'] : '-1',
			'orderby'    => in_array( $s['orderby'] ?? '', array( 'name', 'menu_order', 'count', 'id' ), true ) ? $s['orderby'] : 'name',
			'order'      => in_array( $s['orderby'] ?? '', array( 'count', 'id' ), true ) ? 'DESC' : 'ASC',
			'columns'    => (string) max( 1, (int) ( $s['columns'] ?? 4 ) ),
			'hide_empty' => ! empty( $s['hide_empty'] ) ? '1' : '0',
		);
		if ( 'top' === $source ) {
			$atts['parent'] = '0';
		} elseif ( 'manual' === $source ) {
			$ids = array_values( array_filter( array_map( 'absint', (array) ( $s['categories'] ?? array() ) ) ) );
			if ( ! $ids ) {
				return null;
			}
			$atts['ids']   = implode( ',', $ids );
			$atts['limit'] = '-1';
		} elseif ( 'current' === $source ) {
			$term = ! $ctx->editor && is_product_category() ? get_queried_object() : null;
			// The editor previews the top-level categories.
			$atts['parent'] = $term instanceof \WP_Term ? (string) $term->term_id : ( $ctx->editor ? '0' : '' );
			if ( '' === $atts['parent'] ) {
				return null;
			}
		}
		return $atts;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		if ( ! self::active() || ! class_exists( 'WC_Shortcodes' ) ) {
			$this->placeholder( $ctx );
			return;
		}
		$atts = $this->attributes( $s, $ctx );
		$html = '';
		if ( null !== $atts ) {
			$no_count = static fn() => '';
			if ( empty( $s['show_count'] ) ) {
				add_filter( 'woocommerce_subcategory_count_html', $no_count, 20 );
			}
			try {
				$html = (string) \WC_Shortcodes::product_categories( $atts );
			} finally {
				remove_filter( 'woocommerce_subcategory_count_html', $no_count, 20 );
			}
		}
		if ( false === strpos( $html, '<li' ) ) {
			$this->placeholder( $ctx, __( 'No product categories to show here.', 'uncoder' ) );
			return;
		}
		// WooCommerce's wrapper becomes the grid's loop element.
		$html = preg_replace( '/^<div class="woocommerce columns-\d+">/', '<div class="uncoder-products__loop">', $html, 1 );
		echo '<div class="' . esc_attr( $this->root_class( 'uncoder-products' ) ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's category templates.
	}
}
