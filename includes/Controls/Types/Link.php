<?php
/**
 * URL / link control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical value: { "url": "https://…", "external": false, "nofollow": false, "attributes": "" }.
 * Relative URLs, #anchors, mailto:, tel: and sms: are allowed.
 */
class Link extends Control_Type {

	public const PROTOCOLS = array( 'http', 'https', 'mailto', 'tel', 'sms', 'ftp', 'whatsapp', 'skype' );

	public function name(): string {
		return 'url';
	}

	public static function clean_url( $url ): string {
		if ( ! is_string( $url ) ) {
			return '';
		}
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( '#' === $url[0] ) {
			return '#' . preg_replace( '/[^A-Za-z0-9_\-:.]/', '', substr( $url, 1 ) );
		}
		// esc_url_raw keeps root-relative ("/about") and query-only ("?p=1") URLs as they are.
		return esc_url_raw( $url, self::PROTOCOLS );
	}

	public function sanitize( $value, array $control ) {
		if ( '' === $value || null === $value ) {
			return array( 'url' => '' );
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array( 'url' => self::clean_url( $value['url'] ?? '' ) );
		if ( ! empty( $value['external'] ) ) {
			$out['external'] = true;
		}
		if ( ! empty( $value['nofollow'] ) ) {
			$out['nofollow'] = true;
		}
		if ( ! empty( $value['attributes'] ) && is_string( $value['attributes'] ) ) {
			$attrs = Utils::parse_custom_attributes( $value['attributes'] );
			if ( $attrs ) {
				$out['attributes'] = implode(
					"\n",
					array_map( static fn( $k, $v ) => $k . '|' . $v, array_keys( $attrs ), $attrs )
				);
			}
		}
		return $out;
	}

	public function normalize( $value, array $control ) {
		if ( is_string( $value ) ) {
			return $this->sanitize( array( 'url' => $value ), $control );
		}
		if ( is_array( $value ) && isset( $value['target'] ) && '_blank' === $value['target'] ) {
			$value['external'] = true;
		}
		// Elementor's names for the same options ({url, is_external, nofollow, custom_attributes}).
		if ( is_array( $value ) && ! empty( $value['is_external'] ) ) {
			$value['external'] = true;
		}
		if ( is_array( $value ) && empty( $value['attributes'] ) && ! empty( $value['custom_attributes'] ) ) {
			$value['attributes'] = $value['custom_attributes'];
		}
		return $this->sanitize( $value, $control );
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}

	public function empty_value() {
		return array( 'url' => '' );
	}

	public function value_hint( array $control ): string {
		return '{"url": "https://… | /path | #anchor | mailto: | tel:", "external": bool, "nofollow": bool, "attributes": "key|value"} — plain URL string accepted';
	}
}
