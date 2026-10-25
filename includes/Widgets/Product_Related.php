<?php
/**
 * Related products widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * The Products widget set up for a product page: WooCommerce's related products (or the product's upsells, or the
 * cart's cross-sells), under a heading that hides with an empty list.
 */
class Product_Related extends Products {

	public function name(): string {
		return 'product-related';
	}

	public function title(): string {
		return __( 'Related Products', 'uncoder' );
	}

	public function icon(): string {
		return 'layers';
	}

	public function keywords(): array {
		return array( 'related', 'upsells', 'you may also like', 'cross-sells', 'recommended', 'products', 'woocommerce' );
	}

	public function description(): string {
		return __( 'Related products, upsells or cross-sells of the product being viewed, under a heading. Shows nothing when there are none.', 'uncoder' );
	}

	protected function default_source(): string {
		return 'related';
	}

	public function preset(): array {
		return array(
			'heading' => __( 'Related products', 'uncoder' ),
			'limit'   => 4,
		);
	}
}
