<?php
/**
 * Page checks and branded reports. Uncoder → Page checks runs the page audit (Mcp\Tools\Audit: accessibility,
 * headings, search, content, links, mobile, speed) on every published page built with Uncoder, plus a few checks of
 * the site's setup. With the Agency licence ("reports") the results become a report under the agency's name, logo
 * and colour: a page of its own behind a private link, made for clients and for printing to PDF.
 *
 *   GET    uncoder/v1/checks/pages          pages to check
 *   POST   uncoder/v1/checks/run { ids }    up to 10 pages per call
 *   GET    uncoder/v1/checks/site           the site's setup
 *   GET    uncoder/v1/reports               saved reports
 *   POST   uncoder/v1/reports               { title, client, pages, site, build }  (Agency)
 *   POST   uncoder/v1/reports/{id}/link     a new private link (the old one stops working)
 *   DELETE uncoder/v1/reports/{id}
 *   GET/POST uncoder/v1/reports/brand       name, logo, accent colour, contact, intro  (saving: Agency)
 *   /?uncoder_report={token}                the report (no login; not indexed)
 *
 * Reports are private posts (uncoder_report) holding a snapshot of the results and of the branding, so a report
 * stays as it was sent. Their links keep working when the licence ends; making new ones needs it.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Licence\Licence;
use Uncoder\Builder\Mcp\Tools\Audit;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Page_Checks {

	public const POST_TYPE = 'uncoder_report';
	public const BRAND     = 'uncoder_wb_report_brand';
	private const TOKEN    = '_uncoder_wb_report_token';
	private const FEATURE  = 'reports';
	private const MAX_RUN  = 10;

	/** How each kind of issue is explained to the site's owner (the audit's own fixes are written for AI clients). */
	public const PLAIN = array(
		'no-h1'            => 'Give the page one main title: select it and set its HTML tag to H1.',
		'multiple-h1'      => 'Keep a single H1 per page; set the other big titles to H2.',
		'heading-skip'     => 'Use the next heading level (H2 after H1, H3 after H2). To change how a heading looks, use a text style instead of a different level.',
		'many-fonts'       => 'Use the Design System’s heading and body fonts; two or three families keep pages fast and consistent.',
		'meta-description' => 'Add a meta description of 140–160 characters (in your SEO plugin, or ask your AI assistant to write one). Search engines show it under the page title.',
		'deep-nesting'     => 'Simplify the layout: fewer boxes inside boxes make the page lighter and easier to edit.',
		'empty-container'  => 'Remove the empty box, or put content in it.',
		'empty-heading'    => 'Write the heading, or remove it.',
		'long-heading'     => 'Shorten the heading and move the detail into the text below it.',
		'placeholder'      => 'Replace the placeholder text with real content.',
		'button-link'      => 'Point the button at a real page, a section of the page, a phone number or an email address.',
		'button-text'      => 'Give the button a short label that says what it does.',
		'image-missing'    => 'Choose an image, or remove the empty image block.',
		'image-alt'        => 'Describe the image in its alternative text, so screen readers and search engines know what it shows.',
		'image-hotlink'    => 'Upload the image to this site’s media library instead of loading it from another website.',
		'image-width'      => 'Let the image shrink on phones: set its width to 100% on mobile.',
		'row-mobile'       => 'On phones, stack the columns: switch to Mobile and set the row’s direction to column.',
		'grid-mobile'      => 'On phones, show the grid as one column: switch to Mobile and set its columns to 1.',
		'contrast'         => 'Make the text darker or the background lighter (or the other way round) until the text is easy to read.',
		'link-name'        => 'Give the link a text label (or alternative text on its image) that says where it goes.',
		'link-text'        => 'Use link text that says where it goes, like “View our services” instead of “Click here”.',
		'button-name'      => 'Give the button a text label.',
		'form-label'       => 'Show a label for the form field; a placeholder alone disappears while typing.',
		'frame-title'      => 'Give the embedded map or video a title.',
		'duplicate-id'     => 'Give each element its own CSS ID.',
		'tabindex'         => 'Remove the custom tab order so keyboard users move through the page in reading order.',
		'autoplay-sound'   => 'Mute videos that play by themselves, or let visitors start them.',
	);

	/** Report sections: the audit's categories, grouped for people. */
	public const GROUPS = array(
		'accessibility' => 'Accessibility',
		'headings'      => 'Accessibility',
		'seo'           => 'Search',
		'content'       => 'Content',
		'links'         => 'Content',
		'responsive'    => 'Mobile',
		'performance'   => 'Speed',
		'structure'     => 'Build quality',
	);

	public function register(): void {
		if ( ! Licence::enabled() ) {
			return;
		}
		add_action( 'init', array( $this, 'post_type' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'template_redirect', array( $this, 'serve' ), 1 );
	}

	public function post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'label'               => __( 'Page-check reports', 'uncoder' ),
			)
		);
	}

	/* ------------------------------------------------------------------ Access */

	public static function can_check(): bool {
		return current_user_can( 'edit_pages' ) && 'full' === Role_Manager::access();
	}

	/** Reports and their branding: the people who manage the licence. */
	public static function can_report(): bool {
		return White_Label::can_manage();
	}

	public static function allowed(): bool {
		return Licence::allows( self::FEATURE );
	}

	public function routes(): void {
		$check  = array( self::class, 'can_check' );
		$report = array( self::class, 'can_report' );
		register_rest_route( Rest::NS, '/checks/pages', array( 'methods' => 'GET', 'callback' => array( $this, 'pages' ), 'permission_callback' => $check ) );
		register_rest_route( Rest::NS, '/checks/run', array( 'methods' => 'POST', 'callback' => array( $this, 'run' ), 'permission_callback' => $check ) );
		register_rest_route( Rest::NS, '/checks/site', array( 'methods' => 'GET', 'callback' => array( $this, 'site' ), 'permission_callback' => $check ) );
		register_rest_route(
			Rest::NS,
			'/reports',
			array(
				array( 'methods' => 'GET', 'callback' => array( $this, 'reports' ), 'permission_callback' => $report ),
				array( 'methods' => 'POST', 'callback' => array( $this, 'create' ), 'permission_callback' => $report ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/reports/brand',
			array(
				array( 'methods' => 'GET', 'callback' => array( $this, 'get_brand' ), 'permission_callback' => $report ),
				array( 'methods' => 'POST', 'callback' => array( $this, 'save_brand' ), 'permission_callback' => $report ),
			)
		);
		register_rest_route( Rest::NS, '/reports/(?P<id>\d+)/link', array( 'methods' => 'POST', 'callback' => array( $this, 'relink' ), 'permission_callback' => $report ) );
		register_rest_route( Rest::NS, '/reports/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete' ), 'permission_callback' => $report ) );
	}

	/* ------------------------------------------------------------------ Checks */

	public function pages(): WP_REST_Response {
		$types = array_values( array_diff( Plugin::instance()->documents()->post_types(), array( \Uncoder\Builder\Core\Post_Types::TEMPLATE ) ) );
		$posts = $types ? get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'meta_key'       => Utils::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		) : array();
		$out = array();
		foreach ( $posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$out[] = self::describe( $post );
			}
		}
		return new WP_REST_Response( array( 'pages' => $out, 'front' => (int) get_option( 'page_on_front' ) ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public function run( WP_REST_Request $request ) {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $request->get_param( 'ids' ) ) ) ) ), 0, self::MAX_RUN );
		if ( ! $ids ) {
			return new WP_Error( 'uncoder_invalid', __( 'Choose the pages to check.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$audit   = new Audit();
		$results = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$res = $audit->run( array( 'id' => $id ) );
			if ( is_wp_error( $res ) ) {
				continue;
			}
			// The same problem in several places (one text style used six times) is one point to fix, with its places.
			$issues = array();
			foreach ( (array) $res['issues'] as $issue ) {
				$rule = (string) ( $issue['rule'] ?? '' );
				$key  = $issue['severity'] . '|' . $issue['category'] . '|' . $rule . '|' . $issue['message'];
				if ( ! isset( $issues[ $key ] ) ) {
					$issues[ $key ] = array(
						'severity' => (string) $issue['severity'],
						'category' => (string) $issue['category'],
						'group'    => self::GROUPS[ $issue['category'] ] ?? 'Content',
						'rule'     => $rule,
						'message'  => (string) $issue['message'],
						'fix'      => self::PLAIN[ $rule ] ?? '',
						'count'    => 0,
						'elements' => array(),
					);
				}
				++$issues[ $key ]['count'];
				if ( ! empty( $issue['element_id'] ) ) {
					$issues[ $key ]['elements'][] = (string) $issue['element_id'];
				}
			}
			$issues    = array_values( $issues );
			$results[] = array_merge(
				self::describe( $post ),
				array(
					'score'   => self::score( $issues ),
					'issues'  => $issues,
				)
			);
		}
		return new WP_REST_Response( array( 'results' => $results ) );
	}

	/**
	 * 100, less 12 per thing to fix, 4 per thing to improve and 1 per note (each counted once, wherever it repeats).
	 *
	 * @param array<int,array<string,mixed>> $issues Grouped issues.
	 */
	public static function score( array $issues ): int {
		$counts = array_count_values( array_column( $issues, 'severity' ) );
		return max( 0, 100 - 12 * ( $counts['error'] ?? 0 ) - 4 * ( $counts['warning'] ?? 0 ) - ( $counts['info'] ?? 0 ) );
	}

	/** The site's setup: the things a visitor or a search engine notices before any page. */
	public function site(): WP_REST_Response {
		$home   = home_url( '/' );
		$checks = array(
			array(
				'id'     => 'https',
				'status' => 0 === strpos( $home, 'https://' ) ? 'pass' : 'fail',
				'title'  => __( 'Secure connection (HTTPS)', 'uncoder' ),
				'fix'    => __( 'Serve the site over HTTPS: browsers mark plain HTTP sites as “Not secure”. Your host can turn on a free certificate.', 'uncoder' ),
			),
			array(
				'id'     => 'indexing',
				'status' => (int) get_option( 'blog_public' ) ? 'pass' : 'fail',
				'title'  => __( 'Visible to search engines', 'uncoder' ),
				'fix'    => __( 'Search engines are asked not to index this site. Untick “Discourage search engines” under Settings → Reading when the site is ready.', 'uncoder' ),
			),
			array(
				'id'     => 'icon',
				'status' => has_site_icon() ? 'pass' : 'warn',
				'title'  => __( 'Site icon', 'uncoder' ),
				'fix'    => __( 'Add a site icon (the small image in browser tabs and bookmarks) in the Design System or under Appearance → Customize.', 'uncoder' ),
			),
			array(
				'id'     => 'permalinks',
				'status' => '' !== (string) get_option( 'permalink_structure' ) ? 'pass' : 'warn',
				'title'  => __( 'Readable page addresses', 'uncoder' ),
				'fix'    => __( 'Choose “Post name” under Settings → Permalinks, so addresses read like /about/ instead of /?p=12.', 'uncoder' ),
			),
			array(
				'id'     => 'privacy',
				'status' => (int) get_option( 'wp_page_for_privacy_policy' ) && 'publish' === get_post_status( (int) get_option( 'wp_page_for_privacy_policy' ) ) ? 'pass' : 'warn',
				'title'  => __( 'Privacy policy page', 'uncoder' ),
				'fix'    => __( 'Publish a privacy policy and choose it under Settings → Privacy. Most countries require one when a site has forms or analytics.', 'uncoder' ),
			),
			array(
				'id'     => 'tagline',
				'status' => in_array( trim( (string) get_bloginfo( 'description' ) ), array( '', 'Just another WordPress site' ), true ) ? 'warn' : 'pass',
				'title'  => __( 'Site tagline', 'uncoder' ),
				'fix'    => __( 'Write a short tagline under Settings → General; some themes and search results show it.', 'uncoder' ),
			),
		);
		return new WP_REST_Response( array( 'checks' => $checks ) );
	}

	/** @return array<string,mixed> */
	private static function describe( \WP_Post $post ): array {
		$type = get_post_type_object( $post->post_type );
		return array(
			'id'    => $post->ID,
			'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'type'  => $type ? (string) $type->labels->singular_name : $post->post_type,
			'url'   => (string) get_permalink( $post ),
			'edit'  => admin_url( 'post.php?action=uncoder&post=' . $post->ID ),
		);
	}

	/* ------------------------------------------------------------------ Reports */

	public function reports(): WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$data  = self::data( $post );
			$out[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'created' => get_post_time( 'c', true, $post ),
				'score'   => (int) ( $data['score'] ?? 0 ),
				'pages'   => count( (array) ( $data['pages'] ?? array() ) ),
				'issues'  => (int) ( $data['totals']['issues'] ?? 0 ),
				'url'     => self::url( $post->ID ),
			);
		}
		return new WP_REST_Response( array( 'reports' => $out, 'allowed' => self::allowed(), 'pricing' => Licence::PRICING ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public function create( WP_REST_Request $request ) {
		if ( ! self::allowed() ) {
			return self::locked();
		}
		$build = ! empty( $request->get_param( 'build' ) );
		$pages = array();
		foreach ( array_slice( (array) $request->get_param( 'pages' ), 0, 500 ) as $page ) {
			$clean = self::clean_page( is_array( $page ) ? $page : array(), $build );
			if ( $clean ) {
				$pages[] = $clean;
			}
		}
		if ( ! $pages ) {
			return new WP_Error( 'uncoder_invalid', __( 'Run the checks first.', 'uncoder' ), array( 'status' => 400 ) );
		}
		$site = array();
		foreach ( array_slice( (array) $request->get_param( 'site' ), 0, 20 ) as $c ) {
			if ( is_array( $c ) && in_array( $c['status'] ?? '', array( 'pass', 'warn', 'fail' ), true ) ) {
				$site[] = array(
					'status' => (string) $c['status'],
					'title'  => sanitize_text_field( (string) ( $c['title'] ?? '' ) ),
					'fix'    => sanitize_text_field( (string) ( $c['fix'] ?? '' ) ),
				);
			}
		}
		$issues = array_merge( ...array_map( static fn( $p ) => $p['issues'], $pages ) );
		$counts = array_count_values( array_column( $issues, 'severity' ) );
		$groups = array_count_values( array_column( $issues, 'group' ) );
		$data   = array(
			'version' => 1,
			'created' => gmdate( 'c' ),
			'client'  => mb_substr( sanitize_text_field( (string) ( $request->get_param( 'client' ) ?: get_bloginfo( 'name' ) ) ), 0, 120 ),
			'site'    => array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
			'brand'   => self::brand(),
			'score'   => (int) round( array_sum( array_column( $pages, 'score' ) ) / count( $pages ) ),
			'totals'  => array(
				'issues'   => count( $issues ),
				'errors'   => $counts['error'] ?? 0,
				'warnings' => $counts['warning'] ?? 0,
				'info'     => $counts['info'] ?? 0,
				'groups'   => $groups,
			),
			'setup'   => $site,
			'pages'   => $pages,
		);
		$title = trim( sanitize_text_field( (string) $request->get_param( 'title' ) ) );
		$id    = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				'post_title'   => '' !== $title ? mb_substr( $title, 0, 160 ) : sprintf( /* translators: %s: date. */ __( 'Website check · %s', 'uncoder' ), wp_date( get_option( 'date_format' ) ) ),
				'post_content' => wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ),
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, self::TOKEN, self::token() );
		return new WP_REST_Response( array( 'id' => $id, 'url' => self::url( $id ) ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public function relink( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( self::POST_TYPE !== get_post_type( $id ) ) {
			return new WP_Error( 'uncoder_not_found', __( 'This report no longer exists.', 'uncoder' ), array( 'status' => 404 ) );
		}
		update_post_meta( $id, self::TOKEN, self::token() );
		return new WP_REST_Response( array( 'url' => self::url( $id ) ) );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request['id'];
		if ( self::POST_TYPE === get_post_type( $id ) ) {
			wp_delete_post( $id, true );
		}
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * One page of results as the client sent it, cleaned (reports are shown to people without a login).
	 *
	 * @param array<string,mixed> $page Page.
	 * @return array<string,mixed>|null
	 */
	private static function clean_page( array $page, bool $build ): ?array {
		$id = (int) ( $page['id'] ?? 0 );
		if ( ! $id || ! get_post( $id ) ) {
			return null;
		}
		$issues = array();
		foreach ( array_slice( (array) ( $page['issues'] ?? array() ), 0, 150 ) as $i ) {
			if ( ! is_array( $i ) || ! in_array( $i['severity'] ?? '', array( 'error', 'warning', 'info' ), true ) ) {
				continue;
			}
			$category = sanitize_key( (string) ( $i['category'] ?? '' ) );
			if ( 'structure' === $category && ! $build ) {
				continue; // Build-quality notes are for the builders, unless the report asks for them.
			}
			$rule     = sanitize_key( (string) ( $i['rule'] ?? '' ) );
			$issues[] = array(
				'severity' => (string) $i['severity'],
				'group'    => self::GROUPS[ $category ] ?? 'Content',
				'message'  => mb_substr( sanitize_text_field( (string) ( $i['message'] ?? '' ) ), 0, 300 ),
				'fix'      => self::PLAIN[ $rule ] ?? '',
				'count'    => max( 1, min( 999, (int) ( $i['count'] ?? 1 ) ) ),
			);
		}
		return array(
			'id'     => $id,
			'title'  => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
			'url'    => (string) get_permalink( $id ),
			// Scored again: build notes left out of the report do not count.
			'score'  => self::score( $issues ),
			'issues' => $issues,
		);
	}

	/* ------------------------------------------------------------------ Branding */

	/**
	 * The report's branding: its own settings, else the white-label brand, else the site.
	 *
	 * @return array<string,string>
	 */
	public static function brand(): array {
		$s    = get_option( self::BRAND, array() );
		$s    = is_array( $s ) ? $s : array();
		$wl   = White_Label::brand();
		$logo = ! empty( $s['logo'] ) ? (string) wp_get_attachment_image_url( (int) $s['logo'], 'full' ) : (string) ( $wl['logo'] ?: $wl['icon'] );
		return array(
			'name'    => '' !== trim( (string) ( $s['name'] ?? '' ) ) ? (string) $s['name'] : ( $wl['white'] ? (string) $wl['name'] : '' ),
			'logo'    => $logo,
			'accent'  => (string) ( sanitize_hex_color( (string) ( $s['accent'] ?? '' ) ) ?: '#083241' ),
			'contact' => (string) ( $s['contact'] ?? '' ),
			'intro'   => (string) ( $s['intro'] ?? '' ),
		);
	}

	public function get_brand(): WP_REST_Response {
		$s = get_option( self::BRAND, array() );
		$s = is_array( $s ) ? $s : array();
		return new WP_REST_Response(
			array(
				'name'    => (string) ( $s['name'] ?? '' ),
				'logo'    => array( 'id' => (int) ( $s['logo'] ?? 0 ), 'url' => ! empty( $s['logo'] ) ? (string) wp_get_attachment_image_url( (int) $s['logo'], 'medium' ) : '' ),
				'accent'  => (string) ( $s['accent'] ?? '#083241' ),
				'contact' => (string) ( $s['contact'] ?? '' ),
				'intro'   => (string) ( $s['intro'] ?? '' ),
				'fallback' => self::brand(),
				'allowed' => self::allowed(),
			)
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public function save_brand( WP_REST_Request $request ) {
		if ( ! self::allowed() ) {
			return self::locked();
		}
		$p    = (array) $request->get_json_params();
		$logo = (int) ( $p['logo'] ?? 0 );
		update_option(
			self::BRAND,
			array(
				'name'    => mb_substr( sanitize_text_field( (string) ( $p['name'] ?? '' ) ), 0, 80 ),
				'logo'    => $logo > 0 && wp_attachment_is_image( $logo ) ? $logo : 0,
				'accent'  => (string) ( sanitize_hex_color( (string) ( $p['accent'] ?? '' ) ) ?: '#083241' ),
				'contact' => mb_substr( sanitize_text_field( (string) ( $p['contact'] ?? '' ) ), 0, 160 ),
				'intro'   => mb_substr( sanitize_textarea_field( (string) ( $p['intro'] ?? '' ) ), 0, 1200 ),
			),
			false
		);
		return $this->get_brand();
	}

	/* ------------------------------------------------------------------ The report page */

	public static function url( int $id ): string {
		$token = (string) get_post_meta( $id, self::TOKEN, true );
		return '' === $token ? '' : add_query_arg( 'uncoder_report', $token, home_url( '/' ) );
	}

	private static function token(): string {
		return strtolower( wp_generate_password( 32, false ) );
	}

	/** @return array<string,mixed> */
	private static function data( \WP_Post $post ): array {
		$data = json_decode( (string) $post->post_content, true );
		return is_array( $data ) ? $data : array();
	}

	/** Prints the report for a valid link (template_redirect). */
	public function serve(): void {
		$token = isset( $_GET['uncoder_report'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( sanitize_text_field( wp_unslash( $_GET['uncoder_report'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a shared link, the token is the key.
		if ( '' === $token ) {
			return;
		}
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'meta_key'       => self::TOKEN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Referrer-Policy: no-referrer', true );
		if ( ! $posts || 32 !== strlen( $token ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'This report link does not exist or was replaced by a new one.', 'uncoder' ), esc_html__( 'Report not found', 'uncoder' ), array( 'response' => 404 ) );
		}
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo self::html( $posts[0]->post_title, self::data( $posts[0] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		exit;
	}

	/**
	 * The report as a page of its own: no theme, no scripts but the print button, printable to PDF.
	 *
	 * @param array<string,mixed> $d Report data.
	 */
	public static function html( string $title, array $d ): string {
		$brand  = (array) ( $d['brand'] ?? array() );
		$accent = sanitize_hex_color( (string) ( $brand['accent'] ?? '' ) ) ?: '#083241';
		$score  = (int) ( $d['score'] ?? 0 );
		$tone   = $score >= 90 ? 'good' : ( $score >= 70 ? 'fair' : 'poor' );
		$t      = (array) ( $d['totals'] ?? array() );
		$pages  = (array) ( $d['pages'] ?? array() );
		$e      = static fn( $s ): string => esc_html( (string) $s );
		$msg    = static fn( $s ): string => (string) preg_replace( '/#([0-9a-f]{6})\b/i', '<span class="sw" style="background:#$1"></span>#$1', esc_html( (string) $s ) );
		$date   = wp_date( (string) get_option( 'date_format' ), (int) strtotime( (string) ( $d['created'] ?? 'now' ) ) );
		$by     = (string) ( $brand['name'] ?? '' );

		ob_start();
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo $e( $title ); ?></title>
<style>
:root{--accent:<?php echo esc_html( $accent ); ?>;--ink:#14171a;--muted:#5d6670;--line:#e3e6e9;--soft:#f5f6f7;--good:#1d7a4a;--fair:#a8670c;--poor:#b3261e}
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--soft);color:var(--ink);font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}
.wrap{max-width:880px;margin:0 auto;padding:32px 20px 64px}
.sheet{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
.band{background:var(--accent);color:#fff;padding:28px 32px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
.band img{max-height:44px;max-width:220px;display:block;background:#fff;border-radius:8px;padding:6px 10px}
.band .by{font-weight:600;font-size:17px}
.band .when{opacity:.85;font-size:13px;text-align:right}
.head{padding:28px 32px 8px}
h1{font-size:28px;line-height:1.2;margin:0 0 4px}
.for{color:var(--muted);margin:0}
.intro{margin:16px 0 0;white-space:pre-line}
.summary{display:grid;grid-template-columns:auto 1fr;gap:28px;align-items:center;padding:24px 32px;border-bottom:1px solid var(--line)}
.ring{--v:<?php echo (int) $score; ?>;width:120px;height:120px;border-radius:50%;display:grid;place-items:center;background:conic-gradient(var(--c) calc(var(--v)*1%),#e9ecef 0)}
.ring.good{--c:var(--good)}.ring.fair{--c:var(--fair)}.ring.poor{--c:var(--poor)}
.ring span{width:92px;height:92px;border-radius:50%;background:#fff;display:grid;place-items:center;font-size:30px;font-weight:700}
.stats{display:flex;flex-wrap:wrap;gap:10px}
.stat{background:var(--soft);border-radius:10px;padding:10px 14px;min-width:110px}
.stat b{display:block;font-size:20px}
.stat span{color:var(--muted);font-size:13px}
section{padding:24px 32px;border-bottom:1px solid var(--line)}
section:last-of-type{border-bottom:0}
h2{font-size:18px;margin:0 0 12px}
h3{font-size:16px;margin:0}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{text-align:left;padding:9px 8px;border-bottom:1px solid var(--line);vertical-align:top}
th{color:var(--muted);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
td.num,th.num{text-align:right;white-space:nowrap}
a{color:var(--accent)}
.pill{display:inline-block;border-radius:99px;padding:1px 9px;font-size:12px;font-weight:600;white-space:nowrap}
.pill.error,.pill.fail{background:#fde8e6;color:var(--poor)}.pill.warning,.pill.warn{background:#fdf1dc;color:var(--fair)}.pill.info{background:#e8eef6;color:#2f5480}.pill.pass{background:#e3f4ea;color:var(--good)}
.setup li{display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--line)}
.setup li:last-child{border-bottom:0}
ul{list-style:none;margin:0;padding:0}
.page{padding:18px 0;border-bottom:1px solid var(--line);break-inside:avoid-page}
.page:last-child{border-bottom:0}
.page-head{display:flex;justify-content:space-between;gap:12px;align-items:baseline;margin-bottom:8px}
.page-head .score{font-weight:700}
.issue{display:grid;grid-template-columns:84px 1fr;gap:10px;padding:8px 0;break-inside:avoid}
.issue p{margin:0}
.issue .fix{color:var(--muted);font-size:14px;margin-top:2px}
.group{color:var(--muted);font-size:12px}
.ok{color:var(--good)}
.sw{display:inline-block;width:.85em;height:.85em;border-radius:3px;border:1px solid rgba(0,0,0,.15);vertical-align:-.1em;margin-right:3px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.common li{display:grid;grid-template-columns:84px 1fr auto;gap:10px;padding:9px 0;border-bottom:1px solid var(--line);break-inside:avoid}
.common li:last-child{border-bottom:0}
.common p{margin:0}.common .fix{color:var(--muted);font-size:14px;margin-top:2px}.common .n{color:var(--muted);font-size:13px;white-space:nowrap}
.foot{padding:20px 32px;color:var(--muted);font-size:13px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.print{position:fixed;right:20px;bottom:20px;background:var(--accent);color:#fff;border:0;border-radius:99px;padding:12px 18px;font:600 14px system-ui,sans-serif;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.18)}
@media (max-width:600px){.summary{grid-template-columns:1fr;justify-items:center}.band,.head,section,.summary,.foot{padding-left:18px;padding-right:18px}.issue{grid-template-columns:1fr}}
@media print{body{background:#fff}.wrap{padding:0;max-width:none}.sheet{border:0;border-radius:0}.print{display:none}.band,.ring,.pill{-webkit-print-color-adjust:exact;print-color-adjust:exact}@page{margin:14mm}}
</style>
</head>
<body>
<div class="wrap"><div class="sheet">
	<div class="band">
		<div><?php echo ! empty( $brand['logo'] ) ? '<img src="' . esc_url( (string) $brand['logo'] ) . '" alt="' . esc_attr( $by ) . '">' : ( '' !== $by ? '<div class="by">' . $e( $by ) . '</div>' : '' ); ?></div>
		<div class="when"><?php echo $e( $date ); ?></div>
	</div>
	<div class="head">
		<h1><?php echo $e( $title ); ?></h1>
		<p class="for">
			<?php
			/* translators: 1: client or site name, 2: site address. */
			echo $e( sprintf( __( 'Prepared for %1$s · %2$s', 'uncoder' ), (string) ( $d['client'] ?? '' ), preg_replace( '#^https?://#', '', untrailingslashit( (string) ( $d['site']['url'] ?? '' ) ) ) ) );
			?>
		</p>
		<?php if ( ! empty( $brand['intro'] ) ) : ?>
			<p class="intro"><?php echo $e( $brand['intro'] ); ?></p>
		<?php endif; ?>
	</div>
	<div class="summary">
		<div class="ring <?php echo esc_attr( $tone ); ?>" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: score. */ __( 'Score %d out of 100', 'uncoder' ), $score ) ); ?>"><span><?php echo (int) $score; ?></span></div>
		<div>
			<p style="margin:0 0 12px"><?php echo $e( 'good' === $tone ? __( 'The site is in good shape. The points below are small improvements.', 'uncoder' ) : ( 'fair' === $tone ? __( 'The site is in fair shape. Fixing the points below makes it easier to use and to find.', 'uncoder' ) : __( 'The site needs attention. Start with the issues marked “Fix”.', 'uncoder' ) ) ); ?></p>
			<div class="stats">
				<div class="stat"><b><?php echo count( $pages ); ?></b><span><?php echo $e( _n( 'page checked', 'pages checked', count( $pages ), 'uncoder' ) ); ?></span></div>
				<div class="stat"><b><?php echo (int) ( $t['errors'] ?? 0 ); ?></b><span><?php esc_html_e( 'to fix', 'uncoder' ); ?></span></div>
				<div class="stat"><b><?php echo (int) ( $t['warnings'] ?? 0 ); ?></b><span><?php esc_html_e( 'to improve', 'uncoder' ); ?></span></div>
				<div class="stat"><b><?php echo (int) ( $t['info'] ?? 0 ); ?></b><span><?php esc_html_e( 'notes', 'uncoder' ); ?></span></div>
			</div>
		</div>
	</div>
	<?php if ( ! empty( $d['setup'] ) ) : ?>
	<section>
		<h2><?php esc_html_e( 'Site setup', 'uncoder' ); ?></h2>
		<ul class="setup">
			<?php foreach ( (array) $d['setup'] as $c ) : ?>
				<li>
					<span class="pill <?php echo esc_attr( (string) $c['status'] ); ?>"><?php echo $e( 'pass' === $c['status'] ? __( 'OK', 'uncoder' ) : ( 'fail' === $c['status'] ? __( 'Fix', 'uncoder' ) : __( 'Improve', 'uncoder' ) ) ); ?></span>
					<div><strong><?php echo $e( $c['title'] ); ?></strong><?php echo 'pass' !== $c['status'] ? '<div class="group">' . $e( $c['fix'] ) . '</div>' : ''; ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php endif; ?>
	<?php
	$common = array();
	foreach ( $pages as $p ) {
		foreach ( (array) $p['issues'] as $i ) {
			$key = $i['severity'] . '|' . $i['message'];
			if ( ! isset( $common[ $key ] ) ) {
				$common[ $key ] = array( 'issue' => $i, 'pages' => 0 );
			}
			++$common[ $key ]['pages'];
		}
	}
	$common = array_filter( $common, static fn( $c ) => $c['pages'] > 1 );
	uasort( $common, static fn( $a, $b ) => $b['pages'] <=> $a['pages'] );
	$common = array_slice( $common, 0, 8 );
	if ( $common ) :
		?>
	<section>
		<h2><?php esc_html_e( 'Fix once, improve many pages', 'uncoder' ); ?></h2>
		<p class="group" style="margin:-6px 0 10px"><?php esc_html_e( 'These points repeat across pages, usually because they come from one shared style or template: fixing them there fixes every page at once.', 'uncoder' ); ?></p>
		<ul class="common">
			<?php foreach ( $common as $c ) : ?>
				<li>
					<div><span class="pill <?php echo esc_attr( (string) $c['issue']['severity'] ); ?>"><?php echo $e( 'error' === $c['issue']['severity'] ? __( 'Fix', 'uncoder' ) : ( 'warning' === $c['issue']['severity'] ? __( 'Improve', 'uncoder' ) : __( 'Note', 'uncoder' ) ) ); ?></span></div>
					<div><p><?php echo $msg( $c['issue']['message'] ); ?></p><?php echo '' !== (string) $c['issue']['fix'] ? '<p class="fix">' . $e( $c['issue']['fix'] ) . '</p>' : ''; ?></div>
					<span class="n"><?php echo $e( sprintf( /* translators: %d: number of pages. */ _n( '%d page', '%d pages', (int) $c['pages'], 'uncoder' ), (int) $c['pages'] ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php endif; ?>
	<?php if ( ! empty( $t['groups'] ) ) : ?>
	<section>
		<h2><?php esc_html_e( 'By topic', 'uncoder' ); ?></h2>
		<table><thead><tr><th><?php esc_html_e( 'Topic', 'uncoder' ); ?></th><th class="num"><?php esc_html_e( 'Points', 'uncoder' ); ?></th></tr></thead><tbody>
			<?php
			$groups = (array) $t['groups'];
			arsort( $groups );
			foreach ( $groups as $group => $n ) {
				echo '<tr><td>' . $e( $group ) . '</td><td class="num">' . (int) $n . '</td></tr>';
			}
			?>
		</tbody></table>
	</section>
	<?php endif; ?>
	<section>
		<h2><?php esc_html_e( 'Pages', 'uncoder' ); ?></h2>
		<table><thead><tr><th><?php esc_html_e( 'Page', 'uncoder' ); ?></th><th class="num"><?php esc_html_e( 'Score', 'uncoder' ); ?></th><th class="num"><?php esc_html_e( 'Points', 'uncoder' ); ?></th></tr></thead><tbody>
			<?php
			usort( $pages, static fn( $a, $b ) => (int) $a['score'] <=> (int) $b['score'] );
			foreach ( $pages as $p ) {
				echo '<tr><td><a href="#p' . (int) $p['id'] . '">' . $e( $p['title'] ) . '</a></td><td class="num">' . (int) $p['score'] . '</td><td class="num">' . count( (array) $p['issues'] ) . '</td></tr>';
			}
			?>
		</tbody></table>
	</section>
	<section>
		<h2><?php esc_html_e( 'Details', 'uncoder' ); ?></h2>
		<?php foreach ( $pages as $p ) : ?>
			<div class="page" id="p<?php echo (int) $p['id']; ?>">
				<div class="page-head"><h3><a href="<?php echo esc_url( (string) $p['url'] ); ?>"><?php echo $e( $p['title'] ); ?></a></h3><span class="score"><?php echo (int) $p['score']; ?>/100</span></div>
				<?php if ( empty( $p['issues'] ) ) : ?>
					<p class="ok"><?php esc_html_e( 'No issues found.', 'uncoder' ); ?></p>
				<?php else : ?>
					<?php
					$order = array( 'error' => 0, 'warning' => 1, 'info' => 2 );
					$list  = (array) $p['issues'];
					usort( $list, static fn( $a, $b ) => ( $order[ $a['severity'] ] ?? 3 ) <=> ( $order[ $b['severity'] ] ?? 3 ) );
					foreach ( $list as $i ) :
						?>
						<div class="issue">
							<div><span class="pill <?php echo esc_attr( (string) $i['severity'] ); ?>"><?php echo $e( 'error' === $i['severity'] ? __( 'Fix', 'uncoder' ) : ( 'warning' === $i['severity'] ? __( 'Improve', 'uncoder' ) : __( 'Note', 'uncoder' ) ) ); ?></span></div>
							<div>
								<p><?php echo $msg( $i['message'] ); ?> <span class="group">· <?php echo $e( $i['group'] ); ?><?php echo (int) ( $i['count'] ?? 1 ) > 1 ? ' · ' . $e( sprintf( /* translators: %d: number of places on the page. */ _n( '%d place', '%d places', (int) $i['count'], 'uncoder' ), (int) $i['count'] ) ) : ''; ?></span></p>
								<?php if ( '' !== (string) $i['fix'] ) : ?>
									<p class="fix"><?php echo $e( $i['fix'] ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</section>
	<div class="foot">
		<span><?php echo $e( '' !== $by ? sprintf( /* translators: %s: agency name. */ __( 'Report by %s', 'uncoder' ), $by ) : __( 'Website check', 'uncoder' ) ); ?></span>
		<span><?php echo $e( (string) ( $brand['contact'] ?? '' ) ); ?></span>
	</div>
</div></div>
<button class="print" type="button" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'uncoder' ); ?></button>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	private static function locked(): WP_Error {
		return new WP_Error( 'uncoder_needs_agency', __( 'Branded reports come with the Agency licence.', 'uncoder' ), array( 'status' => 403 ) );
	}
}
