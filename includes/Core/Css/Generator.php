<?php
/**
 * Turns element settings into CSS using the declarative `selectors` of each control.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core\Css;

use Uncoder\Builder\Controls\Types\Dimensions;
use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Mirrors src/shared/css/generator.ts — keep both in sync (fixtures in tests/css-fixtures).
 */
final class Generator {

	/** Bump when the generated CSS changes for the same settings: stored page and kit CSS then rebuild. */
	public const REVISION = '6';

	private const LONGHAND = array(
		'padding'       => array( 'padding-top', 'padding-right', 'padding-bottom', 'padding-left' ),
		'margin'        => array( 'margin-top', 'margin-right', 'margin-bottom', 'margin-left' ),
		'border-width'  => array( 'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width' ),
		'border-radius' => array( 'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius' ),
		'inset'         => array( 'top', 'right', 'bottom', 'left' ),
	);

	private int $doc_id;

	/** @var array<string, array<string,bool>> family => weights */
	private array $fonts = array();

	public function __construct( int $doc_id ) {
		$this->doc_id = $doc_id;
	}

	/**
	 * @param array<int, array<string,mixed>> $elements Tree.
	 * @return array{css:string,fonts:array<string,string[]>}
	 */
	public function document( array $elements ): array {
		$rules = new Rules();
		foreach ( $elements as $element ) {
			$this->element( $element, $rules );
		}
		$fonts = array();
		foreach ( $this->fonts as $family => $weights ) {
			$fonts[ $family ] = array_keys( $weights );
		}
		return array(
			'css'   => $rules->render(),
			'fonts' => $fonts,
		);
	}

	/**
	 * @param array<string,mixed> $element Element node.
	 */
	public function element( array $element, Rules $rules ): void {
		$type = Plugin::instance()->elements()->get( (string) ( $element['type'] ?? '' ) );
		$id   = (string) ( $element['id'] ?? '' );
		if ( $type && Utils::is_valid_id( $id ) ) {
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			$wrapper  = Utils::element_selector( $id );
			$this->settings_css( $settings, $type->get_controls(), $wrapper, $rules );
			$this->states_css( $settings, $type->get_controls(), $wrapper, $rules );
			if ( 'container' === $type->name() ) {
				self::stack_css( $settings, $wrapper, $rules );
			}

			self::custom_css( $settings, '_custom_css', $wrapper, $rules );
		}
		foreach ( (array) ( $element['children'] ?? array() ) as $child ) {
			if ( is_array( $child ) ) {
				$this->element( $child, $rules );
			}
		}
	}

	/**
	 * A row that stacks at a breakpoint (direction "row" on desktop, "column" on tablet/mobile) gives its
	 * child containers the full width there, so desktop column widths (50%…) do not squeeze stacked
	 * content. Zero-specificity child selectors keep a child's own width for that device in charge
	 * (its rule comes later in the same media block). Twin of stackCss() in src/shared/css.ts.
	 *
	 * @param array<string,mixed> $settings Container settings.
	 */
	public static function stack_css( array $settings, string $wrapper, Rules $rules ): void {
		if ( 'grid' === ( $settings['layout'] ?? 'flex' ) || ! in_array( $settings['direction'] ?? 'column', array( 'row', 'row-reverse' ), true ) ) {
			return;
		}
		foreach ( Breakpoints::devices() as $device ) {
			if ( 'desktop' === $device ) {
				continue;
			}
			$dir = $settings[ 'direction' . Breakpoints::suffix( $device ) ] ?? '';
			if ( 'column' === $dir || 'column-reverse' === $dir ) {
				$rules->add( $wrapper . ' > :where(.uncoder-container),' . $wrapper . ' > :where(.uncoder-container__inner) > :where(.uncoder-container)', array( '--uncoder-width:var(--uncoder-full,100%)' ), $device );
			}
		}
	}

	/**
	 * Custom CSS per device ($key, $key_tablet…): desktop unwrapped, each breakpoint in its media
	 * query (same desktop-first cascade as responsive controls). "selector" becomes $wrapper.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	public static function custom_css( array $settings, string $key, string $wrapper, Rules $rules ): void {
		foreach ( Breakpoints::devices() as $device ) {
			$custom = $settings[ $key . Breakpoints::suffix( $device ) ] ?? '';
			if ( is_string( $custom ) && '' !== trim( $custom ) ) {
				$rules->add_raw( str_replace( 'selector', $wrapper, Utils::sanitize_custom_css( $custom ) ), $device );
			}
		}
	}

	/** Built-in states of the inspector's state switch; any other key is a custom selector around "&". */
	public const STATE_KEYS = array( 'hover', 'focus', 'active', 'before', 'after' );

