<?php
/**
 * Icon libraries rendered as inline SVG: Lucide (default) plus Font Awesome Free, Phosphor, Bootstrap Icons,
 * Feather, Heroicons and Themify (built from their npm packages by tools/build-icons.mjs).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lucide lives in assets/data/lucide.json (name => inner SVG); every other library is one file in
 * assets/data/icons/<id>.json, loaded only when a page actually uses one of its icons.
 */
final class Icons {

	/** @var array<string,string>|null */
	private static ?array $icons = null;

	/** @var array<int, array<string,mixed>>|null */
	private static ?array $index = null;

	/** @var array<string, array<string,mixed>> */
	private static array $sets = array();

	/** Class-name prefixes people paste from other builders, mapped to our libraries. */
	private const CLASS_PREFIXES = array(
		'/^fa-solid fa-|^fas fa-|^fa fa-/'     => 'fa-solid',
		'/^fa-regular fa-|^far fa-/'           => 'fa-regular',
		'/^fa-brands fa-|^fab fa-/'            => 'fa-brands',
		'/^bi bi-|^bi-/'                       => 'bootstrap',
		'/^ph ph-|^ph-/'                       => 'phosphor',
		'/^ti ti-|^ti-/'                       => 'themify',
		'/^feather-|^fe-/'                     => 'feather',
	);

