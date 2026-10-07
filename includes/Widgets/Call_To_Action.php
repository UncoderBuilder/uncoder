<?php
/**
 * Call to Action widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Promo box: image beside the content (classic) or behind it (cover), eyebrow, title, text, two buttons, ribbon.
 */
class Call_To_Action extends Widget_Base {

	public const VARIANTS = array( 'primary', 'secondary', 'outline', 'ghost', 'link' );

	public const SIZES = array( 'sm', 'md', 'lg', 'xl' );

	public function name(): string {
		return 'call-to-action';
	}

	public function title(): string {
		return __( 'Call to Action', 'uncoder' );
	}

	public function icon(): string {
		return 'megaphone';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'cta', 'call to action', 'banner', 'promo', 'offer', 'box' );
	}

	public function description(): string {
		return __( 'Promo box with an image, eyebrow, title, text and up to two buttons. "classic" puts the image beside the content, "cover" uses it as the background behind an overlay.', 'uncoder' );
	}

	/**
	 * @return array<string,string>
	 */
	public static function variant_options(): array {
		return array(
			'primary'   => __( 'Primary', 'uncoder' ),
			'secondary' => __( 'Secondary', 'uncoder' ),
			'outline'   => __( 'Outline', 'uncoder' ),
			'ghost'     => __( 'Ghost', 'uncoder' ),
			'link'      => __( 'Text link', 'uncoder' ),
		);
	}

	/**
	 * @return array<string, array<string,string>>
	 */
	public static function size_options(): array {
		return array(
			'sm' => array( 'label' => 'S' ),
			'md' => array( 'label' => 'M' ),
			'lg' => array( 'label' => 'L' ),
			'xl' => array( 'label' => 'XL' ),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'classic',
				'options' => array(
					'classic' => __( 'Classic (image beside content)', 'uncoder' ),
					'cover'   => __( 'Cover (image as background)', 'uncoder' ),
				),
				'ai'      => 'classic = image next to the text; cover = image fills the whole box behind the text with an overlay (good for banners).',
			)
		);
		$this->add_control(
			'image',
			array(
				'type'    => 'media',
				'label'   => __( 'Image', 'uncoder' ),
				'default' => array( 'id' => 0, 'url' => '' ),
				'dynamic' => true,
				'ai'      => 'Upload the image first and pass {"id": attachment_id}. Optional: without an image the box shows only its content.',
			)
		);
		$this->add_control(
			'image_size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'large',
				'options_dynamic' => true,
				'options'         => array(
					'medium'       => 'Medium',
					'medium_large' => 'Medium large',
					'large'        => 'Large',
					'full'         => 'Full',
				),
				'condition'       => array( 'image.url!' => '' ),
			)
		);
		$this->add_responsive_control(
			'image_position',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Image position', 'uncoder' ),
				'default'              => 'left',
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'panel-left' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'panel-right' ),
					'top'    => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'panel-top' ),
					'bottom' => array( 'label' => __( 'Bottom', 'uncoder' ), 'icon' => 'panel-bottom' ),
				),
				'selectors_dictionary' => array(
					'left'   => 'flex-direction:row;--uncoder-cta-media-flex:1 1 var(--uncoder-cta-media-w);--uncoder-cta-body-flex:999 1 320px',
					'right'  => 'flex-direction:row-reverse;--uncoder-cta-media-flex:1 1 var(--uncoder-cta-media-w);--uncoder-cta-body-flex:999 1 320px',
					'top'    => 'flex-direction:column;--uncoder-cta-media-flex:none;--uncoder-cta-body-flex:none',
					'bottom' => 'flex-direction:column-reverse;--uncoder-cta-media-flex:none;--uncoder-cta-body-flex:none',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'condition'            => array( 'layout' => 'classic' ),
				'ai'                   => 'Side-by-side layouts wrap automatically when the box is too narrow; set image_position_mobile:"top" to force stacking.',
			)
		);
		$this->end_section();

		$this->start_section( 'content_text', array( 'label' => __( 'Content', 'uncoder' ) ) );
		$this->add_control(
			'eyebrow',
			array(
				'type'    => 'text',
				'label'   => __( 'Eyebrow', 'uncoder' ),
				'default' => __( 'Limited offer', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'title',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Title', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 2,
				'default' => __( 'Launch your next website in days, not months', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'h2',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control(
			'description',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Description', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 3,
				'default' => __( 'Start from a proven layout, drop in your content and publish with confidence.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->end_section();

		$this->start_section( 'content_buttons', array( 'label' => __( 'Buttons', 'uncoder' ) ) );
		$this->add_control(
			'button_text',
			array(
				'type'    => 'text',
				'label'   => __( 'Button text', 'uncoder' ),
				'default' => __( 'Get started', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'link',
			array(
				'type'    => 'url',
				'label'   => __( 'Button link', 'uncoder' ),
				'default' => array( 'url' => '#' ),
				'dynamic' => true,
			)
		);
		$this->add_control(
			'button_variant',
			array(
				'type'    => 'select',
				'label'   => __( 'Button style', 'uncoder' ),
				'default' => 'primary',
				'options' => self::variant_options(),
			)
		);
		$this->add_control(
			'button_icon',
			array(
				'type'  => 'icon',
				'label' => __( 'Button icon', 'uncoder' ),
			)
		);
		$this->add_control(
			'button2_show',
			array(
				'type'    => 'switch',
				'label'   => __( 'Second button', 'uncoder' ),
				'default' => false,
			)
		);
		$this->add_control(
			'button2_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Second button text', 'uncoder' ),
				'default'   => __( 'See pricing', 'uncoder' ),
				'condition' => array( 'button2_show' => true ),
				'dynamic'   => true,
				'inline'    => true,
			)
		);
		$this->add_control(
			'button2_link',
			array(
				'type'      => 'url',
				'label'     => __( 'Second button link', 'uncoder' ),
				'default'   => array( 'url' => '#' ),
				'condition' => array( 'button2_show' => true ),
				'dynamic'   => true,
			)
		);
		$this->add_control(
			'button2_variant',
			array(
				'type'      => 'select',
				'label'     => __( 'Second button style', 'uncoder' ),
				'default'   => 'outline',
				'options'   => self::variant_options(),
				'condition' => array( 'button2_show' => true ),
			)
		);
		$this->add_control(
			'button_size',
			array(
				'type'    => 'choose',
				'label'   => __( 'Button size', 'uncoder' ),
				'default' => 'md',
				'options' => self::size_options(),
			)
		);
		$this->end_section();

		$this->start_section( 'content_ribbon', array( 'label' => __( 'Ribbon', 'uncoder' ) ) );
		$this->add_control(
			'ribbon_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Ribbon text', 'uncoder' ),
				'placeholder' => __( 'New', 'uncoder' ),
				'description' => __( 'Leave empty to hide the corner ribbon.', 'uncoder' ),
				'inline'      => true,
			)
		);
		$this->add_control(
			'ribbon_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Ribbon position', 'uncoder' ),
				'default'   => 'right',
				'options'   => array(
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'arrow-left-to-line' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
				),
				'condition' => array( 'ribbon_text!' => '' ),
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'min_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Min height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 100, 'max' => 1000 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'min-height: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Content alignment', 'uncoder' ),
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => '--uncoder-cta-align:flex-start;--uncoder-cta-text:start',
					'center' => '--uncoder-cta-align:center;--uncoder-cta-text:center',
					'right'  => '--uncoder-cta-align:flex-end;--uncoder-cta-text:end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'vertical_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Vertical position', 'uncoder' ),
				'options'              => array(
					'top'    => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'align-vertical-justify-start' ),
					'middle' => array( 'label' => __( 'Middle', 'uncoder' ), 'icon' => 'align-vertical-justify-center' ),
					'bottom' => array( 'label' => __( 'Bottom', 'uncoder' ), 'icon' => 'align-vertical-justify-end' ),
				),
				'selectors_dictionary' => array(
					'top'    => '--uncoder-cta-justify:flex-start',
					'middle' => '--uncoder-cta-justify:center',
					'bottom' => '--uncoder-cta-justify:flex-end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'content_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Content max width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'ch', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-cta-content-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Content padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-cta__body' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'background',
			array(
				'type'     => 'background',
				'label'    => __( 'Background', 'uncoder' ),
				'selector' => '{{WRAPPER}}',
			)
		);
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'box_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_group( 'hover_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover' ) );
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();

		$this->start_section( 'style_image', array( 'label' => __( 'Image', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'media_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Image width', 'uncoder' ),
				'size_units' => array( '%', 'px', 'rem' ),
				'range'      => array( '%' => array( 'min' => 10, 'max' => 90 ) ),
				'condition'  => array( 'layout' => 'classic' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-cta-media-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'media_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Image min height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 80, 'max' => 800 ) ),
				'condition'  => array( 'layout' => 'classic' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-cta-media-h: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'media_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap to content', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'condition'  => array( 'layout' => 'classic' ),
				'selectors'  => array( '{{WRAPPER}}' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'object_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Focal point', 'uncoder' ),
				'options'   => \Uncoder\Builder\Controls\Groups\Background::POSITIONS,
				'selectors' => array( '{{WRAPPER}} .uncoder-cta__img' => 'object-position: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'media_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Image radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'condition'  => array( 'layout' => 'classic' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-cta__media' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'image_hover',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover effect', 'uncoder' ),
				'default' => 'zoom',
				'options' => array(
					''          => __( 'None', 'uncoder' ),
					'zoom'      => __( 'Zoom in', 'uncoder' ),
					'zoom-out'  => __( 'Zoom out', 'uncoder' ),
					'grayscale' => __( 'Grayscale to color', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'zoom_scale',
			array(
				'type'      => 'number',
				'label'     => __( 'Zoom scale', 'uncoder' ),
				'min'       => 1,
				'max'       => 1.6,
				'step'      => 0.01,
				'condition' => array( 'image_hover' => array( 'zoom', 'zoom-out' ) ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-cta-zoom: {{VALUE}}' ),
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
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-cta-duration: {{VALUE}}ms' ),
			)
		);
		$this->add_group( 'image_filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-cta__img' ) );
		$this->end_section();

		$this->start_section( 'style_overlay', array( 'label' => __( 'Overlay', 'uncoder' ), 'tab' => 'style' ) );
		$this->start_tabs( 'overlay_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_group(
			'overlay',
			array(
				'type'     => 'background',
				'label'    => __( 'Overlay', 'uncoder' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .uncoder-cta__overlay',
				'ai'       => 'Cover layout defaults to a dark navy overlay at 55%; use a gradient for text legibility, e.g. {"type":"gradient","color":"rgba(0,0,0,0)","color_b":"rgba(0,0,0,.7)"}.',
			)
		);
		$this->add_control(
			'overlay_opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.01,
				'selectors' => array( '{{WRAPPER}} .uncoder-cta__overlay' => 'opacity: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_group(
			'overlay_hover',
			array(
				'type'     => 'background',
				'label'    => __( 'Overlay', 'uncoder' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}}:hover .uncoder-cta__overlay',
			)
		);
		$this->add_control(
			'overlay_hover_opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.01,
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-cta__overlay' => 'opacity: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_control(
			'overlay_blend',
			array(
				'type'      => 'select',
				'label'     => __( 'Blend mode', 'uncoder' ),
				'options'   => array(
					''            => __( 'Normal', 'uncoder' ),
					'multiply'    => __( 'Multiply', 'uncoder' ),
					'screen'      => __( 'Screen', 'uncoder' ),
					'overlay'     => __( 'Overlay', 'uncoder' ),
					'darken'      => __( 'Darken', 'uncoder' ),
					'lighten'     => __( 'Lighten', 'uncoder' ),
					'color'       => __( 'Color', 'uncoder' ),
					'luminosity'  => __( 'Luminosity', 'uncoder' ),
					'soft-light'  => __( 'Soft light', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-cta__overlay' => 'mix-blend-mode: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_eyebrow', array( 'label' => __( 'Eyebrow', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'eyebrow!' => '' ) ) );
		$this->add_group( 'eyebrow_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-cta__eyebrow' ) );
		$this->add_control(
			'eyebrow_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-cta__eyebrow' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'eyebrow_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-cta__eyebrow' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Title', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-cta__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-cta__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'title_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color on box hover', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-cta__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-cta__title' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_group( 'title_shadow', array( 'type' => 'text_shadow', 'label' => __( 'Text shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-cta__title' ) );
		$this->end_section();

		$this->start_section( 'style_description', array( 'label' => __( 'Description', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'description!' => '' ) ) );
		$this->add_group( 'description_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-cta__description' ) );
		$this->add_control(
			'description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-cta__description' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'description_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-cta__description' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_buttons', array( 'label' => __( 'Buttons', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'buttons_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap between buttons', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-cta__buttons' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-cta__button' ) );
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-cta__button' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-cta__button' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_control( 'button_heading', array( 'type' => 'heading', 'label' => __( 'Main button', 'uncoder' ) ) );
		$this->register_button_colors( 'button', '{{WRAPPER}} .uncoder-cta__button--main', array() );
		$this->add_control( 'button2_heading', array( 'type' => 'heading', 'label' => __( 'Second button', 'uncoder' ), 'condition' => array( 'button2_show' => true ) ) );
		$this->register_button_colors( 'button2', '{{WRAPPER}} .uncoder-cta__button--second', array( 'button2_show' => true ) );
		$this->end_section();

		$this->start_section( 'style_ribbon', array( 'label' => __( 'Ribbon', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'ribbon_text!' => '' ) ) );
		$this->add_control(
			'ribbon_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-cta__ribbon-text' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'ribbon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-cta__ribbon-text' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'ribbon_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-cta__ribbon-text' ) );
		$this->add_control(
			'ribbon_distance',
			array(
				'type'       => 'slider',
				'label'      => __( 'Distance from corner', 'uncoder' ),
				'size_units' => array( 'em', 'px' ),
				'range'      => array( 'em' => array( 'min' => 1, 'max' => 6, 'step' => 0.1 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-cta__ribbon' => '--uncoder-cta-ribbon-d: {{VALUE}}' ),
			)
		);
		$this->add_group( 'ribbon_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-cta__ribbon-text' ) );
		$this->end_section();
	}

	/**
	 * Normal / hover colour controls for one of the inner buttons.
	 *
	 * @param array<string,mixed> $condition Section condition.
	 */
	private function register_button_colors( string $prefix, string $selector, array $condition ): void {
		$hover = $selector . ':is(:hover, :focus-visible)';
		$this->start_tabs( $prefix . '_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( $prefix . '_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'condition' => $condition, 'selectors' => array( $selector => 'color: {{VALUE}}' ) ) );
		$this->add_control( $prefix . '_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'condition' => $condition, 'selectors' => array( $selector => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( $prefix . '_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'condition' => $condition, 'selectors' => array( $selector => 'box-shadow: inset 0 0 0 1.5px {{VALUE}}' ) ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( $prefix . '_hover_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'condition' => $condition, 'selectors' => array( $hover => 'color: {{VALUE}}' ) ) );
		$this->add_control( $prefix . '_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'condition' => $condition, 'selectors' => array( $hover => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( $prefix . '_hover_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'condition' => $condition, 'selectors' => array( $hover => 'box-shadow: inset 0 0 0 1.5px {{VALUE}}' ) ) );
		$this->end_tab();
		$this->end_tabs();
	}

	/**
	 * Kit button markup (base .uncoder-btn classes) or '' when there is no text.
	 *
	 * @param mixed $link Link value.
	 */
	private function button( string $text, $link, string $variant, string $size, string $modifier, string $inline, string $icon = '' ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}
		$variant        = in_array( $variant, self::VARIANTS, true ) ? $variant : 'primary';
		$size           = in_array( $size, self::SIZES, true ) ? $size : 'md';
		$attrs          = $this->link_attrs( $link );
		$attrs['class'] = array( 'uncoder-btn', 'uncoder-btn--' . $variant, 'uncoder-btn--' . $size, 'uncoder-cta__button', 'uncoder-cta__button--' . $modifier );
		$tag            = isset( $attrs['href'] ) ? 'a' : 'span';
		return '<' . $tag . Utils::attrs( $attrs ) . '><span class="uncoder-btn__text"' . $inline . '>' . esc_html( $text ) . '</span>' . $icon . '</' . $tag . '>';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$layout = 'cover' === ( $s['layout'] ?? 'classic' ) ? 'cover' : 'classic';
		$title  = $this->inline_html( $s['title'] ?? '' );
		$desc   = $this->inline_html( $s['description'] ?? '' );
		$size   = (string) ( $s['button_size'] ?? 'md' );

		$icon    = $this->has_icon( $s['button_icon'] ?? null ) ? $this->render_icon( $s['button_icon'], array( 'class' => 'uncoder-btn__icon' ) ) : '';
		$buttons = $this->button( (string) ( $s['button_text'] ?? '' ), $s['link'] ?? array(), (string) ( $s['button_variant'] ?? 'primary' ), $size, 'main', $ctx->inline( 'button_text' ), $icon );
		if ( ! empty( $s['button2_show'] ) ) {
			$buttons .= $this->button( (string) ( $s['button2_text'] ?? '' ), $s['button2_link'] ?? array(), (string) ( $s['button2_variant'] ?? 'outline' ), $size, 'second', $ctx->inline( 'button2_text' ) );
		}

		$has_text = '' !== trim( wp_strip_all_tags( $title . $desc ) ) || '' !== trim( (string) ( $s['eyebrow'] ?? '' ) ) || '' !== $buttons;
		$img      = $this->image(
			$s['image'] ?? array(),
			sanitize_key( (string) ( $s['image_size'] ?? 'large' ) ),
			array( 'class' => 'uncoder-cta__img' )
		);
		if ( '' === $img && $ctx->editor ) {
			$img = '<img class="uncoder-cta__img uncoder-cta__img--placeholder" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
		}
		if ( ! $has_text && '' === $img ) {
			return;
		}

		$hover   = (string) ( $s['image_hover'] ?? '' );
		$classes = array( 'uncoder-cta', 'uncoder-cta--' . $layout );
		if ( in_array( $hover, array( 'zoom', 'zoom-out', 'grayscale' ), true ) ) {
			$classes[] = 'uncoder-cta--hover-' . $hover;
		}
		if ( '' !== $img ) {
			$classes[] = 'uncoder-cta--has-media';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( '' !== $img ) {
			echo '<div class="uncoder-cta__media">' . $img . '<span class="uncoder-cta__overlay" aria-hidden="true"></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup / escaped attributes.
		} elseif ( 'cover' === $layout ) {
			echo '<span class="uncoder-cta__overlay" aria-hidden="true"></span>';
		}

		echo '<div class="uncoder-cta__body">';
		if ( '' !== trim( (string) ( $s['eyebrow'] ?? '' ) ) ) {
			echo '<p class="uncoder-cta__eyebrow"' . $ctx->inline( 'eyebrow' ) . '>' . esc_html( (string) $s['eyebrow'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
		}
		if ( '' !== trim( wp_strip_all_tags( $title ) ) || $ctx->editor ) {
			$tag = Utils::tag( $s['title_tag'] ?? 'h2', Utils::HEADING_TAGS, 'h2' );
			echo '<' . $tag . ' class="uncoder-cta__title"' . $ctx->inline( 'title' ) . '>' . $title . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, kses'd title.
		}
		if ( '' !== trim( wp_strip_all_tags( $desc ) ) ) {
			echo '<p class="uncoder-cta__description"' . $ctx->inline( 'description' ) . '>' . $desc . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
		}
		if ( '' !== $buttons ) {
			echo '<div class="uncoder-cta__buttons">' . $buttons . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		echo '</div>';

		$ribbon = trim( (string) ( $s['ribbon_text'] ?? '' ) );
		if ( '' !== $ribbon ) {
			$side = 'left' === ( $s['ribbon_position'] ?? 'right' ) ? 'left' : 'right';
			echo '<div class="uncoder-cta__ribbon uncoder-cta__ribbon--' . esc_attr( $side ) . '"><span class="uncoder-cta__ribbon-text"' . $ctx->inline( 'ribbon_text' ) . '>' . esc_html( $ribbon ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
		}
		echo '</div>';
	}
}
