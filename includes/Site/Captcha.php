<?php
/**
 * CAPTCHA for Uncoder forms: Cloudflare Turnstile, hCaptcha or Google reCAPTCHA v3 (invisible, scored).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Rest\Settings_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Keys live in uncoder_wb_settings['captcha'] = { provider, site_key, secret }. The secret never
 * leaves the server. A form opts in with its "captcha" setting; the provider's script loads only on
 * pages with such a form (it is an external service: see readme.txt).
 */
final class Captcha {

	public const PROVIDERS = array(
		'turnstile' => array(
			'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- The Cloudflare Turnstile CAPTCHA service itself (readme: External services); only loaded when an admin configures it.
			'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- The Cloudflare Turnstile CAPTCHA service itself (readme: External services); only loaded when an admin configures it.
			'class'  => 'cf-turnstile',
			'field'  => 'cf-turnstile-response',
		),
		'hcaptcha'  => array(
			'script' => 'https://js.hcaptcha.com/1/api.js',
			'verify' => 'https://api.hcaptcha.com/siteverify',
			'class'  => 'h-captcha',
			'field'  => 'h-captcha-response',
		),
		// "I'm not a robot" checkbox.
		'recaptcha_v2' => array(
			'script' => 'https://www.google.com/recaptcha/api.js',
			'verify' => 'https://www.google.com/recaptcha/api/siteverify',
			'class'  => 'g-recaptcha',
			'field'  => 'g-recaptcha-response',
		),
		// Invisible: the form asks grecaptcha.execute() for a token just before it submits.
		'recaptcha' => array(
			'script' => 'https://www.google.com/recaptcha/api.js',
			'verify' => 'https://www.google.com/recaptcha/api/siteverify',
			'class'  => 'uncoder-recaptcha',
			'field'  => 'g-recaptcha-response',
		),
	);

	/** reCAPTCHA v3 action name the form passes to grecaptcha.execute(). */
	public const RECAPTCHA_ACTION = 'uncoder_form';

	/**
	 * @return array{provider:string, site_key:string, secret:string, min_score:float}
	 */
	public static function get(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$c      = is_array( $stored ) && is_array( $stored['captcha'] ?? null ) ? $stored['captcha'] : array();
		return array(
			'provider'  => isset( self::PROVIDERS[ $c['provider'] ?? '' ] ) ? (string) $c['provider'] : '',
			'site_key'  => (string) ( $c['site_key'] ?? '' ),
			'secret'    => (string) ( $c['secret'] ?? '' ),
			'min_score' => isset( $c['min_score'] ) && is_numeric( $c['min_score'] ) ? max( 0.1, min( 0.9, (float) $c['min_score'] ) ) : 0.5,
		);
	}

	public static function ready(): bool {
		$c = self::get();
		return '' !== $c['provider'] && '' !== $c['site_key'] && '' !== $c['secret'];
	}

	/**
	 * Sanitizes new settings; an empty secret keeps the stored one (the UI never receives it).
	 *
	 * @param array<string,mixed> $raw Raw.
	 * @return array{provider:string, site_key:string, secret:string, min_score:float}
	 */
	public static function sanitize( array $raw ): array {
		$current  = self::get();
		$provider = (string) ( $raw['provider'] ?? '' );
		$key      = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $raw['site_key'] ?? $current['site_key'] ) );
		$secret   = isset( $raw['secret'] ) && '' !== $raw['secret'] ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $raw['secret'] ) : $current['secret'];
		return array(
			'provider'  => isset( self::PROVIDERS[ $provider ] ) ? $provider : '',
			'site_key'  => substr( (string) $key, 0, 200 ),
			'secret'    => substr( (string) $secret, 0, 200 ),
			'min_score' => isset( $raw['min_score'] ) && is_numeric( $raw['min_score'] ) ? round( max( 0.1, min( 0.9, (float) $raw['min_score'] ) ), 1 ) : $current['min_score'],
		);
	}

	/**
	 * Settings as shown to administrators (no secret).
	 *
	 * @return array{provider:string, site_key:string, has_secret:bool, min_score:float}
	 */
	public static function public_settings(): array {
		$c = self::get();
		return array(
			'provider'   => $c['provider'],
			'site_key'   => $c['site_key'],
			'has_secret' => '' !== $c['secret'],
			'min_score'  => $c['min_score'],
		);
	}

	/**
	 * Widget markup; enqueues the provider script.
	 */
	public static function markup(): string {
		$c = self::get();
		if ( ! self::ready() ) {
			return '';
		}
		$p = self::PROVIDERS[ $c['provider'] ];
		if ( 'recaptcha' === $c['provider'] ) {
			wp_enqueue_script( 'uncoder-captcha-recaptcha', add_query_arg( 'render', rawurlencode( $c['site_key'] ), $p['script'] ), array(), null, array( 'strategy' => 'async', 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			return '<div class="uncoder-form__captcha uncoder-form__captcha--invisible" data-recaptcha="' . esc_attr( $c['site_key'] ) . '" data-action="' . esc_attr( self::RECAPTCHA_ACTION ) . '"><input type="hidden" name="g-recaptcha-response" value=""></div>';
		}
		wp_enqueue_script( 'uncoder-captcha-' . $c['provider'], $p['script'], array(), null, array( 'strategy' => 'async', 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the provider versions its own script.
		return '<div class="uncoder-form__captcha"><div class="' . esc_attr( $p['class'] ) . '" data-sitekey="' . esc_attr( $c['site_key'] ) . '" data-theme="auto"></div></div>';
	}

	/**
	 * Checks the visitor's CAPTCHA answer with the provider.
	 *
	 * @param array<string,mixed> $params Submitted request parameters.
	 */
	public static function verify( array $params ): bool {
		$c = self::get();
		if ( ! self::ready() ) {
			return true; // Not configured: nothing to check.
		}
		$p        = self::PROVIDERS[ $c['provider'] ];
		$response = isset( $params[ $p['field'] ] ) && is_string( $params[ $p['field'] ] ) ? $params[ $p['field'] ] : '';
		if ( '' === $response || strlen( $response ) > 4096 ) {
			return false;
		}
		$result = wp_remote_post(
			$p['verify'],
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $c['secret'],
					'response' => $response,
					'remoteip' => \Uncoder\Builder\Core\Utils::client_ip(),
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			return false;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $result ), true );
		if ( ! is_array( $data ) || empty( $data['success'] ) ) {
			return false;
		}
		if ( 'recaptcha' === $c['provider'] ) {
			// v3 scores the visit (1.0 = very likely human) and echoes the action the page asked for.
			return isset( $data['score'] ) && (float) $data['score'] >= $c['min_score'] && self::RECAPTCHA_ACTION === ( $data['action'] ?? '' );
		}
		return true;
	}
}
