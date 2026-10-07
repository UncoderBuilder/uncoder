<?php
/**
 * Post excerpt widget.
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
 * Excerpt of the current post, trimmed to a number of words, generated from the content when empty.
 */
class Post_Excerpt extends Widget_Base {

	public function name(): string {
		return 'post-excerpt';
	}

	public function title(): string {
		return __( 'Post Excerpt', 'uncoder' );
	}

	public function icon(): string {
		return 'text-quote';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'excerpt', 'summary', 'post', 'intro', 'description' );
	}

	public function description(): string {
		return __( 'The excerpt of the current post (or the start of its content), trimmed to a number of words, with an optional "read more" link.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Excerpt', 'uncoder' ) ) );
		$this->add_control(
			'length',
			array(
				'type'        => 'number',
				'label'       => __( 'Length (words)', 'uncoder' ),
				'description' => __( '0 keeps the whole excerpt.', 'uncoder' ),
				'default'     => 30,
				'min'         => 0,
				'max'         => 300,
			)
		);
		$this->add_control(
			'fallback',
			array(
				'type'        => 'switch',
				'label'       => __( 'Use the content when there is no excerpt', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'more',
			array(
				'type'    => 'text',
				'label'   => __( 'Trimmed text suffix', 'uncoder' ),
				'default' => '…',
			)
		);
		$this->add_control(
			'read_more',
			array(
				'type'        => 'text',
				'label'       => __( 'Read more link', 'uncoder' ),
				'placeholder' => __( 'Continue reading', 'uncoder' ),
				'description' => __( 'Leave empty for no link.', 'uncoder' ),
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

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-post-excerpt__text' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-excerpt__text' => 'color: {{VALUE}}' ),
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
		$this->end_section();

		$this->start_section(
			'style_more',
			array(
				'label'     => __( 'Read more link', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'read_more!' => '' ),
			)
		);
		$this->add_group( 'more_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-post-excerpt__more' ) );
		$this->add_control(
			'more_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-excerpt__more' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'more_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-excerpt__more:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'more_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-post-excerpt__more-wrap' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Plain-text excerpt of a post (manual excerpt or generated from the content).
	 */
	public static function text( \WP_Post $post, bool $fallback = true ): string {
		if ( has_excerpt( $post ) ) {
			return Theme_Context::plain_text( (string) $post->post_excerpt );
		}
		return $fallback ? Theme_Context::plain_text( (string) $post->post_content ) : '';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$post = Theme_Context::post( $ctx );
		$text = ( $post && ! post_password_required( $post ) ) ? self::text( $post, ! empty( $s['fallback'] ) ) : '';
		$url  = $post ? get_permalink( $post ) : '';
		$name = $post ? wp_strip_all_tags( get_the_title( $post ) ) : '';
		if ( '' === $text ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$text = Theme_Context::sample()['excerpt'];
			$url  = '#';
			$name = Theme_Context::sample()['title'];
		}
		$length = (int) ( $s['length'] ?? 30 );
		if ( $length > 0 ) {
			$text = wp_trim_words( $text, $length, (string) ( $s['more'] ?? '…' ) );
		}
		echo '<div class="' . esc_attr( 'center' === ( $s['align'] ?? '' ) ? 'uncoder-post-excerpt uncoder-post-excerpt--centered' : 'uncoder-post-excerpt' ) . '">';
		echo '<p class="uncoder-post-excerpt__text">' . esc_html( wptexturize( $text ) ) . '</p>';
		$more = trim( (string) ( $s['read_more'] ?? '' ) );
		if ( '' !== $more && $url ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '<p class="uncoder-post-excerpt__more-wrap"><a' . Utils::attrs(
				array(
					'class' => 'uncoder-post-excerpt__more',
					'href'  => $url,
				)
			) . '>' . esc_html( $more ) . ( '' !== $name ? '<span class="uncoder-sr-only">: ' . esc_html( $name ) . '</span>' : '' ) . '</a></p>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}
}
