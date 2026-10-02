<?php
/**
 * Posts widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Site\Loop_Filters;
use Uncoder\Builder\Controls\Groups\Query;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Grid, list or cards of posts from a custom query or the current archive query, with pagination.
 */
class Posts extends Widget_Base {

	/** Query arg used to paginate custom queries. */
	public const PAGE_ARG = 'uncoder_page';

	public const META_ITEMS = array( 'author', 'date', 'comments', 'categories', 'reading-time' );

	/** @var int[] Posts already shown on this page (for "avoid duplicates"). */
	private static array $shown = array();

	public function name(): string {
		return 'posts';
	}

	public function title(): string {
		return __( 'Posts', 'uncoder' );
	}

	public function icon(): string {
		return 'layout-grid';
	}

	public function category(): string {
		return 'content';
	}

	public function keywords(): array {
		return array( 'posts', 'blog', 'grid', 'loop', 'archive', 'articles', 'news', 'query' );
	}

	public function description(): string {
		return __( 'A grid, list or cards of posts (image, title, meta, excerpt, read more) from a custom query or the current archive, with pagination. Use source "current" in archive and search templates.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
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
				'ai'      => 'Archive/search templates: {"source":"current"}. Otherwise a custom query, e.g. {"source":"posts","post_type":"post","posts_per_page":3}.',
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

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'cards',
				'options' => array(
					'grid'  => __( 'Grid', 'uncoder' ),
					'cards' => __( 'Cards', 'uncoder' ),
					'list'  => __( 'List (image beside text)', 'uncoder' ),
				),
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'type'        => 'number',
				'label'       => __( 'Columns', 'uncoder' ),
				'description' => __( 'Empty fits as many columns as the space allows.', 'uncoder' ),
				'min'         => 1,
				'max'         => 6,
				'selectors'   => array( '{{WRAPPER}} .uncoder-posts__grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr))' ),
			)
		);
		$this->add_control(
			'card_link',
			array(
				'type'        => 'switch',
				'label'       => __( 'Whole card is clickable', 'uncoder' ),
				'description' => __( 'Extends the title link over the card.', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_parts', array( 'label' => __( 'Card content', 'uncoder' ) ) );
		$this->add_control(
			'show_image',
			array(
				'type'    => 'switch',
				'label'   => __( 'Image', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'image_size',
			array(
				'type'            => 'select',
				'label'           => __( 'Image resolution', 'uncoder' ),
				'default'         => 'medium_large',
				'options_dynamic' => true,
				'options'         => Theme_Context::image_sizes(),
				'condition'       => array( 'show_image' => true ),
			)
		);
		$this->add_responsive_control(
			'image_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Image ratio', 'uncoder' ),
				'options'   => array(
					''     => __( 'Default (3:2)', 'uncoder' ),
					'1/1'  => '1:1',
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'3/4'  => '3:4',
					'4/5'  => '4:5',
					'auto' => __( 'Original', 'uncoder' ),
				),
				'condition' => array( 'show_image' => true ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-posts-ratio: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'fallback_image',
			array(
				'type'        => 'media',
				'label'       => __( 'Fallback image', 'uncoder' ),
				'description' => __( 'For posts without a featured image.', 'uncoder' ),
				'default'     => array( 'id' => 0, 'url' => '' ),
				'condition'   => array( 'show_image' => true ),
			)
		);
		$this->add_control(
			'show_badge',
			array(
				'type'  => 'switch',
				'label' => __( 'Term badge on the image', 'uncoder' ),
			)
		);
		$this->add_control(
			'badge_taxonomy',
			array(
				'type'            => 'select',
				'label'           => __( 'Badge taxonomy', 'uncoder' ),
				'default'         => 'category',
				'options_dynamic' => true,
				'options'         => Theme_Context::taxonomies(),
				'condition'       => array( 'show_badge' => true ),
			)
		);
		$this->add_control(
			'show_title',
			array(
				'type'    => 'switch',
				'label'   => __( 'Title', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'      => 'select',
				'label'     => __( 'Title HTML tag', 'uncoder' ),
				'default'   => 'h3',
				'options'   => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
				'condition' => array( 'show_title' => true ),
			)
		);
		$this->add_control(
			'show_meta',
			array(
				'type'    => 'switch',
				'label'   => __( 'Meta data', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'meta_items',
			array(
				'type'      => 'multiselect',
				'label'     => __( 'Meta items', 'uncoder' ),
				'default'   => array( 'date', 'author' ),
				'options'   => array(
					'author'       => __( 'Author', 'uncoder' ),
					'date'         => __( 'Date', 'uncoder' ),
					'comments'     => __( 'Comments', 'uncoder' ),
					'categories'   => __( 'Categories', 'uncoder' ),
					'reading-time' => __( 'Reading time', 'uncoder' ),
				),
				'condition' => array( 'show_meta' => true ),
			)
		);
		$this->add_control(
			'meta_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Meta position', 'uncoder' ),
				'default'   => 'above',
				'options'   => array(
					'above' => __( 'Above the title', 'uncoder' ),
					'below' => __( 'Below the title', 'uncoder' ),
				),
				'condition' => array( 'show_meta' => true ),
			)
		);
		$this->add_control(
			'meta_separator',
			array(
				'type'      => 'text',
				'label'     => __( 'Meta separator', 'uncoder' ),
				'default'   => '·',
				'condition' => array( 'show_meta' => true ),
			)
		);
		$this->add_control(
			'show_excerpt',
			array(
				'type'    => 'switch',
				'label'   => __( 'Excerpt', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'excerpt_length',
			array(
				'type'      => 'number',
				'label'     => __( 'Excerpt length (words)', 'uncoder' ),
				'default'   => 20,
				'min'       => 1,
				'max'       => 120,
				'condition' => array( 'show_excerpt' => true ),
			)
		);
		$this->add_control(
			'show_read_more',
			array(
				'type'    => 'switch',
				'label'   => __( 'Read more link', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'read_more_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Read more text', 'uncoder' ),
				'default'   => __( 'Read more', 'uncoder' ),
				'condition' => array( 'show_read_more' => true ),
			)
		);
		$this->add_control(
			'read_more_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Read more icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'arrow-right' ),
				'condition' => array( 'show_read_more' => true ),
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
				'selectors'            => array( '{{WRAPPER}} .uncoder-posts__pagination' => 'justify-content: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->register_style_controls();
	}

	private function register_style_controls(): void {
		$this->start_section( 'style_layout', array( 'label' => __( 'Layout', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'column_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Column gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__grid' => 'column-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Row gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__grid' => 'row-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'list_image_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Image width (list)', 'uncoder' ),
				'size_units' => array( '%', 'px' ),
				'condition'  => array( 'layout' => 'list' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-posts-media-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_effect',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover effect', 'uncoder' ),
				'default' => 'zoom',
				'options' => array(
					''          => __( 'None', 'uncoder' ),
					'zoom'      => __( 'Zoom image', 'uncoder' ),
					'lift'      => __( 'Lift card', 'uncoder' ),
					'lift-zoom' => __( 'Lift card and zoom image', 'uncoder' ),
				),
			)
		);
		$this->end_section();

		$this->start_section( 'style_card', array( 'label' => __( 'Card', 'uncoder' ), 'tab' => 'style' ) );
		$this->start_tabs( 'card_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_group( 'card_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__item' ) );
		$this->add_group( 'card_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__item' ) );
		$this->add_group( 'card_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__item' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'card_hover_border',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__item:hover' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'card_hover_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__item:hover' ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'card_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__item' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Content padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__body' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Text alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__body' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_image',
			array(
				'label'     => __( 'Image', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_image' => true ),
			)
		);
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__media' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'image_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-posts-media-gap: {{VALUE}}' ),
			)
		);
		$this->add_group( 'image_filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__img' ) );
		$this->end_section();

		$this->start_section(
			'style_badge',
			array(
				'label'     => __( 'Badge', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_badge' => true ),
			)
		);
		$this->add_group( 'badge_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__badge' ) );
		$this->add_control(
			'badge_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__badge' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'badge_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__badge' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'badge_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__badge' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_title',
			array(
				'label'     => __( 'Title', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_title' => true ),
			)
		);
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'title_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__item:hover .uncoder-posts__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__title' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_meta',
			array(
				'label'     => __( 'Meta data', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_meta' => true ),
			)
		);
		$this->add_group( 'meta_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__meta' ) );
		$this->add_control(
			'meta_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__meta' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'meta_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__meta' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_excerpt',
			array(
				'label'     => __( 'Excerpt', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_excerpt' => true ),
			)
		);
		$this->add_group( 'excerpt_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__excerpt' ) );
		$this->add_control(
			'excerpt_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__excerpt' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'excerpt_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__excerpt' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_more',
			array(
				'label'     => __( 'Read more', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_read_more' => true ),
			)
		);
		$this->add_group( 'more_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__more' ) );
		$this->add_control(
			'more_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__more' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'more_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__more:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_pagination',
			array(
				'label'     => __( 'Pagination', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'pagination!' => 'none' ),
			)
		);
		$this->add_group( 'pagination_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__pagination' ) );
		$this->start_tabs( 'pagination_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'pagination_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__pagination .page-numbers' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pagination_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__pagination .page-numbers' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'pagination_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__pagination a.page-numbers:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pagination_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__pagination a.page-numbers:hover' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Active', 'uncoder' ) );
		$this->add_control(
			'pagination_active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__pagination .page-numbers.current' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pagination_active_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__pagination .page-numbers.current' => 'background-color: {{VALUE}}' ),
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
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__pagination .page-numbers' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'pagination_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Distance from posts', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__pagination' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pagination_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-posts__pagination' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_empty', array( 'label' => __( 'Nothing found message', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'empty_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-posts__empty' ) );
		$this->add_control(
			'empty_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-posts__empty' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/* ------------------------------------------------------------------ Query */

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		// Loop Filter widgets find their grid by this key (the CSS ID, or "loop").
		return array( 'data-uncoder-filter-key' => Loop_Filters::key( $s ) );
	}

	/**
	 * Runs the query for the settings.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array{query:\WP_Query, main:bool, paged:int, max:int}
	 */
	private function query( array $s, Render_Context $ctx ): array {
		$q        = is_array( $s['query'] ?? null ) ? $s['query'] : array();
		$paginate = 'none' !== ( $s['pagination'] ?? 'none' );
		$current  = Theme_Context::post( $ctx );

		if ( 'current' === ( $q['source'] ?? 'posts' ) ) {
			global $wp_query;
			if ( ! $ctx->editor && $wp_query instanceof \WP_Query ) {
				return array(
					'query' => $wp_query,
					'main'  => true,
					'paged' => max( 1, (int) get_query_var( 'paged' ) ),
					'max'   => (int) $wp_query->max_num_pages,
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
		$paged = 1;
		if ( $paginate ) {
			// Custom queries paginate with their own query arg so they never clash with the main query.
			$paged = max( 1, absint( filter_input( INPUT_GET, self::PAGE_ARG, FILTER_SANITIZE_NUMBER_INT ) ) );
			$per   = (int) ( $args['posts_per_page'] ?? 6 );
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
			$per    = max( 1, (int) ( $args['posts_per_page'] ?? 6 ) );
			$offset = (int) ( $q['offset'] ?? 0 );
			$max    = (int) ceil( max( 0, (int) $query->found_posts - $offset ) / $per );
		}
		return array(
			'query' => $query,
			'main'  => false,
			'paged' => $paged,
			'max'   => $max,
		);
	}

	/* ------------------------------------------------------------------ Parts */

	/**
	 * Meta line of a card.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function meta( array $s, ?\WP_Post $post ): string {
		$items = array_values( array_intersect( (array) ( $s['meta_items'] ?? array() ), self::META_ITEMS ) );
		$parts = array();
		$demo  = Theme_Context::sample();
		foreach ( $items as $item ) {
			switch ( $item ) {
				case 'author':
					$parts[] = esc_html( $post ? (string) get_the_author_meta( 'display_name', (int) $post->post_author ) : $demo['author'] );
					break;
				case 'date':
					$parts[] = '<time datetime="' . esc_attr( $post ? (string) get_the_date( 'c', $post ) : wp_date( 'c' ) ) . '">' . esc_html( Theme_Context::post_date( $post, '' ) ) . '</time>';
					break;
				case 'comments':
					$count = $post ? (int) get_comments_number( $post ) : 3;
					/* translators: %s: number of comments. */
					$parts[] = esc_html( sprintf( _n( '%s comment', '%s comments', $count, 'uncoder' ), number_format_i18n( $count ) ) );
					break;
				case 'categories':
					$terms   = $post ? get_the_terms( $post, 'category' ) : false;
					$names   = is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : ( $post ? array() : array( $demo['category'] ) );
					$parts[] = $names ? esc_html( implode( ', ', $names ) ) : '';
					break;
				case 'reading-time':
					$minutes = $post ? Theme_Context::reading_minutes( $post ) : 4;
					/* translators: %s: number of minutes. */
					$parts[] = esc_html( sprintf( _n( '%s min read', '%s min read', $minutes, 'uncoder' ), number_format_i18n( $minutes ) ) );
					break;
			}
		}
		$parts = array_filter( $parts, static fn( $p ) => '' !== $p );
		if ( ! $parts ) {
			return '';
		}
		$sep = trim( (string) ( $s['meta_separator'] ?? '·' ) );
		$sep = '' !== $sep ? '<span class="uncoder-posts__meta-sep" aria-hidden="true">' . esc_html( $sep ) . '</span>' : ' ';
		return '<div class="uncoder-posts__meta"><span class="uncoder-posts__meta-item">' . implode( '</span>' . $sep . '<span class="uncoder-posts__meta-item">', $parts ) . '</span></div>';
	}

	/**
	 * One post card. $post null renders a sample card (editor only).
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function card( array $s, ?\WP_Post $post, int $index = 0 ): string {
		$demo  = Theme_Context::sample();
		$url   = $post ? (string) get_permalink( $post ) : '#';
		$title = $post ? wp_kses( get_the_title( $post ), Utils::kses_inline() ) : esc_html( $demo['title'] );
		$plain = wp_strip_all_tags( $title );
		$html  = '';

		if ( ! empty( $s['show_image'] ) ) {
			$size = sanitize_key( (string) ( $s['image_size'] ?? 'medium_large' ) );
			$size = '' !== $size ? $size : 'medium_large';
			$img  = '';
			if ( $post && has_post_thumbnail( $post ) ) {
				$img = (string) get_the_post_thumbnail(
					$post,
					$size,
					array(
						'class'    => 'uncoder-posts__img',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
			}
			if ( '' === $img ) {
				$img = $this->image( $s['fallback_image'] ?? array(), $size, array( 'class' => 'uncoder-posts__img' ) );
			}
			if ( '' === $img && ! $post ) {
				$img = '<img class="uncoder-posts__img uncoder-posts__img--placeholder" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
			}
			$badge = '';
			if ( ! empty( $s['show_badge'] ) ) {
				$tax  = sanitize_key( (string) ( $s['badge_taxonomy'] ?? 'category' ) );
				$name = '';
				if ( ! $post ) {
					$name = $demo['category'];
				} elseif ( taxonomy_exists( $tax ) && is_taxonomy_viewable( $tax ) ) {
					$terms = get_the_terms( $post, $tax );
					$name  = is_array( $terms ) && $terms ? $terms[0]->name : '';
				}
				$badge = '' !== $name ? '<span class="uncoder-posts__badge">' . esc_html( $name ) . '</span>' : '';
			}
			if ( '' !== $img ) {
				// The title link carries the name; the image link is a duplicate for pointer users only.
				$html .= '<a class="uncoder-posts__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $img . $badge . '</a>';
			}
		}

		$body = '';
		$meta = ! empty( $s['show_meta'] ) ? $this->meta( $s, $post ) : '';
		if ( 'above' === ( $s['meta_position'] ?? 'above' ) ) {
			$body .= $meta;
		}
		if ( ! empty( $s['show_title'] ) ) {
			$tag   = Utils::tag( $s['title_tag'] ?? 'h3', Utils::HEADING_TAGS, 'h3' );
			$body .= '<' . $tag . ' class="uncoder-posts__title"><a class="uncoder-posts__title-link" href="' . esc_url( $url ) . '">' . $title . '</a></' . $tag . '>';
		}
		if ( 'above' !== ( $s['meta_position'] ?? 'above' ) ) {
			$body .= $meta;
		}
		if ( ! empty( $s['show_excerpt'] ) ) {
			$text = $post ? ( post_password_required( $post ) ? '' : Post_Excerpt::text( $post ) ) : $demo['excerpt'];
			if ( '' !== $text ) {
				$body .= '<p class="uncoder-posts__excerpt">' . esc_html( wptexturize( wp_trim_words( $text, max( 1, (int) ( $s['excerpt_length'] ?? 20 ) ), '…' ) ) ) . '</p>';
			}
		}
		if ( ! empty( $s['show_read_more'] ) ) {
			$more  = trim( (string) ( $s['read_more_text'] ?? '' ) );
			$more  = '' !== $more ? $more : __( 'Read more', 'uncoder' );
			$icon  = $this->has_icon( $s['read_more_icon'] ?? null ) ? $this->render_icon( $s['read_more_icon'], array( 'class' => 'uncoder-posts__more-icon' ) ) : '';
			$body .= '<div class="uncoder-posts__more-wrap"><a class="uncoder-posts__more" href="' . esc_url( $url ) . '">' . esc_html( $more ) . '<span class="uncoder-sr-only">: ' . esc_html( $plain ) . '</span>' . $icon . '</a></div>';
		}

		$classes = array( 'uncoder-posts__item' );
		if ( $post ) {
			$classes[] = 'uncoder-posts__item--' . sanitize_html_class( $post->post_type );
			if ( ! has_post_thumbnail( $post ) ) {
				$classes[] = 'uncoder-posts__item--no-image';
			}
		} else {
			$classes[] = 'uncoder-posts__item--sample-' . ( $index + 1 );
		}
		return '<article class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $html . '<div class="uncoder-posts__body">' . $body . '</div></article>';
	}

	/**
	 * Pagination markup.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function pagination( array $s, bool $main, int $paged, int $max, bool $editor ): string {
		$mode = (string) ( $s['pagination'] ?? 'none' );
		if ( 'none' === $mode || $max < 2 ) {
			return '';
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
		if ( $editor ) {
			$args['base']   = '#%#%';
			$args['format'] = '';
		} elseif ( ! $main ) {
			$args['base']   = add_query_arg( self::PAGE_ARG, '%#%' );
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
		return '<nav class="uncoder-posts__pagination" aria-label="' . esc_attr__( 'Posts pagination', 'uncoder' ) . '">' . implode( '', $links ) . '</nav>';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$result = $this->query( $s, $ctx );
		$query  = $result['query'];
		$cards  = '';
		$index  = 0;

		if ( $query->have_posts() ) {
			$previous = $GLOBALS['post'] ?? null;
			while ( $query->have_posts() ) {
				$query->the_post();
				$post = get_post();
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}
				self::$shown[] = (int) $post->ID;
				$cards        .= $this->card( $s, $post, $index++ );
			}
			// Restore the surrounding post (have_posts() already rewound the query).
			$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $previous instanceof \WP_Post ) {
				setup_postdata( $previous );
			}
		}

		$layout  = in_array( $s['layout'] ?? 'cards', array( 'grid', 'cards', 'list' ), true ) ? $s['layout'] : 'cards';
		$hover   = in_array( $s['hover_effect'] ?? 'zoom', array( 'zoom', 'lift', 'lift-zoom' ), true ) ? $s['hover_effect'] : '';
		$classes = array( 'uncoder-posts', 'uncoder-posts--' . $layout );
		if ( '' !== $hover ) {
			$classes[] = 'uncoder-posts--hover-' . $hover;
		}
		if ( ! empty( $s['card_link'] ) ) {
			$classes[] = 'uncoder-posts--card-link';
		}

		if ( '' === $cards ) {
			if ( $ctx->editor ) {
				for ( $i = 0; $i < 3; $i++ ) {
					$cards .= $this->card( $s, null, $i );
				}
			} else {
				$message = trim( (string) ( $s['nothing_found'] ?? '' ) );
				if ( '' !== $message ) {
					echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><p class="uncoder-posts__empty">' . esc_html( $message ) . '</p></div>';
				}
				return;
			}
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		echo '<div class="uncoder-posts__grid">' . $cards . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped card parts.
		echo $this->pagination( $s, $result['main'], $result['paged'], $result['max'], $ctx->editor ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core paginate_links() markup.
		echo '</div>';
	}
}
