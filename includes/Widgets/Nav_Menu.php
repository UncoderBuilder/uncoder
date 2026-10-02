<?php
/**
 * Nav menu widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Nav_Menu_Walker;

defined( 'ABSPATH' ) || exit;

/**
 * A WordPress menu with accessible dropdowns, pointer effects and a mobile menu
 * (dropdown, off-canvas drawer or full screen) below a chosen breakpoint.
 */
class Nav_Menu extends Widget_Base {

	public function name(): string {
		return 'nav-menu';
	}

	public function title(): string {
		return __( 'Nav Menu', 'uncoder' );
	}

	public function icon(): string {
		return 'menu';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'menu', 'navigation', 'nav', 'header', 'hamburger', 'dropdown' );
	}

	public function description(): string {
		return __( 'A WordPress menu (Appearance → Menus) with dropdown submenus and a hamburger mobile menu (dropdown, off-canvas or full screen). The main navigation of headers.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'nav-menu', 'scrollspy' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	/**
	 * Menus and menu locations for the select control.
	 *
	 * @return array<string,string>
	 */
	public static function menu_options(): array {
		$options = array( '' => __( 'First menu with items', 'uncoder' ) );
		if ( taxonomy_exists( 'nav_menu' ) ) {
			foreach ( wp_get_nav_menus() as $menu ) {
				$options[ (string) $menu->term_id ] = $menu->name;
			}
		}
		foreach ( get_registered_nav_menus() as $location => $label ) {
			/* translators: %s: theme menu location name. */
			$options[ 'location:' . $location ] = sprintf( __( 'Location: %s', 'uncoder' ), $label );
		}
		return $options;
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Menu', 'uncoder' ) ) );
		$this->add_control(
			'menu',
			array(
				'type'            => 'select',
				'label'           => __( 'Menu', 'uncoder' ),
				'default'         => '',
				'options_dynamic' => true,
				'options'         => self::menu_options(),
				'description'     => __( 'Manage menus in Appearance → Menus.', 'uncoder' ),
				'ai'              => 'A menu term id (e.g. "12"), a menu slug, or "location:primary" for a theme location. Empty = first menu with items.',
			)
		);
		$this->add_control(
			'layout',
			array(
				'type'    => 'choose',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'horizontal',
				'options' => array(
					'horizontal' => array( 'label' => __( 'Horizontal', 'uncoder' ), 'icon' => 'move-horizontal' ),
					'vertical'   => array( 'label' => __( 'Vertical', 'uncoder' ), 'icon' => 'move-vertical' ),
				),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'start'   => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-left' ),
					'center'  => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'end'     => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-right' ),
					'justify' => array( 'label' => __( 'Stretch', 'uncoder' ), 'icon' => 'align-justify' ),
				),
				'selectors_dictionary' => array(
					'start'   => 'justify-content:flex-start;--uncoder-nav-item-grow:0',
					'center'  => 'justify-content:center;--uncoder-nav-item-grow:0',
					'end'     => 'justify-content:flex-end;--uncoder-nav-item-grow:0',
					'justify' => 'justify-content:space-between;--uncoder-nav-item-grow:1',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-menu--main' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'depth',
			array(
				'type'        => 'number',
				'label'       => __( 'Levels', 'uncoder' ),
				'description' => __( '0 shows every level.', 'uncoder' ),
				'default'     => 0,
				'min'         => 0,
				'max'         => 6,
			)
		);
		$this->add_control(
			'submenu_trigger',
			array(
				'type'    => 'select',
				'label'   => __( 'Open submenus on', 'uncoder' ),
				'default' => 'hover',
				'options' => array(
					'hover' => __( 'Hover and click', 'uncoder' ),
					'click' => __( 'Click only', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'highlight_anchors',
			array(
				'type'        => 'switch',
				'label'       => __( 'Highlight #section links while scrolling', 'uncoder' ),
				'description' => __( 'One-page menus: links to sections of the current page are marked active as each section scrolls into view.', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'submenu_icon',
			array(
				'type'    => 'icon',
				'label'   => __( 'Submenu indicator', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'chevron-down' ),
			)
		);
		$this->add_control(
			'pointer',
			array(
				'type'    => 'select',
				'label'   => __( 'Pointer', 'uncoder' ),
				'default' => 'underline',
				'options' => array(
					'underline'  => __( 'Underline', 'uncoder' ),
					'overline'   => __( 'Overline', 'uncoder' ),
					'double'     => __( 'Double line', 'uncoder' ),
					'background' => __( 'Background', 'uncoder' ),
					'none'       => __( 'None (color only)', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'pointer_animation',
			array(
				'type'      => 'select',
				'label'     => __( 'Pointer animation', 'uncoder' ),
				'default'   => 'grow',
				'options'   => array(
					'grow'  => __( 'Grow', 'uncoder' ),
					'slide' => __( 'Slide', 'uncoder' ),
					'fade'  => __( 'Fade', 'uncoder' ),
					'none'  => __( 'None', 'uncoder' ),
				),
				'condition' => array( 'pointer!' => 'none' ),
			)
		);
		$this->end_section();

		$this->start_section( 'mobile', array( 'label' => __( 'Mobile menu', 'uncoder' ) ) );
		$this->add_control(
			'breakpoint',
			array(
				'type'    => 'select',
				'label'   => __( 'Show hamburger on', 'uncoder' ),
				'default' => 'tablet',
				'options' => array(
					'tablet' => __( 'Tablet and mobile', 'uncoder' ),
					'mobile' => __( 'Mobile only', 'uncoder' ),
					'all'    => __( 'All devices', 'uncoder' ),
					'none'   => __( 'Never', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'mobile_mode',
			array(
				'type'      => 'select',
				'label'     => __( 'Mobile menu', 'uncoder' ),
				'default'   => 'dropdown',
				'options'   => array(
					'dropdown'   => __( 'Dropdown', 'uncoder' ),
					'offcanvas'  => __( 'Off-canvas drawer', 'uncoder' ),
					'fullscreen' => __( 'Full screen', 'uncoder' ),
				),
				'condition' => array( 'breakpoint!' => 'none' ),
			)
		);
		$this->add_control(
			'offcanvas_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Drawer side', 'uncoder' ),
				'default'   => 'right',
				'options'   => array(
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'panel-left' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'panel-right' ),
				),
				'condition' => array(
					'breakpoint!' => 'none',
					'mobile_mode' => 'offcanvas',
				),
			)
		);
		$this->add_control(
			'dropdown_stretch',
			array(
				'type'        => 'switch',
				'label'       => __( 'Full-width dropdown', 'uncoder' ),
				'description' => __( 'Stretches the dropdown to the width of the screen.', 'uncoder' ),
				'default'     => true,
				'condition'   => array(
					'breakpoint!' => 'none',
					'mobile_mode' => 'dropdown',
				),
			)
		);
		$this->add_control(
			'toggle_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Toggle icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'menu' ),
				'condition' => array( 'breakpoint!' => 'none' ),
			)
		);
		$this->add_control(
			'toggle_close_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Close icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'x' ),
				'condition' => array( 'breakpoint!' => 'none' ),
			)
		);
		$this->add_control(
			'toggle_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Toggle label', 'uncoder' ),
				'default'   => __( 'Menu', 'uncoder' ),
				'condition' => array( 'breakpoint!' => 'none' ),
			)
		);
		$this->add_control(
			'toggle_text_visible',
			array(
				'type'        => 'switch',
				'label'       => __( 'Show label', 'uncoder' ),
				'description' => __( 'When off, the label is only read by screen readers.', 'uncoder' ),
				'condition'   => array( 'breakpoint!' => 'none' ),
			)
		);
		$this->add_responsive_control(
			'toggle_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Toggle alignment', 'uncoder' ),
				'options'              => array(
					'start'  => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'end'    => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'start'  => 'flex-start',
					'center' => 'center',
					'end'    => 'flex-end',
				),
				'condition'            => array( 'breakpoint!' => 'none' ),
				'selectors'            => array( '{{WRAPPER}}' => 'justify-content: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->register_style_controls();
	}

	private function register_style_controls(): void {
		/* ---------------------------------------------------------- Main menu */
		$this->start_section( 'style_main', array( 'label' => __( 'Main menu', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-menu--main > .uncoder-menu__item > .uncoder-menu__link' ) );
		$this->start_tabs( 'item_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-bg: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-color-hover: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-bg-hover: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pointer_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Pointer color', 'uncoder' ),
				'condition' => array( 'pointer' => array( 'underline', 'overline', 'double' ) ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-pointer: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Active', 'uncoder' ) );
		$this->add_control(
			'active_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Text color', 'uncoder' ),
				'description' => __( 'The current page and its parent items.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-nav-color-active: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'active_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-bg-active: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'active_pointer_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Pointer color', 'uncoder' ),
				'condition' => array( 'pointer' => array( 'underline', 'overline', 'double' ) ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-pointer-active: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_control(
			'pointer_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Pointer thickness', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
				'condition'  => array( 'pointer' => array( 'underline', 'overline', 'double' ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-pointer-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'item_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Item padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-menu--main' => '--uncoder-nav-pt: {{TOP}}{{UNIT}}; --uncoder-nav-pr: {{RIGHT}}{{UNIT}}; --uncoder-nav-pb: {{BOTTOM}}{{UNIT}}; --uncoder-nav-pl: {{LEFT}}{{UNIT}}' ),
			)
		);
		$this->add_responsive_control(
			'item_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between items', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-menu--main' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'item_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Item radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'indicator_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Indicator size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 6, 'max' => 32 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-ind-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'indicator_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Indicator spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-ind-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------- Dropdown */
		$this->start_section( 'style_dropdown', array( 'label' => __( 'Dropdown', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'dd_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-menu--main .uncoder-menu__sub .uncoder-menu__link' ) );
		$this->start_tabs( 'dd_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'dd_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-dd-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dd_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-dd-bg: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'dd_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-dd-color-hover: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dd_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Item background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-dd-bg-hover: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Active', 'uncoder' ) );
		$this->add_control(
			'dd_active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-dd-color-active: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dd_active_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Item background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-dd-bg-active: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_control(
			'dd_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Min width', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 120, 'max' => 600 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-dd-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dd_offset',
			array(
				'type'        => 'slider',
				'label'       => __( 'Distance', 'uncoder' ),
				'description' => __( 'Space between the menu and its dropdowns.', 'uncoder' ),
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-nav-dd-offset: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dd_padding',
			array(
				'type'       => 'slider',
				'label'      => __( 'Inner padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-dd-pad: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'dd_item_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Item padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-dd-pt: {{TOP}}{{UNIT}}; --uncoder-nav-dd-pr: {{RIGHT}}{{UNIT}}; --uncoder-nav-dd-pb: {{BOTTOM}}{{UNIT}}; --uncoder-nav-dd-pl: {{LEFT}}{{UNIT}}' ),
			)
		);
		$this->add_control(
			'dd_item_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Item radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-dd-item-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dd_divider',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-menu--main .uncoder-menu__sub > .uncoder-menu__item + .uncoder-menu__item' => 'border-top: 1px solid {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'dd_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-menu--main .uncoder-menu__sub' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'dd_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-menu--main .uncoder-menu__sub' ) );
		$this->add_group( 'dd_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-menu--main .uncoder-menu__sub' ) );
		$this->end_section();

		/* ---------------------------------------------------------- Toggle */
		$this->start_section(
			'style_toggle',
			array(
				'label'     => __( 'Hamburger toggle', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'breakpoint!' => 'none' ),
			)
		);
		$this->add_responsive_control(
			'toggle_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-nav-menu__toggle' => '--uncoder-nav-toggle-size: {{VALUE}}' ),
			)
		);
		$this->add_group( 'toggle_typography', array( 'type' => 'typography', 'label' => __( 'Label typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-nav-menu__toggle-text', 'condition' => array( 'toggle_text_visible' => true ) ) );
		$this->start_tabs( 'toggle_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'toggle_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-nav-menu__toggle' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'toggle_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-nav-menu__toggle' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'toggle_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-nav-menu__toggle:is(:hover, :focus-visible, [aria-expanded="true"])' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'toggle_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-nav-menu__toggle:is(:hover, :focus-visible, [aria-expanded="true"])' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'toggle_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-nav-menu__toggle' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'toggle_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-nav-menu__toggle' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'toggle_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-nav-menu__toggle' ) );
		$this->end_section();

		/* ---------------------------------------------------------- Mobile panel */
		$this->start_section(
			'style_mobile',
			array(
				'label'     => __( 'Mobile menu', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'breakpoint!' => 'none' ),
			)
		);
		$this->add_group( 'm_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-menu--mobile .uncoder-menu__link' ) );
		$this->add_control(
			'm_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-m-bg: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'm_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'm_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-m-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'm_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-m-color-hover: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'm_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Item background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-m-bg-hover: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Active', 'uncoder' ) );
		$this->add_control(
			'm_active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-m-color-active: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'm_item_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Item padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-m-pt: {{TOP}}{{UNIT}}; --uncoder-nav-m-pr: {{RIGHT}}{{UNIT}}; --uncoder-nav-m-pb: {{BOTTOM}}{{UNIT}}; --uncoder-nav-m-pl: {{LEFT}}{{UNIT}}' ),
			)
		);
		$this->add_control(
			'm_divider',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-m-divider: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'm_align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-menu--mobile' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'm_offset',
			array(
				'type'       => 'slider',
				'label'      => __( 'Distance from toggle', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'condition'  => array( 'mobile_mode' => 'dropdown' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-m-offset: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'm_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Drawer width', 'uncoder' ),
				'size_units' => array( 'px', 'vw', '%' ),
				'range'      => array( 'px' => array( 'min' => 200, 'max' => 800 ) ),
				'condition'  => array( 'mobile_mode' => 'offcanvas' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-nav-m-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'm_overlay',
			array(
				'type'      => 'color',
				'label'     => __( 'Overlay color', 'uncoder' ),
				'condition' => array( 'mobile_mode' => 'offcanvas' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-nav-m-overlay: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'm_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Panel padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-nav-menu__inner' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'm_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-nav-menu__inner' ) );
		$this->add_control(
			'm_close_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Close button color', 'uncoder' ),
				'condition' => array( 'mobile_mode' => array( 'offcanvas', 'fullscreen' ) ),
				'selectors' => array( '{{WRAPPER}} .uncoder-nav-menu__close' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'm_close_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Close button size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 64 ) ),
				'condition'  => array( 'mobile_mode' => array( 'offcanvas', 'fullscreen' ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-nav-menu__close' => '--uncoder-nav-close-size: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Data for the front-end module.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<string,mixed>
	 */
	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array(
			'data-settings' => $this->json_attr(
				array(
					'trigger' => 'click' === ( $s['submenu_trigger'] ?? 'hover' ) ? 'click' : 'hover',
					'stretch'   => 'dropdown' === ( $s['mobile_mode'] ?? 'dropdown' ) && ! empty( $s['dropdown_stretch'] ),
					'scrollspy' => ! array_key_exists( 'highlight_anchors', $s ) || ! empty( $s['highlight_anchors'] ),
				)
			),
		);
	}

	/**
	 * Resolves the menu setting (id, slug/name, "location:slug" or a bare location slug).
	 */
	private function resolve_menu( string $value ): ?\WP_Term {
		if ( ! taxonomy_exists( 'nav_menu' ) ) {
			return null;
		}
		$menu = null;
		if ( '' === $value ) {
			foreach ( wp_get_nav_menus() as $candidate ) {
				if ( $candidate->count > 0 ) {
					$menu = $candidate;
					break;
				}
			}
		} elseif ( ctype_digit( $value ) ) {
			$menu = wp_get_nav_menu_object( (int) $value );
		} else {
			$is_location = 0 === strpos( $value, 'location:' );
			$location    = $is_location ? substr( $value, 9 ) : $value;
			$locations   = get_nav_menu_locations();
			if ( ! empty( $locations[ $location ] ) ) {
				$menu = wp_get_nav_menu_object( (int) $locations[ $location ] );
			} elseif ( ! $is_location ) {
				$menu = wp_get_nav_menu_object( $value );
			}
		}
		return $menu instanceof \WP_Term ? $menu : null;
	}

	/**
	 * Visibility classes: [ main nav, mobile toggle + panel ]. Null = part not rendered.
	 * Uses the Design System's .uncoder-hide-{device} classes so the switch follows the configured breakpoints.
	 *
	 * @return array{0:?string,1:?string}
	 */
	private function visibility( string $breakpoint ): array {
		if ( 'none' === $breakpoint ) {
			return array( '', null );
		}
		if ( 'all' === $breakpoint ) {
			return array( null, '' );
		}
		$active = Breakpoints::active();
		if ( ! isset( $active[ $breakpoint ] ) || 'max' !== $active[ $breakpoint ]['direction'] ) {
			$breakpoint = isset( $active['mobile'] ) ? 'mobile' : '';
			if ( '' === $breakpoint ) {
				return array( '', null );
			}
		}
		$limit     = (int) $active[ $breakpoint ]['value'];
		$collapsed = array();
		$expanded  = array( 'desktop' );
		foreach ( $active as $device => $bp ) {
			if ( 'max' === $bp['direction'] && (int) $bp['value'] <= $limit ) {
				$collapsed[] = $device;
			} else {
				$expanded[] = $device;
			}
		}
		$classes = static function ( array $devices ): string {
			return implode( ' ', array_map( static fn( $d ) => 'uncoder-hide-' . $d, $devices ) );
		};
		return array( $classes( $collapsed ), $classes( $expanded ) );
	}

	/**
	 * Menu markup for one copy (main or mobile).
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function menu_html( ?\WP_Term $menu, array $s, Render_Context $ctx, bool $mobile ): string {
		$icon      = $this->has_icon( $s['submenu_icon'] ?? null ) ? $s['submenu_icon'] : 'chevron-down';
		$indicator = $this->render_icon( $icon, array( 'class' => 'uncoder-menu__indicator' ) );
		$walker    = new Nav_Menu_Walker( 'uncoder-' . $ctx->element_id . ( $mobile ? '-m' : '-d' ), $indicator, $s, $mobile );
		$class     = 'uncoder-menu ' . ( $mobile ? 'uncoder-menu--mobile' : 'uncoder-menu--main' );
		$depth     = max( 0, min( 6, (int) ( $s['depth'] ?? 0 ) ) );

		if ( null === $menu ) {
			if ( ! $ctx->editor ) {
				return '';
			}
			$args = (object) array(
				'menu'        => '',
				'depth'       => $depth,
				'walker'      => $walker,
				'before'      => '',
				'after'       => '',
				'link_before' => '',
				'link_after'  => '',
			);
			return '<ul class="' . esc_attr( $class ) . '">' . walk_nav_menu_tree( $this->sample_items(), $depth, $args ) . '</ul>';
		}

		$html = wp_nav_menu(
			array(
				'menu'         => $menu,
				'container'    => '',
				'menu_class'   => $class,
				'items_wrap'   => '<ul class="%2$s">%3$s</ul>',
				'depth'        => $depth,
				'walker'       => $walker,
				'echo'         => false,
				'fallback_cb'  => '__return_empty_string',
				'item_spacing' => 'discard',
			)
		);
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Placeholder items for the editor when the site has no menu yet.
	 *
	 * @return \WP_Post[]
	 */
	private function sample_items(): array {
		$rows  = array(
			1 => array( __( 'Home', 'uncoder' ), 0 ),
			2 => array( __( 'About', 'uncoder' ), 0 ),
			3 => array( __( 'Services', 'uncoder' ), 0 ),
			4 => array( __( 'Brand strategy', 'uncoder' ), 3 ),
			5 => array( __( 'Web design', 'uncoder' ), 3 ),
			6 => array( __( 'Development', 'uncoder' ), 3 ),
			7 => array( __( 'Journal', 'uncoder' ), 0 ),
			8 => array( __( 'Contact', 'uncoder' ), 0 ),
		);
		$items = array();
		foreach ( $rows as $i => $row ) {
			$id      = 990000 + $i;
			$items[] = new \WP_Post(
				(object) array(
					'ID'                    => $id,
					'db_id'                 => $id,
					'menu_item_parent'      => $row[1] ? (string) ( 990000 + $row[1] ) : '0',
					'menu_order'            => $i,
					'title'                 => $row[0],
					'url'                   => '#',
					'classes'               => array( '' ),
					'target'                => '',
					'attr_title'            => '',
					'xfn'                   => '',
					'type'                  => 'custom',
					'object'                => 'custom',
					'object_id'             => (string) $id,
					'current'               => 1 === $i,
					'current_item_ancestor' => false,
					'current_item_parent'   => false,
				)
			);
		}
		return $items;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$menu = $this->resolve_menu( (string) ( $s['menu'] ?? '' ) );
		if ( null === $menu && ! $ctx->editor ) {
			return;
		}
		list( $main_class, $mobile_class ) = $this->visibility( (string) ( $s['breakpoint'] ?? 'tablet' ) );

		$label = $menu ? $menu->name : __( 'Main menu', 'uncoder' );
		$mode  = in_array( $s['mobile_mode'] ?? 'dropdown', array( 'dropdown', 'offcanvas', 'fullscreen' ), true ) ? $s['mobile_mode'] : 'dropdown';
		$main  = null !== $main_class ? $this->menu_html( $menu, $s, $ctx, false ) : '';
		$copy  = null !== $mobile_class ? $this->menu_html( $menu, $s, $ctx, true ) : '';
		if ( '' === $main && '' === $copy ) {
			return;
		}

		$pointer   = in_array( $s['pointer'] ?? 'underline', array( 'underline', 'overline', 'double', 'background', 'none' ), true ) ? $s['pointer'] : 'underline';
		$animation = in_array( $s['pointer_animation'] ?? 'grow', array( 'grow', 'slide', 'fade', 'none' ), true ) ? $s['pointer_animation'] : 'grow';
		$classes   = array(
			'uncoder-nav-menu',
			'uncoder-nav-menu--' . ( 'vertical' === ( $s['layout'] ?? 'horizontal' ) ? 'vertical' : 'horizontal' ),
			'uncoder-nav-menu--pointer-' . $pointer,
			'uncoder-nav-menu--anim-' . $animation,
			'uncoder-nav-menu--' . ( 'click' === ( $s['submenu_trigger'] ?? 'hover' ) ? 'click' : 'hover' ),
		);
		$panel_id = 'uncoder-' . $ctx->element_id . '-panel';

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';

		if ( '' !== $main ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- walker output is escaped.
			echo '<nav' . Utils::attrs(
				array(
					'class'      => trim( 'uncoder-nav-menu__main ' . $main_class ),
					'aria-label' => $label,
				)
			) . '>' . $main . '</nav>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( '' !== $copy ) {
			$open_icon  = $this->has_icon( $s['toggle_icon'] ?? null ) ? $s['toggle_icon'] : 'menu';
			$close_icon = $this->has_icon( $s['toggle_close_icon'] ?? null ) ? $s['toggle_close_icon'] : 'x';
			$text       = trim( (string) ( $s['toggle_text'] ?? '' ) );
			$text       = '' !== $text ? $text : __( 'Menu', 'uncoder' );

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '<button' . Utils::attrs(
				array(
					'type'          => 'button',
					'class'         => trim( 'uncoder-nav-menu__toggle ' . $mobile_class ),
					'aria-expanded' => 'false',
					'aria-controls' => $panel_id,
					'aria-haspopup' => 'dropdown' === $mode ? null : 'dialog',
				)
			) . '>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<span class="uncoder-nav-menu__toggle-icon uncoder-nav-menu__toggle-icon--open">' . $this->render_icon( $open_icon ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
			echo '<span class="uncoder-nav-menu__toggle-icon uncoder-nav-menu__toggle-icon--close">' . $this->render_icon( $close_icon ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
			echo '<span class="' . esc_attr( ! empty( $s['toggle_text_visible'] ) ? 'uncoder-nav-menu__toggle-text' : 'uncoder-nav-menu__toggle-text uncoder-sr-only' ) . '">' . esc_html( $text ) . '</span>';
			echo '</button>';

			$nav = '<nav class="uncoder-nav-menu__mobile" aria-label="' . esc_attr( $label ) . '">' . $copy . '</nav>';
			if ( 'dropdown' === $mode ) {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
				echo '<div' . Utils::attrs(
					array(
						'class'  => trim( 'uncoder-nav-menu__panel uncoder-nav-menu__panel--dropdown ' . $mobile_class ),
						'id'     => $panel_id,
						'hidden' => true,
					)
				) . '><div class="uncoder-nav-menu__inner">' . $nav . '</div></div>';
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				$side = 'left' === ( $s['offcanvas_position'] ?? 'right' ) ? 'left' : 'right';
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
				echo '<dialog' . Utils::attrs(
					array(
						'class'      => trim( 'uncoder-nav-menu__panel uncoder-nav-menu__panel--dialog uncoder-nav-menu__panel--' . $mode . ' uncoder-nav-menu__panel--' . $side . ' ' . $mobile_class ),
						'id'         => $panel_id,
						'aria-label' => $label,
					)
				) . '>';
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<div class="uncoder-nav-menu__overlay" data-uncoder-nav-close></div>';
				echo '<div class="uncoder-nav-menu__inner">';
				echo '<button type="button" class="uncoder-nav-menu__close" data-uncoder-nav-close><span class="uncoder-sr-only">' . esc_html__( 'Close menu', 'uncoder' ) . '</span>' . $this->render_icon( $close_icon ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
				echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
				echo '</div></dialog>';
			}
		}

		echo '</div>';
	}
}
