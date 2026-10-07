<?php
/**
 * Color control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Any CSS color; global colors are referenced as var(--uncoder-c-{id}).
 */
class Color extends Control_Type {

	public function name(): string {
		return 'color';
	}

	public function sanitize( $value, array $control ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		$clean = Utils::sanitize_color( $value );
		return '' === $clean ? null : $clean;
	}

	public function normalize( $value, array $control ) {
		if ( is_string( $value ) ) {
			$v = trim( $value );
			// "global:primary" / "@primary" / "primary" (when it is a kit id) → var(--uncoder-c-primary).
			if ( preg_match( '/^(?:global:|@)([a-z0-9_\-]+)$/i', $v, $m ) ) {
				return 'var(--uncoder-c-' . strtolower( $m[1] ) . ')';
			}
			if ( preg_match( '/^[a-z0-9_\-]+$/', $v ) && in_array( $v, \Uncoder\Builder\Plugin::instance()->kit()->color_ids(), true ) ) {
				return 'var(--uncoder-c-' . $v . ')';
			}
			if ( preg_match( '/^[a-f0-9]{6}$/i', $v ) ) {
				$v = '#' . $v;
			}
			$value = $v;
		}
		return $this->sanitize( $value, $control );
	}

	public function value_hint( array $control ): string {
		return 'CSS color (#hex, rgba(), hsl()) or global "var(--uncoder-c-primary)"';
	}
}
