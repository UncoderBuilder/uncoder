<?php
/**
 * Slides widget: full-width hero slider.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Icons;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * Hero slider whose slides are a background image + overlay + heading, description and buttons.
 *
 * Markup: .uncoder-slides (region) > [.uncoder-slides__toggle], .uncoder-slides__viewport >
 * .uncoder-slides__slide (group) > img.uncoder-slides__img, .uncoder-slides__overlay, [a.uncoder-slides__link],
 * .uncoder-slides__inner > .uncoder-slides__content; then arrows, pagination and a polite status region.
 * Slides share one grid cell, so the slider is as tall as its tallest slide (never less than the height
 * setting). Behaviour: front-end module "slides" (src/frontend/modules/slides.ts).
 */
class Slides extends Widget_Base {

	public const VARIANTS = array( 'primary', 'secondary', 'outline', 'ghost' );

	public const SIZES = array( 'sm', 'md', 'lg', 'xl' );

	private const SLIDE = '{{WRAPPER}} {{CURRENT_ITEM}}';

	public function name(): string {
		return 'slides';
	}

	public function title(): string {
		return __( 'Slides', 'uncoder' );
	}

	public function icon(): string {
		return 'gallery-thumbnails';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'slides', 'slider', 'hero', 'hero slider', 'slideshow', 'banner', 'carousel', 'ken burns', 'rotator', 'fullwidth' );
	}

	public function description(): string {
		return __( 'Full-width hero slider. Each slide has its own background image (a real responsive <img>: the first loads eagerly as the page\'s LCP candidate, the rest lazily), background color, overlay, optional Ken Burns zoom, heading, description, up to two buttons (or a whole-slide link) and its own content position. Slide or fade transition, autoplay with pause button, arrows, dots or fraction, swipe and keyboard; reduced motion disables autoplay and animation. Use it for homepage heroes and promo rotators; for slides holding arbitrary widgets use the carousel widget.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'slides' );
	}

	public function preset(): array {
		return array( 'slides' => $this->default_slides( true ) );
	}

	/**
	 * Sample slides (placeholder images in the preset so a new slider shows its layout). Rows carry
	 * fixed _ids so their {{CURRENT_ITEM}} styles (positions) preview at once; the editor gives
	 * inserted copies fresh ones.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private function default_slides( bool $images ): array {
		$image = $images ? array(
			'id'  => 0,
			'url' => $this->placeholder_image(),
			'alt' => '',
		) : array(
			'id'  => 0,
			'url' => '',
		);
		return array(
			array(
				'_id'          => 'sld0001',
				'image'        => $image,
				'ken_burns'    => 'in',
				'heading'      => __( 'Make a first impression that lasts', 'uncoder' ),
				'description'  => __( 'A full-width slider for your biggest announcements, launches and offers.', 'uncoder' ),
				'button_text'  => __( 'Get started', 'uncoder' ),
				'link'         => array( 'url' => '#' ),
				'button2_text' => __( 'Learn more', 'uncoder' ),
				'button2_link' => array( 'url' => '#' ),
				'h_position'   => 'left',
				'text_align'   => 'left',
			),
			array(
				'_id'         => 'sld0002',
				'image'       => $image,
				'heading'     => __( 'Launch faster with ready-made layouts', 'uncoder' ),
				'description' => __( 'Swap in your own photos and words; every slide stays on brand.', 'uncoder' ),
				'button_text' => __( 'Explore features', 'uncoder' ),
				'link'        => array( 'url' => '#' ),
			),
			array(
				'_id'         => 'sld0003',
				'image'       => $image,
				'ken_burns'   => 'out',
				'heading'     => __( 'Tell your story one slide at a time', 'uncoder' ),
				'description' => __( 'Give each slide its own image, message, buttons and position.', 'uncoder' ),
				'button_text' => __( 'Contact us', 'uncoder' ),
				'link'        => array( 'url' => '#' ),
				'h_position'  => 'left',
				'v_position'  => 'bottom',
				'text_align'  => 'left',
			),
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function variant_options(): array {
		return array(
			'primary'   => __( 'Primary', 'uncoder' ),
			'secondary' => __( 'Secondary', 'uncoder' ),
			'outline'   => __( 'Outline', 'uncoder' ),
			'ghost'     => __( 'Ghost', 'uncoder' ),
		);
	}

	/**
	 * Horizontal / vertical / text alignment options and their custom properties.
	 *
	 * @return array<string, array{options: array<string,array<string,string>>, dictionary: array<string,string>}>
	 */
	private static function positions(): array {
		return array(
			'h'    => array(
				'options'    => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-horizontal-justify-start' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-horizontal-justify-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-horizontal-justify-end' ),
				),
				'dictionary' => array(
					'left'   => '--uncoder-slides-justify:flex-start',
					'center' => '--uncoder-slides-justify:center',
					'right'  => '--uncoder-slides-justify:flex-end',
				),
			),
			'v'    => array(
				'options'    => array(
					'top'    => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'align-vertical-justify-start' ),
					'middle' => array( 'label' => __( 'Middle', 'uncoder' ), 'icon' => 'align-vertical-justify-center' ),
					'bottom' => array( 'label' => __( 'Bottom', 'uncoder' ), 'icon' => 'align-vertical-justify-end' ),
				),
				'dictionary' => array(
					'top'    => '--uncoder-slides-align:flex-start',
					'middle' => '--uncoder-slides-align:center',
					'bottom' => '--uncoder-slides-align:flex-end',
				),
			),
			'text' => array(
				'options'    => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'dictionary' => array(
					'left'   => '--uncoder-slides-text:start;--uncoder-slides-buttons:flex-start',
					'center' => '--uncoder-slides-text:center;--uncoder-slides-buttons:center',
					'right'  => '--uncoder-slides-text:end;--uncoder-slides-buttons:flex-end',
				),
			),
		);
	}

	/**
	 * Select options for a per-slide override ("" = the widget's setting).
	 *
	 * @param array<string, array<string,string>> $options Choose options.
	 * @return array<string,string>
	 */
	private static function override_options( array $options ): array {
		$out = array( '' => __( 'Default', 'uncoder' ) );
		foreach ( $options as $key => $option ) {
			$out[ $key ] = $option['label'];
		}
		return $out;
	}

	protected function register_controls(): void {
		$pos = self::positions();

		/* ---------------------------------------------------------------- Content: slides */
		$this->start_section( 'content', array( 'label' => __( 'Slides', 'uncoder' ) ) );
		$this->add_control(
			'slides',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Slides', 'uncoder' ),
				'title_field' => 'heading',
				'fields'      => array(
					'image'            => array(
						'type'  => 'media',
						'label' => __( 'Background image', 'uncoder' ),
						'ai'    => 'Upload first (upload_media / search_images) and pass {"id": attachment_id}. Landscape photos at least 1920px wide work best; the image is cropped to cover the slide.',
					),
					'background_color' => array(
						'type'        => 'color',
						'label'       => __( 'Background color', 'uncoder' ),
						'description' => __( 'Shown behind the image, or instead of it.', 'uncoder' ),
						'selectors'   => array( self::SLIDE => 'background-color: {{VALUE}}' ),
					),
					'ken_burns'        => array(
						'type'    => 'select',
						'label'   => __( 'Ken Burns effect', 'uncoder' ),
						'default' => '',
						'options' => array(
							''    => __( 'None', 'uncoder' ),
							'in'  => __( 'Zoom in', 'uncoder' ),
							'out' => __( 'Zoom out', 'uncoder' ),
						),
						'ai'      => 'Slow zoom on the background image while the slide is shown. Skipped for visitors who prefer reduced motion.',
					),
					'heading'          => array(
						'type'    => 'textarea',
						'label'   => __( 'Heading', 'uncoder' ),
						'html'    => 'inline',
						'rows'    => 2,
						'default' => __( 'Slide heading', 'uncoder' ),
					),
					'description'      => array(
						'type'    => 'textarea',
						'label'   => __( 'Description', 'uncoder' ),
						'html'    => 'inline',
						'rows'    => 3,
						'default' => __( 'Add a short sentence that supports the heading.', 'uncoder' ),
					),
					'button_text'      => array(
						'type'    => 'text',
						'label'   => __( 'Button text', 'uncoder' ),
						'default' => __( 'Learn more', 'uncoder' ),
						'ai'      => 'Empty hides the button.',
					),
					'link'             => array(
						'type'    => 'url',
						'label'   => __( 'Button link', 'uncoder' ),
						'default' => array( 'url' => '#' ),
					),
					'link_whole'       => array(
						'type'        => 'switch',
						'label'       => __( 'Whole slide clickable', 'uncoder' ),
						'description' => __( 'The button link opens from anywhere on the slide.', 'uncoder' ),
						'condition'   => array( 'link.url!' => '' ),
					),
					'button2_text'     => array(
						'type'    => 'text',
						'label'   => __( 'Second button text', 'uncoder' ),
						'default' => '',
						'ai'      => 'Optional second button (e.g. "Learn more"); empty hides it.',
					),
					'button2_link'     => array(
						'type'      => 'url',
						'label'     => __( 'Second button link', 'uncoder' ),
						'default'   => array( 'url' => '#' ),
						'condition' => array( 'button2_text!' => '' ),
					),
					'slide_style'      => array(
						'type'  => 'heading',
						'label' => __( 'This slide', 'uncoder' ),
					),
					'h_position'       => array(
						'type'                 => 'select',
						'label'                => __( 'Horizontal position', 'uncoder' ),
						'options'              => self::override_options( $pos['h']['options'] ),
						'selectors_dictionary' => $pos['h']['dictionary'],
						'selectors'            => array( self::SLIDE => '{{VALUE}}' ),
					),
					'v_position'       => array(
						'type'                 => 'select',
						'label'                => __( 'Vertical position', 'uncoder' ),
						'options'              => self::override_options( $pos['v']['options'] ),
						'selectors_dictionary' => $pos['v']['dictionary'],
						'selectors'            => array( self::SLIDE => '{{VALUE}}' ),
					),
					'text_align'       => array(
						'type'                 => 'select',
						'label'                => __( 'Text alignment', 'uncoder' ),
						'options'              => self::override_options( $pos['text']['options'] ),
						'selectors_dictionary' => $pos['text']['dictionary'],
						'selectors'            => array( self::SLIDE => '{{VALUE}}' ),
					),
					'text_color'       => array(
						'type'      => 'color',
						'label'     => __( 'Text color', 'uncoder' ),
						'selectors' => array( self::SLIDE => '--uncoder-slides-color: {{VALUE}}; --uncoder-slides-heading-color: {{VALUE}}; --uncoder-slides-desc-color: {{VALUE}}' ),
					),
					'overlay_color'    => array(
						'type'      => 'color',
						'label'     => __( 'Overlay color', 'uncoder' ),
						'selectors' => array( self::SLIDE . ' > .uncoder-slides__overlay' => 'background: {{VALUE}}' ),
					),
					'overlay_opacity'  => array(
						'type'      => 'number',
						'label'     => __( 'Overlay opacity', 'uncoder' ),
						'min'       => 0,
						'max'       => 1,
						'step'      => 0.05,
						'selectors' => array( self::SLIDE . ' > .uncoder-slides__overlay' => 'opacity: {{VALUE}}' ),
					),
				),
				'default'     => $this->default_slides( false ),
				'ai'          => 'One row per slide: {"image": {"id": attachment_id}, "heading": "…", "description": "…", "button_text": "…", "link": {"url": "…"}, "button2_text": "…", "button2_link": {"url": "…"}}. Optional per slide: ken_burns "in"|"out", link_whole true, background_color, overlay_color, overlay_opacity 0–1, text_color, h_position left|center|right, v_position top|middle|bottom, text_align left|center|right ("" = the widget setting). Keep headings short (≤ 8 words); 2–5 slides.',
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Content: layout */
		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_responsive_control(
			'height',
			array(
				'type'        => 'slider',
				'label'       => __( 'Height', 'uncoder' ),
				'description' => __( 'Minimum height; a slide whose content needs more room grows (all slides stay equal).', 'uncoder' ),
				'size_units'  => array( 'px', 'vh', 'svh', 'rem' ),
				'range'       => array(
					'px' => array( 'min' => 200, 'max' => 1200 ),
					'vh' => array( 'min' => 20, 'max' => 100 ),
				),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-slides-height: {{VALUE}}' ),
				'ai'          => 'Default is clamp(420px, 75vh, 760px). Full-screen hero: {"size": 100, "unit": "svh"}; set height_mobile for phones, e.g. {"size": 520, "unit": "px"}.',
			)
		);
		$this->add_responsive_control(
			'content_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Content max width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem', 'ch' ),
				'range'      => array( 'px' => array( 'min' => 200, 'max' => 1400 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-slides-content-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'h_position',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Horizontal position', 'uncoder' ),
				'options'              => $pos['h']['options'],
				'selectors_dictionary' => $pos['h']['dictionary'],
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'ai'                   => 'Default for every slide (center); a slide\'s own h_position wins.',
			)
		);
		$this->add_responsive_control(
			'v_position',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Vertical position', 'uncoder' ),
				'options'              => $pos['v']['options'],
				'selectors_dictionary' => $pos['v']['dictionary'],
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'text_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Text alignment', 'uncoder' ),
				'options'              => $pos['text']['options'],
				'selectors_dictionary' => $pos['text']['dictionary'],
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'heading_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Heading HTML tag', 'uncoder' ),
				'default' => 'h2',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
				'ai'      => 'Use h1 only when the slider is the page hero and the page has no other h1.',
			)
		);
		$this->add_control(
			'image_size',
			array(
				'type'            => 'select',
				'label'           => __( 'Image resolution', 'uncoder' ),
				'description'     => __( 'Largest file offered; browsers pick a smaller one on small screens.', 'uncoder' ),
				'default'         => 'full',
				'options_dynamic' => true,
				'options'         => array(
					'medium_large' => 'Medium large',
					'large'        => 'Large',
					'1536x1536'    => '1536',
					'2048x2048'    => '2048',
					'full'         => 'Full',
				),
			)
		);
		$this->add_control(
			'first_image_loading',
			array(
				'type'        => 'select',
				'label'       => __( 'First image loading', 'uncoder' ),
				'default'     => '',
				'options'     => array(
					''      => __( 'Auto', 'uncoder' ),
					'eager' => __( 'Right away (hero)', 'uncoder' ),
					'lazy'  => __( 'Lazy', 'uncoder' ),
				),
				'description' => __( 'Auto loads it right away, with high priority, when the slider is in the page\'s first section. Other slides always load lazily.', 'uncoder' ),
				'ai'          => 'Leave "" (auto) in almost every case.',
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Content: slider behaviour */
		$this->start_section( 'content_slider', array( 'label' => __( 'Slider', 'uncoder' ) ) );
		$this->add_control(
			'transition',
			array(
				'type'    => 'select',
				'label'   => __( 'Transition', 'uncoder' ),
				'default' => 'slide',
				'options' => array(
					'slide' => __( 'Slide', 'uncoder' ),
					'fade'  => __( 'Fade', 'uncoder' ),
				),
				'ai'      => 'slide = horizontal movement (follows the finger when swiping); fade = cross-fade.',
			)
		);
		$this->add_control(
			'speed',
			array(
				'type'    => 'number',
				'label'   => __( 'Transition duration (ms)', 'uncoder' ),
				'min'     => 0,
				'max'     => 3000,
				'step'    => 50,
				'default' => 700,
			)
		);
		$this->add_control(
			'content_animation',
			array(
				'type'    => 'select',
				'label'   => __( 'Content animation', 'uncoder' ),
				'default' => 'fade-up',
				'options' => array(
					''          => __( 'None', 'uncoder' ),
					'fade'      => __( 'Fade in', 'uncoder' ),
					'fade-up'   => __( 'Fade up', 'uncoder' ),
					'fade-down' => __( 'Fade down', 'uncoder' ),
					'zoom'      => __( 'Zoom in', 'uncoder' ),
				),
				'description' => __( 'Plays on the text when a new slide comes in.', 'uncoder' ),
			)
		);
		$this->add_control(
			'loop',
			array(
				'type'        => 'switch',
				'label'       => __( 'Infinite loop', 'uncoder' ),
				'description' => __( 'Next on the last slide shows the first one.', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'swipe',
			array(
				'type'        => 'switch',
				'label'       => __( 'Swipe and drag', 'uncoder' ),
				'description' => __( 'Change slides with a touch swipe or a mouse drag.', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control( 'nav_heading', array( 'type' => 'heading', 'label' => __( 'Navigation', 'uncoder' ) ) );
		$this->add_control(
			'arrows',
			array(
				'type'    => 'switch',
				'label'   => __( 'Arrows', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'prev_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Previous icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'chevron-left' ),
				'condition' => array( 'arrows' => 'yes' ),
			)
		);
		$this->add_control(
			'next_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Next icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'chevron-right' ),
				'condition' => array( 'arrows' => 'yes' ),
			)
		);
		$this->add_control(
			'pagination',
			array(
				'type'    => 'select',
				'label'   => __( 'Pagination', 'uncoder' ),
				'default' => 'dots',
				'options' => array(
					''         => __( 'None', 'uncoder' ),
					'dots'     => __( 'Dots', 'uncoder' ),
					'fraction' => __( 'Fraction (2 / 5)', 'uncoder' ),
				),
			)
		);
		$this->add_control( 'autoplay_heading', array( 'type' => 'heading', 'label' => __( 'Autoplay', 'uncoder' ) ) );
		$this->add_control(
			'autoplay',
			array(
				'type'        => 'switch',
				'label'       => __( 'Autoplay', 'uncoder' ),
				'description' => __( 'Never starts in the editor or for visitors who prefer reduced motion; pauses while hovered, focused or off screen.', 'uncoder' ),
				'default'     => true,
				'ai'          => 'Keep pause_button on when autoplay is on (WCAG 2.2.2).',
			)
		);
		$this->add_control(
			'autoplay_delay',
			array(
				'type'      => 'number',
				'label'     => __( 'Delay (ms)', 'uncoder' ),
				'min'       => 1500,
				'max'       => 30000,
				'step'      => 500,
				'default'   => 6000,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'pause_on_hover',
			array(
				'type'      => 'switch',
				'label'     => __( 'Pause on hover', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'pause_button',
			array(
				'type'        => 'switch',
				'label'       => __( 'Pause button', 'uncoder' ),
				'description' => __( 'Required by WCAG 2.2.2 for content that moves on its own.', 'uncoder' ),
				'default'     => true,
				'condition'   => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'slider_label',
			array(
				'type'        => 'text',
				'label'       => __( 'Accessible name', 'uncoder' ),
				'placeholder' => __( 'Highlights', 'uncoder' ),
				'description' => __( 'Announced by screen readers, e.g. "Featured offers".', 'uncoder' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: slides */
		$this->start_section( 'style_slides', array( 'label' => __( 'Slides', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'slide_background',
			array(
				'type'        => 'color',
				'label'       => __( 'Background color', 'uncoder' ),
				'description' => __( 'For every slide; a slide\'s own color wins.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-slides-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'content_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Content padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%', 'vw' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-slides__inner' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'object_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Image focal point', 'uncoder' ),
				'options'   => \Uncoder\Builder\Controls\Groups\Background::POSITIONS,
				'selectors' => array( '{{WRAPPER}} .uncoder-slides__img' => 'object-position: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'ken_burns_scale',
			array(
				'type'      => 'number',
				'label'     => __( 'Ken Burns zoom', 'uncoder' ),
				'min'       => 1,
				'max'       => 1.6,
				'step'      => 0.01,
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-kb-scale: {{VALUE}}' ),
			)
		);
		$this->add_group( 'image_filters', array( 'type' => 'css_filters', 'label' => __( 'Image filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-slides__img' ) );
		$this->end_section();

		/* ---------------------------------------------------------------- Style: overlay */
		$this->start_section( 'style_overlay', array( 'label' => __( 'Overlay', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group(
			'overlay',
			array(
				'type'     => 'background',
				'label'    => __( 'Overlay', 'uncoder' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .uncoder-slides__overlay',
				'ai'       => 'Default is dark navy at 55% for legible white text. For text at the bottom a gradient reads better, e.g. {"type":"gradient","color":"rgba(0,0,0,0)","color_b":"rgba(0,0,0,.75)"}.',
			)
		);
		$this->add_control(
			'overlay_opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.05,
				'selectors' => array( '{{WRAPPER}} .uncoder-slides__overlay' => 'opacity: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: heading */
		$this->start_section( 'style_heading', array( 'label' => __( 'Heading', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'heading_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-slides__heading' ) );
		$this->add_control(
			'heading_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-heading-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'heading_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-slides__heading' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_group( 'heading_shadow', array( 'type' => 'text_shadow', 'label' => __( 'Text shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-slides__heading' ) );
		$this->end_section();

		/* ---------------------------------------------------------------- Style: description */
		$this->start_section( 'style_description', array( 'label' => __( 'Description', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'description_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-slides__description' ) );
		$this->add_control(
			'description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-desc-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'description_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-slides__description' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: buttons */
		$this->start_section( 'style_buttons', array( 'label' => __( 'Buttons', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'button_variant',
			array(
				'type'    => 'select',
				'label'   => __( 'Button style', 'uncoder' ),
				'default' => 'primary',
				'options' => self::variant_options(),
				'ai'      => 'Design System button variants; outline and ghost turn white over the slide image.',
			)
		);
		$this->add_control(
			'button2_variant',
			array(
				'type'    => 'select',
				'label'   => __( 'Second button style', 'uncoder' ),
				'default' => 'outline',
				'options' => self::variant_options(),
			)
		);
		$this->add_control(
			'button_size',
			array(
				'type'    => 'choose',
				'label'   => __( 'Button size', 'uncoder' ),
				'default' => 'lg',
				'options' => array(
					'sm' => array( 'label' => 'S' ),
					'md' => array( 'label' => 'M' ),
					'lg' => array( 'label' => 'L' ),
					'xl' => array( 'label' => 'XL' ),
				),
			)
		);
		$this->add_responsive_control(
			'buttons_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap between buttons', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-slides__buttons' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-slides__button' ) );
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-slides__button' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control( 'button_heading', array( 'type' => 'heading', 'label' => __( 'Main button', 'uncoder' ) ) );
		$this->register_button_colors( 'button', '{{WRAPPER}} .uncoder-slides__button--main' );
		$this->add_control( 'button2_heading', array( 'type' => 'heading', 'label' => __( 'Second button', 'uncoder' ) ) );
		$this->register_button_colors( 'button2', '{{WRAPPER}} .uncoder-slides__button--second' );
		$this->end_section();

		/* ---------------------------------------------------------------- Style: arrows */
		$arrow = '{{WRAPPER}} > .uncoder-slides__arrow';
		$this->start_section(
			'style_arrows',
			array(
				'label'     => __( 'Arrows', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'arrows' => 'yes' ),
			)
		);
		$this->add_responsive_control(
			'arrow_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Button size', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-slides-arrow-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'arrow_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-slides-arrow-icon: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'arrow_offset',
			array(
				'type'       => 'slider',
				'label'      => __( 'Distance from edge', 'uncoder' ),
				'size_units' => array( 'px', 'rem', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-slides-arrow-offset: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'arrow_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( 'arrow_color', array( 'type' => 'color', 'label' => __( 'Icon color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-arrow-color: {{VALUE}}' ) ) );
		$this->add_control( 'arrow_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-arrow-bg: {{VALUE}}' ) ) );
		$this->add_control( 'arrow_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-arrow-border: {{VALUE}}' ) ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( 'arrow_hover_color', array( 'type' => 'color', 'label' => __( 'Icon color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-arrow-color-hover: {{VALUE}}' ) ) );
		$this->add_control( 'arrow_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-arrow-bg-hover: {{VALUE}}' ) ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'arrow_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $arrow => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: pagination */
		$pag = '{{WRAPPER}} > .uncoder-slides__pagination';
		$this->start_section(
			'style_pagination',
			array(
				'label' => __( 'Pagination & pause button', 'uncoder' ),
				'tab'   => 'style',
			)
		);
		$this->add_responsive_control(
			'pagination_offset',
			array(
				'type'       => 'slider',
				'label'      => __( 'Distance from bottom', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-slides-pag-offset: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dots_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Dot size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 4, 'max' => 30 ) ),
				'condition'  => array( 'pagination' => 'dots' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-slides-dot-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dots_active_width',
			array(
				'type'        => 'slider',
				'label'       => __( 'Active dot width', 'uncoder' ),
				'description' => __( 'Same as the dot size for round dots, wider for a pill.', 'uncoder' ),
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 4, 'max' => 80 ) ),
				'condition'   => array( 'pagination' => 'dots' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-slides-dot-active-width: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dots_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between dots', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'condition'  => array( 'pagination' => 'dots' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-slides-dot-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dots_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Dot color', 'uncoder' ),
				'condition' => array( 'pagination' => 'dots' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-dot-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dots_active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Active dot color', 'uncoder' ),
				'condition' => array( 'pagination' => 'dots' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-slides-dot-active: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'fraction_typography',
			array(
				'type'      => 'typography',
				'label'     => __( 'Typography', 'uncoder' ),
				'condition' => array( 'pagination' => 'fraction' ),
				'selector'  => $pag . ' > .uncoder-slides__fraction',
			)
		);
		$this->add_control(
			'fraction_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'condition' => array( 'pagination' => 'fraction' ),
				'selectors' => array( $pag . ' > .uncoder-slides__fraction' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'toggle_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Pause button color', 'uncoder' ),
				'condition' => array( 'autoplay' => 'yes' ),
				'selectors' => array( '{{WRAPPER}} > .uncoder-slides__toggle' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Normal / hover colour controls for one of the buttons.
	 */
	private function register_button_colors( string $prefix, string $selector ): void {
		$hover = $selector . ':is(:hover, :focus-visible)';
		$this->start_tabs( $prefix . '_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( $prefix . '_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $selector => 'color: {{VALUE}}' ) ) );
		$this->add_control( $prefix . '_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $selector => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( $prefix . '_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( $selector => 'box-shadow: inset 0 0 0 1.5px {{VALUE}}' ) ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( $prefix . '_hover_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( $hover => 'color: {{VALUE}}' ) ) );
		$this->add_control( $prefix . '_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $hover => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( $prefix . '_hover_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( $hover => 'box-shadow: inset 0 0 0 1.5px {{VALUE}}' ) ) );
		$this->end_tab();
		$this->end_tabs();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array(
			'data-settings' => $this->json_attr(
				array(
					'transition'   => 'fade' === ( $s['transition'] ?? 'slide' ) ? 'fade' : 'slide',
					'speed'        => (int) self::number( $s['speed'] ?? '', 700, 0, 3000 ),
					'autoplay'     => ! empty( $s['autoplay'] ),
					'delay'        => (int) self::number( $s['autoplay_delay'] ?? '', 6000, 1500, 30000 ),
					'pauseOnHover' => ! empty( $s['pause_on_hover'] ),
					'loop'         => ! empty( $s['loop'] ),
					'swipe'        => ! empty( $s['swipe'] ),
					'i18n'         => array(
						/* translators: 1: slide number, 2: number of slides. */
						'status' => __( 'Slide %1$s of %2$s', 'uncoder' ),
						'pause'  => __( 'Pause autoplay', 'uncoder' ),
						'play'   => __( 'Start autoplay', 'uncoder' ),
					),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = Repeater_Rows::get( $this, 'slides', $s['slides'] ?? array() );
		if ( ! $rows ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-slides-placeholder">' . esc_html__( 'Add a slide to start building the slider.', 'uncoder' ) . '</div>';
			}
			return;
		}

		$total      = count( $rows );
		$multi      = $total > 1;
		$viewport   = 'uncoder-slides-' . sanitize_html_class( $ctx->element_id );
		$arrows     = $multi && ! empty( $s['arrows'] );
		$pagination = $multi && in_array( $s['pagination'] ?? 'dots', array( 'dots', 'fraction' ), true ) ? (string) $s['pagination'] : '';
		$toggle     = $multi && ! empty( $s['autoplay'] ) && ! empty( $s['pause_button'] );
		$animation  = (string) ( $s['content_animation'] ?? 'fade-up' );
		$label      = trim( (string) ( $s['slider_label'] ?? '' ) );

		$classes = array( 'uncoder-slides', 'uncoder-slides--' . ( 'fade' === ( $s['transition'] ?? 'slide' ) ? 'fade' : 'slide' ) );
		if ( in_array( $animation, array( 'fade', 'fade-up', 'fade-down', 'zoom' ), true ) ) {
			$classes[] = 'uncoder-slides--content-' . $animation;
		}
		if ( $arrows ) {
			$classes[] = 'uncoder-slides--arrows';
		}
		if ( '' !== $pagination || $toggle ) {
			$classes[] = 'uncoder-slides--paginated';
		}
		if ( $multi && ! empty( $s['swipe'] ) ) {
			$classes[] = 'uncoder-slides--swipe';
		}

		$root = array(
			'class'                => $classes,
			'role'                 => 'region',
			'aria-roledescription' => __( 'carousel', 'uncoder' ),
			'aria-label'           => '' !== $label ? $label : __( 'Highlights', 'uncoder' ),
		);
		// Without buttons the region itself takes focus so ← / → still work from the keyboard.
		if ( $multi && ! $arrows && 'dots' !== $pagination ) {
			$root['tabindex'] = '0';
		}
		echo '<div' . Utils::attrs( $root ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().

		// The rotation control comes first in the tab order (WAI-ARIA carousel pattern).
		if ( $toggle ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
			echo '<button' . Utils::attrs(
				array(
					'type'          => 'button',
					'class'         => 'uncoder-slides__toggle',
					'aria-controls' => $viewport,
					'aria-label'    => __( 'Pause autoplay', 'uncoder' ),
				)
			) . '>' . Icons::render( 'pause', array( 'class' => 'uncoder-slides__pause' ) ) . Icons::render( 'play', array( 'class' => 'uncoder-slides__play' ) ) . '</button>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="uncoder-slides__viewport" id="' . esc_attr( $viewport ) . '">';
		$hero = $this->first_image_eager( $s, $ctx );
		foreach ( $rows as $i => $row ) {
			echo $this->slide( $row, $i, $total, $s, $ctx, 0 === $i && $hero ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}
		echo '</div>';

		if ( $arrows ) {
			$prev = $this->has_icon( $s['prev_icon'] ?? null ) ? $s['prev_icon'] : 'chevron-left';
			$next = $this->has_icon( $s['next_icon'] ?? null ) ? $s['next_icon'] : 'chevron-right';
			foreach ( array( 'prev' => array( $prev, __( 'Previous slide', 'uncoder' ) ), 'next' => array( $next, __( 'Next slide', 'uncoder' ) ) ) as $dir => $arrow ) {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
				echo '<button' . Utils::attrs(
					array(
						'type'          => 'button',
						'class'         => 'uncoder-slides__arrow uncoder-slides__arrow--' . $dir,
						'aria-controls' => $viewport,
						'aria-label'    => $arrow[1],
					)
				) . '>' . $this->render_icon( $arrow[0], array( 'class' => 'uncoder-slides__arrow-icon' ) ) . '</button>';
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		if ( 'dots' === $pagination ) {
			echo '<div class="uncoder-slides__pagination uncoder-slides__pagination--dots"><div class="uncoder-slides__dots" role="group" aria-label="' . esc_attr__( 'Choose slide', 'uncoder' ) . '">';
			for ( $i = 0; $i < $total; $i++ ) {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
				echo '<button' . Utils::attrs(
					array(
						'type'          => 'button',
						'class'         => 0 === $i ? 'uncoder-slides__dot is-active' : 'uncoder-slides__dot',
						'aria-controls' => $viewport,
						/* translators: %d: slide number. */
						'aria-label'    => sprintf( __( 'Go to slide %d', 'uncoder' ), $i + 1 ),
						'aria-current'  => 0 === $i ? 'true' : null,
					)
				) . '></button>';
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></div>';
		} elseif ( 'fraction' === $pagination ) {
			echo '<div class="uncoder-slides__pagination uncoder-slides__pagination--fraction"><div class="uncoder-slides__fraction" aria-hidden="true"><span class="uncoder-slides__current">1</span><span class="uncoder-slides__sep">/</span><span class="uncoder-slides__total">' . (int) $total . '</span></div></div>';
		}

		if ( $multi ) {
			echo '<div class="uncoder-slides__status uncoder-sr-only" aria-live="polite" aria-atomic="true"></div>';
		}
		echo '</div>';
	}

	/**
	 * One slide.
	 *
	 * @param array<string,mixed> $row Slide row (field defaults applied).
	 * @param array<string,mixed> $s   Widget settings.
	 */
	private function slide( array $row, int $i, int $total, array $s, Render_Context $ctx, bool $eager ): string {
		$rid     = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );
		$heading = $this->inline_html( $row['heading'] ?? '' );
		$desc    = $this->inline_html( $row['description'] ?? '' );
		$link    = $this->link_attrs( $row['link'] ?? array() );
		$whole   = ! $ctx->editor && ! empty( $row['link_whole'] ) && isset( $link['href'] );
		$size    = in_array( $s['button_size'] ?? 'lg', self::SIZES, true ) ? (string) $s['button_size'] : 'lg';
		$prefix  = 'slides.' . $i . '.';

		$classes = array( 'uncoder-slides__slide' );
		if ( '' !== $rid ) {
			$classes[] = 'uncoder-ri-' . $rid;
		}
		if ( 0 === $i ) {
			$classes[] = 'is-active';
		}
		if ( in_array( $row['ken_burns'] ?? '', array( 'in', 'out' ), true ) ) {
			$classes[] = 'uncoder-slides__slide--kb-' . $row['ken_burns'];
		}
		if ( $whole ) {
			$classes[] = 'uncoder-slides__slide--linked';
		}

		$out = '<div' . Utils::attrs(
			array(
				'class'                => $classes,
				'role'                 => 'group',
				'aria-roledescription' => __( 'slide', 'uncoder' ),
				/* translators: 1: slide number, 2: number of slides. */
				'aria-label'           => sprintf( __( '%1$d of %2$d', 'uncoder' ), $i + 1, $total ),
				'inert'                => 0 !== $i,
				'aria-hidden'          => 0 !== $i ? 'true' : null,
			)
		) . '>';

		// Background: a real <img> (srcset, lazy loading, LCP) cropped like a cover background.
		$media = is_array( $row['image'] ?? null ) ? $row['image'] : array();
		$attrs = array(
			'class'     => 'uncoder-slides__img',
			'draggable' => 'false',
			'loading'   => $eager ? 'eager' : 'lazy',
		);
		if ( $eager ) {
			$attrs['fetchpriority'] = 'high';
		}
		if ( ! empty( $media['id'] ) ) {
			$attrs['sizes'] = '100vw';
		}
		$out .= $this->image( $media, sanitize_key( (string) ( $s['image_size'] ?? 'full' ) ), $attrs );
		$out .= '<span class="uncoder-slides__overlay" aria-hidden="true"></span>';

		if ( $whole ) {
			$name = trim( wp_strip_all_tags( $heading ) );
			$name = '' !== $name ? $name : trim( (string) ( $row['button_text'] ?? '' ) );
			/* translators: %d: slide number. */
			$link['aria-label'] = '' !== $name ? $name : sprintf( __( 'Slide %d', 'uncoder' ), $i + 1 );
			$link['class']      = 'uncoder-slides__link';
			$out               .= '<a' . Utils::attrs( $link ) . '></a>';
		}

		$out .= '<div class="uncoder-slides__inner"><div class="uncoder-slides__content">';
		if ( '' !== trim( wp_strip_all_tags( $heading ) ) || $ctx->editor ) {
			$tag  = Utils::tag( $s['heading_tag'] ?? 'h2', Utils::HEADING_TAGS, 'h2' );
			$out .= '<' . $tag . ' class="uncoder-slides__heading"' . $ctx->inline( $prefix . 'heading' ) . '>' . $heading . '</' . $tag . '>';
		}
		if ( '' !== trim( wp_strip_all_tags( $desc ) ) ) {
			$out .= '<p class="uncoder-slides__description"' . $ctx->inline( $prefix . 'description' ) . '>' . $desc . '</p>';
		}
		$buttons  = $this->button( (string) ( $row['button_text'] ?? '' ), $whole ? array() : $link, (string) ( $s['button_variant'] ?? 'primary' ), $size, 'main', $ctx->inline( $prefix . 'button_text' ) );
		$buttons .= $this->button( (string) ( $row['button2_text'] ?? '' ), $this->link_attrs( $row['button2_link'] ?? array() ), (string) ( $s['button2_variant'] ?? 'outline' ), $size, 'second', $ctx->inline( $prefix . 'button2_text' ) );
		if ( '' !== $buttons ) {
			$out .= '<div class="uncoder-slides__buttons">' . $buttons . '</div>';
		}
		return $out . '</div></div></div>';
	}

	/**
	 * Design System button, or '' without text. Without a link it is a plain span (e.g. under a whole-slide link).
	 *
	 * @param array<string,mixed> $attrs Link attributes.
	 */
	private function button( string $text, array $attrs, string $variant, string $size, string $modifier, string $inline ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}
		$variant        = in_array( $variant, self::VARIANTS, true ) ? $variant : 'primary';
		$attrs['class'] = array( 'uncoder-btn', 'uncoder-btn--' . $variant, 'uncoder-btn--' . $size, 'uncoder-slides__button', 'uncoder-slides__button--' . $modifier );
		$tag            = isset( $attrs['href'] ) ? 'a' : 'span';
		return '<' . $tag . Utils::attrs( $attrs ) . '><span class="uncoder-btn__text"' . $inline . '>' . esc_html( $text ) . '</span></' . $tag . '>';
	}

	/**
	 * Whether the first slide's image loads eagerly with high priority (the page's likely LCP).
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function first_image_eager( array $s, Render_Context $ctx ): bool {
		$mode = (string) ( $s['first_image_loading'] ?? '' );
		if ( 'eager' === $mode || 'lazy' === $mode ) {
			return 'eager' === $mode;
		}
		return $this->in_first_section( $ctx );
	}

	/**
	 * Whether the element being rendered sits in its document's first section (4 levels deep at most).
	 */
	private function in_first_section( Render_Context $ctx ): bool {
		if ( $ctx->editor || $ctx->repeat || ! $ctx->doc_id || '' === $ctx->element_id ) {
			return false;
		}
		static $cache = array();
		if ( ! array_key_exists( $ctx->doc_id, $cache ) ) {
			$doc   = Plugin::instance()->documents()->get( $ctx->doc_id );
			$ids   = array();
			$visit = static function ( array $node, int $depth ) use ( &$visit, &$ids ): void {
				if ( $depth > 4 || ! empty( $node['disabled'] ) ) {
					return;
				}
				if ( 'slides' === ( $node['type'] ?? '' ) && isset( $node['id'] ) ) {
					$ids[] = (string) $node['id'];
				}
				foreach ( (array) ( $node['children'] ?? array() ) as $child ) {
					if ( is_array( $child ) ) {
						$visit( $child, $depth + 1 );
					}
				}
			};
			foreach ( $doc && $doc->is_builder() ? $doc->elements() : array() as $node ) {
				if ( is_array( $node ) && empty( $node['disabled'] ) ) {
					$visit( $node, 0 );
					break;
				}
			}
			$cache[ $ctx->doc_id ] = $ids;
		}
		return in_array( $ctx->element_id, $cache[ $ctx->doc_id ], true );
	}

	/**
	 * Number setting with a fallback for empty values, clamped.
	 *
	 * @param mixed $value Raw value.
	 * @return int|float
	 */
	private static function number( $value, $fallback, $min, $max ) {
		if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
			return $fallback;
		}
		return min( max( $value + 0, $min ), $max );
	}
}
