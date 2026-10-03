<?php
/**
 * Stateless helpers shared by every module.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Small, dependency-free helper functions.
 */
final class Utils {

	public const META_DATA  = '_uncoder_wb_data';
	public const META_MODE  = '_uncoder_wb_mode';
	public const META_PAGE  = '_uncoder_wb_page';
	public const META_CSS   = '_uncoder_wb_css_ver';
	public const META_TYPE  = '_uncoder_wb_type';
	public const META_CONDS = '_uncoder_wb_conditions';
	public const META_TPL   = '_uncoder_wb_template_settings';

	public const HEADING_TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	public const CONTAINER_TAGS = array( 'div', 'section', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'a' );

	/**
	 * Generates a 7-character element id (base36).
	 */
	public static function generate_id(): string {
		$id = '';
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$bytes = random_bytes( 7 );
		for ( $i = 0; $i < 7; $i++ ) {
			$id .= $chars[ ord( $bytes[ $i ] ) % 36 ];
		}
		// Must start with a letter so it is a valid CSS class fragment everywhere.
		if ( ctype_digit( $id[0] ) ) {
			$id[0] = $chars[ ord( $bytes[0] ) % 26 ];
		}
		return $id;
	}

	public static function is_valid_id( $id ): bool {
		return is_string( $id ) && (bool) preg_match( '/^[a-z][a-z0-9]{2,31}$/', $id );
	}

	/**
	 * The id attribute an element prints: only its anchor id (the "Anchor ID" setting, _css_id). Elements
	 * are addressed by their class uncoder-{id}; ids are for #links.
	 *
	 * @param array<string,mixed> $settings Element settings.
	 */
	public static function element_anchor( array $settings ): string {
		return is_string( $settings['_css_id'] ?? null ) ? sanitize_html_class( $settings['_css_id'] ) : '';
	}

	/**
	 * CSS selector of an element (twin of elementSelector() in src/shared/css.ts): its own class
	 * uncoder-{id} inside the document scope, so it outranks the widgets' rules (.uncoder .uncoder-{type})
	 * and theme rules such as `.entry-content h2`.
	 */
	public static function element_selector( string $id ): string {
		return '.uncoder .uncoder-' . $id;
	}

