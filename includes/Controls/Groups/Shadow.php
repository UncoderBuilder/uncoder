<?php
/**
 * Box shadow and text shadow groups.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Groups;

use Uncoder\Builder\Controls\Group_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * box_shadow: { x, y, blur, spread, color, inset } · text_shadow: { x, y, blur, color }.
 * Emitted only when a color is set.
 */
class Shadow extends Group_Type {

	private string $type;

	public function __construct( string $type = 'box_shadow' ) {
		$this->type = $type;
	}

	public function name(): string {
		return $this->type;
	}

	public function fields( array $control ): array {
		$fields = array(
			'x'     => array( 'type' => 'number', 'label' => __( 'Horizontal', 'uncoder' ), 'min' => -200, 'max' => 200 ),
			'y'     => array( 'type' => 'number', 'label' => __( 'Vertical', 'uncoder' ), 'min' => -200, 'max' => 200 ),
			'blur'  => array( 'type' => 'number', 'label' => __( 'Blur', 'uncoder' ), 'min' => 0, 'max' => 300 ),
			'color' => array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ) ),
		);
		if ( 'box_shadow' === $this->type ) {
			$fields['spread'] = array( 'type' => 'number', 'label' => __( 'Spread', 'uncoder' ), 'min' => -200, 'max' => 200 );
			$fields['inset']  = array( 'type' => 'switch', 'label' => __( 'Inset', 'uncoder' ) );
		}
		return $fields;
	}

	public function normalize( $value, array $control ) {
		if ( is_string( $value ) ) {
			$v = trim( $value );
			if ( 'none' === $v || '' === $v ) {
				return array();
			}
			$inset = false;
			if ( 0 === stripos( $v, 'inset ' ) ) {
				$inset = true;
				$v     = trim( substr( $v, 6 ) );
			}
			// Tokenise on whitespace outside parentheses: numbers are offsets, the rest is the color.
			$tokens = array();
			$buf    = '';
			$depth  = 0;
			foreach ( str_split( $v ) as $ch ) {
				if ( '(' === $ch ) {
					++$depth;
				} elseif ( ')' === $ch ) {
					--$depth;
				}
				if ( ctype_space( $ch ) && 0 === $depth ) {
					if ( '' !== $buf ) {
						$tokens[] = $buf;
					}
					$buf = '';
					continue;
				}
				$buf .= $ch;
			}
			if ( '' !== $buf ) {
				$tokens[] = $buf;
			}
			$nums  = array();
			$color = '';
			foreach ( $tokens as $token ) {
				if ( preg_match( '/^-?\d*\.?\d+(px)?$/', $token ) ) {
					$nums[] = (float) $token;
				} else {
					$color = $token;
				}
			}
			if ( count( $nums ) >= 2 ) {
				$value = array(
					'x'     => $nums[0] ?? 0,
					'y'     => $nums[1] ?? 0,
					'blur'  => $nums[2] ?? 0,
					'color' => $color,
				);
				if ( 'box_shadow' === $this->type ) {
					$value['spread'] = $nums[3] ?? 0;
					$value['inset']  = $inset;
				}
			}
		}
		return parent::normalize( $value, $control );
	}

	protected function declarations( array $value, string $device, array $control ): array {
		if ( 'desktop' !== $device ) {
			return array();
		}
		$color = Utils::sanitize_color( (string) ( $value['color'] ?? '' ) );
		if ( '' === $color ) {
			return array();
		}
		$n = static fn( $k ) => ( isset( $value[ $k ] ) && is_numeric( $value[ $k ] ) ? (float) $value[ $k ] : 0 ) . 'px';
		if ( 'text_shadow' === $this->type ) {
			return array( 'text-shadow:' . $n( 'x' ) . ' ' . $n( 'y' ) . ' ' . $n( 'blur' ) . ' ' . $color );
		}
		$inset = ! empty( $value['inset'] ) ? 'inset ' : '';
		return array( 'box-shadow:' . $inset . $n( 'x' ) . ' ' . $n( 'y' ) . ' ' . $n( 'blur' ) . ' ' . $n( 'spread' ) . ' ' . $color );
	}

	public function value_hint( array $control ): string {
		return 'box_shadow' === $this->type
			? '{"x":0,"y":10,"blur":30,"spread":0,"color":"rgba(0,0,0,.12)","inset":false} or CSS "0 10px 30px rgba(0,0,0,.12)"'
			: '{"x":0,"y":2,"blur":4,"color":"rgba(0,0,0,.3)"} or CSS "0 2px 4px rgba(0,0,0,.3)"';
	}
}
