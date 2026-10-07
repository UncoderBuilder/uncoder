<?php
/**
 * Display conditions for theme templates and popups.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Theme;

defined( 'ABSPATH' ) || exit;

/**
 * A condition: { type: include|exclude, rule, post_type?, taxonomy?, ids? }.
 * The most specific matching include wins; any matching exclude vetoes.
 */
final class Conditions {

	/**
	 * Rule catalogue (for validation, the admin UI and AI clients).
	 *
	 * @return array<string, array{label:string, context:string, fields:string[], score:int}>
	 */
	public static function rules(): array {
		return array(
			'general'    => array( 'label' => 'Entire site', 'context' => 'any', 'fields' => array(), 'score' => 10 ),
			'singular'   => array( 'label' => 'Singular (posts, pages, CPTs)', 'context' => 'singular', 'fields' => array( 'post_type', 'ids' ), 'score' => 20 ),
			'front_page' => array( 'label' => 'Front page', 'context' => 'singular', 'fields' => array(), 'score' => 90 ),
			'posts_page' => array( 'label' => 'Blog (posts page)', 'context' => 'archive', 'fields' => array(), 'score' => 85 ),
			'in_term'    => array( 'label' => 'Singular in term', 'context' => 'singular', 'fields' => array( 'taxonomy', 'ids' ), 'score' => 45 ),
			'child_of'   => array( 'label' => 'Child page of', 'context' => 'singular', 'fields' => array( 'ids' ), 'score' => 50 ),
			'by_author'  => array( 'label' => 'Singular by author', 'context' => 'singular', 'fields' => array( 'ids' ), 'score' => 40 ),
			'archive'    => array( 'label' => 'Archives', 'context' => 'archive', 'fields' => array( 'post_type', 'taxonomy', 'ids' ), 'score' => 20 ),
			'author'     => array( 'label' => 'Author archive', 'context' => 'archive', 'fields' => array( 'ids' ), 'score' => 40 ),
			'date'       => array( 'label' => 'Date archive', 'context' => 'archive', 'fields' => array(), 'score' => 40 ),
			'search'     => array( 'label' => 'Search results', 'context' => 'search', 'fields' => array(), 'score' => 60 ),
			'not_found'  => array( 'label' => '404 page', 'context' => '404', 'fields' => array(), 'score' => 90 ),
		);
	}

