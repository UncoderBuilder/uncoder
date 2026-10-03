<?php
/**
 * Tabs widget (nested: every tab owns a container).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * WAI-ARIA tabs whose panels are containers. The layout (horizontal, vertical, accordion) is responsive:
 * the accordion headers are printed next to the panels and shown through CSS variables per breakpoint.
 */
class Tabs extends Widget_Base {

	private const ROOT  = '{{WRAPPER}}';
	private const TAB   = '{{WRAPPER}} > .uncoder-tabs__list > .uncoder-tabs__tab';
	private const ACC   = '{{WRAPPER}} > .uncoder-tabs__panels > .uncoder-tabs__acc';
	private const LIST  = '{{WRAPPER}} > .uncoder-tabs__list';
	private const PANEL = '{{WRAPPER}} > .uncoder-tabs__panels > .uncoder-tabs__panel';

	/** Tab buttons and accordion headers share their look. */
	private const BOTH = self::TAB . ', ' . self::ACC;

	/** CSS variables per layout (consumed by widgets/tabs.css; the horizontal values are the CSS defaults). */
	public const LAYOUTS = array(
		'horizontal' => '--uncoder-tabs-dir:column;--uncoder-tabs-list-display:flex;--uncoder-tabs-list-dir:row;--uncoder-tabs-list-overflow:auto hidden;--uncoder-tabs-list-basis:auto;--uncoder-tabs-acc-display:none;--uncoder-tabs-ws:nowrap;--uncoder-tabs-line-shadow:inset 0 calc(-1 * var(--uncoder-tabs-line-width)) 0 var(--uncoder-tabs-line-color);--uncoder-tabs-line-inline:0px;--uncoder-tabs-ind-block:auto 0;--uncoder-tabs-ind-inline:0;--uncoder-tabs-ind-w:auto;--uncoder-tabs-ind-h:var(--uncoder-tabs-indicator-size);--uncoder-tabs-ind-hidden:scaleX(0);--uncoder-tabs-panel-pad:0px;--uncoder-tabs-title-align-auto:center',
		'vertical'   => '--uncoder-tabs-dir:row;--uncoder-tabs-list-display:flex;--uncoder-tabs-list-dir:column;--uncoder-tabs-list-overflow:visible;--uncoder-tabs-list-basis:var(--uncoder-tabs-list-width);--uncoder-tabs-acc-display:none;--uncoder-tabs-ws:normal;--uncoder-tabs-line-shadow:none;--uncoder-tabs-line-inline:var(--uncoder-tabs-line-width);--uncoder-tabs-ind-block:0;--uncoder-tabs-ind-inline:auto calc(-1 * var(--uncoder-tabs-line-width));--uncoder-tabs-ind-w:var(--uncoder-tabs-indicator-size);--uncoder-tabs-ind-h:auto;--uncoder-tabs-ind-hidden:scaleY(0);--uncoder-tabs-panel-pad:0px;--uncoder-tabs-title-align-auto:flex-start',
		'accordion'  => '--uncoder-tabs-dir:column;--uncoder-tabs-list-display:none;--uncoder-tabs-acc-display:flex;--uncoder-tabs-panel-pad:16px 0',
	);

	public function name(): string {
		return 'tabs';
	}

	public function title(): string {
		return __( 'Tabs', 'uncoder' );
	}

	public function icon(): string {
		return 'panels-top-left';
	}

	public function category(): string {
		return 'layout';
	}

	public function keywords(): array {
		return array( 'tabs', 'tabbed', 'panels', 'nested', 'toggle', 'accordion' );
	}

	public function description(): string {
		return __( 'Accessible tabs whose panels are containers holding any widgets. Horizontal, vertical or accordion per breakpoint; one row in "tabs" = one child container.', 'uncoder' );
	}

	public function nested(): ?array {
		return array( 'items' => 'tabs' );
	}

	public function frontend_scripts(): array {
		return array( 'tabs' );
	}

