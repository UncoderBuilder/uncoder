<?php
/**
 * Restore points: an import (starter site or site kit) can be undone completely.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Menus\Mega_Menu;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Theme\Theme_Builder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * While an import runs, a journal notes the site as it was, the first time the import touches each part of it:
 * - the site's own settings (Uncoder's options, the theme's mods, the home page, logo and icon…) with their old values;
 *   other plugins' options are left alone;
 * - every existing post, page, template or menu item (its row, its meta and its terms) before it is changed or
 *   deleted, each in a row of its own, compressed;
 * - every existing term before it is changed or deleted;
 * - and everything the import creates (posts, media, menus, categories).
 *
 * Nothing is copied ahead of time, so a restore point is small and covers whatever the importer does. Every write is
 * checked: a point that could not be saved whole is marked incomplete and cannot be used.
 *
 * Restoring:
 * - Only the newest import can be undone (undo newer ones first), because each one was noted on top of the one
 *   before.
 * - Every noted value goes back exactly (raw rows and meta).
 * - What the import added goes away: its pages, posts and templates to the trash (recoverable), its menus, menu items
 *   and categories deleted, its media deleted only when asked.
 * - Then the indexes, every stylesheet and the Design System's stylesheet are rebuilt.
 * - The restore is itself noted as a point ("Before undoing …"), so it can be reversed too.
 *
 * Storage: an index (uncoder_wb_restore_points), one option per point (uncoder_wb_restore_{id}) and one per noted post
 * (uncoder_wb_restore_{id}_p{post}), none autoloaded. The newest five points are kept.
 *
 * REST (manage_options):
 * - GET    uncoder/v1/restore-points
 * - POST   uncoder/v1/restore-points/{id}/restore  { media?: bool }
 * - DELETE uncoder/v1/restore-points/{id}
 */
final class Restore_Points {

	public const INDEX   = 'uncoder_wb_restore_points';
	private const PREFIX = 'uncoder_wb_restore_';
	private const KEEP   = 5;

	/** The options an import may change and a restore puts back: the site's own settings, never other plugins'. */
	private const OPTIONS = '/^(uncoder_wb_|theme_mods_|show_on_front$|page_on_front$|page_for_posts$|site_icon$|site_logo$|blogname$|blogdescription$|sticky_posts$|nav_menu_options$|woocommerce_(shop|cart|checkout|myaccount|terms)_page_id$)/';

	/** Of those, the ones that are not settings: the journal itself, histories, caches, the stylesheet's version. */
	private const NOT_OPTIONS = '/^(uncoder_wb_restore|uncoder_wb_kit_snapshots$|uncoder_wb_kit_version$|uncoder_wb_kit_css_build$|uncoder_wb_releases$|uncoder_wb_pro_module$|uncoder_wb_.*_cache$)/';

	/**
	 * Meta never noted and never put back: editor locks, stylesheets rebuilt after every import, and histories kept
	 * apart from the content (AI undo snapshots, autosaves, rewrite backups) that a restore leaves as they are.
	 */
	private const SKIP_META = array( '_edit_lock', '_edit_last', '_uncoder_wb_css_ver', '_uncoder_wb_css_inline', '_uncoder_wb_assets', '_uncoder_wb_snapshots', '_uncoder_wb_autosave', '_uncoder_wb_rewrite_backup' );

	/** Post types removed for good on restore (they only exist inside what the import added). */
	private const DELETE_TYPES = array( 'nav_menu_item', 'revision', 'customize_changeset', 'oembed_cache', 'wp_global_styles' );

	/** @var array<string,mixed>|null The journal being written in this request. */
	private static ?array $journal = null;

