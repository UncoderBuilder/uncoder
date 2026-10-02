<?php
/**
 * AI writing tools in the editor: rewrite, shorten, fix, change tone, translate, image alt text.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Rest\Rest;
use Uncoder\Builder\Rest\Settings_Controller;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Uses the Anthropic API with the site owner's own key (uncoder_wb_settings['ai'], write-only in the
 * admin). Only the text of the field being edited, or the chosen image, is sent, and only when an
 * editor clicks an AI action. Answers are sanitized like any other input of that field. Each user
 * gets 40 requests per 10 minutes.
 */
final class Ai_Writer {

	public const ENDPOINT = 'https://api.anthropic.com/v1/messages';
	public const MODELS   = array(
		'claude-sonnet-5'           => 'Claude Sonnet 5 (balanced, recommended)',
		'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (fastest, lowest cost)',
		'claude-opus-5-5'           => 'Claude Opus 5.5 (most capable)',
	);
	public const TONES   = array( 'professional', 'friendly', 'confident', 'simple', 'persuasive' );
	private const LIMIT  = 40;
	private const WINDOW = 600;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * @return array{enabled:bool, model:string, key:string}
	 */
	private static function stored(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$raw    = is_array( $stored ) && is_array( $stored['ai'] ?? null ) ? $stored['ai'] : array();
		$model  = (string) ( $raw['model'] ?? 'claude-sonnet-5' );
		return array(
			'enabled' => ! empty( $raw['enabled'] ),
			'model'   => isset( self::MODELS[ $model ] ) ? $model : 'claude-sonnet-5',
			'key'     => (string) ( $raw['key'] ?? '' ),
		);
	}

	public static function ready(): bool {
		$s = self::stored();
		return $s['enabled'] && '' !== $s['key'];
	}

	/**
	 * Settings for the admin screen (the key itself never leaves the server).
	 *
	 * @return array{enabled:bool, model:string, has_key:bool}
	 */
	public static function public_settings(): array {
		$s = self::stored();
		return array(
			'enabled' => $s['enabled'],
			'model'   => $s['model'],
			'has_key' => '' !== $s['key'],
		);
	}

	/**
	 * @param array<string,mixed> $raw Body from the admin ("key" empty keeps the stored key).
	 * @return array{enabled:bool, model:string, key:string}
	 */
	public static function sanitize( array $raw ): array {
		$old   = self::stored();
		$key   = trim( sanitize_text_field( (string) ( $raw['key'] ?? '' ) ) );
		$model = (string) ( $raw['model'] ?? $old['model'] );
		return array(
			'enabled' => ! empty( $raw['enabled'] ),
			'model'   => isset( self::MODELS[ $model ] ) ? $model : 'claude-sonnet-5',
			'key'     => ! empty( $raw['remove_key'] ) ? '' : ( '' !== $key ? $key : $old['key'] ),
		);
	}

