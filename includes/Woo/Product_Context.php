<?php
/**
 * The product a WooCommerce widget shows.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Woo;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Product widgets never trust "whatever global $product was set last" (WordPress also keeps the product slug in that
 * global): they ask for the product of the render, switch the globals to it while WooCommerce's template functions
 * run, and put the previous ones back.
 */
final class Product_Context {

	/** @var \WC_Product|null|false Sample product for the editor (false = not looked up yet). */
	private static $sample = false;

	/**
	 * The product to show: the chosen one (a product picked in the widget), else the post being rendered (a product
	 * page, a product in a loop), else in the editor the latest product as a sample. A chosen product that is gone or
	 * not visible (deleted, draft, private) gives none: the widget does not quietly show another product instead.
	 *
	 * @param mixed $chosen A product id picked in the widget (0 / empty = the current product).
	 */
	public static function product( Render_Context $ctx, $chosen = 0 ): ?\WC_Product {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$chosen = absint( is_array( $chosen ) ? ( $chosen[0] ?? 0 ) : $chosen );
		if ( $chosen ) {
			$product = wc_get_product( $chosen );
			return $product instanceof \WC_Product && ( 'publish' === $product->get_status() || current_user_can( 'read_post', $chosen ) ) ? $product : null;
		}
		$post = Theme_Context::post( $ctx );
		if ( $post && 'product' === $post->post_type ) {
			$product = wc_get_product( $post );
			return $product instanceof \WC_Product ? $product : null;
		}
		return $ctx->editor ? self::sample() : null;
	}

	/**
	 * The latest published product (shown by product widgets while designing outside a product).
	 */
	public static function sample(): ?\WC_Product {
		if ( false === self::$sample ) {
			$ids          = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'status' => 'publish', 'limit' => 1, 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
			self::$sample = $ids ? wc_get_product( (int) $ids[0] ) : null;
		}
		return self::$sample instanceof \WC_Product ? self::$sample : null;
	}

	/**
	 * Runs a callback with the globals WooCommerce's templates read ($post, $product) switched to a product, then puts
	 * the previous ones back.
	 *
	 * @param callable $callback Callback.
	 * @return mixed Callback result.
	 */
	public static function with_product( \WC_Product $product, callable $callback ) {
		$post = get_post( $product->get_id() );
		if ( ! $post instanceof \WP_Post ) {
			return $callback();
		}
		$previous = $GLOBALS['product'] ?? null;
		try {
			// setup_postdata() fires "the_post", where WooCommerce sets $product for the post.
			return Theme_Context::with_post(
				$post,
				static function () use ( $product, $callback ) {
					$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
					return $callback();
				}
			);
		} finally {
			$GLOBALS['product'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Output of a callback that prints, with the product's globals in place.
	 *
	 * @param callable $callback Callback that echoes.
	 */
	public static function capture( \WC_Product $product, callable $callback ): string {
		return (string) self::with_product(
			$product,
			static function () use ( $callback ) {
				$level = ob_get_level();
				ob_start();
				try {
					$callback();
					return (string) ob_get_clean();
				} finally {
					// A callback that threw leaves no output buffer open (it would end up in front of a REST answer).
					while ( ob_get_level() > $level ) {
						ob_end_clean();
					}
				}
			}
		);
	}
}
