<?php
/**
 * Conditions per element (Behaviour → Conditions).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * `_conditions` is a list of rule sets: the element is printed when any set matches, and a set matches when
 * all of its rules do (like Bricks). A rule is { key, op, value, name? }; role, weekday and post type take a
 * comma-separated list ("is one of"). Evaluated on the server, so a hidden element is not in the page at all;
 * the editor canvas always shows it. Replaces the flat _show_* settings (from_legacy()).
 */
final class Element_Conditions {

	/** Rule keys → allowed operators (first is the default); "name" marks keys that need a name field. */
	public const RULES = array(
		'login'     => array( 'ops' => array( 'is' ) ),
		'role'      => array( 'ops' => array( 'is', 'is_not' ) ),
		'date'      => array( 'ops' => array( 'from', 'until', 'is' ) ),
		'time'      => array( 'ops' => array( 'from', 'until' ) ),
		'weekday'   => array( 'ops' => array( 'is', 'is_not' ) ),
		'post_type' => array( 'ops' => array( 'is', 'is_not' ) ),
		'page'      => array( 'ops' => array( 'is', 'is_not' ) ),
		'url_param' => array(
			'ops'  => array( 'exists', 'not_exists', 'is', 'is_not', 'contains' ),
			'name' => true,
		),
		'referrer'  => array( 'ops' => array( 'contains', 'not_contains', 'empty', 'not_empty' ) ),
		'cookie'    => array(
			'ops'  => array( 'exists', 'not_exists', 'is', 'contains' ),
			'name' => true,
		),
		'meta'      => array(
			'ops'  => array( 'exists', 'not_exists', 'is', 'is_not', 'contains', 'greater', 'less' ),
			'name' => true,
		),
		// Content of the page (or of the loop item).
		'term'           => array(
			'ops'  => array( 'is', 'is_not' ),
			'name' => true,
		),
		'author'         => array( 'ops' => array( 'is', 'is_not' ) ),
		'parent'         => array( 'ops' => array( 'is', 'is_not', 'exists', 'not_exists' ) ),
		'featured_image' => array( 'ops' => array( 'exists', 'not_exists' ) ),
		'comments'       => array( 'ops' => array( 'greater', 'less', 'is' ) ),
		'archive'        => array( 'ops' => array( 'is', 'is_not' ) ),
		'dynamic'        => array(
			'ops'  => array( 'exists', 'not_exists', 'is', 'is_not', 'contains', 'greater', 'less' ),
			'name' => true,
		),
		// The visitor.
		'browser'        => array( 'ops' => array( 'is', 'is_not' ) ),
		'os'             => array( 'ops' => array( 'is', 'is_not' ) ),
		'language'       => array( 'ops' => array( 'is', 'is_not' ) ),
	);

	/** Values of the "archive" rule: what kind of page is shown. */
	public const ARCHIVE_TYPES = array( 'front_page', 'blog', 'singular', 'category', 'tag', 'taxonomy', 'author', 'date', 'search', 'post_type_archive', 'not_found' );

	public const BROWSERS = array( 'chrome', 'firefox', 'safari', 'edge', 'opera', 'samsung' );

	public const SYSTEMS = array( 'windows', 'macos', 'ios', 'android', 'linux' );

	/** Context of the element being checked (dynamic tag rules resolve in it). */
	private static ?Render_Context $ctx = null;

	/** Operators that take no value. */
	private const NO_VALUE = array( 'exists', 'not_exists', 'empty', 'not_empty' );

	/** The flat settings this replaces (Site\Display_Conditions). */
	public const LEGACY = array( '_show_to', '_show_roles', '_show_from', '_show_until', '_show_days', '_show_param' );

