<?php
/**
 * Timeline animations control (Behaviour → Animations).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Animations;

defined( 'ABSPATH' ) || exit;

/**
 * A list of animation definitions (see Core\Animations). The control's "targets" lists what the
 * element can animate: itself, its child items, and on text widgets its words, letters or lines.
 */
class Animation extends Control_Type {

	public function name(): string {
		return 'animation';
	}

	/**
	 * @param array<string,mixed> $control Control definition.
	 * @return string[]
	 */
	private function targets( array $control ): array {
		$targets = isset( $control['targets'] ) && is_array( $control['targets'] ) ? array_values( array_intersect( Animations::TARGETS, $control['targets'] ) ) : Animations::TARGETS;
		return $targets ? $targets : array( 'self' );
	}

	public function sanitize( $value, array $control ) {
		return Animations::sanitize_list( $value, false, $this->targets( $control ) );
	}

	public function normalize( $value, array $control ) {
		return Animations::sanitize_list( $value, true, $this->targets( $control ) );
	}

	public function validate( $value, array $control ): array {
		$errors = array();
		$clean  = Animations::sanitize_list( $value, true, $this->targets( $control ), $errors );
		if ( null === $clean ) {
			$errors[] = 'Animations must be a list of animation objects, a preset name like "fade-up", or a list of preset names.';
		}
		return $errors;
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}

	public function empty_value() {
		return array();
	}

	public function value_hint( array $control ): string {
		return 'List of animations: a preset name ("fade-up"), [{"preset":"words-rise","delay":200}], or full [{"trigger":"enter|load|scroll|hover|click|loop","target":"' . implode( '|', $this->targets( $control ) ) . '","from":{…},"steps":[{"to":{…},"duration":800,"ease":"power3.out"}],"stagger":60}] — see the Animations guide topic';
	}
}
