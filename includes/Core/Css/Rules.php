<?php
/**
 * CSS rule collector grouped by device.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core\Css;

use Uncoder\Builder\Core\Breakpoints;

defined( 'ABSPATH' ) || exit;

/**
 * Collects declarations per device and selector, then prints them with media queries.
 */
final class Rules {

	/** @var array<string, array<string, array<string, string>>> device => selector => property => declaration */
	private array $rules = array();

	/** @var array<string, string[]> device => raw css blocks */
	private array $raw = array();

	/**
	 * @param string|string[] $declarations "prop:value" strings (a single string may contain several separated by ;).
	 */
	public function add( string $selector, $declarations, string $device = 'desktop' ): void {
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return;
		}
		foreach ( (array) $declarations as $declaration ) {
			foreach ( explode( ';', (string) $declaration ) as $decl ) {
				$decl = trim( $decl );
				if ( '' === $decl || false === strpos( $decl, ':' ) ) {
					continue;
				}
				list( $prop, $value ) = array_map( 'trim', explode( ':', $decl, 2 ) );
				if ( '' === $prop || '' === $value ) {
					continue;
				}
				// Later declarations for the same property win (keeps output small).
				$this->rules[ $device ][ $selector ][ strtolower( $prop ) ] = $prop . ':' . $value;
			}
		}
	}

	public function add_raw( string $css, string $device = 'desktop' ): void {
		$css = trim( $css );
		if ( '' !== $css ) {
			$this->raw[ $device ][] = $css;
		}
	}

	public function merge( Rules $other ): void {
		foreach ( $other->rules as $device => $selectors ) {
			foreach ( $selectors as $selector => $props ) {
				foreach ( $props as $prop => $decl ) {
					$this->rules[ $device ][ $selector ][ $prop ] = $decl;
				}
			}
		}
		foreach ( $other->raw as $device => $blocks ) {
			foreach ( $blocks as $block ) {
				$this->raw[ $device ][] = $block;
			}
		}
	}

	public function is_empty(): bool {
		return ! $this->rules && ! $this->raw;
	}

	public function render(): string {
		$out = '';
		foreach ( Breakpoints::devices() as $device ) {
			$block = $this->render_device( $device );
			if ( '' === $block ) {
				continue;
			}
			if ( 'desktop' === $device ) {
				$out .= $block;
			} else {
				$mq = Breakpoints::media_query( $device );
				if ( '' !== $mq ) {
					$out .= $mq . '{' . $block . '}';
				}
			}
		}
		return $out;
	}

	private function render_device( string $device ): string {
		$out = '';
		// Source order is preserved on purpose: merging identical blocks could reorder the cascade.
		foreach ( $this->rules[ $device ] ?? array() as $selector => $props ) {
			$out .= $selector . '{' . implode( ';', $props ) . '}';
		}
		foreach ( $this->raw[ $device ] ?? array() as $raw ) {
			$out .= $raw;
		}
		return $out;
	}
}
