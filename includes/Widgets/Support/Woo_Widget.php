<?php
/**
 * Base for the WooCommerce widgets.
 *
 * Not a widget: helpers in includes/Widgets/Support are never registered.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce widgets are always registered, so their elements survive while WooCommerce is switched off (saving a
 * page never drops them). Without WooCommerce they print nothing on the site and a short note in the editor, and
 * the Insert panel leaves them out.
 *
 * Their root carries `woocommerce` (WooCommerce's styles and scripts look for it, as in its shortcodes) and
 * `uncoder-woo` (the Design System's button and field styles cover WooCommerce's buttons and fields inside it).
 * WooCommerce's stylesheet uses long selectors, so style controls start from self::W ("{{WRAPPER}}.woocommerce",
 * three classes) and add two more classes where WooCommerce's rule is longer.
 */
abstract class Woo_Widget extends Widget_Base {

	use Woo_Parts;

	/** The widget's root in style selectors. */
	protected const W = '{{WRAPPER}}.woocommerce';

	public function category(): string {
		return 'woo';
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	public function frontend_styles(): array {
		return array( 'woo' );
	}
}