	/**
	 * @param mixed    $value  Raw value.
	 * @param string[] $errors Problems found (for AI clients).
	 * @return array<int, array<int, array<string,string>>>|null
	 */
	public static function sanitize( $value, array &$errors = array() ): ?array {
		if ( null === $value || '' === $value || array() === $value ) {
			return array();
		}
		if ( ! is_array( $value ) ) {
			$errors[] = 'Conditions must be a list of rule sets: [[{"key":"login","op":"is","value":"in"}]].';
			return null;
		}
		// A single set given as a flat list of rules is accepted too.
		if ( isset( $value[0]['key'] ) ) {
			$value = array( $value );
		}
		$sets = array();
		foreach ( $value as $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}
			$rules = array();
			foreach ( $set as $rule ) {
				$clean = is_array( $rule ) ? self::sanitize_rule( $rule, $errors ) : null;
				if ( $clean ) {
					$rules[] = $clean;
				}
			}
			if ( $rules ) {
				$sets[] = $rules;
			}
		}
		return $sets;
	}

	/**
	 * @param array<string,mixed> $rule   Rule.
	 * @param string[]            $errors Problems found.
	 * @return array<string,string>|null
	 */
	private static function sanitize_rule( array $rule, array &$errors ): ?array {
		$key = (string) ( $rule['key'] ?? '' );
		if ( ! isset( self::RULES[ $key ] ) ) {
			$errors[] = sprintf( 'Unknown condition "%s"; use one of %s.', $key, implode( ', ', array_keys( self::RULES ) ) );
			return null;
		}
		$def = self::RULES[ $key ];
		$op  = (string) ( $rule['op'] ?? $def['ops'][0] );
		if ( ! in_array( $op, $def['ops'], true ) ) {
			$errors[] = sprintf( 'Condition "%s" does not support "%s"; use %s.', $key, $op, implode( ' / ', $def['ops'] ) );
			return null;
		}
		$raw   = $rule['value'] ?? '';
		$value = is_array( $raw ) ? implode( ',', array_map( 'strval', array_filter( $raw, 'is_scalar' ) ) ) : ( is_scalar( $raw ) ? (string) $raw : '' );
		$value = trim( sanitize_text_field( $value ) );
		switch ( $key ) {
			case 'login':
				$value = 'out' === $value ? 'out' : 'in';
				break;
			case 'date':
				$value = str_replace( 'T', ' ', $value );
				$value = preg_match( '/^\d{4}-\d{2}-\d{2}( ([01]\d|2[0-3]):[0-5]\d)?$/', $value ) ? $value : '';
				break;
			case 'time':
				$value = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
				break;
			case 'weekday':
				$value = implode( ',', array_values( array_intersect( array( '1', '2', '3', '4', '5', '6', '7' ), array_map( 'trim', explode( ',', $value ) ) ) ) );
				break;
			case 'page':
				$value = (string) absint( $value );
				$value = '0' === $value ? '' : $value;
				break;
			case 'role':
			case 'post_type':
			case 'language':
				$value = implode( ',', array_filter( array_map( 'sanitize_key', explode( ',', $value ) ) ) );
				break;
			case 'term':
			case 'author':
				// Slugs / logins or ids, comma separated.
				$value = implode( ',', array_filter( array_map( static fn( $v ) => sanitize_title( trim( $v ) ), explode( ',', $value ) ) ) );
				break;
			case 'parent':
				$value = (string) absint( $value );
				$value = '0' === $value ? '' : $value;
				break;
			case 'comments':
				$value = is_numeric( $value ) ? (string) max( 0, (int) $value ) : '';
				break;
			case 'archive':
				$value = implode( ',', array_values( array_intersect( array_map( 'trim', explode( ',', $value ) ), self::ARCHIVE_TYPES ) ) );
				break;
			case 'browser':
				$value = implode( ',', array_values( array_intersect( array_map( 'trim', explode( ',', $value ) ), self::BROWSERS ) ) );
				break;
			case 'os':
				$value = implode( ',', array_values( array_intersect( array_map( 'trim', explode( ',', $value ) ), self::SYSTEMS ) ) );
				break;
		}
		if ( '' === $value && ! in_array( $op, self::NO_VALUE, true ) ) {
			$errors[] = sprintf( 'Condition "%s %s" needs a value.', $key, $op );
			return null;
		}
		$out = array(
			'key'   => $key,
			'op'    => $op,
			'value' => in_array( $op, self::NO_VALUE, true ) ? '' : $value,
		);
		if ( ! empty( $def['name'] ) ) {
			$name = is_scalar( $rule['name'] ?? null ) ? (string) preg_replace( '/[^A-Za-z0-9_\-\[\]]/', '', (string) $rule['name'] ) : '';
			if ( '' === $name ) {
				$errors[] = sprintf( 'Condition "%s" needs a name (the parameter, cookie, field, taxonomy or dynamic tag).', $key );
				return null;
			}
			$out['name'] = $name;
		}
		return $out;
	}

	/**
	 * Settings with the old flat display rules (_show_*) turned into one rule set, appended to every
	 * existing set (they all had to pass). Runs on every save and once over stored content.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	public static function from_legacy( array $settings ): array {
		$found = false;
		foreach ( self::LEGACY as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			return $settings;
		}
		$rules = array();
		$to    = (string) ( $settings['_show_to'] ?? '' );
		if ( 'logged_in' === $to || 'logged_out' === $to ) {
			$rules[] = array( 'key' => 'login', 'op' => 'is', 'value' => 'logged_in' === $to ? 'in' : 'out' );
		} elseif ( 'roles' === $to && ! empty( $settings['_show_roles'] ) ) {
			$rules[] = array( 'key' => 'role', 'op' => 'is', 'value' => implode( ',', (array) $settings['_show_roles'] ) );
		}
		foreach ( array( '_show_from' => 'from', '_show_until' => 'until' ) as $key => $op ) {
			if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				$rules[] = array( 'key' => 'date', 'op' => $op, 'value' => substr( str_replace( 'T', ' ', $settings[ $key ] ), 0, 16 ) );
			}
		}
		if ( ! empty( $settings['_show_days'] ) ) {
			$map     = array( 'mon' => '1', 'tue' => '2', 'wed' => '3', 'thu' => '4', 'fri' => '5', 'sat' => '6', 'sun' => '7' );
			$days    = array_values( array_filter( array_map( static fn( $d ) => $map[ $d ] ?? '', (array) $settings['_show_days'] ) ) );
			$rules[] = array( 'key' => 'weekday', 'op' => 'is', 'value' => implode( ',', $days ) );
		}
		$param = trim( (string) ( $settings['_show_param'] ?? '' ) );
		if ( '' !== $param ) {
			$parts   = explode( '=', $param, 2 );
			$rules[] = isset( $parts[1] ) ? array( 'key' => 'url_param', 'name' => $parts[0], 'op' => 'is', 'value' => $parts[1] ) : array( 'key' => 'url_param', 'name' => $parts[0], 'op' => 'exists', 'value' => '' );
		}
		foreach ( self::LEGACY as $key ) {
			unset( $settings[ $key ] );
		}
		if ( $rules ) {
			$sets = is_array( $settings['_conditions'] ?? null ) && $settings['_conditions'] ? $settings['_conditions'] : array( array() );
			foreach ( $sets as $i => $set ) {
				$sets[ $i ] = array_merge( is_array( $set ) ? $set : array(), $rules );
			}
			$settings['_conditions'] = self::sanitize( $sets );
		}
		return $settings;
	}

	/**
	 * Whether an element with these conditions is printed.
	 *
	 * @param mixed $sets `_conditions` value.
	 * @param ?Render_Context $ctx Render context (for dynamic tag rules).
	 */
	public static function passes( $sets, int $post_id, ?Render_Context $ctx = null ): bool {
		self::$ctx = $ctx;
		if ( ! is_array( $sets ) || ! $sets ) {
			return true;
		}
		foreach ( $sets as $set ) {
			if ( ! is_array( $set ) || ! $set ) {
				continue;
			}
			$all = true;
			foreach ( $set as $rule ) {
				if ( ! is_array( $rule ) || ! self::rule( $rule, $post_id ) ) {
					$all = false;
					break;
				}
			}
			if ( $all ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,string> $rule Rule.
	 */
	private static function rule( array $rule, int $post_id ): bool {
		$op    = (string) ( $rule['op'] ?? '' );
		$value = (string) ( $rule['value'] ?? '' );
		$name  = (string) ( $rule['name'] ?? '' );
		$list  = array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' );
		switch ( (string) ( $rule['key'] ?? '' ) ) {
			case 'login':
				return is_user_logged_in() === ( 'out' !== $value );
			case 'role':
				$user = wp_get_current_user();
				$has  = $user->exists() && (bool) array_intersect( $list, (array) $user->roles );
				return 'is_not' === $op ? ! $has : $has;
			case 'date':
				// A date alone compares by day; with a time, to the minute (site time zone).
				$now = strlen( $value ) > 10 ? current_time( 'Y-m-d H:i' ) : current_time( 'Y-m-d' );
				return 'from' === $op ? $now >= $value : ( 'until' === $op ? $now <= $value : current_time( 'Y-m-d' ) === substr( $value, 0, 10 ) );
			case 'time':
				$now = current_time( 'H:i' );
				return 'from' === $op ? $now >= $value : $now <= $value;
			case 'weekday':
				$is = in_array( current_time( 'N' ), $list, true );
				return 'is_not' === $op ? ! $is : $is;
			case 'post_type':
				$is = $post_id && in_array( (string) get_post_type( $post_id ), $list, true );
				return 'is_not' === $op ? ! $is : $is;
			case 'page':
				$is = (int) get_queried_object_id() === (int) $value;
				return 'is_not' === $op ? ! $is : $is;
			case 'url_param':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display rule.
				$raw = isset( $_GET[ $name ] ) && is_scalar( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $name ] ) ) : null;
				return self::compare( $raw, $op, $value );
			case 'referrer':
				$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) ) : '';
				if ( 'empty' === $op || 'not_empty' === $op ) {
					return ( '' === $ref ) === ( 'empty' === $op );
				}
				$has = '' !== $value && false !== stripos( $ref, $value );
				return 'not_contains' === $op ? ! $has : $has;
			case 'cookie':
				$raw = isset( $_COOKIE[ $name ] ) && is_scalar( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ $name ] ) ) : null;
				return self::compare( $raw, $op, $value );
			case 'meta':
				if ( ! $post_id ) {
					return in_array( $op, array( 'not_exists', 'is_not' ), true );
				}
				$raw = metadata_exists( 'post', $post_id, $name ) ? get_post_meta( $post_id, $name, true ) : null;
				return self::compare( null === $raw || is_scalar( $raw ) ? $raw : wp_json_encode( $raw ), $op, $value );
			case 'term':
				// The post (or loop item) has one of the terms, or the archive shown is one of them.
				$has = $post_id && taxonomy_exists( $name ) && has_term( self::terms_list( $list ), $name, $post_id );
				if ( ! $has && ( is_category() || is_tag() || is_tax() ) ) {
					$term = get_queried_object();
					$has  = $term instanceof \WP_Term && $term->taxonomy === $name && ( in_array( $term->slug, $list, true ) || in_array( (string) $term->term_id, $list, true ) );
				}
				return 'is_not' === $op ? ! $has : $has;
			case 'author':
				$author = $post_id ? (int) get_post_field( 'post_author', $post_id ) : ( is_author() ? (int) get_queried_object_id() : 0 );
				$user   = $author ? get_userdata( $author ) : false;
				$is     = $user && ( in_array( (string) $user->ID, $list, true ) || in_array( sanitize_title( $user->user_login ), $list, true ) || in_array( $user->user_nicename, $list, true ) );
				return 'is_not' === $op ? ! $is : $is;
			case 'parent':
				$parent = $post_id ? (int) wp_get_post_parent_id( $post_id ) : 0;
				if ( 'exists' === $op || 'not_exists' === $op ) {
					return ( $parent > 0 ) === ( 'exists' === $op );
				}
				$is = $parent && $parent === (int) $value;
				return 'is_not' === $op ? ! $is : $is;
			case 'featured_image':
				return ( $post_id && has_post_thumbnail( $post_id ) ) === ( 'exists' === $op );
			case 'comments':
				return self::compare( $post_id ? (string) get_comments_number( $post_id ) : '0', 'is' === $op ? 'is' : $op, $value );
			case 'archive':
				$is = (bool) array_filter( $list, array( self::class, 'is_page_kind' ) );
				return 'is_not' === $op ? ! $is : $is;
			case 'dynamic':
				return self::compare( self::dynamic_value( $name ), $op, $value );
			case 'browser':
				$is = in_array( self::browser(), $list, true );
				return 'is_not' === $op ? ! $is : $is;
			case 'os':
				$is = in_array( self::system(), $list, true );
				return 'is_not' === $op ? ! $is : $is;
			case 'language':
				$is = in_array( self::language(), $list, true );
				return 'is_not' === $op ? ! $is : $is;
		}
		return false;
	}

	/**
	 * Term slugs and ids for has_term().
	 *
	 * @param string[] $list Values.
	 * @return array<int, int|string>
	 */
	private static function terms_list( array $list ): array {
		return array_map( static fn( $v ) => ctype_digit( $v ) ? (int) $v : $v, $list );
	}

	/** Whether the page shown is of one kind (value of the "archive" rule). */
	private static function is_page_kind( string $kind ): bool {
		switch ( $kind ) {
			case 'front_page':
				return is_front_page();
			case 'blog':
				return is_home();
			case 'singular':
				return is_singular();
			case 'category':
				return is_category();
			case 'tag':
				return is_tag();
			case 'taxonomy':
				return is_tax();
			case 'author':
				return is_author();
			case 'date':
				return is_date();
			case 'search':
				return is_search();
			case 'post_type_archive':
				return is_post_type_archive();
			case 'not_found':
				return is_404();
		}
		return false;
	}

	/** Text value of a dynamic tag (without options) for the element being checked. */
	private static function dynamic_value( string $tag ): ?string {
		$tags = \Uncoder\Builder\Plugin::instance()->tags();
		$def  = $tags->get( $tag );
		if ( null === $def || ! self::$ctx || ! is_callable( $def['callback'] ?? null ) ) {
			return null;
		}
		$value = call_user_func( $def['callback'], array(), self::$ctx );
		if ( is_array( $value ) ) {
			$value = $value['url'] ?? ( isset( $value['id'] ) ? (string) $value['id'] : '' );
		}
		$text = trim( wp_strip_all_tags( (string) $value ) );
		return '' === $text ? null : $text;
	}

	/** Visitor's browser from the user agent (Edge and Opera before Chrome: they include "Chrome"). */
	public static function browser(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched.
		foreach ( array( 'edge' => '/Edg(e|A|iOS)?\//', 'opera' => '/OPR\/|Opera/', 'samsung' => '/SamsungBrowser/', 'firefox' => '/Firefox|FxiOS/', 'chrome' => '/Chrome|CriOS/', 'safari' => '/Safari/' ) as $name => $re ) {
			if ( preg_match( $re, $ua ) ) {
				return $name;
			}
		}
		return '';
	}

	/** Visitor's operating system from the user agent. */
	public static function system(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched.
		foreach ( array( 'ios' => '/iPhone|iPad|iPod/', 'android' => '/Android/', 'windows' => '/Windows/', 'macos' => '/Macintosh|Mac OS X/', 'linux' => '/Linux|X11/' ) as $name => $re ) {
			if ( preg_match( $re, $ua ) ) {
				return $name;
			}
		}
		return '';
	}

	/** Language shown (WPML / Polylang), else the site language ("en" from en_US). */
	public static function language(): string {
		if ( function_exists( 'pll_current_language' ) && pll_current_language() ) {
			return (string) pll_current_language();
		}
		$wpml = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
		if ( is_string( $wpml ) && '' !== $wpml ) {
			return $wpml;
		}
		return strtolower( substr( determine_locale(), 0, 2 ) );
	}

	/**
	 * @param mixed $actual The value found (null: not there).
	 */
	private static function compare( $actual, string $op, string $value ): bool {
		switch ( $op ) {
			case 'exists':
				return null !== $actual && '' !== (string) $actual;
			case 'not_exists':
				return null === $actual || '' === (string) $actual;
			case 'is':
				return null !== $actual && (string) $actual === $value;
			case 'is_not':
				return null === $actual || (string) $actual !== $value;
			case 'contains':
				return null !== $actual && '' !== $value && false !== stripos( (string) $actual, $value );
			case 'greater':
				return null !== $actual && is_numeric( $actual ) && is_numeric( $value ) && (float) $actual > (float) $value;
			case 'less':
				return null !== $actual && is_numeric( $actual ) && is_numeric( $value ) && (float) $actual < (float) $value;
		}
		return false;
	}
}
