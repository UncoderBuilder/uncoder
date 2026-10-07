<?php
/**
 * Style book: every widget and the Design System on one page.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Css\Generator;
use Uncoder\Builder\Core\Element_Base;
use Uncoder\Builder\Core\Fonts;
use Uncoder\Builder\Core\Renderer;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * home_url( '?uncoder_style_book=1' ) (people who can edit posts) renders the site's colors, text styles,
 * buttons, form fields and one copy of every widget with its starting settings, inside the active theme and
 * with the Design System's CSS. The editor posts its unsaved Design System (field "kit") to preview changes
 * before saving them.
 */
final class Style_Book {

	public const QUERY = 'uncoder_style_book';
	public const NONCE = 'uncoder_style_book';

	/** Widgets left out: no visible output of their own, or output that covers the page. */
	private const SKIP = array( 'container', 'html', 'shortcode', 'menu-anchor', 'template', 'reading-progress', 'post-comments', 'post-content' );

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 1 );
	}

	public static function url(): string {
		return add_query_arg( self::QUERY, '1', home_url( '/' ) );
	}

	public function maybe_render(): void {
		if ( ! isset( $_GET[ self::QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view, capability-checked below.
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			if ( ! is_user_logged_in() ) {
				auth_redirect(); // Exits.
			}
			// auth_redirect() lets any logged-in user through: refuse accounts without the capability.
			wp_die( esc_html__( 'Sorry, you are not allowed to view the style book.', 'uncoder' ), '', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		show_admin_bar( false );
		// The admin bar was set up just before (template_redirect, priority 0) and would still keep 32px free
		// above the page for itself.
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_admin_bar_bump_styles' );
		// A preview for the site owner: no cookie banner over the widgets.
		$consent = Plugin::instance()->module( 'consent' );
		if ( $consent ) {
			remove_action( 'wp_footer', array( $consent, 'banner' ), 50 );
			remove_action( 'wp_enqueue_scripts', array( $consent, 'enqueue' ), 22 );
		}

		$kit   = Plugin::instance()->kit();
		$draft = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified right here.
		if ( isset( $_POST['kit'], $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
			$raw = json_decode( (string) wp_unslash( $_POST['kit'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, sanitized by Kit::sanitize().
			if ( is_array( $raw ) ) {
				$draft = array_replace( $kit->all(), $kit->sanitize( $raw ) );
			}
		}

		$sections = $this->sections( $draft ?? $kit->all() );
		$tree     = $sections['tree'];
		Assets::enqueue_all();
		$generated = ( new Generator( 0 ) )->document( $tree );
		wp_register_style( 'uncoder-style-book', false, array( 'uncoder-kit' ), UNCODER_WB_VERSION ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'uncoder-style-book' );
		wp_add_inline_style( 'uncoder-style-book', self::chrome_css() . ( null !== $draft ? $kit->css( $draft ) : '' ) . $generated['css'] );
		Fonts::enqueue( $generated['fonts'] );
		if ( null !== $draft ) {
			Fonts::enqueue( self::font_map( $draft ) );
		}

		$renderer = new Renderer( 0, false, 0 );
		$body     = $renderer->render_document( $tree, array( 'class' => 'uncoder-sb__doc' ) );
		$this->page( $sections['nav'], $sections['intro'], $body );
		exit;
	}

	/**
	 * @param array<string,mixed> $kit Design System.
	 * @return array<string,string[]> family => weights, for Fonts::enqueue().
	 */
	private static function font_map( array $kit ): array {
		$out = array();
		foreach ( (array) $kit['fonts'] as $font ) {
			$family = (string) ( $font['family'] ?? '' );
			if ( '' !== $family ) {
				$out[ $family ] = array( '400', '500', '600', '700' );
			}
		}
		return $out;
	}

	/**
	 * The page as an element tree: text styles and buttons with core widgets, then every widget.
	 *
	 * @param array<string,mixed> $kit Design System.
	 * @return array{tree:array<int,array<string,mixed>>, nav:array<string,string>, intro:string}
	 */
	private function sections( array $kit ): array {
		$elements = Plugin::instance()->elements();
		$tree     = array();
		$nav      = array(
			'colors' => __( 'Colors', 'uncoder' ),
			'type'   => __( 'Text styles', 'uncoder' ),
			'bt'     => __( 'Buttons', 'uncoder' ),
		);

		// Text styles: one heading per preset, set in that preset.
		$type = array();
		foreach ( (array) $kit['typography'] as $preset ) {
			$id     = sanitize_key( (string) $preset['id'] );
			$tag    = in_array( $id, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $id : ( 'display' === $id ? 'h2' : 'p' );
			$type[] = array(
				'id'       => self::id( 'ty' . $id ),
				'type'     => 'heading',
				'settings' => array(
					'title'      => (string) $preset['name'] . ' — ' . __( 'The quick brown fox jumps over the lazy dog', 'uncoder' ),
					'tag'        => $tag,
					'typography' => array( 'preset' => $id ),
				),
			);
		}
		$tree[] = self::section( 'type', $nav['type'], $type );

		$buttons = array();
		foreach ( array( 'primary', 'secondary', 'outline', 'ghost' ) as $variant ) {
			$buttons[] = array(
				'id'       => self::id( 'bt' . $variant ),
				'type'     => 'button',
				'settings' => array(
					'text'    => ucfirst( $variant ),
					'variant' => $variant,
				),
			);
		}
		$tree[] = self::section( 'bt', $nav['bt'], $buttons, true );

		// Widgets, grouped by panel category.
		$cats = $elements->categories();
		$by   = array();
		foreach ( $elements->all() as $name => $el ) {
			if ( in_array( $name, self::SKIP, true ) || $el->is_container() ) {
				continue;
			}
			$by[ $el->category() ][] = $el;
		}
		foreach ( $cats + array_fill_keys( array_diff( array_keys( $by ), array_keys( $cats ) ), '' ) as $cat => $label ) {
			if ( empty( $by[ $cat ] ) ) {
				continue;
			}
			$nav[ 'w-' . $cat ] = '' !== $label ? $label : ucfirst( (string) $cat );
			$cards              = array();
			foreach ( $by[ $cat ] as $el ) {
				$cards[] = array(
					'id'       => self::id( 'cd' . $el->name() ),
					'type'     => 'container',
					'settings' => array( '_css_classes' => 'uncoder-sb__card', '_attributes' => 'data-sb-title|' . str_replace( array( ',', '|' ), ' ', $el->title() ) ),
					'children' => array( self::widget( $el ) ),
				);
			}
			$tree[] = self::section( 'w-' . $cat, $nav[ 'w-' . $cat ], $cards, false, 'uncoder-sb__grid' );
		}

		$colors = '';
		foreach ( (array) $kit['colors'] as $color ) {
			$colors .= '<li class="uncoder-sb__swatch"><span style="background:var(--uncoder-c-' . esc_attr( sanitize_key( (string) $color['id'] ) ) . ')"></span><strong>' . esc_html( (string) $color['name'] ) . '</strong><code>' . esc_html( (string) $color['value'] ) . '</code></li>';
		}
		return array(
			'tree'  => $tree,
			'nav'   => $nav,
			'intro' => '<section class="uncoder-sb__section" id="uncoder-sb-colors"><h2 class="uncoder-sb__title">' . esc_html__( 'Colors', 'uncoder' ) . '</h2><ul class="uncoder-sb__swatches">' . $colors . '</ul></section>',
		);
	}

	/**
	 * A widget with its starting settings; nested widgets (tabs, accordion…) get one container per item.
	 *
	 * @return array<string,mixed>
	 */
	private static function widget( Element_Base $el ): array {
		$settings = $el->preset();
		$node     = array(
			'id'       => self::id( 'wd' . $el->name() ),
			'type'     => $el->name(),
			'settings' => $settings,
		);
		$nested = $el->nested();
		if ( $nested ) {
			$key              = (string) ( $nested['items'] ?? '' );
			$rows             = is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : array();
			$node['children'] = array();
			foreach ( array_values( $rows ) as $i => $row ) {
				$node['children'][] = array(
					'id'       => self::id( 'nc' . $el->name() . $i ),
					'type'     => 'container',
					'children' => array(
						array(
							'id'       => self::id( 'nt' . $el->name() . $i ),
							'type'     => 'text-editor',
							/* translators: %d: item number. */
							'settings' => array( 'content' => '<p>' . sprintf( esc_html__( 'Content of item %d.', 'uncoder' ), $i + 1 ) . '</p>' ),
						),
					),
				);
			}
		}
		return $node;
	}

	/**
	 * @param array<int, array<string,mixed>> $children Elements.
	 */
	private static function section( string $key, string $title, array $children, bool $row = false, string $class = '' ): array {
		return array(
			'id'       => self::id( 'sc' . $key ),
			'type'     => 'container',
			'settings' => array(
				'_css_id'      => 'uncoder-sb-' . $key,
				'_css_classes' => trim( 'uncoder-sb__section ' . $class ),
				'_attributes'  => 'data-sb-title|' . str_replace( array( ',', '|' ), ' ', $title ),
				'direction'    => $row ? 'row' : 'column',
				'wrap'         => $row ? 'wrap' : '',
			),
			'children' => $children,
		);
	}

	/** A valid element id from a readable seed. */
	private static function id( string $seed ): string {
		return 'sb' . substr( md5( $seed ), 0, 10 );
	}

	/**
	 * @param array<string,string> $nav Section anchors.
	 */
	private function page( array $nav, string $intro, string $body ): void {
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html__( 'Style book', 'uncoder' ) . ' – ' . esc_html( get_bloginfo( 'name' ) ); ?></title>
		<?php wp_head(); ?>
</head>
<body <?php body_class( 'uncoder-kit uncoder-sb' ); ?>>
<header class="uncoder-sb__bar">
	<strong><?php esc_html_e( 'Style book', 'uncoder' ); ?></strong>
	<nav aria-label="<?php esc_attr_e( 'Style book sections', 'uncoder' ); ?>">
		<?php foreach ( $nav as $key => $label ) : ?>
			<a href="#uncoder-sb-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>
</header>
<main class="uncoder-sb__main">
		<?php
		echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in sections().
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaping renderer.
		?>
</main>
		<?php wp_footer(); ?>
</body>
</html>
		<?php
	}

	/** Frame of the page (not the widgets): kept neutral so the widgets show the site's own styles. */
	private static function chrome_css(): string {
		// :root beats the `html{margin-top:32px!important}` a theme's own admin bar callback may still print.
		return 'html:root{margin-top:0!important}body.uncoder-sb{margin:0;background:#f6f6f4}'
			. '.uncoder-sb__bar{position:sticky;top:0;z-index:100;display:flex;gap:18px;align-items:center;padding:10px 24px;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-bottom:1px solid #e4e4e0;font:500 13px/1.3 system-ui,sans-serif;color:#222}'
			. '.uncoder-sb__bar nav{display:flex;gap:4px;overflow-x:auto;scrollbar-width:none}'
			. '.uncoder-sb__bar a{padding:5px 10px;border-radius:99px;color:#444;text-decoration:none;white-space:nowrap}'
			. '.uncoder-sb__bar a:hover,.uncoder-sb__bar a:focus-visible{background:#ececea;color:#111}'
			. '.uncoder-sb__main{max-width:1240px;margin:0 auto;padding:24px 24px 80px}'
			. '.uncoder-sb__section{position:relative;scroll-margin-top:64px;margin:0 0 28px!important;padding:52px 24px 24px!important;background:#fff;border:1px solid #e8e8e4;border-radius:14px;gap:18px}'
			. '.uncoder-sb__section::before{content:attr(data-sb-title);position:absolute;top:20px;left:24px;font:600 13px/1.3 system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;color:#666}'
			. '.uncoder-sb .uncoder-sb__title{margin:0 0 16px;font:600 13px/1.3 system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;color:#666}'
			. '.uncoder-sb__swatches{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;margin:0;padding:0;list-style:none;font:13px/1.35 system-ui,sans-serif;color:#333}'
			. '.uncoder-sb__swatch span{display:block;height:64px;margin-bottom:8px;border-radius:10px;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}'
			. '.uncoder-sb__swatch strong{display:block;font-weight:600}.uncoder-sb__swatch code{color:#777;font-size:12px}'
			. '.uncoder-sb__grid>.uncoder-container__inner,.uncoder-sb__grid:not(:has(>.uncoder-container__inner)){display:grid!important;grid-template-columns:repeat(auto-fill,minmax(min(100%,340px),1fr));gap:18px;align-items:start}'
			. '.uncoder-sb__card{position:relative;min-width:0;padding:34px 16px 16px!important;border:1px dashed #dcdcd6;border-radius:10px}'
			. '.uncoder-sb__card::before{content:attr(data-sb-title);position:absolute;top:9px;left:12px;font:600 11px/1 system-ui,sans-serif;letter-spacing:.04em;text-transform:uppercase;color:#888}';
	}
}
