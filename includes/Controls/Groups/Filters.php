<?php
/**
 * CSS filters group.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Groups;

use Uncoder\Builder\Controls\Group_Type;

defined( 'ABSPATH' ) || exit;

/**
 * { blur, brightness, contrast, saturate, hue, grayscale } → filter: …
 */
class Filters extends Group_Type {

	private const MAP = array(
		'blur'       => array( 'blur', 'px', 0, 20 ),
		'brightness' => array( 'brightness', '%', 0, 200 ),
		'contrast'   => array( 'contrast', '%', 0, 200 ),
		'saturate'   => array( 'saturate', '%', 0, 200 ),
		'grayscale'  => array( 'grayscale', '%', 0, 100 ),
		'hue'        => array( 'hue-rotate', 'deg', 0, 360 ),
	);

	public function name(): string {
		return 'css_filters';
	}

	public function fields( array $control ): array {
		$fields = array();
		foreach ( self::MAP as $key => $def ) {
			$fields[ $key ] = array(
				'type'  => 'number',
				'label' => ucfirst( $key ) . ' (' . $def[1] . ')',
				'min'   => $def[2],
				'max'   => 'blur' === $key ? $this->max_blur() : $def[3],
			);
		}
		return $fields;
	}

	protected function max_blur(): int {
		return 20;
	}

	/**
	 * @return string[]
	 */
	protected function output( string $functions ): array {
		return array( 'filter:' . $functions );
	}

	protected function declarations( array $value, string $device, array $control ): array {
		if ( 'desktop' !== $device ) {
			return array();
		}
		$parts = array();
		foreach ( self::MAP as $key => $def ) {
			if ( isset( $value[ $key ] ) && '' !== $value[ $key ] && is_numeric( $value[ $key ] ) ) {
				$parts[] = $def[0] . '(' . (float) $value[ $key ] . $def[1] . ')';
			}
		}
		return $parts ? $this->output( implode( ' ', $parts ) ) : array();
	}
}
