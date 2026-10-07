<?php
/**
 * Divider widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Horizontal rule with optional text or icon in the middle.
 */
class Divider extends Widget_Base {

	public function name(): string {
		return 'divider';
	}

	public function title(): string {
		return __( 'Divider', 'uncoder' );
	}

	public function icon(): string {
		return 'minus';
	}

	public function keywords(): array {
		return array( 'divider', 'separator', 'line', 'hr', 'rule' );
	}

	public function description(): string {
		return __( 'A horizontal line, optionally with a label or icon in the middle.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Divider', 'uncoder' ) ) );
		$this->add_control(
			'style',
			array(
				'type'      => 'select',
				'label'     => __( 'Style', 'uncoder' ),
				'default'   => 'solid',
				'options'   => array(
					'solid'  => __( 'Solid', 'uncoder' ),
					'dashed' => __( 'Dashed', 'uncoder' ),
					'dotted' => __( 'Dotted', 'uncoder' ),
					'double' => __( 'Double', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-divider-style: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( '%', 'px' ),
				'selectors'  => array( '{{WRAPPER}}' => 'width: {{VALUE}}' ),
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
					'left'   => 'margin-inline:0 auto',
					'center' => 'margin-inline:auto',
					'right'  => 'margin-inline:auto 0',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'element',
			array(
				'type'    => 'select',
				'label'   => __( 'Add element', 'uncoder' ),
				'options' => array(
					''     => __( 'None', 'uncoder' ),
					'text' => __( 'Text', 'uncoder' ),
					'icon' => __( 'Icon', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'text',
			array(
				'type'      => 'text',
				'label'     => __( 'Text', 'uncoder' ),
				'default'   => __( 'Divider', 'uncoder' ),
				'condition' => array( 'element' => 'text' ),
				'inline'    => true,
			)
		);
		$this->add_control(
			'icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'sparkles' ),
				'condition' => array( 'element' => 'icon' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_divider', array( 'label' => __( 'Divider', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-divider-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'weight',
			array(
				'type'       => 'slider',
				'label'      => __( 'Weight', 'uncoder' ),
				'size_units' => array( 'px' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-divider-weight: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Vertical spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding-block: {{VALUE}}' ),
			)
		);
		$this->add_group( 'text_typography', array( 'type' => 'typography', 'label' => __( 'Text typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-divider__text', 'condition' => array( 'element' => 'text' ) ) );
		$this->add_control(
			'element_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text / icon color', 'uncoder' ),
				'condition' => array( 'element!' => '' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-divider__element' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'element_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Element spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'element!' => '' ),
				'selectors'  => array( '{{WRAPPER}}' => 'column-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$element = $s['element'] ?? '';
		$inner   = '';
		if ( 'text' === $element && '' !== (string) ( $s['text'] ?? '' ) ) {
			$inner = '<span class="uncoder-divider__element uncoder-divider__text"' . $ctx->inline( 'text' ) . '>' . esc_html( (string) $s['text'] ) . '</span>';
		} elseif ( 'icon' === $element ) {
			$inner = '<span class="uncoder-divider__element uncoder-divider__icon">' . $this->render_icon( $s['icon'] ?? 'sparkles' ) . '</span>';
		}
		$class = 'uncoder-divider' . ( '' !== $inner ? ' uncoder-divider--has-element' : '' );
		echo '<div class="' . esc_attr( $class ) . '" role="separator">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	}
}
