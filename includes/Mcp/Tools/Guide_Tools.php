<?php
/**
 * Orientation tools: build guide, widget catalogue, schemas, dynamic tags, icons, fonts.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Fonts;
use Uncoder\Builder\Core\Icons;
use Uncoder\Builder\Mcp\Call;
use Uncoder\Builder\Mcp\Guide;
use Uncoder\Builder\Mcp\Registry;
use Uncoder\Builder\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only tools that teach the AI how to build with Uncoder.
 */
final class Guide_Tools {

	public function register( Registry $r ): void {
		$ro = array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false );

		$r->add(
			array(
				'name'        => 'get_build_guide',
				'title'       => 'Build guide',
				'description' => 'How to build with Uncoder: workflow, layout rules, styling values, responsive design, theme builder, editing operations and JSON recipes. Read topic "overview" before your first build. Topics: ' . implode( ', ', array_keys( Guide::topics() ) ) . '.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'topic' => array(
							'type'        => 'string',
							'enum'        => array_keys( Guide::topics() ),
							'description' => 'Guide topic (default overview).',
						),
					),
				),
				'callback'    => static function ( array $a ) {
					$topic = (string) ( $a['topic'] ?? 'overview' );
					return array(
						'topic'  => $topic,
						'guide'  => Guide::topic( $topic ) ?? Guide::topic( 'overview' ),
						'topics' => Guide::topics(),
					);
				},
			)
		);

		$r->add(
			array(
				'name'        => 'list_widgets',
				'title'       => 'List widgets',
				'description' => 'Catalogue of element types (widgets) with a one-line description and their main content settings. Use get_widget_schema for the full settings of one type.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'category' => array( 'type' => 'string', 'description' => 'Filter by category: ' . implode( ', ', array_keys( Plugin::instance()->elements()->categories() ) ) ),
						'search'   => array( 'type' => 'string', 'description' => 'Filter by name/keyword.' ),
					),
				),
				'callback'    => array( $this, 'list_widgets' ),
			)
		);

		$r->add(
			array(
				'name'        => 'get_widget_schema',
				'title'       => 'Widget settings schema',
				'description' => 'Exact setting keys, value shapes, allowed options and defaults for an element type. Always check this before using a widget for the first time. Common Advanced settings (_margin, _padding, _animation…) are listed in the "layout" guide; pass section "advanced" to see them.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'type'    => array( 'type' => 'string', 'description' => 'Element type, e.g. "heading", "container", "icon-box".' ),
						'section' => array(
							'type'        => 'string',
							'enum'        => array( 'content', 'style', 'advanced', 'all' ),
							'description' => 'Which settings to return (default: content + style).',
						),
					),
					'required'   => array( 'type' ),
				),
				'callback'    => static function ( array $a ) {
					return self::widget_schema( (string) $a['type'], (string) ( $a['section'] ?? '' ) );
				},
			)
		);

		$r->add(
			array(
				'name'        => 'list_dynamic_tags',
				'title'       => 'List dynamic tags',
				'description' => 'Dynamic data sources (post title, featured image, custom fields, site logo…) usable on settings marked dynamic, via the element "dynamic" map.',
				'scope'       => 'read',
				'annotations' => $ro,
				'callback'    => static function () {
					$out = array();
					foreach ( Plugin::instance()->tags()->schema() as $name => $tag ) {
						$item = array(
							'tag'        => $name,
							'title'      => $tag['title'],
							'group'      => $tag['group'],
							'categories' => $tag['categories'],
						);
						if ( $tag['controls'] ) {
							$item['options'] = array_map( static fn( $c ) => $c['label'] ?? '', $tag['controls'] );
						}
						$out[] = $item;
					}
					return array(
						'tags'  => $out,
						'usage' => '{"type":"heading","settings":{"tag":"h1"},"dynamic":{"title":{"tag":"post-title"}}} — options: {"tag":"post-date","options":{"format":"F j, Y"},"before":"Updated "}',
					);
				},
			)
		);

		$r->add(
			array(
				'name'        => 'search_icons',
				'title'       => 'Search icons',
				'description' => 'Find icon names for icon settings. Lucide by default (use the plain name); other libraries: Font Awesome (fa-solid, fa-regular, fa-brands — brand logos like github, whatsapp), phosphor, bootstrap, feather, heroicons-outline, heroicons-solid, themify. Use a result as "library:name", e.g. "fa-brands:linkedin". Names and search terms both match.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'query'   => array( 'type' => 'string', 'description' => 'Keyword, e.g. "phone", "arrow", "check", "house".' ),
						'library' => array(
							'type'        => 'string',
							'description' => 'lucide (default), all, or one library id.',
							'enum'        => array_merge( array( 'lucide', 'all' ), array_column( Icons::libraries(), 'id' ) ),
						),
						'limit'   => array( 'type' => 'integer' ),
					),
					'required'   => array( 'query' ),
				),
				'callback'    => static function ( array $a ) {
					$library = (string) ( $a['library'] ?? 'lucide' );
					if ( 'lucide' !== $library && 'all' !== $library && ! Icons::is_library( $library ) ) {
						$library = 'lucide';
					}
					$names = Icons::search( (string) $a['query'], min( 100, max( 1, (int) ( $a['limit'] ?? 40 ) ) ), $library );
					if ( 'lucide' !== $library && 'all' !== $library ) {
						$names = array_map( static fn( $n ) => $library . ':' . $n, $names );
					}
					return array( 'icons' => $names );
				},
			)
		);

		$r->add(
			array(
				'name'        => 'search_fonts',
				'title'       => 'Search fonts',
				'description' => 'Search the Google Fonts catalogue (family, category, available weights) for typography and the Design System.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'query'    => array( 'type' => 'string', 'description' => 'Part of the family name; empty lists popular fonts.' ),
						'category' => array( 'type' => 'string', 'enum' => array( 'sans-serif', 'serif', 'display', 'handwriting', 'monospace' ) ),
						'limit'    => array( 'type' => 'integer' ),
					),
				),
				'callback'    => static function ( array $a ) {
					return array( 'fonts' => Fonts::search( (string) ( $a['query'] ?? '' ), min( 60, max( 1, (int) ( $a['limit'] ?? 25 ) ) ), (string) ( $a['category'] ?? '' ) ) );
				},
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function list_widgets( array $a ): array {
		$category = sanitize_key( (string) ( $a['category'] ?? '' ) );
		$search   = strtolower( trim( (string) ( $a['search'] ?? '' ) ) );
		$out      = array();
		foreach ( Plugin::instance()->elements()->all() as $name => $el ) {
			if ( '' !== $category && $el->category() !== $category ) {
				continue;
			}
			if ( '' !== $search && false === strpos( $name, $search ) && false === stripos( $el->title(), $search ) && ! array_filter( $el->keywords(), static fn( $k ) => false !== stripos( $k, $search ) ) ) {
				continue;
			}
			$main = array();
			foreach ( $el->get_controls() as $key => $c ) {
				if ( 'content' === ( $c['tab'] ?? '' ) && '_' !== $key[0] && ! in_array( $c['type'], array( 'heading', 'notice', 'divider' ), true ) ) {
					$main[] = $key;
				}
				if ( count( $main ) >= 8 ) {
					break;
				}
			}
			$item = array(
				'type'        => $name,
				'title'       => $el->title(),
				'category'    => $el->category(),
				'description' => $el->description(),
				'settings'    => $main,
			);
			if ( null !== $el->nested() ) {
				$item['nested'] = 'Holds one container child per "' . $el->nested()['items'] . '" item.';
			}
			$out[] = $item;
		}
		return array(
			'count'      => count( $out ),
			'widgets'    => $out,
			'categories' => Plugin::instance()->elements()->categories(),
		);
	}

	/**
	 * Compact, AI-oriented schema of an element type.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function widget_schema( string $type, string $section = '' ) {
		$el = Plugin::instance()->elements()->get( sanitize_key( str_replace( '_', '-', $type ) ) );
		if ( ! $el ) {
			return new WP_Error( 'not_found', sprintf( 'Unknown element type "%s". Call list_widgets.', $type ) );
		}
		$registry = Plugin::instance()->controls();
		$tabs     = '' === $section ? array( 'content', 'style' ) : ( 'all' === $section ? array( 'content', 'style', 'advanced' ) : array( $section ) );
		$out      = array();
		foreach ( $el->get_controls() as $key => $c ) {
			if ( ! in_array( $c['tab'] ?? 'content', $tabs, true ) || in_array( $c['type'], array( 'heading', 'notice', 'divider' ), true ) ) {
				continue;
			}
			$item = array(
				'type'  => $c['type'],
				'label' => $c['label'] ?? $key,
				'value' => $registry->value_hint( $c ),
			);
			if ( isset( $c['default'] ) && array() !== $c['default'] && '' !== $c['default'] ) {
				$item['default'] = $c['default'];
			}
			if ( ! empty( $c['responsive'] ) ) {
				$item['responsive'] = true;
			}
			if ( ! empty( $c['dynamic'] ) ) {
				$item['dynamic'] = true;
			}
			if ( ! empty( $c['condition'] ) ) {
				$item['shown_when'] = $c['condition'];
			}
			if ( ! empty( $c['ai'] ) ) {
				$item['hint'] = $c['ai'];
			} elseif ( ! empty( $c['description'] ) ) {
				$item['hint'] = wp_strip_all_tags( (string) $c['description'] );
			}
			if ( 'repeater' === $c['type'] ) {
				$fields = array();
				foreach ( (array) ( $c['fields'] ?? array() ) as $fk => $f ) {
					$fields[ $fk ] = $registry->value_hint( $f ) . ( isset( $f['default'] ) && is_scalar( $f['default'] ) && '' !== $f['default'] ? ' (default ' . wp_json_encode( $f['default'] ) . ')' : '' );
				}
				$item['row_fields'] = $fields;
			}
			$out[ $c['tab'] ?? 'content' ][ $key ] = $item;
		}
		$result = array(
			'type'        => $el->name(),
			'title'       => $el->title(),
			'description' => $el->description(),
			'settings'    => $out,
		);
		if ( $el->is_container() ) {
			$result['children'] = 'Any elements (containers and widgets).';
		} elseif ( null !== $el->nested() ) {
			$result['children'] = sprintf( 'One container per "%s" row, same order. Put each item\'s content inside its container.', $el->nested()['items'] );
		}
		if ( $el->preset() ) {
			$result['example_settings'] = $el->preset();
		}
		$result['notes'] = 'Responsive settings accept _tablet/_mobile suffixed keys. Sizes accept "24px". Colors accept "var(--uncoder-c-primary)". Omit settings to keep defaults.';
		return $result;
	}
}
