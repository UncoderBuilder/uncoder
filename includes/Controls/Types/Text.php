<?php
/**
 * text / textarea / hidden controls.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Plain strings. `html => 'inline'` allows inline formatting (span/strong/em/a/br).
 */
class Text extends Control_Type {

	private string $type;

	public function __construct( string $type = 'text' ) {
		$this->type = $type;
	}

	public function name(): string {
		return $this->type;
	}

	public function sanitize( $value, array $control ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		if ( 'inline' === ( $control['html'] ?? '' ) ) {
			// kses writes every "&" as "&amp;"; keep the plain "&" the person typed (the title field shows what is
			// stored, and the widget escapes it again when it prints the page).
			return str_replace( '&amp;', '&', wp_kses( Utils::popup_links_for_kses( $value ), Utils::kses_inline() ) );
		}
		if ( 'textarea' === $this->type ) {
			return sanitize_textarea_field( $value );
		}
		return sanitize_text_field( $value );
	}

	public function value_hint( array $control ): string {
		return 'inline' === ( $control['html'] ?? '' ) ? 'string (inline HTML allowed: span, strong, em, a, br)' : 'string';
	}
}
