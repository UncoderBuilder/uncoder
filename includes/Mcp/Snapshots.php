<?php
/**
 * Restores snapshots taken before AI changes.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Snapshot ids: "p{post_id}-…" (a post's builder state) or "kit:{kit snapshot id}".
 */
final class Snapshots {

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function restore( string $snapshot ) {
		if ( 0 === strpos( $snapshot, 'kit:' ) ) {
			if ( ! current_user_can( 'edit_theme_options' ) ) {
				return new WP_Error( 'forbidden', 'You cannot change the Design System.' );
			}
			// Same rule as update_design_system: an AI connection needs the "design" permission.
			if ( Context::$current && ! Context::$current->has_scope( 'design' ) ) {
				return new WP_Error( 'forbidden', 'The Design System needs the "design" permission, which this connection was not granted.' );
			}
			$ok = Plugin::instance()->kit()->restore( substr( $snapshot, 4 ) );
			return $ok ? array( 'restored' => 'design_system' ) : new WP_Error( 'not_found', 'Design system snapshot not found.' );
		}
		if ( ! preg_match( '/^p(\d+)-/', $snapshot, $m ) ) {
			return new WP_Error( 'invalid', 'Unknown snapshot id.' );
		}
		$post_id = (int) $m[1];
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden', 'You cannot edit this item.' );
		}
		if ( \Uncoder\Builder\Core\Post_Types::TEMPLATE === get_post_type( $post_id ) && Context::$current && ! Context::$current->has_scope( 'design' ) ) {
			return new WP_Error( 'forbidden', 'Theme templates need the "design" permission, which this connection was not granted.' );
		}
		$list = get_post_meta( $post_id, '_uncoder_wb_snapshots', true );
		foreach ( (array) $list as $snap ) {
			if ( ( $snap['id'] ?? '' ) !== $snapshot ) {
				continue;
			}
			// Snapshot the current state first, so a restore can itself be undone.
			$ctx  = new Context( get_current_user_id(), array_keys( Tokens::SCOPES ), 0, 'restore', 'internal' );
			$call = new Call( $ctx, 'restore_snapshot' );
			$call->snapshot_post( $post_id );

			update_post_meta( $post_id, Utils::META_DATA, wp_slash( (string) $snap['data'] ) );
			if ( is_array( $snap['page'] ?? null ) ) {
				update_post_meta( $post_id, Utils::META_PAGE, $snap['page'] );
			}
			if ( isset( $snap['template'] ) && '' !== $snap['template'] ) {
				update_post_meta( $post_id, '_wp_page_template', $snap['template'] );
			}
			if ( isset( $snap['conds'] ) && is_array( $snap['conds'] ) ) {
				update_post_meta( $post_id, Utils::META_CONDS, $snap['conds'] );
			}
			if ( isset( $snap['tpl'] ) && is_array( $snap['tpl'] ) ) {
				update_post_meta( $post_id, Utils::META_TPL, $snap['tpl'] );
			}
			// Never let undo publish something the user could not publish themselves.
			$status = sanitize_key( (string) $snap['status'] );
			$pto    = get_post_type_object( (string) get_post_type( $post_id ) );
			if ( in_array( $status, array( 'publish', 'private', 'future' ), true ) && ( ! $pto || ! current_user_can( $pto->cap->publish_posts ) || ! current_user_can( 'publish_post', $post_id ) ) ) {
				$status = get_post_status( $post_id ) === 'publish' ? 'publish' : 'pending';
			}
			wp_update_post(
				wp_slash(
					array(
						'ID'          => $post_id,
						'post_title'  => (string) $snap['title'],
						'post_status' => $status,
					)
				)
			);
			$doc = Plugin::instance()->documents()->get( $post_id );
			Plugin::instance()->documents()->forget( $post_id );
			$doc = Plugin::instance()->documents()->get( $post_id );
			if ( $doc ) {
				$doc->regenerate();
				$doc->touch();
			}
			do_action( 'uncoder_wb/document/saved', $doc );
			return array(
				'restored'      => $post_id,
				'snapshot'      => $snapshot,
				'undo_snapshot' => $call->snapshot,
			);
		}
		return new WP_Error( 'not_found', 'Snapshot not found (only the last 15 per page are kept).' );
	}

	/**
	 * Most recent AI snapshot for an object (or overall for the current user).
	 */
	public static function latest( int $post_id = 0 ): string {
		global $wpdb;
		$table = $wpdb->prefix . 'uncoder_wb_mcp_log';
		if ( $post_id ) {
			$row = $wpdb->get_var( $wpdb->prepare( "SELECT snapshot FROM %i WHERE object_id = %d AND snapshot <> '' ORDER BY id DESC LIMIT 1", $table, $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$row = $wpdb->get_var( $wpdb->prepare( "SELECT snapshot FROM %i WHERE user_id = %d AND snapshot <> '' AND tool <> 'restore_snapshot' ORDER BY id DESC LIMIT 1", $table, get_current_user_id() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return (string) $row;
	}
}