	/**
	 * A state key as stored (twin of cleanState() in src/shared/css.ts): a built-in state, or a custom
	 * selector containing "&" (the element itself). '' when invalid.
	 */
	public static function clean_state( string $state ): string {
		if ( in_array( $state, self::STATE_KEYS, true ) ) {
			return $state;
		}
		$v = trim( (string) preg_replace( '/\s+/', ' ', (string) preg_replace( '/[{}<;@\\\\]/', '', $state ) ) );
		$v = substr( $v, 0, 120 );
		return false !== strpos( $v, '&' ) ? $v : '';
	}

	/**
	 * Selector of an element in a state (twin of stateSelector()).
	 */
	public static function state_selector( string $wrapper, string $state ): string {
		switch ( $state ) {
			case 'hover':
				return $wrapper . ':hover';
			case 'focus':
				return $wrapper . ':is(:focus-visible,:has(:focus-visible))';
			case 'active':
				return $wrapper . ':active';
			case 'before':
				return $wrapper . '::before';
			case 'after':
				return $wrapper . '::after';
		}
		$custom = self::clean_state( $state );
		return '' !== $custom ? str_replace( '&', $wrapper, $custom ) : '';
	}

	/**
	 * Pseudo-elements and custom selectors pointing elsewhere (`& > .icon`) style with the element-level
	 * controls only; hover / focus / active and same-element selectors (`&.is-open`) use all of them.
	 */
	public static function state_is_self_only( string $state ): bool {
		if ( 'before' === $state || 'after' === $state ) {
			return true;
		}
		if ( in_array( $state, self::STATE_KEYS, true ) ) {
			return false;
		}
		return ! preg_match( '/^&[.:\[]/', self::clean_state( $state ) );
	}

	/**
	 * Controls reduced to their `{{WRAPPER}}` rules (twin of selfControls()).
	 *
	 * @param array<string, array<string,mixed>> $controls Control definitions.
	 * @return array<string, array<string,mixed>>
	 */
	public static function self_controls( array $controls ): array {
		$registry = Plugin::instance()->controls();
		$out      = array();
		foreach ( $controls as $key => $control ) {
			$ctype = $registry->get( (string) ( $control['type'] ?? '' ) );
			if ( $ctype && $ctype->is_group() ) {
				if ( '{{WRAPPER}}' === trim( (string) ( $control['selector'] ?? '' ) ) ) {
					$out[ $key ] = $control;
				}
				continue;
			}
			if ( empty( $control['selectors'] ) || ! is_array( $control['selectors'] ) ) {
				continue;
			}
			$own = array_filter( $control['selectors'], static fn( $tpl ) => '{{WRAPPER}}' === trim( (string) $tpl ), ARRAY_FILTER_USE_KEY );
			if ( $own ) {
				$out[ $key ] = array_merge( $control, array( 'selectors' => $own ) );
			}
		}
		return $out;
	}

	/**
	 * Each state's values under its selector (`_states` = { state: settings }; twin of statesCss()).
	 *
	 * @param array<string,mixed>                $settings Element settings.
	 * @param array<string, array<string,mixed>> $controls Control definitions.
	 */
	public function states_css( array $settings, array $controls, string $wrapper, Rules $rules ): void {
		$states = $settings['_states'] ?? null;
		if ( ! is_array( $states ) ) {
			return;
		}
		foreach ( $states as $state => $values ) {
			if ( ! is_string( $state ) || ! is_array( $values ) || ! $values ) {
				continue;
			}
			$selector = self::state_selector( $wrapper, $state );
			if ( '' === $selector ) {
				continue;
			}
			if ( 'before' === $state || 'after' === $state ) {
				$rules->add( $selector, array( 'content:""' ), 'desktop' );
			}
			$this->settings_css( $values, self::state_is_self_only( $state ) ? self::self_controls( $controls ) : $controls, $selector, $rules, '', array_merge( $settings, $values ) );
		}
	}