	private static bool $hooked = false;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$admin = static fn() => current_user_can( 'manage_options' );
		register_rest_route(
			'uncoder/v1',
			'/restore-points',
			array(
				'methods'             => 'GET',
				'callback'            => static fn() => new WP_REST_Response( array( 'points' => self::list() ) ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			'uncoder/v1',
			'/restore-points/(?P<id>[a-z0-9]{6,32})/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_restore' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			'uncoder/v1',
			'/restore-points/(?P<id>[a-z0-9]{6,32})',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'rest_delete' ),
				'permission_callback' => $admin,
			)
		);
	}

	/* ------------------------------------------------------------------ Storage */

	/** A value as stored in the journal: serialized, compressed and base64 (binary-safe, charset-safe, small). */
	private static function pack( $value ): string {
		$raw = serialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- our own data, read back by unpack().
		return function_exists( 'gzcompress' ) ? 'z:' . base64_encode( (string) gzcompress( $raw, 6 ) ) : 'r:' . base64_encode( $raw ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** @return mixed */
	private static function unpack( string $packed ) {
		$data = base64_decode( substr( $packed, 2 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $data ) {
			return null;
		}
		if ( 0 === strpos( $packed, 'z:' ) ) {
			$data = function_exists( 'gzuncompress' ) ? @gzuncompress( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return false === $data ? null : unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	}

	/** Writes an option and reads it back from the database: true when it is really there. */
	private static function put( string $name, string $value ): bool {
		global $wpdb;
		update_option( $name, $value, false );
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $stored === $value;
	}

	/** @return array<string,mixed>|null A point's journal. */
	private static function load( string $id ): ?array {
		$raw  = get_option( self::PREFIX . $id );
		$data = is_string( $raw ) ? self::unpack( $raw ) : null;
		return is_array( $data ) ? $data : null;
	}

	/** Saves a point's journal; a failed write marks it incomplete. */
	private static function save( array $data ): void {
		if ( ! self::put( self::PREFIX . (string) $data['id'], self::pack( $data ) ) ) {
			self::break_point( (string) $data['id'] );
		}
	}

	/** Marks a point incomplete (a small option of its own, so it gets written even when the journal could not be). */
	private static function break_point( string $id ): void {
		update_option( self::PREFIX . 'broken_' . $id, 1, false );
	}

	private static function broken( string $id ): bool {
		return (bool) get_option( self::PREFIX . 'broken_' . $id );
	}

	/** Removes everything a point stored. */
	private static function drop( string $id ): void {
		$data = self::load( $id );
		foreach ( array_keys( (array) ( $data['posts'] ?? array() ) ) as $post ) {
			delete_option( self::PREFIX . $id . '_p' . (int) $post );
		}
		delete_option( self::PREFIX . $id );
		delete_option( self::PREFIX . 'broken_' . $id );
	}

	/* ------------------------------------------------------------------ Recording */

	/**
	 * Opens a new restore point (call before the import changes anything).
	 *
	 * @param string $label  What is imported, e.g. "Starter site: Rowan".
	 * @param string $source "site-kit" | "starter" | "undo".
	 * @return string The point's id.
	 */
	public static function begin( string $label, string $source ): string {
		$id   = strtolower( wp_generate_password( 12, false, false ) );
		$user = wp_get_current_user();
		$data = array(
			'id'            => $id,
			'label'         => sanitize_text_field( $label ),
			'source'        => $source,
			'created'       => time(),
			'user'          => $user->exists() ? $user->display_name : '',
			'status'        => 'recording',
			'options'       => array(),
			'posts'         => array(),
			'terms'         => array(),
			'created_posts' => array(),
			'created_terms' => array(),
		);
		self::save( $data );
		$index = self::index();
		array_unshift(
			$index,
			array(
				'id'      => $id,
				'label'   => $data['label'],
				'source'  => $source,
				'created' => $data['created'],
				'user'    => $data['user'],
				'status'  => 'recording',
			)
		);
		// Only the newest few are kept (an open point is never the one dropped: it is first).
		foreach ( array_slice( $index, self::KEEP ) as $old ) {
			self::drop( (string) $old['id'] );
		}
		update_option( self::INDEX, array_slice( $index, 0, self::KEEP ), false );
		return $id;
	}

	/** Whether a point is still open for recording (an import continues it in its next request). */
	public static function recording( string $id ): bool {
		$data = self::load( $id );
		return is_array( $data ) && 'recording' === ( $data['status'] ?? '' );
	}

	/** Starts noting changes into the point for the rest of this request (or until pause()). */
	public static function resume( string $id ): void {
		$data = self::load( $id );
		if ( ! is_array( $data ) || 'recording' !== ( $data['status'] ?? '' ) ) {
			return;
		}
		self::$journal = $data;
		if ( ! self::$hooked ) {
			self::$hooked = true;
			add_action( 'add_option', array( self::class, 'on_add_option' ), 1, 1 );
			add_action( 'update_option', array( self::class, 'on_update_option' ), 1, 2 );
			add_action( 'delete_option', array( self::class, 'on_delete_option' ), 1, 1 );
			add_action( 'wp_insert_post', array( self::class, 'on_insert_post' ), 1, 3 );
			add_action( 'add_attachment', array( self::class, 'on_add_attachment' ), 1, 1 );
			add_action( 'pre_post_update', array( self::class, 'touch_post' ), 1, 1 );
			add_action( 'before_delete_post', array( self::class, 'touch_post' ), 1, 1 );
			add_action( 'add_post_meta', array( self::class, 'on_add_meta' ), 1, 2 );
			add_action( 'update_post_meta', array( self::class, 'on_update_meta' ), 1, 3 );
			add_action( 'delete_post_meta', array( self::class, 'on_delete_meta' ), 1, 3 );
			add_action( 'set_object_terms', array( self::class, 'on_set_terms' ), 1, 6 );
			add_action( 'created_term', array( self::class, 'on_created_term' ), 1, 3 );
			add_action( 'edit_terms', array( self::class, 'touch_term' ), 1, 1 );
			add_action( 'pre_delete_term', array( self::class, 'touch_term' ), 1, 1 );
			// A request that dies half-way still keeps what it noted.
			register_shutdown_function( array( self::class, 'pause' ) );
		}
	}

	/** Stops noting and saves the journal. */
	public static function pause(): void {
		if ( null === self::$journal ) {
			return;
		}
		$data          = self::$journal;
		self::$journal = null;
		self::save( $data );
	}

	/**
	 * Ends recording: the point becomes restorable (call after the import's last step). A point that noted nothing is
	 * dropped; one that could not be saved whole is marked incomplete.
	 *
	 * @param string              $id      Point id.
	 * @param array<string,mixed> $summary Extra numbers for the list.
	 */
	public static function close( string $id, array $summary = array() ): void {
		if ( null !== self::$journal && $id === self::$journal['id'] ) {
			self::pause();
		}
		$data = self::load( $id );
		if ( ! is_array( $data ) ) {
			self::update_index( $id, array( 'status' => 'failed' ) );
			return;
		}
		if ( ! $data['options'] && ! $data['posts'] && ! $data['terms'] && ! $data['created_posts'] && ! $data['created_terms'] ) {
			self::drop( $id );
			update_option( self::INDEX, array_values( array_filter( self::index(), static fn( $p ) => (string) $p['id'] !== $id ) ), false );
			return;
		}
		$data['status']  = self::broken( $id ) ? 'failed' : 'ready';
		$data['summary'] = self::summarize( $data ) + $summary;
		self::save( $data );
		self::update_index( $id, array( 'status' => self::broken( $id ) ? 'failed' : 'ready', 'summary' => $data['summary'] ) );
	}

	private static function noted_option( string $option ): bool {
		return null !== self::$journal && ! isset( self::$journal['options'][ $option ] ) && preg_match( self::OPTIONS, $option ) && ! preg_match( self::NOT_OPTIONS, $option );
	}

	/** @param string $option Option name. */
	public static function on_add_option( $option ): void {
		$option = (string) $option;
		if ( self::noted_option( $option ) ) {
			self::$journal['options'][ $option ] = array( 'exists' => false );
		}
	}

	/**
	 * @param string $option Option name.
	 * @param mixed  $old    Value before the update.
	 */
	public static function on_update_option( $option, $old ): void {
		$option = (string) $option;
		if ( self::noted_option( $option ) ) {
			self::$journal['options'][ $option ] = array(
				'exists' => true,
				'value'  => self::pack( $old ),
			);
		}
	}

	/** @param string $option Option name. */
	public static function on_delete_option( $option ): void {
		$option = (string) $option;
		if ( self::noted_option( $option ) ) {
			self::$journal['options'][ $option ] = array(
				'exists' => true,
				'value'  => self::pack( get_option( $option ) ),
			);
		}
	}

	/**
	 * @param int      $id     Post id.
	 * @param \WP_Post $post   Post.
	 * @param bool     $update Whether an existing post was updated.
	 */
	public static function on_insert_post( $id, $post, $update ): void {
		if ( null === self::$journal || $update || ! $post instanceof \WP_Post || 'auto-draft' === $post->post_status ) {
			return;
		}
		self::$journal['created_posts'][ (int) $id ] = $post->post_type;
		// Its meta and terms were written before this hook ran, which noted it as an existing post: it is new.
		self::forget_post( (int) $id );
	}

	/** @param int $id Attachment id. */
	public static function on_add_attachment( $id ): void {
		if ( null !== self::$journal ) {
			self::$journal['created_posts'][ (int) $id ] = 'attachment';
			self::forget_post( (int) $id );
		}
	}

	private static function forget_post( int $id ): void {
		if ( array_key_exists( $id, self::$journal['posts'] ) ) {
			unset( self::$journal['posts'][ $id ] );
			delete_option( self::PREFIX . self::$journal['id'] . '_p' . $id );
		}
	}

	/**
	 * @param int    $meta_ids Unused.
	 * @param int    $post_id  Post id.
	 * @param string $key      Meta key.
	 */
	public static function on_update_meta( $meta_ids, $post_id, $key ): void {
		if ( ! in_array( (string) $key, self::SKIP_META, true ) ) {
			self::touch_post( (int) $post_id );
		}
	}

	/**
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 */
	public static function on_add_meta( $post_id, $key ): void {
		if ( ! in_array( (string) $key, self::SKIP_META, true ) ) {
			self::touch_post( (int) $post_id );
		}
	}

	/**
	 * @param int[]  $meta_ids Unused.
	 * @param int    $post_id  Post id.
	 * @param string $key      Meta key.
	 */
	public static function on_delete_meta( $meta_ids, $post_id, $key ): void {
		if ( ! in_array( (string) $key, self::SKIP_META, true ) ) {
			self::touch_post( (int) $post_id );
		}
	}

	/** Notes an existing post as it is now, the first time the import touches it (in a row of its own). */
	public static function touch_post( $id, ?array $snap = null ): void {
		$id = (int) $id;
		if ( null === self::$journal || $id <= 0 || isset( self::$journal['created_posts'][ $id ] ) || array_key_exists( $id, self::$journal['posts'] ) ) {
			return;
		}
		$snap = $snap ?? self::post_snapshot( $id );
		if ( null === $snap ) {
			self::$journal['posts'][ $id ] = false; // Nothing there to put back.
			return;
		}
		self::$journal['posts'][ $id ] = true;
		if ( ! self::put( self::PREFIX . self::$journal['id'] . '_p' . $id, self::pack( $snap ) ) ) {
			self::break_point( (string) self::$journal['id'] );
		}
	}

	/**
	 * set_object_terms runs after the change: a post first touched here is noted with its terms as they were.
	 *
	 * @param int    $id       Post id.
	 * @param array  $terms    Unused.
	 * @param int[]  $tt_ids   New term taxonomy ids.
	 * @param string $taxonomy Unused.
	 * @param bool   $append   Unused.
	 * @param int[]  $old      Term taxonomy ids before.
	 */
	public static function on_set_terms( $id, $terms, $tt_ids, $taxonomy, $append, $old ): void {
		$id = (int) $id;
		if ( null === self::$journal || isset( self::$journal['created_posts'][ $id ] ) || array_key_exists( $id, self::$journal['posts'] ) ) {
			return;
		}
		$snap = self::post_snapshot( $id );
		if ( $snap ) {
			$snap['tt'] = array_values( array_unique( array_merge( array_diff( $snap['tt'], array_map( 'intval', (array) $tt_ids ) ), array_map( 'intval', (array) $old ) ) ) );
		}
		self::touch_post( $id, $snap );
	}

	/**
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Unused.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function on_created_term( $term_id, $tt_id, $taxonomy ): void {
		if ( null !== self::$journal ) {
			self::$journal['created_terms'][ (int) $term_id ] = (string) $taxonomy;
		}
	}

	/** Notes an existing term (its rows, meta and, for a deleted one, its posts) before the import changes it. */
	public static function touch_term( $term_id ): void {
		$term_id = (int) $term_id;
		if ( null === self::$journal || isset( self::$journal['created_terms'][ $term_id ] ) || array_key_exists( $term_id, self::$journal['terms'] ) ) {
			return;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->terms} WHERE term_id = %d", $term_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $row ) {
			self::$journal['terms'][ $term_id ] = null;
			return;
		}
		$taxonomies = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$objects    = array();
		foreach ( $taxonomies as $tt ) {
			$objects[ (int) $tt['term_taxonomy_id'] ] = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", (int) $tt['term_taxonomy_id'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		self::$journal['terms'][ $term_id ] = self::pack(
			array(
				'row'     => $row,
				'tt'      => $taxonomies,
				'meta'    => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->termmeta} WHERE term_id = %d ORDER BY meta_id", $term_id ), ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'objects' => $objects,
			)
		);
	}

	/**
	 * A post's row, its meta (raw, but for SKIP_META) and its term taxonomy ids.
	 *
	 * @return array{row:array<string,mixed>,meta:array<int,array{meta_key:string,meta_value:string}>,tt:int[]}|null
	 */
	private static function post_snapshot( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $row ) {
			return null;
		}
		$meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array(
			'row'  => $row,
			'meta' => array_values( array_filter( $meta, static fn( $m ) => ! in_array( $m['meta_key'], self::SKIP_META, true ) ) ),
			'tt'   => array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = %d", $id ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
	}

	/* ------------------------------------------------------------------ Restoring */

	/**
	 * Why a point cannot be restored now, or ''.
	 *
	 * @param array<string,mixed> $entry Its line in the index.
	 */
	private static function blocked( array $entry ): string {
		$status = (string) ( $entry['status'] ?? '' );
		if ( 'restored' === $status ) {
			return __( 'This restore point was already used.', 'uncoder' );
		}
		if ( 'failed' === $status || self::broken( (string) $entry['id'] ) ) {
			return __( 'This restore point is incomplete (it could not be saved whole), so it cannot be used.', 'uncoder' );
		}
		if ( 'ready' !== $status ) {
			return __( 'This import has not finished yet.', 'uncoder' );
		}
		// Each import was noted on top of the ones before: the newest goes first. A "before undoing" point is the
		// newest of all when it can still be used.
		foreach ( self::index() as $p ) {
			if ( (string) $p['id'] === (string) $entry['id'] ) {
				break;
			}
			if ( 'ready' === ( $p['status'] ?? '' ) && ( 'undo' !== ( $p['source'] ?? '' ) || 'undo' === ( $entry['source'] ?? '' ) ) ) {
				/* translators: %s: the newer import. */
				return sprintf( __( 'Undo the newer “%s” first: imports are undone newest first.', 'uncoder' ), (string) ( $p['label'] ?? '' ) );
			}
		}
		return '';
	}

	/**
	 * Puts the site back as it was before the import.
	 *
	 * @param bool $media Also delete the media files the import added.
	 * @return array<string,mixed>|WP_Error What was done.
	 */
	public static function restore( string $id, bool $media = false ) {
		$entry = null;
		foreach ( self::index() as $p ) {
			if ( (string) $p['id'] === $id ) {
				$entry = $p;
			}
		}
		$data = $entry ? self::load( $id ) : null;
		if ( ! $entry || ! is_array( $data ) ) {
			return new WP_Error( 'uncoder_restore_missing', __( 'This restore point no longer exists.', 'uncoder' ), array( 'status' => 404 ) );
		}
		$why = self::blocked( $entry );
		if ( '' !== $why ) {
			return new WP_Error( 'uncoder_restore_blocked', $why, array( 'status' => 409 ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- a large import takes a while to undo.
		}
		global $wpdb;
		$done   = array(
			'trashed'  => 0,
			'deleted'  => 0,
			'restored' => 0,
			'options'  => 0,
			'kept'     => 0,
		);
		$counts = array(); // term taxonomy ids whose counts change.

		// The restore is noted too, so it can be reversed: undoing an import can be redone, and so on.
		$base = (string) ( $data['base'] ?? $data['label'] );
		$undo = self::begin(
			'undo' === ( $entry['source'] ?? '' )
				/* translators: %s: what was imported. */
				? sprintf( __( 'Before redoing “%s”', 'uncoder' ), $base )
				/* translators: %s: what was imported. */
				: sprintf( __( 'Before undoing “%s”', 'uncoder' ), $base ),
			'undo'
		);
		self::resume( $undo );
		self::$journal['base'] = $base;

		// 1. What the import added.
		$created = (array) $data['created_posts'];
		krsort( $created ); // Newest first: children before their parents.
		foreach ( $created as $post_id => $type ) {
			$post = get_post( (int) $post_id );
			if ( ! $post ) {
				continue;
			}
			if ( 'attachment' === $type ) {
				if ( $media ) {
					wp_delete_attachment( (int) $post_id, true );
					++$done['deleted'];
				} else {
					++$done['kept'];
				}
				continue;
			}
			if ( in_array( $type, self::DELETE_TYPES, true ) ) {
				wp_delete_post( (int) $post_id, true );
				++$done['deleted'];
			} elseif ( 'trash' !== $post->post_status ) {
				wp_trash_post( (int) $post_id );
				++$done['trashed'];
			}
		}
		// Posts and terms noted as "not there yet" were written straight into the database by a restore (this point
		// undoes one): they go as well.
		foreach ( array_keys( array_filter( (array) $data['posts'], static fn( $v ) => false === $v ) ) as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post ) {
				continue;
			}
			if ( 'attachment' === $post->post_type ) {
				if ( $media ) {
					wp_delete_attachment( (int) $post_id, true );
					++$done['deleted'];
				}
			} elseif ( in_array( $post->post_type, self::DELETE_TYPES, true ) ) {
				wp_delete_post( (int) $post_id, true );
				++$done['deleted'];
			} elseif ( 'trash' !== $post->post_status ) {
				wp_trash_post( (int) $post_id );
				++$done['trashed'];
			}
		}
		$created_terms = (array) $data['created_terms'];
		foreach ( (array) $data['terms'] as $term_id => $packed ) {
			if ( null === $packed ) {
				$taxonomy = (string) $wpdb->get_var( $wpdb->prepare( "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d", (int) $term_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( '' !== $taxonomy ) {
					$created_terms[ (int) $term_id ] = $taxonomy;
				}
			}
		}
		foreach ( $created_terms as $term_id => $taxonomy ) {
			if ( ! term_exists( (int) $term_id, (string) $taxonomy ) ) {
				continue;
			}
			if ( 'nav_menu' === $taxonomy ) {
				wp_delete_nav_menu( (int) $term_id );
			} else {
				wp_delete_term( (int) $term_id, (string) $taxonomy );
			}
			++$done['deleted'];
		}

		// 2. Terms the import changed or deleted.
		foreach ( (array) $data['terms'] as $term_id => $packed ) {
			$snap = is_string( $packed ) ? self::unpack( $packed ) : null;
			if ( ! is_array( $snap ) ) {
				continue;
			}
			self::touch_term( (int) $term_id );
			$wpdb->replace( $wpdb->terms, $snap['row'] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $snap['tt'] as $tt ) {
				$wpdb->replace( $wpdb->term_taxonomy, $tt ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				foreach ( (array) ( $snap['objects'][ (int) $tt['term_taxonomy_id'] ] ?? array() ) as $object ) {
					$wpdb->replace( $wpdb->term_relationships, array( 'object_id' => (int) $object, 'term_taxonomy_id' => (int) $tt['term_taxonomy_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
				$counts[ (string) $tt['taxonomy'] ][] = (int) $tt['term_taxonomy_id'];
			}
			$wpdb->delete( $wpdb->termmeta, array( 'term_id' => (int) $term_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $snap['meta'] as $m ) {
				$wpdb->insert( $wpdb->termmeta, array( 'term_id' => (int) $term_id, 'meta_key' => $m['meta_key'], 'meta_value' => $m['meta_value'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery
			}
			clean_term_cache( (int) $term_id );
			++$done['restored'];
		}

		// 3. Posts, pages, templates and menu items the import changed or deleted: rows, meta and terms as they were.
		$skip = "'" . implode( "','", array_map( 'esc_sql', self::SKIP_META ) ) . "'";
		foreach ( array_keys( array_filter( (array) $data['posts'] ) ) as $post_id ) {
			$post_id = (int) $post_id;
			$raw     = get_option( self::PREFIX . $id . '_p' . $post_id );
			$snap    = is_string( $raw ) ? self::unpack( $raw ) : null;
			if ( ! is_array( $snap ) ) {
				continue;
			}
			self::touch_post( $post_id );
			$row    = $snap['row'];
			$exists = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $exists ) {
				unset( $row['ID'] );
				$wpdb->update( $wpdb->posts, $row, array( 'ID' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} else {
				$wpdb->insert( $wpdb->posts, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			// The meta a restore leaves alone (histories, stylesheets) stays; the rest is put back as it was.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key NOT IN ({$skip})", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $snap['meta'] as $m ) {
				$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $post_id, 'meta_key' => $m['meta_key'], 'meta_value' => $m['meta_value'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery
			}
			$before = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = %d", $post_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->term_relationships, array( 'object_id' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $snap['tt'] as $tt ) {
				$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => $post_id, 'term_taxonomy_id' => (int) $tt ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			foreach ( array_unique( array_merge( $before, array_map( 'intval', (array) $snap['tt'] ) ) ) as $tt ) {
				$taxonomy = (string) $wpdb->get_var( $wpdb->prepare( "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $tt ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( '' !== $taxonomy ) {
					$counts[ $taxonomy ][] = $tt;
				}
			}
			clean_post_cache( $post_id );
			++$done['restored'];
		}
		foreach ( $counts as $taxonomy => $tts ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				wp_update_term_count_now( array_values( array_unique( $tts ) ), $taxonomy );
			}
		}

		// 4. The site's settings: the Design System, custom code, fonts, logo, menu locations, home page...
		foreach ( (array) $data['options'] as $name => $was ) {
			if ( ! empty( $was['exists'] ) ) {
				update_option( (string) $name, self::unpack( (string) ( $was['value'] ?? '' ) ) );
			} else {
				delete_option( (string) $name );
			}
			++$done['options'];
		}
		self::close( $undo );

		// 5. Everything derived: indexes, every stylesheet, and the Design System's own (a new version, so no visitor
		// keeps the imported one from a cache).
		$kit = Plugin::instance()->kit();
		$kit->reload();
		Custom_Fonts::flush();
		\Uncoder\Builder\Core\Breakpoints::flush();
		update_option( \Uncoder\Builder\Core\Kit::VERSION_OPT, (string) time(), true );
		$kit->write_css();
		$builder = Theme_Builder::instance();
		if ( $builder ) {
			$builder->rebuild_index();
		}
		Mega_Menu::rebuild_index();
		Plugin::instance()->documents()->regenerate_all();

		// The journal is spent: only its line in the list stays.
		self::drop( $id );
		self::update_index(
			$id,
			array(
				'status'   => 'restored',
				'restored' => time(),
				'result'   => $done,
			)
		);
		return $done;
	}

	public function rest_restore( WP_REST_Request $request ) {
		$result = self::restore( (string) $request['id'], (bool) $request->get_param( 'media' ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response(
			array(
				'result' => $result,
				'points' => self::list(),
			)
		);
	}

	public function rest_delete( WP_REST_Request $request ) {
		$id = (string) $request['id'];
		self::drop( $id );
		update_option( self::INDEX, array_values( array_filter( self::index(), static fn( $p ) => (string) $p['id'] !== $id ) ), false );
		return new WP_REST_Response( array( 'points' => self::list() ) );
	}

	/* ------------------------------------------------------------------ The list */

	/**
	 * The points, newest first, for the Library screen.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function list(): array {
		$out = array();
		foreach ( self::index() as $p ) {
			$status = (string) ( $p['status'] ?? '' );
			// A point still "recording" whose import never finished (closed tab): it can still undo what happened.
			if ( 'recording' === $status && (int) ( $p['created'] ?? 0 ) < time() - HOUR_IN_SECONDS ) {
				self::close( (string) $p['id'], array( 'unfinished' => true ) );
				return self::list();
			}
			if ( 'ready' === $status && self::broken( (string) $p['id'] ) ) {
				$status = 'failed';
			}
			$why   = 'ready' === $status ? self::blocked( $p ) : '';
			$out[] = array(
				'id'       => (string) $p['id'],
				'label'    => (string) ( $p['label'] ?? '' ),
				'source'   => (string) ( $p['source'] ?? '' ),
				'created'  => gmdate( 'c', (int) ( $p['created'] ?? 0 ) ),
				'user'     => (string) ( $p['user'] ?? '' ),
				'status'   => $status,
				'blocked'  => $why,
				'summary'  => (array) ( $p['summary'] ?? array() ),
				'restored' => isset( $p['restored'] ) ? gmdate( 'c', (int) $p['restored'] ) : null,
				'result'   => (array) ( $p['result'] ?? array() ),
			);
		}
		return $out;
	}

	/**
	 * Numbers for the list: what the import added and changed.
	 *
	 * @param array<string,mixed> $data Journal.
	 * @return array<string,int>
	 */
	private static function summarize( array $data ): array {
		$added = array(
			'pages'     => 0,
			'templates' => 0,
			'posts'     => 0,
			'media'     => 0,
			'menus'     => 0,
		);
		foreach ( (array) $data['created_posts'] as $type ) {
			if ( 'page' === $type ) {
				++$added['pages'];
			} elseif ( 'uncoder_template' === $type ) {
				++$added['templates'];
			} elseif ( 'attachment' === $type ) {
				++$added['media'];
			} elseif ( ! in_array( $type, self::DELETE_TYPES, true ) ) {
				++$added['posts'];
			}
		}
		foreach ( (array) $data['created_terms'] as $taxonomy ) {
			if ( 'nav_menu' === $taxonomy ) {
				++$added['menus'];
			}
		}
		return $added + array(
			'changed'  => count( array_filter( (array) $data['posts'] ) ),
			'settings' => count( (array) $data['options'] ),
		);
	}

	/** @return array<int, array<string,mixed>> */
	private static function index(): array {
		$index = get_option( self::INDEX, array() );
		return is_array( $index ) ? array_values( array_filter( $index, 'is_array' ) ) : array();
	}

	/** @param array<string,mixed> $patch Fields to change on one entry. */
	private static function update_index( string $id, array $patch ): void {
		$index = self::index();
		foreach ( $index as $i => $p ) {
			if ( (string) $p['id'] === $id ) {
				$index[ $i ] = array_merge( $p, $patch );
			}
		}
		update_option( self::INDEX, $index, false );
	}
}
