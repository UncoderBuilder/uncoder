<?php
/**
 * After-submit actions: notification email and webhook.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Each action returns a small status array stored in the submission meta ("actions").
 */
final class Actions {

	/**
	 * Replaces [field_id], [all-fields], [form_name], [page_url] and [site_name] in a text.
	 *
	 * @param array<string,string> $values   field id => display value (already escaped when $html).
	 * @param array<string,string> $extra    Other placeholders.
	 */
	private static function placeholders( string $text, array $values, array $extra ): string {
		return (string) preg_replace_callback(
			'/\[([a-z0-9_\-]{1,60})\]/i',
			static function ( $m ) use ( $values, $extra ) {
				$key = strtolower( $m[1] );
				if ( array_key_exists( $key, $extra ) ) {
					return $extra[ $key ];
				}
				return array_key_exists( $key, $values ) ? $values[ $key ] : $m[0];
			},
			$text
		);
	}

	/**
	 * Strips line breaks and control characters from a header-bound value.
	 */
	public static function header_safe( string $value ): string {
		$value = preg_replace( '/[\r\n\t\x00-\x1F\x7F]+/', ' ', $value );
		return trim( (string) $value );
	}

	/**
	 * Sends the notification email.
	 *
	 * @param array<string,mixed>             $settings Widget settings (trusted: from the saved document).
	 * @param array<int, array<string,mixed>> $data     Submission data entries.
	 * @param array<string,mixed>             $context  form_name, page_url, submission_id, fields (normalized).
	 * @return array{status:string, error?:string, to?:int}
	 */
	public static function email( array $settings, array $data, array $context ): array {
		$recipients = array();
		foreach ( preg_split( '/[,;]/', (string) ( $settings['email_to'] ?? '' ) ) as $addr ) {
			$addr = trim( (string) $addr );
			// Only clean addresses: anything sanitize_email() would have to alter (spaces, line breaks…) is skipped.
			if ( '' !== $addr && sanitize_email( $addr ) === $addr && is_email( $addr ) ) {
				$recipients[ strtolower( $addr ) ] = $addr;
			}
		}
		if ( ! $recipients ) {
			$admin = (string) get_option( 'admin_email' );
			if ( is_email( $admin ) ) {
				$recipients[ strtolower( $admin ) ] = $admin;
			}
		}
		$recipients = array_slice( array_values( $recipients ), 0, 10 );
		if ( ! $recipients ) {
			return array(
				'status' => 'skipped',
				'error'  => 'no recipient',
			);
		}

		$html      = 'plain' !== ( $settings['email_format'] ?? 'html' );
		$site      = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$form_name = (string) $context['form_name'];
		$page_url  = (string) $context['page_url'];
		$sid       = (int) ( $context['submission_id'] ?? 0 );

		// Plain values (subject) and body values (escaped for HTML).
		$plain = array();
		$body  = array();
		$rows  = array();
		foreach ( $data as $entry ) {
			$display = Fields::display( self::field_for( $entry, $context ), $entry['value'] ?? '' );
			if ( 'file' === ( $entry['type'] ?? '' ) && '' !== $display && $sid && ! empty( $entry['file']['path'] ) ) {
				$link          = Uploads::download_url( $sid, (string) $entry['id'] );
				$body_value    = $html ? '<a href="' . esc_url( $link ) . '">' . esc_html( $display ) . '</a>' : $display . ' (' . $link . ')';
			} else {
				$body_value = $html ? nl2br( esc_html( $display ) ) : $display;
			}
			$plain[ $entry['id'] ] = self::header_safe( $display );
			$body[ $entry['id'] ]  = $body_value;
			$label                 = '' !== (string) ( $entry['label'] ?? '' ) ? (string) $entry['label'] : (string) $entry['id'];
			$rows[]                = $html
				? '<p style="margin:0 0 14px"><strong>' . esc_html( $label ) . '</strong><br>' . ( '' !== $display ? $body_value : '&mdash;' ) . '</p>'
				: $label . ': ' . ( '' !== $display ? $body_value : '-' );
		}
		$all = implode( "\n", $rows );

		$extra_plain = array(
			'form_name' => $form_name,
			'page_url'  => $page_url,
			'site_name' => $site,
		);

		$subject = trim( (string) ( $settings['email_subject'] ?? '' ) );
		if ( '' === $subject ) {
			$subject = __( 'New submission: [form_name]', 'uncoder' );
		}
		$subject = self::header_safe( wp_strip_all_tags( self::placeholders( $subject, $plain, $extra_plain ) ) );
		$subject = mb_substr( $subject, 0, 200 );

		$template = 'template' === ( $settings['email_content'] ?? 'all' ) ? (string) ( $settings['email_template'] ?? '' ) : '';
		if ( '' === trim( $template ) ) {
			$template = '[all-fields]';
		}
		if ( $html ) {
			$extra = array(
				'all-fields' => $all,
				'form_name'  => esc_html( $form_name ),
				'page_url'   => esc_html( $page_url ),
				'site_name'  => esc_html( $site ),
			);
			// Escape the admin-written template text first, then insert escaped values.
			$message  = self::placeholders( nl2br( esc_html( $template ) ), $body, $extra );
			$footer   = sprintf(
				/* translators: 1: form name, 2: page URL */
				esc_html__( 'Sent by the form "%1$s" on %2$s', 'uncoder' ),
				esc_html( $form_name ),
				'' !== $page_url ? '<a href="' . esc_url( $page_url ) . '">' . esc_html( $page_url ) . '</a>' : esc_html( home_url( '/' ) )
			);
			$message = '<div style="font:15px/1.55 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937">' . $message
				. '<hr style="border:0;border-top:1px solid #e5e7eb;margin:20px 0 12px"><p style="margin:0;color:#6b7280;font-size:13px">' . $footer . '</p></div>';
		} else {
			$extra   = array_merge( $extra_plain, array( 'all-fields' => $all ) );
			$message = self::placeholders( $template, $body, $extra );
			/* translators: 1: form name, 2: page URL */
			$message .= "\n\n-- \n" . sprintf( __( 'Sent by the form "%1$s" on %2$s', 'uncoder' ), $form_name, '' !== $page_url ? $page_url : home_url( '/' ) );
		}

		$headers = array();
		if ( $html ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}
		$reply = self::reply_to( $settings, $data );
		if ( '' !== $reply ) {
			$headers[] = 'Reply-To: ' . $reply;
		}

		/**
		 * Filters the notification email before it is sent.
		 *
		 * @param array{to:string[],subject:string,message:string,headers:string[]} $mail    Mail arguments.
		 * @param array<int, array<string,mixed>>                                   $data    Submission data.
		 * @param array<string,mixed>                                               $context Form context.
		 */
		$mail = (array) apply_filters(
			'uncoder_wb/forms/email',
			array(
				'to'      => $recipients,
				'subject' => $subject,
				'message' => $message,
				'headers' => $headers,
			),
			$data,
			$context
		);
		$mail['headers'] = array_map( array( self::class, 'header_safe' ), (array) ( $mail['headers'] ?? array() ) );
		$mail['subject'] = self::header_safe( (string) ( $mail['subject'] ?? $subject ) );

		$from_name = self::header_safe( str_replace( array( '"', '<', '>', ',', ';' ), '', sanitize_text_field( (string) ( $settings['email_from_name'] ?? '' ) ) ) );
		$from_cb   = static function () use ( $from_name ) {
			return $from_name;
		};
		$error    = '';
		$fail_cb  = static function ( $wp_error ) use ( &$error ) {
			$error = is_wp_error( $wp_error ) ? $wp_error->get_error_message() : 'unknown error';
		};
		if ( '' !== $from_name ) {
			add_filter( 'wp_mail_from_name', $from_cb, 99 );
		}
		add_action( 'wp_mail_failed', $fail_cb );
		$sent = wp_mail( (array) $mail['to'], $mail['subject'], (string) $mail['message'], $mail['headers'] );
		remove_action( 'wp_mail_failed', $fail_cb );
		if ( '' !== $from_name ) {
			remove_filter( 'wp_mail_from_name', $from_cb, 99 );
		}

		$result = array(
			'status' => $sent ? 'sent' : 'failed',
			'to'     => count( (array) $mail['to'] ),
		);
		if ( ! $sent ) {
			$result['error'] = mb_substr( '' !== $error ? $error : 'wp_mail returned false', 0, 300 );
		}
		return $result;
	}

