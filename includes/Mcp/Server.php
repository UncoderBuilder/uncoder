<?php
/**
 * MCP JSON-RPC method dispatcher.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Implements the MCP server methods over JSON-RPC 2.0.
 */
final class Server {

	public const PROTOCOLS = array( '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' );

	public const SERVER_NAME = 'uncoder';

	private Context $ctx;

	private string $session;

	public function __construct( Context $ctx, string $session ) {
		$this->ctx     = $ctx;
		$this->session = $session;
	}

	/**
	 * Handles one JSON-RPC message. Returns the response array, or null for notifications.
	 *
	 * @param array<string,mixed> $message Message.
	 * @return array<string,mixed>|null
	 */
	public function handle( array $message ): ?array {
		$id     = $message['id'] ?? null;
		$method = is_string( $message['method'] ?? null ) ? $message['method'] : '';
		$params = is_array( $message['params'] ?? null ) ? $message['params'] : array();
		$is_notification = ! array_key_exists( 'id', $message );

		if ( '2.0' !== ( $message['jsonrpc'] ?? '' ) || '' === $method ) {
			return $is_notification ? null : self::error( $id, -32600, 'Invalid Request' );
		}
		if ( 0 === strpos( $method, 'notifications/' ) ) {
			return null;
		}

		try {
			switch ( $method ) {
				case 'initialize':
					$result = $this->initialize( $params );
					break;
				case 'ping':
					$result = new \stdClass();
					break;
				case 'tools/list':
					$result = array( 'tools' => Registry::instance()->describe( $this->ctx ) );
					break;
				case 'tools/call':
					$result = $this->call_tool( $params );
					break;
				case 'resources/list':
					$result = array( 'resources' => Resources::list() );
					break;
				case 'resources/templates/list':
					$result = array( 'resourceTemplates' => Resources::templates() );
					break;
				case 'resources/read':
					$result = Resources::read( (string) ( $params['uri'] ?? '' ) );
					if ( is_wp_error( $result ) ) {
						return self::error( $id, -32002, $result->get_error_message() );
					}
					break;
				case 'prompts/list':
					$result = array( 'prompts' => Prompts::list() );
					break;
				case 'prompts/get':
					$result = Prompts::get( (string) ( $params['name'] ?? '' ), (array) ( $params['arguments'] ?? array() ) );
					if ( is_wp_error( $result ) ) {
						return self::error( $id, -32602, $result->get_error_message() );
					}
					break;
				case 'logging/setLevel':
				case 'completion/complete':
					$result = 'completion/complete' === $method ? array( 'completion' => array( 'values' => array(), 'hasMore' => false ) ) : new \stdClass();
					break;
				default:
					return $is_notification ? null : self::error( $id, -32601, 'Method not found: ' . $method );
			}
		} catch ( \Throwable $e ) {
			return self::error( $id, -32603, 'Internal error: ' . $e->getMessage() );
		}
		return $is_notification ? null : array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function initialize( array $params ): array {
		$requested = (string) ( $params['protocolVersion'] ?? '' );
		$version   = in_array( $requested, self::PROTOCOLS, true ) ? $requested : self::PROTOCOLS[0];
		$info      = (array) ( $params['clientInfo'] ?? array() );
		Session::update(
			$this->session,
			array(
				'protocol' => $version,
				'client'   => sanitize_text_field( trim( ( $info['name'] ?? '' ) . ' ' . ( $info['version'] ?? '' ) ) ),
			)
		);
		return array(
			'protocolVersion' => $version,
			'capabilities'    => array(
				'tools'     => array( 'listChanged' => false ),
				'resources' => array(
					'subscribe'   => false,
					'listChanged' => false,
				),
				'prompts'   => array( 'listChanged' => false ),
				'logging'   => new \stdClass(),
			),
			'serverInfo'      => array(
				'name'       => self::SERVER_NAME,
				'title'      => 'Uncoder — ' . get_bloginfo( 'name' ),
				'version'    => UNCODER_WB_VERSION,
				'websiteUrl' => \Uncoder\Builder\Core\Brand::URL,
				'icons'      => array(
					array(
						'src'      => \Uncoder\Builder\Core\Brand::asset( 'uncoder-favicon.svg' ),
						'mimeType' => 'image/svg+xml',
						'sizes'    => array( 'any' ),
					),
					array(
						'src'      => \Uncoder\Builder\Core\Brand::asset( 'favicon-256.png' ),
						'mimeType' => 'image/png',
						'sizes'    => array( '256x256' ),
					),
				),
			),
			'instructions'    => Guide::instructions(),
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function call_tool( array $params ): array {
		$name   = (string) ( $params['name'] ?? '' );
		$args   = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : array();
		$result = self::execute( $this->ctx, $name, $args );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			return self::tool_error( $result->get_error_message() . ( is_array( $data ) && ! empty( $data['details'] ) ? "
" . implode( "
", array_slice( (array) $data['details'], 0, 40 ) ) : '' ) );
		}
		return array(
			'content'           => array(
				array(
					'type' => 'text',
					'text' => (string) wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				),
			),
			'structuredContent' => (object) $result,
			'isError'           => false,
		);
	}

	/**
	 * Runs a tool for a caller: scope check, rate limit, argument check, audit log, undo info.
	 * Shared by the MCP endpoint and the WordPress Abilities bridge.
	 *
	 * @param array<string,mixed> $args Arguments.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute( Context $ctx, string $name, array $args ) {
		$tool = Registry::instance()->get( $name );
		if ( ! $tool ) {
			return new WP_Error( 'unknown_tool', sprintf( 'Unknown tool "%s". Call tools/list to see available tools.', $name ) );
		}
		$scope = (string) ( $tool['scope'] ?? 'read' );
		if ( ! $ctx->has_scope( $scope ) ) {
			self::log( $ctx, $name, 'denied', 0, 'Missing scope ' . $scope );
			return new WP_Error( 'scope', sprintf( 'This connection was not granted the "%s" permission needed by %s. Reconnect and approve it, or use an API key with that scope.', $scope, $name ) );
		}
		// Role manager: "No access" roles cannot use the server; "Content only" roles can read but not build.
		$access = \Uncoder\Builder\Site\Role_Manager::access();
		if ( 'none' === $access || ( 'content' === $access && 'read' !== $scope ) ) {
			self::log( $ctx, $name, 'denied', 0, 'Role manager: ' . $access );
			return new WP_Error( 'role', 'none' === $access ? 'Your WordPress role has no access to Uncoder (Uncoder → Settings → Access & roles).' : sprintf( 'Your WordPress role can only edit page content in the Uncoder editor, so %s is not available through AI. Ask an administrator to change it under Uncoder → Settings → Roles.', $name ) );
		}
		$limit = Rate_Limiter::hit( 'mcp|' . ( $ctx->token_id ? 't' . $ctx->token_id : 'u' . $ctx->user_id ), (int) Settings::get( 'rate_limit' ), 60 );
		if ( ! $limit['allowed'] ) {
			self::log( $ctx, $name, 'limited', 0, 'Rate limit reached' );
			return new WP_Error( 'rate_limited', sprintf( 'Rate limit reached (%d calls per minute). Wait %d seconds and continue.', (int) Settings::get( 'rate_limit' ), max( 1, $limit['reset'] - time() ) ) );
		}
		$problem = self::check_args( $args, (array) $tool['input'] );
		if ( '' !== $problem ) {
			self::log( $ctx, $name, 'invalid', 0, $problem );
			return new WP_Error( 'invalid_args', $problem );
		}

		$call    = new Call( $ctx, $name );
		$started = microtime( true );
		$source  = \Uncoder\Builder\Core\Document::$source;
		// Open editors show who changed the page ("Updated by Claude").
		\Uncoder\Builder\Core\Document::$source = self::client_name( $ctx );
		$previous_ctx     = Context::$current;
		Context::$current = $ctx;
		try {
			$result = call_user_func( $tool['callback'], $args, $call );
		} catch ( \Throwable $e ) {
			$result = new WP_Error( 'exception', $e->getMessage() );
		}
		\Uncoder\Builder\Core\Document::$source = $source;
		Context::$current                       = $previous_ctx;
		$ms = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $result ) ) {
			self::log( $ctx, $name, 'error', $ms, $result->get_error_message(), $call );
			return $result;
		}
		$result = (array) $result;
		if ( $call->warnings ) {
			$result['warnings'] = array_values( array_unique( array_merge( (array) ( $result['warnings'] ?? array() ), $call->warnings ) ) );
		}
		if ( '' !== $call->snapshot ) {
			$result['undo'] = array(
				'snapshot' => $call->snapshot,
				'hint'     => 'To revert this change call undo_last_change with this snapshot id.',
			);
		}
		self::log( $ctx, $name, 'ok', $ms, $call->summary, $call );
		return $result;
	}

	/**
	 * Friendly name of the calling app ("Claude", "ChatGPT", "Cursor"…) for editor notices.
	 */
	public static function client_name( Context $ctx ): string {
		$raw   = '' !== $ctx->client_info ? $ctx->client_info : $ctx->client;
		$raw   = (string) preg_replace( '/\s+v?[\d.]+$/', '', $raw );
		$known = array(
			'/claude[-\s]?code/i'          => 'Claude Code',
			'/claude/i'                    => 'Claude',
			'/openai|chatgpt/i'            => 'ChatGPT',
			'/cursor/i'                    => 'Cursor',
			'/windsurf|codeium/i'          => 'Windsurf',
			'/visual studio code|vscode/i' => 'VS Code',
			'/copilot/i'                   => 'GitHub Copilot',
			'/gemini/i'                    => 'Gemini',
			'/zed/i'                       => 'Zed',
		);
		foreach ( $known as $pattern => $name ) {
			if ( preg_match( $pattern, $raw ) ) {
				return $name;
			}
		}
		return '' !== trim( $raw ) ? substr( sanitize_text_field( $raw ), 0, 40 ) : 'an AI app';
	}

	private static function log( Context $ctx, string $tool, string $status, int $ms, string $summary, ?Call $call = null ): void {
		Audit_Log::add(
			array(
				'user_id'     => $ctx->user_id,
				'token_id'    => $ctx->token_id,
				'client'      => $ctx->label(),
				'method'      => 'tools/call',
				'tool'        => $tool,
				'status'      => $status,
				'duration_ms' => $ms,
				'object_id'   => $call ? $call->object_id : 0,
				'snapshot'    => $call ? $call->snapshot : '',
				'summary'     => $summary,
			)
		);
	}

	/**
	 * Minimal JSON-Schema validation: required keys and top-level types, with helpful messages.
	 *
	 * @param array<string,mixed> $args   Arguments.
	 * @param array<string,mixed> $schema Schema.
	 */
	private static function check_args( array $args, array $schema ): string {
		foreach ( (array) ( $schema['required'] ?? array() ) as $key ) {
			if ( ! array_key_exists( $key, $args ) ) {
				return sprintf( 'Missing required argument "%s".', $key );
			}
		}
		$props = $schema['properties'] ?? array();
		if ( $props instanceof \stdClass ) {
			$props = (array) $props;
		}
		foreach ( $args as $key => $value ) {
			if ( ! isset( $props[ $key ]['type'] ) ) {
				continue;
			}
			$types = (array) $props[ $key ]['type'];
			$ok    = false;
			foreach ( $types as $type ) {
				if ( ( 'string' === $type && is_string( $value ) ) || ( 'integer' === $type && ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) )
					|| ( 'number' === $type && is_numeric( $value ) ) || ( 'boolean' === $type && is_bool( $value ) ) || ( 'array' === $type && is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) ) )
					|| ( 'object' === $type && is_array( $value ) ) || ( 'null' === $type && null === $value ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				return sprintf( 'Argument "%s" must be of type %s.', $key, implode( ' or ', $types ) );
			}
			if ( isset( $props[ $key ]['enum'] ) && ! in_array( $value, (array) $props[ $key ]['enum'], true ) ) {
				return sprintf( 'Argument "%s" must be one of: %s.', $key, implode( ', ', (array) $props[ $key ]['enum'] ) );
			}
		}
		return '';
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function tool_error( string $message ): array {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => true,
		);
	}

	/**
	 * @param mixed $id Request id.
	 * @return array<string,mixed>
	 */
	public static function error( $id, int $code, string $message ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
