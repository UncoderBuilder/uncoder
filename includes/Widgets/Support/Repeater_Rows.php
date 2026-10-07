<?php
/**
 * Repeater rows with their field defaults applied.
 *
 * Not a widget: the widget registry only scans includes/Widgets/*.php.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

use Uncoder\Builder\Core\Element_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Saved rows only hold the fields that were set (presets, AI input); render code wants every field.
 */
final class Repeater_Rows {

	/**
	 * @param mixed $value Repeater value.
	 * @return array<int, array<string,mixed>>
	 */
	public static function get( Element_Base $element, string $key, $value ): array {
		$defaults = array();
		$control  = $element->get_control( $key );
		foreach ( (array) ( $control['fields'] ?? array() ) as $field => $def ) {
			if ( is_array( $def ) && array_key_exists( 'default', $def ) ) {
				$defaults[ $field ] = $def['default'];
			}
		}
		$rows = array();
		foreach ( (array) $value as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = array_merge( $defaults, $row );
			}
		}
		return $rows;
	}
}
