<?php
/**
 * File uploads for form fields.
 *
 * Files go to uploads/uncoder/forms/{Y}/{m}/ under random names with a verified extension. The
 * folder denies web access (.htaccess + index.php); admins download files through an
 * admin-post.php handler that checks capabilities. Files are never executed or served inline.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Forms;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Validates, stores and serves uploaded files.
 */
final class Uploads {

	public const ACTION = 'uncoder_wb_form_file';

	/**
	 * Absolute path of the protected forms folder (created on demand).
	 */
	public static function dir(): ?string {
		$uploads = Utils::uploads();
		if ( ! $uploads ) {
			return null;
		}
		$dir = $uploads['dir'] . '/forms';
		if ( ! is_dir( $dir ) || ! file_exists( $dir . '/.htaccess' ) ) {
			Install::create_upload_dirs();
		}
		return is_dir( $dir ) ? $dir : null;
	}

	/**
	 * Uploaded file of a field from the request's file params (`fields[<id>]`).
	 *
	 * @param array<string,mixed> $files Request file params.
	 * @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null
	 */
	public static function from_request( array $files, string $field_id ): ?array {
		$f = $files['fields'] ?? null;
		if ( ! is_array( $f ) || ! isset( $f['name'][ $field_id ] ) ) {
			return null;
		}
		$pick = static function ( string $key ) use ( $f, $field_id ) {
			$v = $f[ $key ][ $field_id ] ?? '';
			return is_array( $v ) ? reset( $v ) : $v; // One file per field.
		};
		$file = array(
			'name'     => (string) $pick( 'name' ),
			'type'     => (string) $pick( 'type' ),
			'tmp_name' => (string) $pick( 'tmp_name' ),
			'error'    => (int) $pick( 'error' ),
			'size'     => (int) $pick( 'size' ),
		);
		if ( UPLOAD_ERR_NO_FILE === $file['error'] || ( '' === $file['name'] && '' === $file['tmp_name'] ) ) {
			return null;
		}
		return $file;
	}

	/**
	 * Mime types allowed for a field: its extensions ∩ WordPress' allowed types, minus blocked ones.
	 *
	 * @param array<string,mixed> $field Normalized field.
	 * @return array<string,string> "ext|ext2" => mime.
	 */
	public static function allowed_mimes( array $field ): array {
		$exts = (array) ( $field['file_types'] ?? array() );
		$out  = array();
		foreach ( get_allowed_mime_types( 0 ) as $pattern => $mime ) {
			$keep = array();
			foreach ( explode( '|', (string) $pattern ) as $ext ) {
				if ( in_array( $ext, $exts, true ) && ! in_array( $ext, Fields::BLOCKED_EXTENSIONS, true ) ) {
					$keep[] = $ext;
				}
			}
			if ( $keep ) {
				$out[ implode( '|', $keep ) ] = $mime;
			}
		}
		return $out;
	}

