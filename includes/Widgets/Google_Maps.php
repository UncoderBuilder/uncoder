<?php
/**
 * Google Maps widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * An embedded Google map for an address or place (no API key needed), loaded lazily.
 */
class Google_Maps extends Widget_Base {

	public function name(): string {
		return 'google-maps';
	}

	public function title(): string {
		return __( 'Google Maps', 'uncoder' );
	}

	public function icon(): string {
		return 'map-pin';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'map', 'google maps', 'location', 'address', 'directions', 'embed' );
	}

	public function description(): string {
		return __( 'An embedded Google map for an address or place name. Loads lazily, or only when the visitor clicks a local placeholder ("Load on click", for privacy laws); height, zoom and a grayscale look are adjustable.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Map', 'uncoder' ) ) );
		$this->add_control(
			'address',
			array(
				'type'        => 'text',
				'label'       => __( 'Location', 'uncoder' ),
				'default'     => __( 'London Eye, London', 'uncoder' ),
				'placeholder' => __( 'Address, place name or coordinates', 'uncoder' ),
				'dynamic'     => true,
				'ai'          => 'Full street address, a place name ("Louvre Museum, Paris") or "lat,lng".',
			)
		);
		$this->add_control(
			'zoom',
			array(
				'type'    => 'number',
				'label'   => __( 'Zoom', 'uncoder' ),
				'default' => 14,
				'min'     => 1,
				'max'     => 20,
				'step'    => 1,
			)
		);
		$this->add_control(
			'map_type',
			array(
				'type'    => 'select',
				'label'   => __( 'Map type', 'uncoder' ),
				'default' => 'm',
				'options' => array(
					'm' => __( 'Roadmap', 'uncoder' ),
					'k' => __( 'Satellite', 'uncoder' ),
					'h' => __( 'Hybrid', 'uncoder' ),
					'p' => __( 'Terrain', 'uncoder' ),
				),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 120, 'max' => 1200 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'height: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'grayscale',
			array(
				'type'  => 'switch',
				'label' => __( 'Grayscale', 'uncoder' ),
			)
		);
		$this->add_control(
			'grayscale_hover',
			array(
				'type'      => 'switch',
				'label'     => __( 'Color on hover', 'uncoder' ),
				'condition' => array( 'grayscale' => 'yes' ),
			)
		);
		$this->add_control(
			'map_title',
			array(
				'type'        => 'text',
				'label'       => __( 'Accessible title', 'uncoder' ),
				'description' => __( 'Read by screen readers. Defaults to "Map of" followed by the location.', 'uncoder' ),
			)
		);
		$this->add_control(
			'click_to_load',
			array(
				'type'        => 'switch',
				'label'       => __( 'Load on click', 'uncoder' ),
				'description' => __( 'Shows a placeholder drawn on your site; Google Maps (and its cookies) load only when the visitor clicks it. Helps with privacy laws such as the GDPR.', 'uncoder' ),
			)
		);
		$this->add_control(
			'facade_label',
			array(
				'type'        => 'text',
				'label'       => __( 'Button text', 'uncoder' ),
				'placeholder' => __( 'Show map', 'uncoder' ),
				'condition'   => array( 'click_to_load' => 'yes' ),
			)
		);
		$this->add_control(
			'facade_note',
			array(
				'type'      => 'text',
				'label'     => __( 'Privacy note', 'uncoder' ),
				'default'   => __( 'Loads the map from Google Maps', 'uncoder' ),
				'condition' => array( 'click_to_load' => 'yes' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_map', array( 'label' => __( 'Map', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->start_tabs( 'filter_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_group( 'filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-google-maps__iframe' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_group( 'hover_filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover .uncoder-google-maps__iframe' ) );
		$this->add_control(
			'transition',
			array(
				'type'      => 'number',
				'label'     => __( 'Transition (ms)', 'uncoder' ),
				'min'       => 0,
				'max'       => 3000,
				'step'      => 50,
				'selectors' => array( '{{WRAPPER}} .uncoder-google-maps__iframe' => 'transition-duration: {{VALUE}}ms' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();
	}

	/**
	 * Keyless Google Maps embed URL.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function embed_url( string $address, array $s ): string {
		$zoom   = is_numeric( $s['zoom'] ?? null ) ? max( 1, min( 20, (int) $s['zoom'] ) ) : 14;
		$type   = in_array( $s['map_type'] ?? 'm', array( 'm', 'k', 'h', 'p' ), true ) ? (string) $s['map_type'] : 'm';
		$locale = strtolower( substr( (string) get_locale(), 0, 2 ) );
		$args   = array(
			'q'      => rawurlencode( $address ),
			't'      => $type,
			'z'      => $zoom,
			'output' => 'embed',
			'iwloc'  => 'near',
		);
		if ( preg_match( '/^[a-z]{2}$/', $locale ) ) {
			$args['hl'] = $locale;
		}
		return add_query_arg( $args, 'https://maps.google.com/maps' );
	}

	/** The shared click-to-load module ("Load on click"); it does nothing for maps without a placeholder. */
	public function frontend_scripts(): array {
		return array( 'embed-facade' );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$address = trim( wp_strip_all_tags( (string) ( $s['address'] ?? '' ) ) );
		if ( '' === $address ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-google-maps uncoder-google-maps--empty">' . $this->render_icon( 'map-pin' ) . '<span>' . esc_html__( 'Enter a location to show the map.', 'uncoder' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
			}
			return;
		}
		$title = trim( (string) ( $s['map_title'] ?? '' ) );
		if ( '' === $title ) {
			/* translators: %s: address or place name. */
			$title = sprintf( __( 'Map of %s', 'uncoder' ), $address );
		}
		$classes = 'uncoder-google-maps';
		if ( ! empty( $s['grayscale'] ) ) {
			$classes .= ' uncoder-google-maps--grayscale' . ( ! empty( $s['grayscale_hover'] ) ? ' uncoder-google-maps--color-hover' : '' );
		}
		$iframe = array(
			'class'           => 'uncoder-google-maps__iframe',
			'src'             => $this->embed_url( $address, $s ),
			'title'           => $title,
			'loading'         => 'lazy',
			'referrerpolicy'  => 'no-referrer-when-downgrade',
			'allowfullscreen' => true,
		);
		if ( empty( $s['click_to_load'] ) || $ctx->editor ) {
			echo '<div class="' . esc_attr( $classes ) . '"><iframe' . Utils::attrs( $iframe ) . '></iframe></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
			return;
		}
		// Nothing is requested from Google until the visitor clicks; the "embed-facade" module then swaps in the iframe.
		$label = trim( (string) ( $s['facade_label'] ?? '' ) );
		$label = '' !== $label ? $label : __( 'Show map', 'uncoder' );
		$note  = trim( (string) ( $s['facade_note'] ?? '' ) );
		echo '<div class="' . esc_attr( $classes . ' uncoder-google-maps--facade' ) . '">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs() / esc_html(); icon markup.
		echo '<button' . Utils::attrs(
			array(
				'type'               => 'button',
				'class'              => 'uncoder-google-maps__facade',
				'data-uncoder-embed' => $iframe['src'],
				'data-title'         => $title,
				/* translators: %s: address or place name. */
				'aria-label'         => sprintf( __( 'Show the map of %s (loads Google Maps)', 'uncoder' ), $address ),
			)
		) . '>';
		echo '<span class="uncoder-google-maps__pin" aria-hidden="true">' . $this->render_icon( 'map-pin' ) . '</span>';
		echo '<span class="uncoder-google-maps__place">' . esc_html( $address ) . '</span>';
		echo '<span class="uncoder-google-maps__show">' . esc_html( $label ) . '</span>';
		if ( '' !== $note ) {
			echo '<span class="uncoder-google-maps__note">' . esc_html( $note ) . '</span>';
		}
		echo '</button>';
		echo '<noscript><iframe' . Utils::attrs( $iframe ) . '></iframe></noscript>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}
