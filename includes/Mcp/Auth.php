<?php
/**
 * Resolves the credential of an MCP request into a user + scopes.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Install;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Accepted credentials: OAuth access tokens, API keys and connection links (Authorization: Bearer …), WordPress
 * Application Passwords (Basic), and a connection link in the URL (?token=uncoder_link_…). A URL only ever accepts a
 * connection link — never an API key or an OAuth token — over HTTPS (or on a local site), while links are allowed.
 */
final class Auth {

	/**
	 * @return Context|WP_Error
	 */
	public static function resolve( WP_REST_Request $request ) {
		$header = (string) $request->get_header( 'authorization' );
		$secret = '';
		if ( preg_match( '/^Bearer\s+(\S+)$/i', $header, $m ) ) {
			$secret = $m[1];
		} elseif ( '' !== Mcp::url_token() ) {
			$secret = Mcp::url_token();
			if ( 0 !== strpos( $secret, 'uncoder_link_' ) ) {
				return new WP_Error( 'invalid_token', __( 'Only a connection link can be used in a URL. Create one under Uncoder → AI & MCP → Connect a client, or send this key in an Authorization: Bearer header.', 'uncoder' ), array( 'status' => 401 ) );
			}
			if ( ! Settings::get( 'links' ) ) {
				return new WP_Error( 'invalid_token', __( 'Connection links are turned off on this site (Uncoder → AI & MCP → Server settings).', 'uncoder' ), array( 'status' => 401 ) );
			}
			if ( ! self::secure_transport() ) {
				return new WP_Error( 'invalid_token', __( 'Connection links work only over HTTPS. Turn on SSL for this site.', 'uncoder' ), array( 'status' => 401 ) );
			}
		}

		$context = null;
		if ( '' !== $secret ) {
			// Keys and tokens issued before the uncoder_ prefix (unc_key_…, unc_at_…) keep working: lookup is by
			// the hash of the whole secret, the prefix only picks the token type.
			if ( 0 === strpos( $secret, 'uncoder_key_' ) || 0 === strpos( $secret, 'unc_key_' ) ) {
				$row = Tokens::find( $secret, 'api_key' );
				if ( $row ) {
					$context = new Context( (int) $row->user_id, array_filter( explode( ' ', (string) $row->scopes ) ), (int) $row->id, 'API key: ' . $row->name, 'api_key' );
				}
			} elseif ( 0 === strpos( $secret, 'uncoder_link_' ) ) {
				$row = Tokens::find( $secret, 'link' );
				if ( $row ) {
					$context = new Context( (int) $row->user_id, array_filter( explode( ' ', (string) $row->scopes ) ), (int) $row->id, 'Connection link: ' . $row->name, 'link' );
				}
			} elseif ( 0 === strpos( $secret, 'uncoder_at_' ) || 0 === strpos( $secret, 'unc_at_' ) ) {
				$row = Tokens::find( $secret, 'access' );
				if ( $row ) {
					$client  = OAuth::client( (string) $row->client_id );
					$label   = $client ? $client['client_name'] : Tokens::client_label( (string) $row->client_id );
					$context = new Context( (int) $row->user_id, array_filter( explode( ' ', (string) $row->scopes ) ), (int) $row->id, (string) $label, 'oauth' );
				}
			}
			if ( null === $context ) {
				return new WP_Error( 'invalid_token', __( 'The access token is missing, expired or revoked.', 'uncoder' ), array( 'status' => 401 ) );
			}
			Tokens::touch( $context->token_id );
		} elseif ( 0 === stripos( $header, 'Basic ' ) && get_current_user_id() && function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available() ) {
			// WordPress core already validated the Application Password.
			$context = new Context( get_current_user_id(), array_keys( Tokens::SCOPES ), 0, 'Application password', 'app_password' );
		} else {
			return new WP_Error( 'unauthorized', __( 'Authentication required.', 'uncoder' ), array( 'status' => 401 ) );
		}

		$user = get_userdata( $context->user_id );
		if ( ! $user || ! user_can( $user, Install::MCP_CAP ) ) {
			return new WP_Error( 'forbidden', __( 'This account is not allowed to use the Uncoder MCP server.', 'uncoder' ), array( 'status' => 403 ) );
		}
		wp_set_current_user( $user->ID );
		return $context;
	}

	/** HTTPS (also behind a proxy that says so), or a local site where a link never leaves the computer. */
	private static function secure_transport(): bool {
		if ( is_ssl() || 'https' === strtolower( (string) ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return true;
		}
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return in_array( wp_get_environment_type(), array( 'local', 'development' ), true )
			|| in_array( $host, array( 'localhost', '127.0.0.1', '[::1]', '::1' ), true )
			|| (bool) preg_match( '/\.(test|local|localhost)$/', $host );
	}
}
