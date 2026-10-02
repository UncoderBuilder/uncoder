<?php
/**
 * Interactions control (Behaviour → Interactions).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Interactions as Rows;

defined( 'ABSPATH' ) || exit;

/**
 * "When … do … to …" rows (see Core\Interactions).
 */
class Interactions extends Control_Type {

	public function name(): string {
		return 'interactions';
	}

	public function sanitize( $value, array $control ) {
		return Rows::sanitize( $value );
	}

	public function validate( $value, array $control ): array {
		$errors = array();
		Rows::sanitize( $value, $errors );
		return $errors;
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}

	public function empty_value() {
		return array();
	}

	public function value_hint( array $control ): string {
		return 'List of {"trigger":"' . implode( '|', Rows::TRIGGERS ) . '","action":"' . implode( '|', Rows::ACTIONS ) . '","target":"self|element|selector","element":"{element id}","selector":".css","value":"class names | name=value | popup id","offset":px (scroll trigger),"delay":ms}. Example: a button that opens a hidden panel: [{"trigger":"click","action":"toggle","target":"element","element":"ab12cd3"}] with "_ix_hidden": true on the panel.';
	}
}
