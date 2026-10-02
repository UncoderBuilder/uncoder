<?php
/**
 * Elementor import: widget mappers (Elementor widget settings → Uncoder widget settings).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site\Elementor_Import;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Each mapper returns [ uncoder type, settings ] or [ type, settings, children ] for nested widgets, or null
 * when the widget cannot be converted. The Converter adds the Advanced-tab settings afterwards.
 */
final class Widgets {

	/** Elementor widgetType → mapper method. */
	private const MAP = array(
		'heading'                   => 'heading',
		'text-editor'               => 'text_editor',
		'image'                     => 'image',
		'button'                    => 'button',
		'icon'                      => 'icon',
		'icon-box'                  => 'icon_box',
		'image-box'                 => 'image_box',
		'icon-list'                 => 'icon_list',
		'video'                     => 'video',
		'spacer'                    => 'spacer',
		'divider'                   => 'divider',
		'counter'                   => 'counter',
		'progress'                  => 'progress',
		'testimonial'               => 'testimonial',
		'tabs'                      => 'tabs',
		'nested-tabs'               => 'nested_tabs',
		'accordion'                 => 'accordion',
		'toggle'                    => 'accordion',
		'nested-accordion'          => 'nested_accordion',
		'social-icons'              => 'social_icons',
		'google_maps'               => 'google_maps',
		'star-rating'               => 'star_rating',
		'rating'                    => 'rating',
		'image-gallery'             => 'image_gallery',
		'gallery'                   => 'gallery',
		'image-carousel'            => 'image_carousel',
		'media-carousel'            => 'media_carousel',
		'alert'                     => 'alert',
		'html'                      => 'html',
		'shortcode'                 => 'shortcode',
		'menu-anchor'               => 'menu_anchor',
		'form'                      => 'form',
		'posts'                     => 'posts',
		'archive-posts'             => 'posts',
		'portfolio'                 => 'posts',
		'loop-grid'                 => 'loop_grid',
		'loop-carousel'             => 'loop_carousel',
		'nav-menu'                  => 'nav_menu',
		'call-to-action'            => 'call_to_action',
		'price-list'                => 'price_list',
		'price-table'               => 'price_table',
		'flip-box'                  => 'flip_box',
		'countdown'                 => 'countdown',
		'animated-headline'         => 'animated_headline',
		'testimonial-carousel'      => 'testimonial_carousel',
		'reviews'                   => 'testimonial_carousel',
		'blockquote'                => 'blockquote',
		'share-buttons'             => 'share_buttons',
		'lottie'                    => 'lottie',
		'table-of-contents'         => 'table_of_contents',
		'nested-carousel'           => 'nested_carousel',
		'code-highlight'            => 'code_highlight',
		'hotspot'                   => 'hotspot',
		'slides'                    => 'slides',
		'theme-site-logo'           => 'site_logo',
		'site-logo'                 => 'site_logo',
		'theme-site-title'          => 'site_title',
		'site-title'                => 'site_title',
		'theme-page-title'          => 'post_title',
		'theme-post-title'          => 'post_title',
		'theme-post-excerpt'        => 'post_excerpt',
		'theme-post-content'        => 'post_content',
		'theme-post-featured-image' => 'featured_image',
		'theme-archive-title'       => 'archive_title',
		'post-info'                 => 'post_info',
		'post-navigation'           => 'post_navigation',
		'author-box'                => 'author_box',
		'post-comments'             => 'post_comments',
		'search-form'               => 'search_form',
		'breadcrumbs'               => 'breadcrumbs',
		'login'                     => 'login',
		'e-heading'                 => 'atomic',
		'e-paragraph'               => 'atomic',
		'e-button'                  => 'atomic',
		'e-image'                   => 'atomic',
		'e-divider'                 => 'atomic',
		'e-youtube'                 => 'atomic',
	);

	/** Elementor key → Uncoder key of settings that may carry dynamic tags. */
	public const DYNAMIC = array(
		'heading'                   => array( 'title' => 'title', 'link' => 'link' ),
		'text-editor'               => array( 'editor' => 'content' ),
		'image'                     => array( 'image' => 'image', 'link' => 'link' ),
		'button'                    => array( 'text' => 'text', 'link' => 'link' ),
		'icon'                      => array( 'link' => 'link' ),
		'icon-box'                  => array( 'title_text' => 'title', 'description_text' => 'description', 'link' => 'link' ),
		'image-box'                 => array( 'image' => 'image', 'title_text' => 'title', 'description_text' => 'description', 'link' => 'link' ),
		'counter'                   => array( 'ending_number' => 'end', 'title' => 'title' ),
		'call-to-action'            => array( 'bg_image' => 'image', 'title' => 'title', 'description' => 'description', 'button' => 'button_text', 'link' => 'link' ),
		'testimonial'               => array( 'testimonial_content' => 'quote', 'testimonial_image' => 'image', 'testimonial_name' => 'name', 'testimonial_job' => 'role', 'link' => 'link' ),
		'theme-post-featured-image' => array( 'image' => '', 'link' => 'link' ),
		'blockquote'                => array( 'blockquote_content' => 'quote', 'author_name' => 'citation' ),
		// "" = the Uncoder widget shows that dynamic value by itself (nothing to convert or report).
		'theme-site-logo'           => array( 'image' => '', 'link' => '' ),
		'site-logo'                 => array( 'image' => '', 'link' => '' ),
		'theme-site-title'          => array( 'title' => '', 'link' => '' ),
		'site-title'                => array( 'title' => '', 'link' => '' ),
		'theme-page-title'          => array( 'title' => '', 'link' => '' ),
		'theme-post-title'          => array( 'title' => '', 'link' => '' ),
		'theme-archive-title'       => array( 'title' => '' ),
		'theme-post-excerpt'        => array( 'excerpt' => '' ),
	);

	private const TAGS = array(
		'h1'   => 'h1',
		'h2'   => 'h2',
		'h3'   => 'h3',
		'h4'   => 'h4',
		'h5'   => 'h5',
		'h6'   => 'h6',
		'div'  => 'div',
		'span' => 'span',
		'p'    => 'p',
	);

	/** Alignment with justify ([left|center|right|justify] controls). */
	private const ALIGN = array(
		'left'    => 'left',
		'center'  => 'center',
		'right'   => 'right',
		'justify' => 'justify',
		'start'   => 'left',
		'end'     => 'right',
	);

	/** Alignment without justify. */
	private const ALIGN3 = array(
		'left'   => 'left',
		'center' => 'center',
		'right'  => 'right',
		'start'  => 'left',
		'end'    => 'right',
	);

	/** start|center|end controls. */
	private const ALIGN_SE = array(
		'left'    => 'start',
		'start'   => 'start',
		'flex-start' => 'start',
		'center'  => 'center',
		'right'   => 'end',
		'end'     => 'end',
		'flex-end' => 'end',
	);

	private const BUTTON_SIZES = array(
		'xs' => 'sm',
		'sm' => 'sm',
		'md' => 'md',
		'lg' => 'lg',
		'xl' => 'xl',
	);

	/** Elementor button hover animations → Uncoder hover effects. */
	private const HOVER = array(
		'grow'        => 'grow',
		'shrink'      => 'shrink',
		'pulse'       => 'pulse',
		'pulse-grow'  => 'pulse',
		'pulse-shrink' => 'pulse',
		'float'       => 'lift',
		'bob'         => 'lift',
		'push'        => 'shrink',
		'pop'         => 'grow',
	);

	private const LOREM = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.';

	/** Font Awesome brand icon → Uncoder social network. */
	private const NETWORKS = array(
		'facebook'        => 'facebook',
		'facebook-f'      => 'facebook',
		'facebook-square' => 'facebook',
		'square-facebook' => 'facebook',
		'twitter'         => 'x',
		'twitter-square'  => 'x',
		'x-twitter'       => 'x',
		'square-x-twitter' => 'x',
		'instagram'       => 'instagram',
		'instagram-square' => 'instagram',
		'linkedin'        => 'linkedin',
		'linkedin-in'     => 'linkedin',
		'youtube'         => 'youtube',
		'youtube-square'  => 'youtube',
		'tiktok'          => 'tiktok',
		'pinterest'       => 'pinterest',
		'pinterest-p'     => 'pinterest',
		'pinterest-square' => 'pinterest',
		'github'          => 'github',
		'github-alt'      => 'github',
		'dribbble'        => 'dribbble',
		'behance'         => 'behance',
		'whatsapp'        => 'whatsapp',
		'whatsapp-square' => 'whatsapp',
		'telegram'        => 'telegram',
		'telegram-plane'  => 'telegram',
		'discord'         => 'discord',
		'threads'         => 'threads',
		'mastodon'        => 'mastodon',
		'reddit'          => 'reddit',
		'reddit-alien'    => 'reddit',
		'envelope'        => 'email',
		'envelope-open'   => 'email',
		'phone'           => 'phone',
		'phone-alt'       => 'phone',
		'rss'             => 'rss',
		'globe'           => 'website',
	);

	/** @return callable|null */
	public static function map( string $type ) {
		$method = self::MAP[ $type ] ?? '';
		return '' !== $method ? array( self::class, $method ) : null;
	}

	/* ------------------------------------------------------------------ Helpers */

	/** Rich text: Elementor runs wpautop on text-editor content. */
	private static function rich( string $html ): string {
		return preg_match( '/<(p|div|h[1-6]|ul|ol|table|blockquote|figure|pre)[\s>]/i', $html ) ? $html : wpautop( $html );
	}

	/** A repeater row id Uncoder keeps (a-z0-9, 3–32 characters). */
	private static function rid( $id ): ?string {
		$id = strtolower( (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $id ) );
		return preg_match( '/^[a-z0-9]{3,32}$/', $id ) ? $id : null;
	}

	/**
	 * @param array<string,mixed> $row Row.
	 * @param array<string,mixed> $src Elementor row (for _id).
	 * @return array<string,mixed>
	 */
	private static function row( array $row, array $src ): array {
		$id = self::rid( $src['_id'] ?? '' );
		if ( null !== $id ) {
			$row['_id'] = $id;
		}
		return $row;
	}

	/**
	 * Icon colors of the icon / icon box widgets (Elementor's primary / secondary depend on the view).
	 *
	 * @param array<string,mixed> $o    Output.
	 * @param string[]            $keys [ icon color, shape color, hover icon color, hover shape color ].
	 */
	private static function icon_colors( Source $s, array &$o, string $view, array $keys ): void {
		$p  = $s->color( 'primary_color' );
		$sc = $s->color( 'secondary_color' );
		$hp = $s->color( 'hover_primary_color' );
		$hs = $s->color( 'hover_secondary_color' );
		if ( 'stacked' === $view ) {
			Source::put( $o, $keys[1], $p );
			Source::put( $o, $keys[0], $sc );
			Source::put( $o, $keys[3], $hp );
			Source::put( $o, $keys[2], $hs );
		} elseif ( 'framed' === $view ) {
			Source::put( $o, $keys[0], $p );
			Source::put( $o, $keys[1], $sc );
			Source::put( $o, $keys[2], $hp );
			Source::put( $o, $keys[3], $hs );
		} else {
			Source::put( $o, $keys[0], $p );
			Source::put( $o, $keys[2], $hp );
		}
	}

	/** Nearest Uncoder column width (100|75|66|50|33|25). */
	private static function width( $v ): ?string {
		if ( ! is_numeric( $v ) ) {
			return null;
		}
		$best = 100;
		foreach ( array( 100, 75, 66, 50, 33, 25 ) as $w ) {
			if ( abs( $w - (float) $v ) < abs( $best - (float) $v ) ) {
				$best = $w;
			}
		}
		return (string) $best;
	}

	/** Elementor form placeholders [field id="x"] → Uncoder [x]. */
	private static function placeholders( string $text ): string {
		return (string) preg_replace( '/\[field id=["\']?([A-Za-z0-9_\-]+)["\']?\]/', '[$1]', $text );
	}

	/**
	 * A tab / accordion panel for old widgets that stored HTML: a container with a text widget.
	 *
	 * @param array<string,mixed> $text Text editor style settings.
	 * @return array<string,mixed>
	 */
	private static function panel( string $html, array $text, Converter $c ): array {
		$c->report['elements'] += 2;
		return array(
			'id'       => Converter::new_id(),
			'type'     => 'container',
			'settings' => array(),
			'children' => array(
				array(
					'id'       => Converter::new_id(),
					'type'     => 'text-editor',
					'settings' => array( 'content' => self::rich( $html ) ) + $text,
				),
			),
		);
	}

	/**
	 * Children of a nested widget, one container per repeater row.
	 *
	 * @param array<string,mixed> $el Element.
	 * @return array<int, array<string,mixed>>
	 */
	private static function nested_children( array $el, int $rows, int $depth, Converter $c ): array {
		$children = $c->children( (array) ( $el['elements'] ?? array() ), $depth + 1 );
		if ( count( $children ) > $rows ) {
			$c->note( 'A nested widget had more panels than items; the extra panels were left out.' );
			$children = array_slice( $children, 0, $rows );
		}
		while ( count( $children ) < $rows ) {
			++$c->report['elements'];
			$children[] = array(
				'id'       => Converter::new_id(),
				'type'     => 'container',
				'settings' => array(),
				'children' => array(),
			);
		}
		return $children;
	}

	/**
	 * Carousel options shared by the carousel widgets (Elementor Swiper settings).
	 *
	 * @param array<string,mixed> $o Output.
	 */
	private static function carousel( Source $s, array &$o, array $defaults = array( 3, 2, 1 ) ): void {
		foreach ( Source::SUFFIXES as $i => $suffix ) {
			$v = $s->num( 'slides_to_show' . $suffix ) ?? $s->num( 'slides_per_view' . $suffix );
			if ( null !== $v && $v > 0 ) {
				$o[ 'slides_per_view' . $suffix ] = (int) $v;
			} elseif ( isset( $defaults[ $i ] ) && ! isset( $o[ 'slides_per_view' . $suffix ] ) ) {
				// Smaller screens never show more slides than the desktop.
				$o[ 'slides_per_view' . $suffix ] = $i > 0 && isset( $o['slides_per_view'] ) ? min( (int) $o['slides_per_view'], $defaults[ $i ] ) : $defaults[ $i ];
			}
			$scroll = $s->num( 'slides_to_scroll' . $suffix );
			if ( null !== $scroll && $scroll > 0 ) {
				$o[ 'slides_to_scroll' . $suffix ] = (int) $scroll;
			}
		}
		$nav = $s->str( 'navigation', '' );
		if ( '' !== $nav ) {
			$o['arrows']     = in_array( $nav, array( 'both', 'arrows' ), true );
			$o['pagination'] = in_array( $nav, array( 'both', 'dots' ), true ) ? 'dots' : '';
		} else {
			if ( $s->exists( 'show_arrows' ) || $s->exists( 'arrows' ) ) {
				$o['arrows'] = $s->yes( 'show_arrows', $s->yes( 'arrows', true ) );
			}
			if ( $s->exists( 'pagination' ) ) {
				$o['pagination'] = array( 'bullets' => 'dots', 'fraction' => 'fraction', 'progressbar' => 'progress' )[ $s->str( 'pagination' ) ] ?? '';
			}
		}
		$o['autoplay'] = $s->yes( 'autoplay', true );
		if ( $o['autoplay'] ) {
			$delay = $s->num( 'autoplay_speed' );
			if ( null !== $delay ) {
				$o['autoplay_delay'] = (int) $delay;
			}
			$o['pause_on_hover'] = $s->yes( 'pause_on_hover', true );
		}
		$o['loop'] = $s->yes( 'infinite', $s->yes( 'loop', true ) );
		$speed     = $s->num( 'speed' );
		if ( null !== $speed ) {
			$o['speed'] = (int) $speed;
		}
		$s->sl( $o, 'gap', 'image_spacing_custom' );
		$s->sl( $o, 'gap', 'space_between' );
		$s->use( 'image_spacing', 'pause_on_interaction', 'effect', 'direction', 'lazyload' );
		$s->opt( $o, 'arrows_position', 'arrows_position', array( 'inside' => 'inside', 'outside' => 'outside' ) );
		$s->sl( $o, 'arrow_icon_size', 'arrows_size' );
		$s->col( $o, 'arrow_color', 'arrows_color' );
		$s->sl( $o, 'dots_size', 'dots_size', false );
		$s->col( $o, 'dots_color', 'dots_inactive_color' );
		$s->col( $o, 'dots_active_color', 'dots_color' );
		$s->sl( $o, 'dots_size', 'pagination_size', false );
		$s->col( $o, 'dots_active_color', 'pagination_color' );
		$s->use( 'dots_position' );
	}

