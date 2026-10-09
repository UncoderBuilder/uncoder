<?php
/**
 * Licence: a key from uncoderbuilder.com unlocks the paid features. Pro: the starter-site library, premium sections,
 * the AI starter rewrite, priority support. Agency adds white-label, the private cloud library, client handoff and
 * branded reports.
 *
 * Activation sends the key and this site's address to the licence server (Uncoder Cloud), which answers with a token
 * signed with Ed25519. The token names the site, the plan and its features; it is checked here with the public key
 * built in (self::KEYS), so the server is only asked again once a day and an outage changes nothing for 30 days.
 * Without a key the plugin never contacts the server.
 *
 * Features check `Licence::allows( 'white_label' )` before a new action (importing from the library, turning a locked
 * feature on or changing it). What was already built or configured keeps working when a licence ends.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Licence;

use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Licence {

	public const OPTION  = 'uncoder_wb_licence';
	public const CRON    = 'uncoder_wb_licence_refresh';
	public const SERVER  = 'https://uncoderbuilder.com/wp-json/uncoder-cloud/v1/';
	public const PRICING = 'https://uncoderbuilder.com/pricing/';
	/** The account page, opened on "email me my keys" (Storefront on uncoderbuilder.com). */
	public const FIND_KEY = 'https://uncoderbuilder.com/account/?keys';

	/** The licence server's public signing keys, by key id (a new key can be added before the old one retires). */
	public const KEYS = array(
		'k1' => 'lUuGtqsBXDBcqghvnKK3EI/UWJUSm9oO5kUwnfiHBPI=',
	);

	/** Each paid feature and the plan that brings it (for badges; the token's own list decides what is unlocked). */
	public const FEATURES = array(
		'library'          => 'pro',
		'sections'         => 'pro',
		'ai_rewrite'       => 'pro',
		'priority_support' => 'pro',
		'white_label'      => 'agency',
		'private_library'  => 'agency',
		'handoff'          => 'agency',
		'reports'          => 'agency',
	);

	/** Verified token payload of this request (false: not looked at yet). @var array<string,mixed>|null|false */
	private static $payload = false;

	public function register(): void {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( self::CRON, array( self::class, 'refresh' ) );
		add_action( 'admin_init', array( self::class, 'schedule' ) );
	}

	/**
	 * Whether licensing is switched on: Pro and Agency launched with 0.1.1, so it is on unless a site turns it off
	 * with define( 'UNCODER_WB_LICENSING', false ).
	 */
	public static function enabled(): bool {
		/**
		 * Filters whether Uncoder's licensing (Settings → Licence, paid features) is switched on.
		 *
		 * @param bool $enabled Whether it is on.
		 */
		return (bool) apply_filters( 'uncoder_wb/licence/enabled', ! defined( 'UNCODER_WB_LICENSING' ) || UNCODER_WB_LICENSING );
	}

	public function routes(): void {
		// White-label "only me": the licence is managed only by the administrator who set it (Site\White_Label).
		$admin = array( \Uncoder\Builder\Site\White_Label::class, 'can_manage' );
		register_rest_route(
			Rest::NS,
			'/licence',
			array(
				array( 'methods' => 'GET', 'callback' => array( $this, 'rest_state' ), 'permission_callback' => $admin ),
				array( 'methods' => 'POST', 'callback' => array( $this, 'rest_activate' ), 'permission_callback' => $admin ),
				array( 'methods' => 'DELETE', 'callback' => array( $this, 'rest_deactivate' ), 'permission_callback' => $admin ),
			)
		);
		register_rest_route( Rest::NS, '/licence/refresh', array( 'methods' => 'POST', 'callback' => array( $this, 'rest_refresh' ), 'permission_callback' => $admin ) );
	}

	public function rest_state(): WP_REST_Response {
		return new WP_REST_Response( self::state() );
	}

	public function rest_activate( WP_REST_Request $request ) {
		$done = self::activate( (string) $request->get_param( 'key' ) );
		return is_wp_error( $done ) ? $done : new WP_REST_Response( self::state() );
	}

	public function rest_deactivate(): WP_REST_Response {
		self::deactivate();
		return new WP_REST_Response( self::state() );
	}

	public function rest_refresh(): WP_REST_Response {
		self::refresh();
		return new WP_REST_Response( self::state() );
	}

	/* ------------------------------------------------------------------ State */

	/**
	 * The stored licence: key, token, licence (what the server last said), checked_at, error.
	 *
	 * @return array<string,mixed>
	 */
	public static function data(): array {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/** @param array<string,mixed> $data Stored licence. */
	private static function save( array $data ): void {
		update_option( self::OPTION, $data, false );
		self::$payload = false;
	}

	/** The verified token payload, or null without a valid one for this site. @return array<string,mixed>|null */
	public static function payload(): ?array {
		if ( false === self::$payload ) {
			$token         = (string) ( self::data()['token'] ?? '' );
			self::$payload = '' === $token ? null : self::verify( $token );
		}
		return self::$payload;
	}

	/** Whether the licence is valid now: a fresh token, an active licence, not past its end. */
	public static function active(): bool {
		$p = self::payload();
		if ( ! $p || time() > (int) ( $p['exp'] ?? 0 ) || 'active' !== ( $p['status'] ?? '' ) ) {
			return false;
		}
		$until = (int) ( $p['until'] ?? 0 );
		return 0 === $until || time() < $until;
	}

	/** Whether this site may use a paid feature now (for new actions; see the class comment). */
	public static function allows( string $feature ): bool {
		/**
		 * Filters whether a paid feature is unlocked (tests, or a host that bundles a licence).
		 *
		 * @param bool   $allowed Whether the licence covers it.
		 * @param string $feature Feature id (self::FEATURES).
		 */
		return (bool) apply_filters( 'uncoder_wb/licence/allows', self::active() && in_array( $feature, (array) ( self::payload()['features'] ?? array() ), true ), $feature );
	}

	/** 'free', 'pro' or 'agency'. */
	public static function plan(): string {
		return self::active() ? (string) ( self::payload()['plan'] ?? 'free' ) : 'free';
	}

	/**
	 * Everything the Settings → Licence card shows.
	 *
	 * @return array<string,mixed>
	 */
	public static function state(): array {
		$data    = self::data();
		$licence = is_array( $data['licence'] ?? null ) ? $data['licence'] : array();
		$error   = is_array( $data['error'] ?? null ) ? $data['error'] : null;
		$status  = 'none';
		if ( ! empty( $data['key'] ) ) {
			if ( self::active() ) {
				$status = 'active';
			} elseif ( 'expired' === ( $licence['status'] ?? '' ) || ( $error && 'expired' === $error['code'] ) || ( self::payload() && (int) self::payload()['until'] > 0 && time() >= (int) self::payload()['until'] ) ) {
				$status = 'expired';
			} elseif ( self::payload() && time() > (int) self::payload()['exp'] ) {
				$status = 'unverified';
			} else {
				$status = 'invalid';
			}
		}
		return array(
			'status'    => $status,
			'plan'      => self::plan(),
			'licence'   => $licence ? array_merge( $licence, array( 'plan' => $licence['plan'] ?? 'pro' ) ) : null,
			'hint'      => empty( $data['key'] ) ? '' : substr( (string) $data['key'], -5 ),
			'dev'       => (bool) ( self::payload()['dev'] ?? false ),
			'checkedAt' => empty( $data['checked_at'] ) ? null : gmdate( 'c', (int) $data['checked_at'] ),
			'error'     => $error,
			'features'  => array_map( static fn( string $f ): bool => self::allows( $f ), array_combine( array_keys( self::FEATURES ), array_keys( self::FEATURES ) ) ),
			'plans'     => self::FEATURES,
			'pricing'   => self::PRICING,
			'findKey'   => self::FIND_KEY,
		);
	}

	/* ------------------------------------------------------------------ Actions */

	/** @return true|WP_Error */
	public static function activate( string $key ) {
		$key = strtoupper( trim( preg_replace( '/\s+/', '', $key ) ) );
		if ( strlen( preg_replace( '/[^A-Z0-9]/', '', $key ) ) < 20 ) {
			return new WP_Error( 'invalid_key', __( 'That does not look like a licence key. It looks like UNC-XXXXX-XXXXX-XXXXX-XXXXX.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$res = self::call( 'activate', $key );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = array(
			'key'        => $key,
			'token'      => (string) $res['token'],
			'licence'    => (array) ( $res['licence'] ?? array() ),
			'checked_at' => time(),
			'error'      => null,
		);
		if ( ! self::verify( $data['token'] ) ) {
			return new WP_Error( 'bad_token', __( 'The licence server answered, but its signature did not check out. Update Uncoder and try again.', 'uncoder' ), array( 'status' => 502 ) );
		}
		// Switching keys: the old key no longer uses a site for this one (a failure leaves the slot in its account).
		$old = (string) ( self::data()['key'] ?? '' );
		if ( '' !== $old && $old !== $key ) {
			self::call( 'deactivate', $old );
		}
		self::save( $data );
		self::schedule();
		return true;
	}

	/** Frees this site's slot on the licence and forgets the key here. */
	public static function deactivate(): void {
		$key = (string) ( self::data()['key'] ?? '' );
		if ( '' !== $key ) {
			self::call( 'deactivate', $key ); // A failure leaves the slot in the account, where it can be freed.
		}
		delete_option( self::OPTION );
		self::$payload = false;
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Asks the server for a fresh token (daily, and from the card's "Check now"). A refused licence drops the token,
	 * which locks the paid features for new actions; an unreachable server keeps it (it stays valid until its exp).
	 */
	public static function refresh(): void {
		$data = self::data();
		if ( empty( $data['key'] ) ) {
			return;
		}
		$res                = self::call( 'check', (string) $data['key'] );
		$data['checked_at'] = time();
		if ( is_wp_error( $res ) ) {
			$code          = $res->get_error_code();
			$data['error'] = array( 'code' => $code, 'message' => $res->get_error_message() );
			if ( in_array( $code, array( 'invalid_key', 'not_activated', 'disabled', 'expired', 'refunded' ), true ) ) {
				unset( $data['token'] );
				$info = $res->get_error_data();
				if ( is_array( $info ) && is_array( $info['licence'] ?? null ) ) {
					$data['licence'] = $info['licence'];
				}
			}
		} elseif ( self::verify( (string) $res['token'] ) ) {
			$data['token']   = (string) $res['token'];
			$data['licence'] = (array) ( $res['licence'] ?? array() );
			$data['error']   = null;
		}
		self::save( $data );
	}

	/** Keeps the daily check scheduled while a key is stored (and only then). */
	public static function schedule(): void {
		$has  = ! empty( self::data()['key'] );
		$next = wp_next_scheduled( self::CRON );
		if ( $has && ! $next ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON );
		} elseif ( ! $has && $next ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/* ------------------------------------------------------------------ Server and token */

	public static function server(): string {
		$url = defined( 'UNCODER_CLOUD_URL' ) ? (string) UNCODER_CLOUD_URL : self::SERVER;
		/**
		 * Filters the licence server's API address.
		 *
		 * @param string $url API base, ending in a slash.
		 */
		return trailingslashit( (string) apply_filters( 'uncoder_wb/licence/server', $url ) );
	}

	/** @return array<string,string> */
	private static function keys(): array {
		$keys = self::KEYS;
		// A development server signs with its own key ('dev'), named in the test site's wp-config.php.
		if ( defined( 'UNCODER_CLOUD_PUBLIC_KEY' ) ) {
			$keys['dev'] = (string) UNCODER_CLOUD_PUBLIC_KEY;
		}
		return $keys;
	}

	/**
	 * This site as the server names it. Twin of Uncoder\Cloud\Sites::normalize(): host without "www.", a non-standard
	 * port, the path of a WordPress in a folder.
	 */
	public static function site(): string {
		$parts = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$host = strtolower( $parts['host'] );
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}
		$port = isset( $parts['port'] ) && ! in_array( (int) $parts['port'], array( 80, 443 ), true ) ? ':' . (int) $parts['port'] : '';
		$path = isset( $parts['path'] ) ? rtrim( strtolower( $parts['path'] ), '/' ) : '';
		return substr( $host . $port . $path, 0, 190 );
	}

	/**
	 * The token's payload when its signature checks out and it names this site.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function verify( string $token ): ?array {
		$parts = explode( '.', $token );
		if ( 2 !== count( $parts ) || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return null;
		}
		$json    = self::b64url_decode( $parts[0] );
		$sig     = self::b64url_decode( $parts[1] );
		$payload = json_decode( $json, true );
		$keys    = self::keys();
		if ( ! is_array( $payload ) || empty( $keys[ $payload['kid'] ?? '' ] ) || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $sig ) ) {
			return null;
		}
		$public = (string) base64_decode( $keys[ $payload['kid'] ], true );
		try {
			$ok = sodium_crypto_sign_verify_detached( $sig, $json, $public );
		} catch ( \Throwable $e ) {
			$ok = false;
		}
		return $ok && ( $payload['site'] ?? '' ) === self::site() ? $payload : null;
	}

	private static function b64url_decode( string $s ): string {
		return (string) base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true );
	}

	/**
	 * @return array<string,mixed>|WP_Error The server's answer ({ token, licence } or { ok }).
	 */
	private static function call( string $action, string $key ) {
		$res = wp_remote_post(
			self::server() . 'licence/' . $action,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
				'body'    => (string) wp_json_encode(
					array(
						'key'      => $key,
						'site_url' => home_url( '/' ),
						'env'      => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
						'version'  => defined( 'UNCODER_WB_VERSION' ) ? UNCODER_WB_VERSION : '',
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			/* translators: %s: the connection error. */
			return new WP_Error( 'offline', sprintf( __( 'Could not reach uncoderbuilder.com (%s). Try again in a moment.', 'uncoder' ), $res->get_error_message() ), array( 'status' => 502 ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $res );
		$body   = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $status >= 200 && $status < 300 && is_array( $body ) && ( ! empty( $body['token'] ) || ! empty( $body['ok'] ) ) ) {
			return $body;
		}
		if ( is_array( $body ) && ! empty( $body['code'] ) ) {
			$data           = is_array( $body['data'] ?? null ) ? $body['data'] : array();
			$data['status'] = 'invalid_key' === $body['code'] || 'not_activated' === $body['code'] ? 404 : 403;
			return new WP_Error( (string) $body['code'], self::message( (string) $body['code'], (string) ( $body['message'] ?? '' ) ), $data );
		}
		/* translators: %d: HTTP status code. */
		return new WP_Error( 'offline', sprintf( __( 'The licence server answered with an error (%d). Try again in a moment.', 'uncoder' ), $status ), array( 'status' => 502 ) );
	}

	/** The server's error, in the plugin's words (translatable). */
	private static function message( string $code, string $fallback ): string {
		$messages = array(
			'invalid_key'    => __( 'This licence key does not exist. Check it for typos, or find it in your purchase email.', 'uncoder' ),
			'expired'        => __( 'This licence has expired. Renew it to use the paid features again; everything you built keeps working.', 'uncoder' ),
			'disabled'       => __( 'This licence is no longer active. Write to hello@uncoderbuilder.com if that is a surprise.', 'uncoder' ),
			'refunded'       => __( 'This licence was refunded.', 'uncoder' ),
			'site_limit'     => __( 'This licence is already active on all its sites. Free a site in your account, or deactivate the licence on a site you no longer use.', 'uncoder' ),
			'not_activated'  => __( 'This site is no longer activated on the licence (it may have been removed from your account). Activate it again.', 'uncoder' ),
			'rate_limited'   => __( 'Too many attempts. Wait a few minutes and try again.', 'uncoder' ),
			'not_configured' => __( 'The licence server is being set up. Try again later.', 'uncoder' ),
		);
		return $messages[ $code ] ?? ( '' !== $fallback ? $fallback : __( 'The licence could not be checked.', 'uncoder' ) );
	}
}
