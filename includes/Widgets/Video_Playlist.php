<?php
/**
 * Video Playlist widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * A player and a list of YouTube / Vimeo / self-hosted videos. The first embed renders as a facade
 * (poster + play button, no third-party request); the "video-playlist" module injects the players.
 * Choosing another video in the list starts it right away: that click is the visitor asking for it.
 */
class Video_Playlist extends Widget_Base {

	private const ROOT   = '{{WRAPPER}}';
	private const PLAYER = '{{WRAPPER}} .uncoder-video-playlist__player';
	private const PANEL  = '{{WRAPPER}} .uncoder-video-playlist__panel';
	private const BUTTON = '{{WRAPPER}} .uncoder-video-playlist__button';
	private const THUMB  = '{{WRAPPER}} .uncoder-video-playlist__thumb';

	/** CSS variables per list position (consumed by widgets/video-playlist.css). */
	public const POSITIONS = array(
		'right' => '--uncoder-vpl-cols:minmax(0,1fr) var(--uncoder-vpl-list-width);--uncoder-vpl-list-order:0;--uncoder-vpl-list-pos:absolute;--uncoder-vpl-list-max:none',
		'left'  => '--uncoder-vpl-cols:var(--uncoder-vpl-list-width) minmax(0,1fr);--uncoder-vpl-list-order:-1;--uncoder-vpl-list-pos:absolute;--uncoder-vpl-list-max:none',
		'below' => '--uncoder-vpl-cols:minmax(0,1fr);--uncoder-vpl-list-order:0;--uncoder-vpl-list-pos:relative;--uncoder-vpl-list-max:var(--uncoder-vpl-list-height)',
	);

	/** Video file extensions recognised in a plain URL. */
	private const FILE_EXT = '/\.(?:mp4|m4v|webm|ogv|ogg|mov)$/i';

	public function name(): string {
		return 'video-playlist';
	}

	public function title(): string {
		return __( 'Video Playlist', 'uncoder' );
	}

