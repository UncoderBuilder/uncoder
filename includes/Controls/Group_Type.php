<?php
/**
 * Base class for group controls (typography, background, border…).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls;

use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Css\Rules;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * A group stores one object; responsive sub-fields use the usual breakpoint suffixes
 * inside that object ("size", "size_tablet", "size_mobile").
 */
abstract class Group_Type extends Control_Type {

	public function is_group(): bool {
		return true;
	}

	/**
	 * Sub-field definitions, keyed by field name.
	 *
	 * @param array<string,mixed> $control Control definition (allows per-instance tweaks).
	 * @return array<string, array<string,mixed>>
	 */
	abstract public function fields( array $control ): array;

	/**
	 * Declarations for one device.
	 *
	 * @param array<string,mixed> $value   Group value.
	 * @param string              $device  Device id.
	 * @param array<string,mixed> $control Control definition.
	 * @return string[] Declarations like "color:#fff".
	 */
	abstract protected function declarations( array $value, string $device, array $control ): array;

	public function sanitize( $value, array $control ) {
		return $this->process( $value, $control, 'sanitize' );
	}

	public function normalize( $value, array $control ) {
		return $this->process( $value, $control, 'normalize' );
	}

	/**
	 * @param mixed $value Raw.
	 * @return array<string,mixed>|null
	 */
	protected function process( $value, array $control, string $mode ) {
		if ( '' === $value || null === $value ) {
			return array();
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$errors = array();
		return Plugin::instance()->controls()->process_settings( $value, $this->fields( $control ), $mode, $errors, '' );
	}

	public function validate( $value, array $control ): array {
		if ( ! is_array( $value ) ) {
			return array( sprintf( '%s must be an object with keys: %s.', $this->name(), implode( ', ', array_keys( $this->fields( $control ) ) ) ) );
		}
		$errors = array();
		Plugin::instance()->controls()->process_settings( $value, $this->fields( $control ), 'normalize', $errors, '' );
		return $errors;
	}

	public function group_css( array $value, array $control, string $selector, Rules $rules ): void {
		foreach ( Breakpoints::devices() as $device ) {
			$decls = $this->declarations( $value, $device, $control );
			if ( $decls ) {
				$rules->add( $selector, $decls, $device );
			}
		}
	}

	/**
	 * Value of a sub-field for a device (no inheritance: the CSS cascade handles that).
	 *
	 * @return mixed
	 */
	protected function v( array $value, string $key, string $device ) {
		$full = $key . Breakpoints::suffix( $device );
		return $value[ $full ] ?? null;
	}

	/**
	 * Whether any breakpoint variant of a sub-field is set.
	 */
	protected function any( array $value, string $key ): bool {
		foreach ( Breakpoints::devices() as $device ) {
			$v = $this->v( $value, $key, $device );
			if ( null !== $v && '' !== $v && ! ( is_array( $v ) && isset( $v['size'] ) && '' === $v['size'] ) ) {
				return true;
			}
		}
		return false;
	}

	protected function size( $v ): ?string {
		if ( ! is_array( $v ) || ! isset( $v['size'] ) || '' === $v['size'] ) {
			return null;
		}
		$unit = (string) ( $v['unit'] ?? 'px' );
		if ( 'custom' === $unit ) {
			return \Uncoder\Builder\Core\Utils::css_value( $v['size'] );
		}
		return $v['size'] . $unit;
	}

	public function empty_value() {
		return array();
	}

	public function export( array $control ): array {
		$registry          = Plugin::instance()->controls();
		$control['fields'] = array();
		foreach ( $this->fields( $control ) as $key => $field ) {
			$control['fields'][ $key ] = $registry->export_control( $field );
		}
		return $control;
	}

	public function value_hint( array $control ): string {
		$parts = array();
		foreach ( $this->fields( $control ) as $key => $field ) {
			$parts[] = $key . ( ! empty( $field['responsive'] ) ? '*' : '' );
		}
		return 'object {' . implode( ', ', $parts ) . '} (* = responsive: add _tablet/_mobile suffix)';
	}
}
