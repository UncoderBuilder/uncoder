<?php
/**
 * Lottie animation widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Plays a Lottie (Bodymovin JSON) animation with the bundled lottie-web "light" player (SVG renderer,
 * no expressions, so animation files cannot run code). The player loads only when the widget nears
 * the viewport. Triggers: autoplay, on hover, on click or tied to the scroll position.
 */
class Lottie extends Widget_Base {

	public const PLAYER = 'assets/vendor/lottie/lottie_light.min.js';

	public function name(): string {
		return 'lottie';
	}

	public function title(): string {
		return __( 'Lottie', 'uncoder' );
	}

	public function icon(): string {
		return 'sparkles';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'lottie', 'animation', 'bodymovin', 'json', 'motion', 'after effects', 'illustration' );
	}

	public function description(): string {
		return __( 'A lightweight vector animation from a Lottie JSON file: autoplay, on hover, on click or following the scroll.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'lottie' );
	}

	public static function player_url(): string {
		return file_exists( UNCODER_WB_PATH . self::PLAYER ) ? UNCODER_WB_URL . self::PLAYER : '';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Lottie', 'uncoder' ) ) );
		$this->add_control(
			'source',
			array(
				'type'    => 'select',
				'label'   => __( 'Source', 'uncoder' ),
				'default' => 'media',
				'options' => array(
					'media' => __( 'Media library', 'uncoder' ),
					'url'   => __( 'External URL', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'file',
			array(
				'type'        => 'media',
				'label'       => __( 'JSON file', 'uncoder' ),
				'types'       => array( 'application/json' ),
				'default'     => array( 'id' => 0, 'url' => '' ),
				'description' => __( 'Upload a Lottie .json file (export from After Effects with Bodymovin, or download from LottieFiles as Lottie JSON).', 'uncoder' ),
				'condition'   => array( 'source' => 'media' ),
				'ai'          => 'A Lottie JSON attachment from the media library: {"id": attachment_id}.',
			)
		);
		$this->add_control(
			'url',
			array(
				'type'        => 'text',
				'label'       => __( 'JSON URL', 'uncoder' ),
				'placeholder' => 'https://…/animation.json',
				'description' => __( 'The host must allow cross-origin requests (CORS).', 'uncoder' ),
				'condition'   => array( 'source' => 'url' ),
				'dynamic'     => true,
			)
		);
		$this->add_control(
			'trigger',
			array(
				'type'    => 'select',
				'label'   => __( 'Play', 'uncoder' ),
				'default' => 'autoplay',
				'options' => array(
					'autoplay' => __( 'Automatically (while visible)', 'uncoder' ),
					'hover'    => __( 'On hover', 'uncoder' ),
					'click'    => __( 'On click', 'uncoder' ),
					'scroll'   => __( 'With the scroll position', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'loop',
			array(
				'type'      => 'switch',
				'label'     => __( 'Loop', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'trigger!' => 'scroll' ),
			)
		);
		$this->add_control(
			'hover_out',
			array(
				'type'      => 'select',
				'label'     => __( 'On mouse leave', 'uncoder' ),
				'default'   => 'reverse',
				'options'   => array(
					'reverse' => __( 'Play backwards', 'uncoder' ),
					'pause'   => __( 'Pause', 'uncoder' ),
					'stop'    => __( 'Back to the start', 'uncoder' ),
					'none'    => __( 'Keep playing', 'uncoder' ),
				),
				'condition' => array( 'trigger' => 'hover' ),
			)
		);
		$this->add_control(
			'speed',
			array(
				'type'    => 'number',
				'label'   => __( 'Speed', 'uncoder' ),
				'default' => 1,
				'min'     => 0.1,
				'max'     => 5,
				'step'    => 0.1,
			)
		);
		$this->add_control(
			'reverse',
			array(
				'type'      => 'switch',
				'label'     => __( 'Play backwards', 'uncoder' ),
				'condition' => array( 'trigger!' => 'scroll' ),
			)
		);
		$this->add_control( 'link', array( 'type' => 'url', 'label' => __( 'Link', 'uncoder' ), 'dynamic' => true ) );
		$this->add_control(
			'label',
			array(
				'type'        => 'text',
				'label'       => __( 'Accessible label', 'uncoder' ),
				'description' => __( 'Describe the animation for screen readers. Leave empty when it is decorative.', 'uncoder' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'flex-start' => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center'     => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'flex-end'   => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				// The animation inside the box, and the box itself once a Width / Max width makes it narrower.
				'selectors_dictionary' => array(
					'flex-start' => '--uncoder-lottie-align:flex-start;margin-inline:0 auto',
					'center'     => '--uncoder-lottie-align:center;margin-inline:auto',
					'flex-end'   => '--uncoder-lottie-align:flex-end;margin-inline:auto 0',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_lottie', array( 'label' => __( 'Animation', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'vw' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 1200 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'vw' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 1200 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.01,
				'selectors' => array( '{{WRAPPER}}' => 'opacity: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * The animation URL, or '' when none is set.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function src( array $s ): string {
		if ( 'url' === ( $s['source'] ?? 'media' ) ) {
			$url = trim( (string) ( $s['url'] ?? '' ) );
		} else {
			$file = is_array( $s['file'] ?? null ) ? $s['file'] : array();
			$url  = (string) ( $file['url'] ?? '' );
			if ( '' === $url && ! empty( $file['id'] ) ) {
				$url = (string) wp_get_attachment_url( (int) $file['id'] );
			}
		}
		return preg_match( '#^(https?:)?//|^/#i', $url ) ? esc_url_raw( $url ) : '';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$src    = $this->src( $s );
		$player = self::player_url();
		if ( '' === $src || '' === $player ) {
			if ( $ctx->editor ) {
				$message = '' === $player
					? __( 'The Lottie player file is missing (assets/vendor/lottie/lottie_light.min.js). Reinstall the plugin.', 'uncoder' )
					: __( 'Choose a Lottie JSON file to show the animation.', 'uncoder' );
				echo '<div class="uncoder-lottie uncoder-lottie--empty">' . $this->render_icon( 'sparkles' ) . '<span>' . esc_html( $message ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
			}
			return;
		}
		$trigger  = in_array( $s['trigger'] ?? 'autoplay', array( 'autoplay', 'hover', 'click', 'scroll' ), true ) ? (string) $s['trigger'] : 'autoplay';
		$settings = array(
			'src'      => $src,
			'player'   => $player,
			'trigger'  => $trigger,
			'loop'     => 'scroll' !== $trigger && ! empty( $s['loop'] ),
			'speed'    => max( 0.1, min( 5, (float) ( $s['speed'] ?? 1 ) ) ),
			'reverse'  => 'scroll' !== $trigger && ! empty( $s['reverse'] ),
			'hoverOut' => in_array( $s['hover_out'] ?? 'reverse', array( 'reverse', 'pause', 'stop', 'none' ), true ) ? (string) $s['hover_out'] : 'reverse',
		);
		$label = trim( (string) ( $s['label'] ?? '' ) );
		$box   = array(
			'class'         => 'uncoder-lottie uncoder-lottie--' . $trigger,
			'data-settings' => $this->json_attr( $settings ),
		);
		if ( '' !== $label ) {
			$box['role']       = 'img';
			$box['aria-label'] = $label;
		} else {
			$box['aria-hidden'] = 'true';
		}
		if ( 'click' === $trigger && ! $this->link_attrs( $s['link'] ?? array() ) ) {
			// A play/pause toggle needs to be reachable by keyboard.
			$box['role']         = 'button';
			$box['tabindex']     = '0';
			$box['aria-pressed'] = 'false';
			$box['aria-label']   = '' !== $label ? $label : __( 'Play animation', 'uncoder' );
			unset( $box['aria-hidden'] );
		}
		$html = '<div' . Utils::attrs( $box ) . '></div>';
		$link = $this->link_attrs( $s['link'] ?? array() );
		if ( $link ) {
			$link['class'] = 'uncoder-lottie__link';
			if ( '' !== $label ) {
				$link['aria-label'] = $label;
			}
			$html = '<a' . Utils::attrs( $link ) . '>' . $html . '</a>';
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
}
