<?php
/**
 * Import from Elementor: converts Elementor pages, templates and Site Settings into Uncoder designs.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Editor\Editor;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Rest;
use Uncoder\Builder\Site\Elementor_Import\Converter;
use Uncoder\Builder\Site\Elementor_Import\Kit_Map;
use Uncoder\Builder\Site\Elementor_Import\Uicore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Works without Elementor installed: everything is read from post meta (_elementor_data, the active kit's
 * _elementor_page_settings). The Elementor data is never changed, so a page converted in "replace" mode can
 * go back to Elementor.
 *
 * REST (namespace Rest::NS):
 * - GET  elementor-import/scan     pages, posts and templates with an Elementor design, and the kit.
 * - POST elementor-import/convert  { ids: int[], kit: bool, mode: "copy"|"replace" }.
 * - POST elementor-import/upload   multipart "file": an Elementor template export (.json) or a .zip of them.
 */
final class Elementor_Import {

	/** On converted copies and templates: the Elementor post they came from. */
	public const META_SOURCE = '_uncoder_elementor_source';

	/** Records that the Elementor kit was imported (later conversions then use the Design System variables). */
	public const OPTION = 'uncoder_wb_elementor_import';

	/** Elementor template types → Uncoder template types (anything else becomes a section). */
	private const TEMPLATE_TYPES = array(
		'header'         => 'header',
		'footer'         => 'footer',
		'single-post'    => 'single-post',
		'single-page'    => 'single-page',
		'single'         => 'single',
		'product'        => 'single-product',
		'archive'        => 'archive',
		'product-archive' => 'product-archive',
		'search-results' => 'search-results',
		'error-404'      => 'error-404',
		'popup'          => 'popup',
		'loop-item'      => 'loop-item',
		'section'        => 'section',
		'container'      => 'section',
		'page'           => 'section',
		'widget'         => 'section',
	);

	private const MAX_ITEMS = 50;

