<?php
/**
 * Facebook Embed widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Brand_Icons;

defined( 'ABSPATH' ) || exit;

/**
 * A Facebook page, post or video through Facebook's iframe plugins (plugins/page.php, post.php,
 * video.php; no JavaScript SDK). A locally drawn facade with a privacy note stands in until the
 * visitor clicks; the shared "embed-facade" module then loads the plugin at the size the box has.
 */
class Facebook_Embed extends Widget_Base {

	private const HOSTS = array( 'facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com', 'business.facebook.com', 'fb.watch', 'fb.com', 'www.fb.com' );

	/** Query arguments that identify content (everything else, e.g. tracking, is dropped). */
	private const KEEP_ARGS = array( 'story_fbid', 'id', 'v', 'fbid', 'set', 'type' );

	/** Width limits of Facebook's plugins ("min-max", empty = open), used when loading. */
	private const FIT_WIDTH = array(
		'page'  => '180-500',
		'post'  => '350-750',
		'video' => '220-',
	);

	public const RATIOS = array(
		'16/9' => '16:9',
		'9/16' => '9:16',
		'1/1'  => '1:1',
		'4/5'  => '4:5',
		'4/3'  => '4:3',
	);

	public function name(): string {
		return 'facebook-embed';
	}

	public function title(): string {
		return __( 'Facebook Embed', 'uncoder' );
	}

