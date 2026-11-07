<?php
/**
 * Activation, deactivation and schema upgrades.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Creates tables, options, capabilities and upload folders.
 */
final class Install {

	public const DB_VERSION        = '1.5.0';
	public const DB_VERSION_OPTION = 'uncoder_wb_db_version';
	public const MCP_CAP           = 'uncoder_wb_use_mcp';

	public static function activate(): void {
		self::install();
		update_option( 'uncoder_wb_flush_rewrite', 1, false );
		if ( ! get_option( 'uncoder_wb_installed_at' ) ) {
			update_option( 'uncoder_wb_installed_at', time(), false );
		}
		// A site built with another page builder keeps its look: the Design System's "Apply to the whole site"
		// starts off there (Uncoder styles only its own pages), on the first activation only.
		if ( null === get_option( Core\Kit::OPTION, null ) && self::has_other_builder_content() ) {
			add_option( Core\Kit::OPTION, array( 'theme' => array( 'enabled' => false ) ) );
		}
	}

	/** Post meta that page builders set on the content they build: meta key => value ('' = any value). */
	private const BUILDER_META = array(
		'_elementor_edit_mode'   => 'builder',
		'_et_pb_use_builder'     => 'on',
		'_fl_builder_enabled'    => '1',
		'_bricks_editor_mode'    => 'bricks',
		'ct_builder_shortcodes'  => '',
		'_wpb_vc_js_status'      => 'true',
		'_breakdance_data'       => '',
		'brizy_post_uid'         => '',
	);

	/**
	 * Whether the site has published content built with another page builder (Elementor, Divi, Beaver Builder,
	 * Bricks, Oxygen, WPBakery, Breakdance, Brizy).
	 */
	public static function has_other_builder_content(): bool {
		global $wpdb;
		$where = array();
		foreach ( self::BUILDER_META as $key => $value ) {
			$where[] = '' === $value
				? $wpdb->prepare( '( m.meta_key = %s AND m.meta_value <> %s )', $key, '' )
				: $wpdb->prepare( '( m.meta_key = %s AND m.meta_value = %s )', $key, $value );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off check on activation; the conditions are prepared above.
		return (bool) $wpdb->get_var( "SELECT 1 FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_status = 'publish' AND ( " . implode( ' OR ', $where ) . ' ) LIMIT 1' );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'uncoder_wb_daily' );
		// Safe mode's must-use plugin must not outlive the plugin.
		Site\Support_Tools::disable_safe_mode();
	}

	public static function maybe_upgrade(): void {
		$stored = (string) get_option( self::DB_VERSION_OPTION, '' );
		if ( $stored !== self::DB_VERSION ) {
			self::install();
			// Fresh installs have nothing to migrate.
			Core\Migrations::run( $stored );
		}
	}

	public static function install(): void {
		self::create_tables();
		self::add_caps();
		self::create_upload_dirs();
		if ( ! wp_next_scheduled( 'uncoder_wb_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'uncoder_wb_daily' );
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'uncoder_wb_';

		$sql = array();

		$sql[] = "CREATE TABLE {$p}submissions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			element_id varchar(32) NOT NULL DEFAULT '',
			form_name varchar(191) NOT NULL DEFAULT '',
			data longtext NOT NULL,
			meta longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'unread',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}mcp_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			token_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client varchar(191) NOT NULL DEFAULT '',
			method varchar(64) NOT NULL DEFAULT '',
			tool varchar(100) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'ok',
			duration_ms int(10) unsigned NOT NULL DEFAULT 0,
			ip varchar(64) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			snapshot varchar(64) NOT NULL DEFAULT '',
			summary text NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY tool (tool),
			KEY user_id (user_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}tokens (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL,
			token_hash char(64) NOT NULL,
			token_hint varchar(16) NOT NULL DEFAULT '',
			name varchar(191) NOT NULL DEFAULT '',
			client_id varchar(191) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			scopes varchar(255) NOT NULL DEFAULT '',
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta longtext NULL,
			created_at datetime NOT NULL,
			expires_at datetime NULL,
			last_used_at datetime NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY type_user (type, user_id),
			KEY client_id (client_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}oauth_clients (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(191) NOT NULL,
			client_name varchar(191) NOT NULL DEFAULT '',
			redirect_uris text NOT NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			last_used_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY client_id (client_id)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	private static function add_caps(): void {
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::MCP_CAP ) ) {
			$admin->add_cap( self::MCP_CAP );
		}
	}

	public static function create_upload_dirs(): void {
		$upload = wp_upload_dir( null, false );
		if ( ! empty( $upload['error'] ) ) {
			return;
		}
		$base = trailingslashit( $upload['basedir'] ) . 'uncoder';
		foreach ( array( $base, $base . '/css', $base . '/forms' ) as $dir ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			$index = $dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				// Silence is golden.
				file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		$htaccess = $base . '/forms/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// Apache 2.4 and 2.2 syntax, each only where its module is loaded (a bare "Deny" is a 500 error without mod_access_compat).
			file_put_contents( $htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
