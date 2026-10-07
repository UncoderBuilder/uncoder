<?php
/**
 * Form integrations: newsletter lists (Mailchimp, MailerLite, Brevo), Slack / Discord notifications and the
 * auto-reply email to the visitor.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Forms;

use Uncoder\Builder\Rest\Settings_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * API keys live in uncoder_wb_settings['integrations'] = { mailchimp: { key }, mailerlite: { key },
 * brevo: { key } } and never leave the server (the settings screen only learns whether one is saved). Each
 * form picks a service and a list in its "After submit" settings.
 */
final class Integrations {

	public const SERVICES = array(
		'mailchimp'      => 'Mailchimp',
		'mailerlite'     => 'MailerLite',
		'brevo'          => 'Brevo',
		'activecampaign' => 'ActiveCampaign',
	);

	/** @return array<string, array{key:string, url?:string}> */
	public static function get(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$raw    = is_array( $stored ) && is_array( $stored['integrations'] ?? null ) ? $stored['integrations'] : array();
		$out    = array();
		foreach ( array_keys( self::SERVICES ) as $service ) {
			$out[ $service ] = array( 'key' => (string) ( $raw[ $service ]['key'] ?? '' ) );
		}
		// ActiveCampaign also needs the account's API URL (https://{account}.api-us1.com).
		$out['activecampaign']['url'] = (string) ( $raw['activecampaign']['url'] ?? '' );
		return $out;
	}

	/**
	 * New keys from the settings screen; an empty key keeps the saved one, "-" removes it.
	 *
	 * @param array<string,mixed> $raw Raw.
	 * @return array<string, array{key:string}>
	 */
	public static function sanitize( array $raw ): array {
		$current = self::get();
		foreach ( array_keys( self::SERVICES ) as $service ) {
			$key = isset( $raw[ $service ]['key'] ) ? trim( (string) $raw[ $service ]['key'] ) : '';
			if ( '-' === $key ) {
				$current[ $service ]['key'] = '';
			} elseif ( '' !== $key ) {
				$current[ $service ]['key'] = substr( (string) preg_replace( '/[^A-Za-z0-9_\-\.]/', '', $key ), 0, 400 );
			}
		}
		if ( isset( $raw['activecampaign']['url'] ) ) {
			$url  = esc_url_raw( trim( (string) $raw['activecampaign']['url'] ), array( 'https' ) );
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			// Only the ActiveCampaign API hosts (the key is sent there).
			$current['activecampaign']['url'] = '' !== $url && preg_match( '/\.(api-us\d+\.com|activehosted\.com)$/', $host ) ? 'https://' . $host : '';
		}
		return $current;
	}

	/** @return array<string, array{connected:bool, url?:string}> For the settings screen (no keys). */
	public static function public_settings(): array {
		$out                          = array_map( static fn( $s ) => array( 'connected' => '' !== $s['key'] ), self::get() );
		$out['activecampaign']['url'] = self::get()['activecampaign']['url'];
		return $out;
	}

	/** Services with a saved key (for the form's list picker). */
	public static function connected(): array {
		return array_keys( array_filter( self::get(), static fn( $s ) => '' !== $s['key'] ) );
	}

