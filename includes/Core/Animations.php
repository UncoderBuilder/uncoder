<?php
/**
 * Timeline animations (Behaviour → Animations): sanitizing, presets and the front-end attribute.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A setting `_animations` holds a list of definitions (format documented in src/shared/anim.ts, which
 * compiles them; ranges here must match its PROPS):
 *
 *   { "trigger": "enter", "target": "words", "from": { "opacity": 0, "y": 24 },
 *     "steps": [ { "to": { "opacity": 1, "y": 0 }, "duration": 700, "ease": "power3.out" } ], "stagger": 60 }
 *
 * Presets live in assets/data/animations.json (shared with the editor). AI clients may send a preset
 * name ("fade-up"), a list of names, or { "preset": "pop", "delay": 200 }; normalize expands them.
 */
final class Animations {

	public const TRIGGERS      = array( 'load', 'enter', 'scroll', 'hover', 'click', 'loop' );
	public const TARGETS       = array( 'self', 'children', 'words', 'chars', 'lines' );
	public const SPLIT_TARGETS = array( 'words', 'chars', 'lines' );
	public const ORDERS        = array( 'start', 'end', 'center', 'edges', 'random' );
	public const REPLAYS       = array( 'once', 'every', 'reverse' );
	public const CLIPS         = array( 'none', 'wipe-up', 'wipe-down', 'wipe-left', 'wipe-right', 'center-x', 'center-y', 'circle' );
	public const DEVICES       = array( 'desktop', 'tablet', 'mobile' );
	public const EASE_FAMILIES = array( 'power1', 'power2', 'power3', 'power4', 'sine', 'expo', 'circ', 'back', 'elastic', 'bounce' );
	public const DEFAULT_EASE  = 'power3.out';

	/** [ natural, min, max ] per numeric property. */
	public const PROPS = array(
		'opacity' => array( 1, 0, 1 ),
		'x'       => array( 0, -3000, 3000 ),
		'y'       => array( 0, -3000, 3000 ),
		'scale'   => array( 1, 0, 10 ),
		'scaleX'  => array( 1, -10, 10 ),
		'scaleY'  => array( 1, -10, 10 ),
		'rotate'  => array( 0, -3600, 3600 ),
		'rotateX' => array( 0, -3600, 3600 ),
		'rotateY' => array( 0, -3600, 3600 ),
		'skewX'   => array( 0, -80, 80 ),
		'skewY'   => array( 0, -80, 80 ),
		'blur'    => array( 0, 0, 100 ),
	);

	private const MAX_DEFS  = 8;
	private const MAX_STEPS = 12;
	private const MAX_MS    = 20000;

	private const TRIGGER_ALIASES = array(
		'viewport'         => 'enter',
		'in-view'          => 'enter',
		'inview'           => 'enter',
		'appear'           => 'enter',
		'scroll-into-view' => 'enter',
		'entrance'         => 'enter',
		'page-load'        => 'load',
		'pageload'         => 'load',
		'onload'           => 'load',
		'scrub'            => 'scroll',
		'scroll-scrub'     => 'scroll',
		'mouseenter'       => 'hover',
		'mouseover'        => 'hover',
		'tap'              => 'click',
		'infinite'         => 'loop',
		'repeat'           => 'loop',
	);

	private const TARGET_ALIASES = array(
		'element'  => 'self',
		'letters'  => 'chars',
		'letter'   => 'chars',
		'word'     => 'words',
		'line'     => 'lines',
		'items'    => 'children',
		'child'    => 'children',
		'elements' => 'children',
	);

	private const EASE_ALIASES = array(
		'linear'      => 'none',
		'ease'        => 'power1.out',
		'ease-in'     => 'power1.in',
		'ease-out'    => 'power1.out',
		'ease-in-out' => 'power1.inOut',
	);

	/** @var array<string,mixed>|null */
	private static ?array $data = null;

	/**
	 * @return array{groups: array<string,string>, presets: array<string, array<string,mixed>>}
	 */
	private static function data(): array {
		if ( null === self::$data ) {
			$file       = UNCODER_WB_PATH . 'assets/data/animations.json';
			$json       = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			self::$data = array(
				'groups'  => is_array( $json['groups'] ?? null ) ? $json['groups'] : array(),
				'presets' => is_array( $json['presets'] ?? null ) ? $json['presets'] : array(),
			);
		}
		return self::$data;
	}

