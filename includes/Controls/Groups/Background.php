<?php
/**
 * Background group.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Groups;

use Uncoder\Builder\Controls\Group_Type;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * { type: classic|gradient|video|slideshow, color, image*, position*, attachment, repeat*, size*,
 *   color_stop, color_b, color_b_stop, gradient_type, gradient_angle, gradient_position, video_url, video_fallback,
 *   slides, slide_duration, slide_transition, slide_speed, ken_burns, slide_size, slide_position }.
 */
class Background extends Group_Type {

	public const POSITIONS = array(
		''              => 'Default',
		'center center' => 'Center center',
		'center left'   => 'Center left',
		'center right'  => 'Center right',
		'top center'    => 'Top center',
		'top left'      => 'Top left',
		'top right'     => 'Top right',
		'bottom center' => 'Bottom center',
		'bottom left'   => 'Bottom left',
		'bottom right'  => 'Bottom right',
	);

	public function name(): string {
		return 'background';
	}

	public function fields( array $control ): array {
		$types = $control['types'] ?? array( 'classic', 'gradient' );
		$opts  = array( '' => 'None' );
		foreach ( $types as $t ) {
			$opts[ $t ] = ucfirst( $t );
		}
		return array(
			'type'              => array( 'type' => 'choose', 'label' => __( 'Type', 'uncoder' ), 'options' => $opts ),
			'color'             => array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ) ),
			'image'             => array( 'type' => 'media', 'label' => __( 'Image', 'uncoder' ), 'responsive' => true ),
			'position'          => array( 'type' => 'select', 'label' => __( 'Position', 'uncoder' ), 'responsive' => true, 'options' => self::POSITIONS ),
			'attachment'        => array( 'type' => 'select', 'label' => __( 'Attachment', 'uncoder' ), 'options' => array( '' => 'Default', 'scroll' => 'Scroll', 'fixed' => 'Fixed' ) ),
			'repeat'            => array( 'type' => 'select', 'label' => __( 'Repeat', 'uncoder' ), 'responsive' => true, 'options' => array( '' => 'Default', 'no-repeat' => 'No repeat', 'repeat' => 'Repeat', 'repeat-x' => 'Repeat X', 'repeat-y' => 'Repeat Y' ) ),
			'size'              => array( 'type' => 'select', 'label' => __( 'Size', 'uncoder' ), 'responsive' => true, 'options' => array( '' => 'Default', 'auto' => 'Auto', 'cover' => 'Cover', 'contain' => 'Contain' ) ),
			'color_stop'        => array( 'type' => 'slider', 'label' => __( 'Location', 'uncoder' ), 'size_units' => array( '%' ) ),
			'color_b'           => array( 'type' => 'color', 'label' => __( 'Second color', 'uncoder' ) ),
			'color_b_stop'      => array( 'type' => 'slider', 'label' => __( 'Location', 'uncoder' ), 'size_units' => array( '%' ) ),
			'gradient_type'     => array( 'type' => 'select', 'label' => __( 'Gradient type', 'uncoder' ), 'options' => array( 'linear' => 'Linear', 'radial' => 'Radial' ) ),
			'gradient_angle'    => array( 'type' => 'slider', 'label' => __( 'Angle', 'uncoder' ), 'size_units' => array( 'deg' ) ),
			'gradient_position' => array( 'type' => 'select', 'label' => __( 'Position', 'uncoder' ), 'options' => self::POSITIONS ),
			'video_url'         => array(
				'type'        => 'text',
				'label'       => __( 'Video', 'uncoder' ),
				'media'       => 'video',
				'placeholder' => __( 'Upload, or paste a YouTube / Vimeo / MP4 link', 'uncoder' ),
			),
			'video_fallback'    => array( 'type' => 'media', 'label' => __( 'Fallback image', 'uncoder' ) ),
			'slides'            => array( 'type' => 'gallery', 'label' => __( 'Images', 'uncoder' ) ),
			'slide_duration'    => array( 'type' => 'number', 'label' => __( 'Duration', 'uncoder' ), 'description' => __( 'Milliseconds each image shows.', 'uncoder' ), 'min' => 1000, 'max' => 30000, 'step' => 500 ),
			'slide_transition'  => array( 'type' => 'select', 'label' => __( 'Transition', 'uncoder' ), 'options' => array( '' => 'Fade', 'slide' => 'Slide' ) ),
			'slide_speed'       => array( 'type' => 'number', 'label' => __( 'Speed', 'uncoder' ), 'description' => __( 'Transition length in milliseconds.', 'uncoder' ), 'min' => 100, 'max' => 5000, 'step' => 100 ),
			'ken_burns'         => array( 'type' => 'select', 'label' => __( 'Ken Burns', 'uncoder' ), 'options' => array( '' => 'Off', 'in' => 'Zoom in', 'out' => 'Zoom out' ) ),
			'slide_size'        => array( 'type' => 'select', 'label' => __( 'Size', 'uncoder' ), 'options' => array( '' => 'Cover', 'contain' => 'Contain', 'auto' => 'Auto' ) ),
			'slide_position'    => array( 'type' => 'select', 'label' => __( 'Position', 'uncoder' ), 'options' => self::POSITIONS ),
		);
	}

	public function normalize( $value, array $control ) {
		if ( is_string( $value ) ) {
			$value = array( 'type' => 'classic', 'color' => $value );
		}
		if ( is_array( $value ) && ! isset( $value['type'] ) ) {
			if ( isset( $value['color_b'] ) ) {
				$value['type'] = 'gradient';
			} elseif ( isset( $value['color'] ) || isset( $value['image'] ) ) {
				$value['type'] = 'classic';
			}
		}
		return parent::normalize( $value, $control );
	}

	protected function declarations( array $value, string $device, array $control ): array {
		$type = (string) ( $value['type'] ?? '' );
		$d    = array();
		if ( '' === $type ) {
			return $d;
		}
		if ( 'gradient' === $type ) {
			if ( 'desktop' !== $device ) {
				return $d;
			}
			$a      = Utils::sanitize_color( (string) ( $value['color'] ?? '' ) );
			$b      = Utils::sanitize_color( (string) ( $value['color_b'] ?? '' ) );
			$a      = '' === $a ? 'transparent' : $a;
			$b      = '' === $b ? 'transparent' : $b;
			$a_stop = $this->size( $value['color_stop'] ?? null ) ?? '0%';
			$b_stop = $this->size( $value['color_b_stop'] ?? null ) ?? '100%';
			if ( 'radial' === ( $value['gradient_type'] ?? 'linear' ) ) {
				$pos = Utils::css_value( $value['gradient_position'] ?? 'center center' );
				$pos = '' === $pos ? 'center center' : $pos;
				$d[] = 'background-color:transparent';
				$d[] = "background-image:radial-gradient(at {$pos}, {$a} {$a_stop}, {$b} {$b_stop})";
			} else {
				$angle = $this->size( $value['gradient_angle'] ?? null ) ?? '180deg';
				$d[]   = 'background-color:transparent';
				$d[]   = "background-image:linear-gradient({$angle}, {$a} {$a_stop}, {$b} {$b_stop})";
			}
			return $d;
		}

		if ( 'desktop' === $device ) {
			$color = Utils::sanitize_color( (string) ( $value['color'] ?? '' ) );
			if ( '' !== $color ) {
				$d[] = 'background-color:' . $color;
			}
			$attachment = (string) ( $value['attachment'] ?? '' );
			if ( '' !== $attachment ) {
				$d[] = 'background-attachment:' . Utils::css_value( $attachment );
			}
		}
		// Videos and slideshows render their own layer; the box keeps the color (and a video's image fallback).
		if ( 'slideshow' === $type || ( 'video' === $type && 'desktop' !== $device ) ) {
			return $d;
		}
		$image = $this->v( $value, 'image', $device );
		if ( is_array( $image ) && ! empty( $image['url'] ) ) {
			$url = Utils::css_url( $image['url'] );
			if ( '' !== $url ) {
				$d[] = 'background-image:url("' . $url . '")';
			}
		}
		foreach ( array( 'position' => 'background-position', 'repeat' => 'background-repeat', 'size' => 'background-size' ) as $field => $prop ) {
			$v = $this->v( $value, $field, $device );
			if ( is_string( $v ) && '' !== $v ) {
				$d[] = $prop . ':' . Utils::css_value( $v );
			}
		}
		return $d;
	}

	public function value_hint( array $control ): string {
		$hint = '{"type":"classic","color":"#fff","image":{"url":"…"},"size":"cover","position":"center center"} or {"type":"gradient","color":"#a","color_b":"#b","gradient_angle":{"size":135,"unit":"deg"}}';
		if ( in_array( 'slideshow', $control['types'] ?? array(), true ) ) {
			$hint .= ' or {"type":"slideshow","slides":[{"url":"…"},{"url":"…"}],"slide_duration":5000,"ken_burns":"in","color":"#111"}';
		}
		return $hint . ' — a color string is accepted';
	}
}
