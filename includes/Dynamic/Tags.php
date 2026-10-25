<?php
/**
 * Dynamic tags registry.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Dynamic;

use Uncoder\Builder\Core\Element_Base;
use Uncoder\Builder\Core\Render_Context;

defined( 'ABSPATH' ) || exit;

/**
 * A dynamic tag replaces a control value with live data (post title, featured image, custom field…).
 *
 * Stored on the element as: "dynamic": { "title": { "tag": "post-title", "options": {}, "before": "", "after": "", "fallback": "" } }
 * A field of one repeater item is keyed "<repeater>.<item _id>.<field>", e.g. "items.a1b2c3.text".
 */
final class Tags {

	/** @var array<string, array<string,mixed>> */
	private array $tags = array();

	private bool $loaded = false;

	public const GROUPS = array(
		'post'    => 'Post',
		'archive' => 'Archive',
		'term'    => 'Term (loop)',
		'site'    => 'Site',
		'author'  => 'Author',
		'user'    => 'User',
		'media'   => 'Media',
		'actions' => 'Actions',
		'other'   => 'Other',
		'woo'     => 'WooCommerce',
	);

	/**
	 * Registers a tag.
	 *
	 * @param array<string,mixed> $def {
	 *     @type string   $title      Label.
	 *     @type string   $group      One of self::GROUPS.
	 *     @type string[] $categories text | url | image | number | color | date | html.
	 *     @type array    $controls   Option controls (same format as element controls).
	 *     @type callable $callback   fn( array $options, Render_Context $ctx ): mixed.
	 *     @type string   $capability Optional capability needed to *save* the tag (e.g. unfiltered_html).
	 * }
	 */
	public function register( string $name, array $def ): void {
		$this->tags[ $name ] = array_merge(
			array(
				'title'      => $name,
				'group'      => 'other',
				'categories' => array( 'text' ),
				'controls'   => array(),
				'capability' => '',
			),
			$def
		);
	}

	private function load(): void {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;
		Core_Tags::register( $this );
		Field_Tags::register( $this );
		/**
		 * Register third-party dynamic tags.
		 *
		 * @param Tags $tags Registry.
		 */
		do_action( 'uncoder_wb/dynamic_tags/register', $this );
	}

	/**
	 * @return array<string, array<string,mixed>>
	 */
	public function all(): array {
		$this->load();
		return $this->tags;
	}

	public function get( string $name ): ?array {
		$this->load();
		return $this->tags[ $name ] ?? null;
	}

	/**
	 * Schema for the editor / AI (callbacks removed).
	 *
	 * @return array<string, array<string,mixed>>
	 */
	public function schema(): array {
		$out = array();
		foreach ( $this->all() as $name => $tag ) {
			unset( $tag['callback'] );
			$tag['name']  = $name;
			$out[ $name ] = $tag;
		}
		return $out;
	}

	/**
	 * Sanitizes a stored dynamic definition. Returns null to drop it.
	 *
	 * @param mixed $def Raw definition.
	 * @return array<string,mixed>|null
	 */
	/**
	 * Tag categories a control accepts (twin of categoriesFor() in the editor's ControlRow.tsx).
	 *
	 * @param array<string,mixed> $control Control.
	 * @return string[]
	 */
	public static function categories_for_control( array $control ): array {
		switch ( $control['type'] ?? '' ) {
			case 'url':
			case 'link':
				return array( 'url' );
			case 'media':
				return array( 'image' );
			case 'number':
				return array( 'number', 'text' );
			case 'color':
				return array( 'color' );
			case 'wysiwyg':
				return array( 'text', 'html' );
			default:
				return array( 'text' );
		}
	}

	public function sanitize( $def ): ?array {
		if ( is_string( $def ) ) {
			$def = array( 'tag' => $def );
		}
		if ( ! is_array( $def ) || empty( $def['tag'] ) || ! is_string( $def['tag'] ) ) {
			return null;
		}
		$tag = $this->get( $def['tag'] );
		if ( null === $tag ) {
			return null;
		}
		if ( '' !== $tag['capability'] && ! current_user_can( $tag['capability'] ) ) {
			return null;
		}
		$errors  = array();
		$options = \Uncoder\Builder\Plugin::instance()->controls()->process_settings(
			is_array( $def['options'] ?? null ) ? $def['options'] : array(),
			$tag['controls'],
			'normalize',
			$errors,
			''
		);
		$out = array(
			'tag'     => $def['tag'],
			'options' => $options,
		);
		foreach ( array( 'before', 'after', 'fallback' ) as $k ) {
			if ( isset( $def[ $k ] ) && is_string( $def[ $k ] ) && '' !== $def[ $k ] ) {
				// Keep one leading/trailing space: "Shown on: " + value must not become "Shown on:value".
				$clean = sanitize_text_field( $def[ $k ] );
				if ( 'fallback' !== $k && '' !== $clean ) {
					$clean = ( preg_match( '/^\s/', $def[ $k ] ) ? ' ' : '' ) . $clean . ( preg_match( '/\s$/', $def[ $k ] ) ? ' ' : '' );
				}
				$out[ $k ] = $clean;
			}
		}
		return $out;
	}

