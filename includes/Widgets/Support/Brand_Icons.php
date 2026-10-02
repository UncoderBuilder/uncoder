<?php
/**
 * Social network glyphs used by the Social Icons widget.
 *
 * Not a widget: helpers in includes/Widgets/Support are never registered.
 *
 * Brand glyphs are simplified 24×24 drawings written for this plugin (the LinkedIn, X and GitHub
 * marks follow Simple Icons, CC0). Generic networks (email, phone, website, RSS) use Lucide.
 * Brand names and logos remain trademarks of their owners.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

use Uncoder\Builder\Core\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Network registry: label, brand colour and SVG markup.
 */
final class Brand_Icons {

	/** Label and brand colour per network. */
	public const NETWORKS = array(
		'facebook'  => array( 'Facebook', '#1877f2' ),
		'x'         => array( 'X', '#000000' ),
		'instagram' => array( 'Instagram', '#e4405f' ),
		'linkedin'  => array( 'LinkedIn', '#0a66c2' ),
		'youtube'   => array( 'YouTube', '#ff0000' ),
		'tiktok'    => array( 'TikTok', '#000000' ),
		'pinterest' => array( 'Pinterest', '#bd081c' ),
		'github'    => array( 'GitHub', '#181717' ),
		'dribbble'  => array( 'Dribbble', '#ea4c89' ),
		'behance'   => array( 'Behance', '#1769ff' ),
		'whatsapp'  => array( 'WhatsApp', '#25d366' ),
		'telegram'  => array( 'Telegram', '#26a5e4' ),
		'discord'   => array( 'Discord', '#5865f2' ),
		'threads'   => array( 'Threads', '#000000' ),
		'mastodon'  => array( 'Mastodon', '#6364ff' ),
		'reddit'    => array( 'Reddit', '#ff4500' ),
		'email'     => array( 'Email', '#ea4335' ),
		'phone'     => array( 'Phone', '#16a34a' ),
		'website'   => array( 'Website', '#475569' ),
		'rss'       => array( 'RSS', '#f26522' ),
		'custom'    => array( 'Custom', '' ),
	);

	/** Networks drawn with a Lucide icon. */
	private const LUCIDE = array(
		'email'   => 'mail',
		'phone'   => 'phone',
		'website' => 'globe',
		'rss'     => 'rss',
	);

