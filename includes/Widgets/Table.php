<?php
/**
 * Table widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Accessible data table: a columns repeater (label, alignment, width) and a rows repeater whose
 * "cells" field holds one cell per line.
 */
class Table extends Widget_Base {

	public function name(): string {
		return 'table';
	}

	public function title(): string {
		return __( 'Table', 'uncoder' );
	}

	public function icon(): string {
		return 'table';
	}

	public function category(): string {
		return 'content';
	}

	public function keywords(): array {
		return array( 'table', 'data', 'comparison', 'grid', 'spreadsheet', 'specs' );
	}

	public function description(): string {
		return __( 'Data or comparison table. Define columns (label, alignment, width), then rows with one cell per line. Striped, bordered, hover, sticky header and caption options; scrolls horizontally (or stacks) on small screens.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content_columns', array( 'label' => __( 'Columns', 'uncoder' ) ) );
		$this->add_control(
			'columns',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Columns', 'uncoder' ),
				'title_field' => 'label',
				'fields'      => array(
					'label' => array(
						'type'    => 'text',
						'label'   => __( 'Header', 'uncoder' ),
						'default' => __( 'Column', 'uncoder' ),
					),
					'align' => array(
						'type'    => 'choose',
						'label'   => __( 'Alignment', 'uncoder' ),
						'default' => 'left',
						'options' => array(
							'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
							'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
							'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
						),
					),
					'width' => array(
						'type'       => 'slider',
						'label'      => __( 'Width', 'uncoder' ),
						'size_units' => array( '%', 'px', 'rem' ),
						'selectors'  => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => 'width: {{VALUE}}' ),
					),
				),
				'default'     => array(
					array(
						'label' => __( 'Feature', 'uncoder' ),
						'align' => 'left',
					),
					array(
						'label' => __( 'Starter', 'uncoder' ),
						'align' => 'center',
					),
					array(
						'label' => __( 'Pro', 'uncoder' ),
						'align' => 'center',
					),
					array(
						'label' => __( 'Business', 'uncoder' ),
						'align' => 'center',
					),
				),
				'ai'          => 'One row per column: {"label":"Price","align":"left|center|right","width":{"size":20,"unit":"%"}}.',
			)
		);
		$this->add_control(
			'show_header',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show header row', 'uncoder' ),
				'default' => true,
			)
		);
		$this->end_section();

		$this->start_section( 'content_rows', array( 'label' => __( 'Rows', 'uncoder' ) ) );
		$this->add_control(
			'rows',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Rows', 'uncoder' ),
				'title_field' => 'cells',
				'fields'      => array(
					'cells'     => array(
						'type'        => 'textarea',
						'label'       => __( 'Cells', 'uncoder' ),
						'description' => __( 'One cell per line, in column order. Inline formatting and links are allowed.', 'uncoder' ),
						'html'        => 'inline',
						'rows'        => 4,
						'default'     => '',
					),
					'highlight' => array(
						'type'    => 'switch',
						'label'   => __( 'Highlight row', 'uncoder' ),
						'default' => false,
					),
				),
				'default'     => array(
					array( 'cells' => "Websites\n1\n5\nUnlimited" ),
					array( 'cells' => "Storage\n10 GB\n100 GB\n1 TB" ),
					array( 'cells' => "Custom domain\nNo\nYes\nYes" ),
					array( 'cells' => "Support\nEmail\nPriority email\nDedicated manager" ),
				),
				'ai'          => 'Each row: {"cells":"first\nsecond\nthird"} — newline-separated, same order as columns. Empty line = empty cell.',
			)
		);
		$this->add_control(
			'row_headers',
			array(
				'type'        => 'switch',
				'label'       => __( 'First column is a header', 'uncoder' ),
				'description' => __( 'Marks the first cell of each row as its row header for screen readers.', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'caption',
			array(
				'type'    => 'text',
				'label'   => __( 'Caption', 'uncoder' ),
				'default' => '',
				'dynamic' => true,
				'ai'      => 'Short title of the table; also used as the accessible name of the scroll area.',
			)
		);
		$this->add_control(
			'caption_visible',
			array(
				'type'      => 'switch',
				'label'     => __( 'Show caption', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'caption!' => '' ),
			)
		);
		$this->add_control(
			'caption_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Caption position', 'uncoder' ),
				'default'   => 'top',
				'options'   => array(
					'top'    => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'panel-top' ),
					'bottom' => array( 'label' => __( 'Bottom', 'uncoder' ), 'icon' => 'panel-bottom' ),
				),
				'condition' => array( 'caption!' => '' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_options', array( 'label' => __( 'Options', 'uncoder' ) ) );
		$this->add_control(
			'striped',
			array(
				'type'    => 'switch',
				'label'   => __( 'Striped rows', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'borders',
			array(
				'type'    => 'select',
				'label'   => __( 'Borders', 'uncoder' ),
				'default' => 'rows',
				'options' => array(
					'rows'  => __( 'Between rows', 'uncoder' ),
					'all'   => __( 'All cells', 'uncoder' ),
					'outer' => __( 'Outer frame only', 'uncoder' ),
					'none'  => __( 'None', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'hover',
			array(
				'type'    => 'switch',
				'label'   => __( 'Highlight row on hover', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'sticky_header',
			array(
				'type'        => 'switch',
				'label'       => __( 'Sticky header', 'uncoder' ),
				'description' => __( 'The table scrolls inside its own area (see max height) and the header stays visible.', 'uncoder' ),
				'default'     => false,
			)
		);
		$this->add_responsive_control(
			'max_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'rem' ),
				'condition'  => array( 'sticky_header' => true ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-table-max-h: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'mobile',
			array(
				'type'    => 'select',
				'label'   => __( 'On phones', 'uncoder' ),
				'default' => 'scroll',
				'options' => array(
					'scroll' => __( 'Scroll horizontally', 'uncoder' ),
					'stack'  => __( 'Stack rows as cards', 'uncoder' ),
				),
			)
		);
		$this->add_responsive_control(
			'min_width',
			array(
				'type'        => 'slider',
				'label'       => __( 'Table min width', 'uncoder' ),
				'description' => __( 'Below this width the table scrolls horizontally.', 'uncoder' ),
				'size_units'  => array( 'px', 'rem' ),
				'range'       => array( 'px' => array( 'min' => 200, 'max' => 2000 ) ),
				'selectors'   => array( '{{WRAPPER}} .uncoder-table__table' => 'min-width: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_table', array( 'label' => __( 'Table', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-table__table' ) );
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-table__table' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-table-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-table-border: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'border_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Border width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 6 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-table-border-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-table__scroll' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'cell_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Cell padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-table__cell' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'vertical_align',
			array(
				'type'      => 'select',
				'label'     => __( 'Vertical alignment', 'uncoder' ),
				'options'   => array(
					''       => __( 'Middle', 'uncoder' ),
					'top'    => __( 'Top', 'uncoder' ),
					'bottom' => __( 'Bottom', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-table__cell' => 'vertical-align: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'stripe_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Stripe color', 'uncoder' ),
				'condition' => array( 'striped' => true ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-table-stripe: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover row color', 'uncoder' ),
				'condition' => array( 'hover' => true ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-table-hover: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'highlight_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Highlighted row color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-table-highlight: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_header', array( 'label' => __( 'Header row', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'show_header' => true ) ) );
		$this->add_group( 'header_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-table__head .uncoder-table__cell' ) );
		$this->add_control(
			'header_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-table__head .uncoder-table__cell' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'header_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-table-head-bg: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_first', array( 'label' => __( 'First column', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'row_headers' => true ) ) );
		$this->add_group( 'first_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-table__body .uncoder-table__rowhead' ) );
		$this->add_control(
			'first_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-table__body .uncoder-table__rowhead' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_caption', array( 'label' => __( 'Caption', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'caption!' => '' ) ) );
		$this->add_group( 'caption_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-table__caption' ) );
		$this->add_control(
			'caption_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-table__caption' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'caption_align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-table__caption' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'caption_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-table__caption' => 'padding-block: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Splits a cells field into kses'd cell HTML strings.
	 *
	 * @return string[]
	 */
	private function cells( $raw ): array {
		$raw   = str_replace( array( "\r\n", "\r" ), "\n", (string) $raw );
		$cells = explode( "\n", $raw );
		// Drop trailing empty lines only (an empty line in the middle is an intentional empty cell).
		while ( $cells && '' === trim( (string) end( $cells ) ) ) {
			array_pop( $cells );
		}
		return array_map(
			function ( $cell ) {
				return trim( $this->inline_html( $cell ) );
			},
			$cells
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$columns = is_array( $s['columns'] ?? null ) ? array_values( array_filter( $s['columns'], 'is_array' ) ) : array();
		$rows    = array();
		foreach ( (array) ( $s['rows'] ?? array() ) as $row ) {
			if ( is_array( $row ) ) {
				$cells = $this->cells( $row['cells'] ?? '' );
				if ( $cells ) {
					$rows[] = array(
						'cells'     => $cells,
						'highlight' => ! empty( $row['highlight'] ),
						'_id'       => isset( $row['_id'] ) && is_string( $row['_id'] ) ? $row['_id'] : '',
					);
				}
			}
		}
		if ( ! $rows && ! $columns ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-table__empty">' . esc_html__( 'Add columns and rows to build the table.', 'uncoder' ) . '</p>';
			}
			return;
		}

		$count = count( $columns );
		foreach ( $rows as $row ) {
			$count = max( $count, count( $row['cells'] ) );
		}
		$aligns = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$align      = (string) ( $columns[ $i ]['align'] ?? 'left' );
			$aligns[ $i ] = in_array( $align, array( 'center', 'right' ), true ) ? ' uncoder-table__cell--' . $align : '';
		}
		$labels = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$labels[ $i ] = trim( (string) ( $columns[ $i ]['label'] ?? '' ) );
		}

		$row_heads = ! array_key_exists( 'row_headers', $s ) || ! empty( $s['row_headers'] );
		$classes   = array(
			'uncoder-table',
			'uncoder-table--borders-' . ( in_array( $s['borders'] ?? 'rows', array( 'rows', 'all', 'outer', 'none' ), true ) ? $s['borders'] : 'rows' ),
			'uncoder-table--mobile-' . ( 'stack' === ( $s['mobile'] ?? 'scroll' ) ? 'stack' : 'scroll' ),
		);
		if ( ! empty( $s['striped'] ) ) {
			$classes[] = 'uncoder-table--striped';
		}
		if ( ! empty( $s['hover'] ) ) {
			$classes[] = 'uncoder-table--hover';
		}
		if ( ! empty( $s['sticky_header'] ) ) {
			$classes[] = 'uncoder-table--sticky';
		}

		$caption    = trim( (string) ( $s['caption'] ?? '' ) );
		$caption_id = 'uncoder-table-' . sanitize_html_class( $ctx->element_id ) . '-caption';
		$region     = array(
			'class'    => 'uncoder-table__scroll',
			'role'     => 'region',
			'tabindex' => '0',
		);
		if ( '' !== $caption ) {
			$region['aria-labelledby'] = $caption_id;
		} else {
			$region['aria-label'] = __( 'Table', 'uncoder' );
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><div' . Utils::attrs( $region ) . '><table class="uncoder-table__table">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		if ( '' !== $caption ) {
			$cap_class = 'uncoder-table__caption' . ( 'bottom' === ( $s['caption_position'] ?? 'top' ) ? ' uncoder-table__caption--bottom' : '' );
			if ( array_key_exists( 'caption_visible', $s ) && empty( $s['caption_visible'] ) ) {
				$cap_class .= ' uncoder-sr-only';
			}
			echo '<caption class="' . esc_attr( $cap_class ) . '" id="' . esc_attr( $caption_id ) . '">' . esc_html( $caption ) . '</caption>';
		}

		// Column widths target <col class="uncoder-ri-…"> so they apply with or without a header row.
		echo '<colgroup>';
		for ( $i = 0; $i < $count; $i++ ) {
			$col_id = ! empty( $columns[ $i ]['_id'] ) && is_string( $columns[ $i ]['_id'] ) ? ' uncoder-ri-' . sanitize_html_class( $columns[ $i ]['_id'] ) : '';
			echo '<col class="' . esc_attr( 'uncoder-table__col' . $col_id ) . '">';
		}
		echo '</colgroup>';

		if ( ! array_key_exists( 'show_header', $s ) || ! empty( $s['show_header'] ) ) {
			echo '<thead class="uncoder-table__head"><tr>';
			for ( $i = 0; $i < $count; $i++ ) {
				echo '<th scope="col" class="' . esc_attr( 'uncoder-table__cell' . $aligns[ $i ] ) . '">' . esc_html( $labels[ $i ] ) . '</th>';
			}
			echo '</tr></thead>';
		}

		echo '<tbody class="uncoder-table__body">';
		foreach ( $rows as $row ) {
			$tr = 'uncoder-table__row' . ( $row['highlight'] ? ' uncoder-table__row--highlight' : '' );
			echo '<tr class="' . esc_attr( $tr ) . '">';
			for ( $i = 0; $i < $count; $i++ ) {
				$cell = $row['cells'][ $i ] ?? '';
				if ( 0 === $i && $row_heads ) {
					echo '<th scope="row" class="' . esc_attr( 'uncoder-table__cell uncoder-table__rowhead' . $aligns[ $i ] ) . '">' . $cell . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
					continue;
				}
				$attrs = array(
					'class'      => 'uncoder-table__cell' . $aligns[ $i ],
					'data-label' => '' !== $labels[ $i ] ? $labels[ $i ] : null,
				);
				echo '<td' . Utils::attrs( $attrs ) . '>' . $cell . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped attrs, kses'd inline HTML.
			}
			echo '</tr>';
		}
		echo '</tbody></table></div></div>';
	}
}