	/**
	 * Sends the confirmation ("auto-reply") email to the visitor: the address in the chosen field, else the first
	 * email field. The message is plain text written by the site owner, with the same [placeholders].
	 *
	 * @param array<string,mixed>             $settings Widget settings.
	 * @param array<int, array<string,mixed>> $data     Submission data entries.
	 * @param array<string,mixed>             $context  Form context.
	 * @return array{status:string, error?:string}
	 */
	public static function autoreply( array $settings, array $data, array $context ): array {
		$wanted = Fields::slug( (string) ( $settings['autoreply_to'] ?? '' ) );
		$to     = '';
		foreach ( $data as $entry ) {
			$value = is_string( $entry['value'] ?? null ) ? trim( $entry['value'] ) : '';
			if ( '' === $value || sanitize_email( $value ) !== $value || ! is_email( $value ) ) {
				continue;
			}
			if ( '' !== $wanted ? $entry['id'] === $wanted : 'email' === ( $entry['type'] ?? '' ) ) {
				$to = $value;
				break;
			}
		}
		if ( '' === $to ) {
			return array(
				'status' => 'skipped',
				'error'  => 'no visitor email',
			);
		}
		$site  = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$plain = array();
		foreach ( $data as $entry ) {
			$plain[ $entry['id'] ] = self::header_safe( Fields::display( self::field_for( $entry, $context ), $entry['value'] ?? '' ) );
		}
		$rows = array();
		foreach ( $data as $entry ) {
			if ( 'file' === ( $entry['type'] ?? '' ) ) {
				continue;
			}
			$label  = '' !== (string) ( $entry['label'] ?? '' ) ? (string) $entry['label'] : (string) $entry['id'];
			$rows[] = '<p style="margin:0 0 12px"><strong>' . esc_html( $label ) . '</strong><br>' . ( '' !== $plain[ $entry['id'] ] ? esc_html( $plain[ $entry['id'] ] ) : '&mdash;' ) . '</p>';
		}
		$extra = array(
			'form_name' => (string) $context['form_name'],
			'page_url'  => (string) $context['page_url'],
			'site_name' => $site,
		);

		$subject = trim( (string) ( $settings['autoreply_subject'] ?? '' ) );
		if ( '' === $subject ) {
			$subject = __( 'Thanks for getting in touch with [site_name]', 'uncoder' );
		}
		$subject = mb_substr( self::header_safe( wp_strip_all_tags( self::placeholders( $subject, $plain, $extra ) ) ), 0, 200 );

		$text = (string) ( $settings['autoreply_message'] ?? '' );
		if ( '' === trim( $text ) ) {
			$text = __( "Hi,\n\nThanks for your message. We have received it and will get back to you soon.\n\n[site_name]", 'uncoder' );
		}
		$escaped = array_map( 'esc_html', $plain );
		$message = self::placeholders(
			nl2br( esc_html( $text ) ),
			$escaped,
			array(
				'form_name'  => esc_html( $extra['form_name'] ),
				'page_url'   => esc_html( $extra['page_url'] ),
				'site_name'  => esc_html( $site ),
				'all-fields' => implode( "\n", $rows ),
			)
		);
		$message = '<div style="font:15px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937">' . $message . '</div>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$reply   = trim( (string) ( $settings['autoreply_reply_to'] ?? '' ) );
		if ( '' !== $reply && sanitize_email( $reply ) === $reply && is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}

		/**
		 * Filters the auto-reply email before it is sent.
		 *
		 * @param array{to:string,subject:string,message:string,headers:string[]} $mail    Mail arguments.
		 * @param array<int, array<string,mixed>>                                 $data    Submission data.
		 * @param array<string,mixed>                                             $context Form context.
		 */
		$mail = (array) apply_filters(
			'uncoder_wb/forms/autoreply',
			array(
				'to'      => $to,
				'subject' => $subject,
				'message' => $message,
				'headers' => $headers,
			),
			$data,
			$context
		);

		$from_name = self::header_safe( str_replace( array( '"', '<', '>', ',', ';' ), '', sanitize_text_field( (string) ( $settings['autoreply_from_name'] ?? '' ) ) ) );
		$from_cb   = static fn() => $from_name;
		$error     = '';
		$fail_cb   = static function ( $wp_error ) use ( &$error ) {
			$error = is_wp_error( $wp_error ) ? $wp_error->get_error_message() : 'unknown error';
		};
		if ( '' !== $from_name ) {
			add_filter( 'wp_mail_from_name', $from_cb, 99 );
		}
		add_action( 'wp_mail_failed', $fail_cb );
		$sent = wp_mail(
			self::header_safe( (string) $mail['to'] ),
			self::header_safe( (string) ( $mail['subject'] ?? $subject ) ),
			(string) $mail['message'],
			array_map( array( self::class, 'header_safe' ), (array) ( $mail['headers'] ?? array() ) )
		);
		remove_action( 'wp_mail_failed', $fail_cb );
		if ( '' !== $from_name ) {
			remove_filter( 'wp_mail_from_name', $from_cb, 99 );
		}
		return $sent ? array( 'status' => 'sent' ) : array(
			'status' => 'failed',
			'error'  => mb_substr( '' !== $error ? $error : 'wp_mail returned false', 0, 300 ),
		);
	}

