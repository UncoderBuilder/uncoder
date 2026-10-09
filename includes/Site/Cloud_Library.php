<?php
/**
 * The private cloud library (Agency licence): sections, pages, templates and whole site kits saved to the agency's
 * licence on uncoderbuilder.com (Uncoder Cloud, private/*) and reused on every site activated on it.
 *
 *   GET    uncoder/v1/cloud                    the library (items, usage, whether it can be changed)
 *   POST   uncoder/v1/cloud/section            { title, elements }   an element and its children
 *   POST   uncoder/v1/cloud/document           { id, title? }        a page, post or template (Transfer's format)
 *   POST   uncoder/v1/cloud/kit                { title, parts }      this site as a site kit (uploaded in parts)
 *   GET    uncoder/v1/cloud/{id}/elements      a section's (or page's) elements, ready to insert in the editor
 *   POST   uncoder/v1/cloud/{id}/import        a page or template, created here as a draft
 *   POST   uncoder/v1/cloud/{id}/stage         a site kit, downloaded and staged for the usual Site Kit import
 *   POST   uncoder/v1/cloud/{id}               { title }  rename
 *   DELETE uncoder/v1/cloud/{id}
 *
 * Sections carry the global classes they use (added here when missing); images are copied into this site's media
 * library when inserted or imported. Saving needs the "private_library" feature; reading also works after the
 * licence ended (the server decides). Users: full builder access, not on a handed-over site, and with white-label
 * "only me", only its owner and the handoff builders.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Licence\Licence;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Cloud_Library {

	public const FEATURE = 'private_library';
	/** Images copied per insert / import. */
	private const MEDIA_BUDGET = 60;

	public function register(): void {
		if ( Licence::enabled() ) {
			add_action( 'rest_api_init', array( $this, 'routes' ) );
		}
	}

	/** Whether the current user may use the agency's library on this site. */
	public static function can_use(): bool {
		if ( ! current_user_can( 'edit_posts' ) || 'full' !== Role_Manager::access() ) {
			return false;
		}
		$owner = White_Label::owner();
		return 0 === $owner || get_current_user_id() === $owner || in_array( get_current_user_id(), Handoff::builders(), true );
	}

	/**
	 * Whether the library shows to the current user (the menu, the editor): when the licence covers it (or an ended
	 * Agency licence can still read it), and to the people who manage the licence also as an offer of the plan.
	 * Editors on a site without Agency never see it.
	 */
	public static function offered(): bool {
		return self::can_use() && ( self::readable() || White_Label::can_manage() );
	}

	/**
	 * What the editor and the admin need to know, or null when the library is not offered to this user.
	 *
	 * @return array<string,bool>|null
	 */
	public static function client_config(): ?array {
		if ( ! Licence::enabled() || ! self::offered() ) {
			return null;
		}
		return array(
			'read'  => self::readable(),
			'write' => self::writable(),
		);
	}

	/** Saving and changing items: an active licence with the feature. */
	public static function writable(): bool {
		return Licence::allows( self::FEATURE );
	}

	/**
	 * Reading: also an Agency licence that ended (the server still lists and serves its items). An ended licence has no
	 * token any more; the licence details the server sent with the refusal still name the plan.
	 */
	public static function readable(): bool {
		if ( self::writable() ) {
			return true;
		}
		$data = Licence::data();
		return ! empty( $data['key'] ) && 'agency' === ( $data['licence']['plan'] ?? '' ) && 'expired' === Licence::state()['status'];
	}

	public function routes(): void {
		$can = array( self::class, 'can_use' );
		register_rest_route( Rest::NS, '/cloud', array( 'methods' => 'GET', 'callback' => array( $this, 'index' ), 'permission_callback' => $can ) );
		register_rest_route( Rest::NS, '/cloud/section', array( 'methods' => 'POST', 'callback' => array( $this, 'save_section' ), 'permission_callback' => $can ) );
		register_rest_route( Rest::NS, '/cloud/document', array( 'methods' => 'POST', 'callback' => array( $this, 'save_document' ), 'permission_callback' => $can ) );
		register_rest_route( Rest::NS, '/cloud/kit', array( 'methods' => 'POST', 'callback' => array( $this, 'save_kit' ), 'permission_callback' => static fn(): bool => self::can_use() && current_user_can( 'manage_options' ) ) );
		register_rest_route( Rest::NS, '/cloud/(?P<id>\d+)/elements', array( 'methods' => 'GET', 'callback' => array( $this, 'elements' ), 'permission_callback' => $can ) );
		register_rest_route( Rest::NS, '/cloud/(?P<id>\d+)/import', array( 'methods' => 'POST', 'callback' => array( $this, 'import' ), 'permission_callback' => $can ) );
		register_rest_route( Rest::NS, '/cloud/(?P<id>\d+)/stage', array( 'methods' => 'POST', 'callback' => array( $this, 'stage' ), 'permission_callback' => static fn(): bool => self::can_use() && current_user_can( 'manage_options' ) ) );
		register_rest_route(
			Rest::NS,
			'/cloud/(?P<id>\d+)',
			array(
				array( 'methods' => 'POST', 'callback' => array( $this, 'rename' ), 'permission_callback' => $can ),
				array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete' ), 'permission_callback' => $can ),
			)
		);
	}

	/* ------------------------------------------------------------------ Library */

	/** @return WP_REST_Response|WP_Error */
	public function index() {
		$base = array(
			'allowed' => self::readable(),
			'write'   => false,
			'items'   => array(),
			'usage'   => array( 'items' => 0, 'bytes' => 0 ),
			'limits'  => null,
			'pricing' => Licence::PRICING,
			'licence' => admin_url( 'admin.php?page=uncoder-settings#licence' ),
		);
		if ( ! self::readable() ) {
			return new WP_REST_Response( $base );
		}
		$res = self::call( 'list' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return new WP_REST_Response( array_merge( $base, array( 'write' => ! empty( $res['write'] ) && self::writable() ), array_intersect_key( $res, array_flip( array( 'items', 'usage', 'limits' ) ) ) ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public function save_section( WP_REST_Request $request ) {
		$elements = $request->get_param( 'elements' );
		if ( ! is_array( $elements ) || ! $elements ) {
			return new WP_Error( 'uncoder_invalid', __( 'There is nothing to save.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$elements = Site_Kit::strip_hooks( array_values( $elements ) );
		$content  = array(
			'format'   => 'uncoder-section',
			'version'  => 1,
			'site'     => home_url( '/' ),
			'plugin'   => UNCODER_WB_VERSION,
			'elements' => $elements,
			'classes'  => self::used_classes( $elements ),
		);
		return self::save( 'section', (string) $request->get_param( 'title' ), $content, array( 'elements' => self::count( $elements ), 'type' => (string) ( $elements[0]['type'] ?? '' ) ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public function save_document( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = ( new Transfer() )->export_item( $id );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		$content = array(
			'format'   => Transfer::FORMAT,
			'version'  => Transfer::VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url( '/' ),
			'plugin'   => UNCODER_WB_VERSION,
			'items'    => array( $item ),
			'classes'  => self::used_classes( (array) $item['elements'] ),
		);
		$title = (string) $request->get_param( 'title' );
		return self::save( (string) $item['kind'], '' !== trim( $title ) ? $title : (string) $item['title'], $content, array( 'type' => (string) $item['type'], 'elements' => self::count( (array) $item['elements'] ) ) );
	}

	/**
	 * Exports this site as a site kit and uploads it in parts.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_kit( WP_REST_Request $request ) {
		if ( ! self::writable() ) {
			return self::locked();
		}
		$parts = (array) ( $request->get_param( 'parts' ) ?? array() );
		$sub   = new WP_REST_Request( 'POST', '/' . Rest::NS . '/site-kit/export' );
		$sub->set_param( 'parts', $parts );
		$export = ( new Site_Kit() )->export( $sub );
		if ( is_wp_error( $export ) ) {
			return $export;
		}
		$data = $export->get_data();
		$path = Site_Kit::export_file( (string) $data['token'] );
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'uncoder_cloud_failed', __( 'The site kit could not be created.', 'uncoder' ), array( 'status' => 500 ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- large uploads.
		}
		$size  = (int) filesize( $path );
		$title = trim( (string) $request->get_param( 'title' ) );
		$start = self::call(
			'upload/start',
			array(
				'title'  => '' !== $title ? $title : get_bloginfo( 'name' ),
				'size'   => $size,
				'sha256' => (string) hash_file( 'sha256', $path ),
				'meta'   => array_merge( (array) ( $data['counts'] ?? array() ), array( 'parts' => array_keys( array_filter( array_map( 'boolval', $parts ) ) ), 'plugin' => UNCODER_WB_VERSION ) ),
			)
		);
		if ( is_wp_error( $start ) ) {
			wp_delete_file( $path );
			return $start;
		}
		$chunk  = max( 256 * KB_IN_BYTES, min( 8 * MB_IN_BYTES, (int) ( $start['chunk'] ?? 4 * MB_IN_BYTES ) ) );
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$n      = 0;
		while ( $handle && ! feof( $handle ) ) {
			$bytes = (string) fread( $handle, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( '' === $bytes ) {
				break;
			}
			$res = wp_remote_post(
				add_query_arg( array( 'upload' => $start['upload'], 'n' => $n ), Licence::server() . 'private/upload/part' ),
				array(
					'timeout' => 120,
					'headers' => array( 'Content-Type' => 'application/octet-stream', 'Accept' => 'application/json' ),
					'body'    => $bytes,
				)
			);
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				wp_delete_file( $path );
				return self::failure( $res, __( 'The upload stopped before the end. Try again.', 'uncoder' ) );
			}
			++$n;
		}
		if ( $handle ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		wp_delete_file( $path );
		$done = self::call( 'upload/finish', array( 'upload' => $start['upload'] ) );
		return is_wp_error( $done ) ? $done : new WP_REST_Response( $done );
	}

	/**
	 * A section's elements (or a page's), with its classes added to this site and its images copied here.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function elements( WP_REST_Request $request ) {
		$got = self::call( 'get', array( 'id' => (int) $request['id'] ) );
		if ( is_wp_error( $got ) ) {
			return $got;
		}
		$content  = is_array( $got['content'] ?? null ) ? $got['content'] : array();
		$elements = 'uncoder-section' === ( $content['format'] ?? '' ) ? (array) ( $content['elements'] ?? array() ) : (array) ( $content['items'][0]['elements'] ?? array() );
		if ( ! $elements ) {
			return new WP_Error( 'uncoder_cloud_empty', __( 'This item has nothing to insert.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$added                       = self::add_classes( (array) ( $content['classes'] ?? array() ) );
		[ $elements, $images, $fail ] = ( new Transfer() )->localize( $elements, self::MEDIA_BUDGET );
		return new WP_REST_Response(
			array(
				'elements' => $elements,
				'classes'  => $added, // Definitions added to the Design System (the editor adds them to its copy).
				'images'   => $images,
				'failed'   => $fail,
			)
		);
	}

	/**
	 * A page or template from the library, created here as a draft.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$got = self::call( 'get', array( 'id' => (int) $request['id'] ) );
		if ( is_wp_error( $got ) ) {
			return $got;
		}
		$content = is_array( $got['content'] ?? null ) ? $got['content'] : array();
		if ( Transfer::FORMAT !== ( $content['format'] ?? '' ) ) {
			return new WP_Error( 'uncoder_cloud_kind', __( 'Only pages and templates are imported this way: insert sections from the editor.', 'uncoder' ), array( 'status' => 400 ) );
		}
		self::add_classes( (array) ( $content['classes'] ?? array() ) );
		unset( $content['kit'] );
		$result = ( new Transfer() )->run( $content, array( 'media' => true, 'conditions' => false ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	/**
	 * A site kit from the library, downloaded and staged: the answer is Site_Kit's preview, then the usual import.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function stage( WP_REST_Request $request ) {
		$got = self::call( 'get', array( 'id' => (int) $request['id'] ) );
		if ( is_wp_error( $got ) ) {
			return $got;
		}
		if ( empty( $got['url'] ) ) {
			return new WP_Error( 'uncoder_cloud_kind', __( 'This item is not a site kit.', 'uncoder' ), array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = download_url( (string) $got['url'], 600 );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'uncoder_cloud_download', __( 'The download stopped before the end. Try again.', 'uncoder' ), array( 'status' => 502 ) );
		}
		if ( ! empty( $got['sha256'] ) && ! hash_equals( (string) $got['sha256'], (string) hash_file( 'sha256', $tmp ) ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'uncoder_cloud_download', __( 'The site kit was damaged on the way. Try again.', 'uncoder' ), array( 'status' => 502 ) );
		}
		$staged = ( new Site_Kit() )->stage_zip( $tmp );
		wp_delete_file( $tmp );
		return $staged;
	}

	/** @return WP_REST_Response|WP_Error */
	public function rename( WP_REST_Request $request ) {
		if ( ! self::writable() ) {
			return self::locked();
		}
		$res = self::call( 'update', array( 'id' => (int) $request['id'], 'title' => (string) $request->get_param( 'title' ) ) );
		return is_wp_error( $res ) ? $res : new WP_REST_Response( $res );
	}

	/** @return WP_REST_Response|WP_Error */
	public function delete( WP_REST_Request $request ) {
		if ( ! self::writable() ) {
			return self::locked();
		}
		$res = self::call( 'delete', array( 'id' => (int) $request['id'] ) );
		return is_wp_error( $res ) ? $res : new WP_REST_Response( $res );
	}

	/* ------------------------------------------------------------------ Helpers */

	/**
	 * @param array<string,mixed> $content Item content.
	 * @param array<string,mixed> $meta    Facts shown in the library.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function save( string $kind, string $title, array $content, array $meta ) {
		if ( ! self::writable() ) {
			return self::locked();
		}
		$title = trim( wp_strip_all_tags( $title ) );
		$res   = self::call(
			'save',
			array(
				'kind'    => $kind,
				'title'   => '' !== $title ? $title : __( 'Untitled', 'uncoder' ),
				'content' => $content,
				'meta'    => array_merge( $meta, array( 'plugin' => UNCODER_WB_VERSION ) ),
			)
		);
		return is_wp_error( $res ) ? $res : new WP_REST_Response( $res );
	}

	/**
	 * The definitions of the global classes a tree uses (settings._classes), from this site's Design System.
	 *
	 * @param array<int,mixed> $elements Tree.
	 * @return array<int,array<string,mixed>>
	 */
	private static function used_classes( array $elements ): array {
		$ids = array();
		$walk = static function ( array $nodes ) use ( &$walk, &$ids ): void {
			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				foreach ( (array) ( $node['settings']['_classes'] ?? array() ) as $c ) {
					$ids[ sanitize_key( (string) $c ) ] = true;
				}
				if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
					$walk( $node['children'] );
				}
			}
		};
		$walk( $elements );
		if ( ! $ids ) {
			return array();
		}
		return array_values( array_filter( (array) Plugin::instance()->kit()->get( 'classes', array() ), static fn( $c ): bool => is_array( $c ) && isset( $ids[ (string) ( $c['id'] ?? '' ) ] ) ) );
	}

	/**
	 * Adds the classes this site does not have yet (a class it has keeps its own styles).
	 *
	 * @param array<int,mixed> $classes Class definitions from the library.
	 * @return array<int,array<string,mixed>> The classes added, as stored.
	 */
	private static function add_classes( array $classes ): array {
		if ( ! $classes || ! current_user_can( 'edit_theme_options' ) ) {
			return array();
		}
		$kit  = Plugin::instance()->kit();
		$have = array_flip( array_map( static fn( $c ): string => (string) ( $c['id'] ?? '' ), (array) $kit->get( 'classes', array() ) ) );
		$new  = array_values( array_filter( $classes, static fn( $c ): bool => is_array( $c ) && ! empty( $c['id'] ) && ! isset( $have[ (string) $c['id'] ] ) ) );
		if ( ! $new ) {
			return array();
		}
		$errors = array();
		$clean  = $kit->sanitize( array( 'classes' => $new ), 'sanitize', $errors );
		if ( empty( $clean['classes'] ) ) {
			return array();
		}
		$kit->update( array( 'classes' => $clean['classes'] ), __( 'Before cloud library insert', 'uncoder' ) );
		return array_values( $clean['classes'] );
	}

	/** @param array<int,mixed> $nodes Tree. */
	private static function count( array $nodes ): int {
		$n = 0;
		foreach ( $nodes as $node ) {
			if ( is_array( $node ) ) {
				$n += 1 + self::count( (array) ( $node['children'] ?? array() ) );
			}
		}
		return $n;
	}

	private static function locked(): WP_Error {
		return new WP_Error( 'uncoder_needs_agency', __( 'Saving to the cloud library needs an active Agency licence. Your saved items can still be inserted.', 'uncoder' ), array( 'status' => 403 ) );
	}

	/**
	 * One call to the library on the licence server, with this site's key.
	 *
	 * @param array<string,mixed> $body Extra fields.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function call( string $action, array $body = array() ) {
		$key = (string) ( Licence::data()['key'] ?? '' );
		if ( '' === $key ) {
			return self::locked();
		}
		$res = wp_remote_post(
			Licence::server() . 'private/' . $action,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
				'body'    => (string) wp_json_encode(
					array_merge(
						$body,
						array(
							'key'      => $key,
							'site_url' => home_url( '/' ),
							'env'      => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
							'version'  => UNCODER_WB_VERSION,
						)
					)
				),
			)
		);
		if ( is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) >= 300 ) {
			return self::failure( $res, __( 'The cloud library could not be reached. Try again in a moment.', 'uncoder' ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return is_array( $data ) ? $data : new WP_Error( 'uncoder_cloud_failed', __( 'The cloud library sent an answer that could not be read.', 'uncoder' ), array( 'status' => 502 ) );
	}

	/**
	 * The server's error in the plugin's words.
	 *
	 * @param array<string,mixed>|WP_Error $res Response.
	 */
	private static function failure( $res, string $fallback ): WP_Error {
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'uncoder_cloud_offline', $fallback, array( 'status' => 502 ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$code = is_array( $body ) ? (string) ( $body['code'] ?? '' ) : '';
		$map  = array(
			'needs_agency'  => __( 'The cloud library comes with the Agency licence.', 'uncoder' ),
			'expired'       => __( 'Your licence has expired: the cloud library is read-only until you renew it.', 'uncoder' ),
			'disabled'      => __( 'Your licence is no longer active.', 'uncoder' ),
			'not_activated' => __( 'This site is not activated on your licence. Activate it again under Settings → Licence.', 'uncoder' ),
			'not_found'     => __( 'This item is no longer in the cloud library.', 'uncoder' ),
			'library_full'  => is_array( $body ) ? (string) ( $body['message'] ?? '' ) : '',
			'too_large'     => is_array( $body ) ? (string) ( $body['message'] ?? '' ) : '',
			'rate_limited'  => __( 'Too many requests. Wait a few minutes and try again.', 'uncoder' ),
		);
		$message = $map[ $code ] ?? '';
		if ( '' === $message ) {
			$message = is_array( $body ) && ! empty( $body['message'] ) ? (string) $body['message'] : $fallback;
		}
		$status = (int) wp_remote_retrieve_response_code( $res );
		return new WP_Error( 'uncoder_cloud_' . ( '' !== $code ? $code : 'failed' ), $message, array( 'status' => $status >= 400 && $status < 500 ? $status : 502 ) );
	}
}
