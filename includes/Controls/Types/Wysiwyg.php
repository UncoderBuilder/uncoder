<?php
/**
 * Rich text control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * HTML limited to post-safe tags.
 */
class Wysiwyg extends Control_Type {

	public function name(): string {
		return 'wysiwyg';
	}

	public function sanitize( $value, array $control ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		return Utils::strip_runtime_attrs( wp_kses( Utils::popup_links_for_kses( $value ), Utils::kses_rich() ) );
	}

	public function normalize( $value, array $control ) {
		if ( is_string( $value ) && '' !== trim( $value ) && false === strpos( $value, '<' ) ) {
			// Plain text from AI: wrap paragraphs.
			$value = wpautop( esc_html( $value ) );
		}
		return $this->sanitize( $value, $control );
	}

	public function value_hint( array $control ): string {
		return 'HTML string (p, h2-h6, ul/ol/li, a, strong, em, blockquote, img, span)';
	}
}
