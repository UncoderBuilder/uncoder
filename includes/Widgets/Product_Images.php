<?php
/**
 * Product images widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Widget;
use Uncoder\Builder\Woo\Product_Context;
use Uncoder\Builder\Woo\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * The product's image and gallery. "Gallery" is WooCommerce's own (slider, thumbnails, zoom and lightbox as the site
 * supports them; options of variable products switch its image); "Grid" and "One column" show every image at once,
 * opening in the lightbox.
 */
class Product_Images extends Woo_Widget {

	private const GALLERY = self::W . ' .woocommerce-product-gallery';

	public function name(): string {
		return 'product-images';
	}

	public function title(): string {
		return __( 'Product Images', 'uncoder' );
	}

	public function icon(): string {
		return 'images';
	}

	public function keywords(): array {
		return array( 'images', 'gallery', 'photos', 'slider', 'thumbnails', 'zoom', 'lightbox', 'product', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The product image and gallery: WooCommerce\'s slider with thumbnails, zoom and lightbox, or all images in a grid.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'woo' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Images', 'uncoder' ) ) );
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'gallery',
				'options' => array(
					'gallery' => __( 'Gallery with thumbnails', 'uncoder' ),
					'grid'    => __( 'Grid', 'uncoder' ),
					'stack'   => __( 'One column', 'uncoder' ),
				),
				'ai'      => '"gallery" = WooCommerce slider with thumbnails, zoom and lightbox; "grid"/"stack" show every image.',
			)
		);
		$this->add_control(
			'thumbs_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Thumbnails', 'uncoder' ),
				'default'   => 'below',
				'options'   => array(
					'below' => array( 'label' => __( 'Below', 'uncoder' ), 'icon' => 'panel-bottom' ),
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'panel-left' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'panel-right' ),
				),
				'condition' => array( 'layout' => 'gallery' ),
			)
		);
		$this->add_responsive_control(
			'thumbs_columns',
			array(
				'type'      => 'number',
				'label'     => __( 'Thumbnails per row', 'uncoder' ),
				'min'       => 2,
				'max'       => 8,
				'condition' => array(
					'layout'          => 'gallery',
					'thumbs_position' => 'below',
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-thumbs: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'type'      => 'number',
				'label'     => __( 'Columns', 'uncoder' ),
				'min'       => 1,
				'max'       => 4,
				'condition' => array( 'layout' => 'grid' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-images-columns: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'first_wide',
			array(
				'type'      => 'switch',
				'label'     => __( 'First image full width', 'uncoder' ),
				'condition' => array( 'layout' => 'grid' ),
			)
		);
		$this->add_control( 'sale_badge', array( 'type' => 'switch', 'label' => __( 'Sale badge', 'uncoder' ) ) );
		$this->add_control(
			'sale_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Badge text', 'uncoder' ),
				'placeholder' => __( 'Sale!', 'uncoder' ),
				'condition'   => array( 'sale_badge' => true ),
			)
		);
		$this->add_control(
			'gallery_note',
			array(
				'type'      => 'notice',
				'label'     => __( 'Zoom, lightbox and sliding come from WooCommerce and work on the site and in Preview.', 'uncoder' ),
				'condition' => array( 'layout' => 'gallery' ),
			)
		);
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_images', array( 'label' => __( 'Images', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-images-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-images-radius: {{VALUE}}' ),
			)
		);
		$this->add_control( 'image_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-images-bg: {{VALUE}}' ) ) );
		$this->add_control( 'thumbs_heading', array( 'type' => 'heading', 'label' => __( 'Thumbnails', 'uncoder' ), 'condition' => array( 'layout' => 'gallery' ) ) );
		$this->add_responsive_control(
			'thumbs_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Column width', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'condition'  => array(
					'layout'           => 'gallery',
					'thumbs_position!' => 'below',
				),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-thumbs-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'thumbs_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'condition'  => array( 'layout' => 'gallery' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-thumbs-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'thumbs_opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity of the others', 'uncoder' ),
				'min'       => 0.1,
				'max'       => 1,
				'step'      => 0.05,
				'condition' => array( 'layout' => 'gallery' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-thumbs-opacity: {{VALUE}}' ),
			)
		);
		$this->add_control( 'thumbs_active', array( 'type' => 'color', 'label' => __( 'Current thumbnail outline', 'uncoder' ), 'condition' => array( 'layout' => 'gallery' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-thumbs-active: {{VALUE}}' ) ) );
		$this->add_control( 'trigger_heading', array( 'type' => 'heading', 'label' => __( 'Full-screen button', 'uncoder' ), 'condition' => array( 'layout' => 'gallery' ) ) );
		$this->add_control( 'trigger_color', array( 'type' => 'color', 'label' => __( 'Icon color', 'uncoder' ), 'condition' => array( 'layout' => 'gallery' ), 'selectors' => array( self::GALLERY . ' .woocommerce-product-gallery__trigger' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'trigger_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'condition' => array( 'layout' => 'gallery' ), 'selectors' => array( self::GALLERY . ' .woocommerce-product-gallery__trigger' => 'background-color: {{VALUE}}' ) ) );
		$this->end_section();

		$this->start_section( 'style_badge', array( 'label' => __( 'Sale badge', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'sale_badge' => true ) ) );
		$badge = '{{WRAPPER}} .uncoder-product-images__badge';
		$this->add_group( 'badge_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $badge ) );
		$this->add_control( 'badge_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $badge => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'badge_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $badge => 'background-color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'badge_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $badge => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'badge_position',
			array(
				'type'    => 'choose',
				'label'   => __( 'Position', 'uncoder' ),
				'default' => 'left',
				'options' => array(
					'left'  => array( 'label' => __( 'Top left', 'uncoder' ), 'icon' => 'arrow-up-left' ),
					'right' => array( 'label' => __( 'Top right', 'uncoder' ), 'icon' => 'arrow-up-right' ),
				),
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
		$layout = in_array( $s['layout'] ?? '', array( 'grid', 'stack' ), true ) ? $s['layout'] : 'gallery';
		$mods   = array( 'uncoder-product-images--' . $layout );
		if ( 'gallery' === $layout ) {
			$mods[] = 'uncoder-product-images--thumbs-' . ( in_array( $s['thumbs_position'] ?? '', array( 'left', 'right' ), true ) ? $s['thumbs_position'] : 'below' );
			if ( ! $ctx->editor ) {
				Woo::enqueue_gallery();
			}
			$html = Product_Context::capture( $product, 'woocommerce_show_product_images' );
		} else {
			if ( 'grid' === $layout && ! empty( $s['first_wide'] ) ) {
				$mods[] = 'uncoder-product-images--first-wide';
			}
			$html = $this->all_images( $product );
		}

		$badge = '';
		if ( ! empty( $s['sale_badge'] ) && ( $product->is_on_sale() || $ctx->editor ) ) {
			$badge = '<span class="uncoder-product-images__badge uncoder-product-images__badge--' . ( 'right' === ( $s['badge_position'] ?? '' ) ? 'right' : 'left' ) . ( $product->is_on_sale() ? '' : ' uncoder-product-images__badge--preview' ) . '">'
				. esc_html( Sale_Badge::sale_text( $product, array( 'text' => $s['sale_text'] ?? '' ) ) ) . '</span>';
		}
		echo '<div class="' . esc_attr( $this->root_class( implode( ' ', $mods ) ) ) . '">' . $badge . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped badge, WooCommerce's gallery template / attachment images.
	}

	/**
	 * Every image of the product, each linking to its full size (opened by the lightbox). The first keeps
	 * WooCommerce's classes, so choosing options of a variable product shows the option's image there.
	 */
	private function all_images( \WC_Product $product ): string {
		$ids = array_values( array_unique( array_filter( array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) ) ) ) );
		if ( ! $ids ) {
			return '<div class="images uncoder-product-images__list"><div class="woocommerce-product-gallery__image--placeholder uncoder-product-images__item">' . wc_placeholder_img( 'woocommerce_single' ) . '</div></div>';
		}
		$size  = apply_filters( 'woocommerce_gallery_image_size', 'woocommerce_single' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
		$items = '';
		foreach ( $ids as $i => $id ) {
			$full = wp_get_attachment_image_src( $id, 'full' );
			$alt  = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$img  = wp_get_attachment_image(
				$id,
				$size,
				false,
				array(
					'class'    => 0 === $i ? 'wp-post-image' : '',
					'alt'      => '' !== $alt ? $alt : $product->get_name(),
					'loading'  => 0 === $i ? 'eager' : 'lazy',
					'decoding' => 'async',
				)
			);
			$items .= '<div class="' . ( 0 === $i ? 'woocommerce-product-gallery__image ' : '' ) . 'uncoder-product-images__item">'
				. ( $full ? '<a href="' . esc_url( $full[0] ) . '">' . $img . '</a>' : $img ) . '</div>';
		}
		return '<div class="images uncoder-product-images__list">' . $items . '</div>';
	}
}
