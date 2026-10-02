<?php
/**
 * Clearing caches.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Plugin;
use Uncoder\Builder\Theme\Theme_Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Rebuilds Uncoder's generated CSS (kit.css and every document stylesheet) and the template index, then purges
 * the common page-cache plugins and the object cache. Used by the admin bar's Clear Cache and the clear_cache AI tool.
 */
final class Cache {

	/**
	 * @return array{css_regenerated:int, purged:string[], object_cache:bool}
	 */
	public static function clear(): array {
		$plugin = Plugin::instance();
		$plugin->kit()->write_css();
		$pages = $plugin->documents()->regenerate_all();
		if ( Theme_Builder::instance() ) {
			Theme_Builder::instance()->rebuild_index();
		}
		$purged = array();
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$purged[] = 'WP Rocket';
		}
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$purged[] = 'LiteSpeed';
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$purged[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$purged[] = 'WP Super Cache';
		}
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
			$purged[] = 'WP Fastest Cache';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$purged[] = 'SiteGround';
		}
		if ( class_exists( '\autoptimizeCache' ) && method_exists( '\autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
			$purged[] = 'Autoptimize';
		}
		wp_cache_flush();
		/**
		 * Fires when Uncoder clears caches (admin bar Clear Cache, or an AI client): hook in custom cache purges.
		 */
		do_action( 'uncoder_wb/clear_cache' );
		return array(
			'css_regenerated' => $pages,
			'purged'          => $purged,
			'object_cache'    => true,
		);
	}
}
