<?php
/**
 * Number control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * A bare number, clamped to min/max.
 */
class Number extends Control_Type {

	public function name(): string {
		return 'number';
	}

	public function sanitize( $value, array $control ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$value = Utils::number( $value );
		// "1e999" is numeric but infinite: it cannot be stored (JSON has no INF / NaN).
		if ( is_float( $value ) && ! is_finite( $value ) ) {
			return null;
		}
		if ( isset( $control['min'] ) && $value < $control['min'] ) {
			$value = $control['min'];
		}
		if ( isset( $control['max'] ) && $value > $control['max'] ) {
			$value = $control['max'];
		}
		return $value;
	}

	public function placeholders( $value, array $control ): ?array {
		if ( '' === $value || null === $value ) {
			return null;
		}
		return array( 'VALUE' => (string) $value );
	}

	public function value_hint( array $control ): string {
		$range = '';
		if ( isset( $control['min'] ) || isset( $control['max'] ) ) {
			$range = ' (' . ( $control['min'] ?? '-∞' ) . '…' . ( $control['max'] ?? '∞' ) . ')';
		}
		return 'number' . $range;
	}
}
