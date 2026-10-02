<?php
/**
 * Icon box widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * An icon (or small image) above or beside a title, a short text and an optional link.
 */
class Icon_Box extends Widget_Base {

	public const POSITIONS = array(
		'top'   => array( 'label' => 'Top', 'icon' => 'panel-top' ),
		'left'  => array( 'label' => 'Left', 'icon' => 'panel-left' ),
		'right' => array( 'label' => 'Right', 'icon' => 'panel-right' ),
	);

	public const POSITION_CSS = array(
		'top'   => '--uncoder-ibox-dir:column;--uncoder-ibox-items:stretch',
		'left'  => '--uncoder-ibox-dir:row;--uncoder-ibox-items:var(--uncoder-ibox-v, flex-start)',
		'right' => '--uncoder-ibox-dir:row-reverse;--uncoder-ibox-items:var(--uncoder-ibox-v, flex-start)',
	);

	public const VALIGN = array(
		'top'    => array( 'label' => 'Top', 'icon' => 'align-start-vertical' ),
		'middle' => array( 'label' => 'Middle', 'icon' => 'align-center-vertical' ),
		'bottom' => array( 'label' => 'Bottom', 'icon' => 'align-end-vertical' ),
	);

	public const BOX_HOVER = array(
		''            => 'None',
		'lift'         => 'Lift',
		'shadow'       => 'Shadow',
		'lift-shadow'  => 'Lift + shadow',
	);

	public const ICON_HOVER = array(
		''       => 'None',
		'grow'   => 'Grow',
		'float'  => 'Float',
		'rotate' => 'Rotate',
	);

	public function name(): string {
		return 'icon-box';
	}

	public function title(): string {
		return __( 'Icon Box', 'uncoder' );
	}