	/**
	 * Splits a dynamic key: a setting ("title") or one field of one repeater item ("items.<item _id>.text"; the item
	 * is named by its _id, which stays the same when items are moved).
	 *
	 * @return array{0:string,1:string,2:string} Setting, item id and field ('' and '' for a setting).
	 */
	public static function split_key( string $key ): array {
		$parts = explode( '.', $key );
		if ( 3 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] && '' !== $parts[2] ) {
			return array( $parts[0], $parts[1], $parts[2] );
		}
		return array( $key, '', '' );
	}

	/**
	 * The control a dynamic key points at on an element: a setting's control, or a field of a repeater. With the
	 * element's settings given, an item key also needs that item to exist.
	 *
	 * @param array<string,mixed>|null $settings The element's settings.
	 * @return array<string,mixed>|null
	 */
	public static function control_for_key( Element_Base $element, string $key, ?array $settings = null ): ?array {
		list( $setting, $item, $field ) = self::split_key( $key );
		$control = $element->get_control( $setting );
		if ( '' === $item ) {
			return $control;
		}
		if ( ! $control || 'repeater' !== ( $control['type'] ?? '' ) || ! isset( $control['fields'][ $field ] ) ) {
			return null;
		}
		if ( null !== $settings && null === self::item_index( $settings[ $setting ] ?? null, $item ) ) {
			return null;
		}
		return $control['fields'][ $field ];
	}

	/**
	 * Position of the repeater item with this _id.
	 *
	 * @param mixed $rows Repeater value.
	 */
	public static function item_index( $rows, string $item ): ?int {
		if ( ! is_array( $rows ) ) {
			return null;
		}
		foreach ( array_values( $rows ) as $i => $row ) {
			if ( is_array( $row ) && (string) ( $row['_id'] ?? '' ) === $item ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * An element's settings with its dynamic tags resolved: settings, and fields of repeater items.
	 *
	 * @param array<string,mixed> $dynamic  The element's "dynamic" map.
	 * @param array<string,mixed> $settings Its (effective) settings.
	 * @return array<string,mixed>
	 */
	public function apply( array $dynamic, array $settings, Render_Context $ctx, ?Element_Base $element = null ): array {
		foreach ( $dynamic as $key => $def ) {
			if ( ! is_array( $def ) || empty( $def['tag'] ) ) {
				continue;
			}
			list( $setting, $item, $field ) = self::split_key( (string) $key );
			if ( '' === $item ) {
				$value = $this->resolve( $def, $settings[ $setting ] ?? null, $ctx );
				if ( null !== $value ) {
					$settings[ $setting ] = $value;
				}
				continue;
			}
			$i = self::item_index( $settings[ $setting ] ?? null, $item );
			if ( null === $i ) {
				continue;
			}
			$rows    = array_values( $settings[ $setting ] );
			$control = $element ? self::control_for_key( $element, (string) $key ) : null;
			// An item without a value of its own for the field still gets the value shape of that field (a link object).
			$current = $rows[ $i ][ $field ] ?? ( $control['default'] ?? self::empty_value( $control ) );
			$value   = $this->resolve( $def, $current, $ctx );
			if ( null !== $value ) {
				$rows[ $i ][ $field ] = $value;
				$settings[ $setting ] = $rows;
			}
		}
		return $settings;
	}

	/**
	 * The empty value of a control whose value is an object (links, media), so a resolved value takes its shape.
	 *
	 * @param array<string,mixed>|null $control Control.
	 * @return mixed
	 */
	private static function empty_value( ?array $control ) {
		switch ( $control['type'] ?? '' ) {
			case 'url':
			case 'link':
				return array( 'url' => '' );
			case 'media':
				return array( 'id' => 0, 'url' => '' );
		}
		return null;
	}

	/**
	 * Resolves a dynamic definition into a value compatible with the control's current value shape.
	 *
	 * @param array<string,mixed> $def     Definition.
	 * @param mixed               $current Current (static) value.
	 * @return mixed Null keeps the static value.
	 */
	public function resolve( array $def, $current, Render_Context $ctx ) {
		$tag = $this->get( (string) $def['tag'] );
		if ( null === $tag || ! is_callable( $tag['callback'] ?? null ) ) {
			return null;
		}
		$value = call_user_func( $tag['callback'], is_array( $def['options'] ?? null ) ? $def['options'] : array(), $ctx );

		$empty = null === $value || '' === $value || array() === $value || ( is_array( $value ) && isset( $value['url'] ) && '' === $value['url'] );
		if ( $empty ) {
			if ( isset( $def['fallback'] ) && '' !== $def['fallback'] ) {
				$value = $def['fallback'];
			} elseif ( $ctx->editor ) {
				return null;
			} else {
				$value = '';
			}
		}

		// Link controls: merge the URL into the existing link object.
		if ( is_array( $current ) && array_key_exists( 'url', $current ) && ! isset( $current['id'] ) ) {
			$url = is_array( $value ) ? (string) ( $value['url'] ?? '' ) : (string) $value;
			return array_merge( $current, array( 'url' => $url ) );
		}
		// Media controls.
		if ( is_array( $current ) && array_key_exists( 'id', $current ) ) {
			if ( is_array( $value ) ) {
				return array_merge( array( 'id' => 0, 'url' => '' ), $value );
			}
			return is_numeric( $value ) ? array( 'id' => (int) $value, 'url' => (string) wp_get_attachment_url( (int) $value ) ) : array( 'id' => 0, 'url' => (string) $value );
		}
		if ( is_array( $value ) ) {
			return $value;
		}
		$text = (string) $value;
		if ( '' !== $text ) {
			$text = ( $def['before'] ?? '' ) . $text . ( $def['after'] ?? '' );
		}
		return $text;
	}
}
