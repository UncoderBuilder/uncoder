<?php
/**
 * Base class for control types.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls;

use Uncoder\Builder\Core\Css\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * A control type knows how to sanitize (strict, for saving), normalize (lenient, for AI/imports),
 * validate (explain problems) and turn a value into CSS placeholders.
 */
abstract class Control_Type {

	abstract public function name(): string;

	/** UI-only controls (headings, notices) store nothing. */
	public function has_value(): bool {
		return true;
	}

	/** Group controls produce their own CSS from a single "selector". */
	public function is_group(): bool {
		return false;
	}

	/**
	 * Strict sanitation of a canonical value. Returns null when the value must be dropped.
	 *
	 * @param mixed                $value   Raw value.
	 * @param array<string, mixed> $control Control definition.
	 * @return mixed
	 */
	abstract public function sanitize( $value, array $control );

	/**
	 * Lenient conversion from shorthand input (e.g. "48px", "10px 20px") into the canonical shape.
	 *
	 * @param mixed                $value   Raw value.
	 * @param array<string, mixed> $control Control definition.
	 * @return mixed
	 */
	public function normalize( $value, array $control ) {
		return $this->sanitize( $value, $control );
	}

	/**
	 * Human readable problems with a raw value (used by the MCP validator).
	 *
	 * @param mixed                $value   Raw value.
	 * @param array<string, mixed> $control Control definition.
	 * @return string[]
	 */
	public function validate( $value, array $control ): array {
		$normalized = $this->normalize( $value, $control );
		if ( null === $normalized && null !== $value && '' !== $value ) {
			return array( sprintf( 'Invalid value for %s control.', $this->name() ) );
		}
		return array();
	}

	/**
	 * Placeholder map used to fill a selector template ({{VALUE}}, {{SIZE}}…). Null = emit nothing.
	 *
	 * @param mixed                $value   Sanitized value.
	 * @param array<string, mixed> $control Control definition.
	 * @return array<string,string>|null
	 */
	public function placeholders( $value, array $control ): ?array {
		if ( null === $value || '' === $value || is_array( $value ) ) {
			return null;
		}
		return array( 'VALUE' => \Uncoder\Builder\Core\Utils::css_value( $value ) );
	}

	/**
	 * Group controls write their declarations directly.
	 *
	 * @param array<string, mixed> $value    Group value (may contain responsive sub keys).
	 * @param array<string, mixed> $control  Control definition.
	 * @param string               $selector Resolved selector.
	 * @param Rules                $rules    Collector.
	 */
	public function group_css( array $value, array $control, string $selector, Rules $rules ): void {}

	/**
	 * Default value when the control does not declare one.
	 *
	 * @return mixed
	 */
	public function empty_value() {
		return '';
	}

	/**
	 * Extra schema information sent to the editor and to AI clients.
	 *
	 * @param array<string, mixed> $control Control definition.
	 * @return array<string, mixed>
	 */
	public function export( array $control ): array {
		return $control;
	}

	/**
	 * Short description of the value shape for AI clients.
	 */
	public function value_hint( array $control ): string {
		return 'string';
	}
}
