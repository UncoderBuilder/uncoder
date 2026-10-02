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
		'product'        => 'single',
		'archive'        => 'archive',
		'product-archive' => 'archive',
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
			$library  = 'elementor_library' === $post->post_type;
			$template = $library ? (string) get_post_meta( $post->ID, '_elementor_template_type', true ) : '';
			if ( in_array( $template, array( 'kit' ), true ) ) {
				continue;
			}
			$object  = get_post_type_object( $post->post_type );
			$raw     = get_post_meta( $post->ID, '_elementor_data', true );
			$items[] = array(
				'id'          => (int) $post->ID,
				'title'       => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'uncoder' ),
				'type'        => $post->post_type,
				'typeLabel'   => $library ? __( 'Elementor template', 'uncoder' ) : ( $object ? $object->labels->singular_name : $post->post_type ),
				'template'    => $template,
				'templateAs'  => $library ? self::template_type( $template ) : '',
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
		$partial = self::convert_kit( $settings );
		$kit     = Plugin::instance()->kit();
		if ( isset( $partial['custom_css'] ) ) {
			$existing = (string) $kit->get( 'custom_css', '' );
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
			'sections'   => array_values( array_intersect( array_keys( $clean ), array( 'theme', 'layout', 'buttons', 'forms', 'breakpoints', 'custom_css' ) ) ),
			'errors'     => $errors,
		);
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
		$library  = 'elementor_library' === $post->post_type;
		$settings = get_post_meta( $id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : array();

		if ( $library ) {
			$type = self::template_type( (string) get_post_meta( $id, '_elementor_template_type', true ) );
			if ( ! self::can_create_template( $type ) ) {
				return $base + array( 'message' => __( 'You are not allowed to create this kind of template (headers, footers and other site-wide templates need the theme options capability).', 'uncoder' ) );
			}
			$target = self::create_template( $post->post_title, $type, $elements, $id, $settings, $report );
			if ( is_wp_error( $target ) ) {
				return $base + array( 'message' => $target->get_error_message() );
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
			$out['custom_css'] = str_replace( 'selector', '.uncoder-' . $target, $s['custom_css'] );
		}
		return $out;
	}

	/**
	 * Creates an Uncoder template (inactive until it gets display conditions).
	 *
	 * @param array<int, array<string,mixed>> $elements Uncoder elements.
	 * @param array<string,mixed>             $settings Elementor page settings of the template.
	 * @param array<string,mixed>             $report   Report (by reference).
	 * @return int|WP_Error
	 */
	private static function create_template( string $title, string $type, array $elements, int $source, array $settings, array &$report ) {
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => Post_Types::TEMPLATE,
					'post_title'  => '' !== trim( $title ) ? $title : __( 'Imported from Elementor', 'uncoder' ),
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$id = (int) $id;
		update_post_meta( $id, Utils::META_MODE, 'builder' );
		update_post_meta( $id, Utils::META_TYPE, $type );
		update_post_meta( $id, Utils::META_CONDS, array() );
		if ( $source ) {
			update_post_meta( $id, self::META_SOURCE, $source );
		}
		if ( ! in_array( $type, array( 'section', 'loop-item' ), true ) ) {
			self::note( $report, __( 'Display conditions were not converted: set where this template shows in Theme Builder.', 'uncoder' ) );
		}
		if ( 'popup' === $type ) {
			self::note( $report, __( 'Popup triggers and layout were not converted: set them in the popup settings.', 'uncoder' ) );
		}
		if ( ! empty( $settings['custom_css'] ) && is_string( $settings['custom_css'] ) && current_user_can( 'unfiltered_html' ) ) {
			Plugin::instance()->documents()->get( $id )->save_page_settings( array( 'custom_css' => str_replace( 'selector', '.uncoder-' . $id, $settings['custom_css'] ) ) );
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
