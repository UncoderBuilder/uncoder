<?php
/**
 * Section pattern library: ready-made, on-brand sections for people (editor Library panel)
 * and AI clients (MCP list_patterns / get_pattern).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Patterns;

use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Mcp\Registry;
use Uncoder\Builder\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Patterns are JSON files in assets/data/patterns/{id}.json:
 *   { "id", "title", "category", "description", "keywords": [], "elements": [ one top-level container ] }
 * Element ids are omitted in the files; get() returns a sanitized copy with fresh ids.
 * The string "{{placeholder_image}}" in any value is replaced with the plugin's placeholder image URL.
 *
 * Themes and plugins add patterns with the `uncoder_wb/patterns` filter (see all()).
 */
final class Patterns {

	/** Token replaced with the placeholder image URL in pattern values. */
	public const PLACEHOLDER = '{{placeholder_image}}';

	private const TRANSIENT = 'uncoder_wb_patterns_index';

	/** Bump when the cached index format (e.g. the shape) changes. */
	private const CACHE_VERSION = 2;

	/** Max levels / children kept in the schematic layout used for thumbnails. */
	private const SHAPE_DEPTH    = 5;
	private const SHAPE_CHILDREN = 12;

	/** @var array<string, array<string,mixed>>|null Index (with filtered additions) for this request. */
	private ?array $index = null;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'uncoder_wb/mcp/tools', array( $this, 'register_tools' ) );
	}

	/**
	 * Known categories in display order.
	 *
	 * @return array<string,string> id => label
	 */
	public static function categories(): array {
		return array(
			'header'       => __( 'Headers', 'uncoder' ),
			'hero'         => __( 'Heroes', 'uncoder' ),
			'features'     => __( 'Features', 'uncoder' ),
			'social-proof' => __( 'Social proof', 'uncoder' ),
			'pricing'      => __( 'Pricing', 'uncoder' ),
			'faq'          => __( 'FAQ', 'uncoder' ),
			'cta'          => __( 'Call to action', 'uncoder' ),
			'content'      => __( 'Content', 'uncoder' ),
			'contact'      => __( 'Contact', 'uncoder' ),
			'blog'         => __( 'Blog', 'uncoder' ),
			'footer'       => __( 'Footers', 'uncoder' ),
		);
	}

	/* ------------------------------------------------------------------ Index */

	/**
	 * Every pattern (metadata only; elements are loaded by get()).
	 *
	 * @return array<string, array<string,mixed>> id => { id, title, category, description, keywords, shape, file?|elements? }
	 */
	public function all(): array {
		if ( null !== $this->index ) {
			return $this->index;
		}
		$patterns = $this->bundled();

		/**
		 * Filters the section pattern library.
		 *
		 * Add a pattern with either inline elements or a JSON file (same shape as the bundled files):
		 *   $patterns['my-hero'] = array(
		 *     'id'          => 'my-hero',
		 *     'title'       => 'Hero: product launch',
		 *     'category'    => 'hero',
		 *     'description' => 'What it looks like and when to use it (read by AI clients).',
		 *     'keywords'    => array( 'hero', 'launch' ),
		 *     'elements'    => array( ... ), // or 'file' => '/absolute/path/my-hero.json'
		 *   );
		 *
		 * @param array<string, array<string,mixed>> $patterns Patterns keyed by id.
		 */
		$filtered = apply_filters( 'uncoder_wb/patterns', $patterns );

		$index = array();
		foreach ( is_array( $filtered ) ? $filtered : array() as $key => $pattern ) {
			if ( ! is_array( $pattern ) ) {
				continue;
			}
			if ( ! isset( $pattern['id'] ) && is_string( $key ) ) {
				$pattern['id'] = $key;
			}
			$entry = isset( $patterns[ $key ] ) && $patterns[ $key ] === $pattern ? $pattern : $this->normalize_entry( $pattern );
			if ( null !== $entry ) {
				$index[ $entry['id'] ] = $entry;
			}
		}
		$this->index = $index;
		return $index;
	}

	/**
	 * Patterns shipped with the plugin, cached in a transient that is rebuilt whenever a file changes.
	 *
	 * @return array<string, array<string,mixed>>
	 */
	private function bundled(): array {
		$files = glob( UNCODER_WB_PATH . 'assets/data/patterns/*.json' );
		$files = is_array( $files ) ? $files : array();
		sort( $files );

		$signature = UNCODER_WB_VERSION . '/' . self::CACHE_VERSION;
		foreach ( $files as $file ) {
			$signature .= '|' . basename( $file ) . ':' . (int) filemtime( $file ) . ':' . (int) filesize( $file );
		}
		$signature = md5( $signature );

		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && ( $cached['signature'] ?? '' ) === $signature && is_array( $cached['items'] ?? null ) ) {
			$items = array();
			foreach ( $cached['items'] as $id => $item ) {
				// Store paths relative to the plugin so a moved install does not break the cache.
				$item['file']  = UNCODER_WB_PATH . $item['file'];
				$items[ $id ] = $item;
			}
			return $items;
		}

		$items = array();
		$store = array();
		foreach ( $files as $file ) {
			$data = self::read_file( $file );
			if ( null === $data ) {
				continue;
			}
			$entry = $this->normalize_entry( $data );
			if ( null === $entry ) {
				continue;
			}
			// Keep only a file reference: elements are read on demand by get().
			unset( $entry['elements'] );
			$entry['file']         = $file;
			$items[ $entry['id'] ] = $entry;
			$store[ $entry['id'] ] = array_merge( $entry, array( 'file' => substr( $file, strlen( UNCODER_WB_PATH ) ) ) );
		}
		set_transient(
			self::TRANSIENT,
			array(
				'signature' => $signature,
				'items'     => $store,
			),
			WEEK_IN_SECONDS
		);
		return $items;
	}

	/**
	 * Validates one pattern definition and reduces it to index metadata (+ shape).
	 *
	 * @param array<string,mixed> $pattern Raw definition.
	 * @return array<string,mixed>|null
	 */
	private function normalize_entry( array $pattern ): ?array {
		$id = isset( $pattern['id'] ) && is_string( $pattern['id'] ) ? sanitize_key( $pattern['id'] ) : '';
		if ( '' === $id ) {
			return null;
		}
		$elements = null;
		if ( isset( $pattern['elements'] ) && is_array( $pattern['elements'] ) ) {
			$elements = $pattern['elements'];
		} elseif ( isset( $pattern['file'] ) && is_string( $pattern['file'] ) ) {
			$data     = self::read_file( $pattern['file'] );
			$elements = is_array( $data['elements'] ?? null ) ? $data['elements'] : null;
		}
		if ( ! $elements ) {
			return null;
		}
		$keywords = array_values( array_filter( array_map( 'sanitize_text_field', array_filter( (array) ( $pattern['keywords'] ?? array() ), 'is_string' ) ) ) );
		$entry    = array(
			'id'          => $id,
			'title'       => sanitize_text_field( (string) ( $pattern['title'] ?? $id ) ),
			'category'    => sanitize_key( (string) ( $pattern['category'] ?? 'content' ) ),
			'description' => sanitize_text_field( (string) ( $pattern['description'] ?? '' ) ),
			'keywords'    => $keywords,
			'shape'       => self::shape( $elements ),
		);
		if ( isset( $pattern['file'] ) && is_string( $pattern['file'] ) && ! isset( $pattern['elements'] ) ) {
			$entry['file'] = $pattern['file'];
		} else {
			$entry['elements'] = $elements;
		}
		return $entry;
	}

	/**
	 * @return array<string,mixed>|null Decoded pattern file.
	 */
	private static function read_file( string $file ): ?array {
		if ( '.json' !== substr( $file, -5 ) || ! is_readable( $file ) ) {
			return null;
		}
		$json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Public metadata of the patterns, optionally filtered by category and search words.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public function list( string $category = '', string $search = '', bool $with_shape = false ): array {
		$category = sanitize_key( $category );
		$split    = preg_split( '/\s+/', strtolower( trim( $search ) ) );
		$words    = array_filter( is_array( $split ) ? $split : array() );
		$order    = array_flip( array_keys( self::categories() ) );
		$out      = array();
		foreach ( $this->all() as $entry ) {
			if ( '' !== $category && $entry['category'] !== $category ) {
				continue;
			}
			if ( $words ) {
				$haystack = strtolower( implode( ' ', array_merge( array( $entry['id'], $entry['title'], $entry['category'], $entry['description'] ), $entry['keywords'] ) ) );
				foreach ( $words as $word ) {
					if ( false === strpos( $haystack, $word ) ) {
						continue 2;
					}
				}
			}
			$item = array(
				'id'          => $entry['id'],
				'title'       => $entry['title'],
				'category'    => $entry['category'],
				'description' => $entry['description'],
				'keywords'    => $entry['keywords'],
			);
			if ( $with_shape ) {
				$item['shape'] = $entry['shape'];
			}
			$out[] = $item;
		}
		// Category order first, then the order patterns were registered in.
		$position = array_flip( array_keys( $this->all() ) );
		usort(
			$out,
			static function ( $a, $b ) use ( $order, $position ) {
				$ca = $order[ $a['category'] ] ?? 99;
				$cb = $order[ $b['category'] ] ?? 99;
				return $ca === $cb ? $position[ $a['id'] ] <=> $position[ $b['id'] ] : $ca <=> $cb;
			}
		);
		return $out;
	}

	/**
	 * Categories that have at least one pattern, with labels and counts.
	 *
	 * @return array<int, array{id:string,label:string,count:int}>
	 */
	public function category_counts(): array {
		$counts = array();
		foreach ( $this->all() as $entry ) {
			$counts[ $entry['category'] ] = ( $counts[ $entry['category'] ] ?? 0 ) + 1;
		}
		$labels = self::categories();
		$out    = array();
		foreach ( $labels as $id => $label ) {
			if ( isset( $counts[ $id ] ) ) {
				$out[] = array(
					'id'    => $id,
					'label' => $label,
					'count' => $counts[ $id ],
				);
				unset( $counts[ $id ] );
			}
		}
		foreach ( $counts as $id => $count ) {
			$out[] = array(
				'id'    => (string) $id,
				'label' => ucwords( str_replace( '-', ' ', (string) $id ) ),
				'count' => $count,
			);
		}
		return $out;
	}

	/**
	 * Index entry of one pattern.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find( string $id ): ?array {
		return $this->all()[ sanitize_key( $id ) ] ?? null;
	}

	/**
	 * The pattern's element tree, sanitized, with fresh element ids and repeater row ids.
	 *
	 * @return array<int, array<string,mixed>>|null Null when the pattern does not exist.
	 */
	public function get( string $id ): ?array {
		$entry = $this->find( $id );
		if ( null === $entry ) {
			return null;
		}
		$elements = $entry['elements'] ?? null;
		if ( null === $elements && isset( $entry['file'] ) ) {
			$data     = self::read_file( (string) $entry['file'] );
			$elements = $data['elements'] ?? null;
		}
		if ( ! is_array( $elements ) ) {
			return null;
		}
		$elements = self::replace_tokens( $elements, array( self::PLACEHOLDER => UNCODER_WB_URL . 'assets/img/placeholder.svg' ) );
		foreach ( $elements as &$node ) {
			self::strip_ids( $node );
		}
		unset( $node );
		return ( new Tree( 'sanitize' ) )->process( $elements );
	}

	/**
	 * @param mixed                $value Value.
	 * @param array<string,string> $map   Token => replacement.
	 * @return mixed
	 */
	private static function replace_tokens( $value, array $map ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::replace_tokens( $v, $map );
			}
			return $value;
		}
		return is_string( $value ) && false !== strpos( $value, '{{' ) ? strtr( $value, $map ) : $value;
	}

	/**
	 * Drops ids and repeater row ids so the sanitizer assigns fresh ones.
	 *
	 * @param mixed $node Node (by reference).
	 */
	private static function strip_ids( &$node ): void {
		if ( ! is_array( $node ) ) {
			return;
		}
		unset( $node['id'] );
		if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
			foreach ( $node['settings'] as &$value ) {
				if ( is_array( $value ) && isset( $value[0] ) && is_array( $value[0] ) ) {
					foreach ( $value as &$row ) {
						if ( is_array( $row ) ) {
							unset( $row['_id'] );
						}
					}
					unset( $row );
				}
			}
			unset( $value );
		}
		if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as &$child ) {
				self::strip_ids( $child );
			}
			unset( $child );
		}
	}

	/* ------------------------------------------------------------------ Schematic (thumbnails) */

	/**
	 * A compact description of the layout used by the editor to draw a schematic thumbnail:
	 * containers { t:"c", d:"r"|"c"|"g", n?:cols, w?:width%, bg?:"dark"|"tint"|"brand", al?:"c", rv?:1, k:[…] },
	 * widgets { t: h|e|x|b|i|o|k|l|r|g, lv?: heading level, al?: "c", v?: "o" (outline button) }.
	 *
	 * @param array<int, mixed> $nodes Nodes.
	 * @return array<int, array<string,mixed>>
	 */
	public static function shape( array $nodes, int $depth = 0 ): array {
		$out = array();
		foreach ( array_slice( array_values( $nodes ), 0, self::SHAPE_CHILDREN ) as $node ) {
			if ( ! is_array( $node ) || empty( $node['type'] ) ) {
				continue;
			}
			$type = (string) $node['type'];
			$s    = is_array( $node['settings'] ?? null ) ? $node['settings'] : array();
			if ( 'container' === $type ) {
				$item = array( 't' => 'c' );
				if ( 'grid' === ( $s['layout'] ?? '' ) ) {
					$item['d'] = 'g';
					$item['n'] = self::grid_columns( $s );
				} else {
					$dir       = (string) ( $s['direction'] ?? 'column' );
					$item['d'] = 0 === strpos( $dir, 'row' ) ? 'r' : 'c';
					if ( '-reverse' === substr( $dir, -8 ) ) {
						$item['rv'] = 1;
					}
				}
				if ( is_array( $s['width'] ?? null ) && '%' === ( $s['width']['unit'] ?? '' ) && is_numeric( $s['width']['size'] ?? '' ) ) {
					$item['w'] = (int) round( (float) $s['width']['size'] );
				}
				$bg = self::tone( $s );
				if ( '' !== $bg ) {
					$item['bg'] = $bg;
				}
				if ( 'center' === ( $s['align'] ?? '' ) ) {
					$item['al'] = 'c';
				}
				$item['k'] = $depth < self::SHAPE_DEPTH && ! empty( $node['children'] ) && is_array( $node['children'] ) ? self::shape( $node['children'], $depth + 1 ) : array();
				$out[]     = $item;
				continue;
			}
			$out[] = self::widget_shape( $type, $s );
		}
		return $out;
	}

	private static function grid_columns( array $s ): int {
		if ( isset( $s['grid_template'] ) && is_string( $s['grid_template'] ) && '' !== trim( $s['grid_template'] ) ) {
			// Split on spaces outside parentheses: "minmax(0, 1fr) 2fr" is two tracks.
			$tracks = preg_split( '/\s+(?![^(]*\))/', trim( $s['grid_template'] ) );
			return max( 1, min( 12, is_array( $tracks ) ? count( $tracks ) : 1 ) );
		}
		return max( 1, min( 12, (int) ( $s['grid_columns'] ?? 3 ) ) );
	}

	/**
	 * "dark" (light text on a dark band), "brand" (primary/accent band), "tint" (any other background) or "".
	 */
	private static function tone( array $s ): string {
		$color = '';
		if ( is_array( $s['background'] ?? null ) ) {
			$bg = $s['background'];
			if ( ! empty( $bg['image']['url'] ) ) {
				return 'dark';
			}
			$color = (string) ( $bg['color'] ?? '' );
		}
		if ( '' === $color ) {
			return '';
		}
		if ( preg_match( '/--uncoder-c-(primary|accent)\b/', $color ) ) {
			return 'brand';
		}
		if ( ! empty( $s['text_color'] ) || preg_match( '/--uncoder-c-(secondary|heading|black)\b/', $color ) ) {
			return 'dark';
		}
		return 'tint';
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function widget_shape( string $type, array $s ): array {
		$center = 'center' === ( $s['align'] ?? '' );
		switch ( $type ) {
			case 'heading':
				$tag  = (string) ( $s['tag'] ?? 'h2' );
				$item = preg_match( '/^h([1-6])$/', $tag, $m ) ? array( 't' => 'h', 'lv' => (int) $m[1] ) : array( 't' => 'e' );
				break;
			case 'text-editor':
			case 'icon-list':
			case 'blockquote':
			case 'alert':
				$item = array( 't' => 'x' );
				break;
			case 'button':
				$item = array( 't' => 'b' );
				if ( in_array( $s['variant'] ?? 'primary', array( 'outline', 'ghost', 'link' ), true ) ) {
					$item['v'] = 'o';
				}
				break;
			case 'image':
			case 'video':
			case 'image-carousel':
			case 'image-gallery':
			case 'image-compare':
			case 'google-maps':
				$item = array( 't' => 'i' );
				break;
			case 'icon':
			case 'star-rating':
				$item = array( 't' => 'o' );
				break;
			case 'icon-box':
			case 'image-box':
			case 'testimonial':
			case 'price-table':
			case 'team-member':
			case 'counter':
			case 'flip-box':
			case 'call-to-action':
				$item = array( 't' => 'k', 'y' => $type );
				break;
			case 'accordion':
			case 'tabs':
			case 'steps':
			case 'timeline':
			case 'posts':
			case 'loop-grid':
			case 'form':
			case 'table':
				$item = array( 't' => 'l', 'y' => $type );
				break;
			case 'logo-grid':
			case 'social-icons':
			case 'nav-menu':
				$item = array( 't' => 'r', 'y' => $type );
				break;
			case 'site-logo':
			case 'site-title':
				$item = array( 't' => 'g' );
				break;
			default:
				$item = array( 't' => 'x' );
		}
		if ( $center ) {
			$item['al'] = 'c';
		}
		return $item;
	}

	/* ------------------------------------------------------------------ REST */

	public function register_routes(): void {
		$can = static fn() => current_user_can( 'edit_posts' );
		register_rest_route(
			'uncoder/v1',
			'/patterns',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_list' ),
				'permission_callback' => $can,
				'args'                => array(
					'category' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
					'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
		register_rest_route(
			'uncoder/v1',
			'/patterns/(?P<id>[a-z0-9_\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get' ),
				'permission_callback' => $can,
			)
		);
	}

	public function rest_list( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'patterns'   => $this->list( (string) $request->get_param( 'category' ), (string) $request->get_param( 'search' ), true ),
				'categories' => $this->category_counts(),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get( WP_REST_Request $request ) {
		$id       = (string) $request['id'];
		$entry    = $this->find( $id );
		$elements = $entry ? $this->get( $id ) : null;
		if ( ! $entry || null === $elements ) {
			return new WP_Error( 'uncoder_pattern_not_found', __( 'Pattern not found.', 'uncoder' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response(
			array(
				'id'       => $entry['id'],
				'title'    => $entry['title'],
				'category' => $entry['category'],
				'elements' => $elements,
			)
		);
	}

	/* ------------------------------------------------------------------ MCP */

	public function register_tools( Registry $registry ): void {
		$ro = array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false );

		$registry->add(
			array(
				'name'        => 'list_patterns',
				'title'       => 'List section patterns',
				'description' => 'Professionally designed, on-brand section patterns (header, hero, features, social proof, pricing, FAQ, CTA, content, contact, blog, footer). Start new sections from a pattern: call get_pattern(id), adapt the copy, then insert it. Returns id, title, category, description and keywords.',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'category' => array(
							'type'        => 'string',
							'enum'        => array_keys( self::categories() ),
							'description' => 'Only patterns of this category.',
						),
						'search'   => array(
							'type'        => 'string',
							'description' => 'Words to match in the title, description and keywords, e.g. "testimonial cards".',
						),
					),
				),
				'callback'    => function ( array $a ) {
					$patterns = $this->list( (string) ( $a['category'] ?? '' ), (string) ( $a['search'] ?? '' ) );
					return array(
						'patterns'   => $patterns,
						'categories' => $this->category_counts(),
						'next'       => $patterns ? 'Call get_pattern with an id to get its element tree.' : 'No match: call list_patterns without filters to see everything.',
					);
				},
			)
		);

		$registry->add(
			array(
				'name'        => 'get_pattern',
				'title'       => 'Get section pattern',
				'description' => 'Returns the element tree of a section pattern (one top-level container, fresh ids), ready to pass to create_page (elements) or edit_elements (op "insert", parent_id null). Adapt the copy first: replace every [bracketed placeholder], and swap placeholder images for uploaded media ({"id": attachment_id} + alt).',
				'scope'       => 'read',
				'annotations' => $ro,
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'string',
							'description' => 'Pattern id from list_patterns, e.g. "hero-split-image".',
						),
					),
					'required'   => array( 'id' ),
				),
				'callback'    => function ( array $a ) {
					$id       = (string) ( $a['id'] ?? '' );
					$entry    = $this->find( $id );
					$elements = $entry ? $this->get( $id ) : null;
					if ( ! $entry || null === $elements ) {
						return new WP_Error( 'not_found', sprintf( 'Unknown pattern "%s". Call list_patterns for valid ids.', $id ) );
					}
					return array(
						'id'          => $entry['id'],
						'title'       => $entry['title'],
						'category'    => $entry['category'],
						'description' => $entry['description'],
						'elements'    => $elements,
						'next'        => 'Rewrite the copy for this site (keep one h1 per page — only hero patterns use h1), replace [bracketed placeholders] and placeholder images (search_images → upload_media → {"id": …} with alt text), keep the Design System tokens, then insert with create_page or edit_elements.',
					);
				},
			)
		);
	}

	/**
	 * The module instance (null before plugins_loaded).
	 */
	public static function instance(): ?self {
		$module = Plugin::instance()->module( 'patterns' );
		return $module instanceof self ? $module : null;
	}
}
