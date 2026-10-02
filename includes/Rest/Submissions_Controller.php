<?php
/**
 * Form submissions for the admin screen.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use Uncoder\Builder\Forms\Forms;
use Uncoder\Builder\Forms\Uploads;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET /submissions, POST /submissions/bulk, GET/POST/DELETE /submissions/{id}, GET /submissions/export.
 * Reading and triaging needs edit_pages; deleting and exporting (personal data) needs manage_options.
 */
final class Submissions_Controller {

	public const STATUSES = array( 'unread', 'read', 'spam' );

	private const EXPORT_LIMIT = 5000;

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'uncoder_wb_submissions';
	}

	public function register_routes(): void {
		$read  = static fn() => current_user_can( 'edit_pages' );
		$admin = static fn() => current_user_can( 'manage_options' );
		$args  = array(
			'status'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'form'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'post_id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'search'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
		register_rest_route(
			Rest::NS,
			'/submissions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_items' ),
				'permission_callback' => $read,
				'args'                => array_merge(
					$args,
					array(
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 25,
							'sanitize_callback' => 'absint',
						),
					)
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/submissions/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export' ),
				'permission_callback' => $admin,
				'args'                => $args,
			)
		);
		register_rest_route(
			Rest::NS,
			'/submissions/bulk',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk' ),
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$body = $request->get_json_params();
					// Normalize exactly like bulk() does, so "DELETE" cannot skip the stricter check.
					$action = is_array( $body ) ? sanitize_key( (string) ( $body['action'] ?? '' ) ) : '';
					if ( 'delete' === $action ) {
						return current_user_can( 'manage_options' );
					}
					return current_user_can( 'edit_pages' );
				},
			)
		);
		register_rest_route(
			Rest::NS,
			'/submissions/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $admin,
				),
			)
		);
	}

	/**
	 * WHERE clause and parameters for the list filters.
	 *
	 * @return array{0:string,1:array<int,mixed>}
	 */
	private function where( WP_REST_Request $request ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		$status = (string) $request->get_param( 'status' );
		if ( in_array( $status, self::STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		} else {
			// "All" leaves spam out, like the comments screen.
			$where[]  = 'status <> %s';
			$params[] = 'spam';
		}
		$form = (string) $request->get_param( 'form' );
		if ( '' !== $form ) {
			$where[]  = 'form_name = %s';
			$params[] = $form;
		}
		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id > 0 ) {
			$where[]  = 'post_id = %d';
			$params[] = $post_id;
		}
		$search = trim( (string) $request->get_param( 'search' ) );
		if ( '' !== $search ) {
			$where[]  = '(data LIKE %s OR form_name LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * @param array<string,mixed> $row DB row.
	 * @return array<string,mixed>
	 */
	private function present( array $row ): array {
		$post_id = (int) $row['post_id'];
		$post    = $post_id ? get_post( $post_id ) : null;
		$data    = json_decode( (string) $row['data'], true );
		$meta    = json_decode( (string) ( $row['meta'] ?? '' ), true );
		return array(
			'id'        => (int) $row['id'],
			'postId'    => $post_id,
			'postTitle' => $post ? html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) : '',
			'postUrl'   => $post ? (string) get_permalink( $post ) : '',
			'elementId' => (string) $row['element_id'],
			'form'      => (string) $row['form_name'],
			'data'      => is_array( $data ) ? self::present_files( (int) $row['id'], $data ) : array(),
			'meta'      => is_array( $meta ) ? $meta : array(),
			'status'    => (string) $row['status'],
			// Stored in UTC (Utils::now_mysql()).
			'createdAt' => gmdate( 'c', (int) strtotime( (string) $row['created_at'] . ' UTC' ) ),
		);
	}

	/**
	 * File entries: the stored path inside the protected folder is never sent; users who may download
	 * form files get the admin download link (capability-checked endpoint) instead.
	 *
	 * @param array<int|string, mixed> $data Stored data entries.
	 * @return array<int|string, mixed>
	 */
	private static function present_files( int $id, array $data ): array {
		$can = current_user_can( Uploads::capability() );
		foreach ( $data as &$entry ) {
			if ( ! is_array( $entry ) || 'file' !== ( $entry['type'] ?? '' ) || ! isset( $entry['file'] ) ) {
				continue;
			}
			$stored = ! empty( $entry['file']['path'] );
			unset( $entry['file'] );
			if ( $stored && $can && '' !== (string) ( $entry['id'] ?? '' ) ) {
				$entry['fileUrl'] = Forms::file_url( $id, (string) $entry['id'] );
			}
		}
		unset( $entry );
		return $data;
	}

	/**
	 * @return array<string,int>
	 */
	private function counts(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i GROUP BY status', self::table() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array(
			'all'    => 0,
			'unread' => 0,
			'read'   => 0,
			'spam'   => 0,
		);
		foreach ( (array) $rows as $row ) {
			$status = (string) $row['status'];
			$n      = (int) $row['n'];
			if ( isset( $out[ $status ] ) ) {
				$out[ $status ] = $n;
			}
			if ( 'spam' !== $status ) {
				$out['all'] += $n;
			}
		}
		return $out;
	}

	/**
	 * Distinct forms (name + page) for the filters.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private function forms(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT form_name, post_id, COUNT(*) AS n FROM %i GROUP BY form_name, post_id ORDER BY n DESC LIMIT 200', self::table() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$post_id = (int) $row['post_id'];
			$out[]   = array(
				'form'      => (string) $row['form_name'],
				'postId'    => $post_id,
				'postTitle' => $post_id ? html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) : '',
				'count'     => (int) $row['n'],
			);
		}
		return $out;
	}

	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		list( $where, $params ) = $this->where( $request );
		$per_page               = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$page                   = max( 1, (int) $request->get_param( 'page' ) );
		$table                  = self::table();
		// $where only joins the fixed fragments of where(); the table is %i and every value is a placeholder.
		$count_sql = 'SELECT COUNT(*) FROM %i WHERE ' . $where;
		$list_sql  = 'SELECT * FROM %i WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $count_sql is built from fixed fragments only.
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( array( $table ), $params ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $list_sql is built from fixed fragments only.
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( array( $table ), $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );

		return new WP_REST_Response(
			array(
				'items'   => array_map( array( $this, 'present' ), (array) $rows ),
				'total'   => $total,
				'page'    => $page,
				'pages'   => (int) max( 1, ceil( $total / $per_page ) ),
				'counts'  => $this->counts(),
				'forms'   => $this->forms(),
				'canEdit' => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function row( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_array( $row ) ? $row : null;
	}

	private function not_found(): WP_Error {
		return new WP_Error( 'uncoder_not_found', __( 'Submission not found.', 'uncoder' ), array( 'status' => 404 ) );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = $this->row( (int) $request['id'] );
		return $row ? new WP_REST_Response( $this->present( $row ) ) : $this->not_found();
	}

	/**
	 * Body: { status: unread|read|spam }.
	 */
	public function update( WP_REST_Request $request ) {
		global $wpdb;
		$id  = (int) $request['id'];
		$row = $this->row( $id );
		if ( ! $row ) {
			return $this->not_found();
		}
		$body   = $request->get_json_params();
		$status = is_array( $body ) ? sanitize_key( (string) ( $body['status'] ?? '' ) ) : '';
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new WP_Error( 'uncoder_invalid_status', __( 'Status must be unread, read or spam.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row['status'] = $status;
		return new WP_REST_Response( $this->present( $row ) );
	}

	/**
	 * Removes uploaded files of submissions that are about to be deleted.
	 *
	 * @param int[] $ids Submission ids.
	 */
	private static function delete_files( array $ids ): void {
		global $wpdb;
		if ( ! $ids || ! class_exists( '\Uncoder\Builder\Forms\Uploads' ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT data FROM %i WHERE id IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( self::table() ), $ids ) ) );
		foreach ( (array) $rows as $raw ) {
			$data = json_decode( (string) $raw, true );
			if ( is_array( $data ) ) {
				\Uncoder\Builder\Forms\Uploads::delete_for( $data );
			}
		}
	}

	public function delete( WP_REST_Request $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		if ( ! $this->row( $id ) ) {
			return $this->not_found();
		}
		self::delete_files( array( $id ) );
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * Body: { ids: int[], action: read|unread|spam|delete }.
	 */
	public function bulk( WP_REST_Request $request ) {
		global $wpdb;
		$body   = $request->get_json_params();
		$body   = is_array( $body ) ? $body : array();
		$action = sanitize_key( (string) ( $body['action'] ?? '' ) );
		$ids    = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) ( $body['ids'] ?? array() ) ) ) ) ), 0, 500 );
		if ( ! $ids ) {
			return new WP_Error( 'uncoder_no_ids', __( 'No submissions selected.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$table = self::table();
		if ( 'delete' === $action ) {
			self::delete_files( $ids );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$count = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( $table ), $ids ) ) );
		} elseif ( in_array( $action, self::STATUSES, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$count = (int) $wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s WHERE id IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( $table, $action ), $ids ) ) );
		} else {
			return new WP_Error( 'uncoder_invalid_action', __( 'Unknown bulk action.', 'uncoder' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response(
			array(
				'action' => $action,
				'count'  => $count,
			)
		);
	}

	/**
	 * Every matching submission (up to 5000) for a client-side CSV download.
	 */
	public function export( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		list( $where, $params ) = $this->where( $request );
		// $where only joins the fixed fragments of where(); the table is %i and every value is a placeholder.
		$sql = 'SELECT * FROM %i WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is built from fixed fragments only.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( self::table() ), $params, array( self::EXPORT_LIMIT ) ) ), ARRAY_A );
		return new WP_REST_Response(
			array(
				'items'     => array_map( array( $this, 'present' ), (array) $rows ),
				'truncated' => count( (array) $rows ) >= self::EXPORT_LIMIT,
			)
		);
	}
}
