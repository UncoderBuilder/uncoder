<?php
/**
 * Submissions table access ({prefix}uncoder_wb_submissions).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Forms;

use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Insert / update / cleanup of stored submissions.
 *
 * Row shape: post_id = document holding the form (page or template), element_id = form element,
 * data = JSON [{id,label,type,value[,file:{path,size,mime}]}], meta = JSON {ip, user_agent,
 * page_id, page_url, user_id, actions, spam?}, status = unread|read|spam.
 */
final class Store {

	public const STATUSES = array( 'unread', 'read', 'spam' );

	/** Unread submissions (indexed count; used for the admin menu bubble). */
	public static function unread_count(): int {
		global $wpdb;
		$table = self::table();
		$wpdb->suppress_errors( true );
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, 'unread' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->suppress_errors( false );
		return $count;
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'uncoder_wb_submissions';
	}

	/**
	 * @param array<int, array<string,mixed>> $data Data entries.
	 * @param array<string,mixed>             $meta Meta.
	 * @return int Inserted id (0 on failure).
	 */
	public static function insert( int $doc_id, string $element_id, string $form_name, array $data, array $meta, string $status = 'unread' ): int {
		global $wpdb;
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'post_id'    => $doc_id,
				'element_id' => substr( $element_id, 0, 32 ),
				'form_name'  => mb_substr( $form_name, 0, 191 ),
				'data'       => (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'meta'       => (string) wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'status'     => in_array( $status, self::STATUSES, true ) ? $status : 'unread',
				'created_at' => Utils::now_mysql(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param array<int, array<string,mixed>> $data Data entries.
	 */
	public static function update_data( int $id, array $data ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::table(),
			array( 'data' => (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param array<string,mixed> $meta Meta.
	 */
	public static function update_meta( int $id, array $meta ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::table(),
			array( 'meta' => (string) wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Data entry of a file field in a stored submission.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function file_entry( int $id, string $field_id ): ?array {
		global $wpdb;
		if ( $id <= 0 || '' === $field_id ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$raw  = $wpdb->get_var( $wpdb->prepare( 'SELECT data FROM %i WHERE id = %d', self::table(), $id ) );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		foreach ( is_array( $data ) ? $data : array() as $entry ) {
			if ( is_array( $entry ) && ( $entry['id'] ?? '' ) === $field_id && 'file' === ( $entry['type'] ?? '' ) && ! empty( $entry['file']['path'] ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Daily: deletes spam older than N days (default 30) and, when a retention period is set, every
	 * submission older than that — with their files.
	 */
	public static function cleanup(): int {
		/**
		 * Days to keep submissions marked as spam.
		 *
		 * @param int $days Days.
		 */
		$spam_days = max( 1, (int) apply_filters( 'uncoder_wb/forms/spam_retention_days', 30 ) );
		$deleted   = self::delete_older( $spam_days, 'spam' );
		/**
		 * Days to keep every submission (0 = keep until deleted by hand, the default; Settings → Forms →
		 * Stored submissions). Personal data should not be kept longer than needed.
		 *
		 * @param int $days Days.
		 */
		$settings = (array) get_option( 'uncoder_wb_settings', array() );
		$days     = max( 0, (int) apply_filters( 'uncoder_wb/forms/retention_days', (int) ( $settings['form_retention_days'] ?? 0 ) ) );
		if ( $days > 0 ) {
			$deleted += self::delete_older( $days, '' );
		}
		return $deleted;
	}

	/**
	 * Deletes submissions (of one status, or all when '') created more than $days days ago, and their files.
	 */
	private static function delete_older( int $days, string $status ): int {
		global $wpdb;
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		if ( '' !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, data FROM %i WHERE status = %s AND created_at < %s LIMIT 5000', $table, $status, $cutoff ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, data FROM %i WHERE created_at < %s LIMIT 5000', $table, $cutoff ), ARRAY_A );
		}
		if ( ! $rows ) {
			return 0;
		}
		$ids = array();
		foreach ( $rows as $row ) {
			$data = json_decode( (string) $row['data'], true );
			if ( is_array( $data ) ) {
				Uploads::delete_for( $data );
			}
			$ids[] = (int) $row['id'];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( $table ), $ids ) ) );
	}
}
