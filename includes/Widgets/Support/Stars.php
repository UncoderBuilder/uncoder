<?php
/**
 * Star markup shared by the Star Rating and Testimonial widgets.
 *
 * Not a widget: helpers in includes/Widgets/Support are never registered. The markup is styled by
 * css/widgets/star-rating.css, so widgets that print stars list 'star-rating' in frontend_styles().
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

use Uncoder\Builder\Core\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a row of icons where the first N are "marked"; a fractional rating fills the next
 * icon partially (in 10% steps, via a clip-path set by a modifier class).
 */
final class Stars {

	/**
	 * Clamps and rounds a rating to one decimal.
	 */
	public static function normalize( $rating, int $scale ): float {
		$rating = is_numeric( $rating ) ? (float) $rating : 0.0;
		return round( max( 0.0, min( (float) $scale, $rating ) ), 1 );
	}

	/**
	 * Human readable rating ("4.5", "5").
	 */
	public static function format( float $rating ): string {
		$decimals = ( floor( $rating ) === $rating ) ? 0 : 1;
		return number_format_i18n( $rating, $decimals );
	}

	/**
	 * Accessible label, e.g. "Rated 4.5 out of 5".
	 */
	public static function label( float $rating, int $scale ): string {
		/* translators: 1: rating value, 2: maximum rating. */
		return sprintf( __( 'Rated %1$s out of %2$s', 'uncoder' ), self::format( $rating ), number_format_i18n( $scale ) );
	}

	/**
	 * Escaped star row markup.
	 *
	 * @param float                      $rating    Rating (already normalized).
	 * @param int                        $scale     Number of icons (5 or 10).
	 * @param array<string,mixed>|string $icon      Icon value (Lucide name or icon control value).
	 * @param string                     $unmarked  "solid" or "outline".
	 * @param string                     $class     Extra class for the row.
	 */
	public static function render( float $rating, int $scale, $icon = 'star', string $unmarked = 'solid', string $class = '' ): string {
		if ( is_array( $icon ) && ( 'none' === ( $icon['library'] ?? '' ) || ( empty( $icon['value'] ) && empty( $icon['url'] ) ) ) ) {
			$icon = 'star';
		}
		$svg = Icons::render( $icon, array( 'class' => 'uncoder-star-rating__icon' ) );
		if ( '' === $svg ) {
			$svg = Icons::render( 'star', array( 'class' => 'uncoder-star-rating__icon' ) );
		}
		$classes = trim( 'uncoder-star-rating__stars uncoder-star-rating__stars--' . ( 'outline' === $unmarked ? 'outline' : 'solid' ) . ' ' . $class );
		$html    = '<span class="' . esc_attr( $classes ) . '" role="img" aria-label="' . esc_attr( self::label( $rating, $scale ) ) . '">';
		for ( $i = 1; $i <= $scale; $i++ ) {
			if ( $rating >= $i ) {
				$html .= '<span class="uncoder-star-rating__star uncoder-star-rating__star--marked">' . $svg . '</span>';
				continue;
			}
			$fraction = (int) round( ( $rating - ( $i - 1 ) ) * 10 ) * 10;
			if ( $fraction >= 100 ) {
				$html .= '<span class="uncoder-star-rating__star uncoder-star-rating__star--marked">' . $svg . '</span>';
			} elseif ( $fraction > 0 ) {
				$html .= '<span class="uncoder-star-rating__star uncoder-star-rating__star--partial uncoder-star-rating__star--p' . $fraction . '">' . $svg . '<span class="uncoder-star-rating__fill">' . $svg . '</span></span>';
			} else {
				$html .= '<span class="uncoder-star-rating__star">' . $svg . '</span>';
			}
		}
		return $html . '</span>';
	}
}
