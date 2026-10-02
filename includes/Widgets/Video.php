<?php
/**
 * Video widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * YouTube / Vimeo / self-hosted video. Embeds render as a lightweight facade (poster + play
 * button); the real player is injected on click by the "video" module.
 */
class Video extends Widget_Base {

	public const RATIOS = array(
		'16/9' => '16:9',
		'4/3'  => '4:3',
		'3/2'  => '3:2',
		'21/9' => '21:9',
		'1/1'  => '1:1',
		'9/16' => '9:16',
	);

	public function name(): string {
		return 'video';
	}

	public function title(): string {
		return __( 'Video', 'uncoder' );
	}

	public function icon(): string {
		return 'circle-play';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'video', 'youtube', 'vimeo', 'mp4', 'player', 'embed', 'media' );
	}

	public function description(): string {
		return __( 'A YouTube, Vimeo or self-hosted video. Embeds load only when played (poster + play button), using privacy-enhanced domains.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'video' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Video', 'uncoder' ) ) );
		$this->add_control(
			'source',
			array(
				'type'    => 'select',
				'label'   => __( 'Source', 'uncoder' ),
				'default' => 'youtube',
				'options' => array(
					'youtube'  => 'YouTube',
					'vimeo'    => 'Vimeo',
					'hosted'   => __( 'Media library', 'uncoder' ),
					'external' => __( 'External file URL', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'youtube_url',
			array(
				'type'        => 'text',
				'label'       => __( 'YouTube URL', 'uncoder' ),
				'default'     => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
				'placeholder' => 'https://www.youtube.com/watch?v=…',
				'dynamic'     => true,
				'condition'   => array( 'source' => 'youtube' ),
			)
		);
		$this->add_control(
			'vimeo_url',
			array(
				'type'        => 'text',
				'label'       => __( 'Vimeo URL', 'uncoder' ),
				'default'     => 'https://vimeo.com/1084537',
				'placeholder' => 'https://vimeo.com/…',
				'dynamic'     => true,
				'condition'   => array( 'source' => 'vimeo' ),
			)
		);
		$this->add_control(
			'hosted',
			array(
				'type'      => 'media',
				'label'     => __( 'Video file', 'uncoder' ),
				'types'     => array( 'video' ),
				'default'   => array( 'id' => 0, 'url' => '' ),
				'dynamic'   => true,
				'condition' => array( 'source' => 'hosted' ),
				'ai'        => 'An MP4/WebM attachment from the media library: {"id": attachment_id}.',
			)
		);
		$this->add_control(
			'external_url',
			array(
				'type'        => 'text',
				'label'       => __( 'File URL', 'uncoder' ),
				'placeholder' => 'https://example.com/video.mp4',
				'dynamic'     => true,
				'condition'   => array( 'source' => 'external' ),
			)
		);
		$this->add_control(
			'video_title',
			array(
				'type'        => 'text',
				'label'       => __( 'Accessible title', 'uncoder' ),
				'placeholder' => __( 'Product tour', 'uncoder' ),
				'description' => __( 'Names the play button and player for screen readers.', 'uncoder' ),
				'dynamic'     => true,
			)
		);
		$this->add_responsive_control(
			'aspect_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Aspect ratio', 'uncoder' ),
				'default'   => '16/9',
				'options'   => self::RATIOS,
				'selectors' => array( '{{WRAPPER}}' => 'aspect-ratio: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'playback', array( 'label' => __( 'Playback', 'uncoder' ) ) );
		$this->add_control(
			'start',
			array(
				'type'        => 'number',
				'label'       => __( 'Start at (seconds)', 'uncoder' ),
				'min'         => 0,
				'step'        => 1,
				'placeholder' => '0',
			)
		);
		$this->add_control(
			'end',
			array(
				'type'      => 'number',
				'label'     => __( 'End at (seconds)', 'uncoder' ),
				'min'       => 0,
				'step'      => 1,
				'condition' => array( 'source!' => 'vimeo' ),
			)
		);
		$this->add_control(
			'autoplay',
			array(
				'type'        => 'switch',
				'label'       => __( 'Autoplay', 'uncoder' ),
				'description' => __( 'Starts muted when the video scrolls into view. Skipped for visitors who prefer reduced motion.', 'uncoder' ),
			)
		);
		$this->add_control(
			'mute',
			array(
				'type'  => 'switch',
				'label' => __( 'Mute', 'uncoder' ),
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
			'controls',
			array(
				'type'    => 'switch',
				'label'   => __( 'Player controls', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'privacy',
			array(
				'type'        => 'switch',
				'label'       => __( 'Privacy mode', 'uncoder' ),
				'description' => __( 'Uses youtube-nocookie.com and Vimeo "do not track".', 'uncoder' ),
				'default'     => true,
				'condition'   => array( 'source' => array( 'youtube', 'vimeo' ) ),
			)
		);
		$this->add_control(
			'facade',
			array(
				'type'        => 'switch',
				'label'       => __( 'Load player on click', 'uncoder' ),
				'description' => __( 'Shows a poster and play button; the player (and its cookies) load only when played.', 'uncoder' ),
				'default'     => true,
				'condition'   => array( 'source' => array( 'youtube', 'vimeo' ) ),
			)
		);
		$this->add_control(
			'poster',
			array(
				'type'        => 'media',
				'label'       => __( 'Poster image', 'uncoder' ),
				'default'     => array( 'id' => 0, 'url' => '' ),
				'description' => __( 'Shown before playback. YouTube videos use their own thumbnail when empty.', 'uncoder' ),
				'dynamic'     => true,
			)
		);
		$this->add_control(
			'play_icon',
			array(
				'type'    => 'icon',
				'label'   => __( 'Play icon', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'play' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_video', array( 'label' => __( 'Video', 'uncoder' ), 'tab' => 'style' ) );
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
		$this->add_control(
			'overlay_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Poster overlay', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-video-overlay: {{VALUE}}' ),
			)
		);
		$this->add_group( 'poster_filters', array( 'type' => 'css_filters', 'label' => __( 'Poster filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-video__poster' ) );
		$this->end_section();

		$this->start_section( 'style_play', array( 'label' => __( 'Play button', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'play_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 32, 'max' => 160 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-video-play: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'play_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-video__play' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'play_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'play_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video__play' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'play_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video__play' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'play_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video__facade:is(:hover, :focus-visible) .uncoder-video__play' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'play_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video__facade:is(:hover, :focus-visible) .uncoder-video__play' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_group( 'play_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-video__play' ) );
		$this->end_section();
	}

	/**
	 * YouTube video id from a URL or a bare id ('' when not recognised).
	 */
	public static function youtube_id( string $url ): string {
		$url = trim( $url );
		if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $url ) ) {
			return $url;
		}
		if ( preg_match( '#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^\#]*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Vimeo id and private hash ("h") from a URL.
	 *
	 * @return array{0:string,1:string}
	 */
	public static function vimeo_id( string $url ): array {
		$url = trim( $url );
		if ( ctype_digit( $url ) ) {
			return array( $url, '' );
		}
		if ( ! preg_match( '#vimeo\.com/(?:.*?/)?(?:video/)?(\d{3,})(?:/([A-Za-z0-9]+))?#i', $url, $m ) ) {
			return array( '', '' );
		}
		$hash = $m[2] ?? '';
		if ( '' === $hash ) {
			$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
			parse_str( $query, $args );
			$hash = isset( $args['h'] ) && is_string( $args['h'] ) ? $args['h'] : '';
		}
		return array( $m[1], (string) preg_replace( '/[^A-Za-z0-9]/', '', $hash ) );
	}

	/**
	 * Player embed URL for YouTube / Vimeo ('' when the URL is not recognised).
	 *
	 * @param array<string,mixed> $s     Settings.
	 * @param bool                $click Built for click-to-play (always autoplays).
	 */
	private function embed_url( array $s, bool $click ): string {
		$start    = is_numeric( $s['start'] ?? null ) ? max( 0, (int) $s['start'] ) : 0;
		$end      = is_numeric( $s['end'] ?? null ) ? max( 0, (int) $s['end'] ) : 0;
		$autoplay = $click || ! empty( $s['autoplay'] );
		$mute     = ! empty( $s['mute'] ) || ! empty( $s['autoplay'] );
		$privacy  = ! empty( $s['privacy'] );

		if ( 'vimeo' === ( $s['source'] ?? '' ) ) {
			list( $id, $hash ) = self::vimeo_id( (string) ( $s['vimeo_url'] ?? '' ) );
			if ( '' === $id ) {
				return '';
			}
			$args = array_filter(
				array(
					'h'        => $hash,
					'autoplay' => $autoplay ? '1' : '',
					'muted'    => $mute ? '1' : '',
					'loop'     => ! empty( $s['loop'] ) ? '1' : '',
					'controls' => empty( $s['controls'] ) ? '0' : '',
					'dnt'      => $privacy ? '1' : '',
					'title'    => '0',
					'byline'   => '0',
					'portrait' => '0',
				),
				static function ( $v ) {
					return '' !== $v;
				}
			);
			return add_query_arg( $args, 'https://player.vimeo.com/video/' . $id ) . ( $start ? '#t=' . $start . 's' : '' );
		}

		$id = self::youtube_id( (string) ( $s['youtube_url'] ?? '' ) );
		if ( '' === $id ) {
			return '';
		}
		$args = array_filter(
			array(
				'autoplay'    => $autoplay ? '1' : '',
				'mute'        => $mute ? '1' : '',
				'loop'        => ! empty( $s['loop'] ) ? '1' : '',
				'playlist'    => ! empty( $s['loop'] ) ? $id : '',
				'controls'    => empty( $s['controls'] ) ? '0' : '',
				'start'       => $start ? (string) $start : '',
				'end'         => $end > $start ? (string) $end : '',
				'rel'         => '0',
				'playsinline' => '1',
			),
			static function ( $v ) {
				return '' !== $v;
			}
		);
		return add_query_arg( $args, ( $privacy ? 'https://www.youtube-nocookie.com/embed/' : 'https://www.youtube.com/embed/' ) . $id );
	}

	/**
	 * Accessible title for the player.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function player_title( array $s ): string {
		$title = trim( (string) ( $s['video_title'] ?? '' ) );
		if ( '' !== $title ) {
			return $title;
		}
		switch ( $s['source'] ?? 'youtube' ) {
			case 'vimeo':
				return __( 'Vimeo video player', 'uncoder' );
			case 'youtube':
				return __( 'YouTube video player', 'uncoder' );
			default:
				return __( 'Video player', 'uncoder' );
		}
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function placeholder( array $s, Render_Context $ctx, string $message ): void {
		if ( ! $ctx->editor ) {
			return;
		}
		echo '<div class="uncoder-video uncoder-video--empty">' . $this->render_icon( 'circle-play' ) . '<span>' . esc_html( $message ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$source = in_array( $s['source'] ?? 'youtube', array( 'youtube', 'vimeo', 'hosted', 'external' ), true ) ? (string) $s['source'] : 'youtube';
		$title  = $this->player_title( $s );

		if ( 'hosted' === $source || 'external' === $source ) {
			$this->render_file( $s, $ctx, $source, $title );
			return;
		}

		$player = $this->embed_url( $s, false );
		if ( '' === $player ) {
			$this->placeholder( $s, $ctx, 'vimeo' === $source ? __( 'Paste a Vimeo link in the settings.', 'uncoder' ) : __( 'Paste a YouTube link in the settings.', 'uncoder' ) );
			return;
		}
		$iframe = array(
			'class'           => 'uncoder-video__iframe',
			'src'             => $player,
			'title'           => $title,
			'loading'         => 'lazy',
			'allow'           => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen',
			'referrerpolicy'  => 'strict-origin-when-cross-origin',
			'allowfullscreen' => true,
		);

		if ( empty( $s['facade'] ) ) {
			echo '<div class="uncoder-video uncoder-video--' . esc_attr( $source ) . '"><iframe' . Utils::attrs( $iframe ) . '></iframe></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
			return;
		}

		// Facade: poster + play button; the module swaps in the player on click.
		$poster = $this->image( $s['poster'] ?? array(), 'large', array( 'class' => 'uncoder-video__poster', 'alt' => '' ) );
		if ( '' === $poster && 'youtube' === $source ) {
			$poster = '<img' . Utils::attrs(
				array(
					'class'          => 'uncoder-video__poster',
					'src'            => 'https://i.ytimg.com/vi/' . self::youtube_id( (string) ( $s['youtube_url'] ?? '' ) ) . '/hqdefault.jpg',
					'alt'            => '',
					'loading'        => 'lazy',
					'decoding'       => 'async',
					'referrerpolicy' => 'no-referrer',
				)
			) . '>';
		}
		$button = array(
			'type'           => 'button',
			'class'          => 'uncoder-video__facade',
			'data-uncoder-embed' => $this->embed_url( $s, true ),
			'data-title'     => $title,
			'data-autoplay'  => ! empty( $s['autoplay'] ) ? 'true' : null,
			/* translators: %s: video title. */
			'aria-label'     => '' !== trim( (string) ( $s['video_title'] ?? '' ) ) ? sprintf( __( 'Play video: %s', 'uncoder' ), $title ) : __( 'Play video', 'uncoder' ),
		);
		$icon = $this->has_icon( $s['play_icon'] ?? null ) ? $this->render_icon( $s['play_icon'] ) : $this->render_icon( 'play' );

		echo '<div class="' . esc_attr( 'uncoder-video uncoder-video--' . $source . ' uncoder-video--facade' . ( '' === $poster ? ' uncoder-video--no-poster' : '' ) ) . '">';
		echo '<button' . Utils::attrs( $button ) . '>' . $poster . '<span class="uncoder-video__play" aria-hidden="true">' . $icon . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes, image/icon markup escaped.
		echo '<noscript><iframe' . Utils::attrs( $iframe ) . '></iframe></noscript>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		echo '</div>';
	}

	/**
	 * Self-hosted or external video file rendered with the native player.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function render_file( array $s, Render_Context $ctx, string $source, string $title ): void {
		if ( 'hosted' === $source ) {
			$media = is_array( $s['hosted'] ?? null ) ? $s['hosted'] : array();
			$url   = ! empty( $media['id'] ) ? (string) wp_get_attachment_url( (int) $media['id'] ) : '';
			$url   = '' !== $url ? $url : (string) ( $media['url'] ?? '' );
		} else {
			$url = (string) ( $s['external_url'] ?? '' );
		}
		$url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			$this->placeholder( $s, $ctx, 'hosted' === $source ? __( 'Choose a video file in the settings.', 'uncoder' ) : __( 'Enter a video file URL in the settings.', 'uncoder' ) );
			return;
		}
		$start = is_numeric( $s['start'] ?? null ) ? max( 0, (int) $s['start'] ) : 0;
		$end   = is_numeric( $s['end'] ?? null ) ? max( 0, (int) $s['end'] ) : 0;
		if ( $start || $end > $start ) {
			$url .= '#t=' . $start . ( $end > $start ? ',' . $end : '' );
		}
		$poster   = is_array( $s['poster'] ?? null ) && ! empty( $s['poster']['url'] ) ? (string) $s['poster']['url'] : '';
		$autoplay = ! empty( $s['autoplay'] ) && ! $ctx->editor;
		$video    = array(
			'class'       => 'uncoder-video__el',
			'src'         => $url,
			'poster'      => '' !== $poster ? $poster : null,
			'controls'    => ! empty( $s['controls'] ),
			'autoplay'    => $autoplay,
			'muted'       => ! empty( $s['mute'] ) || $autoplay,
			'loop'        => ! empty( $s['loop'] ),
			'playsinline' => true,
			'preload'     => '' !== $poster ? 'none' : 'metadata',
			'aria-label'  => $title,
		);
		echo '<div class="uncoder-video uncoder-video--' . esc_attr( $source ) . '"><video' . Utils::attrs( $video ) . '></video></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
	}
}
