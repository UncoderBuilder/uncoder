<?php
/**
 * Archive description widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Description of the current term, author (biography) or post type archive.
 */
class Archive_Description extends Widget_Base {

	public function name(): string {
		return 'archive-description';
	}

	public function title(): string {
		return __( 'Archive Description', 'uncoder' );
	}

	public function icon(): string {
		return 'text-quote';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'archive', 'description', 'category', 'term', 'intro' );
	}

	public function description(): string {
		return __( 'The description of the current category, tag, taxonomy term, author or post type archive. For archive templates.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Description', 'uncoder' ) ) );
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

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
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
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} a' => 'color: {{VALUE}}' ),
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
		$this->add_responsive_control(
			'paragraph_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Paragraph spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} > * + *' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$html = '';
		if ( ! $ctx->editor && ( is_archive() || ( is_home() && ! is_front_page() ) ) ) {
			$html = (string) get_the_archive_description();
		}
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$html = '<p>' . esc_html( Theme_Context::sample()['desc'] ) . '</p>';
		}
		if ( false === strpos( $html, '<p' ) ) {
			$html = wpautop( $html );
		}
		$class = 'uncoder-archive-description' . ( 'center' === ( $s['align'] ?? '' ) ? ' uncoder-archive-description--centered' : '' );
		echo '<div class="' . esc_attr( $class ) . '">' . wp_kses_post( $html ) . '</div>';
	}
}