	/**
	 * Reads a dotted path from a nested array.
	 *
	 * @param array<mixed> $array Source.
	 * @param mixed        $default Default.
	 * @return mixed
	 */
	public static function get( array $array, string $path, $default = null ) {
		$current = $array;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return $default;
			}
			$current = $current[ $segment ];
		}
		return $current;
	}

	public static function is_empty_value( $value ): bool {
		if ( null === $value || '' === $value || array() === $value ) {
			return true;
		}
		if ( is_array( $value ) && array_key_exists( 'size', $value ) && ( '' === $value['size'] || null === $value['size'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Sanitizes a single CSS value so it cannot break out of a declaration.
	 */
	public static function css_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = (string) $value;
		$value = wp_strip_all_tags( $value );
		$value = str_replace( array( '{', '}', ';', '<', '>', '\\', '"', "\n", "\r" ), '', $value );
		if ( preg_match( '/(expression\s*\(|javascript:|@import|behavior\s*:|-moz-binding)/i', $value ) ) {
			return '';
		}
		return trim( $value );
	}

	/**
	 * Sanitizes a URL for use inside url("…").
	 */
	public static function css_url( $url ): string {
		$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		return str_replace( array( '"', "'", '(', ')', '\\', ' ' ), array( '%22', '%27', '%28', '%29', '', '%20' ), $url );
	}

	/**
	 * Sanitizes a colour: hex, rgb[a], hsl[a], oklch, named, CSS variables and color-mix().
	 */
	public static function sanitize_color( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
			return strtolower( $value );
		}
		if ( preg_match( '/^(rgba?|hsla?|oklch|oklab|lab|lch|hwb|color-mix)\([a-z0-9#.,%\s\/\-()]+\)$/i', $value ) ) {
			return self::css_value( $value );
		}
		if ( preg_match( '/^var\(--[a-z0-9\-_]+(\s*,\s*[#a-z0-9.,%\s\-()]+)?\)$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^[a-z]{3,30}$/i', $value ) ) {
			return strtolower( $value );
		}
		return '';
	}

	/**
	 * Cleans free-form custom CSS (only for users allowed to write it).
	 */
	public static function sanitize_custom_css( $css ): string {
		if ( ! is_string( $css ) ) {
			return '';
		}
		$css = wp_check_invalid_utf8( $css );
		$css = preg_replace( '#</?\s*style[^>]*>#i', '', $css );
		// "<" never appears in valid CSS; ">" is the child combinator and stays.
		$css = str_replace( '<', '', $css );
		$css = preg_replace( '/(expression\s*\(|javascript:|behavior\s*:|-moz-binding)/i', '', $css );
		return trim( (string) $css );
	}

	/**
	 * Builds an escaped HTML attribute string.
	 *
	 * @param array<string, mixed> $attrs Attributes; true = boolean attribute, null/false = skipped.
	 */
	public static function attrs( array $attrs ): string {
		$out = '';
		foreach ( $attrs as $name => $value ) {
			if ( null === $value || false === $value || ( is_array( $value ) && empty( $value ) ) ) {
				continue;
			}
			$name = strtolower( preg_replace( '/[^a-zA-Z0-9_\-:]/', '', (string) $name ) );
			if ( '' === $name ) {
				continue;
			}
			if ( true === $value ) {
				$out .= ' ' . $name;
				continue;
			}
			if ( is_array( $value ) ) {
				$value = 'class' === $name ? implode( ' ', array_filter( array_map( 'strval', $value ) ) ) : wp_json_encode( $value );
			}
			if ( 'href' === $name && preg_match( '/^#uncoder-popup:(open|close|toggle)(:\d+)?$/', (string) $value ) ) {
				// Popup actions are not URLs: esc_url() would read "#uncoder-popup" as a protocol and drop the link.
				$out .= ' href="' . esc_attr( (string) $value ) . '"';
			} elseif ( in_array( $name, array( 'href', 'src', 'action', 'poster', 'data-src' ), true ) ) {
				$out .= ' ' . $name . '="' . esc_url( (string) $value ) . '"';
			} else {
				$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
			}
		}
		return $out;
	}

	/**
	 * Parses "key|value" custom attribute lines, rejecting event handlers and scripts.
	 *
	 * @return array<string,string>
	 */
	public static function parse_custom_attributes( $raw ): array {
		$result = array();
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return $result;
		}
		foreach ( preg_split( '/\r\n|\r|\n|,/', $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$name  = strtolower( $parts[0] ?? '' );
			if ( ! preg_match( '/^[a-z][a-z0-9_\-:.]*$/', $name ) ) {
				continue;
			}
			if ( 0 === strpos( $name, 'on' ) || in_array( $name, array( 'style', 'href', 'src', 'srcdoc', 'formaction', 'action', 'id', 'class' ), true ) ) {
				continue;
			}
			// Runtime hooks (data-uncoder-js, data-uncoder-motion, data-settings…) only come from real settings.
			if ( preg_match( '/^data-(?:uncoder(?:-|$)|settings$)/', $name ) ) {
				continue;
			}
			$value = $parts[1] ?? '';
			if ( preg_match( '/(javascript|vbscript|data):/i', $value ) ) {
				continue;
			}
			$result[ $name ] = $value;
		}
		return $result;
	}

	public static function tag( $tag, array $allowed, string $fallback ): string {
		$tag = strtolower( (string) $tag );
		return in_array( $tag, $allowed, true ) ? $tag : $fallback;
	}

	public static function is_builder_post( int $post_id ): bool {
		return 'builder' === get_post_meta( $post_id, self::META_MODE, true );
	}

	/**
	 * Allowed HTML for rich text fields rendered on the front end.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function kses_rich(): array {
		$allowed = wp_kses_allowed_html( 'post' );
		foreach ( array( 'span', 'a', 'p', 'strong', 'em', 'mark', 'code', 'br' ) as $t ) {
			$allowed[ $t ]          = $allowed[ $t ] ?? array();
			$allowed[ $t ]['class'] = true;
			$allowed[ $t ]['style'] = true;
		}
		return $allowed;
	}

	/**
	 * Removes the attributes the front-end runtime acts on (data-uncoder-js, data-uncoder-*, data-settings)
	 * from user HTML, so rich text cannot wire up scripts (e.g. a video embed URL).
	 */
	/**
	 * Popup links inside rich text: kses reads "#uncoder-popup" in `#uncoder-popup:open:12` as a protocol and
	 * drops it, so they are stored as `#uncoder-popup-open-12` (the front-end runtime reads both forms).
	 */
	public static function popup_links_for_kses( string $html ): string {
		if ( false === strpos( $html, '#uncoder-popup:' ) ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'/(href\s*=\s*)(["\'])#uncoder-popup:(open|close|toggle)(?::(\d+))?\2/i',
			static fn( array $m ): string => $m[1] . $m[2] . '#uncoder-popup-' . strtolower( $m[3] ) . ( isset( $m[4] ) && '' !== $m[4] ? '-' . $m[4] : '' ) . $m[2],
			$html
		);
	}

	public static function strip_runtime_attrs( string $html ): string {
		if ( false === stripos( $html, 'data-' ) ) {
			return $html;
		}
		return (string) preg_replace( '/\sdata-(?:uncoder(?:-[a-z0-9_-]*)?|settings)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/i', '', $html );
	}

	/**
	 * Allowed HTML for inline text (headings, buttons): no block elements.
	 *
	 * @return array<string, array<string, bool>>
	 */
	/**
	 * WordPress drops any inline style declaration with parentheses unless the function is calc(), var()… so
	 * "color: rgba(14, 36, 25, 0.55)" disappeared from <span style>. Color functions with numeric arguments only
	 * (rgb, rgba, hsl, hsla) are as safe as a hex value: allow them, nothing else changes.
	 *
	 * @param bool   $allow Whether WordPress already allows it.
	 * @param string $css   The declaration, with the allowed functions already removed.
	 */
	public static function allow_color_functions( $allow, $css ): bool {
		if ( $allow || ! is_string( $css ) ) {
			return (bool) $allow;
		}
		$rest = (string) preg_replace( '/\b(?:rgba?|hsla?)\(\s*[0-9.,%\s\/deg]+\)/i', '', $css );
		return $rest !== $css && ! preg_match( '%[\\\\(&=}]|/\*%', $rest );
	}

	public static function kses_inline(): array {
		return array(
			'span'   => array( 'class' => true, 'style' => true ),
			'strong' => array( 'class' => true ),
			'b'      => array(),
			'em'     => array( 'class' => true ),
			'i'      => array( 'class' => true ),
			'u'      => array(),
			's'      => array(),
			'mark'   => array( 'class' => true ),
			'br'     => array( 'class' => true ), // "uncoder-hide-mobile": a line break on some devices only.
			'sup'    => array(),
			'sub'    => array(),
			'code'   => array(),
			'a'      => array( 'href' => true, 'target' => true, 'rel' => true, 'class' => true ),
		);
	}

	/**
	 * Base URL/dir of the plugin's uploads folder.
	 *
	 * @return array{dir:string,url:string}|null
	 */
	public static function uploads(): ?array {
		$upload = wp_upload_dir( null, false );
		if ( ! empty( $upload['error'] ) ) {
			return null;
		}
		return array(
			'dir' => trailingslashit( $upload['basedir'] ) . 'uncoder',
			'url' => set_url_scheme( trailingslashit( $upload['baseurl'] ) . 'uncoder' ),
		);
	}

	/**
	 * Converts "48px", "2.5rem", 48 into [size, unit].
	 *
	 * @return array{size: float|int|string, unit: string}|null
	 */
	public static function parse_size( $value, string $default_unit = 'px' ): ?array {
		if ( is_int( $value ) || is_float( $value ) ) {
			return array( 'size' => $value, 'unit' => $default_unit );
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return array( 'size' => '', 'unit' => $default_unit );
		}
		if ( preg_match( '/^(-?\d*\.?\d+)\s*(px|%|em|rem|vw|vh|svh|dvh|vmin|vmax|ch|deg|s|ms|fr)?$/i', $value, $m ) ) {
			$num = strpos( $m[1], '.' ) !== false ? (float) $m[1] : (int) $m[1];
			return array( 'size' => $num, 'unit' => isset( $m[2] ) && '' !== $m[2] ? strtolower( $m[2] ) : $default_unit );
		}
		return null;
	}

	public static function number( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		if ( is_numeric( $value ) ) {
			return ( (float) $value == (int) $value ) ? (int) $value : round( (float) $value, 4 ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		}
		return '';
	}

	public static function now_mysql(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Anonymises an IP (zeroes the last octet / last 80 bits) for logs.
	 */
	public static function anonymize_ip( string $ip ): string {
		if ( function_exists( 'wp_privacy_anonymize_ip' ) ) {
			return wp_privacy_anonymize_ip( $ip );
		}
		return $ip;
	}
}
