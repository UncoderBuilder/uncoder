<?php
/**
 * Transform group (uses CSS variables so breakpoints and hover compose).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Groups;

use Uncoder\Builder\Controls\Group_Type;
use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Css\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * { translate_x*, translate_y*, rotate*, scale*, skew_x*, skew_y* }.
 */
class Transform extends Group_Type {

	private const VARS = array(
		'translate_x' => '--uncoder-tx',
		'translate_y' => '--uncoder-ty',
		'rotate'      => '--uncoder-rot',
		'scale'       => '--uncoder-sc',
		'skew_x'      => '--uncoder-skx',
		'skew_y'      => '--uncoder-sky',
	);

	public const TRANSFORM = 'transform:translate(var(--uncoder-tx,0),var(--uncoder-ty,0)) rotate(var(--uncoder-rot,0deg)) scale(var(--uncoder-sc,1)) skew(var(--uncoder-skx,0deg),var(--uncoder-sky,0deg))';

	public function name(): string {
		return 'transform';
	}

	public function fields( array $control ): array {
		return array(
			'translate_x' => array( 'type' => 'slider', 'label' => __( 'Offset X', 'uncoder' ), 'responsive' => true, 'size_units' => array( 'px', '%', 'em', 'rem', 'vw' ) ),
			'translate_y' => array( 'type' => 'slider', 'label' => __( 'Offset Y', 'uncoder' ), 'responsive' => true, 'size_units' => array( 'px', '%', 'em', 'rem', 'vh' ) ),
			'rotate'      => array( 'type' => 'slider', 'label' => __( 'Rotate', 'uncoder' ), 'responsive' => true, 'size_units' => array( 'deg' ) ),
			'scale'       => array( 'type' => 'number', 'label' => __( 'Scale', 'uncoder' ), 'responsive' => true, 'min' => 0, 'max' => 5 ),
			'skew_x'      => array( 'type' => 'slider', 'label' => __( 'Skew X', 'uncoder' ), 'responsive' => true, 'size_units' => array( 'deg' ) ),
			'skew_y'      => array( 'type' => 'slider', 'label' => __( 'Skew Y', 'uncoder' ), 'responsive' => true, 'size_units' => array( 'deg' ) ),
		);
	}

	public function group_css( array $value, array $control, string $selector, Rules $rules ): void {
		$used = false;
		foreach ( array_keys( self::VARS ) as $field ) {
			if ( $this->any( $value, $field ) ) {
				$used = true;
				break;
			}
		}
		if ( ! $used ) {
			return;
		}
		foreach ( Breakpoints::devices() as $device ) {
			$decls = $this->declarations( $value, $device, $control );
			if ( 'desktop' === $device ) {
				$decls[] = self::TRANSFORM;
			}
			if ( $decls ) {
				$rules->add( $selector, $decls, $device );
			}
		}
	}

	protected function declarations( array $value, string $device, array $control ): array {
		$d = array();
		foreach ( self::VARS as $field => $var ) {
			$v = $this->v( $value, $field, $device );
			if ( 'scale' === $field ) {
				if ( null !== $v && '' !== $v && is_numeric( $v ) ) {
					$d[] = $var . ':' . (float) $v;
				}
				continue;
			}
			$size = $this->size( $v );
			if ( null !== $size ) {
				$d[] = $var . ':' . $size;
			}
		}
		return $d;
	}
}