	public function preset(): array {
		return array(
			'tabs'          => $this->default_tabs(),
			'layout_mobile' => 'accordion',
		);
	}

	/**
	 * @return array<int, array<string,string>>
	 */
	private function default_tabs(): array {
		return array(
			array( 'title' => __( 'Overview', 'uncoder' ) ),
			array( 'title' => __( 'Features', 'uncoder' ) ),
			array( 'title' => __( 'Support', 'uncoder' ) ),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Tabs', 'uncoder' ) ) );
		$this->add_control(
			'tabs',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Tabs', 'uncoder' ),
				'title_field' => 'title',
				'fields'      => array(
					'title'  => array(
						'type'    => 'text',
						'label'   => __( 'Title', 'uncoder' ),
						'html'    => 'inline',
						'default' => __( 'Tab title', 'uncoder' ),
						'inline'  => true,
					),
					'icon'   => array( 'type' => 'icon', 'label' => __( 'Icon', 'uncoder' ) ),
					'anchor' => array(
						'type'        => 'text',
						'label'       => __( 'Anchor', 'uncoder' ),
						'placeholder' => 'pricing',
						'description' => __( 'With deep linking on, /page#anchor opens this tab. Letters, numbers, - and _ only.', 'uncoder' ),
					),
				),
				'default'     => $this->default_tabs(),
				'ai'          => 'Each row owns the child container at the same index (children[i]). Add/remove rows together with children.',
			)
		);
		$this->add_control(
			'active',
			array(
				'type'    => 'number',
				'label'   => __( 'Initially open tab', 'uncoder' ),
				'min'     => 1,
				'max'     => 50,
				'step'    => 1,
				'default' => 1,
			)
		);
		$this->add_responsive_control(
			'layout',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Layout', 'uncoder' ),
				'default'              => 'horizontal',
				'options'              => array(
					'horizontal' => array( 'label' => __( 'Horizontal', 'uncoder' ), 'icon' => 'panels-top-left' ),
					'vertical'   => array( 'label' => __( 'Vertical', 'uncoder' ), 'icon' => 'panel-left' ),
					'accordion'  => array( 'label' => __( 'Accordion', 'uncoder' ), 'icon' => 'list-collapse' ),
				),
				'selectors_dictionary' => self::LAYOUTS,
				'selectors'            => array( self::ROOT => '{{VALUE}}' ),
				'ai'                   => 'Common: "horizontal" on desktop with layout_mobile "accordion".',
			)
		);
		$this->add_responsive_control(
			'justify',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Tabs alignment', 'uncoder' ),
				'options'              => array(
					'start'   => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-horizontal-justify-start' ),
					'center'  => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-horizontal-justify-center' ),
					'end'     => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-horizontal-justify-end' ),
					'stretch' => array( 'label' => __( 'Stretch', 'uncoder' ), 'icon' => 'align-horizontal-space-around' ),
				),
				'selectors_dictionary' => array(
					'start'   => '--uncoder-tabs-justify:flex-start;--uncoder-tabs-grow:0',
					'center'  => '--uncoder-tabs-justify:center;--uncoder-tabs-grow:0',
					'end'     => '--uncoder-tabs-justify:flex-end;--uncoder-tabs-grow:0',
					'stretch' => '--uncoder-tabs-justify:stretch;--uncoder-tabs-grow:1',
				),
				'selectors'            => array( self::ROOT => '{{VALUE}}' ),
			)
		);
		// A segmented control (a pill around the tabs) hugs its tabs instead of spanning the width.
		$this->add_responsive_control(
			'list_fit',
			array(
				'type'                 => 'select',
				'label'                => __( 'Tab list width', 'uncoder' ),
				'description'          => __( 'Hug the tabs: the list (and its background) is only as wide as its tabs, e.g. a segmented control at the end.', 'uncoder' ),
				'options'              => array(
					''       => __( 'Full width', 'uncoder' ),
					'start'  => __( 'Hug the tabs, at the start', 'uncoder' ),
					'center' => __( 'Hug the tabs, centered', 'uncoder' ),
					'end'    => __( 'Hug the tabs, at the end', 'uncoder' ),
				),
				'selectors_dictionary' => array(
					''       => 'width:auto;margin-inline:0',
					'start'  => 'width:fit-content;margin-inline:0 auto',
					'center' => 'width:fit-content;margin-inline:auto',
					'end'    => 'width:fit-content;margin-inline:auto 0',
				),
				'condition'            => array( 'layout!' => 'vertical' ),
				'selectors'            => array( self::LIST => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Title alignment', 'uncoder' ),
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => 'flex-start',
					'center' => 'center',
					'right'  => 'flex-end',
				),
				'selectors'            => array( self::ROOT => '--uncoder-tabs-title-align: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_position',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Icon position', 'uncoder' ),
				'options'              => array(
					'before' => array( 'label' => __( 'Before', 'uncoder' ), 'icon' => 'arrow-left-to-line' ),
					'above'  => array( 'label' => __( 'Above', 'uncoder' ), 'icon' => 'arrow-up-to-line' ),
					'after'  => array( 'label' => __( 'After', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
				),
				'selectors_dictionary' => array(
					'before' => 'row',
					'above'  => 'column',
					'after'  => 'row-reverse',
				),
				'selectors'            => array( self::ROOT => '--uncoder-tabs-icon-dir: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'accordion_icon',
			array(
				'type'        => 'icon',
				'label'       => __( 'Accordion icon', 'uncoder' ),
				'description' => __( 'Shown on the accordion headers; it turns upside down when open.', 'uncoder' ),
				'default'     => array( 'library' => 'lucide', 'value' => 'chevron-down' ),
			)
		);
		$this->add_control(
			'transition',
			array(
				'type'    => 'select',
				'label'   => __( 'Panel transition', 'uncoder' ),
				'default' => 'fade',
				'options' => array(
					''      => __( 'None', 'uncoder' ),
					'fade'  => __( 'Fade', 'uncoder' ),
					'slide' => __( 'Fade and slide up', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'deep_link',
			array(
				'type'        => 'switch',
				'label'       => __( 'Deep linking', 'uncoder' ),
				'description' => __( 'Opens the tab named in the URL #hash and updates the hash when a tab is chosen.', 'uncoder' ),
			)
		);
		$this->add_control(
			'list_label',
			array(
				'type'        => 'text',
				'label'       => __( 'Accessible name', 'uncoder' ),
				'description' => __( 'Optional name of the tab list for screen readers, e.g. "Plans".', 'uncoder' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: tabs */
		$this->start_section( 'style_tabs', array( 'label' => __( 'Tabs', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::BOTH ) );
		$this->add_responsive_control(
			'tabs_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between tabs', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-tabs-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Distance to content', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-tabs-spacing: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'list_width',
			array(
				'type'        => 'slider',
				'label'       => __( 'Tab list width', 'uncoder' ),
				'description' => __( 'Vertical layout.', 'uncoder' ),
				'size_units'  => array( 'px', '%', 'rem' ),
				'range'       => array( 'px' => array( 'min' => 80, 'max' => 600 ) ),
				'selectors'   => array( self::ROOT => '--uncoder-tabs-list-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::BOTH => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( self::BOTH => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'tab_states' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-tabs-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => self::BOTH ) );
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::BOTH ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::BOTH ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-tabs-color-hover: {{VALUE}}' ),
			)
		);
		$this->add_group( 'hover_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => self::TAB . ':hover, ' . self::ACC . ':hover' ) );
		$this->add_control(
			'hover_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( self::TAB . ':hover, ' . self::ACC . ':hover' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Active', 'uncoder' ) );
		$this->add_control(
			'active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-tabs-color-active: {{VALUE}}' ),
			)
		);
		$this->add_group( 'active_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => self::TAB . '.is-active, ' . self::ACC . '.is-active' ) );
		$this->add_control(
			'active_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( self::TAB . '.is-active, ' . self::ACC . '.is-active' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'active_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::TAB . '.is-active, ' . self::ACC . '.is-active' ) );
		$this->end_tab();
		$this->end_tabs();

		$this->add_control( 'indicator_heading', array( 'type' => 'heading', 'label' => __( 'Active indicator', 'uncoder' ) ) );
		$this->add_control(
			'indicator',
			array(
				'type'        => 'switch',
				'label'       => __( 'Show indicator', 'uncoder' ),
				'description' => __( 'A line under the active tab (beside it in the vertical layout).', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'indicator_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Indicator color', 'uncoder' ),
				'condition' => array( 'indicator' => 'yes' ),
				'selectors' => array( self::ROOT => '--uncoder-tabs-indicator: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'indicator_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Indicator thickness', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 12 ) ),
				'condition'  => array( 'indicator' => 'yes' ),
				'selectors'  => array( self::ROOT => '--uncoder-tabs-indicator-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'line_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider line color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-tabs-line-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'line_width',
			array(
				'type'        => 'slider',
				'label'       => __( 'Divider line width', 'uncoder' ),
				'description' => __( 'The line between the tabs and the content. 0 hides it.', 'uncoder' ),
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 8 ) ),
				'selectors'   => array( self::ROOT => '--uncoder-tabs-line-width: {{VALUE}}' ),
			)
		);

		$this->add_control( 'list_heading', array( 'type' => 'heading', 'label' => __( 'Tab list', 'uncoder' ) ) );
		$this->add_group( 'list_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => self::LIST ) );
		$this->add_responsive_control(
			'list_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::LIST => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'list_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( self::LIST => 'border-radius: {{VALUE}}' ),
				'ai'         => 'Segmented control look: list_background + list_padding + list_radius, active_background, indicator false, line_width 0.',
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: icon */
		$this->start_section( 'style_icon', array( 'label' => __( 'Icon', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 80 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-tabs-icon-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-tabs-icon-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-tabs-icon-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'active_icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Active color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-tabs-icon-color-active: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: content */
		$this->start_section( 'style_content', array( 'label' => __( 'Content', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'content_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => self::PANEL ) );
		$this->add_group( 'content_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::PANEL ) );
		$this->add_responsive_control(
			'content_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( self::PANEL => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'content_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( self::PANEL => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'content_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::PANEL ) );
		$this->end_section();

		/* ---------------------------------------------------------------- Style: accordion mode */
		$this->start_section( 'style_accordion', array( 'label' => __( 'Accordion layout', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'accordion_notice',
			array(
				'type'  => 'notice',
				'label' => __( 'Used on breakpoints where the layout is "Accordion". Headers reuse the tab typography and colors.', 'uncoder' ),
			)
		);
		$this->add_responsive_control(
			'accordion_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between items', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-tabs-acc-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'accordion_icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( self::ACC . ' > .uncoder-tabs__acc-icon' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array( 'data-settings' => $this->json_attr( array( 'deepLink' => ! empty( $s['deep_link'] ) ) ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = Repeater_Rows::get( $this, 'tabs', $s['tabs'] ?? array() );
		if ( ! $rows ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-tabs-placeholder">' . esc_html__( 'Add a tab to start building the tabs.', 'uncoder' ) . '</div>';
			}
			return;
		}

		$uid     = 'uncoder-tabs-' . sanitize_html_class( $ctx->element_id );
		$count   = count( $rows );
		$active  = is_numeric( $s['active'] ?? '' ) ? (int) $s['active'] : 1;
		$active  = min( max( $active, 1 ), $count ) - 1;
		$classes = array( 'uncoder-tabs' );
		if ( in_array( $s['transition'] ?? 'fade', array( 'fade', 'slide' ), true ) ) {
			$classes[] = 'uncoder-tabs--anim-' . $s['transition'];
		}
		if ( empty( $s['indicator'] ) ) {
			$classes[] = 'uncoder-tabs--no-indicator';
		}
		$acc_icon = $this->render_icon( $this->has_icon( $s['accordion_icon'] ?? null ) ? $s['accordion_icon'] : 'chevron-down', array( 'class' => 'uncoder-tabs__acc-icon' ) );

		$ids = array();
		$used = array();
		foreach ( $rows as $i => $row ) {
			$n      = $i + 1;
			$anchor = sanitize_html_class( (string) ( $row['anchor'] ?? '' ) );
			if ( '' === $anchor || isset( $used[ $anchor ] ) || ! preg_match( '/^[A-Za-z]/', $anchor ) ) {
				$anchor = $uid . '-tab-' . $n;
			}
			$used[ $anchor ] = true;
			$ids[ $i ]       = array(
				'tab'   => $anchor,
				'acc'   => $uid . '-acc-' . $n,
				'panel' => $uid . '-panel-' . $n,
			);
		}

		$list_label = trim( (string) ( $s['list_label'] ?? '' ) );
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		echo '<div' . Utils::attrs(
			array(
				'class'            => 'uncoder-tabs__list',
				'role'             => 'tablist',
				'aria-orientation' => 'vertical' === ( $s['layout'] ?? 'horizontal' ) ? 'vertical' : 'horizontal',
				'aria-label'       => '' !== $list_label ? $list_label : null,
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $rows as $i => $row ) {
			$on = $i === $active;
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
			echo '<button' . Utils::attrs(
				array(
					'type'          => 'button',
					'role'          => 'tab',
					'id'            => $ids[ $i ]['tab'],
					'class'         => array( 'uncoder-tabs__tab', self::row_class( $row ), $on ? 'is-active' : '' ),
					'aria-selected' => $on ? 'true' : 'false',
					'aria-controls' => $ids[ $i ]['panel'],
					'tabindex'      => $on ? '0' : '-1',
				)
			) . '>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->label( $row, $ctx->inline( 'tabs.' . $i . '.title' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '</button>';
		}
		echo '</div><div class="uncoder-tabs__panels">';
		foreach ( $rows as $i => $row ) {
			$on  = $i === $active;
			$row_class = self::row_class( $row );
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '<button' . Utils::attrs(
				array(
					'type'          => 'button',
					'id'            => $ids[ $i ]['acc'],
					'class'         => array( 'uncoder-tabs__acc', $row_class, $on ? 'is-active' : '' ),
					'aria-expanded' => $on ? 'true' : 'false',
					'aria-controls' => $ids[ $i ]['panel'],
				)
			) . '><span class="uncoder-tabs__acc-label">' . $this->label( $row, '' ) . '</span>' . $acc_icon . '</button>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
			echo '<div' . Utils::attrs(
				array(
					'id'              => $ids[ $i ]['panel'],
					'class'           => array( 'uncoder-tabs__panel', $row_class, $on ? 'is-active' : '' ),
					'role'            => 'tabpanel',
					'aria-labelledby' => $ids[ $i ]['tab'],
					'tabindex'        => '0',
					'hidden'          => ! $on,
				)
			) . '>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $ctx->render_child( $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered child container.
			echo '</div>';
		}
		echo '</div></div>';
	}

	/**
	 * Icon + title of a tab (escaped).
	 *
	 * @param array<string,mixed> $row    Repeater row.
	 * @param string              $inline data-uncoder-inline attribute string.
	 */
	private function label( array $row, string $inline ): string {
		$icon = $this->has_icon( $row['icon'] ?? null ) ? $this->render_icon( $row['icon'], array( 'class' => 'uncoder-tabs__icon' ) ) : '';
		return $icon . '<span class="uncoder-tabs__title"' . $inline . '>' . $this->inline_html( $row['title'] ?? '' ) . '</span>';
	}

	/**
	 * @param array<string,mixed> $row Repeater row.
	 */
	private static function row_class( array $row ): string {
		$id = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );
		return '' !== $id ? 'uncoder-ri-' . $id : '';
	}
}
