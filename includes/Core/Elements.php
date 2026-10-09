<?php
/**
 * Element type registry.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Elements\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the container and every widget in includes/Widgets, plus third-party widgets.
 */
final class Elements {

	/** @var array<string, Element_Base> */
	private array $types = array();

	private bool $loaded = false;

	public const CATEGORIES = array(
		'layout'    => 'Layout',
		'basic'     => 'Basic',
		'media'     => 'Media',
		'marketing' => 'Marketing',
		'content'   => 'Content',
		'forms'     => 'Forms',
		'site'      => 'Site & theme',
		'woo'       => 'WooCommerce',
		'other'     => 'Other',
	);

	/**
	 * Elements made one by one by get() before the full registry was needed, by name.
	 *
	 * @var array<string, Element_Base>
	 */
	private array $lazy = array();

	/**
	 * The full registry: the container, every widget, then third-party ones. Needed for lists (the editor's
	 * schema, the Insert panel, AI guides); rendering a page asks get() for the types it uses, which loads only
	 * those, so a page with three widgets does not read eighty widget files (costly without OPcache).
	 */
	private function load(): void {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;
		// Same order as always: elements registered directly by other code first, then the built-ins (reusing any
		// get() already made), then the register action.
		$this->register( $this->lazy['container'] ?? new Container() );
		foreach ( self::files() as $name => $base ) {
			$widget = $this->lazy[ $name ] ?? self::make( $base );
			if ( $widget ) {
				$this->register( $widget );
			}
		}
		$this->lazy = array();

		/**
		 * Register third-party widgets: $elements->register( new My_Widget() ).
		 *
		 * @param Elements $elements Registry.
		 */
		do_action( 'uncoder_wb/widgets/register', $this );
	}

	/**
	 * Built-in widget files by element name: Nav_Menu.php is "nav-menu" (every widget's name() follows its file
	 * name; one that did not would still be found by the full load).
	 *
	 * @return array<string,string> Name => class base name, sorted.
	 */
	private static function files(): array {
		static $files = null;
		if ( null === $files ) {
			$files = array();
			$list  = (array) glob( UNCODER_WB_PATH . 'includes/Widgets/*.php' );
			sort( $list );
			foreach ( $list as $file ) {
				$base                                                  = basename( (string) $file, '.php' );
				$files[ strtolower( str_replace( '_', '-', $base ) ) ] = $base;
			}
		}
		return $files;
	}

	private static function make( string $base ): ?Element_Base {
		$class = 'Uncoder\\Builder\\Widgets\\' . $base;
		if ( ! class_exists( $class ) ) {
			return null;
		}
		$reflection = new \ReflectionClass( $class );
		if ( $reflection->isAbstract() || ! $reflection->isSubclassOf( Element_Base::class ) ) {
			return null;
		}
		$widget = new $class();
		return ! method_exists( $widget, 'is_available' ) || $widget->is_available() ? $widget : null;
	}

	/**
	 * One built-in element without loading the others. Not when other code hooks the register action: it may
	 * replace or remove built-ins, so the full registry decides.
	 */
	private function load_one( string $name ): ?Element_Base {
		if ( has_action( 'uncoder_wb/widgets/register' ) ) {
			return null;
		}
		if ( 'container' === $name ) {
			$this->lazy[ $name ] = new Container();
			return $this->lazy[ $name ];
		}
		$base = self::files()[ $name ] ?? '';
		$el   = '' !== $base ? self::make( $base ) : null;
		if ( ! $el || $el->name() !== $name ) {
			return null;
		}
		$this->lazy[ $name ] = $el;
		return $el;
	}

	public function register( Element_Base $element ): void {
		$this->types[ $element->name() ] = $element;
	}

	public function unregister( string $name ): void {
		$this->load();
		unset( $this->types[ $name ] );
	}

	public function get( string $name ): ?Element_Base {
		if ( ! $this->loaded ) {
			$one = $this->lazy[ $name ] ?? $this->load_one( $name );
			if ( $one ) {
				return $one;
			}
		}
		$this->load();
		return $this->types[ $name ] ?? null;
	}

	public function widget( string $name ): ?Widget_Base {
		$el = $this->get( $name );
		return $el instanceof Widget_Base ? $el : null;
	}

	/**
	 * @return array<string, Element_Base>
	 */
	public function all(): array {
		$this->load();
		return $this->types;
	}

	/**
	 * Full schema for the editor.
	 *
	 * @return array<string, array<string,mixed>>
	 */
	public function schema(): array {
		$out = array();
		foreach ( $this->all() as $name => $element ) {
			$out[ $name ] = $element->schema();
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	public function categories(): array {
		/**
		 * Filters the widget panel categories.
		 *
		 * @param array<string,string> $categories id => label.
		 */
		return apply_filters( 'uncoder_wb/widgets/categories', self::CATEGORIES );
	}
}