	/**
	 * Uncoder gallery value from an Elementor gallery.
	 *
	 * @param mixed $list Elementor gallery.
	 * @return array<int, array<string,mixed>>
	 */
	private static function gallery_list( $list, Converter $c ): array {
		$out = array();
		foreach ( (array) $list as $img ) {
			$m = $c->media( $img );
			if ( $m ) {
				$out[] = $m;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ Basic widgets */

	public static function heading( Source $s, Converter $c ): array {
		$o = array();
		$s->text( $o, 'title', 'title' );
		if ( ! isset( $o['title'] ) ) {
			$o['title'] = 'Add Your Heading Text Here';
		}
		$s->opt( $o, 'tag', 'header_size', self::TAGS );
		$s->lnk( $o, 'link', 'link' );
		$s->opt( $o, 'align', 'align', self::ALIGN, true );
		$s->col( $o, 'color', 'title_color' );
		$s->col( $o, 'hover_color', 'title_hover_color' );
		$s->typ( $o, 'typography', 'typography' );
		$s->tsh( $o, 'text_shadow', 'text_shadow' );
		$size = $s->str( 'size' );
		if ( '' !== $size && 'default' !== $size ) {
			$c->setting( 'heading', 'size: ' . $size );
		}
		return array( 'heading', $o );
	}

	public static function text_editor( Source $s ): array {
		$o       = array();
		$content = $s->raw( 'editor' );
		$o['content'] = is_string( $content ) && '' !== trim( $content ) ? self::rich( $content ) : '';
		$s->opt( $o, 'align', 'align', self::ALIGN, true );
		$s->col( $o, 'color', 'text_color' );
		$s->typ( $o, 'typography', 'typography' );
		if ( $s->yes( 'drop_cap' ) ) {
			$o['drop_cap'] = true;
		}
		foreach ( Source::SUFFIXES as $suffix ) {
			$n = $s->num( 'text_columns' . $suffix );
			if ( null !== $n && $n > 1 ) {
				$o[ 'columns' . $suffix ] = (int) min( 10, $n );
			}
		}
		$s->sl( $o, 'column_gap', 'column_gap' );
		$s->sl( $o, 'paragraph_spacing', 'paragraph_spacing' );
		return array( 'text-editor', $o );
	}

	public static function image( Source $s, Converter $c ): array {
		$o = array();
		$s->img( $o, 'image', 'image' );
		$size = $s->str( 'image_size' );
		if ( 'custom' === $size ) {
			$o['size'] = 'full';
			$dim       = $s->raw( 'image_custom_dimension' );
			if ( is_array( $dim ) && is_numeric( $dim['width'] ?? null ) ) {
				$o['width'] = array( 'size' => (int) $dim['width'], 'unit' => 'px' );
			}
		} elseif ( preg_match( '/^[a-z0-9_\-]+$/', $size ) ) {
			$o['size'] = $size;
		}
		$s->use( 'image_custom_dimension' );
		$s->opt( $o, 'align', 'align', self::ALIGN3, true );
		$caption = $s->str( 'caption_source' );
		if ( 'attachment' === $caption ) {
			$o['caption_source'] = 'attachment';
		} elseif ( 'custom' === $caption ) {
			$o['caption_source'] = 'custom';
			$s->text( $o, 'caption', 'caption' );
		}
		$link = $s->str( 'link_to' );
		if ( 'file' === $link ) {
			$o['link_to'] = 'file';
		} elseif ( 'custom' === $link ) {
			$o['link_to'] = 'custom';
			$s->lnk( $o, 'link', 'link' );
		}
		$s->use( 'open_lightbox', 'link' );
		if ( ! isset( $o['width'] ) ) {
			$s->sl( $o, 'width', 'width' );
		}
		$s->sl( $o, 'max_width', 'space' );
		$s->sl( $o, 'height', 'height' );
		$s->opt( $o, 'object_fit', 'object-fit', array( 'cover' => 'cover', 'contain' => 'contain', 'fill' => 'fill' ), true );
		$pos = $s->str( 'object-position' );
		if ( Source::is_position( $pos ) ) {
			$o['object_position'] = $pos;
		}
		$opacity = $s->num( 'opacity' );
		if ( null !== $opacity && $opacity < 1 ) {
			$o['_opacity'] = max( 0, $opacity );
		}
		Source::put( $o, 'filters', $s->filters( 'css_filters' ) );
		$s->brd( $o, 'border', 'image_border' );
		$s->radius( $o, 'radius', 'image_border_radius' );
		$s->shd( $o, 'shadow', 'image_box_shadow' );
		self::image_hover( $s, $c, $o, 'hover_effect', array( 'grow' => 'zoom', 'float' => 'lift' ) );
		$s->col( $o, 'caption_color', 'text_color' );
		$s->typ( $o, 'caption_typography', 'caption_typography' );
		$s->sl( $o, 'caption_spacing', 'caption_space', false );
		return array( 'image', $o );
	}

	/**
	 * Elementor hover animation → an image hover effect.
	 *
	 * @param array<string,mixed>  $o   Output.
	 * @param array<string,string> $map Elementor → Uncoder.
	 */
	private static function image_hover( Source $s, Converter $c, array &$o, string $key, array $map, bool $explicit = false ): void {
		$hover = $s->str( 'hover_animation' );
		if ( '' !== $hover && isset( $map[ $hover ] ) ) {
			$o[ $key ] = $map[ $hover ];
		} elseif ( '' !== $hover ) {
			$c->setting( $s->type, 'hover_animation: ' . $hover );
			if ( $explicit ) {
				$o[ $key ] = '';
			}
		} elseif ( $explicit ) {
			$o[ $key ] = '';
		}
	}

	public static function button( Source $s, Converter $c ): array {
		$o = array();
		$s->text( $o, 'text', 'text' );
		if ( ! isset( $o['text'] ) ) {
			$o['text'] = 'Click here';
		}
		$s->lnk( $o, 'link', 'link' );
		$o['size'] = self::BUTTON_SIZES[ $s->str( 'size', 'sm' ) ] ?? 'md';
		$s->ico( $o, 'icon', 'selected_icon', 'icon' );
		if ( isset( $o['icon'] ) ) {
			$o['icon_position'] = in_array( $s->str( 'icon_align', 'left' ), array( 'right', 'row-reverse' ), true ) ? 'after' : 'before';
		}
		$s->use( 'icon_align' );
		$s->sl( $o, 'icon_spacing', 'icon_indent', false );
		$s->opt( $o, 'align', 'align', array( 'left' => 'left', 'center' => 'center', 'right' => 'right', 'justify' => 'stretch', 'start' => 'left', 'end' => 'right', 'stretch' => 'stretch' ), true );
		$s->col( $o, 'text_color', 'button_text_color' );
		$bg = $s->bg( 'background' );
		if ( ! $bg ) {
			$color = $s->color( 'background_color' );
			$type  = array( 'info' => '#5bc0de', 'success' => '#5cb85c', 'warning' => '#f0ad4e', 'danger' => '#d9534f' )[ $s->str( 'button_type' ) ] ?? '';
			$color = '' !== $color ? $color : $type;
			if ( '' !== $color ) {
				$bg = array( 'type' => 'classic', 'color' => $color );
			}
		}
		$s->use( 'button_type' );
		Source::put( $o, 'background', $bg );
		$s->col( $o, 'hover_text_color', 'hover_color' );
		$hover = $s->bg( 'button_background_hover' );
		if ( ! $hover ) {
			$color = $s->color( 'button_background_hover_color' );
			$hover = '' !== $color ? array( 'type' => 'classic', 'color' => $color ) : null;
		}
		Source::put( $o, 'hover_background', $hover );
		$s->col( $o, 'hover_border_color', 'button_hover_border_color' );
		$s->brd( $o, 'border', 'border' );
		$s->radius( $o, 'radius', 'border_radius' );
		$s->shd( $o, 'shadow', 'button_box_shadow' );
		$s->dm( $o, 'padding', 'text_padding' );
		$s->typ( $o, 'typography', 'typography' );
		$anim = $s->str( 'hover_animation' );
		if ( '' !== $anim ) {
			if ( isset( self::HOVER[ $anim ] ) ) {
				$o['hover_effect'] = self::HOVER[ $anim ];
			} else {
				$c->setting( 'button', 'hover_animation: ' . $anim );
			}
		}
		$s->text( $o, 'button_id', 'button_css_id' );
		$s->use( 'button_background_hover_transition', 'hover_animation_transition' );
		return array( 'button', $o );
	}

	public static function icon( Source $s ): array {
		$o        = array();
		$has_icon = $s->exists( 'selected_icon' ) || $s->exists( 'icon' );
		$s->ico( $o, 'icon', 'selected_icon', 'icon' );
		if ( ! isset( $o['icon'] ) ) {
			$o['icon'] = $has_icon ? array( 'library' => 'none', 'value' => '' ) : array( 'library' => 'fa-solid', 'value' => 'star' );
		}
		$view = $s->str( 'view', 'default' );
		if ( in_array( $view, array( 'stacked', 'framed' ), true ) ) {
			$o['view']  = $view;
			$o['shape'] = in_array( $s->str( 'shape', 'circle' ), array( 'circle', 'rounded', 'square' ), true ) ? $s->str( 'shape', 'circle' ) : 'circle';
		}
		$s->use( 'shape' );
		$s->lnk( $o, 'link', 'link' );
		$s->opt( $o, 'align', 'align', self::ALIGN3, true );
		self::icon_colors( $s, $o, $view, array( 'color', 'shape_color', 'hover_color', 'hover_shape_color' ) );
		$s->sl( $o, 'size', 'size' );
		if ( ! isset( $o['size'] ) ) {
			$o['size'] = array( 'size' => 50, 'unit' => 'px' );
		}
		$s->sl( $o, 'padding', 'icon_padding' );
		$rotate = $s->slider( 'rotate' );
		if ( $rotate && is_numeric( $rotate['size'] ) ) {
			$o['rotate'] = array( 'size' => $rotate['size'], 'unit' => 'deg' );
		}
		return array( 'icon', $o );
	}

	public static function icon_box( Source $s, Converter $c ): array {
		$o        = array();
		$has_icon = $s->exists( 'selected_icon' ) || $s->exists( 'icon' );
		$s->ico( $o, 'icon', 'selected_icon', 'icon' );
		if ( ! isset( $o['icon'] ) ) {
			if ( $has_icon ) {
				$o['media_type'] = 'none';
			} else {
				$o['icon'] = array( 'library' => 'fa-solid', 'value' => 'star' );
			}
		}
		$view = $s->str( 'view', 'default' );
		if ( in_array( $view, array( 'stacked', 'framed' ), true ) ) {
			$o['view']  = $view;
			$shape      = $s->str( 'shape', 'circle' );
			$o['shape'] = in_array( $shape, array( 'circle', 'rounded', 'square' ), true ) ? $shape : 'circle';
		}
		$s->use( 'shape' );
		$s->text( $o, 'title', 'title_text' );
		$s->text( $o, 'description', 'description_text' );
		$o += array(
			'title'       => 'This is the heading',
			'description' => self::LOREM,
		);
		$s->lnk( $o, 'link', 'link' );
		$s->opt( $o, 'title_tag', 'title_size', self::TAGS );
		$s->opt( $o, 'position', 'position', array( 'top' => 'top', 'left' => 'left', 'right' => 'right', 'block-start' => 'top', 'block-end' => 'top', 'inline-start' => 'left', 'inline-end' => 'right' ), true );
		$s->opt( $o, 'vertical_align', 'content_vertical_alignment', array( 'top' => 'top', 'middle' => 'middle', 'bottom' => 'bottom' ), true );
		$s->opt( $o, 'align', 'text_align', self::ALIGN, true );
		$o += array( 'align' => 'center' );
		self::icon_colors( $s, $o, $view, array( 'icon_color', 'icon_shape_color', 'hover_icon_color', 'hover_icon_shape_color' ) );
		$s->sl( $o, 'icon_spacing', 'icon_space' );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$o += array( 'icon_size' => array( 'size' => 50, 'unit' => 'px' ) );
		$s->sl( $o, 'icon_padding', 'icon_padding' );
		$rotate = $s->slider( 'rotate' );
		if ( $rotate && is_numeric( $rotate['size'] ) ) {
			$o['icon_rotate'] = array( 'size' => $rotate['size'], 'unit' => 'deg' );
		}
		$bw = $s->num( 'border_width' );
		if ( null !== $bw && 'framed' === $view ) {
			$o['icon_border_width'] = array( 'size' => Utils::number( $bw ), 'unit' => 'px' );
		}
		$s->radius( $o, 'icon_radius', 'border_radius' );
		$s->sl( $o, 'title_spacing', 'title_bottom_space' );
		$s->col( $o, 'title_color', 'title_color' );
		$s->col( $o, 'title_hover_color', 'hover_title_color' );
		$s->typ( $o, 'title_typography', 'title_typography' );
		$s->col( $o, 'description_color', 'description_color' );
		$s->typ( $o, 'description_typography', 'description_typography' );
		$anim = $s->str( 'hover_animation' );
		if ( in_array( $anim, array( 'grow', 'float', 'rotate' ), true ) ) {
			$o['icon_hover_effect'] = $anim;
		} elseif ( '' !== $anim ) {
			$c->setting( 'icon-box', 'hover_animation: ' . $anim );
		}
		return array( 'icon-box', $o );
	}

	public static function image_box( Source $s, Converter $c ): array {
		$o = array();
		$s->img( $o, 'image', 'image' );
		$size = $s->str( 'thumbnail_size' );
		if ( '' !== $size ) {
			$o['image_size'] = 'custom' === $size ? 'full' : $size;
		}
		$s->use( 'thumbnail_custom_dimension' );
		$s->text( $o, 'title', 'title_text' );
		$s->text( $o, 'description', 'description_text' );
		$o += array(
			'title'       => 'This is the heading',
			'description' => self::LOREM,
		);
		$s->lnk( $o, 'link', 'link' );
		$s->opt( $o, 'title_tag', 'title_size', self::TAGS );
		$s->opt( $o, 'position', 'position', array( 'top' => 'top', 'left' => 'left', 'right' => 'right', 'block-start' => 'top', 'block-end' => 'top', 'inline-start' => 'left', 'inline-end' => 'right' ), true );
		$s->opt( $o, 'vertical_align', 'content_vertical_alignment', array( 'top' => 'top', 'middle' => 'middle', 'bottom' => 'bottom' ), true );
		$s->opt( $o, 'align', 'text_align', self::ALIGN, true );
		$o += array( 'align' => 'center' );
		$s->sl( $o, 'image_spacing', 'image_space' );
		$s->sl( $o, 'image_width', 'image_size' );
		$o += array( 'image_width' => array( 'size' => 30, 'unit' => '%' ) );
		$s->brd( $o, 'image_border', 'image_border' );
		$s->radius( $o, 'image_radius', 'image_border_radius' );
		$s->shd( $o, 'image_shadow', 'image_box_shadow' );
		Source::put( $o, 'image_filters', $s->filters( 'css_filters' ) );
		self::image_hover( $s, $c, $o, 'image_hover', array( 'grow' => 'zoom', 'shrink' => 'zoom-out' ), true );
		$s->sl( $o, 'title_spacing', 'title_bottom_space' );
		$s->col( $o, 'title_color', 'title_color' );
		$s->typ( $o, 'title_typography', 'title_typography' );
		$s->col( $o, 'description_color', 'description_color' );
		$s->typ( $o, 'description_typography', 'description_typography' );
		return array( 'image-box', $o );
	}

	public static function icon_list( Source $s, Converter $c ): array {
		$o     = array();
		$items = array();
		foreach ( (array) $s->raw( 'icon_list' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = array( 'text' => (string) ( $row['text'] ?? '' ) );
			if ( array_key_exists( 'selected_icon', $row ) || array_key_exists( 'icon', $row ) ) {
				$icon         = $c->icon( $row['selected_icon'] ?? ( $row['icon'] ?? null ) );
				$item['icon'] = $icon ? $icon : array( 'library' => 'none', 'value' => '' );
			} else {
				$item['icon'] = array( 'library' => 'fa-solid', 'value' => 'check' );
			}
			$link = $c->link( $row['link'] ?? null );
			if ( $link ) {
				$item['link'] = $link;
			}
			$items[] = self::row( $item, $row );
		}
		$o['items'] = $items;
		if ( 'inline' === $s->str( 'view' ) ) {
			$o['layout'] = 'inline';
		}
		$s->sl( $o, 'gap', 'space_between' );
		$s->opt( $o, 'align', 'icon_align', self::ALIGN3, true );
		if ( $s->yes( 'divider' ) ) {
			$o['divider'] = true;
			$style        = $s->str( 'divider_style' );
			if ( in_array( $style, array( 'dashed', 'dotted' ), true ) ) {
				$o['divider_style'] = $style;
			}
			$s->sl( $o, 'divider_weight', 'divider_weight', false );
			$s->sl( $o, 'divider_size', 'divider_width' );
			$s->sl( $o, 'divider_height', 'divider_height' );
			$s->col( $o, 'divider_color', 'divider_color' );
		}
		$s->col( $o, 'icon_color', 'icon_color' );
		$s->col( $o, 'icon_hover_color', 'icon_color_hover' );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$s->sl( $o, 'icon_gap', 'text_indent' );
		$va = $s->str( 'icon_self_vertical_align' );
		if ( 'flex-start' === $va ) {
			$o['icon_valign'] = 'top';
		} elseif ( 'center' === $va ) {
			$o['icon_valign'] = 'middle';
		}
		$s->col( $o, 'text_color', 'text_color' );
		$s->col( $o, 'text_hover_color', 'text_color_hover' );
		$s->typ( $o, 'text_typography', 'icon_typography' );
		$s->use( 'link_click', 'icon_self_align', 'text_color_hover_transition', 'icon_color_hover_transition' );
		return array( 'icon-list', $o );
	}

	public static function video( Source $s, Converter $c ): ?array {
		$o    = array();
		$type = $s->str( 'video_type', 'youtube' );
		switch ( $type ) {
			case 'youtube':
				$o['source'] = 'youtube';
				$s->text( $o, 'youtube_url', 'youtube_url' );
				break;
			case 'vimeo':
				$o['source'] = 'vimeo';
				$s->text( $o, 'vimeo_url', 'vimeo_url' );
				break;
			case 'hosted':
				if ( $s->yes( 'insert_url' ) ) {
					$o['source'] = 'external';
					$url         = $s->raw( 'external_url' );
					$url         = is_array( $url ) ? (string) ( $url['url'] ?? '' ) : (string) $url;
					if ( '' !== $url ) {
						$o['external_url'] = $url;
					}
				} else {
					$o['source'] = 'hosted';
					$s->img( $o, 'hosted', 'hosted_url' );
				}
				break;
			case 'dailymotion':
			case 'videopress':
				$url = $s->str( $type . '_url' );
				$c->setting( 'video', 'video_type: ' . $type . ' (became an HTML embed)' );
				if ( 'dailymotion' === $type && preg_match( '#dailymotion\.com/video/([A-Za-z0-9]+)|dai\.ly/([A-Za-z0-9]+)#', $url, $m ) ) {
					$vid = '' !== $m[1] ? $m[1] : $m[2];
					return array( 'html', array( 'html' => '<div style="position:relative;aspect-ratio:16/9"><iframe src="https://www.dailymotion.com/embed/video/' . esc_attr( $vid ) . '" style="position:absolute;inset:0;width:100%;height:100%;border:0" allowfullscreen allow="autoplay; fullscreen"></iframe></div>' ) );
				}
				return null;
		}
		$s->use( 'youtube_url', 'vimeo_url', 'dailymotion_url', 'videopress_url', 'hosted_url', 'external_url', 'insert_url' );
		$start = $s->num( 'start' );
		if ( null !== $start ) {
			$o['start'] = (int) $start;
		}
		$end = $s->num( 'end' );
		if ( null !== $end ) {
			$o['end'] = (int) $end;
		}
		foreach ( array( 'autoplay' => 'autoplay', 'mute' => 'mute', 'loop' => 'loop' ) as $to => $from ) {
			if ( $s->yes( $from ) ) {
				$o[ $to ] = true;
			}
		}
		$o['controls'] = $s->yes( 'controls', true );
		if ( $s->yes( 'show_image_overlay' ) ) {
			$s->img( $o, 'poster', 'image_overlay' );
		}
		$s->use( 'image_overlay', 'image_overlay_size', 'modestbranding', 'rel', 'yt_privacy', 'lazy_load', 'showinfo', 'logo', 'color', 'vimeo_title', 'vimeo_portrait', 'vimeo_byline', 'download_button', 'preload', 'poster', 'play_on_mobile', 'privacy' );
		$ratio = array( '169' => '16/9', '219' => '21/9', '43' => '4/3', '32' => '3/2', '11' => '1/1', '916' => '9/16' )[ $s->str( 'aspect_ratio', '169' ) ] ?? '';
		if ( '' !== $ratio ) {
			$o['aspect_ratio'] = $ratio;
		}
		if ( $s->yes( 'show_play_icon', true ) ) {
			$s->ico( $o, 'play_icon', 'play_icon' );
		}
		if ( $s->yes( 'lightbox' ) ) {
			$c->setting( 'video', 'lightbox' );
		}
		$s->sl( $o, 'play_size', 'play_icon_size' );
		$s->col( $o, 'play_color', 'play_icon_color' );
		return array( 'video', $o );
	}

	public static function spacer( Source $s ): array {
		$o = array();
		$s->sl( $o, 'space', 'space' );
		$o += array( 'space' => array( 'size' => 50, 'unit' => 'px' ) );
		return array( 'spacer', $o );
	}

	public static function divider( Source $s, Converter $c ): array {
		$o     = array();
		$style = $s->str( 'style', 'solid' );
		if ( in_array( $style, array( 'solid', 'dashed', 'dotted', 'double' ), true ) ) {
			$o['style'] = $style;
		} else {
			$c->setting( 'divider', 'style: ' . $style );
		}
		$s->sl( $o, 'weight', 'weight' );
		$s->col( $o, 'color', 'color' );
		$s->sl( $o, 'width', 'width' );
		$s->opt( $o, 'align', 'align', self::ALIGN3, true );
		$s->sl( $o, 'gap', 'gap' );
		$look = $s->str( 'look', 'line' );
		if ( 'line_text' === $look ) {
			$o['element'] = 'text';
			$s->text( $o, 'text', 'text' );
			$s->col( $o, 'element_color', 'text_color' );
			$s->typ( $o, 'text_typography', 'typography' );
			$s->sl( $o, 'element_spacing', 'text_spacing', false );
		} elseif ( 'line_icon' === $look ) {
			$o['element'] = 'icon';
			$s->ico( $o, 'icon', 'icon' );
			$s->col( $o, 'element_color', 'icon_color' );
			$s->sl( $o, 'element_spacing', 'icon_spacing', false );
		}
		$s->use( 'text', 'icon', 'html_tag', 'pattern_spacing_flag', 'pattern_height' );
		return array( 'divider', $o );
	}

	public static function counter( Source $s ): array {
		$o = array();
		$o['start'] = Utils::number( $s->num( 'starting_number' ) ?? 0 );
		$o['end']   = Utils::number( $s->num( 'ending_number' ) ?? 100 );
		$s->text( $o, 'prefix', 'prefix' );
		$o['suffix'] = $s->str( 'suffix' );
		$duration    = $s->num( 'duration' );
		if ( null !== $duration ) {
			$o['duration'] = (int) $duration;
		}
		$o['thousand_separator'] = $s->yes( 'thousand_separator', true );
		$char                    = (string) $s->raw( 'thousand_separator_char' );
		$sep                     = array( '' => ',', ',' => ',', '.' => '.', ' ' => 'space', "'" => 'apos' )[ $char ] ?? ',';
		if ( $o['thousand_separator'] && ',' !== $sep ) {
			$o['separator'] = $sep;
		}
		$s->text( $o, 'title', 'title' );
		$o += array( 'title' => 'Cool Number' );
		$s->opt( $o, 'title_tag', 'title_tag', self::TAGS );
		if ( in_array( $s->str( 'title_position' ), array( 'before', 'start' ), true ) ) {
			$o['title_position'] = 'above';
		}
		$align = $s->str( 'number_alignment', $s->str( 'title_horizontal_alignment' ) );
		$o['align'] = array( 'start' => 'left', 'end' => 'right', 'left' => 'left', 'right' => 'right' )[ $align ] ?? 'center';
		$s->col( $o, 'number_color', 'number_color' );
		$s->typ( $o, 'number_typography', 'typography_number' );
		$s->col( $o, 'title_color', 'title_color' );
		$s->typ( $o, 'title_typography', 'typography_title' );
		$s->tsh( $o, 'number_shadow', 'number_shadow' );
		$s->sl( $o, 'title_spacing', 'title_gap' );
		$s->use( 'title_horizontal_alignment', 'title_vertical_alignment', 'number_position', 'number_gap' );
		return array( 'counter', $o );
	}

	public static function progress( Source $s ): array {
		$o = array();
		$s->text( $o, 'title', 'title' );
		$s->opt( $o, 'title_tag', 'title_tag', self::TAGS );
		$o['percentage'] = (int) round( $s->num( 'percent' ) ?? 50 );
		$s->text( $o, 'inner_text', 'inner_text' );
		$o['show_percentage'] = 'hide' !== $s->str( 'display_percentage', 'show' );
		if ( $o['show_percentage'] ) {
			$o['percentage_position'] = 'inside';
		}
		$bar = $s->color( 'bar_color' );
		if ( '' === $bar ) {
			$bar = array( 'info' => '#5bc0de', 'success' => '#5cb85c', 'warning' => '#f0ad4e', 'danger' => '#d9534f' )[ $s->str( 'progress_type' ) ] ?? '';
		}
		$s->use( 'progress_type' );
		if ( '' !== $bar ) {
			$o['bar_background'] = array( 'type' => 'classic', 'color' => $bar );
		}
		$s->col( $o, 'track_color', 'bar_bg_color' );
		$s->sl( $o, 'bar_height', 'bar_height' );
		$s->radius( $o, 'bar_radius', 'bar_border_radius' );
		$s->col( $o, 'inner_color', 'bar_inline_color' );
		$s->typ( $o, 'inner_typography', 'bar_inner_typography' );
		$s->col( $o, 'title_color', 'title_color' );
		$s->typ( $o, 'title_typography', 'typography' );
		return array( 'progress-bar', $o );
	}

	public static function testimonial( Source $s ): array {
		$o     = array();
		$quote = $s->str( 'testimonial_content' );
		$o['quote'] = self::rich( '' !== $quote ? $quote : 'Click edit button to change this text. Lorem ipsum dolor sit amet consectetur adipiscing elit dolor' );
		$s->img( $o, 'image', 'testimonial_image' );
		$size = $s->str( 'testimonial_image_size' );
		if ( '' !== $size ) {
			$o['image_size'] = 'custom' === $size ? 'full' : $size;
		}
		$s->text( $o, 'name', 'testimonial_name' );
		$s->text( $o, 'role', 'testimonial_job' );
		$o += array(
			'name' => 'John Doe',
			'role' => 'Designer',
		);
		$s->lnk( $o, 'link', 'link' );
		$o['layout'] = 'top' === $s->str( 'testimonial_image_position', 'aside' ) ? 'above' : 'left';
		$s->opt( $o, 'align', 'testimonial_alignment', self::ALIGN3, true );
		$o += array( 'align' => 'center' );
		$s->col( $o, 'quote_color', 'content_content_color' );
		$s->typ( $o, 'quote_typography', 'content_typography' );
		$s->col( $o, 'name_color', 'name_text_color' );
		$s->typ( $o, 'name_typography', 'name_typography' );
		$s->col( $o, 'role_color', 'job_text_color' );
		$s->typ( $o, 'role_typography', 'job_typography' );
		$s->sl( $o, 'image_width', 'image_size' );
		$s->brd( $o, 'image_border', 'image_border' );
		$s->radius( $o, 'image_radius', 'image_border_radius' );
		$s->use( 'testimonial_image_custom_dimension' );
		return array( 'testimonial', $o );
	}

	/* ------------------------------------------------------------------ Nested widgets */

	public static function tabs( Source $s, Converter $c ): array {
		$o    = array();
		$text = array();
		$s->col( $text, 'color', 'content_color' );
		$s->typ( $text, 'typography', 'content_typography' );
		$rows     = array();
		$children = array();
		foreach ( (array) $s->raw( 'tabs' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rows[]     = self::row( array( 'title' => (string) ( $row['tab_title'] ?? 'Tab' ) ), $row );
			$children[] = self::panel( (string) ( $row['tab_content'] ?? '' ), $text, $c );
		}
		$o['tabs'] = $rows;
		$vertical  = 'vertical' === $s->str( 'type' );
		if ( $vertical ) {
			$o['layout'] = 'vertical';
		}
		$justify = $s->str( $vertical ? 'tabs_align_vertical' : 'tabs_align_horizontal' );
		$map     = array( 'start' => 'start', 'center' => 'center', 'end' => 'end', 'stretch' => 'stretch', 'justify' => 'stretch' );
		if ( isset( $map[ $justify ] ) ) {
			$o['justify'] = $map[ $justify ];
		}
		$s->use( 'tabs_align_vertical', 'tabs_align_horizontal' );
		$s->sl( $o, 'list_width', 'navigation_width' );
		$bw = $s->num( 'border_width' );
		$bc = $s->color( 'border_color' );
		if ( null !== $bw || '' !== $bc ) {
			$w                   = null !== $bw ? $bw : 1;
			$o['content_border'] = array_filter(
				array(
					'style' => 'solid',
					'width' => array( 'top' => $w, 'right' => $w, 'bottom' => $w, 'left' => $w, 'unit' => 'px', 'linked' => true ),
					'color' => $bc,
				)
			);
		}
		$s->bgc( $o, 'content_background', 'background_color' );
		$s->col( $o, 'color', 'tab_color' );
		$s->col( $o, 'active_color', 'tab_active_color' );
		$s->typ( $o, 'typography', 'tab_typography' );
		$s->opt( $o, 'title_align', 'title_align', self::ALIGN3, true );
		return array( 'tabs', $o, $children );
	}

	public static function nested_tabs( Source $s, Converter $c, array $el, int $depth ): array {
		$o    = array();
		$rows = array();
		foreach ( (array) $s->raw( 'tabs' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$r    = array( 'title' => (string) ( $row['tab_title'] ?? 'Tab' ) );
			$icon = $c->icon( $row['tab_icon'] ?? null );
			if ( $icon ) {
				$r['icon'] = $icon;
			}
			if ( ! empty( $row['element_id'] ) ) {
				$r['anchor'] = sanitize_html_class( (string) $row['element_id'] );
			}
			$rows[] = self::row( $r, $row );
		}
		$o['tabs'] = $rows;
		$dir       = $s->str( 'tabs_direction', 'block-start' );
		if ( in_array( $dir, array( 'inline-start', 'inline-end', 'start', 'end' ), true ) ) {
			$o['layout'] = 'vertical';
		}
		if ( in_array( $dir, array( 'block-end', 'inline-end', 'end' ), true ) ) {
			$c->setting( 'nested-tabs', 'tabs_direction: ' . $dir );
		}
		$map = array( 'start' => 'start', 'center' => 'center', 'end' => 'end', 'stretch' => 'stretch' );
		$s->opt( $o, 'justify', 'tabs_justify_horizontal', $map, true );
		if ( 'vertical' === ( $o['layout'] ?? '' ) ) {
			$s->opt( $o, 'justify', 'tabs_justify_vertical', $map, true );
		}
		$s->use( 'tabs_justify_vertical' );
		$s->opt( $o, 'title_align', 'title_alignment', self::ALIGN3, true );
		$bp = $s->str( 'breakpoint_selector', 'mobile' );
		if ( 'tablet' === $bp ) {
			$o['layout_tablet'] = 'accordion';
		} elseif ( 'mobile' === $bp ) {
			$o['layout_mobile'] = 'accordion';
		}
		$s->sl( $o, 'tabs_gap', 'tabs_title_space_between' );
		$s->sl( $o, 'spacing', 'tabs_title_spacing' );
		$s->typ( $o, 'typography', 'title_typography' );
		$s->col( $o, 'color', 'title_text_color' );
		$s->col( $o, 'hover_color', 'title_text_color_hover' );
		$s->col( $o, 'active_color', 'title_text_color_active' );
		Source::put( $o, 'background', $s->bg( 'tabs_title_background_color' ) );
		Source::put( $o, 'hover_background', $s->bg( 'tabs_title_background_color_hover' ) );
		Source::put( $o, 'active_background', $s->bg( 'tabs_title_background_color_active' ) );
		$s->brd( $o, 'border', 'tabs_title_border' );
		$hb = $s->border( 'tabs_title_border_hover' );
		if ( ! empty( $hb['color'] ) ) {
			$o['hover_border_color'] = $hb['color'];
		}
		$ab = $s->border( 'tabs_title_border_active' );
		if ( ! empty( $ab['color'] ) ) {
			$o['active_border_color'] = $ab['color'];
		}
		$s->radius( $o, 'radius', 'tabs_title_border_radius' );
		$s->dm( $o, 'padding', 'padding' );
		Source::put( $o, 'content_background', $s->bg( 'box_background_color' ) );
		$s->brd( $o, 'content_border', 'box_border' );
		$s->radius( $o, 'content_radius', 'box_border_radius' );
		$s->dm( $o, 'content_padding', 'box_padding' );
		$s->shd( $o, 'content_shadow', 'box_box_shadow' );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$s->col( $o, 'icon_color', 'icon_color' );
		$s->col( $o, 'active_icon_color', 'icon_color_active' );
		$s->use( 'horizontal_scroll', 'tab_icon_active' );
		return array( 'tabs', $o, self::nested_children( $el, count( $rows ), $depth, $c ) );
	}

	public static function accordion( Source $s, Converter $c ): array {
		$toggle = 'toggle' === $s->type;
		$o      = array();
		$text   = array();
		$s->col( $text, 'color', 'content_color' );
		$s->typ( $text, 'typography', 'content_typography' );
		$rows     = array();
		$children = array();
		foreach ( (array) $s->raw( 'tabs' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rows[]     = self::row( array( 'title' => (string) ( $row['tab_title'] ?? 'Item' ) ), $row );
			$children[] = self::panel( (string) ( $row['tab_content'] ?? '' ), $text, $c );
		}
		$o['items'] = $rows;
		$has_icon   = $s->exists( 'selected_icon' ) || $s->exists( 'icon' );
		$s->ico( $o, 'icon', 'selected_icon', 'icon' );
		$s->ico( $o, 'active_icon', 'selected_active_icon', 'icon_active' );
		if ( ! $has_icon ) {
			$o['icon']        = array( 'library' => 'fa-solid', 'value' => $toggle ? 'caret-right' : 'plus' );
			$o['active_icon'] = array( 'library' => 'fa-solid', 'value' => $toggle ? 'caret-up' : 'minus' );
		} elseif ( ! isset( $o['icon'] ) ) {
			$o['icon']        = array( 'library' => 'none', 'value' => '' );
			$o['active_icon'] = array( 'library' => 'none', 'value' => '' );
		}
		$tag            = $s->str( 'title_html_tag', 'div' );
		$o['title_tag'] = array( 'div' => 'span', 'h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6', 'span' => 'span', 'p' => 'span' )[ $tag ] ?? 'span';
		$o['icon_position'] = 'right' === $s->str( 'icon_align', 'left' ) ? 'end' : 'start';
		$o['first_open']    = ! $toggle;
		$o['multiple']      = $toggle;
		if ( $s->yes( 'faq_schema' ) ) {
			$o['faq_schema'] = true;
		}
		$bw = $s->num( 'border_width' );
		$bc = $s->color( 'border_color' );
		if ( null !== $bw || '' !== $bc ) {
			$w                = null !== $bw ? $bw : 1;
			$o['item_border'] = array_filter(
				array(
					'style' => 'solid',
					'width' => array( 'top' => $w, 'right' => $w, 'bottom' => $w, 'left' => $w, 'unit' => 'px', 'linked' => true ),
					'color' => $bc,
				)
			);
		}
		$s->col( $o, 'header_background', 'title_background' );
		$s->col( $o, 'title_color', 'title_color' );
		$s->col( $o, 'active_title_color', 'tab_active_color' );
		$s->typ( $o, 'title_typography', 'title_typography' );
		$s->dm( $o, 'header_padding', 'title_padding' );
		$s->col( $o, 'icon_color', 'icon_color' );
		$s->col( $o, 'active_icon_color', 'icon_active_color' );
		$s->sl( $o, 'icon_spacing', 'icon_space', false );
		$s->bgc( $o, 'content_background', 'content_background_color' );
		$s->dm( $o, 'content_padding', 'content_padding' );
		$s->sl( $o, 'gap', 'space_between' );
		$s->shd( $o, 'item_shadow', 'box_shadow' );
		return array( 'accordion', $o, $children );
	}

	public static function nested_accordion( Source $s, Converter $c, array $el, int $depth ): array {
		$o    = array();
		$rows = array();
		foreach ( (array) $s->raw( 'items' ) as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = self::row( array( 'title' => (string) ( $row['item_title'] ?? 'Item' ) ), $row );
			}
		}
		$o['items'] = $rows;
		$tag        = $s->str( 'title_tag', 'div' );
		$o['title_tag'] = array( 'div' => 'span', 'h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6', 'span' => 'span', 'p' => 'span' )[ $tag ] ?? 'span';
		$s->ico( $o, 'icon', 'accordion_item_title_icon' );
		$s->ico( $o, 'active_icon', 'accordion_item_title_icon_active' );
		$o['first_open'] = 'all_collapsed' !== $s->str( 'default_state', 'expanded' );
		$o['multiple']   = 'multiple' === $s->str( 'max_items_expended', 'one' );
		$o['icon_position'] = 'start' === $s->str( 'accordion_item_title_icon_position', 'end' ) ? 'start' : 'end';
		$s->opt( $o, 'title_align', 'accordion_item_title_position_horizontal', array( 'start' => 'left', 'center' => 'center', 'end' => 'right', 'stretch' => 'left' ), true );
		$s->sl( $o, 'gap', 'accordion_item_title_space_between' );
		Source::put( $o, 'item_background', $s->bg( 'accordion_background_normal' ) );
		$s->brd( $o, 'item_border', 'accordion_border_normal' );
		$s->radius( $o, 'item_radius', 'accordion_border_radius' );
		$s->dm( $o, 'header_padding', 'accordion_padding' );
		$s->typ( $o, 'title_typography', 'title_typography' );
		$s->col( $o, 'title_color', 'normal_title_color' );
		$s->col( $o, 'hover_title_color', 'hover_title_color' );
		$s->col( $o, 'active_title_color', 'active_title_color' );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$s->sl( $o, 'icon_spacing', 'icon_spacing', false );
		$s->col( $o, 'icon_color', 'normal_icon_color' );
		$s->col( $o, 'active_icon_color', 'active_icon_color' );
		Source::put( $o, 'content_background', $s->bg( 'content_background' ) );
		$s->brd( $o, 'content_border', 'content_border' );
		$s->dm( $o, 'content_padding', 'content_padding' );
		if ( $s->yes( 'faq_schema' ) ) {
			$o['faq_schema'] = true;
		}
		return array( 'accordion', $o, self::nested_children( $el, count( $rows ), $depth, $c ) );
	}

	public static function nested_carousel( Source $s, Converter $c, array $el, int $depth ): array {
		$o    = array();
		$rows = array();
		foreach ( (array) $s->raw( 'carousel_items' ) as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = self::row( array( 'label' => (string) ( $row['slide_title'] ?? 'Slide' ) ), $row );
			}
		}
		$o['slides'] = $rows;
		self::carousel( $s, $o );
		return array( 'carousel', $o, self::nested_children( $el, count( $rows ), $depth, $c ) );
	}

	/* ------------------------------------------------------------------ More basic widgets */

	public static function social_icons( Source $s, Converter $c ): array {
		$o     = array();
		$items = array();
		foreach ( (array) $s->raw( 'social_icon_list' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$icon    = $c->icon( $row['social_icon'] ?? ( $row['social'] ?? null ) );
			$name    = $icon ? (string) $icon['value'] : '';
			$network = self::NETWORKS[ $name ] ?? '';
			$item    = array( 'network' => '' !== $network ? $network : 'custom' );
			if ( '' === $network ) {
				$item['icon'] = $icon ? $icon : array( 'library' => 'lucide', 'value' => 'link' );
				$item['label'] = ucfirst( str_replace( '-', ' ', $name ) );
			}
			$link = $c->link( $row['link'] ?? null );
			if ( $link ) {
				$item['link'] = $link;
			}
			if ( 'custom' === ( $row['item_icon_color'] ?? '' ) ) {
				$color = Utils::sanitize_color( (string) ( $row['item_icon_primary_color'] ?? '' ) );
				if ( '' !== $color ) {
					$item['color'] = $color;
				}
			}
			$items[] = self::row( $item, $row );
		}
		$o['items'] = $items;
		$shape      = $s->str( 'shape', 'rounded' );
		$o['shape'] = in_array( $shape, array( 'circle', 'rounded', 'square' ), true ) ? $shape : 'rounded';
		$o['new_tab'] = false;
		$s->opt( $o, 'align', 'align', self::ALIGN3, true );
		if ( 'custom' === $s->str( 'icon_color' ) ) {
			$o['color_mode'] = 'custom';
			$s->col( $o, 'primary_color', 'icon_primary_color' );
			$s->col( $o, 'secondary_color', 'icon_secondary_color' );
		}
		$s->use( 'icon_primary_color', 'icon_secondary_color', 'columns' );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$s->sl( $o, 'icon_padding', 'icon_padding' );
		$s->sl( $o, 'gap', 'icon_spacing' );
		$s->sl( $o, 'row_gap', 'rows_gap' );
		$s->radius( $o, 'radius', 'border_radius' );
		if ( $s->border( 'image_border' ) ) {
			$c->setting( 'social-icons', 'border' );
		}
		$s->col( $o, 'hover_shape_color', 'hover_primary_color' );
		$s->col( $o, 'hover_icon_color', 'hover_secondary_color' );
		$anim = $s->str( 'hover_animation' );
		$map  = array( 'grow' => 'grow', 'shrink' => 'shrink', 'rotate' => 'rotate', 'float' => 'lift', 'bob' => 'lift' );
		if ( isset( $map[ $anim ] ) ) {
			$o['hover_animation'] = $map[ $anim ];
		} elseif ( '' !== $anim ) {
			$c->setting( 'social-icons', 'hover_animation: ' . $anim );
		}
		return array( 'social-icons', $o );
	}

	public static function google_maps( Source $s ): array {
		$o = array();
		$s->text( $o, 'address', 'address' );
		$o += array( 'address' => 'London Eye, London, United Kingdom' );
		$zoom      = $s->num( 'zoom' );
		$o['zoom'] = null !== $zoom ? (int) $zoom : 10;
		$s->sl( $o, 'height', 'height' );
		$o += array( 'height' => array( 'size' => 300, 'unit' => 'px' ) );
		Source::put( $o, 'filters', $s->filters( 'css_filters' ) );
		Source::put( $o, 'hover_filters', $s->filters( 'css_filters_hover' ) );
		$t = $s->num( 'hover_transition' );
		if ( null !== $t ) {
			$o['transition'] = (int) ( $t * 1000 );
		}
		$s->use( 'prevent_scroll', 'location' );
		return array( 'google-maps', $o );
	}

	public static function star_rating( Source $s ): array {
		$o                = array();
		$o['rating_scale'] = '10' === $s->str( 'rating_scale', '5' ) ? '10' : '5';
		$rating           = $s->num( 'rating' );
		$o['rating']      = Utils::number( null !== $rating ? $rating : 5 );
		if ( 'outline' === $s->str( 'unmarked_star_style' ) ) {
			$o['unmarked_style'] = 'outline';
		}
		$s->use( 'star_style' );
		$s->text( $o, 'title', 'title' );
		$s->opt( $o, 'align', 'align', self::ALIGN3, true );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$s->sl( $o, 'icon_gap', 'icon_space' );
		$s->col( $o, 'marked_color', 'stars_color' );
		$s->col( $o, 'unmarked_color', 'stars_unmarked_color' );
		$s->col( $o, 'title_color', 'title_color' );
		$s->typ( $o, 'title_typography', 'title_typography' );
		$s->sl( $o, 'title_spacing', 'title_gap' );
		return array( 'star-rating', $o );
	}

	public static function rating( Source $s ): array {
		$o                 = array();
		$scale             = $s->num( 'rating_scale' ) ?? 5;
		$o['rating_scale'] = $scale > 5 ? '10' : '5';
		$o['rating']       = Utils::number( $s->num( 'rating_value' ) ?? 5 );
		$s->ico( $o, 'icon', 'rating_icon' );
		$s->opt( $o, 'align', 'icon_alignment', self::ALIGN3, true );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$s->sl( $o, 'icon_gap', 'icon_gap' );
		$s->col( $o, 'marked_color', 'icon_color' );
		$s->col( $o, 'unmarked_color', 'icon_unmarked_color' );
		return array( 'star-rating', $o );
	}

	public static function image_gallery( Source $s, Converter $c ): array {
		$o            = array();
		$o['gallery'] = self::gallery_list( $s->raw( 'wp_gallery' ), $c );
		$cols         = $s->num( 'gallery_columns' );
		$o['columns'] = null !== $cols ? (int) $cols : 4;
		foreach ( array( '_tablet', '_mobile' ) as $suffix ) {
			$n = $s->num( 'gallery_columns' . $suffix );
			if ( null !== $n ) {
				$o[ 'columns' . $suffix ] = (int) $n;
			}
		}
		$size      = $s->str( 'thumbnail_size', 'thumbnail' );
		$o['size'] = 'custom' === $size ? 'full' : $size;
		$link      = $s->str( 'gallery_link', 'file' );
		if ( 'file' === $link ) {
			$o['link_to'] = 'no' === $s->str( 'open_lightbox' ) ? 'file' : 'lightbox';
		} else {
			$o['link_to'] = '';
			if ( 'attachment' === $link ) {
				$c->setting( 'image-gallery', 'gallery_link: attachment page' );
			}
		}
		$o['overlay']      = false;
		$o['hover_effect'] = '';
		if ( 'none' !== $s->str( 'gallery_display_caption' ) ) {
			$o['caption_source'] = 'caption';
		}
		if ( 'custom' === $s->str( 'image_spacing' ) ) {
			$s->sl( $o, 'gap', 'image_spacing_custom' );
		}
		$s->use( 'image_spacing_custom', 'gallery_rand', 'thumbnail_custom_dimension' );
		$s->brd( $o, 'border', 'image_border' );
		$s->radius( $o, 'radius', 'image_border_radius' );
		$s->opt( $o, 'caption_align', 'align', self::ALIGN3, true );
		$s->col( $o, 'caption_color', 'text_color' );
		$s->typ( $o, 'caption_typography', 'typography' );
		return array( 'image-gallery', $o );
	}

	public static function gallery( Source $s, Converter $c ): array {
		$o = array();
		if ( 'multiple' === $s->str( 'gallery_type', 'single' ) ) {
			$o['filterable'] = true;
			$groups          = array();
			foreach ( (array) $s->raw( 'galleries' ) as $row ) {
				if ( is_array( $row ) ) {
					$groups[] = self::row(
						array(
							'title'  => (string) ( $row['gallery_title'] ?? 'Group' ),
							'images' => self::gallery_list( $row['multiple_gallery'] ?? array(), $c ),
						),
						$row
					);
				}
			}
			$o['groups'] = $groups;
			$s->text( $o, 'all_label', 'show_all_galleries_label' );
		} else {
			$o['gallery'] = self::gallery_list( $s->raw( 'gallery' ), $c );
		}
		$s->use( 'gallery', 'galleries', 'show_all_galleries', 'show_all_galleries_label' );
		$layout      = $s->str( 'gallery_layout', 'grid' );
		$o['layout'] = in_array( $layout, array( 'grid', 'masonry', 'justified' ), true ) ? $layout : 'grid';
		foreach ( Source::SUFFIXES as $i => $suffix ) {
			$n = $s->num( 'columns' . $suffix );
			if ( null !== $n ) {
				$o[ 'columns' . $suffix ] = (int) $n;
			} elseif ( 'justified' !== $o['layout'] ) {
				$o[ 'columns' . $suffix ] = array( 4, 2, 1 )[ $i ];
			}
		}
		$s->sl( $o, 'gap', 'gap' );
		$s->sl( $o, 'row_height', 'ideal_row_height' );
		$ratio = array( '1:1' => '1/1', '3:2' => '3/2', '4:3' => '4/3', '16:9' => '16/9', '9:16' => '3/4', '21:9' => '16/9' )[ $s->str( 'aspect_ratio', '3:2' ) ] ?? '';
		if ( 'grid' === $o['layout'] && '' !== $ratio ) {
			$o['ratio'] = $ratio;
		}
		$size = $s->str( 'thumbnail_image_size' );
		if ( '' !== $size ) {
			$o['size'] = 'custom' === $size ? 'full' : $size;
		}
		$link         = $s->str( 'link_to', 'file' );
		$o['link_to'] = 'file' === $link ? 'lightbox' : '';
		if ( 'custom' === $link ) {
			$c->setting( 'gallery', 'link_to: custom URL' );
		}
		$o['overlay'] = $s->yes( 'overlay_background', true ) && '' !== $o['link_to'];
		$title        = $s->str( 'overlay_title' );
		if ( in_array( $title, array( 'title', 'caption', 'alt', 'description' ), true ) ) {
			$o['caption_source']   = 'description' === $title ? 'caption' : $title;
			$o['caption_position'] = 'hover';
		}
		$s->use( 'overlay_description' );
		$anim              = $s->str( 'image_hover_animation' );
		$o['hover_effect'] = array( 'grow' => 'zoom', 'grow-rotate' => 'zoom' )[ $anim ] ?? '';
		$s->brd( $o, 'border', 'image_border' );
		$s->radius( $o, 'radius', 'image_border_radius' );
		return array( 'image-gallery', $o );
	}

	public static function image_carousel( Source $s, Converter $c ): array {
		$o            = array();
		$o['gallery'] = self::gallery_list( $s->raw( 'carousel' ), $c );
		$size         = $s->str( 'thumbnail_size' );
		if ( '' !== $size ) {
			$o['size'] = 'custom' === $size ? 'full' : $size;
		}
		$s->use( 'thumbnail_custom_dimension' );
		self::carousel( $s, $o );
		$link         = $s->str( 'link_to', 'none' );
		$o['link_to'] = 'file' === $link ? 'lightbox' : '';
		if ( 'custom' === $link ) {
			$c->setting( 'image-carousel', 'link_to: custom URL' );
		}
		$s->use( 'link', 'open_lightbox' );
		$caption = $s->str( 'caption_type' );
		if ( '' !== $caption ) {
			$o['caption_source'] = 'description' === $caption ? 'caption' : $caption;
		}
		$o['hover_effect'] = '';
		$s->brd( $o, 'border', 'image_border' );
		$s->radius( $o, 'radius', 'image_border_radius' );
		$s->opt( $o, 'caption_align', 'caption_align', self::ALIGN3, true );
		$s->col( $o, 'caption_color', 'caption_text_color' );
		$s->typ( $o, 'caption_typography', 'caption_typography' );
		if ( $s->yes( 'image_stretch' ) ) {
			$c->setting( 'image-carousel', 'image_stretch' );
		}
		return array( 'image-carousel', $o );
	}

	public static function media_carousel( Source $s, Converter $c ): array {
		$o      = array();
		$images = array();
		foreach ( (array) $s->raw( 'slides' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( 'video' === ( $row['type'] ?? 'image' ) ) {
				$c->setting( 'media-carousel', 'video slides' );
			}
			$m = $c->media( $row['image'] ?? null );
			if ( $m ) {
				$images[] = $m;
			}
		}
		$o['gallery'] = $images;
		$skin         = $s->str( 'skin', 'carousel' );
		if ( 'carousel' !== $skin ) {
			$c->setting( 'media-carousel', 'skin: ' . $skin );
		}
		self::carousel( $s, $o, 'slideshow' === $skin ? array( 1, 1, 1 ) : array( 3, 2, 1 ) );
		$o['link_to']      = 'lightbox';
		$o['hover_effect'] = '';
		$s->use( 'effect', 'thumbs_ratio', 'centered_slides', 'slideshow_slides_per_view', 'image_size_size', 'image_fit' );
		return array( 'image-carousel', $o );
	}

	public static function alert( Source $s ): array {
		$o = array();
		$o['type'] = in_array( $s->str( 'alert_type', 'info' ), array( 'info', 'success', 'warning', 'danger' ), true ) ? $s->str( 'alert_type', 'info' ) : 'info';
		$s->text( $o, 'title', 'alert_title' );
		$s->text( $o, 'description', 'alert_description' );
		$o += array(
			'title'       => 'This is an Alert',
			'description' => 'I am a description. Click the edit button to change this text.',
		);
		$o['dismissible'] = 'hide' !== $s->str( 'show_dismiss', 'show' );
		$o['variant']     = 'accent';
		$o['show_icon']   = false;
		$s->col( $o, 'background_color', 'background' );
		$s->col( $o, 'accent_color', 'border_color' );
		$s->sl( $o, 'accent_width', 'border_left-width' );
		$s->sl( $o, 'accent_width', 'border_left_width' );
		$s->col( $o, 'title_color', 'title_color' );
		$s->typ( $o, 'title_typography', 'alert_title' );
		$s->typ( $o, 'title_typography', 'title_typography' );
		$s->col( $o, 'description_color', 'description_color' );
		$s->typ( $o, 'description_typography', 'alert_description' );
		$s->typ( $o, 'description_typography', 'description_typography' );
		$s->use( 'dismiss_icon' );
		return array( 'alert', $o );
	}

	public static function html( Source $s ): array {
		$html = $s->raw( 'html' );
		return array( 'html', is_string( $html ) && '' !== trim( $html ) ? array( 'html' => $html ) : array() );
	}

	public static function shortcode( Source $s ): array {
		$o = array();
		$s->text( $o, 'shortcode', 'shortcode' );
		return array( 'shortcode', $o );
	}

	public static function menu_anchor( Source $s ): array {
		$o = array();
		$s->text( $o, 'anchor', 'anchor' );
		return array( 'menu-anchor', $o );
	}

	/* ------------------------------------------------------------------ Pro widgets */

	public static function form( Source $s, Converter $c ): array {
		$o = array();
		$s->text( $o, 'form_name', 'form_name' );
		$fields  = array();
		$captcha = false;
		$types   = array(
			'text'       => 'text',
			'email'      => 'email',
			'textarea'   => 'textarea',
			'url'        => 'url',
			'tel'        => 'tel',
			'number'     => 'number',
			'date'       => 'date',
			'time'       => 'text',
			'password'   => 'text',
			'radio'      => 'radio',
			'select'     => 'select',
			'checkbox'   => 'checkbox',
			'acceptance' => 'acceptance',
			'upload'     => 'file',
			'hidden'     => 'hidden',
			'step'       => 'step',
		);
		foreach ( (array) $s->raw( 'form_fields' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$t = (string) ( $row['field_type'] ?? 'text' );
			if ( in_array( $t, array( 'recaptcha', 'recaptcha_v3' ), true ) ) {
				$captcha = true;
				continue;
			}
			if ( 'honeypot' === $t ) {
				continue;
			}
			if ( ! isset( $types[ $t ] ) ) {
				$c->setting( 'form', 'field type: ' . $t );
				continue;
			}
			if ( in_array( $t, array( 'time', 'password' ), true ) ) {
				$c->setting( 'form', 'field type: ' . $t . ' (became a text field)' );
			}
			$f     = array( 'type' => $types[ $t ] );
			$label = (string) ( $row['field_label'] ?? '' );
			if ( '' !== $label ) {
				$f['label'] = $label;
			}
			$id = trim( (string) preg_replace( '/[^A-Za-z0-9_]+/', '_', (string) ( $row['custom_id'] ?? '' ) ), '_' );
			if ( '' !== $id ) {
				$f['field_id'] = $id;
			}
			if ( '' !== (string) ( $row['placeholder'] ?? '' ) ) {
				$f['placeholder'] = (string) $row['placeholder'];
			}
			if ( 'step' !== $t && 'hidden' !== $t ) {
				$f['required'] = in_array( $row['required'] ?? '', array( 'true', 'yes', true, 1, '1' ), true );
			}
			if ( '' !== (string) ( $row['field_options'] ?? '' ) ) {
				$f['options'] = (string) $row['field_options'];
			}
			if ( ! empty( $row['inline_list'] ) ) {
				$f['inline_options'] = true;
			}
			if ( '' !== (string) ( $row['acceptance_text'] ?? '' ) ) {
				$f['acceptance_text'] = (string) $row['acceptance_text'];
			}
			if ( '' !== (string) ( $row['field_value'] ?? '' ) && ! in_array( $t, array( 'upload', 'acceptance', 'step' ), true ) ) {
				$f['default_value'] = (string) $row['field_value'];
			}
			foreach ( array( 'field_min' => 'min', 'field_max' => 'max', 'rows' => 'rows' ) as $from => $to ) {
				if ( is_numeric( $row[ $from ] ?? null ) ) {
					$f[ $to ] = Utils::number( $row[ $from ] );
				}
			}
			if ( 'upload' === $t ) {
				if ( '' !== (string) ( $row['file_types'] ?? '' ) ) {
					$f['file_types'] = (string) $row['file_types'];
				}
				if ( is_numeric( $row['file_sizes'] ?? null ) ) {
					$f['file_size'] = Utils::number( $row['file_sizes'] );
				}
			}
			if ( 'step' === $t ) {
				$f['label'] = '' !== $label ? $label : 'Step';
			}
			if ( 'select' === $t && ! empty( $row['allow_multiple'] ) ) {
				$c->setting( 'form', 'multiple select' );
			}
			foreach ( Source::SUFFIXES as $suffix ) {
				$w = self::width( $row[ 'width' . $suffix ] ?? ( '' === $suffix ? 100 : null ) );
				if ( null !== $w && ! in_array( $t, array( 'hidden', 'step' ), true ) ) {
					$f[ 'width' . $suffix ] = $w;
				}
			}
			$fields[] = self::row( $f, $row );
		}
		$o['fields'] = $fields;
		if ( $captcha ) {
			$o['captcha'] = true;
			$c->note( 'Forms that used reCAPTCHA now use the captcha set up in Uncoder → Settings → Forms.' );
		}
		$o['show_labels']   = $s->yes( 'show_labels', true );
		$o['required_mark'] = $s->yes( 'mark_required', false );
		$s->text( $o, 'button_text', 'button_text' );
		$o += array( 'button_text' => 'Send' );
		$o['button_size'] = self::BUTTON_SIZES[ $s->str( 'button_size', 'sm' ) ] ?? 'md';
		foreach ( Source::SUFFIXES as $suffix ) {
			$w = self::width( $s->raw( 'button_width' . $suffix ) );
			if ( null !== $w ) {
				$o[ 'button_width' . $suffix ] = $w;
			}
		}
		$s->opt( $o, 'button_align', 'button_align', array( 'start' => 'left', 'left' => 'left', 'center' => 'center', 'end' => 'right', 'right' => 'right', 'stretch' => 'stretch', 'justify' => 'stretch' ), true );
		$s->ico( $o, 'button_icon', 'selected_button_icon', 'button_icon' );
		if ( isset( $o['button_icon'] ) ) {
			$o['button_icon_position'] = 'right' === $s->str( 'button_icon_align', 'left' ) ? 'after' : 'before';
		}
		$s->use( 'button_icon_align', 'button_icon_indent' );
		$s->text( $o, 'success_message', 'success_message' );
		$s->text( $o, 'error_message', 'error_message' );
		$s->text( $o, 'required_message', 'required_field_message' );
		$s->use( 'invalid_message', 'custom_messages', 'server_message' );
		$actions  = $s->exists( 'submit_actions' ) ? (array) $s->raw( 'submit_actions' ) : array( 'email' );
		$o['email'] = in_array( 'email', $actions, true );
		if ( $o['email'] ) {
			$s->text( $o, 'email_to', 'email_to' );
			$subject = $s->str( 'email_subject' );
			if ( '' !== $subject ) {
				$o['email_subject'] = self::placeholders( $subject );
			}
			$content = self::placeholders( $s->str( 'email_content', '[all-fields]' ) );
			if ( '[all-fields]' !== trim( $content ) ) {
				$o['email_content']  = 'template';
				$o['email_template'] = $content;
			}
			$s->text( $o, 'email_from_name', 'email_from_name' );
			$reply = $s->str( 'email_reply_to' );
			if ( preg_match( '/^[A-Za-z0-9_]+$/', $reply ) ) {
				$o['email_reply_to'] = $reply;
			}
			if ( 'plain' === $s->str( 'email_content_type' ) ) {
				$o['email_format'] = 'plain';
			}
		}
		$s->use( 'email_to', 'email_subject', 'email_content', 'email_from', 'email_from_name', 'email_reply_to', 'email_to_cc', 'email_to_bcc', 'form_metadata', 'email_content_type' );
		if ( in_array( 'email2', $actions, true ) ) {
			$o['autoreply'] = true;
			$to             = self::placeholders( $s->str( 'email_to_2' ) );
			if ( preg_match( '/^\[([A-Za-z0-9_]+)\]$/', $to, $m ) ) {
				$o['autoreply_to'] = $m[1];
			}
			$subject = $s->str( 'email_subject_2' );
			if ( '' !== $subject ) {
				$o['autoreply_subject'] = self::placeholders( $subject );
			}
			$message = $s->str( 'email_content_2' );
			if ( '' !== $message ) {
				$o['autoreply_message'] = self::placeholders( $message );
			}
			$s->text( $o, 'autoreply_from_name', 'email_from_name_2' );
		}
		$s->use( 'email_to_2', 'email_subject_2', 'email_content_2', 'email_from_2', 'email_from_name_2', 'email_reply_to_2', 'email_to_cc_2', 'email_to_bcc_2', 'form_metadata_2', 'email_content_type_2' );
		if ( in_array( 'redirect', $actions, true ) ) {
			$url = $s->raw( 'redirect_to' );
			$url = is_array( $url ) ? (string) ( $url['url'] ?? '' ) : (string) $url;
			if ( '' !== $url ) {
				$o['redirect_url'] = $url;
			}
		}
		$s->use( 'redirect_to' );
		if ( in_array( 'webhook', $actions, true ) ) {
			if ( $c->option( 'remote' ) ) {
				$c->note( 'Form webhooks from uploaded templates were not copied: add them again in the form settings.' );
			} else {
				$s->text( $o, 'webhook_url', 'webhooks' );
			}
		}
		$s->use( 'webhooks', 'webhooks_advanced_data' );
		foreach ( $actions as $action ) {
			if ( ! in_array( $action, array( 'email', 'email2', 'redirect', 'webhook', 'collect_submissions', 'save-to-database' ), true ) ) {
				$c->setting( 'form', 'action: ' . $action );
			}
		}
		// Styles.
		$s->sl( $o, 'column_gap', 'column_gap' );
		$s->sl( $o, 'row_gap', 'row_gap' );
		$s->sl( $o, 'label_spacing', 'label_spacing', false );
		$s->col( $o, 'label_color', 'label_color' );
		if ( isset( $o['label_color'] ) ) {
			// Elementor's label color covers checkbox, radio and acceptance labels too.
			$o['option_color'] = $o['label_color'];
		}
		$s->typ( $o, 'label_typography', 'label_typography' );
		$s->col( $o, 'mark_color', 'mark_required_color' );
		$s->col( $o, 'field_color', 'field_text_color' );
		$s->typ( $o, 'field_typography', 'field_typography' );
		$s->col( $o, 'field_bg', 'field_background_color' );
		$fb = $s->color( 'field_border_color' );
		$fw = $s->dims( 'field_border_width' );
		if ( '' !== $fb || $fw ) {
			// Elementor fields have a 1px border unless the width is set.
			$o['field_border'] = array_filter(
				array(
					'style' => 'solid',
					'width' => $fw ? $fw : array( 'top' => 1, 'right' => 1, 'bottom' => 1, 'left' => 1, 'unit' => 'px', 'linked' => true ),
					'color' => $fb,
				)
			);
		}
		$s->radius( $o, 'field_radius', 'field_border_radius' );
		$button_bg = $s->bg( 'button_background' );
		if ( ! $button_bg ) {
			$color     = $s->color( 'button_background_color' );
			$button_bg = '' !== $color ? array( 'type' => 'classic', 'color' => $color ) : null;
		}
		Source::put( $o, 'button_background', $button_bg );
		$s->col( $o, 'button_color', 'button_text_color' );
		$s->typ( $o, 'button_typography', 'button_typography' );
		$s->brd( $o, 'button_border', 'button_border' );
		$s->radius( $o, 'button_radius', 'button_border_radius' );
		$s->dm( $o, 'button_padding', 'button_text_padding' );
		$hover = $s->bg( 'button_background_hover' );
		if ( ! $hover ) {
			$color = $s->color( 'button_background_hover_color' );
			$hover = '' !== $color ? array( 'type' => 'classic', 'color' => $color ) : null;
		}
		Source::put( $o, 'button_hover_background', $hover );
		$s->col( $o, 'button_hover_color', 'button_hover_color' );
		$s->col( $o, 'button_hover_border_color', 'button_hover_border_color' );
		$s->col( $o, 'success_color', 'success_message_color' );
		$s->col( $o, 'error_color', 'error_message_color' );
		$s->typ( $o, 'message_typography', 'message_typography' );
		return array( 'form', $o );
	}

	/** Query settings shared by Posts / Loop Grid. @return array<string,mixed> */
	private static function query( Source $s, string $prefix, int $default_per_page, string $per_page_key ): array {
		$type  = $s->str( $prefix . 'post_type', 'post' );
		$query = array(
			'source'         => 'posts',
			'post_type'      => 'post',
			'posts_per_page' => $default_per_page,
			'orderby'        => 'date',
			'order'          => 'desc',
		);
		if ( 'current_query' === $type ) {
			$query['source'] = 'current';
		} elseif ( 'related' === $type ) {
			$query['source'] = 'related';
		} elseif ( 'by_id' === $type ) {
			$query['source']      = 'manual';
			$query['include_ids'] = array_map( 'strval', array_filter( array_map( 'absint', (array) $s->raw( $prefix . 'posts_ids' ) ) ) );
		} elseif ( preg_match( '/^[a-z0-9_\-]+$/', $type ) ) {
			$query['post_type'] = $type;
		}
		$per = $s->num( $per_page_key );
		if ( null !== $per && $per > 0 ) {
			$query['posts_per_page'] = (int) min( 100, $per );
		}
		$orderby          = $s->str( $prefix . 'orderby' );
		$query['orderby'] = array(
			'post_date'     => 'date',
			'date'          => 'date',
			'post_title'    => 'title',
			'title'         => 'title',
			'menu_order'    => 'menu_order',
			'rand'          => 'rand',
			'comment_count' => 'comment_count',
			'modified'      => 'modified',
			'post_modified' => 'modified',
		)[ $orderby ] ?? 'date';
		if ( 'asc' === strtolower( $s->str( $prefix . 'order' ) ) ) {
			$query['order'] = 'asc';
		}
		$offset = $s->num( $prefix . 'offset' );
		if ( null !== $offset && $offset > 0 ) {
			$query['offset'] = (int) $offset;
		}
		$exclude = array_filter( array_map( 'absint', (array) $s->raw( $prefix . 'exclude_ids' ) ) );
		if ( $exclude ) {
			$query['exclude_ids'] = array_map( 'strval', $exclude );
		}
		$s->use( $prefix . 'posts_ids', $prefix . 'include', $prefix . 'exclude', $prefix . 'include_term_ids', $prefix . 'exclude_term_ids', $prefix . 'include_authors', $prefix . 'avoid_duplicates', $prefix . 'select_date', $prefix . 'query_id' );
		return $query;
	}

	public static function posts( Source $s, Converter $c ): array {
		$o    = array();
		$skin = $s->str( '_skin', 'classic' );
		if ( 'archive-posts' === $s->type ) {
			$skin = $s->str( '_skin', 'archive_classic' );
			$skin = (string) preg_replace( '/^archive_/', '', $skin );
		}
		$p    = ( 'archive-posts' === $s->type ? 'archive_' : '' ) . $skin . '_';
		if ( 'portfolio' === $s->type ) {
			$p = '';
		}
		$o['query'] = self::query( $s, 'posts_', 6, $p . 'posts_per_page' );
		if ( 'archive-posts' === $s->type ) {
			$o['query']['source'] = 'current';
		}
		$thumb  = $s->str( $p . 'thumbnail', 'top' );
		$layout = 'cards' === $skin ? 'cards' : ( in_array( $thumb, array( 'left', 'right' ), true ) ? 'list' : 'grid' );
		if ( 'full_content' === $skin ) {
			$layout = 'list';
			$c->setting( $s->type, 'skin: full content' );
		}
		$o['layout'] = $layout;
		foreach ( Source::SUFFIXES as $i => $suffix ) {
			$n = $s->num( $p . 'columns' . $suffix );
			$o[ 'columns' . $suffix ] = null !== $n ? (int) $n : array( 3, 2, 1 )[ $i ];
		}
		$o['show_image'] = 'none' !== $thumb;
		$size            = $s->str( $p . 'thumbnail_size_size' );
		if ( '' !== $size ) {
			$o['image_size'] = 'custom' === $size ? 'full' : $size;
		}
		$ratio = $s->num( $p . 'item_ratio' );
		if ( null !== $ratio ) {
			$ratios = array(
				'1/1'  => 1,
				'4/3'  => 0.75,
				'3/2'  => 0.66,
				'16/9' => 0.5625,
				'3/4'  => 1.33,
			);
			$best   = '3/2';
			foreach ( $ratios as $k => $v ) {
				if ( abs( $v - $ratio ) < abs( $ratios[ $best ] - $ratio ) ) {
					$best = $k;
				}
			}
			$o['image_ratio'] = $best;
		}
		$o['show_title'] = $s->yes( $p . 'show_title', true );
		$s->opt( $o, 'title_tag', $p . 'title_tag', self::TAGS );
		$o['show_excerpt'] = $s->yes( $p . 'show_excerpt', true );
		$len               = $s->num( $p . 'excerpt_length' );
		if ( null !== $len ) {
			$o['excerpt_length'] = (int) $len;
		}
		$meta         = $s->exists( $p . 'meta_data' ) ? (array) $s->raw( $p . 'meta_data' ) : array( 'date', 'comments' );
		$items        = array_values( array_intersect( $meta, array( 'author', 'date', 'comments' ) ) );
		$o['show_meta'] = (bool) $items;
		if ( $items ) {
			$o['meta_items'] = $items;
		}
		$sep = $s->str( $p . 'meta_separator' );
		if ( '' !== $sep ) {
			$o['meta_separator'] = $sep;
		}
		$o['show_read_more'] = $s->yes( $p . 'show_read_more', true );
		$s->text( $o, 'read_more_text', $p . 'read_more_text' );
		if ( 'cards' === $skin ) {
			$o['show_badge'] = $s->yes( 'cards_show_badge', true );
			$tax             = $s->str( 'cards_badge_taxonomy' );
			if ( '' !== $tax ) {
				$o['badge_taxonomy'] = $tax;
			}
		}
		$pagination      = $s->str( 'pagination_type' );
		$o['pagination'] = array(
			'numbers'                    => 'numbers',
			'prev_next'                  => 'prev_next',
			'numbers_and_prev_next'      => 'numbers_prev_next',
			'load_more_on_click'         => 'numbers',
			'load_more_infinite_scroll'  => 'numbers',
		)[ $pagination ] ?? 'none';
		if ( 0 === strpos( $pagination, 'load_more' ) ) {
			$c->setting( $s->type, 'pagination: load more' );
		}
		$s->use( 'pagination_page_limit', 'pagination_prev_label', 'pagination_next_label', 'pagination_numbers_shorten' );
		$s->sl( $o, 'column_gap', $p . 'column_gap' );
		$s->sl( $o, 'row_gap', $p . 'row_gap' );
		$o['hover_effect'] = '';
		$s->col( $o, 'title_color', $p . 'title_color' );
		$s->typ( $o, 'title_typography', $p . 'title_typography' );
		$s->col( $o, 'meta_color', $p . 'meta_color' );
		$s->typ( $o, 'meta_typography', $p . 'meta_typography' );
		$s->col( $o, 'excerpt_color', $p . 'excerpt_color' );
		$s->typ( $o, 'excerpt_typography', $p . 'excerpt_typography' );
		$s->col( $o, 'more_color', $p . 'read_more_color' );
		$s->typ( $o, 'more_typography', $p . 'read_more_typography' );
		$s->bgc( $o, 'card_background', $p . 'card_bg_color' );
		$s->radius( $o, 'card_radius', $p . 'card_border_radius' );
		$s->radius( $o, 'image_radius', $p . 'img_border_radius' );
		$s->use( $p . 'thumbnail', $p . 'masonry', $p . 'open_new_tab', $p . 'show_avatar', $p . 'thumbnail_size_custom_dimension' );
		return array( 'posts', $o );
	}

	public static function loop_grid( Source $s, Converter $c ): array {
		$o        = array();
		$template = (int) $s->raw( 'template_id' );
		$map      = (array) $c->option( 'templates' );
		foreach ( Source::SUFFIXES as $i => $suffix ) {
			$n = $s->num( 'columns' . $suffix );
			$o[ 'columns' . $suffix ] = null !== $n ? (int) $n : array( 3, 2, 1 )[ $i ];
		}
		$o['query'] = self::query( $s, 'post_query_', 6, 'posts_per_page' );
		if ( ! $template || ! isset( $map[ $template ] ) ) {
			// Without its loop item template the grid becomes a Posts grid with the same query.
			$c->setting( 'loop-grid', 'loop item template not converted yet (shown as a Posts grid; convert the template, then this page again)' );
			$o['layout']       = 'cards';
			$o['hover_effect'] = '';
			$pagination        = $s->str( 'pagination_type' );
			$o['pagination']   = array(
				'numbers'                   => 'numbers',
				'prev_next'                 => 'prev_next',
				'numbers_and_prev_next'     => 'numbers_prev_next',
				'load_more_on_click'        => 'numbers',
				'load_more_infinite_scroll' => 'numbers',
			)[ $pagination ] ?? 'none';
			$s->sl( $o, 'column_gap', 'column_gap' );
			$s->sl( $o, 'row_gap', 'row_gap' );
			$s->use( 'template_id', '_skin', 'masonry', 'equal_height', 'load_more_button_text', 'pagination_page_limit', 'pagination_numbers_shorten', 'nothing_found_message', 'nothing_found_message_text' );
			return array( 'posts', $o );
		}
		$o['loop_template'] = (string) (int) $map[ $template ];
		if ( $s->yes( 'masonry' ) ) {
			$o['masonry'] = true;
		}
		if ( $s->exists( 'equal_height' ) ) {
			$o['equal_height'] = $s->yes( 'equal_height' );
		}
		$pagination      = $s->str( 'pagination_type' );
		$o['pagination'] = array(
			'numbers'                   => 'numbers',
			'prev_next'                 => 'prev_next',
			'numbers_and_prev_next'     => 'numbers_prev_next',
			'load_more_on_click'        => 'load_more',
			'load_more_infinite_scroll' => 'infinite',
		)[ $pagination ] ?? 'none';
		$s->text( $o, 'load_more_text', 'load_more_button_text' );
		$s->sl( $o, 'column_gap', 'column_gap' );
		$s->sl( $o, 'row_gap', 'row_gap' );
		$s->text( $o, 'nothing_found', 'nothing_found_message_text' );
		$s->use( '_skin', 'pagination_page_limit', 'pagination_numbers_shorten', 'nothing_found_message' );
		return array( 'loop-grid', $o );
	}

	public static function loop_carousel( Source $s, Converter $c ): array {
		$o        = array();
		$template = (int) $s->raw( 'template_id' );
		$map      = (array) $c->option( 'templates' );
		if ( $template && isset( $map[ $template ] ) ) {
			$o['loop_template'] = (string) (int) $map[ $template ];
		} elseif ( $template ) {
			$c->setting( 'loop-carousel', 'loop item template (convert it first)' );
		}
		$o['query'] = self::query( $s, 'post_query_', 8, 'posts_per_page' );
		self::carousel( $s, $o );
		$s->use( '_skin' );
		return array( 'loop-carousel', $o );
	}

	public static function nav_menu( Source $s, Converter $c ): array {
		$o    = array();
		$slug = $s->str( 'menu' );
		$menu = '' !== $slug ? wp_get_nav_menu_object( $slug ) : false;
		if ( $menu ) {
			$o['menu'] = (string) $menu->term_id;
		} elseif ( '' !== $slug ) {
			$c->setting( 'nav-menu', 'menu “' . $slug . '” (not on this site)' );
		}
		$layout = $s->str( 'layout', 'horizontal' );
		if ( 'vertical' === $layout ) {
			$o['layout'] = 'vertical';
		}
		$s->opt( $o, 'align', 'align_items', array( 'left' => 'start', 'start' => 'start', 'center' => 'center', 'right' => 'end', 'end' => 'end', 'justify' => 'justify' ), true );
		$pointer      = $s->str( 'pointer', 'underline' );
		$o['pointer'] = array( 'underline' => 'underline', 'overline' => 'overline', 'double-line' => 'double', 'framed' => 'underline', 'background' => 'background', 'text' => 'none', 'none' => 'none' )[ $pointer ] ?? 'underline';
		if ( 'framed' === $pointer || 'text' === $pointer ) {
			$c->setting( 'nav-menu', 'pointer: ' . $pointer );
		}
		$anim = $s->str( 'animation_line', $s->str( 'animation_framed', $s->str( 'animation_background', $s->str( 'animation_text' ) ) ) );
		if ( '' !== $anim ) {
			$o['pointer_animation'] = array( 'fade' => 'fade', 'slide' => 'slide', 'grow' => 'grow', 'none' => 'none' )[ $anim ] ?? 'grow';
		}
		$s->ico( $o, 'submenu_icon', 'submenu_icon' );
		$dropdown = $s->str( 'dropdown', 'tablet' );
		if ( 'dropdown' === $layout ) {
			$o['breakpoint'] = 'all';
		} else {
			$o['breakpoint'] = array( 'tablet' => 'tablet', 'mobile' => 'mobile', 'none' => 'none', 'desktop' => 'all' )[ $dropdown ] ?? 'tablet';
		}
		if ( $s->exists( 'full_width' ) ) {
			$o['dropdown_stretch'] = '' !== $s->str( 'full_width' );
		}
		$s->ico( $o, 'toggle_icon', 'toggle_icon_normal' );
		$s->ico( $o, 'toggle_close_icon', 'toggle_icon_active' );
		$s->opt( $o, 'toggle_align', 'toggle_align', array( 'left' => 'start', 'center' => 'center', 'right' => 'end', 'start' => 'start', 'end' => 'end' ), true );
		$s->typ( $o, 'typography', 'menu_typography' );
		$s->col( $o, 'color', 'color_menu_item' );
		$s->col( $o, 'hover_color', 'color_menu_item_hover' );
		$s->col( $o, 'pointer_color', 'pointer_color_menu_item_hover' );
		$s->col( $o, 'active_color', 'color_menu_item_active' );
		$s->col( $o, 'active_pointer_color', 'pointer_color_menu_item_active' );
		$s->sl( $o, 'pointer_width', 'pointer_width', false );
		foreach ( Source::SUFFIXES as $suffix ) {
			$h = $s->num( 'padding_horizontal_menu_item' . $suffix );
			$v = $s->num( 'padding_vertical_menu_item' . $suffix );
			if ( null !== $h || null !== $v ) {
				$o[ 'item_padding' . $suffix ] = array( 'top' => $v ?? 13, 'right' => $h ?? 20, 'bottom' => $v ?? 13, 'left' => $h ?? 20, 'unit' => 'px', 'linked' => false );
			}
		}
		$s->sl( $o, 'item_gap', 'menu_space_between' );
		$s->sl( $o, 'item_radius', 'border_radius_menu_item', false );
		$s->col( $o, 'dd_color', 'color_dropdown_item' );
		$s->col( $o, 'dd_bg', 'background_color_dropdown_item' );
		$s->col( $o, 'dd_hover_color', 'color_dropdown_item_hover' );
		$s->col( $o, 'dd_hover_bg', 'background_color_dropdown_item_hover' );
		$s->col( $o, 'dd_active_color', 'color_dropdown_item_active' );
		$s->col( $o, 'dd_active_bg', 'background_color_dropdown_item_active' );
		$s->typ( $o, 'dd_typography', 'dropdown_typography' );
		$s->brd( $o, 'dd_border', 'dropdown_border' );
		$s->radius( $o, 'dd_radius', 'dropdown_border_radius' );
		$s->shd( $o, 'dd_shadow', 'dropdown_box_shadow' );
		$h = $s->num( 'padding_horizontal_dropdown_item' );
		$v = $s->num( 'padding_vertical_dropdown_item' );
		if ( null !== $h || null !== $v ) {
			$o['dd_item_padding'] = array( 'top' => $v ?? 15, 'right' => $h ?? 20, 'bottom' => $v ?? 15, 'left' => $h ?? 20, 'unit' => 'px', 'linked' => false );
		}
		$s->sl( $o, 'dd_offset', 'dropdown_top_distance', false );
		$s->col( $o, 'toggle_color', 'toggle_color' );
		$s->col( $o, 'toggle_bg', 'toggle_background_color' );
		$s->sl( $o, 'toggle_size', 'toggle_size' );
		$s->use( 'toggle', 'text_align', 'animation_line', 'animation_framed', 'animation_background', 'animation_text', 'indicator', 'menu_name' );
		return array( 'nav-menu', $o );
	}

	public static function call_to_action( Source $s, Converter $c ): array {
		$o           = array();
		$o['layout'] = 'cover' === $s->str( 'skin', 'classic' ) ? 'cover' : 'classic';
		$s->opt( $o, 'image_position', 'layout', array( 'left' => 'left', 'right' => 'right', 'above' => 'top' ), true );
		$s->img( $o, 'image', 'bg_image' );
		$size = $s->str( 'bg_image_size' );
		if ( '' !== $size ) {
			$o['image_size'] = 'custom' === $size ? 'full' : $size;
		}
		$graphic = $s->str( 'graphic_element', 'none' );
		if ( 'none' !== $graphic ) {
			$c->setting( 'call-to-action', 'graphic element: ' . $graphic );
		}
		$s->use( 'graphic_image', 'selected_icon', 'icon', 'graphic_image_size', 'icon_view', 'icon_shape' );
		$s->text( $o, 'title', 'title' );
		$s->text( $o, 'description', 'description' );
		$o += array(
			'title'       => 'This is the heading',
			'description' => self::LOREM,
		);
		$o['eyebrow'] = '';
		$s->opt( $o, 'title_tag', 'title_tag', self::TAGS );
		$s->text( $o, 'button_text', 'button' );
		$o += array( 'button_text' => 'Click Here' );
		$s->lnk( $o, 'link', 'link' );
		$s->text( $o, 'ribbon_text', 'ribbon_title' );
		$s->opt( $o, 'ribbon_position', 'ribbon_horizontal_position', array( 'left' => 'left', 'right' => 'right' ) );
		$s->sl( $o, 'min_height', 'min-height' );
		$s->opt( $o, 'align', 'alignment', self::ALIGN3, true );
		$s->opt( $o, 'vertical_align', 'vertical_position', array( 'top' => 'top', 'middle' => 'middle', 'bottom' => 'bottom' ), true );
		$s->dm( $o, 'padding', 'padding' );
		$s->bgc( $o, 'background', 'content_bg_color' );
		$s->sl( $o, 'media_width', 'image_min_width' );
		$s->sl( $o, 'media_height', 'image_min_height' );
		$s->col( $o, 'title_color', 'title_color' );
		$s->typ( $o, 'title_typography', 'title_typography' );
		$s->sl( $o, 'title_spacing', 'title_spacing' );
		$s->col( $o, 'description_color', 'description_color' );
		$s->typ( $o, 'description_typography', 'description_typography' );
		$s->sl( $o, 'description_spacing', 'description_spacing' );
		$s->typ( $o, 'button_typography', 'button_typography' );
		$s->col( $o, 'button_color', 'button_text_color' );
		$s->col( $o, 'button_background', 'button_background_color' );
		$s->col( $o, 'button_border_color', 'button_border_color' );
		$s->col( $o, 'button_hover_color', 'button_hover_text_color' );
		$s->col( $o, 'button_hover_background', 'button_hover_background_color' );
		$s->col( $o, 'button_hover_border_color', 'button_hover_border_color' );
		$s->radius( $o, 'button_radius', 'button_border_radius' );
		$o['button_size'] = self::BUTTON_SIZES[ $s->str( 'button_size', 'sm' ) ] ?? 'md';
		$s->bgc( $o, 'overlay', 'overlay_color' );
		$s->bgc( $o, 'overlay_hover', 'overlay_color_hover' );
		$blend = $s->str( 'overlay_blend_mode' );
		if ( in_array( $blend, array( 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color', 'luminosity', 'soft-light' ), true ) ) {
			$o['overlay_blend'] = $blend;
		}
		$hover            = $s->str( 'transformation' );
		$o['image_hover'] = array( 'zoom-in' => 'zoom', 'zoom-out' => 'zoom-out' )[ $hover ] ?? '';
		$s->col( $o, 'ribbon_background', 'ribbon_bg_color' );
		$s->col( $o, 'ribbon_color', 'ribbon_text_color' );
		$s->typ( $o, 'ribbon_typography', 'ribbon_typography' );
		$s->use( 'link_click', 'button_icon', 'selected_button_icon', 'effect_duration' );
		return array( 'call-to-action', $o );
	}

	public static function price_list( Source $s, Converter $c ): array {
		$o     = array();
		$items = array();
		foreach ( (array) $s->raw( 'price_list' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = array(
				'title' => (string) ( $row['title'] ?? '' ),
				'price' => (string) ( $row['price'] ?? '' ),
			);
			if ( '' !== (string) ( $row['item_description'] ?? '' ) ) {
				$item['description'] = (string) $row['item_description'];
			}
			$img = $c->media( $row['image'] ?? null );
			if ( $img ) {
				$item['image'] = $img;
			}
			$link = $c->link( $row['link'] ?? null );
			if ( $link ) {
				$item['link'] = $link;
			}
			$items[] = self::row( $item, $row );
		}
		$o['items'] = $items;
		$sep        = $s->str( 'separator_style', 'dotted' );
		$o['leader'] = array( 'solid' => 'solid', 'dotted' => 'dotted', 'dashed' => 'dashed', 'double' => 'solid', 'none' => 'none' )[ $sep ] ?? 'dotted';
		$size = $s->str( 'image_size' );
		if ( '' !== $size ) {
			$o['image_size'] = 'custom' === $size ? 'full' : $size;
		}
		$s->col( $o, 'title_color', 'heading_color' );
		$s->typ( $o, 'title_typography', 'heading_typography' );
		$s->col( $o, 'price_color', 'price_color' );
		$s->typ( $o, 'price_typography', 'price_typography' );
		$s->col( $o, 'description_color', 'description_color' );
		$s->typ( $o, 'description_typography', 'description_typography' );
		$s->col( $o, 'leader_color', 'separator_color' );
		$s->sl( $o, 'leader_weight', 'separator_weight', false );
		$s->sl( $o, 'leader_spacing', 'separator_spacing', false );
		$s->sl( $o, 'row_gap', 'row_gap' );
		$s->sl( $o, 'image_gap', 'image_spacing' );
		$va = $s->str( 'vertical_align' );
		if ( in_array( $va, array( 'middle', 'center' ), true ) ) {
			$o['vertical_align'] = 'center';
		}
		return array( 'price-list', $o );
	}

	public static function price_table( Source $s, Converter $c ): array {
		$o = array();
		$s->text( $o, 'title', 'heading' );
		$s->text( $o, 'subtitle', 'sub_heading' );
		$o += array(
			'title'    => 'Enter your title',
			'subtitle' => '',
		);
		$s->opt( $o, 'title_tag', 'heading_tag', self::TAGS );
		$symbols = array(
			''             => '',
			'dollar'       => '$',
			'euro'         => '€',
			'baht'         => '฿',
			'franc'        => '₣',
			'guilder'      => 'ƒ',
			'krona'        => 'kr',
			'lira'         => '₤',
			'peseta'       => '₧',
			'peso'         => '₱',
			'pound'        => '£',
			'real'         => 'R$',
			'ruble'        => '₽',
			'rupee'        => '₨',
			'indian_rupee' => '₹',
			'shekel'       => '₪',
			'won'          => '₩',
			'yen'          => '¥',
		);
		$symbol        = $s->str( 'currency_symbol', 'dollar' );
		$o['currency'] = 'custom' === $symbol ? $s->str( 'currency_symbol_custom' ) : ( $symbols[ $symbol ] ?? '$' );
		$s->use( 'currency_symbol_custom', 'currency_format' );
		if ( 'after' === $s->str( 'currency_position' ) ) {
			$o['currency_position'] = 'after';
		}
		$price = $s->str( 'price', '39.99' );
		$sep   = ',' === (string) $s->raw( 'currency_format' ) ? ',' : '.';
		$parts = explode( $sep, $price, 2 );
		$o['price']    = $parts[0];
		$o['fraction'] = $parts[1] ?? '';
		$s->text( $o, 'period', 'period' );
		$o += array( 'period' => 'Monthly' );
		$o['period_position'] = 'beside' === $s->str( 'period_position', 'below' ) ? 'beside' : 'below';
		if ( $s->yes( 'sale' ) ) {
			$s->text( $o, 'original_price', 'original_price' );
		}
		$s->use( 'original_price' );
		$features = array();
		foreach ( (array) $s->raw( 'features_list' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$f    = array(
				'text'     => (string) ( $row['item_text'] ?? '' ),
				'included' => true,
			);
			$icon = $c->icon( $row['selected_item_icon'] ?? ( $row['item_icon'] ?? null ) );
			if ( $icon ) {
				$f['icon'] = $icon;
			}
			$color = Utils::sanitize_color( (string) ( $row['item_icon_color'] ?? '' ) );
			if ( '' !== $color ) {
				$f['color'] = $color;
			}
			$features[] = self::row( $f, $row );
		}
		$o['features'] = $features;
		$s->text( $o, 'button_text', 'button_text' );
		$o += array( 'button_text' => 'Click Here' );
		$s->lnk( $o, 'link', 'link' );
		$o['button_size'] = self::BUTTON_SIZES[ $s->str( 'button_size', 'md' ) ] ?? 'md';
		$s->text( $o, 'footer_note', 'footer_additional_info' );
		$o += array( 'footer_note' => '' );
		if ( $s->yes( 'show_ribbon', true ) && '' !== $s->str( 'ribbon_title' ) ) {
			$o['badge_text']  = $s->str( 'ribbon_title' );
			$o['badge_style'] = 'ribbon';
			$s->opt( $o, 'badge_position', 'ribbon_horizontal_position', array( 'left' => 'left', 'right' => 'right' ) );
		}
		$s->use( 'ribbon_title', 'ribbon_horizontal_position' );
		$s->col( $o, 'title_color', 'heading_color' );
		$s->typ( $o, 'title_typography', 'heading_typography' );
		$s->col( $o, 'subtitle_color', 'sub_heading_color' );
		$s->typ( $o, 'subtitle_typography', 'sub_heading_typography' );
		$s->col( $o, 'price_color', 'price_color' );
		$s->typ( $o, 'price_typography', 'price_typography' );
		$s->col( $o, 'period_color', 'period_color' );
		$s->typ( $o, 'period_typography', 'period_typography' );
		$s->col( $o, 'features_color', 'features_list_color' );
		$s->typ( $o, 'features_typography', 'features_list_typography' );
		$s->col( $o, 'button_color', 'button_text_color' );
		$s->col( $o, 'button_background', 'button_background_color' );
		$bb = $s->border( 'button_border' );
		if ( ! empty( $bb['color'] ) ) {
			$o['button_border_color'] = $bb['color'];
		}
		$s->radius( $o, 'button_radius', 'button_border_radius' );
		$s->col( $o, 'button_hover_color', 'button_hover_color' );
		$s->col( $o, 'button_hover_background', 'button_background_hover_color' );
		$s->col( $o, 'button_hover_border_color', 'button_hover_border_color' );
		$s->dm( $o, 'button_padding', 'button_text_padding' );
		$s->typ( $o, 'button_typography', 'button_typography' );
		$s->col( $o, 'footer_color', 'additional_info_color' );
		$s->typ( $o, 'footer_typography', 'additional_info_typography' );
		$s->col( $o, 'badge_background', 'ribbon_bg_color' );
		$s->col( $o, 'badge_color', 'ribbon_text_color' );
		$s->typ( $o, 'badge_typography', 'ribbon_typography' );
		$header = $s->color( 'header_bg_color' );
		if ( '' !== $header ) {
			$c->setting( 'price-table', 'header background' );
		}
		return array( 'price-table', $o );
	}

	public static function flip_box( Source $s, Converter $c ): array {
		$o       = array();
		$graphic = $s->str( 'graphic_element', 'icon' );
		$o['front_media'] = in_array( $graphic, array( 'none', 'icon', 'image' ), true ) ? $graphic : 'icon';
		if ( 'icon' === $graphic ) {
			$s->ico( $o, 'front_icon', 'selected_icon', 'icon' );
			$o += array( 'front_icon' => array( 'library' => 'fa-solid', 'value' => 'star' ) );
		} elseif ( 'image' === $graphic ) {
			$s->img( $o, 'front_image', 'image' );
		}
		$s->use( 'selected_icon', 'icon', 'image', 'image_size', 'icon_view', 'icon_shape' );
		$s->text( $o, 'front_title', 'title_text_a' );
		$s->text( $o, 'front_description', 'description_text_a' );
		$s->text( $o, 'back_title', 'title_text_b' );
		$s->text( $o, 'back_description', 'description_text_b' );
		$o += array(
			'front_title'       => 'This is the heading',
			'front_description' => self::LOREM,
			'back_title'        => 'This is the heading',
			'back_description'  => self::LOREM,
		);
		$s->text( $o, 'button_text', 'button_text' );
		$o += array( 'button_text' => 'Click Here' );
		$s->lnk( $o, 'link', 'link' );
		$effect = $s->str( 'flip_effect', 'flip' );
		$dir    = $s->str( 'flip_direction', 'up' );
		if ( 'flip' === $effect ) {
			$o['effect'] = in_array( $dir, array( 'left', 'right' ), true ) ? 'flip-x' : 'flip-y';
		} elseif ( in_array( $effect, array( 'slide', 'push' ), true ) ) {
			$o['effect']          = 'slide';
			$o['slide_direction'] = array( 'up' => 'bottom', 'down' => 'top', 'left' => 'right', 'right' => 'left' )[ $dir ] ?? 'bottom';
		} elseif ( in_array( $effect, array( 'zoom-in', 'zoom-out' ), true ) ) {
			$o['effect'] = 'zoom';
		} else {
			$o['effect'] = 'fade';
		}
		if ( '' !== $s->str( 'flip_3d' ) ) {
			$o['depth'] = true;
		}
		$s->opt( $o, 'title_tag', 'title_tag', self::TAGS );
		$s->sl( $o, 'height', 'height' );
		$s->radius( $o, 'radius', 'border_radius' );
		Source::put( $o, 'front_background', $s->bg( 'background_a' ) );
		$s->col( $o, 'front_overlay', 'background_overlay_a' );
		$s->opt( $o, 'front_align', 'alignment_a', self::ALIGN3, true );
		$s->opt( $o, 'front_valign', 'vertical_position_a', array( 'top' => 'top', 'middle' => 'middle', 'bottom' => 'bottom' ), true );
		$s->dm( $o, 'front_padding', 'padding_a' );
		$s->col( $o, 'icon_color', 'icon_primary_color' );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$s->col( $o, 'front_title_color', 'title_color_a' );
		$s->typ( $o, 'front_title_typography', 'title_typography_a' );
		$s->col( $o, 'front_description_color', 'description_color_a' );
		$s->typ( $o, 'front_description_typography', 'description_typography_a' );
		Source::put( $o, 'back_background', $s->bg( 'background_b' ) );
		$s->col( $o, 'back_overlay', 'background_overlay_b' );
		$s->opt( $o, 'back_align', 'alignment_b', self::ALIGN3, true );
		$s->opt( $o, 'back_valign', 'vertical_position_b', array( 'top' => 'top', 'middle' => 'middle', 'bottom' => 'bottom' ), true );
		$s->dm( $o, 'back_padding', 'padding_b' );
		$s->col( $o, 'back_title_color', 'title_color_b' );
		$s->typ( $o, 'back_title_typography', 'title_typography_b' );
		$s->col( $o, 'back_description_color', 'description_color_b' );
		$s->typ( $o, 'back_description_typography', 'description_typography_b' );
		$s->col( $o, 'button_color', 'button_text_color' );
		$s->col( $o, 'button_background', 'button_background_color' );
		$s->col( $o, 'button_border_color', 'button_border_color' );
		$s->col( $o, 'button_hover_color', 'button_hover_text_color' );
		$s->col( $o, 'button_hover_background', 'button_hover_background_color' );
		$s->col( $o, 'button_hover_border_color', 'button_hover_border_color' );
		$s->typ( $o, 'button_typography', 'button_typography' );
		$s->radius( $o, 'button_radius', 'button_border_radius' );
		$o['button_size'] = self::BUTTON_SIZES[ $s->str( 'button_size', 'sm' ) ] ?? 'md';
		$s->use( 'link_click' );
		return array( 'flip-box', $o );
	}

	public static function countdown( Source $s ): array {
		$o = array();
		if ( 'evergreen' === $s->str( 'countdown_type', 'due_date' ) ) {
			$o['mode'] = 'evergreen';
			$h         = $s->num( 'evergreen_counter_hours' );
			$m         = $s->num( 'evergreen_counter_minutes' );
			$o['evergreen_hours']   = null !== $h ? (int) $h : 47;
			$o['evergreen_minutes'] = null !== $m ? (int) $m : 59;
		} else {
			$o['mode'] = 'due';
			$s->text( $o, 'due_date', 'due_date' );
		}
		$o['view'] = 'inline' === $s->str( 'label_display', 'block' ) ? 'inline' : 'boxes';
		foreach ( array( 'days', 'hours', 'minutes', 'seconds' ) as $unit ) {
			$o[ 'show_' . $unit ] = $s->yes( 'show_' . $unit, true );
		}
		$o['show_labels'] = $s->yes( 'show_labels', true );
		if ( $s->yes( 'custom_labels' ) ) {
			foreach ( array( 'days', 'hours', 'minutes', 'seconds' ) as $unit ) {
				$s->text( $o, 'label_' . $unit, 'label_' . $unit );
			}
		}
		$s->use( 'label_days', 'label_hours', 'label_minutes', 'label_seconds' );
		$actions = array_values( array_intersect( (array) $s->raw( 'expire_actions' ), array( 'hide', 'message', 'redirect' ) ) );
		if ( $s->exists( 'expire_actions' ) ) {
			$o['expire_actions'] = $actions;
		}
		$s->text( $o, 'expire_message', 'message_after_expire' );
		$s->lnk( $o, 'expire_redirect', 'expire_redirect_url' );
		$s->col( $o, 'box_background', 'box_background_color' );
		$s->brd( $o, 'box_border', 'box_border' );
		$s->radius( $o, 'box_radius', 'box_border_radius' );
		$s->sl( $o, 'gap', 'box_spacing' );
		$s->dm( $o, 'box_padding', 'box_padding' );
		$s->col( $o, 'digits_color', 'digits_color' );
		$s->typ( $o, 'digits_typography', 'digits_typography' );
		$s->col( $o, 'label_color', 'label_color' );
		$s->typ( $o, 'label_typography', 'label_typography' );
		$s->use( 'evergreen_counter_hours', 'evergreen_counter_minutes' );
		return array( 'countdown', $o );
	}

	public static function animated_headline( Source $s ): array {
		$o         = array();
		$rotate    = 'rotate' === $s->str( 'headline_style', 'highlight' );
		$o['style'] = $rotate ? 'rotating' : 'highlight';
		$s->text( $o, 'before_text', 'before_text' );
		$s->text( $o, 'after_text', 'after_text' );
		$o += array(
			'before_text' => 'This page is',
			'after_text'  => '',
		);
		if ( $rotate ) {
			$s->text( $o, 'rotating_text', 'rotating_text' );
			$o += array( 'rotating_text' => "Better\nBigger\nFaster" );
			$o['effect'] = array( 'typing' => 'typing', 'clip' => 'clip', 'flip' => 'flip', 'swirl' => 'flip', 'blinds' => 'flip', 'drop-in' => 'slide', 'wave' => 'fade', 'slide' => 'slide', 'slide-down' => 'slide' )[ $s->str( 'animation_type', 'typing' ) ] ?? 'typing';
			$delay = $s->num( 'rotate_iteration_delay' );
			if ( null !== $delay ) {
				$o['display_time'] = (int) $delay;
			}
		} else {
			$s->text( $o, 'highlighted_text', 'highlighted_text' );
			$o += array( 'highlighted_text' => 'Amazing' );
			$o['shape'] = array( 'circle' => 'circle', 'curly' => 'curly', 'underline' => 'underline', 'double' => 'double', 'double_underline' => 'double', 'underline_zigzag' => 'curly', 'diagonal' => 'strike', 'strikethrough' => 'strike', 'x' => 'cross' )[ $s->str( 'marker', 'circle' ) ] ?? 'circle';
			$duration   = $s->num( 'highlight_animation_duration' );
			if ( null !== $duration ) {
				$o['draw_duration'] = (int) $duration;
			}
			$delay = $s->num( 'highlight_iteration_delay' );
			if ( null !== $delay ) {
				$o['display_time'] = (int) $delay;
			}
		}
		$s->use( 'rotating_text', 'highlighted_text', 'animation_type', 'marker', 'rotate_iteration_delay', 'highlight_iteration_delay', 'highlight_animation_duration' );
		$s->opt( $o, 'tag', 'tag', self::TAGS );
		$o += array( 'tag' => 'h3' );
		$s->lnk( $o, 'link', 'link' );
		$s->opt( $o, 'align', 'alignment', self::ALIGN, true );
		$o += array( 'align' => 'center' );
		$o['loop'] = $s->yes( 'loop', true );
		$s->col( $o, 'shape_color', 'marker_color' );
		$width = $s->num( 'stroke_width' );
		if ( null !== $width ) {
			$o['shape_width'] = Utils::number( $width );
		}
		if ( $s->yes( 'above_content' ) ) {
			$o['shape_front'] = true;
		}
		$s->col( $o, 'color', 'title_color' );
		$s->typ( $o, 'typography', 'title_typography' );
		$s->col( $o, 'animated_color', 'words_color' );
		$s->typ( $o, 'animated_typography', 'words_typography' );
		$s->use( 'rounded_edges', 'typing_selected_color', 'typing_selected_bg_color' );
		return array( 'animated-headline', $o );
	}

	public static function testimonial_carousel( Source $s, Converter $c ): array {
		$o      = array();
		$items  = array();
		$review = 'reviews' === $s->type;
		foreach ( (array) $s->raw( 'slides' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item   = array(
				'quote' => wp_strip_all_tags( (string) ( $row['content'] ?? '' ) ),
				'name'  => (string) ( $row['name'] ?? '' ),
				'role'  => (string) ( $row['title'] ?? '' ),
			);
			$avatar = $c->media( $row['image'] ?? null );
			if ( $avatar ) {
				$item['avatar'] = $avatar;
			}
			if ( is_numeric( $row['rating'] ?? null ) ) {
				$item['rating'] = Utils::number( $row['rating'] );
			}
			$items[] = self::row( $item, $row );
		}
		$o['items']       = $items;
		$layout           = $s->str( 'layout', 'image_inline' );
		$o['layout']      = in_array( $layout, array( 'image_stacked', 'image_above' ), true ) ? 'centered' : 'classic';
		$o['show_rating'] = $review;
		self::carousel( $s, $o, array( 1, 1, 1 ) );
		$s->opt( $o, 'align', 'alignment', self::ALIGN3, true );
		$s->bgc( $o, 'card_background', 'slide_background_color' );
		$bw = $s->num( 'slide_border_size' );
		$bc = $s->color( 'slide_border_color' );
		if ( null !== $bw && $bw > 0 ) {
			$o['card_border'] = array_filter(
				array(
					'style' => 'solid',
					'width' => array( 'top' => $bw, 'right' => $bw, 'bottom' => $bw, 'left' => $bw, 'unit' => 'px', 'linked' => true ),
					'color' => $bc,
				)
			);
		}
		$s->radius( $o, 'card_radius', 'slide_border_radius' );
		$s->dm( $o, 'card_padding', 'slide_padding' );
		$s->col( $o, 'quote_color', 'content_color' );
		$s->typ( $o, 'quote_typography', 'content_typography' );
		$s->col( $o, 'name_color', 'name_color' );
		$s->typ( $o, 'name_typography', 'name_typography' );
		$s->col( $o, 'role_color', 'title_color' );
		$s->typ( $o, 'role_typography', 'title_typography' );
		$s->sl( $o, 'avatar_size', 'image_size' );
		$s->radius( $o, 'avatar_radius', 'image_border_radius' );
		$s->col( $o, 'star_color', 'star_color' );
		$s->col( $o, 'star_empty_color', 'star_unmarked_color' );
		$s->use( 'skin', 'slides_per_view', 'show_arrows', 'star_style', 'unmarked_star_style' );
		return array( 'testimonial-carousel', $o );
	}

	public static function blockquote( Source $s, Converter $c ): array {
		$o = array();
		$s->text( $o, 'quote', 'blockquote_content' );
		$s->text( $o, 'citation', 'author_name' );
		$o += array(
			'quote'    => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
			'citation' => '',
		);
		$o['variant'] = array( 'border' => 'border', 'quotation' => 'quotation', 'boxed' => 'boxed', 'clean' => 'plain' )[ $s->str( 'blockquote_skin', 'border' ) ] ?? 'border';
		if ( $s->yes( 'tweet_button', true ) ) {
			$c->setting( 'blockquote', 'tweet button' );
		}
		$s->use( 'tweet_button_view', 'tweet_button_skin', 'tweet_button_label', 'user_name', 'url_type', 'url' );
		$s->opt( $o, 'align', 'alignment', self::ALIGN3, true );
		$s->col( $o, 'quote_color', 'content_text_color' );
		$s->typ( $o, 'quote_typography', 'content_typography' );
		$s->col( $o, 'citation_color', 'author_text_color' );
		$s->typ( $o, 'citation_typography', 'author_typography' );
		$s->col( $o, 'accent_color', 'border_color' );
		$s->sl( $o, 'border_width', 'border_width' );
		$s->sl( $o, 'border_gap', 'border_gap' );
		$s->bgc( $o, 'box_background', 'box_background_color' );
		$s->dm( $o, 'box_padding', 'box_padding' );
		$s->radius( $o, 'box_radius', 'box_border_radius' );
		$s->col( $o, 'mark_color', 'quote_text_color' );
		$s->sl( $o, 'mark_size', 'quote_size' );
		$s->sl( $o, 'mark_spacing', 'quote_gap' );
		return array( 'blockquote', $o );
	}

	public static function share_buttons( Source $s, Converter $c ): array {
		$o        = array();
		$networks = array();
		$map      = array(
			'facebook'  => 'facebook',
			'twitter'   => 'x',
			'x-twitter' => 'x',
			'linkedin'  => 'linkedin',
			'whatsapp'  => 'whatsapp',
			'telegram'  => 'telegram',
			'pinterest' => 'pinterest',
			'reddit'    => 'reddit',
			'email'     => 'email',
		);
		$rows     = $s->exists( 'share_buttons' ) ? (array) $s->raw( 'share_buttons' ) : array( array( 'button' => 'facebook' ), array( 'button' => 'twitter' ), array( 'button' => 'linkedin' ) );
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$net = (string) ( $row['button'] ?? '' );
			if ( ! isset( $map[ $net ] ) ) {
				$c->setting( 'share-buttons', 'network: ' . $net );
				continue;
			}
			$n = array( 'network' => $map[ $net ] );
			if ( '' !== (string) ( $row['text'] ?? '' ) ) {
				$n['label'] = (string) $row['text'];
			}
			$networks[] = self::row( $n, $row );
		}
		$o['networks'] = $networks;
		$o['view']     = in_array( $s->str( 'view', 'icon-text' ), array( 'icon', 'text', 'icon-text' ), true ) ? $s->str( 'view', 'icon-text' ) : 'icon-text';
		$o['skin']     = array( 'gradient' => 'solid', 'flat' => 'solid', 'minimal' => 'minimal', 'framed' => 'outline', 'boxed' => 'soft' )[ $s->str( 'skin', 'gradient' ) ] ?? 'solid';
		$o['shape']    = in_array( $s->str( 'shape', 'square' ), array( 'square', 'rounded', 'circle' ), true ) ? $s->str( 'shape', 'square' ) : 'square';
		foreach ( Source::SUFFIXES as $suffix ) {
			$cols = $s->str( 'columns' . $suffix );
			if ( in_array( $cols, array( '1', '2', '3', '4', '5', '6' ), true ) ) {
				$o[ 'columns' . $suffix ] = $cols;
			}
		}
		$s->opt( $o, 'align', 'alignment', self::ALIGN, true );
		if ( 'custom' === $s->str( 'share_url_type' ) ) {
			$o['share_source'] = 'custom';
			$s->lnk( $o, 'custom_url', 'share_url' );
		}
		if ( 'custom' === $s->str( 'color_source' ) ) {
			$o['color_source'] = 'custom';
			$s->col( $o, 'custom_color', 'primary_color' );
			$s->col( $o, 'button_color', 'secondary_color' );
		}
		$s->use( 'share_url', 'primary_color', 'secondary_color' );
		$s->sl( $o, 'gap', 'column_gap' );
		$s->sl( $o, 'button_size', 'button_size' );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$s->typ( $o, 'typography', 'typography' );
		$o['hover_effect'] = '';
		return array( 'share-buttons', $o );
	}

	public static function lottie( Source $s ): array {
		$o = array();
		if ( 'external_url' === $s->str( 'source', 'media_file' ) ) {
			$o['source'] = 'url';
			$url         = $s->raw( 'source_external_url' );
			$url         = is_array( $url ) ? (string) ( $url['url'] ?? '' ) : (string) $url;
			if ( '' !== $url ) {
				$o['url'] = $url;
			}
		} else {
			$o['source'] = 'media';
			$s->img( $o, 'file', 'source_json' );
		}
		$s->use( 'source_external_url', 'source_json' );
		$o['trigger'] = array( 'arriving_to_viewport' => 'autoplay', 'on_click' => 'click', 'on_hover' => 'hover', 'bind_to_scroll' => 'scroll', 'none' => 'autoplay' )[ $s->str( 'trigger', 'arriving_to_viewport' ) ] ?? 'autoplay';
		if ( 'scroll' !== $o['trigger'] ) {
			$o['loop'] = $s->yes( 'loop' );
		}
		$speed = $s->num( 'play_speed' );
		if ( null !== $speed ) {
			$o['speed'] = Utils::number( $speed );
		}
		if ( $s->yes( 'reverse_animation' ) ) {
			$o['reverse'] = true;
		}
		$out = $s->str( 'on_hover_out' );
		if ( in_array( $out, array( 'reverse', 'pause' ), true ) ) {
			$o['hover_out'] = $out;
		}
		if ( 'custom' === $s->str( 'link_to' ) ) {
			$s->lnk( $o, 'link', 'custom_link' );
		}
		$s->use( 'custom_link', 'number_of_times', 'start_point', 'end_point', 'viewport', 'renderer', 'lazyload' );
		$s->opt( $o, 'align', 'align', array( 'left' => 'flex-start', 'start' => 'flex-start', 'center' => 'center', 'right' => 'flex-end', 'end' => 'flex-end' ), true );
		$s->sl( $o, 'width', 'width' );
		$s->sl( $o, 'max_width', 'space' );
		$opacity = $s->num( 'opacity' );
		if ( null !== $opacity ) {
			$o['opacity'] = Utils::number( max( 0, min( 1, $opacity ) ) );
		}
		return array( 'lottie', $o );
	}

	public static function table_of_contents( Source $s ): array {
		$o = array();
		$s->text( $o, 'title', 'title' );
		$o += array( 'title' => 'Table of Contents' );
		$s->opt( $o, 'title_tag', 'html_tag', self::TAGS );
		$o += array( 'title_tag' => 'h4' );
		$tags = array_values( array_intersect( (array) $s->raw( 'headings_by_tags' ), array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) );
		$o['headings'] = $tags ? $tags : array( 'h2', 'h3', 'h4', 'h5', 'h6' );
		$s->text( $o, 'container', 'container' );
		$s->text( $o, 'exclude', 'exclude_headings_by_selector' );
		$o['marker']       = 'bullets' === $s->str( 'marker_view', 'numbers' ) ? 'bullets' : 'numbers';
		$o['hierarchical'] = $s->yes( 'hierarchical_view', true );
		$o['collapsible']  = $s->yes( 'minimize_box', true );
		if ( $o['collapsible'] && 'desktop' === $s->str( 'minimized_on' ) ) {
			$o['collapsed'] = true;
		}
		$s->use( 'icon', 'expand_icon', 'collapse_icon', 'minimized_on', 'collapse_subitems', 'no_headings_message' );
		$s->bgc( $o, 'box_background', 'background_color' );
		$bw = $s->num( 'border_width' );
		$bc = $s->color( 'border_color' );
		if ( null !== $bw || '' !== $bc ) {
			$w                = null !== $bw ? $bw : 1;
			$o['box_border'] = array_filter(
				array(
					'style' => 'solid',
					'width' => array( 'top' => $w, 'right' => $w, 'bottom' => $w, 'left' => $w, 'unit' => 'px', 'linked' => true ),
					'color' => $bc,
				)
			);
		}
		$s->radius( $o, 'box_radius', 'border_radius' );
		$s->dm( $o, 'box_padding', 'padding' );
		$s->sl( $o, 'max_height', 'max_height' );
		$s->col( $o, 'title_color', 'header_text_color' );
		$s->typ( $o, 'title_typography', 'header_typography' );
		$s->col( $o, 'link_color', 'item_text_color_normal' );
		$s->col( $o, 'link_hover_color', 'item_text_color_hover' );
		$s->col( $o, 'link_active_color', 'item_text_color_active' );
		$s->typ( $o, 'list_typography', 'item_typography' );
		$s->col( $o, 'marker_color', 'marker_color' );
		return array( 'table-of-contents', $o );
	}

	public static function code_highlight( Source $s ): array {
		$o    = array();
		$code = $s->raw( 'code' );
		if ( is_string( $code ) ) {
			$o['code'] = $code;
		}
		$lang = $s->str( 'language', 'javascript' );
		$langs = array( 'markup' => 'html', 'html' => 'html', 'css' => 'css', 'scss' => 'scss', 'javascript' => 'javascript', 'typescript' => 'typescript', 'jsx' => 'jsx', 'json' => 'json', 'php' => 'php', 'python' => 'python', 'ruby' => 'ruby', 'go' => 'go', 'rust' => 'rust', 'java' => 'java', 'kotlin' => 'kotlin', 'swift' => 'swift', 'c' => 'c', 'cpp' => 'cpp', 'csharp' => 'csharp', 'bash' => 'bash', 'shell' => 'bash', 'powershell' => 'powershell', 'sql' => 'sql', 'yaml' => 'yaml', 'markdown' => 'markdown', 'diff' => 'diff' );
		$o['language']     = $langs[ $lang ] ?? 'plaintext';
		$o['line_numbers'] = '' !== $s->str( 'line_numbers', 'line-numbers' );
		$o['copy_button']  = '' !== $s->str( 'copy_to_clipboard', 'copy-to-clipboard' );
		$s->text( $o, 'highlight_lines', 'highlight_lines' );
		if ( '' !== $s->str( 'word_wrap' ) ) {
			$o['wrap_lines'] = true;
		}
		$theme      = $s->str( 'theme', 'prism' );
		$o['theme'] = in_array( $theme, array( 'prism', 'solarizedlight' ), true ) ? 'light' : 'dark';
		$s->sl( $o, 'max_height', 'height' );
		$s->sl( $o, 'font_size', 'font_size' );
		return array( 'code-highlight', $o );
	}

	public static function hotspot( Source $s, Converter $c ): array {
		$o = array();
		$s->img( $o, 'image', 'image' );
		$size = $s->str( 'image_size' );
		if ( '' !== $size ) {
			$o['size'] = 'custom' === $size ? 'full' : $size;
		}
		$spots = array();
		foreach ( (array) $s->raw( 'hotspot' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$x    = is_numeric( $row['hotspot_offset_x']['size'] ?? null ) ? (float) $row['hotspot_offset_x']['size'] : 50;
			$y    = is_numeric( $row['hotspot_offset_y']['size'] ?? null ) ? (float) $row['hotspot_offset_y']['size'] : 50;
			$x    = 'right' === ( $row['hotspot_horizontal'] ?? 'left' ) ? 100 - $x : $x;
			$y    = 'bottom' === ( $row['hotspot_vertical'] ?? 'top' ) ? 100 - $y : $y;
			$spot = array(
				'label'   => (string) ( $row['hotspot_label'] ?? '' ),
				'content' => wp_strip_all_tags( (string) ( $row['hotspot_tooltip_content'] ?? '' ) ),
				'x'       => array( 'size' => Utils::number( $x ), 'unit' => '%' ),
				'y'       => array( 'size' => Utils::number( $y ), 'unit' => '%' ),
			);
			$icon = $c->icon( $row['hotspot_icon'] ?? null );
			if ( $icon ) {
				$spot['icon'] = $icon;
			}
			$link = $c->link( $row['hotspot_link'] ?? null );
			if ( $link ) {
				$spot['link'] = $link;
			}
			$spots[] = self::row( $spot, $row );
		}
		$o['hotspots'] = $spots;
		$o['trigger']  = 'mouseenter' === $s->str( 'tooltip_trigger', 'click' ) ? 'hover' : 'click';
		$s->opt( $o, 'tooltip_position', 'tooltip_position', array( 'top' => 'top', 'bottom' => 'bottom', 'left' => 'left', 'right' => 'right' ) );
		$s->use( 'tooltip_animation', 'hotspot_sequenced_animation', 'hotspot_animation' );
		return array( 'hotspot', $o );
	}

	/** Elementor Pro Slides → the Uncoder Slides widget (when this Uncoder has one). */
	public static function slides( Source $s, Converter $c ): ?array {
		if ( ! Plugin::instance()->elements()->get( 'slides' ) ) {
			return null;
		}
		$o     = array();
		$rows  = array();
		$pos_h = array( 'left' => 'left', 'center' => 'center', 'right' => 'right' );
		$pos_v = array( 'top' => 'top', 'middle' => 'middle', 'bottom' => 'bottom' );
		foreach ( (array) $s->raw( 'slides' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slide = array(
				'heading'     => (string) ( $row['heading'] ?? '' ),
				'description' => (string) ( $row['description'] ?? '' ),
				'button_text' => (string) ( $row['button_text'] ?? '' ),
			);
			$link  = $c->link( $row['link'] ?? null );
			if ( $link ) {
				$slide['link'] = $link;
				if ( 'button' !== ( $row['link_click'] ?? 'slide' ) ) {
					$slide['link_whole'] = true;
				}
			} else {
				$slide['link'] = array( 'url' => '' );
			}
			$image = $c->media( $row['background_image'] ?? null );
			$slide['image'] = $image ? $image : array( 'id' => 0, 'url' => '' );
			$bg    = Utils::sanitize_color( (string) ( $row['background_color'] ?? '' ) );
			if ( '' !== $bg ) {
				$slide['background_color'] = $bg;
			}
			if ( ! empty( $row['background_ken_burns'] ) ) {
				$slide['ken_burns'] = 'out' === ( $row['zoom_direction'] ?? 'in' ) ? 'out' : 'in';
			}
			if ( ! empty( $row['background_overlay'] ) ) {
				$overlay = Utils::sanitize_color( (string) ( $row['background_overlay_color'] ?? 'rgba(0,0,0,0.5)' ) );
				if ( '' !== $overlay ) {
					$slide['overlay_color'] = $overlay;
				}
			}
			if ( ! empty( $row['custom_style'] ) ) {
				foreach ( array( 'h_position' => array( 'horizontal_position', $pos_h ), 'v_position' => array( 'vertical_position', $pos_v ), 'text_align' => array( 'text_align', $pos_h ) ) as $to => $def ) {
					$v = (string) ( $row[ $def[0] ] ?? '' );
					if ( isset( $def[1][ $v ] ) ) {
						$slide[ $to ] = $def[1][ $v ];
					}
				}
				$color = Utils::sanitize_color( (string) ( $row['content_color'] ?? '' ) );
				if ( '' !== $color ) {
					$slide['text_color'] = $color;
				}
			}
			$rows[] = self::row( $slide, $row );
		}
		$o['slides'] = $rows;
		$s->sl( $o, 'height', 'slides_height' );
		$o += array( 'height' => array( 'size' => 400, 'unit' => 'px' ) );
		$nav             = $s->str( 'navigation', 'both' );
		$o['arrows']     = in_array( $nav, array( 'both', 'arrows' ), true );
		$o['pagination'] = in_array( $nav, array( 'both', 'dots' ), true ) ? 'dots' : '';
		$o['autoplay']   = $s->yes( 'autoplay', true );
		if ( $o['autoplay'] ) {
			$delay = $s->num( 'autoplay_speed' );
			$o['autoplay_delay'] = null !== $delay ? (int) $delay : 5000;
			$o['pause_on_hover'] = $s->yes( 'pause_on_hover', true );
		}
		$o['loop']       = $s->yes( 'infinite', true );
		$o['transition'] = 'fade' === $s->str( 'transition', 'slide' ) ? 'fade' : 'slide';
		$speed           = $s->num( 'transition_speed' );
		$o['speed']      = null !== $speed ? (int) $speed : 500;
		$o['content_animation'] = array( 'fadeInUp' => 'fade-up', 'fadeInDown' => 'fade-down', 'fadeInLeft' => 'fade', 'fadeInRight' => 'fade', 'zoomIn' => 'zoom', 'none' => '', '' => '' )[ $s->str( 'content_animation', 'fadeInUp' ) ] ?? 'fade-up';
		$s->opt( $o, 'heading_tag', 'slides_title_tag', self::TAGS );
		$s->sl( $o, 'content_width', 'content_max_width' );
		$s->dm( $o, 'content_padding', 'slides_padding' );
		$s->opt( $o, 'h_position', 'slides_horizontal_position', $pos_h, true );
		$s->opt( $o, 'v_position', 'slides_vertical_position', $pos_v, true );
		$s->opt( $o, 'text_align', 'slides_text_align', $pos_h, true );
		$o += array(
			'h_position' => 'center',
			'v_position' => 'middle',
			'text_align' => 'center',
		);
		$s->col( $o, 'heading_color', 'heading_color' );
		$s->typ( $o, 'heading_typography', 'heading_typography' );
		$s->sl( $o, 'heading_spacing', 'heading_spacing' );
		$s->col( $o, 'description_color', 'description_color' );
		$s->typ( $o, 'description_typography', 'description_typography' );
		$s->sl( $o, 'description_spacing', 'description_spacing' );
		// Elementor's slide buttons are white outlines unless styled.
		$o['button_variant'] = 'outline';
		$o['button_size']    = self::BUTTON_SIZES[ $s->str( 'button_size', 'sm' ) ] ?? 'md';
		$s->typ( $o, 'button_typography', 'button_typography' );
		$s->radius( $o, 'button_radius', 'button_border_radius' );
		$s->col( $o, 'button_color', 'button_text_color' );
		$s->col( $o, 'button_background', 'button_background_color' );
		$s->col( $o, 'button_border_color', 'button_border_color' );
		$s->col( $o, 'button_hover_color', 'button_hover_text_color' );
		$s->col( $o, 'button_hover_background', 'button_hover_background_color' );
		$s->col( $o, 'button_hover_border_color', 'button_hover_border_color' );
		$o += array(
			'button_color'        => '#ffffff',
			'button_border_color' => '#ffffff',
		);
		$s->sl( $o, 'arrow_icon_size', 'arrows_size' );
		$s->col( $o, 'arrow_color', 'arrows_color' );
		$s->sl( $o, 'dots_size', 'dots_size', false );
		$s->col( $o, 'dots_active_color', 'dots_color' );
		$s->use( 'pause_on_interaction', 'arrows_position', 'dots_position', 'button_border_width', 'slides_description_tag' );
		return array( 'slides', $o );
	}

	/** Elementor 4 atomic widgets (typed props). */
	public static function atomic( Source $s, Converter $c, array $el ): ?array {
		$s->use( ...array_map( 'strval', array_keys( $s->all() ) ) );
		return Atomic::widget( $el, $c );
	}

	/* ------------------------------------------------------------------ Theme Builder widgets */

	public static function site_logo( Source $s, Converter $c ): array {
		$o = array( 'source' => 'site' );
		$s->opt( $o, 'align', 'align', self::ALIGN3, true );
		$s->sl( $o, 'width', 'width' );
		$s->sl( $o, 'max_width', 'space' );
		$s->sl( $o, 'max_height', 'height' );
		$s->brd( $o, 'border', 'image_border' );
		$s->radius( $o, 'radius', 'image_border_radius' );
		$s->shd( $o, 'shadow', 'image_box_shadow' );
		Source::put( $o, 'filters', $s->filters( 'css_filters' ) );
		if ( 'none' === $s->str( 'link_to' ) ) {
			$o['link_to'] = '';
		}
		$s->use( 'image', 'image_size', 'link_to', 'link', 'caption_source' );
		return array( 'site-logo', $o );
	}

	public static function site_title( Source $s ): array {
		$o = array();
		$s->opt( $o, 'tag', 'header_size', self::TAGS );
		$o += array( 'tag' => 'h2' );
		$s->opt( $o, 'align', 'align', self::ALIGN, true );
		$s->col( $o, 'color', 'title_color' );
		$s->typ( $o, 'typography', 'typography' );
		$s->tsh( $o, 'text_shadow', 'text_shadow' );
		$s->use( 'title', 'link', 'size', 'blend_mode' );
		return array( 'site-title', $o );
	}

	public static function post_title( Source $s ): array {
		$o = array();
		$s->opt( $o, 'tag', 'header_size', self::TAGS );
		$o += array( 'tag' => 'h1' );
		$s->opt( $o, 'align', 'align', self::ALIGN, true );
		$s->col( $o, 'color', 'title_color' );
		$s->typ( $o, 'typography', 'typography' );
		$s->tsh( $o, 'text_shadow', 'text_shadow' );
		$s->use( 'title', 'size', 'blend_mode' );
		if ( '' !== (string) ( $s->all()['link']['url'] ?? '' ) ) {
			$o['link'] = true;
		}
		$s->use( 'link' );
		return array( 'post-title', $o );
	}

	public static function post_excerpt( Source $s ): array {
		$o = array();
		$s->opt( $o, 'align', 'align', self::ALIGN, true );
		$s->col( $o, 'color', 'text_color' );
		$s->typ( $o, 'typography', 'typography' );
		$len = $s->num( 'excerpt_length' );
		if ( null !== $len ) {
			$o['length'] = (int) $len;
		}
		$s->use( 'excerpt' );
		return array( 'post-excerpt', $o );
	}

	public static function post_content( Source $s ): array {
		$o = array();
		$s->opt( $o, 'align', 'align', self::ALIGN, true );
		$s->col( $o, 'color', 'text_color' );
		$s->typ( $o, 'typography', 'typography' );
		return array( 'post-content', $o );
	}

	public static function featured_image( Source $s ): array {
		$o    = array();
		$size = $s->str( 'image_size' );
		if ( '' !== $size ) {
			$o['size'] = 'custom' === $size ? 'full' : $size;
		}
		$s->opt( $o, 'align', 'align', self::ALIGN3, true );
		$s->sl( $o, 'width', 'width' );
		$s->sl( $o, 'max_width', 'space' );
		$s->sl( $o, 'height', 'height' );
		$s->opt( $o, 'object_fit', 'object-fit', array( 'cover' => 'cover', 'contain' => 'contain', 'fill' => 'fill' ), true );
		$s->brd( $o, 'border', 'image_border' );
		$s->radius( $o, 'radius', 'image_border_radius' );
		$s->shd( $o, 'shadow', 'image_box_shadow' );
		Source::put( $o, 'filters', $s->filters( 'css_filters' ) );
		$link = $s->str( 'link_to' );
		if ( 'file' === $link ) {
			$o['link_to'] = 'file';
		} elseif ( 'custom' === $link ) {
			$o['link_to'] = 'custom';
			$s->lnk( $o, 'link', 'link' );
		}
		if ( in_array( $s->str( 'caption_source' ), array( 'attachment', 'custom' ), true ) ) {
			$o['caption'] = true;
		}
		$s->use( 'image', 'link', 'caption', 'open_lightbox' );
		return array( 'featured-image', $o );
	}

	public static function archive_title( Source $s ): array {
		$o = array();
		$s->opt( $o, 'tag', 'header_size', self::TAGS );
		$o += array( 'tag' => 'h1' );
		$s->opt( $o, 'align', 'align', self::ALIGN, true );
		$s->col( $o, 'color', 'title_color' );
		$s->typ( $o, 'typography', 'typography' );
		$s->tsh( $o, 'text_shadow', 'text_shadow' );
		$s->use( 'title', 'link', 'size', 'blend_mode', 'include_context' );
		return array( 'archive-title', $o );
	}

	public static function post_info( Source $s, Converter $c ): array {
		$o     = array();
		$items = array();
		foreach ( (array) $s->raw( 'icon_list' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = (string) ( $row['type'] ?? 'date' );
			$item = array();
			switch ( $type ) {
				case 'terms':
					$tax          = (string) ( $row['taxonomy'] ?? 'category' );
					$item['type'] = 'category' === $tax ? 'categories' : ( 'post_tag' === $tax ? 'tags' : 'terms' );
					if ( 'terms' === $item['type'] ) {
						$item['taxonomy'] = $tax;
					}
					break;
				case 'custom':
					$item['type'] = 'custom';
					$item['text'] = (string) ( $row['custom_text'] ?? '' );
					$link         = $c->link( $row['custom_url'] ?? null );
					if ( $link ) {
						$item['custom_link'] = $link;
					}
					break;
				case 'author':
				case 'date':
				case 'time':
				case 'comments':
					$item['type'] = $type;
					break;
				default:
					$c->setting( 'post-info', 'item: ' . $type );
					continue 2;
			}
			if ( '' !== (string) ( $row['text_prefix'] ?? '' ) ) {
				$item['before'] = (string) $row['text_prefix'];
			}
			if ( in_array( $item['type'], array( 'author', 'date', 'categories', 'tags', 'terms', 'comments' ), true ) ) {
				$item['link'] = 'yes' === ( $row['link'] ?? 'yes' );
			}
			if ( 'author' === $type && ! empty( $row['show_avatar'] ) ) {
				$item['avatar'] = true;
			}
			if ( 'none' !== ( $row['show_icon'] ?? 'default' ) ) {
				$icon = $c->icon( $row['selected_icon'] ?? ( $row['icon'] ?? null ) );
				if ( $icon ) {
					$item['icon'] = $icon;
				}
			}
			$items[] = self::row( $item, $row );
		}
		$o['items']  = $items;
		$o['layout'] = 'traditional' === $s->str( 'view', 'inline' ) ? 'list' : 'inline';
		$s->sl( $o, 'gap', 'space_between' );
		$s->opt( $o, 'align', 'icon_align', self::ALIGN_SE, true );
		$s->col( $o, 'icon_color', 'icon_color' );
		$s->sl( $o, 'icon_size', 'icon_size' );
		$s->sl( $o, 'icon_gap', 'text_indent', false );
		$s->col( $o, 'text_color', 'text_color' );
		$s->typ( $o, 'typography', 'icon_typography' );
		return array( 'post-info', $o );
	}

	public static function post_navigation( Source $s ): array {
		$o               = array();
		$o['show_label'] = $s->yes( 'show_label', true );
		$s->text( $o, 'prev_label', 'prev_label' );
		$s->text( $o, 'next_label', 'next_label' );
		$o['show_arrow'] = $s->yes( 'show_arrow', true );
		$o['show_title'] = $s->yes( 'show_title', true );
		if ( $s->yes( 'in_same_term' ) ) {
			$o['in_same_term'] = true;
			$tax               = $s->str( 'in_same_term_taxonomy', 'category' );
			if ( '' !== $tax ) {
				$o['taxonomy'] = $tax;
			}
		}
		$s->col( $o, 'label_color', 'label_color' );
		$s->typ( $o, 'label_typography', 'label_typography' );
		$s->col( $o, 'title_color', 'title_color' );
		$s->typ( $o, 'title_typography', 'title_typography' );
		$s->col( $o, 'arrow_color', 'arrow_color' );
		$s->use( 'arrow', 'show_borders', 'in_same_term_taxonomy' );
		return array( 'post-navigation', $o );
	}

	public static function author_box( Source $s ): array {
		$o                = array();
		$o['show_avatar'] = $s->yes( 'show_avatar', true );
		$o['show_name']   = $s->yes( 'show_name', true );
		$s->opt( $o, 'name_tag', 'author_name_tag', self::TAGS );
		$o['show_bio'] = $s->yes( 'show_biography', true );
		if ( $s->yes( 'show_link' ) ) {
			$o['show_archive'] = true;
			$s->text( $o, 'archive_text', 'link_text' );
		}
		$s->opt( $o, 'layout', 'layout', array( 'left' => 'left', 'above' => 'top', 'right' => 'right' ) );
		$s->opt( $o, 'align', 'alignment', self::ALIGN3, true );
		$s->use( 'source', 'link_to', 'author_name', 'author_bio', 'author_avatar', 'author_website', 'link_text' );
		return array( 'author-box', $o );
	}

	public static function post_comments( Source $s ): array {
		$s->use( '_skin', 'source_type', 'source_custom' );
		return array( 'post-comments', array() );
	}

	public static function search_form( Source $s ): array {
		$o           = array();
		$o['layout'] = 'full_screen' === $s->str( 'skin', 'classic' ) ? 'overlay' : 'inline';
		$s->text( $o, 'placeholder', 'placeholder' );
		$o += array( 'placeholder' => 'Search...' );
		$btype             = $s->str( 'button_type', 'icon' );
		$o['button_type'] = 'minimal' === $s->str( 'skin', 'classic' ) ? 'none' : ( 'text' === $btype ? 'text' : 'icon' );
		$s->text( $o, 'button_text', 'button_text' );
		if ( 'arrow' === $s->str( 'icon', 'search' ) ) {
			$o['button_icon'] = array( 'library' => 'lucide', 'value' => 'arrow-right' );
		}
		$s->col( $o, 'input_color', 'input_text_color' );
		$s->col( $o, 'field_bg', 'input_background_color' );
		$s->typ( $o, 'input_typography', 'input_typography' );
		$s->col( $o, 'button_color', 'button_text_color' );
		$s->col( $o, 'button_bg', 'button_background_color' );
		$s->col( $o, 'button_hover_color', 'button_text_color_hover' );
		$s->col( $o, 'button_hover_bg', 'button_background_color_hover' );
		$s->radius( $o, 'field_radius', 'border_radius' );
		$s->use( 'skin', 'button_type', 'icon', 'size', 'toggle_align', 'heading' );
		return array( 'search-form', $o );
	}

	public static function breadcrumbs( Source $s ): array {
		$o = array();
		$s->opt( $o, 'align', 'align', self::ALIGN_SE, true );
		$s->col( $o, 'current_color', 'text_color' );
		$s->col( $o, 'link_color', 'link_color' );
		$s->col( $o, 'link_hover_color', 'link_hover_color' );
		$s->typ( $o, 'typography', 'typography' );
		$s->use( 'html_tag' );
		return array( 'breadcrumbs', $o );
	}

	public static function login( Source $s ): array {
		$o                  = array();
		$o['show_labels']   = $s->yes( 'show_labels', true );
		$o['show_lost']     = $s->yes( 'show_lost_password', true );
		$o['show_register'] = $s->yes( 'show_register', true );
		$o['show_remember'] = $s->yes( 'show_remember_me', true );
		$s->text( $o, 'button_text', 'button_text' );
		$s->text( $o, 'user_label', 'user_label' );
		$s->text( $o, 'pass_label', 'password_label' );
		$s->text( $o, 'user_placeholder', 'user_placeholder' );
		$s->text( $o, 'pass_placeholder', 'password_placeholder' );
		if ( $s->yes( 'redirect_after_login' ) ) {
			$o['redirect'] = 'custom';
			$s->lnk( $o, 'redirect_url', 'redirect_url' );
		}
		$o['logged_in'] = $s->yes( 'show_logged_in_message', true ) ? 'message' : 'nothing';
		$s->use( 'redirect_url', 'custom_labels', 'redirect_after_logout', 'redirect_logout_url' );
		return array( 'login', $o );
	}
}