	/**
	 * Visitor email for Reply-To: the configured field, else the first email field.
	 *
	 * @param array<string,mixed>             $settings Settings.
	 * @param array<int, array<string,mixed>> $data     Data entries.
	 */
	private static function reply_to( array $settings, array $data ): string {
		$wanted = Fields::slug( (string) ( $settings['email_reply_to'] ?? '' ) );
		$first  = '';
		foreach ( $data as $entry ) {
			$value = is_string( $entry['value'] ?? null ) ? $entry['value'] : '';
			if ( '' === $value || ! is_email( $value ) ) {
				continue;
			}
			if ( '' !== $wanted && $entry['id'] === $wanted ) {
				return self::header_safe( $value );
			}
			if ( '' === $first && 'email' === ( $entry['type'] ?? '' ) ) {
				$first = $value;
			}
		}
		return '' === $wanted ? self::header_safe( $first ) : '';
	}

	/**
	 * Normalized field (for option labels) matching a data entry.
	 *
	 * @param array<string,mixed> $entry   Data entry.
	 * @param array<string,mixed> $context Context with 'fields'.
	 * @return array<string,mixed>
	 */
	private static function field_for( array $entry, array $context ): array {
		foreach ( (array) ( $context['fields'] ?? array() ) as $field ) {
			if ( ( $field['id'] ?? '' ) === ( $entry['id'] ?? '' ) ) {
				return $field;
			}
		}
		return array( 'type' => (string) ( $entry['type'] ?? 'text' ) );
	}

