<?php
/**
 * Animated Headline widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Headline with a hand-drawn highlight shape or rotating words. Without JavaScript (or with reduced
 * motion) it shows the shape fully drawn / the first word.
 */
class Animated_Headline extends Widget_Base {

	/**
	 * SVG paths drawn in a 500×150 box stretched over the highlighted word.
	 */
	public const SHAPES = array(
		'underline' => array( 'M6 132 C 110 118, 250 124, 494 118' ),
		'double'    => array( 'M6 120 C 140 108, 300 114, 494 110', 'M40 142 C 170 132, 330 136, 460 132' ),
		'curly'     => array( 'M6 128 q 20 -22 40 0 t 40 0 t 40 0 t 40 0 t 40 0 t 40 0 t 40 0 t 40 0 t 40 0 t 40 0 t 40 0 t 40 0' ),
		'circle'    => array( 'M332 12 C 214 -4, 58 14, 18 62 C -14 102, 94 142, 250 142 C 404 142, 504 108, 486 64 C 470 24, 364 6, 214 16' ),
		'strike'    => array( 'M6 84 C 150 74, 330 80, 494 72' ),
		'cross'     => array( 'M18 16 L 482 134', 'M482 16 L 18 134' ),
	);

	public const EFFECTS = array( 'typing', 'clip', 'flip', 'slide', 'fade' );

	public function name(): string {
		return 'animated-headline';
	}

	public function title(): string {
		return __( 'Animated Headline', 'uncoder' );
	}

