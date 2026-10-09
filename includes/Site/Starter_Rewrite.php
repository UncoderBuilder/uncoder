<?php
/**
 * "Make it yours" (Pro licence, feature "ai_rewrite"): the owner's own AI app, connected to the site over MCP,
 * rewrites the texts of an imported starter site for the owner's business. Uncoder never calls an AI service and
 * needs no AI key: it keeps the business profile, keeps a copy of every page before the AI changes it (so "Undo"
 * can put it back), fills the site title and the empty Business & SEO details, and writes the instruction the owner
 * pastes into the AI app (the same rules as the MCP prompt "make_it_yours").
 *
 *   GET  uncoder/v1/rewrite                                     state: licence, AI apps connected, profile, documents
 *   POST uncoder/v1/rewrite/prepare { profile, title, business } saves the profile, keeps the copies → { prompt }
 *   POST uncoder/v1/rewrite/undo    { id } or { all: true }      the document(s) as they were before
 *
 * Honesty rules for the AI: no invented prices, numbers, awards, names or testimonials; where the template shows
 * such specifics and the profile has none, a short placeholder in square brackets ("[Client name]") that the owner
 * fills in. The state lists the placeholders left on each page.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Licence\Licence;
use Uncoder\Builder\Mcp\Settings as Mcp_Settings;
use Uncoder\Builder\Mcp\Tokens;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Rest\Rest;
use Uncoder\Builder\Rest\Settings_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Starter_Rewrite {

	public const PROFILE  = 'uncoder_wb_rewrite_profile';
	public const FEATURE  = 'ai_rewrite';
	public const TONES    = array( 'professional', 'friendly', 'confident', 'simple', 'persuasive' );
	private const BACKUP  = '_uncoder_wb_rewrite_backup';

	/** Slugs of credits pages (a starter's photo and font licences), which keep their text. */
	private const CREDITS = array( 'licenses', 'licences', 'license', 'licence', 'credits', 'photo-credits' );

	/** Template types whose texts belong to the site (not layouts of posts). */
	private const TEMPLATE_TYPES = array( 'header', 'footer', 'popup', 'section', 'error-404' );

	public function register(): void {
		if ( Licence::enabled() ) {
			add_action( 'rest_api_init', array( $this, 'routes' ) );
		}
	}

	public static function can_use(): bool {
		return current_user_can( 'manage_options' ) && 'full' === Role_Manager::access();
	}

	/** Whether this site's licence covers "Make it yours" (the screen and the MCP prompt). */
	public static function allowed(): bool {
		return Licence::enabled() && Licence::allows( self::FEATURE );
	}

	public function routes(): void {
		$can = array( self::class, 'can_use' );
		register_rest_route( Rest::NS, '/rewrite', array( 'methods' => 'GET', 'callback' => array( $this, 'state' ), 'permission_callback' => $can ) );
		register_rest_route( Rest::NS, '/rewrite/prepare', array( 'methods' => 'POST', 'callback' => array( $this, 'prepare' ), 'permission_callback' => $can ) );
		register_rest_route( Rest::NS, '/rewrite/undo', array( 'methods' => 'POST', 'callback' => array( $this, 'undo' ), 'permission_callback' => $can ) );
	}

	/* ------------------------------------------------------------------ State */

	public function state(): WP_REST_Response {
		$profile = self::profile();
		return new WP_REST_Response(
			array(
				'allowed'   => self::allowed(),
				'profile'   => $profile,
				'tones'     => self::TONES,
				'documents' => self::document_states(),
				'apps'      => self::apps(),
				'connect'   => admin_url( 'admin.php?page=uncoder-ai' ),
				'prompt'    => self::complete( $profile ) ? self::instruction( $profile ) : '',
				'pricing'   => Licence::PRICING,
			)
		);
	}

	/**
	 * Every document with what has happened to it: "starter" (no copy kept yet), "ready" (copy kept, not changed
	 * yet) or "rewritten" (changed since the copy), and the [placeholders] left in it.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function document_states(): array {
		$out = array();
		foreach ( self::documents() as $post ) {
			$elements = Plugin::instance()->documents()->get( $post->ID )->elements();
			$backup   = get_post_meta( $post->ID, self::BACKUP, true );
			$state    = 'starter';
			if ( is_array( $backup ) && isset( $backup['elements'] ) ) {
				$state = (string) wp_json_encode( $elements ) === (string) $backup['elements'] ? 'ready' : 'rewritten';
			}
			$found = array();
			self::placeholders( $elements, $found );
			$out[] = array(
				'id'           => $post->ID,
				'title'        => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ?: __( '(no title)', 'uncoder' ),
				'kind'         => self::kind( $post ),
				'status'       => $post->post_status,
				'state'        => $state,
				'placeholders' => array_slice( array_values( array_unique( $found ) ), 0, 12 ),
				'edit'         => admin_url( 'post.php?action=uncoder&post=' . $post->ID ),
				'url'          => Post_Types::TEMPLATE === $post->post_type ? '' : (string) get_permalink( $post ),
			);
		}
		return $out;
	}

	/**
	 * Builder pages and posts (front page first), then the header, footer, popups and saved sections.
	 *
	 * @return \WP_Post[]
	 */
	private static function documents(): array {
		$types = array_values( array_diff( Plugin::instance()->documents()->post_types(), array( Post_Types::TEMPLATE ) ) );
		$pages = $types ? get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => array( 'publish', 'draft', 'private', 'future' ),
				'posts_per_page' => 300,
				'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
				'meta_key'       => Utils::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		) : array();
		$front = (int) get_option( 'page_on_front' );
		usort( $pages, static fn( $a, $b ) => (int) ( $b->ID === $front ) <=> (int) ( $a->ID === $front ) );
		$templates = get_posts(
			array(
				'post_type'      => Post_Types::TEMPLATE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$templates = array_filter( $templates, static fn( $t ) => in_array( (string) get_post_meta( $t->ID, Utils::META_TYPE, true ), self::TEMPLATE_TYPES, true ) );
		// A starter's Licenses page credits its photos and fonts: it is not rewritten.
		$pages = array_filter( $pages, static fn( $p ) => ! in_array( $p->post_name, self::CREDITS, true ) );
		return array_values( array_filter( array_merge( $pages, $templates ), static fn( $p ) => current_user_can( 'edit_post', $p->ID ) ) );
	}

	private static function kind( \WP_Post $post ): string {
		if ( Post_Types::TEMPLATE === $post->post_type ) {
			$type = (string) get_post_meta( $post->ID, Utils::META_TYPE, true );
			return Post_Types::TEMPLATE_TYPES[ $type ] ?? $type;
		}
		$obj = get_post_type_object( $post->post_type );
		return $obj ? (string) $obj->labels->singular_name : $post->post_type;
	}

	/**
	 * Placeholders the AI left for the owner: "[Client name]", "[Price]". Shortcodes ("[gallery ids=…]") and
	 * lower-case names are not placeholders.
	 *
	 * @param mixed    $node  A tree, a node or a value.
	 * @param string[] $found Placeholders (by reference).
	 */
	private static function placeholders( $node, array &$found ): void {
		if ( is_string( $node ) ) {
			if ( false !== strpos( $node, '[' ) && preg_match_all( '/\[([A-Z][^\]\[\n=\/]{1,58})\]/u', wp_strip_all_tags( $node ), $m ) ) {
				foreach ( $m[0] as $hit ) {
					$found[] = $hit;
				}
			}
			return;
		}
		if ( is_array( $node ) ) {
			foreach ( $node as $key => $value ) {
				if ( '_' !== substr( (string) $key, 0, 1 ) || 'children' === $key ) {
					self::placeholders( $value, $found );
				}
			}
		}
	}

	/**
	 * How many AI apps can reach the site: connected apps (OAuth) and live connection links or API keys. 0 also
	 * when the MCP server is off.
	 */
	private static function apps(): int {
		if ( empty( Mcp_Settings::get( 'enabled' ) ) ) {
			return 0;
		}
		$keys = array_filter( Tokens::list_keys(), static fn( $k ) => empty( $k['revoked'] ) && empty( $k['expired'] ) );
		return count( $keys ) + count( Tokens::list_grants() );
	}

	/* ------------------------------------------------------------------ Profile */

	/**
	 * The business profile: the last one used, else what Business & SEO and the site already know.
	 *
	 * @return array<string,string>
	 */
	public static function profile(): array {
		$saved = get_option( self::PROFILE, array() );
		if ( is_array( $saved ) && $saved ) {
			return self::clean_profile( $saved );
		}
		$b       = Schema::get();
		$address = implode( ', ', array_filter( array( $b['street'], $b['city'], $b['region'], $b['postal'] ) ) );
		return self::clean_profile(
			array(
				'name'        => '' !== $b['name'] ? $b['name'] : '',
				'description' => $b['description'],
				'location'    => '' !== $b['area'] ? $b['area'] : (string) $b['city'],
				'audience'    => '',
				'services'    => '',
				'phone'       => $b['phone'],
				'email'       => $b['email'],
				'address'     => $address,
				'hours'       => implode( "\n", $b['hours'] ),
				'tone'        => 'friendly',
				'language'    => self::language_name(),
				'notes'       => '',
			)
		);
	}

	/** Enough to write for: a name and what the business does. */
	private static function complete( array $p ): bool {
		return '' !== $p['name'] && '' !== $p['description'];
	}

	/**
	 * @param array<string,mixed> $raw Raw profile.
	 * @return array<string,string>
	 */
	private static function clean_profile( array $raw ): array {
		$line = static fn( string $k, int $max = 160 ): string => mb_substr( sanitize_text_field( (string) ( $raw[ $k ] ?? '' ) ), 0, $max );
		$area = static fn( string $k, int $max = 1200 ): string => mb_substr( sanitize_textarea_field( (string) ( $raw[ $k ] ?? '' ) ), 0, $max );
		$tone = (string) ( $raw['tone'] ?? 'friendly' );
		return array(
			'name'        => $line( 'name', 120 ),
			'description' => $area( 'description', 800 ),
			'location'    => $line( 'location' ),
			'audience'    => $line( 'audience', 300 ),
			'services'    => $area( 'services' ),
			'phone'       => $line( 'phone', 40 ),
			'email'       => sanitize_email( (string) ( $raw['email'] ?? '' ) ),
			'address'     => $line( 'address', 200 ),
			'hours'       => $area( 'hours', 300 ),
			'tone'        => in_array( $tone, self::TONES, true ) ? $tone : 'friendly',
			'language'    => $line( 'language', 40 ) ?: self::language_name(),
			'notes'       => $area( 'notes', 600 ),
		);
	}

	private static function language_name(): string {
		$locale = get_locale();
		if ( class_exists( '\Locale' ) ) {
			$name = \Locale::getDisplayName( $locale, 'en' );
			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}
		return 'en_US' === $locale ? 'English (US)' : $locale;
	}

	/* ------------------------------------------------------------------ Prepare */

	/**
	 * Saves the profile, keeps a copy of every document (the ones not kept yet), sets the site title and fills the
	 * empty Business & SEO details; answers with the instruction for the AI app.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function prepare( WP_REST_Request $request ) {
		if ( ! self::allowed() ) {
			return new WP_Error( 'uncoder_needs_pro', __( '“Make it yours” comes with Pro and Agency.', 'uncoder' ), array( 'status' => 403 ) );
		}
		$p = self::clean_profile( (array) $request->get_param( 'profile' ) );
		if ( ! self::complete( $p ) ) {
			return new WP_Error( 'uncoder_rewrite_profile', __( 'Add the business name and what it does first.', 'uncoder' ), array( 'status' => 400 ) );
		}
		update_option( self::PROFILE, $p, false );
		$kept = self::keep_copies();
		if ( $request->get_param( 'title' ) ) {
			update_option( 'blogname', $p['name'] );
		}
		if ( $request->get_param( 'business' ) ) {
			$stored   = get_option( Settings_Controller::OPTION, array() );
			$stored   = is_array( $stored ) ? $stored : array();
			$business = Schema::get();
			foreach ( array( 'name' => $p['name'], 'description' => $p['description'], 'phone' => $p['phone'], 'email' => $p['email'], 'area' => $p['location'] ) as $k => $v ) {
				if ( '' === (string) $business[ $k ] && '' !== $v ) {
					$business[ $k ] = $v;
				}
			}
			$stored['business'] = Schema::sanitize( $business );
			update_option( Settings_Controller::OPTION, $stored );
		}
		return new WP_REST_Response(
			array(
				'prompt'    => self::instruction( $p ),
				'kept'      => $kept,
				'documents' => self::document_states(),
			)
		);
	}

	/**
	 * Keeps a copy of every document that has none yet (an earlier copy is the starter's own text: never replaced).
	 *
	 * @return int How many copies were made now.
	 */
	public static function keep_copies(): int {
		$made = 0;
		foreach ( self::documents() as $post ) {
			if ( get_post_meta( $post->ID, self::BACKUP, true ) ) {
				continue;
			}
			$elements = Plugin::instance()->documents()->get( $post->ID )->elements();
			update_post_meta( $post->ID, self::BACKUP, wp_slash( array( 'time' => time(), 'elements' => (string) wp_json_encode( $elements ) ) ) );
			++$made;
		}
		return $made;
	}

	/**
	 * What the owner pastes into the AI app (and, with the business in one line, the MCP prompt "make_it_yours").
	 *
	 * @param array<string,string> $p Profile (or name + description + tone + language only).
	 */
	public static function instruction( array $p ): string {
		$facts = array_filter(
			array(
				'Name'                 => $p['name'] ?? '',
				( '' !== (string) ( $p['name'] ?? '' ) ? 'What it does' : 'Business' ) => $p['description'] ?? '',
				'Location or area'     => $p['location'] ?? '',
				'Customers'            => $p['audience'] ?? '',
				'Services or products' => $p['services'] ?? '',
				'Phone'                => $p['phone'] ?? '',
				'Email'                => $p['email'] ?? '',
				'Address'              => $p['address'] ?? '',
				'Opening hours'        => $p['hours'] ?? '',
				'Other true facts'     => $p['notes'] ?? '',
			),
			static fn( $v ) => '' !== trim( (string) $v )
		);
		$lines = '';
		foreach ( $facts as $label => $value ) {
			$lines .= '- ' . $label . ': ' . str_replace( "\n", '; ', (string) $value ) . "\n";
		}
		$docs = '';
		foreach ( self::documents() as $post ) {
			$docs .= '- ' . $post->ID . ' · ' . self::kind( $post ) . ' · ' . html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) . "\n";
		}
		$tone     = (string) ( $p['tone'] ?? 'friendly' );
		$language = (string) ( $p['language'] ?? self::language_name() );
		return 'Use the Uncoder tools for ' . home_url( '/' ) . ' ("' . get_bloginfo( 'name' ) . "\") to make this starter site mine.\n\n"
			. "The site was built from a starter template written for another, fictional business. Rewrite its texts for my business. The layout, images and styles stay as they are; only the texts change, and the contact details in links.\n\n"
			. "My business:\n" . $lines . "\n"
			. 'Write in ' . $language . ', in a ' . $tone . " tone.\n\n"
			. "Rewrite these pages and site parts (id · kind · title):\n" . $docs . "\n"
			. "Steps:\n"
			. "1. get_site_overview.\n"
			. "2. For each id above: get_page to see its sections, then get_page with format \"tree\" and the element_id of one top-level section at a time (whole pages are long), and edit_elements with op \"update\" on every heading, text, button, list item, accordion item, testimonial, form label and option, and image alt text. Keep each text's job and about its length (within a third), keep any HTML tags in the same places, and change no layout, style or image.\n"
			. "3. Replace the template's business name, places and people with mine, in the texts and the image alt texts. Phone (tel:) and email (mailto:) links, in texts and on buttons, get my phone and email; map widgets and directions links get my address or area. Never invent prices, numbers, years, awards, certifications, client or staff names, addresses, phone numbers or testimonials: where the template shows such a specific and my details do not give it, write a short placeholder in square brackets that starts with a capital letter, like [Client name], [Price] or [Year founded]. Leave figures such as \"18+\" as they are and list them for me to check. Do not edit HTML or code widgets (such as an \"open now\" badge with opening hours): list them for me instead.\n"
			. "4. set_seo_meta for each page: a title with my business name (at most 60 characters) and a description of 140–160 characters, without placeholders. update_site_settings with a tagline of at most 60 characters.\n"
			. "5. When done, list every [placeholder], every figure and every HTML widget left for me, page by page, and remind me to replace the logo and the photos with my own.\n\n"
			. "Uncoder kept a copy of every page before you start: I can put any page back with Undo in Uncoder → Library → Starter sites → Make it yours.";
	}

	/**
	 * The MCP prompt "make_it_yours": keeps the copies, then the same instruction with the business in one line.
	 */
	public static function mcp_instruction( string $business, string $tone ): string {
		self::keep_copies();
		$saved = self::profile();
		return self::instruction(
			array(
				'name'        => '',
				'description' => $business,
				'tone'        => in_array( $tone, self::TONES, true ) ? $tone : ( '' !== $tone ? sanitize_text_field( $tone ) : $saved['tone'] ),
				'language'    => $saved['language'],
			)
		);
	}

	/* ------------------------------------------------------------------ Undo */

	/** Puts one document back as it was before the rewrite ({ id }), or every one ({ all: true }). */
	public function undo( WP_REST_Request $request ): WP_REST_Response {
		$ids  = $request->get_param( 'all' ) ? wp_list_pluck( self::documents(), 'ID' ) : array( (int) $request->get_param( 'id' ) );
		$done = 0;
		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$backup = get_post_meta( $id, self::BACKUP, true );
			if ( ! current_user_can( 'edit_post', $id ) || ! is_array( $backup ) ) {
				continue;
			}
			$elements = json_decode( (string) ( $backup['elements'] ?? '' ), true );
			if ( is_array( $elements ) ) {
				Plugin::instance()->documents()->get( $id )->save( $elements, array( 'content_fallback' => Post_Types::TEMPLATE !== get_post_type( $id ) ) );
				delete_post_meta( $id, self::BACKUP );
				++$done;
			}
		}
		return new WP_REST_Response( array( 'ok' => $done > 0, 'undone' => $done, 'documents' => self::document_states() ) );
	}
}
