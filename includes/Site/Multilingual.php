<?php
/**
 * WPML and Polylang support.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Editor\Editor;
use Uncoder\Builder\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * - Theme templates (headers, footers, popups, sections, loop items…) can be translated like pages (WPML:
 *   wpml-config.xml; Polylang: tick "Templates" under Languages → Settings → Custom post types).
 * - A new translation starts as a copy of the original design (copied once, then edited on its own), for pages
 *   and templates alike (wpml-config.xml does the same for WPML).
 * - Templates are picked in the visitor's language: an embedded section, a loop item, a mega menu or a theme
 *   template resolves to its translation when there is one, and templates of another language are skipped
 *   when the current language has its own version.
 * - languages() feeds the Language Switcher widget.
 * - "Translate" (Theme Builder, and Polylang's own "+" for pages): create_translation() makes the linked copy
 *   and opens it in Uncoder, so templates (which have no post list of their own) can be translated too.
 */
final class Multilingual {

	/** Post meta copied into a new translation. */
	public const COPY_META = array( Utils::META_DATA, Utils::META_MODE, Utils::META_PAGE, Utils::META_TYPE, Utils::META_CONDS, Utils::META_TPL );

	public const ACTION = 'uncoder_translate';

	public function register(): void {
		add_filter( 'pll_get_post_types', array( $this, 'polylang_types' ), 10, 2 );
		add_filter( 'pll_copy_post_metas', array( $this, 'polylang_copy' ), 10, 2 );
		add_filter( 'uncoder_wb/theme/resolve', array( self::class, 'translate_id' ), 5 );
		add_filter( 'default_title', array( $this, 'default_title' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_translate' ) );
	}

	/**
	 * Polylang's "+" (post-new.php?from_post=…&new_lang=…) on a page built with Uncoder: the new translation
	 * starts with the original title, so it can be saved (an empty post cannot) and opened in Uncoder.
	 *
	 * @param string   $title Default title.
	 * @param \WP_Post $post  New post.
	 */
	public function default_title( $title, $post ) {
		$from = isset( $_GET['from_post'], $_GET['new_lang'] ) ? absint( $_GET['from_post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only default.
		if ( '' === (string) $title && $from && Utils::is_builder_post( $from ) && current_user_can( 'read_post', $from ) ) {
			return get_the_title( $from );
		}
		return $title;
	}

	/** 'wpml', 'polylang' or ''. */
	public static function plugin(): string {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && has_filter( 'wpml_object_id' ) ) {
			return 'wpml';
		}
		if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_the_languages' ) ) {
			return 'polylang';
		}
		return '';
	}

	/**
	 * @param array<string,string> $types Post types.
	 * @return array<string,string>
	 */
	public function polylang_types( $types, $is_settings ) {
		$types = (array) $types;
		// Listed under Languages → Settings → Custom post types, where the site owner turns translation on.
		if ( $is_settings ) {
			$types[ Post_Types::TEMPLATE ] = Post_Types::TEMPLATE;
		}
		return $types;
	}

	/**
	 * Copies the design into a new translation (not kept in sync afterwards: each language is edited on its own).
	 *
	 * @param string[] $keys Meta keys.
	 * @return string[]
	 */
	public function polylang_copy( $keys, $sync ) {
		return $sync ? (array) $keys : array_values( array_unique( array_merge( (array) $keys, self::COPY_META ) ) );
	}

	/**
	 * The published translation of a post in the current language (or in $lang), or the post itself.
	 *
	 * @param int|mixed $id   Post id.
	 * @param string    $lang Language code; empty = the language being shown.
	 */
	public static function translate_id( $id, $lang = '' ): int {
		$id   = (int) $id;
		$lang = is_string( $lang ) ? $lang : '';
		if ( $id <= 0 ) {
			return $id;
		}
		switch ( self::plugin() ) {
			case 'wpml':
				$tr = (int) apply_filters( 'wpml_object_id', $id, get_post_type( $id ) ?: 'post', true, '' !== $lang ? $lang : null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
				break;
			case 'polylang':
				$tr = (int) ( '' !== $lang ? pll_get_post( $id, $lang ) : pll_get_post( $id ) );
				break;
			default:
				return $id;
		}
		// A draft translation is not live yet: keep showing the original until it is published.
		return $tr && 'publish' === get_post_status( $tr ) ? $tr : $id;
	}

	/**
	 * Whether a template belongs to another language while the current language has its own version of it
	 * (then this one is not shown).
	 */
	public static function has_local_version( int $id ): bool {
		return self::translate_id( $id ) !== $id;
	}

	/**
	 * Languages for a switcher.
	 *
	 * @return array<int, array{code:string, name:string, native:string, url:string, flag:string, current:bool, missing:bool}>
	 */
	public static function languages(): array {
		$out = array();
		if ( 'wpml' === self::plugin() ) {
			$langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
			foreach ( (array) $langs as $l ) {
				$out[] = array(
					'code'    => (string) ( $l['language_code'] ?? $l['code'] ?? '' ),
					'name'    => (string) ( $l['translated_name'] ?? $l['native_name'] ?? '' ),
					'native'  => (string) ( $l['native_name'] ?? '' ),
					'url'     => (string) ( $l['url'] ?? '' ),
					'flag'    => (string) ( $l['country_flag_url'] ?? '' ),
					'current' => ! empty( $l['active'] ),
					'missing' => ! empty( $l['missing'] ),
				);
			}
		} elseif ( 'polylang' === self::plugin() ) {
			$langs = pll_the_languages(
				array(
					'raw'           => 1,
					'hide_if_empty' => 0,
				)
			);
			foreach ( (array) $langs as $l ) {
				$out[] = array(
					'code'    => (string) ( $l['slug'] ?? '' ),
					'name'    => (string) ( $l['name'] ?? '' ),
					'native'  => (string) ( $l['name'] ?? '' ),
					'url'     => (string) ( $l['url'] ?? '' ),
					'flag'    => (string) ( $l['flag'] ?? '' ),
					'current' => ! empty( $l['current_lang'] ),
					'missing' => ! empty( $l['no_translation'] ),
				);
			}
		}
		return array_values( array_filter( $out, static fn( $l ) => '' !== $l['code'] && '' !== $l['url'] ) );
	}

	/* ------------------------------------------------------------------ Admin: languages of a post */

	/**
	 * Every language of the site (not only those of the current page).
	 *
	 * @return array<int, array{code:string, name:string, flag:string}>
	 */
	public static function site_languages(): array {
		$out = array();
		if ( 'polylang' === self::plugin() && function_exists( 'pll_languages_list' ) ) {
			foreach ( (array) pll_languages_list( array( 'fields' => '' ) ) as $l ) {
				if ( is_object( $l ) ) {
					$out[] = array(
						'code' => (string) $l->slug,
						'name' => (string) $l->name,
						'flag' => (string) ( $l->flag_url ?? '' ),
					);
				}
			}
		} elseif ( 'wpml' === self::plugin() ) {
			foreach ( (array) apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) as $l ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
				$out[] = array(
					'code' => (string) ( $l['language_code'] ?? $l['code'] ?? '' ),
					'name' => (string) ( $l['native_name'] ?? $l['translated_name'] ?? '' ),
					'flag' => (string) ( $l['country_flag_url'] ?? '' ),
				);
			}
		}
		return array_values( array_filter( $out, static fn( $l ) => '' !== $l['code'] ) );
	}

	/** Home page URL in a language (the plain home page when the site is not multilingual). */
	public static function home_url( string $lang ): string {
		if ( '' !== $lang && 'polylang' === self::plugin() && function_exists( 'pll_home_url' ) ) {
			return (string) pll_home_url( $lang );
		}
		if ( '' !== $lang && 'wpml' === self::plugin() ) {
			return (string) apply_filters( 'wpml_permalink', home_url( '/' ), $lang, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
		}
		return home_url( '/' );
	}

	/**
	 * get_posts() arguments that limit a query to one language.
	 *
	 * @return array<string,mixed>
	 */
	public static function query_args( string $lang ): array {
		if ( '' === $lang ) {
			return array();
		}
		if ( 'polylang' === self::plugin() ) {
			return array( 'lang' => $lang );
		}
		if ( 'wpml' === self::plugin() ) {
			do_action( 'wpml_switch_language', $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
			return array( 'suppress_filters' => false );
		}
		return array();
	}

	/** Language code of a post ('' when unknown). */
	public static function language_of( int $id ): string {
		if ( 'polylang' === self::plugin() ) {
			return (string) pll_get_post_language( $id );
		}
		if ( 'wpml' === self::plugin() ) {
			$details = apply_filters( 'wpml_post_language_details', null, $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
			return is_array( $details ) ? (string) ( $details['language_code'] ?? '' ) : '';
		}
		return '';
	}

	/** @return array<string,int> language code => post id, the post itself included. */
	public static function translations( int $id ): array {
		if ( 'polylang' === self::plugin() ) {
			return array_map( 'intval', (array) pll_get_post_translations( $id ) );
		}
		if ( 'wpml' === self::plugin() ) {
			$type = 'post_' . get_post_type( $id );
			$trid = apply_filters( 'wpml_element_trid', null, $id, $type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
			$out  = array();
			foreach ( (array) apply_filters( 'wpml_get_element_translations', null, $trid, $type ) as $code => $tr ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
				if ( is_object( $tr ) && ! empty( $tr->element_id ) ) {
					$out[ (string) $code ] = (int) $tr->element_id;
				}
			}
			return $out;
		}
		return array();
	}

	/**
	 * Language data for the Theme Builder list: the template's language and, per other language, its
	 * translation (edit link) or a link that creates one.
	 *
	 * @return array{lang:string, translations:array<int, array<string,mixed>>}|null Null when the site is not multilingual.
	 */
	public static function describe( int $id ): ?array {
		$langs = self::site_languages();
		if ( ! $langs ) {
			return null;
		}
		$own = self::language_of( $id );
		$tr  = self::translations( $id );
		$out = array();
		foreach ( $langs as $l ) {
			if ( $l['code'] === $own ) {
				continue;
			}
			$other = (int) ( $tr[ $l['code'] ] ?? 0 );
			$out[] = $l + ( $other && get_post( $other ) && 'trash' !== get_post_status( $other )
				? array(
					'id'      => $other,
					'status'  => (string) get_post_status( $other ),
					'editUrl' => Editor::url( $other ),
				)
				: array( 'translateUrl' => self::translate_url( $id, $l['code'] ) ) );
		}
		return array(
			'lang'         => $own,
			'translations' => $out,
		);
	}

	/**
	 * Site kit import: gives imported items their language and links translation groups again.
	 *
	 * @param array<int, array<string,mixed>> $created New id => exported item (with lang / translations).
	 * @param array<int,int>                  $ids     Old id => new id.
	 * @return int Items that got a language.
	 */
	public static function restore_languages( array $created, array $ids ): int {
		if ( '' === self::plugin() ) {
			return 0;
		}
		$known = array_column( self::site_languages(), 'code' );
		$count = 0;
		$done  = array();
		foreach ( $created as $new => $item ) {
			$lang = (string) ( $item['lang'] ?? '' );
			if ( '' === $lang || ! in_array( $lang, $known, true ) ) {
				continue;
			}
			self::set_language( (int) $new, $lang );
			++$count;
		}
		foreach ( $created as $new => $item ) {
			if ( isset( $done[ $new ] ) || empty( $item['translations'] ) || ! is_array( $item['translations'] ) ) {
				continue;
			}
			$group = array();
			foreach ( $item['translations'] as $code => $old ) {
				if ( in_array( (string) $code, $known, true ) && isset( $ids[ (int) $old ] ) ) {
					$group[ (string) $code ] = (int) $ids[ (int) $old ];
					$done[ (int) $ids[ (int) $old ] ] = true;
				}
			}
			if ( count( $group ) > 1 ) {
				self::link( $group );
			}
		}
		return $count;
	}

	public static function set_language( int $id, string $lang ): void {
		if ( 'polylang' === self::plugin() ) {
			pll_set_post_language( $id, $lang );
		} elseif ( 'wpml' === self::plugin() ) {
			$type = 'post_' . get_post_type( $id );
			do_action(
				'wpml_set_element_language_details', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
				array(
					'element_id'    => $id,
					'element_type'  => $type,
					'trid'          => apply_filters( 'wpml_element_trid', null, $id, $type ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
					'language_code' => $lang,
				)
			);
		}
	}

	/**
	 * @param array<string,int> $group Language code => post id.
	 */
	public static function link( array $group ): void {
		if ( 'polylang' === self::plugin() ) {
			pll_save_post_translations( $group );
			return;
		}
		if ( 'wpml' === self::plugin() ) {
			$first = (int) reset( $group );
			$type  = 'post_' . get_post_type( $first );
			$trid  = apply_filters( 'wpml_element_trid', null, $first, $type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
			foreach ( $group as $code => $id ) {
				do_action(
					'wpml_set_element_language_details', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
					array(
						'element_id'    => (int) $id,
						'element_type'  => $type,
						'trid'          => $trid,
						'language_code' => (string) $code,
					)
				);
			}
		}
	}

	public static function translate_url( int $id, string $lang ): string {
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'post'     => $id,
				'lang'     => $lang,
				'_wpnonce' => wp_create_nonce( self::ACTION . $id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/** Creates the translation (or finds the existing one) and opens it in Uncoder. */
	public function handle_translate(): void {
		$id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$lang = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
		check_admin_referer( self::ACTION . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You cannot translate this item.', 'uncoder' ), 403 );
		}
		$new = self::create_translation( $id, $lang );
		if ( is_wp_error( $new ) ) {
			wp_die( esc_html( $new->get_error_message() ), 400 );
		}
		wp_safe_redirect( Editor::url( $new ) );
		exit;
	}

	/**
	 * A draft copy of a page or template in another language, linked as its translation, with the design,
	 * page settings, template type, display conditions and popup settings copied (edited on their own afterwards).
	 *
	 * @return int|WP_Error New (or existing) translation id.
	 */
	public static function create_translation( int $id, string $lang ) {
		$post = get_post( $id );
		if ( ! $post || '' === self::plugin() ) {
			return new WP_Error( 'uncoder_translate', __( 'Translation needs WPML or Polylang.', 'uncoder' ) );
		}
		if ( ! in_array( $lang, array_column( self::site_languages(), 'code' ), true ) || self::language_of( $id ) === $lang ) {
			return new WP_Error( 'uncoder_translate', __( 'Unknown language.', 'uncoder' ) );
		}
		$existing = (int) ( self::translations( $id )[ $lang ] ?? 0 );
		if ( $existing && get_post( $existing ) && 'trash' !== get_post_status( $existing ) ) {
			return $existing;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! current_user_can( $type->cap->create_posts ) ) {
			return new WP_Error( 'uncoder_translate', __( 'You cannot create this item.', 'uncoder' ) );
		}
		$new = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $post->post_type,
					'post_title'   => $post->post_title,
					'post_status'  => 'draft',
					'post_excerpt' => $post->post_excerpt,
					'menu_order'   => $post->menu_order,
				)
			),
			true
		);
		if ( is_wp_error( $new ) ) {
			return $new;
		}
		$new = (int) $new;
		foreach ( self::COPY_META as $key ) {
			$value = get_post_meta( $id, $key, true );
			if ( '' !== $value && null !== $value ) {
				update_post_meta( $new, $key, wp_slash( $value ) );
			}
		}
		$template = get_post_meta( $id, '_wp_page_template', true );
		if ( $template ) {
			update_post_meta( $new, '_wp_page_template', $template );
		}
		if ( has_post_thumbnail( $id ) ) {
			set_post_thumbnail( $new, (int) get_post_thumbnail_id( $id ) );
		}

		if ( 'polylang' === self::plugin() ) {
			pll_set_post_language( $new, $lang );
			$group          = self::translations( $id );
			$group[ $lang ] = $new;
			if ( ! isset( $group[ self::language_of( $id ) ] ) ) {
				$group[ self::language_of( $id ) ] = $id;
			}
			pll_save_post_translations( $group );
		} else {
			$element = 'post_' . $post->post_type;
			do_action(
				'wpml_set_element_language_details', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
				array(
					'element_id'           => $new,
					'element_type'         => $element,
					'trid'                 => apply_filters( 'wpml_element_trid', null, $id, $element ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
					'language_code'        => $lang,
					'source_language_code' => self::language_of( $id ),
				)
			);
		}

		// Stylesheet and asset list of the copy.
		$doc = Plugin::instance()->documents()->get( $new );
		if ( $doc ) {
			$doc->regenerate();
		}
		return $new;
	}
}
