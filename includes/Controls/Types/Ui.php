<?php
/**
 * UI-only controls: heading, divider, notice, raw_html.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Presentational controls that never store a value.
 */
class Ui extends Control_Type {

	private string $type;

	public function __construct( string $type ) {
		$this->type = $type;
	}

	public function name(): string {
		return $this->type;
	}

	public function has_value(): bool {
		return false;
	}

	public function sanitize( $value, array $control ) {
		return null;
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}
}
