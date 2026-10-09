<?php
/**
 * Updates from GitHub releases (https://github.com/UncoderBuilder/uncoder).
 *
 * The plugin header's "Update URI: https://github.com/UncoderBuilder/uncoder" makes WordPress ask the
 * update_plugins_github.com filter (and never WordPress.org) for a newer version. Each release carries the
 * installable zip as an asset; only assets of this repository's release downloads are ever offered.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Updater {

	public const REPO = 'UncoderBuilder/uncoder';

	private const CACHE = 'uncoder_wb_releases';

	public function register(): void {
		add_filter( 'update_plugins_github.com', array( $this, 'offer' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'details' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'forget' ), 10, 2 );
	}

	/**
	 * Published releases, newest first: version, package (zip URL), page, date and notes.
	 *
	 * @return array<int, array{version: string, package: string, page: string, date: string, notes: string}>|WP_Error
	 */
	public static function releases( bool $fresh = false ) {
		$cached = $fresh ? false : get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			if ( isset( $cached['error'] ) ) {
				return new WP_Error( 'uncoder_releases', (string) $cached['error'] );
			}
			return (array) ( $cached['list'] ?? array() );
		}
		$response = wp_safe_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases?per_page=30',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Uncoder/' . UNCODER_WB_VERSION . '; ' . home_url( '/' ),
				),
			)
		);
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $data ) ) {
			$message = is_wp_error( $response ) ? $response->get_error_message() : sprintf( 'GitHub answered %d.', $code );
			// Remember a failure briefly, so a GitHub outage or rate limit does not slow every admin page.
			set_site_transient( self::CACHE, array( 'error' => $message ), HOUR_IN_SECONDS );
			return new WP_Error( 'uncoder_releases', $message );
		}
		$list = array();
		foreach ( $data as $release ) {
			if ( ! is_array( $release ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
				continue;
			}
			$version = ltrim( (string) ( $release['tag_name'] ?? '' ), 'vV' );
			$package = self::package( (array) ( $release['assets'] ?? array() ) );
			if ( ! preg_match( '/^\d+(\.\d+){1,3}$/', $version ) || '' === $package ) {
				continue;
			}
			$list[] = array(
				'version' => $version,
				'package' => $package,
				'page'    => esc_url_raw( (string) ( $release['html_url'] ?? 'https://github.com/' . self::REPO . '/releases' ) ),
				'date'    => (string) ( $release['published_at'] ?? '' ),
				'notes'   => (string) ( $release['body'] ?? '' ),
			);
		}
		usort( $list, static fn( $a, $b ) => version_compare( $b['version'], $a['version'] ) );
		set_site_transient( self::CACHE, array( 'list' => $list ), 6 * HOUR_IN_SECONDS );
		return $list;
	}

	/** The plugin zip of a release: an asset of this repository's release downloads, named uncoder*.zip. */
	private static function package( array $assets ): string {
		foreach ( $assets as $asset ) {
			$url  = (string) ( $asset['browser_download_url'] ?? '' );
			$name = (string) ( $asset['name'] ?? '' );
			if ( preg_match( '/^uncoder(-\d+(\.\d+){1,3})?\.zip$/', $name )
				&& 'https' === wp_parse_url( $url, PHP_URL_SCHEME )
				&& 'github.com' === wp_parse_url( $url, PHP_URL_HOST )
				&& 0 === strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' . self::REPO . '/releases/download/' ) ) {
				return $url;
			}
		}
		return '';
	}

	/** Earlier releases than this one: [version => package URL], newest first (for the rollback tool). */
	public static function earlier(): array|WP_Error {
		$list = self::releases();
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		$out = array();
		foreach ( $list as $release ) {
			if ( version_compare( $release['version'], UNCODER_WB_VERSION, '<' ) ) {
				$out[ $release['version'] ] = $release['package'];
			}
		}
		return array_slice( $out, 0, 10, true );
	}

	/**
	 * A development copy (linked folder, Windows junction or git checkout) is never replaced by an update or a
	 * rollback. PHP resolves links in __FILE__, so UNCODER_WB_PATH is where the files really are: when that is not
	 * the plugin's folder inside the (resolved) plugins directory, the folder is a link to somewhere else.
	 */
	public static function dev_copy(): bool {
		$real     = strtolower( wp_normalize_path( untrailingslashit( UNCODER_WB_PATH ) ) );
		$plugins  = realpath( WP_PLUGIN_DIR );
		$expected = strtolower( wp_normalize_path( ( false !== $plugins ? $plugins : WP_PLUGIN_DIR ) . '/' . dirname( UNCODER_WB_BASENAME ) ) );
		return $real !== $expected || file_exists( UNCODER_WB_PATH . '.git' ) || file_exists( dirname( UNCODER_WB_PATH ) . '/.git' );
	}

	/**
	 * update_plugins_github.com: the newest release when it is newer than this version.
	 *
	 * @param array|false $update      Update data from an earlier filter, or false.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public function offer( $update, $plugin_data, $plugin_file ) {
		if ( UNCODER_WB_BASENAME !== $plugin_file || self::dev_copy() ) {
			return $update;
		}
		// "Check again" on Dashboard → Updates asks GitHub now instead of using the cached list.
		$fresh = is_admin() && ! empty( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only refresh.
		$list  = self::releases( $fresh );
		if ( is_wp_error( $list ) || ! $list || ! version_compare( $list[0]['version'], UNCODER_WB_VERSION, '>' ) ) {
			return $update;
		}
		return array(
			'id'           => 'github.com/' . self::REPO,
			'slug'         => 'uncoder',
			'version'      => $list[0]['version'],
			'url'          => $list[0]['page'],
			'package'      => $list[0]['package'],
			'requires'     => '6.6',
			'requires_php' => UNCODER_WB_MIN_PHP,
			'icons'        => array( 'svg' => \Uncoder\Builder\Core\Brand::asset( 'uncoder-app-icon.svg' ) ),
		);
	}

	/**
	 * plugins_api: the "View details" box for Uncoder, from the GitHub releases.
	 *
	 * @param false|object|array $result Result so far.
	 * @param string             $action API action.
	 * @param object             $args   Arguments (slug…).
	 * @return false|object|array
	 */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'uncoder' !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$list = self::releases();
		if ( is_wp_error( $list ) || ! $list ) {
			return $result;
		}
		$changelog = '';
		foreach ( array_slice( $list, 0, 5 ) as $release ) {
			$changelog .= '<h4>' . esc_html( $release['version'] ) . '</h4>' . wpautop( esc_html( $release['notes'] ) );
		}
		$name = \Uncoder\Builder\Core\Brand::name();
		$home = \Uncoder\Builder\Core\Brand::url();
		if ( \Uncoder\Builder\Site\White_Label::active() ) {
			// White-label: the "View details" popup names the agency's builder.
			$changelog = (string) \Uncoder\Builder\Site\White_Label::rename( $changelog );
		}
		return (object) array(
			'name'          => $name,
			'slug'          => 'uncoder',
			'version'       => $list[0]['version'],
			'author'        => '<a href="' . esc_url( $home ) . '">' . esc_html( $name ) . '</a>',
			'homepage'      => $home,
			'requires'      => '6.6',
			'requires_php'  => UNCODER_WB_MIN_PHP,
			'last_updated'  => $list[0]['date'],
			'download_link' => $list[0]['package'],
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'A free visual page and theme builder for WordPress, with an MCP server so your AI can build with it.', 'uncoder' ) . '</p>',
				'changelog'   => $changelog,
			),
		);
	}

	/**
	 * Clears the cached releases after Uncoder itself was updated.
	 *
	 * @param object $upgrader Upgrader.
	 * @param array  $options  Hook extra (type, action, plugins).
	 */
	public function forget( $upgrader, $options ): void {
		if ( 'plugin' === ( $options['type'] ?? '' ) && in_array( UNCODER_WB_BASENAME, (array) ( $options['plugins'] ?? array() ), true ) ) {
			delete_site_transient( self::CACHE );
		}
	}
}
