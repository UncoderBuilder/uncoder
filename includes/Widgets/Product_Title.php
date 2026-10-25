<?php
/**
 * Product title widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Parts;

defined( 'ABSPATH' ) || exit;

/**
 * The Post Title widget for a product: the product being viewed, or one picked in the widget.
 */
class Product_Title extends Post_Title {

	use Woo_Parts;

	public function name(): string {
		return 'product-title';
	}

	public function title(): string {
		return __( 'Product Title', 'uncoder' );
	}

	public function category(): string {
		return 'woo';
	}

	public function keywords(): array {
		return array( 'product', 'title', 'name', 'heading', 'woocommerce', 'shop', 'h1' );
	}

	public function description(): string {
		return __( 'The name of the product. Use it as the h1 of the product page, or as a linked heading in product loop items.', 'uncoder' );
	}

	public function frontend_styles(): array {
		return array( 'post-title' );
	}

	protected function register_controls(): void {
		parent::register_controls();
		$this->update_control( 'link', array( 'label' => __( 'Link to the product', 'uncoder' ) ) );
		$this->update_control( 'tag', array( 'ai' => 'h1 on single-product templates; h2/h3 inside product loop items.' ) );
		$this->product_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$product = $this->product( $s, $ctx );
		if ( ! $product ) {
			$this->placeholder( $ctx );
			return;
		}
		$c          = clone $ctx;
		$c->post_id = $product->get_id();
		parent::render( $s, $c );
	}
}
