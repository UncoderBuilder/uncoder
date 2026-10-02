<?php
/**
 * Dimensions control: four sides + unit (padding, margin, radius, border width).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical value: { "top": 10, "right": 20, "bottom": 10, "left": 20, "unit": "px", "linked": false }.
 * Empty strings mean "not set" and emit no CSS for that side.
 */
class Dimensions extends Control_Type {

	public const SIDES = array( 'top', 'right', 'bottom', 'left' );

	public function name(): string {
		return 'dimensions';
	}

	public function sanitize( $value, array $control ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$units = $control['size_units'] ?? array( 'px', '%', 'em', 'rem', 'vw', 'vh' );
		$unit  = isset( $value['unit'] ) ? (string) $value['unit'] : ( $units[0] ?? 'px' );
		if ( ! in_array( $unit, Slider::ALL_UNITS, true ) || 'custom' === $unit ) {
			$unit = $units[0] ?? 'px';
		}
		$out = array();
		foreach ( self::SIDES as $side ) {
			$v = $value[ $side ] ?? '';
			if ( is_string( $v ) ) {
				$v = trim( $v );
			}
			if ( 'auto' === $v ) {
				$out[ $side ] = 'auto';
				continue;
			}
			if ( self::is_var( $v ) ) {
				$out[ $side ] = $v;
				continue;
			}
			$out[ $side ] = ( '' === $v || null === $v || ! is_numeric( $v ) || ! is_finite( (float) $v ) ) ? '' : Utils::number( $v );
		}
		$out['unit']   = $unit;
		$out['linked'] = ! empty( $value['linked'] );
		return $out;
	}

	public function normalize( $value, array $control ) {
		$units   = $control['size_units'] ?? array( 'px' );
		$default = $units[0] ?? 'px';
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = $value . $default;
		}
		if ( is_string( $value ) ) {
			$parts = preg_split( '/\s+/', trim( $value ) );
			$vals  = array();
			$unit  = $default;
			foreach ( $parts as $part ) {
				if ( 'auto' === $part ) {
					$vals[] = 'auto';
					continue;
				}
				$parsed = Utils::parse_size( $part, $default );
				if ( ! $parsed ) {
					return null;
				}
				$vals[] = $parsed['size'];
				if ( 0 !== $parsed['size'] && '' !== $parsed['size'] ) {
					$unit = $parsed['unit'];
				}
			}
			switch ( count( $vals ) ) {
				case 1:
					$vals = array( $vals[0], $vals[0], $vals[0], $vals[0] );
					break;
				case 2:
					$vals = array( $vals[0], $vals[1], $vals[0], $vals[1] );
					break;
				case 3:
					$vals = array( $vals[0], $vals[1], $vals[2], $vals[1] );
					break;
				case 4:
					break;
				default:
					return null;
			}
			$value = array_combine( self::SIDES, $vals ) + array( 'unit' => $unit );
		}
		if ( is_array( $value ) ) {
			// Accept {x: , y: } and {vertical:, horizontal:} convenience keys.
			foreach ( array( 'y' => array( 'top', 'bottom' ), 'vertical' => array( 'top', 'bottom' ), 'x' => array( 'left', 'right' ), 'horizontal' => array( 'left', 'right' ) ) as $k => $sides ) {
				if ( isset( $value[ $k ] ) ) {
					foreach ( $sides as $s ) {
						$value[ $s ] = $value[ $s ] ?? $value[ $k ];
					}
				}
			}
			foreach ( self::SIDES as $side ) {
				if ( isset( $value[ $side ] ) && is_string( $value[ $side ] ) && ! is_numeric( $value[ $side ] ) && 'auto' !== $value[ $side ] ) {
					$parsed = Utils::parse_size( $value[ $side ], $value['unit'] ?? $default );
					if ( $parsed ) {
						$value[ $side ] = $parsed['size'];
						$value['unit']  = $value['unit'] ?? $parsed['unit'];
					}
				}
			}
			return $this->sanitize( $value, $control );
		}
		return null;
	}

	public function placeholders( $value, array $control ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$unit = (string) ( $value['unit'] ?? 'px' );
		$any  = false;
		$map  = array( 'UNIT' => $unit );
		$vals = array();
		foreach ( self::SIDES as $side ) {
			$v = $value[ $side ] ?? '';
			if ( '' !== $v ) {
				$any = true;
			}
			$map[ strtoupper( $side ) ] = '' === $v ? '' : (string) $v;
			$vals[]                     = '' === $v ? '0' : ( 'auto' === $v || self::is_var( $v ) ? (string) $v : $v . ( 0 == $v ? '' : $unit ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		}
		if ( ! $any ) {
			return null;
		}
		$map['VALUE'] = implode( ' ', $vals );
		return $map;
	}

	public function empty_value() {
		return array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '', 'unit' => 'px', 'linked' => true );
	}

	/**
	 * A design variable as a side value: var(--uncoder-v-{id}) (twin of isVar() in src/shared/css.ts).
	 *
	 * @param mixed $v Side value.
	 */
	public static function is_var( $v ): bool {
		return is_string( $v ) && (bool) preg_match( '/^var\(--uncoder-v-[a-z0-9-]+\)$/', $v );
	}

	public function value_hint( array $control ): string {
		return '{"top": n, "right": n, "bottom": n, "left": n, "unit": "px|%|em|rem"} — CSS shorthand "40px 20px" accepted';
	}
}
