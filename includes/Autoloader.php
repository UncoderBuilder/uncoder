<?php
/**
 * PSR-4 autoloader for the Uncoder\Builder namespace.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Maps Uncoder\Builder\Foo\Bar_Baz to includes/Foo/Bar_Baz.php, then to pro/includes/Foo/Bar_Baz.php: the paid
 * features live in the Pro module (Licence\Pro_Module), which keeps the same class names.
 */
final class Autoloader {

	private const PREFIX = 'Uncoder\\Builder\\';

	/** Whether the Pro module's classes may load (Licence\Pro_Module::load() decides). */
	private static bool $pro = false;

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/** Lets classes load from pro/includes/ (a checked, compatible Pro module is present). */
	public static function enable_pro(): void {
		self::$pro = true;
	}

	public static function load( string $class ): void {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, strlen( self::PREFIX ) ) ) . '.php';
		$file     = UNCODER_WB_PATH . 'includes/' . $relative;
		if ( is_readable( $file ) ) {
			require_once $file;
			return;
		}
		if ( self::$pro && is_readable( UNCODER_WB_PATH . 'pro/includes/' . $relative ) ) {
			require_once UNCODER_WB_PATH . 'pro/includes/' . $relative;
		}
	}
}
