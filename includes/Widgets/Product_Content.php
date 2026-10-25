<?php
/**
 * Product content widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Parts;

defined( 'ABSPATH' ) || exit;

/**
 * The Post Content widget for a product: its long description, through the_content like WooCommerce's
 * Description tab. The editor shows the real description of the previewed product.
 */
class Product_Content extends Post_Content {

	use Woo_Parts;

	public function name(): string {
		return 'product-content';
	}

	public function title(): string {
		return __( 'Product Description', 'uncoder' );
	}

	public function icon(): string {
		return 'file-text';
	}

	public function category(): string {
		return 'woo';
	}

	public function keywords(): array {
		return array( 'description', 'content', 'product', 'long description', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The long description of the product (its content), outside the product tabs.', 'uncoder' );
	}

	public function frontend_styles(): array {
		return array( 'post-content' );
	}

	protected function register_controls(): void {
		parent::register_controls();
		$this->update_control( 'notice', array( 'label' => __( 'Shows the long description of the product being viewed, also in the editor.', 'uncoder' ) ) );
		$this->product_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$product = $this->product( $s, $ctx );
		if ( ! $product || $product->get_id() === $ctx->doc_id ) {
			$this->placeholder( $ctx );
			return;
		}
		if ( '' === trim( (string) $product->get_description() ) ) {
			$this->placeholder( $ctx, __( 'This product has no description.', 'uncoder' ) );
			return;
		}
		// The site's rendering in the editor too: a product description is real content, not a sample.
		$c          = clone $ctx;
		$c->post_id = $product->get_id();
		$c->editor  = false;
		parent::render( $s, $c );
	}
}
