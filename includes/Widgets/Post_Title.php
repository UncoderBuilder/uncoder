<?php
/**
 * Post title widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Title of the current post (single templates, loop items).
 */
class Post_Title extends Widget_Base {

	public function name(): string {
		return 'post-title';
	}

	public function title(): string {
		return __( 'Post Title', 'uncoder' );
	}

	public function icon(): string {
		return 'heading-1';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'title', 'post', 'page', 'heading', 'single', 'h1' );
	}

	public function description(): string {
		return __( 'The title of the current post or page. Use it as the h1 of single templates, or as a linked h3 inside loop items.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Title', 'uncoder' ) ) );
		$this->add_control(
			'tag',
			array(
				'type'    => 'select',
				'label'   => __( 'HTML tag', 'uncoder' ),
				'default' => 'h1',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
				'ai'      => 'h1 in single templates; h2/h3 inside loop items.',
			)
		);
		$this->add_control( 'link', array( 'type' => 'switch', 'label' => __( 'Link to the post', 'uncoder' ) ) );
		$this->add_control(
			'new_tab',
			array(
				'type'      => 'switch',
				'label'     => __( 'Open in a new tab', 'uncoder' ),
				'condition' => array( 'link' => true ),
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

		$this->start_section( 'style_title', array( 'label' => __( 'Title', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
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
				'condition' => array( 'link' => true ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-title__link:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', 'ch', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'max-width: {{VALUE}}' ),
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
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$post  = Theme_Context::post( $ctx );
		$title = $post ? get_the_title( $post ) : '';
		$url   = $post ? get_permalink( $post ) : '';
		if ( '' === trim( wp_strip_all_tags( $title ) ) ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$title = esc_html( Theme_Context::sample()['title'] );
			$url   = '#';
		}
		$tag     = Utils::tag( $s['tag'] ?? 'h1', Utils::HEADING_TAGS, 'h1' );
		$title   = wp_kses( $title, Utils::kses_inline() );
		$classes = 'uncoder-post-title' . ( 'center' === ( $s['align'] ?? '' ) ? ' uncoder-post-title--centered' : '' );
		if ( ! empty( $s['link'] ) && $url ) {
			$attrs = array(
				'class' => 'uncoder-post-title__link',
				'href'  => $url,
			);
			if ( ! empty( $s['new_tab'] ) ) {
				$attrs['target'] = '_blank';
				$attrs['rel']    = 'noopener';
			}
			$title = '<a' . Utils::attrs( $attrs ) . '>' . $title . '</a>';
		}
		echo '<' . $tag . ' class="' . esc_attr( $classes ) . '">' . $title . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, kses'd title.
	}
}
