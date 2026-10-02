<?php
/**
 * Elementor import: reads one Elementor element's settings and turns its values into Uncoder formats.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site\Elementor_Import;

use Uncoder\Builder\Controls\Types\Slider;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Every read marks the Elementor key as used, so whatever is left over at the end can be reported as
 * "not converted". Global references (__globals__) are resolved through the Converter.
 */
final class Source {

	/** Elementor device suffixes that exist in Uncoder by default. */
	public const SUFFIXES = array( '', '_tablet', '_mobile' );

	/** Elementor devices Uncoder does not map (their values are reported). */
	public const EXTRA = array( '_widescreen', '_laptop', '_tablet_extra', '_mobile_extra' );

	public const TYPO_FIELDS = array( 'typography', 'font_family', 'font_size', 'font_weight', 'text_transform', 'font_style', 'text_decoration', 'line_height', 'letter_spacing', 'word_spacing' );

	public const BG_FIELDS = array(
		'background', 'color', 'color_stop', 'color_b', 'color_b_stop', 'gradient_type', 'gradient_angle', 'gradient_position',
		'image', 'position', 'xpos', 'ypos', 'attachment', 'repeat', 'size', 'bg_width', 'video_link', 'video_start', 'video_end',
		'play_once', 'play_on_mobile', 'privacy_mode', 'video_fallback', 'slideshow_gallery', 'slideshow_loop', 'slideshow_slide_duration',
		'slideshow_slide_transition', 'slideshow_transition_duration', 'slideshow_background_size', 'slideshow_background_position',
		'slideshow_lazyload', 'slideshow_ken_burns', 'slideshow_ken_burns_zoom_direction',
	);

	/** @var array<string,mixed> */
	private array $s;

	/** @var array<string,string> */
	private array $globals;

	/** @var array<string,mixed> */
	private array $dynamic;

	/** @var array<string,bool> */
	private array $used = array();

	public string $type;

	private Converter $c;

	/**
	 * @param array<string,mixed> $settings Elementor settings.
	 */
	public function __construct( array $settings, string $type, Converter $c ) {
		$this->globals = array_filter( is_array( $settings['__globals__'] ?? null ) ? $settings['__globals__'] : array(), static fn( $v ) => is_string( $v ) && '' !== $v );
		$this->dynamic = is_array( $settings['__dynamic__'] ?? null ) ? $settings['__dynamic__'] : array();
		unset( $settings['__globals__'], $settings['__dynamic__'] );
		$this->s    = $settings;
		$this->type = $type;
		$this->c    = $c;
	}

	/* ------------------------------------------------------------------ Raw access */

	/** Whether a value (or a global reference) is set. */
	public function has( string $k ): bool {
		return ! self::blank( $this->s[ $k ] ?? null ) || isset( $this->globals[ $k ] );
	}

	/** Whether the key exists at all (even with an empty value: Elementor saves "off" switches as ""). */
	public function exists( string $k ): bool {
		return array_key_exists( $k, $this->s );
	}

	/**
	 * @return mixed
	 */
	public function raw( string $k ) {
		$this->used[ $k ] = true;
		return $this->s[ $k ] ?? null;
	}

	public function str( string $k, string $default = '' ): string {
		$v = $this->raw( $k );
		return is_scalar( $v ) && '' !== trim( (string) $v ) ? trim( (string) $v ) : $default;
	}

	/** Elementor switchers: "yes" (or any label_on value), "" when off. */
	public function yes( string $k, bool $default = false ): bool {
		if ( ! array_key_exists( $k, $this->s ) ) {
			$this->used[ $k ] = true;
			return $default;
		}
		$v = $this->raw( $k );
		return true === $v || 1 === $v || ( is_string( $v ) && '' !== $v && ! in_array( strtolower( $v ), array( 'no', 'false', '0', 'off', 'hide', 'none' ), true ) );
	}

	/** A number from a scalar or a slider ({size}). */
	public function num( string $k ): ?float {
		$v = $this->raw( $k );
		if ( is_array( $v ) ) {
			$v = $v['size'] ?? null;
		}
		return is_numeric( $v ) ? (float) $v : null;
	}

	/** @param string ...$keys Keys to mark as used without reading them. */
	public function use( string ...$keys ): void {
		foreach ( $keys as $k ) {
			foreach ( array_merge( self::SUFFIXES, self::EXTRA ) as $suffix ) {
				$this->used[ $k . $suffix ] = true;
			}
		}
	}

