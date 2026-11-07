<?php
/**
 * The Pro module: Uncoder's paid features, delivered with a licence instead of with the public plugin.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Licence;

use Uncoder\Builder\Autoloader;
use Uncoder\Builder\Rest\Rest;
use Uncoder\Builder\Site\Auto_Updates;
use Uncoder\Builder\Site\White_Label;
use WP_Error;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * The paid features (Make it yours, premium sections, and for Agency: white-label and handoff settings, branded
 * reports, the cloud library) live in a package of their own, the Pro module. The content server
 * (cloud.uncoderbuilder.com) gives it to sites with an active licence: the Pro edition, or the Agency edition, which
 * holds both. It is installed inside this plugin, in pro/, never as a plugin of its own.
 *
 * Install and update:
 * 1. Ask the content server (POST pro/check, with the licence token) for this site's edition. The answer carries the
 *    version, a 15-minute download link, the zip's sha256 and the signature of its manifest.
 * 2. Download the zip; its sha256 must match.
 * 3. Unpack it in a private folder. Its manifest must:
 *    - carry a valid signature, made offline with the key named by kid (KEYS: the server never holds that key, so a
 *      hacked server cannot push code to sites);
 *    - name this edition and version, and a plugin version this one meets;
 *    - list every file with its sha256. Every file must match, and nothing else may be there.
 * 4. Swap the folders: the old pro/ goes only once the new one is in place.
 *
 * When:
 * - right after a licence is activated;
 * - on the daily licence check (when "Update the Pro module automatically" is on, Settings → General → Updates);
 * - on an admin visit when the module is missing.
 * An expired licence keeps the installed module (live sites never lose a feature) but gets no updates.
 *
 * The plugin's own updates replace its folder (an update, an uploaded zip, a rollback): upgrader_source_selection parks
 * pro/ aside before the old folder goes, upgrader_post_install brings it back; if an update died half-way, the next
 * request brings it back, and a module that went missing anyway is downloaded again on the next admin visit.
 *
 * A development copy (pro/ is a link to the source, or holds a .dev file) is never written to.
 *
 * REST (licence managers): GET uncoder/v1/pro-module · POST uncoder/v1/pro-module/sync.
 */
final class Pro_Module {

	/** Ed25519 public keys of the offline package signing key(s), by kid. */
	public const KEYS = array(
		'p1' => 'oebujnKrb6TT0byAI25Nkc2stg9f3BLFykvzMRxCx7w=',
	);

	/** What the last check found: { checked, available: { edition, version }, error, attempt }. */
	private const STATE = 'uncoder_wb_pro_module';
	private const NAME  = 'uncoder-pro';

	/** A one-off cron event: install or update in the background (never while someone waits for a page). */
	private const INSTALL_EVENT = 'uncoder_wb_pro_install';

	/** Limits for a package: the whole download, one file, all files unpacked. */
	private const MAX_DOWNLOAD = 20 * MB_IN_BYTES;
	private const MAX_FILE     = 5 * MB_IN_BYTES;
	private const MAX_TOTAL    = 30 * MB_IN_BYTES;

