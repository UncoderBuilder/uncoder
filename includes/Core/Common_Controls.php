<?php
/**
 * Controls shared by every element (the Advanced tab).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * All keys start with "_" so they never clash with widget settings.
 */
final class Common_Controls {

	public const ANIMATIONS = array(
		''            => 'None',
		'fade-in'     => 'Fade in',
		'fade-up'     => 'Fade up',
		'fade-down'   => 'Fade down',
		'fade-left'   => 'Fade from left',
		'fade-right'  => 'Fade from right',
		'zoom-in'     => 'Zoom in',
		'zoom-out'    => 'Zoom out',
		'slide-up'    => 'Slide up',
		'slide-down'  => 'Slide down',
		'slide-left'  => 'Slide from left',
		'slide-right' => 'Slide from right',
		'flip-up'     => 'Flip up',
		'blur-in'     => 'Blur in',
	);

	/** Widgets whose text can be revealed word by word, letter by letter or line by line. */
	public const REVEAL_WIDGETS = array( 'heading', 'text-editor', 'post-title', 'archive-title', 'site-title', 'site-tagline', 'post-excerpt', 'blockquote' );

	public const REVEAL_EFFECTS = array(
		'fade-up' => 'Fade up',
		'fade'    => 'Fade',
		'blur'    => 'Blur in',
		'mask'    => 'Slide up from a mask',
		'rotate'  => 'Flip up',
	);

	public const MOTION_SCALE = array(
		''       => 'None',
		'in'     => 'Grow into place',
		'out'    => 'Shrink as it leaves',
		'in-out' => 'Grow in, shrink out',
		'grow'   => 'Grow while scrolling',
		'shrink' => 'Shrink while scrolling',
	);

