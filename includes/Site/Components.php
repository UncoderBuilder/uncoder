<?php
/**
 * Components: section templates with properties that each placement can override.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * A section template lists its properties in page_settings.component_props:
 * [ { key, label, element (id in the section), setting (a Content-tab key of that element) } ].
 * The Template widget stores { key: value } in "overrides"; when it renders, each value replaces the
 * setting of that element after passing through that setting's own sanitizer (as the current user).
 */
final class Components {

	/** Control types a property may expose. */
	public const TYPES = array( 'text', 'textarea', 'wysiwyg', 'media', 'url', 'link', 'icon', 'number', 'select', 'choose', 'switch', 'color', 'gallery' );

	/**
	 * @param mixed                           $raw      Submitted props.
	 * @param array<int, array<string,mixed>> $elements The section's tree.
	 * @return array<int, array{key:string,label:string,element:string,setting:string}>
	 */
	public static function sanitize_props( $raw, array $elements ): array {
		$index = self::index( $elements );
		$out   = array();
		$keys  = array();
		foreach ( array_slice( (array) $raw, 0, 60 ) as $prop ) {
			if ( ! is_array( $prop ) ) {
				continue;
			}
			$element = (string) ( $prop['element'] ?? '' );
			$setting = sanitize_key( (string) ( $prop['setting'] ?? '' ) );
			$control = isset( $index[ $element ] ) ? self::control( $index[ $element ]['type'], $setting ) : null;
			if ( ! $control ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $prop['label'] ?? '' ) );
			$label = '' !== $label ? $label : ( $control['label'] ?? $setting );
			$key   = sanitize_key( (string) ( $prop['key'] ?? '' ) );
			$key   = '' !== $key ? $key : sanitize_key( str_replace( ' ', '_', $label ) );
			for ( $i = 2, $base = $key; '' === $key || isset( $keys[ $key ] ); $i++ ) {
				$key = ( '' !== $base ? $base : 'prop' ) . '_' . $i;
			}
			$keys[ $key ] = true;
			$out[]        = array(
				'key'     => $key,
				'label'   => $label,
				'element' => $element,
				'setting' => $setting,
			);
		}
		return $out;
	}

	/**
	 * The control a property points at, when it may be exposed (Content tab, supported type).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function control( string $type, string $setting ): ?array {
		$element = Plugin::instance()->elements()->get( $type );
		$control = $element ? $element->get_control( $setting ) : null;
		if ( ! $control || 'content' !== ( $control['tab'] ?? 'content' ) || ! in_array( $control['type'] ?? '', self::TYPES, true ) || 0 === strpos( $setting, '_' ) ) {
			return null;
		}
		return $control;
	}

	/**
	 * @param array<int, array<string,mixed>> $elements Tree.
	 * @return array<string, array{type:string}>
	 */
	private static function index( array $elements ): array {
		$out = array();
		foreach ( $elements as $node ) {
			if ( ! is_array( $node ) || empty( $node['id'] ) ) {
				continue;
			}
			$out[ (string) $node['id'] ] = array( 'type' => (string) ( $node['type'] ?? '' ) );
			$out                        += self::index( (array) ( $node['children'] ?? array() ) );
		}
		return $out;
	}

	/**
	 * The section's tree with the placement's overrides applied.
	 *
	 * @param array<int, array<string,mixed>> $elements  Section tree.
	 * @param array<int, array<string,mixed>> $props     component_props.
	 * @param array<string,mixed>             $overrides Placement values.
	 * @return array<int, array<string,mixed>>
	 */
	public static function apply( array $elements, array $props, array $overrides ): array {
		if ( ! $props || ! $overrides ) {
			return $elements;
		}
		$by_element = array();
		foreach ( $props as $prop ) {
			if ( array_key_exists( $prop['key'] ?? '', $overrides ) ) {
				$by_element[ (string) $prop['element'] ][ (string) $prop['setting'] ] = $overrides[ $prop['key'] ];
			}
		}
		return $by_element ? self::walk( $elements, $by_element ) : $elements;
	}

	/**
	 * @param array<int, array<string,mixed>>    $nodes      Tree.
	 * @param array<string, array<string,mixed>> $by_element Element id => { setting: raw value }.
	 * @return array<int, array<string,mixed>>
	 */
	private static function walk( array $nodes, array $by_element ): array {
		$registry = Plugin::instance()->controls();
		foreach ( $nodes as $i => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$id = (string) ( $node['id'] ?? '' );
			if ( isset( $by_element[ $id ] ) ) {
				foreach ( $by_element[ $id ] as $setting => $value ) {
					$control = self::control( (string) ( $node['type'] ?? '' ), $setting );
					if ( ! $control ) {
						continue;
					}
					// The value passes the same sanitizer a save of that element would use.
					$errors = array();
					$clean  = $registry->process_settings( array( $setting => $value ), array( $setting => $control ), 'sanitize', $errors, '' );
					if ( array_key_exists( $setting, $clean ) ) {
						$nodes[ $i ]['settings'][ $setting ] = $clean[ $setting ];
						// A dynamic tag on the same setting would win over the override: drop it.
						unset( $nodes[ $i ]['dynamic'][ $setting ] );
					}
				}
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$nodes[ $i ]['children'] = self::walk( $node['children'], $by_element );
			}
		}
		return $nodes;
	}

	/**
	 * component_props of a section template.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function props( int $template_id ): array {
		$doc   = Plugin::instance()->documents()->get( $template_id );
		$props = $doc ? ( $doc->page_settings()['component_props'] ?? array() ) : array();
		return is_array( $props ) ? $props : array();
	}
}
