<?php
/**
 * Font family control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;

defined( 'ABSPATH' ) || exit;

/**
 * A font family name ("Inter", "Playfair Display") or a Design System font variable var(--uncoder-f-{id}).
 */
class Font extends Control_Type {

	public function name(): string {
		return 'font';
	}

	public function sanitize( $value, array $control ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( $value, " \t\n\r\0\x0B\"'" );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^var\(--uncoder-f-[a-z0-9_\-]+\)$/', $value ) ) {
			return $value;
		}
		// Take the first family of a stack.
		$value = trim( explode( ',', $value )[0], " \"'" );
		return preg_match( '/^[A-Za-z0-9][A-Za-z0-9 \-_.]{0,62}$/', $value ) ? $value : null;
	}

	public function placeholders( $value, array $control ): ?array {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}
		return array( 'VALUE' => \Uncoder\Builder\Core\Fonts::css_stack( $value ) );
	}

	public function value_hint( array $control ): string {
		return 'font family name (any Google Font, Fontshare font or custom font) or "var(--uncoder-f-primary)"';
	}
}
