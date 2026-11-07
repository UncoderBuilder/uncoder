<?php
/**
 * Add to cart widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Widget;
use Uncoder\Builder\Woo\Product_Context;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's own add to cart for every product type (simple, variable with its options, grouped, external and
 * types extensions add), with − / + quantity buttons and an optional add without reloading. In product loop items
 * it is WooCommerce's loop button ("Add to cart", "Select options", "Read more").
 */
class Add_To_Cart extends Woo_Widget {

	/** The button, form and loop alike: WooCommerce's classes plus two of ours to stay above its stylesheet. */
	private const BUTTON = self::W . ' .uncoder-add-to-cart__body .button';

	public function name(): string {
		return 'add-to-cart';
	}

	public function title(): string {
		return __( 'Add to Cart', 'uncoder' );
	}

	public function icon(): string {
		return 'shopping-cart';
	}

	public function keywords(): array {
		return array( 'add to cart', 'buy', 'purchase', 'cart', 'quantity', 'variations', 'button', 'product', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The add to cart form of the product: quantity, options of variable products, grouped products, buy buttons of external products. In product loop items, the add to cart button.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'woo' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Add to cart', 'uncoder' ) ) );
		$this->add_control(
			'mode',
			array(
				'type'        => 'select',
				'label'       => __( 'Shows', 'uncoder' ),
				'default'     => 'auto',
				'options'     => array(
					'auto'   => __( 'Automatic', 'uncoder' ),
					'form'   => __( 'Full form', 'uncoder' ),
					'button' => __( 'Button only', 'uncoder' ),
				),
				'description' => __( 'Automatic: the full form (quantity, options) on product pages, the button in product loop items.', 'uncoder' ),
				'ai'          => '"auto" fits nearly always. "button" = WooCommerce\'s loop button (variable products link to the product page).',
			)
		);
		$this->add_control(
			'button_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Button text', 'uncoder' ),
				'placeholder' => __( 'Add to cart', 'uncoder' ),
				'description' => __( 'Leave empty for WooCommerce\'s text, which changes with the product type and stock.', 'uncoder' ),
			)
		);
		$this->add_control( 'quantity', array( 'type' => 'switch', 'label' => __( 'Quantity field', 'uncoder' ), 'default' => true ) );
		$this->add_control(
			'quantity_buttons',
			array(
				'type'      => 'switch',
				'label'     => __( '− / + buttons', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'quantity' => true ),
			)
		);
		$this->add_control(
			'stock',
			array(
				'type'        => 'switch',
				'label'       => __( 'Stock status', 'uncoder' ),
				'default'     => true,
				'description' => __( '"12 in stock" above the button, as WooCommerce shows it.', 'uncoder' ),
			)
		);
		$this->add_control(
			'ajax',
			array(
				'type'        => 'switch',
				'label'       => __( 'Add without reloading the page', 'uncoder' ),
				'description' => __( 'The cart updates in place and the message appears in the Woo Notices widget (or above the button).', 'uncoder' ),
			)
		);
		$this->add_control(
			'layout',
			array(
				'type'    => 'choose',
				'label'   => __( 'Quantity and button', 'uncoder' ),
				'default' => 'inline',
				'options' => array(
					'inline'  => array( 'label' => __( 'Side by side', 'uncoder' ), 'icon' => 'columns-2' ),
					'stacked' => array( 'label' => __( 'Stacked', 'uncoder' ), 'icon' => 'rows-2' ),
				),
			)
		);
		$this->add_control( 'full_width', array( 'type' => 'switch', 'label' => __( 'Full-width button', 'uncoder' ) ) );
		$this->add_control(
			'variations_layout',
			array(
				'type'    => 'choose',
				'label'   => __( 'Product options', 'uncoder' ),
				'default' => 'table',
				'options' => array(
					'table'   => array( 'label' => __( 'Label beside the field', 'uncoder' ), 'icon' => 'columns-2' ),
					'stacked' => array( 'label' => __( 'Label above the field', 'uncoder' ), 'icon' => 'rows-2' ),
				),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => 'flex-start',
					'center' => 'center',
					'right'  => 'flex-end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '--uncoder-atc-justify: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-atc-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_button', array( 'label' => __( 'Button', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'button_note',
			array(
				'type'  => 'notice',
				'label' => __( 'The button follows Design System → Buttons. Change it here for this widget only.', 'uncoder' ),
			)
		);
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::BUTTON ) );
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( 'button_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( self::BUTTON => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( self::BUTTON => 'background-color: {{VALUE}}' ) ) );
		$this->add_group( 'button_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::BUTTON ) );
		$this->add_group( 'button_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::BUTTON ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( 'button_hover_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( self::BUTTON . ':is(:hover, :focus-visible)' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( self::BUTTON . ':is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_border', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( self::BUTTON . ':is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ) ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'button_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( self::BUTTON => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( self::BUTTON => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$qty = self::W . ' .uncoder-add-to-cart__body .quantity';
		$this->start_section( 'style_quantity', array( 'label' => __( 'Quantity', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'quantity' => true ) ) );
		$this->add_responsive_control(
			'qty_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-qty-width: {{VALUE}}' ),
			)
		);
		$this->add_group( 'qty_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $qty . ' input.qty' ) );
		$this->add_control( 'qty_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $qty . ' input.qty, ' . $qty . ' .uncoder-qty__btn' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'qty_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $qty => '--uncoder-qty-bg: {{VALUE}}' ) ) );
		$this->add_control( 'qty_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( $qty => '--uncoder-qty-border: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'qty_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $qty => '--uncoder-qty-radius: {{VALUE}}' ),
			)
		);
		$this->add_control( 'qty_button_hover', array( 'type' => 'color', 'label' => __( '− / + hover background', 'uncoder' ), 'condition' => array( 'quantity_buttons' => true ), 'selectors' => array( $qty . ' .uncoder-qty__btn:hover' => 'background-color: {{VALUE}}' ) ) );
		$this->end_section();

		$this->start_section( 'style_variations', array( 'label' => __( 'Product options', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'variation_label_typography', array( 'type' => 'typography', 'label' => __( 'Label typography', 'uncoder' ), 'selector' => self::W . ' table.variations th.label' ) );
		$this->add_control( 'variation_label_color', array( 'type' => 'color', 'label' => __( 'Label color', 'uncoder' ), 'selectors' => array( self::W . ' table.variations th.label' => 'color: {{VALUE}}' ) ) );
		$this->add_group( 'variation_select_typography', array( 'type' => 'typography', 'label' => __( 'Field typography', 'uncoder' ), 'selector' => self::W . ' table.variations td.value select' ) );
		$this->add_control( 'variation_select_color', array( 'type' => 'color', 'label' => __( 'Field text color', 'uncoder' ), 'selectors' => array( self::W . ' table.variations td.value select' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'variation_select_background', array( 'type' => 'color', 'label' => __( 'Field background', 'uncoder' ), 'selectors' => array( self::W . ' table.variations td.value select' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'variation_select_border', array( 'type' => 'color', 'label' => __( 'Field border color', 'uncoder' ), 'selectors' => array( self::W . ' table.variations td.value select' => 'border-color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'variation_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between options', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-variation-gap: {{VALUE}}' ),
			)
		);
		$this->add_control( 'variation_price_color', array( 'type' => 'color', 'label' => __( 'Option price color', 'uncoder' ), 'selectors' => array( self::W . ' .woocommerce-variation-price .price' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'reset_color', array( 'type' => 'color', 'label' => __( '"Clear" link color', 'uncoder' ), 'selectors' => array( self::W . ' .reset_variations' => 'color: {{VALUE}}' ) ) );
		$this->end_section();

		$this->start_section( 'style_stock', array( 'label' => __( 'Stock status', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'stock' => true ) ) );
		$this->add_group( 'stock_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .stock' ) );
		$this->add_control( 'stock_in_color', array( 'type' => 'color', 'label' => __( 'In stock', 'uncoder' ), 'selectors' => array( self::W . ' .stock.in-stock' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'stock_out_color', array( 'type' => 'color', 'label' => __( 'Out of stock', 'uncoder' ), 'selectors' => array( self::W . ' .stock.out-of-stock' => 'color: {{VALUE}}' ) ) );
		$this->end_section();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$settings = array(
			'qtyButtons' => ! empty( $s['quantity'] ) && ! empty( $s['quantity_buttons'] ),
			'ajax'       => ! empty( $s['ajax'] ),
		);
		if ( $settings['qtyButtons'] ) {
			$settings['i18n'] = array(
				'minus' => __( 'Decrease quantity', 'uncoder' ),
				'plus'  => __( 'Increase quantity', 'uncoder' ),
			);
		}
		if ( $settings['ajax'] && self::active() && class_exists( 'WC_AJAX' ) ) {
			$settings['fragments'] = \WC_AJAX::get_endpoint( 'get_refreshed_fragments' );
		}
		return array( 'data-settings' => $this->json_attr( $settings ) );
	}

	/**
	 * Whether the widget shows WooCommerce's loop button instead of the form.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function loop_button( array $s, Render_Context $ctx ): bool {
		$mode = (string) ( $s['mode'] ?? 'auto' );
		if ( 'auto' === $mode ) {
			return $ctx->repeat || 'loop-item' === $ctx->doc_type;
		}
		return 'button' === $mode;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$product = $this->product( $s, $ctx );
		if ( ! $product ) {
			$this->placeholder( $ctx );
			return;
		}
		$loop = $this->loop_button( $s, $ctx );
		$text = trim( (string) ( $s['button_text'] ?? '' ) );
		$id   = $product->get_id();

		// Changes for this widget only: its button text, no stock line.
		// In a loop only buttons that add to the cart take the text ("Select options" and "Read more" stay).
		$own_text    = '' !== $text && $product->is_purchasable() && $product->is_in_stock() && ( ! $loop || $product->supports( 'ajax_add_to_cart' ) );
		$button_text = static fn( $value, $p = null ) => $own_text && ( ! $p instanceof \WC_Product || $p->get_id() === $id ) ? $text : $value;
		$no_stock    = static fn() => '';
		$hook        = $loop ? 'woocommerce_product_add_to_cart_text' : 'woocommerce_product_single_add_to_cart_text';
		add_filter( $hook, $button_text, 20, 2 );
		if ( empty( $s['stock'] ) ) {
			add_filter( 'woocommerce_get_stock_html', $no_stock, 20 );
		}
		try {
			$html = Product_Context::capture( $product, $loop ? 'woocommerce_template_loop_add_to_cart' : 'woocommerce_template_single_add_to_cart' );
		} finally {
			remove_filter( $hook, $button_text, 20 );
			remove_filter( 'woocommerce_get_stock_html', $no_stock, 20 );
		}

		if ( '' === trim( $html ) ) {
			$this->placeholder( $ctx, __( 'This product cannot be added to the cart (out of stock, or without a price).', 'uncoder' ) );
			return;
		}
		$mods = array(
			'uncoder-add-to-cart--' . ( $loop ? 'loop' : 'form' ),
			'uncoder-add-to-cart--' . sanitize_html_class( $product->get_type() ),
			'stacked' === ( $s['layout'] ?? '' ) ? 'uncoder-add-to-cart--stacked' : '',
			! empty( $s['full_width'] ) ? 'uncoder-add-to-cart--full' : '',
			empty( $s['quantity'] ) ? 'uncoder-add-to-cart--no-qty' : '',
			'stacked' === ( $s['variations_layout'] ?? '' ) ? 'uncoder-add-to-cart--options-stacked' : '',
		);
		echo '<div class="' . esc_attr( $this->root_class( implode( ' ', array_filter( $mods ) ) ) ) . '"><div class="uncoder-add-to-cart__body">' . $html . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's add to cart templates.
	}
}
