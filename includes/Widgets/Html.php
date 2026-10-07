<?php
/**
 * Custom HTML widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Raw HTML. Users without the unfiltered_html capability have it filtered on save (Code control).
 */
class Html extends Widget_Base {

	public function name(): string {
		return 'html';
	}

	/** Raw markup may hold any number of elements: keep a thin div.uncoder-html around it. */
	public function merge_root(): bool {
		return false;
	}

	public function title(): string {
		return __( 'HTML', 'uncoder' );
	}

	public function icon(): string {
		return 'code-xml';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'html', 'code', 'embed', 'custom', 'script', 'iframe' );
	}

	public function description(): string {
		return __( 'Custom HTML code, e.g. an embed snippet. Prefer native widgets for layout and text; scripts run only on the front end.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'HTML', 'uncoder' ) ) );
		$this->add_control(
			'html',
			array(
				'type'        => 'code',
				'language'    => 'html',
				'label'       => __( 'HTML code', 'uncoder' ),
				'rows'        => 14,
				'placeholder' => '<div class="my-embed">…</div>',
				'description' => __( 'Saved as-is only for users allowed to post unfiltered HTML; otherwise unsafe tags are removed.', 'uncoder' ),
				'ai'          => 'Last resort for third-party embeds. Never use it for content that native widgets can build.',
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$html = (string) ( $s['html'] ?? '' );
		if ( '' === trim( $html ) ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-html__placeholder">' . $this->render_icon( 'code-xml' ) . '<span>' . esc_html__( 'Add your HTML code in the settings.', 'uncoder' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
			}
			return;
		}
		// Sanitized on save by the Code control (kept raw only for users with unfiltered_html).
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized on save.
	}
}
