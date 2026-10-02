<?php
/**
 * Find & replace across every Uncoder page, post and template.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Matches by control type, never by guessing: "text" looks in text / textarea / rich text settings,
 * "links" in URL fields, "colors" in color settings (also inside backgrounds, borders and shadows),
 * so replacing a word can never change a layout or style value. Twin of src/editor/lib/findReplace.ts.
 */
final class Find_Replace {

	public const SCOPES    = array( 'text', 'links', 'colors' );
	private const MAX_DOCS = 1000;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			Rest::NS,
			'/find-replace',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ) && Role_Manager::can_use() && ! Role_Manager::content_only(),
			)
		);
	}

	/**
	 * Body: { find, replace, scope: text|links|colors|all, match_case, apply }.
	 */
	public function rest( WP_REST_Request $request ) {
		$result = self::run(
			(string) $request->get_param( 'find' ),
			(string) $request->get_param( 'replace' ),
			(string) ( $request->get_param( 'scope' ) ?? 'text' ),
			(bool) $request->get_param( 'match_case' ),
			(bool) $request->get_param( 'apply' )
		);
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	/**
	 * @return array{matches:int, documents:array<int, array<string,mixed>>, applied:bool}|WP_Error
	 */
	public static function run( string $find, string $replace, string $scope, bool $match_case, bool $apply ) {
		if ( '' === $find || strlen( $find ) > 500 ) {
			return new WP_Error( 'uncoder_invalid', __( 'Enter the text to find (500 characters at most).', 'uncoder' ), array( 'status' => 400 ) );
		}
		$scopes = 'all' === $scope ? self::SCOPES : array_intersect( array( $scope ), self::SCOPES );
		if ( ! $scopes ) {
			return new WP_Error( 'uncoder_invalid', __( 'Unknown scope.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$ids = get_posts(
			array(
				'post_type'      => array_merge( Plugin::instance()->documents()->post_types(), array( Post_Types::TEMPLATE ) ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => self::MAX_DOCS,
				'fields'         => 'ids',
				'meta_key'       => Utils::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$total = 0;
		$docs  = array();
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$doc      = Plugin::instance()->documents()->get( (int) $id );
			$state    = array(
				'find'    => $find,
				'replace' => $replace,
				'case'    => $match_case,
				'scopes'  => array_values( $scopes ),
				'count'   => 0,
				'samples' => array(),
			);
			$elements = self::tree( $doc->elements(), $state );
			if ( ! $state['count'] ) {
				continue;
			}
			$total += $state['count'];
			$docs[] = array(
				'id'      => (int) $id,
				'title'   => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'type'    => Post_Types::TEMPLATE === get_post_type( $id ) ? (string) get_post_meta( $id, Utils::META_TYPE, true ) : (string) get_post_type( $id ),
				'count'   => $state['count'],
				'samples' => $state['samples'],
				'edit'    => admin_url( 'post.php?action=uncoder&post=' . $id ),
			);
			if ( $apply ) {
				$doc->save( $elements, array( 'content_fallback' => Post_Types::TEMPLATE !== get_post_type( $id ) ) );
				if ( wp_revisions_enabled( get_post( $id ) ) ) {
					wp_save_post_revision( $id );
				}
			}
		}
		return array(
			'matches'   => $total,
			'documents' => $docs,
			'applied'   => $apply,
		);
	}

	/**
	 * @param array<int, array<string,mixed>> $nodes Tree.
	 * @param array<string,mixed>             $state Search state (by reference).
	 * @return array<int, array<string,mixed>>
	 */
	private static function tree( array $nodes, array &$state ): array {
		foreach ( $nodes as $i => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$type = Plugin::instance()->elements()->get( (string) ( $node['type'] ?? '' ) );
			if ( $type && is_array( $node['settings'] ?? null ) ) {
				$nodes[ $i ]['settings'] = self::settings( $node['settings'], $type->get_controls(), $state, $type->title() );
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$nodes[ $i ]['children'] = self::tree( $node['children'], $state );
			}
		}
		return $nodes;
	}

	/**
	 * @param array<string,mixed>                $settings Settings (or a group / repeater row).
	 * @param array<string, array<string,mixed>> $controls Their controls.
	 * @param array<string,mixed>                $state    Search state (by reference).
	 * @return array<string,mixed>
	 */
	private static function settings( array $settings, array $controls, array &$state, string $where ): array {
		$registry = Plugin::instance()->controls();
		foreach ( $settings as $key => $value ) {
			if ( 0 === strpos( (string) $key, '_' ) && ! in_array( $key, array( '_background', '_border', '_shadow' ), true ) ) {
				continue; // Advanced keys (ids, classes, custom CSS, attributes) are never touched.
			}
			list( $base ) = Breakpoints::split_key( (string) $key );
			$control      = $controls[ $key ] ?? $controls[ $base ] ?? null;
			if ( ! $control ) {
				continue;
			}
			$ctype = (string) ( $control['type'] ?? '' );
			$label = $where . ' · ' . ( $control['label'] ?? $key );
			if ( in_array( $ctype, array( 'text', 'textarea', 'wysiwyg' ), true ) && is_string( $value ) && in_array( 'text', $state['scopes'], true ) ) {
				$settings[ $key ] = self::swap( $value, $state, $label );
			} elseif ( in_array( $ctype, array( 'url', 'link' ), true ) && is_array( $value ) && is_string( $value['url'] ?? null ) && in_array( 'links', $state['scopes'], true ) ) {
				$settings[ $key ]['url'] = self::swap( $value['url'], $state, $label );
			} elseif ( 'color' === $ctype && is_string( $value ) && in_array( 'colors', $state['scopes'], true ) ) {
				$settings[ $key ] = self::swap( $value, $state, $label, true );
			} elseif ( 'overrides' === $ctype && is_array( $value ) ) {
				// Component overrides: texts and link URLs of this copy.
				foreach ( $value as $k => $v ) {
					if ( is_string( $v ) && in_array( 'text', $state['scopes'], true ) ) {
						$settings[ $key ][ $k ] = self::swap( $v, $state, $label );
					} elseif ( is_array( $v ) && is_string( $v['url'] ?? null ) && ! isset( $v['id'] ) && in_array( 'links', $state['scopes'], true ) ) {
						$settings[ $key ][ $k ]['url'] = self::swap( $v['url'], $state, $label );
					}
				}
			} elseif ( 'repeater' === $ctype && is_array( $value ) ) {
				foreach ( $value as $r => $row ) {
					if ( is_array( $row ) ) {
						$settings[ $key ][ $r ] = self::settings( $row, (array) ( $control['fields'] ?? array() ), $state, $label );
					}
				}
			} elseif ( is_array( $value ) ) {
				$group = $registry->get( $ctype );
				if ( $group && $group->is_group() && method_exists( $group, 'fields' ) ) {
					$settings[ $key ] = self::settings( $value, $group->fields( $control ), $state, $label );
				}
			}
		}
		return $settings;
	}

	/**
	 * @param array<string,mixed> $state Search state (by reference).
	 */
	private static function swap( string $value, array &$state, string $label, bool $color = false ): string {
		$count = 0;
		// Hex colors are case-insensitive whatever the option says.
		$next = ( $state['case'] && ! $color ) ? str_replace( $state['find'], $state['replace'], $value, $count ) : str_ireplace( $state['find'], $state['replace'], $value, $count );
		if ( $count ) {
			$state['count'] += $count;
			if ( count( $state['samples'] ) < 5 ) {
				$plain              = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $value ) ) );
				$state['samples'][] = array(
					'where' => $label,
					'text'  => mb_substr( '' !== $plain ? $plain : $value, 0, 120 ),
				);
			}
		}
		return $next;
	}
}
