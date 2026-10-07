<?php
/**
 * Adobe Fonts (Typekit) web projects.
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
 * uncoder_wb_settings['adobe_fonts'] = { project, families: { css family name: [weights] }, synced }.
 * Connecting a Web Project ID reads its stylesheet (https://use.typekit.net/{id}.css) once to learn the
 * families; they are then offered in every font picker, and that stylesheet is loaded only on pages that use
 * one of them (and in the editor). Adobe serves the fonts: an external service, like Google Fonts.
 */
final class Adobe_Fonts {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$can = static fn() => current_user_can( 'manage_options' );
		register_rest_route(
			Rest::NS,
			'/adobe-fonts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn() => new WP_REST_Response( self::get() ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'connect' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'disconnect' ),
					'permission_callback' => $can,
				),
			)
		);
	}

	/** @return array{project:string, families:array<string,string[]>, synced:int} */
	public static function get(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$raw    = is_array( $stored ) && is_array( $stored['adobe_fonts'] ?? null ) ? $stored['adobe_fonts'] : array();
		return array(
			'project'  => preg_match( '/^[a-z0-9]{5,12}$/i', (string) ( $raw['project'] ?? '' ) ) ? (string) $raw['project'] : '',
			'families' => is_array( $raw['families'] ?? null ) ? $raw['families'] : array(),
			'synced'   => (int) ( $raw['synced'] ?? 0 ),
		);
	}

	private static function put( array $value ): void {
		$stored                = (array) get_option( Settings_Controller::OPTION, array() );
		$stored['adobe_fonts'] = $value;
		update_option( Settings_Controller::OPTION, $stored );
	}

	public static function url(): string {
		$p = self::get()['project'];
		return '' !== $p ? 'https://use.typekit.net/' . $p . '.css' : '';
	}

	/**
	 * Families and weights declared in a Typekit stylesheet.
	 *
	 * @return array<string,string[]>
	 */
	public static function parse( string $css ): array {
		$out = array();
		preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $blocks );
		foreach ( $blocks[1] as $block ) {
			if ( ! preg_match( '/font-family\s*:\s*["\']?([^"\';]+)["\']?\s*;/i', $block, $f ) ) {
				continue;
			}
			$family = trim( (string) preg_replace( '/[^A-Za-z0-9 \-_]/', '', $f[1] ) );
			if ( '' === $family ) {
				continue;
			}
			$weight = preg_match( '/font-weight\s*:\s*(\d{3})/i', $block, $w ) ? $w[1] : '400';
			$out[ $family ][] = $weight;
		}
		foreach ( $out as $family => $weights ) {
			$weights = array_values( array_unique( $weights ) );
			sort( $weights );
			$out[ $family ] = $weights;
		}
		ksort( $out );
		return $out;
	}

	public function connect( WP_REST_Request $request ) {
		$project = strtolower( trim( (string) $request->get_param( 'project' ) ) );
		if ( '' === $project ) {
			$project = self::get()['project']; // "Sync again".
		}
		if ( ! preg_match( '/^[a-z0-9]{5,12}$/', $project ) ) {
			return new WP_Error( 'uncoder_adobe', __( 'Enter the Web Project ID from Adobe Fonts (letters and numbers, e.g. abc1def).', 'uncoder' ), array( 'status' => 400 ) );
		}
		$res = wp_remote_get( 'https://use.typekit.net/' . $project . '.css', array( 'timeout' => 10 ) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'uncoder_adobe', $res->get_error_message(), array( 'status' => 502 ) );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new WP_Error( 'uncoder_adobe', __( 'Adobe Fonts does not know this project. Check the ID and that the project is published.', 'uncoder' ), array( 'status' => 404 ) );
		}
		$families = self::parse( (string) wp_remote_retrieve_body( $res ) );
		if ( ! $families ) {
			return new WP_Error( 'uncoder_adobe', __( 'This project has no fonts yet. Add fonts to it on fonts.adobe.com, then sync again.', 'uncoder' ), array( 'status' => 400 ) );
		}
		self::put(
			array(
				'project'  => $project,
				'families' => $families,
				'synced'   => time(),
			)
		);
		return new WP_REST_Response( self::get() );
	}

	public function disconnect(): WP_REST_Response {
		self::put( array() );
		return new WP_REST_Response( self::get() );
	}

	/**
	 * Font picker entries (editor and Design System), like Custom_Fonts::catalog().
	 *
	 * @return array<string, array<string,mixed>>
	 */
	public static function catalog(): array {
		$out = array();
		foreach ( self::get()['families'] as $family => $weights ) {
			$out[ (string) $family ] = array(
				'c'      => preg_match( '/serif|garamond|baskerville|caslon|didot|bodoni|times/i', (string) $family ) && ! preg_match( '/sans/i', (string) $family ) ? 'serif' : 'sans-serif',
				'w'      => array_values( array_map( 'strval', (array) $weights ) ),
				'custom' => true,
				'adobe'  => true,
			);
		}
		return $out;
	}

	public static function has( string $family ): bool {
		return isset( self::get()['families'][ $family ] );
	}

	/**
	 * Loads the project stylesheet when one of these families is used.
	 *
	 * @param string[] $families Families.
	 */
	public static function enqueue_for( array $families ): void {
		$url = self::url();
		if ( '' === $url || ! array_filter( $families, array( self::class, 'has' ) ) ) {
			return;
		}
		wp_enqueue_style( 'uncoder-adobe-fonts', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Adobe versions the project stylesheet.
	}

	/** Every family (editor canvas and editor UI: previews and any element may use them). */
	public static function enqueue_all(): void {
		$url = self::url();
		if ( '' !== $url && self::get()['families'] ) {
			wp_enqueue_style( 'uncoder-adobe-fonts', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}
}
