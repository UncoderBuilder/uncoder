<?php
/**
 * Carousel widget (nested: every slide is a container).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Carousel_Engine;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * Scroll-snap carousel whose slides are free-form containers.
 */
class Carousel extends Widget_Base {

	use Carousel_Engine;

	private const SLIDE = '{{WRAPPER}} > .uncoder-carousel__viewport > .uncoder-carousel__track > .uncoder-carousel__slide';

	public function name(): string {
		return 'carousel';
	}

	public function title(): string {
		return __( 'Carousel', 'uncoder' );
	}

	public function icon(): string {
		return 'gallery-horizontal-end';
	}

	public function category(): string {
		return 'layout';
	}

	public function keywords(): array {
		return array( 'carousel', 'slider', 'slides', 'slideshow', 'nested', 'swipe' );
	}

	public function description(): string {
		return __( 'Swipeable carousel whose slides are containers holding any widgets. One row in "slides" = one child container.', 'uncoder' );
	}

	public function nested(): ?array {
		return array( 'items' => 'slides' );
	}

	protected function supports_ticker(): bool {
		return true;
	}

	public function frontend_scripts(): array {
		return array( 'carousel' );
	}

	public function preset(): array {
		return array( 'slides' => $this->default_slides() );
	}

	/**
	 * @return array<int, array<string,string>>
	 */
	private function default_slides(): array {
		return array(
			/* translators: %d: slide number. */
			array( 'label' => sprintf( __( 'Slide %d', 'uncoder' ), 1 ) ),
			/* translators: %d: slide number. */
			array( 'label' => sprintf( __( 'Slide %d', 'uncoder' ), 2 ) ),
			/* translators: %d: slide number. */
			array( 'label' => sprintf( __( 'Slide %d', 'uncoder' ), 3 ) ),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Slides', 'uncoder' ) ) );
		$this->add_control(
			'slides',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Slides', 'uncoder' ),
				'title_field' => 'label',
				'fields'      => array(
					'label' => array(
						'type'        => 'text',
						'label'       => __( 'Label', 'uncoder' ),
						'description' => __( 'Shown in the navigator; each slide is edited as a container on the canvas.', 'uncoder' ),
						'default'     => __( 'Slide', 'uncoder' ),
					),
				),
				'default'     => $this->default_slides(),
				'ai'          => 'Each row owns the child container at the same index (children[i]). Add/remove rows together with children.',
			)
		);
		$this->end_section();

		$this->register_carousel_settings();

		$this->start_section( 'style_slides', array( 'label' => __( 'Slides', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'slide_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => self::SLIDE ) );
		$this->add_group( 'slide_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::SLIDE ) );
		$this->add_responsive_control(
			'slide_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( self::SLIDE => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'slide_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( self::SLIDE => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'slide_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::SLIDE ) );
		$this->end_section();

		$this->register_carousel_style();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array( 'data-settings' => $this->json_attr( $this->carousel_data( $s ) ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = Repeater_Rows::get( $this, 'slides', $s['slides'] ?? array() );
		if ( ! $rows ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-carousel-placeholder">' . esc_html__( 'Add a slide to start building the carousel.', 'uncoder' ) . '</div>';
			}
			return;
		}
		$total = count( $rows );
		echo $this->carousel_start( $s, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
		foreach ( $rows as $i => $row ) {
			$rid = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );
			echo $this->carousel_slide_start( $i, $total, '' !== $rid ? array( 'uncoder-ri-' . $rid ) : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
			echo $ctx->render_child( $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered child container.
			echo '</div>';
		}
		// Continuous motion: a second copy of every slide makes the loop seamless (the module adds more copies
		// when one set is narrower than the screen).
		if ( $this->is_ticker( $s ) && ! $ctx->editor ) {
			foreach ( $rows as $i => $row ) {
				$rid = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );
				echo $this->carousel_slide_start( $i, $total, '' !== $rid ? array( 'uncoder-ri-' . $rid ) : array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
				echo $ctx->render_child( $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered child container.
				echo '</div>';
			}
		}
		echo $this->carousel_end( $s, $ctx, $total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	}
}
