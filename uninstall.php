<?php
/**
 * Uninstall: removes plugin data only when "Remove all data on uninstall" is enabled
 * (Uncoder → Settings). Pages keep their HTML copy in post_content either way.
 *
 * @package Uncoder\Builder
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes Uncoder data for the current site.
 */
function uncoder_wb_uninstall_site(): void {
	global $wpdb;

	$settings = get_option( 'uncoder_wb_settings', array() );
	if ( empty( $settings['remove_data'] ) ) {
		// Only scheduled work is removed; content, templates and settings stay.
		wp_clear_scheduled_hook( 'uncoder_wb_daily' );
		return;
	}

	// Theme templates, popups, sections, mega menus and loop items.
	$templates = get_posts(
		array(
			'post_type'      => 'uncoder_template',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $templates as $id ) {
		wp_delete_post( (int) $id, true );
	}

	// Builder data on posts, pages, attachments and menu items (revisions included).
	$meta_keys = array( '_uncoder_wb_data', '_uncoder_wb_mode', '_uncoder_wb_page', '_uncoder_wb_css_ver', '_uncoder_wb_css_inline', '_uncoder_wb_type', '_uncoder_wb_conditions', '_uncoder_wb_template_settings', '_uncoder_wb_assets', '_uncoder_wb_rev', '_uncoder_wb_snapshots', '_uncoder_wb_autosave', '_uncoder_wb_notes', '_uncoder_wb_ai_change', '_uncoder_wb_mega', '_uncoder_wb_seo_title', '_uncoder_wb_seo_description', '_uncoder_wb_generated', '_uncoder_wb_source_url', '_uncoder_wb_attribution', '_uncoder_ai_generated', '_uncoder_elementor_source', '_uncoder_kit_source' );
	foreach ( $meta_keys as $key ) {
		delete_post_meta_by_key( $key );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = 'default' WHERE meta_key = '_wp_page_template' AND meta_value IN (%s, %s)", 'uncoder-canvas', 'uncoder-full-width' ) );

	// Editor preferences (user meta).
	delete_metadata( 'user', 0, 'uncoder_wb_prefs', '', true );

	// Options: the known ones, then anything else with the plugin prefix.
	foreach ( array( 'uncoder_wb_db_version', 'uncoder_wb_flush_rewrite', 'uncoder_wb_installed_at', 'uncoder_wb_kit', 'uncoder_wb_kit_snapshots', 'uncoder_wb_kit_version', 'uncoder_wb_kit_css_build', 'uncoder_wb_mcp_settings', 'uncoder_wb_mega_templates', 'uncoder_wb_settings', 'uncoder_wb_templates_index', 'uncoder_wb_fonts', 'uncoder_wb_snippets', 'uncoder_wb_icon_sets', 'uncoder_wb_elementor_import', 'uncoder_wb_safe_mode' ) as $option ) {
		delete_option( $option );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$leftover = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'uncoder_wb_' ) . '%' ) );
	foreach ( (array) $leftover as $option ) {
		delete_option( $option );
	}
	// Transients (rate limits, sessions, caches): uncoder_wb_* and uncoder_ai_*.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_uncoder_' ) . '%', $wpdb->esc_like( '_transient_timeout_uncoder_' ) . '%' ) );

	// Tables: form submissions, MCP activity log, tokens and OAuth clients.
	foreach ( array( 'submissions', 'mcp_log', 'tokens', 'oauth_clients' ) as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'uncoder_wb_' . $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	// Capability.
	foreach ( wp_roles()->role_objects as $role ) {
		$role->remove_cap( 'uncoder_wb_use_mcp' );
	}

	// Generated files (CSS, self-hosted fonts, form uploads).
	$upload = wp_upload_dir( null, false );
	if ( empty( $upload['error'] ) ) {
		uncoder_wb_uninstall_rmdir( trailingslashit( $upload['basedir'] ) . 'uncoder' );
	}

	wp_clear_scheduled_hook( 'uncoder_wb_daily' );
}

/**
 * Deletes a directory tree inside uploads.
 */
function uncoder_wb_uninstall_rmdir( string $dir ): void {
	$upload = wp_upload_dir( null, false );
	$base   = wp_normalize_path( trailingslashit( $upload['basedir'] ) );
	$dir    = wp_normalize_path( $dir );
	if ( 0 !== strpos( $dir, $base ) || ! is_dir( $dir ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		} else {
			wp_delete_file( $item->getPathname() );
		}
	}
	rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $uncoder_wb_site ) {
		switch_to_blog( (int) $uncoder_wb_site );
		uncoder_wb_uninstall_site();
		restore_current_blog();
	}
} else {
	uncoder_wb_uninstall_site();
}

// Safe mode's must-use plugin (Settings → Tools) never outlives Uncoder.
if ( defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/uncoder-safe-mode.php' ) ) {
	wp_delete_file( WPMU_PLUGIN_DIR . '/uncoder-safe-mode.php' );
}
