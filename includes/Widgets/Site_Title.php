<?php
/**
 * Site title widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * The site name (Settings → General) as text, optionally linked to the home page.
 */
class Site_Title extends Widget_Base {

	public function name(): string {
		return 'site-title';
	}

	public function title(): string {
		return __( 'Site Title', 'uncoder' );
	}

	public function icon(): string {
		return 'heading';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'site', 'title', 'name', 'brand', 'header' );
	}

	public function description(): string {
		return __( 'The site name from Settings → General, linked to the home page. Use it in headers when there is no logo image.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Site title', 'uncoder' ) ) );
		$this->add_control(
			'tag',
			array(
				'type'    => 'select',
				'label'   => __( 'HTML tag', 'uncoder' ),
				'default' => 'p',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
				'ai'      => 'Keep "p" in headers so the page keeps a single h1.',
			)
		);
		$this->add_control(
			'link_home',
			array(
				'type'    => 'switch',
				'label'   => __( 'Link to home page', 'uncoder' ),
				'default' => true,
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
				'label'     => __( 'Hover color', 'uncoder' ),
				'condition' => array( 'link_home' => true ),
				'selectors' => array( '{{WRAPPER}} .uncoder-site-title__link:hover' => 'color: {{VALUE}}' ),
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
		$name = (string) get_bloginfo( 'name', 'display' );
		if ( '' === trim( $name ) ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$name = __( 'Site title', 'uncoder' );
		}
		$tag  = Utils::tag( $s['tag'] ?? 'p', Utils::HEADING_TAGS, 'p' );
		$text = esc_html( $name );
		if ( ! empty( $s['link_home'] ) ) {
			$attrs = array(
				'class' => 'uncoder-site-title__link',
				'href'  => home_url( '/' ),
				'rel'   => 'home',
			);
			if ( ! $ctx->editor && is_front_page() && ! is_paged() ) {
				$attrs['aria-current'] = 'page';
			}
			$text = '<a' . Utils::attrs( $attrs ) . '>' . $text . '</a>';
		}
		echo '<' . $tag . ' class="uncoder-site-title">' . $text . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, escaped parts.
	}
}
