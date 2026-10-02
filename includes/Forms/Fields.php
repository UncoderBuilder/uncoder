<?php
/**
 * Form field definitions: normalization and server-side validation.
 *
 * Shared by the Form widget (render) and the submit handler (validation) so both always read the
 * same field list from the SAVED settings.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Forms;

use Uncoder\Builder\Plugin;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes repeater rows into field definitions and validates submitted values.
 */
final class Fields {

	public const TYPES = array( 'text', 'email', 'tel', 'url', 'number', 'textarea', 'select', 'radio', 'checkbox', 'acceptance', 'date', 'hidden', 'file' );

	/** Types rendered as a group of choices with options. */
	public const CHOICE_TYPES = array( 'select', 'radio', 'checkbox' );

	/** Types whose value is free text (checked by "block links"). */
	public const TEXT_TYPES = array( 'text', 'textarea' );

	public const MAX_FIELDS      = 50;
	public const MAX_LENGTH      = 5000;
	public const MAX_OPTIONS     = 100;
	public const MAX_FILE_FIELDS = 5;
	public const MAX_FILE_MB     = 20;

	public const DEFAULT_FILE_TYPES = 'pdf,jpg,jpeg,png';

	/** Never accepted, whatever the field allows. */
	public const BLOCKED_EXTENSIONS = array(
		'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'inc',
		'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'jspx', 'cfm',
		'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'scr', 'vbs', 'ps1', 'jar',
		'js', 'mjs', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml', 'swf',
		'htaccess', 'htpasswd', 'ini', 'user',
	);

	/** Autocomplete tokens offered for fields (WCAG 1.3.5). */
	public const AUTOCOMPLETE = array(
		''               => 'Automatic',
		'off'            => 'Off',
		'name'           => 'Full name',
		'given-name'     => 'First name',
		'family-name'    => 'Last name',
		'email'          => 'Email',
		'tel'            => 'Phone',
		'organization'   => 'Company',
		'organization-title' => 'Job title',
		'street-address' => 'Street address',
		'address-level2' => 'City',
		'address-level1' => 'State / region',
		'postal-code'    => 'Postal code',
		'country-name'   => 'Country',
		'url'            => 'Website',
		'bday'           => 'Birthday',
	);