	/**
	 * @return array<string, array<string,mixed>> Preset id => { label, group, def }.
	 */
	public static function presets(): array {
		return self::data()['presets'];
	}

	/**
	 * Preset ids by group label, for docs and error messages.
	 *
	 * @return array<string, string[]>
	 */
	public static function preset_index(): array {
		$out = array();
		foreach ( self::presets() as $id => $preset ) {
			$group           = self::data()['groups'][ $preset['group'] ?? '' ] ?? 'Other';
			$out[ $group ][] = $id;
		}
		return $out;
	}

	/**
	 * Cleans a list of definitions. Lenient mode also takes preset names, a single object and aliases.
	 *
	 * @param mixed    $value   Raw value.
	 * @param bool     $lenient Normalize (AI / import) instead of strict sanitizing.
	 * @param string[] $targets Targets this element supports (split targets only on text widgets).
	 * @param string[] $errors  Problems found (lenient mode).
	 * @return array<int, array<string,mixed>>|null Null when the value is not a list at all.
	 */
	public static function sanitize_list( $value, bool $lenient = false, array $targets = self::TARGETS, array &$errors = array() ): ?array {
		if ( null === $value || '' === $value || array() === $value ) {
			return array();
		}
		if ( $lenient ) {
			if ( is_string( $value ) ) {
				$value = array_map( static fn( $p ) => array( 'preset' => trim( $p ) ), array_filter( explode( ',', $value ), 'strlen' ) );
			} elseif ( is_array( $value ) && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
				$value = array( $value );
			}
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( array_slice( array_values( $value ), 0, self::MAX_DEFS ) as $i => $def ) {
			if ( $lenient && is_string( $def ) ) {
				$def = array( 'preset' => $def );
			}
			if ( ! is_array( $def ) ) {
				$errors[] = sprintf( 'Animation %d must be an object or a preset name.', $i + 1 );
				continue;
			}
			$clean = self::sanitize_def( $def, $lenient, $targets, $errors, $i + 1 );
			if ( null !== $clean ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $def     Raw definition.
	 * @param bool                $lenient Normalize mode.
	 * @param string[]            $targets Supported targets.
	 * @param string[]            $errors  Problems found.
	 * @param int                 $n       Position (for messages).
	 * @return array<string,mixed>|null
	 */
	private static function sanitize_def( array $def, bool $lenient, array $targets, array &$errors, int $n ): ?array {
		$presets = self::presets();
		$preset  = sanitize_key( (string) ( $def['preset'] ?? '' ) );
		if ( '' !== $preset && ! isset( $presets[ $preset ] ) ) {
			if ( $lenient ) {
				$errors[] = sprintf( 'Animation %d: unknown preset "%s". Presets: %s.', $n, $preset, implode( ', ', array_keys( $presets ) ) );
			}
			$preset = '';
		}
		if ( $lenient && '' !== $preset && empty( $def['steps'] ) ) {
			// Settings sent with a preset override it; "duration" stretches its steps to that total.
			$duration = $def['duration'] ?? null;
			unset( $def['duration'] );
			$def = array_merge( (array) $presets[ $preset ]['def'], $def );
			if ( is_numeric( $duration ) && ! empty( $def['steps'] ) ) {
				$total = array_sum( array_map( static fn( $s ) => (float) ( $s['duration'] ?? 0 ), $def['steps'] ) );
				if ( $total > 0 ) {
					foreach ( $def['steps'] as &$step ) {
						$step['duration'] = (float) ( $step['duration'] ?? 0 ) * (float) $duration / $total;
					}
					unset( $step );
				}
			}
		}

		$trigger = strtolower( (string) ( $def['trigger'] ?? 'enter' ) );
		if ( $lenient ) {
			$trigger = self::TRIGGER_ALIASES[ $trigger ] ?? $trigger;
		}
		if ( ! in_array( $trigger, self::TRIGGERS, true ) ) {
			if ( $lenient ) {
				$errors[] = sprintf( 'Animation %d: trigger must be one of %s.', $n, implode( ', ', self::TRIGGERS ) );
			}
			$trigger = 'enter';
		}
		$target = strtolower( (string) ( $def['target'] ?? 'self' ) );
		if ( $lenient ) {
			$target = self::TARGET_ALIASES[ $target ] ?? $target;
		}
		if ( ! in_array( $target, self::TARGETS, true ) || ! in_array( $target, $targets, true ) ) {
			if ( $lenient && 'self' !== $target ) {
				$errors[] = sprintf( 'Animation %d: target "%s" is not available here (words / chars / lines need a text widget). Allowed: %s.', $n, $target, implode( ', ', $targets ) );
			}
			$target = 'self';
		}

		$steps = array();
		foreach ( array_slice( is_array( $def['steps'] ?? null ) ? array_values( $def['steps'] ) : array(), 0, self::MAX_STEPS ) as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}
			$clean = array(
				'to'       => self::sanitize_state( $step['to'] ?? array(), $lenient ),
				'duration' => self::ms( $step['duration'] ?? 600 ),
				'ease'     => self::sanitize_ease( $step['ease'] ?? self::DEFAULT_EASE, $lenient ),
			);
			$delay = self::ms( $step['delay'] ?? 0 );
			if ( $delay > 0 ) {
				$clean['delay'] = $delay;
			}
			$steps[] = $clean;
		}
		if ( ! $steps ) {
			if ( $lenient ) {
				$errors[] = sprintf( 'Animation %d needs "steps" (e.g. [{"to":{"opacity":1,"y":0},"duration":800,"ease":"power3.out"}]) or a "preset".', $n );
			}
			return null;
		}

		$out = array();
		if ( '' !== $preset ) {
			$out['preset'] = $preset;
		}
		$out['trigger'] = $trigger;
		if ( 'self' !== $target ) {
			$out['target'] = $target;
		}
		if ( ! empty( $def['mask'] ) && in_array( $target, self::SPLIT_TARGETS, true ) ) {
			$out['mask'] = true;
		}
		$from = self::sanitize_state( $def['from'] ?? array(), $lenient );
		if ( $from ) {
			$out['from'] = $from;
		}
		$out['steps'] = $steps;

		if ( 'scroll' !== $trigger && ! empty( $def['delay'] ) ) {
			$out['delay'] = self::ms( $def['delay'] );
		}
		if ( 'self' !== $target ) {
			$stagger = (int) round( self::clamp( $def['stagger'] ?? 0, 0, 5000 ) );
			if ( $stagger > 0 ) {
				$out['stagger'] = $stagger;
			}
			$order = (string) ( $def['order'] ?? 'start' );
			if ( 'start' !== $order && in_array( $order, self::ORDERS, true ) ) {
				$out['order'] = $order;
			}
		}
		if ( in_array( $trigger, array( 'load', 'enter', 'loop' ), true ) ) {
			$repeat = (int) self::clamp( $def['repeat'] ?? 0, -1, 100 );
			if ( 0 !== $repeat && 'loop' !== $trigger ) {
				$out['repeat'] = $repeat;
			}
			if ( ! empty( $def['yoyo'] ) ) {
				$out['yoyo'] = true;
			}
		}
		if ( 'enter' === $trigger ) {
			$replay = (string) ( $def['replay'] ?? 'once' );
			if ( 'once' !== $replay && in_array( $replay, self::REPLAYS, true ) ) {
				$out['replay'] = $replay;
			}
			if ( isset( $def['offset'] ) && is_numeric( $def['offset'] ) ) {
				$out['offset'] = (int) self::clamp( $def['offset'], 0, 50 );
			}
		}
		if ( 'scroll' === $trigger ) {
			$start = (int) self::clamp( $def['start'] ?? 0, 0, 99 );
			$end   = (int) self::clamp( $def['end'] ?? 100, 1, 100 );
			if ( $end <= $start ) {
				$end = min( 100, $start + 1 );
			}
			$out['start']  = $start;
			$out['end']    = $end;
			$smooth        = round( self::clamp( $def['smooth'] ?? 0, 0, 10 ), 2 );
			if ( $smooth > 0 ) {
				$out['smooth'] = $smooth;
			}
		}
		if ( 'click' === $trigger && ! empty( $def['toggle'] ) ) {
			$out['toggle'] = true;
		}
		if ( isset( $def['devices'] ) && is_array( $def['devices'] ) ) {
			$devices = array_values( array_intersect( self::DEVICES, array_map( 'strval', $def['devices'] ) ) );
			if ( $devices && count( $devices ) < count( self::DEVICES ) ) {
				$out['devices'] = $devices;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $state   Raw state.
	 * @param bool  $lenient Normalize mode (numeric strings accepted).
	 * @return array<string,mixed>
	 */
	public static function sanitize_state( $state, bool $lenient = false ): array {
		if ( ! is_array( $state ) ) {
			return array();
		}
		$out = array();
		foreach ( self::PROPS as $prop => $range ) {
			if ( ! array_key_exists( $prop, $state ) ) {
				continue;
			}
			$v = $state[ $prop ];
			if ( in_array( $prop, array( 'x', 'y' ), true ) && is_string( $v ) && preg_match( '/^(-?\d+(?:\.\d+)?)(px|%|em|rem|vw|vh)$/', trim( $v ), $m ) ) {
				$out[ $prop ] = 'px' === $m[2] ? self::number( self::clamp( $m[1], $range[1], $range[2] ) ) : self::number( self::clamp( $m[1], -1000, 1000 ) ) . $m[2];
				continue;
			}
			if ( is_int( $v ) || is_float( $v ) || ( $lenient && is_string( $v ) && is_numeric( trim( $v ) ) ) ) {
				$out[ $prop ] = self::number( self::clamp( $v, $range[1], $range[2] ) );
			}
		}
		if ( isset( $state['clip'] ) && in_array( $state['clip'], self::CLIPS, true ) ) {
			$out['clip'] = $state['clip'];
		}
		return $out;
	}

	/**
	 * @param mixed $ease    Raw ease.
	 * @param bool  $lenient Normalize mode.
	 */
	public static function sanitize_ease( $ease, bool $lenient = false ): string {
		$ease = trim( (string) $ease );
		if ( $lenient ) {
			$ease = self::EASE_ALIASES[ strtolower( $ease ) ] ?? $ease;
			if ( in_array( $ease, self::EASE_FAMILIES, true ) ) {
				$ease .= '.out';
			}
		}
		if ( 'none' === $ease ) {
			return $ease;
		}
		if ( preg_match( '/^cubic-bezier\(\s*-?[\d.]+\s*,\s*-?[\d.]+\s*,\s*-?[\d.]+\s*,\s*-?[\d.]+\s*\)$/', $ease ) ) {
			return $ease;
		}
		$parts = explode( '.', $ease );
		if ( 2 === count( $parts ) && in_array( $parts[0], self::EASE_FAMILIES, true ) && in_array( $parts[1], array( 'in', 'out', 'inOut' ), true ) ) {
			return $ease;
		}
		return self::DEFAULT_EASE;
	}

	/**
	 * Whether an element has to stay hidden until the script has put it in its start state.
	 *
	 * @param array<int, array<string,mixed>> $list Sanitized definitions.
	 */
	public static function starts_hidden( array $list ): bool {
		foreach ( $list as $def ) {
			if ( ! in_array( $def['trigger'] ?? '', array( 'load', 'enter', 'scroll' ), true ) ) {
				continue;
			}
			foreach ( (array) ( $def['from'] ?? array() ) as $prop => $v ) {
				$natural = 'clip' === $prop ? 'none' : ( self::PROPS[ $prop ][0] ?? null );
				if ( is_string( $v ) && 'clip' !== $prop ? 0.0 !== (float) $v : $v != $natural ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- 1 vs 1.0.
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * The definitions as the front end reads them (data-uncoder-animate): editor-only keys removed.
	 *
	 * @param array<int, array<string,mixed>> $list Sanitized definitions.
	 */
	public static function attribute( array $list ): string {
		return (string) wp_json_encode(
			array_map(
				static function ( $def ) {
					unset( $def['preset'] );
					return $def;
				},
				$list
			)
		);
	}

	/** @param mixed $v Raw number. */
	private static function clamp( $v, float $min, float $max ): float {
		return max( $min, min( $max, is_numeric( $v ) ? (float) $v : 0.0 ) );
	}

	/** @param mixed $v Raw milliseconds. */
	private static function ms( $v ): int {
		return (int) round( self::clamp( $v, 0, self::MAX_MS ) );
	}

	/** Whole numbers stay integers in the JSON. */
	private static function number( float $v ) {
		$v = round( $v, 3 );
		return floor( $v ) === $v ? (int) $v : $v;
	}
}
