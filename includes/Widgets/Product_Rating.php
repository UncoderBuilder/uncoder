<?php
/**
 * Product rating widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Widgets\Support\Stars;
use Uncoder\Builder\Widgets\Support\Woo_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * The product's average rating as stars, with the number of reviews linking to the reviews (the Product Tabs widget
 * opens its Reviews tab for that link).
 */
class Product_Rating extends Woo_Widget {

	public function name(): string {
		return 'product-rating';
	}

	public function title(): string {
		return __( 'Product Rating', 'uncoder' );
	}

	public function icon(): string {
		return 'star';
	}

	public function keywords(): array {
		return array( 'rating', 'stars', 'reviews', 'product', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The average customer rating of the product as stars, with the number of reviews.', 'uncoder' );
	}

	public function frontend_styles(): array {
		return array( 'woo', 'star-rating' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Rating', 'uncoder' ) ) );
		$this->add_control(
			'show_average',
			array(
				'type'  => 'switch',
				'label' => __( 'Average as a number', 'uncoder' ),
			)
		);
		$this->add_control(
			'show_count',
			array(
				'type'    => 'switch',
				'label'   => __( 'Number of reviews', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'count_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Reviews text', 'uncoder' ),
				'placeholder' => __( '(%s customer reviews)', 'uncoder' ),
				'description' => __( '%s is the number of reviews.', 'uncoder' ),
				'condition'   => array( 'show_count' => true ),
			)
		);
		$this->add_control(
			'show_empty',
			array(
				'type'        => 'switch',
				'label'       => __( 'Show without reviews', 'uncoder' ),
				'description' => __( 'Empty stars and a "No reviews yet" note while the product has no reviews.', 'uncoder' ),
			)
		);
		$this->add_control(
			'empty_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Text without reviews', 'uncoder' ),
				'placeholder' => __( 'No reviews yet', 'uncoder' ),
				'condition'   => array( 'show_empty' => true ),
			)
		);
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
					'left'   => 'flex-start',
					'center' => 'center',
					'right'  => 'flex-end',
				),
				'selectors'            => array( '{{WRAPPER}}' => 'justify-content: {{VALUE}}' ),
			)
		);
		$this->end_section();
		$this->product_section();

		$this->start_section( 'style_stars', array( 'label' => __( 'Stars', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'star_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-star-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'star_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-star-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'star_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-star-marked: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'star_empty_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Empty color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-star-unmarked: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'star_style',
			array(
				'type'    => 'select',
				'label'   => __( 'Empty stars', 'uncoder' ),
				'default' => 'solid',
				'options' => array(
					'solid'   => __( 'Filled', 'uncoder' ),
					'outline' => __( 'Outline', 'uncoder' ),
				),
			)
		);
		$this->end_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'text_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-product-rating__text' ) );
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-product-rating__text' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} a.uncoder-product-rating__text:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'text_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space after the stars', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => 'gap: {{VALUE}}' ),
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
		if ( ! wc_review_ratings_enabled() ) {
			$this->placeholder( $ctx, __( 'Star ratings are turned off in WooCommerce → Settings → Products.', 'uncoder' ) );
			return;
		}
		$count   = (int) $product->get_review_count();
		$average = Stars::normalize( $product->get_average_rating(), 5 );
		if ( ! $product->get_rating_count() ) {
			if ( empty( $s['show_empty'] ) ) {
				$this->placeholder( $ctx, __( 'This product has no reviews yet: the rating shows once it has one (or turn on "Show without reviews").', 'uncoder' ) );
				return;
			}
			$average = 0.0;
		}

		$parts = Stars::render( $average, 5, 'star', 'outline' === ( $s['star_style'] ?? '' ) ? 'outline' : 'solid' );
		if ( ! empty( $s['show_average'] ) && $average > 0 ) {
			$parts .= '<span class="uncoder-product-rating__average">' . esc_html( Stars::format( $average ) ) . '</span>';
		}
		if ( 0 === $count && ! empty( $s['show_empty'] ) ) {
			$empty  = trim( (string) ( $s['empty_text'] ?? '' ) );
			$parts .= '<span class="uncoder-product-rating__text">' . esc_html( '' !== $empty ? $empty : __( 'No reviews yet', 'uncoder' ) ) . '</span>';
		} elseif ( $count > 0 && ! empty( $s['show_count'] ) ) {
			$custom = trim( (string) ( $s['count_text'] ?? '' ) );
			/* translators: %s: number of reviews. */
			$format = '' !== $custom ? $custom : _n( '(%s customer review)', '(%s customer reviews)', $count, 'uncoder' );
			$text   = str_contains( $format, '%s' ) ? str_replace( '%s', '<span class="count">' . esc_html( number_format_i18n( $count ) ) . '</span>', esc_html( $format ) ) : esc_html( $format );
			// "#reviews" as in WooCommerce: its script and the Product Tabs widget open the Reviews tab for it. Away
			// from the product's own page (a product grid, a landing page) the link leads to the product's reviews.
			$own    = $ctx->editor || ( is_singular( 'product' ) && (int) get_queried_object_id() === $product->get_id() );
			$href   = $own ? '#reviews' : get_permalink( $product->get_id() ) . '#reviews';
			$parts .= comments_open( $product->get_id() )
				? '<a' . Utils::attrs( array( 'href' => $href, 'class' => 'uncoder-product-rating__text woocommerce-review-link', 'rel' => 'nofollow' ) ) . '>' . $text . '</a>'
				: '<span class="uncoder-product-rating__text">' . $text . '</span>';
		}
		echo '<div class="' . esc_attr( $this->root_class( 'woocommerce-product-rating' ) ) . '">' . $parts . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	}
}
