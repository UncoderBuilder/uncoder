<?php
/**
 * Renders an element tree to HTML.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Controls\Groups\Background;
use Uncoder\Builder\Elements\Container;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side renderer used by the front end, the editor's render endpoint and the MCP previewer.
 */
final class Renderer {

	private Render_Context $ctx;

	/** @var array<string,bool> */
	private array $used = array();

	/** Guards against templates embedding themselves. */
	private static array $stack = array();

	/** @var array<int,string> Documents rendered on this front-end request, in order: id => document type. */
	private static array $rendered = array();

	/**
	 * Documents rendered on this front-end request so far (page, header, footer, templates, popups, loop items…).
	 *
	 * @return array<int,string> id => document type.
	 */
	public static function rendered(): array {
		return self::$rendered;
	}

	/**
	 * Whether an Uncoder document is being rendered right now (its widgets are running).
	 */
	public static function rendering(): bool {
		return ! empty( self::$stack );
	}

	public function __construct( int $doc_id, bool $editor = false, int $post_id = 0 ) {
		$this->ctx           = new Render_Context();
		$this->ctx->doc_id   = $doc_id;
		$this->ctx->editor   = $editor;
		$this->ctx->post_id  = $post_id ? $post_id : (int) get_the_ID();
		$this->ctx->renderer = $this;
		$template            = $doc_id > 0 ? (string) get_post_meta( $doc_id, Utils::META_TYPE, true ) : '';
		$this->ctx->repeat   = 'loop-item' === $template;
		$this->ctx->doc_type = '' !== $template ? $template : ( $doc_id > 0 ? (string) get_post_type( $doc_id ) : '' );
	}

	/**
	 * What makes an element addressable: the type class uncoder-{type}, then its own class uncoder-{id}
	 * (always second, see elementId() in src/frontend/runtime.ts; selector: Utils::element_selector()).
	 * An id attribute is printed only for an anchor id, and not in loop items (rendered many times).
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array{0: string[], 1: array<string,string>}
	 */
	public static function identity( string $type, string $id, array $s, bool $repeat ): array {
		$attrs  = array();
		$anchor = Utils::element_anchor( $s );
		if ( '' !== $anchor && ! $repeat ) {
			$attrs['id'] = $anchor;
		}
		return array( array( 'uncoder-' . $type, 'uncoder-' . $id ), $attrs );
	}

