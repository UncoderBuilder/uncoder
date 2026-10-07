<?php
/**
 * Multi-select control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;

defined( 'ABSPATH' ) || exit;

/**
 * An array of option keys (or ids when `source` is set, e.g. posts/terms).
 */
class Multi extends Control_Type {

	public function name(): string {
		return 'multiselect';
	}

	public function sanitize( $value, array $control ) {
		if ( is_string( $value ) || is_int( $value ) ) {
			$value = '' === $value ? array() : array( $value );
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$keys = array_map( 'strval', array_keys( $control['options'] ?? array() ) );
		$out  = array();
		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$item = (string) $item;
			if ( isset( $control['source'] ) ) {
				if ( preg_match( '/^[A-Za-z0-9_\-:.]{1,191}$/', $item ) ) {
					$out[] = $item;
				}
			} elseif ( in_array( $item, $keys, true ) ) {
				$out[] = $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}

	public function empty_value() {
		return array();
	}

	public function value_hint( array $control ): string {
		return isset( $control['source'] ) ? 'array of ids' : 'array of: ' . implode( ', ', array_keys( $control['options'] ?? array() ) );
	}
}
