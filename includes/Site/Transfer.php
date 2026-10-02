<?php
/**
 * Import / export of templates, pages and the Design System as a JSON file.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Mcp\Tools\Media_Tools;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Popups\Popups;
use Uncoder\Builder\Rest\Rest;
use Uncoder\Builder\Theme\Conditions;
use Uncoder\Builder\Theme\Theme_Builder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * File format: { format: "uncoder-export", version: 1, exported, site, kit?, items: [ { id, kind: "template"|"page",
 * type, title, status, template?, page_settings, conditions?, popup?, elements } ] }.
 * Imports always create drafts; element trees go through the normal sanitizer, media ids from the other
 * site are dropped (the URL stays, or the image is copied into the media library), and references
 * between items of the same file (template / loop item ids) are remapped to the new ids.
 */
final class Transfer {

	public const FORMAT    = 'uncoder-export';
	public const VERSION   = 1;
	private const MAX_ITEMS = 100;
	private const MAX_MEDIA = 60;
	/** Settings that hold the id of another Uncoder document. */
	private const REF_KEYS = array( 'template_id', 'loop_template', 'alternate_template' );

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			Rest::NS,
			'/transfer/export',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'export' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/transfer/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
	}

	/* ------------------------------------------------------------------ Export */

	/**
	 * Body: { ids?: int[], all_templates?: bool, kit?: bool }.
	 */
	public function export( WP_REST_Request $request ) {
		$ids = array_map( 'absint', (array) ( $request->get_param( 'ids' ) ?? array() ) );
		if ( $request->get_param( 'all_templates' ) ) {
			if ( ! current_user_can( 'edit_theme_options' ) ) {
				return new WP_Error( 'uncoder_forbidden', __( 'Exporting every template requires the edit_theme_options capability.', 'uncoder' ), array( 'status' => 403 ) );
			}
			$ids = array_merge(
				$ids,
				get_posts(
					array(
						'post_type'      => Post_Types::TEMPLATE,
						'post_status'    => array( 'publish', 'draft', 'private' ),
						'posts_per_page' => self::MAX_ITEMS,
						'fields'         => 'ids',
						'orderby'        => 'ID',
						'order'          => 'ASC',
					)
				)
			);
		}
		$ids   = array_slice( array_values( array_unique( array_filter( $ids ) ) ), 0, self::MAX_ITEMS );
		$items = array();
		foreach ( $ids as $id ) {
			$item = $this->export_item( $id );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			$items[] = $item;
		}
		$data = array(
			'format'   => self::FORMAT,
			'version'  => self::VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url( '/' ),
			'plugin'   => UNCODER_WB_VERSION,
			'items'    => $items,
		);
		if ( $request->get_param( 'kit' ) ) {
			if ( ! current_user_can( 'edit_theme_options' ) ) {
				return new WP_Error( 'uncoder_forbidden', __( 'Exporting the Design System requires the edit_theme_options capability.', 'uncoder' ), array( 'status' => 403 ) );
			}
			$data['kit'] = Plugin::instance()->kit()->export();
		}
		if ( ! $items && empty( $data['kit'] ) ) {
			return new WP_Error( 'uncoder_invalid', __( 'Nothing to export.', 'uncoder' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( $data );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	private function export_item( int $id ) {
		$post = get_post( $id );
		if ( ! $post || ! current_user_can( 'edit_post', $id ) ) {
			/* translators: %d: post id. */
			return new WP_Error( 'uncoder_forbidden', sprintf( __( 'You cannot export #%d.', 'uncoder' ), $id ), array( 'status' => 403 ) );
		}
		$doc = Plugin::instance()->documents()->get( $id );
		if ( ! $doc || ! $doc->is_builder() ) {
			/* translators: %s: post title. */
			return new WP_Error( 'uncoder_invalid', sprintf( __( '“%s” is not built with Uncoder.', 'uncoder' ), get_the_title( $post ) ), array( 'status' => 400 ) );
		}
		$is_template = Post_Types::TEMPLATE === $post->post_type;
		$item        = array(
			'id'            => $id,
			'kind'          => $is_template ? 'template' : 'page',
			'type'          => $is_template ? (string) get_post_meta( $id, Utils::META_TYPE, true ) : $post->post_type,
			'title'         => $post->post_title, // Stored title: get_the_title() adds "Private: " / "Protected: ".
			'status'        => $post->post_status,
			'page_settings' => $doc->page_settings(),
			'elements'      => Site_Kit::strip_hooks( $doc->elements() ),
		);
		if ( ! $is_template ) {
			$item['template'] = (string) get_post_meta( $id, '_wp_page_template', true );
		}
		$conds = get_post_meta( $id, Utils::META_CONDS, true );
		if ( $is_template && is_array( $conds ) ) {
			$item['conditions'] = $conds;
		}
		$popup = get_post_meta( $id, Utils::META_TPL, true );
		if ( $is_template && 'popup' === $item['type'] && is_array( $popup ) ) {
			$item['popup'] = $popup;
		}
		return $item;
	}

	/* ------------------------------------------------------------------ Import */

	/**
	 * Body: { data: <export file>, options?: { kit?: bool, media?: bool, conditions?: bool } }.
	 */
	public function import( WP_REST_Request $request ) {
		$body   = (array) $request->get_json_params();
		$result = $this->run( is_array( $body['data'] ?? null ) ? $body['data'] : array(), is_array( $body['options'] ?? null ) ? $body['options'] : array() );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	/**
	 * Imports an export file (also used by Starters for the bundled starter sites).
	 *
	 * @param array<string,mixed> $data    Export file.
	 * @param array<string,mixed> $options { kit?: bool, media?: bool, conditions?: bool }.
	 * @return array<string,mixed>|WP_Error
	 */
	public function run( array $data, array $options ) {
		if ( self::FORMAT !== ( $data['format'] ?? '' ) || (int) ( $data['version'] ?? 0 ) > self::VERSION ) {
			return new WP_Error( 'uncoder_invalid', __( 'This is not an Uncoder export file (or it comes from a newer version).', 'uncoder' ), array( 'status' => 400 ) );
		}
		$items = array_slice( array_values( array_filter( (array) ( $data['items'] ?? array() ), 'is_array' ) ), 0, self::MAX_ITEMS );

		// Check every permission first: nothing is written when one item is not allowed.
		foreach ( $items as $item ) {
			$error = $this->can_import( $item );
			if ( $error ) {
				return new WP_Error( 'uncoder_forbidden', $error, array( 'status' => 403 ) );
			}
		}
		$with_kit = ! empty( $options['kit'] ) && is_array( $data['kit'] ?? null );
		if ( $with_kit && ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'uncoder_forbidden', __( 'Importing the Design System requires the edit_theme_options capability.', 'uncoder' ), array( 'status' => 403 ) );
		}

		$warnings = array();
		$media    = array( 'budget' => ! empty( $options['media'] ) && current_user_can( 'upload_files' ) ? self::MAX_MEDIA : 0, 'map' => array(), 'failed' => 0 );
		$created  = array();
		$id_map   = array();

		if ( $with_kit ) {
			$kit    = Plugin::instance()->kit();
			$errors = array();
			$clean  = $kit->sanitize( $data['kit'], 'sanitize', $errors );
			if ( $clean ) {
				$kit->update( $clean, __( 'Before import', 'uncoder' ), true );
			}
			foreach ( $errors as $e ) {
				$warnings[] = 'Design System: ' . $e;
			}
		}

		foreach ( $items as $item ) {
			$result = $this->import_item( $item, ! empty( $options['conditions'] ), $media );
			if ( is_wp_error( $result ) ) {
				$warnings[] = sprintf( '“%s”: %s', sanitize_text_field( (string) ( $item['title'] ?? '' ) ), $result->get_error_message() );
				continue;
			}
			if ( isset( $item['id'] ) ) {
				$id_map[ (int) $item['id'] ] = $result;
			}
			$created[] = $result;
		}

		// Point template / loop-item references at the copies made by this import.
		$unresolved = 0;
		foreach ( $created as $id ) {
			$doc      = Plugin::instance()->documents()->get( $id );
			$elements = $doc->elements();
			$changed  = false;
			$elements = $this->remap( $elements, $id_map, $changed, $unresolved );
			if ( $changed ) {
				$doc->save( $elements, array( 'content_fallback' => Post_Types::TEMPLATE !== get_post_type( $id ) ) );
			}
		}
		if ( $unresolved ) {
			/* translators: %d: number of references. */
			$warnings[] = sprintf( _n( '%d element refers to a template that is not in the file: pick it again in the editor.', '%d elements refer to templates that are not in the file: pick them again in the editor.', $unresolved, 'uncoder' ), $unresolved );
		}
		if ( $media['failed'] ) {
			/* translators: %d: number of images. */
			$warnings[] = sprintf( _n( '%d image could not be copied and still loads from the original site.', '%d images could not be copied and still load from the original site.', $media['failed'], 'uncoder' ), $media['failed'] );
		}

		$builder = Theme_Builder::instance();
		if ( $builder ) {
			$builder->rebuild_index();
		}

		return array(
			'created'  => array_map( array( $this, 'describe' ), $created ),
			'kit'      => $with_kit,
			'images'   => count( $media['map'] ),
			'warnings' => $warnings,
		);
	}

	private function can_import( array $item ): ?string {
		$kind = (string) ( $item['kind'] ?? '' );
		$type = (string) ( $item['type'] ?? '' );
		if ( 'template' === $kind ) {
			if ( ! isset( Post_Types::TEMPLATE_TYPES[ $type ] ) ) {
				/* translators: %s: template type. */
				return sprintf( __( 'Unknown template type “%s”.', 'uncoder' ), $type );
			}
			$cap = 'section' === $type ? 'edit_posts' : 'edit_theme_options';
			return current_user_can( $cap ) ? null : __( 'Your account cannot create templates of this type.', 'uncoder' );
		}
		if ( 'page' === $kind ) {
			$pt = get_post_type_object( $type );
			if ( ! $pt || ! in_array( $type, Plugin::instance()->documents()->post_types(), true ) ) {
				/* translators: %s: post type. */
				return sprintf( __( 'The content type “%s” is not enabled for Uncoder on this site.', 'uncoder' ), $type );
			}
			return current_user_can( $pt->cap->edit_posts ) ? null : __( 'Your account cannot create this content.', 'uncoder' );
		}
		return __( 'Unknown item in the file.', 'uncoder' );
	}

	/**
	 * @param array<string,mixed> $item  Item from the file.
	 * @param array<string,mixed> $media Media import state (by reference).
	 * @return int|WP_Error New post id.
	 */
	private function import_item( array $item, bool $with_conditions, array &$media ) {
		$is_template = 'template' === $item['kind'];
		$title       = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
		$id          = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => $is_template ? Post_Types::TEMPLATE : (string) $item['type'],
					'post_title'  => '' !== $title ? $title : __( 'Imported', 'uncoder' ),
					'post_status' => 'draft',
					'post_author' => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$id = (int) $id;
		if ( $is_template ) {
			update_post_meta( $id, Utils::META_TYPE, (string) $item['type'] );
			$conds = array();
			if ( $with_conditions && isset( $item['conditions'] ) ) {
				$errors = array();
				$conds  = Conditions::sanitize( $item['conditions'], $errors );
			}
			update_post_meta( $id, Utils::META_CONDS, $conds );
			if ( 'popup' === $item['type'] && is_array( $item['popup'] ?? null ) ) {
				$errors = array();
				update_post_meta( $id, Utils::META_TPL, Popups::sanitize( $item['popup'], $errors ) );
			}
		} elseif ( ! empty( $item['template'] ) && is_string( $item['template'] ) ) {
			$template = \Uncoder\Builder\Frontend\Frontend::valid_page_template( $item['template'], (string) $item['type'] );
			if ( '' !== $template ) {
				update_post_meta( $id, '_wp_page_template', $template );
			}
		}

		$elements = is_array( $item['elements'] ?? null ) ? $this->localize_media( $item['elements'], $media ) : array();
		$doc      = Plugin::instance()->documents()->get( $id );
		// Pages and posts also get the plain HTML copy visitors see if the plugin is ever deactivated.
		$doc->save( $elements, array( 'content_fallback' => ! $is_template ) );
		if ( is_array( $item['page_settings'] ?? null ) ) {
			$doc->save_page_settings( $item['page_settings'] );
		}
		return $id;
	}

	/**
	 * Media values ({id, url}) keep only their URL, or become a local attachment when images are imported.
	 *
	 * @param mixed               $value Settings subtree.
	 * @param array<string,mixed> $media State (by reference).
	 * @return mixed
	 */
	private function localize_media( $value, array &$media ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_key_exists( 'id', $value ) && isset( $value['url'] ) && is_string( $value['url'] ) && ! isset( $value['type'] ) ) {
			$url   = $value['url'];
			$local = 0 === strpos( $url, home_url( '/' ) );
			if ( ! $local && $media['budget'] > 0 && preg_match( '/\.(jpe?g|png|gif|webp|avif)(\?|$)/i', (string) wp_parse_url( $url, PHP_URL_PATH ) . '?' ) ) {
				if ( ! isset( $media['map'][ $url ] ) ) {
					--$media['budget'];
					$new                   = Media_Tools::import_url( $url, (string) ( $value['alt'] ?? '' ) );
					$media['map'][ $url ]  = is_wp_error( $new ) ? 0 : $new;
					$media['failed']      += is_wp_error( $new ) ? 1 : 0;
				}
				if ( $media['map'][ $url ] ) {
					$value['id']  = $media['map'][ $url ];
					$value['url'] = (string) wp_get_attachment_url( $media['map'][ $url ] );
					return $value;
				}
			}
			// Same site: the id is right when it still points at this URL. From another site it would name
			// an unrelated local file.
			if ( ! $local || ! $value['id'] || wp_get_attachment_url( (int) $value['id'] ) !== $url ) {
				$value['id'] = 0;
			}
			return $value;
		}
		foreach ( $value as $k => $v ) {
			$value[ $k ] = $this->localize_media( $v, $media );
		}
		return $value;
	}

	/**
	 * @param array<int, array<string,mixed>> $elements Tree.
	 * @param array<int,int>                  $map      Old id => new id.
	 */
	private function remap( array $elements, array $map, bool &$changed, int &$unresolved ): array {
		foreach ( $elements as $i => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			foreach ( self::REF_KEYS as $key ) {
				if ( ! empty( $node['settings'][ $key ] ) && is_numeric( $node['settings'][ $key ] ) ) {
					$old = (int) $node['settings'][ $key ];
					if ( isset( $map[ $old ] ) ) {
						$elements[ $i ]['settings'][ $key ] = $map[ $old ];
						$changed                            = true;
					} elseif ( ! get_post( $old ) || Post_Types::TEMPLATE !== get_post_type( $old ) ) {
						++$unresolved;
					}
				}
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$elements[ $i ]['children'] = $this->remap( $node['children'], $map, $changed, $unresolved );
			}
		}
		return $elements;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function describe( int $id ): array {
		$post = get_post( $id );
		return array(
			'id'    => $id,
			'title' => $post ? html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) : '',
			'kind'  => $post && Post_Types::TEMPLATE === $post->post_type ? 'template' : 'page',
			'type'  => $post && Post_Types::TEMPLATE === $post->post_type ? (string) get_post_meta( $id, Utils::META_TYPE, true ) : ( $post ? $post->post_type : '' ),
			'edit'  => admin_url( 'post.php?action=uncoder&post=' . $id ),
		);
	}
}
