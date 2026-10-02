<?php
/**
 * Border group.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Groups;

use Uncoder\Builder\Controls\Group_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * { style, width*, color }. Width without style implies solid.
 */
class Border extends Group_Type {

	public function name(): string {
		return 'border';
	}

	public function fields( array $control ): array {
		return array(
			'style' => array(
				'type'    => 'select',
				'label'   => __( 'Style', 'uncoder' ),
				'options' => array( '' => 'Default', 'none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double', 'groove' => 'Groove' ),
			),
			'width' => array( 'type' => 'dimensions', 'label' => __( 'Width', 'uncoder' ), 'responsive' => true, 'size_units' => array( 'px', 'em', 'rem' ) ),
			'color' => array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ) ),
		);
	}

	public function normalize( $value, array $control ) {
		// "1px solid #ddd" shorthand.
		if ( is_string( $value ) && preg_match( '/^\s*(\d*\.?\d+(?:px|em|rem)?)\s+(solid|dashed|dotted|double|groove|none)\s+(.+)$/i', $value, $m ) ) {
			$value = array( 'width' => $m[1], 'style' => strtolower( $m[2] ), 'color' => trim( $m[3] ) );
		}
		return parent::normalize( $value, $control );
	}

	protected function declarations( array $value, string $device, array $control ): array {
		$d     = array();
		$style = (string) ( $value['style'] ?? '' );
		if ( 'desktop' === $device ) {
			if ( '' !== $style ) {
				$d[] = 'border-style:' . Utils::css_value( $style );
			} elseif ( $this->any( $value, 'width' ) ) {
				$d[] = 'border-style:solid';
			}
			$color = Utils::sanitize_color( (string) ( $value['color'] ?? '' ) );
			if ( '' !== $color ) {
				$d[] = 'border-color:' . $color;
			}
		}
		if ( 'none' === $style ) {
			return $d;
		}
		$width = $this->v( $value, 'width', $device );
		if ( is_array( $width ) ) {
			$unit = (string) ( $width['unit'] ?? 'px' );
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$v = $width[ $side ] ?? '';
				if ( '' !== $v && null !== $v ) {
					$d[] = 'border-' . $side . '-width:' . $v . $unit;
				}
			}
		}
		return $d;
	}
}
