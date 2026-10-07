<?php
/**
 * MCP sessions (Mcp-Session-Id) stored in transients.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * A session binds a session id to the user and token that created it.
 */
final class Session {

	public const TTL = DAY_IN_SECONDS;

	private static function key( string $id ): string {
		return 'uncoder_wb_mcps_' . md5( $id );
	}

	public static function create( Context $ctx ): string {
		$id = bin2hex( random_bytes( 16 ) );
		set_transient(
			self::key( $id ),
			array(
				'user'    => $ctx->user_id,
				'token'   => $ctx->token_id,
				'created' => time(),
			),
			self::TTL
		);
		return $id;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( string $id ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			return null;
		}
		$data = get_transient( self::key( $id ) );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * @param array<string,mixed> $values Values.
	 */
	public static function update( string $id, array $values ): void {
		$data = self::get( $id );
		if ( null === $data ) {
			return;
		}
		set_transient( self::key( $id ), array_merge( $data, $values ), self::TTL );
	}

	public static function delete( string $id ): void {
		if ( preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			delete_transient( self::key( $id ) );
		}
	}
}
