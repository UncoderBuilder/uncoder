<?php
/**
 * MCP prompts: ready-made multi-step instructions users can pick in their client.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Site\Starter_Rewrite;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * build_website, create_landing_page, redesign_page, audit_and_fix, make_it_yours.
 */
final class Prompts {

	/**
	 * @return array<string, array<string,mixed>>
	 */
	private static function defs(): array {
		$defs = array(
			'build_website'       => array(
				'title'       => 'Build a complete website',
				'description' => 'Design system, header, footer, core pages and navigation for a business.',
				'arguments'   => array(
					array( 'name' => 'business', 'description' => 'What the business is and who it serves', 'required' => true ),
					array( 'name' => 'pages', 'description' => 'Pages to create (default: Home, About, Services, Contact)', 'required' => false ),
					array( 'name' => 'style', 'description' => 'Visual direction, e.g. "warm editorial", "bold tech", "minimal luxury"', 'required' => false ),
				),
			),
			'create_landing_page' => array(
				'title'       => 'Create a landing page',
				'description' => 'A focused, conversion-oriented page for one offer.',
				'arguments'   => array(
					array( 'name' => 'offer', 'description' => 'The product or offer', 'required' => true ),
					array( 'name' => 'audience', 'description' => 'Who it is for', 'required' => false ),
					array( 'name' => 'goal', 'description' => 'Primary action (buy, book, sign up…)', 'required' => false ),
				),
			),
			'redesign_page'       => array(
				'title'       => 'Redesign an existing page',
				'description' => 'Improve structure, hierarchy and visuals of a page while keeping its content.',
				'arguments'   => array(
					array( 'name' => 'page_id', 'description' => 'Page ID', 'required' => true ),
					array( 'name' => 'direction', 'description' => 'What should change', 'required' => false ),
				),
			),
			'make_it_yours'       => array(
				'title'       => 'Make a starter site yours',
				'description' => 'Rewrite every text of an imported starter site for your business; layout, images and styles stay. Uncoder keeps a copy of every page first (Pro and Agency).',
				'arguments'   => array(
					array( 'name' => 'business', 'description' => 'Name, what you do, where, for whom, services, contact details', 'required' => true ),
					array( 'name' => 'tone', 'description' => 'Tone of voice, e.g. friendly, professional, confident', 'required' => false ),
				),
			),
			'audit_and_fix'       => array(
				'title'       => 'Audit and fix a page',
				'description' => 'Run the accessibility/SEO/layout audit and fix what it finds.',
				'arguments'   => array(
					array( 'name' => 'page_id', 'description' => 'Page ID', 'required' => true ),
				),
			),
		);
		if ( ! Starter_Rewrite::allowed() ) {
			unset( $defs['make_it_yours'] ); // Pro and Agency only.
		}
		return $defs;
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	public static function list(): array {
		$out = array();
		foreach ( self::defs() as $name => $def ) {
			$out[] = array_merge( array( 'name' => $name ), $def );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $args Arguments.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get( string $name, array $args ) {
		$defs = self::defs();
		if ( ! isset( $defs[ $name ] ) ) {
			return new WP_Error( 'not_found', 'Unknown prompt.' );
		}
		foreach ( $defs[ $name ]['arguments'] as $arg ) {
			if ( ! empty( $arg['required'] ) && empty( $args[ $arg['name'] ] ) ) {
				return new WP_Error( 'missing', 'Missing argument: ' . $arg['name'] );
			}
		}
		$a    = array_map( static fn( $v ) => sanitize_text_field( (string) $v ), $args );
		$text = '';
		switch ( $name ) {
			case 'build_website':
				$pages = $a['pages'] ?? 'Home, About, Services, Contact';
				$style = $a['style'] ?? 'choose a fitting, distinctive direction';
				$text  = "Build a complete website on this WordPress site for: {$a['business']}.\nPages: {$pages}. Visual direction: {$style}.\n\n"
					. "Steps:\n1. get_site_overview and get_build_guide (overview, layout, design-system, theme-builder).\n"
					. "2. update_design_system: a brand palette (primary, secondary, accent, heading, text, surface, border), a heading + body Google Font pairing, text styles and button style.\n"
					. "3. create_menu for the pages, then a header template (site-logo, nav-menu, CTA button; sticky) and a footer template, both for the entire site.\n"
					. "4. create_page for each page with 4–7 well-structured sections (hero with h1, benefits, social proof placeholders, FAQ, CTA). Start each section from list_patterns / get_pattern and adapt the copy. Use search_images + upload_media for photos with real alt text.\n"
					. "5. update_site_settings: set Home as the front page. set_seo_meta for each page.\n"
					. "6. audit_page each page, fix the issues, then publish_page.\nNever invent facts (prices, reviews, stats) — use [placeholders] the owner can fill in.";
				break;
			case 'create_landing_page':
				$audience = $a['audience'] ?? 'the most likely buyers';
				$goal     = $a['goal'] ?? 'the main conversion';
				$text     = "Create a landing page for: {$a['offer']} (audience: {$audience}; goal: {$goal}).\n"
					. "Read get_build_guide topics overview, layout and recipes first, then list_patterns: build the page from get_pattern sections and adapt their copy. Use the Uncoder Canvas template if a distraction-free page is better.\n"
					. "Structure: hero with one clear CTA → problem/benefits → how it works (steps) → proof (testimonial placeholders) → pricing or offer details → FAQ (accordion) → final CTA band.\n"
					. 'Keep one primary CTA text throughout, check mobile settings, run audit_page and fix issues. Leave it as a draft and report the preview URL.';
				break;
			case 'redesign_page':
				$direction = $a['direction'] ?? 'clearer hierarchy, better spacing, stronger visuals';
				$text      = "Redesign page {$a['page_id']} ({$direction}).\n1. get_page with format outline, then tree for sections you change.\n"
					. "2. get_design_system and keep using its tokens.\n3. Improve section by section with edit_elements (update/replace/insert), keeping the existing copy unless it is placeholder text.\n"
					. '4. audit_page and fix issues. Summarise what changed; remind the user that undo_last_change reverts each step.';
				break;
			case 'make_it_yours':
				// Pro and Agency; Uncoder keeps a copy of every page before the AI app changes it (Undo in the admin).
				$text = Starter_Rewrite::mcp_instruction( $a['business'], $a['tone'] ?? '' );
				break;
			case 'audit_and_fix':
				$text = "Run audit_page for page {$a['page_id']}. Fix every error and as many warnings as sensible using edit_elements (alt text, heading order, contrast, mobile overrides, empty links). Re-run audit_page until it is clean and report the result.";
				break;
		}
		return array(
			'description' => $defs[ $name ]['description'],
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => array(
						'type' => 'text',
						'text' => $text,
					),
				),
			),
		);
	}
}
