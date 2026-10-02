<?php
/**
 * Element conditions control (Behaviour → Conditions).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Controls\Types;

use Uncoder\Builder\Controls\Control_Type;
use Uncoder\Builder\Core\Element_Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Rule sets deciding whether the element is printed (see Core\Element_Conditions). The editor gets the
 * site's roles and post types with the control.
 */
class Conditions extends Control_Type {

	public function name(): string {
		return 'conditions';
	}

	public function sanitize( $value, array $control ) {
		return Element_Conditions::sanitize( $value );
	}

	public function validate( $value, array $control ): array {
		$errors = array();
		Element_Conditions::sanitize( $value, $errors );
		return $errors;
	}

	public function placeholders( $value, array $control ): ?array {
		return null;
	}

	public function empty_value() {
		return array();
	}

	public function export( array $control ): array {
		$roles = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();
		$types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			$types[ $type->name ] = $type->labels->singular_name;
		}
		$control['roles']      = array_map( 'translate_user_role', $roles );
		$control['post_types'] = $types;
		$control['rules']      = array_map( static fn( $r ) => $r['ops'], Element_Conditions::RULES );
		$taxonomies            = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			if ( 'post_format' !== $tax->name ) {
				$taxonomies[ $tax->name ] = $tax->labels->singular_name;
			}
		}
		$control['taxonomies'] = $taxonomies;
		$languages             = array();
		foreach ( \Uncoder\Builder\Site\Multilingual::site_languages() as $lang ) {
			$languages[ $lang['code'] ] = $lang['name'];
		}
		$control['languages'] = $languages;
		return $control;
	}

	public function value_hint( array $control ): string {
		return 'Rule sets (any set shows the element; all rules of a set must match): [[{"key":"login","op":"is","value":"in"},{"key":"role","op":"is","value":"customer"}],[{"key":"url_param","name":"utm_source","op":"is","value":"newsletter"}]]. Keys: login (value in|out), role (comma list = any of), date ("YYYY-MM-DD" or "YYYY-MM-DD HH:MM"; from|until|is), time (HH:MM; from|until), weekday ("1,2,3" 1=Mon…7), post_type (comma list), page (post id), url_param/cookie/meta (with "name"; exists|not_exists|is|is_not|contains, meta also greater|less), referrer (contains|not_contains|empty|not_empty), term (with "name" = taxonomy e.g. category; value = slugs or ids; is|is_not — the post or the archive shown), author (user ids or logins), parent (page id; exists|not_exists = has a parent), featured_image (exists|not_exists), comments (number; greater|less|is), archive (front_page, blog, singular, category, tag, taxonomy, author, date, search, post_type_archive, not_found), dynamic (with "name" = a dynamic tag without options, e.g. post-title; compared like meta), browser (chrome, firefox, safari, edge, opera, samsung), os (windows, macos, ios, android, linux), language (codes, e.g. "en,de"). Browser / os / referrer / cookie rules vary per visitor: avoid them on fully cached pages.';
	}
}
