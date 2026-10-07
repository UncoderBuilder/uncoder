<?php
/**
 * Base class for widgets.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A widget prints its markup as ONE root element (its block, e.g. `<div class="uncoder-{name}">`); the
 * renderer turns that root into the element itself: `id="uncoder-{id}"` (or the custom CSS ID), the
 * class `uncoder-{name}` first, `data-id`, and the element's common classes and attributes (Renderer::merge()).
 * Widgets whose markup cannot be one element return false from merge_root().
 *
 * Rules for render():
 * - One root element; style it through `{{WRAPPER}}` (the root itself) and `{{WRAPPER}} .uncoder-{name}__part`.
 * - Settings arrive with defaults applied and dynamic tags resolved.
 * - Escape everything (esc_html, esc_attr, esc_url, wp_kses with Utils::kses_inline()/kses_rich()).
 * - Use BEM classes prefixed with the widget: .uncoder-{name}__part.
 * - Print $ctx->inline('key') on the element holding an inline-editable text.
 */
abstract class Widget_Base extends Element_Base {

	/**
	 * @param array<string,mixed> $s Effective settings.
	 */
	abstract protected function render( array $s, Render_Context $ctx ): void;

	/**
	 * @param array<string,mixed> $s Effective settings.
	 */
	final public function render_html( array $s, Render_Context $ctx ): string {
		ob_start();
		$this->render( $s, $ctx );
		return (string) ob_get_clean();
	}

	/**
	 * Extra wrapper attributes (e.g. data-uncoder-js + settings for front-end modules).
	 *
	 * @param array<string,mixed> $s Effective settings.
	 * @return array<string,mixed>
	 */
	/**
	 * Whether the element's attributes go on the first tag of the widget's markup (one element, like
	 * Bricks). Widgets whose markup is not a single element (raw HTML, shortcodes, embedded documents)
	 * return false and get a thin div.uncoder-{type} around it.
	 */
	public function merge_root(): bool {
		return true;
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array();
	}

	/* ------------------------------------------------------------------ Helpers */

	/**
	 * Link attributes from a url control value.
	 *
	 * @param mixed $link Link value.
	 * @return array<string,mixed>
	 */
	protected function link_attrs( $link ): array {
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			return array();
		}
		$attrs = array( 'href' => $link['url'] );
		$rel   = array();
		if ( ! empty( $link['external'] ) ) {
			$attrs['target'] = '_blank';
			$rel[]           = 'noopener';
		}
		if ( ! empty( $link['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}
		if ( $rel ) {
			$attrs['rel'] = implode( ' ', $rel );
		}
		if ( ! empty( $link['attributes'] ) ) {
			foreach ( Utils::parse_custom_attributes( (string) $link['attributes'] ) as $k => $v ) {
				$attrs[ $k ] = $v;
			}
		}
		return $attrs;
	}

	/**
	 * @param mixed               $icon  Icon value.
	 * @param array<string,mixed> $attrs Attributes.
	 */
	protected function render_icon( $icon, array $attrs = array() ): string {
		if ( ! is_array( $icon ) && ! is_string( $icon ) ) {
			return '';
		}
		if ( is_array( $icon ) && 'none' === ( $icon['library'] ?? '' ) ) {
			return '';
		}
		return Icons::render( $icon, $attrs );
	}

	protected function has_icon( $icon ): bool {
		if ( is_string( $icon ) ) {
			return '' !== $icon;
		}
		if ( ! is_array( $icon ) ) {
			return false;
		}
		$library = (string) ( $icon['library'] ?? '' );
		if ( 'svg' === $library ) {
			return ! empty( $icon['url'] );
		}
		return ! empty( $icon['value'] ) && ( 'lucide' === $library || Icons::is_library( $library ) );
	}

	/**
	 * Responsive <img> for a media value.
	 *
	 * @param mixed               $media Media value.
	 * @param array<string,mixed> $attrs Attributes.
	 */
	protected function image( $media, string $size = 'full', array $attrs = array() ): string {
		if ( ! is_array( $media ) ) {
			return '';
		}
		$alt = isset( $media['alt'] ) ? (string) $media['alt'] : null;
		if ( ! empty( $media['id'] ) && wp_attachment_is_image( (int) $media['id'] ) ) {
			// An explicit alt from the widget wins over the one stored with the media value.
			if ( null !== $alt && '' !== $alt && ! isset( $attrs['alt'] ) ) {
				$attrs['alt'] = $alt;
			}
			$attrs = array_merge( array( 'loading' => 'lazy', 'decoding' => 'async' ), $attrs );
			$html  = wp_get_attachment_image( (int) $media['id'], $size, false, $attrs );
			if ( '' !== $html ) {
				return $html;
			}
		}
		if ( empty( $media['url'] ) ) {
			return '';
		}
		$base = array(
			'src'      => $media['url'],
			'alt'      => $alt ?? '',
			'loading'  => 'lazy',
			'decoding' => 'async',
		);
		return '<img' . Utils::attrs( array_merge( $base, $attrs ) ) . '>';
	}

	/**
	 * Placeholder image URL bundled with the plugin.
	 */
	protected function placeholder_image(): string {
		return UNCODER_WB_URL . 'assets/img/placeholder.svg';
	}

	/**
	 * Inline text allowing only inline formatting tags.
	 */
	protected function inline_html( $text ): string {
		return wp_kses( (string) $text, Utils::kses_inline() );
	}

	/**
	 * JSON for data-settings attributes consumed by front-end modules.
	 *
	 * @param array<string,mixed> $data Data.
	 */
	protected function json_attr( array $data ): string {
		return (string) wp_json_encode( $data );
	}
}
