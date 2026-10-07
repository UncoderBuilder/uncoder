<?php
/**
 * Blockquote widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A pull quote with an author and an optional source, in three visual styles.
 */
class Blockquote extends Widget_Base {

	public function name(): string {
		return 'blockquote';
	}

	public function title(): string {
		return __( 'Blockquote', 'uncoder' );
	}

	public function icon(): string {
		return 'quote';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'quote', 'blockquote', 'pull quote', 'citation', 'cite' );
	}

	public function description(): string {
		return __( 'A highlighted quotation with its author and source. Styles: side border, large quotation mark or boxed.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Blockquote', 'uncoder' ) ) );
		$this->add_control(
			'quote',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Quote', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 4,
				'default' => __( 'Simple things done well will always outlast clever things done halfway.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'citation',
			array(
				'type'    => 'text',
				'label'   => __( 'Author', 'uncoder' ),
				'default' => __( 'Jordan Lee', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'source',
			array(
				'type'        => 'text',
				'label'       => __( 'Source', 'uncoder' ),
				'placeholder' => __( 'Book, article or talk title', 'uncoder' ),
				'dynamic'     => true,
				'inline'      => true,
			)
		);
		$this->add_control(
			'link',
			array(
				'type'        => 'url',
				'label'       => __( 'Source URL', 'uncoder' ),
				'description' => __( 'Links the source and is stored as the quote’s cite attribute.', 'uncoder' ),
				'dynamic'     => true,
			)
		);
		$this->add_control(
			'variant',
			array(
				'type'    => 'select',
				'label'   => __( 'Style', 'uncoder' ),
				'default' => 'border',
				'options' => array(
					'border'    => __( 'Side border', 'uncoder' ),
					'quotation' => __( 'Quotation mark', 'uncoder' ),
					'boxed'     => __( 'Boxed', 'uncoder' ),
					'plain'     => __( 'Plain', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'mark_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Quotation mark icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'quote' ),
				'condition' => array( 'variant' => array( 'quotation', 'boxed' ) ),
			)
		);
		$this->add_control(
			'show_dash',
			array(
				'type'    => 'switch',
				'label'   => __( 'Dash before author', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'box_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'box_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'box_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'box_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_control(
			'accent_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Accent color', 'uncoder' ),
				'description' => __( 'Side border, quotation mark and box tint.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-bq-accent: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'border_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Side border width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 20 ) ),
				'condition'  => array( 'variant' => 'border' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-bq-border: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'border_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Side border spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'condition'  => array( 'variant' => 'border' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding-inline-start: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_mark',
			array(
				'label'     => __( 'Quotation mark', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'variant' => array( 'quotation', 'boxed' ) ),
			)
		);
		$this->add_control(
			'mark_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-blockquote__mark' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'mark_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 160 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-blockquote__mark' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'mark_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-blockquote__mark' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'mark_opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.01,
				'selectors' => array( '{{WRAPPER}} .uncoder-blockquote__mark' => 'opacity: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Quote', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'quote_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-blockquote__text' ) );
		$this->add_control(
			'quote_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-blockquote__text' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'quote_shadow', array( 'type' => 'text_shadow', 'label' => __( 'Text shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-blockquote__text' ) );
		$this->end_section();

		$this->start_section( 'style_caption', array( 'label' => __( 'Author & source', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'citation_typography', array( 'type' => 'typography', 'label' => __( 'Author typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-blockquote__author' ) );
		$this->add_control(
			'citation_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Author color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-blockquote__author' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'source_typography', array( 'type' => 'typography', 'label' => __( 'Source typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-blockquote__source' ) );
		$this->add_control(
			'source_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Source color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-blockquote__source' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'citation_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-blockquote__caption' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$quote = $this->inline_html( $s['quote'] ?? '' );
		if ( '' === trim( wp_strip_all_tags( $quote ) ) && ! $ctx->editor ) {
			return;
		}
		$variant  = in_array( $s['variant'] ?? 'border', array( 'border', 'quotation', 'boxed', 'plain' ), true ) ? (string) $s['variant'] : 'border';
		$citation = (string) ( $s['citation'] ?? '' );
		$source   = (string) ( $s['source'] ?? '' );
		$link     = $this->link_attrs( $s['link'] ?? array() );

		$classes = 'uncoder-blockquote uncoder-blockquote--' . $variant . ( ! empty( $s['show_dash'] ) ? ' uncoder-blockquote--dash' : '' );
		echo '<figure class="' . esc_attr( $classes ) . '">';
		if ( in_array( $variant, array( 'quotation', 'boxed' ), true ) && $this->has_icon( $s['mark_icon'] ?? null ) ) {
			echo '<span class="uncoder-blockquote__mark" aria-hidden="true">' . $this->render_icon( $s['mark_icon'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
		}
		$cite = isset( $link['href'] ) && preg_match( '#^https?://#i', (string) $link['href'] ) ? array( 'cite' => esc_url_raw( (string) $link['href'] ) ) : array();
		echo '<blockquote class="uncoder-blockquote__quote"' . Utils::attrs( $cite ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		echo '<p class="uncoder-blockquote__text"' . $ctx->inline( 'quote' ) . '>' . $quote . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
		echo '</blockquote>';

		if ( '' !== $citation || '' !== $source || $ctx->editor ) {
			echo '<figcaption class="uncoder-blockquote__caption">';
			if ( '' !== $citation || $ctx->editor ) {
				echo '<span class="uncoder-blockquote__author"' . $ctx->inline( 'citation' ) . '>' . esc_html( $citation ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() escaped.
			}
			if ( '' !== $source ) {
				if ( '' !== $citation ) {
					echo '<span class="uncoder-blockquote__sep">, </span>';
				}
				echo '<cite class="uncoder-blockquote__source">';
				if ( $link ) {
					$link['class'] = 'uncoder-blockquote__source-link';
					echo '<a' . Utils::attrs( $link ) . $ctx->inline( 'source' ) . '>' . esc_html( $source ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
				} else {
					echo '<span' . $ctx->inline( 'source' ) . '>' . esc_html( $source ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() escaped.
				}
				echo '</cite>';
			}
			echo '</figcaption>';
		}
		echo '</figure>';
	}
}
