<?php
/**
 * Spam protection for forms: signed timestamps, honeypot, time trap and rate limit.
 *
 * Pages are usually cached, so WordPress nonces (user + time bound) cannot be the protection.
 * Instead every rendered form carries an HMAC-signed timestamp that binds it to its document and
 * element; the server checks the signature, the minimum fill time, an always-on honeypot field and
 * a per-IP rate limit.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Forms;

use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless helpers; the only state is the rate-limit transient.
 */
final class Security {

	/** Name of the honeypot input (must stay empty). */
	public const HONEYPOT = '_hp';

	/** Seconds a token timestamp may lie in the future (clock skew between servers). */
	private const FUTURE_SKEW = 300;

	/**
	 * Signature over the form identity and a timestamp.
	 */
	public static function sign( int $doc_id, int $post_id, string $element_id, int $ts ): string {
		$payload = implode( '|', array( 'uncoder_wb_form', $doc_id, $post_id, $element_id, $ts ) );
		return hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );
	}

	/**
	 * A fresh timestamp + signature for a form.
	 *
	 * @return array{ts:int, token:string}
	 */
	public static function token( int $doc_id, int $post_id, string $element_id ): array {
		$ts = time();
		return array(
			'ts'    => $ts,
			'token' => self::sign( $doc_id, $post_id, $element_id, $ts ),
		);
	}

	/**
	 * @param mixed $ts    Submitted timestamp.
	 * @param mixed $token Submitted signature.
	 */
	public static function verify( int $doc_id, int $post_id, string $element_id, $ts, $token ): bool {
		if ( ! is_scalar( $ts ) || ! is_string( $token ) || ! ctype_digit( (string) $ts ) || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return false;
		}
		$ts = (int) $ts;
		// Tokens are fetched when a visitor starts filling in the form; a day is plenty.
		if ( $ts <= 0 || $ts > time() + self::FUTURE_SKEW || time() - $ts > DAY_IN_SECONDS ) {
			return false;
		}
		return hash_equals( self::sign( $doc_id, $post_id, $element_id, $ts ), $token );
	}

	/**
	 * Seconds elapsed since the signed timestamp.
	 */
	public static function elapsed( int $ts ): int {
		return time() - $ts;
	}

	/**
	 * Counts one attempt for the visitor's IP and tells whether the limit is exceeded.
	 * Filter `uncoder_wb/forms/rate_limit` → [ 'limit' => 5, 'window' => 60 ] (limit 0 disables).
	 */
	public static function rate_limited(): bool {
		/**
		 * Filters the per-IP submission rate limit.
		 *
		 * @param array{limit:int, window:int} $rule Max attempts per window (seconds).
		 */
		$rule   = (array) apply_filters(
			'uncoder_wb/forms/rate_limit',
			array(
				'limit'  => 5,
				'window' => 60,
			)
		);
		$limit  = max( 0, (int) ( $rule['limit'] ?? 5 ) );
		$window = max( 1, (int) ( $rule['window'] ?? 60 ) );
		if ( 0 === $limit ) {
			return false;
		}
		$ip  = Utils::client_ip();
		$key = 'uncoder_wb_frl_' . substr( hash_hmac( 'sha256', '' !== $ip ? $ip : 'unknown', wp_salt( 'nonce' ) ), 0, 24 );
		$now = time();

		$state = get_transient( $key );
		if ( ! is_array( $state ) || (int) ( $state['reset'] ?? 0 ) <= $now ) {
			$state = array(
				'count' => 0,
				'reset' => $now + $window,
			);
		}
		$state['count'] = (int) $state['count'] + 1;
		set_transient( $key, $state, max( 1, (int) $state['reset'] - $now ) );

		return $state['count'] > $limit;
	}

	/**
	 * The request's Origin header, when present, must be this site (blocks cross-site form posting
	 * from browsers; requests without Origin — old browsers, server-to-server — are allowed).
	 */
	public static function origin_allowed( string $origin ): bool {
		if ( '' === $origin ) {
			return true;
		}
		if ( 'null' === $origin ) {
			return false;
		}
		$host  = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
		$hosts = array_filter(
			array(
				strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
				strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) ),
			)
		);
		/** This filter is documented in wp-includes/pluggable.php */
		$hosts = array_merge( $hosts, array_map( 'strtolower', (array) apply_filters( 'allowed_redirect_hosts', array(), $host ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core WordPress filter.
		return '' !== $host && in_array( $host, $hosts, true );
	}
}
