<?php
/**
 * Forms module: submit endpoint, token endpoint, file downloads and cleanup.
 *
 * REST contract
 * -------------
 * POST /wp-json/uncoder/v1/forms/submit   (multipart/form-data, x-www-form-urlencoded or JSON)
 *   doc_id      int     Document that holds the form (the page itself, or a published uncoder_template).
 *   post_id     int     Page the form was shown on (context; equals doc_id for page forms).
 *   element_id  string  Form element id.
 *   ts, token   Signed timestamp from the rendered form (or GET /forms/token).
 *   fields      object  { <field_id>: value | value[] } — files as fields[<field_id>] in multipart.
 *   _hp         string  Honeypot, must be empty.
 *   _unc_js     "1"     Set by the front-end module → JSON response. Without it (no-JS form post)
 *                       the endpoint answers with a 303 redirect (success) or a small HTML page (errors).
 *   _redirect   string  (query or body) Page to return to after a no-JS post; same-site only.
 * Response (JSON): { success: bool, message: string, redirect?: string, errors: { field_id: message }, code?: string }
 *   200 success · 400 invalid/expired form · 403 foreign origin · 404 unknown form · 422 validation · 429 rate limit · 500 storage.
 *
 * GET /wp-json/uncoder/v1/forms/token?doc_id&post_id&element_id → { ts, token } (fresh signed timestamp; never cached).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Forms;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Registers routes and hooks; handles submissions.
 */
final class Forms {