	/**
	 * Lucide icons.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		if ( null === self::$icons ) {
			self::$icons = self::read( UNCODER_WB_PATH . 'assets/data/lucide.json' );
		}
		return self::$icons;
	}

	/**
	 * The other libraries: [{ id, title, group, count, viewBox, mode, sw? }].
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function libraries(): array {
		if ( null === self::$index ) {
			$data        = self::read( UNCODER_WB_PATH . 'assets/data/icons/index.json' );
			// Plus the sets uploaded under Design System → Custom icons.
			self::$index = array_merge( array_values( array_filter( $data, 'is_array' ) ), \Uncoder\Builder\Site\Custom_Icons::index() );
		}
		return self::$index;
	}

	public static function is_library( string $id ): bool {
		foreach ( self::libraries() as $lib ) {
			if ( ( $lib['id'] ?? '' ) === $id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function set( string $id ): ?array {
		if ( ! self::is_library( $id ) ) {
			return null;
		}
		if ( ! isset( self::$sets[ $id ] ) ) {
			$custom            = 0 === strpos( $id, 'custom-' ) ? \Uncoder\Builder\Site\Custom_Icons::path( $id ) : '';
			self::$sets[ $id ] = self::read( '' !== $custom ? $custom : UNCODER_WB_PATH . 'assets/data/icons/' . $id . '.json' );
		}
		return self::$sets[ $id ] ? self::$sets[ $id ] : null;
	}

	/**
	 * @return array<mixed>
	 */
	private static function read( string $file ): array {
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $data ) ? $data : array();
	}

	public static function exists( string $name, string $library = 'lucide' ): bool {
		if ( 'lucide' === $library ) {
			return isset( self::all()[ $name ] );
		}
		$set = self::set( $library );
		return $set && isset( $set['icons'][ $name ] );
	}

	/**
	 * An uploaded SVG icon drawn in currentColor, printed inline so it takes the icon colour (an <img> cannot):
	 * the markup after "<svg" with the root's class and ARIA attributes removed (render() adds its own). Only for
	 * small files of plain shapes; anything else stays an <img>.
	 */
	private static function inline_svg( int $id ): string {
		static $cache = array();
		if ( $id <= 0 ) {
			return '';
		}
		if ( ! isset( $cache[ $id ] ) ) {
			$cache[ $id ] = '';
			$file         = 'image/svg+xml' === get_post_mime_type( $id ) ? get_attached_file( $id ) : '';
			if ( $file && is_readable( $file ) && filesize( $file ) <= 64 * KB_IN_BYTES ) {
				$svg = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( false !== stripos( $svg, 'currentcolor' ) && Media::is_plain_svg( $svg ) && preg_match( '/<svg\b([^>]*)>(.*)<\/svg>/is', $svg, $m ) ) {
					$root         = (string) preg_replace( '/\s(class|aria-[a-z]+|focusable|role)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $m[1] );
					$cache[ $id ] = $root . '>' . $m[2] . '</svg>';
				}
			}
		}
		return $cache[ $id ];
	}

	/**
	 * "fas fa-house", "bi-house", "phosphor:house" … → [ library, name ], or null for a plain Lucide name.
	 *
	 * @return array{0:string,1:string}|null
	 */
	public static function parse( string $value ): ?array {
		$v = strtolower( trim( $value ) );
		if ( preg_match( '/^([a-z0-9\-]+)[:\/]([a-z0-9\-]+)$/', $v, $m ) && ( 'lucide' === $m[1] || self::is_library( $m[1] ) ) ) {
			return array( $m[1], $m[2] );
		}
		foreach ( self::CLASS_PREFIXES as $pattern => $library ) {
			if ( preg_match( $pattern, $v ) ) {
				return array( $library, (string) preg_replace( $pattern, '', $v ) );
			}
		}
		return null;
	}

	/**
	 * Renders an icon value ({library,value} array or a Lucide name) as SVG markup.
	 *
	 * @param array<string,mixed>|string $icon  Icon value.
	 * @param array<string,mixed>        $attrs Extra attributes.
	 */
	public static function render( $icon, array $attrs = array() ): string {
		if ( is_string( $icon ) ) {
			$parsed = self::parse( $icon );
			$icon   = $parsed ? array( 'library' => $parsed[0], 'value' => $parsed[1] ) : array( 'library' => 'lucide', 'value' => $icon );
		}
		if ( ! is_array( $icon ) ) {
			return '';
		}
		$library = (string) ( $icon['library'] ?? 'lucide' );
		$class   = trim( 'uncoder-svg ' . ( $attrs['class'] ?? '' ) );
		unset( $attrs['class'] );

		if ( 'svg' === $library && ! empty( $icon['url'] ) ) {
			$inline = self::inline_svg( (int) ( $icon['id'] ?? 0 ) );
			if ( '' !== $inline ) {
				return '<svg' . Utils::attrs(
					array_merge(
						array(
							'class'       => $class . ' uncoder-svg--inline',
							'aria-hidden' => 'true',
							'focusable'   => 'false',
						),
						$attrs
					)
				) . $inline;
			}
			return '<img' . Utils::attrs(
				array_merge(
					array(
						'class'    => $class . ' uncoder-svg--img',
						'src'      => $icon['url'],
						'alt'      => '',
						'loading'  => 'lazy',
						'decoding' => 'async',
					),
					$attrs
				)
			) . '>';
		}
		$name = (string) ( $icon['value'] ?? '' );
		$base = array(
			'class'       => $class,
			'xmlns'       => 'http://www.w3.org/2000/svg',
			'aria-hidden' => 'true',
			'focusable'   => 'false',
		);

		if ( 'lucide' === $library ) {
			$body = self::all()[ $name ] ?? '';
			if ( '' === $body ) {
				return '';
			}
			$base += array(
				'viewBox'         => '0 0 24 24',
				'fill'            => 'none',
				'stroke'          => 'currentColor',
				'stroke-width'    => '2',
				'stroke-linecap'  => 'round',
				'stroke-linejoin' => 'round',
			);
		} else {
			$set   = self::set( $library );
			$entry = $set['icons'][ $name ] ?? null;
			if ( ! $set || null === $entry ) {
				return '';
			}
			$body            = is_array( $entry ) ? (string) $entry[0] : (string) $entry;
			$base['viewBox'] = is_array( $entry ) && ! empty( $entry[1] ) ? (string) $entry[1] : (string) $set['viewBox'];
			if ( 'stroke' === ( $set['mode'] ?? 'fill' ) ) {
				$base += array(
					'fill'            => 'none',
					'stroke'          => 'currentColor',
					'stroke-width'    => (string) ( $set['sw'] ?? 2 ),
					'stroke-linecap'  => 'round',
					'stroke-linejoin' => 'round',
				);
			} else {
				$base['fill'] = 'currentColor';
			}
			// Wide icons (brands, some Font Awesome glyphs) keep their proportions inside the square box.
			$base['class'] .= ' uncoder-svg--' . $library;
		}
		// $body comes from the bundled, trusted icon data files.
		return '<svg' . Utils::attrs( array_merge( $base, $attrs ) ) . '>' . $body . '</svg>';
	}

	/**
	 * Icon names matching a query (name or search terms), from one library or all of them.
	 * Results are "name" for Lucide and "library:name" for the others when searching everything.
	 *
	 * @return string[]
	 */
	public static function search( string $query, int $limit = 60, string $library = 'lucide' ): array {
		$query = strtolower( trim( $query ) );
		$out   = array();
		$libs  = 'all' === $library ? array_merge( array( 'lucide' ), array_column( self::libraries(), 'id' ) ) : array( $library );
		foreach ( $libs as $lib ) {
			if ( 'lucide' === $lib ) {
				$names = array_keys( self::all() );
				$tags  = array();
			} else {
				$set   = self::set( $lib );
				$names = $set ? array_keys( (array) $set['icons'] ) : array();
				$tags  = $set['tags'] ?? array();
			}
			foreach ( $names as $name ) {
				$hit = '' === $query || false !== strpos( (string) $name, $query );
				if ( ! $hit && isset( $tags[ $name ] ) ) {
					foreach ( (array) $tags[ $name ] as $term ) {
						if ( false !== strpos( (string) $term, $query ) ) {
							$hit = true;
							break;
						}
					}
				}
				if ( $hit ) {
					$out[] = 'all' === $library && 'lucide' !== $lib ? $lib . ':' . $name : (string) $name;
					if ( count( $out ) >= $limit ) {
						return $out;
					}
				}
			}
		}
		return $out;
	}
}
