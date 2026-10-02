<?php
/**
 * SVG path data helpers used by the Text Path widget.
 *
 * Not a widget: helpers in includes/Widgets/Support are never registered.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Validates user path data ("d") and reverses a path (so text runs the other way along it).
 */
final class Svg_Path {

	/** Parameters per command. */
	private const ARGS = array(
		'M' => 2,
		'L' => 2,
		'H' => 1,
		'V' => 1,
		'C' => 6,
		'S' => 4,
		'Q' => 4,
		'T' => 2,
		'A' => 7,
		'Z' => 0,
	);

	/**
	 * Path data safe to print, or '' when it is not a parseable path (only path commands, numbers and
	 * separators; must start with a moveto).
	 */
	public static function clean( string $d ): string {
		$d = trim( (string) preg_replace( '/\s+/', ' ', $d ) );
		if ( '' === $d || strlen( $d ) > 20000 || ! preg_match( '/^[Mm][MmLlHhVvCcSsQqTtAaZz0-9eE.,+\- ]*$/', $d ) ) {
			return '';
		}
		return null === self::subpaths( $d ) ? '' : $d;
	}

	/**
	 * The same path drawn from its end to its start ('' when the data cannot be parsed).
	 */
	public static function reverse( string $d ): string {
		$subs = self::subpaths( $d );
		if ( null === $subs ) {
			return '';
		}
		$out = array();
		foreach ( array_reverse( $subs ) as $sub ) {
			if ( ! $sub['segs'] ) {
				continue;
			}
			$segs  = $sub['segs'];
			$last  = $segs[ count( $segs ) - 1 ];
			$out[] = 'M' . self::pt( $last['to'] );
			foreach ( array_reverse( $segs ) as $seg ) {
				switch ( $seg['type'] ) {
					case 'C':
						$out[] = 'C' . self::pt( $seg['c2'] ) . ' ' . self::pt( $seg['c1'] ) . ' ' . self::pt( $seg['from'] );
						break;
					case 'Q':
						$out[] = 'Q' . self::pt( $seg['c'] ) . ' ' . self::pt( $seg['from'] );
						break;
					case 'A':
						$a     = $seg['arc'];
						$out[] = 'A' . self::num( $a[0] ) . ' ' . self::num( $a[1] ) . ' ' . self::num( $a[2] ) . ' ' . ( $a[3] ? '1' : '0' ) . ' ' . ( $a[4] ? '0' : '1' ) . ' ' . self::pt( $seg['from'] );
						break;
					default:
						$out[] = 'L' . self::pt( $seg['from'] );
				}
			}
			if ( $sub['closed'] ) {
				$out[] = 'Z';
			}
		}
		return implode( ' ', $out );
	}