	/** Inner SVG markup (viewBox 0 0 24 24, fill currentColor, even-odd fill rule). */
	private const GLYPHS = array(
		'facebook'  => '<path d="M13.5 21.5v-8h2.7l.4-3.2h-3.1V8.3c0-.9.3-1.6 1.6-1.6h1.7V3.9c-.3 0-1.3-.1-2.5-.1-2.5 0-4.1 1.5-4.1 4.2v2.3H7.4v3.2h2.8v8z"/>',
		'x'         => '<path d="M17.751 2.961h3.067l-6.7 7.658L22 21.038h-6.172l-4.833-6.32-5.532 6.32H2.395l7.167-8.192L2 2.962h6.328l4.369 5.777zM16.675 19.203h1.699L7.405 4.7H5.582z"/>',
		'instagram' => '<path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm5.25-3.75a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z"/>',
		'linkedin'  => '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452z"/>',
		'youtube'   => '<path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4a2.5 2.5 0 0 0-1.8 1.8C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 0 0 1.8 1.8c1.6.4 7.8.4 7.8.4s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8zM10 15V9l5.2 3z"/>',
		'tiktok'    => '<path d="M12.6 2h3.2c.2 2.3 1.6 4 4.2 4.2v3.2c-1.6.1-3-.4-4.2-1.2v6.6c0 3.4-2.6 6.2-6 6.2S3.8 18.3 3.8 15s2.6-6 5.9-6c.4 0 .8 0 1.1.1v3.3c-.3-.1-.7-.2-1.1-.2-1.5 0-2.7 1.3-2.7 2.8s1.2 2.8 2.7 2.8c1.6 0 2.9-1.2 2.9-3z"/>',
		'pinterest' => '<path d="M12.4 2C7 2 4.2 5.6 4.2 9c0 2 .8 3.8 2.4 4.5.3.1.5 0 .6-.3l.3-1.1c.1-.3 0-.4-.2-.7-.5-.6-.8-1.3-.8-2.4 0-3 2.3-5.8 6-5.8 3.3 0 5.1 2 5.1 4.7 0 3.5-1.6 6.5-3.9 6.5-1.3 0-2.2-1.1-1.9-2.4.4-1.5 1.1-3.2 1.1-4.3 0-1-.5-1.8-1.6-1.8-1.3 0-2.3 1.3-2.3 3.1 0 1.1.4 1.9.4 1.9l-1.5 6.5c-.4 1.9-.1 4.3 0 4.5 0 .1.2.2.3.1.1-.2 1.6-2 2.1-3.8l.8-3.2c.4.8 1.6 1.4 2.9 1.4 3.8 0 6.4-3.5 6.4-8.1C20.4 5.4 16.9 2 12.4 2z"/>',
		'github'    => '<path transform="translate(1.8 1.8) scale(.85)" d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>',
		'dribbble'  => '<g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M19.13 5.09C15.22 9.14 10 10.44 2.25 10.94"/><path d="M21.75 12.84c-6.62-1.41-12.14 1-16.38 6.32"/><path d="M8.56 2.75c4.37 6 6 9.42 8 17.72"/></g>',
		'behance'   => '<path d="M2 6h5.2c2.2 0 3.6 1.1 3.6 2.9 0 1.2-.6 2-1.6 2.4 1.4.4 2.2 1.4 2.2 2.9 0 2.2-1.7 3.8-4.3 3.8H2zm2.8 2.3v2.4h2.1c.9 0 1.4-.4 1.4-1.2s-.5-1.2-1.4-1.2zm0 4.6v2.8h2.3c1 0 1.6-.5 1.6-1.4s-.6-1.4-1.6-1.4zM17.6 9.2c2.6 0 4.2 1.9 4.2 4.6v.6h-6.1c.1 1.3.9 2 2 2 .8 0 1.4-.3 1.7-.9h2.3c-.5 1.8-2 2.8-4 2.8-2.6 0-4.4-1.8-4.4-4.5s1.8-4.6 4.3-4.6zm-1.9 3.6h3.7c-.1-1-.8-1.7-1.8-1.7s-1.7.6-1.9 1.7zM15 6.3h5.4v1.4H15z"/>',
		'whatsapp'  => '<path d="M12 1.9a9.9 9.9 0 0 0-8.6 14.8L2 22l5.4-1.4A9.9 9.9 0 1 0 12 1.9zm0 1.8a8.1 8.1 0 0 1 0 16.2c-1.5 0-2.9-.4-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.1 8.1 0 0 1 12 3.7z"/><path transform="translate(6.84 6.64) scale(.43)" d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384z"/>',
		'telegram'  => '<path d="M21.4 3.3 2.9 10.4c-1.3.5-1.2 1.2-.2 1.5l4.7 1.5 1.8 5.6c.2.6.1.9.8.9.5 0 .7-.2 1-.5l2.3-2.2 4.8 3.5c.9.5 1.5.2 1.7-.8l3.1-14.7c.3-1.3-.5-2.3-1.5-1.9zM9.3 13.6l8.6-5.5c.4-.2.6 0 .3.3l-7 6.4-.3 2.9z"/>',
		'discord'   => '<path d="M19.3 5.3A17.6 17.6 0 0 0 15 4l-.5 1.1a16.3 16.3 0 0 0-5 0L9 4a17.6 17.6 0 0 0-4.3 1.3C2 9.3 1.3 13.2 1.6 17.1a17.7 17.7 0 0 0 5.3 2.7l1.1-1.8c-.6-.2-1.2-.5-1.8-.9l.4-.3a12.6 12.6 0 0 0 10.8 0l.4.3c-.6.4-1.2.7-1.8.9l1.1 1.8a17.6 17.6 0 0 0 5.3-2.7c.4-4.5-.7-8.4-3.1-11.8zM8.7 14.7c-1 0-1.9-1-1.9-2.1s.8-2.1 1.9-2.1 1.9 1 1.9 2.1-.8 2.1-1.9 2.1zm6.6 0c-1 0-1.9-1-1.9-2.1s.8-2.1 1.9-2.1 1.9 1 1.9 2.1-.8 2.1-1.9 2.1z"/>',
		'threads'   => '<path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M17.7 8.3C16.9 5.1 14.8 3.2 12 3.2 7.6 3.2 5 6.7 5 12s2.6 8.8 7 8.8c3.5 0 6.3-2.2 6.3-5.3 0-2.7-2.1-4.3-5.1-4.3-2.4 0-4 1.2-4 3 0 1.6 1.3 2.7 3.1 2.7 2.6 0 3.9-2.1 3.9-5.6"/>',
		'mastodon'  => '<path d="M12 2c-3.1 0-5.4.3-6.6.8C3.6 3.5 2.5 5 2.5 7.4v4.2c0 3.5 1.1 6.3 4.4 7.5 2.4.9 5.6.9 8 .2l-.1-1.9c-1.9.5-4.6.5-5.8-.3-.8-.5-1.2-1.3-1.3-2.2 2.6.6 6.1.7 9 .2 2.6-.5 4.8-2 4.8-4.8V7.4c0-2.4-1.1-3.9-2.9-4.6C17.4 2.3 15.1 2 12 2zm6.2 11h-2.2V9.2c0-1.2-.5-1.8-1.4-1.8-1 0-1.5.7-1.5 2v2.1h-2.2V9.4c0-1.3-.5-2-1.5-2-.9 0-1.4.6-1.4 1.8V13H5.8V9c0-1.3.3-2.3 1-3 .6-.7 1.5-1.1 2.6-1.1 1.1 0 1.7.5 2.2 1.4l.4.7.4-.7c.5-.9 1.1-1.4 2.2-1.4 1.1 0 2 .4 2.6 1.1.7.7 1 1.7 1 3z"/>',
		'reddit'    => '<path d="M20 14c0 3.04-3.58 5.5-8 5.5s-8-2.46-8-5.5 3.58-5.5 8-5.5 8 2.46 8 5.5zM9 12a1.3 1.3 0 1 0 0 2.6A1.3 1.3 0 0 0 9 12zm6 0a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6zm-6.2 4c.8.9 1.9 1.4 3.2 1.4s2.4-.5 3.2-1.4l-.6-.5c-.6.7-1.5 1.1-2.6 1.1s-2-.4-2.6-1.1z"/><circle cx="4.6" cy="11.4" r="2"/><circle cx="19.4" cy="11.4" r="2"/><circle cx="18.6" cy="4.6" r="1.7"/><path fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" d="m12 8.6 1.3-5.2 3.9.9"/>',
	);