	public const NS = 'uncoder/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_html' ), 10, 3 );
		add_action( 'admin_post_' . Uploads::ACTION, array( Uploads::class, 'serve' ) );
		add_action( 'admin_post_nopriv_' . Uploads::ACTION, array( Uploads::class, 'serve' ) );
		add_action( 'uncoder_wb_daily', array( Store::class, 'cleanup' ) );
		( new Privacy() )->register();
	}

	public function routes(): void {
		register_rest_route(
			self::NS,
			'/forms/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit' ),
				// Visitors submit forms: authorization is the signed token + spam checks below.
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/forms/token',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'token' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'doc_id'     => array(
						'type'     => 'integer',
						'required' => true,
					),
					'post_id'    => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'element_id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Submit endpoint URL.
	 */
	public static function submit_url(): string {
		return rest_url( self::NS . '/forms/submit' );
	}

	public static function token_url(): string {
		return rest_url( self::NS . '/forms/token' );
	}

	/**
	 * Download link of an uploaded file (for admin screens).
	 */
	public static function file_url( int $submission_id, string $field_id ): string {
		return Uploads::download_url( $submission_id, $field_id );
	}

	/* ------------------------------------------------------------------ Form lookup */

	/**
	 * Finds an element that is not disabled itself nor inside a disabled container.
	 *
	 * @param array<int, array<string,mixed>> $nodes Tree.
	 * @return array<string,mixed>|null
	 */
	private static function find_active( array $nodes, string $id ): ?array {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) || ! empty( $node['disabled'] ) ) {
				continue;
			}
			if ( ( $node['id'] ?? '' ) === $id ) {
				return $node;
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$found = self::find_active( $node['children'], $id );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Finds a published form from request identifiers. The configuration always comes from the
	 * saved document, never from the client.
	 *
	 * @return array<string,mixed>|null doc_id, post_id, element_id, settings, fields, name, page_url
	 */
	public static function locate( int $doc_id, int $post_id, string $element_id ): ?array {
		if ( $doc_id <= 0 || ! Utils::is_valid_id( $element_id ) ) {
			return null;
		}
		$doc  = Plugin::instance()->documents()->get( $doc_id );
		$post = $doc ? $doc->post() : null;
		if ( ! $doc || ! $post || ! $doc->is_builder() ) {
			return null;
		}
		if ( Post_Types::TEMPLATE === $post->post_type ) {
			// Theme builder templates, popups and sections: must be published.
			if ( 'publish' !== $post->post_status ) {
				return null;
			}
		} else {
			// A content document only accepts its own forms, when visitors can see it.
			if ( $doc_id !== $post_id ) {
				return null;
			}
			// Only published pages: drafts and previews must not send mail or webhooks for their author.
			if ( 'publish' !== $post->post_status || post_password_required( $post ) ) {
				return null;
			}
		}

		$node = self::find_active( $doc->elements(), $element_id );
		if ( ! $node || 'form' !== ( $node['type'] ?? '' ) ) {
			return null;
		}
		$widget = Plugin::instance()->elements()->get( 'form' );
		if ( ! $widget ) {
			return null;
		}
		$settings = $widget->effective_settings( is_array( $node['settings'] ?? null ) ? $node['settings'] : array() );

		// Outbound actions carry the site's name and domain: only trust the recipients and webhook
		// configured by someone who may edit others' content. Otherwise mail goes to the site admin only.
		$saver = $doc->rev()['user'];
		if ( ! $saver ) {
			$saver = (int) $post->post_author;
		}
		if ( ! $saver || ! user_can( $saver, 'edit_others_posts' ) ) {
			$settings['email_to']        = (string) get_option( 'admin_email' );
			$settings['webhook_url']     = '';
			$settings['slack_webhook']   = '';
			$settings['discord_webhook'] = '';
			$settings['autoreply']       = '';
			$settings['newsletter']      = '';
		}

		$page_url = '';
		if ( $post_id > 0 ) {
			$page = get_post( $post_id );
			if ( $page && is_post_publicly_viewable( $page ) ) {
				$page_url = (string) get_permalink( $page );
			}
		}
		$name = trim( sanitize_text_field( (string) ( $settings['form_name'] ?? '' ) ) );

		return array(
			'doc_id'     => $doc_id,
			'post_id'    => $post_id,
			'element_id' => $element_id,
			'settings'   => $settings,
			'fields'     => Fields::from_settings( $settings ),
			'name'       => '' !== $name ? $name : __( 'Form', 'uncoder' ),
			'page_url'   => $page_url,
		);
	}

	/* ------------------------------------------------------------------ Token endpoint */

	public function token( WP_REST_Request $request ): WP_REST_Response {
		$doc_id     = absint( $request->get_param( 'doc_id' ) );
		$post_id    = absint( $request->get_param( 'post_id' ) );
		$element_id = strtolower( sanitize_key( (string) $request->get_param( 'element_id' ) ) );
		$form       = self::locate( $doc_id, $post_id, $element_id );
		if ( null === $form ) {
			$response = new WP_REST_Response(
				array(
					'code'    => 'uncoder_form_not_found',
					'message' => __( 'This form is not available.', 'uncoder' ),
				),
				404
			);
		} else {
			$response = new WP_REST_Response( Security::token( $doc_id, $post_id, $element_id ), 200 );
		}
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/* ------------------------------------------------------------------ Submit endpoint */

	public function submit( WP_REST_Request $request ): WP_REST_Response {
		$doc_id     = absint( $request->get_param( 'doc_id' ) );
		$post_id    = absint( $request->get_param( 'post_id' ) );
		$element_id = strtolower( sanitize_key( (string) $request->get_param( 'element_id' ) ) );
		$back       = $this->back_url( $request, $post_id );

		if ( ! Security::origin_allowed( (string) $request->get_header( 'origin' ) ) ) {
			return $this->respond( $request, 403, $this->failure( __( 'This form cannot be submitted from another site.', 'uncoder' ), 'foreign_origin' ), $back, $element_id );
		}

		if ( Security::rate_limited() ) {
			return $this->respond( $request, 429, $this->failure( __( 'Too many submissions. Please wait a minute and try again.', 'uncoder' ), 'rate_limited' ), $back, $element_id );
		}

		$form = self::locate( $doc_id, $post_id, $element_id );
		if ( null === $form ) {
			return $this->respond( $request, 404, $this->failure( __( 'This form is no longer available. Please reload the page.', 'uncoder' ), 'not_found' ), $back, $element_id );
		}
		$settings = $form['settings'];

		if ( ! Security::verify( $doc_id, $post_id, $element_id, $request->get_param( 'ts' ), $request->get_param( 'token' ) ) ) {
			return $this->respond( $request, 400, $this->failure( __( 'This form has expired. Please reload the page and try again.', 'uncoder' ), 'invalid_token' ), $back, $element_id );
		}

		// CAPTCHA (when the form asks for it and keys are set): a failed check is shown, not hidden,
		// because people can fail it too.
		if ( ! empty( $settings['captcha'] ) && ! \Uncoder\Builder\Site\Captcha::verify( (array) $request->get_params() ) ) {
			return $this->respond( $request, 400, $this->failure( __( 'Please complete the security check and try again.', 'uncoder' ), 'captcha' ), $back, $element_id );
		}

		// Spam signals: honeypot (always on) and minimum fill time.
		$spam = '';
		$hp   = $request->get_param( Security::HONEYPOT );
		if ( null !== $hp && '' !== trim( is_scalar( $hp ) ? (string) $hp : 'x' ) ) {
			$spam = 'honeypot';
		}
		$min_time = is_numeric( $settings['min_time'] ?? null ) ? max( 0, min( 60, (int) $settings['min_time'] ) ) : 3;
		if ( '' === $spam && $min_time > 0 && Security::elapsed( (int) $request->get_param( 'ts' ) ) < $min_time ) {
			$spam = 'too_fast';
		}
		/**
		 * Filters the spam verdict ('' = not spam, otherwise a short reason). Hook in to add CAPTCHA
		 * checks or third-party spam services.
		 *
		 * @param string              $spam    Reason or ''.
		 * @param WP_REST_Request     $request Request.
		 * @param array<string,mixed> $form    Form context.
		 */
		$spam = (string) apply_filters( 'uncoder_wb/forms/is_spam', $spam, $request, $form );

		// Validate every field defined in the SAVED settings; unknown client fields are ignored.
		$messages = Fields::messages( $settings );
		$posted   = $request->get_param( 'fields' );
		$posted   = is_array( $posted ) ? $posted : array();
		$files    = (array) $request->get_file_params();
		$data     = array();
		$errors   = array();
		$uploads  = array();

		foreach ( $form['fields'] as $field ) {
			$id = $field['id'];
			if ( 'file' === $field['type'] ) {
				$file  = Uploads::from_request( $files, $id );
				$error = '';
				if ( null === $file ) {
					$error = $field['required'] ? $messages['required'] : '';
				} else {
					$error = Uploads::check( $field, $file, $messages );
					if ( '' === $error ) {
						$uploads[ $id ] = $file;
					}
				}
				if ( '' !== $error ) {
					$errors[ $id ] = $error;
				}
				$data[] = array(
					'id'    => $id,
					'label' => $field['label'],
					'type'  => 'file',
					'value' => null !== $file ? sanitize_file_name( $file['name'] ) : '',
				);
				continue;
			}
			$result = Fields::validate( $field, $posted[ $id ] ?? null, $settings, $messages );
			if ( '' !== $result['error'] ) {
				$errors[ $id ] = $result['error'];
			}
			$data[] = array(
				'id'    => $id,
				'label' => $field['label'],
				'type'  => $field['type'],
				'value' => $result['value'],
			);
		}

		$context = array(
			'doc_id'     => $doc_id,
			'post_id'    => $post_id,
			'element_id' => $element_id,
			'form_name'  => $form['name'],
			'page_url'   => '' !== $form['page_url'] ? $form['page_url'] : $back,
			'fields'     => $form['fields'],
		);
		$meta    = $this->meta( $request, $context );
		$store   = ! array_key_exists( 'store', $settings ) || ! empty( $settings['store'] );

		if ( '' !== $spam ) {
			// Pretend success so bots learn nothing; keep a copy in the spam folder (no files, no actions).
			if ( $store ) {
				$meta['spam'] = mb_substr( $spam, 0, 60 );
				foreach ( $data as &$entry ) {
					if ( 'file' === $entry['type'] ) {
						$entry['value'] = '';
					}
				}
				unset( $entry );
				Store::insert( $doc_id, $element_id, $form['name'], $data, $meta, 'spam' );
			}
			return $this->respond( $request, 200, $this->success( $settings, array() ), $back, $element_id );
		}

		// Conditional fields that are hidden for these answers are neither required nor stored.
		$values = array();
		foreach ( $data as $entry ) {
			$values[ $entry['id'] ] = $entry['value'];
		}
		foreach ( $form['fields'] as $field ) {
			if ( ! empty( $field['show_if'] ) && ! Fields::visible( $field['show_if'], $values ) ) {
				unset( $errors[ $field['id'] ], $uploads[ $field['id'] ] );
				$values[ $field['id'] ] = '';
				foreach ( $data as $i => $entry ) {
					if ( $entry['id'] === $field['id'] ) {
						$data[ $i ]['value'] = '';
					}
				}
			}
		}

		/**
		 * Filters validation errors after the built-in checks.
		 *
		 * @param array<string,string>            $errors  field_id => message.
		 * @param array<int, array<string,mixed>> $data    Sanitized data entries.
		 * @param array<string,mixed>             $form    Form context (settings, fields…).
		 * @param WP_REST_Request                 $request Request.
		 */
		$errors = (array) apply_filters( 'uncoder_wb/forms/validate', $errors, $data, $form, $request );
		$errors = array_map( 'wp_strip_all_tags', array_map( 'strval', $errors ) );

		if ( $errors ) {
			return $this->respond(
				$request,
				422,
				array(
					'success' => false,
					'message' => $messages['fix'],
					'errors'  => $errors,
					'code'    => 'invalid',
				),
				$back,
				$element_id,
				$form
			);
		}

		// Files are moved only once everything else is valid.
		foreach ( $data as &$entry ) {
			if ( 'file' !== $entry['type'] || ! isset( $uploads[ $entry['id'] ] ) ) {
				continue;
			}
			$field  = $this->field( $form['fields'], $entry['id'] );
			$stored = $field ? Uploads::store( $field, $uploads[ $entry['id'] ] ) : null;
			if ( null === $stored ) {
				Uploads::delete_for( $data );
				return $this->respond( $request, 500, $this->failure( $messages['file'], 'upload_failed' ), $back, $element_id );
			}
			$entry['value'] = $stored['name'];
			$entry['file']  = array(
				'path' => $stored['path'],
				'size' => $stored['size'],
				'mime' => $stored['mime'],
			);
		}
		unset( $entry );

		$submission_id = 0;
		if ( $store ) {
			$submission_id = Store::insert( $doc_id, $element_id, $form['name'], $data, $meta, 'unread' );
			if ( ! $submission_id ) {
				return $this->respond( $request, 500, $this->failure( $this->error_message( $settings ), 'storage_failed' ), $back, $element_id );
			}
		}
		$context['submission_id'] = $submission_id;

		$actions = array();
		if ( ! empty( $settings['email'] ) ) {
			$actions['email'] = Actions::email( $settings, $data, $context );
		}
		$webhook = trim( (string) ( $settings['webhook_url'] ?? '' ) );
		if ( '' !== $webhook ) {
			$actions['webhook'] = Actions::webhook( $webhook, $data, $context );
		}
		foreach ( array( 'slack', 'discord' ) as $platform ) {
			$chat = trim( (string) ( $settings[ $platform . '_webhook' ] ?? '' ) );
			if ( '' !== $chat ) {
				$actions[ $platform ] = Integrations::chat( $platform, $chat, $data, $context );
			}
		}
		if ( ! empty( $settings['newsletter'] ) ) {
			$email = Integrations::value( $data, Fields::slug( (string) ( $settings['newsletter_email'] ?? '' ) ), 'email' );
			$optin = Fields::slug( (string) ( $settings['newsletter_consent'] ?? '' ) );
			// A consent checkbox, when chosen, must be ticked.
			if ( '' === $optin || '' !== Integrations::value( $data, $optin ) ) {
				$actions['newsletter'] = Integrations::subscribe(
					(string) ( $settings['newsletter_service'] ?? 'mailchimp' ),
					trim( (string) ( $settings['newsletter_list'] ?? '' ) ),
					$email,
					Integrations::value( $data, Fields::slug( (string) ( $settings['newsletter_name'] ?? '' ) ) ),
					! empty( $settings['newsletter_double'] )
				);
			} else {
				$actions['newsletter'] = array(
					'status' => 'skipped',
					'error'  => 'no consent',
				);
			}
		}
		if ( ! empty( $settings['autoreply'] ) ) {
			$actions['autoreply'] = Actions::autoreply( $settings, $data, $context );
		}
		if ( $submission_id && $actions ) {
			$meta['actions'] = $actions;
			Store::update_meta( $submission_id, $meta );
		}

		// Nothing kept the submission: report the failed delivery instead of losing it silently.
		$delivered = array_filter(
			array( 'email', 'webhook', 'slack', 'discord' ),
			static fn( $k ) => in_array( $actions[ $k ]['status'] ?? '', array( 'sent', 'ok' ), true )
		);
		if ( ! $submission_id && ! $delivered ) {
			Uploads::delete_for( $data );
			return $this->respond( $request, 500, $this->failure( $this->error_message( $settings ), 'not_delivered' ), $back, $element_id );
		}

		$response = $this->success( $settings, $data );
		/**
		 * Filters the success response after a submission was stored and its actions ran.
		 * Also the hook to run custom integrations.
		 *
		 * @param array<string,mixed>             $response      { success, message, redirect?, errors }.
		 * @param array<int, array<string,mixed>> $data          Data entries.
		 * @param array<string,mixed>             $form          Form context.
		 * @param int                             $submission_id Stored row id (0 when storing is off).
		 * @param array<string,mixed>             $actions       Action results (email, webhook).
		 */
		$response = (array) apply_filters( 'uncoder_wb/forms/submitted', $response, $data, $form, $submission_id, $actions );

		return $this->respond( $request, 200, $response, $back, $element_id );
	}

	/**
	 * @param array<int, array<string,mixed>> $fields Normalized fields.
	 * @return array<string,mixed>|null
	 */
	private function field( array $fields, string $id ): ?array {
		foreach ( $fields as $field ) {
			if ( $field['id'] === $id ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 */
	private function error_message( array $settings ): string {
		$msg = trim( sanitize_text_field( (string) ( $settings['error_message'] ?? '' ) ) );
		return '' !== $msg ? $msg : __( 'Something went wrong. Please try again.', 'uncoder' );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function failure( string $message, string $code ): array {
		return array(
			'success' => false,
			'message' => $message,
			'errors'  => new \stdClass(),
			'code'    => $code,
		);
	}

	/**
	 * @param array<string,mixed>             $settings Settings.
	 * @param array<int, array<string,mixed>> $data     Data (unused; kept for symmetry with filters).
	 * @return array<string,mixed>
	 */
	private function success( array $settings, array $data ): array {
		$msg      = trim( sanitize_textarea_field( (string) ( $settings['success_message'] ?? '' ) ) );
		$response = array(
			'success' => true,
			'message' => '' !== $msg ? $msg : __( 'Thanks! Your message has been sent.', 'uncoder' ),
			'errors'  => new \stdClass(),
		);
		$redirect = esc_url_raw( trim( (string) ( $settings['redirect_url'] ?? '' ) ), array( 'http', 'https' ) );
		if ( '' !== $redirect ) {
			$response['redirect'] = $redirect;
		}
		return $response;
	}

	/**
	 * Submission meta: anonymized IP, truncated user agent, page, user.
	 *
	 * @param array<string,mixed> $context Context.
	 * @return array<string,mixed>
	 */
	private function meta( WP_REST_Request $request, array $context ): array {
		$ua = (string) $request->get_header( 'user_agent' );
		return array(
			'ip'         => Utils::anonymize_ip( Utils::client_ip() ),
			'user_agent' => mb_substr( sanitize_text_field( $ua ), 0, 255 ),
			'page_id'    => (int) $context['post_id'],
			'page_url'   => esc_url_raw( (string) $context['page_url'] ),
			'user_id'    => get_current_user_id(),
		);
	}

	/* ------------------------------------------------------------------ Responses */

	/**
	 * JSON for the front-end module / API clients; otherwise (plain HTML form post) a redirect or an HTML page.
	 */
	private function wants_json( WP_REST_Request $request ): bool {
		if ( '' !== (string) $request->get_param( '_unc_js' ) ) {
			return true;
		}
		$accept = strtolower( (string) $request->get_header( 'accept' ) );
		$type   = strtolower( (string) $request->get_header( 'content_type' ) );
		return false !== strpos( $accept, 'application/json' ) || false !== strpos( $type, 'application/json' );
	}

	/**
	 * Same-site page to return to after a no-JS post.
	 */
	private function back_url( WP_REST_Request $request, int $post_id ): string {
		$candidates = array(
			(string) $request->get_param( '_redirect' ),
			(string) $request->get_header( 'referer' ),
		);
		if ( $post_id > 0 && get_post( $post_id ) && is_post_publicly_viewable( $post_id ) ) {
			$candidates[] = (string) get_permalink( $post_id );
		}
		foreach ( $candidates as $url ) {
			$url = trim( $url );
			if ( '' === $url || false !== strpos( $url, '/wp-json/' ) || false !== strpos( $url, 'rest_route=' ) ) {
				continue;
			}
			$url = wp_validate_redirect( esc_url_raw( $url, array( 'http', 'https' ) ), '' );
			if ( '' !== $url ) {
				return strtok( $url, '#' );
			}
		}
		return home_url( '/' );
	}

	/**
	 * @param array<string,mixed>      $data Response data.
	 * @param array<string,mixed>|null $form Form context (for error labels).
	 */
	private function respond( WP_REST_Request $request, int $status, array $data, string $back, string $element_id, ?array $form = null ): WP_REST_Response {
		if ( $this->wants_json( $request ) ) {
			$response = new WP_REST_Response( $data, $status );
			$response->header( 'Cache-Control', 'no-store, max-age=0' );
			return $response;
		}

		// No-JS: success → 303 to the redirect URL or back to the page (the #…-sent target shows the message).
		if ( ! empty( $data['success'] ) ) {
			$location = ! empty( $data['redirect'] ) ? (string) $data['redirect'] : $back . '#uncoder-form-' . $element_id . '-sent';
			$response = new WP_REST_Response( null, 303 );
			$response->header( 'Location', esc_url_raw( $location ) );
			$response->header( 'Cache-Control', 'no-store, max-age=0' );
			return $response;
		}

		$response = new WP_REST_Response( array( '_unc_html' => $this->error_page( $data, $back, $form ) ), $status );
		$response->header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}

	/**
	 * Minimal page listing the problems of a no-JS submission, with a link back to the form.
	 *
	 * @param array<string,mixed>      $data Response data.
	 * @param array<string,mixed>|null $form Form context.
	 */
	private function error_page( array $data, string $back, ?array $form ): string {
		$labels = array();
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			$labels[ $field['id'] ] = '' !== $field['label'] ? $field['label'] : $field['id'];
		}
		$items = '';
		foreach ( (array) ( $data['errors'] ?? array() ) as $id => $msg ) {
			$items .= '<li><strong>' . esc_html( $labels[ $id ] ?? (string) $id ) . ':</strong> ' . esc_html( (string) $msg ) . '</li>';
		}
		$title = __( 'The form could not be sent', 'uncoder' );
		return '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $title ) . '</title>'
			. '<style>body{font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#1f2937;max-width:560px;margin:10vh auto;padding:0 20px}h1{font-size:1.4rem}a{color:#2b59ff}li{margin:6px 0}</style></head><body>'
			. '<h1>' . esc_html( $title ) . '</h1><p role="alert">' . esc_html( (string) ( $data['message'] ?? '' ) ) . '</p>'
			. ( '' !== $items ? '<ul>' . $items . '</ul>' : '' )
			. '<p>' . esc_html__( 'Use your browser\'s back button to correct your answers, or', 'uncoder' ) . ' <a href="' . esc_url( $back ) . '">' . esc_html__( 'return to the page', 'uncoder' ) . '</a>.</p>'
			. '</body></html>';
	}

	/**
	 * Serves the HTML error page built by respond() instead of JSON.
	 *
	 * @param bool                   $served  Already served.
	 * @param \WP_HTTP_Response|null $result  Result.
	 * @param WP_REST_Request|null   $request Request.
	 */
	public function serve_html( $served, $result, $request ) {
		if ( $served || ! $result instanceof \WP_HTTP_Response || ! $request instanceof WP_REST_Request ) {
			return $served;
		}
		if ( 0 !== strpos( (string) $request->get_route(), '/' . self::NS . '/forms/' ) ) {
			return $served;
		}
		$data = $result->get_data();
		if ( 303 === $result->get_status() ) {
			return true; // Redirect: no body.
		}
		if ( ! is_array( $data ) || ! isset( $data['_unc_html'] ) ) {
			return $served;
		}
		echo $data['_unc_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in error_page().
		return true;
	}
}
