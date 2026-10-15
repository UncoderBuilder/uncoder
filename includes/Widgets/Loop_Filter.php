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
					'field'    => __( 'Custom field', 'uncoder' ),
					'search'   => __( 'Search', 'uncoder' ),
					'sort'     => __( 'Sort order', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'field_key',
			array(
				'type'        => 'text',
				'label'       => __( 'Field key', 'uncoder' ),
				'placeholder' => 'location',
				'description' => __( 'The custom field (meta key) the posts of the grid have, for example location or salary_from. Private fields, whose key starts with _, cannot filter.', 'uncoder' ),
				'condition'   => array( 'filter' => 'field' ),
				'ai'          => 'Meta key of a public custom field on the grid\'s posts (set with create_post/update_post meta). Several Loop Filters on different fields narrow the grid together.',
			)
		);
		$this->add_control(
			'field_compare',
			array(
				'type'      => 'select',
				'label'     => __( 'Show posts whose field', 'uncoder' ),
				'default'   => '',
				'options'   => array(
					''    => __( 'Is the chosen value', 'uncoder' ),
					'min' => __( 'Is at least the chosen number', 'uncoder' ),
					'max' => __( 'Is at most the chosen number', 'uncoder' ),
				),
				'condition' => array( 'filter' => 'field' ),
				'ai'        => '"" exact match (one of the chosen values); "min"/"max" numeric thresholds, e.g. a salary_from field with options "£40,000+|40000".',
			)
		);
		$this->add_control(
			'field_options',
			array(
				'type'        => 'textarea',
				'label'       => __( 'Options', 'uncoder' ),
				'placeholder' => "Manchester\nLondon\nRemote (UK)|remote",
				'description' => __( 'One per line: the value, or Label|value. Empty: the values the posts have, A–Z. For numbers, list the thresholds, for example £40,000+|40000.', 'uncoder' ),
				'condition'   => array( 'filter' => 'field' ),
				'ai'          => 'Newline-separated "value" or "Label|value". Needed for min/max; optional for exact match (empty lists the distinct values found).',
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
				'condition' => array( 'filter' => array( 'taxonomy', 'field' ) ),
			)
		);
		$this->add_control( 'multiple', array( 'type' => 'switch', 'label' => __( 'Allow several at once', 'uncoder' ), 'condition' => array( 'filter' => array( 'taxonomy', 'field' ), 'display' => 'pills' ) ) );
		$this->add_control( 'show_all', array( 'type' => 'switch', 'label' => __( 'Show “All”', 'uncoder' ), 'default' => true, 'condition' => array( 'filter' => array( 'taxonomy', 'field' ), 'display!' => 'checkboxes' ) ) );
		$this->add_control( 'all_label', array( 'type' => 'text', 'label' => __( '“All” label', 'uncoder' ), 'placeholder' => __( 'All', 'uncoder' ), 'condition' => array( 'filter' => array( 'taxonomy', 'field' ) ) ) );
		$this->add_control( 'show_count', array( 'type' => 'switch', 'label' => __( 'Show counts', 'uncoder' ), 'condition' => array( 'filter' => 'taxonomy' ) ) );
		$this->add_control( 'hide_empty', array( 'type' => 'switch', 'label' => __( 'Hide empty terms', 'uncoder' ), 'default' => true, 'condition' => array( 'filter' => 'taxonomy' ) ) );
		$this->add_control( 'top_level', array( 'type' => 'switch', 'label' => __( 'Top-level terms only', 'uncoder' ), 'condition' => array( 'filter' => 'taxonomy' ) ) );
		$this->add_control(
			'term_order',
			array(
				'type'      => 'select',
				'label'     => __( 'Order', 'uncoder' ),
				'default'   => '',
				'options'   => array(
					''       => __( 'By name', 'uncoder' ),
					'count'  => __( 'Most posts first', 'uncoder' ),
					'custom' => __( 'In the order below', 'uncoder' ),
				),
				'condition' => array( 'filter' => 'taxonomy' ),
				'ai'        => 'The order of the terms: "" A–Z, "count" the most used first, "custom" exactly the slugs in term_list (e.g. "insight, press, news"; only those are shown).',
			)
		);
		$this->add_control(
			'term_list',
			array(
				'type'        => 'text',
				'label'       => __( 'Terms, in order', 'uncoder' ),
				'placeholder' => 'insight, press, news',
				'description' => __( 'Comma-separated slugs. Only these are shown, in this order.', 'uncoder' ),
				'condition'   => array( 'filter' => 'taxonomy', 'term_order' => 'custom' ),
			)
		);
		$this->add_control(
			'exclude',
			array(
				'type'        => 'text',
				'label'       => __( 'Exclude term IDs', 'uncoder' ),
				'placeholder' => '1, 12',
				'description' => __( 'Comma-separated IDs of terms to leave out, e.g. Uncategorized.', 'uncoder' ),
				'ai'          => 'Comma-separated term IDs to hide from the filter, e.g. "1" for Uncategorized.',
				'condition'   => array( 'filter' => 'taxonomy' ),
			)
		);
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
		$this->add_control(
			'label_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Label position', 'uncoder' ),
				'default'   => '',
				'options'   => array(
					''      => __( 'Beside the filter', 'uncoder' ),
					'above' => __( 'Above the filter', 'uncoder' ),
				),
				'condition' => array( 'label!' => '' ),
				'ai'        => '"above" puts the label on its own line and stretches a dropdown or search box to the full width (a row of filters in a filter bar).',
			)
		);
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
		$attrs = array( 'data-uncoder-filter' => $this->key( $s ) );
		if ( 'above' === ( $s['label_position'] ?? '' ) && '' !== trim( (string) ( $s['label'] ?? '' ) ) ) {
			$attrs['class'] = 'uncoder-loop-filter-wrap--label-above';
		}
		return $attrs;
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
		$filter = in_array( $s['filter'] ?? 'taxonomy', array( 'taxonomy', 'field', 'search', 'sort' ), true ) ? $s['filter'] : 'taxonomy';
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

		if ( 'field' === $filter ) {
			$items = $this->field_items( $s, $ctx );
			if ( null === $items ) {
				return;
			}
			$mode  = in_array( $s['field_compare'] ?? '', array( 'min', 'max' ), true ) ? (string) $s['field_compare'] : 'f';
			$field = sanitize_key( (string) ( $s['field_key'] ?? '' ) );
			$name  = $mode . '_' . $field;
			$aria  = '' !== $label ? $label : ucfirst( str_replace( array( '_', '-' ), ' ', $field ) );
		} else {
			$tax = sanitize_key( (string) ( $s['taxonomy'] ?? 'category' ) );
			// Only public taxonomies filter a grid (Site\Loop_Filters ignores the others).
			if ( ! taxonomy_exists( $tax ) || ! is_taxonomy_viewable( $tax ) ) {
				if ( $ctx->editor ) {
					echo '<p class="uncoder-loop-filter__empty">' . esc_html__( 'Choose a taxonomy.', 'uncoder' ) . '</p>';
				}
				return;
			}
			$items = $this->term_items( $s, $tax );
			if ( ! $items ) {
				if ( $ctx->editor ) {
					echo '<p class="uncoder-loop-filter__empty">' . esc_html__( 'No terms to show yet.', 'uncoder' ) . '</p>';
				}
				return;
			}
			$mode = 'f';
			$name = $tax;
			$aria = '' !== $label ? $label : get_taxonomy( $tax )->labels->name;
		}
		$param    = Loop_Filters::param( $key, $name );
		$active   = Loop_Filters::values( $key, $name );
		$display  = in_array( $s['display'] ?? 'pills', array( 'pills', 'dropdown', 'checkboxes' ), true ) ? $s['display'] : 'pills';
		// A number threshold takes one choice: checkboxes become a dropdown, buttons stay single.
		$single   = 'f' !== $mode;
		$display  = $single && 'checkboxes' === $display ? 'dropdown' : $display;
		$multiple = ! $single && ( ! empty( $s['multiple'] ) || 'checkboxes' === $display );
		$all      = trim( (string) ( $s['all_label'] ?? '' ) ) ?: __( 'All', 'uncoder' );
		$counts   = 'field' !== $filter && ! empty( $s['show_count'] );
		$count    = static fn( $item ) => $counts ? ' <span class="uncoder-loop-filter__count">' . (int) $item['count'] . '</span>' : '';

		if ( 'dropdown' === $display ) {
			echo '<form class="uncoder-loop-filter uncoder-loop-filter--dropdown" method="get" data-filter="' . esc_attr( $name ) . '">' . $head . $this->keep( $key, $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '<select class="uncoder-loop-filter__field" name="' . esc_attr( $param ) . '" aria-label="' . esc_attr( $aria ) . '">';
			echo '<option value="">' . esc_html( $all ) . '</option>';
			foreach ( $items as $item ) {
				echo '<option value="' . esc_attr( $item['value'] ) . '"' . selected( in_array( $item['value'], $active, true ), true, false ) . '>' . esc_html( $item['label'] ) . ( $counts ? ' (' . (int) $item['count'] . ')' : '' ) . '</option>';
			}
			echo '</select><noscript><button type="submit" class="uncoder-loop-filter__pill">' . esc_html__( 'Filter', 'uncoder' ) . '</button></noscript></form>';
			return;
		}

		if ( 'checkboxes' === $display ) {
			echo '<form class="uncoder-loop-filter uncoder-loop-filter--checkboxes" method="get" data-filter="' . esc_attr( $name ) . '"><fieldset><legend class="uncoder-loop-filter__label' . ( '' === $label ? ' uncoder-sr-only' : '' ) . '">' . esc_html( $aria ) . '</legend>' . $this->keep( $key, $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			foreach ( $items as $item ) {
				echo '<label class="uncoder-loop-filter__check"><input type="checkbox" name="' . esc_attr( $param ) . '[]" value="' . esc_attr( $item['value'] ) . '"' . checked( in_array( $item['value'], $active, true ), true, false ) . '> <span>' . esc_html( $item['label'] ) . '</span>' . $count( $item ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			}
			echo '</fieldset><noscript><button type="submit" class="uncoder-loop-filter__pill">' . esc_html__( 'Filter', 'uncoder' ) . '</button></noscript></form>';
			return;
		}

		// Buttons: links to the filtered URL (aria-pressed state for assistive technology).
		echo '<nav class="uncoder-loop-filter uncoder-loop-filter--pills" aria-label="' . esc_attr( $aria ) . '" data-filter="' . esc_attr( $name ) . '">' . $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		if ( ! array_key_exists( 'show_all', $s ) || ! empty( $s['show_all'] ) ) {
			echo '<a class="uncoder-loop-filter__pill' . ( $active ? '' : ' is-active' ) . '" href="' . esc_url( Loop_Filters::url( array( $param => '' ) ) ) . '"' . ( $active ? '' : ' aria-current="true"' ) . '>' . esc_html( $all ) . '</a>';
		}
		foreach ( $items as $item ) {
			$on   = in_array( $item['value'], $active, true );
			$next = $multiple ? ( $on ? array_values( array_diff( $active, array( $item['value'] ) ) ) : array_merge( $active, array( $item['value'] ) ) ) : ( $on ? array() : array( $item['value'] ) );
			echo '<a class="uncoder-loop-filter__pill' . ( $on ? ' is-active' : '' ) . '" href="' . esc_url( Loop_Filters::url( array( $param => $next ) ) ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html( $item['label'] ) . $count( $item ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}
		echo '</nav>';
	}

	/**
	 * The terms a taxonomy filter lists, as value / label / count items.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<int, array{value:string,label:string,count:int}>
	 */
	private function term_items( array $s, string $tax ): array {
		$args = array(
			'taxonomy'   => $tax,
			'hide_empty' => ! array_key_exists( 'hide_empty', $s ) || ! empty( $s['hide_empty'] ),
			'number'     => 100,
		);
		if ( ! empty( $s['top_level'] ) ) {
			$args['parent'] = 0;
		}
		$exclude = array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) ( $s['exclude'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY ) ?: array() ) ) );
		if ( $exclude ) {
			$args['exclude'] = $exclude;
		}
		// The order: A–Z (the default), the most used first, or a given list of slugs (slugs, not ids, so a Site Kit
		// import keeps it).
		$order = (string) ( $s['term_order'] ?? '' );
		if ( 'count' === $order ) {
			$args['orderby'] = 'count';
			$args['order']   = 'DESC';
		} elseif ( 'custom' === $order ) {
			$slugs = array_values( array_filter( array_map( 'sanitize_title', preg_split( '/\s*,\s*/', trim( (string) ( $s['term_list'] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY ) ?: array() ) ) );
			if ( $slugs ) {
				$args['slug']    = $slugs;
				$args['orderby'] = 'slug__in';
			}
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map(
			static fn( $term ) => array(
				'value' => (string) $term->slug,
				'label' => (string) $term->name,
				'count' => (int) $term->count,
			),
			$terms
		);
	}

	/**
	 * The choices of a custom field filter: the typed options ("value" or "Label|value" per line), or else the
	 * values the published posts have, A–Z. Null when there is nothing to show (with a hint in the editor).
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<int, array{value:string,label:string,count:int}>|null
	 */
	private function field_items( array $s, Render_Context $ctx ): ?array {
		$field = sanitize_key( (string) ( $s['field_key'] ?? '' ) );
		if ( '' === $field || is_protected_meta( $field, 'post' ) ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-loop-filter__empty">' . esc_html__( 'Type the key of a custom field (one that does not start with _).', 'uncoder' ) . '</p>';
			}
			return null;
		}
		$items = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $s['field_options'] ?? '' ) ) ?: array() as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts   = array_map( 'trim', explode( '|', $line, 2 ) );
			$value   = sanitize_text_field( $parts[1] ?? $parts[0] );
			$items[] = array(
				'value' => $value,
				'label' => sanitize_text_field( $parts[0] ),
				'count' => 0,
			);
			if ( count( $items ) >= 100 ) {
				break;
			}
		}
		if ( ! $items ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- distinct values of one public field, small and read-only.
			$values = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_status = 'publish' AND pm.meta_value <> '' ORDER BY pm.meta_value ASC LIMIT 100", $field ) );
			foreach ( $values as $value ) {
				$value   = sanitize_text_field( (string) $value );
				$items[] = array(
					'value' => $value,
					'label' => $value,
					'count' => 0,
				);
			}
		}
		if ( ! $items && $ctx->editor ) {
			echo '<p class="uncoder-loop-filter__empty">' . esc_html__( 'No values to show yet: no published post has this field, and no options are typed.', 'uncoder' ) . '</p>';
		}
		return $items ? $items : null;
	}
}