	/** @param string[] $fields Field names of a group. */
	private function use_group( string $name, array $fields ): void {
		foreach ( $fields as $f ) {
			$this->use( $name . '_' . $f );
		}
	}

	/** @return array<string,mixed> Every setting (read only; marks nothing). */
	public function all(): array {
		return $this->s;
	}

	/** @return array<string,mixed> The raw __dynamic__ map. */
	public function dynamic(): array {
		return $this->dynamic;
	}

	public function global_ref( string $k ): string {
		return $this->globals[ $k ] ?? '';
	}

	/** Adds a global reference the element would get by default (Elementor's default colors / fonts). */
	public function default_global( string $k, string $ref ): void {
		if ( ! isset( $this->globals[ $k ] ) && self::blank( $this->s[ $k ] ?? null ) && ! ( 'custom' === ( $this->s[ $k ] ?? '' ) ) ) {
			$this->globals[ $k ] = $ref;
		}
	}

	/* ------------------------------------------------------------------ Values */

	public function color( string $k ): string {
		$this->used[ $k ] = true;
		$ref              = $this->global_ref( $k );
		if ( '' !== $ref ) {
			$color = $this->c->global_color( $ref );
			if ( '' !== $color ) {
				return $color;
			}
		}
		$v = $this->s[ $k ] ?? '';
		if ( ! is_string( $v ) || '' === trim( $v ) ) {
			return '';
		}
		$clean = Utils::sanitize_color( trim( $v ) );
		if ( '' === $clean ) {
			$this->c->setting( $this->type, $k );
		}
		return $clean;
	}

	/** @return array<string,mixed>|null */
	public function slider( string $k ): ?array {
		return self::to_slider( $this->raw( $k ) );
	}

	/**
	 * @param array<string,int|float>|null $fill Values for empty sides (px).
	 * @return array<string,mixed>|null
	 */
	public function dims( string $k, ?array $fill = null ): ?array {
		return self::to_dims( $this->raw( $k ), $fill );
	}

	/** @return array<string,mixed>|null */
	public function link( string $k ): ?array {
		return $this->c->link( $this->raw( $k ) );
	}

	/** @return array<string,mixed>|null */
	public function media( string $k ): ?array {
		return $this->c->media( $this->raw( $k ) );
	}

	/** @return array<string,mixed>|null */
	public function icon( string $k ): ?array {
		return $this->c->icon( $this->raw( $k ) );
	}

	/**
	 * Typography group ({name}_typography = custom, {name}_font_family …) or its global.
	 *
	 * @return array<string,mixed>|null
	 */
	public function typo( string $name ): ?array {
		$this->use_group( $name, self::TYPO_FIELDS );
		$ref = $this->global_ref( $name . '_typography' );
		if ( '' !== $ref ) {
			$global = $this->c->global_typography( $ref );
			if ( null !== $global ) {
				return $global;
			}
		}
		if ( 'custom' !== ( $this->s[ $name . '_typography' ] ?? '' ) ) {
			return null;
		}
		return self::typo_fields( $this->s, $name );
	}

	/**
	 * Uncoder typography value from an Elementor typography group.
	 *
	 * @param array<string,mixed> $s Settings holding the group.
	 * @return array<string,mixed>|null
	 */
	public static function typo_fields( array $s, string $name ): ?array {
		$p   = $name . '_';
		$out = array();
		$fam = $s[ $p . 'font_family' ] ?? '';
		if ( is_string( $fam ) && '' !== trim( $fam ) ) {
			$fam = trim( explode( ',', $fam )[0], " \"'" );
			if ( preg_match( '/^[A-Za-z0-9][A-Za-z0-9 \-_.]{0,62}$/', $fam ) ) {
				$out['family'] = $fam;
			}
		}
		$weight = (string) ( $s[ $p . 'font_weight' ] ?? '' );
		if ( preg_match( '/^(?:[1-9]\d{0,2}|1000|normal|bold)$/', $weight ) ) {
			$out['weight'] = $weight;
		}
		$lists = array(
			'transform'  => array( 'text_transform', array( 'none', 'uppercase', 'lowercase', 'capitalize' ) ),
			'style'      => array( 'font_style', array( 'normal', 'italic', 'oblique' ) ),
			'decoration' => array( 'text_decoration', array( 'none', 'underline', 'overline', 'line-through' ) ),
		);
		foreach ( $lists as $to => $def ) {
			$v = (string) ( $s[ $p . $def[0] ] ?? '' );
			if ( in_array( $v, $def[1], true ) ) {
				$out[ $to ] = $v;
			}
		}
		foreach ( array( 'size' => 'font_size', 'line_height' => 'line_height', 'letter_spacing' => 'letter_spacing', 'word_spacing' => 'word_spacing' ) as $to => $from ) {
			foreach ( self::SUFFIXES as $suffix ) {
				$v = self::to_slider( $s[ $p . $from . $suffix ] ?? null, 'line_height' === $to ? 'em' : 'px' );
				if ( null !== $v ) {
					$out[ $to . $suffix ] = $v;
				}
			}
		}
		return $out ? $out : null;
	}

