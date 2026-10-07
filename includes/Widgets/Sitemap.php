<?php
/**
 * Sitemap widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * An HTML sitemap: columns of links to the site's published pages, posts, custom post types and terms.
 */
class Sitemap extends Widget_Base {

	/** Most items one section lists (keeps huge blogs from printing thousands of links). */
	public const MAX_ITEMS = 1000;

	public const TITLE_TAGS = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' );

	public function name(): string {
		return 'sitemap';
	}

	public function title(): string {
		return __( 'Sitemap', 'uncoder' );
	}

	public function icon(): string {
		return 'network';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'sitemap', 'site map', 'pages', 'index', 'archive', 'categories', 'tags', 'links', 'directory' );
	}

	public function description(): string {
		return __( 'An HTML sitemap: columns listing the site\'s published pages (as a tree), posts, custom post types, categories, tags or other taxonomies, straight from WordPress. Use it on a Sitemap page or a 404 page; it updates itself as content is added.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	public function preset(): array {
		return array(
			'sections'       => array(
				array(
					'_id'       => 'smap001',
					'title'     => __( 'Pages', 'uncoder' ),
					'source'    => 'post_type',
					'post_type' => 'page',
				),
				array(
					'_id'       => 'smap002',
					'title'     => __( 'Latest posts', 'uncoder' ),
					'source'    => 'post_type',
					'post_type' => 'post',
					'orderby'   => 'date',
					'order'     => 'desc',
					'limit'     => 10,
				),
				array(
					'_id'        => 'smap003',
					'title'      => __( 'Categories', 'uncoder' ),
					'source'     => 'taxonomy',
					'taxonomy'   => 'category',
					'show_count' => true,
				),
			),
			'columns'        => '3',
			'columns_tablet' => '2',
			'columns_mobile' => '1',
		);
	}

	/**
	 * Public post types as options.
	 *
	 * @return array<string,string>
	 */
	private static function post_types(): array {
		$out = array(
			'page' => __( 'Pages', 'uncoder' ),
			'post' => __( 'Posts', 'uncoder' ),
		);
		if ( did_action( 'init' ) ) {
			foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
				if ( 'attachment' !== $type->name ) {
					$out[ $type->name ] = $type->labels->name;
				}
			}
		}
		return $out;
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Sitemap', 'uncoder' ) ) );
		$this->add_control(
			'sections',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Sections', 'uncoder' ),
				'title_field' => 'title',
				'fields'      => array(
					'title'      => array(
						'type'        => 'text',
						'label'       => __( 'Title', 'uncoder' ),
						'placeholder' => __( 'Empty: the content type\'s name', 'uncoder' ),
					),
					'source'     => array(
						'type'    => 'select',
						'label'   => __( 'List', 'uncoder' ),
						'default' => 'post_type',
						'options' => array(
							'post_type' => __( 'Post type (pages, posts…)', 'uncoder' ),
							'taxonomy'  => __( 'Taxonomy (categories, tags…)', 'uncoder' ),
						),
					),
					'post_type'  => array(
						'type'            => 'select',
						'label'           => __( 'Post type', 'uncoder' ),
						'default'         => 'page',
						'options_dynamic' => true,
						'options'         => self::post_types(),
						'condition'       => array( 'source' => 'post_type' ),
					),
					'taxonomy'   => array(
						'type'            => 'select',
						'label'           => __( 'Taxonomy', 'uncoder' ),
						'default'         => 'category',
						'options_dynamic' => true,
						'options'         => Theme_Context::taxonomies(),
						'condition'       => array( 'source' => 'taxonomy' ),
					),
					'orderby'    => array(
						'type'    => 'select',
						'label'   => __( 'Order by', 'uncoder' ),
						'default' => 'title',
						'options' => array(
							'title'      => __( 'Title / name', 'uncoder' ),
							'date'       => __( 'Date (post types)', 'uncoder' ),
							'modified'   => __( 'Last modified (post types)', 'uncoder' ),
							'menu_order' => __( 'Page order (post types)', 'uncoder' ),
							'count'      => __( 'Number of posts (taxonomies)', 'uncoder' ),
							'id'         => __( 'ID', 'uncoder' ),
						),
					),
					'order'      => array(
						'type'    => 'select',
						'label'   => __( 'Order', 'uncoder' ),
						'default' => 'asc',
						'options' => array(
							'asc'  => __( 'Ascending', 'uncoder' ),
							'desc' => __( 'Descending', 'uncoder' ),
						),
					),
					'depth'      => array(
						'type'        => 'number',
						'label'       => __( 'Depth', 'uncoder' ),
						'default'     => 0,
						'min'         => 0,
						'max'         => 10,
						'description' => __( 'For hierarchical types (pages, categories): 0 shows every level, 1 only the top level.', 'uncoder' ),
					),
					'limit'      => array(
						'type'        => 'number',
						'label'       => __( 'Maximum items', 'uncoder' ),
						'min'         => 1,
						'max'         => self::MAX_ITEMS,
						'placeholder' => __( 'All', 'uncoder' ),
					),
					'exclude'    => array(
						'type'        => 'text',
						'label'       => __( 'Exclude IDs', 'uncoder' ),
						'placeholder' => '12, 34',
						'description' => __( 'Comma-separated post or term IDs. Excluded items hide their children too.', 'uncoder' ),
					),
					'hide_empty' => array(
						'type'      => 'switch',
						'label'     => __( 'Hide empty terms', 'uncoder' ),
						'default'   => true,
						'condition' => array( 'source' => 'taxonomy' ),
					),
					'show_count' => array(
						'type'      => 'switch',
						'label'     => __( 'Show post count', 'uncoder' ),
						'condition' => array( 'source' => 'taxonomy' ),
					),
				),
				'default'     => array(
					array(
						'_id'       => 'smap001',
						'title'     => __( 'Pages', 'uncoder' ),
						'source'    => 'post_type',
						'post_type' => 'page',
					),
				),
				'ai'          => 'One row per column: {"source":"post_type","post_type":"page","title":"Pages"} (hierarchical tree), {"source":"post_type","post_type":"post","orderby":"date","order":"desc","limit":20}, {"source":"taxonomy","taxonomy":"category","show_count":true}. Only published, public content is listed; an empty title uses the content type name.',
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'type'        => 'select',
				'label'       => __( 'Columns', 'uncoder' ),
				'options'     => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'description' => __( 'Empty fits as many columns as the width allows.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr))' ),
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'h3',
				'options' => array_combine( self::TITLE_TAGS, array_map( 'strtoupper', self::TITLE_TAGS ) ),
			)
		);
		$this->add_control(
			'nofollow',
			array(
				'type'        => 'switch',
				'label'       => __( 'Add rel="nofollow" to links', 'uncoder' ),
				'description' => __( 'Usually off: a sitemap helps search engines find your pages.', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_layout', array( 'label' => __( 'Layout', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'column_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Column gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'column-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Row gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'row-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Section titles', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-sitemap__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-sitemap__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-sitemap__title' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'title_divider',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider line', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-sitemap__title' => 'padding-bottom: 0.5em; border-bottom: 1px solid {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_list', array( 'label' => __( 'List', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'list_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-sitemap__list' ) );
		$this->start_tabs( 'link_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-sitemap__link' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-sitemap__link:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_control(
			'bullet',
			array(
				'type'                 => 'select',
				'label'                => __( 'Bullet', 'uncoder' ),
				'options'              => array(
					''        => __( 'Default (disc)', 'uncoder' ),
					'circle'  => __( 'Circle', 'uncoder' ),
					'square'  => __( 'Square', 'uncoder' ),
					'dash'    => __( 'Dash', 'uncoder' ),
					'chevron' => __( 'Chevron', 'uncoder' ),
					'none'    => __( 'None', 'uncoder' ),
				),
				'selectors_dictionary' => array(
					'circle'  => '--uncoder-sitemap-bullet:circle',
					'square'  => '--uncoder-sitemap-bullet:square',
					'dash'    => '--uncoder-sitemap-bullet:"–  "',
					'chevron' => '--uncoder-sitemap-bullet:"›  "',
					'none'    => '--uncoder-sitemap-bullet:none;--uncoder-sitemap-indent-top:0',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'bullet_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Bullet color', 'uncoder' ),
				'condition' => array( 'bullet!' => 'none' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-sitemap__item::marker' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'indent',
			array(
				'type'       => 'slider',
				'label'      => __( 'Indent', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-sitemap-indent: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'item_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between items', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-sitemap-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'count_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Count color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-sitemap__count' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * @return int[]
	 */
	private static function ids( $raw ): array {
		$ids = array_map( 'absint', preg_split( '/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY ) ?: array() );
		return array_values( array_filter( array_unique( $ids ) ) );
	}

	/**
	 * Nodes (flat list with parent ids) of one section, or null when the source is not public.
	 *
	 * @param array<string,mixed> $row Section row.
	 * @return array{label:string, nodes: array<int, array{id:int, parent:int, title:string, url:string, count:int|null, current:bool}>, hierarchical: bool}|null
	 */
	private function collect( array $row ): ?array {
		$limit   = is_numeric( $row['limit'] ?? '' ) ? max( 1, min( self::MAX_ITEMS, (int) $row['limit'] ) ) : self::MAX_ITEMS;
		$order   = 'desc' === ( $row['order'] ?? 'asc' ) ? 'DESC' : 'ASC';
		$orderby = (string) ( $row['orderby'] ?? 'title' );
		$depth   = max( 0, (int) ( $row['depth'] ?? 0 ) );
		$exclude = self::ids( $row['exclude'] ?? '' );
		$nodes   = array();

		if ( 'taxonomy' === ( $row['source'] ?? 'post_type' ) ) {
			$tax = sanitize_key( (string) ( $row['taxonomy'] ?? 'category' ) );
			$obj = get_taxonomy( $tax );
			if ( ! $obj || ! $obj->public || ! is_taxonomy_viewable( $tax ) ) {
				return null;
			}
			$hier = is_taxonomy_hierarchical( $tax );
			$args = array(
				'taxonomy'               => $tax,
				'hide_empty'             => ! empty( $row['hide_empty'] ),
				'orderby'                => array(
					'count' => 'count',
					'id'    => 'term_id',
				)[ $orderby ] ?? 'name',
				'order'                  => $order,
				'number'                 => $limit,
				'update_term_meta_cache' => false,
			);
			if ( $hier && 1 === $depth ) {
				$args['parent'] = 0;
			}
			if ( ! $hier && $exclude ) {
				$args['exclude'] = $exclude;
			}
			$terms   = get_terms( $args );
			$current = ( is_category() || is_tag() || is_tax() ) ? (int) get_queried_object_id() : 0;
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				$url = get_term_link( $term );
				if ( ! is_string( $url ) ) {
					continue;
				}
				$nodes[] = array(
					'id'      => (int) $term->term_id,
					'parent'  => $hier ? (int) $term->parent : 0,
					'title'   => wp_strip_all_tags( $term->name ),
					'url'     => $url,
					'count'   => ! empty( $row['show_count'] ) ? (int) $term->count : null,
					'current' => $current === (int) $term->term_id,
				);
			}
			return array(
				'label'        => (string) $obj->labels->name,
				'nodes'        => $nodes,
				'hierarchical' => $hier,
				'exclude'      => $hier ? $exclude : array(),
			);
		}

		$type = sanitize_key( (string) ( $row['post_type'] ?? 'page' ) );
		$obj  = get_post_type_object( $type );
		if ( ! $obj || ! $obj->public || 'attachment' === $type || ! is_post_type_viewable( $obj ) ) {
			return null;
		}
		$hier = is_post_type_hierarchical( $type );
		$map  = array(
			'date'       => 'date',
			'modified'   => 'modified',
			'menu_order' => 'menu_order',
			'id'         => 'ID',
		);
		$by   = $map[ $orderby ] ?? 'title';
		$args = array(
			'post_type'              => $type,
			'post_status'            => 'publish',
			'has_password'           => false,
			'posts_per_page'         => $limit,
			'orderby'                => 'menu_order' === $by ? array( 'menu_order' => $order, 'title' => 'ASC' ) : $by,
			'order'                  => $order,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		);
		if ( $hier && 1 === $depth ) {
			$args['post_parent'] = 0;
		}
		if ( ! $hier && $exclude ) {
			$args['post__not_in'] = $exclude;
		}
		$query   = new \WP_Query( $args );
		$current = is_singular() ? (int) get_queried_object_id() : 0;
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
			$nodes[] = array(
				'id'      => (int) $post->ID,
				'parent'  => $hier ? (int) $post->post_parent : 0,
				'title'   => '' !== $title ? $title : __( '(no title)', 'uncoder' ),
				'url'     => (string) get_permalink( $post ),
				'count'   => null,
				'current' => $current === (int) $post->ID,
			);
		}
		return array(
			'label'        => (string) $obj->labels->name,
			'nodes'        => $nodes,
			'hierarchical' => $hier,
			'exclude'      => $hier ? $exclude : array(),
		);
	}

	/**
	 * Nested list markup. Items whose parent is not listed (a draft, or beyond the limit) join the top level;
	 * excluded items drop with their children.
	 *
	 * @param array<int, array<string,mixed>> $nodes    Nodes.
	 * @param int[]                           $exclude  Excluded ids.
	 */
	private function tree_html( array $nodes, array $exclude, int $depth, bool $nofollow ): string {
		$by_id    = array();
		$children = array();
		foreach ( $nodes as $node ) {
			$by_id[ $node['id'] ] = $node;
		}
		$roots = array();
		foreach ( $nodes as $node ) {
			if ( $node['parent'] && isset( $by_id[ $node['parent'] ] ) ) {
				$children[ $node['parent'] ][] = $node['id'];
			} else {
				$roots[] = $node['id'];
			}
		}
		$skip = array_flip( $exclude );
		$walk = function ( array $ids, int $level ) use ( &$walk, $by_id, $children, $skip, $depth, $nofollow ): string {
			$items = '';
			foreach ( $ids as $id ) {
				if ( isset( $skip[ $id ] ) ) {
					continue;
				}
				$node  = $by_id[ $id ];
				$attrs = array(
					'class' => 'uncoder-sitemap__link',
					'href'  => $node['url'],
				);
				if ( $nofollow ) {
					$attrs['rel'] = 'nofollow';
				}
				if ( $node['current'] ) {
					$attrs['aria-current'] = 'page';
				}
				$sub = '';
				if ( ! empty( $children[ $id ] ) && ( 0 === $depth || $level < $depth ) ) {
					$sub = $walk( $children[ $id ], $level + 1 );
				}
				$items .= '<li class="uncoder-sitemap__item">'
					. '<a' . Utils::attrs( $attrs ) . '>' . esc_html( $node['title'] ) . '</a>'
					. ( null !== $node['count'] ? ' <span class="uncoder-sitemap__count">(' . esc_html( number_format_i18n( (int) $node['count'] ) ) . ')</span>' : '' )
					. $sub
					. '</li>';
			}
			if ( '' === $items ) {
				return '';
			}
			return '<ul class="uncoder-sitemap__list' . ( $level > 1 ? ' uncoder-sitemap__list--sub' : '' ) . '" role="list">' . $items . '</ul>';
		};
		return $walk( $roots, 1 );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = Repeater_Rows::get( $this, 'sections', $s['sections'] ?? array() );
		$tag  = Utils::tag( $s['title_tag'] ?? 'h3', self::TITLE_TAGS, 'h3' );
		$html = '';
		foreach ( $rows as $i => $row ) {
			$data = $this->collect( $row );
			if ( null === $data ) {
				if ( $ctx->editor ) {
					$html .= '<div class="uncoder-sitemap__section"><p class="uncoder-sitemap__placeholder">' . esc_html__( 'This content type is not public or no longer exists.', 'uncoder' ) . '</p></div>';
				}
				continue;
			}
			$list = $this->tree_html( $data['nodes'], $data['exclude'], max( 0, (int) ( $row['depth'] ?? 0 ) ), ! empty( $s['nofollow'] ) );
			if ( '' === $list ) {
				if ( ! $ctx->editor ) {
					continue;
				}
				$list = '<p class="uncoder-sitemap__placeholder">' . esc_html__( 'Nothing published here yet.', 'uncoder' ) . '</p>';
			}
			$title = trim( (string) ( $row['title'] ?? '' ) );
			$title = '' !== $title ? $title : $data['label'];
			$id    = isset( $row['_id'] ) ? sanitize_html_class( (string) $row['_id'] ) : '';
			$html .= '<div class="' . esc_attr( trim( 'uncoder-sitemap__section' . ( '' !== $id ? ' uncoder-ri-' . $id : '' ) ) ) . '">'
				. '<' . $tag . ' class="uncoder-sitemap__title"' . $ctx->inline( 'sections.' . $i . '.title' ) . '>' . esc_html( $title ) . '</' . $tag . '>'
				. $list
				. '</div>';
		}
		if ( '' === $html ) {
			if ( $ctx->editor ) {
				echo '<nav class="uncoder-sitemap"><p class="uncoder-sitemap__placeholder">' . esc_html__( 'Add a section (pages, posts or a taxonomy) in the Content tab.', 'uncoder' ) . '</p></nav>';
			}
			return;
		}
		echo '<nav class="uncoder-sitemap" aria-label="' . esc_attr__( 'Sitemap', 'uncoder' ) . '">' . $html . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
