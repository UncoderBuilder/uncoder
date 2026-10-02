<?php
/**
 * Media control (image / video / file from the library or a URL).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical value: { "id": 123, "url": "https://…", "alt": "" }. id = 0 for external URLs.
 */
class Media extends Control_Type {

	public function name(): string {
		return 'media';
	}

	public function sanitize( $value, array $control ) {
		if ( '' === $value || null === $value ) {
			return array( 'id' => 0, 'url' => '' );
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array(
			'id'  => isset( $value['id'] ) ? absint( $value['id'] ) : 0,
			'url' => isset( $value['url'] ) ? esc_url_raw( (string) $value['url'] ) : '',
		);
		if ( isset( $value['alt'] ) && is_string( $value['alt'] ) ) {
			$out['alt'] = sanitize_text_field( $value['alt'] );
		}
		if ( isset( $value['size'] ) && is_string( $value['size'] ) ) {
			$out['size'] = sanitize_key( $value['size'] );
		}
		// Trust the attachment's canonical URL when an id is given.
		if ( $out['id'] && '' === $out['url'] ) {
			$url        = wp_get_attachment_url( $out['id'] );
			$out['url'] = $url ? $url : '';
		}
		return $out;
	}

	public function normalize( $value, array $control ) {
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return $this->sanitize( array( 'id' => (int) $value ), $control );
		}
		if ( is_string( $value ) ) {
			return $this->sanitize( array( 'url' => $value ), $control );
		}
		return $this->sanitize( $value, $control );
	}

	public function placeholders( $value, array $control ): ?array {
		if ( ! is_array( $value ) || empty( $value['url'] ) ) {
			return null;
		}
		$url = Utils::css_url( $value['url'] );
		return '' === $url ? null : array( 'URL' => $url, 'VALUE' => $url );
	}

	public function empty_value() {
		return array( 'id' => 0, 'url' => '' );
	}

	public function value_hint( array $control ): string {
		return '{"id": attachment_id, "url": "https://…", "alt": "…"} — a URL string or attachment id is accepted (external images are imported by upload_media)';
	}
}
