<?php
/**
 * Shared parts of the WooCommerce widgets (Woo_Widget, and widgets that extend another widget).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Woo\Product_Context;

defined( 'ABSPATH' ) || exit;

/**
 * The product a widget shows, its "Product" section, the editor's placeholder and the root classes.
 */
trait Woo_Parts {

	/**
	 * Whether WooCommerce is running.
	 */
	public static function active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * A note at the top of the Content tab while WooCommerce is not active.
	 */
	protected function woo_notice(): void {
		if ( ! self::active() ) {
			$this->add_control(
				'woo_notice',
				array(
					'type'  => 'notice',
					'label' => __( 'Activate WooCommerce to use this widget. Until then it shows nothing on the site.', 'uncoder' ),
				)
			);
		}
	}

	/**
	 * The "Product" section: which product the widget shows. Empty shows the product being viewed.
	 */
	protected function product_section(): void {
		$this->start_section( 'content_product', array( 'label' => __( 'Product', 'uncoder' ) ) );
		$this->woo_notice();
		$this->add_control(
			'product',
			array(
				'type'        => 'multiselect',
				'label'       => __( 'Product', 'uncoder' ),
				'source'      => 'products',
				'max'         => 1,
				'description' => __( 'Leave empty to show the product being viewed (product pages, product loop items). Pick a product to show it on any page.', 'uncoder' ),
				'ai'          => 'Leave empty on single-product templates and product loop items. ["<product id>"] shows that product anywhere (a landing page).',
			)
		);
		$this->end_section();
	}

	/**
	 * The product to show (see Product_Context::product()).
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	protected function product( array $s, Render_Context $ctx ): ?\WC_Product {
		return self::active() ? Product_Context::product( $ctx, $s['product'] ?? 0 ) : null;
	}

	/**
	 * What the editor shows when there is nothing to render; the site prints nothing.
	 *
	 * @param string $message Note (default: WooCommerce inactive / no product).
	 */
	protected function placeholder( Render_Context $ctx, string $message = '' ): void {
		if ( ! $ctx->editor ) {
			return;
		}
		if ( '' === $message ) {
			$message = self::active() ? __( 'Add a product to your store to preview this widget.', 'uncoder' ) : __( 'WooCommerce is not active.', 'uncoder' );
		}
		echo '<div class="uncoder-woo-placeholder">' . esc_html( $message ) . '</div>';
	}

	/**
	 * Root classes: the widget's block plus the WooCommerce hooks described above.
	 *
	 * @param string $extra Modifier classes.
	 */
	protected function root_class( string $extra = '' ): string {
		return trim( 'uncoder-' . $this->name() . ' uncoder-woo woocommerce ' . $extra );
	}
}
