<?php
/**
 * Post content widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * The full content of the current post, run through the_content filters (blocks, shortcodes, embeds).
 */
class Post_Content extends Widget_Base {

	/** @var array<int,bool> Posts whose content is being rendered (recursion guard). */
	private static array $rendering = array();

	public function name(): string {
		return 'post-content';
	}

	public function title(): string {
		return __( 'Post Content', 'uncoder' );
	}

	public function icon(): string {
		return 'file-text';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'content', 'post', 'body', 'article', 'the_content', 'single' );
	}

	public function description(): string {
		return __( 'The content of the current post or page (blocks, shortcodes and embeds included). Place it once in single post and page templates.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Content', 'uncoder' ) ) );
		$this->add_control(
			'notice',
			array(
				'type'  => 'notice',
				'label' => __( 'Shows the content of the post being viewed. The editor displays sample text so you can style it.', 'uncoder' ),
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
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} a:not(.wp-element-button)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} a:not(.wp-element-button):hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'paragraph_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Paragraph spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-content-flow: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_headings', array( 'label' => __( 'Headings', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'heading_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} :is(h1, h2, h3, h4, h5, h6)' ) );
		$this->add_control(
			'heading_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} :is(h1, h2, h3, h4, h5, h6)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'heading_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space above headings', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} > :is(h1, h2, h3, h4, h5, h6):not(:first-child)' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_media', array( 'label' => __( 'Images & quotes', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'image_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Image radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} img' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'quote_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Quote accent', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} blockquote' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Sample paragraphs for the editor canvas.
	 */
	private function sample(): string {
		$html  = '<p>' . esc_html__( 'This is where the content of each post appears. Style the text here and every article on the site follows: body copy, links, headings, lists, quotes and images.', 'uncoder' ) . '</p>';
		$html .= '<h2>' . esc_html__( 'Start with the reader', 'uncoder' ) . '</h2>';
		$html .= '<p>' . esc_html__( 'Good articles open with the problem they solve. Keep paragraphs short, use descriptive subheadings and let the layout breathe so people can scan before they commit to reading.', 'uncoder' ) . ' <a href="#">' . esc_html__( 'Links look like this', 'uncoder' ) . '</a>.</p>';
		$html .= '<ul><li>' . esc_html__( 'Clear, specific headings', 'uncoder' ) . '</li><li>' . esc_html__( 'One idea per paragraph', 'uncoder' ) . '</li><li>' . esc_html__( 'Examples that show, not tell', 'uncoder' ) . '</li></ul>';
		$html .= '<blockquote><p>' . esc_html__( 'Simplicity is not the absence of clutter; it is the presence of clarity.', 'uncoder' ) . '</p></blockquote>';
		$html .= '<h3>' . esc_html__( 'Finish with a next step', 'uncoder' ) . '</h3>';
		$html .= '<p>' . esc_html__( 'Close with a summary and one clear action, such as a related article, a newsletter sign-up or a way to get in touch.', 'uncoder' ) . '</p>';
		return $html;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$post = Theme_Context::post( $ctx );

		if ( $ctx->editor ) {
			if ( $post && $post->ID === $ctx->doc_id ) {
				echo '<div class="uncoder-post-content-notice">' . esc_html__( 'Post Content shows the content of the post being viewed, so it cannot be placed inside that same post. Use it in a single post or page template.', 'uncoder' ) . '</div>';
				return;
			}
			echo '<div class="uncoder-post-content">' . $this->sample() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped sample.
			return;
		}

		// Never render a post inside itself (a page embedding its own content) or recursively.
		if ( ! $post || $post->ID === $ctx->doc_id || isset( self::$rendering[ $post->ID ] ) ) {
			return;
		}
		if ( post_password_required( $post ) ) {
			echo '<div class="uncoder-post-content">' . get_the_password_form( $post ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core form markup.
			return;
		}

		self::$rendering[ $post->ID ] = true;
		try {
			$html = Theme_Context::with_post(
				$post,
				static function () use ( $post ): string {
					/** This filter is documented in wp-includes/post-template.php */
					$content = (string) apply_filters( 'the_content', get_the_content( null, false, $post ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
					$content = str_replace( ']]>', ']]&gt;', $content );
					$pages   = wp_link_pages(
						array(
							'before' => '<nav class="uncoder-post-content__pages" aria-label="' . esc_attr__( 'Post pages', 'uncoder' ) . '"><span class="uncoder-post-content__pages-label">' . esc_html__( 'Pages:', 'uncoder' ) . '</span>',
							'after'  => '</nav>',
							'echo'   => 0,
						)
					);
					return $content . $pages;
				}
			);
		} finally {
			unset( self::$rendering[ $post->ID ] );
		}
		if ( '' === trim( (string) $html ) ) {
			return;
		}
		echo '<div class="uncoder-post-content">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output of the_content filters, like core themes.
	}
}
