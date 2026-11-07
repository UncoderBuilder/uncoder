<?php
/**
 * Page checks (branded reports come with the Pro module: Site\Page_Reports). Uncoder → Page checks runs the page audit (Mcp\Tools\Audit: accessibility,
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
	/** Pages with something to fix after the last check, page id => number of errors (the menu badge). */
	public const ERRORS    = 'uncoder_wb_check_errors';

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
		'long-heading'     => 'Shorten the heading and move the detail into the text below it. If it is a statement rather than a heading, set its HTML tag to P.',
		'placeholder'      => 'Replace the placeholder text with real content.',
		'button-link'      => 'Point the button at a real page, a section of the page, a phone number or an email address.',
		'button-text'      => 'Give the button a short label that says what it does. A button that shows only an icon (a play button, an arrow) needs its "Label for screen readers".',
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
		'wrapper-label'    => 'Put the label on the link or button itself (its Link → Attributes), or give the box a role; screen readers skip a label on a plain box.',
		'sticky-position'  => 'Remove “position” from the box’s custom CSS: Sticky sets it, and the custom value stops the box (and a header pinned from it) from sticking.',
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
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/* ------------------------------------------------------------------ Access */

	public static function can_check(): bool {
		return current_user_can( 'edit_pages' ) && 'full' === Role_Manager::access();
	}

	/** Reports and their branding: the people who manage the licence, once the Pro module brings reports (Agency). */
	public static function can_report(): bool {
		return class_exists( Page_Reports::class ) && White_Label::can_manage();
	}

	public static function allowed(): bool {
		return Licence::allows( self::FEATURE );
	}

	public function routes(): void {
		$check = array( self::class, 'can_check' );
		register_rest_route( Rest::NS, '/checks/pages', array( 'methods' => 'GET', 'callback' => array( $this, 'pages' ), 'permission_callback' => $check ) );
		register_rest_route( Rest::NS, '/checks/run', array( 'methods' => 'POST', 'callback' => array( $this, 'run' ), 'permission_callback' => $check ) );
		register_rest_route( Rest::NS, '/checks/site', array( 'methods' => 'GET', 'callback' => array( $this, 'site' ), 'permission_callback' => $check ) );
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
		$errors  = array();
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
			$errors[ $id ] = count( array_filter( $issues, static fn( $i ) => 'error' === $i['severity'] ) );
		}
		self::remember_errors( $errors );
		return new WP_REST_Response( array( 'results' => $results ) );
	}

	/**
	 * Keeps the error count of each page checked (pages without errors drop out), for the badge on Page Checks.
	 *
	 * @param array<int,int> $errors Page id => errors found now.
	 */
	private static function remember_errors( array $errors ): void {
		if ( ! $errors ) {
			return;
		}
		$known = (array) get_option( self::ERRORS, array() );
		foreach ( $errors as $id => $n ) {
			if ( $n > 0 ) {
				$known[ (int) $id ] = (int) $n;
			} else {
				unset( $known[ (int) $id ] );
			}
		}
		update_option( self::ERRORS, $known, false );
	}

	/** How many pages had something to fix when they were last checked (still published ones only). */
	public static function pages_with_errors(): int {
		$known = (array) get_option( self::ERRORS, array() );
		$n     = 0;
		foreach ( array_keys( $known ) as $id ) {
			if ( 'publish' === get_post_status( (int) $id ) ) {
				++$n;
			}
		}
		return $n;
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
				'fix'    => __( 'Add a site icon (the small image in browser tabs, bookmarks and search results) under Settings → General.', 'uncoder' ),
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
}
