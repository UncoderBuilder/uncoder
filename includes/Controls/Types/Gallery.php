<?php
/**
 * Gallery control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;

defined( 'ABSPATH' ) || exit;

/**
 * A list of media values.
 */
class Gallery extends Control_Type {

	private Media $media;

	public function __construct() {
		$this->media = new Media();
	}

	public function name(): string {
		return 'gallery';
	}

	public function sanitize( $value, array $control ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( array_slice( array_values( $value ), 0, 200 ) as $item ) {
			$clean = $this->media->sanitize( $item, $control );
			if ( is_array( $clean ) && ( $clean['id'] || $clean['url'] ) ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	public function normalize( $value, array $control ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( array_values( $value ) as $item ) {
			$clean = $this->media->normalize( $item, $control );
			if ( is_array( $clean ) && ( $clean['id'] || $clean['url'] ) ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}

	public function empty_value() {
		return array();
	}

	public function value_hint( array $control ): string {
		return 'array of media {"id","url","alt"} (URL strings accepted)';
	}
}