	/**
	 * Adds a subscriber. Returns the action log entry.
	 *
	 * @return array{status:string, error?:string, code?:int}
	 */
	public static function subscribe( string $service, string $list, string $email, string $name, bool $double ): array {
		$key = self::get()[ $service ]['key'] ?? '';
		if ( '' === $key || '' === $list || ! is_email( $email ) ) {
			return array(
				'status' => 'skipped',
				'error'  => '' === $key ? 'no API key saved for ' . $service : ( '' === $list ? 'no list chosen' : 'no valid email' ),
			);
		}
		$name = sanitize_text_field( $name );
		switch ( $service ) {
			case 'mailchimp':
				// Keys end in "-us21": the data center.
				$dc = substr( (string) strrchr( $key, '-' ), 1 );
				if ( ! preg_match( '/^[a-z]{2}\d{1,3}$/', $dc ) ) {
					return array(
						'status' => 'error',
						'error'  => 'the Mailchimp API key has no data center suffix (e.g. -us21)',
					);
				}
				$res = wp_safe_remote_request(
					'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . rawurlencode( $list ) . '/members/' . md5( strtolower( $email ) ),
					array(
						'method'  => 'PUT',
						'timeout' => 8,
						'headers' => array(
							'Authorization' => 'Basic ' . base64_encode( 'uncoder:' . $key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
							'Content-Type'  => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'email_address' => $email,
								'status_if_new' => $double ? 'pending' : 'subscribed',
								'merge_fields'  => $name ? array( 'FNAME' => $name ) : new \stdClass(),
							)
						),
					)
				);
				break;
			case 'mailerlite':
				$res = wp_safe_remote_post(
					'https://connect.mailerlite.com/api/subscribers',
					array(
						'timeout' => 8,
						'headers' => array(
							'Authorization' => 'Bearer ' . $key,
							'Content-Type'  => 'application/json',
							'Accept'        => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'email'  => $email,
								'fields' => $name ? array( 'name' => $name ) : new \stdClass(),
								'groups' => array( $list ),
							)
						),
					)
				);
				break;
			case 'brevo':
				$res = wp_safe_remote_post(
					'https://api.brevo.com/v3/contacts',
					array(
						'timeout' => 8,
						'headers' => array(
							'api-key'      => $key,
							'Content-Type' => 'application/json',
							'Accept'       => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'email'         => $email,
								'attributes'    => $name ? array( 'FIRSTNAME' => $name ) : new \stdClass(),
								'listIds'       => array( (int) $list ),
								'updateEnabled' => true,
							)
						),
					)
				);
				break;
			case 'activecampaign':
				$base = self::get()['activecampaign']['url'];
				if ( '' === $base ) {
					return array(
						'status' => 'skipped',
						'error'  => 'no ActiveCampaign API URL saved',
					);
				}
				$headers = array(
					'Api-Token'    => $key,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				);
				$sync    = wp_safe_remote_post(
					$base . '/api/3/contact/sync',
					array(
						'timeout' => 8,
						'headers' => $headers,
						'body'    => wp_json_encode( array( 'contact' => array_filter( array( 'email' => $email, 'firstName' => $name ) ) ) ),
					)
				);
				$contact = is_wp_error( $sync ) ? 0 : (int) ( json_decode( (string) wp_remote_retrieve_body( $sync ), true )['contact']['id'] ?? 0 );
				if ( ! $contact ) {
					$res = $sync;
					break;
				}
				$res = wp_safe_remote_post(
					$base . '/api/3/contactLists',
					array(
						'timeout' => 8,
						'headers' => $headers,
						'body'    => wp_json_encode( array( 'contactList' => array( 'list' => (int) $list, 'contact' => $contact, 'status' => 1 ) ) ),
					)
				);
				break;
			default:
				return array(
					'status' => 'skipped',
					'error'  => 'unknown service',
				);
		}
		if ( is_wp_error( $res ) ) {
			return array(
				'status' => 'error',
				'error'  => $res->get_error_message(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code >= 200 && $code < 300 ) {
			return array(
				'status' => 'sent',
				'code'   => $code,
			);
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return array(
			'status' => 'error',
			'code'   => $code,
			'error'  => is_array( $body ) ? substr( (string) ( $body['detail'] ?? $body['message'] ?? $body['title'] ?? 'request failed' ), 0, 300 ) : 'request failed',
		);
	}

	/**
	 * A short message in a Slack or Discord channel (incoming webhook URL).
	 *
	 * @param array<int, array<string,mixed>> $data    Submission entries.
	 * @param array<string,mixed>             $context Form context.
	 * @return array{status:string, error?:string, code?:int}
	 */
	public static function chat( string $platform, string $url, array $data, array $context ): array {
		$url   = Actions::webhook_url( $url );
		$host  = (string) wp_parse_url( $url, PHP_URL_HOST );
		$valid = 'slack' === $platform ? 'hooks.slack.com' === $host : in_array( $host, array( 'discord.com', 'discordapp.com' ), true );
		if ( '' === $url || ! $valid ) {
			return array(
				'status' => 'skipped',
				'error'  => 'slack' === $platform ? 'use a https://hooks.slack.com/… webhook URL' : 'use a https://discord.com/api/webhooks/… URL',
			);
		}
		$lines = array();
		foreach ( $data as $entry ) {
			$value = is_array( $entry['value'] ?? null ) ? implode( ', ', array_map( 'strval', $entry['value'] ) ) : (string) ( $entry['value'] ?? '' );
			if ( '' !== trim( $value ) ) {
				$lines[] = ( 'slack' === $platform ? '*' . $entry['label'] . ':* ' : '**' . $entry['label'] . ':** ' ) . wp_strip_all_tags( $value );
			}
		}
		/* translators: 1: form name, 2: site name. */
		$title = sprintf( __( 'New “%1$s” submission on %2$s', 'uncoder' ), (string) $context['form_name'], get_bloginfo( 'name' ) );
		$text  = $title . "\n" . implode( "\n", $lines ) . ( ! empty( $context['page_url'] ) ? "\n" . (string) $context['page_url'] : '' );
		$res   = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 6,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				// Visitors' answers must not ping the channel: Discord would expand @everyone / @here otherwise
				// (Slack only expands <!channel>-style mentions, which wp_strip_all_tags() removed above).
				'body'        => wp_json_encode(
					'slack' === $platform
						? array( 'text' => $text )
						: array(
							'content'          => mb_substr( $text, 0, 1990 ),
							'allowed_mentions' => array( 'parse' => array() ),
						)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array(
				'status' => 'error',
				'error'  => $res->get_error_message(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return $code >= 200 && $code < 300 ? array(
			'status' => 'sent',
			'code'   => $code,
		) : array(
			'status' => 'error',
			'code'   => $code,
		);
	}

	/**
	 * Value of a submitted field by id (first email field when $id is empty and $type is "email").
	 *
	 * @param array<int, array<string,mixed>> $data Submission entries.
	 */
	public static function value( array $data, string $id, string $type = '' ): string {
		foreach ( $data as $entry ) {
			if ( ( '' !== $id && ( $entry['id'] ?? '' ) === $id ) || ( '' === $id && '' !== $type && ( $entry['type'] ?? '' ) === $type ) ) {
				return is_array( $entry['value'] ?? null ) ? implode( ', ', array_map( 'strval', $entry['value'] ) ) : (string) ( $entry['value'] ?? '' );
			}
		}
		return '';
	}
}
