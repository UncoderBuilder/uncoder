<?php
/**
 * Cookie consent banner; holds back analytics / marketing code snippets until the visitor agrees.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Editor\Preview;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Settings_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * uncoder_wb_settings['consent'] holds the banner texts and options. Custom Code snippets of the
 * "analytics" and "marketing" categories are printed inside <template data-uncoder-consent="…"> while
 * consent is on; the consent module brings them to life once the visitor agrees (cookie uncoder_consent),
 * and updates Google Consent Mode v2 when that option is on. Rejecting is as easy as accepting.
 */
final class Consent {

	public const CATEGORIES = array( 'analytics', 'marketing' );
	public const COOKIE     = 'uncoder_consent';

	public function register(): void {
		add_action( 'wp_head', array( $this, 'consent_mode' ), 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 22 );
		add_action( 'wp_footer', array( $this, 'banner' ), 50 );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$raw    = is_array( $stored ) && is_array( $stored['consent'] ?? null ) ? $stored['consent'] : array();
		return self::sanitize( $raw, false );
	}

	/**
	 * @param array<string,mixed> $raw Raw settings.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw, bool $bump = false ): array {
		$text = static fn( $key, $max = 200 ) => mb_substr( sanitize_text_field( (string) ( $raw[ $key ] ?? '' ) ), 0, $max );
		$out  = array(
			'enabled'      => ! empty( $raw['enabled'] ),
			'position'     => in_array( $raw['position'] ?? 'bar', array( 'bar', 'box-left', 'box-right' ), true ) ? (string) ( $raw['position'] ?? 'bar' ) : 'bar',
			'title'        => $text( 'title', 80 ),
			'message'      => mb_substr( wp_kses( (string) ( $raw['message'] ?? '' ), Utils::kses_inline() ), 0, 1000 ),
			'accept_label' => $text( 'accept_label', 40 ),
			'reject_label' => $text( 'reject_label', 40 ),
			'prefs_label'  => $text( 'prefs_label', 40 ),
			'privacy_url'  => esc_url_raw( (string) ( $raw['privacy_url'] ?? '' ) ),
			'consent_mode' => ! empty( $raw['consent_mode'] ),
			'reopen'       => ! empty( $raw['reopen'] ),
			'version'      => max( 1, (int) ( $raw['version'] ?? 1 ) ),
		);
		if ( $bump ) {
			++$out['version'];
		}
		return $out;
	}

	public static function active(): bool {
		if ( is_admin() || is_feed() ) {
			return false;
		}
		$preview = Plugin::instance()->module( 'preview' );
		return ! empty( self::get()['enabled'] ) && ! ( $preview instanceof Preview && $preview->active() );
	}

	/**
	 * Google Consent Mode v2 defaults: everything denied until the visitor decides.
	 */
	public function consent_mode(): void {
		if ( ! self::active() || empty( self::get()['consent_mode'] ) ) {
			return;
		}
		wp_print_inline_script_tag( "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});" );
	}

	public function enqueue(): void {
		if ( self::active() ) {
			Assets::enqueue_base();
			Assets::enqueue_module( 'consent' );
			Assets::enqueue_widget_style( 'consent' );
		}
	}

	/**
	 * Wraps a snippet so it only runs after consent for its category.
	 */
	public static function gate( string $code, string $category ): string {
		if ( ! in_array( $category, self::CATEGORIES, true ) || ! self::active() ) {
			return $code;
		}
		return '<template data-uncoder-consent="' . esc_attr( $category ) . '">' . $code . '</template>';
	}

