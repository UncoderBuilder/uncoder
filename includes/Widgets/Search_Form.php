<?php
/**
 * Search form widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Site search: an inline field with a button, or an icon that opens a full-screen search overlay.
 */
class Search_Form extends Widget_Base {

	public function name(): string {
		return 'search-form';
	}

	public function title(): string {
		return __( 'Search Form', 'uncoder' );
	}

	public function icon(): string {
		return 'search';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'search', 'find', 'form', 'query', 'header', 'live search', 'autocomplete', 'ajax' );
	}

	public function description(): string {
		return __( 'A site search field with a button, or a search icon that opens a full-screen search overlay (for headers). Optional live results while typing.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'search-form' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	/**
	 * @return array<string,string>
	 */
	private static function post_type_options(): array {
		$out = array( '' => __( 'All content', 'uncoder' ) );
		if ( did_action( 'init' ) ) {
			foreach ( get_post_types( array( 'public' => true, 'exclude_from_search' => false ), 'objects' ) as $type ) {
				if ( 'attachment' !== $type->name ) {
					$out[ $type->name ] = $type->labels->name;
				}
			}
		}
		return $out;
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Search', 'uncoder' ) ) );
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'inline',
				'options' => array(
					'inline'  => __( 'Field and button', 'uncoder' ),
					'overlay' => __( 'Icon with full-screen overlay', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'placeholder',
			array(
				'type'    => 'text',
				'label'   => __( 'Placeholder', 'uncoder' ),
				'default' => __( 'Search…', 'uncoder' ),
			)
		);
		$this->add_control(
			'label',
			array(
				'type'        => 'text',
				'label'       => __( 'Field label', 'uncoder' ),
				'default'     => __( 'Search', 'uncoder' ),
				'description' => __( 'Read by screen readers; shown above the field when enabled below.', 'uncoder' ),
			)
		);
		$this->add_control( 'show_label', array( 'type' => 'switch', 'label' => __( 'Show label', 'uncoder' ) ) );
		$this->add_control(
			'button_type',
			array(
				'type'    => 'choose',
				'label'   => __( 'Button', 'uncoder' ),
				'default' => 'icon',
				'options' => array(
					'icon' => array( 'label' => __( 'Icon', 'uncoder' ), 'icon' => 'search' ),
					'text' => array( 'label' => __( 'Text', 'uncoder' ), 'icon' => 'type' ),
					'both' => array( 'label' => __( 'Icon and text', 'uncoder' ), 'icon' => 'text-cursor-input' ),
					'none' => array( 'label' => __( 'None', 'uncoder' ), 'icon' => 'x' ),
				),
			)
		);
		$this->add_control(
			'button_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Button text', 'uncoder' ),
				'default'   => __( 'Search', 'uncoder' ),
				'condition' => array( 'button_type!' => 'none' ),
			)
		);
		$this->add_control(
			'button_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Button icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'search' ),
				'condition' => array( 'button_type' => array( 'icon', 'both' ) ),
			)
		);
		$this->add_control(
			'post_type',
			array(
				'type'            => 'select',
				'label'           => __( 'Search in', 'uncoder' ),
				'default'         => '',
				'options_dynamic' => true,
				'options'         => self::post_type_options(),
			)
		);
		$this->add_control(
			'live',
			array(
				'type'        => 'switch',
				'label'       => __( 'Live results', 'uncoder' ),
				'description' => __( 'Lists matching pages and posts under the field while the visitor types.', 'uncoder' ),
				'ai'          => 'true shows an accessible suggestion list (published content only) while typing.',
			)
		);
		$this->add_control(
			'live_count',
			array(
				'type'      => 'number',
				'label'     => __( 'Number of results', 'uncoder' ),
				'default'   => 5,
				'min'       => 1,
				'max'       => 10,
				'condition' => array( 'live' => true ),
			)
		);
		$this->add_control(
			'live_image',
			array(
				'type'      => 'switch',
				'label'     => __( 'Show featured images', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'live' => true ),
			)
		);
		$this->add_control(
			'live_excerpt',
			array(
				'type'      => 'switch',
				'label'     => __( 'Show excerpts', 'uncoder' ),
				'condition' => array( 'live' => true ),
			)
		);
		$this->add_control(
			'live_all',
			array(
				'type'      => 'switch',
				'label'     => __( '“See all results” link', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'live' => true ),
			)
		);
		$this->add_control(
			'toggle_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Toggle icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'search' ),
				'condition' => array( 'layout' => 'overlay' ),
			)
		);
		$this->add_control(
			'toggle_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Toggle label', 'uncoder' ),
				'default'   => __( 'Open search', 'uncoder' ),
				'condition' => array( 'layout' => 'overlay' ),
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
				'condition' => array( 'layout' => 'overlay' ),
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* Field box */
		$this->start_section( 'style_field', array( 'label' => __( 'Field', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'field_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem', 'vw' ),
				'selectors'  => array( '{{WRAPPER}}.uncoder-search--inline .uncoder-search__form' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'field_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 28, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__box' => 'min-height: {{VALUE}}' ),
			)
		);
		$this->add_group( 'input_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-search__input' ) );
		$this->start_tabs( 'field_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'input_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__input' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'placeholder_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Placeholder color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__input::placeholder' => 'color: {{VALUE}}; opacity: 1' ),
			)
		);
		$this->add_control(
			'field_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__box' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'field_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-search__box' ) );
		$this->add_group( 'field_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-search__box' ) );
		$this->end_tab();
		$this->start_tab( 'focus', __( 'Focus', 'uncoder' ) );
		$this->add_control(
			'focus_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__box:focus-within' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'focus_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__box:focus-within' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'focus_ring',
			array(
				'type'      => 'color',
				'label'     => __( 'Focus ring', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__box' => '--uncoder-search-ring: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'field_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__box' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'field_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__box' => 'padding: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* Label */
		$this->start_section(
			'style_label',
			array(
				'label'     => __( 'Label', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_label' => true ),
			)
		);
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-search__label' ) );
		$this->add_control(
			'label_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__label' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'label_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__label' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* Button */
		$this->start_section(
			'style_button',
			array(
				'label'     => __( 'Button', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'button_type!' => 'none' ),
			)
		);
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-search__button' ) );
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'button_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__button' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__button' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'button_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__button:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__button:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'button_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 48 ) ),
				'condition'  => array( 'button_type' => array( 'icon', 'both' ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__button .uncoder-svg' => 'width: {{VALUE}}; height: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Min width', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__button' => 'min-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__button' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__button' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space from the field', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__box' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* Live results */
		$this->start_section(
			'style_results',
			array(
				'label'     => __( 'Live results', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'live' => true ),
			)
		);
		$this->add_group( 'results_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-search__result-title' ) );
		$this->add_control(
			'results_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__results' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'results_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__results' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'results_active_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Highlighted result', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__results' => '--uncoder-search-active: {{VALUE}}' ),
			)
		);
		$this->add_group( 'results_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-search__results' ) );
		$this->add_responsive_control(
			'results_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__results' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'results_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-search__results' ) );
		$this->add_responsive_control(
			'results_image_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Image size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 120 ) ),
				'condition'  => array( 'live_image' => true ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__results' => '--uncoder-search-thumb: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* Overlay toggle + overlay */
		$this->start_section(
			'style_toggle',
			array(
				'label'     => __( 'Toggle icon', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'layout' => 'overlay' ),
			)
		);
		$this->add_responsive_control(
			'toggle_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__toggle' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'toggle_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'toggle_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__toggle' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'toggle_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__toggle' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'toggle_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__toggle:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'toggle_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__toggle:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
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
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__toggle' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'toggle_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__toggle' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_overlay',
			array(
				'label'     => __( 'Overlay', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'layout' => 'overlay' ),
			)
		);
		$this->add_control(
			'overlay_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__backdrop' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'overlay_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__panel' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'overlay_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Form width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'vw' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-search__panel .uncoder-search__form' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->add_group( 'overlay_typography', array( 'type' => 'typography', 'label' => __( 'Input typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-search__panel .uncoder-search__input' ) );
		$this->add_control(
			'close_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Close button color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-search__close' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * The search form markup.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function form( array $s, Render_Context $ctx, bool $overlay ): string {
		$id          = 'uncoder-search-' . $ctx->element_id . ( $overlay ? '-o' : '' );
		$label       = trim( (string) ( $s['label'] ?? '' ) );
		$label       = '' !== $label ? $label : __( 'Search', 'uncoder' );
		$type        = in_array( $s['button_type'] ?? 'icon', array( 'icon', 'text', 'both', 'none' ), true ) ? $s['button_type'] : 'icon';
		$button_text = trim( (string) ( $s['button_text'] ?? '' ) );
		$button_text = '' !== $button_text ? $button_text : __( 'Search', 'uncoder' );
		$post_type   = sanitize_key( (string) ( $s['post_type'] ?? '' ) );
		$show_label  = ! empty( $s['show_label'] ) && ! $overlay;

		$live  = ! empty( $s['live'] );
		$form  = array(
			'class'  => $live ? 'uncoder-search__form uncoder-search__form--live' : 'uncoder-search__form',
			'role'   => 'search',
			'method' => 'get',
			'action' => home_url( '/' ),
		);
		if ( $live ) {
			$form['data-live'] = $this->json_attr(
				array(
					'n'       => max( 1, min( 10, (int) ( $s['live_count'] ?? 5 ) ) ),
					'image'   => ! empty( $s['live_image'] ),
					'excerpt' => ! empty( $s['live_excerpt'] ),
					'all'     => ! empty( $s['live_all'] ),
					'type'    => '' !== $post_type && post_type_exists( $post_type ) ? $post_type : '',
					'i18n'    => array(
						'none'    => __( 'No results found.', 'uncoder' ),
						'all'     => __( 'See all results', 'uncoder' ),
						/* translators: %d: number of results. */
						'count'   => __( '%d results available. Use the up and down arrow keys to browse.', 'uncoder' ),
						'loading' => __( 'Searching…', 'uncoder' ),
					),
				)
			);
		}
		$html  = '<form' . Utils::attrs( $form ) . '>';
		$html .= '<label class="' . esc_attr( $show_label ? 'uncoder-search__label' : 'uncoder-search__label uncoder-sr-only' ) . '" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		$html .= '<div class="uncoder-search__box">';
		$html .= '<input' . Utils::attrs(
			array(
				'type'              => 'search',
				'id'                => $id,
				'class'             => 'uncoder-search__input',
				'name'              => 's',
				'value'             => $ctx->editor ? '' : get_search_query( false ),
				'placeholder'       => (string) ( $s['placeholder'] ?? '' ),
				// A combobox that owns the suggestion list (ARIA 1.2 pattern).
				'role'              => $live ? 'combobox' : null,
				'aria-autocomplete' => $live ? 'list' : null,
				'aria-expanded'     => $live ? 'false' : null,
				'aria-controls'     => $live ? $id . '-results' : null,
				'autocomplete'      => $live ? 'off' : null,
			)
		) . '>';
		if ( '' !== $post_type && post_type_exists( $post_type ) ) {
			$html .= '<input type="hidden" name="post_type" value="' . esc_attr( $post_type ) . '">';
		}
		if ( 'none' !== $type ) {
			$icon  = in_array( $type, array( 'icon', 'both' ), true ) ? $this->render_icon( $this->has_icon( $s['button_icon'] ?? null ) ? $s['button_icon'] : 'search' ) : '';
			$text  = 'icon' === $type
				? '<span class="uncoder-sr-only">' . esc_html( $button_text ) . '</span>'
				: '<span class="uncoder-search__button-text">' . esc_html( $button_text ) . '</span>';
			$html .= '<button type="submit" class="uncoder-search__button uncoder-search__button--' . esc_attr( $type ) . '">' . $icon . $text . '</button>';
		}
		$html .= '</div>';
		if ( $live ) {
			$html .= '<div class="uncoder-search__results" id="' . esc_attr( $id . '-results' ) . '" role="listbox" aria-label="' . esc_attr__( 'Search suggestions', 'uncoder' ) . '" hidden></div>';
			$html .= '<p class="uncoder-search__status uncoder-sr-only" role="status" aria-live="polite" aria-atomic="true"></p>';
		}
		$html .= '</form>';
		return $html;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		if ( 'overlay' !== ( $s['layout'] ?? 'inline' ) ) {
			echo '<div class="uncoder-search uncoder-search--inline">' . $this->form( $s, $ctx, false ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			return;
		}
		$dialog_id = 'uncoder-search-' . $ctx->element_id . '-dialog';
		$label     = trim( (string) ( $s['toggle_label'] ?? '' ) );
		$label     = '' !== $label ? $label : __( 'Open search', 'uncoder' );
		$icon      = $this->has_icon( $s['toggle_icon'] ?? null ) ? $s['toggle_icon'] : 'search';

		echo '<div class="uncoder-search uncoder-search--overlay">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '<button' . Utils::attrs(
			array(
				'type'          => 'button',
				'class'         => 'uncoder-search__toggle',
				'aria-haspopup' => 'dialog',
				'aria-expanded' => 'false',
				'aria-controls' => $dialog_id,
			)
		) . '>' . $this->render_icon( $icon ) . '<span class="uncoder-sr-only">' . esc_html( $label ) . '</span></button>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<dialog class="uncoder-search__dialog" id="' . esc_attr( $dialog_id ) . '" aria-label="' . esc_attr__( 'Search', 'uncoder' ) . '">';
		echo '<div class="uncoder-search__backdrop" data-uncoder-search-close></div>';
		echo '<div class="uncoder-search__panel">';
		echo '<button type="button" class="uncoder-search__close" data-uncoder-search-close><span class="uncoder-sr-only">' . esc_html__( 'Close search', 'uncoder' ) . '</span>' . $this->render_icon( 'x' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
		echo $this->form( $s, $ctx, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '</div></dialog></div>';
	}
}
