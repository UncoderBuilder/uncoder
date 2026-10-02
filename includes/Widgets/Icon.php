<?php
/**
 * Icon widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A single icon, plain, stacked (filled shape) or framed (outlined shape).
 */
class Icon extends Widget_Base {

	public function name(): string {
		return 'icon';
	}

	public function title(): string {
		return __( 'Icon', 'uncoder' );
	}

	public function icon(): string {
		return 'star';
	}

	public function keywords(): array {
		return array( 'icon', 'symbol', 'glyph', 'svg' );
	}

	public function description(): string {
		return __( 'One icon from Lucide, Font Awesome, Phosphor, Bootstrap, Feather, Heroicons or Themify (or an uploaded SVG), optionally inside a circle or square.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Icon', 'uncoder' ) ) );
		$this->add_control(
			'icon',
			array(
				'type'    => 'icon',
				'label'   => __( 'Icon', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'star' ),
			)
		);
		$this->add_control(
			'view',
			array(
				'type'    => 'select',
				'label'   => __( 'View', 'uncoder' ),
				'default' => 'default',
				'options' => array(
					'default' => __( 'Default', 'uncoder' ),
					'stacked' => __( 'Stacked', 'uncoder' ),
					'framed'  => __( 'Framed', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'shape',
			array(
				'type'      => 'select',
				'label'     => __( 'Shape', 'uncoder' ),
				'default'   => 'circle',
				'options'   => array(
					'circle'  => __( 'Circle', 'uncoder' ),
					'rounded' => __( 'Rounded', 'uncoder' ),
					'square'  => __( 'Square', 'uncoder' ),
				),
				'condition' => array( 'view!' => 'default' ),
			)
		);
		$this->add_control( 'link', array( 'type' => 'url', 'label' => __( 'Link', 'uncoder' ), 'dynamic' => true ) );
		$this->add_control( 'label', array( 'type' => 'text', 'label' => __( 'Accessible label', 'uncoder' ), 'description' => __( 'Needed when the icon is a link.', 'uncoder' ) ) );
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => 'flex-start',
					'center' => 'center',
					'right'  => 'flex-end',
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-icon-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_icon', array( 'label' => __( 'Icon', 'uncoder' ), 'tab' => 'style' ) );
		$this->start_tabs( 'icon_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-icon-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'shape_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Shape color', 'uncoder' ),
				'condition' => array( 'view!' => 'default' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-icon-shape: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover' => '--uncoder-icon-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_shape_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Shape color', 'uncoder' ),
				'condition' => array( 'view!' => 'default' ),
				'selectors' => array( '{{WRAPPER}}:hover' => '--uncoder-icon-shape: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 300 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-icon-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'slider',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'view!' => 'default' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-icon-pad: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'stroke',
			array(
				'type'      => 'number',
				'label'     => __( 'Stroke width', 'uncoder' ),
				'min'       => 0.5,
				'max'       => 4,
				'step'      => 0.25,
				'selectors' => array( '{{WRAPPER}} .uncoder-svg' => 'stroke-width: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'rotate',
			array(
				'type'       => 'slider',
				'label'      => __( 'Rotate', 'uncoder' ),
				'size_units' => array( 'deg' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-svg' => 'transform: rotate({{VALUE}})' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$view    = in_array( $s['view'] ?? 'default', array( 'default', 'stacked', 'framed' ), true ) ? $s['view'] : 'default';
		$shape   = in_array( $s['shape'] ?? 'circle', array( 'circle', 'rounded', 'square' ), true ) ? $s['shape'] : 'circle';
		$classes = 'uncoder-icon-wrap uncoder-icon-wrap--' . $view . ( 'default' !== $view ? ' uncoder-icon-wrap--' . $shape : '' );
		// The root (merged with the element) only positions; the inner span draws the icon and its shape.
		$svg  = '<span class="' . esc_attr( $classes ) . '">' . $this->render_icon( $s['icon'] ?? 'star' ) . '</span>';
		$link = $this->link_attrs( $s['link'] ?? array() );
		if ( $link ) {
			if ( ! empty( $s['label'] ) ) {
				$link['aria-label'] = (string) $s['label'];
			} elseif ( empty( $link['aria-label'] ) ) {
				// An icon-only link needs a name: fall back to the icon's name ("arrow-right" → "Arrow right").
				$name = is_array( $s['icon'] ?? null ) ? (string) ( $s['icon']['value'] ?? '' ) : (string) ( $s['icon'] ?? 'star' );
				$name = trim( str_replace( array( '-', '_' ), ' ', (string) preg_replace( '/^[a-z-]+:/', '', $name ) ) );
				if ( '' !== $name ) {
					$link['aria-label'] = ucfirst( $name );
				}
			}
			echo '<a' . Utils::attrs( $link ) . '>' . $svg . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
			return;
		}
		echo '<span' . ( ! empty( $s['label'] ) ? ' role="img" aria-label="' . esc_attr( (string) $s['label'] ) . '"' : '' ) . '>' . $svg . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
	}
}
