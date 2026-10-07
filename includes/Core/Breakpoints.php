<?php
/**
 * Responsive breakpoints.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Desktop-first breakpoints. Desktop has no media query; the others cascade by max-width,
 * widescreen uses min-width.
 */
final class Breakpoints {

	public const DEFAULTS = array(
		'widescreen'   => array( 'label' => 'Widescreen', 'value' => 2400, 'direction' => 'min', 'enabled' => false ),
		'laptop'       => array( 'label' => 'Laptop', 'value' => 1366, 'direction' => 'max', 'enabled' => false ),
		'tablet_extra' => array( 'label' => 'Tablet extra', 'value' => 1200, 'direction' => 'max', 'enabled' => false ),
		'tablet'       => array( 'label' => 'Tablet', 'value' => 1024, 'direction' => 'max', 'enabled' => true ),
		'mobile_extra' => array( 'label' => 'Mobile extra', 'value' => 880, 'direction' => 'max', 'enabled' => false ),
		'mobile'       => array( 'label' => 'Mobile', 'value' => 767, 'direction' => 'max', 'enabled' => true ),
	);

	/** @var array<string,array>|null */
	private static ?array $cache = null;

	/**
	 * Active breakpoints (excluding desktop) in cascade order: widescreen first, then max-width descending.
	 *
	 * @return array<string, array{label:string,value:int,direction:string}>
	 */
	public static function active(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$configured = Plugin::instance()->kit()->get( 'breakpoints', array() );
		$result     = array();
		foreach ( self::DEFAULTS as $id => $def ) {
			$conf    = is_array( $configured[ $id ] ?? null ) ? $configured[ $id ] : array();
			$enabled = isset( $conf['enabled'] ) ? (bool) $conf['enabled'] : $def['enabled'];
			if ( ! $enabled ) {
				continue;
			}
			$value         = isset( $conf['value'] ) ? absint( $conf['value'] ) : $def['value'];
			$result[ $id ] = array(
				'label'     => $def['label'],
				'value'     => $value > 0 ? $value : $def['value'],
				'direction' => $def['direction'],
			);
		}
		uasort(
			$result,
			static function ( $a, $b ) {
				if ( $a['direction'] !== $b['direction'] ) {
					return 'min' === $a['direction'] ? -1 : 1;
				}
				return $b['value'] <=> $a['value'];
			}
		);
		self::$cache = $result;
		return $result;
	}

	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * All device ids in cascade order, desktop first.
	 *
	 * @return string[]
	 */
	public static function devices(): array {
		return array_merge( array( 'desktop' ), array_keys( self::active() ) );
	}

	public static function suffix( string $device ): string {
		return 'desktop' === $device ? '' : '_' . $device;
	}

	public static function media_query( string $device ): string {
		$active = self::active();
		if ( ! isset( $active[ $device ] ) ) {
			return '';
		}
		$bp = $active[ $device ];
		return 'min' === $bp['direction']
			? '@media (min-width:' . (int) $bp['value'] . 'px)'
			: '@media (max-width:' . (int) $bp['value'] . 'px)';
	}

	/**
	 * Every suffix that may legitimately appear on a responsive key (enabled or not).
	 *
	 * @return string[]
	 */
	public static function all_suffixes(): array {
		return array_map(
			static fn( $id ) => '_' . $id,
			array_keys( self::DEFAULTS )
		);
	}

	/**
	 * Splits "padding_tablet" into ["padding", "tablet"].
	 *
	 * @return array{0:string,1:string}
	 */
	public static function split_key( string $key ): array {
		foreach ( array_keys( self::DEFAULTS ) as $id ) {
			$suffix = '_' . $id;
			if ( strlen( $key ) > strlen( $suffix ) && substr( $key, -strlen( $suffix ) ) === $suffix ) {
				return array( substr( $key, 0, -strlen( $suffix ) ), $id );
			}
		}
		return array( $key, 'desktop' );
	}

	/**
	 * Exported for the editor.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function export(): array {
		$out = array(
			array( 'id' => 'desktop', 'label' => 'Desktop', 'value' => null, 'direction' => 'max' ),
		);
		foreach ( self::active() as $id => $bp ) {
			$out[] = array( 'id' => $id ) + $bp;
		}
		return $out;
	}
}
