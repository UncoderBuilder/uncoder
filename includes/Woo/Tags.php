<?php
/**
 * WooCommerce dynamic tags.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Woo;

use Uncoder\Builder\Core\Document;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Dynamic\Tags as Registry;
use Uncoder\Builder\Widgets\Sale_Badge;

defined( 'ABSPATH' ) || exit;

/**
 * Product data (price, SKU, stock, rating, categories, images, add-to-cart link, discount) for any widget, on product
 * pages and in product loop items, plus the cart (count, total) and the shop's page links. The product is the one
 * being rendered (Product_Context), so tags in a loop item show each card's product.
 */
final class Tags {

	public static function register( Registry $tags ): void {
		$product = static fn( Render_Context $ctx ): ?\WC_Product => Product_Context::product( $ctx );
		$plain   = static fn( string $html ): string => trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );

		$tags->register(
			'product-price',
			array(
				'title'      => __( 'Product price', 'uncoder' ),
				'group'      => 'woo',
				'categories' => array( 'text', 'number' ),
				'controls'   => array(
					'which' => array(
						'type'    => 'select',
						'label'   => __( 'Price', 'uncoder' ),
						'options' => array(
							''        => __( 'Current price', 'uncoder' ),
							'regular' => __( 'Regular price', 'uncoder' ),
							'sale'    => __( 'Sale price', 'uncoder' ),
							'number'  => __( 'Current price, number only', 'uncoder' ),
						),
					),
				),
				'callback'   => static function ( $o, $ctx ) use ( $product, $plain ) {
					$p = $product( $ctx );
					if ( ! $p ) {
						return '';
					}
					$which = (string) ( $o['which'] ?? '' );
					switch ( $which ) {
						case 'regular':
						case 'sale':
							if ( 'sale' === $which && ! $p->is_on_sale() ) {
								return '';
							}
							if ( $p instanceof \WC_Product_Variable ) {
								// The lowest option's price, already with or without tax as the shop shows prices.
								$value = 'sale' === $which ? $p->get_variation_sale_price( 'min', true ) : $p->get_variation_regular_price( 'min', true );
								return '' !== (string) $value ? $plain( wc_price( (float) $value ) ) : '';
							}
							// Grouped products have neither (their sale is a child's): nothing rather than $0.00.
							$value = 'sale' === $which ? $p->get_sale_price() : $p->get_regular_price();
							return '' !== (string) $value ? $plain( wc_price( (float) wc_get_price_to_display( $p, array( 'price' => (float) $value ) ) ) ) : '';
						case 'number':
							return (string) wc_get_price_to_display( $p );
						default:
							// The price as shoppers read it: without the crossed-out regular price and the screen
							// reader phrases ("Original price was: …"); ranges stay ranges.
							$html = (string) preg_replace( array( '#<del\b.*?</del>#is', '#<span class="screen-reader-text">.*?</span>#is' ), '', (string) $p->get_price_html() );
							return trim( (string) preg_replace( '/\s+/u', ' ', $plain( $html ) ) );
					}
				},
			)
		);
		$tags->register(
			'product-sku',
			array(
				'title'    => __( 'Product SKU', 'uncoder' ),
				'group'    => 'woo',
				'callback' => static fn( $o, $ctx ) => ( $p = $product( $ctx ) ) ? (string) $p->get_sku() : '',
			)
		);
		$tags->register(
			'product-stock',
			array(
				'title'      => __( 'Product stock', 'uncoder' ),
				'group'      => 'woo',
				'categories' => array( 'text', 'number' ),
				'controls'   => array(
					'show' => array(
						'type'    => 'select',
						'label'   => __( 'Show', 'uncoder' ),
						'options' => array(
							''         => __( 'Status ("12 in stock")', 'uncoder' ),
							'quantity' => __( 'Quantity in stock', 'uncoder' ),
						),
					),
				),
				'callback'   => static function ( $o, $ctx ) use ( $product ) {
					$p = $product( $ctx );
					if ( ! $p ) {
						return '';
					}
					if ( 'quantity' === ( $o['show'] ?? '' ) ) {
						return $p->managing_stock() ? (string) (int) $p->get_stock_quantity() : '';
					}
					$availability = $p->get_availability();
					return (string) ( $availability['availability'] ?? '' );
				},
			)
		);
		$tags->register(
			'product-rating',
			array(
				'title'      => __( 'Product rating', 'uncoder' ),
				'group'      => 'woo',
				'categories' => array( 'text', 'number' ),
				'controls'   => array(
					'show' => array(
						'type'    => 'select',
						'label'   => __( 'Show', 'uncoder' ),
						'options' => array(
							''      => __( 'Average rating (4.5)', 'uncoder' ),
							'count' => __( 'Number of reviews', 'uncoder' ),
						),
					),
				),
				'callback'   => static function ( $o, $ctx ) use ( $product ) {
					$p = $product( $ctx );
					if ( ! $p ) {
						return '';
					}
					if ( 'count' === ( $o['show'] ?? '' ) ) {
						return (string) (int) $p->get_review_count();
					}
					return $p->get_rating_count() ? number_format_i18n( (float) $p->get_average_rating(), 1 ) : '';
				},
			)
		);
		$tags->register(
			'product-discount',
			array(
				'title'      => __( 'Product discount', 'uncoder' ),
				'group'      => 'woo',
				'categories' => array( 'text', 'number' ),
				'controls'   => array(
					'format' => array( 'type' => 'text', 'label' => __( 'Format', 'uncoder' ), 'placeholder' => '-%s%', 'description' => __( '%s is the discount in percent. Empty when the product is not on sale.', 'uncoder' ) ),
				),
				'callback'   => static function ( $o, $ctx ) use ( $product ) {
					$p       = $product( $ctx );
					$percent = $p && $p->is_on_sale() ? Sale_Badge::discount( $p ) : 0;
					if ( $percent <= 0 ) {
						return '';
					}
					$format = trim( (string) ( $o['format'] ?? '' ) );
					return str_replace( '%s', number_format_i18n( $percent ), '' !== $format ? $format : '-%s%' );
				},
			)
		);
		$tags->register(
			'product-categories',
			array(
				'title'    => __( 'Product categories', 'uncoder' ),
				'group'    => 'woo',
				'controls' => array(
					'separator' => array( 'type' => 'text', 'label' => __( 'Separator', 'uncoder' ), 'placeholder' => ', ' ),
				),
				'callback' => static function ( $o, $ctx ) use ( $product ) {
					$p     = $product( $ctx );
					$terms = $p ? get_the_terms( $p->get_id(), 'product_cat' ) : false;
					if ( ! is_array( $terms ) ) {
						return '';
					}
					$sep = (string) ( $o['separator'] ?? '' );
					return implode( '' !== $sep ? $sep : ', ', wp_list_pluck( $terms, 'name' ) );
				},
			)
		);
		$tags->register(
			'product-short-description',
			array(
				'title'      => __( 'Product short description', 'uncoder' ),
				'group'      => 'woo',
				'categories' => array( 'text', 'html' ),
				'callback'   => static fn( $o, $ctx ) => ( $p = $product( $ctx ) ) && ( $ctx->editor || ! post_password_required( $p->get_id() ) ) ? wp_kses_post( (string) $p->get_short_description() ) : '',
			)
		);
		$tags->register(
			'product-image',
			array(
				'title'      => __( 'Product image', 'uncoder' ),
				'group'      => 'woo',
				'categories' => array( 'image', 'url' ),
				'controls'   => array(
					'index' => array( 'type' => 'number', 'label' => __( 'Gallery image', 'uncoder' ), 'min' => 0, 'max' => 20, 'description' => __( '0: the product image; 1, 2…: images of its gallery.', 'uncoder' ) ),
				),
				'callback'   => static function ( $o, $ctx ) use ( $product ) {
					$p     = $product( $ctx );
					$index = max( 0, (int) ( $o['index'] ?? 0 ) );
					$ids   = $p ? array_values( array_filter( array_merge( array( (int) $p->get_image_id() ), array_map( 'intval', $p->get_gallery_image_ids() ) ) ) ) : array();
					$id    = $ids[ $index ] ?? 0;
					if ( ! $id ) {
						return array( 'id' => 0, 'url' => '' );
					}
					return array(
						'id'  => $id,
						'url' => (string) wp_get_attachment_image_url( $id, 'full' ),
						'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
					);
				},
			)
		);
		$tags->register(
			'product-add-to-cart-url',
			array(
				'title'      => __( 'Add to cart link', 'uncoder' ),
				'group'      => 'woo',
				'categories' => array( 'url' ),
				'callback'   => static fn( $o, $ctx ) => ( $p = $product( $ctx ) ) ? (string) $p->add_to_cart_url() : '',
			)
		);
		$tags->register(
			'cart-count',
			array(
				'title'      => __( 'Cart item count', 'uncoder' ),
				'group'      => 'woo',
				'categories' => array( 'text', 'number' ),
				'callback'   => static fn( $o, $ctx ) => (string) self::cart( $ctx )['count'],
			)
		);
		$tags->register(
			'cart-total',
			array(
				'title'    => __( 'Cart total', 'uncoder' ),
				'group'    => 'woo',
				'callback' => static fn( $o, $ctx ) => $plain( (string) self::cart( $ctx )['total'] ),
			)
		);
		$tags->register(
			'woo-page-url',
			array(
				'title'      => __( 'Shop page link', 'uncoder' ),
				'group'      => 'woo',
				'categories' => array( 'url' ),
				'controls'   => array(
					'page' => array(
						'type'    => 'select',
						'label'   => __( 'Page', 'uncoder' ),
						'options' => array(
							'shop'      => __( 'Shop', 'uncoder' ),
							'cart'      => __( 'Cart', 'uncoder' ),
							'checkout'  => __( 'Checkout', 'uncoder' ),
							'myaccount' => __( 'My account', 'uncoder' ),
						),
					),
				),
				'callback'   => static function ( $o ) {
					$page = in_array( $o['page'] ?? '', array( 'cart', 'checkout', 'myaccount' ), true ) ? $o['page'] : 'shop';
					$id   = wc_get_page_id( $page );
					return $id > 0 ? (string) get_permalink( $id ) : (string) home_url( '/' );
				},
			)
		);
	}

	/**
	 * The visitor's cart count and total (a sample in the editor; nothing in saved content or API output).
	 *
	 * @return array{count:int,total:string}
	 */
	private static function cart( Render_Context $ctx ): array {
		if ( $ctx->editor ) {
			return array( 'count' => 2, 'total' => wc_price( 34 ) );
		}
		if ( Document::$static_render || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! function_exists( 'WC' ) || null === WC()->cart ) {
			return array( 'count' => 0, 'total' => '' );
		}
		return array( 'count' => (int) WC()->cart->get_cart_contents_count(), 'total' => (string) WC()->cart->get_total() );
	}
}
