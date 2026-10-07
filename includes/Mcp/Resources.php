<?php
/**
 * MCP resources: guide topics, widget schemas, the design system and pages.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * uncoder://guide/{topic}, uncoder://widgets/{type}, uncoder://design-system, uncoder://pages/{id}.
 */
final class Resources {

	/**
	 * @return array<int, array<string,mixed>>
	 */
	public static function list(): array {
		$out = array(
			array(
				'uri'         => 'uncoder://design-system',
				'name'        => 'design-system',
				'title'       => 'Design System (design system)',
				'description' => 'Global colors, fonts, text styles, buttons and layout of this site.',
				'mimeType'    => 'application/json',
			),
		);
		foreach ( Guide::topics() as $topic => $title ) {
			$out[] = array(
				'uri'         => 'uncoder://guide/' . $topic,
				'name'        => 'guide-' . $topic,
				'title'       => 'Build guide: ' . $title,
				'description' => $title,
				'mimeType'    => 'text/markdown',
			);
		}
		return $out;
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	public static function templates(): array {
		return array(
			array(
				'uriTemplate' => 'uncoder://widgets/{type}',
				'name'        => 'widget-schema',
				'title'       => 'Widget schema',
				'description' => 'Settings of a widget type (same as get_widget_schema).',
				'mimeType'    => 'application/json',
			),
			array(
				'uriTemplate' => 'uncoder://pages/{id}',
				'name'        => 'page',
				'title'       => 'Page elements',
				'description' => 'Element tree of a page or template.',
				'mimeType'    => 'application/json',
			),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function read( string $uri ) {
		$text = null;
		$mime = 'application/json';
		if ( 'uncoder://design-system' === $uri ) {
			$text = wp_json_encode( Plugin::instance()->kit()->export() );
		} elseif ( preg_match( '#^uncoder://guide/([a-z\-]+)$#', $uri, $m ) ) {
			$text = Guide::topic( $m[1] );
			$mime = 'text/markdown';
		} elseif ( preg_match( '#^uncoder://widgets/([a-z0-9\-]+)$#', $uri, $m ) ) {
			$schema = Tools\Guide_Tools::widget_schema( $m[1], 'all' );
			$text   = is_wp_error( $schema ) ? null : wp_json_encode( $schema );
		} elseif ( preg_match( '#^uncoder://pages/(\d+)$#', $uri, $m ) ) {
			$id = (int) $m[1];
			if ( current_user_can( 'edit_post', $id ) ) {
				$doc  = Plugin::instance()->documents()->get( $id );
				$text = $doc ? wp_json_encode( array( 'id' => $id, 'elements' => $doc->elements() ) ) : null;
			}
		}
		if ( null === $text || false === $text ) {
			return new WP_Error( 'not_found', 'Resource not found: ' . $uri );
		}
		return array(
			'contents' => array(
				array(
					'uri'      => $uri,
					'mimeType' => $mime,
					'text'     => $text,
				),
			),
		);
	}
}
