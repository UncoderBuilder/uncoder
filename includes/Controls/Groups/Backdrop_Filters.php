<?php
/**
 * Backdrop filter group.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Groups;

defined( 'ABSPATH' ) || exit;

/**
 * Same fields as CSS filters, applied to what shows through the element (frosted glass): needs a
 * background that is at least partly transparent.
 */
class Backdrop_Filters extends Filters {

	public function name(): string {
		return 'backdrop_filter';
	}

	protected function max_blur(): int {
		return 60;
	}

	protected function output( string $functions ): array {
		return array( '-webkit-backdrop-filter:' . $functions, 'backdrop-filter:' . $functions );
	}
}