	/**
	 * Background group ({name}_background = classic | gradient | video | slideshow).
	 *
	 * @param string[] $types Types the target control accepts.
	 * @return array<string,mixed>|null
	 */
	public function bg( string $name, array $types = array( 'classic', 'gradient' ) ): ?array {
		$this->use_group( $name, self::BG_FIELDS );
		$p    = $name . '_';
		$type = (string) ( $this->s[ $p . 'background' ] ?? '' );
		if ( '' === $type ) {
			return null;
		}
		if ( ! in_array( $type, $types, true ) ) {
			$this->c->setting( $this->type, $p . 'background' );
			$type = 'classic';
		}
		$out   = array( 'type' => $type );
		$color = $this->color( $p . 'color' );
		if ( '' !== $color ) {
			$out['color'] = $color;
		}
		if ( 'gradient' === $type ) {
			$b = $this->color( $p . 'color_b' );
			if ( '' !== $b ) {
				$out['color_b'] = $b;
			}
			foreach ( array( 'color_stop', 'color_b_stop', 'gradient_angle' ) as $f ) {
				$v = self::to_slider( $this->s[ $p . $f ] ?? null, 'gradient_angle' === $f ? 'deg' : '%' );
				if ( null !== $v ) {
					$out[ $f ] = $v;
				}
			}
			$gtype = (string) ( $this->s[ $p . 'gradient_type' ] ?? '' );
			if ( in_array( $gtype, array( 'linear', 'radial' ), true ) ) {
				$out['gradient_type'] = $gtype;
			}
			$pos = (string) ( $this->s[ $p . 'gradient_position' ] ?? '' );
			if ( self::is_position( $pos ) ) {
				$out['gradient_position'] = $pos;
			}
			return $out;
		}
		if ( 'video' === $type ) {
			$url = (string) ( $this->s[ $p . 'video_link' ] ?? '' );
			if ( '' !== $url ) {
				$out['video_url'] = esc_url_raw( $url );
			}
			$fallback = $this->c->media( $this->s[ $p . 'video_fallback' ] ?? null );
			if ( $fallback ) {
				$out['video_fallback'] = $fallback;
			}
			return $out;
		}
		if ( 'slideshow' === $type ) {
			$slides = array();
			foreach ( (array) ( $this->s[ $p . 'slideshow_gallery' ] ?? array() ) as $img ) {
				$m = $this->c->media( $img );
				if ( $m ) {
					$slides[] = $m;
				}
			}
			$out['slides'] = $slides;
			$duration      = $this->s[ $p . 'slideshow_slide_duration' ] ?? '';
			if ( is_numeric( $duration ) ) {
				$out['slide_duration'] = max( 1000, min( 30000, (int) $duration ) );
			}
			$speed = $this->s[ $p . 'slideshow_transition_duration' ] ?? '';
			if ( is_numeric( $speed ) ) {
				$out['slide_speed'] = max( 100, min( 5000, (int) $speed ) );
			}
			if ( 0 === strpos( (string) ( $this->s[ $p . 'slideshow_slide_transition' ] ?? '' ), 'slide' ) ) {
				$out['slide_transition'] = 'slide';
			}
			if ( ! empty( $this->s[ $p . 'slideshow_ken_burns' ] ) ) {
				$out['ken_burns'] = 'out' === ( $this->s[ $p . 'slideshow_ken_burns_zoom_direction' ] ?? 'in' ) ? 'out' : 'in';
			}
			$size = (string) ( $this->s[ $p . 'slideshow_background_size' ] ?? '' );
			if ( in_array( $size, array( 'contain', 'auto' ), true ) ) {
				$out['slide_size'] = $size;
			}
			$pos = (string) ( $this->s[ $p . 'slideshow_background_position' ] ?? '' );
			if ( self::is_position( $pos ) ) {
				$out['slide_position'] = $pos;
			}
			return $out;
		}
		// Classic.
		foreach ( self::SUFFIXES as $suffix ) {
			$img = $this->c->media( $this->s[ $p . 'image' . $suffix ] ?? null );
			if ( $img ) {
				$out[ 'image' . $suffix ] = $img;
			}
			$pos = (string) ( $this->s[ $p . 'position' . $suffix ] ?? '' );
			if ( self::is_position( $pos ) ) {
				$out[ 'position' . $suffix ] = $pos;
			} elseif ( 'initial' === $pos ) {
				$this->c->setting( $this->type, $p . 'position (custom)' );
			}
			$repeat = (string) ( $this->s[ $p . 'repeat' . $suffix ] ?? '' );
			if ( in_array( $repeat, array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ), true ) ) {
				$out[ 'repeat' . $suffix ] = $repeat;
			}
			$size = (string) ( $this->s[ $p . 'size' . $suffix ] ?? '' );
			if ( in_array( $size, array( 'auto', 'cover', 'contain' ), true ) ) {
				$out[ 'size' . $suffix ] = $size;
			} elseif ( 'initial' === $size ) {
				$this->c->setting( $this->type, $p . 'size (custom)' );
			}
		}
		$att = (string) ( $this->s[ $p . 'attachment' ] ?? '' );
		if ( in_array( $att, array( 'scroll', 'fixed' ), true ) ) {
			$out['attachment'] = $att;
		}
		return count( $out ) > 1 ? $out : null;
	}

	/**
	 * Border group: {name}_border (style), {name}_width (dimensions), {name}_color.
	 *
	 * @return array<string,mixed>|null
	 */
	public function border( string $name ): ?array {
		$this->use( $name . '_border', $name . '_width', $name . '_color' );
		$style = (string) ( $this->s[ $name . '_border' ] ?? '' );
		if ( '' === $style ) {
			return null;
		}
		$out = array( 'style' => in_array( $style, array( 'none', 'solid', 'dashed', 'dotted', 'double', 'groove' ), true ) ? $style : 'solid' );
		if ( 'none' === $style ) {
			return $out;
		}
		foreach ( self::SUFFIXES as $suffix ) {
			$w = self::to_dims( $this->s[ $name . '_width' . $suffix ] ?? null );
			if ( $w ) {
				$out[ 'width' . $suffix ] = $w;
			}
		}
		$color = $this->color( $name . '_color' );
		if ( '' !== $color ) {
			$out['color'] = $color;
		}
		return $out;
	}

	/**
	 * Box shadow group: {name}_box_shadow_type = yes, {name}_box_shadow {horizontal, vertical, blur, spread, color}.
	 *
	 * @return array<string,mixed>|null
	 */
	public function shadow( string $name ): ?array {
		$this->use( $name . '_box_shadow_type', $name . '_box_shadow', $name . '_box_shadow_position' );
		if ( empty( $this->s[ $name . '_box_shadow_type' ] ) ) {
			return null;
		}
		$v   = (array) ( $this->s[ $name . '_box_shadow' ] ?? array( 'horizontal' => 0, 'vertical' => 0, 'blur' => 10, 'spread' => 0, 'color' => 'rgba(0,0,0,0.5)' ) );
		$out = self::shadow_value( $v, true );
		if ( $out && 'inset' === trim( (string) ( $this->s[ $name . '_box_shadow_position' ] ?? '' ) ) ) {
			$out['inset'] = true;
		}
		return $out;
	}

	/** @return array<string,mixed>|null */
	public function text_shadow( string $name ): ?array {
		$this->use( $name . '_text_shadow_type', $name . '_text_shadow' );
		if ( empty( $this->s[ $name . '_text_shadow_type' ] ) ) {
			return null;
		}
		return self::shadow_value( (array) ( $this->s[ $name . '_text_shadow' ] ?? array() ), false );
	}

	/**
	 * @param array<string,mixed> $v Elementor shadow value.
	 * @return array<string,mixed>|null
	 */
	public static function shadow_value( array $v, bool $box ): ?array {
		$color = Utils::sanitize_color( (string) ( $v['color'] ?? 'rgba(0,0,0,0.3)' ) );
		if ( '' === $color ) {
			return null;
		}
		$n   = static fn( $k, $min, $max, $d = 0 ) => max( $min, min( $max, is_numeric( $v[ $k ] ?? null ) ? (float) $v[ $k ] + 0 : $d ) );
		$out = array(
			'x'     => $n( 'horizontal', -200, 200 ),
			'y'     => $n( 'vertical', -200, 200 ),
			'blur'  => $n( 'blur', 0, 300, 10 ),
			'color' => $color,
		);
		if ( $box ) {
			$out['spread'] = $n( 'spread', -200, 200 );
		}
		return $out;
	}

	/**
	 * CSS filters group: {name}_css_filter = custom, {name}_blur, _brightness, _contrast, _saturate, _hue.
	 *
	 * @return array<string,mixed>|null
	 */
	public function filters( string $name ): ?array {
		$fields = array( 'blur' => 'blur', 'brightness' => 'brightness', 'contrast' => 'contrast', 'saturate' => 'saturate', 'hue' => 'hue' );
		$this->use( $name . '_css_filter' );
		foreach ( $fields as $f ) {
			$this->use( $name . '_' . $f );
		}
		if ( 'custom' !== ( $this->s[ $name . '_css_filter' ] ?? '' ) ) {
			return null;
		}
		$out = array();
		foreach ( $fields as $to => $from ) {
			$v = $this->s[ $name . '_' . $from ] ?? null;
			$v = is_array( $v ) ? ( $v['size'] ?? null ) : $v;
			if ( is_numeric( $v ) ) {
				$out[ $to ] = Utils::number( $v );
			}
		}
		return $out ? $out : null;
	}

	/* ------------------------------------------------------------------ Writers (set only real values) */

	/**
	 * @param array<string,mixed> $o     Output settings.
	 * @param mixed               $value Value.
	 */
	public static function put( array &$o, string $key, $value ): void {
		if ( null !== $value && '' !== $value && array() !== $value ) {
			$o[ $key ] = $value;
		}
	}

	/** @param array<string,mixed> $o Output. */
	public function text( array &$o, string $to, string $from ): void {
		$v = $this->raw( $from );
		if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
			$o[ $to ] = (string) $v;
		}
	}

	/** @param array<string,mixed> $o Output. */
	public function col( array &$o, string $to, string $from ): void {
		self::put( $o, $to, $this->color( $from ) );
	}

	/**
	 * A color as a classic background group value.
	 *
	 * @param array<string,mixed> $o Output.
	 */
	public function bgc( array &$o, string $to, string $from ): void {
		$c = $this->color( $from );
		if ( '' !== $c ) {
			$o[ $to ] = array( 'type' => 'classic', 'color' => $c );
		}
	}

	/** @param array<string,mixed> $o Output. */
	public function typ( array &$o, string $to, string $name ): void {
		self::put( $o, $to, $this->typo( $name ) );
	}

	/** @param array<string,mixed> $o Output. */
	public function brd( array &$o, string $to, string $name ): void {
		self::put( $o, $to, $this->border( $name ) );
	}

	/** @param array<string,mixed> $o Output. */
	public function shd( array &$o, string $to, string $name ): void {
		self::put( $o, $to, $this->shadow( $name ) );
	}

	/** @param array<string,mixed> $o Output. */
	public function tsh( array &$o, string $to, string $name ): void {
		self::put( $o, $to, $this->text_shadow( $name ) );
	}

	/** @param array<string,mixed> $o Output. */
	public function lnk( array &$o, string $to, string $from ): void {
		self::put( $o, $to, $this->link( $from ) );
	}

	/** @param array<string,mixed> $o Output. */
	public function img( array &$o, string $to, string $from ): void {
		self::put( $o, $to, $this->media( $from ) );
	}

	/** @param array<string,mixed> $o Output. */
	public function ico( array &$o, string $to, string ...$from ): void {
		foreach ( $from as $k ) {
			$icon = $this->icon( $k );
			if ( $icon ) {
				$o[ $to ] = $icon;
				foreach ( $from as $other ) {
					$this->use( $other );
				}
				return;
			}
		}
	}

	/** Switch: written only when Elementor stored the key ("" = off). @param array<string,mixed> $o Output. */
	public function flag( array &$o, string $to, string $from ): void {
		if ( array_key_exists( $from, $this->s ) ) {
			$o[ $to ] = $this->yes( $from );
		} else {
			$this->used[ $from ] = true;
		}
	}

	/** @param array<string,mixed> $o Output. */
	public function number( array &$o, string $to, string $from, ?float $min = null, ?float $max = null ): void {
		$v = $this->num( $from );
		if ( null === $v ) {
			return;
		}
		if ( null !== $min ) {
			$v = max( $min, $v );
		}
		if ( null !== $max ) {
			$v = min( $max, $v );
		}
		$o[ $to ] = Utils::number( $v );
	}

	/**
	 * Slider, per device.
	 *
	 * @param array<string,mixed> $o Output.
	 */
	public function sl( array &$o, string $to, string $from, bool $responsive = true ): void {
		foreach ( $responsive ? self::SUFFIXES : array( '' ) as $suffix ) {
			self::put( $o, $to . $suffix, $this->slider( $from . $suffix ) );
		}
	}

	/**
	 * Dimensions, per device.
	 *
	 * @param array<string,mixed> $o Output.
	 */
	public function dm( array &$o, string $to, string $from, bool $responsive = true ): void {
		foreach ( $responsive ? self::SUFFIXES : array( '' ) as $suffix ) {
			self::put( $o, $to . $suffix, $this->dims( $from . $suffix ) );
		}
	}

	/**
	 * A radius that Elementor stores as dimensions or as one slider.
	 *
	 * @param array<string,mixed> $o Output.
	 */
	public function radius( array &$o, string $to, string $from, bool $responsive = true ): void {
		foreach ( $responsive ? self::SUFFIXES : array( '' ) as $suffix ) {
			$v = $this->raw( $from . $suffix );
			if ( is_array( $v ) && isset( $v['size'] ) && ! isset( $v['top'] ) ) {
				$sl = self::to_slider( $v );
				if ( $sl && 'custom' !== $sl['unit'] ) {
					$o[ $to . $suffix ] = array( 'top' => $sl['size'], 'right' => $sl['size'], 'bottom' => $sl['size'], 'left' => $sl['size'], 'unit' => $sl['unit'], 'linked' => true );
				}
				continue;
			}
			self::put( $o, $to . $suffix, self::to_dims( $v ) );
		}
	}

	/**
	 * A select value through a map (value => Uncoder value; null map = unchanged).
	 *
	 * @param array<string,mixed> $o   Output.
	 * @param array<string,string>|null $map Value map.
	 */
	public function opt( array &$o, string $to, string $from, ?array $map = null, bool $responsive = false ): void {
		foreach ( $responsive ? self::SUFFIXES : array( '' ) as $suffix ) {
			$v = $this->str( $from . $suffix );
			if ( '' === $v ) {
				continue;
			}
			if ( null === $map ) {
				$o[ $to . $suffix ] = $v;
			} elseif ( isset( $map[ $v ] ) ) {
				if ( '' !== $map[ $v ] || '' !== $suffix ) {
					$o[ $to . $suffix ] = $map[ $v ];
				}
			} else {
				$this->c->setting( $this->type, $from . ': ' . $v );
			}
		}
	}

	/* ------------------------------------------------------------------ Converters (static) */

	/**
	 * @param mixed $v Elementor slider ({unit, size}) or number.
	 * @return array<string,mixed>|null
	 */
	public static function to_slider( $v, string $default_unit = 'px' ): ?array {
		if ( is_int( $v ) || is_float( $v ) || ( is_string( $v ) && is_numeric( $v ) ) ) {
			return array( 'size' => Utils::number( $v ), 'unit' => $default_unit );
		}
		if ( ! is_array( $v ) ) {
			return null;
		}
		$size = $v['size'] ?? '';
		$unit = isset( $v['unit'] ) && is_string( $v['unit'] ) ? $v['unit'] : $default_unit;
		if ( '' === $size || null === $size || is_array( $size ) ) {
			return null;
		}
		if ( 'custom' === $unit || ! is_numeric( $size ) ) {
			$parsed = is_string( $size ) ? Utils::parse_size( $size, 'px' ) : null;
			if ( $parsed && '' !== $parsed['size'] ) {
				return array( 'size' => Utils::number( $parsed['size'] ), 'unit' => $parsed['unit'] );
			}
			$css = Utils::css_value( $size );
			return '' === $css ? null : array( 'size' => $css, 'unit' => 'custom' );
		}
		if ( ! in_array( $unit, Slider::ALL_UNITS, true ) ) {
			$unit = $default_unit;
		}
		return array( 'size' => Utils::number( $size ), 'unit' => $unit );
	}

	/**
	 * @param mixed                        $v    Elementor dimensions ({unit, top, right, bottom, left, isLinked}).
	 * @param array<string,int|float>|null $fill Values for empty sides (px units only).
	 * @return array<string,mixed>|null
	 */
	public static function to_dims( $v, ?array $fill = null ): ?array {
		if ( ! is_array( $v ) ) {
			return null;
		}
		$unit = isset( $v['unit'] ) && is_string( $v['unit'] ) ? $v['unit'] : 'px';
		$out  = array();
		$any  = false;
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$x = $v[ $side ] ?? '';
			$x = is_string( $x ) ? trim( $x ) : $x;
			if ( '' === $x || null === $x || is_array( $x ) ) {
				$out[ $side ] = '';
				continue;
			}
			if ( 'auto' === $x ) {
				$out[ $side ] = 'auto';
				$any          = true;
				continue;
			}
			if ( ! is_numeric( $x ) ) {
				$parsed = Utils::parse_size( (string) $x, 'px' );
				if ( ! $parsed || '' === $parsed['size'] ) {
					$out[ $side ] = '';
					continue;
				}
				$x    = $parsed['size'];
				$unit = 'custom' === $unit ? $parsed['unit'] : $unit;
			}
			$out[ $side ] = Utils::number( $x );
			$any          = true;
		}
		if ( ! $any ) {
			return null;
		}
		if ( 'custom' === $unit || ! in_array( $unit, Slider::ALL_UNITS, true ) || '' === $unit ) {
			$unit = 'px';
		}
		if ( $fill ) {
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( '' === $out[ $side ] ) {
					$out[ $side ] = 'px' === $unit ? ( $fill[ $side ] ?? 0 ) : 0;
				}
			}
		}
		$out['unit']   = $unit;
		$out['linked'] = ! empty( $v['isLinked'] ) && 'false' !== $v['isLinked'];
		return $out;
	}

	public static function is_position( string $pos ): bool {
		return in_array( $pos, array( 'center center', 'center left', 'center right', 'top center', 'top left', 'top right', 'bottom center', 'bottom left', 'bottom right' ), true );
	}

	/**
	 * Empty values Elementor saves for untouched controls ("", [], {unit:"px", size:""}, {url:""}…).
	 *
	 * @param mixed $v Value.
	 */
	public static function blank( $v ): bool {
		if ( null === $v || '' === $v || array() === $v ) {
			return true;
		}
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				if ( in_array( $k, array( 'unit', 'isLinked', 'sizes', 'library', 'source', 'is_external', 'nofollow' ), true ) ) {
					continue;
				}
				if ( ! self::blank( $x ) ) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------ Leftovers */

	/**
	 * Settings that were not read (and are not empty), plus the breakpoints that were dropped.
	 *
	 * @param string[] $ignore Keys never worth reporting.
	 * @return array{0: string[], 1: string[]} [ keys, dropped breakpoint names ]
	 */
	public function leftovers( array $ignore = array() ): array {
		$keys = array();
		$bps  = array();
		foreach ( $this->s as $k => $v ) {
			$k = (string) $k;
			if ( isset( $this->used[ $k ] ) || in_array( $k, $ignore, true ) || self::blank( $v ) || self::noise( $k ) ) {
				continue;
			}
			foreach ( self::EXTRA as $suffix ) {
				if ( strlen( $k ) > strlen( $suffix ) && substr( $k, -strlen( $suffix ) ) === $suffix ) {
					$bps[ ltrim( $suffix, '_' ) ] = true;
					continue 2;
				}
			}
			$base = (string) preg_replace( '/_(tablet|mobile)$/', '', $k );
			if ( $base !== $k && ! isset( $this->used[ $base ] ) && ! array_key_exists( $base, $this->s ) ) {
				$k = $base;
			}
			$keys[ $k ] = true;
		}
		return array( array_keys( $keys ), array_keys( $bps ) );
	}

	/** Keys third-party add-ons and Elementor internals write on every element. */
	private static function noise( string $k ): bool {
		return (bool) preg_match( '/^(_title$|__fa4_migrated|_inline_size|_column_size|eael_|premium_|ha_|jet_|pa_|ekit_|uael_|htmega_|wpr_|exad_|bdt_|pp_|_ob_|plus_|tp_|hover_animation_duration)/', $k );
	}
}