	/**
	 * Absolute segments per subpath: [ [ 'segs' => [ { type L|C|Q|A, from, to, c1?, c2?, c?, arc? } ], 'closed' => bool ] ].
	 *
	 * @return array<int, array{segs: array<int, array<string,mixed>>, closed: bool}>|null
	 */
	private static function subpaths( string $d ): ?array {
		if ( ! preg_match_all( '/([MmLlHhVvCcSsQqTtAaZz])([^MmLlHhVvCcSsQqTtAaZz]*)/', $d, $groups, PREG_SET_ORDER ) ) {
			return null;
		}
		$subs  = array();
		$cur   = null;
		$x     = 0.0;
		$y     = 0.0;
		$sx    = 0.0;
		$sy    = 0.0;
		$prev  = '';
		$ctrl  = null;
		$after = false; // Just closed: a drawing command now starts a new subpath at the start point.

		foreach ( $groups as $group ) {
			$letter = $group[1];
			$cmd    = strtoupper( $letter );
			$rel    = $letter !== $cmd;
			if ( 'Z' === $cmd ) {
				if ( '' !== trim( $group[2] ) || null === $cur ) {
					return null;
				}
				if ( abs( $x - $sx ) > 1e-9 || abs( $y - $sy ) > 1e-9 ) {
					$cur['segs'][] = array( 'type' => 'L', 'from' => array( $x, $y ), 'to' => array( $sx, $sy ) );
				}
				$cur['closed'] = true;
				$x             = $sx;
				$y             = $sy;
				$after         = true;
				$prev          = 'Z';
				continue;
			}
			$nums  = self::numbers( $group[2], 'A' === $cmd );
			$count = self::ARGS[ $cmd ];
			if ( null === $nums || ! $nums || 0 !== count( $nums ) % $count ) {
				return null;
			}
			if ( 'M' !== $cmd && null === $cur ) {
				return null;
			}
			if ( 'M' !== $cmd && $after ) {
				$subs[] = $cur;
				$cur    = array(
					'segs'   => array(),
					'closed' => false,
				);
			}
			$after = false;

			foreach ( array_chunk( $nums, $count ) as $i => $n ) {
				$ox = $rel ? $x : 0.0;
				$oy = $rel ? $y : 0.0;
				$from = array( $x, $y );
				$type = $cmd;
				if ( 'M' === $cmd && 0 === $i ) {
					if ( null !== $cur ) {
						$subs[] = $cur;
					}
					$cur  = array(
						'segs'   => array(),
						'closed' => false,
					);
					$x    = $n[0] + $ox;
					$y    = $n[1] + $oy;
					$sx   = $x;
					$sy   = $y;
					$prev = 'M';
					continue;
				}
				if ( 'M' === $cmd ) {
					$type = 'L';
				}
				switch ( $type ) {
					case 'L':
						$to  = array( $n[0] + $ox, $n[1] + $oy );
						$seg = array( 'type' => 'L', 'from' => $from, 'to' => $to );
						break;
					case 'H':
						$to  = array( $n[0] + $ox, $y );
						$seg = array( 'type' => 'L', 'from' => $from, 'to' => $to );
						break;
					case 'V':
						$to  = array( $x, $n[0] + $oy );
						$seg = array( 'type' => 'L', 'from' => $from, 'to' => $to );
						break;
					case 'C':
					case 'S':
						if ( 'C' === $type ) {
							$c1 = array( $n[0] + $ox, $n[1] + $oy );
							$n  = array_slice( $n, 2 );
						} else {
							$c1 = in_array( $prev, array( 'C', 'S' ), true ) && $ctrl ? array( 2 * $x - $ctrl[0], 2 * $y - $ctrl[1] ) : $from;
						}
						$c2   = array( $n[0] + $ox, $n[1] + $oy );
						$to   = array( $n[2] + $ox, $n[3] + $oy );
						$seg  = array( 'type' => 'C', 'from' => $from, 'to' => $to, 'c1' => $c1, 'c2' => $c2 );
						$ctrl = $c2;
						break;
					case 'Q':
					case 'T':
						if ( 'Q' === $type ) {
							$c = array( $n[0] + $ox, $n[1] + $oy );
							$n = array_slice( $n, 2 );
						} else {
							$c = in_array( $prev, array( 'Q', 'T' ), true ) && $ctrl ? array( 2 * $x - $ctrl[0], 2 * $y - $ctrl[1] ) : $from;
						}
						$to   = array( $n[0] + $ox, $n[1] + $oy );
						$seg  = array( 'type' => 'Q', 'from' => $from, 'to' => $to, 'c' => $c );
						$ctrl = $c;
						break;
					default: // A.
						$to  = array( $n[5] + $ox, $n[6] + $oy );
						$seg = array(
							'type' => 'A',
							'from' => $from,
							'to'   => $to,
							'arc'  => array( abs( $n[0] ), abs( $n[1] ), $n[2], (bool) $n[3], (bool) $n[4] ),
						);
				}
				$cur['segs'][] = $seg;
				$x             = $to[0];
				$y             = $to[1];
				$prev          = $type;
			}
		}
		if ( null !== $cur ) {
			$subs[] = $cur;
		}
		return $subs;
	}

	/**
	 * Numbers of one command's arguments; arc flags may be written without separators ("a5 5 0 015 5").
	 *
	 * @return float[]|null
	 */
	private static function numbers( string $s, bool $arc ): ?array {
		$out = array();
		$pos = 0;
		$len = strlen( $s );
		$k   = 0;
		while ( true ) {
			while ( $pos < $len && ( ' ' === $s[ $pos ] || ',' === $s[ $pos ] ) ) {
				++$pos;
			}
			if ( $pos >= $len ) {
				break;
			}
			if ( $arc && in_array( $k % 7, array( 3, 4 ), true ) ) {
				if ( '0' !== $s[ $pos ] && '1' !== $s[ $pos ] ) {
					return null;
				}
				$out[] = (float) $s[ $pos ];
				++$pos;
				++$k;
				continue;
			}
			if ( ! preg_match( '/\G[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $s, $m, 0, $pos ) ) {
				return null;
			}
			$out[] = (float) $m[0];
			$pos  += strlen( $m[0] );
			++$k;
		}
		return $out;
	}

	/**
	 * @param float[] $p Point.
	 */
	private static function pt( array $p ): string {
		return self::num( $p[0] ) . ',' . self::num( $p[1] );
	}

	private static function num( float $v ): string {
		$s = rtrim( rtrim( number_format( $v, 3, '.', '' ), '0' ), '.' );
		return '-0' === $s || '' === $s ? '0' : $s;
	}
}
