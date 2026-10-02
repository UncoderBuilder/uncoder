<?php
/**
 * Fixed-window rate limiter backed by the object cache / transients.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * hit( key, limit, window ) returns false when the caller is over the limit.
 */
final class Rate_Limiter {

	/**
	 * @return array{allowed:bool, remaining:int, reset:int}
	 */
	public static function hit( string $key, int $limit, int $window = 60 ): array {
		$bucket = (int) floor( time() / $window );
		$name   = 'uncoder_wb_rl_' . md5( $key . '|' . $bucket );
		$count  = (int) get_transient( $name );
		++$count;
		set_transient( $name, $count, $window + 5 );
		return array(
			'allowed'   => $count <= $limit,
			'remaining' => max( 0, $limit - $count ),
			'reset'     => ( $bucket + 1 ) * $window,
		);
	}
}
