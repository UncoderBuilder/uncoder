<?php
/**
 * Off-canvas widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A trigger button that opens a container in a panel sliding in from a screen edge
 * (modal dialog with focus trap, Escape and overlay click to close).
 */
class Off_Canvas extends Widget_Base {

	public const POSITIONS = array( 'left', 'right', 'top', 'bottom' );

	public function name(): string {
		return 'off-canvas';
	}

	public function title(): string {
		return __( 'Off-Canvas', 'uncoder' );
	}

	public function icon(): string {
		return 'panel-right';
	}

	public function category(): string {
		return 'layout';
	}

	public function keywords(): array {
		return array( 'off canvas', 'offcanvas', 'drawer', 'sidebar', 'panel', 'slide', 'menu', 'popup' );
	}

	public function description(): string {
		return __( 'A button that opens a panel sliding in from the left, right, top or bottom. The panel is a container: put any widgets in it (menus, forms, text). Links to #uncoder-offcanvas-{element id} also open it.', 'uncoder' );
	}

	public function nested(): ?array {
		return array( 'items' => 'panels' );
	}

	public function frontend_scripts(): array {
		return array( 'off-canvas' );
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private static function default_panels(): array {
		return array( array( 'label' => __( 'Menu', 'uncoder' ) ) );
	}

	public function preset(): array {
		return array( 'panels' => self::default_panels() );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Trigger', 'uncoder' ) ) );
		$this->add_control(
			'show_trigger',
			array(
				'type'        => 'switch',
				'label'       => __( 'Show trigger button', 'uncoder' ),
				'description' => __( 'Turn off to open the panel only from links.', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'trigger_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Text', 'uncoder' ),
				'default'   => __( 'Menu', 'uncoder' ),
				'inline'    => true,
				'condition' => array( 'show_trigger' => true ),
			)
		);
		$this->add_control(
			'trigger_text_visible',
			array(
				'type'        => 'switch',
				'label'       => __( 'Show text', 'uncoder' ),
				'description' => __( 'When off, the text is only read by screen readers.', 'uncoder' ),
				'default'     => true,
				'condition'   => array( 'show_trigger' => true ),
			)
		);
		$this->add_control(
			'trigger_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'menu' ),
				'condition' => array( 'show_trigger' => true ),
			)
		);
		$this->add_control(
			'icon_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Icon position', 'uncoder' ),
				'default'   => 'before',
				'options'   => array(
					'before' => array( 'label' => __( 'Before', 'uncoder' ), 'icon' => 'arrow-left-to-line' ),
					'after'  => array( 'label' => __( 'After', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
				),
				'condition' => array( 'show_trigger' => true ),
			)
		);
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
				'condition' => array( 'show_trigger' => true ),
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'panel', array( 'label' => __( 'Panel', 'uncoder' ) ) );
		$this->add_control(
			'panels',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Panel', 'uncoder' ),
				'title_field' => 'label',
				'max'         => 1,
				'default'     => self::default_panels(),
				'fields'      => array(
					'label' => array(
						'type'        => 'text',
						'label'       => __( 'Accessible name', 'uncoder' ),
						'description' => __( 'Announced by screen readers when the panel opens.', 'uncoder' ),
						'default'     => __( 'Menu', 'uncoder' ),
					),
				),
				'ai'          => 'Exactly one row; its container child holds the panel content.',
			)
		);
		$this->add_control(
			'position',
			array(
				'type'    => 'choose',
				'label'   => __( 'Opens from', 'uncoder' ),
				'default' => 'right',
				'options' => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'panel-left' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'panel-right' ),
					'top'    => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'panel-top' ),
					'bottom' => array( 'label' => __( 'Bottom', 'uncoder' ), 'icon' => 'panel-bottom' ),
				),
			)
		);
		$this->add_responsive_control(
			'width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px', 'vw', '%', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 200, 'max' => 1200 ) ),
				'condition'  => array( 'position' => array( 'left', 'right' ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-oc-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', '%', 'rem' ),
				'condition'  => array( 'position' => array( 'top', 'bottom' ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-oc-h: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'close_button',
			array(
				'type'    => 'switch',
				'label'   => __( 'Close button', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'close_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Close icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'x' ),
				'condition' => array( 'close_button' => true ),
			)
		);
		$this->add_control(
			'close_on_overlay',
			array(
				'type'    => 'switch',
				'label'   => __( 'Close on overlay click', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'prevent_scroll',
			array(
				'type'    => 'switch',
				'label'   => __( 'Prevent page scrolling', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'anchor',
			array(
				'type'        => 'text',
				'label'       => __( 'Link anchor', 'uncoder' ),
				'placeholder' => 'contact-panel',
				'description' => __( 'Links to #uncoder-offcanvas-{element id} always open this panel; add a friendlier anchor here (links to #contact-panel).', 'uncoder' ),
			)
		);
		$this->add_control(
			'duration',
			array(
				'type'      => 'number',
				'label'     => __( 'Animation (ms)', 'uncoder' ),
				'min'       => 0,
				'max'       => 2000,
				'step'      => 50,
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-oc-duration: {{VALUE}}ms' ),
			)
		);
		$this->end_section();

		$this->register_style_controls();
	}

	private function register_style_controls(): void {
		$this->start_section(
			'style_trigger',
			array(
				'label'     => __( 'Trigger', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_trigger' => true ),
			)
		);
		$this->add_group( 'trigger_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-offcanvas__trigger' ) );
		$this->start_tabs( 'trigger_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'trigger_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__trigger' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'trigger_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__trigger' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'trigger_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-offcanvas__trigger' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'trigger_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__trigger:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'trigger_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__trigger:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'trigger_hover_border',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__trigger:is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'trigger_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-offcanvas__trigger .uncoder-svg' => 'width: {{VALUE}}; height: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'trigger_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-offcanvas__trigger' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'trigger_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-offcanvas__trigger' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'trigger_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-offcanvas__trigger' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_panel', array( 'label' => __( 'Panel', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'panel_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-offcanvas__panel' ) );
		$this->add_control(
			'panel_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__panel' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'panel_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', 'vw' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-offcanvas__content' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'panel_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-offcanvas__panel' ) );
		$this->add_responsive_control(
			'panel_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-offcanvas__panel' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'panel_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-offcanvas__panel' ) );
		$this->end_section();

		$this->start_section( 'style_overlay', array( 'label' => __( 'Overlay', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'overlay_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-oc-overlay: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'overlay_blur',
			array(
				'type'       => 'slider',
				'label'      => __( 'Background blur', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-oc-blur: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_close',
			array(
				'label'     => __( 'Close button', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'close_button' => true ),
			)
		);
		$this->add_control(
			'close_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-offcanvas__close' => '--uncoder-oc-close-size: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'close_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'close_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__close' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'close_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__close' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'close_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__close:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'close_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-offcanvas__close:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 * @return array<string,mixed>
	 */
	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array(
			'data-settings' => $this->json_attr(
				array(
					'lock'    => ! empty( $s['prevent_scroll'] ),
					'overlay' => ! empty( $s['close_on_overlay'] ),
					'anchor'  => sanitize_html_class( (string) ( $s['anchor'] ?? '' ) ),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$panel_id = 'uncoder-offcanvas-' . $ctx->element_id;
		$position = in_array( $s['position'] ?? 'right', self::POSITIONS, true ) ? $s['position'] : 'right';
		$panels   = is_array( $s['panels'] ?? null ) ? array_values( $s['panels'] ) : array();
		$label    = trim( (string) ( $panels[0]['label'] ?? '' ) );
		$text     = trim( (string) ( $s['trigger_text'] ?? '' ) );
		$label    = '' !== $label ? $label : ( '' !== $text ? $text : __( 'Menu', 'uncoder' ) );

		echo '<div class="' . esc_attr( 'uncoder-offcanvas uncoder-offcanvas--' . $position ) . '">';

		if ( ! empty( $s['show_trigger'] ) ) {
			$icon       = $this->has_icon( $s['trigger_icon'] ?? null ) ? $this->render_icon( $s['trigger_icon'], array( 'class' => 'uncoder-offcanvas__icon' ) ) : '';
			$visible    = ! empty( $s['trigger_text_visible'] ) && '' !== $text;
			$text_html  = '<span class="' . esc_attr( $visible ? 'uncoder-offcanvas__text' : 'uncoder-offcanvas__text uncoder-sr-only' ) . '"' . $ctx->inline( 'trigger_text' ) . '>' . esc_html( '' !== $text ? $text : $label ) . '</span>';
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '<button' . Utils::attrs(
				array(
					'type'          => 'button',
					'class'         => 'uncoder-offcanvas__trigger',
					'aria-haspopup' => 'dialog',
					'aria-expanded' => 'false',
					'aria-controls' => $panel_id,
				)
			) . '>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'after' === ( $s['icon_position'] ?? 'before' ) ? $text_html . $icon : $icon . $text_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '</button>';
		}

		$close = '';
		if ( ! empty( $s['close_button'] ) ) {
			$close_icon = $this->has_icon( $s['close_icon'] ?? null ) ? $s['close_icon'] : 'x';
			$close      = '<button type="button" class="uncoder-offcanvas__close" data-uncoder-oc-close><span class="uncoder-sr-only">' . esc_html__( 'Close', 'uncoder' ) . '</span>' . $this->render_icon( $close_icon ) . '</button>';
		}
		$content = '<div class="uncoder-offcanvas__content">' . $ctx->render_child( 0 ) . '</div>';

		if ( $ctx->editor ) {
			// The canvas edits the panel inline; the live page opens it as a modal dialog.
			$sides = array(
				'left'   => __( 'Off-canvas panel · opens from the left', 'uncoder' ),
				'right'  => __( 'Off-canvas panel · opens from the right', 'uncoder' ),
				'top'    => __( 'Off-canvas panel · opens from the top', 'uncoder' ),
				'bottom' => __( 'Off-canvas panel · opens from the bottom', 'uncoder' ),
			);
			echo '<div class="uncoder-offcanvas__dialog uncoder-offcanvas__dialog--preview" id="' . esc_attr( $panel_id ) . '">';
			echo '<span class="uncoder-offcanvas__preview-label">' . esc_html( $sides[ $position ] ) . '</span>';
			echo '<div class="uncoder-offcanvas__panel">' . $close . $content . '</div></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts, child render.
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '<dialog' . Utils::attrs(
			array(
				'class'      => 'uncoder-offcanvas__dialog',
				'id'         => $panel_id,
				'aria-label' => $label,
				'aria-modal' => 'true',
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="uncoder-offcanvas__overlay"' . ( ! empty( $s['close_on_overlay'] ) ? ' data-uncoder-oc-close' : '' ) . '></div>';
		echo '<div class="uncoder-offcanvas__panel">' . $close . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts, child render.
		echo '</dialog></div>';
	}
}
