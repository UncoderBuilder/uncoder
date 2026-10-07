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

	private function load(): void {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;
		$this->register( new Container() );

		$files = glob( UNCODER_WB_PATH . 'includes/Widgets/*.php' );
		sort( $files );
		foreach ( (array) $files as $file ) {
			$class = 'Uncoder\\Builder\\Widgets\\' . basename( $file, '.php' );
			if ( class_exists( $class ) ) {
				$reflection = new \ReflectionClass( $class );
				if ( ! $reflection->isAbstract() && $reflection->isSubclassOf( Element_Base::class ) ) {
					$widget = new $class();
					if ( ! method_exists( $widget, 'is_available' ) || $widget->is_available() ) {
						$this->register( $widget );
					}
				}
			}
		}

		/**
		 * Register third-party widgets: $elements->register( new My_Widget() ).
		 *
		 * @param Elements $elements Registry.
		 */
		do_action( 'uncoder_wb/widgets/register', $this );
	}

	public function register( Element_Base $element ): void {
		$this->types[ $element->name() ] = $element;
	}

	public function unregister( string $name ): void {
		$this->load();
		unset( $this->types[ $name ] );
	}

	public function get( string $name ): ?Element_Base {
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
