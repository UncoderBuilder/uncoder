<?php
/**
 * Element display conditions: who sees an element, when, and on which URLs.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Document;

defined( 'ABSPATH' ) || exit;

/**
 * The first, flat version of element display rules: _show_to ("logged_in" | "logged_out" | "roles" +
 * _show_roles), _show_from / _show_until, _show_days and _show_param. Replaced by Behaviour → Conditions
 * (Core\Element_Conditions, which converts these on save and in the 1.4.0 migration); this filter still
 * honours them for anything not converted yet, such as an old revision shown in a preview.
 */
final class Display_Conditions {

	public const KEYS = array( '_show_to', '_show_roles', '_show_from', '_show_until', '_show_days', '_show_param' );

	public const DAYS = array(
		'mon' => 'Monday',
		'tue' => 'Tuesday',
		'wed' => 'Wednesday',
		'thu' => 'Thursday',
		'fri' => 'Friday',
		'sat' => 'Saturday',
		'sun' => 'Sunday',
	);

	public function register(): void {
		add_filter( 'uncoder_wb/render/should_render', array( $this, 'filter' ), 10, 2 );
	}

	/**
	 * @param bool                $render  Current decision.
	 * @param array<string,mixed> $element Node.
	 */
	public function filter( $render, $element ): bool {
		if ( ! $render || ! is_array( $element ) ) {
			return (bool) $render;
		}
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		return self::passes( $settings );
	}

	public static function has_rules( array $s ): bool {
		foreach ( self::KEYS as $key ) {
			if ( '_show_roles' !== $key && ! empty( $s[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $s Element settings.
	 */
	public static function passes( array $s ): bool {
		if ( ! self::has_rules( $s ) ) {
			return true;
		}

		$to = (string) ( $s['_show_to'] ?? '' );
		if ( 'logged_in' === $to && ! is_user_logged_in() ) {
			return false;
		}
		if ( 'logged_out' === $to && is_user_logged_in() ) {
			return false;
		}
		if ( 'roles' === $to ) {
			$user = wp_get_current_user();
			if ( ! $user->exists() || ! array_intersect( (array) ( $s['_show_roles'] ?? array() ), (array) $user->roles ) ) {
				return false;
			}
		}

		$now = current_datetime();
		$from = self::time( $s['_show_from'] ?? '' );
		if ( $from && $now < $from ) {
			return false;
		}
		$until = self::time( $s['_show_until'] ?? '' );
		if ( $until && $now >= $until ) {
			return false;
		}
		$days = array_intersect( (array) ( $s['_show_days'] ?? array() ), array_keys( self::DAYS ) );
		if ( $days && ! in_array( strtolower( $now->format( 'D' ) ), $days, true ) ) {
			return false;
		}

		$param = trim( (string) ( $s['_show_param'] ?? '' ) );
		if ( '' !== $param ) {
			// The public HTML copy (post_content) is rendered without a request: URL rules never match.
			if ( Document::$static_render ) {
				return false;
			}
			$parts    = explode( '=', $param, 2 );
			$key      = trim( $parts[0] );
			$expected = isset( $parts[1] ) ? trim( $parts[1] ) : null;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only comparison.
			$actual = isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) : null;
			if ( '' === $key || null === $actual || ( null !== $expected && '' !== $expected && $actual !== $expected ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A date-time in the site's time zone ("2026-10-01 09:00" or "2026-10-01T09:00").
	 *
	 * @param mixed $value Raw.
	 */
	private static function time( $value ): ?\DateTimeImmutable {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}
		$time = date_create_immutable( trim( $value ), wp_timezone() );
		return $time ? $time : null;
	}
}