	public function icon(): string {
		return 'highlighter';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'headline', 'animated', 'rotating', 'typing', 'highlight', 'title', 'text' );
	}

	public function description(): string {
		return __( 'Heading with animated words: "highlight" draws a hand-drawn shape (underline, circle, strike…) around one word; "rotating" cycles through several words (typing, clip, flip, slide, fade).', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'animated-headline' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Headline', 'uncoder' ) ) );
		$this->add_control(
			'style',
			array(
				'type'    => 'choose',
				'label'   => __( 'Style', 'uncoder' ),
				'default' => 'highlight',
				'options' => array(
					'highlight' => array( 'label' => __( 'Highlight', 'uncoder' ), 'icon' => 'highlighter' ),
					'rotating'  => array( 'label' => __( 'Rotating', 'uncoder' ), 'icon' => 'repeat' ),
				),
			)
		);
		$this->add_control(
			'before_text',
			array(
				'type'    => 'text',
				'label'   => __( 'Before text', 'uncoder' ),
				'default' => __( 'Build websites that', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'highlighted_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Highlighted text', 'uncoder' ),
				'default'   => __( 'convert', 'uncoder' ),
				'condition' => array( 'style' => 'highlight' ),
				'dynamic'   => true,
				'inline'    => true,
			)
		);
		$this->add_control(
			'rotating_text',
			array(
				'type'        => 'textarea',
				'label'       => __( 'Rotating words', 'uncoder' ),
				'description' => __( 'One per line. The first one is shown without JavaScript.', 'uncoder' ),
				'rows'        => 4,
				'default'     => "convert\ninspire\nscale",
				'condition'   => array( 'style' => 'rotating' ),
				'ai'          => 'Newline-separated words or short phrases, e.g. "faster\nsmarter\nsimpler".',
			)
		);
		$this->add_control(
			'after_text',
			array(
				'type'    => 'text',
				'label'   => __( 'After text', 'uncoder' ),
				'default' => __( 'from day one.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'shape',
			array(
				'type'      => 'select',
				'label'     => __( 'Shape', 'uncoder' ),
				'default'   => 'underline',
				'options'   => array(
					'underline' => __( 'Underline', 'uncoder' ),
					'double'    => __( 'Double underline', 'uncoder' ),
					'curly'     => __( 'Curly underline', 'uncoder' ),
					'circle'    => __( 'Circle', 'uncoder' ),
					'strike'    => __( 'Strikethrough', 'uncoder' ),
					'cross'     => __( 'Cross out', 'uncoder' ),
				),
				'condition' => array( 'style' => 'highlight' ),
			)
		);
		$this->add_control(
			'effect',
			array(
				'type'      => 'select',
				'label'     => __( 'Animation', 'uncoder' ),
				'default'   => 'slide',
				'options'   => array(
					'typing' => __( 'Typing', 'uncoder' ),
					'clip'   => __( 'Clip', 'uncoder' ),
					'flip'   => __( 'Flip', 'uncoder' ),
					'slide'  => __( 'Slide up', 'uncoder' ),
					'fade'   => __( 'Fade', 'uncoder' ),
				),
				'condition' => array( 'style' => 'rotating' ),
			)
		);
		$this->add_control(
			'tag',
			array(
				'type'    => 'select',
				'label'   => __( 'HTML tag', 'uncoder' ),
				'default' => 'h2',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control( 'link', array( 'type' => 'url', 'label' => __( 'Link', 'uncoder' ), 'dynamic' => true ) );
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

		$this->start_section( 'content_animation', array( 'label' => __( 'Animation', 'uncoder' ) ) );
		$this->add_control(
			'display_time',
			array(
				'type'        => 'number',
				'label'       => __( 'Display time (ms)', 'uncoder' ),
				'description' => __( 'How long each word (or the drawn shape) stays before the next cycle.', 'uncoder' ),
				'default'     => 2500,
				'min'         => 500,
				'max'         => 20000,
				'step'        => 100,
			)
		);
		$this->add_control(
			'draw_duration',
			array(
				'type'      => 'number',
				'label'     => __( 'Drawing duration (ms)', 'uncoder' ),
				'default'   => 1200,
				'min'       => 100,
				'max'       => 6000,
				'step'      => 100,
				'condition' => array( 'style' => 'highlight' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-ah-draw: {{VALUE}}ms' ),
			)
		);
		$this->add_control(
			'loop',
			array(
				'type'    => 'switch',
				'label'   => __( 'Loop', 'uncoder' ),
				'default' => true,
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_headline', array( 'label' => __( 'Headline', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'text_shadow', array( 'type' => 'text_shadow', 'label' => __( 'Text shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'text_stroke_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Text stroke', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 10, 'step' => 0.5 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '-webkit-text-stroke-width: {{VALUE}}; stroke-width: {{VALUE}}' ),
				'ai'         => 'Outline around the letters, e.g. {"size":1,"unit":"px"}; with a transparent text color it gives outlined text.',
			)
		);
		$this->add_control(
			'text_stroke_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Stroke color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '-webkit-text-stroke-color: {{VALUE}}; stroke: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'text_wrap',
			array(
				'type'      => 'select',
				'label'     => __( 'Text wrap', 'uncoder' ),
				'options'   => array(
					''        => __( 'Default', 'uncoder' ),
					'balance' => __( 'Balanced', 'uncoder' ),
					'pretty'  => __( 'Pretty', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'text-wrap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_animated', array( 'label' => __( 'Animated text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'animated_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-ah__dynamic' ) );
		$this->add_control(
			'animated_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-ah__dynamic' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'animated_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-ah__dynamic' => 'background-color: {{VALUE}}; padding-inline: 0.15em; border-radius: 0.15em' ),
			)
		);
		$this->add_control( 'shape_heading', array( 'type' => 'heading', 'label' => __( 'Shape', 'uncoder' ), 'condition' => array( 'style' => 'highlight' ) ) );
		$this->add_control(
			'shape_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Shape color', 'uncoder' ),
				'condition' => array( 'style' => 'highlight' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-ah-shape: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'shape_width',
			array(
				'type'        => 'number',
				'label'       => __( 'Stroke width', 'uncoder' ),
				'description' => __( 'Relative to the text size (default 12).', 'uncoder' ),
				'min'         => 1,
				'max'         => 40,
				'step'        => 1,
				'condition'   => array( 'style' => 'highlight' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-ah-stroke: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'shape_front',
			array(
				'type'      => 'switch',
				'label'     => __( 'Draw over the text', 'uncoder' ),
				'default'   => false,
				'condition' => array( 'style' => 'highlight' ),
			)
		);
		$this->add_control(
			'cursor_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Cursor color', 'uncoder' ),
				'condition' => array(
					'style'  => 'rotating',
					'effect' => array( 'typing', 'clip' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-ah-cursor: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 * @return string[]
	 */
	private function words( array $s ): array {
		$lines = preg_split( '/\r\n|\r|\n/', (string) ( $s['rotating_text'] ?? '' ) );
		$words = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$words[] = $line;
			}
		}
		return array_slice( $words, 0, 20 );
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array(
			'data-settings' => $this->json_attr(
				array(
					'style'   => 'rotating' === ( $s['style'] ?? 'highlight' ) ? 'rotating' : 'highlight',
					'effect'  => in_array( $s['effect'] ?? 'slide', self::EFFECTS, true ) ? $s['effect'] : 'slide',
					'hold'    => max( 500, (int) ( $s['display_time'] ?? 2500 ) ),
					'draw'    => max( 100, (int) ( $s['draw_duration'] ?? 1200 ) ),
					'loop'    => ! array_key_exists( 'loop', $s ) || ! empty( $s['loop'] ),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$style  = 'rotating' === ( $s['style'] ?? 'highlight' ) ? 'rotating' : 'highlight';
		$before = trim( (string) ( $s['before_text'] ?? '' ) );
		$after  = trim( (string) ( $s['after_text'] ?? '' ) );
		$tag    = Utils::tag( $s['tag'] ?? 'h2', Utils::HEADING_TAGS, 'h2' );

		$classes = array( 'uncoder-ah', 'uncoder-ah--' . $style );
		$dynamic = '';
		if ( 'highlight' === $style ) {
			$text  = trim( (string) ( $s['highlighted_text'] ?? '' ) );
			$shape = isset( self::SHAPES[ $s['shape'] ?? '' ] ) ? $s['shape'] : 'underline';
			$classes[] = 'uncoder-ah--shape-' . $shape;
			if ( ! empty( $s['shape_front'] ) ) {
				$classes[] = 'uncoder-ah--shape-front';
			}
			if ( '' !== $text ) {
				$paths = '';
				foreach ( self::SHAPES[ $shape ] as $d ) {
					$paths .= '<path d="' . esc_attr( $d ) . '"/>';
				}
				$dynamic = '<span class="uncoder-ah__dynamic"><span class="uncoder-ah__text"' . $ctx->inline( 'highlighted_text' ) . '>' . esc_html( $text ) . '</span>'
					. '<svg class="uncoder-ah__svg" viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true" focusable="false">' . $paths . '</svg></span>';
			}
		} else {
			$words     = $this->words( $s );
			$effect    = in_array( $s['effect'] ?? 'slide', self::EFFECTS, true ) ? $s['effect'] : 'slide';
			$classes[] = 'uncoder-ah--fx-' . $effect;
			if ( $words ) {
				$items = '';
				foreach ( $words as $i => $word ) {
					$items .= '<span class="uncoder-ah__word' . ( 0 === $i ? ' uncoder-ah__word--active' : '' ) . '">' . esc_html( $word ) . '</span>';
				}
				$dynamic = '<span class="uncoder-ah__dynamic"><span class="uncoder-sr-only">' . esc_html( implode( ', ', $words ) ) . '</span>'
					. '<span class="uncoder-ah__words" aria-hidden="true">' . $items . '</span></span>';
			}
		}

		if ( '' === $before && '' === $after && '' === $dynamic && ! $ctx->editor ) {
			return;
		}

		$parts = array();
		if ( '' !== $before ) {
			$parts[] = '<span class="uncoder-ah__before"' . $ctx->inline( 'before_text' ) . '>' . esc_html( $before ) . '</span>';
		}
		if ( '' !== $dynamic ) {
			$parts[] = $dynamic;
		}
		if ( '' !== $after ) {
			$parts[] = '<span class="uncoder-ah__after"' . $ctx->inline( 'after_text' ) . '>' . esc_html( $after ) . '</span>';
		}
		$inner = implode( ' ', $parts );
		$link  = $this->link_attrs( $s['link'] ?? array() );
		if ( $link ) {
			$link['class'] = 'uncoder-ah__link';
			$inner         = '<a' . Utils::attrs( $link ) . '>' . $inner . '</a>';
		}
		echo '<' . $tag . ' class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $inner . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, escaped parts.
	}
}
