<?php
/**
 * Icon control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical value: { "library": "lucide", "value": "arrow-right" }, another library such as
 * { "library": "fa-solid", "value": "house" } (see Icons::libraries()), { "library": "svg", "id": 12, "url": "…" }
 * or { "library": "none" }.
 */
class Icon extends Control_Type {

	public function name(): string {
		return 'icon';
	}

	public function sanitize( $value, array $control ) {
		if ( '' === $value || null === $value ) {
			return array( 'library' => 'none', 'value' => '' );
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$library = (string) ( $value['library'] ?? 'lucide' );
		if ( 'svg' === $library ) {
			$id = absint( $value['id'] ?? 0 );
			if ( ! $id ) {
				return null;
			}
			$url = wp_get_attachment_url( $id );
			return array( 'library' => 'svg', 'id' => $id, 'url' => $url ? $url : '' );
		}
		if ( 'none' === $library ) {
			return array( 'library' => 'none', 'value' => '' );
		}
		$name = strtolower( (string) ( $value['value'] ?? '' ) );
		if ( ! preg_match( '/^[a-z0-9\-]{1,64}$/', $name ) ) {
			return null;
		}
		if ( 'lucide' !== $library && ! \Uncoder\Builder\Core\Icons::is_library( $library ) ) {
			$library = 'lucide';
		}
		return array( 'library' => $library, 'value' => $name );
	}

	public function normalize( $value, array $control ) {
		if ( is_string( $value ) ) {
			$v = strtolower( trim( $value ) );
			if ( '' === $v || 'none' === $v ) {
				return array( 'library' => 'none', 'value' => '' );
			}
			// "fa-solid:house", "fas fa-house", "bi-house", "ph-house" … pick their library; the rest is Lucide.
			$parsed = \Uncoder\Builder\Core\Icons::parse( $v );
			if ( $parsed ) {
				return $this->sanitize( array( 'library' => $parsed[0], 'value' => $parsed[1] ), $control );
			}
			$v = preg_replace( '/^(lucide[:\-\/]|fa-)/', '', $v );
			return $this->sanitize( array( 'library' => 'lucide', 'value' => $v ), $control );
		}
		return $this->sanitize( $value, $control );
	}

	public function validate( $value, array $control ): array {
		$clean = $this->normalize( $value, $control );
		if ( null === $clean ) {
			return array( 'Icon must be an icon name like "arrow-right" (Lucide), "fa-solid:house", or {"library":"phosphor","value":"house"}.' );
		}
		if ( ! in_array( $clean['library'], array( 'svg', 'none' ), true ) && ! \Uncoder\Builder\Core\Icons::exists( $clean['value'], $clean['library'] ) ) {
			return array( sprintf( 'Unknown %s icon "%s". Use search_icons (library "all" searches every library) or a common name like "check", "arrow-right", "star".', $clean['library'], $clean['value'] ) );
		}
		return array();
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}

	public function empty_value() {
		return array( 'library' => 'none', 'value' => '' );
	}

	public function value_hint( array $control ): string {
		return 'Icon name: Lucide "arrow-right", or "library:name" / {"library":"…","value":"…"} with library fa-solid, fa-regular, fa-brands, phosphor, bootstrap, feather, heroicons-outline, heroicons-solid, themify';
	}
}