	/**
	 * Normalized field definitions from the (effective) widget settings.
	 *
	 * @param array<string,mixed> $settings Widget settings.
	 * @return array<int, array<string,mixed>>
	 */
	public static function from_settings( array $settings ): array {
		$widget = Plugin::instance()->elements()->get( 'form' );
		$rows   = is_array( $settings['fields'] ?? null ) ? $settings['fields'] : array();
		$rows   = $widget ? Repeater_Rows::get( $widget, 'fields', $rows ) : $rows;

		$allow_files = ! empty( $settings['allow_uploads'] );
		$fields      = array();
		$used        = array();
		$file_count  = 0;
		$step        = 0;

		foreach ( $rows as $row ) {
			if ( count( $fields ) >= self::MAX_FIELDS ) {
				break;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			// A "Step break" row starts the next step of a multi-step form; it is not a field.
			if ( 'step' === ( $row['type'] ?? '' ) ) {
				++$step;
				continue;
			}
			$type = in_array( $row['type'] ?? 'text', self::TYPES, true ) ? (string) $row['type'] : 'text';
			if ( 'file' === $type ) {
				if ( ! $allow_files || $file_count >= self::MAX_FILE_FIELDS ) {
					continue;
				}
				++$file_count;
			}
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			$row_id = isset( $row['_id'] ) && is_string( $row['_id'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( $row['_id'] ) ) : '';

			$id = self::slug( (string) ( $row['field_id'] ?? '' ) );
			if ( '' === $id ) {
				$id = self::slug( $label );
			}
			if ( '' === $id ) {
				$id = 'field_' . ( '' !== $row_id ? $row_id : (string) ( count( $fields ) + 1 ) );
			}
			$base = $id;
			$n    = 2;
			while ( isset( $used[ $id ] ) ) {
				$id = substr( $base, 0, 36 ) . '_' . $n;
				++$n;
			}
			$used[ $id ] = true;

			$size = is_numeric( $row['file_size'] ?? null ) ? (float) $row['file_size'] : 2;
			$size = max( 0.1, min( (float) self::MAX_FILE_MB, $size ) );
			$auto = is_string( $row['autocomplete'] ?? null ) ? $row['autocomplete'] : '';

			$fields[] = array(
				'id'           => $id,
				'row'          => $row_id,
				'type'         => $type,
				'label'        => $label,
				'placeholder'  => sanitize_text_field( (string) ( $row['placeholder'] ?? '' ) ),
				'required'     => 'hidden' !== $type && ! empty( $row['required'] ),
				'options'      => in_array( $type, self::CHOICE_TYPES, true ) ? self::parse_options( (string) ( $row['options'] ?? '' ) ) : array(),
				'inline'       => ! empty( $row['inline_options'] ),
				'default'      => sanitize_text_field( (string) ( $row['default_value'] ?? '' ) ),
				'help'         => sanitize_text_field( (string) ( $row['help'] ?? '' ) ),
				'acceptance'   => (string) ( $row['acceptance_text'] ?? '' ),
				'min'          => is_numeric( $row['min'] ?? null ) ? (float) $row['min'] : null,
				'max'          => is_numeric( $row['max'] ?? null ) ? (float) $row['max'] : null,
				'rows'         => is_numeric( $row['rows'] ?? null ) ? max( 2, min( 30, (int) $row['rows'] ) ) : 4,
				'file_types'   => 'file' === $type ? self::file_types( (string) ( $row['file_types'] ?? self::DEFAULT_FILE_TYPES ) ) : array(),
				'file_size'    => $size,
				'autocomplete' => isset( self::AUTOCOMPLETE[ $auto ] ) ? $auto : '',
				'mobile_width' => isset( $row['width_mobile'] ) && '' !== $row['width_mobile'],
				'step'         => $step,
				'show_if'      => self::show_if( $row ),
			);
		}
		return $fields;
	}

	/**
	 * Conditional display rule of a field row, or null.
	 *
	 * @param array<string,mixed> $row Repeater row.
	 * @return array{field:string, op:string, value:string}|null
	 */
	private static function show_if( array $row ): ?array {
		$field = self::slug( (string) ( $row['show_if_field'] ?? '' ) );
		if ( '' === $field ) {
			return null;
		}
		$op = in_array( $row['show_if_op'] ?? 'is', array( 'is', 'is_not', 'contains', 'filled', 'empty' ), true ) ? (string) ( $row['show_if_op'] ?? 'is' ) : 'is';
		return array(
			'field' => $field,
			'op'    => $op,
			'value' => sanitize_text_field( (string) ( $row['show_if_value'] ?? '' ) ),
		);
	}

	/**
	 * Whether a conditional field is shown for the submitted values (twin of the check in form.ts).
	 *
	 * @param array{field:string, op:string, value:string}|null $rule   Rule.
	 * @param array<string, mixed>                              $values Field id => submitted value.
	 */
	public static function visible( ?array $rule, array $values ): bool {
		if ( ! $rule ) {
			return true;
		}
		$actual = $values[ $rule['field'] ] ?? '';
		$list   = is_array( $actual ) ? array_map( 'strval', $actual ) : ( '' === (string) $actual ? array() : array( (string) $actual ) );
		$want   = strtolower( $rule['value'] );
		$has    = in_array( $want, array_map( 'strtolower', $list ), true );
		switch ( $rule['op'] ) {
			case 'is_not':
				return ! $has;
			case 'contains':
				foreach ( $list as $item ) {
					if ( '' !== $want && false !== stripos( $item, $want ) ) {
						return true;
					}
				}
				return false;
			case 'filled':
				return (bool) array_filter( $list, 'strlen' );
			case 'empty':
				return ! array_filter( $list, 'strlen' );
			default:
				return $has;
		}
	}

	/**
	 * Step titles of a multi-step form (index => title); empty steps are skipped.
	 *
	 * @param array<string,mixed>             $settings Widget settings.
	 * @param array<int, array<string,mixed>> $fields   from_settings() result.
	 * @return array<int,string>
	 */
	public static function steps( array $settings, array $fields ): array {
		$titles = array( 0 => '' );
		$step   = 0;
		foreach ( (array) ( $settings['fields'] ?? array() ) as $row ) {
			if ( is_array( $row ) && 'step' === ( $row['type'] ?? '' ) ) {
				$titles[ ++$step ] = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			}
		}
		$used = array_unique( array_map( static fn( $f ) => (int) $f['step'], $fields ) );
		return array_intersect_key( $titles, array_flip( $used ) );
	}

	/**
	 * Field id / name from a label or a typed id: lowercase ascii, digits and underscores.
	 */
	public static function slug( string $text ): string {
		$text = strtolower( remove_accents( wp_strip_all_tags( $text ) ) );
		$text = preg_replace( '/[^a-z0-9]+/', '_', $text );
		$text = trim( (string) $text, '_' );
		$text = substr( $text, 0, 40 );
		$text = rtrim( $text, '_' );
		if ( '' !== $text && ctype_digit( $text[0] ) ) {
			$text = 'f_' . $text;
		}
		return $text;
	}

	/**
	 * "Label" or "Label|value" per line.
	 *
	 * @return array<int, array{label:string, value:string}>
	 */
	public static function parse_options( string $raw ): array {
		$out  = array();
		$seen = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = explode( '|', $line, 2 );
			$label = sanitize_text_field( $parts[0] );
			$value = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : $label;
			if ( '' === $value ) {
				$value = $label;
			}
			if ( '' === $label ) {
				$label = $value;
			}
			if ( '' === $value || isset( $seen[ $value ] ) ) {
				continue;
			}
			$seen[ $value ] = true;
			$out[]          = array(
				'label' => $label,
				'value' => $value,
			);
			if ( count( $out ) >= self::MAX_OPTIONS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Allowed file extensions of a file field (lowercase, blocked ones removed).
	 *
	 * @return string[]
	 */
	public static function file_types( string $raw ): array {
		$out = array();
		foreach ( preg_split( '/[\s,;|]+/', strtolower( $raw ) ) as $ext ) {
			$ext = preg_replace( '/[^a-z0-9]/', '', (string) $ext );
			if ( '' !== $ext && ! in_array( $ext, self::BLOCKED_EXTENSIONS, true ) ) {
				$out[ $ext ] = true;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Validation messages, shared with the front-end module so client and server say the same thing.
	 *
	 * @param array<string,mixed> $settings Widget settings.
	 * @return array<string,string>
	 */
	public static function messages( array $settings ): array {
		$required = trim( sanitize_text_field( (string) ( $settings['required_message'] ?? '' ) ) );
		return array(
			'required'  => '' !== $required ? $required : __( 'This field is required.', 'uncoder' ),
			'email'     => __( 'Enter a valid email address.', 'uncoder' ),
			'url'       => __( 'Enter a valid web address.', 'uncoder' ),
			'tel'       => __( 'Enter a valid phone number.', 'uncoder' ),
			'number'    => __( 'Enter a number.', 'uncoder' ),
			/* translators: %s: minimum number */
			'min'       => __( 'Enter a number of at least %s.', 'uncoder' ),
			/* translators: %s: maximum number */
			'max'       => __( 'Enter a number no greater than %s.', 'uncoder' ),
			'date'      => __( 'Enter a valid date.', 'uncoder' ),
			'option'    => __( 'Choose one of the available options.', 'uncoder' ),
			/* translators: %s: maximum number of characters */
			'length'    => __( 'Use %s characters or fewer.', 'uncoder' ),
			'links'     => __( 'Links are not allowed in this field.', 'uncoder' ),
			'file_type' => __( 'This file type is not allowed.', 'uncoder' ),
			/* translators: %s: maximum file size, e.g. "2 MB" */
			'file_size' => __( 'The file is too large (maximum %s).', 'uncoder' ),
			'file'      => __( 'The file could not be uploaded. Please try again.', 'uncoder' ),
			'fix'       => __( 'Please correct the highlighted fields.', 'uncoder' ),
			'network'   => __( 'The form could not be sent. Check your connection and try again.', 'uncoder' ),
		);
	}

	/**
	 * Whether a text contains a link (http(s)://, www., HTML anchors or BBCode urls).
	 */
	public static function has_link( string $value ): bool {
		return (bool) preg_match( '~(https?://|\bwww\.|<a\s|\[url[\]=])~i', $value );
	}

	/**
	 * Human readable size ("2 MB").
	 */
	public static function size_label( float $mb ): string {
		$n = floor( $mb ) === $mb ? (string) (int) $mb : (string) round( $mb, 1 );
		/* translators: %s: number of megabytes */
		return sprintf( __( '%s MB', 'uncoder' ), $n );
	}

	/**
	 * Validates and sanitizes one submitted value against its field definition.
	 * File fields are validated by Uploads::check(); here they only get the "required" check.
	 *
	 * @param array<string,mixed>  $field    Normalized field.
	 * @param mixed                $raw      Raw submitted value (unslashed).
	 * @param array<string,mixed>  $settings Widget settings.
	 * @param array<string,string> $messages Messages.
	 * @return array{value: string|string[], error: string}
	 */
	public static function validate( array $field, $raw, array $settings, array $messages ): array {
		$type  = (string) $field['type'];
		$error = '';

		if ( 'checkbox' === $type && $field['options'] ) {
			$values  = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== $raw ? array( $raw ) : array() );
			$allowed = wp_list_pluck( $field['options'], 'value' );
			$clean   = array();
			foreach ( array_slice( $values, 0, self::MAX_OPTIONS ) as $v ) {
				if ( ! is_scalar( $v ) ) {
					continue;
				}
				$v = sanitize_text_field( (string) $v );
				if ( '' === $v ) {
					continue;
				}
				if ( ! in_array( $v, $allowed, true ) ) {
					$error = $messages['option'];
					continue;
				}
				$clean[ $v ] = $v;
			}
			$clean = array_values( $clean );
			if ( ! $error && $field['required'] && ! $clean ) {
				$error = $messages['required'];
			}
			return array(
				'value' => $clean,
				'error' => $error,
			);
		}

		if ( is_array( $raw ) || is_object( $raw ) ) {
			$raw = '';
		}
		$raw = trim( (string) $raw );

		if ( 'acceptance' === $type || 'checkbox' === $type ) {
			$on = '' !== $raw && ! in_array( strtolower( $raw ), array( '0', 'false', 'no', 'off' ), true );
			return array(
				'value' => $on ? 'yes' : '',
				'error' => ( $field['required'] && ! $on ) ? $messages['required'] : '',
			);
		}

		if ( 'file' === $type ) {
			return array(
				'value' => '',
				'error' => '',
			);
		}

		if ( '' === $raw ) {
			return array(
				'value' => '',
				'error' => $field['required'] ? $messages['required'] : '',
			);
		}

		if ( mb_strlen( $raw ) > self::MAX_LENGTH ) {
			return array(
				'value' => '',
				'error' => sprintf( $messages['length'], number_format_i18n( self::MAX_LENGTH ) ),
			);
		}

		$value = 'textarea' === $type ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );

		switch ( $type ) {
			case 'email':
				$email = sanitize_email( $raw );
				if ( ! is_email( $email ) || $email !== $raw ) {
					$error = $messages['email'];
				}
				$value = $email;
				break;

			case 'url':
				$candidate = preg_match( '~^[a-z][a-z0-9+.\-]*://~i', $raw ) ? $raw : 'http://' . $raw;
				$url       = esc_url_raw( $candidate, array( 'http', 'https' ) );
				$host      = (string) wp_parse_url( $url, PHP_URL_HOST );
				if ( '' === $url || '' === $host || false === strpos( $host, '.' ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
					$error = $messages['url'];
				}
				$value = $url;
				break;

			case 'tel':
				if ( ! preg_match( '~^\+?[0-9\s().\-/]{3,40}$~', $raw ) || preg_match_all( '/[0-9]/', $raw ) < 3 ) {
					$error = $messages['tel'];
				}
				break;

			case 'number':
				if ( ! is_numeric( $raw ) || ! is_finite( (float) $raw ) ) {
					$error = $messages['number'];
					break;
				}
				$num = (float) $raw;
				if ( null !== $field['min'] && $num < $field['min'] ) {
					$error = sprintf( $messages['min'], self::number_label( $field['min'] ) );
				} elseif ( null !== $field['max'] && $num > $field['max'] ) {
					$error = sprintf( $messages['max'], self::number_label( $field['max'] ) );
				}
				$value = $raw;
				break;

			case 'date':
				if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
					$error = $messages['date'];
				}
				break;

			case 'select':
			case 'radio':
				if ( $field['options'] && ! in_array( $value, wp_list_pluck( $field['options'], 'value' ), true ) ) {
					$error = $messages['option'];
				}
				break;
		}

		if ( ! $error && ! empty( $settings['block_links'] ) && in_array( $type, self::TEXT_TYPES, true ) && self::has_link( $raw ) ) {
			$error = $messages['links'];
		}

		return array(
			'value' => $error ? '' : $value,
			'error' => $error,
		);
	}

	/**
	 * @param float|int $n Number.
	 */
	private static function number_label( $n ): string {
		return floor( (float) $n ) === (float) $n ? (string) (int) $n : (string) $n;
	}

	/**
	 * Display text of a stored value (options show their label, acceptance shows Yes).
	 *
	 * @param array<string,mixed> $field Normalized field (or a stored data entry with type).
	 * @param mixed               $value Stored value.
	 */
	public static function display( array $field, $value ): string {
		$labels = array();
		foreach ( (array) ( $field['options'] ?? array() ) as $opt ) {
			$labels[ $opt['value'] ] = $opt['label'];
		}
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( $value as $v ) {
				$parts[] = $labels[ $v ] ?? (string) $v;
			}
			return implode( ', ', $parts );
		}
		$value = (string) $value;
		if ( in_array( $field['type'] ?? '', array( 'acceptance', 'checkbox' ), true ) && 'yes' === $value ) {
			return __( 'Yes', 'uncoder' );
		}
		return $labels[ $value ] ?? $value;
	}
}
