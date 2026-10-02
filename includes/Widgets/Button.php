<?php
/**
 * Button widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Link styled as a button; inherits the Design System button style unless overridden.
 */
class Button extends Widget_Base {

	public const HOVER_EFFECTS = array(
		''         => 'None',
		'lift'     => 'Lift',
		'grow'     => 'Grow',
		'shrink'   => 'Shrink',
		'pulse'    => 'Pulse',
		'shine'    => 'Shine',
		'arrow'    => 'Nudge icon',
		'fill'     => 'Fill from left',
		'underline' => 'Underline',
		'flip'     => 'Text roll',
	);

	public function name(): string {
		return 'button';
	}

	public function title(): string {
		return __( 'Button', 'uncoder' );
	}

	public function icon(): string {
		return 'rectangle-horizontal';
	}

	public function keywords(): array {
		return array( 'button', 'cta', 'link', 'call to action' );
	}

	public function description(): string {
		return __( 'Call-to-action link styled as a button. Variants: primary (Design System), secondary, outline, ghost, link.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Button', 'uncoder' ) ) );
		$this->add_control(
			'text',
			array(
				'type'    => 'text',
				'label'   => __( 'Text', 'uncoder' ),
				'default' => __( 'Click here', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'link',
			array(
				'type'    => 'url',
				'label'   => __( 'Link', 'uncoder' ),
				'default' => array( 'url' => '#' ),
				'dynamic' => true,
			)
		);
		$this->add_control(
			'variant',
			array(
				'type'    => 'select',
				'label'   => __( 'Style', 'uncoder' ),
				'default' => 'primary',
				'options' => array(
					'primary'   => __( 'Primary', 'uncoder' ),
					'secondary' => __( 'Secondary', 'uncoder' ),
					'outline'   => __( 'Outline', 'uncoder' ),
					'ghost'     => __( 'Ghost', 'uncoder' ),
					'link'      => __( 'Text link', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'size',
			array(
				'type'    => 'choose',
				'label'   => __( 'Size', 'uncoder' ),
				'default' => 'md',
				'options' => array(
					'sm' => array( 'label' => 'S' ),
					'md' => array( 'label' => 'M' ),
					'lg' => array( 'label' => 'L' ),
					'xl' => array( 'label' => 'XL' ),
				),
			)
		);
		$this->add_control( 'icon', array( 'type' => 'icon', 'label' => __( 'Icon', 'uncoder' ) ) );
		$this->add_control(
			'icon_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Icon position', 'uncoder' ),
				'default'   => 'after',
				'options'   => array(
					'before' => array( 'label' => __( 'Before', 'uncoder' ), 'icon' => 'arrow-left-to-line' ),
					'after'  => array( 'label' => __( 'After', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
				),
				'condition' => array( 'icon.library!' => 'none' ),
			)
		);
		$this->add_control(
			'icon_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-btn' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'left'    => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center'  => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'   => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
					'stretch' => array( 'label' => __( 'Full width', 'uncoder' ), 'icon' => 'align-justify' ),
				),
				'selectors_dictionary' => array(
					'left'    => 'justify-content:flex-start',
					'center'  => 'justify-content:center',
					'right'   => 'justify-content:flex-end',
					'stretch' => 'justify-content:stretch;--uncoder-btn-width:100%',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_control( 'hover_effect', array( 'type' => 'select', 'label' => __( 'Hover effect', 'uncoder' ), 'options' => self::HOVER_EFFECTS ) );
		$this->add_control( 'button_id', array( 'type' => 'text', 'label' => __( 'Button ID', 'uncoder' ), 'ai' => 'For analytics/tracking.' ) );
		$this->end_section();

		$this->start_section( 'style_button', array( 'label' => __( 'Button', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-btn' ) );
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-btn' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'background',
			array(
				'type'     => 'background',
				'label'    => __( 'Background', 'uncoder' ),
				'selector' => '{{WRAPPER}} .uncoder-btn',
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-btn' ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-btn' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-btn:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'hover_background',
			array(
				'type'     => 'background',
				'label'    => __( 'Background', 'uncoder' ),
				'selector' => '{{WRAPPER}} .uncoder-btn:is(:hover, :focus-visible)',
			)
		);
		$this->add_control(
			'hover_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-btn:is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'hover_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-btn:hover' ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-btn' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-btn' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-btn .uncoder-svg' => 'width: {{VALUE}}; height: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$variant = in_array( $s['variant'] ?? 'primary', array( 'primary', 'secondary', 'outline', 'ghost', 'link' ), true ) ? $s['variant'] : 'primary';
		$size    = in_array( $s['size'] ?? 'md', array( 'sm', 'md', 'lg', 'xl' ), true ) ? $s['size'] : 'md';
		$classes = array( 'uncoder-btn', 'uncoder-btn--' . $variant, 'uncoder-btn--' . $size );
		if ( ! empty( $s['hover_effect'] ) && isset( self::HOVER_EFFECTS[ $s['hover_effect'] ] ) ) {
			$classes[] = 'uncoder-hover-' . $s['hover_effect'];
		}
		$attrs          = $this->link_attrs( $s['link'] ?? array() );
		$attrs['class'] = implode( ' ', $classes );
		if ( ! empty( $s['button_id'] ) ) {
			$attrs['id'] = sanitize_html_class( (string) $s['button_id'] );
		}
		$tag = isset( $attrs['href'] ) ? 'a' : 'span';
		if ( 'span' === $tag ) {
			$attrs['role'] = 'presentation';
		}
		$icon = $this->has_icon( $s['icon'] ?? null ) ? $this->render_icon( $s['icon'], array( 'class' => 'uncoder-btn__icon' ) ) : '';
		$text = '<span class="uncoder-btn__text"' . $ctx->inline( 'text' ) . '>' . esc_html( (string) ( $s['text'] ?? '' ) ) . '</span>';

		echo '<div class="uncoder-button">';
		echo '<' . $tag . Utils::attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		echo 'before' === ( $s['icon_position'] ?? 'after' ) ? $icon . $text : $text . $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '</' . $tag . '></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
