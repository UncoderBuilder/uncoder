<?php
/**
 * Reading progress bar widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A thin bar that fills as the visitor reads: pinned to the top or bottom of the screen, or in place
 * (e.g. along the bottom of a sticky header). Follows the whole page, the post content or any element
 * by CSS ID. Decorative (hidden from screen readers); the module only updates a transform.
 */
class Reading_Progress extends Widget_Base {

	public function name(): string {
		return 'reading-progress';
	}

	public function title(): string {
		return __( 'Reading Progress', 'uncoder' );
	}

	public function icon(): string {
		return 'book-open-check';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'reading', 'progress', 'scroll', 'indicator', 'bar', 'article', 'blog' );
	}

	public function description(): string {
		return __( 'A bar that fills while the visitor scrolls through the page or the article. Put it in the single post template or the header.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'reading-progress' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Reading progress', 'uncoder' ) ) );
		$this->add_control(
			'position',
			array(
				'type'    => 'select',
				'label'   => __( 'Position', 'uncoder' ),
				'default' => 'top',
				'options' => array(
					'top'    => __( 'Top of the screen', 'uncoder' ),
					'bottom' => __( 'Bottom of the screen', 'uncoder' ),
					'inline' => __( 'Where the widget is', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'track',
			array(
				'type'    => 'select',
				'label'   => __( 'Measure', 'uncoder' ),
				'default' => 'content',
				'options' => array(
					'content' => __( 'The post content (falls back to the page)', 'uncoder' ),
					'page'    => __( 'The whole page', 'uncoder' ),
					'element' => __( 'An element by CSS ID', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'target',
			array(
				'type'        => 'text',
				'label'       => __( 'CSS ID', 'uncoder' ),
				'placeholder' => 'article',
				'description' => __( 'The Anchor ID (Behaviour → Anchor & classes) of the section to measure, without #.', 'uncoder' ),
				'condition'   => array( 'track' => 'element' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_bar', array( 'label' => __( 'Bar', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 20 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-rp-height: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Bar color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-rp-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'track_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Track color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-rp-track: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'offset',
			array(
				'type'        => 'slider',
				'label'       => __( 'Distance from the edge', 'uncoder' ),
				'description' => __( 'Room for a sticky header, for example.', 'uncoder' ),
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 200 ) ),
				'condition'   => array( 'position!' => 'inline' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-rp-offset: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'rounded',
			array(
				'type'      => 'switch',
				'label'     => __( 'Rounded end', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-reading-progress__bar' => 'border-radius: 0 999px 999px 0' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$position = in_array( $s['position'] ?? 'top', array( 'top', 'bottom', 'inline' ), true ) ? (string) $s['position'] : 'top';
		$track    = in_array( $s['track'] ?? 'content', array( 'content', 'page', 'element' ), true ) ? (string) $s['track'] : 'content';
		$settings = array(
			'track'  => $track,
			'target' => 'element' === $track ? sanitize_html_class( (string) ( $s['target'] ?? '' ) ) : '',
		);
		$box = array(
			// The editor shows the bar in place, a third full, instead of covering the canvas.
			'class'         => 'uncoder-reading-progress uncoder-reading-progress--' . ( $ctx->editor ? 'inline uncoder-reading-progress--preview' : $position ),
			'data-settings' => $this->json_attr( $settings ),
			'aria-hidden'   => 'true',
		);
		echo '<div' . Utils::attrs( $box ) . '><span class="uncoder-reading-progress__bar"></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
	}
}
