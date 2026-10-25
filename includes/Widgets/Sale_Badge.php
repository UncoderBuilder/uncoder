<?php
/**
 * Sale badge widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * "Sale" (or the discount, "-20%") while the product is on sale, and optionally "Sold out" when it is out of stock.
 * Prints nothing otherwise, so it can sit over a product image in a loop item.
 */
class Sale_Badge extends Woo_Widget {

	public function name(): string {
		return 'sale-badge';
	}

	public function title(): string {
		return __( 'Sale Badge', 'uncoder' );
	}

	public function icon(): string {
		return 'badge-percent';
	}

	public function keywords(): array {
		return array( 'sale', 'badge', 'discount', 'onsale', 'flash', 'sold out', 'product', 'woocommerce' );
	}

	public function description(): string {
		return __( 'A "Sale" or discount badge shown only while the product is on sale; optionally "Sold out" when it is out of stock.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Badge', 'uncoder' ) ) );
		$this->add_control(
			'content',
			array(
				'type'    => 'select',
				'label'   => __( 'Shows', 'uncoder' ),
				'default' => 'text',
				'options' => array(
					'text'    => __( 'Text', 'uncoder' ),
					'percent' => __( 'Discount (-20%)', 'uncoder' ),
					'both'    => __( 'Text and discount', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'text',
			array(
				'type'        => 'text',
				'label'       => __( 'Text', 'uncoder' ),
				'placeholder' => __( 'Sale!', 'uncoder' ),
				'condition'   => array( 'content!' => 'percent' ),
			)
		);
		$this->add_control(
			'sold_out',
			array(
				'type'  => 'switch',
				'label' => __( '"Sold out" when out of stock', 'uncoder' ),
			)
		);
		$this->add_control(
			'sold_out_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Sold out text', 'uncoder' ),
				'placeholder' => __( 'Sold out', 'uncoder' ),
				'condition'   => array( 'sold_out' => true ),
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
				'selectors'            => array( '{{WRAPPER}}' => 'justify-content: {{VALUE}}' ),
			)
		);
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_badge', array( 'label' => __( 'Badge', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-sale-badge__badge' ) );
		$this->add_control( 'color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-sale-badge__badge' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-sale-badge__badge' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'sold_out_color', array( 'type' => 'color', 'label' => __( 'Sold out text color', 'uncoder' ), 'condition' => array( 'sold_out' => true ), 'selectors' => array( '{{WRAPPER}} .uncoder-sale-badge__badge--sold-out' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'sold_out_background', array( 'type' => 'color', 'label' => __( 'Sold out background', 'uncoder' ), 'condition' => array( 'sold_out' => true ), 'selectors' => array( '{{WRAPPER}} .uncoder-sale-badge__badge--sold-out' => 'background-color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-sale-badge__badge' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-sale-badge__badge' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-sale-badge__badge' ) );
		$this->end_section();
	}

	/**
	 * The largest discount of the product in percent (variable products: over their variations).
	 */
	public static function discount( \WC_Product $product ): int {
		$prices = array();
		if ( $product->is_type( 'variable' ) && $product instanceof \WC_Product_Variable ) {
			$all = $product->get_variation_prices( true );
			foreach ( (array) ( $all['regular_price'] ?? array() ) as $id => $regular ) {
				$prices[] = array( (float) $regular, (float) ( $all['sale_price'][ $id ] ?? $regular ) );
			}
		} elseif ( $product->is_type( 'grouped' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && $child->is_on_sale() ) {
					$prices[] = array( (float) $child->get_regular_price(), (float) $child->get_sale_price() );
				}
			}
		} else {
			$prices[] = array( (float) $product->get_regular_price(), (float) $product->get_sale_price() );
		}
		$best = 0;
		foreach ( $prices as $pair ) {
			if ( $pair[0] > 0 && $pair[1] > 0 && $pair[1] < $pair[0] ) {
				$best = max( $best, (int) round( ( $pair[0] - $pair[1] ) / $pair[0] * 100 ) );
			}
		}
		return $best;
	}

	/**
	 * Badge text for a product on sale ("Sale!", "-20%", "Sale! -20%").
	 *
	 * @param array<string,mixed> $s Settings (content, text).
	 */
	public static function sale_text( \WC_Product $product, array $s ): string {
		$text    = trim( (string) ( $s['text'] ?? '' ) );
		$text    = '' !== $text ? $text : __( 'Sale!', 'uncoder' );
		$mode    = (string) ( $s['content'] ?? 'text' );
		$percent = 'text' !== $mode ? self::discount( $product ) : 0;
		/* translators: %s: discount in percent. */
		$off = $percent > 0 ? sprintf( __( '-%s%%', 'uncoder' ), number_format_i18n( $percent ) ) : '';
		if ( 'percent' === $mode ) {
			return '' !== $off ? $off : $text;
		}
		return 'both' === $mode && '' !== $off ? $text . ' ' . $off : $text;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$product = $this->product( $s, $ctx );
		if ( ! $product ) {
			$this->placeholder( $ctx );
			return;
		}
		$sold_out = ! empty( $s['sold_out'] ) && ! $product->is_in_stock();
		if ( $sold_out ) {
			$text  = trim( (string) ( $s['sold_out_text'] ?? '' ) );
			$badge = '<span class="uncoder-sale-badge__badge uncoder-sale-badge__badge--sold-out">' . esc_html( '' !== $text ? $text : __( 'Sold out', 'uncoder' ) ) . '</span>';
		} elseif ( $product->is_on_sale() ) {
			$badge = '<span class="uncoder-sale-badge__badge">' . esc_html( self::sale_text( $product, $s ) ) . '</span>';
		} elseif ( $ctx->editor ) {
			// Off sale the badge is hidden; the editor shows it faded so it can be styled.
			$badge = '<span class="uncoder-sale-badge__badge uncoder-sale-badge__badge--preview" title="' . esc_attr__( 'Shown only while the product is on sale', 'uncoder' ) . '">' . esc_html( self::sale_text( $product, $s ) ) . '</span>';
		} else {
			return;
		}
		echo '<div class="' . esc_attr( $this->root_class() ) . '">' . $badge . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped badge.
	}
}
