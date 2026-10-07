<?php
/**
 * Animated container backgrounds.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * The animated backgrounds a container can have (Style → Background → Animated background): five CSS styles and
 * WebGL shaders from assets/vendor/animated-bg (module bg-animated), drawn in a layer behind the content.
 * Twin of animatedBg() in the editor's ElementView.tsx.
 */
final class Animated_Backgrounds {

	/** Drifting radial gradients in CSS (no script). */
	public const CSS = array(
		'style-1' => 'Style 1',
		'style-2' => 'Style 2',
		'style-3' => 'Style 3',
		'style-4' => 'Style 4',
		'style-5' => 'Style 5',
	);

	/** WebGL shaders by group: file name (without .js) => label. */
	public const SHADERS = array(
		'Gradient' => array(
			'fluid-gradient' => 'Fluid Gradient',
			'borealis'       => 'Borealis',
			'gradient-mesh'  => 'Gradient Mesh',
			'mist'           => 'Mist',
			'mystic-lake'    => 'Mystic Lake',
			'noir-haze'      => 'Noir Haze',
			'void-wave'      => 'Void Wave',
			'halftone'       => 'Halftone',
		),
		'Light'    => array(
			'the-shining'   => 'The Shining',
			'phase-tunnel'  => 'Phase Tunnel',
			'plasma-line'   => 'Plasma Line',
			'light-strings' => 'Light Strings',
			'light-rays'    => 'Light Rays',
		),
		'Shape'    => array(
			'flame'        => 'Flame',
			'pulse-bubble' => 'Pulse Bubble',
			'neon-eclipse' => 'Neon Eclipse',
			'echo-sphere'  => 'Echo Sphere',
		),
		'Image'    => array(
			'liquid-mask'  => 'Liquid Mask',
			'liquid-image' => 'Liquid Image',
		),
		'Pattern'  => array(
			'bit-wave'         => 'Bit Wave',
			'flux-stripes'     => 'Flux Stripes',
			'perspective-grid' => 'Perspective Grid',
		),
	);

	/** Animations that ignore a setting (as in Uncoder Elements), so the control is hidden for them. */
	private const IGNORE = array(
		'bg'          => array( 'flux-stripes', 'fluid-gradient', 'bit-wave', 'gradient-mesh', 'liquid-mask', 'liquid-image', 'mystic-lake', 'neon-eclipse', 'the-shining', 'plasma-line', 'light-rays' ),
		'color_2'     => array( 'mystic-lake', 'noir-haze', 'void-wave', 'the-shining', 'mist', 'flame', 'liquid-mask', 'liquid-image', 'halftone', 'light-rays' ),
		'color_3'     => array( 'mystic-lake', 'pulse-bubble', 'noir-haze', 'void-wave', 'the-shining', 'mist', 'flame', 'halftone', 'bit-wave', 'echo-sphere', 'liquid-mask', 'liquid-image', 'phase-tunnel', 'light-rays' ),
		'color_4'     => array( 'mystic-lake', 'pulse-bubble', 'noir-haze', 'void-wave', 'the-shining', 'mist', 'flame', 'halftone', 'bit-wave', 'echo-sphere', 'liquid-mask', 'liquid-image', 'phase-tunnel', 'style-1', 'style-2', 'style-3', 'style-4', 'style-5', 'light-rays' ),
		'offset'      => array( 'fluid-gradient', 'borealis', 'bit-wave', 'void-wave', 'noir-haze', 'mystic-lake', 'gradient-mesh', 'liquid-mask', 'liquid-image' ),
		'speed'       => array( 'flux-stripes', 'liquid-mask', 'liquid-image' ),
		'noise'       => array( 'fluid-gradient', 'liquid-mask', 'perspective-grid', 'halftone', 'light-rays' ),
		'interactive' => array( 'liquid-image', 'light-rays' ),
	);

	/** Animations that use a setting only they have. */
	private const ONLY = array(
		'angle' => array( 'flux-stripes', 'light-strings', 'plasma-line', 'the-shining', 'mist', 'light-rays' ),
		'image' => array( 'liquid-mask', 'liquid-image' ),
	);

	/** Default values of the shader settings (Uncoder Elements' defaults). */
	public const DEFAULTS = array(
		'speed'     => 20,
		'scale'     => 10,
		'intensity' => 50,
		'noise'     => 20,
		'angle'     => 0,
		'frame'     => 10,
	);

	/**
	 * @return array<string,string> Shader name => label.
	 */
	public static function shaders(): array {
		return array_merge( ...array_values( self::SHADERS ) );
	}

