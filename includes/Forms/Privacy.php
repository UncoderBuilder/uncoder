<?php
/**
 * Personal data tools for form submissions: WordPress' export and erase requests, and the suggested
 * privacy policy text.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Tools → Export / Erase Personal Data find the submissions that contain the requester's email address
 * (in any field) or that were sent while logged in as that user. Erasing deletes those submissions and
 * their uploaded files.
 */
final class Privacy {

	/** Rows looked at per exporter / eraser page. */
	private const BATCH = 50;

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy_text' ) );
	}

	/**
	 * @param array<string, array<string,mixed>> $exporters Exporters.
	 * @return array<string, array<string,mixed>>
	 */
	public function exporters( $exporters ): array {
		$exporters                             = (array) $exporters;
		$exporters['uncoder-form-submissions'] = array(
			'exporter_friendly_name' => __( 'Uncoder form submissions', 'uncoder' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * @param array<string, array<string,mixed>> $erasers Erasers.
	 * @return array<string, array<string,mixed>>
	 */
	public function erasers( $erasers ): array {
		$erasers                             = (array) $erasers;
		$erasers['uncoder-form-submissions'] = array(
			'eraser_friendly_name' => __( 'Uncoder form submissions', 'uncoder' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Candidate rows (the LIKE match is confirmed in PHP by belongs_to()).
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private static function candidates( string $email, int $offset ): array {
		global $wpdb;
		$table = Store::table();
		$user  = get_user_by( 'email', $email );
		$like  = '%' . $wpdb->esc_like( $email ) . '%';
		if ( $user ) {
			$uid = '"user_id":' . (int) $user->ID;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, form_name, data, meta, created_at FROM %i WHERE data LIKE %s OR meta LIKE %s OR meta LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d', $table, $like, '%' . $wpdb->esc_like( $uid . ',' ) . '%', '%' . $wpdb->esc_like( $uid . '}' ) . '%', self::BATCH, $offset ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, form_name, data, meta, created_at FROM %i WHERE data LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d', $table, $like, self::BATCH, $offset ), ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Whether a stored submission holds this email address or was sent by this user.
	 *
	 * @param array<string,mixed> $row Row.
	 */
	private static function belongs_to( array $row, string $email, int $user_id ): bool {
		$meta = json_decode( (string) ( $row['meta'] ?? '' ), true );
		if ( $user_id && is_array( $meta ) && (int) ( $meta['user_id'] ?? 0 ) === $user_id ) {
			return true;
		}
		$data  = json_decode( (string) ( $row['data'] ?? '' ), true );
		$email = strtolower( $email );
		foreach ( is_array( $data ) ? $data : array() as $entry ) {
			$values = is_array( $entry['value'] ?? null ) ? $entry['value'] : array( $entry['value'] ?? '' );
			foreach ( $values as $value ) {
				if ( is_scalar( $value ) && strtolower( trim( (string) $value ) ) === $email ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return array{data: array<int, array<string,mixed>>, done: bool}
	 */
	public function export( $email, $page = 1 ): array {
		$email = sanitize_email( (string) $email );
		$page  = max( 1, (int) $page );
		if ( '' === $email ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$user    = get_user_by( 'email', $email );
		$user_id = $user ? (int) $user->ID : 0;
		$rows    = self::candidates( $email, ( $page - 1 ) * self::BATCH );
		$items   = array();
		foreach ( $rows as $row ) {
			if ( ! self::belongs_to( $row, $email, $user_id ) ) {
				continue;
			}
			$meta   = json_decode( (string) $row['meta'], true );
			$meta   = is_array( $meta ) ? $meta : array();
			$fields = array(
				array(
					'name'  => __( 'Form', 'uncoder' ),
					'value' => (string) $row['form_name'],
				),
				array(
					'name'  => __( 'Sent', 'uncoder' ),
					'value' => (string) $row['created_at'] . ' UTC',
				),
			);
			if ( ! empty( $meta['page_url'] ) ) {
				$fields[] = array(
					'name'  => __( 'Page', 'uncoder' ),
					'value' => (string) $meta['page_url'],
				);
			}
			$data = json_decode( (string) $row['data'], true );
			foreach ( is_array( $data ) ? $data : array() as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$label    = '' !== (string) ( $entry['label'] ?? '' ) ? (string) $entry['label'] : (string) ( $entry['id'] ?? '' );
				$fields[] = array(
					'name'  => $label,
					'value' => Fields::display( array( 'type' => (string) ( $entry['type'] ?? 'text' ) ), $entry['value'] ?? '' ),
				);
			}
			if ( ! empty( $meta['ip'] ) ) {
				$fields[] = array(
					'name'  => __( 'IP address (anonymized)', 'uncoder' ),
					'value' => (string) $meta['ip'],
				);
			}
			if ( ! empty( $meta['user_agent'] ) ) {
				$fields[] = array(
					'name'  => __( 'Browser', 'uncoder' ),
					'value' => (string) $meta['user_agent'],
				);
			}
			$items[] = array(
				'group_id'    => 'uncoder-form-submissions',
				'group_label' => __( 'Form submissions', 'uncoder' ),
				'item_id'     => 'uncoder-submission-' . (int) $row['id'],
				'data'        => $fields,
			);
		}
		return array(
			'data' => $items,
			'done' => count( $rows ) < self::BATCH,
		);
	}

	/**
	 * Deletes the submissions (and uploaded files) of this email address / user.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase( $email, $page = 1 ): array {
		global $wpdb;
		$email  = sanitize_email( (string) $email );
		$result = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
		if ( '' === $email ) {
			return $result;
		}
		$user    = get_user_by( 'email', $email );
		$user_id = $user ? (int) $user->ID : 0;
		// Deleted rows drop out of the next query, so every page starts from the top.
		$rows    = self::candidates( $email, 0 );
		$deleted = 0;
		foreach ( $rows as $row ) {
			if ( ! self::belongs_to( $row, $email, $user_id ) ) {
				continue;
			}
			$data = json_decode( (string) $row['data'], true );
			if ( is_array( $data ) ) {
				Uploads::delete_for( $data );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $wpdb->delete( Store::table(), array( 'id' => (int) $row['id'] ), array( '%d' ) ) ) {
				++$deleted;
			}
		}
		$result['items_removed'] = $deleted > 0;
		// Another page only when this one was full and removed something (rows that merely mention the
		// address in passing are left alone and would otherwise come back forever).
		$result['done'] = count( $rows ) < self::BATCH || 0 === $deleted;
		return $result;
	}

	/**
	 * Suggested text for Settings → Privacy → Policy guide.
	 */
	public function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p class="privacy-policy-tutorial">' . esc_html__( 'Uncoder stores what visitors send through its forms. Describe which forms your site has and why you keep their answers.', 'uncoder' ) . '</p>'
			. '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'uncoder' ) . '</strong> '
			. esc_html__( 'When you send a form on this website, we store the information you enter (and any file you attach), the page you sent it from, the time, your browser type and an anonymized version of your IP address. We use it to answer you and to protect the form from spam. Messages marked as spam are deleted automatically after 30 days. You can ask us for a copy of your submissions or ask us to delete them.', 'uncoder' )
			. '</p><p>' . esc_html__( 'If this website uses a CAPTCHA (Cloudflare Turnstile, hCaptcha or Google reCAPTCHA) on a form, that service receives your IP address and browser details when the form loads and is sent. If a form sends its answers to a newsletter or chat service (Mailchimp, MailerLite, Brevo, ActiveCampaign, Slack, Discord) or to a webhook, the answers are passed to that service.', 'uncoder' ) . '</p>';
		wp_add_privacy_policy_content( \Uncoder\Builder\Site\White_Label::active() ? \Uncoder\Builder\Core\Brand::name() : 'Uncoder – AI-Powered Website Builder', wp_kses_post( $text ) );
	}
}
