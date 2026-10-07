<?php
/**
 * Before / after image comparison widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Gallery_Items;

defined( 'ABSPATH' ) || exit;

/**
 * Two stacked images revealed by a draggable divider; a native range input provides keyboard
 * and screen reader support.
 */
class Image_Compare extends Widget_Base {

	private const ROOT  = '{{WRAPPER}}';
	private const MEDIA = '{{WRAPPER}} .uncoder-image-compare__media';
	private const KNOB  = '{{WRAPPER}} .uncoder-image-compare__knob';
	private const LABEL = '{{WRAPPER}} .uncoder-image-compare__label';

	public function name(): string {
		return 'image-compare';
	}

	public function title(): string {
		return __( 'Before / After', 'uncoder' );
	}

	public function icon(): string {
		return 'square-split-horizontal';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'before after', 'compare', 'comparison', 'slider', 'image', 'reveal', 'juxtapose' );
	}

	public function description(): string {
		return __( 'Before / after comparison of two images with a draggable, keyboard-accessible divider.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'image-compare' );
	}

	public function preset(): array {
		$placeholder = Gallery_Items::placeholders( 1 )[0];
		return array(
			'before_image' => $placeholder,
			'after_image'  => $placeholder,
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Images', 'uncoder' ) ) );
		$this->add_control(
			'before_image',
			array(
				'type'    => 'media',
				'label'   => __( 'Before image', 'uncoder' ),
				'default' => array( 'id' => 0, 'url' => '' ),
				'dynamic' => true,
				'ai'      => 'Both images should share the same size and framing.',
			)
		);
		$this->add_control(
			'after_image',
			array(
				'type'    => 'media',
				'label'   => __( 'After image', 'uncoder' ),
				'default' => array( 'id' => 0, 'url' => '' ),
				'dynamic' => true,
			)
		);
		$this->add_control(
			'size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'large',
				'options_dynamic' => true,
				'options'         => Gallery_Items::SIZES,
			)
		);
		$this->add_control(
			'before_label',
			array(
				'type'    => 'text',
				'label'   => __( 'Before label', 'uncoder' ),
				'default' => __( 'Before', 'uncoder' ),
				'inline'  => true,
			)
		);
		$this->add_control(
			'after_label',
			array(
				'type'    => 'text',
				'label'   => __( 'After label', 'uncoder' ),
				'default' => __( 'After', 'uncoder' ),
				'inline'  => true,
			)
		);
		$this->add_control(
			'labels',
			array(
				'type'    => 'select',
				'label'   => __( 'Show labels', 'uncoder' ),
				'default' => 'always',
				'options' => array(
					'always' => __( 'Always', 'uncoder' ),
					'hover'  => __( 'On hover', 'uncoder' ),
					''       => __( 'Never', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'labels_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Labels position', 'uncoder' ),
				'default'   => 'start',
				'options'   => array(
					'start'  => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-start-vertical' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center-vertical' ),
					'end'    => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-end-vertical' ),
				),
				'condition' => array( 'labels!' => '' ),
			)
		);
		$this->add_control(
			'orientation',
			array(
				'type'    => 'choose',
				'label'   => __( 'Orientation', 'uncoder' ),
				'default' => 'horizontal',
				'options' => array(
					'horizontal' => array( 'label' => __( 'Horizontal', 'uncoder' ), 'icon' => 'move-horizontal' ),
					'vertical'   => array( 'label' => __( 'Vertical', 'uncoder' ), 'icon' => 'move-vertical' ),
				),
			)
		);
		$this->add_control(
			'position',
			array(
				'type'       => 'slider',
				'label'      => __( 'Initial position', 'uncoder' ),
				'size_units' => array( '%' ),
				'range'      => array( '%' => array( 'min' => 0, 'max' => 100 ) ),
				'default'    => array( 'size' => 50, 'unit' => '%' ),
				'selectors'  => array( self::ROOT => '--uncoder-compare-pos: {{SIZE}}%' ),
				'render'     => 'template',
			)
		);
		$this->add_control(
			'move_on_hover',
			array(
				'type'        => 'switch',
				'label'       => __( 'Follow the mouse', 'uncoder' ),
				'description' => __( 'The divider follows the pointer without clicking.', 'uncoder' ),
			)
		);
		$this->add_control(
			'handle_icon',
			array(
				'type'    => 'icon',
				'label'   => __( 'Handle icon', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'chevrons-left-right' ),
			)
		);
		$this->add_responsive_control(
			'ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Aspect ratio', 'uncoder' ),
				'options'   => array(
					''     => __( 'Original', 'uncoder' ),
					'1/1'  => '1:1',
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'21/9' => '21:9',
					'3/4'  => '3:4',
					'4/5'  => '4:5',
				),
				'selectors' => array( self::MEDIA => 'aspect-ratio: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: image */
		$this->start_section( 'style_image', array( 'label' => __( 'Image', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( self::MEDIA => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::MEDIA ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::MEDIA ) );
		$this->end_section();

		/* ---------------------------------------------------------------- Style: handle */
		$this->start_section( 'style_handle', array( 'label' => __( 'Handle', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'line_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Line color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-compare-line: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'line_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Line width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 12 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-compare-line-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'knob_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Handle size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 16, 'max' => 120 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-compare-knob: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'knob_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Handle background', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-compare-knob-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'knob_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Handle icon color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-compare-knob-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'knob_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Handle radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( self::KNOB => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'knob_border', array( 'type' => 'border', 'label' => __( 'Handle border', 'uncoder' ), 'selector' => self::KNOB ) );
		$this->add_group( 'knob_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Handle shadow', 'uncoder' ), 'selector' => self::KNOB ) );
		$this->end_section();

		/* ---------------------------------------------------------------- Style: labels */
		$this->start_section(
			'style_labels',
			array(
				'label'     => __( 'Labels', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'labels!' => '' ),
			)
		);
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::LABEL ) );
		$this->add_control(
			'label_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::LABEL => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'label_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::LABEL => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'label_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( self::LABEL => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'label_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( self::LABEL => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'label_offset',
			array(
				'type'       => 'slider',
				'label'      => __( 'Distance from edge', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-compare-label-offset: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array(
			'data-settings' => $this->json_attr(
				array(
					'hover'  => ! empty( $s['move_on_hover'] ),
					'before' => (string) ( $s['before_label'] ?? '' ),
					'after'  => (string) ( $s['after_label'] ?? '' ),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$size   = sanitize_key( (string) ( $s['size'] ?? 'large' ) );
		$before = $this->image( $s['before_image'] ?? array(), $size, array( 'class' => 'uncoder-image-compare__img', 'draggable' => 'false' ) );
		$after  = $this->image( $s['after_image'] ?? array(), $size, array( 'class' => 'uncoder-image-compare__img', 'draggable' => 'false' ) );
		if ( '' === $before || '' === $after ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$placeholder = '<img class="uncoder-image-compare__img" src="' . esc_url( $this->placeholder_image() ) . '" alt="" draggable="false">';
			$before      = '' !== $before ? $before : $placeholder;
			$after       = '' !== $after ? $after : $placeholder;
		}

		$vertical = 'vertical' === ( $s['orientation'] ?? 'horizontal' );
		$labels   = in_array( $s['labels'] ?? 'always', array( 'always', 'hover' ), true ) ? $s['labels'] : '';
		$lpos     = in_array( $s['labels_position'] ?? 'start', array( 'start', 'center', 'end' ), true ) ? $s['labels_position'] : 'start';
		$pos      = is_array( $s['position'] ?? null ) && is_numeric( $s['position']['size'] ?? '' ) ? (int) round( (float) $s['position']['size'] ) : 50;
		$pos      = min( max( $pos, 0 ), 100 );
		$classes  = array( 'uncoder-image-compare', 'uncoder-image-compare--' . ( $vertical ? 'vertical' : 'horizontal' ) );
		if ( '' !== $labels ) {
			$classes[] = 'uncoder-image-compare--labels-' . $labels;
			$classes[] = 'uncoder-image-compare--labels-' . $lpos;
		}
		$before_label = trim( (string) ( $s['before_label'] ?? '' ) );
		$after_label  = trim( (string) ( $s['after_label'] ?? '' ) );
		$handle_icon  = $this->has_icon( $s['handle_icon'] ?? null ) ? $this->render_icon( $s['handle_icon'], array( 'class' => 'uncoder-image-compare__icon' ) ) : '';

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		echo '<input' . Utils::attrs(
			array(
				'type'             => 'range',
				'class'            => 'uncoder-image-compare__range',
				'min'              => '0',
				'max'              => '100',
				'step'             => '1',
				'value'            => (string) $pos,
				'dir'              => 'ltr',
				'aria-label'       => '' !== $before_label && '' !== $after_label
					/* translators: 1: before label, 2: after label. */
					? sprintf( __( 'Compare %1$s and %2$s', 'uncoder' ), $before_label, $after_label )
					: __( 'Image comparison divider', 'uncoder' ),
				'aria-orientation' => $vertical ? 'vertical' : null,
				'aria-valuetext'   => $pos . '%',
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="uncoder-image-compare__media">';
		echo '<div class="uncoder-image-compare__after">' . $after . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
		echo '<div class="uncoder-image-compare__before">' . $before . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
		if ( '' !== $labels ) {
			if ( '' !== $before_label ) {
				echo '<span class="uncoder-image-compare__label uncoder-image-compare__label--before" aria-hidden="true"' . $ctx->inline( 'before_label' ) . '>' . esc_html( $before_label ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
			}
			if ( '' !== $after_label ) {
				echo '<span class="uncoder-image-compare__label uncoder-image-compare__label--after" aria-hidden="true"' . $ctx->inline( 'after_label' ) . '>' . esc_html( $after_label ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
			}
		}
		echo '<div class="uncoder-image-compare__handle" aria-hidden="true"><span class="uncoder-image-compare__knob">' . $handle_icon . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
		echo '</div></div>';
	}
}