	public function icon(): string {
		return 'thumbs-up';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'facebook', 'fb', 'page', 'timeline', 'feed', 'post', 'video', 'social', 'embed', 'events' );
	}

	public function description(): string {
		return __( 'A Facebook page (timeline, events or messages tabs), a single post or a video, embedded with Facebook\'s iframe plugins (no SDK). A click-to-load facade with a short privacy note stands in, so nothing loads from Facebook until the visitor asks.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'embed-facade' );
	}

	public function preset(): array {
		return array(
			'embed_type' => 'page',
			'page_url'   => 'https://www.facebook.com/facebook',
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Facebook', 'uncoder' ) ) );
		$this->add_control(
			'embed_type',
			array(
				'type'    => 'select',
				'label'   => __( 'Embed', 'uncoder' ),
				'default' => 'page',
				'options' => array(
					'page'  => __( 'Page (timeline)', 'uncoder' ),
					'post'  => __( 'Post', 'uncoder' ),
					'video' => __( 'Video', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'page_url',
			array(
				'type'        => 'text',
				'label'       => __( 'Page URL', 'uncoder' ),
				'default'     => '',
				'placeholder' => 'https://www.facebook.com/yourpage',
				'dynamic'     => true,
				'condition'   => array( 'embed_type' => 'page' ),
				'ai'          => 'The public Facebook page address, e.g. https://www.facebook.com/nasa. Ask the user; never invent one.',
			)
		);
		$this->add_control(
			'post_url',
			array(
				'type'        => 'text',
				'label'       => __( 'Post URL', 'uncoder' ),
				'default'     => '',
				'placeholder' => 'https://www.facebook.com/yourpage/posts/…',
				'dynamic'     => true,
				'condition'   => array( 'embed_type' => 'post' ),
				'ai'          => 'The link of a public post (… → Copy link / Embed on Facebook).',
			)
		);
		$this->add_control(
			'video_url',
			array(
				'type'        => 'text',
				'label'       => __( 'Video URL', 'uncoder' ),
				'default'     => '',
				'placeholder' => 'https://www.facebook.com/yourpage/videos/…',
				'dynamic'     => true,
				'condition'   => array( 'embed_type' => 'video' ),
				'ai'          => 'The link of a public Facebook video or reel.',
			)
		);
		$this->add_control(
			'tabs',
			array(
				'type'        => 'multiselect',
				'label'       => __( 'Tabs', 'uncoder' ),
				'default'     => array( 'timeline' ),
				'options'     => array(
					'timeline' => __( 'Timeline', 'uncoder' ),
					'events'   => __( 'Events', 'uncoder' ),
					'messages' => __( 'Messages', 'uncoder' ),
				),
				'description' => __( 'Leave empty to show only the page header.', 'uncoder' ),
				'condition'   => array( 'embed_type' => 'page' ),
			)
		);
		$this->add_control(
			'small_header',
			array(
				'type'      => 'switch',
				'label'     => __( 'Small header', 'uncoder' ),
				'condition' => array( 'embed_type' => 'page' ),
			)
		);
		$this->add_control(
			'hide_cover',
			array(
				'type'      => 'switch',
				'label'     => __( 'Hide cover photo', 'uncoder' ),
				'condition' => array( 'embed_type' => 'page' ),
			)
		);
		$this->add_control(
			'show_facepile',
			array(
				'type'      => 'switch',
				'label'     => __( 'Show friends who like the page', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'embed_type' => 'page' ),
			)
		);
		$this->add_control(
			'hide_cta',
			array(
				'type'      => 'switch',
				'label'     => __( 'Hide the call-to-action button', 'uncoder' ),
				'condition' => array( 'embed_type' => 'page' ),
			)
		);
		$this->add_control(
			'post_text',
			array(
				'type'      => 'switch',
				'label'     => __( 'Include the post text', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'embed_type' => 'post' ),
			)
		);
		$this->add_control(
			'video_text',
			array(
				'type'      => 'switch',
				'label'     => __( 'Include the post text', 'uncoder' ),
				'condition' => array( 'embed_type' => 'video' ),
			)
		);
		$this->end_section();

		$this->start_section( 'size', array( 'label' => __( 'Size', 'uncoder' ) ) );
		$this->add_responsive_control(
			'width',
			array(
				'type'        => 'slider',
				'label'       => __( 'Width', 'uncoder' ),
				'description' => __( 'Maximum width. Facebook draws pages 180–500px wide and posts 350–750px; the embed takes the space it has within those limits.', 'uncoder' ),
				'size_units'  => array( 'px', '%' ),
				'range'       => array( 'px' => array( 'min' => 180, 'max' => 1200 ) ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-fb-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'type'        => 'slider',
				'label'       => __( 'Height', 'uncoder' ),
				'description' => __( 'Default: 500px for a page, 600px for a post. Without Facebook\'s SDK a post cannot resize itself, so pick a height that fits it.', 'uncoder' ),
				'size_units'  => array( 'px', 'vh', 'rem' ),
				'range'       => array( 'px' => array( 'min' => 70, 'max' => 1400 ) ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-fb-h: {{VALUE}}' ),
				'condition'   => array( 'embed_type' => array( 'page', 'post' ) ),
			)
		);
		$this->add_responsive_control(
			'video_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Aspect ratio', 'uncoder' ),
				'default'   => '16/9',
				'options'   => self::RATIOS,
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-fb-ratio: {{VALUE}}' ),
				'condition' => array( 'embed_type' => 'video' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => 'margin-inline:0 auto',
					'center' => 'margin-inline:auto',
					'right'  => 'margin-inline:auto 0',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'privacy', array( 'label' => __( 'Privacy', 'uncoder' ) ) );
		$this->add_control(
			'facade',
			array(
				'type'        => 'switch',
				'label'       => __( 'Load on click', 'uncoder' ),
				'description' => __( 'Shows a preview drawn by the site; Facebook (and its cookies) load only when the visitor clicks. Also fits the embed to the space it has.', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'facade_label',
			array(
				'type'        => 'text',
				'label'       => __( 'Button text', 'uncoder' ),
				'placeholder' => __( 'Show Facebook page', 'uncoder' ),
				'condition'   => array( 'facade' => true ),
			)
		);
		$this->add_control(
			'facade_note',
			array(
				'type'      => 'text',
				'label'     => __( 'Privacy note', 'uncoder' ),
				'default'   => __( 'Loads content from Facebook', 'uncoder' ),
				'condition' => array( 'facade' => true ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
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
		$this->end_section();

		$this->start_section(
			'style_facade',
			array(
				'label'     => __( 'Facade', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'facade' => true ),
			)
		);
		$this->add_control(
			'facade_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-fb-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'facade_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-fb-fg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Button background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-fb-accent: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Button text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-facebook-embed__cta' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Button typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-facebook-embed__cta' ) );
		$this->end_section();
	}

	/**
	 * Canonical https://www.facebook.com URL ('' when it is not a Facebook link).
	 */
	public static function content_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( ! in_array( $host, self::HOSTS, true ) || '/' === $path ) {
			return '';
		}
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );
		$args = array_intersect_key( $args, array_flip( self::KEEP_ARGS ) );
		$args = array_filter( $args, 'is_string' );
		$base = 'fb.watch' === $host ? 'https://fb.watch' : 'https://www.facebook.com';
		return esc_url_raw( $base . $path . ( $args ? '?' . http_build_query( $args ) : '' ), array( 'https' ) );
	}

	/**
	 * Short label for the facade: "facebook.com/page" or the page name of a post / video link.
	 */
	private static function label( string $url, string $type ): string {
		$segment = explode( '/', trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) )[0] ?? '';
		$generic = array( 'permalink.php', 'story.php', 'photo.php', 'video.php', 'watch', 'reel', 'share', 'groups', 'events', 'profile.php' );
		if ( '' === $segment || ctype_digit( $segment ) || in_array( strtolower( $segment ), $generic, true ) || false !== strpos( $url, 'https://fb.watch' ) ) {
			return 'page' === $type ? 'facebook.com' : 'Facebook';
		}
		$segment = rawurldecode( $segment );
		return 'page' === $type ? 'facebook.com/' . $segment : $segment;
	}

	/**
	 * Pixel size from a slider value, or the fallback.
	 *
	 * @param mixed $value Slider value.
	 */
	private static function px( $value, int $fallback, int $min, int $max ): int {
		if ( is_array( $value ) && 'px' === ( $value['unit'] ?? 'px' ) && is_numeric( $value['size'] ?? '' ) ) {
			$fallback = (int) round( (float) $value['size'] );
		}
		return max( $min, min( $max, $fallback ) );
	}

	/**
	 * Plugin URL for the embed type. Width / height are rewritten to the box size when loaded by the module.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function plugin_url( string $type, string $href, array $s, bool $click ): string {
		$width  = self::px( $s['width'] ?? null, 'video' === $type ? 560 : 500, 180, 'page' === $type ? 500 : ( 'post' === $type ? 750 : 1920 ) );
		$height = self::px( $s['height'] ?? null, 'post' === $type ? 600 : 500, 70, 2000 );
		$flag   = static function ( $on ): string {
			return $on ? 'true' : 'false';
		};
		switch ( $type ) {
			case 'post':
				$args = array(
					'show_text' => $flag( ! empty( $s['post_text'] ) ),
					'width'     => $width,
				);
				break;
			case 'video':
				list( $w, $h ) = array_map( 'floatval', explode( '/', (string) ( $s['video_ratio'] ?? '16/9' ) ) + array( 16, 9 ) );
				$args = array(
					'show_text' => $flag( ! empty( $s['video_text'] ) ),
					'width'     => $width,
					'height'    => $w > 0 ? (int) round( $width * $h / $w ) : 315,
					't'         => '0',
					'autoplay'  => $click ? 'true' : null,
				);
				break;
			default:
				$tabs = array_intersect( array( 'timeline', 'events', 'messages' ), (array) ( $s['tabs'] ?? array() ) );
				$args = array(
					'tabs'                  => rawurlencode( implode( ',', $tabs ) ),
					'width'                 => $width,
					'height'                => $height,
					'small_header'          => $flag( ! empty( $s['small_header'] ) ),
					'adapt_container_width' => 'true',
					'hide_cover'            => $flag( ! empty( $s['hide_cover'] ) ),
					'show_facepile'         => $flag( ! empty( $s['show_facepile'] ) ),
					'hide_cta'              => $flag( ! empty( $s['hide_cta'] ) ),
				);
		}
		$locale = (string) get_locale();
		if ( preg_match( '/^[a-z]{2,3}_[A-Z]{2}$/', $locale ) ) {
			$args['locale'] = $locale;
		}
		$args = array_merge( array( 'href' => rawurlencode( $href ) ), array_filter( $args, static fn( $v ) => null !== $v ) );
		$file = array(
			'page'  => 'page.php',
			'post'  => 'post.php',
			'video' => 'video.php',
		);
		return add_query_arg( $args, 'https://www.facebook.com/plugins/' . $file[ $type ] );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$type = in_array( $s['embed_type'] ?? 'page', array( 'page', 'post', 'video' ), true ) ? (string) $s['embed_type'] : 'page';
		$href = self::content_url( (string) ( $s[ $type . '_url' ] ?? '' ) );
		if ( '' === $href ) {
			if ( $ctx->editor ) {
				$messages = array(
					'page'  => __( 'Paste the address of a Facebook page in the settings.', 'uncoder' ),
					'post'  => __( 'Paste the link of a public Facebook post in the settings.', 'uncoder' ),
					'video' => __( 'Paste the link of a public Facebook video in the settings.', 'uncoder' ),
				);
				echo '<div class="uncoder-facebook-embed uncoder-facebook-embed--empty">' . Brand_Icons::svg( 'facebook' ) . '<span>' . esc_html( $messages[ $type ] ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG, escaped text.
			}
			return;
		}

		$name   = self::label( $href, $type );
		$titles = array(
			/* translators: %s: Facebook page address. */
			'page'  => sprintf( __( 'Facebook page: %s', 'uncoder' ), $name ),
			/* translators: %s: page name. */
			'post'  => sprintf( __( 'Facebook post: %s', 'uncoder' ), $name ),
			/* translators: %s: page name. */
			'video' => sprintf( __( 'Facebook video: %s', 'uncoder' ), $name ),
		);
		$classes = array( 'uncoder-facebook-embed', 'uncoder-facebook-embed--' . $type );
		$iframe  = array(
			'class'           => 'uncoder-facebook-embed__iframe',
			'src'             => $this->plugin_url( $type, $href, $s, false ),
			'title'           => $titles[ $type ],
			'loading'         => 'lazy',
			'scrolling'       => 'no',
			'allow'           => 'autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share',
			'allowfullscreen' => true,
		);

		if ( empty( $s['facade'] ) && ! $ctx->editor ) {
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><iframe' . Utils::attrs( $iframe ) . '></iframe></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
			return;
		}

		$labels  = array(
			'page'  => __( 'Show Facebook page', 'uncoder' ),
			'post'  => __( 'Show Facebook post', 'uncoder' ),
			'video' => __( 'Play Facebook video', 'uncoder' ),
		);
		$label   = trim( (string) ( $s['facade_label'] ?? '' ) );
		$label   = '' !== $label ? $label : $labels[ $type ];
		$note    = trim( (string) ( $s['facade_note'] ?? '' ) );
		$note_id = 'uncoder-fb-note-' . sanitize_html_class( '' !== $ctx->element_id ? $ctx->element_id : wp_unique_id() );
		$classes[] = 'uncoder-facebook-embed--facade';
		if ( 'page' === $type && ! empty( $s['hide_cover'] ) ) {
			$classes[] = 'uncoder-facebook-embed--no-cover';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		echo '<button' . Utils::attrs(
			array(
				'type'               => 'button',
				'class'              => 'uncoder-facebook-embed__facade',
				'data-uncoder-embed' => $this->plugin_url( $type, $href, $s, true ),
				'data-title'         => $titles[ $type ],
				'data-fit-width'     => self::FIT_WIDTH[ $type ],
				'data-fit-height'    => 'post' === $type ? null : '70-',
				'aria-label'         => $label . ': ' . $name,
				'aria-describedby'   => '' !== $note ? $note_id : null,
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'page' === $type ) {
			echo '<span class="uncoder-facebook-embed__cover" aria-hidden="true"></span>';
		}
		echo '<span class="uncoder-facebook-embed__identity"><span class="uncoder-facebook-embed__logo" aria-hidden="true">' . Brand_Icons::svg( 'facebook' ) . '</span><span class="uncoder-facebook-embed__name">' . esc_html( $name ) . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		if ( 'video' !== $type ) {
			echo '<span class="uncoder-facebook-embed__lines" aria-hidden="true"><span></span><span></span><span></span></span>';
		}
		echo '<span class="uncoder-facebook-embed__cta">' . $this->render_icon( 'video' === $type ? 'play' : 'eye' ) . '<span>' . esc_html( $label ) . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
		if ( '' !== $note ) {
			echo '<span class="uncoder-facebook-embed__note" id="' . esc_attr( $note_id ) . '">' . $this->render_icon( 'shield-check' ) . esc_html( $note ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup, escaped text.
		}
		echo '</button>';
		echo '<noscript><iframe' . Utils::attrs( $iframe ) . '></iframe></noscript>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		echo '</div>';
	}
}
