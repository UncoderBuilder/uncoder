<?php
/**
 * Component overrides control (Template widget).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;

defined( 'ABSPATH' ) || exit;

/**
 * { prop key: value } for the component properties of the embedded section. Only the shape is
 * checked here; every value is sanitized again by the target element's own control when the
 * section renders (Site\Components::apply()), because only then is the target known.
 */
class Overrides extends Control_Type {

	private const MAX_KEYS   = 60;
	private const MAX_STRING = 50000;

	public function name(): string {
		return 'overrides';
	}

	public function sanitize( $value, array $control ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( array_slice( $value, 0, self::MAX_KEYS, true ) as $key => $v ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			$clean = $this->shape( $v, 0 );
			if ( null !== $clean ) {
				$out[ $key ] = $clean;
			}
		}
		return $out;
	}

	/**
	 * Scalars and small nested arrays (media, links, icons) only.
	 *
	 * @param mixed $v Value.
	 * @return mixed
	 */
	private function shape( $v, int $depth ) {
		if ( is_bool( $v ) || is_int( $v ) ) {
			return $v;
		}
		if ( is_float( $v ) ) {
			return is_finite( $v ) ? $v : null; // INF / NaN cannot be stored as JSON.
		}
		if ( is_string( $v ) ) {
			return strlen( $v ) > self::MAX_STRING ? substr( $v, 0, self::MAX_STRING ) : $v;
		}
		if ( is_array( $v ) && $depth < 3 && count( $v ) <= 40 ) {
			$out = array();
			foreach ( $v as $k => $item ) {
				$item = $this->shape( $item, $depth + 1 );
				if ( null !== $item ) {
					$out[ is_int( $k ) ? $k : sanitize_key( (string) $k ) ] = $item;
				}
			}
			return $out;
		}
		return null;
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}

	public function empty_value() {
		return array();
	}

	public function value_hint( array $control ): string {
		return 'object: { "<component property key>": value } — keys come from the section template\'s page_settings.component_props';
	}
}
