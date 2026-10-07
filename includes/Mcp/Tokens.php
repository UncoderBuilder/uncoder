<?php
/**
 * API keys and OAuth tokens (stored hashed).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Token types: api_key, link (an API key made to be pasted as one URL: ?token=), access, refresh, code. Secrets
 * are never stored, only an HMAC.
 */
final class Tokens {

	public const SCOPES = array(
		'read'    => 'Read pages, templates, media, menus and settings',
		'content' => 'Create and edit pages, posts, media and their SEO title and description',
		'design'  => 'Change the Design System, theme templates, popups and custom CSS',
		'site'    => 'Change site settings and menus',
	);

	public const DEFAULT_SCOPES = array( 'read', 'content', 'design' );

	/**
	 * Translated descriptions of the scopes (for the consent screen and the admin app).
	 *
	 * @return array<string,string>
	 */
	public static function scope_labels(): array {
		return array(
			'read'    => __( 'Read pages, templates, media, menus and settings', 'uncoder' ),
			'content' => __( 'Create and edit pages, posts, media and their SEO title and description', 'uncoder' ),
			'design'  => __( 'Change the Design System, theme templates, popups and custom CSS', 'uncoder' ),
			'site'    => __( 'Change site settings and menus', 'uncoder' ),
		);
	}

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'uncoder_wb_tokens';
	}

	public static function hash( string $secret ): string {
		return hash_hmac( 'sha256', $secret, wp_salt( 'auth' ) . 'uncoder_wb' );
	}

	public static function random( string $prefix, int $bytes = 30 ): string {
		return $prefix . rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- token encoding.
	}

	/**
	 * @param string[] $scopes Scopes.
	 * @return string[]
	 */
	public static function clean_scopes( array $scopes ): array {
		return array_values( array_intersect( array_keys( self::SCOPES ), array_map( 'strval', $scopes ) ) );
	}

	/**
	 * Creates a token and returns [ secret, id ].
	 *
	 * @param array<string,mixed> $args type, user_id, scopes, name, client_id, ttl, parent_id, meta.
	 * @return array{0:string,1:int}
	 */
	public static function create( array $args ): array {
		global $wpdb;
		$prefix = array(
			'api_key' => 'uncoder_key_',
			'link'    => 'uncoder_link_',
			'access'  => 'uncoder_at_',
			'refresh' => 'uncoder_rt_',
			'code'    => 'uncoder_ac_',
		)[ $args['type'] ] ?? 'uncoder_';
		$secret = self::random( $prefix );
		$ttl    = isset( $args['ttl'] ) ? (int) $args['ttl'] : 0;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'type'       => $args['type'],
				'token_hash' => self::hash( $secret ),
				'token_hint' => substr( $secret, -4 ),
				'name'       => sanitize_text_field( (string) ( $args['name'] ?? '' ) ),
				'client_id'  => (string) ( $args['client_id'] ?? '' ),
				'user_id'    => (int) ( $args['user_id'] ?? 0 ),
				'scopes'     => implode( ' ', self::clean_scopes( (array) ( $args['scopes'] ?? array() ) ) ),
				'parent_id'  => (int) ( $args['parent_id'] ?? 0 ),
				'meta'       => isset( $args['meta'] ) ? wp_json_encode( $args['meta'] ) : null,
				'created_at' => Utils::now_mysql(),
				'expires_at' => $ttl > 0 ? gmdate( 'Y-m-d H:i:s', time() + $ttl ) : null,
			)
		);
		return array( $secret, (int) $wpdb->insert_id );
	}

	/**
	 * Finds a valid (not revoked, not expired) token by secret.
	 *
	 * @return object|null Row.
	 */
	public static function find( string $secret, string $type ) {
		global $wpdb;
		if ( strlen( $secret ) < 20 || strlen( $secret ) > 200 ) {
			return null;
		}
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM %i WHERE token_hash = %s AND type = %s LIMIT 1', self::table(), self::hash( $secret ), $type )
		);
		if ( ! $row || (int) $row->revoked ) {
			return null;
		}
		if ( $row->expires_at && strtotime( $row->expires_at . ' UTC' ) < time() ) {
			return null;
		}
		return $row;
	}

	public static function touch( int $id ): void {
		global $wpdb;
		// Only write once a minute per token to keep hot paths cheap.
		$key = 'uncoder_wb_tok_touch_' . $id;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, MINUTE_IN_SECONDS );
		$wpdb->update( self::table(), array( 'last_used_at' => Utils::now_mysql() ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function revoke( int $id ): void {
		global $wpdb;
		if ( $id <= 0 ) {
			return; // parent_id 0 would match every top-level token.
		}
		$wpdb->update( self::table(), array( 'revoked' => 1 ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( self::table(), array( 'revoked' => 1 ), array( 'parent_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function revoke_client( string $client_id, int $user_id = 0 ): void {
		global $wpdb;
		if ( '' === $client_id ) {
			return; // API keys have no client id.
		}
		$where = array( 'client_id' => $client_id );
		if ( $user_id ) {
			$where['user_id'] = $user_id;
		}
		$wpdb->update( self::table(), array( 'revoked' => 1 ), $where ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * API keys for the admin screen.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function list_keys(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, type, name, token_hint, user_id, scopes, created_at, expires_at, last_used_at, revoked FROM %i WHERE type IN ('api_key','link') ORDER BY id DESC LIMIT 200", self::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( array( self::class, 'present' ), (array) $rows );
	}

	/**
	 * OAuth grants grouped by client and user (active refresh tokens).
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function list_grants(): array {
		global $wpdb;
		$now  = Utils::now_mysql();
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT t.client_id, t.user_id, MAX(t.scopes) AS scopes, MIN(t.created_at) AS created_at, MAX(t.last_used_at) AS last_used_at, c.client_name FROM %i t LEFT JOIN %i c ON c.client_id = t.client_id WHERE t.type IN (\'refresh\',\'access\') AND t.revoked = 0 AND (t.expires_at IS NULL OR t.expires_at > %s) GROUP BY t.client_id, t.user_id, c.client_name ORDER BY last_used_at DESC',
				self::table(),
				$wpdb->prefix . 'uncoder_wb_oauth_clients',
				$now
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$user  = get_userdata( (int) $row->user_id );
			$out[] = array(
				'client_id'   => $row->client_id,
				'client_name' => $row->client_name ? $row->client_name : self::client_label( (string) $row->client_id ),
				'user'        => $user ? $user->display_name : '',
				'user_id'     => (int) $row->user_id,
				'scopes'      => array_filter( explode( ' ', (string) $row->scopes ) ),
				'created_at'  => $row->created_at,
				'last_used'   => $row->last_used_at,
			);
		}
		return $out;
	}

	public static function client_label( string $client_id ): string {
		if ( 0 === strpos( $client_id, 'https://' ) ) {
			$host = wp_parse_url( $client_id, PHP_URL_HOST );
			return $host ? (string) $host : $client_id;
		}
		return $client_id;
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function present( $row ): array {
		$user = get_userdata( (int) $row->user_id );
		return array(
			'id'         => (int) $row->id,
			'name'       => $row->name,
			'link'       => 'link' === ( $row->type ?? '' ),
			'hint'       => $row->token_hint,
			'user'       => $user ? $user->display_name : '',
			'scopes'     => array_filter( explode( ' ', (string) $row->scopes ) ),
			'created_at' => $row->created_at,
			'expires_at' => $row->expires_at,
			'last_used'  => $row->last_used_at,
			'revoked'    => (bool) $row->revoked,
			'expired'    => $row->expires_at && strtotime( $row->expires_at . ' UTC' ) < time(),
		);
	}

	/**
	 * Deletes expired codes/access tokens and long-revoked rows.
	 */
	public static function prune(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE type IN ('code','access') AND expires_at < %s", self::table(), gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE type = 'refresh' AND (revoked = 1 OR expires_at < %s) AND created_at < %s", self::table(), Utils::now_mysql(), gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
