<?php
/**
 * Document factory and cache.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Returns shared Document instances per post id.
 */
final class Documents {

	/** @var array<int, Document> */
	private array $cache = array();

	public function get( int $post_id ): ?Document {
		if ( $post_id <= 0 ) {
			return null;
		}
		if ( ! isset( $this->cache[ $post_id ] ) ) {
			if ( ! get_post( $post_id ) ) {
				return null;
			}
			$this->cache[ $post_id ] = new Document( $post_id );
		}
		return $this->cache[ $post_id ];
	}

	public function forget( int $post_id ): void {
		unset( $this->cache[ $post_id ] );
	}

	/**
	 * Post types that can be edited with the builder.
	 *
	 * @return string[]
	 */
	public function post_types(): array {
		$settings = get_option( 'uncoder_wb_settings', array() );
		$types    = is_array( $settings['post_types'] ?? null ) ? $settings['post_types'] : array( 'page', 'post' );
		$types[]  = Post_Types::TEMPLATE;
		/**
		 * Filters the post types editable with the builder.
		 *
		 * @param string[] $types Post types.
		 */
		return array_values( array_unique( apply_filters( 'uncoder_wb/post_types', $types ) ) );
	}

	public function is_supported( int $post_id ): bool {
		$type = get_post_type( $post_id );
		return $type && in_array( $type, $this->post_types(), true );
	}

	/**
	 * Regenerates CSS for every builder document (after kit/breakpoint changes).
	 */
	public function regenerate_all(): int {
		// Not 'any': that leaves out post types excluded from search, which includes theme templates.
		$ids = get_posts(
			array(
				'post_type'      => $this->post_types(),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'meta_key'       => Utils::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $ids as $id ) {
			$doc = $this->get( (int) $id );
			if ( $doc ) {
				$doc->regenerate();
			}
		}
		return count( $ids );
	}
}
