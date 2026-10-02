<?php
/**
 * Shortcode widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Runs a WordPress shortcode (forms, galleries, plugin output) and prints the result.
 */
class Shortcode extends Widget_Base {

	public function name(): string {
		return 'shortcode';
	}

	public function title(): string {
		return __( 'Shortcode', 'uncoder' );
	}

	public function icon(): string {
		return 'brackets';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'shortcode', 'plugin', 'embed', 'form', 'code' );
	}

	public function description(): string {
		return __( 'Outputs a WordPress shortcode from another plugin, e.g. [contact-form-7 id="12"].', 'uncoder' );
	}

	/**
	 * Shortcode output can depend on the current post, user or query.
	 */
	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Shortcode', 'uncoder' ) ) );
		$this->add_control(
			'shortcode',
			array(
				'type'        => 'textarea',
				'label'       => __( 'Shortcode', 'uncoder' ),
				'rows'        => 3,
				'placeholder' => '[gallery ids="12,34,56"]',
				'ai'          => 'A registered shortcode such as [contact-form-7 id="12"]. HTML tags are stripped.',
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$code = trim( (string) ( $s['shortcode'] ?? '' ) );
		if ( '' === $code ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-shortcode__placeholder">' . $this->render_icon( 'brackets' ) . '<span>' . esc_html__( 'Enter a shortcode in the settings.', 'uncoder' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
			}
			return;
		}
		$output = do_shortcode( $code );
		if ( $ctx->editor && '' === trim( $output ) ) {
			echo '<div class="uncoder-shortcode__placeholder">' . $this->render_icon( 'brackets' ) . '<span>' . esc_html( $code ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
			return;
		}
		echo '<div class="uncoder-shortcode">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output is the responsibility of its plugin, as in post content.
	}
}
