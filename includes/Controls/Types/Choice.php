<?php
/**
 * select / choose controls.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * One value from a fixed option list. Options: [ value => label ] or [ value => [ label, icon ] ].
 * Set `options_dynamic => true` for lists resolved at runtime (menus, templates…): any slug-like value is kept.
 */
class Choice extends Control_Type {

	private string $type;

	public function __construct( string $type = 'select' ) {
		$this->type = $type;
	}

	public function name(): string {
		return $this->type;
	}

	/**
	 * @return string[]
	 */
	private function keys( array $control ): array {
		return array_map( 'strval', array_keys( $control['options'] ?? array() ) );
	}

	public function sanitize( $value, array $control ) {
		if ( is_int( $value ) ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		if ( '' === $value ) {
			return '';
		}
		if ( ! empty( $control['options_dynamic'] ) ) {
			return preg_match( '/^[A-Za-z0-9_\-:.\/]{1,191}$/', $value ) ? $value : null;
		}
		if ( in_array( $value, $this->keys( $control ), true ) ) {
			return $value;
		}
		// Controls may accept values beyond their options (e.g. any numeric font weight).
		if ( ! empty( $control['allow'] ) && is_string( $control['allow'] ) && preg_match( $control['allow'], $value ) ) {
			return $value;
		}
		return null;
	}

	public function normalize( $value, array $control ) {
		if ( is_bool( $value ) ) {
			$value = $value ? 'yes' : '';
		}
		$clean = $this->sanitize( $value, $control );
		if ( null !== $clean || ! is_string( $value ) ) {
			return $clean;
		}
		// Case-insensitive and label matching for AI input.
		$needle = strtolower( trim( $value ) );
		foreach ( $control['options'] ?? array() as $key => $label ) {
			$label = is_array( $label ) ? ( $label['label'] ?? $label['title'] ?? '' ) : $label;
			if ( strtolower( (string) $key ) === $needle || strtolower( (string) $label ) === $needle ) {
				return (string) $key;
			}
		}
		return null;
	}

	public function validate( $value, array $control ): array {
		if ( null === $this->normalize( $value, $control ) ) {
			return array( sprintf( 'Must be one of: %s.', implode( ', ', array_map( static fn( $k ) => '"' . $k . '"', $this->keys( $control ) ) ) ) );
		}
		return array();
	}

	public function placeholders( $value, array $control ): ?array {
		if ( '' === $value || null === $value || ! is_string( $value ) ) {
			return null;
		}
		return array( 'VALUE' => Utils::css_value( $value ) );
	}

	public function value_hint( array $control ): string {
		if ( ! empty( $control['options_dynamic'] ) ) {
			return 'string (see options)';
		}
		return 'one of: ' . implode( ' | ', array_map( static fn( $k ) => '"' . $k . '"', $this->keys( $control ) ) );
	}
}
