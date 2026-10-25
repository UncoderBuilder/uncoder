<?php
/**
 * Base for the Cart, Checkout and My Account widgets.
 *
 * Not a widget: helpers in includes/Widgets/Support are never registered.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

use Uncoder\Builder\Core\Document;
use Uncoder\Builder\Core\Render_Context;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's classic page shortcodes ([woocommerce_cart], [woocommerce_checkout], [woocommerce_my_account]) with
 * Uncoder's layout and styling, so payment gateways, shipping calculators and account extensions keep working. These
 * pages live in the visitor's session: the editor (which has none) shows sample markup with the same classes, and
 * saved content or API output never includes them.
 */
abstract class Woo_Page_Widget extends Woo_Widget {

	public function frontend_scripts(): array {
		return array( 'woo' );
	}

	/**
	 * WooCommerce's output for the visitor.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	abstract protected function live( array $s ): string;

	/**
	 * Sample markup for the editor, with WooCommerce's classes.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	abstract protected function sample( array $s, Render_Context $ctx ): string;

	/**
	 * Modifier classes of the root.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	protected function modifiers( array $s ): string {
		return '';
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$settings = array( 'qtyButtons' => ! empty( $s['quantity_buttons'] ) );
		if ( $settings['qtyButtons'] ) {
			$settings['i18n'] = array(
				'minus' => __( 'Decrease quantity', 'uncoder' ),
				'plus'  => __( 'Increase quantity', 'uncoder' ),
			);
		}
		return array( 'data-settings' => $this->json_attr( $settings ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		if ( ! self::active() ) {
			$this->placeholder( $ctx );
			return;
		}
		if ( $ctx->editor ) {
			$html = $this->sample( $s, $ctx );
		} elseif ( Document::$static_render || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! function_exists( 'WC' ) || null === WC()->cart ) {
			return;
		} else {
			$html = $this->live( $s );
		}
		if ( '' === trim( $html ) ) {
			return;
		}
		echo '<div class="' . esc_attr( $this->root_class( $this->modifiers( $s ) ) ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's templates / escaped sample.
	}

	/**
	 * Two products for samples (the latest ones), or fewer.
	 *
	 * @return \WC_Product[]
	 */
	protected static function sample_products( int $count = 2 ): array {
		$ids = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => $count,
				'type'    => array( 'simple', 'variable' ),
				'return'  => 'ids',
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		return array_values( array_filter( array_map( 'wc_get_product', (array) $ids ) ) );
	}

	/**
	 * Price of a product as WooCommerce formats amounts.
	 */
	protected static function amount( float $value ): string {
		return wc_price( $value );
	}
}
