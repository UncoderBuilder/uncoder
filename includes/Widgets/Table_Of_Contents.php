<?php
/**
 * Table of contents widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Table of contents built in the browser from the headings of the page (the "toc" module).
 */
class Table_Of_Contents extends Widget_Base {

	public const LEVELS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

	public function name(): string {
		return 'table-of-contents';
	}

	public function title(): string {
		return __( 'Table of Contents', 'uncoder' );
	}

	public function icon(): string {
		return 'list-tree';
	}

	public function category(): string {
		return 'content';
	}

	public function keywords(): array {
		return array( 'toc', 'table of contents', 'contents', 'headings', 'anchor', 'index', 'outline' );
	}

	public function description(): string {
		return __( 'A table of contents generated from the headings of the page, with smooth scrolling, active-section highlighting and an optional collapsible or sticky box.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'toc' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Table of contents', 'uncoder' ) ) );
		$this->add_control(
			'title',
			array(
				'type'    => 'text',
				'label'   => __( 'Title', 'uncoder' ),
				'default' => __( 'Table of contents', 'uncoder' ),
				'inline'  => true,
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'p',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control(
			'headings',
			array(
				'type'    => 'multiselect',
				'label'   => __( 'Headings to include', 'uncoder' ),
				'default' => array( 'h2', 'h3' ),
				'options' => array_combine( self::LEVELS, array_map( 'strtoupper', self::LEVELS ) ),
			)
		);
		$this->add_control(
			'container',
			array(
				'type'        => 'text',
				'label'       => __( 'Container selector', 'uncoder' ),
				'placeholder' => '.uncoder-post-content',
				'description' => __( 'CSS selector of the area to scan. Empty scans the whole document (template) the widget is in.', 'uncoder' ),
			)
		);
		$this->add_control(
			'exclude',
			array(
				'type'        => 'text',
				'label'       => __( 'Exclude selector', 'uncoder' ),
				'placeholder' => '.no-toc',
				'description' => __( 'Headings inside elements matching this selector are skipped.', 'uncoder' ),
			)
		);
		$this->add_control(
			'marker',
			array(
				'type'    => 'select',
				'label'   => __( 'Marker', 'uncoder' ),
				'default' => 'numbers',
				'options' => array(
					'numbers' => __( 'Numbers (1.1, 1.2…)', 'uncoder' ),
					'bullets' => __( 'Bullets', 'uncoder' ),
					'none'    => __( 'None', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'hierarchical',
			array(
				'type'    => 'switch',
				'label'   => __( 'Nest sub-headings', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'min_headings',
			array(
				'type'        => 'number',
				'label'       => __( 'Minimum headings', 'uncoder' ),
				'description' => __( 'The box hides itself when the page has fewer headings.', 'uncoder' ),
				'default'     => 2,
				'min'         => 1,
				'max'         => 20,
			)
		);
		$this->end_section();

		$this->start_section( 'behaviour', array( 'label' => __( 'Behaviour', 'uncoder' ) ) );
		$this->add_control(
			'collapsible',
			array(
				'type'  => 'switch',
				'label' => __( 'Collapsible', 'uncoder' ),
			)
		);
		$this->add_control(
			'collapsed',
			array(
				'type'      => 'switch',
				'label'     => __( 'Start collapsed', 'uncoder' ),
				'condition' => array( 'collapsible' => true ),
			)
		);
		$this->add_control(
			'smooth',
			array(
				'type'    => 'switch',
				'label'   => __( 'Smooth scroll', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'offset',
			array(
				'type'        => 'number',
				'label'       => __( 'Scroll offset (px)', 'uncoder' ),
				'description' => __( 'Space kept above the heading, e.g. the height of a sticky header.', 'uncoder' ),
				'default'     => 24,
				'min'         => 0,
				'max'         => 400,
			)
		);
		$this->add_control(
			'highlight',
			array(
				'type'    => 'switch',
				'label'   => __( 'Highlight the current section', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'sticky',
			array(
				'type'        => 'switch',
				'label'       => __( 'Sticky', 'uncoder' ),
				'description' => __( 'Stays in view while scrolling its column.', 'uncoder' ),
			)
		);
		$this->add_control(
			'sticky_offset',
			array(
				'type'       => 'slider',
				'label'      => __( 'Sticky distance from top', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'vh' ),
				'condition'  => array( 'sticky' => true ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-toc-top: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'box_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_group( 'box_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'box_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'box_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'box_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'max_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'List max height', 'uncoder' ),
				'size_units' => array( 'px', 'vh' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-toc__body' => 'max-height: {{VALUE}}; overflow-y: auto' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Title', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-toc__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-toc__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'title_divider',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-toc__header' => 'border-bottom: 1px solid {{VALUE}}; padding-bottom: var(--uncoder-toc-title-gap, 12px)' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-toc-title-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_list', array( 'label' => __( 'List', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'list_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-toc__list' ) );
		$this->start_tabs( 'list_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-toc__link' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'marker_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Marker color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-toc__list' => '--uncoder-toc-marker: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-toc__link:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Active', 'uncoder' ) );
		$this->add_control(
			'link_active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-toc__link.is-active' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'active_indicator',
			array(
				'type'      => 'color',
				'label'     => __( 'Indicator color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-toc__list' => '--uncoder-toc-indicator: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'item_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between items', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-toc__list' => '--uncoder-toc-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'indent',
			array(
				'type'       => 'slider',
				'label'      => __( 'Sub-level indent', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-toc__list' => '--uncoder-toc-indent: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'toggle_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Collapse icon color', 'uncoder' ),
				'condition' => array( 'collapsible' => true ),
				'selectors' => array( '{{WRAPPER}} .uncoder-toc__chevron' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 * @return array<string,mixed>
	 */
	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$levels = array_values( array_intersect( self::LEVELS, (array) ( $s['headings'] ?? array() ) ) );
		$attrs  = array(
			'data-settings' => $this->json_attr(
				array(
					'headings'     => $levels ? $levels : array( 'h2', 'h3' ),
					'container'    => trim( (string) ( $s['container'] ?? '' ) ),
					'exclude'      => trim( (string) ( $s['exclude'] ?? '' ) ),
					'hierarchical' => ! empty( $s['hierarchical'] ),
					'smooth'       => ! empty( $s['smooth'] ),
					'offset'       => max( 0, (int) ( $s['offset'] ?? 0 ) ),
					'highlight'    => ! empty( $s['highlight'] ),
					'min'          => max( 1, (int) ( $s['min_headings'] ?? 2 ) ),
					'sample'       => $ctx->editor ? array(
						__( 'Introduction', 'uncoder' ),
						__( 'Getting started', 'uncoder' ),
						'-' . __( 'Choosing the right tools', 'uncoder' ),
						'-' . __( 'Setting up your workspace', 'uncoder' ),
						__( 'Key takeaways', 'uncoder' ),
					) : array(),
				)
			),
		);
		if ( ! empty( $s['sticky'] ) ) {
			$attrs['class'] = 'uncoder-toc-sticky';
		}
		return $attrs;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$id        = 'uncoder-toc-' . $ctx->element_id;
		$tag       = Utils::tag( $s['title_tag'] ?? 'p', Utils::HEADING_TAGS, 'p' );
		$title     = trim( (string) ( $s['title'] ?? '' ) );
		$marker    = in_array( $s['marker'] ?? 'numbers', array( 'numbers', 'bullets', 'none' ), true ) ? $s['marker'] : 'numbers';
		$list_tag  = 'numbers' === $marker ? 'ol' : 'ul';
		$collapse  = ! empty( $s['collapsible'] );
		$collapsed = $collapse && ! empty( $s['collapsed'] );

		echo '<div class="' . esc_attr( 'uncoder-toc uncoder-toc--' . $marker . ( $collapsed ? ' is-collapsed' : '' ) ) . '">';
		if ( '' !== $title || $collapse ) {
			echo '<div class="uncoder-toc__header">';
			echo '<' . $tag . ' class="uncoder-toc__title" id="' . esc_attr( $id . '-title' ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag.
			if ( $collapse ) {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
				echo '<button' . Utils::attrs(
					array(
						'type'          => 'button',
						'class'         => 'uncoder-toc__toggle',
						'aria-expanded' => $collapsed ? 'false' : 'true',
						'aria-controls' => $id . '-body',
					)
				) . '><span class="uncoder-toc__title-text"' . $ctx->inline( 'title' ) . '>' . esc_html( '' !== $title ? $title : __( 'Table of contents', 'uncoder' ) ) . '</span>' . $this->render_icon( 'chevron-down', array( 'class' => 'uncoder-toc__chevron' ) ) . '</button>';
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<span class="uncoder-toc__title-text"' . $ctx->inline( 'title' ) . '>' . esc_html( $title ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
			}
			echo '</' . $tag . '></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag.
		}
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '<nav' . Utils::attrs(
			array(
				'class'           => 'uncoder-toc__body',
				'id'              => $id . '-body',
				'aria-labelledby' => '' !== $title || $collapse ? $id . '-title' : null,
				'aria-label'      => '' === $title && ! $collapse ? __( 'Table of contents', 'uncoder' ) : null,
				'hidden'          => $collapsed,
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		// Filled by the "toc" module from the page headings; stays empty without JavaScript.
		echo '<' . $list_tag . ' class="uncoder-toc__list" data-uncoder-toc-list></' . $list_tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.
		echo '</nav></div>';
	}
}
