<?php
/**
 * Typography group.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Groups;

use Uncoder\Builder\Controls\Group_Type;
use Uncoder\Builder\Core\Fonts;
use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * { preset, family, size*, weight, transform, style, decoration, line_height*, letter_spacing*, word_spacing* }.
 * A preset references a Design System text style and emits CSS variables; explicit fields override it.
 */
class Typography extends Group_Type {

	public const WEIGHTS = array(
		''       => 'Default',
		'100'    => '100 · Thin',
		'200'    => '200 · Extra light',
		'300'    => '300 · Light',
		'400'    => '400 · Regular',
		'500'    => '500 · Medium',
		'600'    => '600 · Semibold',
		'700'    => '700 · Bold',
		'800'    => '800 · Extra bold',
		'900'    => '900 · Black',
		'normal' => 'Normal',
		'bold'   => 'Bold',
	);

	/** Map of field => CSS variable suffix used by Design System presets. */
	public const PRESET_VARS = array(
		'family'         => array( 'font-family', 'ff' ),
		'size'           => array( 'font-size', 'fs' ),
		'weight'         => array( 'font-weight', 'fw' ),
		'transform'      => array( 'text-transform', 'tt' ),
		'style'          => array( 'font-style', 'fst' ),
		'decoration'     => array( 'text-decoration', 'td' ),
		'line_height'    => array( 'line-height', 'lh' ),
		'letter_spacing' => array( 'letter-spacing', 'ls' ),
	);

	public function name(): string {
		return 'typography';
	}

	public function fields( array $control ): array {
		return array(
			'preset'         => array( 'type' => 'select', 'label' => __( 'Text style', 'uncoder' ), 'options_dynamic' => true, 'options' => array() ),
			'family'         => array( 'type' => 'font', 'label' => __( 'Family', 'uncoder' ) ),
			'size'           => array( 'type' => 'slider', 'label' => __( 'Size', 'uncoder' ), 'responsive' => true, 'size_units' => array( 'px', 'rem', 'em', 'vw' ), 'range' => array( 'px' => array( 'min' => 1, 'max' => 200 ) ) ),
			'weight'         => array( 'type' => 'select', 'label' => __( 'Weight', 'uncoder' ), 'options' => self::WEIGHTS, 'allow' => '/^(?:[1-9]\d{0,2}|1000)$/', 'ai' => 'Also any number 1–1000 for variable fonts (e.g. "650").' ),
			'transform'      => array(
				'type'    => 'select',
				'label'   => __( 'Transform', 'uncoder' ),
				'options' => array( '' => 'Default', 'none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize' ),
			),
			'style'          => array( 'type' => 'select', 'label' => __( 'Style', 'uncoder' ), 'options' => array( '' => 'Default', 'normal' => 'Normal', 'italic' => 'Italic', 'oblique' => 'Oblique' ) ),
			'decoration'     => array( 'type' => 'select', 'label' => __( 'Decoration', 'uncoder' ), 'options' => array( '' => 'Default', 'none' => 'None', 'underline' => 'Underline', 'overline' => 'Overline', 'line-through' => 'Line through' ) ),
			'line_height'    => array( 'type' => 'slider', 'label' => __( 'Line height', 'uncoder' ), 'responsive' => true, 'size_units' => array( '', 'em', 'px' ), 'range' => array( '' => array( 'min' => 0.5, 'max' => 3, 'step' => 0.05 ) ) ),
			'letter_spacing' => array( 'type' => 'slider', 'label' => __( 'Letter spacing', 'uncoder' ), 'responsive' => true, 'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => -5, 'max' => 20, 'step' => 0.1 ) ) ),
			'word_spacing'   => array( 'type' => 'slider', 'label' => __( 'Word spacing', 'uncoder' ), 'responsive' => true, 'size_units' => array( 'px', 'em' ) ),
		);
	}

	protected function declarations( array $value, string $device, array $control ): array {
		$d      = array();
		$preset = 'desktop' === $device ? sanitize_key( (string) ( $value['preset'] ?? '' ) ) : '';

		if ( '' !== $preset ) {
			foreach ( self::PRESET_VARS as $field => $var ) {
				// Only an explicit desktop value replaces the preset; breakpoint overrides cascade on top.
				$own = $value[ $field ] ?? null;
				if ( null === $own || '' === $own || ( is_array( $own ) && ( ! isset( $own['size'] ) || '' === $own['size'] ) ) ) {
					$d[] = $var[0] . ':var(--uncoder-t-' . $preset . '-' . $var[1] . ')';
				}
			}
		}

		$family = $this->v( $value, 'family', $device );
		if ( is_string( $family ) && '' !== $family ) {
			$d[] = 'font-family:' . Fonts::css_stack( $family );
		}
		$size = $this->size( $this->v( $value, 'size', $device ) );
		if ( null !== $size ) {
			$d[] = 'font-size:' . $size;
		}
		foreach ( array( 'weight' => 'font-weight', 'transform' => 'text-transform', 'style' => 'font-style', 'decoration' => 'text-decoration' ) as $field => $prop ) {
			$v = $this->v( $value, $field, $device );
			if ( is_string( $v ) && '' !== $v ) {
				$d[] = $prop . ':' . Utils::css_value( $v );
			}
		}
		foreach ( array( 'line_height' => 'line-height', 'letter_spacing' => 'letter-spacing', 'word_spacing' => 'word-spacing' ) as $field => $prop ) {
			$v = $this->size( $this->v( $value, $field, $device ) );
			if ( null !== $v ) {
				$d[] = $prop . ':' . $v;
			}
		}
		return $d;
	}
}
