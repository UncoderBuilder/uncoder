<?php
/**
 * SoundCloud widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A SoundCloud track or playlist in SoundCloud's widget player (w.soundcloud.com iframe). The player
 * waits behind a click-to-load facade drawn locally (title, a waveform, a privacy note); the shared
 * "embed-facade" module swaps in the iframe, already playing, on click.
 */
class Soundcloud extends Widget_Base {

	private const HOSTS = array( 'soundcloud.com', 'www.soundcloud.com', 'm.soundcloud.com', 'on.soundcloud.com', 'api.soundcloud.com' );

	public function name(): string {
		return 'soundcloud';
	}

	public function title(): string {
		return __( 'SoundCloud', 'uncoder' );
	}

	public function icon(): string {
		return 'audio-lines';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'soundcloud', 'audio', 'music', 'podcast', 'track', 'playlist', 'player', 'embed', 'sound' );
	}

	public function description(): string {
		return __( 'A SoundCloud track or playlist player, visual (large artwork) or classic. Nothing loads from SoundCloud until the visitor clicks the play facade; height, button color and the comments / artwork / user / play count options are adjustable.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'embed-facade' );
	}

	public function preset(): array {
		return array(
			'url'    => 'https://soundcloud.com/forss/flickermood',
			'player' => 'visual',
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'SoundCloud', 'uncoder' ) ) );
		$this->add_control(
			'url',
			array(
				'type'        => 'text',
				'label'       => __( 'Track or playlist URL', 'uncoder' ),
				'default'     => '',
				'placeholder' => 'https://soundcloud.com/artist/track',
				'dynamic'     => true,
				'ai'          => 'A public soundcloud.com track URL, or a playlist (…/sets/…) URL. Ask the user for it; never invent one.',
			)
		);
		$this->add_control(
			'player',
			array(
				'type'    => 'choose',
				'label'   => __( 'Player', 'uncoder' ),
				'default' => 'visual',
				'options' => array(
					'visual'  => array( 'label' => __( 'Visual (large artwork)', 'uncoder' ), 'icon' => 'image' ),
					'classic' => array( 'label' => __( 'Classic (waveform)', 'uncoder' ), 'icon' => 'audio-lines' ),
				),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'type'        => 'slider',
				'label'       => __( 'Height', 'uncoder' ),
				'description' => __( 'Default: 300px for the visual player, 166px for a classic track, 450px for playlists.', 'uncoder' ),
				'size_units'  => array( 'px', 'vh', 'rem' ),
				'range'       => array( 'px' => array( 'min' => 80, 'max' => 900 ) ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-soundcloud-h: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'player_title',
			array(
				'type'        => 'text',
				'label'       => __( 'Title', 'uncoder' ),
				'placeholder' => __( 'Episode 12: Summer mix', 'uncoder' ),
				'description' => __( 'Shown on the play facade and read by screen readers. Defaults to a title made from the URL.', 'uncoder' ),
				'dynamic'     => true,
			)
		);
		$this->end_section();

		$this->start_section( 'options', array( 'label' => __( 'Player options', 'uncoder' ) ) );
		$this->add_control(
			'facade',
			array(
				'type'        => 'switch',
				'label'       => __( 'Load player on click', 'uncoder' ),
				'description' => __( 'Shows a lightweight preview; SoundCloud (and its cookies) load only when the visitor presses play, and playback starts right away.', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'autoplay',
			array(
				'type'        => 'switch',
				'label'       => __( 'Autoplay', 'uncoder' ),
				'description' => __( 'Without "Load player on click": start playing when the player loads. Most browsers block sound until the visitor interacts with the page.', 'uncoder' ),
				'condition'   => array( 'facade' => false ),
			)
		);
		$this->add_control(
			'color',
			array(
				'type'        => 'text',
				'label'       => __( 'Button color (hex)', 'uncoder' ),
				'default'     => 'ff5500',
				'placeholder' => 'ff5500',
				'description' => __( 'Hex color without #, used by the play button and the waveform.', 'uncoder' ),
				'ai'          => 'Six hex digits without "#", e.g. "ff5500".',
			)
		);
		$this->add_control(
			'show_artwork',
			array(
				'type'      => 'switch',
				'label'     => __( 'Show artwork', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'player' => 'classic' ),
			)
		);
		$this->add_control(
			'show_comments',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show comments', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'show_user',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show user name', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'show_playcount',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show play count', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'hide_related',
			array(
				'type'  => 'switch',
				'label' => __( 'Hide related tracks', 'uncoder' ),
			)
		);
		$this->add_control(
			'facade_note',
			array(
				'type'      => 'text',
				'label'     => __( 'Privacy note', 'uncoder' ),
				'default'   => __( 'Loads content from SoundCloud', 'uncoder' ),
				'condition' => array( 'facade' => true ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_player', array( 'label' => __( 'Player', 'uncoder' ), 'tab' => 'style' ) );
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
		$this->add_control( 'facade_heading', array( 'type' => 'heading', 'label' => __( 'Play facade', 'uncoder' ) ) );
		$this->add_control(
			'facade_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-soundcloud-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'facade_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-soundcloud-fg: {{VALUE}}' ),
			)
		);
		$this->add_group( 'facade_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-soundcloud__title' ) );
		$this->end_section();
	}

	/**
	 * Canonical https URL of a SoundCloud track / playlist ('' when it is not one).
	 */
	public static function track_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( ! in_array( $host, self::HOSTS, true ) || '' === trim( $path, '/' ) ) {
			return '';
		}
		if ( in_array( $host, array( 'www.soundcloud.com', 'm.soundcloud.com' ), true ) ) {
			$host = 'soundcloud.com';
		}
		// Tracking parameters are dropped; private links keep their secret token (a path segment).
		return esc_url_raw( 'https://' . $host . '/' . trim( $path, '/' ), array( 'https' ) );
	}

	/**
	 * Hex color without "#" (the SoundCloud default when invalid).
	 */
	private static function hex( $value ): string {
		$hex = ltrim( trim( (string) $value ), '#' );
		return preg_match( '/^(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $hex ) ? strtolower( $hex ) : 'ff5500';
	}

	/**
	 * [ user, title ] guessed from a soundcloud.com URL (empty strings for short or API links).
	 *
	 * @return array{0:string,1:string}
	 */
	private static function guess( string $url ): array {
		if ( 0 !== strpos( $url, 'https://soundcloud.com/' ) ) {
			return array( '', '' );
		}
		$parts  = explode( '/', trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) );
		$pretty = static function ( string $slug ): string {
			$text = trim( str_replace( array( '-', '_' ), ' ', rawurldecode( $slug ) ) );
			return function_exists( 'mb_convert_case' ) ? mb_convert_case( $text, MB_CASE_TITLE, 'UTF-8' ) : ucwords( $text );
		};
		$user  = $parts[0] ?? '';
		$slug  = 'sets' === ( $parts[1] ?? '' ) ? ( $parts[2] ?? '' ) : ( $parts[1] ?? '' );
		return array( $user, '' !== $slug ? $pretty( $slug ) : '' );
	}

	/**
	 * Player URL.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function player_url( string $track, array $s, bool $autoplay ): string {
		$visual = 'classic' !== ( $s['player'] ?? 'visual' );
		$flag   = static function ( $on ): string {
			return $on ? 'true' : 'false';
		};
		return add_query_arg(
			array(
				'url'            => rawurlencode( $track ),
				'color'          => rawurlencode( '#' . self::hex( $s['color'] ?? '' ) ),
				'auto_play'      => $flag( $autoplay ),
				'visual'         => $flag( $visual ),
				'show_artwork'   => $flag( $visual || ! empty( $s['show_artwork'] ) ),
				'show_comments'  => $flag( ! empty( $s['show_comments'] ) ),
				'show_user'      => $flag( ! empty( $s['show_user'] ) ),
				'show_playcount' => $flag( ! empty( $s['show_playcount'] ) ),
				'hide_related'   => $flag( ! empty( $s['hide_related'] ) ),
				'show_reposts'   => 'false',
				'show_teaser'    => 'true',
			),
			'https://w.soundcloud.com/player/'
		);
	}

	/**
	 * A waveform drawn from the URL (decorative, stable per track).
	 */
	private static function waveform( string $seed ): string {
		$state = crc32( $seed );
		$phase = ( $state % 628 ) / 100;
		$level = 0.5;
		$bars  = '';
		$count = 150;
		for ( $i = 0; $i < $count; $i++ ) {
			$state = ( $state * 1103515245 + 12345 ) & 0x7fffffff;
			// Smoothed noise over slow swells, quieter at both ends: reads as audio, not as a bar chart.
			$level = 0.55 * $level + 0.45 * ( ( $state % 1000 ) / 1000 );
			$swell = 0.55 + 0.3 * sin( $i / 11 + $phase ) + 0.15 * sin( $i / 3.7 );
			$fade  = min( 1, ( $i + 4 ) / 14, ( $count - $i + 3 ) / 14 );
			$h     = max( 3, round( 40 * min( 1, $swell * ( 0.25 + 0.9 * $level ) ) * $fade, 1 ) );
			$bars .= '<rect x="' . ( $i * 3 ) . '" y="' . ( 40 - $h ) . '" width="2" height="' . $h . '"/>';
		}
		return '<svg class="uncoder-soundcloud__wave" viewBox="0 0 ' . ( $count * 3 ) . ' 40" preserveAspectRatio="none" aria-hidden="true" focusable="false">' . $bars . '</svg>';
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array( 'style' => '--uncoder-soundcloud-accent:#' . self::hex( $s['color'] ?? '' ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$track = self::track_url( (string) ( $s['url'] ?? '' ) );
		if ( '' === $track ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-soundcloud uncoder-soundcloud--empty">' . $this->render_icon( 'audio-lines' ) . '<span>' . esc_html__( 'Paste a SoundCloud track or playlist link in the settings.', 'uncoder' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
			}
			return;
		}

		list( $user, $guess ) = self::guess( $track );
		$custom   = trim( (string) ( $s['player_title'] ?? '' ) );
		$name     = '' !== $custom ? $custom : ( '' !== $guess ? $guess : __( 'SoundCloud', 'uncoder' ) );
		/* translators: %s: track or playlist title. */
		$title    = sprintf( __( 'SoundCloud player: %s', 'uncoder' ), $name );
		$visual   = 'classic' !== ( $s['player'] ?? 'visual' );
		$playlist = false !== strpos( $track, '/sets/' );
		$classes  = array( 'uncoder-soundcloud', 'uncoder-soundcloud--' . ( $visual ? 'visual' : 'classic' ) );
		if ( $playlist ) {
			$classes[] = 'uncoder-soundcloud--playlist';
		}
		$iframe = array(
			'class'   => 'uncoder-soundcloud__iframe',
			'src'     => $this->player_url( $track, $s, ! empty( $s['autoplay'] ) && ! $ctx->editor ),
			'title'   => $title,
			'loading' => 'lazy',
			'allow'   => 'autoplay; encrypted-media',
		);

		if ( empty( $s['facade'] ) && ! $ctx->editor ) {
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><iframe' . Utils::attrs( $iframe ) . '></iframe></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
			return;
		}

		$note    = trim( (string) ( $s['facade_note'] ?? '' ) );
		$note_id = 'uncoder-sc-note-' . sanitize_html_class( '' !== $ctx->element_id ? $ctx->element_id : wp_unique_id() );
		$classes[] = 'uncoder-soundcloud--facade';

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		echo '<button' . Utils::attrs(
			array(
				'type'               => 'button',
				'class'              => 'uncoder-soundcloud__facade',
				'data-uncoder-embed' => $this->player_url( $track, $s, true ),
				'data-title'         => $title,
				/* translators: %s: track or playlist title. */
				'aria-label'         => sprintf( __( 'Play %s on SoundCloud', 'uncoder' ), $name ),
				'aria-describedby'   => '' !== $note ? $note_id : null,
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="uncoder-soundcloud__play" aria-hidden="true">' . $this->render_icon( 'play' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
		echo '<span class="uncoder-soundcloud__meta">';
		if ( '' !== $user && ! empty( $s['show_user'] ) ) {
			echo '<span class="uncoder-soundcloud__user">' . esc_html( $user ) . '</span>';
		}
		echo '<span class="uncoder-soundcloud__title">' . esc_html( $name ) . '</span>';
		echo '</span>';
		echo self::waveform( $track ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- numeric SVG markup.
		if ( '' !== $note ) {
			echo '<span class="uncoder-soundcloud__note" id="' . esc_attr( $note_id ) . '">' . $this->render_icon( 'cloud' ) . esc_html( $note ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
		}
		echo '</button>';
		echo '<noscript><iframe' . Utils::attrs( $iframe ) . '></iframe></noscript>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		echo '</div>';
	}
}
