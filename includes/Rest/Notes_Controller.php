<?php
/**
 * Notes on elements (editor → Notes).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /documents/{id}/notes, POST/DELETE /documents/{id}/notes/{note}. Notes are comments for the team
 * on one element of a page (post meta `_uncoder_wb_notes`): text, author, time, resolved. Anyone who can edit
 * the page reads, adds and resolves them; the author (or an editor of others' posts) deletes them.
 */
final class Notes_Controller {

	public const META = '_uncoder_wb_notes';

	public function register_routes(): void {
		register_rest_route(
			Rest::NS,
			'/documents/(?P<id>\d+)/notes',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_notes' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/documents/(?P<id>\d+)/notes/(?P<note>[a-z0-9]+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
	}

	public function can_edit( WP_REST_Request $request ): bool {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private static function all( int $post_id ): array {
		$notes = get_post_meta( $post_id, self::META, true );
		return is_array( $notes ) ? array_values( array_filter( $notes, static fn( $n ) => is_array( $n ) && isset( $n['id'] ) ) ) : array();
	}

	/**
	 * @param array<int, array<string,mixed>> $notes Notes.
	 */
	private static function store( int $post_id, array $notes ): void {
		update_post_meta( $post_id, self::META, wp_slash( array_values( $notes ) ) );
	}

	/**
	 * A note for the editor: with the author's name and avatar, and whether this user may delete it.
	 *
	 * @param array<string,mixed> $note Stored note.
	 * @return array<string,mixed>
	 */
	private static function present( array $note ): array {
		$user = get_userdata( (int) ( $note['author'] ?? 0 ) );
		return array(
			'id'        => (string) $note['id'],
			'element'   => (string) ( $note['element'] ?? '' ),
			'text'      => (string) ( $note['text'] ?? '' ),
			'time'      => (int) ( $note['time'] ?? 0 ),
			'resolved'  => ! empty( $note['resolved'] ),
			'author'    => $user ? $user->display_name : __( 'Someone', 'uncoder' ),
			'avatar'    => $user ? (string) get_avatar_url( $user->ID, array( 'size' => 48 ) ) : '',
			'canDelete' => get_current_user_id() === (int) ( $note['author'] ?? 0 ) || current_user_can( 'edit_others_posts' ),
		);
	}

	public function list_notes( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array_map( array( self::class, 'present' ), self::all( (int) $request['id'] ) ) );
	}

	public function create( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$element = strtolower( (string) $request->get_param( 'element' ) );
		$text    = trim( sanitize_textarea_field( (string) $request->get_param( 'text' ) ) );
		if ( ! \Uncoder\Builder\Core\Utils::is_valid_id( $element ) || '' === $text ) {
			return new WP_Error( 'uncoder_bad_note', __( 'A note needs an element and some text.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$note    = array(
			'id'       => strtolower( wp_generate_password( 10, false ) ),
			'element'  => $element,
			'text'     => mb_substr( $text, 0, 2000 ),
			'author'   => get_current_user_id(),
			'time'     => time(),
			'resolved' => false,
		);
		$notes   = self::all( $id );
		$notes[] = $note;
		self::store( $id, $notes );
		return new WP_REST_Response( self::present( $note ) );
	}

	public function update( WP_REST_Request $request ) {
		$id    = (int) $request['id'];
		$notes = self::all( $id );
		foreach ( $notes as $i => $note ) {
			if ( (string) $note['id'] !== (string) $request['note'] ) {
				continue;
			}
			if ( null !== $request->get_param( 'resolved' ) ) {
				$notes[ $i ]['resolved'] = (bool) $request->get_param( 'resolved' );
			}
			$text = $request->get_param( 'text' );
			if ( is_string( $text ) && '' !== trim( $text ) && get_current_user_id() === (int) $note['author'] ) {
				$notes[ $i ]['text'] = mb_substr( trim( sanitize_textarea_field( $text ) ), 0, 2000 );
			}
			self::store( $id, $notes );
			return new WP_REST_Response( self::present( $notes[ $i ] ) );
		}
		return new WP_Error( 'uncoder_no_note', __( 'That note does not exist.', 'uncoder' ), array( 'status' => 404 ) );
	}

	public function delete( WP_REST_Request $request ) {
		$id    = (int) $request['id'];
		$notes = self::all( $id );
		foreach ( $notes as $i => $note ) {
			if ( (string) $note['id'] !== (string) $request['note'] ) {
				continue;
			}
			if ( get_current_user_id() !== (int) $note['author'] && ! current_user_can( 'edit_others_posts' ) ) {
				return new WP_Error( 'uncoder_note_forbidden', __( 'Only its author can delete this note.', 'uncoder' ), array( 'status' => 403 ) );
			}
			unset( $notes[ $i ] );
			self::store( $id, $notes );
			return new WP_REST_Response( array( 'deleted' => true ) );
		}
		return new WP_Error( 'uncoder_no_note', __( 'That note does not exist.', 'uncoder' ), array( 'status' => 404 ) );
	}
}
