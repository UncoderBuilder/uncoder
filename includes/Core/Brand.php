<?php
/**
 * The Uncoder mark and loader (twin of src/shared/brand.ts and src/editor/ui/Brand.tsx).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A U whose N-shaped lightning bolt is cut out as negative space (assets/brand/). Two paths draw the
 * U in a 92 × 96 box; BOLT is the cut, which the loader flashes.
 */
final class Brand {

	public const URL = 'https://uncoderbuilder.com';

	public const U_LEFT  = 'M0 0H70L49 34L29 14L11 86C3.6 80 0 69.6 0 58Z';
	public const U_RIGHT = 'M79 0H92V58C92 82 74.6 96 46 96C34.7 96 25.1 94.1 17 90L39 56L59 76Z';
	public const BOLT    = 'M70 0H79L59 76L39 56L17 90L11 86L29 14L49 34Z';

	/** URL of a file in assets/brand/. */
	public static function asset( string $file ): string {
		return UNCODER_WB_URL . 'assets/brand/' . $file;
	}

	/** The builder's name: "Uncoder", or the agency's (Site\White_Label). */
	public static function name(): string {
		return \Uncoder\Builder\Site\White_Label::name();
	}

	/** The website the builder links to: uncoderbuilder.com, or the agency's under white-label. */
	public static function url(): string {
		$url = (string) ( \Uncoder\Builder\Site\White_Label::brand()['url'] ?? '' );
		return '' !== $url ? $url : self::URL;
	}

	/** The white-label icon's URL, or '' (the Uncoder mark). */
	private static function custom_icon(): string {
		return \Uncoder\Builder\Site\White_Label::active() ? \Uncoder\Builder\Site\White_Label::image( 'icon' ) : '';
	}

	/**
	 * The symbol as inline SVG in the current text color (row actions, admin bar, buttons).
	 *
	 * @param int    $size  Height in px.
	 * @param string $style Extra inline CSS.
	 */
	/**
	 * The app icon (assets/brand/uncoder-app-icon.svg): the mark optically centred in a petrol circle, about
	 * half its diameter. Twin of AppIcon in src/editor/ui/Brand.tsx and APP_ICON in src/shared/brand.ts.
	 */
	public static function app_icon( int $size = 64 ): string {
		$icon = self::custom_icon();
		if ( '' !== $icon ) {
			return '<img class="uncoder-app-icon" src="' . esc_url( $icon ) . '" width="' . $size . '" height="' . $size . '" alt="" style="object-fit:contain">';
		}
		return '<svg class="uncoder-app-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 256 256" aria-hidden="true" focusable="false">'
			. '<circle cx="128" cy="128" r="128" fill="#083241"/>'
			. '<g transform="translate(66.36 66.762) scale(1.34)" fill="#fff"><path d="' . self::U_LEFT . '"/><path d="' . self::U_RIGHT . '"/></g></svg>';
	}

	public static function mark( int $size = 14, string $style = '' ): string {
		$icon = self::custom_icon();
		if ( '' !== $icon ) {
			return '<img class="uncoder-mark" src="' . esc_url( $icon ) . '" width="' . $size . '" height="' . $size . '" alt="" style="object-fit:contain;vertical-align:middle;' . esc_attr( $style ) . '">';
		}
		return '<svg class="uncoder-mark" width="' . (int) round( $size * 92 / 96 ) . '" height="' . $size . '" viewBox="0 0 92 96" fill="currentColor" aria-hidden="true" focusable="false"'
			. ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>'
			. '<path d="' . self::U_LEFT . '"/><path d="' . self::U_RIGHT . '"/></svg>';
	}

	/**
	 * The loading mark (styles in src/editor/ui/brand.css, bundled with the editor and admin CSS).
	 *
	 * @param string $label Text for screen readers.
	 * @param string $size  "sm", "md" or "lg".
	 */
	public static function loader( string $label, string $size = 'md' ): string {
		$size = in_array( $size, array( 'sm', 'md', 'lg' ), true ) ? $size : 'md';
		if ( \Uncoder\Builder\Site\White_Label::active() ) {
			// White-label: the agency's icon (or a plain disc) instead of the Uncoder mark.
			$icon = self::custom_icon();
			return '<div class="uncoder-ui-loader uncoder-ui-loader--' . $size . ' uncoder-ui-loader--plain" role="status"><div class="uncoder-ui-loader__disc">'
				. ( '' !== $icon ? '<img class="uncoder-ui-loader__img" src="' . esc_url( $icon ) . '" alt="">' : '' )
				. '</div><span class="uncoder-ui-loader__label">' . esc_html( $label ) . '</span></div>';
		}
		return '<div class="uncoder-ui-loader uncoder-ui-loader--' . $size . '" role="status">'
			. '<div class="uncoder-ui-loader__disc">'
			. '<svg class="uncoder-ui-loader__mark" viewBox="-2 -2 96 100" aria-hidden="true" focusable="false">'
			. '<path class="uncoder-ui-loader__u" d="' . self::U_LEFT . '"/>'
			. '<path class="uncoder-ui-loader__u" d="' . self::U_RIGHT . '"/>'
			. '<path class="uncoder-ui-loader__bolt" d="' . self::BOLT . '"/>'
			. '</svg></div>'
			. '<span class="uncoder-ui-loader__label">' . esc_html( $label ) . '</span>'
			. '</div>';
	}

	/**
	 * The admin menu icon: WordPress repaints the fill in the admin color scheme. The padding in the viewBox
	 * keeps the mark about 16px tall in the 20px box, the size of the Dashicons beside it.
	 */
	public static function menu_icon(): string {
		$icon = self::custom_icon();
		if ( '' !== $icon ) {
			return $icon;
		}
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-14 -12 120 120" fill="#a7aaad"><path d="' . self::U_LEFT . '"/><path d="' . self::U_RIGHT . '"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- inline SVG menu icon.
	}
}
