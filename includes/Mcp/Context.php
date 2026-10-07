<?php
/**
 * Authenticated MCP caller.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * The user, granted scopes and client of the current MCP request.
 */
final class Context {

	/** The caller of the tool that is running (set by Server::execute). */
	public static ?Context $current = null;

	public int $user_id;

	/** @var string[] */
	public array $scopes;

	public int $token_id;

	public string $client;

	public string $via;

	/** Client info reported in initialize (name/version). */
	public string $client_info = '';

	/**
	 * @param string[] $scopes Scopes.
	 */
	public function __construct( int $user_id, array $scopes, int $token_id, string $client, string $via ) {
		$this->user_id  = $user_id;
		$this->scopes   = Tokens::clean_scopes( $scopes );
		$this->token_id = $token_id;
		$this->client   = $client;
		$this->via      = $via;
	}

	public function has_scope( string $scope ): bool {
		return in_array( $scope, $this->scopes, true );
	}

	public function label(): string {
		return '' !== $this->client_info ? $this->client_info . ' · ' . $this->client : $this->client;
	}
}
