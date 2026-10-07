<?php
/**
 * Progress bar widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A labelled bar filled to a percentage; the fill animates when it scrolls into view.
 */
class Progress_Bar extends Widget_Base {

	public function name(): string {
		return 'progress-bar';
	}

	public function title(): string {
		return __( 'Progress Bar', 'uncoder' );
	}

	public function icon(): string {
		return 'chart-bar';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'progress', 'bar', 'skill', 'percentage', 'level', 'meter' );
	}

	public function description(): string {
		return __( 'A labelled bar filled to a percentage, e.g. skills or goals. The fill animates when it scrolls into view.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'progress' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Progress bar', 'uncoder' ) ) );
		$this->add_control(
			'title',
			array(
				'type'    => 'text',
				'label'   => __( 'Title', 'uncoder' ),
				'default' => __( 'Web design', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'span',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control(
			'percentage',
			array(
				'type'        => 'number',
				'label'       => __( 'Percentage', 'uncoder' ),
				'default'     => 75,
				'min'         => 0,
				'max'         => 100,
				'step'        => 1,
				'render'      => 'template',
				'css_default' => true,
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-progress-value: {{VALUE}}%' ),
			)
		);
		$this->add_control(
			'inner_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Inner text', 'uncoder' ),
				'placeholder' => __( 'Advanced', 'uncoder' ),
				'description' => __( 'Shown inside the filled part of the bar.', 'uncoder' ),
				'inline'      => true,
			)
		);
		$this->add_control(
			'show_percentage',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show percentage', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'percentage_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Percentage position', 'uncoder' ),
				'default'   => 'title',
				'options'   => array(
					'title'  => array( 'label' => __( 'Next to title', 'uncoder' ) ),
					'inside' => array( 'label' => __( 'Inside bar', 'uncoder' ) ),
				),
				'condition' => array( 'show_percentage' => 'yes' ),
			)
		);
		$this->add_control(
			'animate',
			array(
				'type'    => 'switch',
				'label'   => __( 'Animate on scroll', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'duration',
			array(
				'type'      => 'number',
				'label'     => __( 'Duration (ms)', 'uncoder' ),
				'min'       => 100,
				'max'       => 6000,
				'step'      => 100,
				'condition' => array( 'animate' => 'yes' ),
				'render'    => 'template',
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-progress-duration: {{VALUE}}ms' ),
			)
		);
		$this->add_control(
			'striped',
			array(
				'type'  => 'switch',
				'label' => __( 'Striped', 'uncoder' ),
			)
		);
		$this->add_control(
			'striped_animate',
			array(
				'type'      => 'switch',
				'label'     => __( 'Move stripes', 'uncoder' ),
				'condition' => array( 'striped' => 'yes' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_bar', array( 'label' => __( 'Bar', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'bar_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 2, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-progress-h: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'bar_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-progress__track' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'bar_background',
			array(
				'type'     => 'background',
				'label'    => __( 'Fill', 'uncoder' ),
				'selector' => '{{WRAPPER}} .uncoder-progress__bar',
				'ai'       => 'Fill color or gradient, e.g. {"type":"classic","color":"var(--uncoder-c-primary)"}.',
			)
		);
		$this->add_control(
			'track_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Track color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-progress__track' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'track_border', array( 'type' => 'border', 'label' => __( 'Track border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-progress__track' ) );
		$this->add_group( 'track_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Track shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-progress__track' ) );
		$this->add_control( 'inner_heading', array( 'type' => 'heading', 'label' => __( 'Inner text & percentage', 'uncoder' ) ) );
		$this->add_group( 'inner_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-progress__bar' ) );
		$this->add_control(
			'inner_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-progress__bar' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'inner_padding',
			array(
				'type'       => 'slider',
				'label'      => __( 'Horizontal padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-progress__bar' => 'padding-inline: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Title & percentage', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-progress__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Title color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-progress__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'percent_typography', array( 'type' => 'typography', 'label' => __( 'Percentage typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-progress__header .uncoder-progress__percent' ) );
		$this->add_control(
			'percent_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Percentage color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-progress__header .uncoder-progress__percent' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-progress-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$value     = is_numeric( $s['percentage'] ?? null ) ? (int) round( max( 0, min( 100, (float) $s['percentage'] ) ) ) : 0;
		$title     = (string) ( $s['title'] ?? '' );
		$inner     = (string) ( $s['inner_text'] ?? '' );
		$show      = ! empty( $s['show_percentage'] );
		$inside    = $show && 'inside' === ( $s['percentage_position'] ?? 'title' );
		$percent   = number_format_i18n( $value ) . '%';
		$tag       = Utils::tag( $s['title_tag'] ?? 'span', Utils::HEADING_TAGS, 'span' );

		$classes = array( 'uncoder-progress' );
		if ( '' !== $inner || $inside ) {
			$classes[] = 'uncoder-progress--has-inner';
		}
		if ( ! empty( $s['striped'] ) ) {
			$classes[] = 'uncoder-progress--striped';
			if ( ! empty( $s['striped_animate'] ) ) {
				$classes[] = 'uncoder-progress--stripes-move';
			}
		}
		$attrs = array( 'class' => implode( ' ', $classes ) );
		if ( ! empty( $s['animate'] ) ) {
			$attrs['data-animate']  = 'true';
			$attrs['data-value']    = (string) $value;
			$attrs['data-duration'] = (string) ( is_numeric( $s['duration'] ?? null ) ? (int) $s['duration'] : 1200 );
		}

		$track = array(
			'class'           => 'uncoder-progress__track',
			'role'            => 'progressbar',
			'aria-valuemin'   => '0',
			'aria-valuemax'   => '100',
			'aria-valuenow'   => (string) $value,
			'aria-valuetext'  => $percent . ( '' !== $inner ? ' (' . $inner . ')' : '' ),
			'aria-label'      => '' !== $title ? $title : __( 'Progress', 'uncoder' ),
		);

		echo '<div' . Utils::attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		if ( '' !== $title || ( $show && ! $inside ) || $ctx->editor ) {
			echo '<div class="uncoder-progress__header">';
			if ( '' !== $title || $ctx->editor ) {
				echo '<' . $tag . ' class="uncoder-progress__title"' . $ctx->inline( 'title' ) . '>' . esc_html( $title ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, inline() escaped.
			}
			if ( $show && ! $inside ) {
				echo '<span class="uncoder-progress__percent" aria-hidden="true">' . esc_html( $percent ) . '</span>';
			}
			echo '</div>';
		}
		echo '<div' . Utils::attrs( $track ) . '><div class="uncoder-progress__bar">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		if ( '' !== $inner ) {
			echo '<span class="uncoder-progress__inner-text" aria-hidden="true"' . $ctx->inline( 'inner_text' ) . '>' . esc_html( $inner ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() escaped.
		}
		if ( $inside ) {
			echo '<span class="uncoder-progress__percent uncoder-progress__percent--inside" aria-hidden="true">' . esc_html( $percent ) . '</span>';
		}
		echo '</div></div></div>';
	}
}
