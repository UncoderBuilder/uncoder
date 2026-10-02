<?php
/**
 * State passed to element renderers.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Carries the document, the element being rendered and helpers for nested children.
 */
final class Render_Context {

	/** Document (post id) whose tree is being rendered. */
	public int $doc_id = 0;

	/** Post that dynamic data refers to (the queried post, or the loop item). */
	public int $post_id = 0;

	/** Rendering for the editor canvas. */
	public bool $editor = false;

	/** The document renders many times on one page (loop items): elements get a class instead of an id. */
	public bool $repeat = false;

	/** Inside a document's first top-level element (above the fold): background images load right away. */
	public bool $eager = false;

	/** Document type (page, post, header, loop-item…): decides the Auto tag of top-level containers. */
	public string $doc_type = '';

	/** The term of a container term loop (term-* dynamic tags read it). */
	public ?\WP_Term $term = null;

	/** @var array<string,mixed> Current element node. */
	public array $element = array();

	public string $element_id = '';

	public int $depth = 0;

	public ?Renderer $renderer = null;

	/**
	 * Renders the nth child container of a nested widget (tabs, accordion…).
	 * In the editor this returns a slot the canvas fills in.
	 */
	public function render_child( int $index ): string {
		$children = (array) ( $this->element['children'] ?? array() );
		if ( $this->editor ) {
			$child_id = isset( $children[ $index ]['id'] ) ? (string) $children[ $index ]['id'] : '';
			return '<div class="uncoder-slot" data-uncoder-slot="' . esc_attr( (string) $index ) . '" data-uncoder-slot-for="' . esc_attr( $child_id ) . '"></div>';
		}
		if ( ! isset( $children[ $index ] ) || null === $this->renderer ) {
			return '';
		}
		return $this->renderer->render_element( $children[ $index ], $this->depth + 1 );
	}

	public function child_count(): int {
		return count( (array) ( $this->element['children'] ?? array() ) );
	}

	/**
	 * `data-uncoder-inline` attribute string for canvas inline editing (editor only).
	 */
	public function inline( string $key ): string {
		return $this->editor ? ' data-uncoder-inline="' . esc_attr( $key ) . '"' : '';
	}
}