	/**
	 * Normalizes and validates conditions. Errors describe what is wrong for AI clients.
	 *
	 * @param mixed    $raw    Raw conditions.
	 * @param string[] $errors Errors.
	 * @return array<int, array<string,mixed>>
	 */
	public static function sanitize( $raw, array &$errors = array() ): array {
		if ( is_string( $raw ) ) {
			$raw = array( array( 'type' => 'include', 'rule' => $raw ) );
		}
		if ( ! is_array( $raw ) ) {
			$errors[] = 'Conditions must be an array of {type, rule, …} objects.';
			return array();
		}
		if ( isset( $raw['rule'] ) ) {
			$raw = array( $raw );
		}
		$rules = self::rules();
		$out   = array();
		foreach ( array_values( $raw ) as $i => $c ) {
			if ( is_string( $c ) ) {
				$c = array( 'rule' => $c );
			}
			if ( ! is_array( $c ) ) {
				$errors[] = "conditions[{$i}] must be an object.";
				continue;
			}
			$rule = sanitize_key( str_replace( '-', '_', (string) ( $c['rule'] ?? '' ) ) );
			$rule = array( 'entire_site' => 'general', 'all' => 'general', 'singular_all' => 'singular', '404' => 'not_found', 'front' => 'front_page', 'home' => 'front_page', 'blog' => 'posts_page' )[ $rule ] ?? $rule;
			if ( ! isset( $rules[ $rule ] ) ) {
				$errors[] = sprintf( 'conditions[%d]: unknown rule "%s". Use one of: %s.', $i, (string) ( $c['rule'] ?? '' ), implode( ', ', array_keys( $rules ) ) );
				continue;
			}
			$type = 'exclude' === ( $c['type'] ?? 'include' ) ? 'exclude' : 'include';
			$item = array(
				'type' => $type,
				'rule' => $rule,
			);
			if ( in_array( 'post_type', $rules[ $rule ]['fields'], true ) && ! empty( $c['post_type'] ) ) {
				$pt = sanitize_key( (string) $c['post_type'] );
				if ( ! post_type_exists( $pt ) ) {
					$errors[] = sprintf( 'conditions[%d]: post type "%s" does not exist.', $i, $pt );
					continue;
				}
				$item['post_type'] = $pt;
			}
			if ( in_array( 'taxonomy', $rules[ $rule ]['fields'], true ) && ! empty( $c['taxonomy'] ) ) {
				$tax = sanitize_key( (string) $c['taxonomy'] );
				if ( ! taxonomy_exists( $tax ) ) {
					$errors[] = sprintf( 'conditions[%d]: taxonomy "%s" does not exist.', $i, $tax );
					continue;
				}
				$item['taxonomy'] = $tax;
			}
			if ( 'in_term' === $rule && empty( $item['taxonomy'] ) ) {
				$item['taxonomy'] = 'category';
			}
			if ( in_array( 'ids', $rules[ $rule ]['fields'], true ) && ! empty( $c['ids'] ) ) {
				$item['ids'] = array_values( array_filter( array_map( 'absint', (array) $c['ids'] ) ) );
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Specificity score of a condition for the current request, or null when it does not match.
	 *
	 * @param array<string,mixed> $c Condition.
	 */
	public static function score( array $c ): ?int {
		$rules = self::rules();
		$rule  = (string) ( $c['rule'] ?? '' );
		if ( ! isset( $rules[ $rule ] ) ) {
			return null;
		}
		$base = $rules[ $rule ]['score'];
		$ids  = (array) ( $c['ids'] ?? array() );
		$qid  = (int) get_queried_object_id();

		switch ( $rule ) {
			case 'general':
				return $base;
			case 'front_page':
				return is_front_page() ? $base : null;
			case 'posts_page':
				return is_home() ? $base : null;
			case 'not_found':
				return is_404() ? $base : null;
			case 'search':
				return is_search() ? $base : null;
			case 'date':
				return is_date() ? $base : null;
			case 'author':
				if ( ! is_author() ) {
					return null;
				}
				return $ids ? ( in_array( $qid, $ids, true ) ? 70 : null ) : $base;
			case 'singular':
				if ( ! is_singular() || is_404() ) {
					return null;
				}
				$score = $base;
				if ( ! empty( $c['post_type'] ) ) {
					if ( get_post_type( $qid ) !== $c['post_type'] ) {
						return null;
					}
					$score = 30;
				}
				if ( $ids ) {
					return in_array( $qid, $ids, true ) ? 80 : null;
				}
				return $score;
			case 'in_term':
				if ( ! is_singular() ) {
					return null;
				}
				$tax   = (string) ( $c['taxonomy'] ?? 'category' );
				$terms = wp_get_post_terms( $qid, $tax, array( 'fields' => 'ids' ) );
				if ( is_wp_error( $terms ) || ! $terms ) {
					return null;
				}
				return ! $ids || array_intersect( $ids, array_map( 'intval', $terms ) ) ? $base : null;
			case 'child_of':
				if ( ! is_singular() ) {
					return null;
				}
				$ancestors = get_post_ancestors( $qid );
				return $ancestors && ( ! $ids || array_intersect( $ids, array_map( 'intval', $ancestors ) ) ) ? $base : null;
			case 'by_author':
				if ( ! is_singular() ) {
					return null;
				}
				$author = (int) get_post_field( 'post_author', $qid );
				return ! $ids || in_array( $author, $ids, true ) ? $base : null;
			case 'archive':
				if ( ! ( is_archive() || is_home() ) || is_search() ) {
					return null;
				}
				if ( ! empty( $c['taxonomy'] ) ) {
					$tax = (string) $c['taxonomy'];
					$is  = 'category' === $tax ? is_category() : ( 'post_tag' === $tax ? is_tag() : is_tax( $tax ) );
					if ( ! $is ) {
						return null;
					}
					if ( $ids ) {
						return in_array( $qid, $ids, true ) ? 65 : null;
					}
					return 40;
				}
				if ( ! empty( $c['post_type'] ) ) {
					$pt = (string) $c['post_type'];
					if ( 'post' === $pt ) {
						return ( is_home() || is_category() || is_tag() || is_date() || is_author() ) ? 30 : null;
					}
					if ( is_post_type_archive( $pt ) ) {
						return 35;
					}
					if ( is_tax() ) {
						$term = get_queried_object();
						$tax  = $term instanceof \WP_Term ? get_taxonomy( $term->taxonomy ) : null;
						return $tax && in_array( $pt, (array) $tax->object_type, true ) ? 30 : null;
					}
					return null;
				}
				return $base;
		}
		return null;
	}

	/**
	 * Best score for a list of conditions, or null (no include matched or an exclude matched).
	 *
	 * @param array<int, array<string,mixed>> $conditions Conditions.
	 */
	public static function match( array $conditions ): ?int {
		$best = null;
		foreach ( $conditions as $c ) {
			$score = self::score( $c );
			if ( null === $score ) {
				continue;
			}
			if ( 'exclude' === ( $c['type'] ?? 'include' ) ) {
				return null;
			}
			$best = null === $best ? $score : max( $best, $score );
		}
		return $best;
	}

	/**
	 * Human summary ("Entire site · − Page: Pricing").
	 *
	 * @param array<int, array<string,mixed>> $conditions Conditions.
	 */
	public static function summary( array $conditions ): string {
		$rules = self::rules();
		$parts = array();
		foreach ( $conditions as $c ) {
			$label = $rules[ $c['rule'] ]['label'] ?? $c['rule'];
			if ( ! empty( $c['post_type'] ) ) {
				$obj    = get_post_type_object( $c['post_type'] );
				$label .= ': ' . ( $obj ? $obj->labels->name : $c['post_type'] );
			}
			if ( ! empty( $c['taxonomy'] ) ) {
				$tax    = get_taxonomy( $c['taxonomy'] );
				$label .= ': ' . ( $tax ? $tax->labels->name : $c['taxonomy'] );
			}
			if ( ! empty( $c['ids'] ) ) {
				$label .= ' #' . implode( ', #', $c['ids'] );
			}
			$parts[] = ( 'exclude' === $c['type'] ? '− ' : '' ) . $label;
		}
		return $parts ? implode( ' · ', $parts ) : 'Not shown anywhere';
	}
}