	public function register(): void {
		if ( ! Licence::enabled() ) {
			return;
		}
		add_filter( 'upgrader_source_selection', array( self::class, 'park' ), 10, 4 );
		add_filter( 'upgrader_post_install', array( self::class, 'unpark' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'uncoder_wb/licence/activated', array( self::class, 'on_activated' ) );
		add_action( Licence::CRON, array( self::class, 'daily' ), 20 );
		add_action( self::INSTALL_EVENT, array( self::class, 'daily' ) );
		add_action( 'admin_init', array( self::class, 'maybe_install' ) );
		// After the plugin itself was updated, the module is checked right away (a new plugin may need a new module).
		add_action( 'upgrader_process_complete', array( self::class, 'after_update' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ Loading */

	/** pro/ inside the plugin's folder. */
	public static function dir(): string {
		return UNCODER_WB_PATH . 'pro/';
	}

	/** Where an update parks pro/ while it replaces the plugin's folder. */
	private static function parked(): string {
		return WP_CONTENT_DIR . '/upgrade/uncoder-pro-keep';
	}

	/**
	 * Loads the Pro module, if this site has a compatible one (Plugin, before its modules are made).
	 */
	public static function load(): void {
		self::recover();
		$file = self::dir() . 'uncoder-pro.php';
		if ( ! is_readable( $file ) ) {
			return;
		}
		$m = self::manifest();
		if ( $m && version_compare( UNCODER_WB_VERSION, (string) ( $m['requires_core'] ?? '0' ), '<' ) ) {
			return; // Made for a newer plugin: it waits for the plugin's update.
		}
		Autoloader::enable_pro();
		require_once $file;
	}

	/** @return array<string,mixed>|null The installed module's manifest. */
	public static function manifest(): ?array {
		$file = self::dir() . 'manifest.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$m = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $m ) ? $m : null;
	}

	/** Whether pro/ is a development copy (a link to the source, or marked with .dev): never replaced. */
	public static function dev(): bool {
		if ( \Uncoder\Builder\Site\Updater::dev_copy() ) {
			return true; // The plugin itself is a linked or git copy: nothing is written into it.
		}
		$dir = rtrim( self::dir(), '/' );
		if ( ! is_dir( $dir ) ) {
			return false;
		}
		if ( file_exists( $dir . '/.dev' ) || is_link( $dir ) ) {
			return true;
		}
		// A Windows junction is not a link to PHP: its real path is somewhere else.
		$real = realpath( $dir );
		return false !== $real && strtolower( wp_normalize_path( $real ) ) !== strtolower( wp_normalize_path( (string) realpath( UNCODER_WB_PATH ) . '/pro' ) );
	}

	/**
	 * The installed module: version and edition ('' when none).
	 *
	 * @return array{version:string,edition:string,dev:bool}
	 */
	public static function installed(): array {
		$m = self::manifest();
		if ( self::dev() ) {
			return array( 'version' => 'dev', 'edition' => 'agency', 'dev' => true );
		}
		return array(
			'version' => is_readable( self::dir() . 'uncoder-pro.php' ) ? (string) ( $m['version'] ?? '' ) : '',
			'edition' => (string) ( $m['edition'] ?? '' ),
			'dev'     => false,
		);
	}

	/* ------------------------------------------------------------------ When */

	public static function on_activated(): void {
		self::sync( true );
	}

	/** The daily licence check: install a missing module, update an installed one when allowed. */
	public static function daily(): void {
		$have = self::installed();
		self::sync( '' === $have['version'] || Auto_Updates::pro() );
	}

	/**
	 * An admin visit on a licensed site without its module (a failed install, a site moved over, the first visit after
	 * updating from a plugin that had the paid code built in): installs it in the background, at most every 10 minutes.
	 */
	public static function maybe_install(): void {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || ( is_multisite() && ! is_super_admin() ) || ! Licence::active() || '' !== self::installed()['version'] || self::dev() ) {
			return;
		}
		if ( time() - (int) ( self::state()['attempt'] ?? 0 ) < 10 * MINUTE_IN_SECONDS || true !== self::can_install() ) {
			return;
		}
		self::remember( array( 'attempt' => time() ) );
		self::schedule_now();
	}

	/**
	 * upgrader_process_complete: the plugin was updated, so check its module now (in the background).
	 *
	 * @param mixed               $upgrader Upgrader.
	 * @param array<string,mixed> $options  What was updated.
	 */
	public static function after_update( $upgrader, $options ): void {
		if ( 'plugin' === ( $options['type'] ?? '' ) && in_array( UNCODER_WB_BASENAME, (array) ( $options['plugins'] ?? array() ), true ) && Licence::active() ) {
			self::schedule_now();
		}
	}

	private static function schedule_now(): void {
		if ( ! wp_next_scheduled( self::INSTALL_EVENT ) ) {
			wp_schedule_single_event( time(), self::INSTALL_EVENT );
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/** Whether this server can install the module at all (checked before anything is downloaded). */
	private static function can_install() {
		if ( ! class_exists( '\ZipArchive' ) || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return new WP_Error( 'uncoder_pro_server', __( 'This server cannot install the Pro module (PHP needs the zip and sodium extensions). Ask your host to turn them on.', 'uncoder' ) );
		}
		if ( ! wp_is_writable( UNCODER_WB_PATH ) ) {
			return new WP_Error( 'uncoder_pro_writable', __( 'WordPress cannot write to the Uncoder plugin folder, so the Pro module cannot be installed. Ask your host to let WordPress update plugins.', 'uncoder' ) );
		}
		return true;
	}

	/* ------------------------------------------------------------------ Checking and installing */

	/** @return array<string,mixed> */
	private static function state(): array {
		$s = get_option( self::STATE, array() );
		return is_array( $s ) ? $s : array();
	}

	/** @param array<string,mixed> $patch Fields to change. */
	private static function remember( array $patch ): void {
		update_option( self::STATE, array_merge( self::state(), $patch ), false );
	}

	/**
	 * Asks the content server for this site's module and installs or updates it when $install and needed.
	 *
	 * @return true|WP_Error
	 */
	public static function sync( bool $install = true ) {
		if ( self::dev() ) {
			return new WP_Error( 'uncoder_pro_dev', __( 'This is a development copy of the Pro module: it is never replaced.', 'uncoder' ), array( 'status' => 409 ) );
		}
		if ( ! Licence::active() ) {
			return new WP_Error( 'uncoder_pro_licence', __( 'Activate a Pro or Agency licence first.', 'uncoder' ), array( 'status' => 403 ) );
		}
		$ready = self::can_install();
		if ( is_wp_error( $ready ) ) {
			return self::fail( $ready->get_error_code(), $ready->get_error_message() );
		}
		self::remember( array( 'attempt' => time() ) );
		$have = self::installed();
		$ask  = static fn() => Licence::content_post(
			'pro/check',
			array_merge(
				array(
					'core' => UNCODER_WB_VERSION,
					'have' => $have['version'],
				),
				Licence::content_params()
			),
			20
		);
		$res  = $ask();
		$body = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		// The token was older than the content server takes: check the licence now and ask once more.
		if ( is_array( $body ) && 'stale_token' === ( $body['code'] ?? '' ) ) {
			Licence::refresh();
			$res  = $ask();
			$body = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		}
		if ( is_wp_error( $res ) ) {
			return self::fail( 'uncoder_pro_offline', __( 'The Pro module server could not be reached. It will try again later.', 'uncoder' ) );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || ! is_array( $body ) || empty( $body['version'] ) ) {
			$code = is_array( $body ) ? (string) ( $body['code'] ?? '' ) : '';
			$map  = array(
				'needs_licence' => __( 'The Pro module comes with Pro and Agency licences.', 'uncoder' ),
				'expired'       => __( 'Your licence has expired: the Pro module you have keeps working, renew to get its updates.', 'uncoder' ),
				'disabled'      => __( 'Your licence is no longer active.', 'uncoder' ),
				'not_activated' => __( 'This site is not activated on your licence. Activate it again under Settings → Licence.', 'uncoder' ),
				'needs_core'    => __( 'The Pro module needs a newer version of Uncoder. Update the plugin first.', 'uncoder' ),
			);
			return self::fail( 'uncoder_pro_' . ( '' !== $code ? $code : 'failed' ), $map[ $code ] ?? __( 'The Pro module could not be checked. It will try again later.', 'uncoder' ) );
		}
		$offer = array(
			'edition'       => (string) $body['edition'],
			'version'       => (string) $body['version'],
			'requires_core' => (string) ( $body['requires_core'] ?? '0' ),
			'sha256'        => (string) ( $body['sha256'] ?? '' ),
			'url'           => (string) ( $body['url'] ?? '' ),
		);
		self::remember(
			array(
				'checked'   => time(),
				'available' => array( 'edition' => $offer['edition'], 'version' => $offer['version'] ),
				'error'     => null,
			)
		);
		// Never an older module than the one installed. A changed plan swaps the edition (same or newer version), except
		// on a multisite network, where the module is shared: there it only ever goes up (Agency covers Pro).
		$newer   = '' === $have['version'] || version_compare( $offer['version'], $have['version'], '>' );
		$swap    = '' !== $have['version'] && $have['edition'] !== $offer['edition'] && version_compare( $offer['version'], $have['version'], '>=' ) && ! ( is_multisite() && 'agency' === $have['edition'] );
		$needed  = $newer || $swap;
		if ( $needed && is_multisite() && 'agency' === $have['edition'] && 'pro' === $offer['edition'] ) {
			$needed = false; // A Pro site on a network whose module is the Agency edition already has everything.
		}
		if ( ! $needed || ! $install ) {
			return true;
		}
		$done = self::install( $offer );
		if ( is_wp_error( $done ) ) {
			return self::fail( $done->get_error_code(), $done->get_error_message() );
		}
		self::remember( array( 'installed' => time() ) );
		return true;
	}

	private static function fail( string $code, string $message ): WP_Error {
		self::remember( array( 'error' => $message ) );
		return new WP_Error( $code, $message, array( 'status' => 502 ) );
	}

	/**
	 * Downloads, checks and puts the offered module in place. Nothing from the package is written to disk before its
	 * signature, its file list and every file's hash have been checked inside the zip.
	 *
	 * @param array<string,string> $offer Edition, version, requires_core, sha256, url.
	 * @return true|WP_Error
	 */
	private static function install( array $offer ) {
		$ready = self::can_install();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		if ( version_compare( UNCODER_WB_VERSION, $offer['requires_core'], '<' ) ) {
			return new WP_Error( 'uncoder_pro_needs_core', __( 'The Pro module needs a newer version of Uncoder. Update the plugin first.', 'uncoder' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$zip = wp_tempnam( 'uncoder-pro.zip' );
		$res = wp_safe_remote_get(
			$offer['url'],
			array(
				'timeout'             => 120,
				'stream'              => true,
				'filename'            => $zip,
				'limit_response_size' => self::MAX_DOWNLOAD,
			)
		);
		$work = WP_CONTENT_DIR . '/upgrade/uncoder-pro-' . strtolower( wp_generate_password( 10, false, false ) );
		try {
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				return new WP_Error( 'uncoder_pro_download', __( 'The Pro module download stopped before the end. It will try again later.', 'uncoder' ) );
			}
			if ( '' === $offer['sha256'] || ! hash_equals( $offer['sha256'], (string) hash_file( 'sha256', $zip ) ) ) {
				return new WP_Error( 'uncoder_pro_damaged', __( 'The Pro module was damaged on the way. It will try again later.', 'uncoder' ) );
			}
			$files = self::verify( $zip, $offer );
			if ( is_wp_error( $files ) ) {
				return $files;
			}
			$z = new \ZipArchive();
			if ( true !== $z->open( $zip ) ) {
				return new WP_Error( 'uncoder_pro_damaged', __( 'The Pro module could not be opened.', 'uncoder' ) );
			}
			wp_mkdir_p( $work );
			$ok = $z->extractTo( $work, array_merge( $files, array( 'manifest.json', 'manifest.sig' ) ) );
			$z->close();
			if ( ! $ok ) {
				return new WP_Error( 'uncoder_pro_unpack', __( 'The Pro module could not be unpacked.', 'uncoder' ) );
			}
			return self::swap( $work );
		} finally {
			wp_delete_file( $zip );
			if ( is_dir( $work ) ) {
				self::rmdir( $work );
			}
		}
	}

	/**
	 * Checks the package inside the zip: the manifest's signature (KEYS: a key the server never holds), its name, edition
	 * and version, the plugin version it needs, that the zip holds exactly the listed files (plus their folders), each
	 * within the size limits and with the listed sha256.
	 *
	 * @param array<string,string> $offer What the server offered.
	 * @return string[]|WP_Error The files to unpack.
	 */
	private static function verify( string $zip, array $offer ) {
		$bad = new WP_Error( 'uncoder_pro_signature', __( 'The Pro module did not pass its signature check, so it was not installed.', 'uncoder' ) );
		$z   = new \ZipArchive();
		if ( true !== $z->open( $zip ) ) {
			return new WP_Error( 'uncoder_pro_damaged', __( 'The Pro module could not be opened.', 'uncoder' ) );
		}
		try {
			if ( $z->numFiles > 2000 ) {
				return $bad;
			}
			$raw = $z->getFromName( 'manifest.json', self::MAX_FILE );
			$sig = json_decode( (string) $z->getFromName( 'manifest.sig', 4096 ), true );
			if ( ! is_string( $raw ) || '' === $raw || ! is_array( $sig ) || ! isset( self::KEYS[ (string) ( $sig['kid'] ?? '' ) ] ) ) {
				return $bad;
			}
			$key       = base64_decode( self::KEYS[ (string) $sig['kid'] ], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$signature = base64_decode( (string) ( $sig['sig'] ?? '' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( ! $key || ! $signature || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $signature ) || ! sodium_crypto_sign_verify_detached( $signature, $raw, $key ) ) {
				return $bad;
			}
			$m = json_decode( $raw, true );
			if ( ! is_array( $m ) || self::NAME !== ( $m['name'] ?? '' ) || $offer['edition'] !== ( $m['edition'] ?? '' ) || $offer['version'] !== ( $m['version'] ?? '' ) || ! is_array( $m['files'] ?? null ) || ! $m['files'] ) {
				return $bad;
			}
			if ( version_compare( UNCODER_WB_VERSION, (string) ( $m['requires_core'] ?? '0' ), '<' ) ) {
				return new WP_Error( 'uncoder_pro_needs_core', __( 'The Pro module needs a newer version of Uncoder. Update the plugin first.', 'uncoder' ) );
			}
			$files   = array_map( 'strval', array_keys( $m['files'] ) );
			$allowed = array_flip( array_merge( $files, array( 'manifest.json', 'manifest.sig' ) ) );
			foreach ( $files as $f ) {
				if ( '' === $f || false !== strpos( $f, '..' ) || false !== strpos( $f, '\\' ) || '/' === $f[0] || preg_match( '/^[a-z]:/i', $f ) ) {
					return $bad;
				}
				for ( $d = dirname( $f ); '.' !== $d && '' !== $d; $d = dirname( $d ) ) {
					$allowed[ $d . '/' ] = true; // the folders the files sit in
				}
			}
			// The zip holds the listed files and their folders, nothing else, within the limits.
			$total = 0;
			for ( $i = 0; $i < $z->numFiles; $i++ ) {
				$stat = $z->statIndex( $i );
				$name = (string) ( $stat['name'] ?? '' );
				if ( ! isset( $allowed[ $name ] ) || (int) $stat['size'] > self::MAX_FILE ) {
					return $bad;
				}
				$total += (int) $stat['size'];
			}
			if ( $total > self::MAX_TOTAL ) {
				return $bad;
			}
			foreach ( $m['files'] as $path => $hash ) {
				$data = $z->getFromName( (string) $path, self::MAX_FILE );
				if ( ! is_string( $data ) || ! hash_equals( (string) $hash, hash( 'sha256', $data ) ) ) {
					return $bad;
				}
			}
			return $files;
		} finally {
			$z->close();
		}
	}

	/** Puts the checked copy in place of pro/ (the old one goes only once the new one is there). */
	private static function swap( string $work ) {
		$dest = rtrim( self::dir(), '/' );
		$old  = $dest . '-old-' . time();
		if ( is_dir( $dest ) && ! @rename( $dest, $old ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
			return new WP_Error( 'uncoder_pro_writable', __( 'The Pro module could not be replaced: WordPress cannot change the Uncoder plugin folder.', 'uncoder' ) );
		}
		if ( ! @rename( $work, $dest ) && ! self::copy( $work, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
			if ( is_dir( $dest ) ) {
				self::rmdir( $dest ); // A half-made copy goes before the old module comes back.
			}
			if ( is_dir( $old ) ) {
				@rename( $old, $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
			}
			return new WP_Error( 'uncoder_pro_writable', __( 'The Pro module could not be put in place: WordPress cannot write to the Uncoder plugin folder.', 'uncoder' ) );
		}
		if ( is_dir( $old ) ) {
			self::rmdir( $old );
		}
		if ( function_exists( 'opcache_reset' ) && ini_get( 'opcache.enable' ) ) {
			@opcache_reset(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the next request loads the new files.
		}
		return true;
	}

	/* ------------------------------------------------------------------ Plugin updates keep the module */

	/**
	 * upgrader_source_selection: a package of this plugin is about to replace its folder (an update, "Replace current
	 * with uploaded", a rollback); pro/ waits outside it. Runs before WordPress clears the old folder.
	 *
	 * @param string|\WP_Error $source        The unpacked package's folder.
	 * @param string           $remote_source Unused.
	 * @param mixed            $upgrader      Unused.
	 * @param array            $hook_extra    Unused.
	 * @return string|\WP_Error
	 */
	public static function park( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		if ( is_string( $source ) && self::ours( $source ) && is_dir( self::dir() ) && ! self::dev() ) {
			$keep = self::parked();
			if ( is_dir( $keep ) ) {
				self::rmdir( $keep );
			}
			wp_mkdir_p( dirname( $keep ) );
			if ( @rename( rtrim( self::dir(), '/' ), $keep ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
				file_put_contents( $keep . '/.parked', (string) time() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		return $source;
	}

	/**
	 * upgrader_post_install: the new copy of this plugin is in place; pro/ goes back into it.
	 *
	 * @param mixed               $response   Earlier answer.
	 * @param array<string,mixed> $hook_extra Unused.
	 * @param array<string,mixed> $result     Where it went.
	 * @return mixed
	 */
	public static function unpark( $response, $hook_extra, $result ) {
		$keep = self::parked();
		$dest = (string) ( $result['destination'] ?? '' );
		if ( is_dir( $keep ) && '' !== $dest && self::ours( $dest ) ) {
			$target = trailingslashit( $dest ) . 'pro';
			if ( ! is_dir( $target ) && @rename( $keep, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
				wp_delete_file( $target . '/.parked' );
			}
		}
		return $response;
	}

	/** Whether a folder is a copy of this plugin (same folder name, with its main file). */
	private static function ours( string $dir ): bool {
		$dir = untrailingslashit( wp_normalize_path( $dir ) );
		return basename( $dir ) === dirname( UNCODER_WB_BASENAME ) && is_readable( $dir . '/' . basename( UNCODER_WB_FILE ) );
	}

	/** A module parked by an update that never finished goes back (after a few minutes, so a running update is not disturbed). */
	private static function recover(): void {
		$keep = self::parked();
		if ( ! is_dir( $keep ) || is_dir( self::dir() ) ) {
			return;
		}
		$since = is_readable( $keep . '/.parked' ) ? (int) file_get_contents( $keep . '/.parked' ) : 0; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( time() - $since < 5 * MINUTE_IN_SECONDS ) {
			return;
		}
		if ( @rename( $keep, rtrim( self::dir(), '/' ) ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
			wp_delete_file( self::dir() . '.parked' );
		}
	}

	/* ------------------------------------------------------------------ Files */

	private static function copy( string $from, string $to ): bool {
		if ( ! wp_mkdir_p( $to ) ) {
			return false;
		}
		foreach ( (array) scandir( $from ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$src = $from . '/' . $name;
			$dst = $to . '/' . $name;
			if ( is_dir( $src ) ? ! self::copy( $src, $dst ) : ! copy( $src, $dst ) ) {
				return false;
			}
		}
		return true;
	}

	private static function rmdir( string $dir ): void {
		if ( is_link( $dir ) ) {
			return; // never follow a link out of the plugin
		}
		foreach ( (array) scandir( $dir ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$path = $dir . '/' . $name;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				self::rmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/* ------------------------------------------------------------------ REST */

	public function routes(): void {
		$can = array( White_Label::class, 'can_manage' );
		register_rest_route( Rest::NS, '/pro-module', array( 'methods' => 'GET', 'callback' => array( $this, 'rest_state' ), 'permission_callback' => $can ) );
		register_rest_route( Rest::NS, '/pro-module/sync', array( 'methods' => 'POST', 'callback' => array( $this, 'rest_sync' ), 'permission_callback' => static fn() => White_Label::can_manage() && ( ! is_multisite() || is_super_admin() ) ) );
		self::stand_ins();
	}

	/**
	 * Without the module (a free site, or a licensed one whose module is not installed yet), the paid screens still
	 * ask their routes: they get the locked state, or a clear message, instead of "No route".
	 */
	private static function stand_ins(): void {
		$manage = array( White_Label::class, 'can_manage' );
		if ( ! class_exists( \Uncoder\Builder\Site\Page_Reports::class ) ) {
			register_rest_route(
				Rest::NS,
				'/reports',
				array(
					array( 'methods' => 'GET', 'callback' => static fn() => new WP_REST_Response( array( 'reports' => array(), 'allowed' => false, 'missing' => Licence::allows( 'reports' ), 'pricing' => Licence::PRICING ) ), 'permission_callback' => $manage ),
					array( 'methods' => 'POST', 'callback' => static fn() => self::missing( 'reports' ), 'permission_callback' => $manage ),
				)
			);
			$empty = array( 'name' => '', 'logo' => '', 'accent' => '#083241', 'contact' => '', 'intro' => '' );
			register_rest_route(
				Rest::NS,
				'/reports/brand',
				array(
					array( 'methods' => 'GET', 'callback' => static fn() => new WP_REST_Response( array_merge( $empty, array( 'logo' => array( 'id' => 0, 'url' => '' ), 'fallback' => $empty, 'allowed' => false ) ) ), 'permission_callback' => $manage ),
					array( 'methods' => 'POST', 'callback' => static fn() => self::missing( 'reports' ), 'permission_callback' => $manage ),
				)
			);
		}
		if ( ! class_exists( \Uncoder\Builder\Site\Starter_Rewrite::class ) ) {
			$full = static fn() => current_user_can( 'manage_options' ) && 'full' === \Uncoder\Builder\Site\Role_Manager::access();
			register_rest_route(
				Rest::NS,
				'/rewrite',
				array(
					'methods'             => 'GET',
					'callback'            => static fn() => new WP_REST_Response(
						array(
							'allowed'   => false,
							'missing'   => Licence::allows( 'ai_rewrite' ),
							'profile'   => array(),
							'tones'     => array(),
							'documents' => array(),
							'apps'      => 0,
							'connect'   => admin_url( 'admin.php?page=uncoder-ai' ),
							'prompt'    => '',
							'pricing'   => Licence::PRICING,
						)
					),
					'permission_callback' => $full,
				)
			);
		}
		if ( ! class_exists( \Uncoder\Builder\Site\Section_Insert::class ) ) {
			$can = array( \Uncoder\Builder\Site\Section_Library::class, 'can_use' );
			register_rest_route( Rest::NS, '/sections/(?P<id>[a-z0-9\-]+)', array( 'methods' => 'GET', 'callback' => static fn() => self::missing( 'sections' ), 'permission_callback' => $can ) );
			register_rest_route( Rest::NS, '/sections/(?P<id>[a-z0-9\-]+)/save', array( 'methods' => 'POST', 'callback' => static fn() => self::missing( 'sections' ), 'permission_callback' => $can ) );
		}
	}

	/** What a paid action answers without the module: install it (licensed), or it comes with a plan. */
	private static function missing( string $feature ): WP_Error {
		if ( Licence::allows( $feature ) ) {
			return new WP_Error( 'uncoder_pro_missing', __( 'The Pro module is not installed on this site yet. It installs by itself within a few minutes, or right away from Settings → Licence.', 'uncoder' ), array( 'status' => 409 ) );
		}
		return new WP_Error( 'uncoder_needs_licence', __( 'This comes with the Pro and Agency licences.', 'uncoder' ), array( 'status' => 403 ) );
	}

	/** @return array<string,mixed> For Settings → Licence. */
	public static function status(): array {
		$s    = self::state();
		$have = self::installed();
		$next = (array) ( $s['available'] ?? array() );
		return array(
			'installed' => $have['version'],
			'edition'   => $have['edition'],
			'dev'       => $have['dev'],
			'available' => (string) ( $next['version'] ?? '' ),
			'nextEdition' => (string) ( $next['edition'] ?? '' ),
			'update'    => '' !== $have['version'] && ! $have['dev'] && ! empty( $next['version'] ) && ( version_compare( (string) $next['version'], $have['version'], '>' ) || ( $next['edition'] ?? '' ) !== $have['edition'] ),
			'checked'   => ! empty( $s['checked'] ) ? gmdate( 'c', (int) $s['checked'] ) : null,
			'error'     => $s['error'] ?? null,
			'auto'      => Auto_Updates::pro(),
			'licensed'  => Licence::active(),
		);
	}

	public function rest_state(): WP_REST_Response {
		return new WP_REST_Response( self::status() );
	}

	public function rest_sync() {
		$done = self::sync( true );
		return is_wp_error( $done ) ? $done : new WP_REST_Response( self::status() );
	}
}
