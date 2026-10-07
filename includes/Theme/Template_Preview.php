<?php
/**
 * Live preview of a theme template (Theme Builder → Preview).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Theme;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Renderer;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Site\Multilingual;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * home_url( '?uncoder_template_preview={id}' ) shows a template the way visitors will see it, drafts included,
 * for people who may edit it:
 * - header / footer: on their own (like a section), or with `in_page=1` on the home page (or `sample`) in place
 *   of the current one;
 * - single, archive, search and 404 layouts: on a matching page — the latest post, a page, the blog, a search,
 *   a missing URL, or the post given as `sample` — in place of whatever layout would apply;
 * - popup: on the home page, opened at once;
 * - section, loop item (three latest posts), mega menu, and a header or footer by default: on their own, inside the theme.
 * Other popups and the cookie banner stay out of the way; the admin bar is hidden. On a WPML / Polylang site
 * the sample page is in the template's language.
 */
final class Template_Preview {

	public const QUERY  = 'uncoder_template_preview';
	private const AT    = 'uncoder_tp_at';
	private const BODY  = array( 'single-post', 'single-page', 'single', 'archive', 'search-results', 'error-404' );
	private const ALONE = array( 'section', 'loop-item', 'mega-menu' );
	/** Shown on their own unless `in_page` asks for them on a page. */
	private const PARTS = array( 'header', 'footer' );

