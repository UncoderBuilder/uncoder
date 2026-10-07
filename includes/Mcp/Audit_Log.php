<?php
/**
 * Audit log of every MCP call.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Stores who called which tool, when, from which client, the outcome and a short summary.
 * Arguments are never stored verbatim (they can contain page content); only a summary.
 */
final class Audit_Log {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'uncoder_wb_mcp_log';
	}

	/**
	 * @param array<string,mixed> $entry user_id, token_id, client, method, tool, status, duration_ms, object_id, snapshot, summary.
	 */
	public static function add( array $entry ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'created_at'  => Utils::now_mysql(),
				'user_id'     => (int) ( $entry['user_id'] ?? get_current_user_id() ),
				'token_id'    => (int) ( $entry['token_id'] ?? 0 ),
				'client'      => substr( sanitize_text_field( (string) ( $entry['client'] ?? '' ) ), 0, 190 ),
				'method'      => substr( (string) ( $entry['method'] ?? '' ), 0, 64 ),
				'tool'        => substr( (string) ( $entry['tool'] ?? '' ), 0, 100 ),
				'status'      => substr( (string) ( $entry['status'] ?? 'ok' ), 0, 20 ),
				'duration_ms' => (int) ( $entry['duration_ms'] ?? 0 ),
				'ip'          => Utils::anonymize_ip( Utils::client_ip() ),
				'object_id'   => (int) ( $entry['object_id'] ?? 0 ),
				'snapshot'    => substr( (string) ( $entry['snapshot'] ?? '' ), 0, 64 ),
				'summary'     => isset( $entry['summary'] ) ? mb_substr( wp_strip_all_tags( (string) $entry['summary'] ), 0, 500 ) : null,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string,mixed> $args limit, offset, tool, status, since_id.
	 * @return array<int, array<string,mixed>>
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['tool'] ) ) {
			$where[]  = 'tool = %s';
			$params[] = (string) $args['tool'];
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}
		if ( ! empty( $args['since_id'] ) ) {
			$where[]  = 'id > %d';
			$params[] = (int) $args['since_id'];
		}
		$limit    = max( 1, min( 200, (int) ( $args['limit'] ?? 50 ) ) );
		$offset   = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$params[] = $limit;
		$params[] = $offset;
		// Only the fixed fragments above are joined into the SQL; the table is %i and every value is a placeholder.
		$sql = 'SELECT * FROM %i WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is built from fixed fragments only.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( self::table() ), $params ) ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$user  = get_userdata( (int) $row->user_id );
			$out[] = array(
				'id'        => (int) $row->id,
				'time'      => $row->created_at,
				'user'      => $user ? $user->display_name : '',
				'client'    => $row->client,
				'method'    => $row->method,
				'tool'      => $row->tool,
				'status'    => $row->status,
				'duration'  => (int) $row->duration_ms,
				'object_id' => (int) $row->object_id,
				'snapshot'  => $row->snapshot,
				'summary'   => $row->summary,
			);
		}
		return $out;
	}

	public static function clear(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function prune( int $days ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', self::table(), gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return array{calls:int, errors:int, writes:int}
	 */
	public static function stats( int $hours = 24 ): array {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) calls, SUM(status <> 'ok') errors, SUM(snapshot <> '') writes FROM %i WHERE created_at >= %s AND method = %s", self::table(), $since, 'tools/call' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array(
			'calls'  => (int) ( $row->calls ?? 0 ),
			'errors' => (int) ( $row->errors ?? 0 ),
			'writes' => (int) ( $row->writes ?? 0 ),
		);
	}
}