	/**
	 * CSS for one settings map (element or repeater row).
	 *
	 * @param array<string,mixed>                $settings Settings.
	 * @param array<string, array<string,mixed>> $controls Control definitions.
	 * @param array<string,mixed>|null           $context  For a state: its values are printed alone, but
	 *                                                     conditions and references read the element too.
	 */
	public function settings_css( array $settings, array $controls, string $wrapper, Rules $rules, string $current_item = '', ?array $context = null ): void {
		$registry = Plugin::instance()->controls();
		$ctx      = $context ?? $settings;

		foreach ( $controls as $key => $control ) {
			$ctype = $registry->get( (string) ( $control['type'] ?? '' ) );
			if ( null === $ctype || ! $ctype->has_value() ) {
				continue;
			}
			if ( ! self::conditions_met( $control, $ctx, $controls ) ) {
				continue;
			}

			if ( $ctype->is_group() ) {
				if ( empty( $control['selector'] ) ) {
					continue;
				}
				$value = $settings[ $key ] ?? ( ! empty( $control['css_default'] ) && null === $context ? ( $control['default'] ?? null ) : null );
				if ( ! is_array( $value ) || ! $value ) {
					continue;
				}
				$selector = $this->selector( (string) $control['selector'], $wrapper, $current_item );
				$ctype->group_css( $value, $control, $selector, $rules );
				if ( 'typography' === $ctype->name() ) {
					$this->collect_fonts( $value );
				}
				continue;
			}

			if ( 'repeater' === $ctype->name() ) {
				foreach ( (array) ( $settings[ $key ] ?? array() ) as $row ) {
					if ( is_array( $row ) && ! empty( $row['_id'] ) ) {
						$this->settings_css( $row, $control['fields'] ?? array(), $wrapper, $rules, '.uncoder-ri-' . sanitize_html_class( (string) $row['_id'] ) );
					}
				}
				continue;
			}

			if ( empty( $control['selectors'] ) || ! is_array( $control['selectors'] ) ) {
				continue;
			}

			$devices = ! empty( $control['responsive'] ) ? Breakpoints::devices() : array( 'desktop' );
			foreach ( $devices as $device ) {
				$full = $key . Breakpoints::suffix( $device );
				if ( array_key_exists( $full, $settings ) ) {
					$value = $settings[ $full ];
				} elseif ( 'desktop' === $device && ! empty( $control['css_default'] ) && isset( $control['default'] ) && null === $context ) {
					$value = $control['default'];
				} else {
					continue;
				}
				$ph = $ctype->placeholders( $value, $control );
				if ( null === $ph ) {
					continue;
				}
				if ( isset( $control['selectors_dictionary'] ) && is_scalar( $value ) && isset( $control['selectors_dictionary'][ (string) $value ] ) ) {
					// Dictionary values are written by developers (trusted) and may hold several declarations.
					$ph['VALUE'] = (string) $control['selectors_dictionary'][ (string) $value ];
					if ( '' === $ph['VALUE'] ) {
						continue;
					}
				}
				if ( 'font' === $ctype->name() && is_string( $value ) ) {
					$this->fonts[ $value ] = $this->fonts[ $value ] ?? array();
				}
				foreach ( $control['selectors'] as $selector_tpl => $decl_tpl ) {
					$selector = $this->selector( (string) $selector_tpl, $wrapper, $current_item );
					$decls    = $this->declarations( (string) $decl_tpl, $ph, $ctype->name(), $ctx, $controls, $device );
					if ( $decls ) {
						$rules->add( $selector, $decls, $device );
					}
				}
			}
		}
	}

	private function selector( string $tpl, string $wrapper, string $current_item ): string {
		$tpl = str_replace( '{{CURRENT_ITEM}}', $current_item, $tpl );
		return str_replace( '{{WRAPPER}}', $wrapper, $tpl );
	}

