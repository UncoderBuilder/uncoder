<?php
/**
 * Slider control: a number with a CSS unit.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical value: { "size": 24, "unit": "px" }. Unit "custom" stores any CSS expression in size
 * (e.g. clamp(2rem, 5vw, 4rem)).
 */
class Slider extends Control_Type {

	public const ALL_UNITS = array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'svh', 'dvh', 'vmin', 'vmax', 'ch', 'deg', 's', 'ms', 'fr', '', 'custom' );

	public function name(): string {
		return 'slider';
	}

	/**
	 * @return string[]
	 */
	public static function units( array $control ): array {
		$units = $control['size_units'] ?? array( 'px' );
		return array_values( array_unique( array_merge( (array) $units, array( 'custom' ) ) ) );
	}

	public function sanitize( $value, array $control ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$units = self::units( $control );
		$unit  = isset( $value['unit'] ) ? (string) $value['unit'] : ( $units[0] ?? 'px' );
		if ( ! in_array( $unit, self::ALL_UNITS, true ) ) {
			return null;
		}
		if ( 'custom' === $unit ) {
			$size = Utils::css_value( $value['size'] ?? '' );
			return array( 'size' => $size, 'unit' => 'custom' );
		}
		$size = $value['size'] ?? '';
		if ( '' === $size || null === $size ) {
			return array( 'size' => '', 'unit' => $unit );
		}
		// Infinite numbers ("1e999") cannot be stored as JSON.
		if ( ! is_numeric( $size ) || ! is_finite( (float) $size ) ) {
			return null;
		}
		return array( 'size' => Utils::number( $size ), 'unit' => $unit );
	}

	public function normalize( $value, array $control ) {
		$units   = self::units( $control );
		$default = $units[0] ?? 'px';
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) && is_string( $value['size'] ) && ! is_numeric( $value['size'] ) && ! isset( $value['unit'] ) ) {
				$value = $value['size'];
			} else {
				return $this->sanitize( $value, $control );
			}
		}
		if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( trim( $value ) ) ) ) {
			return is_finite( (float) $value ) ? array( 'size' => Utils::number( $value ), 'unit' => $default ) : null;
		}
		if ( is_string( $value ) ) {
			$parsed = Utils::parse_size( $value, $default );
			if ( $parsed ) {
				return $this->sanitize( $parsed, $control );
			}
			$css = Utils::css_value( $value );
			if ( '' !== $css ) {
				return array( 'size' => $css, 'unit' => 'custom' );
			}
		}
		return null;
	}

	public function placeholders( $value, array $control ): ?array {
		if ( ! is_array( $value ) || ! isset( $value['size'] ) || '' === $value['size'] ) {
			return null;
		}
		$unit = (string) ( $value['unit'] ?? 'px' );
		if ( 'custom' === $unit ) {
			$css = Utils::css_value( $value['size'] );
			return array( 'SIZE' => $css, 'UNIT' => '', 'VALUE' => $css );
		}
		$size = (string) $value['size'];
		return array( 'SIZE' => $size, 'UNIT' => $unit, 'VALUE' => $size . $unit );
	}

	public function empty_value() {
		return array( 'size' => '', 'unit' => 'px' );
	}

	public function value_hint( array $control ): string {
		return '{"size": number, "unit": "' . implode( '|', self::units( $control ) ) . '"} — shorthand "24px" accepted';
	}
}
