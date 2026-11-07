<?php
/**
 * Flip Box widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Icons;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Two-sided card that flips (or slides, fades, zooms) on hover, keyboard focus and tap.
 */
class Flip_Box extends Widget_Base {

	public const EFFECTS = array( 'flip-x', 'flip-y', 'slide', 'fade', 'zoom', 'circle' );

	/** Where the circle reveal starts. */
	public const ORIGINS = array(
		'top-left'     => '0% 0%',
		'top-right'    => '100% 0%',
		'bottom-left'  => '0% 100%',
		'bottom-right' => '100% 100%',
		'center'       => '50% 50%',
	);

	public function name(): string {
		return 'flip-box';
	}

	public function title(): string {
		return __( 'Flip Box', 'uncoder' );
	}

	public function icon(): string {
		return 'rotate-3d';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'flip', 'card', 'hover', 'reveal', 'box', '3d' );
	}

	public function description(): string {
		return __( 'Card with a front (icon or image, title, text, a hint) and a back (title, text, a checklist, button) revealed on hover, keyboard focus or tap. Effects: flip horizontal/vertical, slide, fade, zoom, circle reveal.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'flip-box' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content_front', array( 'label' => __( 'Front', 'uncoder' ) ) );
		$this->add_control(
			'front_media',
			array(
				'type'    => 'choose',
				'label'   => __( 'Graphic', 'uncoder' ),
				'default' => 'icon',
				'options' => array(
					'none'  => array( 'label' => __( 'None', 'uncoder' ), 'icon' => 'ban' ),
					'icon'  => array( 'label' => __( 'Icon', 'uncoder' ), 'icon' => 'star' ),
					'image' => array( 'label' => __( 'Image', 'uncoder' ), 'icon' => 'image' ),
				),
			)
		);
		$this->add_control(
			'front_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'rocket' ),
				'condition' => array( 'front_media' => 'icon' ),
			)
		);
		$this->add_control(
			'front_image',
			array(
				'type'      => 'media',
				'label'     => __( 'Image', 'uncoder' ),
				'default'   => array( 'id' => 0, 'url' => '' ),
				'condition' => array( 'front_media' => 'image' ),
				'dynamic'   => true,
			)
		);
		$this->add_control(
			'front_title',
			array(
				'type'    => 'text',
				'label'   => __( 'Title', 'uncoder' ),
				'default' => __( 'Fast onboarding', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'front_description',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Description', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 3,
				'default' => __( 'Go from sign-up to your first live page in under an hour.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'front_hint',
			array(
				'type'        => 'text',
				'label'       => __( 'Hint', 'uncoder' ),
				'placeholder' => __( 'Tap for more', 'uncoder' ),
				'description' => __( 'A short line under the text that says there is more on the back.', 'uncoder' ),
				'inline'      => true,
			)
		);
		$this->add_control(
			'front_hint_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Hint icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'arrow-right' ),
				'condition' => array( 'front_hint!' => '' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_back', array( 'label' => __( 'Back', 'uncoder' ) ) );
		$this->add_control(
			'back_title',
			array(
				'type'    => 'text',
				'label'   => __( 'Title', 'uncoder' ),
				'default' => __( 'Guided setup', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'back_description',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Description', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 3,
				'default' => __( 'Import your content, pick a starter layout and follow the checklist to launch.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'back_list_title',
			array(
				'type'        => 'text',
				'label'       => __( 'List title', 'uncoder' ),
				'placeholder' => __( 'What is included', 'uncoder' ),
				'inline'      => true,
			)
		);
		$this->add_control(
			'back_list',
			array(
				'type'        => 'textarea',
				'label'       => __( 'List', 'uncoder' ),
				'rows'        => 4,
				'description' => __( 'One item per line, each with a tick.', 'uncoder' ),
				'ai'          => 'Checklist on the back, one item per line ("One-to-one coaching\nProgress tracking").',
			)
		);
		$this->add_control(
			'back_list_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'List icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'check' ),
				'condition' => array( 'back_list!' => '' ),
			)
		);
		$this->add_control(
			'button_text',
			array(
				'type'    => 'text',
				'label'   => __( 'Button text', 'uncoder' ),
				'default' => __( 'Start free trial', 'uncoder' ),
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
				'ai'      => 'Without a link the button is hidden and the box itself becomes focusable so keyboard users can reveal the back.',
			)
		);
		$this->add_control(
			'button_variant',
			array(
				'type'    => 'select',
				'label'   => __( 'Button style', 'uncoder' ),
				'default' => 'primary',
				'options' => Call_To_Action::variant_options(),
			)
		);
		$this->add_control(
			'button_size',
			array(
				'type'    => 'choose',
				'label'   => __( 'Button size', 'uncoder' ),
				'default' => 'md',
				'options' => Call_To_Action::size_options(),
			)
		);
		$this->end_section();

		$this->start_section( 'content_effect', array( 'label' => __( 'Effect', 'uncoder' ) ) );
		$this->add_control(
			'effect',
			array(
				'type'    => 'select',
				'label'   => __( 'Effect', 'uncoder' ),
				'default' => 'flip-x',
				'options' => array(
					'flip-x' => __( 'Flip horizontal', 'uncoder' ),
					'flip-y' => __( 'Flip vertical', 'uncoder' ),
					'slide'  => __( 'Slide', 'uncoder' ),
					'fade'   => __( 'Fade', 'uncoder' ),
					'zoom'   => __( 'Zoom', 'uncoder' ),
					'circle' => __( 'Circle reveal', 'uncoder' ),
				),
				'ai'      => '"circle": the back grows out of a corner (reveal_from) as a circle over the front.',
			)
		);
		$this->add_control(
			'reveal_from',
			array(
				'type'                 => 'select',
				'label'                => __( 'Reveal from', 'uncoder' ),
				'default'              => 'top-left',
				'options'              => array(
					'top-left'     => __( 'Top left corner', 'uncoder' ),
					'top-right'    => __( 'Top right corner', 'uncoder' ),
					'bottom-left'  => __( 'Bottom left corner', 'uncoder' ),
					'bottom-right' => __( 'Bottom right corner', 'uncoder' ),
					'center'       => __( 'The middle', 'uncoder' ),
				),
				'selectors_dictionary' => self::ORIGINS,
				'selectors'            => array( '{{WRAPPER}}' => '--uncoder-flip-origin: {{VALUE}}' ),
				'condition'            => array( 'effect' => 'circle' ),
			)
		);
		$this->add_control(
			'slide_direction',
			array(
				'type'      => 'choose',
				'label'     => __( 'Slide from', 'uncoder' ),
				'default'   => 'bottom',
				'options'   => array(
					'bottom' => array( 'label' => __( 'Bottom', 'uncoder' ), 'icon' => 'arrow-up' ),
					'top'    => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'arrow-down' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'arrow-left' ),
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'arrow-right' ),
				),
				'condition' => array( 'effect' => 'slide' ),
			)
		);
		$this->add_control(
			'depth',
			array(
				'type'        => 'switch',
				'label'       => __( '3D depth', 'uncoder' ),
				'description' => __( 'Lifts the content off the card while it flips.', 'uncoder' ),
				'default'     => false,
				'condition'   => array( 'effect' => array( 'flip-x', 'flip-y' ) ),
			)
		);
		$this->add_control(
			'duration',
			array(
				'type'      => 'number',
				'label'     => __( 'Duration (ms)', 'uncoder' ),
				'min'       => 100,
				'max'       => 3000,
				'step'      => 50,
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-flip-duration: {{VALUE}}ms' ),
			)
		);
		$this->add_control(
			'trigger',
			array(
				'type'        => 'select',
				'label'       => __( 'Turn the card', 'uncoder' ),
				'description' => __( 'With a button, only a press on + turns the card (and − turns it back): nothing flips by accident under the pointer.', 'uncoder' ),
				'options'     => array(
					''       => __( 'On hover, focus or tap', 'uncoder' ),
					'button' => __( 'With a round button', 'uncoder' ),
				),
				'ai'          => 'trigger "button": a round + button in the bottom corner turns the card, − turns it back (Framer "Toggle details" cards). Style it with toggle_size, toggle_background, toggle_color, toggle_offset.',
			)
		);
		$this->add_control(
			'toggle_open_label',
			array(
				'type'        => 'text',
				'label'       => __( 'Button label: show', 'uncoder' ),
				'placeholder' => __( 'Show details', 'uncoder' ),
				'description' => __( 'Read by screen readers with the front title.', 'uncoder' ),
				'condition'   => array( 'trigger' => 'button' ),
			)
		);
		$this->add_control(
			'toggle_close_label',
			array(
				'type'        => 'text',
				'label'       => __( 'Button label: hide', 'uncoder' ),
				'placeholder' => __( 'Hide details', 'uncoder' ),
				'condition'   => array( 'trigger' => 'button' ),
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
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'rem', 'em' ),
				'range'      => array( 'px' => array( 'min' => 120, 'max' => 900 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-flip-h: {{VALUE}}' ),
				'ai'         => 'Both faces share this height; keep text short enough to fit (default 320px), or turn on fit for a height that follows the content.',
			)
		);
		$this->add_control(
			'fit',
			array(
				'type'        => 'switch',
				'label'       => __( 'Fit the taller side', 'uncoder' ),
				'description' => __( 'The card is as tall as its longer side, so no text is cut off. Height becomes the least height.', 'uncoder' ),
				'default'     => false,
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-flip-box__face, {{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-flip-box__face' ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-flip-box__face' ) );
		$this->add_control(
			'focus_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Focus ring color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-flip-focus: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->register_face_style( 'front', __( 'Front', 'uncoder' ) );
		$this->register_face_style( 'back', __( 'Back', 'uncoder' ) );

		$this->start_section( 'style_toggle', array( 'label' => __( 'Round button', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'trigger' => 'button' ) ) );
		$this->add_responsive_control(
			'toggle_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 96 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-flip-toggle-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'toggle_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-flip-toggle-icon: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'toggle_offset',
			array(
				'type'        => 'slider',
				'label'       => __( 'Distance from the corner', 'uncoder' ),
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-flip-toggle-offset: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'toggle_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-flip-toggle-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'toggle_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-flip-toggle-color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_button', array( 'label' => __( 'Button', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'button_text!' => '' ) ) );
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-flip-box__button' ) );
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( 'button_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__button' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__button' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__button' => 'box-shadow: inset 0 0 0 1.5px {{VALUE}}' ) ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( 'button_hover_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__button:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__button:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__button:is(:hover, :focus-visible)' => 'box-shadow: inset 0 0 0 1.5px {{VALUE}}' ) ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-flip-box__button' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing above', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-flip-box__actions' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Style controls shared by both faces.
	 */
	private function register_face_style( string $face, string $label ): void {
		$sel = '{{WRAPPER}} .uncoder-flip-box__' . $face;
		$this->start_section( 'style_' . $face, array( 'label' => $label, 'tab' => 'style' ) );
		$this->add_group(
			$face . '_background',
			array(
				'type'     => 'background',
				'label'    => __( 'Background', 'uncoder' ),
				'selector' => $sel,
				'ai'       => 'front defaults to the surface colour, back to the secondary colour. A background image works well on the front with an overlay.',
			)
		);
		$this->add_control(
			$face . '_overlay',
			array(
				'type'        => 'color',
				'label'       => __( 'Overlay', 'uncoder' ),
				'description' => __( 'Tints a background image for legible text.', 'uncoder' ),
				'selectors'   => array( $sel => '--uncoder-flip-overlay: {{VALUE}}' ),
			)
		);
		$this->add_control(
			$face . '_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( $sel => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			$face . '_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( $sel => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			$face . '_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => '--uncoder-flip-align:flex-start;--uncoder-flip-text:start',
					'center' => '--uncoder-flip-align:center;--uncoder-flip-text:center',
					'right'  => '--uncoder-flip-align:flex-end;--uncoder-flip-text:end',
				),
				'selectors'            => array( $sel => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			$face . '_valign',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Vertical position', 'uncoder' ),
				'options'              => array(
					'top'    => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'align-vertical-justify-start' ),
					'middle' => array( 'label' => __( 'Middle', 'uncoder' ), 'icon' => 'align-vertical-justify-center' ),
					'bottom' => array( 'label' => __( 'Bottom', 'uncoder' ), 'icon' => 'align-vertical-justify-end' ),
				),
				'selectors_dictionary' => array(
					'top'    => '--uncoder-flip-justify:flex-start',
					'middle' => '--uncoder-flip-justify:center',
					'bottom' => '--uncoder-flip-justify:flex-end',
				),
				'selectors'            => array( $sel => '{{VALUE}}' ),
			)
		);
		if ( 'front' === $face ) {
			$this->add_control( 'front_media_heading', array( 'type' => 'heading', 'label' => __( 'Icon / image', 'uncoder' ), 'condition' => array( 'front_media!' => 'none' ) ) );
			$this->add_control(
				'icon_color',
				array(
					'type'      => 'color',
					'label'     => __( 'Icon color', 'uncoder' ),
					'condition' => array( 'front_media' => 'icon' ),
					'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__icon' => 'color: {{VALUE}}' ),
				)
			);
			$this->add_control(
				'icon_background',
				array(
					'type'      => 'color',
					'label'     => __( 'Icon background', 'uncoder' ),
					'condition' => array( 'front_media' => 'icon' ),
					'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__icon' => 'background-color: {{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				'icon_size',
				array(
					'type'       => 'slider',
					'label'      => __( 'Icon size', 'uncoder' ),
					'size_units' => array( 'px', 'em', 'rem' ),
					'range'      => array( 'px' => array( 'min' => 12, 'max' => 160 ) ),
					'condition'  => array( 'front_media' => 'icon' ),
					'selectors'  => array( '{{WRAPPER}} .uncoder-flip-box__icon' => 'font-size: {{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				'image_width',
				array(
					'type'       => 'slider',
					'label'      => __( 'Image width', 'uncoder' ),
					'size_units' => array( 'px', '%', 'rem' ),
					'condition'  => array( 'front_media' => 'image' ),
					'selectors'  => array( '{{WRAPPER}} .uncoder-flip-box__image' => 'width: {{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				'image_radius',
				array(
					'type'       => 'dimensions',
					'label'      => __( 'Image radius', 'uncoder' ),
					'size_units' => array( 'px', '%' ),
					'condition'  => array( 'front_media' => 'image' ),
					'selectors'  => array( '{{WRAPPER}} .uncoder-flip-box__image' => 'border-radius: {{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				'media_spacing',
				array(
					'type'       => 'slider',
					'label'      => __( 'Spacing below', 'uncoder' ),
					'size_units' => array( 'px', 'em', 'rem' ),
					'condition'  => array( 'front_media!' => 'none' ),
					'selectors'  => array( '{{WRAPPER}} .uncoder-flip-box__graphic' => 'margin-bottom: {{VALUE}}' ),
				)
			);
		}
		$this->add_control( $face . '_title_heading', array( 'type' => 'heading', 'label' => __( 'Title', 'uncoder' ) ) );
		$this->add_group( $face . '_title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $sel . ' .uncoder-flip-box__title' ) );
		$this->add_control(
			$face . '_title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( $sel . ' .uncoder-flip-box__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			$face . '_title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( $sel . ' .uncoder-flip-box__title' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_control( $face . '_description_heading', array( 'type' => 'heading', 'label' => __( 'Description', 'uncoder' ) ) );
		$this->add_group( $face . '_description_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $sel . ' .uncoder-flip-box__description' ) );
		$this->add_control(
			$face . '_description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( $sel . ' .uncoder-flip-box__description' => 'color: {{VALUE}}' ),
			)
		);
		if ( 'front' === $face ) {
			$this->add_control( 'hint_heading', array( 'type' => 'heading', 'label' => __( 'Hint', 'uncoder' ), 'condition' => array( 'front_hint!' => '' ) ) );
			$this->add_group( 'hint_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-flip-box__hint', 'condition' => array( 'front_hint!' => '' ) ) );
			$this->add_control(
				'hint_color',
				array(
					'type'      => 'color',
					'label'     => __( 'Color', 'uncoder' ),
					'condition' => array( 'front_hint!' => '' ),
					'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__hint' => 'color: {{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				'hint_spacing',
				array(
					'type'       => 'slider',
					'label'      => __( 'Spacing above', 'uncoder' ),
					'size_units' => array( 'px', 'em', 'rem' ),
					'condition'  => array( 'front_hint!' => '' ),
					'selectors'  => array( '{{WRAPPER}} .uncoder-flip-box__hint' => 'margin-top: {{VALUE}}' ),
				)
			);
		} else {
			$this->add_control( 'list_heading', array( 'type' => 'heading', 'label' => __( 'List', 'uncoder' ), 'condition' => array( 'back_list!' => '' ) ) );
			$this->add_group( 'list_title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-flip-box__list-title', 'condition' => array( 'back_list!' => '' ) ) );
			$this->add_control(
				'list_title_color',
				array(
					'type'      => 'color',
					'label'     => __( 'Title color', 'uncoder' ),
					'condition' => array( 'back_list!' => '' ),
					'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__list-title' => 'color: {{VALUE}}' ),
				)
			);
			$this->add_group( 'list_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-flip-box__list', 'condition' => array( 'back_list!' => '' ) ) );
			$this->add_control(
				'list_color',
				array(
					'type'      => 'color',
					'label'     => __( 'Color', 'uncoder' ),
					'condition' => array( 'back_list!' => '' ),
					'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__list' => 'color: {{VALUE}}' ),
				)
			);
			$this->add_control(
				'list_icon_color',
				array(
					'type'      => 'color',
					'label'     => __( 'Icon color', 'uncoder' ),
					'condition' => array( 'back_list!' => '' ),
					'selectors' => array( '{{WRAPPER}} .uncoder-flip-box__list-icon' => 'color: {{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				'list_gap',
				array(
					'type'       => 'slider',
					'label'      => __( 'Space between items', 'uncoder' ),
					'size_units' => array( 'px', 'em', 'rem' ),
					'condition'  => array( 'back_list!' => '' ),
					'selectors'  => array( '{{WRAPPER}}' => '--uncoder-flip-list-gap: {{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				'list_spacing',
				array(
					'type'       => 'slider',
					'label'      => __( 'Spacing above', 'uncoder' ),
					'size_units' => array( 'px', 'em', 'rem' ),
					'condition'  => array( 'back_list!' => '' ),
					'selectors'  => array( '{{WRAPPER}} .uncoder-flip-box__checklist' => 'margin-top: {{VALUE}}' ),
				)
			);
		}
		$this->end_section();
	}

	/**
	 * Title + description markup of a face.
	 */
	private function face_text( string $face, array $s, string $tag, Render_Context $ctx ): string {
		$html  = '';
		$title = trim( (string) ( $s[ $face . '_title' ] ?? '' ) );
		$desc  = $this->inline_html( $s[ $face . '_description' ] ?? '' );
		if ( '' !== $title || $ctx->editor ) {
			$html .= '<' . $tag . ' class="uncoder-flip-box__title"' . $ctx->inline( $face . '_title' ) . '>' . esc_html( $title ) . '</' . $tag . '>';
		}
		if ( '' !== trim( wp_strip_all_tags( $desc ) ) ) {
			$html .= '<p class="uncoder-flip-box__description"' . $ctx->inline( $face . '_description' ) . '>' . $desc . '</p>';
		}
		if ( 'front' === $face ) {
			$hint = trim( (string) ( $s['front_hint'] ?? '' ) );
			if ( '' !== $hint ) {
				$icon  = $this->has_icon( $s['front_hint_icon'] ?? null ) ? '<span class="uncoder-flip-box__hint-icon" aria-hidden="true">' . $this->render_icon( $s['front_hint_icon'] ) . '</span>' : '';
				$html .= '<p class="uncoder-flip-box__hint"><span' . $ctx->inline( 'front_hint' ) . '>' . esc_html( $hint ) . '</span>' . $icon . '</p>';
			}
			return $html;
		}
		$items = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $s['back_list'] ?? '' ) ) ), 'strlen' ) );
		if ( $items ) {
			$title = trim( (string) ( $s['back_list_title'] ?? '' ) );
			$icon  = $this->has_icon( $s['back_list_icon'] ?? null ) ? '<span class="uncoder-flip-box__list-icon" aria-hidden="true">' . $this->render_icon( $s['back_list_icon'] ) . '</span>' : '';
			$html .= '<div class="uncoder-flip-box__checklist">';
			if ( '' !== $title ) {
				$html .= '<p class="uncoder-flip-box__list-title"' . $ctx->inline( 'back_list_title' ) . '>' . esc_html( $title ) . '</p>';
			}
			$html .= '<ul class="uncoder-flip-box__list">';
			foreach ( $items as $item ) {
				$html .= '<li>' . $icon . '<span>' . esc_html( $item ) . '</span></li>';
			}
			$html .= '</ul></div>';
		}
		return $html;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$effect = in_array( $s['effect'] ?? 'flip-x', self::EFFECTS, true ) ? $s['effect'] : 'flip-x';
		$tag    = Utils::tag( $s['title_tag'] ?? 'h3', Utils::HEADING_TAGS, 'h3' );

		$classes = array( 'uncoder-flip-box', 'uncoder-flip-box--' . $effect );
		if ( 'slide' === $effect ) {
			$dir       = in_array( $s['slide_direction'] ?? 'bottom', array( 'bottom', 'top', 'left', 'right' ), true ) ? $s['slide_direction'] : 'bottom';
			$classes[] = 'uncoder-flip-box--from-' . $dir;
		}
		if ( ! empty( $s['depth'] ) && in_array( $effect, array( 'flip-x', 'flip-y' ), true ) ) {
			$classes[] = 'uncoder-flip-box--3d';
		}
		if ( ! empty( $s['fit'] ) ) {
			$classes[] = 'uncoder-flip-box--fit';
		}
		$with_button = 'button' === ( $s['trigger'] ?? '' );
		if ( $with_button ) {
			$classes[] = 'uncoder-flip-box--button';
		}

		// Front graphic.
		$graphic = '';
		$media   = (string) ( $s['front_media'] ?? 'icon' );
		if ( 'icon' === $media && $this->has_icon( $s['front_icon'] ?? null ) ) {
			$graphic = '<span class="uncoder-flip-box__graphic uncoder-flip-box__icon">' . $this->render_icon( $s['front_icon'] ) . '</span>';
		} elseif ( 'image' === $media ) {
			$img = $this->image( $s['front_image'] ?? array(), 'medium_large', array( 'class' => 'uncoder-flip-box__image' ) );
			if ( '' === $img && $ctx->editor ) {
				$img = '<img class="uncoder-flip-box__image" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
			}
			$graphic = '' !== $img ? '<div class="uncoder-flip-box__graphic">' . $img . '</div>' : '';
		}

		// Back button.
		$button = '';
		$text   = trim( (string) ( $s['button_text'] ?? '' ) );
		$link   = $this->link_attrs( $s['link'] ?? array() );
		if ( '' !== $text && $link ) {
			$variant        = in_array( $s['button_variant'] ?? 'primary', Call_To_Action::VARIANTS, true ) ? $s['button_variant'] : 'primary';
			$size           = in_array( $s['button_size'] ?? 'md', Call_To_Action::SIZES, true ) ? $s['button_size'] : 'md';
			$link['class']  = array( 'uncoder-btn', 'uncoder-btn--' . $variant, 'uncoder-btn--' . $size, 'uncoder-flip-box__button' );
			$button         = '<div class="uncoder-flip-box__actions"><a' . Utils::attrs( $link ) . '><span class="uncoder-btn__text"' . $ctx->inline( 'button_text' ) . '>' . esc_html( $text ) . '</span></a></div>';
		}

		$box_attrs = array( 'class' => $classes );
		// With the round button, the buttons are what keyboards reach; the card itself is not focusable.
		$toggles = array(
			'front' => '',
			'back'  => '',
		);
		if ( $with_button ) {
			$front_title = trim( (string) ( $s['front_title'] ?? '' ) );
			$name        = static fn( string $label ): string => '' !== $front_title ? $label . ': ' . $front_title : $label;
			$open        = trim( (string) ( $s['toggle_open_label'] ?? '' ) );
			$close       = trim( (string) ( $s['toggle_close_label'] ?? '' ) );
			$toggles     = array(
				'front' => '<button type="button" class="uncoder-flip-box__toggle" data-flip="open" aria-expanded="false" aria-label="' . esc_attr( $name( '' !== $open ? $open : __( 'Show details', 'uncoder' ) ) ) . '">' . Icons::render( 'plus', array( 'aria-hidden' => 'true' ) ) . '</button>',
				'back'  => '<button type="button" class="uncoder-flip-box__toggle" data-flip="close" aria-label="' . esc_attr( $name( '' !== $close ? $close : __( 'Hide details', 'uncoder' ) ) ) . '">' . Icons::render( 'minus', array( 'aria-hidden' => 'true' ) ) . '</button>',
			);
		} elseif ( '' === $button ) {
			// Nothing focusable on the back: let keyboard users focus the card itself to reveal it.
			$box_attrs['tabindex'] = '0';
			$box_attrs['role']     = 'group';
			$front_title           = trim( (string) ( $s['front_title'] ?? '' ) );
			if ( '' !== $front_title ) {
				$box_attrs['aria-label'] = $front_title;
			}
		}

		echo '<div' . Utils::attrs( $box_attrs ) . '><div class="uncoder-flip-box__inner">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		echo '<div class="uncoder-flip-box__face uncoder-flip-box__front"><div class="uncoder-flip-box__content">';
		echo $graphic . $this->face_text( 'front', $s, $tag, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '</div>' . $toggles['front'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '<div class="uncoder-flip-box__face uncoder-flip-box__back"' . ( $with_button && ! $ctx->editor ? ' inert' : '' ) . '><div class="uncoder-flip-box__content">';
		echo $this->face_text( 'back', $s, $tag, $ctx ) . $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '</div>' . $toggles['back'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '</div></div>';
	}
}
