<?php
/**
 * Heading widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Title text with an HTML tag (h1–h6, p, div, span) and an optional link.
 */
class Heading extends Widget_Base {

	public const ALIGN = array(
		'left'    => array( 'label' => 'Left', 'icon' => 'align-left' ),
		'center'  => array( 'label' => 'Center', 'icon' => 'align-center' ),
		'right'   => array( 'label' => 'Right', 'icon' => 'align-right' ),
		'justify' => array( 'label' => 'Justify', 'icon' => 'align-justify' ),
	);

	public function name(): string {
		return 'heading';
	}

	public function title(): string {
		return __( 'Heading', 'uncoder' );
	}

	public function icon(): string {
		return 'heading';
	}

	public function keywords(): array {
		return array( 'title', 'headline', 'h1', 'h2', 'text' );
	}

	public function description(): string {
		return __( 'A title (h1–h6). Use exactly one h1 per page; use the text style presets for consistent sizes.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Heading', 'uncoder' ) ) );
		$this->add_control(
			'title',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Title', 'uncoder' ),
				'html'    => 'inline',
				'default' => __( 'Add your heading text here', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
				'rows'    => 2,
			)
		);
		$this->add_control( 'link', array( 'type' => 'url', 'label' => __( 'Link', 'uncoder' ), 'dynamic' => true ) );
		$this->add_control(
			'tag',
			array(
				'type'    => 'select',
				'label'   => __( 'HTML tag', 'uncoder' ),
				'default' => 'h2',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => self::ALIGN,
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Title', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group(
			'typography',
			array(
				'type'     => 'typography',
				'label'    => __( 'Typography', 'uncoder' ),
				'selector' => '{{WRAPPER}}',
			)
		);
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link hover color', 'uncoder' ),
				'condition' => array( 'link.url!' => '' ),
				'selectors' => array( '{{WRAPPER}} a:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'highlight_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Highlight color', 'uncoder' ),
				'description' => __( 'Colors text wrapped in <mark> or <strong>.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}} :is(mark, strong)' => 'color: {{VALUE}}; background: none' ),
			)
		);
		// A marker box behind the highlighted words (after the color, whose rule clears the background).
		$this->add_control(
			'highlight_background',
			array(
				'type'        => 'color',
				'label'       => __( 'Highlight background', 'uncoder' ),
				'description' => __( 'A box behind the <mark> or <strong> words.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}} :is(mark, strong)' => 'background: {{VALUE}}; -webkit-box-decoration-break: clone; box-decoration-break: clone' ),
			)
		);
		$this->add_responsive_control(
			'highlight_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Highlight padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'highlight_background!' => '' ),
				'selectors'  => array( '{{WRAPPER}} :is(mark, strong)' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'highlight_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Highlight radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'highlight_background!' => '' ),
				'selectors'  => array( '{{WRAPPER}} :is(mark, strong)' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', 'ch', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'max-width: {{VALUE}}' ),
				'ai'         => 'Limit line length, e.g. {"size":18,"unit":"ch"}. Combine with align center for centred titles.',
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
				'options'   => array( '' => __( 'Default', 'uncoder' ), 'balance' => __( 'Balanced', 'uncoder' ), 'pretty' => __( 'Pretty', 'uncoder' ) ),
				'selectors' => array( '{{WRAPPER}}' => 'text-wrap: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$tag   = Utils::tag( $s['tag'] ?? 'h2', Utils::HEADING_TAGS, 'h2' );
		$title = $this->inline_html( $s['title'] ?? '' );
		if ( '' === trim( wp_strip_all_tags( $title ) ) && ! $ctx->editor ) {
			return;
		}
		$link = $this->link_attrs( $s['link'] ?? array() );
		$max  = ! empty( $s['max_width']['size'] ) && in_array( $s['align'] ?? '', array( 'center' ), true ) ? ' uncoder-heading--centered' : '';
		echo '<' . $tag . ' class="uncoder-heading' . esc_attr( $max ) . '"' . ( $link ? '' : $ctx->inline( 'title' ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is allow-listed, inline() is escaped.
		if ( $link ) {
			echo '<a' . Utils::attrs( $link ) . $ctx->inline( 'title' ) . '>' . $title . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes; $title is kses'd.
		} else {
			echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
		}
		echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
