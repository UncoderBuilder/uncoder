<?php
/**
 * WPForms forms as Uncoder forms.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site\Elementor_Import;

defined( 'ABSPATH' ) || exit;

/**
 * A WPForms form (its fields, button, confirmation and first email notification) as the settings of an Uncoder Form
 * widget, so the page no longer needs WPForms: Uncoder then checks, stores and emails the submissions itself.
 */
final class Wpforms {

	/** WPForms field types → Uncoder field types; the others are left out with a note. */
	private const TYPES = array(
		'text'          => 'text',
		'email'         => 'email',
		'textarea'      => 'textarea',
		'number'        => 'number',
		'number-slider' => 'number',
		'phone'         => 'tel',
		'url'           => 'url',
		'select'        => 'select',
		'radio'         => 'radio',
		'checkbox'      => 'checkbox',
		'gdpr-checkbox' => 'acceptance',
		'date-time'     => 'date',
		'hidden'        => 'hidden',
		'file-upload'   => 'file',
		'pagebreak'     => 'step',
		'divider'       => 'heading',
	);

	/**
	 * Form widget settings for a WPForms form, or null when there is no such form.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function form( int $id, Converter $c ): ?array {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || 'wpforms' !== $post->post_type ) {
			return null;
		}
		$data = json_decode( (string) $post->post_content, true );
		if ( ! is_array( $data ) || empty( $data['fields'] ) ) {
			return null;
		}
		$fields = array();
		$tags   = array(); // WPForms field id → the Uncoder placeholder(s) of its value, for the email subject.
		$split  = false;
		foreach ( (array) $data['fields'] as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$type  = (string) ( $f['type'] ?? '' );
			$wid   = (string) ( $f['id'] ?? count( $fields ) );
			$key   = 'field_' . $wid;
			$label = trim( wp_strip_all_tags( (string) ( $f['label'] ?? '' ) ) );
			if ( 'pagebreak' === $type && '' === $label ) {
				$label = trim( wp_strip_all_tags( (string) ( $f['title'] ?? '' ) ) );
			}
			$req   = ! empty( $f['required'] );
			if ( 'name' === $type ) {
				if ( 'simple' === ( $f['format'] ?? '' ) ) {
					$fields[]     = self::row( $id, $key, 'text', $label, (string) ( $f['simple_placeholder'] ?? '' ), $req, array( 'autocomplete' => 'name' ) );
					$tags[ $wid ] = '[' . $key . ']';
					continue;
				}
				// First and last name side by side under the field's one label (the second has none; the form lines
				// their inputs up at the bottom).
				$split        = true;
				$fields[]     = self::row( $id, $key . '_first', 'text', $label, (string) ( $f['first_placeholder'] ?? '' ), $req, array( 'autocomplete' => 'given-name', 'width' => '50' ) );
				$fields[]     = self::row( $id, $key . '_last', 'text', '', (string) ( $f['last_placeholder'] ?? '' ), $req, array( 'autocomplete' => 'family-name', 'width' => '50' ) );
				$tags[ $wid ] = '[' . $key . '_first] [' . $key . '_last]';
				continue;
			}
			$to = self::TYPES[ $type ] ?? '';
			if ( '' === $to ) {
				if ( 'html' !== $type && 'content' !== $type ) {
					$c->setting( 'wpforms', 'field type: ' . $type );
				}
				continue;
			}
			$extra = array();
			if ( in_array( $to, array( 'select', 'radio', 'checkbox' ), true ) ) {
				$extra['options'] = implode( "\n", array_map( static fn( $choice ) => trim( wp_strip_all_tags( (string) ( $choice['label'] ?? '' ) ) ), array_values( (array) ( $f['choices'] ?? array() ) ) ) );
			}
			if ( 'acceptance' === $to ) {
				$first                    = array_values( (array) ( $f['choices'] ?? array() ) )[0]['label'] ?? $label;
				$extra['acceptance_text'] = trim( wp_strip_all_tags( (string) $first ) );
			}
			if ( 'textarea' === $to ) {
				$extra['rows'] = 5;
			}
			if ( '' !== trim( (string) ( $f['description'] ?? '' ) ) ) {
				$extra['help'] = trim( wp_strip_all_tags( (string) $f['description'] ) );
			}
			$fields[]     = self::row( $id, $key, $to, $label, (string) ( $f['placeholder'] ?? '' ), $req, $extra );
			$tags[ $wid ] = '[' . $key . ']';
		}
		if ( ! $fields ) {
			return null;
		}
		$settings = (array) ( $data['settings'] ?? array() );
		$out      = array(
			'form_name'   => $post->post_title,
			'fields'      => $fields,
			'button_text' => trim( (string) ( $settings['submit_text'] ?? '' ) ),
			'store'       => true,
		);
		if ( '' === $out['button_text'] ) {
			unset( $out['button_text'] );
		}
		foreach ( (array) ( $settings['confirmations'] ?? array() ) as $confirmation ) {
			if ( 'redirect' === ( $confirmation['type'] ?? '' ) && ! empty( $confirmation['redirect'] ) ) {
				$out['redirect_url'] = esc_url_raw( (string) $confirmation['redirect'] );
			} elseif ( 'page' === ( $confirmation['type'] ?? '' ) && ! empty( $confirmation['page'] ) && get_permalink( (int) $confirmation['page'] ) ) {
				$out['redirect_url'] = esc_url_raw( (string) get_permalink( (int) $confirmation['page'] ) );
			} elseif ( '' !== trim( wp_strip_all_tags( (string) ( $confirmation['message'] ?? '' ) ) ) ) {
				$out['success_message'] = trim( wp_strip_all_tags( (string) $confirmation['message'] ) );
			}
			break;
		}
		$notify = array_values( (array) ( $settings['notifications'] ?? array() ) )[0] ?? null;
		$out['email'] = is_array( $notify ) && '1' !== (string) ( $settings['notification_enable'] ?? '1' ) ? false : is_array( $notify );
		if ( $out['email'] ) {
			// Literal addresses only; WPForms' {admin_email} is Uncoder's default (the site admin email).
			$to = array_filter( array_map( 'trim', explode( ',', (string) ( $notify['email'] ?? '' ) ) ), 'is_email' );
			if ( $to ) {
				$out['email_to'] = implode( ', ', $to );
			}
			$subject = (string) ( $notify['subject'] ?? '' );
			if ( '' !== $subject ) {
				$subject = (string) preg_replace_callback( '/\{field_id="(\d+)"\}/', static fn( $m ) => $tags[ $m[1] ] ?? '', $subject );
				$subject = str_replace( array( '{form_name}', '{site_name}' ), array( '[form_name]', '[site_name]' ), $subject );
				$subject = trim( (string) preg_replace( array( '/\{[a-z_]+(?:="[^"]*")?\}/', '/\s*[#:]\s*$/', '/\s{2,}/' ), array( '', '', ' ' ), $subject ) );
				if ( '' !== $subject ) {
					$out['email_subject'] = $subject;
				}
			}
			if ( preg_match( '/\{field_id="(\d+)"\}/', (string) ( $notify['replyto'] ?? '' ), $m ) && isset( $tags[ $m[1] ] ) ) {
				$out['email_reply_to'] = 'field_' . $m[1];
			}
		}
		if ( $split ) {
			$out['_custom_css'] = 'selector .uncoder-form__fields{align-items:flex-end}';
		}
		return $out;
	}

	/**
	 * One field row of the Form widget.
	 *
	 * @param array<string,mixed> $extra More settings of the row.
	 * @return array<string,mixed>
	 */
	private static function row( int $form, string $key, string $type, string $label, string $placeholder, bool $required, array $extra = array() ): array {
		$row = array(
			'_id'      => substr( md5( $form . ':' . $key ), 0, 7 ),
			'type'     => $type,
			'label'    => $label,
			'field_id' => $key,
			'required' => $required,
		);
		if ( '' !== trim( $placeholder ) ) {
			$row['placeholder'] = trim( $placeholder );
		}
		return array_merge( $row, $extra );
	}
}
