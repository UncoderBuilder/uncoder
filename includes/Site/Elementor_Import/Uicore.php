<?php
/**
 * UiCore Framework's theme builder (themes such as Framey, Affirm, Brisk): its Elementor templates in the import.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site\Elementor_Import;

use Uncoder\Builder\Menus\Mega_Menu;

defined( 'ABSPATH' ) || exit;

/**
 * UiCore keeps its templates as `uicore-tb` posts: the kind in the `tb_type` taxonomy (_type_header, _type_footer,
 * _type_popup, _type_mm = mega menu, _type_pagetitle, _type_block, _type_single…), where they show in the
 * `tb_rule_include` / `tb_rule_exclude` meta (lists of { rule: { value }, specific }). Everything is read from the
 * database, so it works with UiCore switched off; UiCore's own data is not changed (a mega menu item becomes a plain
 * link, its original kind kept in META_ITEM).
 */
final class Uicore {

	public const POST_TYPE = 'uicore-tb';

	/** On a menu item that was a UiCore mega menu item: its original type, object and object id. */
	public const META_ITEM = '_uncoder_uicore_item';

	/** UiCore kinds → Uncoder template types. Page titles, blocks and the rest become sections. */
	private const TYPES = array(
		'header'  => 'header',
		'footer'  => 'footer',
		'popup'   => 'popup',
		'mm'      => 'mega-menu',
		'single'  => 'single',
		'archive' => 'archive',
	);

	public static function is_template( \WP_Post $post ): bool {
		return self::POST_TYPE === $post->post_type;
	}

