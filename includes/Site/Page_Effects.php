<?php
/**
 * Page preloader and page transitions (Design System settings).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Editor\Preview;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * settings.preloader ("" | spinner | bar | logo) covers the page until it has loaded (at most 4 s),
 * by default only on the first page of a visit. settings.page_transition ("" | fade | slide) animates
 * between pages with the browser's View Transitions API: pure CSS, no delay, and browsers without
 * support simply navigate as usual. Both are skipped in the editor and for reduced motion.
 */
final class Page_Effects {

	public const PRELOADERS  = array( 'spinner', 'bar', 'logo' );
	public const TRANSITIONS = array( 'fade', 'slide' );

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 5 );
		add_action( 'wp_body_open', array( $this, 'preloader' ), 0 );
	}

	/**
	 * The effects' CSS, printed in <head> through a style handle without a file.
	 */
	public function enqueue(): void {
		$css = self::transition_css() . self::preloader_css();
		if ( '' === $css ) {
			return;
		}
		wp_register_style( 'uncoder-page-effects', false, array(), UNCODER_WB_VERSION );
		wp_enqueue_style( 'uncoder-page-effects' );
		wp_add_inline_style( 'uncoder-page-effects', $css );
	}

	private static function preloader_type(): string {
		$type = (string) Plugin::instance()->kit()->setting( 'preloader', '' );
		return in_array( $type, self::PRELOADERS, true ) && self::active() ? $type : '';
	}

	private static function active(): bool {
		if ( is_admin() || is_feed() || is_embed() ) {
			return false;
		}
		$preview = Plugin::instance()->module( 'preview' );
		return ! ( $preview instanceof Preview && $preview->active() );
	}

	private static function transition_css(): string {
		$type = (string) Plugin::instance()->kit()->setting( 'page_transition', '' );
		if ( ! in_array( $type, self::TRANSITIONS, true ) || ! self::active() ) {
			return '';
		}
		$css = '@media (prefers-reduced-motion:no-preference){@view-transition{navigation:auto}';
		if ( 'slide' === $type ) {
			$css .= '::view-transition-old(root){animation:.25s cubic-bezier(.4,0,1,1) both uncoder-vt-out}'
				. '::view-transition-new(root){animation:.4s cubic-bezier(0,0,.2,1) both uncoder-vt-in}'
				. '@keyframes uncoder-vt-out{to{opacity:0;transform:translateY(-12px)}}'
				. '@keyframes uncoder-vt-in{from{opacity:0;transform:translateY(24px)}}';
		} else {
			$css .= '::view-transition-group(root){animation-duration:.3s}';
		}
		return $css . '}';
	}

	/** Static CSS of the preloader (only when one is chosen). */
	private static function preloader_css(): string {
		if ( '' === self::preloader_type() ) {
			return '';
		}
		return 'html:not(.uncoder-js) .uncoder-preloader{display:none}'
			. '.uncoder-preloader{position:fixed;inset:0;z-index:100000;display:grid;place-items:center;background:var(--uncoder-c-white,#fff);color:var(--uncoder-c-primary,#2b59ff);transition:opacity .4s ease,visibility 0s linear .4s}'
			. '.uncoder-preloader.is-done{opacity:0;visibility:hidden}'
			. '.uncoder-preloader__spinner{width:40px;height:40px;border:3px solid color-mix(in srgb,currentColor 20%,transparent);border-top-color:currentColor;border-radius:50%;animation:uncoder-pl-spin .8s linear infinite}'
			. '.uncoder-preloader--bar{place-items:start stretch}'
			. '.uncoder-preloader__bar{display:block;height:3px;background:currentColor;transform-origin:0 50%;animation:uncoder-pl-bar 2.4s cubic-bezier(.1,.6,.3,1) forwards}'
			. '.uncoder-preloader__logo{width:auto;max-width:180px;height:auto;max-height:80px;animation:uncoder-pl-pulse 1.4s ease-in-out infinite}'
			. '.uncoder-preloader__name{color:var(--uncoder-c-heading,#111);font-size:22px;font-weight:700;animation:uncoder-pl-pulse 1.4s ease-in-out infinite}'
			. '@keyframes uncoder-pl-spin{to{transform:rotate(1turn)}}'
			. '@keyframes uncoder-pl-bar{from{transform:scaleX(0)}to{transform:scaleX(.9)}}'
			. '@keyframes uncoder-pl-pulse{50%{opacity:.45}}'
			. '@media (prefers-reduced-motion:reduce){.uncoder-preloader *{animation:none!important}}';
	}

	public function preloader(): void {
		$type = self::preloader_type();
		if ( '' === $type ) {
			return;
		}
		$kit   = Plugin::instance()->kit();
		$inner = '<span class="uncoder-preloader__spinner"></span>';
		if ( 'bar' === $type ) {
			$inner = '<span class="uncoder-preloader__bar"></span>';
		} elseif ( 'logo' === $type ) {
			$logo  = (int) get_theme_mod( 'custom_logo' );
			$img   = $logo ? wp_get_attachment_image( $logo, 'medium', false, array( 'class' => 'uncoder-preloader__logo', 'alt' => '', 'loading' => 'eager', 'decoding' => 'async' ) ) : '';
			$inner = '' !== $img ? $img : '<span class="uncoder-preloader__name">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		}
		$once = (bool) $kit->setting( 'preloader_once', true );
		// Hides the overlay once the page has loaded (or after 4 s); with "once", later pages of the
		// same visit skip it. Runs right after the markup, before anything else paints.
		$js = "(function(){var p=document.getElementById('uncoder-preloader');if(!p)return;"
			. ( $once ? "try{if(sessionStorage.getItem('uncoder-preloaded')){p.remove();return}sessionStorage.setItem('uncoder-preloaded','1')}catch(e){}" : '' )
			. "var done=function(){if(p.classList.contains('is-done'))return;p.classList.add('is-done');setTimeout(function(){p.remove()},450)};"
			. "if(document.readyState==='complete')done();else{addEventListener('load',done);setTimeout(done,4000)}"
			. "addEventListener('pageshow',function(e){if(e.persisted)done()})})();";
		echo '<div id="uncoder-preloader" class="uncoder-preloader uncoder-preloader--' . esc_attr( $type ) . '" aria-hidden="true">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		wp_print_inline_script_tag( $js );
	}
}
