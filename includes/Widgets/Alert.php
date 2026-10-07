<?php
/**
 * Alert widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A notice box (info, success, warning, danger) with an optional dismiss button.
 */
class Alert extends Widget_Base {

	/** Default icon per type. */
	public const TYPE_ICONS = array(
		'info'    => 'info',
		'success' => 'circle-check',
		'warning' => 'triangle-alert',
		'danger'  => 'octagon-alert',
	);

	public function name(): string {
		return 'alert';
	}

	public function title(): string {
		return __( 'Alert', 'uncoder' );
	}

	public function icon(): string {
		return 'circle-alert';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'alert', 'notice', 'message', 'callout', 'warning', 'info', 'banner' );
	}

	public function description(): string {
		return __( 'A highlighted notice (info, success, warning or danger) with a title, text and an optional dismiss button that can be remembered.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'alert' );
	}

	/**
	 * Localized, screen-reader-only prefix announcing the alert type.
	 */
	private function type_label( string $type ): string {
		switch ( $type ) {
			case 'success':
				return __( 'Success:', 'uncoder' );
			case 'warning':
				return __( 'Warning:', 'uncoder' );
			case 'danger':
				return __( 'Important:', 'uncoder' );
			default:
				return __( 'Note:', 'uncoder' );
		}
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Alert', 'uncoder' ) ) );
		$this->add_control(
			'type',
			array(
				'type'    => 'select',
				'label'   => __( 'Type', 'uncoder' ),
				'default' => 'info',
				'options' => array(
					'info'    => __( 'Info', 'uncoder' ),
					'success' => __( 'Success', 'uncoder' ),
					'warning' => __( 'Warning', 'uncoder' ),
					'danger'  => __( 'Danger', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'variant',
			array(
				'type'    => 'select',
				'label'   => __( 'Style', 'uncoder' ),
				'default' => 'soft',
				'options' => array(
					'soft'    => __( 'Soft', 'uncoder' ),
					'accent'  => __( 'Soft with side accent', 'uncoder' ),
					'outline' => __( 'Outline', 'uncoder' ),
					'solid'   => __( 'Solid', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'title',
			array(
				'type'    => 'text',
				'label'   => __( 'Title', 'uncoder' ),
				'html'    => 'inline',
				'default' => __( 'Heads up', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'description',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Description', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 3,
				'default' => __( 'Our office is closed on public holidays. Orders placed then ship the next working day.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'show_icon',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show icon', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'icon',
			array(
				'type'        => 'icon',
				'label'       => __( 'Icon', 'uncoder' ),
				'description' => __( 'Leave empty to use the icon of the alert type.', 'uncoder' ),
				'condition'   => array( 'show_icon' => 'yes' ),
			)
		);
		$this->add_control(
			'dismissible',
			array(
				'type'    => 'switch',
				'label'   => __( 'Dismiss button', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'remember',
			array(
				'type'        => 'switch',
				'label'       => __( 'Remember dismissal', 'uncoder' ),
				'description' => __( 'Keeps the alert hidden for this visitor (stored in their browser).', 'uncoder' ),
				'condition'   => array( 'dismissible' => 'yes' ),
			)
		);
		$this->add_control(
			'storage_key',
			array(
				'type'        => 'text',
				'label'       => __( 'Storage key', 'uncoder' ),
				'placeholder' => 'holiday-notice-2026',
				'description' => __( 'Change it to show the alert again to everyone. Defaults to the element ID.', 'uncoder' ),
				'condition'   => array(
					'dismissible' => 'yes',
					'remember'    => 'yes',
				),
			)
		);
		$this->end_section();

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'accent_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Accent color', 'uncoder' ),
				'description' => __( 'Drives the icon, border and tinted background.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-alert-accent: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'background_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Background color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'accent_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Side accent width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 16 ) ),
				'condition'  => array( 'variant' => 'accent' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-alert-bar: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->end_section();

		$this->start_section(
			'style_icon',
			array(
				'label'     => __( 'Icon', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);
		$this->add_control(
			'icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-alert__icon' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-alert-icon-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'column-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_content', array( 'label' => __( 'Content', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-alert__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Title color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-alert__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Title spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-alert__title' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_group( 'description_typography', array( 'type' => 'typography', 'label' => __( 'Description typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-alert__description' ) );
		$this->add_control(
			'description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Description color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-alert__description' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_dismiss',
			array(
				'label'     => __( 'Dismiss button', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'dismissible' => 'yes' ),
			)
		);
		$this->add_responsive_control(
			'dismiss_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-alert__dismiss' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'dismiss_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'dismiss_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-alert__dismiss' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'dismiss_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-alert__dismiss:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dismiss_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-alert__dismiss:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();
	}

	/**
	 * Storage key for a remembered dismissal ("" when not remembered).
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function storage_key( array $s, Render_Context $ctx ): string {
		if ( empty( $s['dismissible'] ) || empty( $s['remember'] ) ) {
			return '';
		}
		$key = (string) preg_replace( '/\s+/', '-', trim( (string) ( $s['storage_key'] ?? '' ) ) );
		$key = strtolower( (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', $key ) );
		if ( '' === $key ) {
			$key = $ctx->doc_id . '-' . $ctx->element_id;
		}
		return substr( $key, 0, 64 );
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$key = $this->storage_key( $s, $ctx );
		if ( '' === $key ) {
			return array();
		}
		return array( 'data-settings' => $this->json_attr( array( 'key' => $key ) ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$type        = isset( self::TYPE_ICONS[ $s['type'] ?? '' ] ) ? (string) $s['type'] : 'info';
		$variant     = in_array( $s['variant'] ?? 'soft', array( 'soft', 'accent', 'outline', 'solid' ), true ) ? (string) $s['variant'] : 'soft';
		$title       = $this->inline_html( $s['title'] ?? '' );
		$description = $this->inline_html( $s['description'] ?? '' );
		$has_title   = '' !== trim( wp_strip_all_tags( $title ) );
		$has_desc    = '' !== trim( wp_strip_all_tags( $description ) );
		if ( ! $has_title && ! $has_desc && ! $ctx->editor ) {
			return;
		}

		$icon = '';
		if ( ! empty( $s['show_icon'] ) ) {
			$icon = $this->has_icon( $s['icon'] ?? null ) ? $this->render_icon( $s['icon'] ) : $this->render_icon( self::TYPE_ICONS[ $type ] );
		}

		echo '<div class="' . esc_attr( 'uncoder-alert uncoder-alert--' . $type . ' uncoder-alert--' . $variant ) . '">';
		if ( '' !== $icon ) {
			echo '<span class="uncoder-alert__icon">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup escaped.
		}
		// Screen readers hear the severity first ("Warning: …"); sighted users get it from colour and icon.
		$prefix = '<span class="uncoder-sr-only">' . esc_html( $this->type_label( $type ) ) . ' </span>';
		echo '<div class="uncoder-alert__content">';
		if ( $has_title || $ctx->editor ) {
			echo '<p class="uncoder-alert__title">' . $prefix . '<span' . $ctx->inline( 'title' ) . '>' . $title . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML, escaped prefix.
			$prefix = '';
		}
		if ( $has_desc || $ctx->editor ) {
			echo '<p class="uncoder-alert__description">' . $prefix . '<span' . $ctx->inline( 'description' ) . '>' . $description . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML, escaped prefix.
		}
		echo '</div>';
		if ( ! empty( $s['dismissible'] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes, icon markup escaped.
			echo '<button' . Utils::attrs(
				array(
					'type'       => 'button',
					'class'      => 'uncoder-alert__dismiss',
					'aria-label' => __( 'Dismiss this message', 'uncoder' ),
				)
			) . '>' . $this->render_icon( 'x' ) . '</button>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}
}
