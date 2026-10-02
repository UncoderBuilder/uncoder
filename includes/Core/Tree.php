<?php
/**
 * Element tree sanitizer / normalizer / walker.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Validates every node against its element schema. "sanitize" drops what is invalid silently,
 * "normalize" (AI / import) additionally converts shorthands and reports actionable errors.
 */
final class Tree {

	public const MAX_DEPTH    = 40;
	public const MAX_ELEMENTS = 6000;

	/** Friendly aliases accepted in normalize mode. */
	public const ALIASES = array(
		'section'     => 'container',
		'column'      => 'container',
		'row'         => 'container',
		'grid'        => 'container',
		'div'         => 'container',
		'text'        => 'text-editor',
		'paragraph'   => 'text-editor',
		'rich-text'   => 'text-editor',
		'title'       => 'heading',
		'img'         => 'image',
		'icon_box'    => 'icon-box',
		'icon_list'   => 'icon-list',
		'image_box'   => 'image-box',
		'text_editor' => 'text-editor',
	);

	/** @var array<string,bool> */
	private array $seen = array();

	private int $count = 0;

	/** @var string[] */
	public array $errors = array();

	/** @var string[] */
	public array $warnings = array();

	private string $mode;

	public function __construct( string $mode = 'sanitize' ) {
		$this->mode = $mode;
	}

