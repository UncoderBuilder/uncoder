<?php
/**
 * Switch control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Boolean. Accepts "yes"/"no", 1/0, "true"/"false" when normalizing.
 */
class Toggle extends Control_Type {

	public function name(): string {
		return 'switch';
	}

	public function sanitize( $value, array $control ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( '' === $value || null === $value ) {
			return false;
		}
		return null;
	}

	public function normalize( $value, array $control ) {
		if ( is_string( $value ) ) {
			$v = strtolower( trim( $value ) );
			if ( in_array( $v, array( 'yes', 'true', '1', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $v, array( 'no', 'false', '0', 'off', '' ), true ) ) {
				return false;
			}
			return null;
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}
		return $this->sanitize( $value, $control );
	}

	public function placeholders( $value, array $control ): ?array {
		if ( true !== $value ) {
			return null;
		}
		return array( 'VALUE' => '1' );
	}

	public function empty_value() {
		return false;
	}

	public function value_hint( array $control ): string {
		return 'boolean';
	}
}