	/**
	 * Whether a webhook URL is acceptable (https, public host).
	 */
	public static function webhook_url( string $url ): string {
		$url = esc_url_raw( trim( $url ), array( 'https' ) );
		if ( '' === $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! wp_http_validate_url( $url ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * POSTs the submission as JSON. Short timeout; never follows redirects; SSRF-safe (wp_safe_remote_post).
	 *
	 * @param array<int, array<string,mixed>> $data    Data entries.
	 * @param array<string,mixed>             $context Form context.
	 * @return array{status:string, code?:int, error?:string}
	 */
	public static function webhook( string $url, array $data, array $context ): array {
		$url = self::webhook_url( $url );
		if ( '' === $url ) {
			return array(
				'status' => 'skipped',
				'error'  => 'webhook URL must be a public https:// URL',
			);
		}
		$fields = array();
		$clean  = array();
		foreach ( $data as $entry ) {
			// File links are for admins only (email); the webhook gets the file name.
			$value                   = $entry['value'] ?? '';
			$fields[ $entry['id'] ]  = $value;
			$clean[]                 = array(
				'id'    => $entry['id'],
				'label' => $entry['label'],
				'type'  => $entry['type'],
				'value' => $value,
			);
		}
		$payload = array(
			'event'         => 'form_submission',
			'form'          => array(
				'name'       => (string) $context['form_name'],
				'document'   => (int) $context['doc_id'],
				'element_id' => (string) $context['element_id'],
			),
			'submission_id' => (int) ( $context['submission_id'] ?? 0 ),
			'page_url'      => (string) $context['page_url'],
			'submitted_at'  => gmdate( 'c' ),
			'fields'        => (object) $fields,
			'data'          => $clean,
			'site'          => home_url( '/' ),
		);
		/**
		 * Filters the webhook JSON payload.
		 *
		 * @param array<string,mixed> $payload Payload.
		 * @param array<string,mixed> $context Form context.
		 */
		$payload = apply_filters( 'uncoder_wb/forms/webhook_payload', $payload, $context );

		/**
		 * Filters the webhook request arguments (timeout defaults to 3 seconds).
		 *
		 * @param array<string,mixed> $args    wp_safe_remote_post() args.
		 * @param string              $url     Webhook URL.
		 */
		$args = apply_filters(
			'uncoder_wb/forms/webhook_args',
			array(
				'timeout'     => 3,
				'redirection' => 0,
				'blocking'    => true,
				'headers'     => array(
					'Content-Type' => 'application/json; charset=utf-8',
					'User-Agent'   => 'Uncoder-Forms/' . ( defined( 'UNCODER_WB_VERSION' ) ? UNCODER_WB_VERSION : '1' ) . '; ' . home_url( '/' ),
				),
				'body'        => (string) wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'data_format' => 'body',
			),
			$url
		);
		$args['reject_unsafe_urls'] = true;

		$response = wp_safe_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 'failed',
				'error'  => mb_substr( $response->get_error_message(), 0, 300 ),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return array(
			'status' => $code >= 200 && $code < 300 ? 'ok' : 'failed',
			'code'   => $code,
		);
	}
}