	public function banner(): void {
		if ( ! self::active() ) {
			return;
		}
		$c       = self::get();
		$privacy = '' !== $c['privacy_url'] ? $c['privacy_url'] : (string) get_privacy_policy_url();
		$title   = '' !== $c['title'] ? $c['title'] : __( 'Cookies & privacy', 'uncoder' );
		$message = '' !== $c['message'] ? $c['message'] : esc_html__( 'We use cookies to understand how the site is used and to improve it. You can accept them, reject them or choose which ones to allow.', 'uncoder' );
		$labels  = array(
			'accept' => '' !== $c['accept_label'] ? $c['accept_label'] : __( 'Accept all', 'uncoder' ),
			'reject' => '' !== $c['reject_label'] ? $c['reject_label'] : __( 'Reject all', 'uncoder' ),
			'prefs'  => '' !== $c['prefs_label'] ? $c['prefs_label'] : __( 'Preferences', 'uncoder' ),
		);
		$cats = array(
			'necessary' => array( __( 'Necessary', 'uncoder' ), __( 'Needed for the site to work, such as security and remembering this choice. Always on.', 'uncoder' ) ),
			'analytics' => array( __( 'Analytics', 'uncoder' ), __( 'Anonymous statistics that show us which pages are visited and how.', 'uncoder' ) ),
			'marketing' => array( __( 'Marketing', 'uncoder' ), __( 'Personalized ads and measuring the success of our campaigns.', 'uncoder' ) ),
		);
		$settings = array(
			'version'     => $c['version'],
			'consentMode' => $c['consent_mode'],
			'cookie'      => self::COOKIE,
		);
		$id = 'uncoder-consent-title';
		echo '<div class="uncoder-consent uncoder-consent--' . esc_attr( $c['position'] ) . '" data-uncoder-js="consent" data-settings="' . esc_attr( (string) wp_json_encode( $settings ) ) . '" role="region" aria-labelledby="' . esc_attr( $id ) . '" hidden>';
		echo '<div class="uncoder-consent__body"><p class="uncoder-consent__title" id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</p>';
		echo '<p class="uncoder-consent__text">' . wp_kses( $message, Utils::kses_inline() ) . ( '' !== $privacy ? ' <a class="uncoder-consent__link" href="' . esc_url( $privacy ) . '">' . esc_html__( 'Privacy policy', 'uncoder' ) . '</a>' : '' ) . '</p>';
		echo '<form class="uncoder-consent__prefs" hidden><fieldset><legend class="uncoder-sr-only">' . esc_html__( 'Cookie categories', 'uncoder' ) . '</legend>';
		foreach ( $cats as $key => $cat ) {
			echo '<label class="uncoder-consent__cat"><input type="checkbox" name="' . esc_attr( $key ) . '"' . ( 'necessary' === $key ? ' checked disabled' : '' ) . '><span><strong>' . esc_html( $cat[0] ) . '</strong> ' . esc_html( $cat[1] ) . '</span></label>';
		}
		echo '</fieldset></form></div>';
		echo '<div class="uncoder-consent__actions">'
			. '<button type="button" class="uncoder-btn uncoder-btn--secondary uncoder-btn--sm uncoder-consent__btn" data-consent="prefs" aria-expanded="false">' . esc_html( $labels['prefs'] ) . '</button>'
			. '<button type="button" class="uncoder-btn uncoder-btn--secondary uncoder-btn--sm uncoder-consent__btn" data-consent="reject">' . esc_html( $labels['reject'] ) . '</button>'
			. '<button type="button" class="uncoder-btn uncoder-btn--primary uncoder-btn--sm uncoder-consent__btn" data-consent="accept">' . esc_html( $labels['accept'] ) . '</button>'
			. '<button type="button" class="uncoder-btn uncoder-btn--primary uncoder-btn--sm uncoder-consent__btn" data-consent="save" hidden>' . esc_html__( 'Save choices', 'uncoder' ) . '</button>'
			. '</div></div>';
		if ( $c['reopen'] ) {
			echo '<button type="button" class="uncoder-consent-reopen" data-consent-open hidden aria-label="' . esc_attr__( 'Cookie settings', 'uncoder' ) . '">' . \Uncoder\Builder\Core\Icons::render( 'cookie', array( 'width' => '20', 'height' => '20' ) ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG.
		}
	}
}
