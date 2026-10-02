<?php
/**
 * Flip Box widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Two-sided card that flips (or slides, fades, zooms) on hover, keyboard focus and tap.
 */
class Flip_Box extends Widget_Base {

	public const EFFECTS = array( 'flip-x', 'flip-y', 'slide', 'fade', 'zoom' );

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
		return __( 'Card with a front (icon or image, title, text) and a back (title, text, button) revealed on hover, keyboard focus or tap. Effects: flip horizontal/vertical, slide, fade, zoom.', 'uncoder' );
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
				),
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
				'ai'         => 'Both faces share this height; keep text short enough to fit (default 320px).',
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
		if ( '' === $button ) {
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
		echo '</div></div>';
		echo '<div class="uncoder-flip-box__face uncoder-flip-box__back"><div class="uncoder-flip-box__content">';
		echo $this->face_text( 'back', $s, $tag, $ctx ) . $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '</div></div>';
		echo '</div></div>';
	}
}
