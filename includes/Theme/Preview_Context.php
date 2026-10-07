<?php
/**
 * Sample content for previewing theme templates in the editor and through MCP.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Theme;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * A single-post template previews with a real post, an archive template with a real category
 * archive, a search template with a real search. The page setting "preview_post" overrides the post.
 */
final class Preview_Context {

	/** @var array<string,mixed>|null Saved globals while a context is applied. */
	private ?array $saved = null;

	/**
	 * Resolves the preview context of a document.
	 *
	 * @return array{post:int, query:array<string,mixed>|null}
	 */
	public static function resolve( int $doc_id ): array {
		$out = array(
			'post'  => $doc_id,
			'query' => null,
		);
		if ( Post_Types::TEMPLATE !== get_post_type( $doc_id ) ) {
			return $out;
		}
		$type     = (string) get_post_meta( $doc_id, Utils::META_TYPE, true );
		$settings = Plugin::instance()->documents()->get( $doc_id )->page_settings();
		$chosen   = absint( $settings['preview_post'] ?? 0 );
		if ( $chosen && 'publish' === get_post_status( $chosen ) ) {
			$out['post'] = $chosen;
			return $out;
		}

		switch ( $type ) {
			case 'single-post':
			case 'loop-item':
			case 'header':
			case 'footer':
			case 'popup':
			case 'mega-menu':
			case 'section':
				$out['post'] = self::latest( 'post' );
				break;
			case 'single-page':
				$out['post'] = self::latest( 'page' );
				break;
			case 'single':
				$out['post'] = self::latest( self::condition_post_type( $doc_id ) ?? 'post' );
				break;
			case 'archive':
				$out['query'] = self::archive_query( $doc_id );
				break;
			case 'search-results':
				$out['query'] = array(
					's'              => self::sample_search_term(),
					'posts_per_page' => (int) get_option( 'posts_per_page', 10 ),
				);
				break;
			case 'error-404':
				$out['query'] = array( 'error' => '404' );
				break;
		}
		if ( ! $out['post'] ) {
			$out['post'] = $doc_id;
		}
		return $out;
	}

	/**
	 * Applies a context: the global post and, for archives, the main query. Call restore() after rendering.
	 *
	 * @param array{post:int, query:array<string,mixed>|null} $context Context.
	 */
	public function apply( array $context ): void {
		global $post, $wp_query, $wp_the_query;
		$this->saved = array(
			'post'         => $post,
			'wp_query'     => $wp_query,
			'wp_the_query' => $wp_the_query,
		);
		if ( is_array( $context['query'] ) ) {
			if ( isset( $context['query']['error'] ) ) {
				$query = new \WP_Query();
				$query->set_404();
			} else {
				$query = new \WP_Query( $context['query'] );
			}
			$wp_query     = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_the_query = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$post         = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			return;
		}
		$target = get_post( (int) $context['post'] );
		if ( $target && current_user_can( 'read_post', $target->ID ) ) {
			$post = $target; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );
		}
	}

	public function restore(): void {
		if ( null === $this->saved ) {
			return;
		}
		global $post, $wp_query, $wp_the_query;
		$wp_query     = $this->saved['wp_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_the_query = $this->saved['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post         = $this->saved['post']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $post ) {
			setup_postdata( $post );
		} else {
			wp_reset_postdata();
		}
		$this->saved = null;
	}

	private static function latest( string $post_type ): int {
		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	private static function condition_post_type( int $doc_id ): ?string {
		foreach ( (array) get_post_meta( $doc_id, Utils::META_CONDS, true ) as $c ) {
			if ( is_array( $c ) && 'include' === ( $c['type'] ?? '' ) && ! empty( $c['post_type'] ) && post_type_exists( (string) $c['post_type'] ) ) {
				return (string) $c['post_type'];
			}
		}
		return null;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function archive_query( int $doc_id ): array {
		$per_page = (int) get_option( 'posts_per_page', 10 );
		foreach ( (array) get_post_meta( $doc_id, Utils::META_CONDS, true ) as $c ) {
			if ( ! is_array( $c ) || 'include' !== ( $c['type'] ?? '' ) || 'archive' !== ( $c['rule'] ?? '' ) ) {
				continue;
			}
			if ( ! empty( $c['taxonomy'] ) && taxonomy_exists( (string) $c['taxonomy'] ) ) {
				$term = ! empty( $c['ids'][0] ) ? get_term( (int) $c['ids'][0], (string) $c['taxonomy'] ) : self::busiest_term( (string) $c['taxonomy'] );
				if ( $term && ! is_wp_error( $term ) ) {
					if ( 'category' === $term->taxonomy ) {
						return array(
							'cat'            => $term->term_id,
							'posts_per_page' => $per_page,
						);
					}
					return array(
						'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => $term->taxonomy,
								'terms'    => $term->term_id,
							),
						),
						'posts_per_page' => $per_page,
					);
				}
			}
			if ( ! empty( $c['post_type'] ) && post_type_exists( (string) $c['post_type'] ) ) {
				return array(
					'post_type'      => (string) $c['post_type'],
					'posts_per_page' => $per_page,
				);
			}
		}
		$term = self::busiest_term( 'category' );
		return $term ? array(
			'cat'            => $term->term_id,
			'posts_per_page' => $per_page,
		) : array( 'posts_per_page' => $per_page );
	}

	private static function busiest_term( string $taxonomy ): ?\WP_Term {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 1,
				'hide_empty' => true,
			)
		);
		return is_array( $terms ) && $terms ? $terms[0] : null;
	}

	private static function sample_search_term(): string {
		$latest = self::latest( 'post' );
		$words  = $latest ? preg_split( '/\s+/', wp_strip_all_tags( get_the_title( $latest ) ) ) : array();
		foreach ( (array) $words as $word ) {
			$word = trim( (string) $word, '.,:;!?"\'' );
			if ( mb_strlen( $word ) >= 4 ) {
				return mb_strtolower( $word );
			}
		}
		return 'the';
	}
}
