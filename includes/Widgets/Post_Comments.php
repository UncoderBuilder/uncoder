<?php
/**
 * Post comments widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * The comment list and reply form of the current post (the theme's comments template).
 */
class Post_Comments extends Widget_Base {

	private const FIELDS = '{{WRAPPER}} :is(input[type="text"], input[type="email"], input[type="url"], textarea)';

	private const SUBMIT = '{{WRAPPER}} :is(input[type="submit"], .submit, .wp-element-button)';

	public function name(): string {
		return 'post-comments';
	}

	public function title(): string {
		return __( 'Post Comments', 'uncoder' );
	}

	public function icon(): string {
		return 'message-square';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'comments', 'discussion', 'replies', 'reply', 'form' );
	}

	public function description(): string {
		return __( 'The comments of the current post and the reply form, from the theme\'s comments template. Hidden when comments are closed and there are none.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Comments', 'uncoder' ) ) );
		$this->add_control(
			'notice',
			array(
				'type'  => 'notice',
				'label' => __( 'Shows the comments and reply form of the post being viewed, where comments are open or already exist. Manage discussion in Settings → Discussion.', 'uncoder' ),
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
				'selectors' => array( '{{WRAPPER}} a' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} a:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'heading_typography', array( 'type' => 'typography', 'label' => __( 'Headings typography', 'uncoder' ), 'selector' => '{{WRAPPER}} :is(.comments-title, .comment-reply-title, #comments, #reply-title)' ) );
		$this->add_control(
			'heading_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Headings color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} :is(.comments-title, .comment-reply-title, #comments, #reply-title)' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_list', array( 'label' => __( 'Comment list', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'comment_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between comments', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-comments-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-comments-divider: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'avatar_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Avatar radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .avatar' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'meta_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Meta color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} :is(.comment-metadata, .commentmetadata, .comment-meta)' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_form', array( 'label' => __( 'Form fields', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'field_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::FIELDS ) );
		$this->add_control(
			'field_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( self::FIELDS => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'field_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::FIELDS => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'field_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( self::FIELDS => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'field_focus_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Focus border color', 'uncoder' ),
				'selectors' => array( self::FIELDS . ':focus' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'field_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( self::FIELDS => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_button', array( 'label' => __( 'Submit button', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::SUBMIT ) );
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'button_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( self::SUBMIT => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::SUBMIT => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'button_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( self::SUBMIT . ':hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::SUBMIT . ':hover' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_control(
			'button_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( self::SUBMIT => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( self::SUBMIT => 'padding: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$post = Theme_Context::post( $ctx );

		if ( $ctx->editor ) {
			$state = '';
			if ( $post ) {
				$state = comments_open( $post )
					? __( 'Comments are open on the previewed post.', 'uncoder' )
					: __( 'Comments are closed on the previewed post: the list appears only if it already has comments.', 'uncoder' );
			}
			echo '<div class="uncoder-post-comments uncoder-post-comments--placeholder">';
			echo '<p class="uncoder-post-comments__notice-title">' . esc_html__( 'Comments', 'uncoder' ) . '</p>';
			echo '<p class="uncoder-post-comments__notice">' . esc_html__( 'The comments of the post being viewed and the reply form appear here on the site.', 'uncoder' ) . ( '' !== $state ? ' ' . esc_html( $state ) : '' ) . '</p>';
			echo '</div>';
			return;
		}

		if ( ! $post || post_password_required( $post ) ) {
			return;
		}
		if ( ! comments_open( $post ) && ! (int) get_comments_number( $post ) ) {
			return;
		}

		$html = Theme_Context::with_post(
			$post,
			static function (): string {
				global $withcomments;
				$previous     = $withcomments;
				$withcomments = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below; lets the template render outside is_singular().
				ob_start();
				// Themes without comments.php fall back to theme-compat, which raises a deprecation notice.
				add_filter( 'deprecated_file_trigger_error', '__return_false' );
				comments_template();
				remove_filter( 'deprecated_file_trigger_error', '__return_false' );
				$withcomments = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				return (string) ob_get_clean();
			}
		);
		if ( '' === trim( $html ) ) {
			return;
		}
		echo '<div class="uncoder-post-comments">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme comments template output.
	}
}