	/**
	 * Select options: None, the CSS styles, then the shaders with their group in the label.
	 *
	 * @return array<string,string>
	 */
	public static function options(): array {
		$options = array( '' => __( 'None', 'uncoder' ) );
		foreach ( self::CSS as $key => $label ) {
			/* translators: %s: style name, e.g. "Style 1". */
			$options[ $key ] = sprintf( __( 'CSS: %s', 'uncoder' ), $label );
		}
		foreach ( self::SHADERS as $group => $items ) {
			foreach ( $items as $key => $label ) {
				$options[ $key ] = $group . ': ' . $label;
			}
		}
		return $options;
	}

	/**
	 * Animation names that use a setting, for control conditions.
	 *
	 * @param string $setting One of color_1…color_4, bg, scale, intensity, speed, noise, angle, offset, interactive, image.
	 * @return string[]
	 */
	public static function using( string $setting ): array {
		if ( isset( self::ONLY[ $setting ] ) ) {
			return self::ONLY[ $setting ];
		}
		$all = in_array( $setting, array( 'color_1', 'color_2', 'color_3', 'color_4', 'bg' ), true )
			? array_merge( array_keys( self::CSS ), array_keys( self::shaders() ) )
			: array_keys( self::shaders() );
		return array_values( array_diff( $all, self::IGNORE[ $setting ] ?? array() ) );
	}

	/**
	 * The chosen animation, or '' when none (or unknown).
	 *
	 * @param array<string,mixed> $s Container settings.
	 */
	public static function name( array $s ): string {
		$name = (string) ( $s['bg_animation'] ?? '' );
		return isset( self::CSS[ $name ] ) || isset( self::shaders()[ $name ] ) ? $name : '';
	}

	public static function is_shader( string $name ): bool {
		return isset( self::shaders()[ $name ] );
	}

	/**
	 * The background layer markup, or '' when the container has no animated background.
	 *
	 * @param array<string,mixed> $s Container settings.
	 */
	public static function layer( array $s ): string {
		$name = self::name( $s );
		if ( '' === $name ) {
			return '';
		}
		if ( ! self::is_shader( $name ) ) {
			return '<div class="uncoder-abg uncoder-abg--css uncoder-abg--' . esc_attr( $name ) . '" aria-hidden="true"><div class="uncoder-abg__g"></div></div>';
		}
		return '<div' . Utils::attrs(
			array(
				'class'           => 'uncoder-abg uncoder-abg--' . $name,
				'data-uncoder-js' => 'bg-animated',
				'data-settings'   => wp_json_encode( self::settings( $s, $name ) ),
				'aria-hidden'     => 'true',
			)
		) . '></div>';
	}

	/**
	 * Settings for the bg-animated module.
	 *
	 * @param array<string,mixed> $s    Container settings.
	 * @param string              $name Shader name.
	 * @return array<string,mixed>
	 */
	public static function settings( array $s, string $name ): array {
		$num = static function ( string $key, string $default_key, float $min, float $max ) use ( $s ): float {
			$value = $s[ $key ] ?? '';
			$value = is_numeric( $value ) ? (float) $value : (float) self::DEFAULTS[ $default_key ];
			return max( $min, min( $max, $value ) );
		};
		// Liquid styles show the chosen image, else the container's own background image.
		$image = '';
		if ( in_array( $name, self::ONLY['image'], true ) ) {
			$image = (string) ( $s['bg_anim_image']['url'] ?? '' );
			$bg    = $s['background'] ?? array();
			if ( '' === $image && is_array( $bg ) && in_array( $bg['type'] ?? 'classic', array( '', 'classic' ), true ) ) {
				$image = (string) ( $bg['image']['url'] ?? '' );
			}
		}
		$out = array(
			'name'        => $name,
			'base'        => UNCODER_WB_URL . 'assets/vendor/animated-bg/',
			'ver'         => UNCODER_WB_VERSION,
			'speed'       => $num( 'bg_anim_speed', 'speed', 1, 100 ),
			'scale'       => $num( 'bg_anim_scale', 'scale', 0, 100 ),
			'intensity'   => $num( 'bg_anim_intensity', 'intensity', 0, 100 ),
			'noise'       => $num( 'bg_anim_noise', 'noise', 0, 100 ),
			'angle'       => $num( 'bg_anim_angle', 'angle', 0, 360 ),
			'offsetX'     => is_numeric( $s['bg_anim_offset_x'] ?? '' ) ? max( -400, min( 400, (float) $s['bg_anim_offset_x'] ) ) : 0,
			'offsetY'     => is_numeric( $s['bg_anim_offset_y'] ?? '' ) ? max( -400, min( 400, (float) $s['bg_anim_offset_y'] ) ) : 0,
			'interactive' => ! empty( $s['bg_anim_interactive'] ),
			'static'      => ! empty( $s['bg_anim_freeze'] ),
			'frame'       => $num( 'bg_anim_frame', 'frame', 0, 1000 ),
		);
		if ( '' !== $image ) {
			$out['image'] = esc_url_raw( $image );
		}
		return $out;
	}
}
