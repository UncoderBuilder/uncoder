<?php
/**
 * Shared controls and markup of the scroll-snap carousel engine.
 *
 * Not a widget: the widget registry only scans includes/Widgets/*.php.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

use Uncoder\Builder\Core\Icons;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Used by the carousel, image-carousel and testimonial-carousel widgets.
 *
 * Markup: .uncoder-carousel > .uncoder-carousel__viewport > .uncoder-carousel__track > .uncoder-carousel__slide,
 * arrows inside the viewport, pagination after it. Behaviour: front-end module "carousel".
 * Styles: widgets/carousel.css (declare frontend_styles() => [ 'carousel' ] when the widget is not "carousel").
 * Every selector uses child combinators so carousels nested inside slides keep their own settings.
 */
trait Carousel_Engine {

	/**
	 * Selector of the carousel root: the widget's own element (its single root merges with the wrapper).
	 */
	protected function carousel_root(): string {
		return '{{WRAPPER}}';
	}

	/**
	 * Whether the widget offers the "Continuous (ticker)" motion: its render() prints the slides a second time
	 * with carousel_slide_start( …, true ) after the real ones (see Carousel).
	 */
	protected function supports_ticker(): bool {
		return false;
	}

	/**
	 * @param array<string,mixed> $s Effective settings.
	 */
	protected function is_ticker( array $s ): bool {
		return $this->supports_ticker() && 'continuous' === ( $s['motion'] ?? '' );
	}