	public function icon(): string {
		return 'square-star';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'icon box', 'feature', 'service', 'card', 'benefit', 'info box' );
	}

	public function description(): string {
		return __( 'An icon or small image with a title, a short description and an optional link. Use for feature lists, services and contact blocks.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Icon box', 'uncoder' ) ) );
		$this->add_control(
			'media_type',
			array(
				'type'    => 'choose',
				'label'   => __( 'Media', 'uncoder' ),
				'default' => 'icon',
				'options' => array(
					'icon'  => array( 'label' => __( 'Icon', 'uncoder' ), 'icon' => 'star' ),
					'image' => array( 'label' => __( 'Image', 'uncoder' ), 'icon' => 'image' ),
					'none'  => array( 'label' => __( 'None', 'uncoder' ), 'icon' => 'ban' ),
				),
			)
		);
		$this->add_control(
			'icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'zap' ),
				'condition' => array( 'media_type' => 'icon' ),
			)
		);
		$this->add_control(
			'view',
			array(
				'type'      => 'select',
				'label'     => __( 'View', 'uncoder' ),
				'default'   => 'default',
				'options'   => array(
					'default' => __( 'Default', 'uncoder' ),
					'stacked' => __( 'Stacked', 'uncoder' ),
					'framed'  => __( 'Framed', 'uncoder' ),
				),
				'condition' => array( 'media_type' => 'icon' ),
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
				'condition' => array(
					'media_type' => 'icon',
					'view!'      => 'default',
				),
			)
		);
		$this->add_control(
			'image',
			array(
				'type'      => 'media',
				'label'     => __( 'Image', 'uncoder' ),
				'default'   => array( 'id' => 0, 'url' => '' ),
				'dynamic'   => true,
				'condition' => array( 'media_type' => 'image' ),
				'ai'        => 'Small illustration or logo. Upload it first and pass {"id": attachment_id}.',
			)
		);
		$this->add_control(
			'image_size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'thumbnail',
				'options_dynamic' => true,
				'options'         => array(
					'thumbnail'    => 'Thumbnail',
					'medium'       => 'Medium',
					'medium_large' => 'Medium large',
					'large'        => 'Large',
					'full'         => 'Full',
				),
				'condition'       => array( 'media_type' => 'image' ),
			)
		);
		$this->add_control(
			'title',
			array(
				'type'    => 'text',
				'label'   => __( 'Title', 'uncoder' ),
				'html'    => 'inline',
				'default' => __( 'Fast turnaround', 'uncoder' ),
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
				'rows'    => 4,
				'default' => __( 'Most projects go from first call to launch in under three weeks, with a clear plan at every step.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'h3',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control( 'link', array( 'type' => 'url', 'label' => __( 'Link', 'uncoder' ), 'dynamic' => true ) );
		$this->add_control(
			'link_click',
			array(
				'type'      => 'select',
				'label'     => __( 'Clickable area', 'uncoder' ),
				'default'   => 'box',
				'options'   => array(
					'box'    => __( 'Whole box', 'uncoder' ),
					'title'  => __( 'Icon and title', 'uncoder' ),
					'button' => __( 'Link text only', 'uncoder' ),
				),
				'condition' => array( 'link.url!' => '' ),
			)
		);
		$this->add_control(
			'link_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Link text', 'uncoder' ),
				'placeholder' => __( 'Learn more', 'uncoder' ),
				'description' => __( 'Optional call to action shown under the description.', 'uncoder' ),
				'inline'      => true,
				'dynamic'     => true,
			)
		);
		$this->add_control(
			'link_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Link icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'arrow-right' ),
				'condition' => array( 'link_text!' => '' ),
			)
		);
		$this->add_responsive_control(
			'position',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Media position', 'uncoder' ),
				'default'              => 'top',
				'options'              => self::POSITIONS,
				'selectors_dictionary' => self::POSITION_CSS,
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'condition'            => array( 'media_type!' => 'none' ),
				'ai'                   => 'Common pattern: "left" on desktop and "top" on mobile (position_mobile).',
			)
		);
		$this->add_responsive_control(
			'vertical_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Vertical alignment', 'uncoder' ),
				'description'          => __( 'Applies when the media sits left or right of the text.', 'uncoder' ),
				'options'              => self::VALIGN,
				'selectors_dictionary' => array(
					'top'    => 'flex-start',
					'middle' => 'center',
					'bottom' => 'flex-end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '--uncoder-ibox-v: {{VALUE}}' ),
				'condition'            => array( 'media_type!' => 'none' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => Heading::ALIGN,
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->register_box_style();
		$this->register_media_style();
		$this->register_content_style();
	}

	private function register_box_style(): void {
		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'box_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'box_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'box_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_group( 'box_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_group( 'box_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_group( 'box_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_group( 'hover_box_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover' ) );
		$this->add_control(
			'hover_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'hover_box_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover' ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_control(
			'hover_effect',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover effect', 'uncoder' ),
				'options' => self::BOX_HOVER,
			)
		);
		$this->add_control(
			'hover_lift',
			array(
				'type'       => 'slider',
				'label'      => __( 'Lift distance', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'condition'  => array( 'hover_effect' => array( 'lift', 'lift-shadow' ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ibox-lift: calc({{VALUE}} * -1)' ),
			)
		);
		$this->add_control(
			'transition',
			array(
				'type'      => 'number',
				'label'     => __( 'Transition (ms)', 'uncoder' ),
				'min'       => 0,
				'max'       => 3000,
				'step'      => 50,
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-ibox-dur: {{VALUE}}ms' ),
			)
		);
		$this->end_section();
	}

	private function register_media_style(): void {
		$this->start_section(
			'style_icon',
			array(
				'label'     => __( 'Icon', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'media_type' => 'icon' ),
			)
		);
		$this->start_tabs( 'icon_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-box__icon' => '--uncoder-icon-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_shape_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Shape color', 'uncoder' ),
				'condition' => array( 'view!' => 'default' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-box__icon' => '--uncoder-icon-shape: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-icon-box__icon' => '--uncoder-icon-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_icon_shape_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Shape color', 'uncoder' ),
				'condition' => array( 'view!' => 'default' ),
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-icon-box__icon' => '--uncoder-icon-shape: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_hover_effect',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover animation', 'uncoder' ),
				'options' => self::ICON_HOVER,
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 200 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-icon-box__icon' => '--uncoder-icon-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_padding',
			array(
				'type'       => 'slider',
				'label'      => __( 'Shape padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'view!' => 'default' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-icon-box__icon' => '--uncoder-icon-pad: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_border_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Frame width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 12 ) ),
				'condition'  => array( 'view' => 'framed' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-icon-box__icon' => 'border-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_radius',
			array(
				'type'        => 'dimensions',
				'label'       => __( 'Shape radius', 'uncoder' ),
				'description' => __( 'Overrides the shape corners.', 'uncoder' ),
				'size_units'  => array( 'px', '%', 'em' ),
				'condition'   => array( 'view!' => 'default' ),
				'selectors'   => array( '{{WRAPPER}} .uncoder-icon-box__icon' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_stroke',
			array(
				'type'      => 'number',
				'label'     => __( 'Stroke width', 'uncoder' ),
				'min'       => 0.5,
				'max'       => 4,
				'step'      => 0.25,
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-box__icon .uncoder-svg' => 'stroke-width: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_rotate',
			array(
				'type'       => 'slider',
				'label'      => __( 'Rotate', 'uncoder' ),
				'size_units' => array( 'deg' ),
				'range'      => array( 'deg' => array( 'min' => -180, 'max' => 180 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-icon-box__icon .uncoder-svg' => 'transform: rotate({{VALUE}})' ),
			)
		);
		$this->add_responsive_control(
			'icon_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ibox-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_image',
			array(
				'label'     => __( 'Image', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'media_type' => 'image' ),
			)
		);
		$this->add_responsive_control(
			'image_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 16, 'max' => 400 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-icon-box__image' => '--uncoder-ibox-img-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'image_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Aspect ratio', 'uncoder' ),
				'options'   => array(
					''     => __( 'Original', 'uncoder' ),
					'1/1'  => '1:1',
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'3/4'  => '3:4',
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-box__image img' => 'aspect-ratio: {{VALUE}}; object-fit: cover' ),
			)
		);
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-icon-box__image' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'image_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-icon-box__image' ) );
		$this->add_group( 'image_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-icon-box__image' ) );
		$this->add_responsive_control(
			'image_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ibox-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	private function register_content_style(): void {
		$this->start_section( 'style_content', array( 'label' => __( 'Content', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control( 'title_heading', array( 'type' => 'heading', 'label' => __( 'Title', 'uncoder' ) ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-icon-box__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-box__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'title_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-icon-box__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-icon-box__title' => 'margin-bottom: {{VALUE}}' ),
			)
		);

		$this->add_control( 'description_heading', array( 'type' => 'heading', 'label' => __( 'Description', 'uncoder' ) ) );
		$this->add_group( 'description_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-icon-box__description' ) );
		$this->add_control(
			'description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-box__description' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'description_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-icon-box__description' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'description_max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', 'ch', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-icon-box__description' => 'max-width: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'link_heading',
			array(
				'type'      => 'heading',
				'label'     => __( 'Link text', 'uncoder' ),
				'condition' => array( 'link_text!' => '' ),
			)
		);
		$this->add_group(
			'link_typography',
			array(
				'type'      => 'typography',
				'label'     => __( 'Typography', 'uncoder' ),
				'selector'  => '{{WRAPPER}} .uncoder-icon-box__cta',
				'condition' => array( 'link_text!' => '' ),
			)
		);
		$this->add_control(
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'condition' => array( 'link_text!' => '' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-box__cta' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'condition' => array( 'link_text!' => '' ),
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-icon-box__cta' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'link_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'condition'  => array( 'link_text!' => '' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-icon-box__cta' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$title       = $this->inline_html( $s['title'] ?? '' );
		$description = $this->inline_html( $s['description'] ?? '' );
		$link_text   = (string) ( $s['link_text'] ?? '' );
		$has_title   = '' !== trim( wp_strip_all_tags( $title ) );
		$has_desc    = '' !== trim( wp_strip_all_tags( $description ) );
		$media       = $this->media_html( $s, $ctx );

		if ( ! $has_title && ! $has_desc && '' === $media && '' === $link_text && ! $ctx->editor ) {
			return;
		}

		$link  = $this->link_attrs( $s['link'] ?? array() );
		$click = in_array( $s['link_click'] ?? 'box', array( 'box', 'title', 'button' ), true ) ? (string) ( $s['link_click'] ?? 'box' ) : 'box';
		if ( 'button' === $click && '' === $link_text ) {
			$click = 'title';
		}

		$classes = array( 'uncoder-icon-box', 'uncoder-icon-box--media-' . sanitize_html_class( (string) ( $s['media_type'] ?? 'icon' ) ) );
		if ( ! empty( $s['hover_effect'] ) && isset( self::BOX_HOVER[ $s['hover_effect'] ] ) ) {
			foreach ( explode( '-', (string) $s['hover_effect'] ) as $effect ) {
				$classes[] = 'uncoder-icon-box--hover-' . $effect;
			}
		}
		if ( ! empty( $s['icon_hover_effect'] ) && isset( self::ICON_HOVER[ $s['icon_hover_effect'] ] ) ) {
			$classes[] = 'uncoder-icon-box--icon-' . $s['icon_hover_effect'];
		}
		if ( $link && 'box' === $click ) {
			$classes[] = 'uncoder-icon-box--linked';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';

		if ( '' !== $media ) {
			echo '<div class="uncoder-icon-box__media">';
			if ( $link && 'title' === $click ) {
				// Duplicate of the title link for pointer users only; the title link is the accessible one.
				$media_link                = $link;
				$media_link['class']       = 'uncoder-icon-box__media-link';
				$media_link['tabindex']    = '-1';
				$media_link['aria-hidden'] = 'true';
				echo '<a' . Utils::attrs( $media_link ) . '>' . $media . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes; media markup is escaped.
			} else {
				echo $media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
			}
			echo '</div>';
		}

		// In "whole box" mode the title link stretches over the box; without a title the link text or a hidden label carries it.
		$stretch_on = '';
		if ( $link && 'box' === $click ) {
			$stretch_on = $has_title ? 'title' : ( '' !== $link_text ? 'cta' : 'overlay' );
		}
		if ( ! $has_title && ! $has_desc && '' === $link_text && '' === $stretch_on && ! $ctx->editor ) {
			echo '</div>';
			return;
		}
		echo '<div class="uncoder-icon-box__content">';

		if ( $has_title || $ctx->editor ) {
			$tag = Utils::tag( $s['title_tag'] ?? 'h3', Utils::HEADING_TAGS, 'h3' );
			if ( $link && ( 'title' === $click || 'title' === $stretch_on ) ) {
				$title_link          = $link;
				$title_link['class'] = 'uncoder-icon-box__title-link' . ( 'title' === $stretch_on ? ' uncoder-icon-box__stretched' : '' );
				echo '<' . $tag . ' class="uncoder-icon-box__title"><a' . Utils::attrs( $title_link ) . $ctx->inline( 'title' ) . '>' . $title . '</a></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, attrs() escapes, kses'd title.
			} else {
				echo '<' . $tag . ' class="uncoder-icon-box__title"' . $ctx->inline( 'title' ) . '>' . $title . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, kses'd title.
			}
		}

		if ( $has_desc || $ctx->editor ) {
			echo '<p class="uncoder-icon-box__description"' . $ctx->inline( 'description' ) . '>' . $description . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
		}

		if ( '' !== $link_text ) {
			$icon = $this->has_icon( $s['link_icon'] ?? null ) ? $this->render_icon( $s['link_icon'], array( 'class' => 'uncoder-icon-box__cta-icon' ) ) : '';
			$text = '<span class="uncoder-icon-box__cta-text"' . $ctx->inline( 'link_text' ) . '>' . esc_html( $link_text ) . '</span>';
			if ( $link && 'box' !== $click ) {
				$cta          = $link;
				$cta['class'] = 'uncoder-icon-box__cta';
				if ( 'title' === $click && $has_title ) {
					$cta['tabindex']    = '-1';
					$cta['aria-hidden'] = 'true';
				}
				echo '<a' . Utils::attrs( $cta ) . '>' . $text . $icon . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			} elseif ( 'cta' === $stretch_on ) {
				$cta          = $link;
				$cta['class'] = 'uncoder-icon-box__cta uncoder-icon-box__stretched';
				echo '<a' . Utils::attrs( $cta ) . '>' . $text . $icon . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			} else {
				echo '<span class="uncoder-icon-box__cta">' . $text . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			}
		}

		if ( 'overlay' === $stretch_on ) {
			$overlay          = $link;
			$overlay['class'] = 'uncoder-icon-box__stretched uncoder-icon-box__overlay';
			$label            = wp_strip_all_tags( $description );
			echo '<a' . Utils::attrs( $overlay ) . '><span class="uncoder-sr-only">' . esc_html( '' !== trim( $label ) ? $label : __( 'Open link', 'uncoder' ) ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		}
		echo '</div></div>';
	}

	/**
	 * Icon or image markup (escaped), or '' when there is nothing to show.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function media_html( array $s, Render_Context $ctx ): string {
		$type = $s['media_type'] ?? 'icon';
		if ( 'icon' === $type ) {
			if ( ! $this->has_icon( $s['icon'] ?? null ) ) {
				return '';
			}
			$view    = in_array( $s['view'] ?? 'default', array( 'default', 'stacked', 'framed' ), true ) ? (string) $s['view'] : 'default';
			$shape   = in_array( $s['shape'] ?? 'circle', array( 'circle', 'rounded', 'square' ), true ) ? (string) $s['shape'] : 'circle';
			$classes = 'uncoder-icon-wrap uncoder-icon-wrap--' . $view . ( 'default' !== $view ? ' uncoder-icon-wrap--' . $shape : '' ) . ' uncoder-icon-box__icon';
			return '<span class="' . esc_attr( $classes ) . '">' . $this->render_icon( $s['icon'] ) . '</span>';
		}
		if ( 'image' === $type ) {
			$size = sanitize_key( (string) ( $s['image_size'] ?? 'thumbnail' ) );
			$img  = $this->image( $s['image'] ?? array(), '' !== $size ? $size : 'thumbnail', array( 'class' => 'uncoder-icon-box__img' ) );
			if ( '' === $img ) {
				if ( ! $ctx->editor ) {
					return '';
				}
				$img = '<img class="uncoder-icon-box__img uncoder-icon-box__img--placeholder" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
			}
			return '<span class="uncoder-icon-box__image">' . $img . '</span>';
		}
		return '';
	}
}