	private int $id     = 0;
	private string $type = '';

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'route' ), 0 );
	}

	/**
	 * @param int $sample Post to preview a single layout with (0 = automatic).
	 */
	public static function url( int $id, int $sample = 0 ): string {
		$args = array( self::QUERY => $id );
		if ( $sample ) {
			$args['sample'] = $sample;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	public function route(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view, capability-checked.
		if ( empty( $_GET[ self::QUERY ] ) ) {
			return;
		}
		$id     = absint( $_GET[ self::QUERY ] );
		$sample = isset( $_GET['sample'] ) ? absint( $_GET['sample'] ) : 0;
		$at     = ! empty( $_GET[ self::AT ] );
		$inpage = ! empty( $_GET['in_page'] );
		// phpcs:enable
		$post = get_post( $id );
		if ( ! $post || Post_Types::TEMPLATE !== $post->post_type || 'trash' === $post->post_status ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			if ( ! is_user_logged_in() ) {
				auth_redirect(); // Exits.
			}
			// auth_redirect() lets any logged-in user through: drafts must not reach accounts that cannot edit them.
			wp_die( esc_html__( 'Sorry, you are not allowed to preview this template.', 'uncoder' ), '', array( 'response' => 403 ) );
		}
		$this->id   = $id;
		$this->type = (string) get_post_meta( $id, Utils::META_TYPE, true );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		show_admin_bar( false );
		$this->quiet();
		// Only the previewed popup (when it is one); the site's own popups stay out of the way.
		add_filter( 'uncoder_wb/popups/matched', fn() => 'popup' === $this->type ? array( $this->id ) : array() );

		if ( in_array( $this->type, self::ALONE, true ) || ( in_array( $this->type, self::PARTS, true ) && ! $inpage ) ) {
			$this->render_alone();
			exit;
		}
		if ( ! $at ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag.
			wp_safe_redirect( add_query_arg( array_filter( array( self::QUERY => $id, self::AT => 1, 'in_page' => $inpage ? 1 : null, 'thumb' => empty( $_GET['thumb'] ) ? null : 1 ) ), $this->sample_url( $sample ) ) );
			exit;
		}

		// The 404 layout is previewed on a missing URL: it still renders as the 404 page, but the preview
		// (and the Theme Builder thumbnail) loads with 200 so the browser logs no failed request.
		if ( 'error-404' === $this->type && is_404() ) {
			status_header( 200 );
		}

		// On the sample page: this template wins its location; other layouts step aside.
		add_filter(
			'uncoder_wb/theme/resolve',
			function ( $best, $type ) {
				if ( $type === $this->type ) {
					return $this->id;
				}
				return in_array( $this->type, self::BODY, true ) && in_array( $type, self::BODY, true ) ? 0 : $best;
			},
			99,
			2
		);
		add_filter(
			'uncoder_wb/popups/settings',
			function ( $s, $popup ) {
				if ( (int) $popup === $this->id ) {
					$s['triggers']  = array( 'load' => array( 'enabled' => true, 'delay' => 0 ) );
					$s['frequency'] = array( 'times' => 0, 'period' => 'session' );
					$s['devices']   = array( 'desktop', 'tablet', 'mobile' );
					// "Who sees it" rules (referrer, URL parameter, visit number, schedule, browsers) never hide a preview.
					$s['rules'] = array_merge(
						(array) ( $s['rules'] ?? array() ),
						array(
							'referrer'       => '',
							'referrer_value' => '',
							'url_param'      => '',
							'sessions'       => 0,
							'schedule'       => array_merge( (array) ( $s['rules']['schedule'] ?? array() ), array( 'enabled' => false ) ),
							'browsers'       => array(),
						)
					);
				}
				return $s;
			},
			10,
			2
		);
		if ( empty( $_GET['thumb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag.
			add_action( 'wp_footer', array( $this, 'badge' ), 99 );
		}
	}

	/** Cookie banner off: it would cover the template. */
	private function quiet(): void {
		$consent = Plugin::instance()->module( 'consent' );
		if ( $consent ) {
			remove_action( 'wp_footer', array( $consent, 'banner' ), 50 );
			remove_action( 'wp_enqueue_scripts', array( $consent, 'enqueue' ), 22 );
		}
	}

	/** A page where this kind of template applies. */
	private function sample_url( int $sample ): string {
		if ( $sample && get_post_status( $sample ) && is_post_publicly_viewable( $sample ) ) {
			return (string) get_permalink( $sample );
		}
		$lang   = Multilingual::language_of( $this->id );
		$home   = Multilingual::home_url( $lang );
		$latest = static function ( $type ) use ( $lang ) {
			$ids = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) + Multilingual::query_args( $lang ) );
			return $ids ? (string) get_permalink( (int) $ids[0] ) : '';
		};
		switch ( $this->type ) {
			case 'single-post':
				$url = $latest( 'post' );
				break;
			case 'single-page':
				$front = 'page' === get_option( 'show_on_front' ) ? Multilingual::translate_id( (int) get_option( 'page_on_front' ), $lang ) : 0;
				$url   = $front ? (string) get_permalink( $front ) : $latest( 'page' );
				break;
			case 'single':
				$url = '';
				foreach ( get_post_types( array( 'public' => true, '_builtin' => false ) ) as $cpt ) {
					if ( Post_Types::TEMPLATE !== $cpt ) {
						$url = $latest( $cpt );
						if ( '' !== $url ) {
							break;
						}
					}
				}
				$url = '' !== $url ? $url : $latest( 'post' );
				break;
			case 'archive':
				$blog = (int) get_option( 'page_for_posts' );
				if ( $blog ) {
					$url = (string) get_permalink( Multilingual::translate_id( $blog, $lang ) );
				} elseif ( 'posts' === get_option( 'show_on_front' ) ) {
					$url = $home;
				} else {
					$cats = get_categories( array( 'number' => 1, 'orderby' => 'count', 'order' => 'DESC' ) + Multilingual::query_args( $lang ) );
					$url  = $cats ? (string) get_category_link( $cats[0] ) : $home;
				}
				break;
			case 'search-results':
				$ids  = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1 ) + Multilingual::query_args( $lang ) );
				$word = $ids ? (string) strtok( wp_strip_all_tags( $ids[0]->post_title ), ' ' ) : 'a';
				$url  = add_query_arg( 's', rawurlencode( $word ), $home );
				break;
			case 'error-404':
				$url = trailingslashit( $home ) . 'uncoder-preview-not-found-' . wp_rand( 1000, 9999 ) . '/';
				break;
			default: // header, footer, popup.
				$url = $home;
		}
		return '' !== $url ? $url : $home;
	}

	/** Small label in the corner (the page may also be opened in its own tab). */
	public function badge(): void {
		$post   = get_post( $this->id );
		$label  = Post_Types::TEMPLATE_TYPES[ $this->type ] ?? $this->type;
		$status = $post && 'publish' !== $post->post_status ? ' · ' . __( 'draft', 'uncoder' ) : '';
		echo '<div class="uncoder-tpl-preview-badge" style="position:fixed;left:12px;bottom:12px;z-index:2147483000;padding:6px 10px;border-radius:8px;background:rgba(8,50,65,.92);color:#fff;font:500 12px/1.3 system-ui,sans-serif;box-shadow:0 6px 18px rgba(0,0,0,.2);pointer-events:none">'
			. esc_html( sprintf( /* translators: 1: template type, 2: template title. */ __( 'Preview · %1$s “%2$s”', 'uncoder' ), $label, $post ? $post->post_title : '' ) . $status ) . '</div>';
	}

	/** Sections, loop items, mega menus, headers and footers on their own page, inside the theme. */
	private function render_alone(): void {
		$doc = Plugin::instance()->documents()->get( $this->id );
		if ( ! $doc ) {
			wp_die( esc_html__( 'This template cannot be shown.', 'uncoder' ), 404 );
		}
		Assets::enqueue_document( $doc );
		$html = '';
		if ( 'loop-item' === $this->type ) {
			Assets::enqueue_widget_style( 'loop-grid' );
			$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 3 ) );
			foreach ( $posts as $p ) {
				$html .= Theme_Context::with_post(
					$p,
					fn() => ( new Renderer( $this->id, false, (int) $p->ID ) )->render_document(
						$doc->elements(),
						array(
							'tag'   => 'article',
							'class' => 'uncoder-loop-grid__item',
						)
					)
				);
			}
			$html = '<div class="uncoder-loop-grid uncoder-loop-grid--equal" style="max-width:1200px;margin:48px auto;padding:0 24px"><div class="uncoder-loop-grid__grid" style="grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr))">' . $html . '</div></div>';
			if ( ! $posts ) {
				$html = '<p style="text-align:center;margin:64px 16px">' . esc_html__( 'Publish a post to preview this loop item with real content.', 'uncoder' ) . '</p>';
			}
		} elseif ( 'mega-menu' === $this->type ) {
			Assets::enqueue_widget_style( 'mega-menu' );
			$html = '<div style="max-width:1200px;margin:48px auto;padding:0 24px"><div style="background:#fff;border-radius:12px;box-shadow:0 24px 60px -24px rgba(15,23,42,.35);overflow:hidden">' . $doc->render() . '</div></div>';
		} else {
			$html = $doc->render();
		}
		$thumb = ! empty( $_GET['thumb'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag.
		if ( ! $thumb ) {
			add_action( 'wp_footer', array( $this, 'badge' ), 99 );
		}
		// A header or footer is a thin strip: Theme Builder thumbnails show it in the middle.
		$centre = $thumb && in_array( $this->type, self::PARTS, true );
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( get_the_title( $this->id ) ); ?></title>
		<?php wp_head(); ?>
</head>
<body <?php body_class( 'uncoder-kit uncoder-tpl-preview' ); ?> style="margin:0;<?php echo in_array( $this->type, array( 'section', 'header', 'footer' ), true ) ? '' : 'background:#f4f3f1;'; echo $centre ? 'min-height:100vh;display:flex;flex-direction:column;justify-content:center' : ''; ?>">
		<?php
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaping renderer.
		wp_footer();
		?>
</body>
</html>
		<?php
	}
}
