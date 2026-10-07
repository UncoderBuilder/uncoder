<?php
/**
 * Icon list widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A list of short items, each with an icon and an optional link. Vertical or inline.
 */
class Icon_List extends Widget_Base {

	/** Layout swaps the flex direction and the divider geometry (a horizontal rule vs. a vertical bar). */
	public const LAYOUT_CSS = array(
		'vertical' => '--uncoder-ilist-dir:column;--uncoder-ilist-wrap:nowrap;--uncoder-ilist-justify:flex-start;--uncoder-ilist-dv-top:calc(var(--uncoder-ilist-gap) / -2);--uncoder-ilist-dv-left:0;--uncoder-ilist-dv-right:0;--uncoder-ilist-dv-w:var(--uncoder-ilist-dv-size, 100%);--uncoder-ilist-dv-h:0;--uncoder-ilist-dv-mi:var(--uncoder-ilist-dv-align-mi, 0 auto);--uncoder-ilist-dv-bt:var(--uncoder-ilist-dv-weight);--uncoder-ilist-dv-bl:0;--uncoder-ilist-dv-tf:translateY(-50%)',
		'inline'   => '--uncoder-ilist-dir:row;--uncoder-ilist-wrap:wrap;--uncoder-ilist-justify:var(--uncoder-ilist-align, flex-start);--uncoder-ilist-dv-top:50%;--uncoder-ilist-dv-left:calc(var(--uncoder-ilist-gap) / -2);--uncoder-ilist-dv-right:auto;--uncoder-ilist-dv-w:0;--uncoder-ilist-dv-h:var(--uncoder-ilist-dv-size-inline, 1em);--uncoder-ilist-dv-mi:0;--uncoder-ilist-dv-bt:0;--uncoder-ilist-dv-bl:var(--uncoder-ilist-dv-weight);--uncoder-ilist-dv-tf:translate(-50%, -50%)',
	);

	public function name(): string {
		return 'icon-list';
	}

	public function title(): string {
		return __( 'Icon List', 'uncoder' );
	}

