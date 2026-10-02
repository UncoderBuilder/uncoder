<?php
/**
 * Timeline widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Vertical timeline of dated events: alternating around a centre line, or with the line on one side.
 */
class Timeline extends Widget_Base {

	public function name(): string {
		return 'timeline';
	}

	public function title(): string {
		return __( 'Timeline', 'uncoder' );
	}

	public function icon(): string {
		return 'milestone';
	}

	public function category(): string {
		return 'content';
	}

	public function keywords(): array {
		return array( 'timeline', 'history', 'milestones', 'events', 'roadmap', 'story' );
	}

	public function description(): string {
		return __( 'Vertical timeline of events (date, title, text, optional icon). Layouts: alternating around a centre line (stacks on phones), line on the left or right. Items fade in as they scroll into view.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'timeline' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content_items', array( 'label' => __( 'Events', 'uncoder' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Events', 'uncoder' ),
				'title_field' => 'title',
				'fields'      => array(
					'date'        => array(
						'type'    => 'text',
						'label'   => __( 'Date', 'uncoder' ),
						'default' => '2025',
					),
					'title'       => array(
						'type'    => 'text',
						'label'   => __( 'Title', 'uncoder' ),
						'default' => __( 'Milestone', 'uncoder' ),
					),
					'description' => array(
						'type'  => 'textarea',
						'label' => __( 'Description', 'uncoder' ),
						'html'  => 'inline',
						'rows'  => 3,
					),
					'icon'        => array(
						'type'        => 'icon',
						'label'       => __( 'Icon', 'uncoder' ),
						'description' => __( 'Shown when the marker style is "Icon".', 'uncoder' ),
					),
					'color'       => array(
						'type'      => 'color',
						'label'     => __( 'Marker color', 'uncoder' ),
						'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => '--uncoder-tl-accent: {{VALUE}}' ),
					),
				),
				'default'     => array(
					array(
						'date'        => '2019',
						'title'       => __( 'Founded in a spare bedroom', 'uncoder' ),
						'description' => __( 'Two designers set out to make publishing effortless for small teams.', 'uncoder' ),
						'icon'        => array( 'library' => 'lucide', 'value' => 'lightbulb' ),
					),
					array(
						'date'        => '2021',
						'title'       => __( 'First 10,000 customers', 'uncoder' ),
						'description' => __( 'Word of mouth carried us to 40 countries without a sales team.', 'uncoder' ),
						'icon'        => array( 'library' => 'lucide', 'value' => 'users' ),
					),
					array(
						'date'        => '2023',
						'title'       => __( 'A new studio in Lisbon', 'uncoder' ),
						'description' => __( 'We opened our second office and doubled the product team.', 'uncoder' ),
						'icon'        => array( 'library' => 'lucide', 'value' => 'flag' ),
					),
					array(
						'date'        => '2025',
						'title'       => __( 'Assisted site building', 'uncoder' ),
						'description' => __( 'Pages that draft, design and optimise themselves while you stay in control.', 'uncoder' ),
						'icon'        => array( 'library' => 'lucide', 'value' => 'rocket' ),
					),
				),
				'ai'          => 'Rows: {"date":"2021","title":"…","description":"…","icon":"lucide-name"}. Keep 3–8 events in chronological order.',
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
		$this->end_section();

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'alternate',
				'options' => array(
					'alternate' => __( 'Alternating (centre line)', 'uncoder' ),
					'left'      => __( 'Line on the left', 'uncoder' ),
					'right'     => __( 'Line on the right', 'uncoder' ),
				),
				'ai'      => 'alternate switches to the left layout below 768px.',
			)
		);
		$this->add_control(
			'marker',
			array(
				'type'    => 'select',
				'label'   => __( 'Marker', 'uncoder' ),
				'default' => 'dot',
				'options' => array(
					'dot'    => __( 'Dot', 'uncoder' ),
					'ring'   => __( 'Ring', 'uncoder' ),
					'icon'   => __( 'Icon', 'uncoder' ),
					'number' => __( 'Number', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'card_arrow',
			array(
				'type'    => 'switch',
				'label'   => __( 'Card pointer', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'reveal',
			array(
				'type'    => 'select',
				'label'   => __( 'Entrance animation', 'uncoder' ),
				'default' => 'fade-up',
				'options' => array(
					''        => __( 'None', 'uncoder' ),
					'fade-up' => __( 'Fade up', 'uncoder' ),
					'fade-in' => __( 'Fade in', 'uncoder' ),
					'slide'   => __( 'Slide from the side', 'uncoder' ),
				),
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_layout', array( 'label' => __( 'Spacing & line', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between events', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-tl-row-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'column_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space around the line', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-tl-col-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'line_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Line color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-tl-line: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'line_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Line width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 12 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-tl-line-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'line_style',
			array(
				'type'      => 'select',
				'label'     => __( 'Line style', 'uncoder' ),
				'options'   => array(
					''       => __( 'Solid', 'uncoder' ),
					'dashed' => __( 'Dashed', 'uncoder' ),
					'dotted' => __( 'Dotted', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-tl-line-style: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_marker', array( 'label' => __( 'Marker', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'marker_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 96 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-tl-marker: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'marker_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-tl-accent: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'marker_content_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon / number color', 'uncoder' ),
				'condition' => array( 'marker' => array( 'icon', 'number' ) ),
				'selectors' => array( '{{WRAPPER}} .uncoder-timeline__marker' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'marker_ring',
			array(
				'type'      => 'color',
				'label'     => __( 'Halo color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-tl-halo: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'marker_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'marker' => 'icon' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-timeline__marker .uncoder-svg' => 'width: {{VALUE}}; height: {{VALUE}}' ),
			)
		);
		$this->add_group( 'number_typography', array( 'type' => 'typography', 'label' => __( 'Number typography', 'uncoder' ), 'condition' => array( 'marker' => 'number' ), 'selector' => '{{WRAPPER}} .uncoder-timeline__marker' ) );
		$this->end_section();

		$this->start_section( 'style_card', array( 'label' => __( 'Card', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'card_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-tl-card-bg: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-timeline__card' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-timeline__card' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'card_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-timeline__card' ) );
		$this->add_group( 'card_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-timeline__card' ) );
		$this->end_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'date_typography', array( 'type' => 'typography', 'label' => __( 'Date typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-timeline__date' ) );
		$this->add_control(
			'date_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Date color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-timeline__date' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-timeline__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Title color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-timeline__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Title spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-timeline__title' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_group( 'description_typography', array( 'type' => 'typography', 'label' => __( 'Description typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-timeline__description' ) );
		$this->add_control(
			'description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Description color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-timeline__description' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = is_array( $s['items'] ?? null ) ? array_values( array_filter( $s['items'], 'is_array' ) ) : array();
		if ( ! $rows ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-timeline__empty">' . esc_html__( 'Add events to the timeline.', 'uncoder' ) . '</p>';
			}
			return;
		}
		$layout = in_array( $s['layout'] ?? 'alternate', array( 'alternate', 'left', 'right' ), true ) ? $s['layout'] : 'alternate';
		$marker = in_array( $s['marker'] ?? 'dot', array( 'dot', 'ring', 'icon', 'number' ), true ) ? $s['marker'] : 'dot';
		$reveal = in_array( $s['reveal'] ?? '', array( 'fade-up', 'fade-in', 'slide' ), true ) ? $s['reveal'] : '';
		$tag    = Utils::tag( $s['title_tag'] ?? 'h3', Utils::HEADING_TAGS, 'h3' );

		$classes = array( 'uncoder-timeline', 'uncoder-timeline--' . $layout, 'uncoder-timeline--marker-' . $marker );
		if ( ! empty( $s['card_arrow'] ) ) {
			$classes[] = 'uncoder-timeline--arrow';
		}
		if ( '' !== $reveal ) {
			$classes[] = 'uncoder-timeline--reveal';
			$classes[] = 'uncoder-timeline--reveal-' . $reveal;
		}

		echo '<ol class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		foreach ( $rows as $i => $row ) {
			$item = array( 'uncoder-timeline__item' );
			if ( ! empty( $row['_id'] ) && is_string( $row['_id'] ) ) {
				$item[] = 'uncoder-ri-' . sanitize_html_class( $row['_id'] );
			}
			$inner = '';
			if ( 'icon' === $marker && $this->has_icon( $row['icon'] ?? null ) ) {
				$inner = $this->render_icon( $row['icon'] );
			} elseif ( 'number' === $marker ) {
				$inner = esc_html( (string) ( $i + 1 ) );
			}
			$date  = trim( (string) ( $row['date'] ?? '' ) );
			$title = trim( (string) ( $row['title'] ?? '' ) );
			$desc  = $this->inline_html( $row['description'] ?? '' );

			echo '<li class="' . esc_attr( implode( ' ', $item ) ) . '">';
			echo '<div class="uncoder-timeline__marker" aria-hidden="true">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped icon / number.
			if ( '' !== $date ) {
				echo '<p class="uncoder-timeline__date">' . esc_html( $date ) . '</p>';
			}
			echo '<div class="uncoder-timeline__card">';
			if ( '' !== $title ) {
				echo '<' . $tag . ' class="uncoder-timeline__title">' . esc_html( $title ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag.
			}
			if ( '' !== trim( wp_strip_all_tags( $desc ) ) ) {
				echo '<p class="uncoder-timeline__description">' . $desc . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
			}
			echo '</div></li>';
		}
		echo '</ol>';
	}
}
