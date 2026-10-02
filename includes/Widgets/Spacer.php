<?php
/**
 * Spacer widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Empty vertical space. Prefer container gap/padding; use this for one-off spacing.
 */
class Spacer extends Widget_Base {

	public function name(): string {
		return 'spacer';
	}

	public function title(): string {
		return __( 'Spacer', 'uncoder' );
	}

	public function icon(): string {
		return 'move-vertical';
	}

	public function keywords(): array {
		return array( 'space', 'gap', 'margin', 'blank' );
	}

	public function description(): string {
		return __( 'Blank vertical space. Prefer container gap and padding for rhythm.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Spacer', 'uncoder' ) ) );
		$this->add_responsive_control(
			'space',
			array(
				'type'        => 'slider',
				'label'       => __( 'Space', 'uncoder' ),
				'size_units'  => array( 'px', 'vh', 'rem', 'em' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 600 ) ),
				'default'     => array( 'size' => 48, 'unit' => 'px' ),
				'css_default' => true,
				'selectors'   => array( '{{WRAPPER}}' => 'height: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		echo '<div class="uncoder-spacer" aria-hidden="true"></div>';
	}
}