	/**
	 * @param mixed $elements Raw tree.
	 * @return array<int, array<string,mixed>>
	 */
	public function process( $elements, string $path = '' ): array {
		if ( '' === $path ) {
			// Trees written with the old unc- names (exports, snapshots, AI clients) are converted on the way in.
			$elements = Migrations::legacy( $elements );
		}
		if ( ! is_array( $elements ) ) {
			$this->errors[] = 'Elements must be an array.';
			return array();
		}
		$out = array();
		foreach ( array_values( $elements ) as $i => $node ) {
			$clean = $this->node( $node, 0, $path . '[' . $i . ']' );
			if ( null !== $clean ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	/**
	 * Registers ids that already exist elsewhere (when inserting a subtree into a document).
	 *
	 * @param string[] $ids Ids.
	 */
	public function reserve_ids( array $ids ): void {
		foreach ( $ids as $id ) {
			$this->seen[ $id ] = true;
		}
	}

	/**
	 * @param mixed $node Raw node.
	 * @return array<string,mixed>|null
	 */
	public function node( $node, int $depth, string $path ): ?array {
		if ( ! is_array( $node ) ) {
			$this->errors[] = $path . ': element must be an object.';
			return null;
		}
		if ( $depth > self::MAX_DEPTH ) {
			$this->errors[] = $path . ': nesting is too deep.';
			return null;
		}
		if ( ++$this->count > self::MAX_ELEMENTS ) {
			if ( self::MAX_ELEMENTS + 1 === $this->count ) {
				$this->errors[] = 'Too many elements in one document.';
			}
			return null;
		}

		$type_name = is_string( $node['type'] ?? null ) ? strtolower( $node['type'] ) : '';
		$settings  = is_array( $node['settings'] ?? null ) ? $node['settings'] : array();

		if ( 'normalize' === $this->mode ) {
			if ( '' === $type_name && isset( $node['widget'] ) && is_string( $node['widget'] ) ) {
				$type_name = strtolower( $node['widget'] );
			}
			if ( isset( self::ALIASES[ $type_name ] ) ) {
				if ( 'row' === $type_name && ! isset( $settings['direction'] ) ) {
					$settings['direction']        = 'row';
					$settings['direction_mobile'] = $settings['direction_mobile'] ?? 'column';
				}
				if ( 'grid' === $type_name ) {
					$settings['layout'] = 'grid';
				}
				$type_name = self::ALIASES[ $type_name ];
			}
			$type_name = str_replace( '_', '-', $type_name );
		}

		$type = Plugin::instance()->elements()->get( $type_name );
		if ( null === $type ) {
			$this->errors[] = sprintf( '%s: unknown element type "%s".%s', $path, $type_name, $this->suggest_type( $type_name ) );
			return null;
		}

		$id = is_string( $node['id'] ?? null ) ? strtolower( $node['id'] ) : '';
		if ( ! Utils::is_valid_id( $id ) || isset( $this->seen[ $id ] ) ) {
			$id = Utils::generate_id();
			while ( isset( $this->seen[ $id ] ) ) {
				$id = Utils::generate_id();
			}
		}
		$this->seen[ $id ] = true;

		$errors   = array();
		$controls = $type->get_controls();
		// The old flat display rules (_show_*) become a rule set in _conditions.
		$settings = is_array( $settings ) ? Element_Conditions::from_legacy( $settings ) : $settings;
		$states   = is_array( $settings ) && array_key_exists( '_states', $settings ) ? $settings['_states'] : null;
		if ( is_array( $settings ) ) {
			unset( $settings['_states'] );
		}
		$clean = Plugin::instance()->controls()->process_settings( $settings, $controls, $this->mode, $errors, $path . '.settings.' );
		if ( null !== $states ) {
			$states = $this->states( $states, $controls, $errors, $path . '.settings._states.' );
			if ( $states ) {
				$clean['_states'] = $states;
			}
		}
		foreach ( $errors as $error ) {
			$this->errors[] = $error;
		}

		$out = array(
			'id'       => $id,
			'type'     => $type->name(),
			'settings' => $clean,
		);

		if ( ! empty( $node['label'] ) && is_string( $node['label'] ) ) {
			$out['label'] = sanitize_text_field( $node['label'] );
		}
		if ( ! empty( $node['locked'] ) ) {
			$out['locked'] = true;
		}
		if ( ! empty( $node['disabled'] ) ) {
			$out['disabled'] = true;
		}

		if ( ! empty( $node['dynamic'] ) && is_array( $node['dynamic'] ) ) {
			$dynamic = array();
			foreach ( $node['dynamic'] as $key => $def ) {
				if ( ! isset( $controls[ $key ] ) || empty( $controls[ $key ]['dynamic'] ) ) {
					$this->errors[] = sprintf( '%s.dynamic.%s: this setting does not accept dynamic tags.', $path, $key );
					continue;
				}
				$clean_def = Plugin::instance()->tags()->sanitize( $def );
				if ( null === $clean_def ) {
					$this->errors[] = sprintf( '%s.dynamic.%s: unknown or unauthorized dynamic tag.', $path, $key );
					continue;
				}
				// A tag must produce the kind of value the setting expects (e.g. only URL tags in links).
				$tag_def  = Plugin::instance()->tags()->get( $clean_def['tag'] );
				$accepted = \Uncoder\Builder\Dynamic\Tags::categories_for_control( $controls[ $key ] );
				if ( ! $tag_def || ! array_intersect( (array) $tag_def['categories'], $accepted ) ) {
					$this->errors[] = sprintf( '%s.dynamic.%s: tag "%s" cannot be used here (this setting needs a %s tag).', $path, $key, $clean_def['tag'], implode( '/', $accepted ) );
					continue;
				}
				$dynamic[ $key ] = $clean_def;
			}
			if ( $dynamic ) {
				$out['dynamic'] = $dynamic;
			}
		}

		$can_have_children = $type->is_container() || null !== $type->nested();
		if ( isset( $node['children'] ) && is_array( $node['children'] ) && $node['children'] ) {
			if ( ! $can_have_children ) {
				$this->errors[] = sprintf( '%s: "%s" cannot contain children; wrap them in a container instead.', $path, $type->name() );
			} else {
				$children = array();
				foreach ( array_values( $node['children'] ) as $i => $child ) {
					if ( null !== $type->nested() && is_array( $child ) && 'container' !== ( $child['type'] ?? 'container' ) ) {
						// Nested widgets only hold containers: wrap stray widgets.
						$child = array(
							'type'     => 'container',
							'children' => array( $child ),
						);
					}
					$c = $this->node( $child, $depth + 1, $path . '.children[' . $i . ']' );
					if ( null !== $c ) {
						$children[] = $c;
					}
				}
				$out['children'] = $children;
			}
		} elseif ( $can_have_children ) {
			$out['children'] = array();
		}
		return $out;
	}

	private function suggest_type( string $name ): string {
		$best  = '';
		$score = PHP_INT_MAX;
		foreach ( array_keys( Plugin::instance()->elements()->all() ) as $candidate ) {
			$d = levenshtein( $name, $candidate );
			if ( $d < $score ) {
				$score = $d;
				$best  = $candidate;
			}
		}
		return $score <= 3 ? sprintf( ' Did you mean "%s"?', $best ) : ' Call list_widgets for valid types.';
	}

	/* ------------------------------------------------------------------ Walk helpers */

	/**
	 * Calls $fn( &$node, $parent_id, $index, $depth ) for every node (depth-first). Return false to stop.
	 *
	 * @param array<int, array<string,mixed>> $elements Tree (by reference).
	 */
	public static function walk( array &$elements, callable $fn, string $parent = '', int $depth = 0 ): bool {
		foreach ( $elements as $i => &$node ) {
			if ( false === $fn( $node, $parent, $i, $depth ) ) {
				return false;
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				if ( false === self::walk( $node['children'], $fn, (string) $node['id'], $depth + 1 ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param array<int, array<string,mixed>> $elements Tree.
	 * @return string[]
	 */
	public static function ids( array $elements ): array {
		$ids = array();
		self::walk(
			$elements,
			static function ( $node ) use ( &$ids ) {
				$ids[] = (string) ( $node['id'] ?? '' );
			}
		);
		return $ids;
	}

	/**
	 * @param array<int, array<string,mixed>> $elements Tree.
	 * @return string[] Element types used.
	 */
	public static function types( array $elements ): array {
		$types = array();
		self::walk(
			$elements,
			static function ( $node ) use ( &$types ) {
				$types[ (string) ( $node['type'] ?? '' ) ] = true;
			}
		);
		return array_keys( $types );
	}

	/**
	 * Finds a node by id.
	 *
	 * @param array<int, array<string,mixed>> $elements Tree.
	 * @return array<string,mixed>|null
	 */
	public static function find( array $elements, string $id ): ?array {
		$found = null;
		self::walk(
			$elements,
			static function ( $node ) use ( $id, &$found ) {
				if ( ( $node['id'] ?? '' ) === $id ) {
					$found = $node;
					return false;
				}
				return true;
			}
		);
		return $found;
	}

	/**
	 * `_states` (hover, focus, active, before, after or a custom "&" selector → settings), each sanitized
	 * like normal settings against the controls that produce CSS (Css\Generator::states_css()).
	 *
	 * @param mixed                              $states   Raw value.
	 * @param array<string, array<string,mixed>> $controls Element controls.
	 * @param string[]                           $errors   Problems found.
	 * @return array<string, array<string,mixed>>
	 */
	private function states( $states, array $controls, array &$errors, string $path ): array {
		if ( ! is_array( $states ) ) {
			$errors[] = $path . ': must be an object of state → settings, e.g. {"hover": {"_background": …}}.';
			return array();
		}
		$registry = Plugin::instance()->controls();
		$css      = array_filter(
			$controls,
			static function ( $control ) use ( $registry ) {
				$type = $registry->get( (string) ( $control['type'] ?? '' ) );
				return $type && ( $type->is_group() ? ! empty( $control['selector'] ) : ! empty( $control['selectors'] ) );
			}
		);
		$out = array();
		foreach ( $states as $state => $values ) {
			$key = is_string( $state ) ? Css\Generator::clean_state( $state ) : '';
			if ( '' === $key ) {
				$errors[] = sprintf( '%s: "%s" is not a state; use hover, focus, active, before, after or a selector with & (e.g. "&.is-open", "& > .icon").', rtrim( $path, '.' ), (string) $state );
				continue;
			}
			if ( ! is_array( $values ) ) {
				continue;
			}
			$clean = Plugin::instance()->controls()->process_settings( $values, $css, $this->mode, $errors, $path . $key . '.' );
			if ( $clean ) {
				$out[ $key ] = $clean;
			}
		}
		return $out;
	}
}