	/**
	 * Fills a declaration template ("color: {{VALUE}}; …") with placeholders.
	 *
	 * @param array<string,string> $ph Placeholders.
	 * @return string[]
	 */
	private function declarations( string $tpl, array $ph, string $type, array $settings, array $controls, string $device ): array {
		$out = array();
		foreach ( explode( ';', $tpl ) as $decl ) {
			$decl = trim( $decl );
			if ( '' === $decl ) {
				continue;
			}
			// Dimensions shorthand → per-side longhands so unset sides stay untouched.
			if ( 'dimensions' === $type && preg_match( '/^([a-z\-]+)\s*:\s*\{\{VALUE\}\}(\s*!important)?$/i', $decl, $m ) && isset( self::LONGHAND[ strtolower( $m[1] ) ] ) ) {
				$important = isset( $m[2] ) && '' !== $m[2] ? ' !important' : '';
				foreach ( Dimensions::SIDES as $i => $side ) {
					$v = $ph[ strtoupper( $side ) ] ?? '';
					if ( '' === $v ) {
						continue;
					}
					$out[] = self::LONGHAND[ strtolower( $m[1] ) ][ $i ] . ':' . ( 'auto' === $v || \Uncoder\Builder\Controls\Types\Dimensions::is_var( $v ) ? $v : $v . ( '0' === $v ? '' : $ph['UNIT'] ) ) . $important;
				}
				continue;
			}
			$missing = false;
			$filled  = preg_replace_callback(
				'/\{\{([A-Za-z0-9_]+\.)?([A-Z]+)\}\}/',
				function ( $m ) use ( $ph, $settings, $controls, $device, &$missing ) {
					if ( '' !== $m[1] ) {
						$ref = $this->reference( rtrim( $m[1], '.' ), $m[2], $settings, $controls, $device );
						if ( null === $ref || '' === $ref ) {
							$missing = true;
							return '';
						}
						return $ref;
					}
					if ( ! isset( $ph[ $m[2] ] ) || ( '' === $ph[ $m[2] ] && 'UNIT' !== $m[2] ) ) {
						$missing = true;
						return '';
					}
					return $ph[ $m[2] ];
				},
				$decl
			);
			if ( ! $missing && null !== $filled ) {
				$out[] = $filled;
			}
		}
		return $out;
	}

	/**
	 * Resolves {{other_control.VALUE}} references.
	 */
	private function reference( string $key, string $placeholder, array $settings, array $controls, string $device ): ?string {
		if ( ! isset( $controls[ $key ] ) ) {
			return null;
		}
		$control = $controls[ $key ];
		$ctype   = Plugin::instance()->controls()->get( (string) ( $control['type'] ?? '' ) );
		if ( ! $ctype ) {
			return null;
		}
		$full  = $key . Breakpoints::suffix( $device );
		$value = $settings[ $full ] ?? $settings[ $key ] ?? ( $control['default'] ?? null );
		if ( null === $value ) {
			return null;
		}
		$ph = $ctype->placeholders( $value, $control );
		return $ph[ $placeholder ] ?? null;
	}

	/**
	 * Evaluates a control's `condition` against settings (and defaults).
	 *
	 * Syntax: [ 'key' => 'value' | ['a','b'], 'key!' => 'value', 'group.sub' => 'value' ].
	 *
	 * @param array<string,mixed> $control  Control.
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $controls All controls (for defaults).
	 */
	public static function conditions_met( array $control, array $settings, array $controls ): bool {
		if ( empty( $control['condition'] ) || ! is_array( $control['condition'] ) ) {
			return true;
		}
		foreach ( $control['condition'] as $key => $expected ) {
			$negate = '!' === substr( (string) $key, -1 );
			$key    = rtrim( (string) $key, '!' );
			$parts  = explode( '.', $key, 2 );
			$root   = $parts[0];
			$actual = array_key_exists( $root, $settings ) ? $settings[ $root ] : ( $controls[ $root ]['default'] ?? '' );
			if ( isset( $parts[1] ) ) {
				$actual = is_array( $actual ) ? ( $actual[ $parts[1] ] ?? '' ) : '';
			}
			if ( is_bool( $actual ) && is_string( $expected ) ) {
				$actual = $actual ? 'yes' : '';
			}
			$match = is_array( $expected ) ? in_array( $actual, $expected, true ) : ( $actual === $expected || ( is_scalar( $actual ) && (string) $actual === (string) $expected ) );
			if ( $match === $negate ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $typography Typography value.
	 */
	private function collect_fonts( array $typography ): void {
		foreach ( $typography as $k => $v ) {
			if ( 0 === strpos( (string) $k, 'family' ) && is_string( $v ) && '' !== $v && 0 !== strpos( $v, 'var(' ) ) {
				$weight                         = isset( $typography['weight'] ) && '' !== $typography['weight'] ? (string) $typography['weight'] : '400';
				$weight                         = 'bold' === $weight ? '700' : ( 'normal' === $weight ? '400' : $weight );
				$this->fonts[ $v ][ $weight ]   = true;
				$this->fonts[ $v ]['400']       = true;
			}
		}
	}
}
