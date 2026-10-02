<?php
/**
 * PSR-4 autoloader for the Uncoder\Builder namespace.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Maps Uncoder\Builder\Foo\Bar_Baz to includes/Foo/Bar_Baz.php.
 */
final class Autoloader {

	private const PREFIX = 'Uncoder\\Builder\\';

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	public static function load( string $class ): void {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}
		$relative = substr( $class, strlen( self::PREFIX ) );
		$file     = UNCODER_WB_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
