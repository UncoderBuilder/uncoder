<?php
/**
 * Code control (HTML / CSS / JS).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Raw code is only kept for users with unfiltered_html; everyone else is filtered.
 */
class Code extends Control_Type {

	public function name(): string {
		return 'code';
	}

	public function sanitize( $value, array $control ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		// Code samples that are only ever printed escaped (e.g. Code Highlight) are plain text.
		if ( ! empty( $control['escaped'] ) ) {
			return substr( str_replace( "\0", '', $value ), 0, 100000 );
		}
		$language = $control['language'] ?? 'html';
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			// CSS can load external resources and overlay the page; HTML is filtered.
			return 'html' === $language ? Utils::strip_runtime_attrs( wp_kses_post( $value ) ) : '';
		}
		if ( 'css' === $language ) {
			return Utils::sanitize_custom_css( $value );
		}
		return $value;
	}

	public function value_hint( array $control ): string {
		return 'string (' . ( $control['language'] ?? 'html' ) . ' code)';
	}
}
