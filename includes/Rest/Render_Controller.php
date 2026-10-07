<?php
/**
 * Widget render endpoint used by the editor canvas.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use Uncoder\Builder\Core\Renderer;
use Uncoder\Builder\Theme\Preview_Context;
use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Plugin;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * POST /render { post_id, elements: [ {id, type, settings, dynamic, children?} ] }
 * → { items: { id: { html, attrs } } }. Widgets only; containers are drawn by the editor.
 */
final class Render_Controller {

	public const MAX_BATCH = 80;

	public function register_routes(): void {
		register_rest_route(
			Rest::NS,
			'/render',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'render' ),
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$id = (int) ( $request->get_json_params()['post_id'] ?? 0 );
					return $id > 0 && current_user_can( 'edit_post', $id );
				},
			)
		);
	}

	public function render( WP_REST_Request $request ): WP_REST_Response {
		$body     = $request->get_json_params();
		$post_id  = (int) ( $body['post_id'] ?? 0 );
		$elements = array_slice( (array) ( $body['elements'] ?? array() ), 0, self::MAX_BATCH );

		// Templates preview with sample content (a real post, archive or search); pages with themselves.
		$preview = Preview_Context::resolve( $post_id );
		$explicit = (int) ( $body['context_post'] ?? 0 );
		if ( $explicit && $explicit !== $post_id && current_user_can( 'read_post', $explicit ) ) {
			$preview = array(
				'post'  => $explicit,
				'query' => null,
			);
		}
		$scope = new Preview_Context();
		$scope->apply( $preview );
		$context = null === $preview['query'] ? (int) $preview['post'] : 0;

		$renderer = new Renderer( $post_id, true, $context );
		$items    = array();
		foreach ( $elements as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$raw_id = is_string( $raw['id'] ?? null ) ? $raw['id'] : '';
			// Editor ids only (they end up in class names, data-id and CSS selectors).
			if ( ! \Uncoder\Builder\Core\Utils::is_valid_id( $raw_id ) ) {
				continue;
			}
			// Sanitize exactly as a save would, so the preview never shows unsafe output.
			$tree = new Tree( 'sanitize' );
			$node = $tree->node( $raw, 0, '' );
			if ( null === $node ) {
				continue;
			}
			$node['id'] = $raw_id;
			$widget     = Plugin::instance()->elements()->get( $node['type'] );
			if ( ! $widget instanceof Widget_Base || '' === $raw_id ) {
				continue;
			}
			$settings = $widget->effective_settings( $node['settings'] );
			if ( ! empty( $node['dynamic'] ) ) {
				foreach ( $node['dynamic'] as $key => $def ) {
					$value = Plugin::instance()->tags()->resolve( $def, $settings[ $key ] ?? null, $renderer->context() );
					if ( null !== $value ) {
						$settings[ $key ] = $value;
					}
				}
			}
			try {
				$parts            = $renderer->widget_parts( $widget, $node, $settings );
				$items[ $raw_id ] = $parts;
			} catch ( \Throwable $e ) {
				$items[ $raw_id ] = array(
					'html'  => '<div class="uncoder-render-error">' . esc_html( $e->getMessage() ) . '</div>',
					'attrs' => array(),
					'outer' => '<div class="uncoder-render-error" data-id="' . esc_attr( $raw_id ) . '">' . esc_html( $e->getMessage() ) . '</div>',
				);
			}
		}

		$scope->restore();

		return new WP_REST_Response( array( 'items' => (object) $items ) );
	}
}
