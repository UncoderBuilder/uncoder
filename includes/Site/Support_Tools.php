<?php
/**
 * Settings → Tools: system info, safe mode, version rollback.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * - System info: what support needs to know (WordPress, server, theme, plugins, Uncoder), copyable as text.
 * - Safe mode: the editor loads with only Uncoder active and a default theme, to tell whether another plugin or
 *   the theme breaks it. A must-use plugin (wp-content/mu-plugins/uncoder-safe-mode.php) does this for editor
 *   requests (the builder screen, Uncoder REST calls, the canvas preview) from the browser that turned it on
 *   (secret cookie); visitors and other admins are never affected.
 * - Rollback: reinstalls an earlier release from WordPress.org (only when the listing is Uncoder's own, never
 *   on a development copy).
 */
final class Support_Tools {

	public const SAFE_OPTION = 'uncoder_wb_safe_mode';
	public const SAFE_COOKIE = 'uncoder_safe_mode';
	private const MU_FILE    = 'uncoder-safe-mode.php';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$admin = static fn() => current_user_can( 'manage_options' );
		register_rest_route( Rest::NS, '/system-info', array( 'methods' => 'GET', 'callback' => array( $this, 'system_info' ), 'permission_callback' => $admin ) );
		register_rest_route(
			Rest::NS,
			'/safe-mode',
			array(
				array( 'methods' => 'GET', 'callback' => fn() => new WP_REST_Response( self::safe_state() ), 'permission_callback' => $admin ),
				array( 'methods' => 'POST', 'callback' => array( $this, 'set_safe_mode' ), 'permission_callback' => $admin ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/rollback',
			array(
				array( 'methods' => 'GET', 'callback' => array( $this, 'versions' ), 'permission_callback' => static fn() => current_user_can( 'update_plugins' ) ),
				array( 'methods' => 'POST', 'callback' => array( $this, 'rollback' ), 'permission_callback' => static fn() => current_user_can( 'update_plugins' ) ),
			)
		);
	}

	/* ------------------------------------------------------------------ System info */

	public function system_info(): WP_REST_Response {
		global $wpdb, $wp_version;
		$theme   = wp_get_theme();
		$plugins = array();
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $file => $data ) {
			if ( is_plugin_active( $file ) ) {
				$plugins[] = $data['Name'] . ' ' . $data['Version'];
			}
		}
		$uploads  = wp_upload_dir( null, false );
		$css_dir  = Utils::uploads();
		$count    = static fn( $type, $meta = true ) => (int) ( new \WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => $meta ? Utils::META_MODE : '', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $meta ? 'builder' : '', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'lang'           => '',
			)
		) )->found_posts;
		$ext      = array();
		foreach ( array( 'zip', 'gd', 'imagick', 'mbstring', 'curl', 'intl', 'dom', 'libxml', 'openssl', 'exif' ) as $e ) {
			$ext[ $e ] = extension_loaded( $e );
		}
		$sections = array(
			'WordPress' => array(
				'Version'          => $wp_version,
				'Site URL'         => site_url(),
				'Home URL'         => home_url(),
				'Multisite'        => is_multisite(),
				'Language'         => get_locale(),
				'Time zone'        => wp_timezone_string(),
				'Permalinks'       => (string) get_option( 'permalink_structure' ) ?: 'plain',
				'Debug mode'       => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'Memory limit'     => WP_MEMORY_LIMIT,
				'Environment'      => wp_get_environment_type(),
				'Cron'             => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'disabled (DISABLE_WP_CRON)' : 'on',
				'Object cache'     => (bool) wp_using_ext_object_cache(),
			),
			'Server'    => array(
				'PHP'                 => PHP_VERSION,
				'Server'              => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
				'Database'            => $wpdb->db_server_info(),
				'PHP memory limit'    => ini_get( 'memory_limit' ),
				'Max execution time'  => ini_get( 'max_execution_time' ) . ' s',
				'Upload max filesize' => ini_get( 'upload_max_filesize' ),
				'Post max size'       => ini_get( 'post_max_size' ),
				'Max input vars'      => ini_get( 'max_input_vars' ),
				'Extensions'          => implode( ', ', array_map( static fn( $k, $v ) => $k . ( $v ? '' : ' (missing)' ), array_keys( $ext ), $ext ) ),
			),
			'Theme'     => array(
				'Name'        => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
				'Block theme' => $theme->is_block_theme(),
				'Child theme' => is_child_theme() && $theme->parent() ? $theme->parent()->get( 'Name' ) : false,
			),
			'Plugins'   => array(
				'Active (' . count( $plugins ) . ')' => implode( ', ', $plugins ),
				'Must-use'                            => implode( ', ', array_map( static fn( $p ) => $p['Name'], get_mu_plugins() ) ),
			),
			'Uncoder'   => array(
				'Version'             => defined( 'UNCODER_WB_VERSION' ) ? UNCODER_WB_VERSION : '',
				'Pages & posts built' => $count( 'any' ),
				'Theme templates'     => $count( Post_Types::TEMPLATE, false ),
				'Uploads writable'    => wp_is_writable( $uploads['basedir'] ),
				'CSS files writable'  => $css_dir && wp_is_writable( $css_dir['dir'] ),
				'Safe mode'           => self::safe_state()['enabled'],
				'REST API'            => rest_url( Rest::NS ),
				'Multilingual'        => Multilingual::plugin() ?: 'no',
			),
		);
		// Readable values (booleans as Yes / No).
		foreach ( $sections as $name => $rows ) {
			foreach ( $rows as $k => $v ) {
				$sections[ $name ][ $k ] = is_bool( $v ) ? ( $v ? 'Yes' : 'No' ) : (string) $v;
			}
		}
		return new WP_REST_Response( $sections );
	}

	/* ------------------------------------------------------------------ Safe mode */

	/** @return array{enabled:bool, mine:bool, possible:bool, file:string} */
	public static function safe_state(): array {
		$opt  = get_option( self::SAFE_OPTION, array() );
		$on   = is_array( $opt ) && ! empty( $opt['token'] );
		$mine = $on && isset( $_COOKIE[ self::SAFE_COOKIE ] ) && hash_equals( (string) $opt['token'], sanitize_text_field( wp_unslash( $_COOKIE[ self::SAFE_COOKIE ] ) ) );
		$dir  = WPMU_PLUGIN_DIR; // Always defined by WordPress before plugins load.
		return array(
			'enabled'  => $on && file_exists( $dir . '/' . self::MU_FILE ),
			'mine'     => $mine,
			'possible' => wp_is_writable( is_dir( $dir ) ? $dir : dirname( $dir ) ),
			'file'     => $dir . '/' . self::MU_FILE,
		);
	}

	/**
	 * Turns safe mode off: removes the must-use plugin and its option. Also for deactivation / uninstall.
	 */
	public static function disable_safe_mode(): void {
		$dir  = WPMU_PLUGIN_DIR; // Always defined by WordPress before plugins load.
		$file = $dir . '/' . self::MU_FILE;
		delete_option( self::SAFE_OPTION );
		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
	}

	public function set_safe_mode( WP_REST_Request $request ) {
		$dir  = WPMU_PLUGIN_DIR; // Always defined by WordPress before plugins load.
		$file = $dir . '/' . self::MU_FILE;
		if ( ! $request->get_param( 'enabled' ) ) {
			self::disable_safe_mode();
			setcookie( self::SAFE_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
			return new WP_REST_Response( self::safe_state() );
		}
		if ( ! wp_mkdir_p( $dir ) || ! wp_is_writable( $dir ) ) {
			return new WP_Error( 'uncoder_safe', __( 'wp-content/mu-plugins is not writable, so safe mode cannot be turned on.', 'uncoder' ), array( 'status' => 500 ) );
		}
		$token = wp_generate_password( 32, false, false );
		update_option(
			self::SAFE_OPTION,
			array(
				'token'  => $token,
				'plugin' => plugin_basename( UNCODER_WB_PATH . 'uncoder.php' ),
				'theme'  => self::fallback_theme(),
				'user'   => get_current_user_id(),
				'time'   => time(),
			),
			false
		);
		file_put_contents( $file, self::mu_plugin() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		setcookie( self::SAFE_COOKIE, $token, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ self::SAFE_COOKIE ] = $token;
		return new WP_REST_Response( self::safe_state() );
	}

	/** A default theme that is installed (the editor canvas then shows pages in it). */
	private static function fallback_theme(): string {
		foreach ( array( 'twentytwentyfive', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone' ) as $slug ) {
			if ( wp_get_theme( $slug )->exists() ) {
				return $slug;
			}
		}
		return '';
	}

	/** Source of the must-use plugin (static: it only reads the option and the cookie). */
	private static function mu_plugin(): string {
		return <<<'PHP'
<?php
/**
 * Plugin Name: Uncoder safe mode
 * Description: While Uncoder's safe mode is on (Uncoder → Settings → Tools), the builder loads with only Uncoder active and a default theme — for the browser that turned it on. It removes itself after a day, or when safe mode is turned off or Uncoder is deactivated. Delete this file to turn it off.
 */

defined( 'ABSPATH' ) || exit;

( static function () {
	$opt    = get_option( 'uncoder_wb_safe_mode' );
	$plugin = is_array( $opt ) ? (string) ( $opt['plugin'] ?? '' ) : '';
	$active = '' !== $plugin && ( in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) || ( is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $plugin ] ) ) );
	// Off, expired (the cookie lasts a day) or Uncoder no longer active: this file cleans up after itself.
	if ( ! is_array( $opt ) || empty( $opt['token'] ) || (int) ( $opt['time'] ?? 0 ) < time() - DAY_IN_SECONDS || ! $active ) {
		if ( ! is_multisite() ) {
			delete_option( 'uncoder_wb_safe_mode' );
			wp_delete_file( __FILE__ );
		}
		return;
	}
	if ( empty( $_COOKIE['uncoder_safe_mode'] ) || ! hash_equals( (string) $opt['token'], (string) $_COOKIE['uncoder_safe_mode'] ) ) {
		return;
	}
	$uri    = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$editor = ( isset( $_GET['action'] ) && 'uncoder' === $_GET['action'] )
		|| false !== strpos( $uri, '/wp-json/uncoder/' )
		|| ( isset( $_GET['rest_route'] ) && 0 === strpos( (string) $_GET['rest_route'], '/uncoder/' ) )
		|| isset( $_GET['uncoder_preview'] ) || isset( $_GET['uncoder_draft'] );
	if ( ! $editor ) {
		return;
	}
	$plugin = (string) ( $opt['plugin'] ?? '' );
	add_filter( 'option_active_plugins', static fn() => '' !== $plugin ? array( $plugin ) : array(), 1 );
	add_filter( 'site_option_active_sitewide_plugins', static fn() => array(), 1 );
	$theme = (string) ( $opt['theme'] ?? '' );
	if ( '' !== $theme ) {
		add_filter( 'pre_option_template', static fn() => $theme, 1 );
		add_filter( 'pre_option_stylesheet', static fn() => $theme, 1 );
	}
	define( 'UNCODER_SAFE_MODE', true );
} )();
PHP;
	}

	/* ------------------------------------------------------------------ Rollback */

	/** A development copy (symlinked or junctioned folder, or a git checkout) is never overwritten. */
	private static function dev_copy(): bool {
		$path = wp_normalize_path( untrailingslashit( UNCODER_WB_PATH ) );
		$real = wp_normalize_path( (string) realpath( UNCODER_WB_PATH ) );
		return is_link( untrailingslashit( UNCODER_WB_PATH ) ) || strtolower( $path ) !== strtolower( $real ) || file_exists( UNCODER_WB_PATH . '.git' ) || file_exists( dirname( UNCODER_WB_PATH ) . '/.git' );
	}

	/**
	 * Earlier releases: [version => package URL], from WordPress.org when the listing is Uncoder's. Only
	 * packages served by downloads.wordpress.org are ever offered.
	 *
	 * @return array<string,string>|WP_Error
	 */
	private static function available() {
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		$info = plugins_api( 'plugin_information', array( 'slug' => 'uncoder', 'fields' => array( 'versions' => true, 'sections' => false ) ) );
		if ( is_wp_error( $info ) ) {
			return new WP_Error( 'uncoder_rollback', __( 'Earlier versions are on builder.uncoder.co: download one and upload it under Plugins → Add New.', 'uncoder' ) );
		}
		// Only the real Uncoder listing (another plugin could use the same slug).
		$home = strtolower( (string) ( $info->homepage ?? '' ) . ' ' . (string) ( $info->author ?? '' ) );
		if ( false === strpos( $home, 'uncoderstudio.com' ) && false === strpos( $home, 'uncoder studio' ) ) {
			return new WP_Error( 'uncoder_rollback', __( 'The WordPress.org listing with this name is not Uncoder, so no versions are offered.', 'uncoder' ) );
		}
		$out = array();
		foreach ( (array) ( $info->versions ?? array() ) as $version => $url ) {
			$url = (string) $url;
			if ( 'trunk' === $version || ! preg_match( '/^\d+(\.\d+){1,3}$/', (string) $version ) || ! version_compare( (string) $version, UNCODER_WB_VERSION, '<' ) ) {
				continue;
			}
			if ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && 'downloads.wordpress.org' === wp_parse_url( $url, PHP_URL_HOST ) && 0 === strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), '/plugin/uncoder.' ) ) {
				$out[ (string) $version ] = $url;
			}
		}
		uksort( $out, static fn( $a, $b ) => version_compare( $b, $a ) );
		return array_slice( $out, 0, 10, true );
	}

	public function versions(): WP_REST_Response {
		$dev  = self::dev_copy();
		$list = $dev ? array() : self::available();
		return new WP_REST_Response(
			array(
				'current'  => UNCODER_WB_VERSION,
				'dev'      => $dev,
				'versions' => is_wp_error( $list ) ? array() : array_keys( $list ),
				'message'  => $dev ? __( 'This is a development copy of Uncoder (linked folder or git checkout): rollback is off so your working files are never replaced.', 'uncoder' ) : ( is_wp_error( $list ) ? $list->get_error_message() : '' ),
			)
		);
	}

	public function rollback( WP_REST_Request $request ) {
		if ( self::dev_copy() ) {
			return new WP_Error( 'uncoder_rollback', __( 'Rollback is off on a development copy.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$list    = self::available();
		$version = (string) $request->get_param( 'version' );
		if ( is_wp_error( $list ) || ! isset( $list[ $version ] ) ) {
			return new WP_Error( 'uncoder_rollback', __( 'This version is not available.', 'uncoder' ), array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $list[ $version ], array( 'overwrite_package' => true ) );
		if ( is_wp_error( $result ) || ! $result ) {
			$message = is_wp_error( $result ) ? $result->get_error_message() : implode( ' ', (array) $skin->get_error_messages() );
			return new WP_Error( 'uncoder_rollback', '' !== $message ? $message : __( 'The package could not be installed.', 'uncoder' ), array( 'status' => 500 ) );
		}
		activate_plugin( plugin_basename( UNCODER_WB_PATH . 'uncoder.php' ) );
		return new WP_REST_Response( array( 'installed' => $version ) );
	}
}
