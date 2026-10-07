<?php
/**
 * Lets the media library accept Lottie animation files (.json) for the Lottie widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress refuses .json uploads. Users who can upload files may add them here, but only files that
 * really are Lottie animations (valid JSON with version, frame rate, size and layers) and at most
 * 5 MB. JSON is data: browsers never run it, and the widget plays it with the expression-free player.
 */
final class Lottie_Files {

	public const MAX_BYTES = 5242880;

	public function register(): void {
		add_filter( 'upload_mimes', array( $this, 'mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'filetype' ), 10, 5 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'validate' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'validate' ) );
	}

	/**
	 * @param array<string,string> $mimes Allowed types.
	 * @return array<string,string>
	 */
	public function mimes( $mimes ): array {
		$mimes = is_array( $mimes ) ? $mimes : array();
		if ( current_user_can( 'upload_files' ) ) {
			$mimes['json'] = 'application/json';
		}
		return $mimes;
	}

	/**
	 * Servers detect JSON as text/plain; accept it when the extension is .json and JSON is allowed.
	 *
	 * @param array<string,mixed>       $data      Detected type.
	 * @param string                    $file      Temp path.
	 * @param string                    $filename  File name.
	 * @param array<string,string>|null $mimes     Allowed types.
	 * @param string|false              $real_mime Detected MIME type.
	 * @return array<string,mixed>
	 */
	public function filetype( $data, $file, $filename, $mimes, $real_mime = false ): array {
		$data = is_array( $data ) ? $data : array();
		if ( ! empty( $data['ext'] ) || 'json' !== strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ) ) {
			return $data;
		}
		$allowed = is_array( $mimes ) ? $mimes : get_allowed_mime_types();
		if ( isset( $allowed['json'] ) && in_array( $real_mime, array( false, 'text/plain', 'application/json' ), true ) ) {
			$data['ext']  = 'json';
			$data['type'] = 'application/json';
		}
		return $data;
	}

	/**
	 * @param array<string,mixed> $file Upload.
	 * @return array<string,mixed>
	 */
	public function validate( $file ): array {
		$file = is_array( $file ) ? $file : array();
		if ( 'json' !== strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) ) {
			return $file;
		}
		if ( ! self::is_lottie( (string) ( $file['tmp_name'] ?? '' ) ) ) {
			$file['error'] = __( 'Only Lottie animation files (.json, up to 5 MB) can be uploaded here.', 'uncoder' );
		}
		return $file;
	}

	public static function is_lottie( string $path ): bool {
		if ( '' === $path || ! is_readable( $path ) || filesize( $path ) > self::MAX_BYTES ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp file.
		$data = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $data )
			&& isset( $data['v'], $data['fr'], $data['w'], $data['h'], $data['layers'] )
			&& is_array( $data['layers'] )
			&& is_numeric( $data['fr'] ) && is_numeric( $data['w'] ) && is_numeric( $data['h'] );
	}
}
