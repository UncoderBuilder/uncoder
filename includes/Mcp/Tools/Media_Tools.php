<?php
/**
 * Media tools: library, imports, openly licensed image search, generated placeholders and logos.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Icons;
use Uncoder\Builder\Core\Media;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Mcp\Call;
use Uncoder\Builder\Mcp\Registry;
use Uncoder\Builder\Mcp\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * list_media, upload_media, search_images, set_image_alt, generate_placeholder, generate_logo.
 */
final class Media_Tools {

	private const MAX_BYTES = 15 * MB_IN_BYTES;

	private const ALLOWED = array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'avif' => 'image/avif',
	);

	/** Openverse image search (openly licensed images); see readme.txt → External services. */
	private const OPENVERSE = 'https://api.openverse.org/v1/images/';

	public function register( Registry $r ): void {
		$r->add(
			array(
				'name'        => 'list_media',
				'title'       => 'List media',
				'description' => 'Images in the media library with id, URL, alt text, size and orientation. Use an id in image settings: {"image": {"id": 123}}.',
				'scope'       => 'read',
				'annotations' => array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'search'      => array( 'type' => 'string' ),
						'missing_alt' => array( 'type' => 'boolean', 'description' => 'Only images without alt text.' ),
						'limit'       => array( 'type' => 'integer', 'description' => 'Max 100 (default 30).' ),
						'page'        => array( 'type' => 'integer' ),
					),
				),
				'callback'    => array( $this, 'list_media' ),
			)
		);

		$r->add(
			array(
				'name'        => 'upload_media',
				'title'       => 'Upload media',
				'description' => 'Imports an image into the media library from a public http(s) URL (e.g. a search_images result) or base64 data, with alt text. JPEG, PNG, GIF, WebP, AVIF up to 15 MB. The same URL is only imported once. Returns the attachment id.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'url'         => array( 'type' => 'string' ),
						'data'        => array( 'type' => 'string', 'description' => 'Base64 image data (or a data: URI) instead of url.' ),
						'filename'    => array( 'type' => 'string', 'description' => 'File name (recommended with data), e.g. "team-photo.jpg".' ),
						'alt'         => array( 'type' => 'string', 'description' => 'Describe the image for screen readers ("" for decorative images).' ),
						'title'       => array( 'type' => 'string' ),
						'caption'     => array( 'type' => 'string' ),
						'attribution' => array( 'type' => 'string', 'description' => 'Credit line required by the license (from search_images).' ),
						'post_id'     => array( 'type' => 'integer', 'description' => 'Attach to this post/page.' ),
					),
				),
				'callback'    => array( $this, 'upload' ),
			)
		);

		$r->add(
			array(
				'name'        => 'search_images',
				'title'       => 'Search images',
				'description' => 'Searches openly licensed photos (Openverse: Creative Commons / public domain, commercial use and modification allowed) and returns URLs with license and attribution. Import one with upload_media (pass the attribution).',
				'scope'       => 'read',
				'annotations' => array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => true ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'query'       => array( 'type' => 'string', 'description' => 'Plain words, e.g. "barista pouring latte".' ),
						'orientation' => array( 'type' => 'string', 'enum' => array( 'landscape', 'portrait', 'square' ) ),
						'size'        => array( 'type' => 'string', 'enum' => array( 'small', 'medium', 'large' ), 'description' => 'Use "large" for heroes and full-width backgrounds (larger originals, usually ≥ 1600px wide).' ),
						'limit'       => array( 'type' => 'integer', 'description' => 'Max 20 (default 10).' ),
						'page'        => array( 'type' => 'integer' ),
					),
					'required'   => array( 'query' ),
				),
				'callback'    => array( $this, 'search_images' ),
			)
		);

		$r->add(
			array(
				'name'        => 'set_image_alt',
				'title'       => 'Set image alt text',
				'description' => 'Sets alt text (and optionally title/caption) of a media library image. Image widgets use the library alt text by default.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'alt'     => array( 'type' => 'string' ),
						'title'   => array( 'type' => 'string' ),
						'caption' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id', 'alt' ),
				),
				'callback'    => array( $this, 'set_alt' ),
			)
		);

		$r->add(
			array(
				'name'        => 'generate_placeholder',
				'title'       => 'Generate placeholder',
				'description' => 'Creates a clean labelled placeholder image (SVG) in the media library for layouts where no suitable photo exists yet (e.g. "Team photo", "Product screenshot"). Replace it with a real image later.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'label'      => array( 'type' => 'string' ),
						'width'      => array( 'type' => 'integer', 'description' => 'Default 1200.' ),
						'height'     => array( 'type' => 'integer', 'description' => 'Default 800.' ),
						'background' => array( 'type' => 'string', 'description' => 'Hex color (default a soft neutral).' ),
						'color'      => array( 'type' => 'string', 'description' => 'Text/icon hex color.' ),
						'icon'       => array( 'type' => 'string', 'description' => 'Lucide icon name (default "image").' ),
					),
				),
				'callback'    => array( $this, 'placeholder' ),
			)
		);

		$r->add(
			array(
				'name'        => 'generate_logo',
				'title'       => 'Generate logo',
				'description' => 'Creates a simple SVG wordmark logo (optional Lucide icon mark) in the media library. set_as_site_logo:true makes it the site logo used by the site-logo widget. Good as a starting point until the brand has a real logo.',
				'scope'       => 'content',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'text'             => array( 'type' => 'string', 'description' => 'Brand name.' ),
						'icon'             => array( 'type' => 'string', 'description' => 'Lucide icon name for the mark (search_icons), optional.' ),
						'color'            => array( 'type' => 'string', 'description' => 'Text color (hex).' ),
						'icon_color'       => array( 'type' => 'string', 'description' => 'Mark color (hex, default the text color).' ),
						'icon_background'  => array( 'type' => 'string', 'description' => 'Rounded tile behind the mark (hex, optional).' ),
						'style'            => array( 'type' => 'string', 'enum' => array( 'sans', 'serif', 'mono', 'rounded' ) ),
						'weight'           => array( 'type' => 'integer', 'description' => '400–900 (default 700).' ),
						'uppercase'        => array( 'type' => 'boolean' ),
						'letter_spacing'   => array( 'type' => 'number', 'description' => 'In em, e.g. -0.02 or 0.12.' ),
						'layout'           => array( 'type' => 'string', 'enum' => array( 'horizontal', 'stacked', 'icon' ) ),
						'set_as_site_logo' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'text' ),
				),
				'callback'    => array( $this, 'logo' ),
			)
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_media( array $a ) {
		// Like the WordPress media library: only for users who can upload files.
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'forbidden', 'The media library needs the upload_files capability.' );
		}
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => min( 100, max( 1, (int) ( $a['limit'] ?? 30 ) ) ),
			'paged'          => max( 1, (int) ( $a['page'] ?? 1 ) ),
			's'              => (string) ( $a['search'] ?? '' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( ! empty( $a['missing_alt'] ) ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_wp_attachment_image_alt',
					'value' => '',
				),
			);
		}
		$query = new \WP_Query( $args );
		return array(
			'total' => (int) $query->found_posts,
			'items' => array_map( array( $this, 'info' ), $query->posts ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function upload( array $a, Call $call ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'forbidden', 'You cannot upload files.' );
		}
		$post_id = absint( $a['post_id'] ?? 0 );
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden', 'You cannot attach media to post #' . $post_id . '.' );
		}
		$url = trim( (string) ( $a['url'] ?? '' ) );
		if ( '' === $url && empty( $a['data'] ) ) {
			return new WP_Error( 'invalid', 'Pass url (public http/https) or data (base64).' );
		}

		// Already imported?
		if ( '' !== $url ) {
			$existing = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => Media::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => md5( $url ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			if ( $existing ) {
				$id = (int) $existing[0];
				if ( isset( $a['alt'] ) && '' === (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
					update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $a['alt'] ) );
				}
				return array(
					'media'  => $this->info( get_post( $id ) ),
					'reused' => true,
				);
			}
		}

		$tmp = '' !== $url ? self::download( $url ) : $this->decode( (string) $a['data'] );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$filename = sanitize_file_name( (string) ( $a['filename'] ?? '' ) );
		// Descriptive file names help image SEO: derive one from the alt text.
		if ( '' === $filename && ! empty( $a['alt'] ) ) {
			$filename = substr( sanitize_title( (string) $a['alt'] ), 0, 60 );
		}
		if ( '' === $filename && '' !== $url ) {
			$filename = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		}
		$filename = preg_replace( '/\.[^.]+$/', '', (string) $filename );
		if ( '' === $filename ) {
			$filename = sanitize_title( (string) ( $a['title'] ?? $a['alt'] ?? 'image' ) );
		}
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$mime = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
		$ext  = array_search( $mime, self::ALLOWED, true );
		if ( false === $ext ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'invalid', 'The file is not a supported image (JPEG, PNG, GIF, WebP, AVIF).' );
		}
		$file = array(
			'name'     => substr( $filename, 0, 80 ) . '.' . ( 'jpeg' === $ext ? 'jpg' : $ext ),
			'tmp_name' => $tmp,
		);
		$check = wp_check_filetype_and_ext( $tmp, $file['name'] );
		if ( empty( $check['type'] ) || ! in_array( $check['type'], self::ALLOWED, true ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'invalid', 'The image type is not allowed on this site.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_sideload(
			$file,
			$post_id,
			isset( $a['title'] ) ? sanitize_text_field( (string) $a['title'] ) : ( ! empty( $a['alt'] ) ? wp_trim_words( sanitize_text_field( (string) $a['alt'] ), 8, '' ) : null ),
			array(
				'post_excerpt' => sanitize_text_field( (string) ( $a['caption'] ?? '' ) ),
			)
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) ( $a['alt'] ?? '' ) ) );
		if ( '' !== $url ) {
			update_post_meta( $id, Media::META_SOURCE, md5( $url ) );
		}
		if ( ! empty( $a['attribution'] ) ) {
			update_post_meta( $id, '_uncoder_wb_attribution', sanitize_text_field( (string) $a['attribution'] ) );
			if ( empty( $a['caption'] ) ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $id,
							'post_excerpt' => sanitize_text_field( (string) $a['attribution'] ),
						)
					)
				);
			}
		}
		if ( ! isset( $a['alt'] ) ) {
			$call->warn( 'No alt text given: add one with set_image_alt unless the image is decorative.' );
		}
		$call->object_id = (int) $id;
		$call->summary   = 'Imported image "' . get_the_title( $id ) . '"';
		return array(
			'media' => $this->info( get_post( $id ) ),
			'use'   => array( 'image' => array( 'id' => (int) $id ) ),
		);
	}

	/**
	 * Imports a public image URL into the media library once (template import, not MCP-specific).
	 * Returns the existing attachment when the same URL was imported before.
	 *
	 * @return int|WP_Error Attachment id.
	 */
	public static function import_url( string $url, string $alt = '' ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'forbidden', 'You cannot upload files.' );
		}
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => Media::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => md5( $url ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}
		$tmp = self::download( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$ext  = array_search( is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '', self::ALLOWED, true );
		$name = preg_replace( '/\.[^.]+$/', '', sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) );
		$file = array(
			'name'     => substr( '' !== $name ? $name : 'image', 0, 80 ) . '.' . ( 'jpeg' === $ext ? 'jpg' : $ext ),
			'tmp_name' => $tmp,
		);
		$check = false !== $ext ? wp_check_filetype_and_ext( $tmp, $file['name'] ) : array();
		if ( empty( $check['type'] ) || ! in_array( $check['type'], self::ALLOWED, true ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'invalid', 'The file is not a supported image (JPEG, PNG, GIF, WebP, AVIF).' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		update_post_meta( $id, Media::META_SOURCE, md5( $url ) );
		return (int) $id;
	}

	/**
	 * Downloads a public URL to a temp file (SSRF-safe: wp_safe_remote_get blocks private/reserved hosts).
	 *
	 * @return string|WP_Error Temp path.
	 */
	private static function download( string $url ) {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'invalid', 'The URL must be a public http(s) address.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = wp_tempnam( 'uncoder-media' );
		if ( ! $tmp ) {
			return new WP_Error( 'tmp', 'Could not create a temporary file.' );
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 25,
				'redirection'         => 3,
				'stream'              => true,
				'filename'            => $tmp,
				'limit_response_size' => self::MAX_BYTES,
				'user-agent'          => 'Uncoder/' . UNCODER_WB_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'download', 'Download failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'download', sprintf( 'Download failed (HTTP %d).', $code ) );
		}
		if ( filesize( $tmp ) >= self::MAX_BYTES ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'too_large', 'The image is larger than 15 MB.' );
		}
		return $tmp;
	}

	/**
	 * @return string|WP_Error Temp path.
	 */
	private function decode( string $data ) {
		if ( preg_match( '#^data:image/[a-z0-9.+-]+;base64,#i', $data, $m ) ) {
			$data = substr( $data, strlen( $m[0] ) );
		}
		if ( strlen( $data ) > self::MAX_BYTES * 4 / 3 ) {
			return new WP_Error( 'too_large', 'The image is larger than 15 MB.' );
		}
		$bytes = base64_decode( preg_replace( '/\s+/', '', $data ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- image upload payload.
		if ( false === $bytes || '' === $bytes ) {
			return new WP_Error( 'invalid', 'data is not valid base64.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = wp_tempnam( 'uncoder-media' );
		if ( ! $tmp || false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error( 'tmp', 'Could not write a temporary file.' );
		}
		return $tmp;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function search_images( array $a ) {
		if ( ! Settings::get( 'image_search' ) ) {
			return new WP_Error( 'disabled', 'Image search is turned off in Uncoder → AI & MCP settings. Use list_media, generate_placeholder, or ask the user for images.' );
		}
		$query = trim( sanitize_text_field( (string) $a['query'] ) );
		if ( '' === $query ) {
			return new WP_Error( 'invalid', 'query is empty.' );
		}
		$params = array(
			'q'            => $query,
			'page_size'    => min( 20, max( 1, (int) ( $a['limit'] ?? 10 ) ) ),
			'page'         => max( 1, min( 20, (int) ( $a['page'] ?? 1 ) ) ),
			'license_type' => 'commercial,modification',
			'mature'       => 'false',
		);
		$ratio = array(
			'landscape' => 'wide',
			'portrait'  => 'tall',
			'square'    => 'square',
		)[ (string) ( $a['orientation'] ?? '' ) ] ?? '';
		if ( '' !== $ratio ) {
			$params['aspect_ratio'] = $ratio;
		}
		if ( in_array( $a['size'] ?? '', array( 'small', 'medium', 'large' ), true ) ) {
			$params['size'] = $a['size'];
		}
		$cache_key = 'uncoder_wb_img_' . md5( (string) wp_json_encode( $params ) );
		$data      = get_transient( $cache_key );
		if ( ! is_array( $data ) ) {
			$response = wp_safe_remote_get(
				add_query_arg( array_map( 'rawurlencode', $params ), self::OPENVERSE ),
				array(
					'timeout'    => 15,
					'user-agent' => 'Uncoder/' . UNCODER_WB_VERSION . ' (WordPress page builder; +' . \Uncoder\Builder\Core\Brand::URL . ')',
					'headers'    => array( 'Accept' => 'application/json' ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'search', 'Image search is unavailable: ' . $response->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				return new WP_Error( 'search', sprintf( 'Image search failed (HTTP %d). Try again later or use generate_placeholder.', $code ) );
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) ) {
				return new WP_Error( 'search', 'Unexpected response from the image search.' );
			}
			set_transient( $cache_key, $data, DAY_IN_SECONDS );
		}
		$items = array();
		foreach ( (array) ( $data['results'] ?? array() ) as $r ) {
			if ( empty( $r['url'] ) ) {
				continue;
			}
			$license = strtoupper( (string) ( $r['license'] ?? '' ) );
			$items[] = array(
				'url'         => esc_url_raw( (string) $r['url'] ),
				'thumbnail'   => esc_url_raw( (string) ( $r['thumbnail'] ?? '' ) ),
				'width'       => (int) ( $r['width'] ?? 0 ),
				'height'      => (int) ( $r['height'] ?? 0 ),
				'title'       => sanitize_text_field( (string) ( $r['title'] ?? '' ) ),
				'creator'     => sanitize_text_field( (string) ( $r['creator'] ?? '' ) ),
				'license'     => trim( ( 'CC0' === $license || 'PDM' === $license ? $license : 'CC ' . $license ) . ' ' . ( $r['license_version'] ?? '' ) ),
				'license_url' => esc_url_raw( (string) ( $r['license_url'] ?? '' ) ),
				'source'      => esc_url_raw( (string) ( $r['foreign_landing_url'] ?? '' ) ),
				'attribution' => sanitize_text_field( (string) ( $r['attribution'] ?? '' ) ),
			);
		}
		return array(
			'query' => $query,
			'total' => (int) ( $data['result_count'] ?? count( $items ) ),
			'items' => $items,
			'note'  => $items ? 'Pick images that fit the brand; prefer large ones (width ≥ 1600 for heroes). Import with upload_media {url, alt, attribution}. CC BY licenses require the attribution to be shown (it is stored as the caption).' : 'Nothing found: try simpler words, or use generate_placeholder.',
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function set_alt( array $a, Call $call ) {
		$id = absint( $a['id'] );
		if ( 'attachment' !== get_post_type( $id ) ) {
			return new WP_Error( 'not_found', 'No media item with id ' . $id . '.' );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'forbidden', 'You cannot edit this media item.' );
		}
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $a['alt'] ) );
		$update = array( 'ID' => $id );
		if ( isset( $a['title'] ) ) {
			$update['post_title'] = sanitize_text_field( (string) $a['title'] );
		}
		if ( isset( $a['caption'] ) ) {
			$update['post_excerpt'] = sanitize_text_field( (string) $a['caption'] );
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( wp_slash( $update ) );
		}
		$call->object_id = $id;
		$call->summary   = 'Alt text for media #' . $id;
		return array( 'media' => $this->info( get_post( $id ) ) );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function placeholder( array $a, Call $call ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'forbidden', 'You cannot upload files.' );
		}
		$w     = max( 16, min( 4000, (int) ( $a['width'] ?? 1200 ) ) );
		$h     = max( 16, min( 4000, (int) ( $a['height'] ?? 800 ) ) );
		$bg    = $this->hex( (string) ( $a['background'] ?? '' ), '#e8ebf0' );
		$fg    = $this->hex( (string) ( $a['color'] ?? '' ), $this->readable_on( $bg ) );
		$label = sanitize_text_field( (string) ( $a['label'] ?? '' ) );
		$icon  = $this->icon_body( (string) ( $a['icon'] ?? 'image' ) );
		$min   = min( $w, $h );
		$isz   = (int) round( $min * 0.16 );
		$fs    = max( 12, (int) round( $min * 0.055 ) );
		$cy    = $h / 2 - ( '' !== $label ? $fs * 0.9 : 0 );
		$grid  = max( 16, (int) round( $min / 12 ) );

		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">';
		$svg .= '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $bg . '"/><stop offset="1" stop-color="' . $this->shade( $bg, -0.07 ) . '"/></linearGradient>';
		$svg .= '<pattern id="p" width="' . $grid . '" height="' . $grid . '" patternUnits="userSpaceOnUse"><path d="M ' . $grid . ' 0 L 0 0 0 ' . $grid . '" fill="none" stroke="' . $fg . '" stroke-opacity="0.07" stroke-width="1"/></pattern></defs>';
		$svg .= '<rect width="100%" height="100%" fill="url(#g)"/><rect width="100%" height="100%" fill="url(#p)"/>';
		if ( '' !== $icon ) {
			$scale = $isz / 24;
			$svg  .= '<g transform="translate(' . round( $w / 2 - $isz / 2, 2 ) . ' ' . round( $cy - $isz / 2, 2 ) . ') scale(' . round( $scale, 4 ) . ')" fill="none" stroke="' . $fg . '" stroke-opacity="0.55" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $icon . '</g>';
		}
		if ( '' !== $label ) {
			$svg .= '<text x="50%" y="' . round( $cy + $isz / 2 + $fs * 1.6, 2 ) . '" text-anchor="middle" font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif" font-size="' . $fs . '" font-weight="600" fill="' . $fg . '" fill-opacity="0.75">' . esc_xml( $label ) . '</text>';
		}
		$svg .= '<text x="50%" y="' . ( $h - max( 10, (int) round( $fs * 0.8 ) ) ) . '" text-anchor="middle" font-family="ui-monospace,Menlo,Consolas,monospace" font-size="' . max( 10, (int) round( $fs * 0.55 ) ) . '" fill="' . $fg . '" fill-opacity="0.4">' . $w . ' × ' . $h . '</text>';
		$svg .= '</svg>';

		$id = Media::insert_svg( $svg, 'placeholder-' . ( '' !== $label ? sanitize_title( $label ) : $w . 'x' . $h ), $w, $h, '' !== $label ? 'Placeholder: ' . $label : 'Placeholder', 'placeholder' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', '' );
		$call->object_id = $id;
		$call->summary   = 'Generated placeholder ' . $w . '×' . $h;
		return array(
			'media' => $this->info( get_post( $id ) ),
			'use'   => array( 'image' => array( 'id' => $id ) ),
			'note'  => 'Placeholders have empty alt text; set real alt text when you replace them with photos.',
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function logo( array $a, Call $call ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'forbidden', 'You cannot upload files.' );
		}
		$text = trim( sanitize_text_field( (string) $a['text'] ) );
		if ( '' === $text || mb_strlen( $text ) > 40 ) {
			return new WP_Error( 'invalid', 'text must be 1–40 characters.' );
		}
		$upper   = ! empty( $a['uppercase'] );
		$display = $upper ? mb_strtoupper( $text ) : $text;
		$style   = in_array( $a['style'] ?? '', array( 'sans', 'serif', 'mono', 'rounded' ), true ) ? $a['style'] : 'sans';
		$weight  = max( 100, min( 900, (int) round( (int) ( $a['weight'] ?? 700 ) / 100 ) * 100 ) );
		$spacing = max( -0.1, min( 0.4, (float) ( $a['letter_spacing'] ?? ( $upper ? 0.08 : -0.02 ) ) ) );
		$layout  = in_array( $a['layout'] ?? '', array( 'horizontal', 'stacked', 'icon' ), true ) ? $a['layout'] : 'horizontal';
		$color   = $this->hex( (string) ( $a['color'] ?? '' ), '#111827' );
		$icolor  = $this->hex( (string) ( $a['icon_color'] ?? '' ), $color );
		$tile    = $this->hex( (string) ( $a['icon_background'] ?? '' ), '' );
		$icon    = $this->icon_body( (string) ( $a['icon'] ?? '' ) );
		if ( 'icon' === $layout && '' === $icon ) {
			return new WP_Error( 'invalid', 'layout "icon" needs an icon (Lucide name, see search_icons).' );
		}
		$fonts = array(
			'sans'    => 'Inter, "Helvetica Neue", Arial, system-ui, sans-serif',
			'serif'   => 'Georgia, "Times New Roman", ui-serif, serif',
			'mono'    => 'ui-monospace, Menlo, Consolas, monospace',
			'rounded' => 'ui-rounded, "SF Pro Rounded", "Arial Rounded MT Bold", system-ui, sans-serif',
		);

		$fs     = 64;
		$text_w = $this->text_width( $display, $fs, $style, $weight, $spacing );
		$mark   = 1.0 * $fs;
		$pad    = 4;
		$parts  = '';
		if ( 'horizontal' === $layout ) {
			$gap    = '' !== $icon ? 0.32 * $fs : 0;
			$mw     = '' !== $icon ? $mark : 0;
			$width  = (int) ceil( $pad * 2 + $mw + $gap + $text_w );
			$height = (int) ceil( $fs * 1.3 + $pad * 2 );
			if ( '' !== $icon ) {
				$parts .= $this->mark_svg( $icon, $pad, ( $height - $mark ) / 2, $mark, $icolor, $tile );
			}
			$parts .= $this->text_svg( $display, $pad + $mw + $gap, $height / 2 + $fs * 0.35, $fs, $fonts[ $style ], $weight, $spacing, $color );
		} elseif ( 'stacked' === $layout ) {
			$mark   = '' !== $icon ? 1.4 * $fs : 0;
			$width  = (int) ceil( max( $text_w, $mark ) + $pad * 2 );
			$height = (int) ceil( $pad * 2 + $mark + ( '' !== $icon ? 0.3 * $fs : 0 ) + $fs * 1.2 );
			if ( '' !== $icon ) {
				$parts .= $this->mark_svg( $icon, ( $width - $mark ) / 2, $pad, $mark, $icolor, $tile );
			}
			$parts .= $this->text_svg( $display, ( $width - $text_w ) / 2, $height - $pad - $fs * 0.25, $fs, $fonts[ $style ], $weight, $spacing, $color );
		} else {
			$mark   = 2 * $fs;
			$width  = (int) ceil( $mark + $pad * 2 );
			$height = $width;
			$parts .= $this->mark_svg( $icon, $pad, $pad, $mark, $icolor, $tile );
		}
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . esc_attr( $text ) . '"><title>' . esc_xml( $text ) . '</title>' . $parts . '</svg>';

		$id = Media::insert_svg( $svg, sanitize_title( $text ) . '-logo', $width, $height, $text . ' logo', 'logo' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $text );
		$out = array(
			'media' => $this->info( get_post( $id ) ),
			'note'  => 'Wordmarks use system fonts (SVG images cannot load web fonts). Replace with the real logo when available.',
		);
		if ( ! empty( $a['set_as_site_logo'] ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				set_theme_mod( 'custom_logo', $id );
				update_option( 'site_logo', $id );
				$out['site_logo'] = true;
			} else {
				$call->warn( 'Only administrators can change the site logo; the image was created but not set.' );
			}
		}
		$call->object_id = $id;
		$call->summary   = 'Generated logo "' . $text . '"';
		return $out;
	}

	/* ---------------------------------------------------------------- Helpers */

	/**
	 * @return array<string,mixed>
	 */
	private function info( \WP_Post $post ): array {
		$meta = wp_get_attachment_metadata( $post->ID );
		$w    = (int) ( $meta['width'] ?? 0 );
		$h    = (int) ( $meta['height'] ?? 0 );
		$out  = array(
			'id'     => $post->ID,
			'url'    => wp_get_attachment_url( $post->ID ),
			'title'  => get_the_title( $post ),
			'alt'    => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'width'  => $w,
			'height' => $h,
			'mime'   => $post->post_mime_type,
		);
		if ( $w && $h ) {
			$out['orientation'] = $w > $h * 1.1 ? 'landscape' : ( $h > $w * 1.1 ? 'portrait' : 'square' );
		}
		$large = wp_get_attachment_image_src( $post->ID, 'large' );
		if ( $large && $large[0] !== $out['url'] ) {
			$out['large'] = $large[0];
		}
		$generated = (string) get_post_meta( $post->ID, Media::META_GENERATED, true );
		if ( '' !== $generated ) {
			$out['generated'] = $generated;
		}
		return $out;
	}

	private function hex( string $value, string $fallback ): string {
		$value = strtolower( trim( $value ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $m ) ) {
			$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : $fallback;
	}

	private function shade( string $hex, float $amount ): string {
		$rgb = Audit::rgb( $hex );
		if ( ! $rgb ) {
			return $hex;
		}
		$out = '#';
		foreach ( $rgb as $c ) {
			$c    = $amount < 0 ? $c * ( 1 + $amount ) : $c + ( 255 - $c ) * $amount;
			$out .= str_pad( dechex( (int) max( 0, min( 255, round( $c ) ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	private function readable_on( string $bg ): string {
		$rgb = Audit::rgb( $bg );
		if ( ! $rgb ) {
			return '#334155';
		}
		return Audit::contrast( $rgb, array( 255, 255, 255 ) ) > Audit::contrast( $rgb, array( 15, 23, 42 ) ) ? '#ffffff' : '#334155';
	}

	/**
	 * Inner SVG of a bundled Lucide icon ('' when unknown).
	 */
	private function icon_body( string $name ): string {
		$name = sanitize_key( $name );
		if ( '' === $name ) {
			return '';
		}
		$all = Icons::all();
		return (string) ( $all[ $name ] ?? '' );
	}

	private function mark_svg( string $icon, float $x, float $y, float $size, string $color, string $tile ): string {
		$out = '';
		if ( '' !== $tile ) {
			$out  .= '<rect x="' . round( $x, 2 ) . '" y="' . round( $y, 2 ) . '" width="' . round( $size, 2 ) . '" height="' . round( $size, 2 ) . '" rx="' . round( $size * 0.24, 2 ) . '" fill="' . $tile . '"/>';
			$inner = $size * 0.6;
			$x    += ( $size - $inner ) / 2;
			$y    += ( $size - $inner ) / 2;
			$size  = $inner;
		}
		return $out . '<g transform="translate(' . round( $x, 2 ) . ' ' . round( $y, 2 ) . ') scale(' . round( $size / 24, 4 ) . ')" fill="none" stroke="' . $color . '" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">' . $icon . '</g>';
	}

	private function text_svg( string $text, float $x, float $baseline, int $fs, string $family, int $weight, float $spacing, string $color ): string {
		return '<text x="' . round( $x, 2 ) . '" y="' . round( $baseline, 2 ) . '" font-family="' . esc_attr( $family ) . '" font-size="' . $fs . '" font-weight="' . $weight . '" letter-spacing="' . round( $spacing * $fs, 2 ) . '" fill="' . $color . '">' . esc_xml( $text ) . '</text>';
	}

	/**
	 * Approximate advance width of a string (no font metrics available server-side).
	 */
	private function text_width( string $text, int $fs, string $style, int $weight, float $spacing ): float {
		$width = 0.0;
		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( (array) $chars as $ch ) {
			if ( preg_match( '/[ilj.,:;\'!|]/', $ch ) ) {
				$em = 0.28;
			} elseif ( ' ' === $ch ) {
				$em = 0.28;
			} elseif ( preg_match( '/[mwMW@]/', $ch ) ) {
				$em = 0.86;
			} elseif ( preg_match( '/[A-Z0-9&]/', $ch ) ) {
				$em = 0.68;
			} else {
				$em = 0.56;
			}
			$width += $em + $spacing;
		}
		$factor = array(
			'sans'    => 1.0,
			'serif'   => 0.98,
			'mono'    => 1.08,
			'rounded' => 1.02,
		)[ $style ];
		$bold   = 1 + max( 0, $weight - 400 ) / 2500;
		return ( 'mono' === $style ? count( (array) $chars ) * ( 0.6 + $spacing ) : $width * $factor * $bold ) * $fs * 1.04;
	}
}
