<?php
/**
 * Price List widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Menu-style list of items with a price, optional image, description and link, joined by a dotted leader.
 */
class Price_List extends Widget_Base {

	public function name(): string {
		return 'price-list';
	}

	public function title(): string {
		return __( 'Price List', 'uncoder' );
	}

	public function icon(): string {
		return 'receipt';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'price', 'menu', 'list', 'services', 'restaurant', 'rates' );
	}

	public function description(): string {
		return __( 'Menu-style list: each item has a title, price, optional description, image and link; a dotted leader joins title and price. Good for restaurant menus and service rates.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content_items', array( 'label' => __( 'Items', 'uncoder' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Items', 'uncoder' ),
				'title_field' => 'title',
				'fields'      => array(
					'title'       => array(
						'type'    => 'text',
						'label'   => __( 'Title', 'uncoder' ),
						'default' => __( 'Item', 'uncoder' ),
					),
					'price'       => array(
						'type'    => 'text',
						'label'   => __( 'Price', 'uncoder' ),
						'default' => '$10',
					),
					'description' => array(
						'type'  => 'textarea',
						'label' => __( 'Description', 'uncoder' ),
						'html'  => 'inline',
						'rows'  => 2,
					),
					'image'       => array(
						'type'  => 'media',
						'label' => __( 'Image', 'uncoder' ),
					),
					'link'        => array(
						'type'  => 'url',
						'label' => __( 'Link', 'uncoder' ),
					),
				),
				'default'     => array(
					array(
						'title'       => __( 'Flat white', 'uncoder' ),
						'price'       => '$4.20',
						'description' => __( 'Double ristretto with silky steamed milk.', 'uncoder' ),
					),
					array(
						'title'       => __( 'Cold brew', 'uncoder' ),
						'price'       => '$4.80',
						'description' => __( 'Steeped for 18 hours and served over ice.', 'uncoder' ),
					),
					array(
						'title'       => __( 'Almond croissant', 'uncoder' ),
						'price'       => '$5.50',
						'description' => __( 'Baked in-house every morning with toasted almonds.', 'uncoder' ),
					),
				),
				'ai'          => 'Rows: {"title":"…","price":"$4.20","description":"optional","image":{"id":…},"link":{"url":"…"}}. The price is free text, include the currency.',
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'h3',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control(
			'image_size',
			array(
				'type'            => 'select',
				'label'           => __( 'Image resolution', 'uncoder' ),
				'default'         => 'thumbnail',
				'options_dynamic' => true,
				'options'         => array(
					'thumbnail'    => 'Thumbnail',
					'medium'       => 'Medium',
					'medium_large' => 'Medium large',
					'large'        => 'Large',
					'full'         => 'Full',
				),
			)
		);
		$this->end_section();

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_responsive_control(
			'columns',
			array(
				'type'      => 'select',
				'label'     => __( 'Columns', 'uncoder' ),
				'options'   => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-plist-cols: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'price_position',
			array(
				'type'    => 'select',
				'label'   => __( 'Price position', 'uncoder' ),
				'default' => 'inline',
				'options' => array(
					'inline' => __( 'On the title line', 'uncoder' ),
					'below'  => __( 'Below the title', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'leader',
			array(
				'type'      => 'select',
				'label'     => __( 'Leader line', 'uncoder' ),
				'default'   => 'dotted',
				'options'   => array(
					'none'   => __( 'None', 'uncoder' ),
					'dotted' => __( 'Dotted', 'uncoder' ),
					'dashed' => __( 'Dashed', 'uncoder' ),
					'solid'  => __( 'Solid', 'uncoder' ),
				),
				'condition' => array( 'price_position' => 'inline' ),
			)
		);
		$this->add_control(
			'image_position',
			array(
				'type'    => 'choose',
				'label'   => __( 'Image position', 'uncoder' ),
				'default' => 'left',
				'options' => array(
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'panel-left' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'panel-right' ),
					'top'   => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'panel-top' ),
				),
			)
		);
		$this->add_control(
			'dividers',
			array(
				'type'    => 'switch',
				'label'   => __( 'Dividers between items', 'uncoder' ),
				'default' => false,
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_list', array( 'label' => __( 'List', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between items', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'row-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'column_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between columns', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'column-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'item_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Item padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-list__item' => 'padding: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'item_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'item_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Item background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-list__item' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'item_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Item background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-list__item:hover' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'item_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Item radius', 'uncoder' ),
				'size_units' => array( 'px', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-list__item' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'condition' => array( 'dividers' => true ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-plist-divider: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'vertical_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Image vertical alignment', 'uncoder' ),
				'options'              => array(
					'top'    => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'align-start-horizontal' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center-horizontal' ),
				),
				'selectors_dictionary' => array(
					'top'    => 'align-items:flex-start',
					'center' => 'align-items:center',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-price-list__item' => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_image', array( 'label' => __( 'Image', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'image_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 400 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-plist-img: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'image_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Aspect ratio', 'uncoder' ),
				'options'   => array(
					''     => __( 'Square', 'uncoder' ),
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'3/4'  => '3:4',
					'auto' => __( 'Original', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-list__image' => 'aspect-ratio: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-list__image' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'image_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap to text', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-list__item' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Title', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-list__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-list__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'title_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Linked item hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-list__item--linked:hover .uncoder-price-list__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_price', array( 'label' => __( 'Price', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'price_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-list__price' ) );
		$this->add_control(
			'price_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-list__price' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'price_background',
			array(
				'type'        => 'color',
				'label'       => __( 'Background', 'uncoder' ),
				'description' => __( 'Turns the price into a small chip.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}} .uncoder-price-list__price' => 'background-color: {{VALUE}}; padding: 0.2em 0.6em; border-radius: 999px' ),
			)
		);
		$this->add_control( 'leader_heading', array( 'type' => 'heading', 'label' => __( 'Leader line', 'uncoder' ), 'condition' => array( 'leader!' => 'none', 'price_position' => 'inline' ) ) );
		$this->add_control(
			'leader_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'condition' => array( 'leader!' => 'none', 'price_position' => 'inline' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-plist-leader-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'leader_weight',
			array(
				'type'       => 'slider',
				'label'      => __( 'Weight', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 8 ) ),
				'condition'  => array( 'leader!' => 'none', 'price_position' => 'inline' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-plist-leader-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'leader_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space around', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'leader!' => 'none', 'price_position' => 'inline' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-list__leader' => 'margin-inline: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_description', array( 'label' => __( 'Description', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'description_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-list__description' ) );
		$this->add_control(
			'description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-list__description' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'description_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing above', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-list__description' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = is_array( $s['items'] ?? null ) ? array_values( array_filter( $s['items'], 'is_array' ) ) : array();
		if ( ! $rows ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-price-list__empty">' . esc_html__( 'Add items to the price list.', 'uncoder' ) . '</p>';
			}
			return;
		}
		$tag      = Utils::tag( $s['title_tag'] ?? 'h3', Utils::HEADING_TAGS, 'h3' );
		$size     = sanitize_key( (string) ( $s['image_size'] ?? 'thumbnail' ) );
		$inline   = 'below' !== ( $s['price_position'] ?? 'inline' );
		$leader   = in_array( $s['leader'] ?? 'dotted', array( 'none', 'dotted', 'dashed', 'solid' ), true ) ? $s['leader'] : 'dotted';
		$position = in_array( $s['image_position'] ?? 'left', array( 'left', 'right', 'top' ), true ) ? $s['image_position'] : 'left';

		$classes = array( 'uncoder-price-list', 'uncoder-price-list--image-' . $position, 'uncoder-price-list--price-' . ( $inline ? 'inline' : 'below' ) );
		if ( $inline && 'none' !== $leader ) {
			$classes[] = 'uncoder-price-list--leader-' . $leader;
		}
		if ( ! empty( $s['dividers'] ) ) {
			$classes[] = 'uncoder-price-list--dividers';
		}

		echo '<ul class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		foreach ( $rows as $row ) {
			$title = trim( (string) ( $row['title'] ?? '' ) );
			$price = trim( (string) ( $row['price'] ?? '' ) );
			$desc  = $this->inline_html( $row['description'] ?? '' );
			$img   = $this->image( $row['image'] ?? array(), $size, array( 'class' => 'uncoder-price-list__image' ) );
			$link  = $this->link_attrs( $row['link'] ?? array() );

			$item = array( 'uncoder-price-list__item' );
			if ( ! empty( $row['_id'] ) && is_string( $row['_id'] ) ) {
				$item[] = 'uncoder-ri-' . sanitize_html_class( $row['_id'] );
			}
			if ( '' !== $img ) {
				$item[] = 'uncoder-price-list__item--has-image';
			}
			if ( $link ) {
				$item[] = 'uncoder-price-list__item--linked';
			}

			$title_html = esc_html( $title );
			if ( $link ) {
				$link['class'] = 'uncoder-price-list__link';
				$title_html    = '<a' . Utils::attrs( $link ) . '>' . $title_html . '</a>';
			}

			echo '<li class="' . esc_attr( implode( ' ', $item ) ) . '">';
			echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
			echo '<div class="uncoder-price-list__body"><div class="uncoder-price-list__header">';
			echo '<' . $tag . ' class="uncoder-price-list__title">' . $title_html . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, escaped content.
			if ( '' !== $price ) {
				if ( $inline && 'none' !== $leader ) {
					echo '<span class="uncoder-price-list__leader" aria-hidden="true"></span>';
				}
				echo '<span class="uncoder-price-list__price">' . esc_html( $price ) . '</span>';
			}
			echo '</div>';
			if ( '' !== trim( wp_strip_all_tags( $desc ) ) ) {
				echo '<p class="uncoder-price-list__description">' . $desc . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
			}
			echo '</div></li>';
		}
		echo '</ul>';
	}
}