	public function icon(): string {
		return 'list-checks';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'icon list', 'list', 'bullets', 'checklist', 'features', 'links' );
	}

	public function description(): string {
		return __( 'A list of short items with icons and optional links, stacked or inline. Use for feature checklists, contact details and footer links.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Icon list', 'uncoder' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Items', 'uncoder' ),
				'title_field' => 'text',
				'fields'      => array(
					'text'       => array(
						'type'    => 'text',
						'label'   => __( 'Text', 'uncoder' ),
						'html'    => 'inline',
						'default' => __( 'List item', 'uncoder' ),
						'dynamic' => true,
						'inline'  => true,
					),
					'icon'       => array(
						'type'    => 'icon',
						'label'   => __( 'Icon', 'uncoder' ),
						'default' => array( 'library' => 'lucide', 'value' => 'check' ),
					),
					'link'       => array(
						'type'    => 'url',
						'label'   => __( 'Link', 'uncoder' ),
						'dynamic' => true,
					),
					'icon_color' => array(
						'type'      => 'color',
						'label'     => __( 'Icon color', 'uncoder' ),
						'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .uncoder-icon-list__icon' => 'color: {{VALUE}}' ),
					),
				),
				'default'     => array(
					array(
						'_id'  => 'ilst001',
						'text' => __( 'Free shipping on orders over $50', 'uncoder' ),
						'icon' => array( 'library' => 'lucide', 'value' => 'check' ),
					),
					array(
						'_id'  => 'ilst002',
						'text' => __( '30-day hassle-free returns', 'uncoder' ),
						'icon' => array( 'library' => 'lucide', 'value' => 'check' ),
					),
					array(
						'_id'  => 'ilst003',
						'text' => __( 'Support from real people, seven days a week', 'uncoder' ),
						'icon' => array( 'library' => 'lucide', 'value' => 'check' ),
					),
				),
				'ai'          => 'Rows: {"text": "…", "icon": "check", "link": {"url": "…"}}. Use an empty icon {"library":"none"} for plain text links.',
			)
		);
		$this->add_responsive_control(
			'layout',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Layout', 'uncoder' ),
				'default'              => 'vertical',
				'options'              => array(
					'vertical' => array( 'label' => __( 'Vertical', 'uncoder' ), 'icon' => 'rows-2' ),
					'inline'   => array( 'label' => __( 'Inline', 'uncoder' ), 'icon' => 'columns-2' ),
				),
				'selectors_dictionary' => self::LAYOUT_CSS,
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		// Columns fill top to bottom (11 items in 2 columns: 6 + 5). A column is never narrower than the
		// minimum width (Design → List), so on phones the list falls back to one column by itself.
		$this->add_responsive_control(
			'columns',
			array(
				'type'                 => 'select',
				'label'                => __( 'Columns', 'uncoder' ),
				'default'              => '1',
				'options'              => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'condition'            => array( 'layout!' => 'inline' ),
				'description'          => __( 'Fills top to bottom. Columns stay at least the minimum width (Design → List), so phones show one column unless you set one here.', 'uncoder' ),
				'ai'                   => 'Vertical layout only. "2" splits 11 items into 6 + 5; on narrow screens columns never shrink below column_min (default 200px), so mobile falls back to one column automatically.',
				'selectors_dictionary' => array(
					'1' => 'display:flex;columns:auto;--uncoder-ilist-item-mb:0',
					'2' => 'display:block;columns:var(--uncoder-ilist-col-min, 200px) 2;column-gap:var(--uncoder-ilist-col-gap, 2em);--uncoder-ilist-item-mb:var(--uncoder-ilist-gap)',
					'3' => 'display:block;columns:var(--uncoder-ilist-col-min, 200px) 3;column-gap:var(--uncoder-ilist-col-gap, 2em);--uncoder-ilist-item-mb:var(--uncoder-ilist-gap)',
					'4' => 'display:block;columns:var(--uncoder-ilist-col-min, 200px) 4;column-gap:var(--uncoder-ilist-col-gap, 2em);--uncoder-ilist-item-mb:var(--uncoder-ilist-gap)',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider',
			array(
				'type'  => 'switch',
				'label' => __( 'Divider between items', 'uncoder' ),
			)
		);
		$this->add_control(
			'new_tab',
			array(
				'type'        => 'switch',
				'label'       => __( 'Open links in a new tab', 'uncoder' ),
				'description' => __( 'Applies to every linked item.', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_list', array( 'label' => __( 'List', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'column_min',
			array(
				'type'       => 'slider',
				'label'      => __( 'Minimum column width', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 80, 'max' => 480 ) ),
				'condition'  => array( 'columns!' => '1' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ilist-col-min: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'column_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Column gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'condition'  => array( 'columns!' => '1' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ilist-col-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ilist-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => '--uncoder-ilist-align:flex-start;--uncoder-ilist-dv-align-mi:0 auto;text-align:left',
					'center' => '--uncoder-ilist-align:center;--uncoder-ilist-dv-align-mi:auto;text-align:center',
					'right'  => '--uncoder-ilist-align:flex-end;--uncoder-ilist-dv-align-mi:auto 0;text-align:right',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider_heading',
			array(
				'type'      => 'heading',
				'label'     => __( 'Divider', 'uncoder' ),
				'condition' => array( 'divider' => 'yes' ),
			)
		);
		$this->add_control(
			'divider_style',
			array(
				'type'      => 'select',
				'label'     => __( 'Style', 'uncoder' ),
				'options'   => array(
					''       => __( 'Solid', 'uncoder' ),
					'dashed' => __( 'Dashed', 'uncoder' ),
					'dotted' => __( 'Dotted', 'uncoder' ),
				),
				'condition' => array( 'divider' => 'yes' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-ilist-dv-style: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider_weight',
			array(
				'type'       => 'slider',
				'label'      => __( 'Weight', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
				'condition'  => array( 'divider' => 'yes' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ilist-dv-weight: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'divider_size',
			array(
				'type'        => 'slider',
				'label'       => __( 'Length (vertical layout)', 'uncoder' ),
				'size_units'  => array( '%', 'px' ),
				'condition'   => array( 'divider' => 'yes' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-ilist-dv-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'divider_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Height (inline layout)', 'uncoder' ),
				'size_units' => array( 'px', 'em', '%' ),
				'condition'  => array( 'divider' => 'yes' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ilist-dv-size-inline: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'condition' => array( 'divider' => 'yes' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-ilist-dv-color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_icon', array( 'label' => __( 'Icon', 'uncoder' ), 'tab' => 'style' ) );
		$this->start_tabs( 'icon_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-ilist-icon-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'icon_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-list__item:hover .uncoder-icon-list__icon' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 6, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ilist-icon-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Text indent', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-ilist-icon-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_valign',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Vertical position', 'uncoder' ),
				'description'          => __( 'Top keeps the icon on the first line of multi-line items.', 'uncoder' ),
				'options'              => array(
					'top'    => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'align-start-vertical' ),
					'middle' => array( 'label' => __( 'Middle', 'uncoder' ), 'icon' => 'align-center-vertical' ),
				),
				'selectors_dictionary' => array(
					'top'    => '--uncoder-ilist-valign: flex-start',
					// Centred items need no first-line shift (see .uncoder-icon-list__text).
					'middle' => '--uncoder-ilist-valign: center; --uncoder-ilist-text-shift: 0px',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_stroke',
			array(
				'type'      => 'number',
				'label'     => __( 'Stroke width', 'uncoder' ),
				'min'       => 0.5,
				'max'       => 4,
				'step'      => 0.25,
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-list__icon .uncoder-svg' => 'stroke-width: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		// Typography sits on the row so em-based icon sizes and the icon's line box follow the text.
		$this->add_group( 'text_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-icon-list__inner' ) );
		$this->start_tabs( 'text_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-list__text' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'text_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-icon-list__item:hover .uncoder-icon-list__text' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'text_hover_underline',
			array(
				'type'      => 'switch',
				'label'     => __( 'Underline links', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} a.uncoder-icon-list__inner:hover .uncoder-icon-list__text' => 'text-decoration: underline; text-underline-offset: 0.2em' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$items = is_array( $s['items'] ?? null ) ? array_values( $s['items'] ) : array();
		if ( ! $items ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-icon-list__placeholder">' . esc_html__( 'Add items to this list in the Content tab.', 'uncoder' ) . '</p>';
			}
			return;
		}

		$class = 'uncoder-icon-list' . ( ! empty( $s['divider'] ) ? ' uncoder-icon-list--divider' : '' );
		echo '<ul class="' . esc_attr( $class ) . '" role="list">';
		foreach ( $items as $i => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			// Rows created without an icon key (AI, imports) get the field default; {"library":"none"} opts out.
			$row_icon = array_key_exists( 'icon', $item ) ? $item['icon'] : array( 'library' => 'lucide', 'value' => 'check' );
			$text     = $this->inline_html( $item['text'] ?? '' );
			$icon     = $this->has_icon( $row_icon ) ? $this->render_icon( $row_icon ) : '';
			if ( '' === trim( wp_strip_all_tags( $text ) ) && '' === $icon && ! $ctx->editor ) {
				continue;
			}
			$row_id = isset( $item['_id'] ) ? sanitize_html_class( (string) $item['_id'] ) : '';
			$inner  = ( '' !== $icon ? '<span class="uncoder-icon-list__icon">' . $icon . '</span>' : '' )
				. '<span class="uncoder-icon-list__text"' . $ctx->inline( 'items.' . $i . '.text' ) . '>' . $text . '</span>';

			echo '<li class="' . esc_attr( trim( 'uncoder-icon-list__item ' . ( '' !== $row_id ? 'uncoder-ri-' . $row_id : '' ) ) ) . '">';
			$link = $this->link_attrs( $item['link'] ?? array() );
			if ( $link ) {
				if ( ! empty( $s['new_tab'] ) && ! isset( $link['target'] ) && ! preg_match( '/^(mailto|tel|sms):|^#/i', (string) $link['href'] ) ) {
					$link['target'] = '_blank';
					$link['rel']    = trim( ( $link['rel'] ?? '' ) . ' noopener' );
				}
				$link['class'] = 'uncoder-icon-list__inner';
				echo '<a' . Utils::attrs( $link ) . '>' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes, parts escaped.
			} else {
				echo '<span class="uncoder-icon-list__inner">' . $inner . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
