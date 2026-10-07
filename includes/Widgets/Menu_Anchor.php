<?php
/**
 * Menu anchor widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * An invisible scroll target: links to "#id" jump here.
 */
class Menu_Anchor extends Widget_Base {

	public function name(): string {
		return 'menu-anchor';
	}

	public function title(): string {
		return __( 'Menu Anchor', 'uncoder' );
	}

	public function icon(): string {
		return 'anchor';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'anchor', 'menu', 'scroll', 'jump', 'id', 'one page' );
	}

	public function description(): string {
		return __( 'An invisible target for one-page navigation: a link to "#your-id" scrolls here. Place it just above the section to reach.', 'uncoder' );
	}

	/**
	 * Same character set as "#anchor" URLs accepted by the link control.
	 */
	public static function sanitize_anchor( $value ): string {
		$value = trim( (string) $value );
		$value = ltrim( $value, '#' );
		$value = (string) preg_replace( '/\s+/', '-', $value );
		return (string) preg_replace( '/[^A-Za-z0-9_\-:.]/', '', $value );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Menu anchor', 'uncoder' ) ) );
		$this->add_control(
			'anchor',
			array(
				'type'        => 'text',
				'label'       => __( 'Anchor ID', 'uncoder' ),
				'placeholder' => 'pricing',
				'description' => __( 'Letters, numbers and dashes only. Link to it with "#pricing". Must be unique on the page.', 'uncoder' ),
				'ai'          => 'ID without "#", e.g. "pricing". Menu links then use {"url": "#pricing"}.',
			)
		);
		$this->add_control(
			'offset',
			array(
				'type'        => 'slider',
				'label'       => __( 'Scroll offset', 'uncoder' ),
				'description' => __( 'Space kept above the target, e.g. the height of a sticky header.', 'uncoder' ),
				'size_units'  => array( 'px', 'rem', 'vh' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 300 ) ),
				'selectors'   => array( '{{WRAPPER}} .uncoder-menu-anchor__target' => 'scroll-margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/** The scroll target carries the anchor as its id, so the element keeps a thin wrapper. */
	public function merge_root(): bool {
		return false;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$anchor = self::sanitize_anchor( $s['anchor'] ?? '' );
		if ( '' !== $anchor ) {
			echo '<span class="uncoder-menu-anchor__target" id="' . esc_attr( $anchor ) . '"></span>';
		}
		if ( ! $ctx->editor ) {
			return;
		}
		$label = '' !== $anchor ? '#' . $anchor : __( '#anchor — set an ID in the settings', 'uncoder' );
		echo '<div class="uncoder-menu-anchor__placeholder">' . $this->render_icon( 'anchor' ) . '<span>' . esc_html( $label ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped label.
	}
}