	public function routes(): void {
		$can = static fn() => current_user_can( 'edit_posts' ) && Role_Manager::can_use() && self::ready();
		register_rest_route(
			Rest::NS,
			'/ai/text',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'text' ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			Rest::NS,
			'/ai/alt',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'alt' ),
				'permission_callback' => static fn() => $can() && current_user_can( 'upload_files' ),
			)
		);
	}

	/**
	 * Body: { action: improve|shorten|expand|fix|tone|translate|custom, text, html?: bool, tone?, language?, instruction? }.
	 *
	 * @return array{text:string}|WP_Error
	 */
	public function text( WP_REST_Request $request ) {
		$action = (string) $request->get_param( 'action' );
		$html   = (bool) $request->get_param( 'html' );
		$text   = (string) $request->get_param( 'text' );
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			return new WP_Error( 'uncoder_ai_empty', __( 'There is no text to work on yet.', 'uncoder' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $text ) > 12000 ) {
			return new WP_Error( 'uncoder_ai_long', __( 'The text is too long for the AI tools (12,000 characters at most).', 'uncoder' ), array( 'status' => 400 ) );
		}
		$tone     = in_array( $request->get_param( 'tone' ), self::TONES, true ) ? (string) $request->get_param( 'tone' ) : 'professional';
		$language = mb_substr( sanitize_text_field( (string) $request->get_param( 'language' ) ), 0, 40 );
		$custom   = mb_substr( sanitize_text_field( (string) $request->get_param( 'instruction' ) ), 0, 300 );
		$tasks    = array(
			'improve'   => 'Improve this text: clearer, more specific and engaging, about the same length and the same meaning.',
			'shorten'   => 'Make this text about half as long while keeping its key message.',
			'expand'    => 'Make this text somewhat longer and more helpful by elaborating on what it already says. Do not add new facts, numbers, names, prices, awards or claims.',
			'fix'       => 'Fix spelling, grammar and punctuation only. Change nothing else.',
			'tone'      => 'Rewrite this text in a ' . $tone . ' tone, keeping its meaning and length.',
			'translate' => 'Translate this text into ' . ( '' !== $language ? $language : 'English' ) . '. Keep names, brands and URLs as they are.',
			'custom'    => 'Apply this instruction to the text: ' . $custom,
		);
		if ( ! isset( $tasks[ $action ] ) || ( 'custom' === $action && '' === $custom ) ) {
			return new WP_Error( 'uncoder_ai_action', __( 'Unknown AI action.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$limited = self::rate_limit();
		if ( $limited ) {
			return $limited;
		}
		$system = 'You edit text for a website built with a page builder. ' . $tasks[ $action ]
			. ' Reply with the resulting text only: no introduction, notes, quotes or Markdown.'
			. ( 'translate' === $action ? '' : ' Keep the language of the original text.' )
			. ( $html
				? ' The text is HTML: keep every tag and attribute (links, lists, bold) and return valid HTML with the same structure.'
				: ' The text is plain text: return plain text without HTML or Markdown, on one line unless the original has line breaks.' )
			. ' Never invent facts, statistics, testimonials or contact details.';
		$out = self::call(
			array(
				'system'     => $system,
				'max_tokens' => (int) min( 4096, 400 + mb_strlen( $text ) ),
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $text,
					),
				),
			)
		);
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$out = trim( (string) preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', trim( $out ) ) );
		return array( 'text' => $html ? wp_kses_post( $out ) : sanitize_textarea_field( $out ) );
	}

	/**
	 * Body: { id: attachment id, save?: bool } — alt text for an image (saved to the attachment on save).
	 *
	 * @return array{alt:string, saved:bool}|WP_Error
	 */
	public function alt( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			return new WP_Error( 'uncoder_ai_image', __( 'Choose an image from the media library first.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$image = self::image_data( $id );
		if ( is_wp_error( $image ) ) {
			return $image;
		}
		$limited = self::rate_limit();
		if ( $limited ) {
			return $limited;
		}
		$lang = get_bloginfo( 'language' );
		$out  = self::call(
			array(
				'system'     => 'You write alt text for images on websites. Reply with the alt text only: one sentence of at most 125 characters that says what the image shows and why it matters on a web page. Do not start with "Image of" or "Picture of". Write in the language with the code ' . $lang . '. If the image is purely decorative, reply with an empty line.',
				'max_tokens' => 200,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => array(
							array(
								'type'   => 'image',
								'source' => array(
									'type'       => 'base64',
									'media_type' => $image['type'],
									'data'       => $image['data'],
								),
							),
							array(
								'type' => 'text',
								'text' => 'Alt text for this image. File name: ' . sanitize_file_name( basename( (string) get_attached_file( $id ) ) ),
							),
						),
					),
				),
			)
		);
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$alt   = mb_substr( trim( sanitize_text_field( $out ), " \"'" ), 0, 250 );
		$saved = false;
		if ( $request->get_param( 'save' ) && current_user_can( 'edit_post', $id ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
			$saved = true;
		}
		return array(
			'alt'   => $alt,
			'saved' => $saved,
		);
	}

	/**
	 * The image as base64 (the "large" size when the original is big). SVG and unusual formats are refused.
	 *
	 * @return array{type:string, data:string}|WP_Error
	 */
	private static function image_data( int $id ) {
		$types = array(
			'image/jpeg' => true,
			'image/png'  => true,
			'image/gif'  => true,
			'image/webp' => true,
		);
		$mime  = (string) get_post_mime_type( $id );
		if ( ! isset( $types[ $mime ] ) ) {
			return new WP_Error( 'uncoder_ai_format', __( 'Alt text can be written for JPEG, PNG, GIF and WebP images.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$file = (string) get_attached_file( $id );
		$meta = wp_get_attachment_metadata( $id );
		foreach ( array( 'large', 'medium_large' ) as $size ) {
			if ( is_array( $meta ) && ! empty( $meta['sizes'][ $size ]['file'] ) ) {
				$candidate = dirname( $file ) . '/' . $meta['sizes'][ $size ]['file'];
				if ( is_readable( $candidate ) ) {
					$file = $candidate;
					$mime = (string) ( $meta['sizes'][ $size ]['mime-type'] ?? $mime );
					break;
				}
			}
		}
		if ( ! is_readable( $file ) || filesize( $file ) > 4 * 1024 * 1024 ) {
			return new WP_Error( 'uncoder_ai_file', __( 'The image file could not be read (or is larger than 4 MB).', 'uncoder' ), array( 'status' => 400 ) );
		}
		return array(
			'type' => isset( $types[ $mime ] ) ? $mime : 'image/jpeg',
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- local upload sent to the API as base64.
			'data' => base64_encode( (string) file_get_contents( $file ) ),
		);
	}

	private static function rate_limit(): ?WP_Error {
		$key   = 'uncoder_ai_' . get_current_user_id();
		$count = (int) get_transient( $key );
		if ( $count >= self::LIMIT ) {
			return new WP_Error( 'uncoder_ai_rate', __( 'Too many AI requests. Please wait a few minutes.', 'uncoder' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, self::WINDOW );
		return null;
	}

	/**
	 * One Messages API call; returns the text of the answer.
	 *
	 * @param array<string,mixed> $body Request body without the model.
	 * @return string|WP_Error
	 */
	private static function call( array $body ) {
		$s        = self::stored();
		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array(
					'x-api-key'         => $s['key'],
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				),
				'body'    => (string) wp_json_encode( array( 'model' => $s['model'] ) + $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'uncoder_ai_http', __( 'Could not reach the AI service. Try again in a moment.', 'uncoder' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $data ) ) {
			$reason = is_array( $data ) ? (string) ( $data['error']['type'] ?? '' ) : '';
			$msg    = 'authentication_error' === $reason || 401 === $code
				? __( 'The AI service rejected the API key. Check it under Uncoder → AI & MCP → AI writing.', 'uncoder' )
				: ( 429 === $code || 529 === $code ? __( 'The AI service is busy. Try again in a moment.', 'uncoder' ) : __( 'The AI service returned an error.', 'uncoder' ) );
			return new WP_Error( 'uncoder_ai_api', $msg, array( 'status' => 502 ) );
		}
		$text = '';
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= (string) $block['text'];
			}
		}
		return $text;
	}
}
