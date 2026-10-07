<?php
/**
 * Preview in a new tab, with unsaved changes.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Editor;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The editor stores an autosave, then opens the page with `uncoder_draft={nonce}`: for that user only (valid
 * nonce, edit rights, their own autosave) the page renders the autosaved tree, with CSS built in memory.
 * With `uncoder_rev={revision id}` it renders that saved version instead (History → Saved versions).
 */
final class Draft_Preview {

	public const QUERY_VAR = 'uncoder_draft';
	public const NONCE     = 'uncoder_draft_';

	public function register(): void {
		add_action( 'wp', array( $this, 'setup' ) );
	}

	public function setup(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified below.
		$nonce = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';
		if ( '' === $nonce || ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! $post_id || ! wp_verify_nonce( $nonce, self::NONCE . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! Utils::is_builder_post( $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$rev = isset( $_GET['uncoder_rev'] ) ? absint( $_GET['uncoder_rev'] ) : 0;
		if ( $rev ) {
			$revision = wp_get_post_revision( $rev ); // $rev is a variable: the function takes it by reference.
			if ( ! $revision || (int) $revision->post_parent !== $post_id ) {
				return;
			}
			// Stored by a save, so sanitized then.
			$raw      = get_metadata( 'post', $revision->ID, Utils::META_DATA, true );
			$data     = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
			$elements = is_array( $data['elements'] ?? null ) ? $data['elements'] : ( is_array( $data ) && isset( $data[0] ) ? $data : null );
		} else {
			$autosave = get_post_meta( $post_id, '_uncoder_wb_autosave', true );
			if ( ! is_array( $autosave ) || (int) ( $autosave['user'] ?? 0 ) !== get_current_user_id() ) {
				return;
			}
			// Sanitized when it was stored (Documents_Controller::autosave()).
			$elements = json_decode( (string) ( $autosave['elements'] ?? '' ), true );
		}
		$doc      = Plugin::instance()->documents()->get( $post_id );
		if ( ! is_array( $elements ) || ! $doc ) {
			return;
		}
		$doc->use_draft( $elements );
		nocache_headers();
	}
}
