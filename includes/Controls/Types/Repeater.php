<?php
/**
 * Repeater control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * A list of rows; each row is a settings map validated with the control's `fields`.
 * Every row carries a stable `_id` used for {{CURRENT_ITEM}} selectors.
 */
class Repeater extends Control_Type {

	public const MAX_ROWS = 200;

	public function name(): string {
		return 'repeater';
	}

	public function sanitize( $value, array $control ) {
		return $this->process( $value, $control, 'sanitize' );
	}

	public function normalize( $value, array $control ) {
		return $this->process( $value, $control, 'normalize' );
	}

	/**
	 * @param mixed $value Rows.
	 * @return array<int, array<string,mixed>>|null
	 */
	private function process( $value, array $control, string $mode ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$fields   = $control['fields'] ?? array();
		$registry = Plugin::instance()->controls();
		$rows     = array();
		$seen     = array();
		foreach ( array_slice( array_values( $value ), 0, self::MAX_ROWS ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$errors = array();
			$clean  = $registry->process_settings( $row, $fields, $mode, $errors, '' );
			if ( 'normalize' === $mode ) {
				// Rows written by AI clients get the field defaults the editor would have added.
				foreach ( $fields as $key => $field ) {
					if ( ! array_key_exists( $key, $clean ) && array_key_exists( 'default', $field ) ) {
						$clean[ $key ] = $field['default'];
					}
				}
			}
			$id     = isset( $row['_id'] ) && is_string( $row['_id'] ) && preg_match( '/^[a-z0-9]{3,32}$/', $row['_id'] ) ? $row['_id'] : Utils::generate_id();
			if ( isset( $seen[ $id ] ) ) {
				$id = Utils::generate_id();
			}
			$seen[ $id ]  = true;
			$clean['_id'] = $id;
			$rows[]       = $clean;
		}
		return $rows;
	}

	public function validate( $value, array $control ): array {
		if ( ! is_array( $value ) ) {
			return array( 'Repeater value must be an array of row objects.' );
		}
		$errors   = array();
		$registry = Plugin::instance()->controls();
		foreach ( array_values( $value ) as $i => $row ) {
			if ( ! is_array( $row ) ) {
				$errors[] = sprintf( 'Row %d must be an object.', $i );
				continue;
			}
			$row_errors = array();
			$registry->process_settings( $row, $control['fields'] ?? array(), 'normalize', $row_errors, '[' . $i . ']' );
			$errors = array_merge( $errors, $row_errors );
		}
		return $errors;
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}

	public function empty_value() {
		return array();
	}

	public function export( array $control ): array {
		$registry = Plugin::instance()->controls();
		$fields   = array();
		foreach ( $control['fields'] ?? array() as $key => $field ) {
			$fields[ $key ] = $registry->export_control( $field );
		}
		$control['fields'] = $fields;
		return $control;
	}

	public function value_hint( array $control ): string {
		return 'array of row objects with fields: ' . implode( ', ', array_keys( $control['fields'] ?? array() ) );
	}
}
