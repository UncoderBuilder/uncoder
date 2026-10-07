<?php
/**
 * Site tagline widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * The site tagline (Settings → General).
 */
class Site_Tagline extends Widget_Base {

	public function name(): string {
		return 'site-tagline';
	}

	public function title(): string {
		return __( 'Site Tagline', 'uncoder' );
	}

	public function icon(): string {
		return 'text-quote';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'tagline', 'slogan', 'description', 'site', 'motto' );
	}

	public function description(): string {
		return __( 'The site tagline from Settings → General, e.g. under the logo in a header or footer.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Tagline', 'uncoder' ) ) );
		$this->add_control(
			'tag',
			array(
				'type'    => 'select',
				'label'   => __( 'HTML tag', 'uncoder' ),
				'default' => 'p',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
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

		$this->start_section( 'style_tagline', array( 'label' => __( 'Tagline', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}' ),
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
		$this->add_group( 'text_shadow', array( 'type' => 'text_shadow', 'label' => __( 'Text shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$tagline = (string) get_bloginfo( 'description', 'display' );
		if ( '' === trim( $tagline ) ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$tagline = __( 'Your site tagline appears here', 'uncoder' );
		}
		$tag   = Utils::tag( $s['tag'] ?? 'p', Utils::HEADING_TAGS, 'p' );
		$class = 'uncoder-site-tagline' . ( 'center' === ( $s['align'] ?? '' ) ? ' uncoder-site-tagline--centered' : '' );
		echo '<' . $tag . ' class="' . esc_attr( $class ) . '">' . esc_html( $tagline ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag.
	}
}
