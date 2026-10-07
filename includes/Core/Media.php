<?php
/**
 * Media helpers: generated SVG placeholders and logos.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress does not treat SVG attachments as images. Uncoder only creates SVGs it generates itself
 * (placeholders, wordmark logos) and flags them, so for those files it reports their size so
 * wp_get_attachment_image(), the custom logo and the image widgets work. SVG uploads stay disabled.
 */
final class Media {

	public const META_GENERATED = '_uncoder_wb_generated';
	public const META_SOURCE    = '_uncoder_wb_source_url';

	public function register(): void {
		add_filter( 'image_downsize', array( $this, 'downsize' ), 10, 3 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'image_attributes' ), 10, 2 );
	}

	public static function is_generated_svg( int $id ): bool {
		return 'image/svg+xml' === get_post_mime_type( $id ) && '' !== (string) get_post_meta( $id, self::META_GENERATED, true );
	}

	/**
	 * An attachment usable where an image is expected.
	 */
	public static function is_image( int $id ): bool {
		return wp_attachment_is_image( $id ) || self::is_generated_svg( $id );
	}

	/**
	 * @param false|array $out  Short-circuit value.
	 * @param int         $id   Attachment id.
	 * @param mixed       $size Size.
	 * @return false|array
	 */
	public function downsize( $out, $id, $size ) {
		if ( false !== $out || ! self::is_generated_svg( (int) $id ) ) {
			return $out;
		}
		$meta = wp_get_attachment_metadata( (int) $id );
		$url  = wp_get_attachment_url( (int) $id );
		if ( ! $url ) {
			return false;
		}
		return array( $url, (int) ( $meta['width'] ?? 0 ), (int) ( $meta['height'] ?? 0 ), false );
	}

	/**
	 * @param array<string,string> $attr       Attributes.
	 * @param \WP_Post             $attachment Attachment.
	 * @return array<string,string>
	 */
	public function image_attributes( $attr, $attachment ): array {
		if ( $attachment instanceof \WP_Post && self::is_generated_svg( $attachment->ID ) ) {
			unset( $attr['srcset'], $attr['sizes'] );
		}
		return (array) $attr;
	}

	/**
	 * Shapes a plain SVG may hold: no animation (<set>, <animate> can rewrite links), links, embeds or scripts. Filter
	 * effects (blurs, shadows, colour changes) only repaint the shapes, so they count as plain; <feImage> can load a
	 * picture and does not.
	 */
	public const PLAIN_SVG_TAGS = array( 'svg', 'title', 'desc', 'defs', 'lineargradient', 'radialgradient', 'stop', 'pattern', 'clippath', 'mask', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'filter', 'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fefunca', 'fefuncr', 'fefuncg', 'fefuncb', 'fecomposite', 'feflood', 'fegaussianblur', 'femerge', 'femergenode', 'feoffset', 'fedropshadow', 'femorphology', 'feturbulence', 'fedisplacementmap' );

	/**
	 * Whether SVG markup holds only plain shapes: no scripts, styles, event handlers, javascript: links, foreign
	 * objects, embeds or outside references (a Site Kit imports such files; icons print them inline).
	 */
	public static function is_plain_svg( string $svg ): bool {
		if ( '' === $svg || strlen( $svg ) > 2 * MB_IN_BYTES || ! preg_match( '/<svg[\s>]/i', $svg ) ) {
			return false;
		}
		preg_match_all( '/<\s*([a-zA-Z][a-zA-Z0-9:._-]*)/', $svg, $tags );
		if ( array_diff( array_map( 'strtolower', $tags[1] ), self::PLAIN_SVG_TAGS ) ) {
			return false;
		}
		return ! preg_match( '/<!|<\?(?!xml\s)|\son[a-z]+\s*=|javascript:|href\s*=\s*["\']\s*(?!#)/i', $svg );
	}

	/**
	 * Saves generated SVG markup as an attachment.
	 *
	 * @return int|\WP_Error Attachment id.
	 */
	public static function insert_svg( string $svg, string $filename, int $width, int $height, string $title, string $kind, int $parent = 0 ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'uploads', (string) $upload['error'] );
		}
		$name = wp_unique_filename( $upload['path'], sanitize_file_name( $filename ) . '.svg' );
		$path = trailingslashit( $upload['path'] ) . $name;
		if ( false === file_put_contents( $path, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new \WP_Error( 'write', 'Could not write the file to the uploads folder.' );
		}
		$id = wp_insert_attachment(
			array(
				'post_title'     => sanitize_text_field( $title ),
				'post_mime_type' => 'image/svg+xml',
				'post_status'    => 'inherit',
				'guid'           => trailingslashit( $upload['url'] ) . $name,
			),
			$path,
			$parent,
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $path );
			return $id;
		}
		update_post_meta( (int) $id, self::META_GENERATED, sanitize_key( $kind ) );
		wp_update_attachment_metadata(
			(int) $id,
			array(
				'width'  => $width,
				'height' => $height,
				'file'   => _wp_relative_upload_path( $path ),
			)
		);
		return (int) $id;
	}
}
