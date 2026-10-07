<?php
/**
 * AI image generation (editor → image field → Generate with AI).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Rest\Rest;
use Uncoder\Builder\Rest\Settings_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * uncoder_wb_settings['ai_images'] = { enabled, key, model, endpoint }. Uses an OpenAI-compatible Images API
 * (POST {endpoint} with model, prompt, size) and the owner's own key, which never leaves the server. The
 * picture is saved to the media library with the prompt as its alt text, then used in the field.
 */
final class Ai_Images {

	public const ENDPOINT = 'https://api.openai.com/v1/images/generations';
	public const MODEL    = 'gpt-image-1';
	public const SIZES    = array(
		'square'    => '1024x1024',
		'landscape' => '1536x1024',
		'portrait'  => '1024x1536',
	);
	private const LIMIT  = 20;
	private const WINDOW = 600;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/** @return array{enabled:bool, key:string, model:string, endpoint:string} */
	private static function stored(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$raw    = is_array( $stored ) && is_array( $stored['ai_images'] ?? null ) ? $stored['ai_images'] : array();
		return array(
			'enabled'  => ! empty( $raw['enabled'] ),
			'key'      => (string) ( $raw['key'] ?? '' ),
			'model'    => '' !== (string) ( $raw['model'] ?? '' ) ? (string) $raw['model'] : self::MODEL,
			'endpoint' => '' !== (string) ( $raw['endpoint'] ?? '' ) ? (string) $raw['endpoint'] : self::ENDPOINT,
		);
	}

	public static function ready(): bool {
		$s = self::stored();
		return $s['enabled'] && '' !== $s['key'];
	}

	/** @return array{enabled:bool, model:string, endpoint:string, has_key:bool} */
	public static function public_settings(): array {
		$s = self::stored();
		return array(
			'enabled'  => $s['enabled'],
			'model'    => $s['model'],
			'endpoint' => $s['endpoint'],
			'has_key'  => '' !== $s['key'],
		);
	}

	/**
	 * @param array<string,mixed> $raw Body from the admin ("key" empty keeps the stored key).
	 * @return array{enabled:bool, key:string, model:string, endpoint:string}
	 */
	public static function sanitize( array $raw ): array {
		$old      = self::stored();
		$key      = trim( sanitize_text_field( (string) ( $raw['key'] ?? '' ) ) );
		$model    = (string) preg_replace( '/[^A-Za-z0-9._\-]/', '', (string) ( $raw['model'] ?? $old['model'] ) );
		$endpoint = esc_url_raw( trim( (string) ( $raw['endpoint'] ?? $old['endpoint'] ) ), array( 'https' ) );
		return array(
			'enabled'  => ! empty( $raw['enabled'] ),
			'key'      => ! empty( $raw['remove_key'] ) ? '' : ( '' !== $key ? $key : $old['key'] ),
			'model'    => '' !== $model ? $model : self::MODEL,
			'endpoint' => '' !== $endpoint ? $endpoint : self::ENDPOINT,
		);
	}

	public function routes(): void {
		register_rest_route(
			Rest::NS,
			'/ai/image',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => static fn() => current_user_can( 'upload_files' ) && current_user_can( 'edit_posts' ) && Role_Manager::can_use() && self::ready(),
			)
		);
	}

	public function generate( WP_REST_Request $request ) {
		$prompt = trim( sanitize_textarea_field( (string) $request->get_param( 'prompt' ) ) );
		if ( mb_strlen( $prompt ) < 3 ) {
			return new WP_Error( 'uncoder_ai_image', __( 'Describe the image you want.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$prompt = mb_substr( $prompt, 0, 2000 );
		$size   = self::SIZES[ (string) $request->get_param( 'size' ) ] ?? self::SIZES['landscape'];
		$key    = 'uncoder_ai_img_' . get_current_user_id();
		$count  = (int) get_transient( $key );
		if ( $count >= self::LIMIT ) {
			return new WP_Error( 'uncoder_ai_rate', __( 'Too many images in a short time. Please wait a few minutes.', 'uncoder' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, self::WINDOW );

		$s = self::stored();
		// The endpoint is configurable (OpenAI-compatible services): public https hosts only, no redirects,
		// so the key is never sent to an internal address.
		$res = wp_safe_remote_post(
			$s['endpoint'],
			array(
				'timeout'             => 120,
				'redirection'         => 0,
				'limit_response_size' => 40 * MB_IN_BYTES,
				'headers'             => array(
					'Authorization' => 'Bearer ' . $s['key'],
					'Content-Type'  => 'application/json',
				),
				'body'                => (string) wp_json_encode(
					array(
						'model'  => $s['model'],
						'prompt' => $prompt,
						'size'   => $size,
						'n'      => 1,
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'uncoder_ai_http', __( 'Could not reach the image service. Try again in a moment.', 'uncoder' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || ! is_array( $data ) || empty( $data['data'][0] ) ) {
			$msg = 401 === $code ? __( 'The image service rejected the API key. Check it under Uncoder → AI & MCP → AI writing.', 'uncoder' )
				: ( is_array( $data ) && ! empty( $data['error']['message'] ) ? mb_substr( sanitize_text_field( (string) $data['error']['message'] ), 0, 300 ) : __( 'The image service returned an error.', 'uncoder' ) );
			return new WP_Error( 'uncoder_ai_api', $msg, array( 'status' => 502 ) );
		}
		$item  = $data['data'][0];
		$bytes = '';
		if ( ! empty( $item['b64_json'] ) ) {
			$bytes = (string) base64_decode( (string) $item['b64_json'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- image data from the API.
		} elseif ( ! empty( $item['url'] ) && 0 === strpos( (string) $item['url'], 'https://' ) ) {
			$dl    = wp_safe_remote_get(
				(string) $item['url'],
				array(
					'timeout'             => 60,
					'limit_response_size' => 20 * MB_IN_BYTES,
				)
			);
			$bytes = is_wp_error( $dl ) ? '' : (string) wp_remote_retrieve_body( $dl );
		}
		$id = '' !== $bytes ? self::save( $bytes, $prompt ) : new WP_Error( 'uncoder_ai_api', __( 'The image service sent no image.', 'uncoder' ), array( 'status' => 502 ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return new WP_REST_Response(
			array(
				'id'  => $id,
				'url' => (string) wp_get_attachment_url( $id ),
				'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			)
		);
	}

	/**
	 * The generated picture as a media library item (only real PNG / JPEG / WebP data is accepted).
	 *
	 * @return int|WP_Error
	 */
	private static function save( string $bytes, string $prompt ) {
		$info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $bytes ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$mime = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
		$ext  = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		)[ $mime ] ?? '';
		if ( '' === $ext ) {
			return new WP_Error( 'uncoder_ai_api', __( 'The image service sent something that is not an image.', 'uncoder' ), array( 'status' => 502 ) );
		}
		$slug   = sanitize_title( wp_trim_words( $prompt, 6, '' ) ) ?: 'ai-image';
		$upload = wp_upload_bits( $slug . '-' . wp_generate_password( 4, false, false ) . '.' . $ext, null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'uncoder_ai_upload', (string) $upload['error'], array( 'status' => 500 ) );
		}
		$title = mb_substr( wp_trim_words( $prompt, 12, '…' ), 0, 120 );
		$id    = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => $title,
				'post_content'   => $prompt,
				'post_status'    => 'inherit',
			),
			$upload['file'],
			0,
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', mb_substr( $prompt, 0, 250 ) );
		update_post_meta( $id, '_uncoder_ai_generated', 1 );
		return (int) $id;
	}
}