	public function icon(): string {
		return 'list-video';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'video playlist', 'playlist', 'videos', 'youtube', 'vimeo', 'mp4', 'course', 'lessons', 'episodes', 'series', 'media' );
	}

	public function description(): string {
		return __( 'A video player with a clickable playlist of YouTube, Vimeo or self-hosted videos (title, thumbnail, duration and description per row). The list sits right, left or below the player; embeds load only when played, on privacy-enhanced domains, and the next video can start automatically.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'video-playlist' );
	}

	public function preset(): array {
		return array(
			'videos'               => array(
				array(
					'title'       => __( 'Sample video: part one', 'uncoder' ),
					'url'         => 'https://www.youtube.com/watch?v=I1YQho4pMC4',
					'duration'    => '0:22',
					'description' => __( 'Replace these sample rows with your own videos.', 'uncoder' ),
				),
				array(
					'title'       => __( 'Sample video: part two', 'uncoder' ),
					'url'         => 'https://www.youtube.com/watch?v=eRsGyueVLvQ',
					'duration'    => '14:48',
					'description' => __( 'Each row takes a YouTube, Vimeo or video file link.', 'uncoder' ),
				),
				array(
					'title'       => __( 'Sample video: part three', 'uncoder' ),
					'url'         => 'https://vimeo.com/1084537',
					'duration'    => '9:56',
					'description' => __( 'Vimeo rows look best with a thumbnail image.', 'uncoder' ),
				),
			),
			'list_position_mobile' => 'below',
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content_videos', array( 'label' => __( 'Videos', 'uncoder' ) ) );
		$this->add_control(
			'videos',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Videos', 'uncoder' ),
				'title_field' => 'title',
				'fields'      => array(
					'title'       => array(
						'type'    => 'text',
						'label'   => __( 'Title', 'uncoder' ),
						'default' => __( 'Video', 'uncoder' ),
					),
					'url'         => array(
						'type'        => 'text',
						'label'       => __( 'Video URL', 'uncoder' ),
						'placeholder' => 'https://www.youtube.com/watch?v=…',
						'description' => __( 'A YouTube or Vimeo link, or the URL of an MP4 / WebM file.', 'uncoder' ),
					),
					'file'        => array(
						'type'        => 'media',
						'label'       => __( 'Or a video file', 'uncoder' ),
						'types'       => array( 'video' ),
						'description' => __( 'A video from the media library (used instead of the URL).', 'uncoder' ),
					),
					'thumbnail'   => array(
						'type'        => 'media',
						'label'       => __( 'Thumbnail', 'uncoder' ),
						'description' => __( 'YouTube videos use their own thumbnail when empty.', 'uncoder' ),
					),
					'duration'    => array(
						'type'        => 'text',
						'label'       => __( 'Duration', 'uncoder' ),
						'placeholder' => '4:32',
					),
					'description' => array(
						'type'  => 'textarea',
						'label' => __( 'Description', 'uncoder' ),
						'rows'  => 2,
					),
				),
				'default'     => array(),
				'ai'          => 'Rows: {"title":"…","url":"https://www.youtube.com/watch?v=… | https://vimeo.com/… | https://…/clip.mp4","thumbnail":{"id":…},"duration":"4:32","description":"optional one-liner"}. A media-library video goes in "file":{"id":…} instead of "url". YouTube rows get their thumbnail automatically; give Vimeo and file rows a thumbnail. Never invent video URLs: ask the user.',
			)
		);
		$this->add_control(
			'playlist_title',
			array(
				'type'        => 'text',
				'label'       => __( 'Playlist title', 'uncoder' ),
				'default'     => __( 'Playlist', 'uncoder' ),
				'description' => __( 'Shown above the list and names it for screen readers.', 'uncoder' ),
				'inline'      => true,
			)
		);
		$this->add_control(
			'show_count',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show position (2 / 5)', 'uncoder' ),
				'default' => true,
			)
		);
		$this->end_section();

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_responsive_control(
			'list_position',
			array(
				'type'                 => 'choose',
				'label'                => __( 'List position', 'uncoder' ),
				'default'              => 'right',
				'options'              => array(
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'panel-left' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'panel-right' ),
					'below' => array( 'label' => __( 'Below', 'uncoder' ), 'icon' => 'panel-bottom' ),
				),
				'selectors_dictionary' => self::POSITIONS,
				'selectors'            => array( self::ROOT => '{{VALUE}}' ),
				'ai'                   => 'Common: "right" on desktop with list_position_mobile "below".',
			)
		);
		$this->add_responsive_control(
			'list_width',
			array(
				'type'        => 'slider',
				'label'       => __( 'List width', 'uncoder' ),
				'description' => __( 'When the list sits beside the player.', 'uncoder' ),
				'size_units'  => array( 'px', '%', 'rem' ),
				'range'       => array( 'px' => array( 'min' => 180, 'max' => 640 ) ),
				'selectors'   => array( self::ROOT => '--uncoder-vpl-list-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'list_height',
			array(
				'type'        => 'slider',
				'label'       => __( 'List height', 'uncoder' ),
				'description' => __( 'Maximum height when the list sits below the player (it scrolls beyond). Beside the player the list matches the player height.', 'uncoder' ),
				'size_units'  => array( 'px', 'vh', 'rem' ),
				'range'       => array( 'px' => array( 'min' => 120, 'max' => 1000 ) ),
				'selectors'   => array( self::ROOT => '--uncoder-vpl-list-height: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'aspect_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Player aspect ratio', 'uncoder' ),
				'default'   => '16/9',
				'options'   => Video::RATIOS,
				'selectors' => array( self::PLAYER => 'aspect-ratio: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'show_thumbnails',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show thumbnails', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'show_duration',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show durations', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'show_description',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show descriptions', 'uncoder' ),
				'default' => true,
			)
		);
		$this->end_section();

		$this->start_section( 'content_playback', array( 'label' => __( 'Playback', 'uncoder' ) ) );
		$this->add_control(
			'autoplay_next',
			array(
				'type'        => 'switch',
				'label'       => __( 'Play the next video automatically', 'uncoder' ),
				'description' => __( 'When a video ends, the next one in the list starts.', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'loop',
			array(
				'type'      => 'switch',
				'label'     => __( 'Loop the playlist', 'uncoder' ),
				'condition' => array( 'autoplay_next' => true ),
			)
		);
		$this->add_control(
			'privacy',
			array(
				'type'        => 'switch',
				'label'       => __( 'Privacy mode', 'uncoder' ),
				'description' => __( 'Uses youtube-nocookie.com and Vimeo "do not track".', 'uncoder' ),
				'default'     => true,
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

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_player', array( 'label' => __( 'Player', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap to the list', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( self::ROOT => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'player_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( self::PLAYER => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'player_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::PLAYER ) );
		$this->add_control( 'play_heading', array( 'type' => 'heading', 'label' => __( 'Play button', 'uncoder' ) ) );
		$this->add_responsive_control(
			'play_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 32, 'max' => 160 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-vpl-play: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'play_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__play' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'play_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__play' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'play_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__facade:is(:hover, :focus-visible) .uncoder-video-playlist__play' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_list', array( 'label' => __( 'List', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'list_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::PANEL => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'list_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::PANEL => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'list_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( self::PANEL => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'list_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::PANEL ) );
		$this->add_responsive_control(
			'item_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between items', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-video-playlist__list' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'item_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Item padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::BUTTON => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'item_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Item radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( self::BUTTON => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'item_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'item_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( self::BUTTON => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'item_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::BUTTON => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'item_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__button:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'item_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__button:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Active', 'uncoder' ) );
		$this->add_control(
			'item_active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__button.is-active' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'item_active_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__button.is-active' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'item_accent',
			array(
				'type'      => 'color',
				'label'     => __( 'Indicator color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-vpl-accent: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'heading_typography', array( 'type' => 'typography', 'label' => __( 'Playlist title', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-video-playlist__heading' ) );
		$this->add_control(
			'heading_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Playlist title color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__header' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Video titles', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-video-playlist__title' ) );
		$this->add_group( 'description_typography', array( 'type' => 'typography', 'label' => __( 'Descriptions', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-video-playlist__desc' ) );
		$this->add_control(
			'description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Description color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__desc' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'duration_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Duration color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__duration' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'duration_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Duration background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-video-playlist__duration' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_thumbnail',
			array(
				'label'     => __( 'Thumbnails', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_thumbnails' => true ),
			)
		);
		$this->add_responsive_control(
			'thumb_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 48, 'max' => 320 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-vpl-thumb: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'thumb_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( self::THUMB => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array(
			'data-settings' => $this->json_attr(
				array(
					'next'    => ! empty( $s['autoplay_next'] ),
					'loop'    => ! empty( $s['autoplay_next'] ) && ! empty( $s['loop'] ),
					/* translators: 1: video title, 2: position, 3: number of videos. */
					'playing' => __( 'Now playing: %1$s (%2$d of %3$d)', 'uncoder' ),
				)
			),
		);
	}

	/* ------------------------------------------------------------ URLs */

	/**
	 * Start offset in seconds from ?t= / ?start= / #t= (90, "1m30s").
	 */
	private static function start_time( string $url ): int {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $args );
		$t = $args['t'] ?? $args['start'] ?? '';
		if ( ! is_string( $t ) || '' === $t ) {
			$fragment = (string) wp_parse_url( $url, PHP_URL_FRAGMENT );
			$t        = preg_match( '/^t=([0-9hms]+)$/', $fragment, $m ) ? $m[1] : '';
		}
		if ( ctype_digit( $t ) ) {
			return (int) $t;
		}
		if ( '' !== $t && preg_match( '/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $t, $m ) ) {
			return (int) ( $m[1] ?? 0 ) * 3600 + (int) ( $m[2] ?? 0 ) * 60 + (int) ( $m[3] ?? 0 );
		}
		return 0;
	}

	/**
	 * What a row plays: [ kind, player URL, fallback URL for <noscript> ] or null when unrecognised.
	 * Embed URLs always autoplay: players are only created after the visitor asked for one.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return array{0:string,1:string,2:string,3:string}|null kind, src, noscript src, YouTube id.
	 */
	private function source( array $row, bool $privacy, bool $api ): ?array {
		$media = is_array( $row['file'] ?? null ) ? $row['file'] : array();
		$file  = ! empty( $media['id'] ) ? (string) wp_get_attachment_url( (int) $media['id'] ) : '';
		$file  = '' !== $file ? $file : (string) ( $media['url'] ?? '' );
		$url   = trim( (string) ( $row['url'] ?? '' ) );

		if ( '' === $file && '' !== $url ) {
			$youtube = Video::youtube_id( $url );
			if ( '' !== $youtube ) {
				$start = self::start_time( $url );
				$args  = self::args(
					array(
						'rel'         => '0',
						'playsinline' => '1',
						'start'       => $start ? (string) $start : '',
						'enablejsapi' => $api ? '1' : '',
					)
				);
				$base  = ( $privacy ? 'https://www.youtube-nocookie.com/embed/' : 'https://www.youtube.com/embed/' ) . $youtube;
				return array( 'youtube', add_query_arg( array_merge( array( 'autoplay' => '1' ), $args ), $base ), add_query_arg( $args, $base ), $youtube );
			}
			list( $vimeo, $hash ) = Video::vimeo_id( $url );
			if ( '' !== $vimeo ) {
				$start = self::start_time( $url );
				$args  = self::args(
					array(
						'h'        => $hash,
						'dnt'      => $privacy ? '1' : '',
						'title'    => '0',
						'byline'   => '0',
						'portrait' => '0',
					)
				);
				$base  = 'https://player.vimeo.com/video/' . $vimeo;
				$at    = $start ? '#t=' . $start . 's' : '';
				return array( 'vimeo', add_query_arg( array_merge( array( 'autoplay' => '1' ), $args ), $base ) . $at, add_query_arg( $args, $base ) . $at, '' );
			}
			if ( preg_match( self::FILE_EXT, (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) {
				$file = $url;
			}
		}
		$file = esc_url_raw( trim( $file ), array( 'http', 'https' ) );
		return '' === $file ? null : array( 'file', $file, $file, '' );
	}

	/**
	 * Query arguments without the empty ones ("0" is a value).
	 *
	 * @param array<string,string> $args Arguments.
	 * @return array<string,string>
	 */
	private static function args( array $args ): array {
		return array_filter(
			$args,
			static function ( $v ) {
				return '' !== $v;
			}
		);
	}

	/**
	 * Image URL of a media value at a size ('' when empty).
	 *
	 * @param mixed $media Media value.
	 */
	private static function media_url( $media, string $size ): string {
		if ( ! is_array( $media ) ) {
			return '';
		}
		if ( ! empty( $media['id'] ) ) {
			$src = wp_get_attachment_image_url( (int) $media['id'], $size );
			if ( $src ) {
				return (string) $src;
			}
		}
		return (string) ( $media['url'] ?? '' );
	}

	/* ------------------------------------------------------------ Render */

	protected function render( array $s, Render_Context $ctx ): void {
		$privacy = ! empty( $s['privacy'] );
		$api     = ! empty( $s['autoplay_next'] );
		$items   = array();
		foreach ( Repeater_Rows::get( $this, 'videos', $s['videos'] ?? array() ) as $row ) {
			$source = $this->source( $row, $privacy, $api );
			if ( null === $source ) {
				continue;
			}
			$title   = trim( (string) ( $row['title'] ?? '' ) );
			$items[] = array(
				'row'    => $row,
				'kind'   => $source[0],
				'src'    => $source[1],
				'static' => $source[2],
				'yt'     => $source[3],
				/* translators: %d: position in the playlist. */
				'title'  => '' !== $title ? $title : sprintf( __( 'Video %d', 'uncoder' ), count( $items ) + 1 ),
			);
		}
		if ( ! $items ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-video-playlist uncoder-video-playlist--empty">' . $this->render_icon( 'list-video' ) . '<span>' . esc_html__( 'Add videos to the playlist (YouTube, Vimeo or video file links).', 'uncoder' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
			}
			return;
		}

		$thumbs  = ! empty( $s['show_thumbnails'] );
		$uid     = 'uncoder-vpl-' . sanitize_html_class( '' !== $ctx->element_id ? $ctx->element_id : wp_unique_id() );
		$heading = trim( (string) ( $s['playlist_title'] ?? '' ) );
		$count   = count( $items );
		$classes = array( 'uncoder-video-playlist', $thumbs ? 'uncoder-video-playlist--thumbs' : 'uncoder-video-playlist--no-thumbs' );

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		echo '<div class="uncoder-video-playlist__player">';
		$this->render_stage( $items[0], $s );
		echo '</div>';

		echo '<div class="uncoder-video-playlist__aside"><div class="uncoder-video-playlist__panel">';
		$show_count = ! empty( $s['show_count'] ) && $count > 1;
		if ( '' !== $heading || $show_count ) {
			echo '<div class="uncoder-video-playlist__header">';
			if ( '' !== $heading ) {
				echo '<span class="uncoder-video-playlist__heading" id="' . esc_attr( $uid . '-label' ) . '"' . $ctx->inline( 'playlist_title' ) . '>' . esc_html( $heading ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
			}
			if ( $show_count ) {
				echo '<span class="uncoder-video-playlist__count" aria-hidden="true"><span class="uncoder-video-playlist__position">1</span> / ' . esc_html( (string) $count ) . '</span>';
			}
			echo '</div>';
		}
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		echo '<ol' . Utils::attrs(
			array(
				'class'           => 'uncoder-video-playlist__list',
				'aria-labelledby' => '' !== $heading ? $uid . '-label' : null,
				'aria-label'      => '' === $heading ? __( 'Playlist', 'uncoder' ) : null,
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $items as $i => $item ) {
			$this->render_item( $item, $i, $s );
		}
		echo '</ol></div></div>';
		echo '<span class="uncoder-sr-only uncoder-video-playlist__status" role="status"></span>';
		echo '</div>';
	}

	/**
	 * The first video: a facade for embeds, the native player for files.
	 *
	 * @param array<string,mixed> $item Item.
	 * @param array<string,mixed> $s    Settings.
	 */
	private function render_stage( array $item, array $s ): void {
		$row = $item['row'];
		if ( 'file' === $item['kind'] ) {
			$poster = esc_url_raw( self::media_url( $row['thumbnail'] ?? null, 'large' ), array( 'http', 'https' ) );
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
			echo '<video' . Utils::attrs(
				array(
					'class'       => 'uncoder-video-playlist__video',
					'src'         => $item['src'],
					'poster'      => '' !== $poster ? $poster : null,
					'controls'    => true,
					'playsinline' => true,
					'preload'     => '' !== $poster ? 'none' : 'metadata',
					'aria-label'  => $item['title'],
				)
			) . '></video>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$poster = $this->image( $row['thumbnail'] ?? null, 'large', array( 'class' => 'uncoder-video-playlist__poster', 'alt' => '' ) );
		if ( '' === $poster && '' !== $item['yt'] ) {
			$poster = '<img' . Utils::attrs(
				array(
					'class'          => 'uncoder-video-playlist__poster',
					'src'            => Video::youtube_poster( $item['yt'] ),
					'alt'            => '',
					'decoding'       => 'async',
					'referrerpolicy' => 'no-referrer',
				)
			) . '>';
		}
		$icon = $this->has_icon( $s['play_icon'] ?? null ) ? $this->render_icon( $s['play_icon'] ) : $this->render_icon( 'play' );
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes, image/icon markup.
		echo '<button' . Utils::attrs(
			array(
				'type'       => 'button',
				'class'      => array( 'uncoder-video-playlist__facade', '' === $poster ? 'uncoder-video-playlist__facade--no-poster' : '' ),
				/* translators: %s: video title. */
				'aria-label' => sprintf( __( 'Play video: %s', 'uncoder' ), $item['title'] ),
			)
		) . '>' . $poster . '<span class="uncoder-video-playlist__play" aria-hidden="true">' . $icon . '</span></button>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		echo '<noscript><iframe' . Utils::attrs(
			array(
				'class'           => 'uncoder-video-playlist__iframe',
				'src'             => $item['static'],
				'title'           => $item['title'],
				'loading'         => 'lazy',
				'allow'           => 'autoplay; encrypted-media; picture-in-picture; fullscreen',
				'referrerpolicy'  => 'strict-origin-when-cross-origin',
				'allowfullscreen' => true,
			)
		) . '></iframe></noscript>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * One list entry: a button holding everything the module needs to play the video.
	 *
	 * @param array<string,mixed> $item Item.
	 * @param array<string,mixed> $s    Settings.
	 */
	private function render_item( array $item, int $i, array $s ): void {
		$row      = $item['row'];
		$active   = 0 === $i;
		$duration = ! empty( $s['show_duration'] ) ? trim( (string) ( $row['duration'] ?? '' ) ) : '';
		$desc     = ! empty( $s['show_description'] ) ? trim( (string) ( $row['description'] ?? '' ) ) : '';
		$poster   = 'file' === $item['kind'] ? esc_url_raw( self::media_url( $row['thumbnail'] ?? null, 'large' ), array( 'http', 'https' ) ) : '';
		$row_id   = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );

		echo '<li class="' . esc_attr( 'uncoder-video-playlist__item' . ( '' !== $row_id ? ' uncoder-ri-' . $row_id : '' ) ) . '">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		echo '<button' . Utils::attrs(
			array(
				'type'         => 'button',
				'class'        => array( 'uncoder-video-playlist__button', $active ? 'is-active' : '' ),
				'aria-current' => $active ? 'true' : null,
				'data-kind'    => $item['kind'],
				'data-src'     => $item['src'],
				'data-poster'  => '' !== $poster ? $poster : null,
				'data-title'   => $item['title'],
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $s['show_thumbnails'] ) ) {
			$img = $this->image( $row['thumbnail'] ?? null, 'medium', array( 'class' => 'uncoder-video-playlist__img', 'alt' => '' ) );
			if ( '' === $img && '' !== $item['yt'] ) {
				$img = '<img' . Utils::attrs(
					array(
						'class'          => 'uncoder-video-playlist__img',
						'src'            => 'https://i.ytimg.com/vi/' . $item['yt'] . '/mqdefault.jpg',
						'alt'            => '',
						'loading'        => 'lazy',
						'decoding'       => 'async',
						'referrerpolicy' => 'no-referrer',
					)
				) . '>';
			}
			echo '<span class="' . esc_attr( 'uncoder-video-playlist__thumb' . ( '' === $img ? ' uncoder-video-playlist__thumb--empty' : '' ) ) . '">';
			echo '' !== $img ? $img : $this->render_icon( 'film' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- image/icon markup.
			echo '<span class="uncoder-video-playlist__now" aria-hidden="true">' . $this->render_icon( 'play' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
			if ( '' !== $duration ) {
				echo '<span class="uncoder-video-playlist__duration">' . esc_html( $duration ) . '</span>';
			}
			echo '</span>';
		} else {
			echo '<span class="uncoder-video-playlist__index" aria-hidden="true">' . esc_html( (string) ( $i + 1 ) ) . '</span>';
		}

		echo '<span class="uncoder-video-playlist__text"><span class="uncoder-video-playlist__title">' . esc_html( $item['title'] ) . '</span>';
		if ( '' !== $desc ) {
			echo '<span class="uncoder-video-playlist__desc">' . esc_html( $desc ) . '</span>';
		}
		if ( '' !== $duration && empty( $s['show_thumbnails'] ) ) {
			echo '<span class="uncoder-video-playlist__duration">' . esc_html( $duration ) . '</span>';
		}
		echo '</span></button></li>';
	}
}