	/**
	 * A widget as one element: its attributes merged into the first tag of its markup (the widget's own
	 * block), like Bricks. Widgets that opt out or render nothing, or an anchor id on a root that already
	 * has an id, get a thin div.uncoder-{type} around their markup instead.
	 *
	 * @param array<string,mixed> $attrs Element attributes (class, anchor id, modules, custom attributes…).
	 */
	public static function merge( Widget_Base $widget, string $html, array $attrs ): string {
		$trimmed = ltrim( $html );
		if ( $widget->merge_root() && '' !== $trimmed && '<' === $trimmed[0] ) {
			$p = new \WP_HTML_Tag_Processor( $trimmed );
			if ( $p->next_tag() && ( ! isset( $attrs['id'] ) || null === $p->get_attribute( 'id' ) ) ) {
				foreach ( $attrs as $name => $value ) {
					if ( null === $value || false === $value ) {
						continue;
					}
					$current = $p->get_attribute( $name );
					if ( 'class' === $name ) {
						// Type class and the element's own class first, then the block's own classes, then the rest.
						$ours = preg_split( '/\s+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
						$lead = array_slice( $ours, 0, 2 );
						$own  = preg_split( '/\s+/', (string) $current, -1, PREG_SPLIT_NO_EMPTY );
						$p->set_attribute( 'class', implode( ' ', array_unique( array_merge( $lead, $own, array_slice( $ours, count( $lead ) ) ) ) ) );
					} elseif ( 'style' === $name && is_string( $current ) && '' !== trim( $current ) ) {
						$p->set_attribute( 'style', rtrim( trim( $current ), ';' ) . ';' . $value );
					} elseif ( 'data-uncoder-js' === $name && is_string( $current ) ) {
						$p->set_attribute( $name, implode( ' ', array_unique( array_filter( array_merge( explode( ' ', $current ), explode( ' ', (string) $value ) ) ) ) ) );
					} elseif ( null === $current || 'id' === $name ) {
						$p->set_attribute( $name, true === $value ? true : (string) $value );
					}
				}
				// The element's id, class and data-id lead the tag.
				return self::lead( $p->get_updated_html() );
			}
		}
		return '<div' . Utils::attrs( $attrs ) . '>' . $html . '</div>';
	}

	/** Puts id, class and data-id first in the opening tag (readable markup; attribute order has no effect). */
	private static function lead( string $html ): string {
		if ( ! preg_match( '/^<([a-z][a-z0-9-]*)(\s[^>]*)?>/i', $html, $m ) || empty( $m[2] ) ) {
			return $html;
		}
		$rest  = $m[2];
		$first = '';
		foreach ( array( 'id', 'class', 'data-id' ) as $name ) {
			if ( preg_match( '/\s' . $name . '(="[^"]*"|=\'[^\']*\')/', $rest, $a ) ) {
				$first .= ' ' . $name . $a[1];
				$rest   = str_replace( $a[0], '', $rest );
			}
		}
		return '<' . $m[1] . $first . $rest . '>' . substr( $html, strlen( $m[0] ) );
	}

	public function context(): Render_Context {
		return $this->ctx;
	}

	/**
	 * Renders a whole document wrapped in its scope element.
	 *
	 * @param array<int, array<string,mixed>> $elements Tree.
	 * @param array<string,mixed>             $args     class, tag, attrs.
	 */
	public function render_document( array $elements, array $args = array() ): string {
		$doc = $this->ctx->doc_id;
		if ( isset( self::$stack[ $doc ] ) ) {
			return '';
		}
		self::$stack[ $doc ] = true;
		// What this page is made of, for the admin bar's Edit with Uncoder menu (front-end page views only).
		if ( $doc > 0 && ! isset( self::$rendered[ $doc ] ) && ! $this->ctx->editor && ! Document::$static_render && ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			self::$rendered[ $doc ] = $this->ctx->doc_type;
		}
		$html                = '';
		$first               = true;
		$eager               = $this->ctx->eager;
		foreach ( $elements as $element ) {
			if ( is_array( $element ) ) {
				// The first section is above the fold: its background images are never lazy.
				$this->ctx->eager = $eager || ( $first && ! $this->ctx->repeat );
				$html            .= $this->render_element( $element, 0 );
				$first            = $first && ! empty( $element['disabled'] );
			}
		}
		$this->ctx->eager = $eager;
		unset( self::$stack[ $doc ] );

		$tag   = $args['tag'] ?? 'div';
		$attrs = array_merge(
			array(
				'class'        => trim( 'uncoder uncoder-' . $doc . ' ' . ( $args['class'] ?? '' ) ),
				'data-uncoder-doc' => (string) $doc,
			),
			$args['attrs'] ?? array()
		);
		return '<' . $tag . Utils::attrs( $attrs ) . '>' . $html . '</' . $tag . '>';
	}

	/**
	 * @param array<string,mixed> $element Node.
	 */
	public function render_element( array $element, int $depth = 0 ): string {
		$type = Plugin::instance()->elements()->get( (string) ( $element['type'] ?? '' ) );
		$id   = (string) ( $element['id'] ?? '' );
		if ( null === $type || ! Utils::is_valid_id( $id ) || ! empty( $element['disabled'] ) ) {
			return '';
		}
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();

		/**
		 * Lets modules (display conditions, A/B tests…) skip an element on the front end.
		 *
		 * @param bool                $render  Whether to render.
		 * @param array<string,mixed> $element Node.
		 */
		if ( ! $this->ctx->editor && ! apply_filters( 'uncoder_wb/render/should_render', true, $element ) ) {
			return '';
		}
		// Conditions (Behaviour → Conditions). The stored HTML copy leaves conditional elements out, so
		// members-only content never ends up in post_content.
		if ( ! $this->ctx->editor && ! empty( $settings['_conditions'] ) && ( Document::$static_render || ! Element_Conditions::passes( $settings['_conditions'], $this->ctx->post_id, $this->ctx ) ) ) {
			return '';
		}

		$this->used[ $type->name() ] = true;

		// A container with a query loop prints one copy per item (not in the editor canvas: it shows one).
		if ( $type->is_container() && ! $this->ctx->editor && ! empty( $settings['_loop'] ) ) {
			return $this->render_loop( $type, $element, $type->effective_settings( $settings ), $depth );
		}

		$settings = $this->resolve_dynamic( $element, $type->effective_settings( $settings ) );

		if ( $type->is_container() ) {
			return $this->render_container( $element, $settings, $depth );
		}
		if ( $type instanceof Widget_Base ) {
			$parts = $this->widget_parts( $type, $element, $settings, $depth );
			return self::merge( $type, $parts['html'], $parts['attrs'] );
		}
		return '';
	}

	/**
	 * A widget's markup and element attributes; "outer" is the finished element (Renderer::merge()).
	 *
	 * @param array<string,mixed> $element  Node.
	 * @param array<string,mixed> $settings Effective settings.
	 * @return array{html:string, attrs:array<string,mixed>}
	 */
	public function widget_parts( Widget_Base $widget, array $element, array $settings, int $depth = 0 ): array {
		$id                   = (string) $element['id'];
		$prev                 = array( $this->ctx->element, $this->ctx->element_id, $this->ctx->depth );
		$this->ctx->element   = $element;
		$this->ctx->element_id = $id;
		$this->ctx->depth     = $depth;

		$html = $widget->render_html( $settings, $this->ctx );

		$common                  = Common_Controls::wrapper( $settings );
		list( $classes, $ident ) = self::identity( $widget->name(), $id, $settings, $this->ctx->repeat );
		$classes                 = array_merge( $classes, $common['classes'] );
		if ( $this->lazy_bg( $settings['_background'] ?? null ) ) {
			$classes[] = 'uncoder-lazy-bg';
		}
		$extra                   = $widget->wrapper_attributes( $settings, $this->ctx );
		if ( isset( $extra['class'] ) ) {
			$classes = array_merge( $classes, (array) $extra['class'] );
			unset( $extra['class'] );
		}
		// data-id is for the editor canvas only; the site addresses elements by their class.
		$attrs = array_merge(
			$ident,
			array(
				'class'   => implode( ' ', array_unique( $classes ) ),
				'data-id' => $this->ctx->editor ? $id : null,
			),
			$common['attrs'],
			$extra
		);
		// Merge module names (e.g. "sticky" from common settings + the widget's own modules).
		$modules = array_merge( $widget->frontend_scripts(), preg_split( '/\s+/', (string) ( $attrs['data-uncoder-js'] ?? '' ) ) );
		$modules = array_values( array_unique( array_filter( $modules ) ) );
		if ( $modules ) {
			$attrs['data-uncoder-js'] = implode( ' ', $modules );
		}

		list( $this->ctx->element, $this->ctx->element_id, $this->ctx->depth ) = $prev;
		return array(
			'html'  => $html,
			'attrs' => $attrs,
			'outer' => self::merge( $widget, $html, $attrs ),
		);
	}

	/**
	 * A looping container: one copy per post or term, each with that item as the context of its dynamic
	 * data (and of Theme_Context). Copies never repeat an anchor id. See Core\Container_Loop.
	 *
	 * @param array<string,mixed> $element Node.
	 * @param array<string,mixed> $s       Effective settings.
	 */
	private function render_loop( Element_Base $type, array $element, array $s, int $depth ): string {
		$items = Container_Loop::items( $s, $this->ctx );
		if ( ! $items ) {
			$text = trim( (string) ( $s['_loop_empty'] ?? '' ) );
			return '' === $text ? '' : '<p class="uncoder-loop-empty">' . wp_kses( $text, Utils::kses_inline() ) . '</p>';
		}
		$prev              = array( $this->ctx->post_id, $this->ctx->term, $this->ctx->repeat );
		$this->ctx->repeat = true;
		$html              = '';
		foreach ( $items as $item ) {
			$this->ctx->term = $item['term'] ?? null;
			if ( isset( $item['post'] ) && $item['post'] instanceof \WP_Post ) {
				$post               = $item['post'];
				$this->ctx->post_id = $post->ID;
				$html              .= \Uncoder\Builder\Widgets\Support\Theme_Context::with_post( $post, fn() => $this->render_container( $element, $this->resolve_dynamic( $element, $s ), $depth ) );
			} else {
				$html .= $this->render_container( $element, $this->resolve_dynamic( $element, $s ), $depth );
			}
		}
		list( $this->ctx->post_id, $this->ctx->term, $this->ctx->repeat ) = $prev;
		return $html;
	}

	/**
	 * @param array<string,mixed> $element  Node.
	 * @param array<string,mixed> $s        Effective settings.
	 */
	private function render_container( array $element, array $s, int $depth ): string {
		$id       = (string) $element['id'];
		$raw      = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$boxed    = Container::is_boxed( $raw, $depth );
		$children = '';
		foreach ( (array) ( $element['children'] ?? array() ) as $child ) {
			if ( is_array( $child ) ) {
				$children .= $this->render_element( $child, $depth + 1 );
			}
		}
		if ( ! in_array( $s['tag'] ?? '', Utils::CONTAINER_TAGS, true ) ) {
			$s['tag'] = Container::auto_tag( $depth, $this->ctx->doc_type );
		}
		$parts = self::container_parts( $id, $s, $boxed, $this->ctx->editor, $this->ctx->repeat );
		if ( $this->lazy_bg( $s['background'] ?? null ) || $this->lazy_bg( $s['_background'] ?? null ) ) {
			$parts['attrs']['class'] .= ' uncoder-lazy-bg';
		}
		$tag   = $parts['tag'];
		$inner = $boxed ? '<div class="uncoder-container__inner">' . $children . '</div>' : $children;
		return '<' . $tag . Utils::attrs( $parts['attrs'] ) . '>' . $parts['before'] . $inner . '</' . $tag . '>';
	}

	/**
	 * Wrapper tag/attributes/background video for a container. Shared with the editor render endpoint.
	 *
	 * @param array<string,mixed> $s Effective settings.
	 * @return array{tag:string, attrs:array<string,mixed>, before:string}
	 */
	/**
	 * Rows that distribute free space (justify center/end/space-*) let child containers size to their
	 * content; otherwise children fill the row (width 100%) and the justification has no effect.
	 * Twin of fitsChildren() in the editor's ElementView.tsx.
	 *
	 * @param array<string,mixed> $s Container settings.
	 */
	public static function fits_children( array $s ): bool {
		// A wrapping row also fits: with 100%-wide children every child would sit on a line of its own.
		return 'grid' !== ( $s['layout'] ?? 'flex' )
			&& in_array( $s['direction'] ?? 'column', array( 'row', 'row-reverse' ), true )
			&& ( in_array( $s['justify'] ?? '', array( 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ), true ) || 'wrap' === ( $s['wrap'] ?? '' ) );
	}

	public static function container_parts( string $id, array $s, bool $boxed, bool $editor = false, bool $repeat = false ): array {
		$tag                     = Utils::tag( $s['tag'] ?? 'div', Utils::CONTAINER_TAGS, 'div' );
		$common                  = Common_Controls::wrapper( $s );
		list( $classes, $ident ) = self::identity( 'container', $id, $s, $repeat );
		// Only what differs from a plain container (flex, full width) gets a modifier class.
		if ( $boxed ) {
			$classes[] = 'uncoder-container--boxed';
		}
		if ( 'grid' === ( $s['layout'] ?? 'flex' ) ) {
			$classes[] = 'uncoder-container--grid';
		}
		if ( ! empty( $s['overlay']['type'] ) ) {
			$classes[] = 'uncoder-container--overlay';
		}
		if ( self::fits_children( $s ) ) {
			$classes[] = 'uncoder-container--fit';
		}
		$attrs = array();
		if ( 'a' === $tag ) {
			$link = $s['link'] ?? array();
			if ( is_array( $link ) && ! empty( $link['url'] ) ) {
				$attrs['href'] = $link['url'];
				if ( ! empty( $link['external'] ) ) {
					$attrs['target'] = '_blank';
					$attrs['rel']    = 'noopener';
				}
			}
			if ( $editor ) {
				$attrs['href'] = null;
			}
		}
		$before = '';
		$bg     = $s['background'] ?? array();
		if ( is_array( $bg ) && 'video' === ( $bg['type'] ?? '' ) && ! empty( $bg['video_url'] ) ) {
			$classes[] = 'uncoder-container--video';
			$before    = self::background_video( (string) $bg['video_url'], $bg['video_fallback']['url'] ?? '' );
		} elseif ( is_array( $bg ) ) {
			$layer = self::background_layer( $bg, $s );
			if ( '' !== $layer ) {
				$classes[] = 'uncoder-container--bg';
				$before    = $layer;
			}
		}
		$animated = Animated_Backgrounds::layer( $s );
		if ( '' !== $animated ) {
			$classes[] = 'uncoder-container--bg';
			$before   .= $animated;
		}
		$shapes = self::shape_dividers( $s );
		if ( '' !== $shapes ) {
			$classes[] = 'uncoder-container--shape';
			$before   .= $shapes;
		}
		$attrs = array_merge(
			$ident,
			array(
				'class'   => implode( ' ', array_merge( $classes, $common['classes'] ) ),
				'data-id' => $editor ? $id : null,
			),
			$attrs,
			$common['attrs']
		);
		return array(
			'tag'    => $tag,
			'attrs'  => $attrs,
			'before' => $before,
		);
	}

	/**
	 * Background layer for slideshows and moving (parallax / zoom / pointer) images. A classic image
	 * layer inherits the container's own responsive background, so only motion is added here.
	 * Twin of BgLayer in the editor's ElementView.tsx.
	 *
	 * @param array<string,mixed> $bg Background group value.
	 * @param array<string,mixed> $s  Container settings.
	 */
	/**
	 * Whether an element's background image loads only when it comes near the screen (Settings → General →
	 * Performance). Not in the editor, not above the fold.
	 *
	 * @param mixed $bg Background group value.
	 */
	private function lazy_bg( $bg ): bool {
		if ( $this->ctx->editor || $this->ctx->eager || ! is_array( $bg ) || ! in_array( $bg['type'] ?? 'classic', array( '', 'classic' ), true ) || ! \Uncoder\Builder\Site\Performance::get()['lazy_bg'] ) {
			return false;
		}
		return self::has_background_image( $bg );
	}

	private static function background_layer( array $bg, array $s ): string {
		$type   = (string) ( $bg['type'] ?? '' );
		$motion = (string) ( $s['bg_motion'] ?? '' );
		$motion = in_array( $motion, array( 'parallax', 'zoom-in', 'zoom-out', 'mouse' ), true ) ? $motion : '';
		$speed  = max( 1, min( 10, (float) ( $s['bg_motion_speed'] ?? 4 ) ) );
		$slides = '';
		$vars   = array();
		$class  = array( 'uncoder-container__bg' );
		$js     = array();
		$data   = array();

		if ( 'slideshow' === $type ) {
			$first = true;
			foreach ( array_slice( (array) ( $bg['slides'] ?? array() ), 0, 50 ) as $image ) {
				// Only the first slide loads with the page; the runtime fills in the next one ahead of time.
				$url = Utils::css_url( is_array( $image ) ? ( $image['url'] ?? '' ) : '' );
				if ( '' === $url ) {
					continue;
				}
				$slides .= $first
					? '<div class="uncoder-bg-slide is-active" style="background-image:url(&quot;' . esc_attr( $url ) . '&quot;)"></div>'
					: '<div class="uncoder-bg-slide" data-bg="' . esc_attr( $url ) . '"></div>';
				$first   = false;
			}
			if ( '' === $slides ) {
				return '';
			}
			$class[] = 'uncoder-container__bg--slides';
			$class[] = 'slide' === ( $bg['slide_transition'] ?? '' ) ? 'uncoder-bg-slides--slide' : 'uncoder-bg-slides--fade';
			if ( in_array( $bg['ken_burns'] ?? '', array( 'in', 'out' ), true ) ) {
				$class[] = 'uncoder-bg-slides--kb-' . $bg['ken_burns'];
			}
			$duration = max( 1000, min( 30000, (int) ( $bg['slide_duration'] ?? 5000 ) ?: 5000 ) );
			$speed_ms = max( 100, min( 5000, (int) ( $bg['slide_speed'] ?? 1000 ) ?: 1000 ) );
			$size     = in_array( $bg['slide_size'] ?? '', array( 'contain', 'auto' ), true ) ? $bg['slide_size'] : 'cover';
			$position = isset( Background::POSITIONS[ $bg['slide_position'] ?? '' ] ) && '' !== ( $bg['slide_position'] ?? '' ) ? $bg['slide_position'] : 'center center';
			$vars[]   = '--uncoder-ss-duration:' . $duration . 'ms';
			$vars[]   = '--uncoder-ss-speed:' . $speed_ms . 'ms';
			$vars[]   = '--uncoder-ss-size:' . $size;
			$vars[]   = '--uncoder-ss-pos:' . $position;
			$js[]     = 'bg-slideshow';
			$data     = array(
				'duration'   => $duration,
				'transition' => $speed_ms,
			);
		} elseif ( 'classic' !== $type || '' === $motion || ! self::has_background_image( $bg ) ) {
			return '';
		} else {
			$class[] = 'uncoder-container__bg--image';
		}

		if ( '' !== $motion ) {
			$class[]        = 'uncoder-container__bg--' . $motion;
			$js[]           = 'bg-motion';
			$data['motion'] = $motion;
			$data['speed']  = $speed;
			if ( 'parallax' === $motion ) {
				$vars[] = '--uncoder-bgm-extra:' . ( $speed * 3 ) . '%';
			} elseif ( 'mouse' === $motion ) {
				$vars[] = '--uncoder-bgm-extra:' . ( $speed * 5 + 4 ) . 'px';
			}
		}

		$attrs = array(
			'class'       => implode( ' ', $class ),
			'aria-hidden' => 'true',
		);
		if ( $vars ) {
			$attrs['style'] = implode( ';', $vars );
		}
		if ( $js ) {
			$attrs['data-uncoder-js']   = implode( ' ', $js );
			$attrs['data-settings'] = wp_json_encode( $data );
		}
		return '<div' . Utils::attrs( $attrs ) . '><div class="uncoder-container__bg-layer">' . $slides . '</div></div>';
	}

	/**
	 * @param array<string,mixed> $bg Background group value.
	 */
	private static function has_background_image( array $bg ): bool {
		foreach ( $bg as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'image' ) && is_array( $value ) && ! empty( $value['url'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Top / bottom SVG shape dividers. Twin of ShapeDividers in the editor's ElementView.tsx.
	 *
	 * @param array<string,mixed> $s Container settings.
	 */
	public static function shape_dividers( array $s ): string {
		$out = '';
		foreach ( array( 'top', 'bottom' ) as $side ) {
			$name = (string) ( $s[ 'shape_' . $side ] ?? '' );
			if ( '' === $name || ! Shapes::exists( $name ) ) {
				continue;
			}
			$class = 'uncoder-shape uncoder-shape--' . $side;
			if ( ! empty( $s[ 'shape_' . $side . '_flip' ] ) ) {
				$class .= ' uncoder-shape--flip';
			}
			if ( ! empty( $s[ 'shape_' . $side . '_front' ] ) ) {
				$class .= ' uncoder-shape--front';
			}
			$out .= '<div class="' . $class . '" aria-hidden="true">' . Shapes::svg( $name, ! empty( $s[ 'shape_' . $side . '_invert' ] ) ) . '</div>';
		}
		return $out;
	}

	private static function background_video( string $url, string $poster ): string {
		$url = esc_url( $url );
		if ( '' === $url ) {
			return '';
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $host && preg_match( '/(youtube\.com|youtu\.be|vimeo\.com)$/', (string) $host ) ) {
			return '<div class="uncoder-bg-video" data-uncoder-js="bg-video" data-src="' . esc_attr( $url ) . '" aria-hidden="true"></div>';
		}
		return '<div class="uncoder-bg-video" aria-hidden="true"><video class="uncoder-bg-video__el" autoplay muted loop playsinline preload="metadata"' . ( $poster ? ' poster="' . esc_url( $poster ) . '"' : '' ) . ' src="' . $url . '"></video></div>';
	}

	/**
	 * Applies dynamic tags: element.dynamic = { key: { tag, options, before, after, fallback } }.
	 *
	 * @param array<string,mixed> $element  Node.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	private function resolve_dynamic( array $element, array $settings ): array {
		if ( empty( $element['dynamic'] ) || ! is_array( $element['dynamic'] ) ) {
			return $settings;
		}
		$tags = Plugin::instance()->tags();
		foreach ( $element['dynamic'] as $key => $def ) {
			if ( ! is_array( $def ) || empty( $def['tag'] ) ) {
				continue;
			}
			$value = $tags->resolve( $def, $settings[ $key ] ?? null, $this->ctx );
			if ( null !== $value ) {
				$settings[ $key ] = $value;
			}
		}
		return $settings;
	}

	/**
	 * Element types rendered so far.
	 *
	 * @return string[]
	 */
	public function used_types(): array {
		return array_keys( $this->used );
	}
}
