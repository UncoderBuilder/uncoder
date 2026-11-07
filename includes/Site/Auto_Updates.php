<?php
/**
 * Automatic updates (Settings → General → Updates): the plugin, the Uncoder theme and the Pro module.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Rest\Settings_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin and theme switches are WordPress's own (the auto_update_plugins / auto_update_themes site options), so they
 * always match the Plugins and Themes screens. WordPress installs those updates from its twice-daily check. The Pro
 * module has its own switch in the plugin settings (on by default): Pro_Module checks for its new versions with the
 * licence.
 */
final class Auto_Updates {

	/** Key in the plugin settings (uncoder_wb_settings): update the Pro module automatically. */
	public const PRO_KEY = 'pro_auto_update';

	/** The Uncoder theme's folder name. */
	private const THEME = 'uncoder';

	/**
	 * @return array{plugin:bool,theme:bool,pro:bool,themeInstalled:bool,canPlugin:bool,canTheme:bool,devCopy:bool,blocked:bool}
	 */
	public static function get(): array {
		$plugins = (array) get_site_option( 'auto_update_plugins', array() );
		$themes  = (array) get_site_option( 'auto_update_themes', array() );
		$theme   = wp_get_theme( self::THEME )->exists();
		return array(
			'plugin'         => in_array( UNCODER_WB_BASENAME, $plugins, true ),
			'theme'          => $theme && in_array( self::THEME, $themes, true ),
			'pro'            => self::pro(),
			'themeInstalled' => $theme,
			'canPlugin'      => self::can( 'plugin' ),
			'canTheme'       => $theme && self::can( 'theme' ),
			// A linked or git copy (a developer's) is never updated, whatever the switch says.
			'devCopy'        => Updater::dev_copy(),
			// WordPress's automatic updates are turned off for the whole site (wp-config constant or a filter).
			'blocked'        => ! self::enabled( 'plugin' ),
		);
	}

	/** Update the Pro module automatically (on unless switched off). */
	public static function pro(): bool {
		$stored = get_option( Settings_Controller::OPTION, array() );
		return ! is_array( $stored ) || ! array_key_exists( self::PRO_KEY, $stored ) || ! empty( $stored[ self::PRO_KEY ] );
	}

	/**
	 * Applies the switches the current user may change. The Pro switch goes into $stored (the caller saves it).
	 *
	 * @param array<string,mixed> $in     { plugin?: bool, theme?: bool, pro?: bool }.
	 * @param array<string,mixed> $stored Plugin settings, changed in place.
	 */
	public static function save( array $in, array &$stored ): void {
		if ( array_key_exists( 'plugin', $in ) && self::can( 'plugin' ) ) {
			self::toggle( 'auto_update_plugins', UNCODER_WB_BASENAME, (bool) $in['plugin'] );
		}
		if ( array_key_exists( 'theme', $in ) && wp_get_theme( self::THEME )->exists() && self::can( 'theme' ) ) {
			self::toggle( 'auto_update_themes', self::THEME, (bool) $in['theme'] );
		}
		if ( array_key_exists( 'pro', $in ) ) {
			$stored[ self::PRO_KEY ] = (bool) $in['pro'];
		}
	}

	private static function toggle( string $option, string $item, bool $on ): void {
		$list = array_values( array_filter( (array) get_site_option( $option, array() ), 'is_string' ) );
		$has  = in_array( $item, $list, true );
		if ( $on && ! $has ) {
			$list[] = $item;
		} elseif ( ! $on && $has ) {
			$list = array_values( array_diff( $list, array( $item ) ) );
		} else {
			return;
		}
		update_site_option( $option, $list );
	}

	/** Same rules as the Plugins / Themes screens: the right capability (network admins on a multisite). */
	private static function can( string $type ): bool {
		if ( ! self::enabled( $type ) ) {
			return false;
		}
		if ( is_multisite() ) {
			return current_user_can( 'plugin' === $type ? 'manage_network_plugins' : 'manage_network_themes' );
		}
		return current_user_can( 'plugin' === $type ? 'update_plugins' : 'update_themes' );
	}

	private static function enabled( string $type ): bool {
		if ( ! function_exists( 'wp_is_auto_update_enabled_for_type' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		return wp_is_auto_update_enabled_for_type( $type );
	}
}