	/**
	 * Checks an uploaded file; returns an error message or ''.
	 *
	 * @param array<string,mixed>  $field    Normalized field.
	 * @param array<string,mixed>  $file     File from from_request().
	 * @param array<string,string> $messages Messages.
	 */
	public static function check( array $field, array $file, array $messages ): string {
		if ( UPLOAD_ERR_INI_SIZE === $file['error'] || UPLOAD_ERR_FORM_SIZE === $file['error'] ) {
			return sprintf( $messages['file_size'], Fields::size_label( (float) $field['file_size'] ) );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] || '' === $file['tmp_name'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return $messages['file'];
		}
		$max = (int) min( $field['file_size'] * MB_IN_BYTES, wp_max_upload_size() );
		if ( $file['size'] <= 0 || $file['size'] > $max || filesize( $file['tmp_name'] ) > $max ) {
			return sprintf( $messages['file_size'], Fields::size_label( (float) $field['file_size'] ) );
		}
		$mimes = self::allowed_mimes( $field );
		if ( ! $mimes ) {
			return $messages['file_type'];
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ), $mimes );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! in_array( strtolower( (string) $check['ext'] ), (array) $field['file_types'], true ) ) {
			return $messages['file_type'];
		}
		return '';
	}

	/**
	 * Moves a checked upload into the protected folder under a random name.
	 *
	 * @param array<string,mixed> $field Normalized field.
	 * @param array<string,mixed> $file  File from from_request().
	 * @return array{name:string, path:string, size:int, mime:string}|null
	 */
	public static function store( array $field, array $file ): ?array {
		$dir = self::dir();
		if ( null === $dir ) {
			return null;
		}
		$name  = sanitize_file_name( $file['name'] );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $name, self::allowed_mimes( $field ) );
		$ext   = strtolower( (string) $check['ext'] );
		if ( '' === $ext || in_array( $ext, Fields::BLOCKED_EXTENSIONS, true ) ) {
			return null;
		}
		$sub = gmdate( 'Y' ) . '/' . gmdate( 'm' );
		if ( ! is_dir( $dir . '/' . $sub ) && ! wp_mkdir_p( $dir . '/' . $sub ) ) {
			return null;
		}
		foreach ( array( gmdate( 'Y' ), $sub ) as $folder ) {
			$index = $dir . '/' . $folder . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		$random   = strtolower( wp_generate_password( 32, false, false ) );
		$relative = $sub . '/' . $random . '.' . $ext;
		$target   = $dir . '/' . $relative;
		// WordPress moves the upload (wp_handle_upload), pointed at the protected folder for this one call.
		$folder = static function ( array $uploads ) use ( $dir, $sub ): array {
			$uploads['path']   = $dir . '/' . $sub;
			$uploads['url']    = '';
			$uploads['subdir'] = '';
			$uploads['error']  = false;
			return $uploads;
		};
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$upload = array(
			'name'     => $random . '.' . $ext,
			'type'     => (string) $check['type'],
			'tmp_name' => (string) $file['tmp_name'],
			'error'    => UPLOAD_ERR_OK,
			'size'     => (int) $file['size'],
		);
		add_filter( 'upload_dir', $folder, PHP_INT_MAX );
		$moved = wp_handle_upload(
			$upload,
			array(
				'test_form'                => false,
				'mimes'                    => self::allowed_mimes( $field ),
				'unique_filename_callback' => static fn( $folder_path, $filename, $extension ) => $random . $extension,
			)
		);
		remove_filter( 'upload_dir', $folder, PHP_INT_MAX );
		if ( ! is_array( $moved ) || ! empty( $moved['error'] ) || empty( $moved['file'] ) || wp_normalize_path( (string) $moved['file'] ) !== wp_normalize_path( $target ) ) {
			if ( is_array( $moved ) && ! empty( $moved['file'] ) && is_file( (string) $moved['file'] ) ) {
				wp_delete_file( (string) $moved['file'] );
			}
			return null;
		}
		return array(
			'name' => '' !== $name ? $name : 'file.' . $ext,
			'path' => $relative,
			'size' => (int) filesize( $target ),
			'mime' => (string) $check['type'],
		);
	}

	/**
	 * Resolves a stored relative path to an absolute path inside the forms folder, or null.
	 */
	public static function path( string $relative ): ?string {
		$dir = self::dir();
		if ( null === $dir || ! preg_match( '~^\d{4}/\d{2}/[a-z0-9]{16,64}\.[a-z0-9]{1,10}$~', $relative ) ) {
			return null;
		}
		$real_dir  = realpath( $dir );
		$real_file = realpath( $dir . '/' . $relative );
		if ( false === $real_dir || false === $real_file || 0 !== strpos( $real_file, $real_dir . DIRECTORY_SEPARATOR ) || ! is_file( $real_file ) ) {
			return null;
		}
		return $real_file;
	}

	/**
	 * Download link for a file field of a stored submission (admins only; used in notification emails).
	 */
	public static function download_url( int $submission_id, string $field_id ): string {
		return add_query_arg(
			array(
				'action'     => self::ACTION,
				'submission' => $submission_id,
				'field'      => $field_id,
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function capability(): string {
		/**
		 * Capability needed to download files uploaded through forms.
		 *
		 * @param string $cap Capability.
		 */
		return (string) apply_filters( 'uncoder_wb/forms/file_capability', 'manage_options' );
	}

	/**
	 * admin-post.php?action=uncoder_wb_form_file&submission=ID&field=FIELD_ID — sends the file as a download.
	 */
	public static function serve(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to download this file.', 'uncoder' ), '', array( 'response' => 403 ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only download behind a capability check; links live in emails.
		$submission_id = isset( $_GET['submission'] ) ? absint( $_GET['submission'] ) : 0;
		$field_id      = isset( $_GET['field'] ) ? Fields::slug( sanitize_text_field( wp_unslash( $_GET['field'] ) ) ) : '';
		// phpcs:enable

		$entry = Store::file_entry( $submission_id, $field_id );
		$path  = $entry ? self::path( (string) ( $entry['file']['path'] ?? '' ) ) : null;
		if ( null === $path ) {
			wp_die( esc_html__( 'File not found.', 'uncoder' ), '', array( 'response' => 404 ) );
		}
		$name = sanitize_file_name( (string) ( $entry['value'] ?? basename( $path ) ) );
		$mime = (string) ( $entry['file']['mime'] ?? 'application/octet-stream' );
		if ( ! preg_match( '~^[a-z]+/[a-z0-9.+\-]+$~i', $mime ) ) {
			$mime = 'application/octet-stream';
		}

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', "\r", "\n" ), '', $name ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Deletes the files attached to submission data entries.
	 *
	 * @param array<int, array<string,mixed>> $data Stored data entries.
	 */
	public static function delete_for( array $data ): void {
		foreach ( $data as $entry ) {
			if ( is_array( $entry ) && ! empty( $entry['file']['path'] ) ) {
				$path = self::path( (string) $entry['file']['path'] );
				if ( $path ) {
					wp_delete_file( $path );
				}
			}
		}
	}
}
