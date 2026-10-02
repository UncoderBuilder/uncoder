<?php
/**
 * Loop Filter widget: live category / tag filters, search and sorting for a Loop Grid or Posts widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Site\Loop_Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Plain links and GET forms (works without JavaScript and gives shareable URLs); the loop-filter
 * module swaps the grid in place instead of reloading. Several filters can target the same grid.
 */
class Loop_Filter extends Widget_Base {

	public function name(): string {
		return 'loop-filter';
	}

	public function title(): string {
		return __( 'Loop Filter', 'uncoder' );
	}

	public function icon(): string {
		return 'list-filter';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'filter', 'category', 'tags', 'search', 'sort', 'ajax', 'portfolio', 'blog', 'facet' );
	}

	public function description(): string {
		return __( 'Category pills, a dropdown, checkboxes, a search box or a sort menu that filter a Loop Grid or Posts widget on the same page without reloading.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'loop-filter' );
	}

	/**
	 * @return array<string,string>
	 */
	private static function taxonomies(): array {
		$out = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			if ( 'post_format' !== $tax->name ) {
				$out[ $tax->name ] = $tax->labels->name;
			}
		}
		return $out;
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Filter', 'uncoder' ) ) );
		$this->add_control(
			'target',
			array(
				'type'        => 'text',
				'label'       => __( 'Grid anchor ID', 'uncoder' ),
				'placeholder' => __( 'Empty: the grid without an ID', 'uncoder' ),
				'description' => __( 'The Anchor ID (Behaviour → Anchor & classes) of the Loop Grid or Posts widget to filter. Only needed when a page has several grids.', 'uncoder' ),
				'ai'          => 'Must equal the target grid\'s _css_id; omit when the page has one grid without _css_id.',
			)
		);
		$this->add_control(
			'filter',
			array(
				'type'    => 'select',
				'label'   => __( 'Filter by', 'uncoder' ),
				'default' => 'taxonomy',
				'options' => array(
					'taxonomy' => __( 'Category, tag or taxonomy', 'uncoder' ),
					'search'   => __( 'Search', 'uncoder' ),
					'sort'     => __( 'Sort order', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'taxonomy',
			array(
				'type'      => 'select',
				'label'     => __( 'Taxonomy', 'uncoder' ),
				'default'   => 'category',
				'options'   => self::taxonomies(),
				'condition' => array( 'filter' => 'taxonomy' ),
			)
		);
		$this->add_control(
			'display',
			array(
				'type'      => 'choose',
				'label'     => __( 'Display', 'uncoder' ),
				'default'   => 'pills',
				'options'   => array(
					'pills'      => array( 'label' => __( 'Buttons', 'uncoder' ), 'icon' => 'rectangle-horizontal' ),
					'dropdown'   => array( 'label' => __( 'Dropdown', 'uncoder' ), 'icon' => 'chevron-down' ),
					'checkboxes' => array( 'label' => __( 'Checkboxes', 'uncoder' ), 'icon' => 'list-checks' ),
				),
				'condition' => array( 'filter' => 'taxonomy' ),
			)
		);
		$this->add_control( 'multiple', array( 'type' => 'switch', 'label' => __( 'Allow several at once', 'uncoder' ), 'condition' => array( 'filter' => 'taxonomy', 'display' => 'pills' ) ) );
		$this->add_control( 'show_all', array( 'type' => 'switch', 'label' => __( 'Show “All”', 'uncoder' ), 'default' => true, 'condition' => array( 'filter' => 'taxonomy', 'display!' => 'checkboxes' ) ) );
		$this->add_control( 'all_label', array( 'type' => 'text', 'label' => __( '“All” label', 'uncoder' ), 'placeholder' => __( 'All', 'uncoder' ), 'condition' => array( 'filter' => 'taxonomy' ) ) );
		$this->add_control( 'show_count', array( 'type' => 'switch', 'label' => __( 'Show counts', 'uncoder' ), 'condition' => array( 'filter' => 'taxonomy' ) ) );
		$this->add_control( 'hide_empty', array( 'type' => 'switch', 'label' => __( 'Hide empty terms', 'uncoder' ), 'default' => true, 'condition' => array( 'filter' => 'taxonomy' ) ) );
		$this->add_control( 'top_level', array( 'type' => 'switch', 'label' => __( 'Top-level terms only', 'uncoder' ), 'condition' => array( 'filter' => 'taxonomy' ) ) );
		$this->add_control( 'placeholder', array( 'type' => 'text', 'label' => __( 'Placeholder', 'uncoder' ), 'placeholder' => __( 'Search…', 'uncoder' ), 'condition' => array( 'filter' => 'search' ) ) );
		$this->add_control(
			'sorts',
			array(
				'type'      => 'multiselect',
				'label'     => __( 'Sort options', 'uncoder' ),
				'default'   => array( 'date_desc', 'date_asc', 'title_asc' ),
				'options'   => self::sort_labels(),
				'condition' => array( 'filter' => 'sort' ),
			)
		);
		$this->add_control( 'label', array( 'type' => 'text', 'label' => __( 'Label', 'uncoder' ), 'description' => __( 'Shown before the filter; also its accessible name.', 'uncoder' ) ) );
		$this->end_section();

		$this->start_section( 'style_items', array( 'label' => __( 'Buttons & fields', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'justify',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'flex-start' => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-left' ),
					'center'     => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'flex-end'   => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'justify-content: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control( 'gap', array( 'type' => 'slider', 'label' => __( 'Gap', 'uncoder' ), 'size_units' => array( 'px' ), 'selectors' => array( '{{WRAPPER}}' => 'gap: {{VALUE}}' ) ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-loop-filter__pill, {{WRAPPER}} .uncoder-loop-filter__field' ) );
		$this->add_control( 'color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-lf-color: {{VALUE}}' ) ) );
		$this->add_control( 'background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-lf-bg: {{VALUE}}' ) ) );
		$this->add_control( 'border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-lf-border: {{VALUE}}' ) ) );
		$this->add_control( 'active_color', array( 'type' => 'color', 'label' => __( 'Active text', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-lf-active-color: {{VALUE}}' ) ) );
		$this->add_control( 'active_background', array( 'type' => 'color', 'label' => __( 'Active background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-lf-active-bg: {{VALUE}}' ) ) );
		$this->add_responsive_control( 'radius', array( 'type' => 'slider', 'label' => __( 'Radius', 'uncoder' ), 'size_units' => array( 'px' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-lf-radius: {{VALUE}}' ) ) );
		$this->add_responsive_control( 'padding', array( 'type' => 'dimensions', 'label' => __( 'Padding', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-loop-filter__pill' => 'padding: {{VALUE}}' ) ) );
		$this->end_section();
	}

	/**
	 * @return array<string,string>
	 */
	private static function sort_labels(): array {
		return array(
			'date_desc'     => __( 'Newest first', 'uncoder' ),
			'date_asc'      => __( 'Oldest first', 'uncoder' ),
			'title_asc'     => __( 'Title A–Z', 'uncoder' ),
			'title_desc'    => __( 'Title Z–A', 'uncoder' ),
			'modified_desc' => __( 'Recently updated', 'uncoder' ),
			'comments_desc' => __( 'Most commented', 'uncoder' ),
			'menu_order'    => __( 'Custom order', 'uncoder' ),
		);
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array( 'data-uncoder-filter' => $this->key( $s ) );
	}

	private function key( array $s ): string {
		$key = sanitize_key( (string) ( $s['target'] ?? '' ) );
		return '' !== $key ? $key : 'loop';
	}

	/**
	 * Hidden inputs that keep the other active filters of the same grid when a form is submitted.
	 */
	private function keep( string $key, string $except ): string {
		$html = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		foreach ( array_keys( $_GET ) as $param ) {
			$param = (string) $param;
			if ( 0 === strpos( $param, 'uf-' . $key . '-' ) && Loop_Filters::param( $key, $except ) !== $param ) {
				$name  = substr( $param, strlen( 'uf-' . $key . '-' ) );
				$html .= '<input type="hidden" name="' . esc_attr( $param ) . '" value="' . esc_attr( implode( ',', Loop_Filters::values( $key, sanitize_key( $name ) ) ) ) . '">';
			}
		}
		return $html;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$key    = $this->key( $s );
		$filter = in_array( $s['filter'] ?? 'taxonomy', array( 'taxonomy', 'search', 'sort' ), true ) ? $s['filter'] : 'taxonomy';
		$label  = trim( (string) ( $s['label'] ?? '' ) );
		$head   = '' !== $label ? '<span class="uncoder-loop-filter__label">' . esc_html( $label ) . '</span>' : '';

		if ( 'search' === $filter ) {
			$param = Loop_Filters::param( $key, 's' );
			$value = implode( ' ', Loop_Filters::values( $key, 's' ) );
			echo '<form class="uncoder-loop-filter uncoder-loop-filter--search" method="get" role="search" data-filter="s">' . $head . $this->keep( $key, 's' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '<input class="uncoder-loop-filter__field" type="search" name="' . esc_attr( $param ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( trim( (string) ( $s['placeholder'] ?? '' ) ) ?: __( 'Search…', 'uncoder' ) ) . '" aria-label="' . esc_attr( '' !== $label ? $label : __( 'Search', 'uncoder' ) ) . '">';
			echo '<button type="submit" class="uncoder-loop-filter__pill uncoder-loop-filter__submit">' . $this->render_icon( 'search' ) . '<span class="uncoder-sr-only">' . esc_html__( 'Search', 'uncoder' ) . '</span></button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
			return;
		}

		if ( 'sort' === $filter ) {
			$param   = Loop_Filters::param( $key, 'sort' );
			$current = Loop_Filters::values( $key, 'sort' )[0] ?? '';
			$labels  = self::sort_labels();
			$sorts   = array_values( array_intersect( (array) ( $s['sorts'] ?? array() ), array_keys( $labels ) ) );
			echo '<form class="uncoder-loop-filter uncoder-loop-filter--sort" method="get" data-filter="sort">' . $head . $this->keep( $key, 'sort' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '<select class="uncoder-loop-filter__field" name="' . esc_attr( $param ) . '" aria-label="' . esc_attr( '' !== $label ? $label : __( 'Sort by', 'uncoder' ) ) . '">';
			echo '<option value="">' . esc_html__( 'Default order', 'uncoder' ) . '</option>';
			foreach ( $sorts as $sort ) {
				echo '<option value="' . esc_attr( $sort ) . '"' . selected( $current, $sort, false ) . '>' . esc_html( $labels[ $sort ] ) . '</option>';
			}
			echo '</select><noscript><button type="submit" class="uncoder-loop-filter__pill">' . esc_html__( 'Sort', 'uncoder' ) . '</button></noscript></form>';
			return;
		}

		$tax = sanitize_key( (string) ( $s['taxonomy'] ?? 'category' ) );
		// Only public taxonomies filter a grid (Site\Loop_Filters ignores the others).
		if ( ! taxonomy_exists( $tax ) || ! is_taxonomy_viewable( $tax ) ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-loop-filter__empty">' . esc_html__( 'Choose a taxonomy.', 'uncoder' ) . '</p>';
			}
			return;
		}
		$args = array(
			'taxonomy'   => $tax,
			'hide_empty' => ! array_key_exists( 'hide_empty', $s ) || ! empty( $s['hide_empty'] ),
			'number'     => 100,
		);
		if ( ! empty( $s['top_level'] ) ) {
			$args['parent'] = 0;
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || ! $terms ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-loop-filter__empty">' . esc_html__( 'No terms to show yet.', 'uncoder' ) . '</p>';
			}
			return;
		}
		$param    = Loop_Filters::param( $key, $tax );
		$active   = Loop_Filters::values( $key, $tax );
		$display  = in_array( $s['display'] ?? 'pills', array( 'pills', 'dropdown', 'checkboxes' ), true ) ? $s['display'] : 'pills';
		$multiple = ! empty( $s['multiple'] ) || 'checkboxes' === $display;
		$all      = trim( (string) ( $s['all_label'] ?? '' ) ) ?: __( 'All', 'uncoder' );
		$count    = static fn( $term ) => ! empty( $s['show_count'] ) ? ' <span class="uncoder-loop-filter__count">' . (int) $term->count . '</span>' : '';
		$name     = '' !== $label ? $label : get_taxonomy( $tax )->labels->name;

		if ( 'dropdown' === $display ) {
			echo '<form class="uncoder-loop-filter uncoder-loop-filter--dropdown" method="get" data-filter="' . esc_attr( $tax ) . '">' . $head . $this->keep( $key, $tax ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '<select class="uncoder-loop-filter__field" name="' . esc_attr( $param ) . '" aria-label="' . esc_attr( $name ) . '">';
			echo '<option value="">' . esc_html( $all ) . '</option>';
			foreach ( $terms as $term ) {
				echo '<option value="' . esc_attr( $term->slug ) . '"' . selected( in_array( $term->slug, $active, true ), true, false ) . '>' . esc_html( $term->name ) . ( ! empty( $s['show_count'] ) ? ' (' . (int) $term->count . ')' : '' ) . '</option>';
			}
			echo '</select><noscript><button type="submit" class="uncoder-loop-filter__pill">' . esc_html__( 'Filter', 'uncoder' ) . '</button></noscript></form>';
			return;
		}

		if ( 'checkboxes' === $display ) {
			echo '<form class="uncoder-loop-filter uncoder-loop-filter--checkboxes" method="get" data-filter="' . esc_attr( $tax ) . '"><fieldset><legend class="uncoder-loop-filter__label' . ( '' === $label ? ' uncoder-sr-only' : '' ) . '">' . esc_html( $name ) . '</legend>' . $this->keep( $key, $tax ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			foreach ( $terms as $term ) {
				echo '<label class="uncoder-loop-filter__check"><input type="checkbox" name="' . esc_attr( $param ) . '[]" value="' . esc_attr( $term->slug ) . '"' . checked( in_array( $term->slug, $active, true ), true, false ) . '> <span>' . esc_html( $term->name ) . '</span>' . $count( $term ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			}
			echo '</fieldset><noscript><button type="submit" class="uncoder-loop-filter__pill">' . esc_html__( 'Filter', 'uncoder' ) . '</button></noscript></form>';
			return;
		}

		// Buttons: links to the filtered URL (aria-pressed state for assistive technology).
		echo '<nav class="uncoder-loop-filter uncoder-loop-filter--pills" aria-label="' . esc_attr( $name ) . '" data-filter="' . esc_attr( $tax ) . '">' . $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		if ( ! array_key_exists( 'show_all', $s ) || ! empty( $s['show_all'] ) ) {
			echo '<a class="uncoder-loop-filter__pill' . ( $active ? '' : ' is-active' ) . '" href="' . esc_url( Loop_Filters::url( array( $param => '' ) ) ) . '"' . ( $active ? '' : ' aria-current="true"' ) . '>' . esc_html( $all ) . '</a>';
		}
		foreach ( $terms as $term ) {
			$on   = in_array( $term->slug, $active, true );
			$next = $multiple ? ( $on ? array_values( array_diff( $active, array( $term->slug ) ) ) : array_merge( $active, array( $term->slug ) ) ) : ( $on ? array() : array( $term->slug ) );
			echo '<a class="uncoder-loop-filter__pill' . ( $on ? ' is-active' : '' ) . '" href="' . esc_url( Loop_Filters::url( array( $param => $next ) ) ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html( $term->name ) . $count( $term ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}
		echo '</nav>';
	}
}
