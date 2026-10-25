<?php
/**
 * Products widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Document;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Widgets\Support\Woo_Widget;
use Uncoder\Builder\Woo\Product_Context;

defined( 'ABSPATH' ) || exit;

/**
 * A grid of products in WooCommerce's own product loop (content-product.php), so extensions that add to product
 * cards (wishlists, swatches, badges) keep working. Products come from the shop's current query (shop, categories,
 * tags, product search) or from a list: latest, featured, on sale, best selling, top rated, categories / tags, picked
 * products, related products, upsells or cross-sells. WooCommerce's shortcode engine does the querying, so its
 * visibility rules, "hide out of stock" setting and price / popularity / rating ordering apply.
 */
class Products extends Woo_Widget {

	/** A product card: WooCommerce's li.product (the five-class base the widget's CSS uses too). */
	protected const CARD = self::W . ' ul.products li.product';

	/** Sources that list related products of the product being viewed. */
	private const PRODUCT_SOURCES = array( 'related', 'upsells' );

	/** Card parts and the WooCommerce template functions that print them: [ hook, function ]. */
	private const PARTS = array(
		'image'  => array( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail' ),
		'badge'  => array( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash' ),
		'title'  => array( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title' ),
		'rating' => array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating' ),
		'price'  => array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price' ),
		'button' => array( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' ),
	);

	/** Whether cards show their second image on hover (for media() while a widget renders). */
	private static bool $hover = false;

	/** The card images of the loop being printed load lazily. */
	private static bool $lazy = false;

	public function name(): string {
		return 'products';
	}

	public function title(): string {
		return __( 'Products', 'uncoder' );
	}

	public function icon(): string {
		return 'layout-grid';
	}

	public function keywords(): array {
		return array( 'products', 'shop', 'grid', 'catalog', 'featured', 'sale', 'best selling', 'related', 'upsells', 'woocommerce' );
	}

	public function description(): string {
		return __( 'A grid of products: the shop and category listing, or latest, featured, on sale, best selling, chosen, related products and more. With pagination and sorting.', 'uncoder' );
	}

	public function frontend_styles(): array {
		return array( 'woo', 'products' );
	}

	/**
	 * The source a new widget starts with.
	 */
	protected function default_source(): string {
		return 'latest';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Products', 'uncoder' ) ) );
		$this->woo_notice();
		$this->add_control(
			'source',
			array(
				'type'    => 'select',
				'label'   => __( 'Show', 'uncoder' ),
				'default' => $this->default_source(),
				'options' => array(
					'current'     => __( 'Current listing (shop, category, search)', 'uncoder' ),
					'latest'      => __( 'Latest products', 'uncoder' ),
					'featured'    => __( 'Featured products', 'uncoder' ),
					'sale'        => __( 'Products on sale', 'uncoder' ),
					'best_selling' => __( 'Best selling', 'uncoder' ),
					'top_rated'   => __( 'Top rated', 'uncoder' ),
					'terms'       => __( 'From categories or tags', 'uncoder' ),
					'manual'      => __( 'Chosen products', 'uncoder' ),
					'related'     => __( 'Related to the product viewed', 'uncoder' ),
					'upsells'     => __( 'Upsells of the product viewed', 'uncoder' ),
					'cross_sells' => __( 'Cross-sells of the cart', 'uncoder' ),
				),
				'ai'      => 'Shop / product-archive templates: "current". Product pages: "related" or "upsells". Cart page: "cross_sells". Elsewhere a list such as "featured", "sale", "best_selling".',
			)
		);
		$this->add_control(
			'products',
			array(
				'type'      => 'multiselect',
				'label'     => __( 'Products', 'uncoder' ),
				'source'    => 'products',
				'condition' => array( 'source' => 'manual' ),
			)
		);
		$this->add_control(
			'categories',
			array(
				'type'      => 'multiselect',
				'label'     => __( 'Categories', 'uncoder' ),
				'source'    => 'product-categories',
				'condition' => array( 'source' => 'terms' ),
			)
		);
		$this->add_control(
			'tags',
			array(
				'type'      => 'multiselect',
				'label'     => __( 'Tags', 'uncoder' ),
				'source'    => 'product-tags',
				'condition' => array( 'source' => 'terms' ),
			)
		);
		$this->add_control(
			'orderby',
			array(
				'type'      => 'select',
				'label'     => __( 'Order', 'uncoder' ),
				'default'   => 'date',
				'options'   => array(
					'date'       => __( 'Newest first', 'uncoder' ),
					'popularity' => __( 'Popularity (sales)', 'uncoder' ),
					'rating'     => __( 'Average rating', 'uncoder' ),
					'price'      => __( 'Price: low to high', 'uncoder' ),
					'price-desc' => __( 'Price: high to low', 'uncoder' ),
					'title'      => __( 'Name (A to Z)', 'uncoder' ),
					'menu_order' => __( 'Custom order (menu order)', 'uncoder' ),
					'rand'       => __( 'Random', 'uncoder' ),
				),
				'condition' => array( 'source!' => array( 'current', 'best_selling', 'top_rated' ) ),
			)
		);
		$this->add_control(
			'limit',
			array(
				'type'        => 'number',
				'label'       => __( 'Number of products', 'uncoder' ),
				'default'     => 8,
				'min'         => 1,
				'max'         => 100,
				'condition'   => array( 'source!' => 'current' ),
				'description' => __( 'Per page when the list has pages.', 'uncoder' ),
			)
		);
		$this->add_control(
			'current_note',
			array(
				'type'      => 'notice',
				'label'     => __( 'Lists what the visitor is browsing, with WooCommerce\'s "Products per page" and sorting. The editor previews the latest products.', 'uncoder' ),
				'condition' => array( 'source' => 'current' ),
			)
		);
		$this->add_control(
			'heading',
			array(
				'type'        => 'text',
				'label'       => __( 'Heading', 'uncoder' ),
				'placeholder' => __( 'None', 'uncoder' ),
				'description' => __( 'Shown above the products, and hidden with them when there are none.', 'uncoder' ),
				'ai'          => 'e.g. "Related products", "You may also like…". Hidden together with an empty list.',
			)
		);
		$this->add_control(
			'heading_tag',
			array(
				'type'      => 'select',
				'label'     => __( 'Heading tag', 'uncoder' ),
				'default'   => 'h2',
				'options'   => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
				'condition' => array( 'heading!' => '' ),
			)
		);
		$this->add_control(
			'nothing_found',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Text when there are no products', 'uncoder' ),
				'rows'    => 2,
				'default' => '',
				'placeholder' => __( 'No products were found matching your selection.', 'uncoder' ),
				'description' => __( 'Related products, upsells and cross-sells show nothing when there are none.', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_responsive_control(
			'columns',
			array(
				'type'        => 'number',
				'label'       => __( 'Columns', 'uncoder' ),
				'placeholder' => 4,
				'description' => __( 'Empty: 4 on desktops, 3 on tablets, 2 on phones.', 'uncoder' ),
				'min'         => 1,
				'max'         => 8,
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-products-columns: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Column gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-products-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Row gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-products-row-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'paginate',
			array(
				'type'        => 'switch',
				'label'       => __( 'Pages', 'uncoder' ),
				'description' => __( 'Page numbers below the grid when there are more products.', 'uncoder' ),
				'condition'   => array( 'source' => array( 'latest', 'featured', 'sale', 'best_selling', 'top_rated', 'terms' ) ),
			)
		);
		$this->add_control(
			'result_count',
			array(
				'type'        => 'switch',
				'label'       => __( 'Result count', 'uncoder' ),
				'default'     => true,
				'description' => __( '"Showing 1–12 of 30 results", with pages.', 'uncoder' ),
			)
		);
		$this->add_control(
			'ordering',
			array(
				'type'        => 'switch',
				'label'       => __( 'Sorting menu', 'uncoder' ),
				'default'     => true,
				'description' => __( 'Lets shoppers sort by price, popularity…, with pages.', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_card', array( 'label' => __( 'Product card', 'uncoder' ) ) );
		$this->add_control( 'show_image', array( 'type' => 'switch', 'label' => __( 'Image', 'uncoder' ), 'default' => true ) );
		$this->add_control(
			'hover_image',
			array(
				'type'        => 'switch',
				'label'       => __( 'Second image on hover', 'uncoder' ),
				'description' => __( 'Shows the first gallery image while the pointer is over the card.', 'uncoder' ),
				'condition'   => array( 'show_image' => true ),
			)
		);
		$this->add_control( 'show_badge', array( 'type' => 'switch', 'label' => __( 'Sale badge', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'show_title', array( 'type' => 'switch', 'label' => __( 'Name', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'show_rating', array( 'type' => 'switch', 'label' => __( 'Rating', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'show_price', array( 'type' => 'switch', 'label' => __( 'Price', 'uncoder' ), 'default' => true ) );
		$this->add_control( 'show_button', array( 'type' => 'switch', 'label' => __( 'Add to cart button', 'uncoder' ), 'default' => true ) );
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
					'left'   => 'text-align:left;--uncoder-card-justify:flex-start',
					'center' => 'text-align:center;--uncoder-card-justify:center',
					'right'  => 'text-align:right;--uncoder-card-justify:flex-end',
				),
				'selectors'            => array( self::CARD => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->style_controls();
	}

	/**
	 * Style tab: card, image, name, rating, price, button, badge, pagination and the bar above the grid.
	 */
	private function style_controls(): void {
		$card = self::CARD;
		$this->start_section( 'style_card', array( 'label' => __( 'Card', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control( 'card_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $card => 'background-color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'card_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( $card => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'card_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => $card ) );
		$this->add_responsive_control(
			'card_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $card => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'card_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => $card ) );
		$this->add_group( 'card_hover_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow on hover', 'uncoder' ), 'selector' => $card . ':hover' ) );
		$this->add_responsive_control(
			'card_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between parts', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-card-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_image', array( 'label' => __( 'Image', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'show_image' => true ) ) );
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-card-image-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'image_ratio',
			array(
				'type'    => 'select',
				'label'   => __( 'Shape', 'uncoder' ),
				'options' => array(
					''     => __( 'As uploaded', 'uncoder' ),
					'1/1'  => __( 'Square', 'uncoder' ),
					'4/5'  => __( 'Portrait 4:5', 'uncoder' ),
					'3/4'  => __( 'Portrait 3:4', 'uncoder' ),
					'4/3'  => __( 'Landscape 4:3', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-card-image-ratio: {{VALUE}}' ),
			)
		);
		$this->add_control( 'image_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-card-image-bg: {{VALUE}}' ) ) );
		$this->add_control( 'image_zoom', array( 'type' => 'switch', 'label' => __( 'Zoom on hover', 'uncoder' ) ) );
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Name', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'show_title' => true ) ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $card . ' .woocommerce-loop-product__title' ) );
		$this->add_control( 'title_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( $card . ' .woocommerce-loop-product__title' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'title_hover_color', array( 'type' => 'color', 'label' => __( 'Hover color', 'uncoder' ), 'selectors' => array( $card . ' a:hover .woocommerce-loop-product__title' => 'color: {{VALUE}}' ) ) );
		$this->end_section();

		$this->start_section( 'style_price', array( 'label' => __( 'Price', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'show_price' => true ) ) );
		$this->add_group( 'price_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $card . ' .price' ) );
		$this->add_control( 'price_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( $card . ' .price' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'price_regular_color', array( 'type' => 'color', 'label' => __( 'Crossed-out price color', 'uncoder' ), 'selectors' => array( $card . ' .price del' => 'color: {{VALUE}}' ) ) );
		$this->end_section();

		$this->start_section( 'style_rating', array( 'label' => __( 'Rating', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'show_rating' => true ) ) );
		$this->add_responsive_control(
			'star_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-star-size: {{VALUE}}' ),
			)
		);
		$this->add_control( 'star_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-star-marked: {{VALUE}}' ) ) );
		$this->add_control( 'star_empty_color', array( 'type' => 'color', 'label' => __( 'Empty color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-star-unmarked: {{VALUE}}' ) ) );
		$this->end_section();

		$button = $card . ' .button';
		$this->start_section( 'style_button', array( 'label' => __( 'Button', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'show_button' => true ) ) );
		$this->add_control(
			'button_note',
			array(
				'type'  => 'notice',
				'label' => __( 'The button follows Design System → Buttons. Change it here for this widget only.', 'uncoder' ),
			)
		);
		$this->add_control( 'button_full', array( 'type' => 'switch', 'label' => __( 'Full width', 'uncoder' ) ) );
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $button ) );
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( 'button_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $button => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $button => 'background-color: {{VALUE}}' ) ) );
		$this->add_group( 'button_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => $button ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( 'button_hover_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $button . ':is(:hover, :focus-visible)' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $button . ':is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_border', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( $button . ':is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ) ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'button_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $button => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $button => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$badge = $card . ' .onsale';
		$this->start_section( 'style_badge', array( 'label' => __( 'Sale badge', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'show_badge' => true ) ) );
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

		$this->start_section( 'style_heading', array( 'label' => __( 'Heading', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'heading!' => '' ) ) );
		$this->add_group( 'heading_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-products__heading' ) );
		$this->add_control( 'heading_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-products__heading' => 'color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'heading_align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => Heading::ALIGN,
				'selectors' => array( '{{WRAPPER}} .uncoder-products__heading' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'heading_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space below', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-products__heading' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$pages = self::W . ' .woocommerce-pagination .page-numbers';
		$this->start_section( 'style_pagination', array( 'label' => __( 'Pages', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'pagination_align',
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
				'selectors'            => array( '{{WRAPPER}}' => '--uncoder-pages-justify: {{VALUE}}' ),
			)
		);
		$this->add_group( 'pagination_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $pages . ' :is(a, span)' ) );
		$this->add_control( 'pagination_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-pages-color: {{VALUE}}' ) ) );
		$this->add_control( 'pagination_active_color', array( 'type' => 'color', 'label' => __( 'Current page text', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-pages-active-color: {{VALUE}}' ) ) );
		$this->add_control( 'pagination_active_background', array( 'type' => 'color', 'label' => __( 'Current page background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-pages-active-bg: {{VALUE}}' ) ) );
		$this->add_control( 'bar_heading', array( 'type' => 'heading', 'label' => __( 'Result count and sorting', 'uncoder' ) ) );
		$this->add_group( 'bar_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .woocommerce-result-count, ' . self::W . ' .woocommerce-ordering select' ) );
		$this->add_control( 'bar_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( self::W . ' .woocommerce-result-count' => 'color: {{VALUE}}' ) ) );
		$this->end_section();
	}

	/**
	 * Card options as root classes (badge side, image effects, full-width button).
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function modifiers( array $s ): string {
		// "uncoder-products" also on widgets built on this one (Related Products), for the shared stylesheet.
		$mods = array( 'uncoder-products', 'uncoder-products--badge-' . ( 'right' === ( $s['badge_position'] ?? '' ) ? 'right' : 'left' ) );
		if ( ! empty( $s['image_zoom'] ) ) {
			$mods[] = 'uncoder-products--zoom';
		}
		if ( ! empty( $s['hover_image'] ) ) {
			$mods[] = 'uncoder-products--hover-image';
		}
		if ( ! empty( $s['button_full'] ) ) {
			$mods[] = 'uncoder-products--button-full';
		}
		return implode( ' ', $mods );
	}

	/**
	 * Products of the "related / upsells / cross-sells" sources (ids), or null for the other sources.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return int[]|null
	 */
	private function product_ids( array $s, Render_Context $ctx, string $source ): ?array {
		if ( 'manual' === $source ) {
			return array_values( array_filter( array_map( 'absint', (array) ( $s['products'] ?? array() ) ) ) );
		}
		$limit = max( 1, (int) ( $s['limit'] ?? 8 ) );
		if ( in_array( $source, self::PRODUCT_SOURCES, true ) ) {
			$product = Product_Context::product( $ctx );
			if ( ! $product ) {
				return array();
			}
			return 'related' === $source ? array_map( 'intval', wc_get_related_products( $product->get_id(), $limit ) ) : array_map( 'intval', $product->get_upsell_ids() );
		}
		if ( 'cross_sells' === $source ) {
			if ( ! $ctx->editor && ! Document::$static_render && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && function_exists( 'WC' ) && WC()->cart ) {
				return array_map( 'intval', WC()->cart->get_cross_sells() );
			}
			// The editor has no cart: the cross-sells of the preview product stand in.
			$product = $ctx->editor ? Product_Context::product( $ctx ) : null;
			return $product ? array_map( 'intval', $product->get_cross_sell_ids() ) : array();
		}
		return null;
	}

	/**
	 * WooCommerce's [products] shortcode type and attributes for the widget's list.
	 *
	 * @param array<string,mixed> $s   Settings.
	 * @param int[]|null          $ids Fixed products (related, upsells…).
	 * @return array{0:string,1:array<string,mixed>}
	 */
	private function shortcode( array $s, string $source, ?array $ids ): array {
		$types = array(
			'sale'         => 'sale_products',
			'best_selling' => 'best_selling_products',
			'top_rated'    => 'top_rated_products',
		);
		$atts  = array(
			'limit'    => max( 1, (int) ( $s['limit'] ?? 8 ) ),
			'columns'  => max( 1, (int) ( $s['columns'] ?? 4 ) ),
			'orderby'  => (string) ( $s['orderby'] ?? 'date' ),
			'order'    => in_array( $s['orderby'] ?? 'date', array( 'title', 'menu_order', 'price' ), true ) ? 'ASC' : 'DESC',
			'paginate' => ! empty( $s['paginate'] ) && null === $ids ? 'true' : 'false',
			'class'    => 'uncoder-products__loop',
			'cache'    => 'rand' === ( $s['orderby'] ?? '' ) ? 'false' : 'true',
		);
		if ( 'price-desc' === $atts['orderby'] ) {
			$atts['orderby'] = 'price';
			$atts['order']   = 'DESC';
		}
		if ( 'featured' === $source ) {
			$atts['visibility'] = 'featured';
		}
		if ( 'terms' === $source ) {
			$cats = array_values( array_filter( array_map( 'absint', (array) ( $s['categories'] ?? array() ) ) ) );
			if ( $cats ) {
				$atts['category'] = implode( ',', $cats );
			}
			$tags = array();
			foreach ( array_filter( array_map( 'absint', (array) ( $s['tags'] ?? array() ) ) ) as $tag_id ) {
				$term = get_term( $tag_id, 'product_tag' );
				if ( $term instanceof \WP_Term ) {
					$tags[] = $term->slug;
				}
			}
			if ( $tags ) {
				$atts['tag'] = implode( ',', $tags );
			}
		}
		if ( null !== $ids ) {
			// WooCommerce reads an empty list as "every product": a list with nothing in it asks for none.
			$atts['ids']   = $ids ? implode( ',', $ids ) : '0';
			$atts['limit'] = in_array( $source, self::PRODUCT_SOURCES, true ) || 'cross_sells' === $source ? $atts['limit'] : max( count( $ids ), 1 );
			if ( 'manual' === $source && 'date' === ( $s['orderby'] ?? 'date' ) ) {
				$atts['orderby'] = 'post__in';
			}
		}
		return array( $types[ $source ] ?? 'products', $atts );
	}

	/**
	 * Removes the card parts the widget hides (and the bar items), runs the callback, then puts them back.
	 *
	 * @param array<string,mixed> $s    Settings.
	 * @param bool                $lazy Card images load lazily (the widget is below the page's first section).
	 * @return mixed Callback result.
	 */
	private function with_parts( array $s, callable $callback, bool $lazy = false ) {
		$removed = array();
		foreach ( self::PARTS as $part => $hook ) {
			if ( empty( $s[ 'show_' . $part ] ) ) {
				$priority = has_action( $hook[0], $hook[1] );
				if ( false !== $priority ) {
					remove_action( $hook[0], $hook[1], (int) $priority );
					$removed[] = array( $hook[0], $hook[1], (int) $priority );
				}
			}
		}
		foreach ( array( 'result_count' => 'woocommerce_result_count', 'ordering' => 'woocommerce_catalog_ordering' ) as $key => $fn ) {
			if ( empty( $s[ $key ] ) ) {
				$priority = has_action( 'woocommerce_before_shop_loop', $fn );
				if ( false !== $priority ) {
					remove_action( 'woocommerce_before_shop_loop', $fn, (int) $priority );
					$removed[] = array( 'woocommerce_before_shop_loop', $fn, (int) $priority );
				}
			}
		}
		// The image in a frame (shape, rounded corners, zoom, the second image on hover).
		$added = array();
		list( $hook, $fn ) = self::PARTS['image'];
		$priority          = ! empty( $s['show_image'] ) ? has_action( $hook, $fn ) : false;
		if ( false !== $priority ) {
			remove_action( $hook, $fn, (int) $priority );
			$removed[] = array( $hook, $fn, (int) $priority );
			add_action( $hook, array( self::class, 'media' ), (int) $priority );
			$added[] = array( $hook, array( self::class, 'media' ), (int) $priority );
		}
		$prev        = array( self::$hover, self::$lazy );
		self::$hover = ! empty( $s['hover_image'] );
		self::$lazy  = $lazy;
		try {
			return $callback();
		} finally {
			foreach ( $added as $hook ) {
				remove_action( $hook[0], $hook[1], $hook[2] );
			}
			foreach ( $removed as $hook ) {
				add_action( $hook[0], $hook[1], $hook[2] );
			}
			list( self::$hover, self::$lazy ) = $prev;
		}
	}

	/**
	 * A card's image: WooCommerce's thumbnail (its filters apply) in a frame, with the first gallery image over it
	 * for "Second image on hover".
	 */
	public static function media(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$second = '';
		if ( self::$hover ) {
			$ids    = $product->get_gallery_image_ids();
			$second = $ids ? wp_get_attachment_image( (int) $ids[0], 'woocommerce_thumbnail', false, array( 'class' => 'uncoder-products__hover-image', 'alt' => '', 'loading' => 'lazy', 'aria-hidden' => 'true' ) ) : '';
		}
		if ( self::$lazy ) {
			add_filter( 'wp_get_attachment_image_attributes', array( self::class, 'lazy_attrs' ) );
		}
		$thumb = woocommerce_get_product_thumbnail();
		remove_filter( 'wp_get_attachment_image_attributes', array( self::class, 'lazy_attrs' ) );
		echo '<span class="uncoder-products__media">' . $thumb . $second . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's and core image markup.
	}

	/**
	 * Cards below the page's first section: their images load as they come near the screen (WordPress leaves
	 * images outside the post content as they are).
	 *
	 * @param array<string,string> $attr Image attributes.
	 * @return array<string,string>
	 */
	public static function lazy_attrs( $attr ): array {
		$attr = (array) $attr;
		if ( empty( $attr['loading'] ) && empty( $attr['fetchpriority'] ) ) {
			$attr['loading']  = 'lazy';
			$attr['decoding'] = 'async';
		}
		return $attr;
	}

	/**
	 * The current listing (shop, categories, tags, product search): the main query in WooCommerce's shop loop.
	 */
	private function current_listing(): string {
		ob_start();
		if ( woocommerce_product_loop() ) {
			do_action( 'woocommerce_before_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
			woocommerce_product_loop_start();
			if ( wc_get_loop_prop( 'total' ) ) {
				while ( have_posts() ) {
					the_post();
					do_action( 'woocommerce_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
					wc_get_template_part( 'content', 'product' );
				}
			}
			woocommerce_product_loop_end();
			do_action( 'woocommerce_after_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
		} else {
			do_action( 'woocommerce_no_products_found' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
		}
		// Leave the loop as found: another listing widget (a copy for phones) and the widgets after it start afresh.
		wc_reset_loop();
		wp_reset_postdata();
		return '<div class="uncoder-products__loop">' . ob_get_clean() . '</div>';
	}

	/**
	 * Whether the page is one of WooCommerce's product listings (its main query lists products).
	 */
	private static function on_listing(): bool {
		return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		if ( ! self::active() ) {
			$this->placeholder( $ctx );
			return;
		}
		$source = (string) ( $s['source'] ?? $this->default_source() );
		$html   = '';
		$listed = false;

		if ( 'current' === $source && ! $ctx->editor && self::on_listing() ) {
			// An empty listing shows the widget's own "nothing found" text when it has one.
			if ( ! have_posts() && '' !== trim( (string) ( $s['nothing_found'] ?? '' ) ) ) {
				$this->nothing( $s, $ctx, $source );
				return;
			}
			wc_set_loop_prop( 'columns', max( 1, (int) ( $s['columns'] ?? 4 ) ) );
			$html   = $this->with_parts( $s, fn() => $this->current_listing(), ! $ctx->eager );
			$listed = true;
		} else {
			if ( 'current' === $source ) {
				// Outside a listing (the editor, a page): the latest products, with the listing's bar and pages.
				$s['paginate'] = true;
				$s['limit']    = (int) apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
				$source        = 'latest';
			}
			$ids = $this->product_ids( $s, $ctx, $source );
			if ( null !== $ids && ! $ids ) {
				$this->nothing( $s, $ctx, $source );
				return;
			}
			list( $type, $atts ) = $this->shortcode( $s, $source, $ids );
			if ( ! array_key_exists( 'post', $GLOBALS ) ) {
				$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's loop reads and restores it.
			}
			$shortcode = new \WC_Shortcode_Products( $atts, $type );
			$html      = $this->with_parts( $s, static fn() => (string) $shortcode->get_content(), ! $ctx->eager && ! $ctx->editor );
			// The shortcode prints an empty wrapper when nothing matches.
			$listed = false !== strpos( $html, '<li' );
		}

		if ( ! $listed ) {
			$this->nothing( $s, $ctx, $source );
			return;
		}
		echo '<div class="' . esc_attr( $this->root_class( $this->modifiers( $s ) ) ) . '">' . $this->heading( $s ) . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped heading, WooCommerce's product loop templates.
	}

	/**
	 * The heading above the products (escaped), or ''.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function heading( array $s ): string {
		$text = trim( (string) ( $s['heading'] ?? '' ) );
		if ( '' === $text ) {
			return '';
		}
		$tag = Utils::tag( $s['heading_tag'] ?? 'h2', Utils::HEADING_TAGS, 'h2' );
		return '<' . $tag . ' class="uncoder-products__heading">' . esc_html( $text ) . '</' . $tag . '>';
	}

	/**
	 * No products: the widget's text (lists of the product viewed print nothing, as WooCommerce's sections do).
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function nothing( array $s, Render_Context $ctx, string $source ): void {
		$text = trim( (string) ( $s['nothing_found'] ?? '' ) );
		if ( in_array( $source, array( 'related', 'upsells', 'cross_sells' ), true ) && '' === $text ) {
			$this->placeholder( $ctx, __( 'No products to list here for the preview product. On the site this widget then shows nothing.', 'uncoder' ) );
			return;
		}
		$text = '' !== $text ? $text : __( 'No products were found matching your selection.', 'uncoder' );
		echo '<div class="' . esc_attr( $this->root_class( $this->modifiers( $s ) ) ) . '">' . $this->heading( $s ) . '<p class="woocommerce-info woocommerce-no-products-found">' . esc_html( $text ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped heading.
	}
}
