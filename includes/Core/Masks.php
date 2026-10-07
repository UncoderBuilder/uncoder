<?php
/**
 * Mask shapes for the common "Mask" controls.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Each shape is a 100×100 SVG turned into a data URI, so the CSS needs no file and both CSS engines (PHP and
 * the editor) print exactly the same declarations. preserveAspectRatio="none" lets "Stretch" fill any box;
 * "Fit" (contain) keeps the proportions.
 */
final class Masks {

	/** id => [ label, SVG shape markup (viewBox 0 0 100 100) ]. */
	public const SHAPES = array(
		'circle'   => array( 'Circle', "<circle cx='50' cy='50' r='50'/>" ),
		'squircle' => array( 'Rounded square', "<path d='M50 0C90 0 100 10 100 50S90 100 50 100 0 90 0 50 10 0 50 0Z'/>" ),
		'blob'     => array( 'Blob', "<path d='M78 12c12 9 21 24 22 39 1 16-6 32-18 41-12 10-29 11-44 7C23 95 9 85 3 70-3 55 0 37 9 24 18 11 34 2 50 1c10 0 20 4 28 11Z'/>" ),
		'arch'     => array( 'Arch', "<path d='M0 100V50a50 50 0 0 1 100 0v50Z'/>" ),
		'hexagon'  => array( 'Hexagon', "<path d='M25 3h50l25 47-25 47H25L0 50Z'/>" ),
		'triangle' => array( 'Triangle', "<path d='M50 3l49 94H1Z'/>" ),
		'diamond'  => array( 'Diamond', "<path d='M50 0l50 50-50 50L0 50Z'/>" ),
		'star'     => array( 'Star', "<path d='M50 2l14.7 30.3 33.3 4.6-24.2 23.3 5.9 33.1L50 77.6 20.3 93.3l5.9-33.1L2 36.9l33.3-4.6Z'/>" ),
		'heart'    => array( 'Heart', "<path d='M50 94C19 71 1 53 1 31 1 15 13 4 28 4c10 0 17 5 22 13 5-8 12-13 22-13 15 0 27 11 27 27 0 22-18 40-49 63Z'/>" ),
		'bubble'   => array( 'Speech bubble', "<path d='M12 4h76a12 12 0 0 1 12 12v48a12 12 0 0 1-12 12H42L20 96V76h-8A12 12 0 0 1 0 64V16A12 12 0 0 1 12 4Z'/>" ),
		'flower'   => array( 'Flower', "<path d='M50 0a19 19 0 0 1 17.7 26A19 19 0 0 1 93 35.3a19 19 0 0 1-7.8 25.4A19 19 0 0 1 80 91.6a19 19 0 0 1-24.3-7.9A19 19 0 0 1 50 100a19 19 0 0 1-5.7-16.3A19 19 0 0 1 20 91.6a19 19 0 0 1-5.2-30.9A19 19 0 0 1 7 35.3 19 19 0 0 1 32.3 26 19 19 0 0 1 50 0Z'/>" ),
		'wave'     => array( 'Wavy bottom', "<path d='M0 0h100v86c-12 8-25 8-37 0s-25-8-37 0-18 5-26 1Z'/>" ),
	);

	/** CSS url() of a shape. */
	public static function url( string $id ): string {
		$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='100' height='100' preserveAspectRatio='none'>" . self::SHAPES[ $id ][1] . '</svg>';
		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#', '"' ), array( '%3C', '%3E', '%23', "'" ), $svg ) . '")';
	}

	/** @return array<string,string> Select options (shape id => label), with "none" and "custom". */
	public static function options(): array {
		return array(
			''         => __( 'None', 'uncoder' ),
			'circle'   => __( 'Circle', 'uncoder' ),
			'squircle' => __( 'Rounded square', 'uncoder' ),
			'blob'     => __( 'Blob', 'uncoder' ),
			'arch'     => __( 'Arch', 'uncoder' ),
			'hexagon'  => __( 'Hexagon', 'uncoder' ),
			'triangle' => __( 'Triangle', 'uncoder' ),
			'diamond'  => __( 'Diamond', 'uncoder' ),
			'star'     => __( 'Star', 'uncoder' ),
			'heart'    => __( 'Heart', 'uncoder' ),
			'bubble'   => __( 'Speech bubble', 'uncoder' ),
			'flower'   => __( 'Flower', 'uncoder' ),
			'wave'     => __( 'Wavy bottom', 'uncoder' ),
			'fade-bottom' => __( 'Fade out at the bottom', 'uncoder' ),
			'fade-top'    => __( 'Fade out at the top', 'uncoder' ),
			'fade-y'      => __( 'Fade out at top and bottom', 'uncoder' ),
			'fade-x'      => __( 'Fade out at the sides', 'uncoder' ),
			'custom'   => __( 'Custom image (SVG / PNG)', 'uncoder' ),
		);
	}

	/**
	 * Fades: a gradient instead of a shape (a wall of cards that fades out at the bottom, a row fading at its sides),
	 * over the fade length (`--uncoder-mask-fade`, the Fade length control; 32% by default).
	 */
	public const FADES = array(
		'fade-bottom' => 'linear-gradient(to bottom,#000 calc(100% - var(--uncoder-mask-fade,32%)),transparent)',
		'fade-top'    => 'linear-gradient(to top,#000 calc(100% - var(--uncoder-mask-fade,32%)),transparent)',
		'fade-y'      => 'linear-gradient(to bottom,transparent,#000 var(--uncoder-mask-fade,32%),#000 calc(100% - var(--uncoder-mask-fade,32%)),transparent)',
		'fade-x'      => 'linear-gradient(to right,transparent,#000 var(--uncoder-mask-fade,32%),#000 calc(100% - var(--uncoder-mask-fade,32%)),transparent)',
	);

	/**
	 * selectors_dictionary for the shape control: the image plus sensible defaults (fit, centred, no repeat)
	 * that the size / position / repeat controls override.
	 *
	 * @return array<string,string>
	 */
	public static function dictionary(): array {
		$defaults = '-webkit-mask-size:contain;mask-size:contain;-webkit-mask-position:center;mask-position:center;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat';
		$out      = array( 'custom' => $defaults );
		foreach ( array_keys( self::SHAPES ) as $id ) {
			$url       = self::url( $id );
			$out[ $id ] = '-webkit-mask-image:' . $url . ';mask-image:' . $url . ';' . $defaults;
		}
		foreach ( self::FADES as $id => $gradient ) {
			$out[ $id ] = '-webkit-mask-image:' . $gradient . ';mask-image:' . $gradient . ';-webkit-mask-size:100% 100%;mask-size:100% 100%;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat';
		}
		return $out;
	}
}
