<?php
/**
 * State of one tool invocation (for snapshots and the audit log).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Tools report the object they changed and take snapshots through this object.
 */
final class Call {

	public Context $ctx;

	public string $tool;

	public int $object_id = 0;

	public string $snapshot = '';

	public string $summary = '';

	/** @var string[] */
	public array $warnings = array();

	public function __construct( Context $ctx, string $tool ) {
		$this->ctx  = $ctx;
		$this->tool = $tool;
	}

	/**
	 * Saves the current builder state of a post so this change can be undone.
	 */
	public function snapshot_post( int $post_id ): string {
		if ( ! Settings::get( 'snapshots' ) ) {
			return '';
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$id   = 'p' . $post_id . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
		$list = get_post_meta( $post_id, '_uncoder_wb_snapshots', true );
		$list = is_array( $list ) ? $list : array();
		array_unshift(
			$list,
			array(
				'id'       => $id,
				'time'     => time(),
				'tool'     => $this->tool,
				'client'   => $this->ctx->label(),
				'user'     => $this->ctx->user_id,
				'title'    => $post->post_title,
				'status'   => $post->post_status,
				'data'     => (string) get_post_meta( $post_id, Utils::META_DATA, true ),
				'page'     => get_post_meta( $post_id, Utils::META_PAGE, true ),
				'template' => get_post_meta( $post_id, '_wp_page_template', true ),
				'conds'    => get_post_meta( $post_id, Utils::META_CONDS, true ),
				'tpl'      => get_post_meta( $post_id, Utils::META_TPL, true ),
			)
		);
		// Keep the last 15 snapshots per post (they hold full JSON copies).
		update_post_meta( $post_id, '_uncoder_wb_snapshots', wp_slash( array_slice( $list, 0, 15 ) ) );
		$this->object_id = $post_id;
		$this->snapshot  = $id;
		return $id;
	}

	public function snapshot_kit(): string {
		$id             = 'kit:' . \Uncoder\Builder\Plugin::instance()->kit()->snapshot( 'Before ' . $this->tool . ' (' . $this->ctx->label() . ')' );
		$this->snapshot = $id;
		return $id;
	}

	public function warn( string $message ): void {
		$this->warnings[] = $message;
	}
}