	public static function register( Element_Base $el ): void {
		$is_container = $el->is_container();

		$el->start_section( '_section_layout', array( 'label' => __( 'Layout', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_responsive_control(
			'_margin',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Margin', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'vh' ),
				'allow_auto' => true,
				'selectors'  => array( '{{WRAPPER}}' => 'margin: {{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'vh' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		if ( ! $is_container ) {
			$el->add_responsive_control(
				'_width',
				array(
					'type'                 => 'choose',
					'label'                => __( 'Width', 'uncoder' ),
					'options'              => array(
						''       => array( 'label' => __( 'Default', 'uncoder' ), 'icon' => 'rotate-ccw' ),
						'full'   => array( 'label' => __( 'Full width', 'uncoder' ), 'icon' => 'move-horizontal' ),
						'auto'   => array( 'label' => __( 'Fit content', 'uncoder' ), 'icon' => 'minimize-2' ),
						'custom' => array( 'label' => __( 'Custom', 'uncoder' ), 'icon' => 'ruler' ),
					),
					'selectors_dictionary' => array(
						'full' => 'width:100%;max-width:100%',
						'auto' => 'width:auto;max-width:100%',
					),
					'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				)
			);
			$el->add_responsive_control(
				'_custom_width',
				array(
					'type'       => 'slider',
					'label'      => __( 'Custom width', 'uncoder' ),
					'size_units' => array( 'px', '%', 'vw', 'rem' ),
					'condition'  => array( '_width' => 'custom' ),
					'selectors'  => array( '{{WRAPPER}}' => 'width: {{VALUE}}; max-width: 100%' ),
				)
			);
		}
		$el->add_responsive_control(
			'_align_self',
			array(
				'type'      => 'choose',
				'label'     => __( 'Align self', 'uncoder' ),
				'axis'      => 'self',
				'options'   => array(
					'flex-start' => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-start-vertical' ),
					'center'     => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center-vertical' ),
					'flex-end'   => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-end-vertical' ),
					'stretch'    => array( 'label' => __( 'Stretch', 'uncoder' ), 'icon' => 'stretch-vertical' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'align-self: {{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_order',
			array(
				'type'      => 'number',
				'label'     => __( 'Order', 'uncoder' ),
				'min'       => -99,
				'max'       => 99,
				'selectors' => array( '{{WRAPPER}}' => 'order: {{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_flex_grow',
			array(
				'type'      => 'number',
				'label'     => __( 'Grow', 'uncoder' ),
				'min'       => 0,
				'max'       => 99,
				'selectors' => array( '{{WRAPPER}}' => 'flex-grow: {{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_flex_shrink',
			array(
				'type'      => 'number',
				'label'     => __( 'Shrink', 'uncoder' ),
				'min'       => 0,
				'max'       => 99,
				'selectors' => array( '{{WRAPPER}}' => 'flex-shrink: {{VALUE}}' ),
			)
		);
		// Inside a grid container: how many tracks the element takes (bento tiles, a tall card beside two short ones).
		$el->add_responsive_control(
			'_grid_column_span',
			array(
				'type'        => 'number',
				'label'       => __( 'Column span', 'uncoder' ),
				'description' => __( 'Inside a grid container: the number of columns this element takes.', 'uncoder' ),
				'min'         => 1,
				'max'         => 12,
				'selectors'   => array( '{{WRAPPER}}' => 'grid-column: span {{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_grid_row_span',
			array(
				'type'        => 'number',
				'label'       => __( 'Row span', 'uncoder' ),
				'description' => __( 'Inside a grid container: the number of rows this element takes.', 'uncoder' ),
				'min'         => 1,
				'max'         => 12,
				'selectors'   => array( '{{WRAPPER}}' => 'grid-row: span {{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Position', 'uncoder' ),
				'options'   => array(
					''         => __( 'Default', 'uncoder' ),
					'relative' => __( 'Relative', 'uncoder' ),
					'absolute' => __( 'Absolute', 'uncoder' ),
					'fixed'    => __( 'Fixed', 'uncoder' ),
					'sticky'   => __( 'Sticky', 'uncoder' ),
				),
				// "Sticky" (Motion) sets the position itself; a position here would undo it.
				'condition' => array( '_sticky' => '' ),
				'selectors' => array( '{{WRAPPER}}' => 'position: {{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_offset',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Offsets', 'uncoder' ),
				'size_units' => array( 'px', '%', 'vw', 'vh', 'rem' ),
				'condition'  => array( '_position!' => '' ),
				'selectors'  => array( '{{WRAPPER}}' => 'inset: {{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_z_index',
			array(
				'type'      => 'number',
				'label'     => __( 'Z-index', 'uncoder' ),
				'min'       => -1,
				'max'       => 99999,
				'selectors' => array( '{{WRAPPER}}' => 'z-index: {{VALUE}}' ),
			)
		);
		$el->add_control(
			'_classes',
			array(
				'type'   => 'multiselect',
				'label'  => __( 'Global classes', 'uncoder' ),
				'source' => 'classes',
				'ui'     => 'classes',
				'ai'     => 'Ids of Design System classes for this element type (get_design_system → kit.classes). Shared styles live in the class; the element\'s own settings still override it.',
			)
		);
		$el->end_section();

		$el->start_section( '_section_style', array( 'label' => __( 'Background & border', 'uncoder' ), 'tab' => 'advanced' ) );
		if ( ! $is_container ) {
			$el->add_group( '_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
			$el->add_group( '_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
			$el->add_responsive_control(
				'_radius',
				array(
					'type'       => 'dimensions',
					'label'      => __( 'Border radius', 'uncoder' ),
					'size_units' => array( 'px', '%', 'em', 'rem' ),
					'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
				)
			);
			$el->add_group( '_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Box shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		}
		$el->add_control(
			'_overflow',
			array(
				'type'      => 'select',
				'label'     => __( 'Overflow', 'uncoder' ),
				'options'   => array( '' => __( 'Default', 'uncoder' ), 'hidden' => __( 'Hidden', 'uncoder' ), 'clip' => __( 'Clip', 'uncoder' ), 'auto' => __( 'Scroll', 'uncoder' ) ),
				'selectors' => array( '{{WRAPPER}}' => 'overflow: {{VALUE}}' ),
			)
		);
		$el->add_control(
			'_opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.01,
				'selectors' => array( '{{WRAPPER}}' => 'opacity: {{VALUE}}' ),
			)
		);
		$el->end_section();

		// Mask: shows the element only inside a shape (image, video, container…).
		$el->start_section( '_section_mask', array( 'label' => __( 'Mask', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_control(
			'_mask',
			array(
				'type'                 => 'select',
				'label'                => __( 'Shape', 'uncoder' ),
				'options'              => Masks::options(),
				'selectors_dictionary' => Masks::dictionary(),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'ai'                   => 'Clips the element to a shape: circle, squircle, blob, arch, hexagon, triangle, diamond, star, heart, bubble, flower, wave, or "custom" with _mask_image.',
			)
		);
		$el->add_control(
			'_mask_image',
			array(
				'type'        => 'media',
				'label'       => __( 'Mask image', 'uncoder' ),
				'description' => __( 'Opaque parts show the element; transparent parts hide it.', 'uncoder' ),
				'condition'   => array( '_mask' => 'custom' ),
				'selectors'   => array( '{{WRAPPER}}' => '-webkit-mask-image: url("{{URL}}"); mask-image: url("{{URL}}")' ),
			)
		);
		$el->add_responsive_control(
			'_mask_size',
			array(
				'type'                 => 'select',
				'label'                => __( 'Size', 'uncoder' ),
				'options'              => array(
					''        => __( 'Fit', 'uncoder' ),
					'cover'   => __( 'Fill', 'uncoder' ),
					'stretch' => __( 'Stretch', 'uncoder' ),
					'custom'  => __( 'Custom', 'uncoder' ),
				),
				'selectors_dictionary' => array(
					'cover'   => '-webkit-mask-size:cover;mask-size:cover',
					'stretch' => '-webkit-mask-size:100% 100%;mask-size:100% 100%',
					'custom'  => '',
				),
				'condition'            => array( '_mask!' => '' ),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_mask_scale',
			array(
				'type'       => 'slider',
				'label'      => __( 'Mask width', 'uncoder' ),
				'size_units' => array( '%', 'px', 'em', 'vw' ),
				'range'      => array( '%' => array( 'min' => 10, 'max' => 200 ) ),
				'condition'  => array(
					'_mask!'      => '',
					'_mask_size' => 'custom',
				),
				'selectors'  => array( '{{WRAPPER}}' => '-webkit-mask-size: {{VALUE}}; mask-size: {{VALUE}}' ),
			)
		);
		$el->add_responsive_control(
			'_mask_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Position', 'uncoder' ),
				'options'   => array(
					''              => __( 'Center', 'uncoder' ),
					'top center'    => __( 'Top', 'uncoder' ),
					'bottom center' => __( 'Bottom', 'uncoder' ),
					'center left'   => __( 'Left', 'uncoder' ),
					'center right'  => __( 'Right', 'uncoder' ),
					'top left'      => __( 'Top left', 'uncoder' ),
					'top right'     => __( 'Top right', 'uncoder' ),
					'bottom left'   => __( 'Bottom left', 'uncoder' ),
					'bottom right'  => __( 'Bottom right', 'uncoder' ),
				),
				'condition' => array( '_mask!' => '' ),
				'selectors' => array( '{{WRAPPER}}' => '-webkit-mask-position: {{VALUE}}; mask-position: {{VALUE}}' ),
			)
		);
		$el->add_control(
			'_mask_repeat',
			array(
				'type'      => 'select',
				'label'     => __( 'Repeat', 'uncoder' ),
				'options'   => array(
					''         => __( 'No repeat', 'uncoder' ),
					'repeat'   => __( 'Repeat', 'uncoder' ),
					'repeat-x' => __( 'Repeat horizontally', 'uncoder' ),
					'repeat-y' => __( 'Repeat vertically', 'uncoder' ),
					'round'    => __( 'Round', 'uncoder' ),
					'space'    => __( 'Space', 'uncoder' ),
				),
				'condition' => array( '_mask!' => '' ),
				'selectors' => array( '{{WRAPPER}}' => '-webkit-mask-repeat: {{VALUE}}; mask-repeat: {{VALUE}}' ),
			)
		);
		$el->end_section();

		// First in Behaviour: how the element is linked to and targeted (it prints no id unless one is set here).
		$el->start_section( '_section_identity', array( 'label' => __( 'Anchor & classes', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_control(
			'_css_id',
			array(
				'type'        => 'text',
				'label'       => __( 'Anchor ID', 'uncoder' ),
				'placeholder' => 'contact',
				'description' => __( 'Link to this element with #your-id (e.g. a menu item “/#contact”). Also usable as a CSS id.', 'uncoder' ),
				'ai'          => 'Anchor id without #, used for #links (e.g. "pricing" for a menu link "/#pricing"). Elements print no id attribute otherwise.',
			)
		);
		$el->add_control(
			'_css_classes',
			array(
				'type'        => 'text',
				'label'       => __( 'CSS classes', 'uncoder' ),
				'placeholder' => 'my-card is-featured',
				'description' => __( 'Extra classes for your own CSS, separated by spaces. Every element already has uncoder-{type} and uncoder-{id}.', 'uncoder' ),
			)
		);
		$el->end_section();

		$el->start_section( '_section_animations', array( 'label' => __( 'Animations', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_control(
			'_animations',
			array(
				'type'    => 'animation',
				'render'  => 'none',
				'targets' => in_array( $el->name(), self::REVEAL_WIDGETS, true ) ? Animations::TARGETS : array( 'self', 'children' ),
			)
		);
		$el->end_section();

		$el->start_section( '_section_motion', array( 'label' => __( 'Motion & effects', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_control( '_animation', array( 'type' => 'select', 'label' => __( 'Entrance animation', 'uncoder' ), 'options' => self::ANIMATIONS ) );
		$el->add_control(
			'_animation_duration',
			array(
				'type'      => 'number',
				'label'     => __( 'Duration (ms)', 'uncoder' ),
				'min'       => 100,
				'max'       => 5000,
				'condition' => array( '_animation!' => '' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-anim-duration: {{VALUE}}ms' ),
			)
		);
		$el->add_control(
			'_animation_delay',
			array(
				'type'      => 'number',
				'label'     => __( 'Delay (ms)', 'uncoder' ),
				'min'       => 0,
				'max'       => 10000,
				'condition' => array( '_animation!' => '' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-anim-delay: {{VALUE}}ms' ),
			)
		);
		if ( in_array( $el->name(), self::REVEAL_WIDGETS, true ) ) {
			self::register_reveal( $el );
		}
		$el->start_tabs( '_transform_tabs' );
		$el->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$el->add_group( '_transform', array( 'type' => 'transform', 'label' => __( 'Transform', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$el->end_tab();
		$el->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$el->add_group( '_transform_hover', array( 'type' => 'transform', 'label' => __( 'Transform on hover', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover' ) );
		$el->end_tab();
		$el->end_tabs();
		$el->add_control(
			'_transition',
			array(
				'type'      => 'number',
				'label'     => __( 'Transition (ms)', 'uncoder' ),
				'min'       => 0,
				'max'       => 3000,
				'selectors' => array( '{{WRAPPER}}' => 'transition-duration: {{VALUE}}ms; transition-property: transform, background-color, box-shadow, border-color, opacity, color' ),
			)
		);
		$el->add_control(
			'_sticky',
			array(
				'type'        => 'select',
				'label'       => __( 'Sticky', 'uncoder' ),
				'description' => __( 'In a header: Top keeps the header pinned from this row down; rows above it, like a top bar, scroll away.', 'uncoder' ),
				'options'     => array( '' => __( 'None', 'uncoder' ), 'top' => __( 'Top', 'uncoder' ), 'bottom' => __( 'Bottom', 'uncoder' ) ),
			)
		);
		$el->add_control(
			'_sticky_offset',
			array(
				'type'      => 'number',
				'label'     => __( 'Sticky offset (px)', 'uncoder' ),
				'min'       => 0,
				'max'       => 500,
				'condition' => array( '_sticky!' => '' ),
			)
		);
		$devices = array( 'desktop' => __( 'Desktop', 'uncoder' ) );
		foreach ( Breakpoints::active() as $id => $bp ) {
			$devices[ $id ] = $bp['label'];
		}
		$el->add_control(
			'_sticky_on',
			array(
				'type'        => 'multiselect',
				'label'       => __( 'Sticky on', 'uncoder' ),
				'options'     => $devices,
				'default'     => array_keys( $devices ),
				'description' => __( 'Devices where the element sticks; elsewhere it scrolls with the page.', 'uncoder' ),
				'condition'   => array( '_sticky!' => '' ),
			)
		);
		$el->add_group( '_filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$el->add_group(
			'_backdrop',
			array(
				'type'        => 'backdrop_filter',
				'label'       => __( 'Backdrop filter', 'uncoder' ),
				'description' => __( 'Blurs or tints what shows through the element (frosted glass). Give it a semi-transparent background.', 'uncoder' ),
				'selector'    => '{{WRAPPER}}',
			)
		);
		$el->end_section();

		self::register_effects( $el );

		$el->start_section( '_section_visibility', array( 'label' => __( 'Visibility', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_control( '_hide_desktop', array( 'type' => 'switch', 'label' => __( 'Hide on desktop', 'uncoder' ) ) );
		$el->add_control( '_hide_tablet', array( 'type' => 'switch', 'label' => __( 'Hide on tablet', 'uncoder' ) ) );
		$el->add_control( '_hide_mobile', array( 'type' => 'switch', 'label' => __( 'Hide on mobile', 'uncoder' ) ) );
		$el->add_control(
			'_hide_dark',
			array(
				'type'        => 'switch',
				'label'       => __( 'Hide in dark mode', 'uncoder' ),
				'description' => __( 'With dark mode on in the Design System: e.g. a dark logo for light pages and a light one for dark pages.', 'uncoder' ),
			)
		);
		$el->add_control( '_hide_light', array( 'type' => 'switch', 'label' => __( 'Hide in light mode', 'uncoder' ) ) );
		$el->end_section();

		$el->start_section( '_section_conditions', array( 'label' => __( 'Conditions', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_control(
			'_conditions',
			array(
				'type'        => 'conditions',
				'label'       => __( 'Show this element when', 'uncoder' ),
				'description' => __( 'Any set can match; every rule in a set must match. Checked on the server, so a hidden element is not in the page at all. The editor always shows it. With a page cache, user-based rules need the cache to vary by login (most do).', 'uncoder' ),
				'ai'          => 'Server-side display rules. Omit to always show. Members only: [[{"key":"login","op":"is","value":"in"}]]. Campaign banner: [[{"key":"url_param","name":"utm_campaign","op":"is","value":"spring"}]]. Office hours: [[{"key":"time","op":"from","value":"09:00"},{"key":"time","op":"until","value":"17:00"},{"key":"weekday","op":"is","value":"1,2,3,4,5"}]]. Limited offer: [[{"key":"date","op":"from","value":"2026-12-01 09:00"},{"key":"date","op":"until","value":"2026-12-24"}]].',
			)
		);
		$el->end_section();

		$el->start_section( '_section_interactions', array( 'label' => __( 'Interactions', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_control(
			'_interactions',
			array(
				'type'        => 'interactions',
				'label'       => __( 'When…', 'uncoder' ),
				'description' => __( 'Make things happen without code: toggle a class, show or hide another element, open a popup, scroll somewhere. They run on the live page, not while editing.', 'uncoder' ),
				'ai'          => 'Rows run by the front end. A menu button that opens a panel: on the button [{"trigger":"click","action":"toggle","target":"element","element":"<panel id>"}] and "_ix_hidden": true on the panel. A header that changes after scrolling: [{"trigger":"scroll","offset":80,"action":"add_class","target":"self","value":"is-scrolled"}] (the class comes off again above the offset) plus custom CSS for .is-scrolled.',
			)
		);
		$el->add_control(
			'_ix_hidden',
			array(
				'type'        => 'switch',
				'label'       => __( 'Start hidden', 'uncoder' ),
				'description' => __( 'For a box that an interaction shows (Show or Show / hide). The editor keeps it visible, dimmed.', 'uncoder' ),
			)
		);
		$el->end_section();

		$el->start_section( '_section_attributes', array( 'label' => __( 'Attributes & CSS', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_control(
			'_attributes',
			array(
				'type'        => 'textarea',
				'label'       => __( 'Custom attributes', 'uncoder' ),
				'description' => __( 'One per line: key|value. Event handlers are not allowed.', 'uncoder' ),
			)
		);
		$el->add_control(
			'_custom_css',
			array(
				'type'        => 'code',
				'language'    => 'css',
				'label'       => __( 'Custom CSS', 'uncoder' ),
				'description' => __( 'Use "selector" to target this element, e.g. selector:hover { opacity:.8 }. Each device tab adds CSS for that screen size and smaller (desktop applies everywhere).', 'uncoder' ),
				'render'      => 'css',
				'responsive'  => true,
			)
		);
		$el->end_section();
	}

	/**
	 * Scroll and pointer effects (runtime module "motion"). They use the individual translate / rotate /
	 * scale properties, so they add up with the Transform settings instead of replacing them.
	 */
	private static function register_effects( Element_Base $el ): void {
		$el->start_section( '_section_effects', array( 'label' => __( 'Scroll & mouse effects', 'uncoder' ), 'tab' => 'advanced' ) );
		$el->add_control( '_motion_heading', array( 'type' => 'heading', 'label' => __( 'While scrolling', 'uncoder' ) ) );
		$speed = array(
			'type' => 'number',
			'min'  => -10,
			'max'  => 10,
			'step' => 0.5,
		);
		$el->add_control(
			'_motion_y',
			$speed + array(
				'label'       => __( 'Vertical', 'uncoder' ),
				'description' => __( '−10 to 10. Positive moves up faster than the page, negative lags behind (parallax).', 'uncoder' ),
			)
		);
		$el->add_control(
			'_motion_x',
			$speed + array(
				'label'       => __( 'Horizontal', 'uncoder' ),
				'description' => __( 'Positive drifts right as the page scrolls down.', 'uncoder' ),
			)
		);
		$el->add_control( '_motion_rotate', $speed + array( 'label' => __( 'Rotate', 'uncoder' ) ) );
		$el->add_control(
			'_motion_scale',
			array(
				'type'    => 'select',
				'label'   => __( 'Scale', 'uncoder' ),
				'options' => self::MOTION_SCALE,
			)
		);
		$el->add_control(
			'_motion_scale_amount',
			array(
				'type'      => 'number',
				'label'     => __( 'Amount (%)', 'uncoder' ),
				'min'       => 5,
				'max'       => 100,
				'default'   => 20,
				'condition' => array( '_motion_scale!' => '' ),
			)
		);
		$fade = array(
			''       => __( 'None', 'uncoder' ),
			'in'     => __( 'In (as it enters)', 'uncoder' ),
			'out'    => __( 'Out (as it leaves)', 'uncoder' ),
			'in-out' => __( 'In and out', 'uncoder' ),
		);
		$el->add_control(
			'_motion_fade',
			array(
				'type'    => 'select',
				'label'   => __( 'Fade', 'uncoder' ),
				'options' => $fade,
			)
		);
		$el->add_control(
			'_motion_blur',
			array(
				'type'        => 'select',
				'label'       => __( 'Blur', 'uncoder' ),
				'options'     => $fade,
				'description' => __( 'Replaces the CSS filters of this element while active.', 'uncoder' ),
			)
		);
		$el->add_control(
			'_motion_start',
			array(
				'type'        => 'number',
				'label'       => __( 'Range start', 'uncoder' ),
				'min'         => 0,
				'max'         => 100,
				'placeholder' => '0',
				'description' => __( 'Percent of the trip across the screen, from entering at the bottom (0) to leaving at the top (100).', 'uncoder' ),
			)
		);
		$el->add_control(
			'_motion_end',
			array(
				'type'        => 'number',
				'label'       => __( 'Range end', 'uncoder' ),
				'min'         => 0,
				'max'         => 100,
				'placeholder' => '100',
			)
		);
		$el->add_control( '_mouse_heading', array( 'type' => 'heading', 'label' => __( 'Pointer', 'uncoder' ) ) );
		$el->add_control(
			'_motion_mouse',
			array(
				'type'    => 'select',
				'label'   => __( 'Mouse effect', 'uncoder' ),
				'options' => array(
					''      => __( 'None', 'uncoder' ),
					'track' => __( 'Follow the pointer', 'uncoder' ),
					'tilt'  => __( '3D tilt on hover', 'uncoder' ),
				),
			)
		);
		$el->add_control(
			'_motion_mouse_speed',
			$speed + array(
				'label'       => __( 'Strength', 'uncoder' ),
				'default'     => 4,
				'description' => __( 'Negative values move the other way.', 'uncoder' ),
				'condition'   => array( '_motion_mouse!' => '' ),
			)
		);
		$el->add_control(
			'_motion_devices',
			array(
				'type'        => 'multiselect',
				'label'       => __( 'Apply on', 'uncoder' ),
				'options'     => array(
					'desktop' => __( 'Desktop', 'uncoder' ),
					'tablet'  => __( 'Tablet', 'uncoder' ),
					'mobile'  => __( 'Mobile', 'uncoder' ),
				),
				'description' => __( 'Empty means every device. Effects never run for visitors who prefer reduced motion.', 'uncoder' ),
			)
		);
		$el->end_section();
	}

	/**
	 * Runtime options of the "motion" module, or null when the element has no effect.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<string,mixed>|null
	 */
	public static function motion( array $s ): ?array {
		$num = static fn( $key, $min, $max ) => isset( $s[ $key ] ) && is_numeric( $s[ $key ] ) ? max( $min, min( $max, (float) $s[ $key ] ) ) : 0.0;
		$fx  = array_filter(
			array(
				'y'      => $num( '_motion_y', -10, 10 ),
				'x'      => $num( '_motion_x', -10, 10 ),
				'rotate' => $num( '_motion_rotate', -10, 10 ),
				'scale'  => isset( self::MOTION_SCALE[ $s['_motion_scale'] ?? '' ] ) ? (string) ( $s['_motion_scale'] ?? '' ) : '',
				'fade'   => in_array( $s['_motion_fade'] ?? '', array( 'in', 'out', 'in-out' ), true ) ? $s['_motion_fade'] : '',
				'blur'   => in_array( $s['_motion_blur'] ?? '', array( 'in', 'out', 'in-out' ), true ) ? $s['_motion_blur'] : '',
				'mouse'  => in_array( $s['_motion_mouse'] ?? '', array( 'track', 'tilt' ), true ) ? $s['_motion_mouse'] : '',
			)
		);
		if ( ! $fx ) {
			return null;
		}
		if ( ! empty( $fx['scale'] ) ) {
			$fx['amount'] = ( isset( $s['_motion_scale_amount'] ) && is_numeric( $s['_motion_scale_amount'] ) ? max( 5, min( 100, (float) $s['_motion_scale_amount'] ) ) : 20 ) / 100;
		}
		if ( ! empty( $fx['mouse'] ) ) {
			$fx['strength'] = isset( $s['_motion_mouse_speed'] ) && is_numeric( $s['_motion_mouse_speed'] ) ? max( -10, min( 10, (float) $s['_motion_mouse_speed'] ) ) : 4;
		}
		$start = $num( '_motion_start', 0, 100 );
		$end   = isset( $s['_motion_end'] ) && is_numeric( $s['_motion_end'] ) ? $num( '_motion_end', 0, 100 ) : 100.0;
		if ( $start > 0 || $end < 100 ) {
			$fx['range'] = $end > $start ? array( $start, $end ) : array( 0, 100 );
		}
		$devices = array_values( array_intersect( (array) ( $s['_motion_devices'] ?? array() ), array( 'desktop', 'tablet', 'mobile' ) ) );
		if ( $devices && count( $devices ) < 3 ) {
			$fx['devices'] = $devices;
		}
		return $fx;
	}

	/**
	 * Wrapper classes / attributes derived from common settings.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array{classes:string[], attrs:array<string,mixed>}
	 */
	private static function register_reveal( Element_Base $el ): void {
		$el->add_control(
			'_reveal',
			array(
				'type'        => 'select',
				'label'       => __( 'Text reveal', 'uncoder' ),
				'description' => __( 'Animates the text in piece by piece when it scrolls into view.', 'uncoder' ),
				'options'     => array(
					''        => __( 'None', 'uncoder' ),
					'words'   => __( 'Word by word', 'uncoder' ),
					'letters' => __( 'Letter by letter', 'uncoder' ),
					'lines'   => __( 'Line by line', 'uncoder' ),
				),
			)
		);
		$el->add_control(
			'_reveal_effect',
			array(
				'type'      => 'select',
				'label'     => __( 'Reveal effect', 'uncoder' ),
				'default'   => 'fade-up',
				'options'   => self::REVEAL_EFFECTS,
				'condition' => array( '_reveal!' => '' ),
			)
		);
		$el->add_control(
			'_reveal_trigger',
			array(
				'type'        => 'select',
				'label'       => __( 'Trigger', 'uncoder' ),
				'description' => __( 'Follow the scroll: the text starts faint and lights up piece by piece as the visitor scrolls, and dims again when scrolling back.', 'uncoder' ),
				'options'     => array(
					''       => __( 'Once, when it scrolls into view', 'uncoder' ),
					'scroll' => __( 'Follow the scroll', 'uncoder' ),
				),
				'condition'   => array( '_reveal!' => '' ),
			)
		);
		$el->add_control(
			'_reveal_dim',
			array(
				'type'        => 'number',
				'label'       => __( 'Start opacity', 'uncoder' ),
				'description' => __( 'How visible the text is before it is revealed. Empty: 0, or 0.2 when following the scroll.', 'uncoder' ),
				'min'         => 0,
				'max'         => 1,
				'step'        => 0.05,
				'condition'   => array( '_reveal!' => '' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-reveal-dim: {{VALUE}}' ),
			)
		);
		$el->add_control(
			'_reveal_blur',
			array(
				'type'        => 'number',
				'label'       => __( 'Blur (px)', 'uncoder' ),
				'description' => __( 'Empty: 10, or 4 when following the scroll.', 'uncoder' ),
				'min'         => 0,
				'max'         => 40,
				'condition'   => array(
					'_reveal!'        => '',
					'_reveal_effect' => 'blur',
				),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-reveal-blur: {{VALUE}}px' ),
			)
		);
		$el->add_control(
			'_reveal_stagger',
			array(
				'type'        => 'number',
				'label'       => __( 'Stagger (ms)', 'uncoder' ),
				'description' => __( 'Pause between pieces. Empty: 60 for words, 25 for letters, 140 for lines.', 'uncoder' ),
				'min'         => 0,
				'max'         => 1000,
				'condition'   => array(
					'_reveal!'         => '',
					'_reveal_trigger!' => 'scroll',
				),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-reveal-stagger: {{VALUE}}ms' ),
			)
		);
		$el->add_control(
			'_reveal_duration',
			array(
				'type'      => 'number',
				'label'     => __( 'Reveal duration (ms)', 'uncoder' ),
				'min'       => 100,
				'max'       => 5000,
				'condition' => array( '_reveal!' => '' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-reveal-duration: {{VALUE}}ms' ),
			)
		);
		$el->add_control(
			'_reveal_delay',
			array(
				'type'      => 'number',
				'label'     => __( 'Reveal delay (ms)', 'uncoder' ),
				'min'       => 0,
				'max'       => 10000,
				'condition' => array(
					'_reveal!'         => '',
					'_reveal_trigger!' => 'scroll',
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-reveal-delay: {{VALUE}}ms' ),
			)
		);
	}

	/**
	 * Text reveal as "mode effect [scroll]" (e.g. "words fade-up", "words blur scroll"), or '' when off.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	public static function reveal( array $s ): string {
		$mode = (string) ( $s['_reveal'] ?? '' );
		if ( ! in_array( $mode, array( 'words', 'letters', 'lines' ), true ) ) {
			return '';
		}
		$effect = (string) ( $s['_reveal_effect'] ?? 'fade-up' );
		$scroll = 'scroll' === ( $s['_reveal_trigger'] ?? '' ) ? ' scroll' : '';
		return $mode . ' ' . ( isset( self::REVEAL_EFFECTS[ $effect ] ) ? $effect : 'fade-up' ) . $scroll;
	}

	/**
	 * Timeline animations of an element (sanitized again: templates may hold older data).
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<int, array<string,mixed>>
	 */
	public static function animations( array $s ): array {
		return empty( $s['_animations'] ) ? array() : (array) Animations::sanitize_list( $s['_animations'] );
	}

	public static function wrapper( array $s ): array {
		$classes = array();
		$attrs   = array();
		foreach ( Breakpoints::devices() as $device ) {
			if ( ! empty( $s[ '_hide_' . $device ] ) ) {
				$classes[] = 'uncoder-hide-' . $device;
			}
		}
		if ( ! empty( $s['_hide_dark'] ) ) {
			$classes[] = 'uncoder-hide-in-dark';
		}
		if ( ! empty( $s['_hide_light'] ) ) {
			$classes[] = 'uncoder-hide-in-light';
		}
		foreach ( (array) ( $s['_classes'] ?? array() ) as $class ) {
			$class = sanitize_key( (string) $class );
			if ( '' !== $class ) {
				$classes[] = 'uncoder-gc-' . $class;
			}
		}
		if ( ! empty( $s['_css_classes'] ) && is_string( $s['_css_classes'] ) ) {
			foreach ( preg_split( '/\s+/', $s['_css_classes'] ) as $class ) {
				$class = sanitize_html_class( $class );
				if ( '' !== $class ) {
					$classes[] = $class;
				}
			}
		}
		$modules = array();
		if ( ! empty( $s['_animation'] ) && isset( self::ANIMATIONS[ $s['_animation'] ] ) ) {
			$attrs['data-uncoder-anim'] = $s['_animation'];
			$classes[]                  = 'uncoder-anim';
			$modules[]                  = 'animations';
		}
		$animations = self::animations( $s );
		if ( $animations ) {
			$classes[] = Animations::starts_hidden( $animations ) ? 'uncoder-animate uncoder-animate--pre' : 'uncoder-animate';
			$attrs['data-uncoder-animate'] = Animations::attribute( $animations );
			$modules[]                     = 'animate';
		}
		if ( ! empty( $s['_sticky'] ) && in_array( $s['_sticky'], array( 'top', 'bottom' ), true ) ) {
			$classes[]      = 'bottom' === $s['_sticky'] ? 'uncoder-sticky uncoder-sticky--bottom' : 'uncoder-sticky';
			$attrs['style'] = '--uncoder-sticky-offset:' . absint( $s['_sticky_offset'] ?? 0 ) . 'px';
			// Devices left out of "Sticky on" scroll normally (Kit::visibility_css() ranges).
			if ( isset( $s['_sticky_on'] ) && is_array( $s['_sticky_on'] ) ) {
				foreach ( Breakpoints::devices() as $device ) {
					if ( ! in_array( $device, $s['_sticky_on'], true ) ) {
						$classes[] = 'uncoder-sticky-off-' . $device;
					}
				}
			}
			$modules[]      = 'sticky';
		}
		$reveal = self::reveal( $s );
		if ( '' !== $reveal ) {
			$attrs['data-uncoder-reveal'] = $reveal;
			$modules[]                = 'text-reveal';
		}
		if ( ! empty( $s['_ix_hidden'] ) ) {
			$classes[] = 'uncoder-ix-hidden';
		}
		if ( ! empty( $s['_interactions'] ) && is_array( $s['_interactions'] ) ) {
			$attrs['data-uncoder-ix'] = (string) wp_json_encode( array_values( $s['_interactions'] ) );
			$modules[]                = 'interactions';
		}
		$motion = self::motion( $s );
		if ( $motion ) {
			$classes[]               = 'uncoder-motion';
			$attrs['data-uncoder-motion'] = wp_json_encode( $motion );
			$modules[]               = 'motion';
		}
		if ( $modules ) {
			$attrs['data-uncoder-js'] = implode( ' ', $modules );
		}
		if ( ! empty( $s['_attributes'] ) ) {
			foreach ( Utils::parse_custom_attributes( (string) $s['_attributes'] ) as $k => $v ) {
				if ( ! isset( $attrs[ $k ] ) ) {
					$attrs[ $k ] = $v;
				}
			}
		}
		return array(
			'classes' => $classes,
			'attrs'   => $attrs,
		);
	}
}