	/**
	 * "Carousel" behaviour section (content tab).
	 */
	protected function register_carousel_settings(): void {
		$root = $this->carousel_root();

		$this->start_section( 'carousel', array( 'label' => __( 'Carousel', 'uncoder' ) ) );
		$slides = array();
		if ( $this->supports_ticker() ) {
			$slides = array( 'motion!' => 'continuous' );
			$this->add_control(
				'motion',
				array(
					'type'    => 'select',
					'label'   => __( 'Motion', 'uncoder' ),
					'default' => '',
					'options' => array(
						''           => __( 'Slide by slide', 'uncoder' ),
						'continuous' => __( 'Continuous (ticker)', 'uncoder' ),
					),
					'ai'      => '"continuous": the slides glide by endlessly at a steady speed, like a marquee of cards (testimonial walls, logo strips); no arrows, dots or autoplay. Set slide_width (each card\'s width), ticker_speed (px per second) and ticker_direction.',
				)
			);
			$this->add_responsive_control(
				'slide_width',
				array(
					'type'       => 'slider',
					'label'      => __( 'Slide width', 'uncoder' ),
					'size_units' => array( 'px', 'rem', 'vw' ),
					'range'      => array( 'px' => array( 'min' => 80, 'max' => 1200 ) ),
					'condition'  => array( 'motion' => 'continuous' ),
					'selectors'  => array( $root => '--uncoder-carousel-slide-w: {{VALUE}}' ),
				)
			);
			$this->add_control(
				'ticker_speed',
				array(
					'type'      => 'number',
					'label'     => __( 'Speed (px per second)', 'uncoder' ),
					'min'       => 5,
					'max'       => 500,
					'step'      => 5,
					'default'   => 40,
					'condition' => array( 'motion' => 'continuous' ),
				)
			);
			$this->add_control(
				'ticker_direction',
				array(
					'type'      => 'choose',
					'label'     => __( 'Direction', 'uncoder' ),
					'default'   => 'left',
					'options'   => array(
						'left'  => array( 'label' => __( 'To the left', 'uncoder' ), 'icon' => 'arrow-left' ),
						'right' => array( 'label' => __( 'To the right', 'uncoder' ), 'icon' => 'arrow-right' ),
					),
					'condition' => array( 'motion' => 'continuous' ),
				)
			);
			$this->add_responsive_control(
				'ticker_layout',
				array(
					'type'                 => 'select',
					'label'                => __( 'Layout', 'uncoder' ),
					'default'              => 'row',
					'options'              => array(
						'row'   => __( 'Moving row', 'uncoder' ),
						'stack' => __( 'Still list', 'uncoder' ),
					),
					'description'          => __( 'Set "Still list" on phones to show the slides one under another.', 'uncoder' ),
					'condition'            => array( 'motion' => 'continuous' ),
					// Custom properties read by the ticker rules in carousel.css (no script needed per device).
					'selectors_dictionary' => array(
						'row'   => '--uncoder-carousel-stack:0;--uncoder-ticker-dir:row;--uncoder-ticker-anim:uncoder-carousel-ticker;--uncoder-ticker-anim-rtl:uncoder-carousel-ticker-rtl;--uncoder-ticker-track-w:max-content;--uncoder-ticker-end:var(--uncoder-carousel-gap);--uncoder-ticker-slide:var(--uncoder-carousel-slide-w, 320px);--uncoder-ticker-copy:flex;--uncoder-ticker-clip:hidden;--uncoder-ticker-fade-mask:initial',
						'stack' => '--uncoder-carousel-stack:1;--uncoder-ticker-dir:column;--uncoder-ticker-anim:none;--uncoder-ticker-anim-rtl:none;--uncoder-ticker-track-w:auto;--uncoder-ticker-end:0px;--uncoder-ticker-slide:auto;--uncoder-ticker-copy:none;--uncoder-ticker-clip:visible;--uncoder-ticker-fade-mask:none',
					),
					'selectors'            => array( $root => '{{VALUE}}' ),
					'ai'                   => 'Continuous motion only: "stack" (usually ticker_layout_mobile) turns the moving row into a still list, every slide full width.',
				)
			);
			$this->add_control(
				'ticker_pause',
				array(
					'type'        => 'switch',
					'label'       => __( 'Pause on hover', 'uncoder' ),
					'description' => __( 'Also while a link inside has keyboard focus. Visitors who prefer reduced motion get a still row they can scroll.', 'uncoder' ),
					'default'     => true,
					'condition'   => array( 'motion' => 'continuous' ),
				)
			);
			$this->add_control(
				'ticker_fade',
				array(
					'type'        => 'switch',
					'label'       => __( 'Fade edges', 'uncoder' ),
					'description' => __( 'The slides fade in at one side and out at the other.', 'uncoder' ),
					'condition'   => array( 'motion' => 'continuous' ),
				)
			);
			$this->add_control(
				'ticker_fade_width',
				array(
					'type'       => 'slider',
					'label'      => __( 'Edge fade width', 'uncoder' ),
					'size_units' => array( '%', 'px' ),
					'range'      => array( 'px' => array( 'min' => 0, 'max' => 400 ) ),
					'condition'  => array( 'motion' => 'continuous', 'ticker_fade' => 'yes' ),
					'selectors'  => array( $root => '--uncoder-carousel-fade: {{VALUE}}' ),
				)
			);
		}
		$this->add_responsive_control(
			'slides_per_view',
			array(
				'type'      => 'number',
				'label'     => __( 'Slides per view', 'uncoder' ),
				'min'       => 1,
				'max'       => 10,
				'step'      => 0.1,
				'condition' => $slides,
				'selectors' => array( $root => '--uncoder-carousel-spv: {{VALUE}}' ),
				'ai'        => 'Decimals reveal part of the next slide, e.g. 1.2. Set slides_per_view_tablet / slides_per_view_mobile for smaller screens.',
			)
		);
		$this->add_responsive_control(
			'slides_to_scroll',
			array(
				'type'      => 'number',
				'label'     => __( 'Slides to scroll', 'uncoder' ),
				'min'       => 1,
				'max'       => 10,
				'step'      => 1,
				'condition' => $slides,
				'selectors' => array( $root => '--uncoder-carousel-sts: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'em', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( $root => '--uncoder-carousel-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'slides_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Vertical alignment', 'uncoder' ),
				'options'              => array(
					'stretch' => array( 'label' => __( 'Equal height', 'uncoder' ), 'icon' => 'stretch-vertical' ),
					'start'   => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'align-start-horizontal' ),
					'center'  => array( 'label' => __( 'Middle', 'uncoder' ), 'icon' => 'align-center-horizontal' ),
					'end'     => array( 'label' => __( 'Bottom', 'uncoder' ), 'icon' => 'align-end-horizontal' ),
				),
				'selectors_dictionary' => array(
					'stretch' => 'stretch',
					'start'   => 'flex-start',
					'center'  => 'center',
					'end'     => 'flex-end',
				),
				'selectors'            => array( $root => '--uncoder-carousel-align: {{VALUE}}' ),
			)
		);
		// Framer's slideshow with effects: the current slide in the middle, its smaller, paler neighbours either side.
		$this->add_control(
			'center_mode',
			array(
				'type'        => 'switch',
				'label'       => __( 'Center the current slide', 'uncoder' ),
				'description' => __( 'The current slide sits in the middle with its neighbours on both sides. With Rewind on, the slides loop endlessly.', 'uncoder' ),
				'condition'   => $slides,
				'ai'          => 'A centered, endless slideshow: center_mode + loop, a fixed center_slide_width (e.g. "350px") and gap; inactive_scale (e.g. 0.85) and inactive_opacity (e.g. 0.5) shrink and fade the neighbours; autoplay for the motion.',
			)
		);
		$centered = array_merge( $slides, array( 'center_mode' => 'yes' ) );
		$this->add_responsive_control(
			'center_slide_width',
			array(
				'type'        => 'slider',
				'label'       => __( 'Slide width', 'uncoder' ),
				'description' => __( 'Empty: from Slides per view.', 'uncoder' ),
				'size_units'  => array( 'px', '%', 'rem', 'vw' ),
				'range'       => array( 'px' => array( 'min' => 80, 'max' => 1200 ) ),
				'condition'   => $centered,
				'selectors'   => array( $root => '--uncoder-carousel-center-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'inactive_scale',
			array(
				'type'      => 'number',
				'label'     => __( 'Other slides: scale', 'uncoder' ),
				'min'       => 0.5,
				'max'       => 1,
				'step'      => 0.01,
				'condition' => $centered,
				'selectors' => array( $root => '--uncoder-carousel-inactive-scale: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'inactive_opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Other slides: opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.05,
				'condition' => $centered,
				'selectors' => array( $root => '--uncoder-carousel-inactive-opacity: {{VALUE}}' ),
			)
		);
		$this->add_control( 'nav_heading', array( 'type' => 'heading', 'label' => __( 'Navigation', 'uncoder' ), 'condition' => $slides ) );
		$this->add_control(
			'arrows',
			array(
				'type'      => 'switch',
				'label'     => __( 'Arrows', 'uncoder' ),
				'default'   => true,
				'condition' => $slides,
			)
		);
		$this->add_control(
			'arrows_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Arrows position', 'uncoder' ),
				'default'   => 'inside',
				'options'   => array(
					'inside'  => __( 'Inside, over the slides', 'uncoder' ),
					'outside' => __( 'Outside the slides', 'uncoder' ),
					'bottom'  => __( 'Below, next to the pagination', 'uncoder' ),
					'top'     => __( 'Above the slides, at the end', 'uncoder' ),
				),
				'condition' => array( 'arrows' => 'yes' ),
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
				'default'   => 'dots',
				'condition' => $slides,
				'options'   => array(
					''         => __( 'None', 'uncoder' ),
					'dots'     => __( 'Dots', 'uncoder' ),
					'fraction' => __( 'Fraction (2 / 5)', 'uncoder' ),
					'progress' => __( 'Progress bar', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'loop',
			array(
				'type'        => 'switch',
				'label'       => __( 'Rewind', 'uncoder' ),
				'description' => __( 'Next on the last slide goes back to the first one; a centered carousel loops endlessly.', 'uncoder' ),
				'condition'   => $slides,
			)
		);
		$this->add_control(
			'drag',
			array(
				'type'        => 'switch',
				'label'       => __( 'Mouse drag', 'uncoder' ),
				'description' => __( 'Touch swipe and trackpads always work.', 'uncoder' ),
				'default'     => true,
				'condition'   => $slides,
			)
		);
		$this->add_control(
			'speed',
			array(
				'type'    => 'number',
				'label'     => __( 'Transition duration (ms)', 'uncoder' ),
				'min'       => 0,
				'max'       => 3000,
				'step'      => 50,
				'default'   => 500,
				'condition' => $slides,
			)
		);
		$this->add_control( 'autoplay_heading', array( 'type' => 'heading', 'label' => __( 'Autoplay', 'uncoder' ), 'condition' => $slides ) );
		$this->add_control(
			'autoplay',
			array(
				'type'        => 'switch',
				'label'       => __( 'Autoplay', 'uncoder' ),
				'description' => __( 'Never starts for visitors who prefer reduced motion; pauses while hovered or focused.', 'uncoder' ),
				'condition'   => $slides,
			)
		);
		$this->add_control(
			'autoplay_delay',
			array(
				'type'      => 'number',
				'label'     => __( 'Delay (ms)', 'uncoder' ),
				'min'       => 1000,
				'max'       => 30000,
				'step'      => 500,
				'default'   => 5000,
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
			'carousel_label',
			array(
				'type'        => 'text',
				'label'       => __( 'Accessible name', 'uncoder' ),
				'placeholder' => __( 'Carousel', 'uncoder' ),
				'description' => __( 'Announced by screen readers, e.g. "Customer stories".', 'uncoder' ),
			)
		);
		$this->end_section();
	}

	/**
	 * "Arrows" and "Pagination" style sections.
	 */
	protected function register_carousel_style(): void {
		$root  = $this->carousel_root();
		$arrow = $root . ' > .uncoder-carousel__viewport > .uncoder-carousel__arrow';
		$pag   = $root . ' > .uncoder-carousel__pagination';

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
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 120 ) ),
				'selectors'  => array( $root => '--uncoder-carousel-arrow-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'arrow_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 80 ) ),
				'selectors'  => array( $root => '--uncoder-carousel-arrow-icon: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'arrow_offset',
			array(
				'type'        => 'slider',
				'label'       => __( 'Offset', 'uncoder' ),
				'description' => __( 'Distance from the slides edge (inside / outside) or between the two arrows (below).', 'uncoder' ),
				'size_units'  => array( 'px', 'rem' ),
				'range'       => array( 'px' => array( 'min' => -40, 'max' => 100 ) ),
				'selectors'   => array( $root => '--uncoder-carousel-arrow-offset: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'arrow_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'arrow_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( $root => '--uncoder-carousel-arrow-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'arrow_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( $root => '--uncoder-carousel-arrow-bg: {{VALUE}}' ),
			)
		);
		$this->add_group( 'arrow_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => $arrow ) );
		$this->add_group( 'arrow_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => $arrow ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'arrow_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( $root => '--uncoder-carousel-arrow-color-hover: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'arrow_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( $root => '--uncoder-carousel-arrow-bg-hover: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'arrow_hover_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( $arrow . ':is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ),
			)
		);
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

		$this->start_section(
			'style_pagination',
			array(
				'label'     => __( 'Pagination', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'pagination!' => '' ),
			)
		);
		$this->add_responsive_control(
			'pagination_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Distance from slides', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'selectors'  => array( $root => '--uncoder-carousel-pag-spacing: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'pagination_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
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
				'selectors'            => array( $root => '--uncoder-carousel-pag-justify: {{VALUE}}' ),
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
				'selectors'  => array( $root => '--uncoder-carousel-dot-size: {{VALUE}}' ),
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
				'selectors'   => array( $root => '--uncoder-carousel-dot-active-width: {{VALUE}}' ),
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
				'selectors'  => array( $root => '--uncoder-carousel-dot-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dots_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Dot color', 'uncoder' ),
				'condition' => array( 'pagination' => 'dots' ),
				'selectors' => array( $root => '--uncoder-carousel-dot-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dots_active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Active dot color', 'uncoder' ),
				'condition' => array( 'pagination' => 'dots' ),
				'selectors' => array( $root => '--uncoder-carousel-dot-active: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'fraction_typography',
			array(
				'type'      => 'typography',
				'label'     => __( 'Typography', 'uncoder' ),
				'condition' => array( 'pagination' => 'fraction' ),
				'selector'  => $pag . ' > .uncoder-carousel__fraction',
			)
		);
		$this->add_control(
			'fraction_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'condition' => array( 'pagination' => 'fraction' ),
				'selectors' => array( $pag . ' > .uncoder-carousel__fraction' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'progress_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Bar height', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 12 ) ),
				'condition'  => array( 'pagination' => 'progress' ),
				'selectors'  => array( $root => '--uncoder-carousel-progress-h: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'progress_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Bar color', 'uncoder' ),
				'condition' => array( 'pagination' => 'progress' ),
				'selectors' => array( $root => '--uncoder-carousel-progress-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'progress_track_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Track color', 'uncoder' ),
				'condition' => array( 'pagination' => 'progress' ),
				'selectors' => array( $root => '--uncoder-carousel-progress-track: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'toggle_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Pause button color', 'uncoder' ),
				'condition' => array( 'autoplay' => 'yes' ),
				'selectors' => array( $pag . ' > .uncoder-carousel__toggle' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Settings consumed by the "carousel" module (data-settings on the widget wrapper).
	 *
	 * @param array<string,mixed> $s Effective settings.
	 * @return array<string,mixed>
	 */
	protected function carousel_data( array $s ): array {
		if ( $this->is_ticker( $s ) ) {
			return array( 'ticker' => (int) self::carousel_number( $s['ticker_speed'] ?? '', 40, 5, 500 ) );
		}
		return array(
			'autoplay'     => ! empty( $s['autoplay'] ),
			'delay'        => (int) self::carousel_number( $s['autoplay_delay'] ?? '', 5000, 1000, 30000 ),
			'pauseOnHover' => ! empty( $s['pause_on_hover'] ),
			'loop'         => ! empty( $s['loop'] ),
			'center'       => ! empty( $s['center_mode'] ),
			'drag'         => ! empty( $s['drag'] ),
			'speed'        => (int) self::carousel_number( $s['speed'] ?? '', 500, 0, 3000 ),
			'i18n'         => array(
				/* translators: %s: slide number. */
				'goto'   => __( 'Go to slide %s', 'uncoder' ),
				/* translators: 1: slide number, 2: number of slides. */
				'status' => __( 'Slide %1$s of %2$s', 'uncoder' ),
				'pause'  => __( 'Pause autoplay', 'uncoder' ),
				'play'   => __( 'Start autoplay', 'uncoder' ),
			),
		);
	}

	/**
	 * Opens the carousel root, viewport and track.
	 *
	 * @param array<string,mixed> $s       Effective settings.
	 * @param string[]            $classes Extra root classes.
	 */
	protected function carousel_start( array $s, Render_Context $ctx, array $classes = array() ): string {
		$classes = array_merge( array( 'uncoder-carousel' ), $classes );
		if ( $this->is_ticker( $s ) ) {
			$classes[] = 'uncoder-carousel--ticker';
			// In the editor the row stays still, so every slide can be selected and edited.
			if ( ! $ctx->editor ) {
				$classes[] = 'uncoder-carousel--ticker-run';
			}
			if ( 'right' === ( $s['ticker_direction'] ?? 'left' ) ) {
				$classes[] = 'uncoder-carousel--right';
			}
			if ( ! array_key_exists( 'ticker_pause', $s ) || ! empty( $s['ticker_pause'] ) ) {
				$classes[] = 'uncoder-carousel--pause';
			}
			if ( ! empty( $s['ticker_fade'] ) ) {
				$classes[] = 'uncoder-carousel--fade-edges';
			}
		} else {
			if ( ! empty( $s['arrows'] ) ) {
				$position  = in_array( $s['arrows_position'] ?? 'inside', array( 'inside', 'outside', 'bottom', 'top' ), true ) ? $s['arrows_position'] : 'inside';
				$classes[] = 'uncoder-carousel--arrows-' . $position;
			}
			if ( ! empty( $s['center_mode'] ) ) {
				$classes[] = 'uncoder-carousel--center';
			}
		}
		$label = trim( (string) ( $s['carousel_label'] ?? '' ) );
		return '<div' . Utils::attrs(
			array(
				'class'                => $classes,
				'role'                 => 'region',
				'aria-roledescription' => __( 'carousel', 'uncoder' ),
				'aria-label'           => '' !== $label ? $label : __( 'Carousel', 'uncoder' ),
			)
		) . '><div class="uncoder-carousel__viewport"><div class="uncoder-carousel__track" id="' . esc_attr( $this->carousel_track_id( $ctx ) ) . '">';
	}

	/**
	 * Opens one slide. A clone (the ticker's second copy) is hidden from assistive tech and inert.
	 *
	 * @param string[] $classes Extra slide classes.
	 */
	protected function carousel_slide_start( int $index, int $total, array $classes = array(), bool $clone = false ): string {
		array_unshift( $classes, 'uncoder-carousel__slide' );
		if ( $clone ) {
			$classes[] = 'uncoder-carousel__slide--clone';
			return '<div' . Utils::attrs(
				array(
					'class'       => $classes,
					'aria-hidden' => 'true',
					'inert'       => true,
				)
			) . '>';
		}
		return '<div' . Utils::attrs(
			array(
				'class'                => $classes,
				'role'                 => 'group',
				'aria-roledescription' => __( 'slide', 'uncoder' ),
				/* translators: 1: slide number, 2: number of slides. */
				'aria-label'           => sprintf( __( '%1$d of %2$d', 'uncoder' ), $index + 1, $total ),
			)
		) . '>';
	}

	/**
	 * Closes the track, prints arrows, pagination and the live region, closes the root.
	 *
	 * @param array<string,mixed> $s Effective settings.
	 */
	protected function carousel_end( array $s, Render_Context $ctx, int $total ): string {
		if ( $this->is_ticker( $s ) ) {
			return '</div></div></div>';
		}
		$track = $this->carousel_track_id( $ctx );
		$out   = '</div>';
		if ( ! empty( $s['arrows'] ) ) {
			$prev = $this->has_icon( $s['prev_icon'] ?? null ) ? $s['prev_icon'] : 'chevron-left';
			$next = $this->has_icon( $s['next_icon'] ?? null ) ? $s['next_icon'] : 'chevron-right';
			$out .= '<button' . Utils::attrs(
				array(
					'type'          => 'button',
					'class'         => 'uncoder-carousel__arrow uncoder-carousel__arrow--prev',
					'aria-controls' => $track,
					'aria-label'    => __( 'Previous slide', 'uncoder' ),
				)
			) . '>' . $this->render_icon( $prev, array( 'class' => 'uncoder-carousel__arrow-icon' ) ) . '</button>';
			$out .= '<button' . Utils::attrs(
				array(
					'type'          => 'button',
					'class'         => 'uncoder-carousel__arrow uncoder-carousel__arrow--next',
					'aria-controls' => $track,
					'aria-label'    => __( 'Next slide', 'uncoder' ),
				)
			) . '>' . $this->render_icon( $next, array( 'class' => 'uncoder-carousel__arrow-icon' ) ) . '</button>';
		}
		$out .= '</div>';

		$pagination = in_array( $s['pagination'] ?? 'dots', array( 'dots', 'fraction', 'progress' ), true ) ? (string) $s['pagination'] : '';
		$toggle     = ! empty( $s['autoplay'] ) && ! empty( $s['pause_button'] );
		if ( '' !== $pagination || $toggle ) {
			$out .= '<div class="uncoder-carousel__pagination uncoder-carousel__pagination--' . esc_attr( '' !== $pagination ? $pagination : 'none' ) . '">';
			if ( $toggle ) {
				$out .= '<button' . Utils::attrs(
					array(
						'type'          => 'button',
						'class'         => 'uncoder-carousel__toggle',
						'aria-controls' => $track,
						'aria-label'    => __( 'Pause autoplay', 'uncoder' ),
					)
				) . '>' . Icons::render( 'pause', array( 'class' => 'uncoder-carousel__pause' ) ) . Icons::render( 'play', array( 'class' => 'uncoder-carousel__play' ) ) . '</button>';
			}
			if ( 'dots' === $pagination ) {
				$out .= '<div class="uncoder-carousel__dots" role="group" aria-label="' . esc_attr__( 'Choose slide', 'uncoder' ) . '"></div>';
			} elseif ( 'fraction' === $pagination ) {
				$out .= '<div class="uncoder-carousel__fraction" aria-hidden="true"><span class="uncoder-carousel__current">1</span><span class="uncoder-carousel__sep">/</span><span class="uncoder-carousel__total">' . (int) $total . '</span></div>';
			} elseif ( 'progress' === $pagination ) {
				$out .= '<div class="uncoder-carousel__progress" aria-hidden="true"><span class="uncoder-carousel__progress-bar"></span></div>';
			}
			$out .= '</div>';
		}
		$out .= '<div class="uncoder-carousel__status uncoder-sr-only" aria-live="polite" aria-atomic="true"></div>';
		return $out . '</div>';
	}

	private function carousel_track_id( Render_Context $ctx ): string {
		return 'uncoder-carousel-' . sanitize_html_class( $ctx->element_id ) . '-track';
	}

	/**
	 * Number setting with a fallback for empty values, clamped.
	 *
	 * @param mixed $value Raw value.
	 * @return int|float
	 */
	private static function carousel_number( $value, $fallback, $min, $max ) {
		if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
			return $fallback;
		}
		return min( max( $value + 0, $min ), $max );
	}
}
