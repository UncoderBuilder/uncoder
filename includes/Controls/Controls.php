<?php
/**
 * Control type registry and settings processor.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls;

use Uncoder\Builder\Core\Breakpoints;

defined( 'ABSPATH' ) || exit;

/**
 * Holds every control type and validates settings maps against control definitions.
 */
final class Controls {

	/** @var array<string, Control_Type> */
	private array $types = array();

	public function __construct() {
		$types = array(
			new Types\Text( 'text' ),
			new Types\Text( 'textarea' ),
			new Types\Text( 'hidden' ),
			new Types\Text( 'date_time' ),
			new Types\Wysiwyg(),
			new Types\Code(),
			new Types\Number(),
			new Types\Slider(),
			new Types\Dimensions(),
			new Types\Choice( 'select' ),
			new Types\Choice( 'choose' ),
			new Types\Multi(),
			new Types\Toggle(),
			new Types\Color(),
			new Types\Font(),
			new Types\Media(),
			new Types\Gallery(),
			new Types\Icon(),
			new Types\Link(),
			new Types\Repeater(),
			new Types\Overrides(),
			new Types\Animation(),
			new Types\Conditions(),
			new Types\Interactions(),
			new Types\Ui( 'heading' ),
			new Types\Ui( 'divider' ),
			new Types\Ui( 'notice' ),
			new Groups\Typography(),
			new Groups\Background(),
			new Groups\Border(),
			new Groups\Shadow( 'box_shadow' ),
			new Groups\Shadow( 'text_shadow' ),
			new Groups\Filters(),
			new Groups\Transform(),
			new Groups\Query(),
			new Groups\Backdrop_Filters(),
		);
		foreach ( $types as $type ) {
			$this->types[ $type->name() ] = $type;
		}
		// Aliases.
		$this->types['link']   = $this->types['url'];
		$this->types['select2'] = $this->types['multiselect'];

		/**
		 * Register custom control types.
		 *
		 * @param Controls $registry Registry.
		 */
		do_action( 'uncoder_wb/controls/register', $this );
	}

	public function register( Control_Type $type ): void {
		$this->types[ $type->name() ] = $type;
	}

	public function get( string $name ): ?Control_Type {
		return $this->types[ $name ] ?? null;
	}

	/**
	 * @return array<string, Control_Type>
	 */
	public function all(): array {
		return $this->types;
	}

	/**
	 * Sanitizes (strict) or normalizes (lenient) a settings map against control definitions.
	 *
	 * @param array<string,mixed>                $settings Raw settings.
	 * @param array<string, array<string,mixed>> $controls Control definitions keyed by setting key.
	 * @param string                             $mode     "sanitize" | "normalize".
	 * @param string[]                           $errors   Collected problems (normalize mode is descriptive).
	 * @param string                             $path     Prefix for error messages.
	 * @return array<string,mixed>
	 */
	public function process_settings( array $settings, array $controls, string $mode, array &$errors, string $path ): array {
		$out = array();
		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) || '_id' === $key ) {
				continue;
			}
			$control = $controls[ $key ] ?? null;
			if ( null === $control ) {
				list( $base, $device ) = Breakpoints::split_key( $key );
				if ( 'desktop' !== $device && isset( $controls[ $base ] ) ) {
					$control = $controls[ $base ];
					if ( empty( $control['responsive'] ) ) {
						// Groups (border, typography…) keep their per-device values inside the group object.
						$group  = $this->get( (string) ( $control['type'] ?? '' ) );
						$fields = $group && method_exists( $group, 'is_group' ) && $group->is_group() ? $group->fields( $control ) : array();
						$inner  = array_keys( array_filter( $fields, static fn( $f ) => ! empty( $f['responsive'] ) ) );
						$errors[] = $inner
							? sprintf( '"%s%s": "%s" is a group; put per-device values inside it, e.g. "%s": {"%s": …, "%s_%s": …}.', $path, $key, $base, $base, $inner[0], $inner[0], $device )
							: sprintf( '"%s%s": "%s" is not responsive; set "%s" instead.', $path, $key, $base, $base );
						continue;
					}
				}
			}
			if ( null === $control ) {
				$errors[] = sprintf( 'Unknown setting "%s%s".%s', $path, $key, $this->suggest( $key, array_keys( $controls ) ) );
				continue;
			}
			$type = $this->get( (string) ( $control['type'] ?? '' ) );
			if ( null === $type || ! $type->has_value() ) {
				continue;
			}
			$clean = 'normalize' === $mode ? $type->normalize( $value, $control ) : $type->sanitize( $value, $control );
			if ( null === $clean ) {
				$problems = $type->validate( $value, $control );
				$errors[] = sprintf(
					'"%s%s": %s Expected %s.',
					$path,
					$key,
					$problems ? implode( ' ', $problems ) : 'invalid value.',
					$type->value_hint( $control )
				);
				continue;
			}
			if ( 'normalize' === $mode ) {
				foreach ( $type->validate( $value, $control ) as $problem ) {
					if ( ! $type->is_group() && 'repeater' !== $type->name() ) {
						$errors[] = sprintf( '"%s%s": %s', $path, $key, $problem );
					}
				}
				if ( $type->is_group() || 'repeater' === $type->name() ) {
					$sub = array();
					if ( $type->is_group() && $type instanceof Group_Type && is_array( $value ) ) {
						$this->process_settings( $value, $type->fields( $control ), 'normalize', $sub, $path . $key . '.' );
					} elseif ( 'repeater' === $type->name() && is_array( $value ) ) {
						foreach ( array_values( $value ) as $i => $row ) {
							if ( is_array( $row ) ) {
								$this->process_settings( $row, $control['fields'] ?? array(), 'normalize', $sub, $path . $key . '[' . $i . '].' );
							}
						}
					}
					$errors = array_merge( $errors, $sub );
				}
			}
			$out[ $key ] = $clean;
		}
		return $out;
	}

	private function suggest( string $key, array $candidates ): string {
		$best  = '';
		$score = PHP_INT_MAX;
		foreach ( $candidates as $candidate ) {
			$d = levenshtein( $key, (string) $candidate );
			if ( $d < $score ) {
				$score = $d;
				$best  = (string) $candidate;
			}
		}
		return ( '' !== $best && $score <= max( 2, (int) floor( strlen( $key ) / 3 ) ) ) ? sprintf( ' Did you mean "%s"?', $best ) : '';
	}

	/**
	 * Control definition as sent to the editor / AI clients.
	 *
	 * @param array<string,mixed> $control Definition.
	 * @return array<string,mixed>
	 */
	public function export_control( array $control ): array {
		$type = $this->get( (string) ( $control['type'] ?? '' ) );
		if ( $type ) {
			$control = $type->export( $control );
		}
		foreach ( $control as $k => $v ) {
			if ( $v instanceof \Closure || ( is_array( $v ) && ! in_array( $k, array( 'options', 'selectors', 'fields', 'default', 'targets' ), true ) && is_callable( $v ) ) ) {
				unset( $control[ $k ] );
			}
		}
		return $control;
	}

	public function value_hint( array $control ): string {
		$type = $this->get( (string) ( $control['type'] ?? '' ) );
		return $type ? $type->value_hint( $control ) : 'unknown';
	}
}
