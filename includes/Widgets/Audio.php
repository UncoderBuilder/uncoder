<?php
/**
 * Audio widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A sound file from the media library (a voice sample, a podcast clip, a greeting) with its own player: a play / pause
 * button, and in the "bar" layout a seek bar and the time. Playing one pauses any other Uncoder audio on the page.
 * Without JavaScript the browser's own player shows instead (the "audio" module swaps in the custom one).
 */
class Audio extends Widget_Base {

	public function name(): string {
		return 'audio';
	}

	public function title(): string {
		return __( 'Audio', 'uncoder' );
	}

	public function icon(): string {
		return 'volume-2';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'audio', 'sound', 'mp3', 'voice', 'podcast', 'music', 'player', 'play', 'sample', 'clip' );
	}

	public function description(): string {
		return __( 'A sound file from your media library with its own player: a play button with a seek bar and the time, or just a round play button (a voice sample on a card). One plays at a time.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'audio' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Audio', 'uncoder' ) ) );
		$this->add_control(
			'file',
			array(
				'type'        => 'media',
				'label'       => __( 'Audio file', 'uncoder' ),
				'types'       => array( 'audio' ),
				'default'     => array( 'id' => 0, 'url' => '' ),
				'description' => __( 'An MP3, M4A, OGG or WAV file from the media library.', 'uncoder' ),
				'dynamic'     => true,
				'ai'          => 'The sound file, {"id":<attachment id>,"url":"…mp3"}.',
			)
		);
		$this->add_control(
			'title',
			array(
				'type'        => 'text',
				'label'       => __( 'Title', 'uncoder' ),
				'placeholder' => __( 'Voice sample', 'uncoder' ),
				'description' => __( 'What the recording is. Screen readers hear "Play" with it; shown next to the player when "Show title" is on.', 'uncoder' ),
				'dynamic'     => true,
			)
		);
		$this->add_control(
			'show_title',
			array(
				'type'      => 'switch',
				'label'     => __( 'Show title', 'uncoder' ),
				'condition' => array( 'layout!' => 'button' ),
			)
		);
		$this->add_control(
			'layout',
			array(
				'type'    => 'choose',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'bar',
				'options' => array(
					'bar'    => array( 'label' => __( 'Button, bar and time', 'uncoder' ), 'icon' => 'audio-lines' ),
					'button' => array( 'label' => __( 'Play button only', 'uncoder' ), 'icon' => 'circle-play' ),
				),
				'ai'      => '"bar" = round play button + seek bar + "0:00 / 0:30"; "button" = only the round play / pause button (put it over a picture or a card).',
			)
		);
		$this->add_control(
			'show_time',
			array(
				'type'      => 'switch',
				'label'     => __( 'Show time', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'layout' => 'bar' ),
			)
		);
		$this->add_control(
			'loop',
			array(
				'type'  => 'switch',
				'label' => __( 'Loop', 'uncoder' ),
			)
		);
		$this->add_control(
			'pause_others',
			array(
				'type'        => 'switch',
				'label'       => __( 'Pause other audio', 'uncoder' ),
				'default'     => true,
				'description' => __( 'Playing this one pauses any other audio or video playing with sound on the page, and a video started with sound pauses it.', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_button', array( 'label' => __( 'Play button', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'button_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 160 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-audio-btn: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-audio-icon: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-audio__toggle' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-audio__toggle' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-audio__toggle:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-audio__toggle:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-audio__toggle' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'button_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-audio__toggle' ) );
		$this->add_group( 'button_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-audio__toggle' ) );
		$this->end_section();

		$this->start_section(
			'style_bar',
			array(
				'label'     => __( 'Bar & time', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'layout' => 'bar' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-audio-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'bar_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Bar height', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 16 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-audio-bar: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'track_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Track color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-audio-track: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'fill_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Played color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-audio-fill: {{VALUE}}' ),
			)
		);
		$this->add_group( 'time_typography', array( 'type' => 'typography', 'label' => __( 'Time typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-audio__time' ) );
		$this->add_control(
			'time_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Time color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-audio__time' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-audio__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Title color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-audio__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$file = is_array( $s['file'] ?? null ) ? $s['file'] : array();
		// The library file's own address wins (it follows the site to a new domain); a typed URL otherwise.
		$url   = ! empty( $file['id'] ) ? (string) wp_get_attachment_url( (int) $file['id'] ) : '';
		$url   = esc_url_raw( '' !== $url ? $url : (string) ( $file['url'] ?? '' ), array( 'http', 'https' ) );
		$title = trim( wp_strip_all_tags( (string) ( $s['title'] ?? '' ) ) );
		$bar   = 'button' !== ( $s['layout'] ?? 'bar' );
		if ( '' === $url ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-audio__placeholder">' . esc_html__( 'Choose an audio file.', 'uncoder' ) . '</p>';
			}
			return;
		}
		$play  = '' !== $title
			/* translators: %s: what the recording is. */
			? sprintf( __( 'Play %s', 'uncoder' ), $title )
			: __( 'Play', 'uncoder' );
		$pause = '' !== $title
			/* translators: %s: what the recording is. */
			? sprintf( __( 'Pause %s', 'uncoder' ), $title )
			: __( 'Pause', 'uncoder' );

		$attrs = array(
			'class'              => 'uncoder-audio uncoder-audio--' . ( $bar ? 'bar' : 'button' ),
			'data-pause-others'  => empty( $s['pause_others'] ) ? 'false' : 'true',
		);
		$audio = array(
			'class'    => 'uncoder-audio__el',
			'src'      => $url,
			'preload'  => 'metadata',
			'controls' => true,
		);
		if ( ! empty( $s['loop'] ) ) {
			$audio['loop'] = true;
		}
		if ( '' !== $title ) {
			$audio['aria-label'] = $title;
		}

		echo '<div' . Utils::attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		echo '<audio' . Utils::attrs( $audio ) . '></audio>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		echo '<div class="uncoder-audio__ui">';
		echo '<button type="button" class="uncoder-audio__toggle" aria-label="' . esc_attr( $play ) . '" data-play-label="' . esc_attr( $play ) . '" data-pause-label="' . esc_attr( $pause ) . '">';
		echo '<svg class="uncoder-audio__icon uncoder-audio__icon--play" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>';
		echo '<svg class="uncoder-audio__icon uncoder-audio__icon--pause" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>';
		echo '</button>';
		if ( $bar ) {
			if ( '' !== $title && ! empty( $s['show_title'] ) ) {
				echo '<span class="uncoder-audio__title">' . esc_html( $title ) . '</span>';
			}
			$seek = '' !== $title
				/* translators: %s: what the recording is. */
				? sprintf( __( 'Seek %s', 'uncoder' ), $title )
				: __( 'Seek', 'uncoder' );
			echo '<input type="range" class="uncoder-audio__seek" min="0" max="100" step="0.1" value="0" aria-label="' . esc_attr( $seek ) . '" aria-valuetext="0:00">';
			if ( ! empty( $s['show_time'] ) ) {
				echo '<span class="uncoder-audio__time"><span class="uncoder-audio__current">0:00</span> / <span class="uncoder-audio__duration">0:00</span></span>';
			}
		}
		echo '</div></div>';
	}
}
