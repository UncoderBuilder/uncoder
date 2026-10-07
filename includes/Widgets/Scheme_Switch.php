<?php
/**
 * Color scheme switch widget (dark mode toggle).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Lets visitors switch between the Design System's light and dark colors (Design System → dark mode). The
 * choice is remembered in the browser and applied before the page paints (Frontend::js_flag()).
 */
class Scheme_Switch extends Widget_Base {

	public function name(): string {
		return 'scheme-switch';
	}

	public function title(): string {
		return __( 'Color Scheme Switch', 'uncoder' );
	}

	public function icon(): string {
		return 'sun-moon';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'dark', 'light', 'mode', 'theme', 'toggle', 'night', 'scheme' );
	}

	public function description(): string {
		return __( 'A dark mode toggle for visitors. Turn on dark mode and give colors dark values in the Design System first.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'scheme-switch' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Switch', 'uncoder' ) ) );
		$this->add_control(
			'style',
			array(
				'type'    => 'choose',
				'label'   => __( 'Style', 'uncoder' ),
				'default' => 'icon',
				'options' => array(
					'icon'   => array( 'label' => __( 'Icon button', 'uncoder' ), 'icon' => 'sun-moon' ),
					'switch' => array( 'label' => __( 'Switch', 'uncoder' ), 'icon' => 'toggle-left' ),
				),
			)
		);
		$this->add_control(
			'label',
			array(
				'type'        => 'text',
				'label'       => __( 'Accessible label', 'uncoder' ),
				'placeholder' => __( 'Dark mode', 'uncoder' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'flex-start' => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-left' ),
					'center'     => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'flex-end'   => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'display: flex; justify-content: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style', array( 'label' => __( 'Switch', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ss-size: {{VALUE}}' ),
			)
		);
		$this->add_control( 'color', array( 'type' => 'color', 'label' => __( 'Icon color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-ss-bg: {{VALUE}}' ) ) );
		$this->add_control( 'active_color', array( 'type' => 'color', 'label' => __( 'Switch color when dark', 'uncoder' ), 'condition' => array( 'style' => 'switch' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-ss-on: {{VALUE}}' ) ) );
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		if ( 'light' === Plugin::instance()->kit()->setting( 'color_scheme', 'light' ) ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-scheme-switch__off">' . esc_html__( 'Dark mode is off. Turn it on in Design System → Colors → Dark mode.', 'uncoder' ) . '</div>';
			}
			return;
		}
		$style = 'switch' === ( $s['style'] ?? 'icon' ) ? 'switch' : 'icon';
		$label = trim( (string) ( $s['label'] ?? '' ) );
		$sun   = $this->render_icon( 'sun', array( 'class' => 'uncoder-scheme-switch__icon uncoder-scheme-switch__icon--sun' ) );
		$moon  = $this->render_icon( 'moon', array( 'class' => 'uncoder-scheme-switch__icon uncoder-scheme-switch__icon--moon' ) );
		echo '<button type="button" class="uncoder-scheme-switch uncoder-scheme-switch--' . esc_attr( $style ) . '" aria-pressed="false" aria-label="' . esc_attr( '' !== $label ? $label : __( 'Dark mode', 'uncoder' ) ) . '">';
		if ( 'switch' === $style ) {
			echo $sun . '<span class="uncoder-scheme-switch__track" aria-hidden="true"><span class="uncoder-scheme-switch__thumb"></span></span>' . $moon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
		} else {
			echo $sun . $moon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
		}
		echo '</button>';
	}
}