	/**
	 * Options for a select control: network => label.
	 *
	 * @return array<string,string>
	 */
	public static function options(): array {
		$out = array();
		foreach ( self::NETWORKS as $key => $def ) {
			$out[ $key ] = 'custom' === $key ? __( 'Custom', 'uncoder' ) : $def[0];
		}
		return $out;
	}

	public static function exists( string $network ): bool {
		return isset( self::NETWORKS[ $network ] );
	}

	public static function label( string $network ): string {
		switch ( $network ) {
			case 'email':
				return __( 'Email', 'uncoder' );
			case 'phone':
				return __( 'Phone', 'uncoder' );
			case 'website':
				return __( 'Website', 'uncoder' );
			case 'custom':
				return __( 'Link', 'uncoder' );
		}
		return self::NETWORKS[ $network ][0] ?? '';
	}

	public static function color( string $network ): string {
		return self::NETWORKS[ $network ][1] ?? '';
	}

	/**
	 * SVG markup for a network ('' for "custom" or unknown networks). All markup is static.
	 */
	public static function svg( string $network, string $class = '' ): string {
		if ( isset( self::LUCIDE[ $network ] ) ) {
			return Icons::render( self::LUCIDE[ $network ], array( 'class' => $class ) );
		}
		if ( ! isset( self::GLYPHS[ $network ] ) ) {
			return '';
		}
		return '<svg class="' . esc_attr( trim( 'uncoder-svg ' . $class ) ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd" aria-hidden="true" focusable="false">' . self::GLYPHS[ $network ] . '</svg>';
	}
}
