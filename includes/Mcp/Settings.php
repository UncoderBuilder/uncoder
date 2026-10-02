<?php
/**
 * MCP settings.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in the `uncoder_wb_mcp_settings` option.
 */
final class Settings {

	public const OPTION = 'uncoder_wb_mcp_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'             => true,
			'allowed_roles'       => array( 'administrator' ),
			'rate_limit'          => 120,
			'allow_registration'  => true,
			'allowed_origins'     => array(),
			'snapshots'           => true,
			'confirm_destructive' => true,
			'links'               => true,
			'image_search'        => true,
			'access_ttl'          => HOUR_IN_SECONDS,
			'refresh_ttl'         => 30 * DAY_IN_SECONDS,
			'log_days'            => 30,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function save( array $input ): array {
		$current = self::all();
		$roles   = array_keys( wp_roles()->roles );
		$out     = $current;
		foreach ( array( 'enabled', 'allow_registration', 'snapshots', 'confirm_destructive', 'links', 'image_search' ) as $bool ) {
			if ( array_key_exists( $bool, $input ) ) {
				$out[ $bool ] = (bool) $input[ $bool ];
			}
		}
		if ( isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
			$out['allowed_roles'] = array_values( array_intersect( array_map( 'sanitize_key', $input['allowed_roles'] ), $roles ) );
			if ( ! in_array( 'administrator', $out['allowed_roles'], true ) ) {
				$out['allowed_roles'][] = 'administrator';
			}
		}
		if ( isset( $input['rate_limit'] ) ) {
			$out['rate_limit'] = max( 10, min( 2000, absint( $input['rate_limit'] ) ) );
		}
		if ( isset( $input['log_days'] ) ) {
			$out['log_days'] = max( 1, min( 365, absint( $input['log_days'] ) ) );
		}
		if ( isset( $input['allowed_origins'] ) && is_array( $input['allowed_origins'] ) ) {
			$origins = array();
			foreach ( $input['allowed_origins'] as $origin ) {
				$origin = esc_url_raw( trim( (string) $origin ), array( 'http', 'https' ) );
				if ( $origin ) {
					$origins[] = untrailingslashit( $origin );
				}
			}
			$out['allowed_origins'] = array_values( array_unique( $origins ) );
		}
		update_option( self::OPTION, $out, false );
		self::sync_role_caps( $out['allowed_roles'] );
		return $out;
	}

	/**
	 * Keeps the uncoder_wb_use_mcp capability in step with the allowed roles.
	 *
	 * @param string[] $allowed Role slugs.
	 */
	public static function sync_role_caps( array $allowed ): void {
		foreach ( wp_roles()->role_objects as $slug => $role ) {
			if ( in_array( $slug, $allowed, true ) ) {
				$role->add_cap( \Uncoder\Builder\Install::MCP_CAP );
			} else {
				$role->remove_cap( \Uncoder\Builder\Install::MCP_CAP );
			}
		}
	}
}
