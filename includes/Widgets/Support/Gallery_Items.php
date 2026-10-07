<?php
/**
 * Helpers for widgets built on a gallery control (image carousel, image gallery, logo grid).
 *
 * Not a widget: the widget registry only scans includes/Widgets/*.php.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless helpers.
 */
final class Gallery_Items {

	/** Image resolution options (the select is options_dynamic, so registered sizes also validate). */
	public const SIZES = array(
		'thumbnail'    => 'Thumbnail',
		'medium'       => 'Medium',
		'medium_large' => 'Medium large',
		'large'        => 'Large',
		'full'         => 'Full',
	);

	/** Caption sources. */
	public const CAPTIONS = array(
		''        => 'None',
		'caption' => 'Media library caption',
		'alt'     => 'Alt text',
		'title'   => 'Title',
	);

	/**
	 * Valid media values of a gallery setting.
	 *
	 * @param mixed $value Gallery value.
	 * @return array<int, array<string,mixed>>
	 */
	public static function items( $value ): array {
		$out = array();
		foreach ( (array) $value as $item ) {
			if ( is_array( $item ) && ( ! empty( $item['id'] ) || ! empty( $item['url'] ) ) ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * Caption text for an item.
	 *
	 * @param array<string,mixed> $media  Media value.
	 * @param string              $source "", caption, alt or title.
	 */
	public static function caption( array $media, string $source ): string {
		$id = (int) ( $media['id'] ?? 0 );
		switch ( $source ) {
			case 'caption':
				return $id ? (string) wp_get_attachment_caption( $id ) : '';
			case 'alt':
				return self::alt( $media );
			case 'title':
				return $id ? (string) get_the_title( $id ) : '';
		}
		return '';
	}

	/**
	 * Alt text: the value stored with the item, else the media library alt.
	 *
	 * @param array<string,mixed> $media Media value.
	 */
	public static function alt( array $media ): string {
		if ( isset( $media['alt'] ) && '' !== trim( (string) $media['alt'] ) ) {
			return trim( (string) $media['alt'] );
		}
		$id = (int) ( $media['id'] ?? 0 );
		return $id ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
	}

	/**
	 * Full-size URL (lightbox / file links).
	 *
	 * @param array<string,mixed> $media Media value.
	 */
	public static function full_url( array $media ): string {
		$id = (int) ( $media['id'] ?? 0 );
		if ( $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( $url ) {
				return (string) $url;
			}
		}
		return (string) ( $media['url'] ?? '' );
	}

	/**
	 * Placeholder media values used by presets so a freshly inserted widget shows its layout.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function placeholders( int $count ): array {
		$out = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$out[] = array(
				'id'  => 0,
				'url' => UNCODER_WB_URL . 'assets/img/placeholder.svg',
				'alt' => '',
			);
		}
		return $out;
	}
}
