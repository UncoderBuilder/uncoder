<?php
/**
 * Loop Grid widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Site\Loop_Filters;
use Uncoder\Builder\Controls\Groups\Query;
use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Document;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Renderer;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Template_Embed;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a "loop-item" template once per post of a query, in a CSS grid, with pagination,
 * "load more" and infinite scroll. Dynamic tags inside the template resolve to each post.
 *
 * Per-post values never reach the template's shared stylesheet: the CSS engine only reads static
 * settings (dynamic tags are resolved at render time, into markup), so every card can share
 * `.uncoder-{templateId}` CSS. The optional `--uncoder-loop-image` variable carries the featured image
 * per card for custom CSS backgrounds.
 */
class Loop_Grid extends Widget_Base {

	public const TEMPLATE_TYPE = 'loop-item';

	public const MODES = array( 'none', 'numbers', 'prev_next', 'numbers_prev_next', 'load_more', 'infinite' );

	public const VARIANTS = array( 'primary', 'secondary', 'outline', 'ghost' );

	/** @var int[] Posts already shown on this page (for "avoid duplicates"). */
	protected static array $shown = array();

	/**
	 * Posts already shown on this page by loops (also used by looping containers).
	 *
	 * @return int[]
	 */
	public static function shown(): array {
		return self::$shown;
	}

	/**
	 * Records posts a loop has shown.
	 *
	 * @param int[] $ids Post ids.
	 */
	public static function mark_shown( array $ids ): void {
		foreach ( $ids as $id ) {
			self::$shown[] = (int) $id;
		}
	}

	/** @var array<string,int> Grid ids handed out on this page (keeps DOM ids unique). */
	private static array $ids = array();

	public function name(): string {
		return 'loop-grid';
	}

	public function title(): string {
		return __( 'Loop Grid', 'uncoder' );
	}

	public function icon(): string {
		return 'layout-dashboard';
	}

	public function category(): string {
		return 'content';
	}

	public function keywords(): array {
		return array( 'loop', 'grid', 'posts', 'query', 'cards', 'archive', 'blog', 'template', 'masonry', 'load more', 'infinite scroll' );
	}

