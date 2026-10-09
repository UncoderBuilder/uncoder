<?php
/**
 * Starter sites: a Design System, header, footer and pages assembled from the bundled section patterns.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Patterns\Patterns;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * assets/data/starters/{id}.json: { id, title, description, kit, header, footer, pages: [ { title,
 * front?, sections: [pattern ids] } ] }. Importing builds an export file from the patterns and runs
 * it through Transfer::run(), so the usual sanitizing applies and everything arrives as drafts: the
 * live site only changes when the owner publishes (the Design System is the exception, with a snapshot).
 */
final class Starters {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$can = static fn() => current_user_can( 'edit_theme_options' ) && current_user_can( 'edit_pages' );
		register_rest_route(
			Rest::NS,
			'/starters',
			array(
				'methods'             => 'GET',
				'callback'            => fn() => new WP_REST_Response( array_values( array_map( array( $this, 'summary' ), self::all() ) ) ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			Rest::NS,
			'/starters/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * @return array<string, array<string,mixed>>
	 */
	public static function all(): array {
		$out = array();
		foreach ( (array) glob( UNCODER_WB_PATH . 'assets/data/starters/*.json' ) as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled data file.
			$data = json_decode( (string) file_get_contents( (string) $file ), true );
			if ( is_array( $data ) && ! empty( $data['id'] ) && is_array( $data['pages'] ?? null ) ) {
				$out[ sanitize_key( (string) $data['id'] ) ] = $data;
			}
		}
		/**
		 * Filters the starter sites offered in the dashboard (add your own recipes).
		 *
		 * @param array<string, array<string,mixed>> $out Starters by id.
		 */
		$out = (array) apply_filters( 'uncoder_wb/starters/list', $out );
		// The earlier name, outside the uncoder_wb/ prefix: still honoured.
		return (array) apply_filters_deprecated( 'uncoder_starter_sites', array( $out ), '0.1.2', 'uncoder_wb/starters/list' );
	}

	/**
	 * What the dashboard shows: title, description, palette, fonts and pages.
	 *
	 * @param array<string,mixed> $s Starter.
	 * @return array<string,mixed>
	 */
	public function summary( array $s ): array {
		$colors = array();
		foreach ( (array) ( $s['kit']['colors'] ?? array() ) as $c ) {
			$colors[ (string) ( $c['id'] ?? '' ) ] = (string) ( $c['value'] ?? '' );
		}
		$fonts = array();
		foreach ( (array) ( $s['kit']['fonts'] ?? array() ) as $f ) {
			$fonts[ (string) ( $f['id'] ?? '' ) ] = (string) ( $f['family'] ?? '' );
		}
		return array(
			'id'          => (string) $s['id'],
			'title'       => (string) ( $s['title'] ?? $s['id'] ),
			'description' => (string) ( $s['description'] ?? '' ),
			'colors'      => $colors,
			'fonts'       => $fonts,
			'pages'       => array_map( static fn( $p ) => array( 'title' => (string) ( $p['title'] ?? '' ), 'sections' => count( (array) ( $p['sections'] ?? array() ) ) ), (array) $s['pages'] ),
		);
	}

	/**
	 * Body: { id, kit?: bool (default true), templates?: bool (default true), conditions?: bool (default true) }.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$id      = sanitize_key( (string) $request->get_param( 'id' ) );
		$starter = self::all()[ $id ] ?? null;
		if ( ! $starter ) {
			return new WP_Error( 'uncoder_not_found', __( 'Unknown starter site.', 'uncoder' ), array( 'status' => 404 ) );
		}
		$flag     = static fn( string $key ): bool => null === $request->get_param( $key ) || (bool) $request->get_param( $key );
		$patterns = Plugin::instance()->module( 'patterns' );
		if ( ! $patterns instanceof Patterns ) {
			return new WP_Error( 'uncoder_unavailable', __( 'The section library is not available.', 'uncoder' ), array( 'status' => 500 ) );
		}
		$items = array();
		if ( $flag( 'templates' ) ) {
			foreach ( array( 'header', 'footer' ) as $type ) {
				$tree = ! empty( $starter[ $type ] ) ? $patterns->get( (string) $starter[ $type ] ) : null;
				if ( $tree ) {
					$items[] = array(
						'kind'       => 'template',
						'type'       => $type,
						/* translators: 1: starter site name, 2: Header or Footer. */
						'title'      => sprintf( __( '%1$s – %2$s', 'uncoder' ), (string) $starter['title'], 'header' === $type ? __( 'Header', 'uncoder' ) : __( 'Footer', 'uncoder' ) ),
						'conditions' => array( array( 'type' => 'include', 'rule' => 'general' ) ),
						'elements'   => $tree,
					);
				}
			}
		}
		foreach ( (array) $starter['pages'] as $page ) {
			$elements = array();
			foreach ( (array) ( $page['sections'] ?? array() ) as $pattern ) {
				$tree = $patterns->get( (string) $pattern );
				if ( $tree ) {
					$elements = array_merge( $elements, $tree );
				}
			}
			$items[] = array(
				'kind'          => 'page',
				'type'          => 'page',
				'title'         => (string) ( $page['title'] ?? __( 'Page', 'uncoder' ) ),
				'template'      => 'uncoder-full-width',
				'page_settings' => array( 'hide_title' => true ),
				'elements'      => $elements,
			);
		}
		$transfer = Plugin::instance()->module( 'transfer' );
		if ( ! $transfer instanceof Transfer ) {
			return new WP_Error( 'uncoder_unavailable', __( 'Import is not available.', 'uncoder' ), array( 'status' => 500 ) );
		}
		$result = $transfer->run(
			array(
				'format'  => Transfer::FORMAT,
				'version' => Transfer::VERSION,
				'kit'     => is_array( $starter['kit'] ?? null ) ? $starter['kit'] : null,
				'items'   => $items,
			),
			array(
				'kit'        => $flag( 'kit' ),
				'media'      => false,
				'conditions' => $flag( 'conditions' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['starter'] = $this->summary( $starter );
		// The Design System before the import (newest restore point), for an "undo" in the dashboard.
		$result['kitSnapshot'] = ! empty( $result['kit'] ) ? (string) ( Plugin::instance()->kit()->snapshots()[0]['id'] ?? '' ) : '';
		return new WP_REST_Response( $result );
	}
}
