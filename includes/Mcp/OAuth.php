<?php
/**
 * OAuth 2.1 authorization server for MCP clients (claude.ai, ChatGPT, VS Code, Cursor…).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Install;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Implements: RFC 8414 metadata, RFC 9728 protected resource metadata, RFC 7591 dynamic client
 * registration, Client ID Metadata Documents, authorization code + PKCE (S256 only), refresh
 * token rotation with reuse detection, and RFC 7009 revocation.
 */
final class OAuth {

	public const AUTHORIZE_PAGE = 'uncoder-authorize';
	public const CODE_TTL       = 300;

	public static function issuer(): string {
		return untrailingslashit( home_url() );
	}

	public static function resource(): string {
		return rest_url( 'uncoder/v1/mcp' );
	}

	public static function resource_metadata_url(): string {
		return rest_url( 'uncoder/v1/oauth/protected-resource' );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function metadata(): array {
		return array(
			'issuer'                                => self::issuer(),
			'authorization_endpoint'                => admin_url( 'admin.php?page=' . self::AUTHORIZE_PAGE ),
			'token_endpoint'                        => rest_url( 'uncoder/v1/oauth/token' ),
			'registration_endpoint'                 => Settings::get( 'allow_registration' ) ? rest_url( 'uncoder/v1/oauth/register' ) : null,
			'revocation_endpoint'                   => rest_url( 'uncoder/v1/oauth/revoke' ),
			'scopes_supported'                      => array_keys( Tokens::SCOPES ),
			'response_types_supported'              => array( 'code' ),
			'response_modes_supported'              => array( 'query' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
			'revocation_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'client_id_metadata_document_supported' => true,
			'authorization_response_iss_parameter_supported' => true,
			'service_documentation'                 => admin_url( 'admin.php?page=uncoder-ai' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function resource_metadata(): array {
		return array(
			'resource'                 => self::resource(),
			'authorization_servers'    => array( self::issuer() ),
			'scopes_supported'         => array_keys( Tokens::SCOPES ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name'            => get_bloginfo( 'name' ) . ' — ' . \Uncoder\Builder\Core\Brand::name(),
			'resource_documentation'   => admin_url( 'admin.php?page=uncoder-ai' ),
		);
	}

	/* ------------------------------------------------------------------ Clients */

	private static function clients_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'uncoder_wb_oauth_clients';
	}

	/**
	 * @return array{client_id:string, client_name:string, redirect_uris:string[], secret_hash:string, logo_uri:string, client_uri:string}|null
	 */
	public static function client( string $client_id ): ?array {
		global $wpdb;
		if ( '' === $client_id || strlen( $client_id ) > 190 ) {
			return null;
		}
		if ( 0 === strpos( $client_id, 'https://' ) ) {
			return self::metadata_document_client( $client_id );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE client_id = %s', self::clients_table(), $client_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $row ) {
			return null;
		}
		$meta = json_decode( (string) $row->meta, true );
		return array(
			'client_id'     => $row->client_id,
			'client_name'   => $row->client_name,
			'redirect_uris' => (array) json_decode( (string) $row->redirect_uris, true ),
			'secret_hash'   => (string) ( $meta['secret_hash'] ?? '' ),
			'logo_uri'      => (string) ( $meta['logo_uri'] ?? '' ),
			'client_uri'    => (string) ( $meta['client_uri'] ?? '' ),
		);
	}

	/**
	 * Client ID Metadata Document: the client_id is an HTTPS URL serving the client's metadata.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function metadata_document_client( string $url ): ?array {
		$key    = 'uncoder_wb_cimd_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => 65536,
				'headers'             => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ( $data['client_id'] ?? '' ) !== $url ) {
			return null;
		}
		$uris = array_values( array_filter( (array) ( $data['redirect_uris'] ?? array() ), array( self::class, 'valid_redirect_uri' ) ) );
		if ( ! $uris ) {
			return null;
		}
		$client = array(
			'client_id'     => $url,
			'client_name'   => sanitize_text_field( (string) ( $data['client_name'] ?? Tokens::client_label( $url ) ) ),
			'redirect_uris' => $uris,
			'secret_hash'   => '',
			'logo_uri'      => esc_url_raw( (string) ( $data['logo_uri'] ?? '' ), array( 'https' ) ),
			'client_uri'    => esc_url_raw( (string) ( $data['client_uri'] ?? '' ), array( 'https' ) ),
		);
		set_transient( $key, $client, HOUR_IN_SECONDS );
		return $client;
	}

	public static function valid_redirect_uri( $uri ): bool {
		if ( ! is_string( $uri ) || strlen( $uri ) > 500 || false !== strpos( $uri, '#' ) ) {
			return false;
		}
		$parts = wp_parse_url( $uri );
		if ( ! $parts || empty( $parts['scheme'] ) ) {
			return false;
		}
		$scheme = strtolower( $parts['scheme'] );
		if ( 'https' === $scheme ) {
			return ! empty( $parts['host'] );
		}
		if ( 'http' === $scheme ) {
			return in_array( $parts['host'] ?? '', array( 'localhost', '127.0.0.1', '[::1]', '::1' ), true );
		}
		// Private-use schemes for native apps (RFC 8252), never script-capable ones.
		return (bool) preg_match( '/^[a-z][a-z0-9+.\-]{2,}$/', $scheme ) && ! in_array( $scheme, array( 'javascript', 'data', 'file', 'vbscript', 'blob', 'about' ), true );
	}

	private static function redirect_matches( string $given, array $registered ): bool {
		foreach ( $registered as $uri ) {
			if ( hash_equals( (string) $uri, $given ) ) {
				return true;
			}
			// Loopback redirects may use any port (RFC 8252 §7.3).
			$a = wp_parse_url( (string) $uri );
			$b = wp_parse_url( $given );
			if ( $a && $b && 'http' === ( $a['scheme'] ?? '' ) && in_array( $a['host'] ?? '', array( '127.0.0.1', 'localhost', '[::1]' ), true )
				&& ( $a['host'] ?? '' ) === ( $b['host'] ?? '' ) && ( $a['path'] ?? '' ) === ( $b['path'] ?? '' ) && ( $a['scheme'] ?? '' ) === ( $b['scheme'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ REST: registration, token, revoke */

	public static function register_routes(): void {
		$public = static fn() => true;
		register_rest_route( 'uncoder/v1', '/oauth/register', array( 'methods' => 'POST', 'callback' => array( self::class, 'register' ), 'permission_callback' => $public ) );
		register_rest_route( 'uncoder/v1', '/oauth/token', array( 'methods' => 'POST', 'callback' => array( self::class, 'token' ), 'permission_callback' => $public ) );
		register_rest_route( 'uncoder/v1', '/oauth/revoke', array( 'methods' => 'POST', 'callback' => array( self::class, 'revoke' ), 'permission_callback' => $public ) );
		register_rest_route(
			'uncoder/v1',
			'/oauth/protected-resource',
			array(
				'methods'             => 'GET',
				'callback'            => static fn() => self::json( self::resource_metadata() ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			'uncoder/v1',
			'/oauth/metadata',
			array(
				'methods'             => 'GET',
				'callback'            => static fn() => self::json( array_filter( self::metadata() ) ),
				'permission_callback' => $public,
			)
		);
	}

	/**
	 * @param array<string,mixed> $data Body.
	 */
	public static function json( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Access-Control-Allow-Origin', '*' );
		return $response;
	}

	private static function error( string $code, string $description, int $status = 400 ): WP_REST_Response {
		return self::json(
			array(
				'error'             => $code,
				'error_description' => $description,
			),
			$status
		);
	}

	/**
	 * RFC 7591 dynamic client registration (public clients with PKCE; optional secret).
	 */
	public static function register( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		if ( ! Settings::get( 'enabled' ) || ! Settings::get( 'allow_registration' ) ) {
			return self::error( 'access_denied', 'Dynamic client registration is disabled on this site.', 403 );
		}
		$limit = Rate_Limiter::hit( 'register|' . Utils::client_ip(), 20, HOUR_IN_SECONDS );
		if ( ! $limit['allowed'] ) {
			return self::error( 'slow_down', 'Too many registrations from this address. Try again later.', 429 );
		}
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		$uris = array_values( array_unique( array_filter( (array) ( $body['redirect_uris'] ?? array() ), array( self::class, 'valid_redirect_uri' ) ) ) );
		if ( ! $uris || count( $uris ) > 10 ) {
			return self::error( 'invalid_redirect_uri', 'Provide 1–10 redirect_uris using https, http on a loopback address (localhost, 127.0.0.1 or [::1]), or a private app scheme.' );
		}
		$method = (string) ( $body['token_endpoint_auth_method'] ?? 'none' );
		if ( ! in_array( $method, array( 'none', 'client_secret_post', 'client_secret_basic' ), true ) ) {
			return self::error( 'invalid_client_metadata', 'Unsupported token_endpoint_auth_method.' );
		}
		$client_id = 'uncoder_client_' . strtolower( wp_generate_password( 24, false, false ) );
		$secret    = 'none' === $method ? '' : Tokens::random( 'uncoder_cs_' );
		$name      = sanitize_text_field( (string) ( $body['client_name'] ?? 'MCP client' ) );
		$meta      = array(
			'secret_hash' => '' !== $secret ? Tokens::hash( $secret ) : '',
			'logo_uri'    => esc_url_raw( (string) ( $body['logo_uri'] ?? '' ), array( 'https' ) ),
			'client_uri'  => esc_url_raw( (string) ( $body['client_uri'] ?? '' ), array( 'https' ) ),
			'ip'          => Utils::anonymize_ip( Utils::client_ip() ),
		);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::clients_table(),
			array(
				'client_id'     => $client_id,
				'client_name'   => substr( '' !== $name ? $name : 'MCP client', 0, 190 ),
				'redirect_uris' => wp_json_encode( $uris ),
				'meta'          => wp_json_encode( $meta ),
				'created_at'    => Utils::now_mysql(),
			)
		);
		$out = array(
			'client_id'                  => $client_id,
			'client_id_issued_at'        => time(),
			'client_name'                => $name,
			'redirect_uris'              => $uris,
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => $method,
			'scope'                      => implode( ' ', array_keys( Tokens::SCOPES ) ),
		);
		if ( '' !== $secret ) {
			$out['client_secret']            = $secret;
			$out['client_secret_expires_at'] = 0;
		}
		return self::json( $out, 201 );
	}

	/**
	 * Authenticates the client on the token/revoke endpoints.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|WP_REST_Response Client or error response.
	 */
	private static function authenticate_client( WP_REST_Request $request, array $params ) {
		$client_id = (string) ( $params['client_id'] ?? '' );
		$secret    = (string) ( $params['client_secret'] ?? '' );
		$basic     = (string) $request->get_header( 'authorization' );
		if ( 0 === stripos( $basic, 'Basic ' ) ) {
			$decoded = base64_decode( substr( $basic, 6 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- HTTP Basic auth.
			if ( $decoded && false !== strpos( $decoded, ':' ) ) {
				list( $client_id, $secret ) = array_map( 'rawurldecode', explode( ':', $decoded, 2 ) );
			}
		}
		$client = self::client( $client_id );
		if ( ! $client ) {
			return self::error( 'invalid_client', 'Unknown client.', 401 );
		}
		if ( '' !== $client['secret_hash'] && ! hash_equals( $client['secret_hash'], Tokens::hash( $secret ) ) ) {
			return self::error( 'invalid_client', 'Client authentication failed.', 401 );
		}
		return $client;
	}

	public static function token( WP_REST_Request $request ): WP_REST_Response {
		$params = array_merge( (array) $request->get_body_params(), (array) ( $request->get_json_params() ?? array() ) );
		$limit  = Rate_Limiter::hit( 'token|' . Utils::client_ip(), 60, 60 );
		if ( ! $limit['allowed'] ) {
			return self::error( 'slow_down', 'Too many token requests.', 429 );
		}
		if ( ! Settings::get( 'enabled' ) ) {
			return self::error( 'access_denied', 'The MCP server is disabled.', 403 );
		}
		$client = self::authenticate_client( $request, $params );
		if ( $client instanceof WP_REST_Response ) {
			return $client;
		}
		$grant = (string) ( $params['grant_type'] ?? '' );

		if ( 'authorization_code' === $grant ) {
			$code = Tokens::find( (string) ( $params['code'] ?? '' ), 'code' );
			if ( ! $code || $code->client_id !== $client['client_id'] ) {
				return self::error( 'invalid_grant', 'The authorization code is invalid or expired.' );
			}
			Tokens::revoke( (int) $code->id ); // Single use.
			$meta     = (array) json_decode( (string) $code->meta, true );
			$verifier = (string) ( $params['code_verifier'] ?? '' );
			if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $verifier ) ) {
				return self::error( 'invalid_grant', 'A valid code_verifier (PKCE) is required.' );
			}
			$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PKCE.
			if ( ! hash_equals( (string) ( $meta['code_challenge'] ?? '' ), $challenge ) ) {
				return self::error( 'invalid_grant', 'PKCE verification failed.' );
			}
			if ( (string) ( $params['redirect_uri'] ?? '' ) !== (string) ( $meta['redirect_uri'] ?? '' ) ) {
				return self::error( 'invalid_grant', 'redirect_uri does not match the authorization request.' );
			}
			$user = get_userdata( (int) $code->user_id );
			if ( ! $user || ! user_can( $user, Install::MCP_CAP ) ) {
				return self::error( 'invalid_grant', 'The approving user can no longer use MCP.' );
			}
			return self::issue( $client['client_id'], (int) $code->user_id, array_filter( explode( ' ', (string) $code->scopes ) ) );
		}

		if ( 'refresh_token' === $grant ) {
			$secret = (string) ( $params['refresh_token'] ?? '' );
			$row    = Tokens::find( $secret, 'refresh' );
			if ( ! $row ) {
				self::detect_reuse( $secret );
				return self::error( 'invalid_grant', 'The refresh token is invalid, expired or already used.' );
			}
			if ( $row->client_id !== $client['client_id'] ) {
				return self::error( 'invalid_grant', 'The refresh token was issued to another client.' );
			}
			Tokens::revoke( (int) $row->id );
			$scopes = array_filter( explode( ' ', (string) $row->scopes ) );
			if ( ! empty( $params['scope'] ) ) {
				$scopes = array_intersect( $scopes, preg_split( '/\s+/', (string) $params['scope'] ) );
			}
			return self::issue( $client['client_id'], (int) $row->user_id, $scopes );
		}

		return self::error( 'unsupported_grant_type', 'Use authorization_code or refresh_token.' );
	}

	/**
	 * A revoked refresh token presented again means it leaked: revoke the whole grant.
	 */
	private static function detect_reuse( string $secret ): void {
		global $wpdb;
		if ( '' === $secret ) {
			return;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT client_id, user_id, revoked FROM %i WHERE token_hash = %s AND type = 'refresh'", $wpdb->prefix . 'uncoder_wb_tokens', Tokens::hash( $secret ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $row && (int) $row->revoked ) {
			Tokens::revoke_client( (string) $row->client_id, (int) $row->user_id );
			Audit_Log::add(
				array(
					'user_id' => (int) $row->user_id,
					'client'  => (string) $row->client_id,
					'method'  => 'oauth/token',
					'status'  => 'revoked',
					'summary' => 'Refresh token reuse detected: all tokens of this client were revoked.',
				)
			);
		}
	}

	/**
	 * @param string[] $scopes Scopes.
	 */
	private static function issue( string $client_id, int $user_id, array $scopes ): WP_REST_Response {
		global $wpdb;
		$scopes = Tokens::clean_scopes( $scopes );
		list( $refresh, $refresh_id ) = Tokens::create(
			array(
				'type'      => 'refresh',
				'user_id'   => $user_id,
				'client_id' => $client_id,
				'scopes'    => $scopes,
				'ttl'       => (int) Settings::get( 'refresh_ttl' ),
			)
		);
		list( $access ) = Tokens::create(
			array(
				'type'      => 'access',
				'user_id'   => $user_id,
				'client_id' => $client_id,
				'scopes'    => $scopes,
				'ttl'       => (int) Settings::get( 'access_ttl' ),
				'parent_id' => $refresh_id,
			)
		);
		$wpdb->update( $wpdb->prefix . 'uncoder_wb_oauth_clients', array( 'last_used_at' => Utils::now_mysql() ), array( 'client_id' => $client_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return self::json(
			array(
				'access_token'  => $access,
				'token_type'    => 'Bearer',
				'expires_in'    => (int) Settings::get( 'access_ttl' ),
				'refresh_token' => $refresh,
				'scope'         => implode( ' ', $scopes ),
			)
		);
	}

	public static function revoke( WP_REST_Request $request ): WP_REST_Response {
		$params = array_merge( (array) $request->get_body_params(), (array) ( $request->get_json_params() ?? array() ) );
		$token  = (string) ( $params['token'] ?? '' );
		foreach ( array( 'refresh', 'access' ) as $type ) {
			$row = Tokens::find( $token, $type );
			if ( $row ) {
				Tokens::revoke( (int) ( 'access' === $type && $row->parent_id ? $row->parent_id : $row->id ) );
			}
		}
		// RFC 7009: always 200, even for unknown tokens.
		return self::json( array() );
	}

	/* ------------------------------------------------------------------ Authorization (consent screen) */

	/**
	 * Runs on admin_init for admin.php?page=uncoder-authorize (login is enforced by wp-admin).
	 */
	public static function maybe_authorize(): void {
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		if ( self::AUTHORIZE_PAGE !== $page ) {
			return;
		}
		// admin_init also runs for admin-post.php / admin-ajax.php without a login: only admin.php
		// (which enforces login) may show the consent screen or redirect anywhere.
		if ( 'admin.php' !== ( $GLOBALS['pagenow'] ?? '' ) || ! is_user_logged_in() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- request parameters of an OAuth request; the decision form is nonce-checked below.
		$req = array();
		foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method', 'scope', 'resource' ) as $k ) {
			$req[ $k ] = isset( $_REQUEST[ $k ] ) && is_string( $_REQUEST[ $k ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $k ] ) ) : '';
		}
		// Kept verbatim (sanitize_text_field could alter it): it must match a registered redirect URI exactly, checked below.
		$req['redirect_uri'] = isset( $_REQUEST['redirect_uri'] ) && is_string( $_REQUEST['redirect_uri'] ) ? trim( wp_unslash( $_REQUEST['redirect_uri'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated against the registered URIs below.
		$method              = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		// phpcs:enable

		$client = self::client( $req['client_id'] );
		if ( ! $client ) {
			self::render_error( __( 'This application is not registered with this site.', 'uncoder' ) );
		}
		if ( ! self::redirect_matches( $req['redirect_uri'], $client['redirect_uris'] ) ) {
			self::render_error( __( 'The redirect address does not match the application registration.', 'uncoder' ) );
		}
		$fail = static function ( string $error, string $description ) use ( $req ) {
			self::redirect_back( $req['redirect_uri'], array( 'error' => $error, 'error_description' => $description, 'state' => $req['state'] ) );
		};
		// Malformed requests are shown here rather than bounced to the (self-registered) redirect URI,
		// so the endpoint cannot be used as an open redirector.
		if ( ! Settings::get( 'enabled' ) ) {
			self::render_error( __( 'The MCP server is disabled on this site.', 'uncoder' ) );
		}
		if ( 'code' !== $req['response_type'] ) {
			self::render_error( __( 'Only response_type=code is supported.', 'uncoder' ) );
		}
		if ( 'S256' !== $req['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9\-_]{43,128}$/', $req['code_challenge'] ) ) {
			self::render_error( __( 'PKCE with code_challenge_method=S256 is required.', 'uncoder' ) );
		}
		if ( ! current_user_can( Install::MCP_CAP ) ) {
			self::render_error( __( 'Your account is not allowed to connect AI clients to this site. Ask an administrator to allow your role in Uncoder → AI & MCP.', 'uncoder' ) );
		}
		$requested = '' !== $req['scope'] ? preg_split( '/\s+/', $req['scope'] ) : Tokens::DEFAULT_SCOPES;
		$scopes    = Tokens::clean_scopes( (array) $requested );
		if ( ! $scopes ) {
			$scopes = Tokens::DEFAULT_SCOPES;
		}
		// Capabilities the user does not have are never granted.
		if ( ! current_user_can( 'manage_options' ) ) {
			$scopes = array_values( array_diff( $scopes, array( 'site' ) ) );
		}
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			$scopes = array_values( array_diff( $scopes, array( 'design' ) ) );
		}

		if ( 'POST' === $method ) {
			check_admin_referer( 'uncoder_authorize_' . $client['client_id'] );
			if ( empty( $_POST['approve'] ) ) {
				$fail( 'access_denied', 'The user denied the request.' );
			}
			$granted = isset( $_POST['scopes'] ) && is_array( $_POST['scopes'] ) ? array_values( array_intersect( $scopes, array_map( 'sanitize_key', wp_unslash( $_POST['scopes'] ) ) ) ) : array();
			if ( ! in_array( 'read', $granted, true ) ) {
				$granted[] = 'read';
			}
			list( $code ) = Tokens::create(
				array(
					'type'      => 'code',
					'user_id'   => get_current_user_id(),
					'client_id' => $client['client_id'],
					'scopes'    => $granted,
					'ttl'       => self::CODE_TTL,
					'meta'      => array(
						'code_challenge' => $req['code_challenge'],
						'redirect_uri'   => $req['redirect_uri'],
						'resource'       => $req['resource'],
					),
				)
			);
			Audit_Log::add(
				array(
					'client'  => $client['client_name'],
					'method'  => 'oauth/authorize',
					'status'  => 'ok',
					'summary' => 'Approved with scopes: ' . implode( ', ', $granted ),
				)
			);
			self::redirect_back(
				$req['redirect_uri'],
				array(
					'code'  => $code,
					'state' => $req['state'],
					'iss'   => self::issuer(),
				)
			);
		}

		self::render_consent( $client, $req, $scopes );
	}

	/**
	 * @param array<string,string> $params Query params.
	 */
	private static function redirect_back( string $uri, array $params ): void {
		$params = array_filter( $params, static fn( $v ) => '' !== $v && null !== $v );
		$url    = $uri . ( false === strpos( $uri, '?' ) ? '?' : '&' ) . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		// An external address by design: $uri was matched exactly against the client's registered redirect URIs.
		wp_redirect( $url, 302, 'Uncoder' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- validated OAuth redirect URI.
		exit;
	}

	private static function render_error( string $message ): void {
		self::page_open( __( 'Connection problem', 'uncoder' ) );
		echo '<h1>' . esc_html__( 'Can’t connect this application', 'uncoder' ) . '</h1><p class="msg">' . esc_html( $message ) . '</p>';
		echo '<a class="btn" href="' . esc_url( admin_url() ) . '">' . esc_html__( 'Back to the dashboard', 'uncoder' ) . '</a>';
		self::page_close();
		exit;
	}

	/**
	 * @param array<string,mixed>  $client Client.
	 * @param array<string,string> $req    Request.
	 * @param string[]             $scopes Scopes offered.
	 */
	private static function render_consent( array $client, array $req, array $scopes ): void {
		$user = wp_get_current_user();
		$host = wp_parse_url( $req['redirect_uri'], PHP_URL_HOST );
		self::page_open( __( 'Authorize application', 'uncoder' ) );
		?>
		<div class="app">
			<?php if ( ! empty( $client['logo_uri'] ) ) : ?>
				<img class="logo" src="<?php echo esc_url( $client['logo_uri'] ); ?>" alt="" width="40" height="40">
			<?php else : ?>
				<span class="logo logo--letter" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( (string) $client['client_name'], 0, 1 ) ) ); ?></span>
			<?php endif; ?>
			<div>
				<h1><?php echo esc_html( sprintf( /* translators: 1: application name, 2: site name. */ __( '%1$s wants to connect to %2$s', 'uncoder' ), $client['client_name'], get_bloginfo( 'name' ) ) ); ?></h1>
				<p class="muted"><?php echo esc_html( sprintf( /* translators: 1: user name, 2: host. */ __( 'Signed in as %1$s · returns to %2$s', 'uncoder' ), $user->display_name, $host ? $host : $req['redirect_uri'] ) ); ?></p>
			</div>
		</div>
		<form method="post">
			<?php wp_nonce_field( 'uncoder_authorize_' . $client['client_id'] ); ?>
			<?php foreach ( $req as $k => $v ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
			<?php endforeach; ?>
			<fieldset>
				<legend><?php esc_html_e( 'It will be able to:', 'uncoder' ); ?></legend>
				<?php foreach ( Tokens::scope_labels() as $scope => $label ) : ?>
					<?php $offered = in_array( $scope, $scopes, true ); ?>
					<label class="scope<?php echo $offered ? '' : ' is-off'; ?>">
						<input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $scope ); ?>" <?php checked( $offered && 'site' !== $scope ); ?> <?php disabled( ! $offered || 'read' === $scope ); ?>>
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="note"><?php esc_html_e( 'Every change the application makes is logged and can be undone from Uncoder → AI & MCP. You can revoke access at any time.', 'uncoder' ); ?></p>
			<div class="actions">
				<button type="submit" name="deny" value="1" class="btn btn--ghost"><?php esc_html_e( 'Cancel', 'uncoder' ); ?></button>
				<button type="submit" name="approve" value="1" class="btn"><?php esc_html_e( 'Approve', 'uncoder' ); ?></button>
			</div>
		</form>
		<?php
		self::page_close();
		exit;
	}

	private static function page_open( string $title ): void {
		nocache_headers();
		header( 'X-Frame-Options: DENY' );
		header( "Content-Security-Policy: frame-ancestors 'none'" );
		?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $title ); ?></title>
			<?php
			// A standalone screen (no wp-admin chrome): its styles are printed through the style queue.
			wp_register_style( 'uncoder-authorize', false, array(), UNCODER_WB_VERSION );
			wp_add_inline_style( 'uncoder-authorize', self::consent_css() );
			wp_print_styles( array( 'uncoder-authorize' ) );
			?>
</head>
<body>
<main class="card">
<div class="brand"><i><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4v9a6 6 0 0 0 12 0V4"/></svg></i><?php echo esc_html( get_bloginfo( 'name' ) ); ?> · Uncoder</div>
		<?php
	}

	private static function page_close(): void {
		echo "</main>\n</body>\n</html>";
	}

	private static function consent_css(): string {
		return 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f5f7;color:#16181d;font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}'
			. '.card{width:min(460px,calc(100vw - 32px));background:#fff;border-radius:14px;box-shadow:0 0 0 1px #e6e8ec,0 24px 60px -30px rgb(22 24 29/.35);padding:28px}'
			. '.brand{display:flex;align-items:center;gap:8px;margin-bottom:22px;color:#555b67;font-weight:600;font-size:13px}'
			. '.brand i{width:22px;height:22px;border-radius:6px;background:#6e56ff;display:inline-flex;align-items:center;justify-content:center}'
			. '.app{display:flex;gap:14px;align-items:flex-start;margin-bottom:18px}.logo{width:40px;height:40px;border-radius:10px;flex-shrink:0;object-fit:cover}'
			. '.logo--letter{display:flex;align-items:center;justify-content:center;background:#eeebff;color:#4330c9;font-weight:700;font-size:18px}'
			. 'h1{margin:0 0 4px;font-size:17px;line-height:1.35}.muted,.note{color:#6b7280;margin:0}.note{font-size:12.5px;margin:14px 0 18px}'
			. 'fieldset{border:0;margin:0;padding:14px;border-radius:10px;background:#f7f8fa}legend{padding:0;font-weight:600;margin-bottom:8px;float:left;width:100%}'
			. '.scope{display:flex;gap:10px;align-items:center;padding:6px 0;clear:both}.scope.is-off{color:#9ca3af}.scope input{width:16px;height:16px;accent-color:#6e56ff}'
			. '.actions{display:flex;justify-content:flex-end;gap:8px}.btn{display:inline-flex;align-items:center;height:38px;padding:0 18px;border:0;border-radius:8px;background:#6e56ff;color:#fff;font:600 14px system-ui,sans-serif;text-decoration:none;cursor:pointer}'
			. '.btn:hover{background:#5b43f0}.btn--ghost{background:#fff;color:#16181d;box-shadow:inset 0 0 0 1px #d9dce1}.btn--ghost:hover{background:#f4f5f7}.msg{margin:8px 0 18px;color:#555b67}';
	}

	/**
	 * Deletes dynamically registered clients that never obtained a token (abandoned registrations).
	 */
	public static function prune_clients(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_used_at IS NULL AND created_at < %s', self::clients_table(), gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