	public function description(): string {
		return __( 'Repeats a Loop item template (a card designed with dynamic tags) for every post of a query, in a responsive grid with pagination, load more or infinite scroll. Use source "current" in archive and search templates.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'loop-grid' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	public function preset(): array {
		return array(
			'columns'        => 3,
			'columns_tablet' => 2,
			'columns_mobile' => 1,
		);
	}

	protected function register_controls(): void {
		$load_modes = array( 'load_more', 'infinite' );
		$link_modes = array( 'numbers', 'prev_next', 'numbers_prev_next' );

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_control(
			'template_notice',
			array(
				'type'  => 'notice',
				'label' => __( 'Each card is a Loop item template: create one in Uncoder → Theme Builder → Loop items, design it with dynamic tags such as Post title, Featured image and Post URL, publish it, then choose it here.', 'uncoder' ),
			)
		);
		$this->add_control(
			'loop_template',
			array(
				'type'            => 'select',
				'label'           => __( 'Loop item template', 'uncoder' ),
				'default'         => '',
				'options_dynamic' => true,
				'options'         => array(),
				'source'          => 'templates-' . self::TEMPLATE_TYPE,
				'ai'              => 'Id of a published template of type "loop-item" (create_template {"type":"loop-item"}), e.g. "42". Its dynamic tags (post-title, featured-image, post-url…) resolve to each post.',
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'type'        => 'number',
				'label'       => __( 'Columns', 'uncoder' ),
				'description' => __( 'Empty fits as many columns as the space allows.', 'uncoder' ),
				'min'         => 1,
				'max'         => 12,
				'selectors'   => array( '{{WRAPPER}} .uncoder-loop-grid__grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr))' ),
			)
		);
		$this->add_control(
			'masonry',
			array(
				'type'        => 'switch',
				'label'       => __( 'Masonry', 'uncoder' ),
				'description' => __( 'Cards keep their own height and move up into the gaps.', 'uncoder' ),
				'default'     => false,
			)
		);
		$this->add_control(
			'masonry_order',
			array(
				'type'        => 'select',
				'label'       => __( 'Masonry order', 'uncoder' ),
				'description' => __( 'Column by column: card 1 in the first column, card 2 in the second… and so on, each under the previous card of its column (with an alternate template every second card this forms a checkerboard).', 'uncoder' ),
				'default'     => '',
				'options'     => array(
					''        => __( 'Shortest column first', 'uncoder' ),
					'columns' => __( 'Column by column', 'uncoder' ),
				),
				'condition'   => array( 'masonry' => true ),
			)
		);
		$this->add_control(
			'equal_height',
			array(
				'type'        => 'switch',
				'label'       => __( 'Equal height', 'uncoder' ),
				'description' => __( 'Stretches the cards of a row to the tallest one.', 'uncoder' ),
				'default'     => true,
				'condition'   => array( 'masonry!' => true ),
			)
		);
		$this->add_control(
			'image_var',
			array(
				'type'        => 'switch',
				'label'       => __( 'Featured image as CSS variable', 'uncoder' ),
				'description' => __( 'Adds --uncoder-loop-image to every card, e.g. for custom CSS "background-image: var(--uncoder-loop-image)".', 'uncoder' ),
				'default'     => false,
			)
		);
		$this->end_section();

		$this->start_section( 'content_query', array( 'label' => __( 'Query', 'uncoder' ) ) );
		$this->add_group(
			'query',
			array(
				'type'    => 'query',
				'label'   => __( 'Query', 'uncoder' ),
				'default' => array(
					'source'         => 'posts',
					'post_type'      => 'post',
					'posts_per_page' => 6,
					'orderby'        => 'date',
					'order'          => 'desc',
				),
				'ai'      => 'Archive/search templates: {"source":"current"}. Otherwise a custom query, e.g. {"source":"posts","post_type":"post","posts_per_page":6}.',
			)
		);
		$this->add_control(
			'nothing_found',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Nothing found message', 'uncoder' ),
				'default' => __( 'Nothing found. Try another search or browse the latest posts.', 'uncoder' ),
				'rows'    => 2,
			)
		);
		$this->end_section();

		$this->start_section( 'content_alternate', array( 'label' => __( 'Alternate template', 'uncoder' ) ) );
		$this->add_control(
			'alternate_template',
			array(
				'type'            => 'select',
				'label'           => __( 'Alternate template', 'uncoder' ),
				'description'     => __( 'Optional second Loop item template shown at a set position, e.g. a highlighted card.', 'uncoder' ),
				'default'         => '',
				'options_dynamic' => true,
				'options'         => array(),
				'source'          => 'templates-' . self::TEMPLATE_TYPE,
				'ai'              => 'Optional id of another "loop-item" template used at alternate_position.',
			)
		);
		$this->add_control(
			'alternate_position',
			array(
				'type'      => 'number',
				'label'     => __( 'Position', 'uncoder' ),
				'default'   => 3,
				'min'       => 1,
				'max'       => 100,
				'condition' => array( 'alternate_template!' => '' ),
			)
		);
		$this->add_control(
			'alternate_repeat',
			array(
				'type'        => 'switch',
				'label'       => __( 'Repeat', 'uncoder' ),
				'description' => __( 'On: every Nth card. Off: only the Nth card.', 'uncoder' ),
				'default'     => true,
				'condition'   => array( 'alternate_template!' => '' ),
			)
		);
		$this->add_responsive_control(
			'alternate_span',
			array(
				'type'        => 'number',
				'label'       => __( 'Column span', 'uncoder' ),
				'description' => __( 'Keep it at or below the number of columns.', 'uncoder' ),
				'min'         => 1,
				'max'         => 12,
				'condition'   => array( 'alternate_template!' => '' ),
				'selectors'   => array( '{{WRAPPER}} .uncoder-loop-grid__item--alt' => 'grid-column: span {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_pagination', array( 'label' => __( 'Pagination', 'uncoder' ) ) );
		$this->add_control(
			'pagination',
			array(
				'type'    => 'select',
				'label'   => __( 'Pagination', 'uncoder' ),
				'default' => 'none',
				'options' => array(
					'none'              => __( 'None', 'uncoder' ),
					'numbers'           => __( 'Numbers', 'uncoder' ),
					'prev_next'         => __( 'Previous / next', 'uncoder' ),
					'numbers_prev_next' => __( 'Numbers with previous / next', 'uncoder' ),
					'load_more'         => __( 'Load more button', 'uncoder' ),
					'infinite'          => __( 'Infinite scroll', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'prev_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Previous text', 'uncoder' ),
				'default'   => __( 'Previous', 'uncoder' ),
				'condition' => array( 'pagination' => array( 'prev_next', 'numbers_prev_next' ) ),
			)
		);
		$this->add_control(
			'next_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Next text', 'uncoder' ),
				'default'   => __( 'Next', 'uncoder' ),
				'condition' => array( 'pagination' => array( 'prev_next', 'numbers_prev_next' ) ),
			)
		);
		$this->add_control(
			'load_more_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Button text', 'uncoder' ),
				'default'   => __( 'Load more', 'uncoder' ),
				'condition' => array( 'pagination' => $load_modes ),
			)
		);
		$this->add_control(
			'loading_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Loading text', 'uncoder' ),
				'default'   => __( 'Loading…', 'uncoder' ),
				'condition' => array( 'pagination' => $load_modes ),
			)
		);
		$this->add_control(
			'more_variant',
			array(
				'type'      => 'select',
				'label'     => __( 'Button style', 'uncoder' ),
				'default'   => 'outline',
				'options'   => array(
					'primary'   => __( 'Primary', 'uncoder' ),
					'secondary' => __( 'Secondary', 'uncoder' ),
					'outline'   => __( 'Outline', 'uncoder' ),
					'ghost'     => __( 'Ghost', 'uncoder' ),
				),
				'condition' => array( 'pagination' => 'load_more' ),
			)
		);
		$this->add_responsive_control(
			'pagination_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'start'  => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'end'    => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'start'  => 'flex-start',
					'center' => 'center',
					'end'    => 'flex-end',
				),
				'condition'            => array( 'pagination!' => 'none' ),
				'selectors'            => array( '{{WRAPPER}}' => '--uncoder-loop-nav-justify: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->register_style_controls( $link_modes, $load_modes );
	}

	/**
	 * @param string[] $link_modes Pagination modes printing links.
	 * @param string[] $load_modes Pagination modes printing a button.
	 */
	private function register_style_controls( array $link_modes, array $load_modes ): void {
		$this->start_section( 'style_layout', array( 'label' => __( 'Layout', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'column_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Column gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-loop-col-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Row gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-loop-row-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'nav_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Pagination distance', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'condition'  => array( 'pagination!' => 'none' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-loop-nav-space: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_pagination',
			array(
				'label'     => __( 'Pagination', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'pagination' => $link_modes ),
			)
		);
		$this->add_group( 'pagination_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-loop-grid__pagination' ) );
		$this->start_tabs( 'pagination_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'pagination_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__pagination .page-numbers' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pagination_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__pagination .page-numbers' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'pagination_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__pagination a.page-numbers:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pagination_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__pagination a.page-numbers:hover' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Active', 'uncoder' ) );
		$this->add_control(
			'pagination_active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__pagination .page-numbers.current' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pagination_active_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__pagination .page-numbers.current' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_control(
			'pagination_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-loop-grid__pagination .page-numbers' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pagination_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-loop-grid__pagination' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_more',
			array(
				'label'     => __( 'Load more button', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'pagination' => $load_modes ),
			)
		);
		$this->add_group( 'more_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-loop-grid__more' ) );
		$this->start_tabs( 'more_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'more_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__more' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'more_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__more' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'more_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__more:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'more_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__more:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'more_hover_border',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__more:is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_group( 'more_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-loop-grid__more' ) );
		$this->add_responsive_control(
			'more_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-loop-grid__more' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'more_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-loop-grid__more' => 'padding: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_empty', array( 'label' => __( 'Nothing found message', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'empty_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-loop-grid__empty' ) );
		$this->add_control(
			'empty_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-loop-grid__empty' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/* ------------------------------------------------------------------ Helpers */

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	protected function mode( array $s ): string {
		$mode = (string) ( $s['pagination'] ?? 'none' );
		return in_array( $mode, self::MODES, true ) ? $mode : 'none';
	}

	private static function is_load_mode( string $mode ): bool {
		return 'load_more' === $mode || 'infinite' === $mode;
	}

	/**
	 * Runs the query for the settings (same rules as the Posts widget, shared `uncoder_page` arg).
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array{query:\WP_Query, main:bool, paged:int, max:int, per_page:int}
	 */
	protected function query( array $s, Render_Context $ctx ): array {
		$q        = is_array( $s['query'] ?? null ) ? $s['query'] : array();
		$paginate = 'none' !== $this->mode( $s );
		$current  = Theme_Context::post( $ctx );

		if ( 'current' === ( $q['source'] ?? 'posts' ) ) {
			global $wp_query;
			if ( ! $ctx->editor && $wp_query instanceof \WP_Query ) {
				// A Loop Filter on the current archive: the same query again, with the visitor's filters applied.
				$base = (array) $wp_query->query_vars;
				if ( empty( $base['post_type'] ) ) {
					$base['post_type'] = 'post';
				}
				$filtered = Loop_Filters::apply( $base, Loop_Filters::key( $s ) );
				if ( $filtered !== $base ) {
					$filtered['paged'] = max( 1, (int) get_query_var( 'paged' ) );
					$filtered_query    = new \WP_Query( $filtered );
					return array(
						'query'    => $filtered_query,
						'main'     => true,
						'paged'    => $filtered['paged'],
						'max'      => (int) $filtered_query->max_num_pages,
						'per_page' => max( 1, (int) $filtered_query->get( 'posts_per_page' ) ),
					);
				}
				return array(
					'query'    => $wp_query,
					'main'     => true,
					'paged'    => max( 1, (int) get_query_var( 'paged' ) ),
					'max'      => (int) $wp_query->max_num_pages,
					'per_page' => max( 1, (int) $wp_query->get( 'posts_per_page' ) ),
				);
			}
			// The editor has no archive request: preview the latest posts instead.
			$q = array(
				'source'         => 'posts',
				'post_type'      => 'post',
				'posts_per_page' => max( 1, (int) get_option( 'posts_per_page', 10 ) ),
			);
		}

		$args  = Query::to_wp_query_args( $q, $current ? $current->ID : 0 );
		$args  = Loop_Filters::apply( is_array( $args ) ? $args : array(), Loop_Filters::key( $s ) );
		$per   = max( 1, (int) ( $args['posts_per_page'] ?? 6 ) );
		$paged = 1;
		if ( $paginate ) {
			// Custom queries paginate with their own query arg so they never clash with the main query.
			$paged = $ctx->editor ? 1 : max( 1, absint( filter_input( INPUT_GET, Posts::PAGE_ARG, FILTER_SANITIZE_NUMBER_INT ) ) );
			if ( ! empty( $args['offset'] ) ) {
				$args['offset'] = (int) $args['offset'] + ( $paged - 1 ) * $per;
			} else {
				$args['paged'] = $paged;
			}
		} else {
			$args['no_found_rows'] = true;
		}
		if ( ! empty( $q['avoid_duplicates'] ) && self::$shown ) {
			$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), self::$shown ) ) );
		}
		$args['ignore_sticky_posts'] = $args['ignore_sticky_posts'] ?? true;

		$query = new \WP_Query( $args );
		$max   = 0;
		if ( $paginate ) {
			$offset = (int) ( $q['offset'] ?? 0 );
			$max    = (int) ceil( max( 0, (int) $query->found_posts - $offset ) / $per );
		}
		return array(
			'query'    => $query,
			'main'     => false,
			'paged'    => $paged,
			'max'      => $max,
			'per_page' => $per,
		);
	}

	/**
	 * Whether the card at a 1-based position (counted across pages) uses the alternate template.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function is_alternate( int $position, array $s ): bool {
		$n = max( 1, (int) ( $s['alternate_position'] ?? 3 ) );
		return ! empty( $s['alternate_repeat'] ) ? 0 === $position % $n : $position === $n;
	}

	/**
	 * The cards of the current page.
	 *
	 * @param array{query:\WP_Query, main:bool, paged:int, max:int, per_page:int} $result Query result.
	 * @param array<string,mixed>                                                 $s      Settings.
	 * @return array{html:string, count:int}
	 */
	private function items( array $result, Document $template, ?Document $alt, array $s, Render_Context $ctx ): array {
		$html  = '';
		$count = 0;
		$start = ( $result['paged'] - 1 ) * $result['per_page'];
		foreach ( $result['query']->posts as $raw ) {
			$post = get_post( $raw );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			++$count;
			self::$shown[] = (int) $post->ID;
			$use_alt       = null !== $alt && $this->is_alternate( $start + $count, $s );
			$doc           = $use_alt ? $alt : $template;
			$html         .= Theme_Context::with_post( $post, fn() => $this->item( $doc, $post, $use_alt, $s, $ctx ) );
		}
		return array(
			'html'  => $html,
			'count' => $count,
		);
	}

	/**
	 * One card: the loop item document rendered for $post (global post already switched to it).
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	protected function item( Document $doc, \WP_Post $post, bool $alternate, array $s, Render_Context $ctx ): string {
		$classes = array( 'uncoder-loop-grid__item', 'uncoder--loop-item' ); // The loop-item type class keeps cards out of the page section spacing.
		if ( $alternate ) {
			$classes[] = 'uncoder-loop-grid__item--alt';
		}
		foreach ( get_post_class( '', $post ) as $class ) {
			$classes[] = sanitize_html_class( $class );
		}
		$attrs = array( 'data-post-id' => (string) $post->ID );
		if ( ! empty( $s['image_var'] ) ) {
			$url = Utils::css_url( (string) get_the_post_thumbnail_url( $post, 'large' ) );
			if ( '' !== $url ) {
				$attrs['style'] = '--uncoder-loop-image:url("' . $url . '")';
			}
		}
		// Always a front-end render, even in the editor canvas: the card's elements belong to the template,
		// not to the edited document, so they must not carry canvas hooks (inline editing, nested slots).
		$renderer = new Renderer( $doc->id(), false, (int) $post->ID );
		$html     = $renderer->render_document(
			$doc->elements(),
			array(
				'tag'   => 'article',
				'class' => implode( ' ', array_unique( array_filter( $classes ) ) ),
				'attrs' => $attrs,
			)
		);
		return $ctx->editor ? Template_Embed::for_canvas( $html ) : $html;
	}

	private function page_url( int $page, bool $main ): string {
		if ( $main ) {
			return (string) get_pagenum_link( $page, false );
		}
		return (string) add_query_arg( Posts::PAGE_ARG, $page );
	}

	/**
	 * Pagination links, or the load-more button.
	 *
	 * @param array<string,mixed>                                                 $s      Settings.
	 * @param array{query:\WP_Query, main:bool, paged:int, max:int, per_page:int} $result Query result.
	 */
	private function navigation( array $s, array $result, Render_Context $ctx, string $grid_id ): string {
		$mode  = $this->mode( $s );
		$max   = $result['max'];
		$paged = $result['paged'];
		if ( 'none' === $mode || $max < 2 ) {
			return '';
		}

		if ( self::is_load_mode( $mode ) ) {
			if ( $paged >= $max ) {
				return '';
			}
			$next    = $ctx->editor ? '' : esc_url_raw( $this->page_url( $paged + 1, $result['main'] ) );
			$label   = trim( (string) ( $s['load_more_text'] ?? '' ) );
			$label   = '' !== $label ? $label : __( 'Load more', 'uncoder' );
			$variant = in_array( $s['more_variant'] ?? 'outline', self::VARIANTS, true ) ? $s['more_variant'] : 'outline';
			$button  = '<button' . Utils::attrs(
				array(
					'type'          => 'button',
					'class'         => 'uncoder-btn uncoder-btn--' . $variant . ' uncoder-loop-grid__more',
					'data-next'     => '' !== $next ? $next : null,
					'aria-controls' => $grid_id,
				)
			) . '><span class="uncoder-loop-grid__more-text">' . esc_html( $label ) . '</span><span class="uncoder-loop-grid__spinner" aria-hidden="true"></span></button>';
			// Without JavaScript (and for crawlers) the next page stays reachable through a plain link.
			$link = '' !== $next ? '<a class="uncoder-loop-grid__next-link" href="' . esc_url( $next ) . '">' . esc_html__( 'Next page', 'uncoder' ) . '</a>' : '';
			return '<div class="uncoder-loop-grid__load">' . $button . $link . '</div>';
		}

		$prev = trim( (string) ( $s['prev_text'] ?? '' ) );
		$next = trim( (string) ( $s['next_text'] ?? '' ) );
		$args = array(
			'total'     => $max,
			'current'   => min( $paged, $max ),
			'type'      => 'array',
			'mid_size'  => 1,
			'prev_next' => 'numbers' !== $mode,
			'prev_text' => esc_html( '' !== $prev ? $prev : __( 'Previous', 'uncoder' ) ),
			'next_text' => esc_html( '' !== $next ? $next : __( 'Next', 'uncoder' ) ),
		);
		if ( $ctx->editor ) {
			$args['base']   = '#%#%';
			$args['format'] = '';
		} elseif ( ! $result['main'] ) {
			$args['base']   = add_query_arg( Posts::PAGE_ARG, '%#%' );
			$args['format'] = '';
		}
		$links = paginate_links( $args );
		if ( ! is_array( $links ) || ! $links ) {
			return '';
		}
		if ( 'prev_next' === $mode ) {
			$links = array_values(
				array_filter(
					$links,
					static fn( $link ) => false !== strpos( (string) $link, 'prev page-numbers' ) || false !== strpos( (string) $link, 'next page-numbers' )
				)
			);
			if ( ! $links ) {
				return '';
			}
		}
		// paginate_links() returns core-escaped markup.
		return '<nav class="uncoder-loop-grid__pagination" aria-label="' . esc_attr__( 'Posts pagination', 'uncoder' ) . '">' . implode( '', $links ) . '</nav>';
	}

	/**
	 * Editor-only placeholder.
	 */
	protected function placeholder( string $title, string $text, string $link = '', string $link_text = '' ): string {
		$html  = '<div class="uncoder-loop-grid__placeholder">';
		$html .= $this->render_icon( 'layout-dashboard', array( 'class' => 'uncoder-loop-grid__placeholder-icon' ) );
		$html .= '<strong class="uncoder-loop-grid__placeholder-title">' . esc_html( $title ) . '</strong>';
		$html .= '<span class="uncoder-loop-grid__placeholder-text">' . esc_html( $text ) . '</span>';
		if ( '' !== $link ) {
			$html .= '<a class="uncoder-loop-grid__placeholder-link" href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( $link_text ) . '</a>';
		}
		return $html . '</div>';
	}

	private function grid_id( Render_Context $ctx ): string {
		$base                = 'uncoder-loop-' . sanitize_html_class( $ctx->element_id );
		self::$ids[ $base ] = ( self::$ids[ $base ] ?? 0 ) + 1;
		return 1 === self::$ids[ $base ] ? $base : $base . '-' . self::$ids[ $base ];
	}

	/* ------------------------------------------------------------------ Output */

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		// Loop Filter widgets find their grid by this key (the CSS ID, or "loop").
		$filter = array( 'data-uncoder-filter-key' => Loop_Filters::key( $s ) );
		if ( ! self::is_load_mode( $this->mode( $s ) ) ) {
			return $filter;
		}
		return $filter + array(
			'data-settings' => $this->json_attr(
				array(
					'mode'    => $this->mode( $s ),
					'loading' => trim( (string) ( $s['loading_text'] ?? '' ) ),
					'done'    => __( 'All items are loaded.', 'uncoder' ),
					'error'   => __( 'Could not load more items. Please try again.', 'uncoder' ),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$tid = absint( $s['loop_template'] ?? 0 );
		if ( ! $tid ) {
			if ( $ctx->editor ) {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in placeholder().
				echo $this->placeholder(
					__( 'Choose a loop item template', 'uncoder' ),
					__( 'Create a Loop item template in Theme Builder, design the card with dynamic tags (featured image, post title, excerpt, read more), publish it, then pick it in Layout → Loop item template.', 'uncoder' ),
					Template_Embed::theme_builder_url(),
					__( 'Open Theme Builder', 'uncoder' )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return;
		}

		$found = Template_Embed::document( $tid, self::TEMPLATE_TYPE, $ctx );
		if ( null === $found['doc'] || Template_Embed::would_recurse( $tid, $ctx ) ) {
			if ( $ctx->editor ) {
				$messages = array(
					'missing' => __( 'The selected loop item template no longer exists. Choose another one.', 'uncoder' ),
					/* translators: %d: template id. */
					'type'    => sprintf( __( 'Template #%d is not a Loop item template. Choose a template of type “Loop item”.', 'uncoder' ), $tid ),
					'status'  => __( 'The selected loop item template is not published.', 'uncoder' ),
				);
				$text     = $messages[ $found['error'] ] ?? __( 'This loop item template is already shown here: a loop grid cannot display the template it is placed in.', 'uncoder' );
				echo $this->placeholder( __( 'Loop Grid', 'uncoder' ), $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in placeholder().
			}
			return;
		}
		$template = $found['doc'];

		$alt    = null;
		$alt_id = absint( $s['alternate_template'] ?? 0 );
		if ( $alt_id && $alt_id !== $tid && ! Template_Embed::would_recurse( $alt_id, $ctx ) ) {
			$alt = Template_Embed::document( $alt_id, self::TEMPLATE_TYPE, $ctx )['doc'];
		}

		$mode = $this->mode( $s );
		if ( ! $ctx->editor ) {
			// Normally already in <head> through the page's document references; late for other contexts.
			Assets::enqueue_late( $template );
			if ( $alt ) {
				Assets::enqueue_late( $alt );
			}
		}

		$result = $this->query( $s, $ctx );
		$items  = Template_Embed::guard(
			array_filter( array( $tid, $alt ? $alt->id() : 0 ) ),
			fn() => $this->items( $result, $template, $alt, $s, $ctx )
		);

		$classes = array( 'uncoder-loop-grid' );
		if ( 'none' !== $mode ) {
			$classes[] = 'uncoder-loop-grid--' . str_replace( '_', '-', $mode );
		}
		if ( ! empty( $s['masonry'] ) ) {
			$classes[] = 'uncoder-loop-grid--masonry';
			if ( 'columns' === ( $s['masonry_order'] ?? '' ) ) {
				$classes[] = 'uncoder-loop-grid--masonry-columns';
			}
		} elseif ( ! empty( $s['equal_height'] ) ) {
			$classes[] = 'uncoder-loop-grid--equal';
		}

		$css  = $ctx->editor ? Template_Embed::editor_css( $template ) . ( $alt ? Template_Embed::editor_css( $alt ) : '' ) : '';
		$note = '';
		if ( $ctx->editor && 'publish' !== get_post_status( $tid ) ) {
			$note = '<p class="uncoder-loop-grid__note">' . esc_html__( 'This loop item template is not published yet: the cards only show on the live site once it is.', 'uncoder' ) . '</p>';
		}

		if ( 0 === $items['count'] ) {
			$message = trim( (string) ( $s['nothing_found'] ?? '' ) );
			if ( '' === $message && $ctx->editor ) {
				$message = __( 'No posts match this query.', 'uncoder' );
			}
			if ( '' !== $message ) {
				echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $css . $note . '<p class="uncoder-loop-grid__empty">' . esc_html( $message ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped stylesheet tag and note.
			}
			return;
		}

		$grid_id = $this->grid_id( $ctx );
		$grid    = array(
			'class' => 'uncoder-loop-grid__grid',
			'id'    => $grid_id,
		);
		if ( self::is_load_mode( $mode ) ) {
			/* translators: %s: number of items. */
			$grid['data-loaded'] = sprintf( _n( '%s more item loaded', '%s more items loaded', $items['count'], 'uncoder' ), number_format_i18n( $items['count'] ) );
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		echo $css . $note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped stylesheet tag and note.
		echo '<div' . Utils::attrs( $grid ) . '>' . $items['html'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cards come from the escaping renderer.
		echo $this->navigation( $s, $result, $ctx, $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped button / core paginate_links() markup.
		if ( self::is_load_mode( $mode ) ) {
			echo '<p class="uncoder-sr-only uncoder-loop-grid__status" role="status" aria-live="polite" aria-atomic="true"></p>';
		}
		echo '</div>';
	}
}
