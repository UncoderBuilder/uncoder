<?php
/**
 * Interactions (Behaviour → Interactions).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * `_interactions`: a list of "when this happens, do that to this" rows run by the front-end module
 * "interactions" (src/frontend/modules/interactions.ts). Everything is sanitized here: class and attribute
 * names, no event-handler attributes, no javascript: URLs, selectors without braces.
 */
final class Interactions {

	public const TRIGGERS = array( 'click', 'mouseenter', 'mouseleave', 'enter', 'leave', 'load', 'scroll' );
	public const ACTIONS  = array( 'add_class', 'remove_class', 'toggle_class', 'show', 'hide', 'toggle', 'set_attr', 'remove_attr', 'open_popup', 'close_popup', 'scroll_to' );
	public const TARGETS  = array( 'self', 'element', 'selector' );

	/**
	 * @param mixed    $value  Raw list.
	 * @param string[] $errors Problems found (for AI clients).
	 * @return array<int, array<string,mixed>>|null
	 */
	public static function sanitize( $value, array &$errors = array() ): ?array {
		if ( null === $value || '' === $value || array() === $value ) {
			return array();
		}
		if ( ! is_array( $value ) ) {
			$errors[] = 'Interactions must be a list: [{"trigger":"click","action":"toggle","target":"element","element":"ab12cd3"}].';
			return null;
		}
		$out = array();
		foreach ( array_values( $value ) as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$trigger = (string) ( $row['trigger'] ?? 'click' );
			$action  = (string) ( $row['action'] ?? '' );
			if ( ! in_array( $trigger, self::TRIGGERS, true ) || ! in_array( $action, self::ACTIONS, true ) ) {
				$errors[] = sprintf( 'Interaction %d: trigger must be one of %s and action one of %s.', $i + 1, implode( ', ', self::TRIGGERS ), implode( ', ', self::ACTIONS ) );
				continue;
			}
			$clean = array(
				'_id'     => isset( $row['_id'] ) && is_string( $row['_id'] ) ? substr( sanitize_key( $row['_id'] ), 0, 12 ) : substr( md5( (string) wp_json_encode( $row ) . $i ), 0, 7 ),
				'trigger' => $trigger,
				'action'  => $action,
			);
			if ( 'scroll' === $trigger ) {
				$clean['offset'] = max( 0, min( 20000, (int) ( $row['offset'] ?? 100 ) ) );
			}
			if ( ! empty( $row['delay'] ) ) {
				$clean['delay'] = max( 0, min( 10000, (int) $row['delay'] ) );
			}
			if ( ! in_array( $action, array( 'open_popup', 'close_popup' ), true ) ) {
				$target = (string) ( $row['target'] ?? 'self' );
				$target = in_array( $target, self::TARGETS, true ) ? $target : 'self';
				if ( 'element' === $target ) {
					$el = strtolower( (string) ( $row['element'] ?? '' ) );
					if ( ! Utils::is_valid_id( $el ) ) {
						$errors[] = sprintf( 'Interaction %d: "element" must be the id of another element.', $i + 1 );
						continue;
					}
					$clean['element'] = $el;
				} elseif ( 'selector' === $target ) {
					$sel = trim( (string) preg_replace( '/[{}<>;]/', '', (string) ( $row['selector'] ?? '' ) ) );
					if ( '' === $sel || strlen( $sel ) > 300 ) {
						$errors[] = sprintf( 'Interaction %d: "selector" must be a CSS selector.', $i + 1 );
						continue;
					}
					$clean['selector'] = $sel;
				}
				$clean['target'] = $target;
			}
			$value = is_scalar( $row['value'] ?? null ) ? trim( (string) $row['value'] ) : '';
			switch ( $action ) {
				case 'add_class':
				case 'remove_class':
				case 'toggle_class':
					$value = implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', $value ) ) ) );
					if ( '' === $value ) {
						$errors[] = sprintf( 'Interaction %d: give the class name to %s.', $i + 1, str_replace( '_class', '', $action ) );
						continue 2;
					}
					break;
				case 'set_attr':
				case 'remove_attr':
					$parts = explode( '=', $value, 2 );
					$name  = strtolower( trim( $parts[0] ) );
					if ( ! preg_match( '/^[a-z][a-z0-9_:\-]*$/', $name ) || 0 === strpos( $name, 'on' ) || in_array( $name, array( 'style', 'srcdoc' ), true ) ) {
						$errors[] = sprintf( 'Interaction %d: "%s" is not an attribute that can be set.', $i + 1, $name );
						continue 2;
					}
					$val = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : '';
					if ( in_array( $name, array( 'href', 'src', 'action', 'formaction', 'xlink:href' ), true ) ) {
						$val = esc_url_raw( $val );
					}
					$value = 'remove_attr' === $action ? $name : $name . '=' . $val;
					break;
				case 'open_popup':
					$value = (string) absint( $value );
					if ( '0' === $value ) {
						$errors[] = sprintf( 'Interaction %d: open_popup needs the popup id as value.', $i + 1 );
						continue 2;
					}
					break;
				case 'close_popup':
					$value = (string) absint( $value );
					$value = '0' === $value ? '' : $value;
					break;
				default:
					$value = '';
			}
			if ( '' !== $value ) {
				$clean['value'] = $value;
			}
			$out[] = $clean;
		}
		return $out;
	}
}
