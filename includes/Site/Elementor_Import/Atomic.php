<?php
/**
 * Elementor import: Elementor 4 "atomic" elements (e-flexbox, e-div-block, e-heading, e-paragraph …).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site\Elementor_Import;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic elements store typed props ({"$$type": "string", "value": …}) and their design in class styles.
 * The content and the basic layout are converted; the rest of the class styles is reported.
 */
final class Atomic {

	/**
	 * @param array<string,mixed> $el Element.
	 * @return array<int, array<string,mixed>>
	 */
	public static function element( array $el, int $depth, Converter $c ): array {
		$type = (string) ( $el['elType'] ?? '' );
		if ( ! in_array( $type, array( 'e-flexbox', 'e-div-block', 'e-grid' ), true ) ) {
			$c->unmapped( $type );
			return array( $c->placeholder( $el, $type ) );
		}
		$settings = self::unwrap( (array) ( $el['settings'] ?? array() ) );
		$props    = self::styles( $el );
		$o        = array( 'content_width' => 'full' );
		$tag      = (string) ( $settings['tag'] ?? '' );
		if ( in_array( $tag, array( 'div', 'section', 'header', 'footer', 'main', 'article', 'aside', 'nav' ), true ) ) {
			$o['tag'] = $tag;
		}
		if ( 'e-grid' === $type || 'grid' === ( $props['display'] ?? '' ) ) {
			$o['layout'] = 'grid';
		} elseif ( 'e-flexbox' === $type || 'flex' === ( $props['display'] ?? '' ) ) {
			$dir = (string) ( $props['flex-direction'] ?? 'row' );
			if ( in_array( $dir, array( 'row', 'column', 'row-reverse', 'column-reverse' ), true ) ) {
				$o['direction'] = $dir;
			}
			$wrap = (string) ( $props['flex-wrap'] ?? '' );
			if ( in_array( $wrap, array( 'wrap', 'nowrap' ), true ) ) {
				$o['wrap'] = $wrap;
			}
		}
		foreach ( array( 'justify-content' => 'justify', 'align-items' => 'align' ) as $from => $to ) {
			$v = (string) ( $props[ $from ] ?? '' );
			$v = array( 'start' => 'flex-start', 'end' => 'flex-end' )[ $v ] ?? $v;
			if ( in_array( $v, array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly', 'stretch' ), true ) ) {
				$o[ $to ] = $v;
			}
		}
		$gap = Source::to_slider( $props['gap'] ?? null );
		if ( $gap ) {
			$o['gap'] = $gap;
		}
		$pad = self::dims( $props['padding'] ?? null );
		if ( $pad ) {
			$o['_padding'] = $pad;
		}
		$bg = $props['background']['color'] ?? ( $props['background-color'] ?? '' );
		if ( is_string( $bg ) && '' !== \Uncoder\Builder\Core\Utils::sanitize_color( $bg ) ) {
			$o['background'] = array(
				'type'  => 'classic',
				'color' => \Uncoder\Builder\Core\Utils::sanitize_color( $bg ),
			);
		}
		if ( count( $props ) > 6 ) {
			$c->note( 'Elementor 4 (atomic) class styles were only partly converted: layout, gap, padding and background.' );
		}
		$s                = new Source( array(), $type, $c );
		$node             = $c->node( $el, 'container', $o, $s );
		$node['children'] = $c->children( (array) ( $el['elements'] ?? array() ), $depth + 1 );
		return array( $node );
	}

	/**
	 * Widgets: e-heading, e-paragraph, e-button, e-image, e-divider, e-youtube.
	 *
	 * @param array<string,mixed> $el Element.
	 * @return array{0:string,1:array<string,mixed>}|null
	 */
	public static function widget( array $el, Converter $c ): ?array {
		$type = (string) ( $el['widgetType'] ?? '' );
		$s    = self::unwrap( (array) ( $el['settings'] ?? array() ) );
		$link = $c->link( is_array( $s['link'] ?? null ) ? array( 'url' => (string) ( $s['link']['destination'] ?? ( $s['link']['url'] ?? '' ) ), 'is_external' => ! empty( $s['link']['isTargetBlank'] ) ) : null );
		switch ( $type ) {
			case 'e-heading':
				$o = array( 'title' => (string) ( $s['title'] ?? '' ) );
				if ( in_array( $s['tag'] ?? '', array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ) {
					$o['tag'] = $s['tag'];
				}
				if ( $link ) {
					$o['link'] = $link;
				}
				return array( 'heading', $o );
			case 'e-paragraph':
				return array( 'text-editor', array( 'content' => wpautop( (string) ( $s['paragraph'] ?? '' ) ) ) );
			case 'e-button':
				$o = array( 'text' => (string) ( $s['text'] ?? 'Click here' ) );
				if ( $link ) {
					$o['link'] = $link;
				}
				return array( 'button', $o );
			case 'e-image':
				$img = $s['image']['src'] ?? ( $s['image'] ?? null );
				$m   = $c->media( is_array( $img ) ? array( 'id' => $img['id'] ?? 0, 'url' => $img['url'] ?? '' ) : $img );
				return array( 'image', $m ? array( 'image' => $m ) : array() );
			case 'e-divider':
				return array( 'divider', array() );
			case 'e-youtube':
				return array(
					'video',
					array(
						'source'      => 'youtube',
						'youtube_url' => (string) ( $s['source'] ?? '' ),
					),
				);
		}
		return null;
	}

	/**
	 * Strips {"$$type", "value"} wrappers.
	 *
	 * @param mixed $v Value.
	 * @return mixed
	 */
	public static function unwrap( $v ) {
		if ( ! is_array( $v ) ) {
			return $v;
		}
		if ( array_key_exists( '$$type', $v ) && array_key_exists( 'value', $v ) ) {
			return self::unwrap( $v['value'] );
		}
		foreach ( $v as $k => $x ) {
			$v[ $k ] = self::unwrap( $x );
		}
		return $v;
	}

	/**
	 * Desktop props of the element's local class.
	 *
	 * @param array<string,mixed> $el Element.
	 * @return array<string,mixed>
	 */
	private static function styles( array $el ): array {
		$props = array();
		foreach ( (array) ( $el['styles'] ?? array() ) as $style ) {
			foreach ( (array) ( $style['variants'] ?? array() ) as $variant ) {
				$meta = (array) ( $variant['meta'] ?? array() );
				if ( 'desktop' === ( $meta['breakpoint'] ?? 'desktop' ) && empty( $meta['state'] ) ) {
					$props = array_merge( $props, (array) self::unwrap( $variant['props'] ?? array() ) );
				}
			}
		}
		return $props;
	}

	/**
	 * @param mixed $v Atomic dimensions ({block-start, inline-end …} or {top, right …} of sizes).
	 * @return array<string,mixed>|null
	 */
	private static function dims( $v ): ?array {
		if ( ! is_array( $v ) ) {
			$one = Source::to_slider( $v );
			return $one && 'custom' !== $one['unit'] ? array( 'top' => $one['size'], 'right' => $one['size'], 'bottom' => $one['size'], 'left' => $one['size'], 'unit' => $one['unit'], 'linked' => true ) : null;
		}
		$map  = array( 'top' => array( 'block-start', 'top' ), 'right' => array( 'inline-end', 'right' ), 'bottom' => array( 'block-end', 'bottom' ), 'left' => array( 'inline-start', 'left' ) );
		$out  = array();
		$unit = 'px';
		foreach ( $map as $side => $keys ) {
			$sl = Source::to_slider( $v[ $keys[0] ] ?? ( $v[ $keys[1] ] ?? null ) );
			if ( $sl && 'custom' !== $sl['unit'] ) {
				$out[ $side ] = $sl['size'];
				$unit         = $sl['unit'];
			} else {
				$out[ $side ] = '';
			}
		}
		if ( '' === implode( '', $out ) ) {
			return null;
		}
		return $out + array(
			'unit'   => $unit,
			'linked' => false,
		);
	}
}