	/**
	 * UiCore's kind of a template, without the `_type_` prefix (header, footer, popup, mm, pagetitle, block…).
	 */
	public static function kind( int $id ): string {
		global $wpdb;
		// The taxonomy is not registered while UiCore is off: read the term directly.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$name = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.slug FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} x ON x.term_id = t.term_id
				INNER JOIN {$wpdb->term_relationships} r ON r.term_taxonomy_id = x.term_taxonomy_id
				WHERE r.object_id = %d AND x.taxonomy = 'tb_type' LIMIT 1",
				$id
			)
		);
		return (string) preg_replace( '/^_type_/', '', $name );
	}

	/** The Uncoder template type for a UiCore template. */
	public static function template_type( int $id ): string {
		return self::TYPES[ self::kind( $id ) ] ?? 'section';
	}

	/**
	 * The site has UiCore Theme Options (a UiCore theme styled it), whether or not the theme is still active.
	 */
	public static function has_options(): bool {
		static $has = null;
		if ( null === $has ) {
			$admin = get_option( 'uicore_theme_options_admin' );
			$has   = ! empty( $admin );
		}
		return $has;
	}

	/**
	 * A UiCore theme is active with its page title switched off: Elementor pages then show edge to edge, without
	 * the theme's title.
	 */
	public static function page_title_off(): bool {
		$options = get_option( 'uicore_theme_options' );
		return is_array( $options ) && in_array( $options['pagetitle'] ?? '', array( 'false', false ), true ) && defined( 'UICORE_VERSION' );
	}

	/** A short name for the scan list. */
	public static function label( int $id ): string {
		$kind = self::kind( $id );
		return array(
			'mm'        => __( 'UiCore mega menu', 'uncoder' ),
			'pagetitle' => __( 'UiCore page title', 'uncoder' ),
			'block'     => __( 'UiCore block', 'uncoder' ),
		)[ $kind ] ?? __( 'UiCore template', 'uncoder' );
	}

	/**
	 * UiCore display rules as Uncoder conditions (unsanitized; Elementor_Import sanitizes them).
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function conditions( int $id ): array {
		$out = array();
		foreach ( array( 'include' => 'tb_rule_include', 'exclude' => 'tb_rule_exclude' ) as $mode => $key ) {
			foreach ( (array) get_post_meta( $id, $key, true ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				foreach ( self::rule( (string) ( $row['rule']['value'] ?? '' ), (array) ( $row['specific'] ?? array() ) ) as $c ) {
					$out[] = array( 'type' => $mode ) + $c;
				}
			}
		}
		return $out;
	}

	/**
	 * @param array<int, mixed> $specific "Specific pages" picks: "post-12", "tax-5", "tax-5-single" or { id: … }.
	 * @return array<int, array<string,mixed>>
	 */
	private static function rule( string $value, array $specific ): array {
		$simple = array(
			'basic-global'     => array( 'rule' => 'general' ),
			'basic-page'       => array( 'rule' => 'singular', 'post_type' => 'page' ),
			'basic-single'     => array( 'rule' => 'singular', 'post_type' => 'post' ),
			'basic-archives'   => array( 'rule' => 'archive' ),
			'special-404'      => array( 'rule' => 'not_found' ),
			'special-search'   => array( 'rule' => 'search' ),
			'special-blog'     => array( 'rule' => 'posts_page' ),
			'special-front'    => array( 'rule' => 'front_page' ),
			'special-date'     => array( 'rule' => 'date' ),
			'special-author'   => array( 'rule' => 'author' ),
			'special-woo-shop' => array( 'rule' => 'archive', 'post_type' => 'product' ),
		);
		if ( isset( $simple[ $value ] ) ) {
			return array( $simple[ $value ] );
		}
		if ( 'specifics' === $value ) {
			$out = array();
			foreach ( $specific as $pick ) {
				$pick = (string) ( is_array( $pick ) ? ( $pick['id'] ?? '' ) : $pick );
				$p    = explode( '-', $pick );
				$id   = absint( $p[1] ?? 0 );
				if ( ! $id ) {
					continue;
				}
				if ( 'post' === $p[0] && get_post_type( $id ) ) {
					$out[] = array( 'rule' => 'singular', 'post_type' => get_post_type( $id ), 'ids' => array( $id ) );
				} elseif ( 'tax' === $p[0] ) {
					$term = get_term( $id );
					if ( $term instanceof \WP_Term ) {
						// "tax-5-single": posts in that term; the term's archive otherwise.
						$out[] = 'single' === ( $p[2] ?? '' )
							? array( 'rule' => 'in_term', 'taxonomy' => $term->taxonomy, 'ids' => array( $id ) )
							: array( 'rule' => 'archive', 'taxonomy' => $term->taxonomy, 'ids' => array( $id ) );
					}
				}
			}
			return $out;
		}
		if ( preg_match( '/^cp-archive-([a-z0-9_-]+)(?:\|all\|taxarchive\|([a-z0-9_-]+))?$/', $value, $m ) ) {
			return array( ! empty( $m[2] ) ? array( 'rule' => 'archive', 'taxonomy' => $m[2] ) : array( 'rule' => 'archive', 'post_type' => $m[1] ) );
		}
		if ( preg_match( '/^cp-([a-z0-9_-]+)(?:\|all)?$/', $value, $m ) ) {
			return array( array( 'rule' => 'singular', 'post_type' => $m[1] ) );
		}
		return array();
	}

	/**
	 * A UiCore theme keeps its global colours and fonts in its Theme Options and copies them into the Elementor kit as
	 * custom globals (uicore_primary, uicore_headline…), which the kit import brings in as e-uicore_* entries. Those
	 * are the site's real colours and fonts: they also become the Design System's own (primary, headings, text…), and
	 * the Theme Options' heading and paragraph sizes its h1–h6 and body text styles.
	 *
	 * @param array<string,mixed> $partial Kit converted from Elementor (Kit_Map).
	 * @return array<string,mixed>
	 */
	public static function kit( array $partial ): array {
		// The theme's own CSS (Theme Options → Custom CSS) joins the site CSS, where it can be edited.
		$admin = get_option( 'uicore_theme_options_admin' );
		$admin = is_string( $admin ) ? json_decode( $admin, true ) : $admin;
		$own   = is_array( $admin ) ? trim( (string) ( $admin['customcss'] ?? '' ) ) : '';
		if ( '' !== $own ) {
			$partial['custom_css'] = trim( (string) ( $partial['custom_css'] ?? '' ) . "\n\n/* Custom CSS from the theme's Theme Options (UiCore) */\n" . $own );
		}
		if ( is_array( $admin ) ) {
			// UiCore keeps a page's top-level boxed content at 95% of the width it has (at most the box width): a
			// little side space on tablets and phones that the pages were designed with.
			$partial['custom_css'] = trim( (string) ( $partial['custom_css'] ?? '' ) . "\n\n/* UiCore's boxed width: 95% of the screen at most */\n.uncoder > .uncoder-container--boxed > .uncoder-container__inner {\n\tmax-width: min(95%, var(--uncoder-boxed-width));\n}" );
		}
		$colors = array_column( (array) ( $partial['colors'] ?? array() ), 'value', 'id' );
		if ( ! isset( $colors['e-uicore_primary'] ) ) {
			return $partial;
		}
		$slots = array(
			'primary'   => 'e-uicore_primary',
			'secondary' => 'e-uicore_secondary',
			'accent'    => 'e-uicore_accent',
			'heading'   => 'e-uicore_headline',
			'text'      => 'e-uicore_body',
			'surface'   => 'e-uicore_light',
		);
		$list = array_values( array_filter( (array) $partial['colors'], static fn( $c ) => ! isset( $slots[ $c['id'] ?? '' ] ) ) );
		foreach ( $slots as $slot => $source ) {
			if ( isset( $colors[ $source ] ) ) {
				$list[] = array(
					'id'    => $slot,
					'value' => $colors[ $source ],
				);
			}
		}
		$partial['colors'] = $list;

		$typo  = array_column( (array) ( $partial['typography'] ?? array() ), 'value', 'id' );
		$fonts = array(
			'heading' => (string) ( $typo['e-uicore_primary']['family'] ?? '' ),
			'body'    => (string) ( $typo['e-uicore_text']['family'] ?? '' ),
		);
		$partial['fonts'] = array_values( array_filter( (array) ( $partial['fonts'] ?? array() ), static fn( $f ) => ! isset( $fonts[ $f['id'] ?? '' ] ) ) );
		foreach ( $fonts as $id => $family ) {
			if ( '' !== $family ) {
				$partial['fonts'][] = array(
					'id'     => $id,
					'family' => $family,
				);
			}
		}

		// Theme Options keep only what differs from the theme's defaults (often h1, h2 and the paragraph); the other
		// tags take UiCore Framework's defaults, as on the site.
		$options = get_option( 'uicore_theme_options_admin' );
		$options = is_string( $options ) ? json_decode( $options, true ) : $options;
		$options = is_array( $options ) ? $options : array();
		$presets = array();
		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ) as $tag ) {
			$t = $options[ $tag ] ?? ( $options ? self::typography_default( $tag ) : null );
			if ( ! is_array( $t ) ) {
				continue;
			}
			$value = array( 'family' => 'p' === $tag ? 'var(--uncoder-f-body)' : 'var(--uncoder-f-heading)' );
			foreach ( array( 'd' => '', 't' => '_tablet', 'm' => '_mobile' ) as $device => $suffix ) {
				$size = $t['s'][ $device ] ?? null;
				if ( is_array( $size ) && is_numeric( $size['value'] ?? null ) ) {
					$value[ 'size' . $suffix ] = array(
						'size' => (float) $size['value'],
						'unit' => in_array( $size['unit'] ?? 'px', array( 'px', 'em', 'rem' ), true ) ? $size['unit'] : 'px',
					);
				}
			}
			$lh = $t['h']['d'] ?? null;
			if ( is_array( $lh ) && is_numeric( $lh['value'] ?? null ) ) {
				$unit = (string) ( $lh['unit'] ?? '' );
				// The body's line height stays in em, as UiCore sets it: inherited as a length, smaller text inside
				// (a 14px footer note) keeps the body's line spacing, as on the site.
				$value['line_height'] = array(
					'size' => (float) $lh['value'],
					'unit' => 'px' === $unit ? 'px' : ( 'p' === $tag && 'em' === $unit ? 'em' : '' ),
				);
			}
			$ls = $t['ls']['d'] ?? null;
			if ( is_array( $ls ) && is_numeric( $ls['value'] ?? null ) && 0.0 !== (float) $ls['value'] ) {
				$value['letter_spacing'] = array(
					'size' => (float) $ls['value'],
					'unit' => 'em' === ( $ls['unit'] ?? '' ) ? 'em' : 'px',
				);
			}
			$weight = (string) ( $t['st'] ?? '' );
			$weight = array( 'normal' => '400', 'regular' => '400', 'bold' => '700' )[ $weight ] ?? $weight;
			if ( preg_match( '/^[1-9]00$/', $weight ) ) {
				$value['weight'] = $weight;
			}
			$presets[] = array(
				'id'    => 'p' === $tag ? 'body' : $tag,
				'name'  => 'p' === $tag ? 'Body' : 'Heading ' . substr( $tag, 1 ),
				'value' => $value,
			);
		}
		if ( $presets ) {
			$ids                   = array_column( $presets, 'id' );
			$partial['typography'] = array_merge( array_values( array_filter( (array) ( $partial['typography'] ?? array() ), static fn( $p ) => ! in_array( $p['id'] ?? '', $ids, true ) ) ), $presets );
		}
		return $partial;
	}

	/**
	 * UiCore Framework's default text style for a tag (its Theme Options defaults), in the Theme Options' own shape.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function typography_default( string $tag ): ?array {
		// size desktop / tablet / phone (px), line height (em), letter spacing (em), weight.
		$defaults = array(
			'h1' => array( 72, 60, 40, 1.2, -0.027, '600' ),
			'h2' => array( 48, 34, 26, 1.175, -0.027, '700' ),
			'h3' => array( 24, 21, 20, 1.2, -0.027, '600' ),
			'h4' => array( 21, 18, 16, 1.42, -0.027, '600' ),
			'h5' => array( 16, 15, 14, 1.187, -0.015, '600' ),
			'h6' => array( 14, 13, 12, 1.2, -0.027, '600' ),
			'p'  => array( 16, 15, 14, 1.875, 0, '400' ),
		);
		if ( ! isset( $defaults[ $tag ] ) ) {
			return null;
		}
		list( $d, $t, $m, $lh, $ls, $weight ) = $defaults[ $tag ];
		$px = static fn( $v ) => array(
			'value' => (string) $v,
			'unit'  => 'px',
		);
		return array(
			's'  => array(
				'd' => $px( $d ),
				't' => $px( $t ),
				'm' => $px( $m ),
			),
			'h'  => array(
				'd' => array(
					'value' => (string) $lh,
					'unit'  => 'em',
				),
			),
			'ls' => array(
				'd' => array(
					'value' => (string) $ls,
					'unit'  => 'em',
				),
			),
			'st' => $weight,
		);
	}

	/**
	 * The theme's custom fonts (Theme Options → Typography → Custom Fonts): one entry per file that exists in the
	 * uploads folder.
	 *
	 * @return array<int, array{family:string, weight:string, style:string, path:string}>
	 */
	public static function custom_fonts(): array {
		$options = get_option( 'uicore_theme_options' );
		$fonts   = is_array( $options ) && is_array( $options['customFonts'] ?? null ) ? $options['customFonts'] : array();
		$uploads = wp_get_upload_dir();
		$out     = array();
		foreach ( $fonts as $font ) {
			$family = is_array( $font ) ? (string) ( $font['family'] ?? '' ) : '';
			foreach ( (array) ( $font['variants'] ?? array() ) as $variant ) {
				// "600", "300italic", "normal", "italic", "bold".
				$type   = (string) ( $variant['type'] ?? 'normal' );
				$style  = false !== strpos( $type, 'italic' ) ? 'italic' : 'normal';
				$weight = (string) preg_replace( '/[^0-9]/', '', $type );
				$weight = '' !== $weight ? $weight : ( false !== strpos( $type, 'bold' ) ? '700' : '400' );
				foreach ( array( 'woff2', 'woff', 'ttf' ) as $format ) {
					$url = (string) ( $variant['src'][ $format ] ?? '' );
					if ( '' === $url || 0 !== strpos( $url, $uploads['baseurl'] ) ) {
						continue;
					}
					$path = $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
					if ( '' !== $family && preg_match( '/^[1-9]00$/', $weight ) && is_readable( $path ) ) {
						$out[] = array(
							'family' => $family,
							'weight' => $weight,
							'style'  => $style,
							'path'   => $path,
						);
						break;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Menu items that open a UiCore mega menu: they become plain links (UiCore's item points at its template post,
	 * which disappears with UiCore) carrying the converted mega menu template.
	 *
	 * @return int Menu items changed.
	 */
	public static function attach_mega_menu( int $source, int $target ): int {
		$items = get_posts(
			array(
				'post_type'      => 'nav_menu_item',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_menu_item_object',
						'value' => self::POST_TYPE,
					),
					array(
						'key'   => '_menu_item_object_id',
						'value' => (string) $source,
					),
				),
			)
		);
		$settings = (array) get_post_meta( $source, 'tb_settings', true );
		$width    = 'custom' === ( $settings['width'] ?? '' ) ? 'auto' : ( 'full' === ( $settings['width'] ?? '' ) ? 'full' : 'container' );
		foreach ( $items as $item ) {
			$item = (int) $item;
			if ( ! get_post_meta( $item, self::META_ITEM, true ) ) {
				update_post_meta(
					$item,
					self::META_ITEM,
					array(
						'type'      => (string) get_post_meta( $item, '_menu_item_type', true ),
						'object'    => self::POST_TYPE,
						'object_id' => $source,
					)
				);
			}
			$url = (string) get_post_meta( $item, '_menu_item_url', true );
			update_post_meta( $item, '_menu_item_type', 'custom' );
			update_post_meta( $item, '_menu_item_object', 'custom' );
			update_post_meta( $item, '_menu_item_object_id', (string) $item );
			update_post_meta( $item, '_menu_item_url', '' !== $url ? $url : '#' );
			if ( '' === get_the_title( $item ) ) {
				wp_update_post(
					array(
						'ID'         => $item,
						'post_title' => get_the_title( $source ),
					)
				);
			}
			Mega_Menu::set( $item, $target, $width );
		}
		return count( $items );
	}
}
