<?php
/**
 * MCP module: Streamable HTTP endpoint, OAuth discovery, admin API.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoint: POST /wp-json/uncoder/v1/mcp (JSON-RPC 2.0, JSON responses).
 */
final class Mcp {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'parse_request', array( $this, 'well_known' ), 0 );
		add_action( 'admin_init', array( OAuth::class, 'maybe_authorize' ), 0 );
		add_action( 'admin_menu', array( $this, 'authorize_page' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'empty_body' ), 10, 4 );
		add_filter( 'rest_allowed_cors_headers', array( $this, 'cors_headers' ) );
		add_filter( 'rest_exposed_cors_headers', array( $this, 'exposed_headers' ) );
		add_action( 'uncoder_wb/daily', array( $this, 'prune' ) );
	}

	/**
	 * wp-admin denies unregistered pages before admin_init runs, so the consent screen is registered
	 * as a hidden page. Its output comes from OAuth::maybe_authorize(), which exits on admin_init.
	 */
	public function authorize_page(): void {
		add_submenu_page( '', __( 'Connect an AI app', 'uncoder' ), '', 'read', OAuth::AUTHORIZE_PAGE, '__return_null' );
	}

	public function routes(): void {
		register_rest_route(
			'uncoder/v1',
			'/mcp',
			array(
				'methods'             => array( 'GET', 'POST', 'DELETE' ),
				'callback'            => array( $this, 'handle' ),
				// Authentication happens inside handle() so failures can carry WWW-Authenticate.
				'permission_callback' => '__return_true',
			)
		);
		OAuth::register_routes();
		( new Admin_Api() )->register_routes();
	}

	/**
	 * Serves OAuth discovery documents from the site root.
	 *
	 * @param \WP $wp WP.
	 */
	public function well_known( $wp ): void {
		$path = trim( (string) ( $wp->request ?? '' ), '/' );
		if ( 0 !== strpos( $path, '.well-known/' ) ) {
			return;
		}
		$doc = null;
		if ( preg_match( '#^\.well-known/(oauth-authorization-server|openid-configuration)(/.*)?$#', $path ) ) {
			$doc = array_filter( OAuth::metadata() );
		} elseif ( preg_match( '#^\.well-known/oauth-protected-resource(/.*)?$#', $path ) ) {
			$doc = OAuth::resource_metadata();
		}
		if ( null === $doc ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		echo wp_json_encode( $doc, JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON document.
		exit;
	}

	private function origin_allowed( string $origin ): bool {
		if ( '' === $origin || 'null' === $origin ) {
			return '' === $origin;
		}
		$host = wp_parse_url( $origin, PHP_URL_HOST );
		if ( $host && wp_parse_url( home_url(), PHP_URL_HOST ) === $host ) {
			return true;
		}
		if ( in_array( untrailingslashit( $origin ), (array) Settings::get( 'allowed_origins' ), true ) ) {
			return true;
		}
		// Local development tools (MCP Inspector) on loopback, only on local/development sites.
		return in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) && in_array( $host, array( 'localhost', '127.0.0.1' ), true );
	}

	/**
	 * @param array<string,mixed>|null $body Body.
	 */
	private function respond( $body, int $status, array $headers = array() ): WP_REST_Response {
		$response = new WP_REST_Response( $body, $status );
		foreach ( $headers as $k => $v ) {
			$response->header( $k, $v );
		}
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Settings::get( 'enabled' ) ) {
			return $this->respond( Server::error( null, -32000, 'The Uncoder MCP server is disabled on this site.' ), 503 );
		}
		$origin = (string) $request->get_header( 'origin' );
		if ( '' !== $origin && ! $this->origin_allowed( $origin ) ) {
			return $this->respond( Server::error( null, -32000, 'Origin not allowed. Add it in Uncoder → AI & MCP → Allowed origins.' ), 403 );
		}
		if ( 'GET' === $request->get_method() ) {
			// No server-initiated SSE stream: responses are plain JSON.
			return $this->respond( Server::error( null, -32000, 'Method not allowed: use POST.' ), 405, array( 'Allow' => 'POST, DELETE' ) );
		}

		$ctx = Auth::resolve( $request );
		if ( is_wp_error( $ctx ) ) {
			$status = (int) ( $ctx->get_error_data()['status'] ?? 401 );
			$header = 'Bearer resource_metadata="' . OAuth::resource_metadata_url() . '"';
			if ( 'invalid_token' === $ctx->get_error_code() ) {
				$header .= ', error="invalid_token", error_description="' . esc_attr( $ctx->get_error_message() ) . '"';
			}
			if ( 403 === $status ) {
				$header = 'Bearer error="insufficient_scope", resource_metadata="' . OAuth::resource_metadata_url() . '"';
			}
			return $this->respond( Server::error( null, -32001, $ctx->get_error_message() ), $status, array( 'WWW-Authenticate' => $header ) );
		}

		$session = (string) $request->get_header( 'mcp-session-id' );
		if ( 'DELETE' === $request->get_method() ) {
			// Only the user who owns a session can end it.
			$data = Session::get( $session );
			if ( null !== $data && (int) $data['user'] === $ctx->user_id ) {
				Session::delete( $session );
			}
			return $this->respond( null, 202 );
		}

		$protocol = (string) $request->get_header( 'mcp-protocol-version' );
		if ( '' !== $protocol && ! in_array( $protocol, Server::PROTOCOLS, true ) ) {
			return $this->respond( Server::error( null, -32600, 'Unsupported MCP-Protocol-Version: ' . $protocol ), 400 );
		}

		$message = $request->get_json_params();
		if ( ! is_array( $message ) ) {
			return $this->respond( Server::error( null, -32700, 'Parse error: send one JSON-RPC message as the request body.' ), 400 );
		}
		if ( isset( $message[0] ) ) {
			return $this->respond( Server::error( null, -32600, 'Batch requests are not supported.' ), 400 );
		}

		$headers = array();
		if ( 'initialize' === ( $message['method'] ?? '' ) ) {
			$session                   = Session::create( $ctx );
			$headers['Mcp-Session-Id'] = $session;
		} elseif ( '' !== $session ) {
			$data = Session::get( $session );
			if ( null === $data ) {
				return $this->respond( Server::error( $message['id'] ?? null, -32001, 'Session expired. Send initialize again.' ), 404 );
			}
			if ( (int) $data['user'] !== $ctx->user_id ) {
				return $this->respond( Server::error( $message['id'] ?? null, -32001, 'Session belongs to another user.' ), 403 );
			}
			$ctx->client_info = (string) ( $data['client'] ?? '' );
		}

		$server   = new Server( $ctx, $session );
		$response = $server->handle( $message );
		if ( null === $response ) {
			return $this->respond( null, 202, $headers );
		}
		if ( 'initialize' === ( $message['method'] ?? '' ) ) {
			Audit_Log::add(
				array(
					'user_id'  => $ctx->user_id,
					'token_id' => $ctx->token_id,
					'client'   => $ctx->client,
					'method'   => 'initialize',
					'status'   => 'ok',
					'summary'  => 'Session started' . ( ! empty( $message['params']['clientInfo']['name'] ) ? ' by ' . sanitize_text_field( (string) $message['params']['clientInfo']['name'] ) : '' ),
				)
			);
		}
		return $this->respond( $response, 200, $headers );
	}

	/**
	 * 202 responses (notifications) must have an empty body.
	 *
	 * @param bool             $served  Served.
	 * @param mixed            $result  Result.
	 * @param WP_REST_Request  $request Request.
	 */
	public function empty_body( $served, $result, $request = null, $server = null ) {
		if ( $result instanceof \WP_HTTP_Response && 202 === $result->get_status() && $request instanceof WP_REST_Request && '/uncoder/v1/mcp' === $request->get_route() ) {
			return true;
		}
		return $served;
	}

	/**
	 * @param string[] $headers Headers.
	 * @return string[]
	 */
	public function cors_headers( array $headers ): array {
		return array_merge( $headers, array( 'Mcp-Session-Id', 'MCP-Protocol-Version', 'Authorization', 'Last-Event-ID' ) );
	}

	/**
	 * @param string[] $headers Headers.
	 * @return string[]
	 */
	public function exposed_headers( array $headers ): array {
		return array_merge( $headers, array( 'Mcp-Session-Id', 'WWW-Authenticate' ) );
	}

	public function prune(): void {
		Tokens::prune();
		OAuth::prune_clients();
		Audit_Log::prune( (int) Settings::get( 'log_days' ) );
	}
}