	private const MAX_JSON = 20 * MB_IN_BYTES;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'init', array( $this, 'compat_shortcode' ), 30 );
	}

	/**
	 * Once Elementor Pro is off, its [elementor-template id="…"] shortcodes (in content, widgets, theme code)
	 * print the Uncoder template converted from that Elementor template, instead of the raw shortcode.
	 */
	public function compat_shortcode(): void {
		if ( class_exists( 'WooCommerce' ) && ! shortcode_exists( 'uncoder_wc_archive_products' ) ) {
			add_shortcode( 'uncoder_wc_archive_products', array( self::class, 'wc_archive_products' ) );
		}
		// The same for UiCore's [uicore-block id="…"] once UiCore is off.
		foreach ( array( 'elementor-template', 'uicore-block' ) as $tag ) {
			if ( shortcode_exists( $tag ) ) {
				continue;
			}
			add_shortcode(
				$tag,
				static function ( $atts ) use ( $tag ): string {
					$source = absint( shortcode_atts( array( 'id' => 0 ), (array) $atts, $tag )['id'] );
					$target = $source ? ( self::template_map()[ $source ] ?? 0 ) : 0;
					return $target ? do_shortcode( '[uncoder_template id="' . $target . '"]' ) : '';
				}
			);
		}
	}

	/**
	 * [uncoder_wc_archive_products columns="" rows=""]: the products of the shop or product archive being viewed, as
	 * WooCommerce's archive template prints them, for templates converted from Elementor's Archive Products widget.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 */
	public static function wc_archive_products( $atts ): string {
		if ( ! function_exists( 'woocommerce_product_loop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
			return '';
		}
		$atts    = shortcode_atts( array( 'columns' => '', 'rows' => '' ), (array) $atts, 'uncoder_wc_archive_products' );
		$columns = absint( $atts['columns'] );
		if ( $columns ) {
			wc_set_loop_prop( 'columns', $columns );
		}
		$columns = absint( wc_get_loop_prop( 'columns' ) );
		ob_start();
		if ( woocommerce_product_loop() ) {
			do_action( 'woocommerce_before_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
			woocommerce_product_loop_start();
			if ( wc_get_loop_prop( 'total' ) ) {
				while ( have_posts() ) {
					the_post();
					do_action( 'woocommerce_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
					wc_get_template_part( 'content', 'product' );
				}
			}
			woocommerce_product_loop_end();
			do_action( 'woocommerce_after_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
		} else {
			do_action( 'woocommerce_no_products_found' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
		}
		rewind_posts();
		wp_reset_postdata();
		return '<div class="woocommerce' . ( $columns ? ' columns-' . $columns : '' ) . '">' . ob_get_clean() . '</div>';
	}

	public function routes(): void {
		$editor = static fn() => current_user_can( 'edit_posts' );
		$admin  = static fn() => current_user_can( 'manage_options' );
		register_rest_route( Rest::NS, '/elementor-import/scan', array( 'methods' => 'GET', 'callback' => array( $this, 'scan' ), 'permission_callback' => $editor ) );
		register_rest_route(
			Rest::NS,
			'/elementor-import/convert',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'convert_request' ),
				'permission_callback' => $editor,
				'args'                => array(
					'ids'  => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
					),
					'kit'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'mode' => array(
						'type'    => 'string',
						'enum'    => array( 'copy', 'replace' ),
						'default' => 'copy',
					),
				),
			)
		);
		register_rest_route( Rest::NS, '/elementor-import/upload', array( 'methods' => 'POST', 'callback' => array( $this, 'upload' ), 'permission_callback' => $admin ) );
	}

	/* ------------------------------------------------------------------ Public converters */

	/**
	 * Elementor elements → Uncoder elements.
	 *
	 * @param array<int, mixed>   $elementor_elements Decoded _elementor_data (or a template's "content").
	 * @param array<string,mixed> $report             Filled with: elements, converted, unmapped, settings, notes, remote.
	 * @param array<string,mixed> $options            See Elementor_Import\Converter (globals, kit, remote, resolve…).
	 * @return array<int, array<string,mixed>>
	 */
	public static function convert( array $elementor_elements, array &$report, array $options = array() ): array {
		$converter = new Converter( $options );
		$elements  = $converter->run( $elementor_elements );
		$report    = $converter->report;
		return $elements;
	}

	/**
	 * Elementor kit settings → a partial Design System for Kit::sanitize() / Kit::update().
	 *
	 * @param array<string,mixed> $elementor_kit_settings The active kit's _elementor_page_settings.
	 * @return array<string,mixed>
	 */
	public static function convert_kit( array $elementor_kit_settings ): array {
		return Kit_Map::convert( $elementor_kit_settings );
	}

	/* ------------------------------------------------------------------ Site data */

	/** @return array<string,mixed> The active Elementor kit's settings (empty without a kit). */
	public static function elementor_kit(): array {
		$id       = (int) get_option( 'elementor_active_kit' );
		$settings = $id ? get_post_meta( $id, '_elementor_page_settings', true ) : array();
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * A post's Elementor elements.
	 *
	 * @return array<int, mixed>|null
	 */
	public static function elementor_data( int $post_id ): ?array {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = json_decode( wp_unslash( $raw ), true );
		}
		if ( ! is_array( $data ) ) {
			// Some sites carry a shortcode whose attribute quotes were saved unescaped ("[x a="1"]" inside a JSON
			// string): Elementor then shows the page from its element cache only. Escape those quotes and try again.
			$fixed = (string) preg_replace_callback(
				'/\[[a-z_][a-z0-9_-]*\s[^\]\[]*\]/i',
				static fn( array $m ): string => (string) preg_replace( '/(?<!\\\\)"/', '\\"', $m[0] ),
				$raw
			);
			$data  = $fixed !== $raw ? json_decode( $fixed, true ) : null;
		}
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Converter options for data from this site.
	 *
	 * @return array<string,mixed>
	 */
	private static function site_options( bool $kit_imported ): array {
		$kit = self::elementor_kit();
		return array(
			'globals'        => $kit_imported ? 'vars' : ( $kit ? 'values' : 'fallback' ),
			'kit'            => $kit,
			'default_colors' => 'yes' !== get_option( 'elementor_disable_color_schemes' ),
			'default_fonts'  => 'yes' !== get_option( 'elementor_disable_typography_schemes' ),
			'remote'         => false,
			'resolve'        => static fn( int $id ) => self::elementor_data( $id ),
			'templates'      => self::template_map(),
		);
	}

	/** @return array<int,int> Elementor template id => Uncoder template converted from it. */
	private static function template_map(): array {
		$ids = get_posts(
			array(
				'post_type'      => Post_Types::TEMPLATE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'meta_key'       => self::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		$map = array();
		foreach ( $ids as $id ) {
			$source = (int) get_post_meta( (int) $id, self::META_SOURCE, true );
			if ( $source && ! isset( $map[ $source ] ) ) {
				$map[ $source ] = (int) $id;
			}
		}
		return $map;
	}

	private static function kit_imported(): bool {
		$state = get_option( self::OPTION );
		return is_array( $state ) && ! empty( $state['kit'] );
	}

	/** Editing rights for a post (Elementor's library post type is not registered without Elementor). */
	private static function can_edit( \WP_Post $post ): bool {
		return post_type_exists( $post->post_type ) ? current_user_can( 'edit_post', $post->ID ) : current_user_can( 'manage_options' );
	}

	/** Site-wide template types (everything but sections) need the theme capability, like in Theme Builder. */
	private static function can_create_template( string $type ): bool {
		return current_user_can( 'edit_posts' ) && ( ! Post_Types::needs_theme_caps( $type ) || current_user_can( 'edit_theme_options' ) );
	}

	/* ------------------------------------------------------------------ Scan */

	public function scan(): WP_REST_Response {
		global $wpdb;
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			WHERE m.meta_key = '_elementor_data' AND m.meta_value NOT IN ('', '[]')
			AND p.post_status IN ('publish', 'draft', 'private', 'pending', 'future')
			AND p.post_type NOT IN ('revision', 'attachment', 'nav_menu_item', 'customize_changeset')
			ORDER BY p.post_type ASC, p.menu_order ASC, p.post_title ASC LIMIT 1000"
		);
		$supported = Plugin::instance()->documents()->post_types();
		$copies    = self::copies();
		$items     = array();
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post || ! self::can_edit( $post ) ) {
				continue;
			}
			$uicore   = Uicore::is_template( $post );
			$library  = 'elementor_library' === $post->post_type || $uicore;
			$template = $uicore ? Uicore::kind( $post->ID ) : ( $library ? (string) get_post_meta( $post->ID, '_elementor_template_type', true ) : '' );
			if ( in_array( $template, array( 'kit' ), true ) ) {
				continue;
			}
			$object  = get_post_type_object( $post->post_type );
			$raw     = get_post_meta( $post->ID, '_elementor_data', true );
			$label   = $uicore ? Uicore::label( $post->ID ) : __( 'Elementor template', 'uncoder' );
			$items[] = array(
				'id'          => (int) $post->ID,
				'title'       => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'uncoder' ),
				'type'        => $post->post_type,
				'typeLabel'   => $library ? $label : ( $object ? $object->labels->singular_name : $post->post_type ),
				'template'    => $template,
				'templateAs'  => $uicore ? Uicore::template_type( $post->ID ) : ( $library ? self::template_type( $template ) : '' ),
				'status'      => $post->post_status,
				'modified'    => (string) get_post_modified_time( 'c', true, $post ),
				'uncoder'     => Utils::is_builder_post( $post->ID ) && '' !== (string) get_post_meta( $post->ID, Utils::META_DATA, true ),
				'replaceable' => ! $library && in_array( $post->post_type, $supported, true ),
				'copyable'    => $library || in_array( $post->post_type, $supported, true ),
				'elements'    => is_string( $raw ) ? substr_count( $raw, '"elType"' ) : 0,
				'copies'      => $copies[ (int) $post->ID ] ?? array(),
				'viewUrl'     => $library ? '' : (string) get_permalink( $post ),
				'editUrl'     => Utils::is_builder_post( $post->ID ) ? Editor::url( $post->ID ) : '',
			);
		}
		$kit     = self::elementor_kit();
		$summary = $kit ? Kit_Map::summary( $kit ) : array( 'colors' => 0, 'fonts' => 0 );
		return new WP_REST_Response(
			array(
				'items'     => $items,
				'kit'       => array(
					'exists'   => (bool) $kit,
					'colors'   => $summary['colors'],
					'fonts'    => $summary['fonts'],
					'imported' => self::kit_imported(),
					'canImport' => current_user_can( 'manage_options' ),
				),
				'elementor' => defined( 'ELEMENTOR_VERSION' ),
				'canUpload' => current_user_can( 'manage_options' ),
				'maxUpload' => wp_max_upload_size(),
			)
		);
	}

	/** @return array<int, array<int, array<string,mixed>>> Elementor post id => Uncoder copies of it. */
	private static function copies(): array {
		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 1000,
				'fields'         => 'ids',
				'meta_key'       => self::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		$ids = array_merge( $ids, array_values( self::template_map() ) );
		$out = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$source = (int) get_post_meta( $id, self::META_SOURCE, true );
			$post   = get_post( $id );
			if ( $source && $post ) {
				$out[ $source ][] = array(
					'id'      => $id,
					'title'   => $post->post_title,
					'status'  => $post->post_status,
					'editUrl' => Editor::url( $id ),
				);
			}
		}
		return $out;
	}

	private static function template_type( string $elementor_type ): string {
		$type = self::TEMPLATE_TYPES[ $elementor_type ] ?? 'section';
		return isset( Post_Types::TEMPLATE_TYPES[ $type ] ) ? $type : 'section';
	}

	/* ------------------------------------------------------------------ Convert */

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function convert_request( WP_REST_Request $request ) {
		$ids  = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $request->get_param( 'ids' ) ) ) ) ), 0, self::MAX_ITEMS );
		$mode = 'replace' === $request->get_param( 'mode' ) ? 'replace' : 'copy';
		$out  = array(
			'kit'   => null,
			'items' => array(),
		);
		if ( rest_sanitize_boolean( $request->get_param( 'kit' ) ) ) {
			$out['kit'] = current_user_can( 'manage_options' )
				? $this->import_kit()
				: array(
					'ok'      => false,
					'message' => __( 'Only administrators can change the Design System.', 'uncoder' ),
				);
		}
		// Templates first (loop items and popups before the rest) so pages that use them point at the new ones.
		$rank = static function ( int $id ): int {
			if ( Uicore::POST_TYPE === get_post_type( $id ) ) {
				return in_array( Uicore::kind( $id ), array( 'mm', 'popup', 'block' ), true ) ? 1 : 2;
			}
			if ( 'elementor_library' !== get_post_type( $id ) ) {
				return 3;
			}
			return in_array( (string) get_post_meta( $id, '_elementor_template_type', true ), array( 'loop-item', 'popup' ), true ) ? 1 : 2;
		};
		usort( $ids, static fn( $a, $b ) => $rank( $a ) <=> $rank( $b ) );
		foreach ( $ids as $id ) {
			$out['items'][] = $this->convert_post( $id, $mode );
		}
		return new WP_REST_Response( $out );
	}

	/**
	 * Imports the Elementor kit into the Design System (a restore point is kept).
	 *
	 * @return array<string,mixed>
	 */
	private function import_kit(): array {
		$settings = self::elementor_kit();
		if ( ! $settings ) {
			return array(
				'ok'      => false,
				'message' => __( 'This site has no Elementor Site Settings (kit) to import.', 'uncoder' ),
			);
		}
		// The kit's text styles may use Elementor Pro's uploaded fonts: bring the files along first.
		$custom_fonts = self::import_elementor_fonts();
		$partial      = Uicore::kit( self::convert_kit( $settings ) );
		// Elementor loads Google fonts as static files, without the optical-size axis: the text keeps that cut (and its
		// width, so headings wrap where they did) instead of the display cut variable fonts use at large sizes.
		$partial['theme'] = array_merge( (array) ( $partial['theme'] ?? array() ), array( 'optical_sizing' => 'none' ) );
		$kit              = Plugin::instance()->kit();
		if ( isset( $partial['custom_css'] ) ) {
			$partial['custom_css'] = Converter::site_css( (string) $partial['custom_css'] );
			$existing              = (string) $kit->get( 'custom_css', '' );
			if ( false !== strpos( $existing, $partial['custom_css'] ) ) {
				unset( $partial['custom_css'] );
			} else {
				$partial['custom_css'] = trim( $existing . "\n\n/* Site CSS imported from Elementor */\n" . $partial['custom_css'] );
			}
		}
		$errors = array();
		$clean  = $kit->sanitize( $partial, 'sanitize', $errors );
		// Kit::update() merges text styles key by key: a re-import clears what the Elementor fonts no longer set.
		$current = array_column( (array) $kit->get( 'typography', array() ), 'value', 'id' );
		foreach ( (array) ( $clean['typography'] ?? array() ) as $i => $preset ) {
			$id = (string) $preset['id'];
			if ( ! isset( $current[ $id ] ) || ! ( in_array( $id, Converter::SYSTEM, true ) || 0 === strpos( $id, 'e-' ) ) ) {
				continue;
			}
			foreach ( (array) $current[ $id ] as $key => $old ) {
				if ( ! array_key_exists( $key, $preset['value'] ) ) {
					$clean['typography'][ $i ]['value'][ $key ] = is_array( $old ) ? array( 'size' => '', 'unit' => (string) ( $old['unit'] ?? 'px' ) ) : '';
				}
			}
		}
		$kit->update( $clean, __( 'Before Elementor import', 'uncoder' ) );
		update_option(
			self::OPTION,
			array(
				'kit'  => (int) get_option( 'elementor_active_kit' ),
				'time' => time(),
			),
			false
		);
		return array(
			'ok'         => true,
			'colors'     => count( (array) ( $clean['colors'] ?? array() ) ),
			'typography' => count( (array) ( $clean['typography'] ?? array() ) ),
			'fonts'      => array_values( array_filter( array_column( (array) ( $clean['fonts'] ?? array() ), 'family' ) ) ),
			'customFonts' => $custom_fonts,
			'sections'   => array_values( array_intersect( array_keys( $clean ), array( 'theme', 'layout', 'buttons', 'forms', 'breakpoints', 'custom_css' ) ) ),
			'errors'     => $errors,
		);
	}

	/**
	 * Elementor Pro display conditions (_elementor_conditions: "include/singular/portfolio", "exclude/archive/
	 * category/12"…) → Uncoder conditions, and the template type they need: Elementor lets a single-page or
	 * single-post template show on any post type, Uncoder keeps those types to pages / posts and gives other post
	 * types to "single". Elementor Pro shows pages built with Elementor full width, without a single template, so
	 * those pages are left out of a template that covers pages.
	 *
	 * @return array{0: array<int, array<string,mixed>>, 1: string} [ conditions, Uncoder template type ].
	 */
	private static function elementor_conditions( int $source, string $type ): array {
		$out   = array();
		$types = array();
		foreach ( (array) get_post_meta( $source, '_elementor_conditions', true ) as $raw ) {
			$p     = explode( '/', (string) $raw );
			$mode  = 'exclude' === ( $p[0] ?? '' ) ? 'exclude' : 'include';
			$group = (string) ( $p[1] ?? '' );
			$sub   = (string) ( $p[2] ?? '' );
			$ids   = ! empty( $p[3] ) ? array( absint( $p[3] ) ) : array();
			$c     = null;
			if ( 'general' === $group ) {
				$c = array( 'rule' => 'general' );
			} elseif ( 'singular' === $group ) {
				if ( '' === $sub ) {
					$c = array( 'rule' => 'singular' );
				} elseif ( 'front_page' === $sub ) {
					$c = array( 'rule' => 'front_page' );
				} elseif ( 'not_found404' === $sub ) {
					$c = array( 'rule' => 'not_found' );
				} elseif ( post_type_exists( $sub ) ) {
					$c       = array( 'rule' => 'singular', 'post_type' => $sub ) + ( $ids ? array( 'ids' => $ids ) : array() );
					$types[] = $sub;
				}
			} elseif ( 'archive' === $group || 'product_archive' === $group ) {
				if ( '' === $sub ) {
					$c = 'product_archive' === $group ? array( 'rule' => 'archive', 'post_type' => 'product' ) : array( 'rule' => 'archive' );
					if ( 'archive' === $group && 'include' === $mode && class_exists( 'WooCommerce' ) ) {
						// Elementor's "All Archives" leaves the shop and product archives to WooCommerce (and its
						// product archive templates).
						$out[] = array( 'type' => 'exclude', 'rule' => 'archive', 'post_type' => 'product' );
					}
				} elseif ( 'post_archive' === $sub ) {
					// Elementor's "Posts Archive" is the blog page only (its categories, tags… stay with other templates).
					$c = array( 'rule' => 'posts_page' );
				} elseif ( 'shop_page' === $sub ) {
					$c = array( 'rule' => 'archive', 'post_type' => 'product' );
				} elseif ( preg_match( '/^(.+)_archive$/', $sub, $m ) && post_type_exists( $m[1] ) ) {
					$c = array( 'rule' => 'archive', 'post_type' => $m[1] );
				} elseif ( in_array( $sub, array( 'author', 'date', 'search' ), true ) ) {
					$c = array( 'rule' => $sub );
				} elseif ( taxonomy_exists( $sub ) ) {
					$c = array( 'rule' => 'archive', 'taxonomy' => $sub ) + ( $ids ? array( 'ids' => $ids ) : array() );
				}
			}
			if ( $c ) {
				$out[] = array( 'type' => $mode ) + $c;
			}
		}
		$own = array( 'single-page' => 'page', 'single-post' => 'post', 'single-product' => 'product' )[ $type ] ?? '';
		if ( '' !== $own && array_diff( $types, array( $own ) ) ) {
			$type = 'single';
		}
		$pages = false;
		foreach ( $out as $c ) {
			$pages = $pages || ( 'include' === $c['type'] && 'singular' === $c['rule'] && in_array( $c['post_type'] ?? 'page', array( 'page' ), true ) && empty( $c['ids'] ) );
		}
		if ( $pages && in_array( $type, array( 'single', 'single-page' ), true ) ) {
			$built = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'any',
					'posts_per_page' => 500,
					'fields'         => 'ids',
					'meta_key'       => '_elementor_edit_mode', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			if ( $built ) {
				$out[] = array( 'type' => 'exclude', 'rule' => 'singular', 'post_type' => 'page', 'ids' => array_map( 'intval', $built ) );
			}
		}
		$errors = array();
		return array( $out ? \Uncoder\Builder\Theme\Conditions::sanitize( $out, $errors ) : array(), $type );
	}

	/**
	 * Uncoder Elements' sticky header → the header template's behaviour. That add-on makes every container with the
	 * "header" tag sticky and "smart" (back on scroll up) unless switched off, with an optional background for the
	 * stuck state. The header keeps its own background (sites that want it transparent over a hero do that with
	 * CSS on the "not stuck" state, converted by Converter::css_locations()). Without the add-on nothing changes.
	 *
	 * @param array<int, mixed>               $data     Elementor elements of the header template.
	 * @param array<int, array<string,mixed>> $elements Converted elements.
	 * @return array<string,mixed> Header behaviour page settings.
	 */
	private static function header_behaviour( array $data, array &$elements ): array {
		if ( ! defined( 'UNCODER_ELEMENTS_VERSION' ) ) {
			return array();
		}
		$color = static function ( array $s, string $key ): string {
			$id = Converter::global_id( (string) ( $s['__globals__'][ $key ] ?? '' ), 'colors' );
			return '' !== $id ? 'var(--uncoder-c-' . Converter::color_id( $id ) . ')' : Utils::sanitize_color( (string) ( $s[ $key ] ?? '' ) );
		};
		foreach ( $data as $el ) {
			$s = is_array( $el ) ? (array) ( $el['settings'] ?? array() ) : array();
			if ( 'container' !== ( $el['elType'] ?? '' ) || 'header' !== ( $s['html_tag'] ?? '' ) || 'yes' !== ( $s['uncoder_sticky_header'] ?? 'yes' ) ) {
				continue;
			}
			$out = array( 'header_sticky' => 'yes' === ( $s['uncoder_sticky_header_smart'] ?? 'yes' ) ? 'reveal' : 'always' );
			$bg  = $color( $s, 'uncoder_sticky_header_bg_color' );
			if ( '' !== $bg ) {
				$out['header_scrolled_bg'] = $bg;
			}
			return $out;
		}
		return array();
	}

	/**
	 * Elementor Pro's custom fonts (elementor_font posts) → Uncoder custom fonts, so text styles that use them keep
	 * their typeface once Elementor is turned off. A family Uncoder already has is left as it is.
	 *
	 * @return string[] Families added.
	 */
	private static function import_elementor_fonts(): array {
		$have  = Custom_Fonts::all();
		$added = array();
		foreach ( get_posts( array( 'post_type' => 'elementor_font', 'post_status' => 'publish', 'numberposts' => 100 ) ) as $font ) {
			$family = Custom_Fonts::clean_family( $font->post_title );
			if ( '' === $family || isset( $have[ $family ] ) ) {
				continue;
			}
			$category = preg_match( '/serif/i', $family ) && ! preg_match( '/sans/i', $family ) ? 'serif' : 'sans-serif';
			foreach ( (array) get_post_meta( $font->ID, 'elementor_font_files', true ) as $face ) {
				$weight = (string) ( $face['font_weight'] ?? 'normal' );
				$weight = array( 'normal' => '400', 'bold' => '700' )[ $weight ] ?? $weight;
				if ( ! is_array( $face ) || ! preg_match( '/^[1-9]00$/', $weight ) ) {
					continue;
				}
				$style = 'italic' === ( $face['font_style'] ?? '' ) ? 'italic' : 'normal';
				foreach ( array( 'woff2', 'woff', 'ttf' ) as $format ) {
					$path = ! empty( $face[ $format ]['id'] ) ? (string) get_attached_file( (int) $face[ $format ]['id'] ) : '';
					if ( '' !== $path && is_readable( $path ) && true === Custom_Fonts::add_face( $family, $category, $weight, $style, $path, false, false ) ) {
						$added[ $family ] = $family;
					}
				}
			}
		}
		foreach ( Uicore::custom_fonts() as $face ) {
			$family = Custom_Fonts::clean_family( $face['family'] );
			if ( '' === $family || ( isset( $have[ $family ] ) && ! isset( $added[ $family ] ) ) ) {
				continue;
			}
			$category = preg_match( '/serif/i', $family ) && ! preg_match( '/sans/i', $family ) ? 'serif' : 'sans-serif';
			if ( true === Custom_Fonts::add_face( $family, $category, $face['weight'], $face['style'], $face['path'], false, false ) ) {
				$added[ $family ] = $family;
			}
		}
		return array_values( $added );
	}

	/**
	 * @return array<string,mixed> Per-item result.
	 */
	private function convert_post( int $id, string $mode ): array {
		$post = get_post( $id );
		$base = array(
			'id'    => $id,
			'title' => $post ? $post->post_title : '',
			'ok'    => false,
		);
		if ( ! $post || ! self::can_edit( $post ) ) {
			return $base + array( 'message' => __( 'This item does not exist or you cannot edit it.', 'uncoder' ) );
		}
		$data = self::elementor_data( $id );
		if ( null === $data ) {
			return $base + array( 'message' => __( 'No Elementor design was found on this item.', 'uncoder' ) );
		}
		$report   = array();
		$elements = self::convert( $data, $report, self::site_options( self::kit_imported() ) );
		$uicore   = Uicore::is_template( $post );
		$library  = 'elementor_library' === $post->post_type || $uicore;
		$settings = get_post_meta( $id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : array();

		if ( $uicore ) {
			$type = Uicore::template_type( $id );
			if ( ! self::can_create_template( $type ) ) {
				return $base + array( 'message' => __( 'You are not allowed to create this kind of template (headers, footers and other site-wide templates need the theme options capability).', 'uncoder' ) );
			}
			$conds = array();
			if ( ! in_array( $type, array( 'section', 'mega-menu' ), true ) ) {
				$errors = array();
				$conds  = \Uncoder\Builder\Theme\Conditions::sanitize( Uicore::conditions( $id ), $errors );
			}
			$target = self::create_template( $post->post_title, $type, $elements, $id, $settings, $report, $conds );
			if ( is_wp_error( $target ) ) {
				return $base + array( 'message' => $target->get_error_message() );
			}
			if ( 'mega-menu' === $type ) {
				$linked = Uicore::attach_mega_menu( $id, $target );
				/* translators: %d: number of menu items. */
				self::note( $report, sprintf( _n( 'Attached as the mega menu of %d menu item.', 'Attached as the mega menu of %d menu items.', $linked, 'uncoder' ), $linked ) );
			} elseif ( 'pagetitle' === Uicore::kind( $id ) ) {
				self::note( $report, __( 'A UiCore page title: place this section at the top of the pages or templates where it showed.', 'uncoder' ) );
			} elseif ( 'block' === Uicore::kind( $id ) ) {
				self::note( $report, __( 'Its [uicore-block] shortcodes now print this template.', 'uncoder' ) );
			}
			$used = 'template';
		} elseif ( $library ) {
			$type = self::template_type( (string) get_post_meta( $id, '_elementor_template_type', true ) );
			if ( ! self::can_create_template( $type ) ) {
				return $base + array( 'message' => __( 'You are not allowed to create this kind of template (headers, footers and other site-wide templates need the theme options capability).', 'uncoder' ) );
			}
			$behaviour           = 'header' === $type ? self::header_behaviour( $data, $elements ) : array();
			list( $conds, $type ) = in_array( $type, array( 'section', 'loop-item' ), true ) ? array( array(), $type ) : self::elementor_conditions( $id, $type );
			$target              = self::create_template( $post->post_title, $type, $elements, $id, $settings, $report, $conds );
			if ( is_wp_error( $target ) ) {
				return $base + array( 'message' => $target->get_error_message() );
			}
			if ( $behaviour ) {
				Plugin::instance()->documents()->get( $target )->save_page_settings( $behaviour );
				self::note( $report, __( 'The Uncoder Elements sticky header became the header behaviour (sticky, scrolled background).', 'uncoder' ) );
			}
			$used = 'template';
			if ( 'replace' === $mode ) {
				self::note( $report, __( 'Elementor templates are always converted into a new Uncoder template.', 'uncoder' ) );
			}
		} else {
			if ( ! in_array( $post->post_type, Plugin::instance()->documents()->post_types(), true ) ) {
				/* translators: %s: post type. */
				return $base + array( 'message' => sprintf( __( 'Uncoder is not enabled for “%s” (Settings → General → Post types).', 'uncoder' ), $post->post_type ) );
			}
			$object = get_post_type_object( $post->post_type );
			if ( 'replace' === $mode ) {
				$target = $id;
				update_post_meta( $id, Utils::META_MODE, 'builder' );
			} elseif ( ! $object || ! current_user_can( $object->cap->create_posts ) ) {
				return $base + array( 'message' => __( 'You are not allowed to create this kind of content.', 'uncoder' ) );
			} else {
				$target = wp_insert_post(
					wp_slash(
						array(
							'post_type'    => $post->post_type,
							/* translators: %s: title of the Elementor page. */
							'post_title'   => sprintf( __( '%s (Uncoder)', 'uncoder' ), $post->post_title ),
							'post_status'  => 'draft',
							'post_content' => '',
							'post_excerpt' => $post->post_excerpt,
							'post_parent'  => (int) $post->post_parent,
							'menu_order'   => (int) $post->menu_order,
							'post_author'  => get_current_user_id(),
						)
					),
					true
				);
				if ( is_wp_error( $target ) ) {
					return $base + array( 'message' => $target->get_error_message() );
				}
				$target = (int) $target;
				update_post_meta( $target, self::META_SOURCE, $id );
				update_post_meta( $target, Utils::META_MODE, 'builder' );
				$thumb = (int) get_post_thumbnail_id( $id );
				if ( $thumb ) {
					set_post_thumbnail( $target, $thumb );
				}
			}
			$page_template = (string) get_post_meta( $id, '_wp_page_template', true );
			$page_template = '' !== $page_template && 'default' !== $page_template ? $page_template : (string) ( $settings['template'] ?? '' );
			$uncoder_tpl   = array(
				'elementor_canvas'        => 'uncoder-canvas',
				'elementor_header_footer' => 'uncoder-full-width',
				'elementor_theme'         => 'default',
			)[ $page_template ] ?? '';
			// Hello Elementor prints Elementor pages edge to edge on its default template (its boxed page layout is
			// only for other content): without Elementor, Uncoder Full Width keeps that.
			// UiCore themes (Framey…) do the same when their page title is off (Theme Options → Page Title).
			if ( '' === $uncoder_tpl && in_array( $page_template, array( '', 'default' ), true ) && ( 'hello-elementor' === get_template() || Uicore::page_title_off() ) ) {
				$uncoder_tpl = 'uncoder-full-width';
			}
			if ( '' !== $uncoder_tpl && 'page' === $post->post_type ) {
				update_post_meta( $target, '_wp_page_template', $uncoder_tpl );
			}
			$used = $mode;
		}

		$doc    = Plugin::instance()->documents()->get( (int) $target );
		$result = $doc ? $doc->save( $elements, array( 'content_fallback' => 'copy' === $used ) ) : array( 'errors' => array( 'Document not found.' ) );
		if ( $doc && 'template' !== $used ) {
			$page = self::page_settings( $settings, (int) $target, $report );
			if ( $page ) {
				$doc->save_page_settings( $page );
			}
		}
		$target_post = get_post( (int) $target );
		return array(
			'id'      => $id,
			'title'   => $post->post_title,
			'ok'      => true,
			'mode'    => $used,
			'target'  => (int) $target,
			'targetTitle' => $target_post ? $target_post->post_title : '',
			'editUrl' => Editor::url( (int) $target ),
			'viewUrl' => 'template' === $used ? '' : (string) ( 'publish' === get_post_status( (int) $target ) ? get_permalink( (int) $target ) : get_preview_post_link( (int) $target ) ),
			'report'  => self::public_report( $report, (array) ( $result['errors'] ?? array() ) ),
		);
	}

	/**
	 * Elementor page settings Uncoder has: hide title, background color, custom CSS.
	 *
	 * @param array<string,mixed> $s      Elementor page settings.
	 * @param array<string,mixed> $report Report (by reference).
	 * @return array<string,mixed>
	 */
	private static function page_settings( array $s, int $target, array &$report ): array {
		$out = array();
		if ( 'yes' === ( $s['hide_title'] ?? '' ) ) {
			$out['hide_title'] = true;
		}
		if ( 'classic' === ( $s['background_background'] ?? '' ) ) {
			$color = Utils::sanitize_color( (string) ( $s['background_color'] ?? '' ) );
			if ( '' !== $color ) {
				$out['background'] = $color;
			}
			if ( ! empty( $s['background_image']['url'] ) ) {
				self::note( $report, __( 'The page background image was not converted (only its color).', 'uncoder' ) );
			}
		}
		if ( ! empty( $s['custom_css'] ) && is_string( $s['custom_css'] ) ) {
			$out['custom_css'] = Converter::css_locations( str_replace( 'selector', '.uncoder-' . $target, $s['custom_css'] ) );
		}
		return $out;
	}

	/**
	 * Creates an Uncoder template (inactive until it gets display conditions).
	 *
	 * @param array<int, array<string,mixed>> $elements Uncoder elements.
	 * @param array<string,mixed>             $settings Elementor page settings of the template.
	 * @param array<string,mixed>             $report   Report (by reference).
	 * @param array<int, array<string,mixed>> $conds    Display conditions converted from Elementor Pro.
	 * @return int|WP_Error
	 */
	private static function create_template( string $title, string $type, array $elements, int $source, array $settings, array &$report, array $conds = array() ) {
		// A draft (switched-off) template stays a draft: it must not start showing on the site.
		$draft = $source && 'publish' !== get_post_status( $source );
		$id    = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => Post_Types::TEMPLATE,
					'post_title'  => '' !== trim( $title ) ? $title : __( 'Imported from Elementor', 'uncoder' ),
					'post_status' => $draft ? 'draft' : 'publish',
					'post_author' => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$id = (int) $id;
		if ( $draft ) {
			self::note( $report, __( 'Saved as a draft, like the original: publish it in Theme Builder to use it.', 'uncoder' ) );
		}
		update_post_meta( $id, Utils::META_MODE, 'builder' );
		update_post_meta( $id, Utils::META_TYPE, $type );
		update_post_meta( $id, Utils::META_CONDS, $conds );
		if ( $source ) {
			update_post_meta( $id, self::META_SOURCE, $source );
		}
		$builder = $conds ? \Uncoder\Builder\Theme\Theme_Builder::instance() : null;
		if ( $builder ) {
			$builder->rebuild_index();
		} elseif ( ! $conds && ! in_array( $type, array( 'section', 'loop-item', 'mega-menu' ), true ) ) {
			self::note( $report, __( 'This template had no Elementor display conditions: set where it shows in Theme Builder.', 'uncoder' ) );
		}
		if ( 'popup' === $type ) {
			self::note( $report, __( 'Popup triggers and layout were not converted: set them in the popup settings.', 'uncoder' ) );
		}
		if ( ! empty( $settings['custom_css'] ) && is_string( $settings['custom_css'] ) && current_user_can( 'unfiltered_html' ) ) {
			Plugin::instance()->documents()->get( $id )->save_page_settings( array( 'custom_css' => Converter::css_locations( str_replace( 'selector', '.uncoder-' . $id, $settings['custom_css'] ) ) ) );
		}
		return $id;
	}

	/**
	 * @param array<string,mixed> $report Report (by reference).
	 */
	private static function note( array &$report, string $message ): void {
		$report['notes'][ $message ] = ( $report['notes'][ $message ] ?? 0 ) + 1;
	}

	/**
	 * The report as the admin screen shows it (lists sorted by count, internal notes kept for debugging).
	 *
	 * @param array<string,mixed> $r      Converter report.
	 * @param string[]            $errors Sanitizer errors of the saved tree.
	 * @return array<string,mixed>
	 */
	public static function public_report( array $r, array $errors = array() ): array {
		$list = static function ( $map, int $limit = 60 ): array {
			$map = is_array( $map ) ? $map : array();
			arsort( $map );
			$out = array();
			foreach ( array_slice( $map, 0, $limit, true ) as $name => $count ) {
				$out[] = array(
					'name'  => (string) $name,
					'count' => (int) $count,
				);
			}
			return $out;
		};
		return array(
			'elements'  => (int) ( $r['elements'] ?? 0 ),
			'converted' => $list( $r['converted'] ?? array() ),
			'unmapped'  => $list( $r['unmapped'] ?? array() ),
			'settings'  => $list( $r['settings'] ?? array(), 80 ),
			'notes'     => $list( $r['notes'] ?? array() ),
			'remote'    => array_slice( (array) ( $r['remote'] ?? array() ), 0, 50 ),
			'errors'    => array_slice( $errors, 0, 20 ),
		);
	}

	/* ------------------------------------------------------------------ Upload */

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload( WP_REST_Request $request ) {
		$file = $request->get_file_params()['file'] ?? null;
		if ( ! is_array( $file ) || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) && ! is_readable( (string) $file['tmp_name'] ) ) {
			return new WP_Error( 'uncoder_upload', __( 'The file did not arrive. It may be larger than the server allows.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$docs = self::read_upload( (string) $file['tmp_name'], (string) ( $file['name'] ?? '' ) );
		if ( is_wp_error( $docs ) ) {
			return $docs;
		}
		if ( ! $docs ) {
			return new WP_Error( 'uncoder_not_elementor', __( 'No Elementor template was found in this file. Export templates in Elementor under Templates → Saved Templates → Export.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$options = array(
			'globals'   => 'fallback',
			'remote'    => true,
			'templates' => array(),
		);
		$items   = array();
		foreach ( $docs as $doc ) {
			$report   = array();
			$elements = self::convert( $doc['content'], $report, $options );
			$type     = self::template_type( $doc['type'] );
			$id       = self::create_template( $doc['title'], $type, $elements, 0, $doc['settings'], $report );
			if ( is_wp_error( $id ) ) {
				$items[] = array(
					'title'   => $doc['title'],
					'ok'      => false,
					'message' => $id->get_error_message(),
				);
				continue;
			}
			$saved   = Plugin::instance()->documents()->get( $id )->save( $elements );
			$items[] = array(
				'title'     => $doc['title'],
				'ok'        => true,
				'id'        => $id,
				'type'      => $type,
				'typeLabel' => Post_Types::TEMPLATE_TYPES[ $type ] ?? $type,
				'editUrl'   => Editor::url( $id ),
				'report'    => self::public_report( $report, (array) ( $saved['errors'] ?? array() ) ),
			);
		}
		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * Templates in an uploaded .json (one export) or .zip (several exports, or an Elementor kit export).
	 *
	 * @return array<int, array{title:string, type:string, content:array<int,mixed>, settings:array<string,mixed>}>|WP_Error
	 */
	private static function read_upload( string $path, string $name ) {
		$head = (string) file_get_contents( $path, false, null, 0, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$docs = array();
		if ( "PK\x03\x04" === $head || str_ends_with( strtolower( $name ), '.zip' ) ) {
			if ( ! class_exists( '\ZipArchive' ) ) {
				return new WP_Error( 'uncoder_no_zip', __( 'This server cannot open zip files (the PHP zip extension is missing).', 'uncoder' ), array( 'status' => 500 ) );
			}
			$zip = new \ZipArchive();
			if ( true !== $zip->open( $path ) ) {
				return new WP_Error( 'uncoder_bad_zip', __( 'This zip file could not be opened.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$files    = array();
			$total    = 0;
			$manifest = array();
			for ( $i = 0; $i < min( $zip->numFiles, 500 ); $i++ ) {
				$stat = $zip->statIndex( $i );
				$file = (string) ( $stat['name'] ?? '' );
				if ( ! preg_match( '/\.json$/i', $file ) || false !== strpos( $file, '..' ) || (int) $stat['size'] > self::MAX_JSON ) {
					continue;
				}
				$total += (int) $stat['size'];
				if ( $total > 3 * self::MAX_JSON ) {
					break;
				}
				$json = json_decode( (string) $zip->getFromIndex( $i ), true );
				if ( ! is_array( $json ) ) {
					continue;
				}
				if ( 'manifest.json' === strtolower( basename( $file ) ) ) {
					$manifest = $json;
					continue;
				}
				$files[ $file ] = $json;
			}
			$zip->close();
			foreach ( $files as $file => $json ) {
				$doc = self::template_doc( $json, $file, $manifest );
				if ( $doc ) {
					$docs[] = $doc;
				}
			}
			return array_slice( $docs, 0, self::MAX_ITEMS );
		}
		if ( filesize( $path ) > self::MAX_JSON ) {
			return new WP_Error( 'uncoder_too_big', __( 'This file is too large.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'uncoder_bad_json', __( 'This is not a JSON file.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$doc = self::template_doc( $json, $name, array() );
		return $doc ? array( $doc ) : array();
	}

	/**
	 * One template from an Elementor export file.
	 *
	 * @param array<mixed>        $json     Decoded file.
	 * @param array<string,mixed> $manifest Kit export manifest (titles and types by id).
	 * @return array{title:string, type:string, content:array<int,mixed>, settings:array<string,mixed>}|null
	 */
	private static function template_doc( array $json, string $file, array $manifest ): ?array {
		$content = null;
		if ( isset( $json['content'] ) && is_array( $json['content'] ) ) {
			$content = $json['content'];
		} elseif ( isset( $json[0]['elType'] ) ) {
			$content = $json;
		}
		if ( null === $content ) {
			return null;
		}
		$id    = (string) pathinfo( $file, PATHINFO_FILENAME );
		$known = $manifest['templates'][ $id ] ?? ( $manifest['content']['page'][ $id ] ?? array() );
		$title = (string) ( $json['title'] ?? ( $known['title'] ?? '' ) );
		$type  = (string) ( $json['type'] ?? ( $known['doc_type'] ?? ( $json['metadata']['template_type'] ?? 'section' ) ) );
		return array(
			'title'    => sanitize_text_field( '' !== $title ? $title : ucfirst( str_replace( array( '-', '_' ), ' ', $id ) ) ),
			'type'     => sanitize_key( $type ),
			'content'  => $content,
			'settings' => is_array( $json['page_settings'] ?? null ) ? $json['page_settings'] : ( is_array( $json['settings'] ?? null ) ? $json['settings'] : array() ),
		);
	}
}
