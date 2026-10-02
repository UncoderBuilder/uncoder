<?php
/**
 * Maintenance and coming-soon mode.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Settings_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in uncoder_wb_settings['maintenance']:
 * { mode: "" | "coming_soon" | "maintenance", page: int, access: "logged_in" | "roles", roles: string[] }.
 * Visitors without access get the chosen Uncoder page (or a plain notice) instead of the site:
 * maintenance answers 503 + Retry-After so search engines keep the old results; coming soon answers 200.
 * wp-admin, wp-login.php, REST, AJAX, cron, robots.txt and the favicon keep working.
 */
final class Maintenance {

	public const MODES = array( 'coming_soon', 'maintenance' );

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'intercept' ), 0 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'admin_bar_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_bar_style' ) );
	}

	/**
	 * @return array{mode:string, page:int, access:string, roles:string[]}
	 */
	public static function get(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$raw    = is_array( $stored ) && is_array( $stored['maintenance'] ?? null ) ? $stored['maintenance'] : array();
		return self::sanitize( $raw );
	}

	/**
	 * @param array<string,mixed> $raw Raw settings.
	 * @return array{mode:string, page:int, access:string, roles:string[]}
	 */
	public static function sanitize( array $raw ): array {
		$mode  = (string) ( $raw['mode'] ?? '' );
		$roles = array_values( array_intersect( array_map( 'strval', (array) ( $raw['roles'] ?? array() ) ), array_keys( wp_roles()->get_names() ) ) );
		return array(
			'mode'   => in_array( $mode, self::MODES, true ) ? $mode : '',
			'page'   => absint( $raw['page'] ?? 0 ),
			'access' => 'roles' === ( $raw['access'] ?? '' ) ? 'roles' : 'logged_in',
			'roles'  => $roles,
		);
	}

	public static function save( array $raw ): array {
		$clean                  = self::sanitize( $raw );
		$stored                 = get_option( Settings_Controller::OPTION, array() );
		$stored                 = is_array( $stored ) ? $stored : array();
		$stored['maintenance']  = $clean;
		update_option( Settings_Controller::OPTION, $stored );
		return $clean;
	}

	/**
	 * Whether the current visitor sees the real site.
	 *
	 * @param array{mode:string, page:int, access:string, roles:string[]} $s Settings.
	 */
	public static function can_access( array $s ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) || 'logged_in' === $s['access'] ) {
			return true;
		}
		return (bool) array_intersect( $s['roles'], (array) wp_get_current_user()->roles );
	}

	public function intercept(): void {
		$s = self::get();
		if ( '' === $s['mode'] || self::can_access( $s ) || is_robots() || is_favicon() || ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) ) {
			return;
		}

		/**
		 * Lets a request through maintenance mode (e.g. a payment webhook page).
		 *
		 * @param bool $bypass Whether to serve the real site.
		 */
		if ( apply_filters( 'uncoder_wb/maintenance/bypass', false ) ) {
			return;
		}

		nocache_headers();
		if ( 'maintenance' === $s['mode'] ) {
			status_header( 503 );
			header( 'Retry-After: 3600' );
		} else {
			status_header( 200 );
		}

		// Any builder page works, drafts included: it is shown on purpose, and a draft keeps it off menus and sitemaps.
		$status = $s['page'] ? get_post_status( $s['page'] ) : false;
		$doc    = $status && 'trash' !== $status ? Plugin::instance()->documents()->get( $s['page'] ) : null;
		$ok     = $doc && $doc->is_builder();
		if ( $ok ) {
			Assets::enqueue_document( $doc );
		}
		// Only the chosen page: no theme header/footer, popups or admin chrome for visitors.
		$popups = Plugin::instance()->module( 'popups' );
		if ( $popups ) {
			remove_action( 'wp_footer', array( $popups, 'render' ), 20 );
		}
		add_filter( 'show_admin_bar', '__return_false' );
		add_filter( 'pre_get_document_title', static fn() => get_bloginfo( 'name' ) . ( 'maintenance' === $s['mode'] ? ' — ' . __( 'Maintenance', 'uncoder' ) : '' ), PHP_INT_MAX );

		echo '<!doctype html><html ';
		language_attributes();
		echo '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
		if ( ! current_theme_supports( 'title-tag' ) ) {
			echo '<title>' . esc_html( wp_get_document_title() ) . '</title>';
		}
		wp_head();
		// No theme header or footer is printed here, so drop the classes that describe them.
		$classes = array_filter( get_body_class( 'uncoder-site-closed uncoder-site-closed--' . $s['mode'] ), static fn( $c ) => 0 !== strpos( $c, 'uncoder-has-' ) );
		echo '</head><body class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( $ok ) {
			echo $doc->render( array( 'post_id' => $s['page'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped by the Renderer.
		} else {
			echo self::fallback( $s['mode'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		}
		wp_footer();
		echo '</body></html>';
		exit;
	}

	private static function fallback( string $mode ): string {
		$title = 'maintenance' === $mode ? __( 'We’ll be back soon', 'uncoder' ) : __( 'Coming soon', 'uncoder' );
		$text  = 'maintenance' === $mode ? __( 'The site is getting some care. Please check back in a little while.', 'uncoder' ) : __( 'Something new is on its way.', 'uncoder' );
		return '<main style="min-height:100vh;display:grid;place-items:center;padding:24px;font-family:system-ui,sans-serif;text-align:center">'
			. '<div><p style="margin:0 0 8px;opacity:.6">' . esc_html( get_bloginfo( 'name' ) ) . '</p><h1 style="margin:0 0 12px;font-size:clamp(28px,5vw,48px)">' . esc_html( $title ) . '</h1><p style="margin:0;opacity:.75">' . esc_html( $text ) . '</p></div></main>';
	}

	/**
	 * Reminds logged-in admins that visitors see the maintenance page.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public function admin_bar( $bar ): void {
		$s = self::get();
		if ( '' === $s['mode'] || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'uncoder-site-closed',
				'title' => 'maintenance' === $s['mode'] ? __( 'Maintenance mode is on', 'uncoder' ) : __( 'Coming soon mode is on', 'uncoder' ),
				'href'  => admin_url( 'admin.php?page=uncoder-settings#access' ),
				'meta'  => array( 'class' => 'uncoder-site-closed' ),
			)
		);
	}

	public function admin_bar_style(): void {
		if ( '' === self::get()['mode'] || ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_inline_style( 'admin-bar', '#wpadminbar .uncoder-site-closed > .ab-item{background:#b32d2e;color:#fff}#wpadminbar .uncoder-site-closed:hover > .ab-item{background:#8a2424!important;color:#fff!important}' );
	}
}
