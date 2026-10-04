<?php
/**
 * Container: the flexbox / grid layout element.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Elements;

use Uncoder\Builder\Core\Animated_Backgrounds;
use Uncoder\Builder\Core\Element_Base;
use Uncoder\Builder\Core\Shapes;

defined( 'ABSPATH' ) || exit;

/**
 * Nestable flex/grid box. Top-level containers default to "boxed" (content limited to the site
 * container width), nested containers default to "full".
 */
class Container extends Element_Base {

	public function name(): string {
		return 'container';
	}

	public function title(): string {
		return __( 'Container', 'uncoder' );
	}

	public function icon(): string {
		return 'square-dashed';
	}

	public function category(): string {
		return 'layout';
	}

	public function keywords(): array {
		return array( 'section', 'row', 'column', 'flex', 'grid', 'wrapper', 'box' );
	}

	public function description(): string {
		return __( 'Flexbox or CSS grid box that holds widgets and other containers. Use it for every section, row, column and card.', 'uncoder' );
	}

	public function is_container(): bool {
		return true;
	}

	protected function register_controls(): void {
		$this->start_section( 'layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_control(
			'layout',
			array(
				'type'    => 'choose',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'flex',
				'options' => array(
					'flex' => array( 'label' => __( 'Flexbox', 'uncoder' ), 'icon' => 'columns-3' ),
					'grid' => array( 'label' => __( 'Grid', 'uncoder' ), 'icon' => 'layout-grid' ),
				),
				'ai'      => 'flex for rows/columns/stacks; grid for card grids.',
			)
		);
		$this->add_control(
			'content_width',
			array(
				'type'    => 'choose',
				'label'   => __( 'Content width', 'uncoder' ),
				'options' => array(
					'boxed' => array( 'label' => __( 'Boxed', 'uncoder' ), 'icon' => 'square' ),
					'full'  => array( 'label' => __( 'Full width', 'uncoder' ), 'icon' => 'move-horizontal' ),
				),
				'ai'      => 'Omit to use the default: "boxed" for top-level sections (content centred at the site container width, side gutters), "full" for nested containers.',
			)
		);
		$this->add_responsive_control(
			'boxed_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Boxed width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'vw', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 300, 'max' => 2000 ) ),
				'condition'  => array( 'content_width!' => 'full' ), // Also the implicit default of top-level sections.
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-boxed-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( '%', 'px', 'vw', 'rem' ),
				'range'      => array( '%' => array( 'min' => 1, 'max' => 100 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-width: {{VALUE}}' ),
				'ai'         => 'Column width inside a row, e.g. 50%. Leave empty to share space equally.',
			)
		);
		$this->add_responsive_control(
			'min_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Min height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'svh', 'rem', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 1200 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'min-height: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'direction',
			array(
				'type'      => 'choose',
				'label'     => __( 'Direction', 'uncoder' ),
				'options'   => array(
					'row'            => array( 'label' => __( 'Row', 'uncoder' ), 'icon' => 'arrow-right' ),
					'column'         => array( 'label' => __( 'Column', 'uncoder' ), 'icon' => 'arrow-down' ),
					'row-reverse'    => array( 'label' => __( 'Row reversed', 'uncoder' ), 'icon' => 'arrow-left' ),
					'column-reverse' => array( 'label' => __( 'Column reversed', 'uncoder' ), 'icon' => 'arrow-up' ),
				),
				'condition' => array( 'layout' => 'flex' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-dir: {{VALUE}}' ),
				'ai'        => 'Default column. Use row for side-by-side columns and set direction_mobile:"column" to stack on phones.',
			)
		);
		$this->add_responsive_control(
			'grid_columns',
			array(
				'type'      => 'number',
				'label'     => __( 'Columns', 'uncoder' ),
				'min'       => 1,
				'max'       => 12,
				'condition' => array( 'layout' => 'grid' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-grid-cols: repeat({{VALUE}}, minmax(0, 1fr))' ),
				'ai'        => 'Equal columns. Set grid_columns_tablet / grid_columns_mobile for smaller screens.',
			)
		);
		$this->add_responsive_control(
			'grid_template',
			array(
				'type'        => 'text',
				'label'       => __( 'Custom columns', 'uncoder' ),
				'placeholder' => '2fr 1fr',
				'condition'   => array( 'layout' => 'grid' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-grid-cols: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'grid_rows',
			array(
				'type'      => 'number',
				'label'     => __( 'Rows', 'uncoder' ),
				'min'       => 1,
				'max'       => 12,
				'condition' => array( 'layout' => 'grid' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-grid-rows: repeat({{VALUE}}, auto)' ),
			)
		);
		// Framer grids often give every row the tallest row's height (a short review card keeps the gap below it).
		$this->add_responsive_control(
			'grid_row_sizing',
			array(
				'type'                 => 'select',
				'label'                => __( 'Row heights', 'uncoder' ),
				'description'          => __( 'Equal: every row as tall as the tallest one.', 'uncoder' ),
				'options'              => array(
					''      => __( 'Fit content', 'uncoder' ),
					'equal' => __( 'Equal', 'uncoder' ),
				),
				'condition'            => array( 'layout' => 'grid' ),
				'selectors_dictionary' => array(
					''      => '--uncoder-grid-auto-rows: auto',
					'equal' => '--uncoder-grid-auto-rows: 1fr',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'grid_auto_flow',
			array(
				'type'      => 'select',
				'label'     => __( 'Auto flow', 'uncoder' ),
				'options'   => array( '' => __( 'Row', 'uncoder' ), 'column' => __( 'Column', 'uncoder' ), 'row dense' => __( 'Dense', 'uncoder' ) ),
				'condition' => array( 'layout' => 'grid' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-grid-flow: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'justify',
			array(
				'type'      => 'choose',
				'label'     => __( 'Justify content', 'uncoder' ),
				'axis'      => 'main',
				'options'   => array(
					'flex-start'    => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-horizontal-justify-start' ),
					'center'        => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-horizontal-justify-center' ),
					'flex-end'      => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-horizontal-justify-end' ),
					'space-between' => array( 'label' => __( 'Space between', 'uncoder' ), 'icon' => 'align-horizontal-space-between' ),
					'space-around'  => array( 'label' => __( 'Space around', 'uncoder' ), 'icon' => 'align-horizontal-space-around' ),
					'space-evenly'  => array( 'label' => __( 'Space evenly', 'uncoder' ), 'icon' => 'align-horizontal-distribute-center' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-justify: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Align items', 'uncoder' ),
				'axis'      => 'cross',
				'options'   => array(
					'flex-start' => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-vertical-justify-start' ),
					'center'     => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-vertical-justify-center' ),
					'flex-end'   => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-vertical-justify-end' ),
					'stretch'    => array( 'label' => __( 'Stretch', 'uncoder' ), 'icon' => 'stretch-vertical' ),
					'baseline'   => array( 'label' => __( 'Baseline', 'uncoder' ), 'icon' => 'baseline' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-align: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'em', '%', 'vw' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 200 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Row gap', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'em', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-row-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'wrap',
			array(
				'type'      => 'choose',
				'label'     => __( 'Wrap', 'uncoder' ),
				'options'   => array(
					'nowrap' => array( 'label' => __( 'No wrap', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
					'wrap'   => array( 'label' => __( 'Wrap', 'uncoder' ), 'icon' => 'wrap-text' ),
				),
				'condition' => array( 'layout' => 'flex' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-wrap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'tag',
			array(
				'type'        => 'select',
				'label'       => __( 'HTML tag', 'uncoder' ),
				'default'     => '',
				'description' => __( 'Auto: <section> for page sections, <article> for loop-item cards, <div> inside and in headers, footers and popups (their wrapper is already a landmark).', 'uncoder' ),
				'ai'          => 'Omit for Auto (section for top-level page sections, article for loop-item cards, div elsewhere). Set header / footer / nav / main / aside only for real landmarks; "a" makes the whole box a link (set link).',
				'options'     => array(
					''        => __( 'Auto', 'uncoder' ),
					'div'     => 'div',
					'section' => 'section',
					'header'  => 'header',
					'footer'  => 'footer',
					'main'    => 'main',
					'article' => 'article',
					'aside'   => 'aside',
					'nav'     => 'nav',
					'a'       => 'a (link)',
				),
			)
		);
		$this->add_control(
			'link',
			array(
				'type'      => 'url',
				'label'     => __( 'Link', 'uncoder' ),
				'dynamic'   => true,
				'condition' => array( 'tag' => 'a' ),
			)
		);
		$this->end_section();

		// Query loop: the container repeats once per post or term (Core\Container_Loop, Renderer::render_loop()).
		$this->start_section( 'loop', array( 'label' => __( 'Query loop', 'uncoder' ) ) );
		$this->add_control(
			'_loop',
			array(
				'type'        => 'switch',
				'label'       => __( 'Repeat for each item', 'uncoder' ),
				'description' => __( 'This container repeats once per post or term, and dynamic data inside it shows that item. Put it inside a grid or row to lay the copies out. The editor shows the container once, with the data of the page itself; preview the page to see every item.', 'uncoder' ),
				'ai'          => 'Turn a card container into a loop: "_loop": true with "_loop_query" (posts) or "_loop_source": "terms". Use dynamic tags inside (post-title, featured-image, post-url; term-name, term-url…). The parent container lays the copies out (grid / row).',
			)
		);
		$this->add_control(
			'_loop_source',
			array(
				'type'      => 'choose',
				'label'     => __( 'Items', 'uncoder' ),
				'default'   => 'posts',
				'options'   => array(
					'posts' => array( 'label' => __( 'Posts', 'uncoder' ), 'icon' => 'file-text' ),
					'terms' => array( 'label' => __( 'Terms', 'uncoder' ), 'icon' => 'tags' ),
				),
				'condition' => array( '_loop' => true ),
			)
		);
		$this->add_group(
			'_loop_query',
			array(
				'type'      => 'query',
				'label'     => __( 'Query', 'uncoder' ),
				'default'   => array(
					'source'         => 'posts',
					'post_type'      => 'post',
					'posts_per_page' => 6,
					'orderby'        => 'date',
					'order'          => 'desc',
				),
				'condition' => array(
					'_loop'        => true,
					'_loop_source' => 'posts',
				),
			)
		);
		$this->add_control(
			'_loop_taxonomy',
			array(
				'type'            => 'select',
				'label'           => __( 'Taxonomy', 'uncoder' ),
				'default'         => 'category',
				'options'         => array(),
				'options_dynamic' => true,
				'source'          => 'taxonomies',
				'condition'       => array(
					'_loop'        => true,
					'_loop_source' => 'terms',
				),
			)
		);
		$this->add_control(
			'_loop_terms_scope',
			array(
				'type'      => 'select',
				'label'     => __( 'Which terms', 'uncoder' ),
				'default'   => 'all',
				'options'   => array(
					'all'  => __( 'All terms', 'uncoder' ),
					'post' => __( 'Terms of this post', 'uncoder' ),
				),
				'condition' => array(
					'_loop'        => true,
					'_loop_source' => 'terms',
				),
			)
		);
		$this->add_control(
			'_loop_terms_orderby',
			array(
				'type'      => 'select',
				'label'     => __( 'Order by', 'uncoder' ),
				'default'   => 'name',
				'options'   => array(
					'name'       => __( 'Name', 'uncoder' ),
					'count'      => __( 'Number of posts', 'uncoder' ),
					'term_order' => __( 'Custom order', 'uncoder' ),
					'id'         => __( 'Newest', 'uncoder' ),
				),
				'condition' => array(
					'_loop'        => true,
					'_loop_source' => 'terms',
				),
			)
		);
		$this->add_control(
			'_loop_terms_order',
			array(
				'type'      => 'choose',
				'label'     => __( 'Order', 'uncoder' ),
				'default'   => 'asc',
				'options'   => array(
					'asc'  => array( 'label' => __( 'Ascending', 'uncoder' ), 'icon' => 'arrow-up-narrow-wide' ),
					'desc' => array( 'label' => __( 'Descending', 'uncoder' ), 'icon' => 'arrow-down-wide-narrow' ),
				),
				'condition' => array(
					'_loop'        => true,
					'_loop_source' => 'terms',
				),
			)
		);
		$this->add_control(
			'_loop_terms_number',
			array(
				'type'      => 'number',
				'label'     => __( 'Number of terms', 'uncoder' ),
				'default'   => 12,
				'min'       => 1,
				'max'       => 100,
				'condition' => array(
					'_loop'        => true,
					'_loop_source' => 'terms',
				),
			)
		);
		$this->add_control(
			'_loop_terms_hide_empty',
			array(
				'type'      => 'switch',
				'label'     => __( 'Hide empty terms', 'uncoder' ),
				'default'   => true,
				'condition' => array(
					'_loop'        => true,
					'_loop_source' => 'terms',
				),
			)
		);
		$this->add_control(
			'_loop_empty',
			array(
				'type'        => 'text',
				'label'       => __( 'When nothing is found', 'uncoder' ),
				'placeholder' => __( 'e.g. No posts yet.', 'uncoder' ),
				'condition'   => array( '_loop' => true ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_background', array( 'label' => __( 'Background', 'uncoder' ), 'tab' => 'style' ) );
		$this->start_tabs( 'background_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_group(
			'background',
			array(
				'type'     => 'background',
				'label'    => __( 'Background', 'uncoder' ),
				'types'    => array( 'classic', 'gradient', 'video', 'slideshow' ),
				'selector' => '{{WRAPPER}}',
			)
		);
		$this->add_control(
			'bg_motion',
			array(
				'type'      => 'select',
				'label'     => __( 'Motion', 'uncoder' ),
				'options'   => array(
					''         => __( 'None', 'uncoder' ),
					'parallax' => __( 'Parallax', 'uncoder' ),
					'zoom-in'  => __( 'Zoom in on scroll', 'uncoder' ),
					'zoom-out' => __( 'Zoom out on scroll', 'uncoder' ),
					'mouse'    => __( 'Follow the pointer', 'uncoder' ),
				),
				'condition' => array( 'background.type' => array( 'classic', 'slideshow' ) ),
				'ai'        => 'Moves the background image (classic image or slideshow) while the page scrolls. Works on touch devices, unlike attachment "fixed".',
			)
		);
		$this->add_control(
			'bg_motion_speed',
			array(
				'type'      => 'number',
				'label'     => __( 'Motion speed', 'uncoder' ),
				'min'       => 1,
				'max'       => 10,
				'default'   => 4,
				'condition' => array(
					'background.type' => array( 'classic', 'slideshow' ),
					'bg_motion!'      => '',
				),
			)
		);
		$this->register_animated_background();
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_group(
			'background_hover',
			array(
				'type'     => 'background',
				'label'    => __( 'Background', 'uncoder' ),
				'selector' => '{{WRAPPER}}:hover',
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();

		$this->start_section( 'style_overlay', array( 'label' => __( 'Background overlay', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group(
			'overlay',
			array(
				'type'     => 'background',
				'label'    => __( 'Overlay', 'uncoder' ),
				'selector' => '{{WRAPPER}}::before',
			)
		);
		$this->add_control(
			'overlay_opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.01,
				'selectors' => array( '{{WRAPPER}}::before' => 'opacity: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_shape', array( 'label' => __( 'Shape dividers', 'uncoder' ), 'tab' => 'style' ) );
		$this->start_tabs( 'shape_tabs' );
		foreach ( array( 'top' => __( 'Top', 'uncoder' ), 'bottom' => __( 'Bottom', 'uncoder' ) ) as $side => $label ) {
			$shape    = 'shape_' . $side;
			$selector = '{{WRAPPER}} > .uncoder-shape--' . $side;
			$on       = array( $shape . '!' => '' );
			$this->start_tab( $side, $label );
			$this->add_control(
				$shape,
				array(
					'type'    => 'select',
					'label'   => __( 'Shape', 'uncoder' ),
					'options' => array( '' => __( 'None', 'uncoder' ) ) + Shapes::options(),
					'ui'      => 'shape',
					'ai'      => 'Decorative SVG edge. Give it the color of the neighbouring section so the two interlock.',
				)
			);
			$this->add_control(
				$shape . '_color',
				array(
					'type'      => 'color',
					'label'     => __( 'Color', 'uncoder' ),
					'condition' => $on,
					'selectors' => array( $selector => '--uncoder-shape-color: {{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				$shape . '_width',
				array(
					'type'       => 'slider',
					'label'      => __( 'Width', 'uncoder' ),
					'size_units' => array( '%' ),
					'range'      => array( '%' => array( 'min' => 100, 'max' => 300 ) ),
					'condition'  => $on,
					'selectors'  => array( $selector => '--uncoder-shape-w: {{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				$shape . '_height',
				array(
					'type'       => 'slider',
					'label'      => __( 'Height', 'uncoder' ),
					'size_units' => array( 'px', 'vw' ),
					'range'      => array( 'px' => array( 'min' => 0, 'max' => 500 ) ),
					'condition'  => $on,
					'selectors'  => array( $selector => '--uncoder-shape-h: {{VALUE}}' ),
				)
			);
			$this->add_control( $shape . '_flip', array( 'type' => 'switch', 'label' => __( 'Flip', 'uncoder' ), 'condition' => $on ) );
			$this->add_control(
				$shape . '_invert',
				array(
					'type'      => 'switch',
					'label'     => __( 'Invert', 'uncoder' ),
					'condition' => array( $shape => Shapes::invertible() ),
				)
			);
			$this->add_control( $shape . '_front', array( 'type' => 'switch', 'label' => __( 'Bring to front', 'uncoder' ), 'condition' => $on ) );
			$this->end_tab();
		}
		$this->end_tabs();
		$this->end_section();

		$this->start_section( 'style_border', array( 'label' => __( 'Border & shadow', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Box shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_group( 'shadow_hover', array( 'type' => 'box_shadow', 'label' => __( 'Box shadow on hover', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover' ) );
		$this->add_control(
			'border_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color on hover', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Text color', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}; --uncoder-heading-color: {{VALUE}}' ),
				'ai'        => 'Sets the inherited text color for everything inside (useful on dark sections).',
			)
		);
		$this->add_control(
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} a:not(.uncoder-btn)' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * The tag of a container whose HTML tag is Auto (twin of autoTag() in the editor's ElementView.tsx):
	 * top-level sections of page-like documents are <section>, loop-item cards <article>, the rest <div>.
	 * Header and footer templates already print inside <header> / <footer>.
	 */
	public static function auto_tag( int $depth, string $doc_type ): string {
		if ( 0 !== $depth ) {
			return 'div';
		}
		if ( 'loop-item' === $doc_type ) {
			return 'article';
		}
		return in_array( $doc_type, array( 'header', 'footer', 'popup', 'mega-menu' ), true ) ? 'div' : 'section';
	}

	/**
	 * Whether the container renders boxed content.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	public static function is_boxed( array $s, int $depth ): bool {
		$width = $s['content_width'] ?? '';
		if ( '' === $width || null === $width ) {
			return 0 === $depth;
		}
		return 'boxed' === $width;
	}

	/**
	 * Style → Background → Animated background: the animation and the settings it uses
	 * (Core\Animated_Backgrounds, module bg-animated).
	 */
	private function register_animated_background(): void {
		$this->add_control( 'bg_animation_heading', array( 'type' => 'heading', 'label' => __( 'Animated background', 'uncoder' ) ) );
		$this->add_control(
			'bg_animation',
			array(
				'type'    => 'select',
				'label'   => __( 'Animation', 'uncoder' ),
				'options' => Animated_Backgrounds::options(),
				'ai'      => 'An animated layer behind the content. CSS styles "style-1"…"style-5" (soft drifting gradients, no script) or WebGL shaders: gradients "fluid-gradient", "borealis", "gradient-mesh", "mist", "mystic-lake", "noir-haze", "void-wave", "halftone"; lights "the-shining", "phase-tunnel", "plasma-line", "light-strings"; shapes "flame", "pulse-bubble", "neon-eclipse", "echo-sphere"; images "liquid-mask", "liquid-image" (use bg_anim_image or the background image); patterns "bit-wave", "flux-stripes", "perspective-grid". Colours default to the Design System. Use on one or two sections (hero, CTA), with enough contrast for the text on top.',
			)
		);
		$colors = array(
			'color_1' => array( 'bg_anim_color_1', __( 'Color 1', 'uncoder' ), '--uncoder-abg-c1' ),
			'color_2' => array( 'bg_anim_color_2', __( 'Color 2', 'uncoder' ), '--uncoder-abg-c2' ),
			'color_3' => array( 'bg_anim_color_3', __( 'Color 3', 'uncoder' ), '--uncoder-abg-c3' ),
			'color_4' => array( 'bg_anim_color_4', __( 'Color 4', 'uncoder' ), '--uncoder-abg-c4' ),
			'bg'      => array( 'bg_anim_bg', __( 'Base color', 'uncoder' ), '--uncoder-abg-cbg' ),
		);
		foreach ( $colors as $setting => list( $key, $label, $var ) ) {
			$this->add_control(
				$key,
				array(
					'type'        => 'color',
					'label'       => $label,
					'description' => 'color_1' === $setting ? __( 'Empty colours follow the Design System (primary, secondary, accent, heading).', 'uncoder' ) : '',
					'condition'   => array( 'bg_animation' => Animated_Backgrounds::using( $setting ) ),
					'selectors'   => array( '{{WRAPPER}}' => $var . ': {{VALUE}}' ),
				)
			);
		}
		$this->add_control(
			'bg_anim_image',
			array(
				'type'        => 'media',
				'label'       => __( 'Image', 'uncoder' ),
				'description' => __( 'Empty: the background image of the container.', 'uncoder' ),
				'condition'   => array( 'bg_animation' => Animated_Backgrounds::using( 'image' ) ),
			)
		);
		$this->add_control(
			'bg_anim_freeze',
			array(
				'type'        => 'switch',
				'label'       => __( 'Freeze motion', 'uncoder' ),
				'description' => __( 'Shows one still frame instead of the animation.', 'uncoder' ),
				'condition'   => array( 'bg_animation' => Animated_Backgrounds::using( 'speed' ) ),
			)
		);
		$numbers = array(
			'bg_anim_speed'     => array( 'speed', __( 'Speed', 'uncoder' ), 1, 100, 'speed' ),
			'bg_anim_frame'     => array( 'speed', __( 'Still frame', 'uncoder' ), 0, 1000, 'frame' ),
			'bg_anim_scale'     => array( 'scale', __( 'Scale', 'uncoder' ), 0, 100, 'scale' ),
			'bg_anim_intensity' => array( 'intensity', __( 'Intensity', 'uncoder' ), 0, 100, 'intensity' ),
			'bg_anim_noise'     => array( 'noise', __( 'Noise', 'uncoder' ), 0, 100, 'noise' ),
			'bg_anim_angle'     => array( 'angle', __( 'Angle', 'uncoder' ), 0, 360, 'angle' ),
		);
		foreach ( $numbers as $key => list( $setting, $label, $min, $max, $default ) ) {
			$condition = array( 'bg_animation' => Animated_Backgrounds::using( $setting ) );
			if ( 'bg_anim_speed' === $key ) {
				$condition['bg_anim_freeze!'] = true;
			} elseif ( 'bg_anim_frame' === $key ) {
				$condition['bg_anim_freeze'] = true;
			}
			$this->add_control(
				$key,
				array(
					'type'      => 'number',
					'label'     => $label,
					'min'       => $min,
					'max'       => $max,
					'default'   => Animated_Backgrounds::DEFAULTS[ $default ],
					'condition' => $condition,
				)
			);
		}
		foreach ( array( 'bg_anim_offset_x' => __( 'Offset X', 'uncoder' ), 'bg_anim_offset_y' => __( 'Offset Y', 'uncoder' ) ) as $key => $label ) {
			$this->add_control(
				$key,
				array(
					'type'      => 'number',
					'label'     => $label,
					'min'       => -400,
					'max'       => 400,
					'condition' => array( 'bg_animation' => Animated_Backgrounds::using( 'offset' ) ),
				)
			);
		}
		$this->add_control(
			'bg_anim_interactive',
			array(
				'type'        => 'switch',
				'label'       => __( 'Follow the pointer', 'uncoder' ),
				'description' => __( 'The animation reacts to the mouse.', 'uncoder' ),
				'condition'   => array( 'bg_animation' => Animated_Backgrounds::using( 'interactive' ) ),
			)
		);
	}
}
