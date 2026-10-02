<?php
/**
 * Dynamic tags registry.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Dynamic;

use Uncoder\Builder\Core\Render_Context;

defined( 'ABSPATH' ) || exit;

/**
 * A dynamic tag replaces a control value with live data (post title, featured image, custom field…).
 *
 * Stored on the element as: "dynamic": { "title": { "tag": "post-title", "options": {}, "before": "", "after": "", "fallback": "" } }
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
