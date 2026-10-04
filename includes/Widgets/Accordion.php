<?php
/**
 * Accordion widget (nested: every item owns a container).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * <details>/<summary> items (native keyboard support, find-in-page, works without JS) whose panels
 * are containers; the "accordion" module adds height animation and the single-open behaviour.
 */
class Accordion extends Widget_Base {

	private const ROOT    = '{{WRAPPER}}';
	private const ITEM    = '{{WRAPPER}} > .uncoder-accordion__item';
	private const HEADER  = '{{WRAPPER}} > .uncoder-accordion__item > .uncoder-accordion__header';
	private const CONTENT = '{{WRAPPER}} > .uncoder-accordion__item > .uncoder-accordion__panel > .uncoder-accordion__content';

	/** Open state (also while the JS module animates a closing item). */
	private const OPEN = '[open]:not(.is-closing)';

	/** <summary> accepts phrasing and heading content only. */
	public const TITLE_TAGS = array(
		'span' => 'Default (span)',
		'h2'   => 'H2',
		'h3'   => 'H3',
		'h4'   => 'H4',
		'h5'   => 'H5',
		'h6'   => 'H6',
	);

	public function name(): string {
		return 'accordion';
	}

	public function title(): string {
		return __( 'Accordion', 'uncoder' );
	}

	public function icon(): string {
		return 'list-collapse';
	}

	public function category(): string {
		return 'layout';
	}

	public function keywords(): array {
		return array( 'accordion', 'faq', 'toggle', 'collapse', 'questions', 'nested', 'details' );
	}

	public function description(): string {
		return __( 'Collapsible items whose panels are containers holding any widgets; optional FAQPage structured data. One row in "items" = one child container.', 'uncoder' );
	}

	public function nested(): ?array {
		return array( 'items' => 'items' );
	}

	public function frontend_scripts(): array {
		return array( 'accordion' );
	}

	public function preset(): array {
		return array( 'items' => $this->default_items() );
	}

	/**
	 * @return array<int, array<string,string>>
	 */
	private function default_items(): array {
		return array(
			array( 'title' => __( 'What is included in every plan?', 'uncoder' ) ),
			array( 'title' => __( 'Can I switch plans later?', 'uncoder' ) ),
			array( 'title' => __( 'How does billing work?', 'uncoder' ) ),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Accordion', 'uncoder' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Items', 'uncoder' ),
				'title_field' => 'title',
				'fields'      => array(
					'title' => array(
						'type'    => 'text',
						'label'   => __( 'Title', 'uncoder' ),
						'html'    => 'inline',
						'default' => __( 'Accordion item', 'uncoder' ),
						'inline'  => true,
					),
					'icon'  => array( 'type' => 'icon', 'label' => __( 'Icon', 'uncoder' ) ),
				),
				'default'     => $this->default_items(),
				'ai'          => 'Each row owns the child container at the same index (children[i]) holding the answer. Add/remove rows together with children.',
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'span',
				'options' => self::TITLE_TAGS,
				'ai'      => 'Use h3 (or the level that fits the page outline) when the questions should be headings.',
			)
		);
		$this->add_control(
			'first_open',
			array(
				'type'    => 'switch',
				'label'   => __( 'First item open', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'multiple',
			array(
				'type'  => 'switch',
				'label' => __( 'Allow several open items', 'uncoder' ),
			)
		);
		$this->add_control(
			'icon',
			array(
				'type'    => 'icon',
				'label'   => __( 'Icon', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'plus' ),
				'ai'      => 'Typical pairs: plus/minus, chevron-down with no active icon (it rotates).',
			)
		);
		$this->add_control(
			'active_icon',
			array(
				'type'        => 'icon',
				'label'       => __( 'Active icon', 'uncoder' ),
				'description' => __( 'None: the icon turns when the item opens (see Open rotation).', 'uncoder' ),
				'default'     => array( 'library' => 'lucide', 'value' => 'minus' ),
			)
		);
		$this->add_control(
			'open_rotation',
			array(
				'type'        => 'slider',
				'label'       => __( 'Open rotation', 'uncoder' ),
				'description' => __( 'How far the icon turns when its item opens, with no active icon: 180° flips a chevron, 45° turns a plus into a cross.', 'uncoder' ),
				'size_units'  => array( 'deg' ),
				'range'       => array( 'deg' => array( 'min' => -360, 'max' => 360 ) ),
				'selectors'   => array( self::ROOT => '--uncoder-acc-icon-rotate: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_position',
			array(
				'type'    => 'choose',
				'label'   => __( 'Icon position', 'uncoder' ),
				'default' => 'end',
				'options' => array(
					'start' => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'arrow-left-to-line' ),
					'end'   => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
				),
			)
		);
		$this->add_control(
			'duration',
			array(
				'type'    => 'number',
				'label'   => __( 'Animation duration (ms)', 'uncoder' ),
				'min'     => 0,
				'max'     => 2000,
				'step'    => 50,
				'default' => 300,
			)
		);
		$this->add_control(
			'faq_schema',
			array(
				'type'        => 'switch',
				'label'       => __( 'FAQ schema', 'uncoder' ),
				'description' => __( 'Adds FAQPage structured data (JSON-LD) built from the titles and the text of the panels. Use it once per page, for real questions and answers.', 'uncoder' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: items */
		$this->start_section( 'style_items', array( 'label' => __( 'Items', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between items', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-acc-gap: {{VALUE}}' ),
				'ai'         => 'With a gap, style items as cards: item_background, item_border, item_radius, header_padding.',
			)
		);
		$this->add_group( 'item_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => self::ITEM ) );
		$this->add_group( 'item_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::ITEM ) );
		$this->add_control(
			'active_item_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Open item border color', 'uncoder' ),
				'selectors' => array( self::ITEM . self::OPEN => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'item_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( self::ITEM => 'border-radius: {{VALUE}}' ),
			)
		);
		// Space around the whole item (title and open panel together), apart from the title's own padding.
		$this->add_responsive_control(
			'item_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::ITEM => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'item_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::ITEM ) );
		$this->end_section();

		/* ---------------------------------------------------------------- Style: header */
		$this->start_section( 'style_header', array( 'label' => __( 'Title', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::HEADER ) );
		$this->add_responsive_control(
			'header_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::HEADER => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( self::HEADER . ' > .uncoder-accordion__title' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'header_states' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::HEADER => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'header_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::HEADER => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::HEADER . ':hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_header_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::HEADER . ':hover' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Open', 'uncoder' ) );
		$this->add_control(
			'active_title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::ITEM . self::OPEN . ' > .uncoder-accordion__header' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'active_header_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::ITEM . self::OPEN . ' > .uncoder-accordion__header' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();

		/* ---------------------------------------------------------------- Style: icon */
		$this->start_section( 'style_icon', array( 'label' => __( 'Icon', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 60 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-acc-icon-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-acc-icon-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-acc-icon-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'active_icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Open color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-acc-icon-color-active: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_box',
			array(
				'type'        => 'slider',
				'label'       => __( 'Icon box size', 'uncoder' ),
				'description' => __( 'Puts the icon in a tile of this size (with the background and radius below).', 'uncoder' ),
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 16, 'max' => 80 ) ),
				'selectors'   => array( self::ROOT => '--uncoder-acc-icon-box: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon background', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-acc-icon-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'active_icon_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Open icon background', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-acc-icon-bg-active: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Icon radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( self::ROOT => '--uncoder-acc-icon-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'item_icon_heading',
			array(
				'type'  => 'heading',
				'label' => __( 'Item icons', 'uncoder' ),
			)
		);
		$this->add_control(
			'item_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 60 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-acc-item-icon-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'item_icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-acc-item-icon-color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: content */
		$this->start_section( 'style_content', array( 'label' => __( 'Content', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'content_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => self::CONTENT ) );
		$this->add_responsive_control(
			'content_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( self::CONTENT => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'content_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::CONTENT ) );
		$this->end_section();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$duration = is_numeric( $s['duration'] ?? '' ) ? (int) $s['duration'] : 300;
		return array(
			'data-settings' => $this->json_attr(
				array(
					'multiple' => ! empty( $s['multiple'] ),
					'duration' => min( max( $duration, 0 ), 2000 ),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = Repeater_Rows::get( $this, 'items', $s['items'] ?? array() );
		if ( ! $rows ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-accordion-placeholder">' . esc_html__( 'Add an item to start building the accordion.', 'uncoder' ) . '</div>';
			}
			return;
		}

		$uid      = 'uncoder-acc-' . sanitize_html_class( $ctx->element_id );
		$tag      = Utils::tag( $s['title_tag'] ?? 'span', array_keys( self::TITLE_TAGS ), 'span' );
		$count    = count( $rows );
		$multiple = ! empty( $s['multiple'] );
		$has_open = $this->has_icon( $s['active_icon'] ?? null );
		$closed   = $this->has_icon( $s['icon'] ?? null ) ? $s['icon'] : 'plus';
		$icon     = '<span class="uncoder-accordion__icon" aria-hidden="true">'
			. $this->render_icon( $closed, array( 'class' => 'uncoder-accordion__icon-closed' ) )
			. ( $has_open ? $this->render_icon( $s['active_icon'], array( 'class' => 'uncoder-accordion__icon-open' ) ) : '' )
			. '</span>';
		$classes  = array( 'uncoder-accordion', 'uncoder-accordion--icon-' . ( 'start' === ( $s['icon_position'] ?? 'end' ) ? 'start' : 'end' ) );
		if ( ! $has_open ) {
			$classes[] = 'uncoder-accordion--rotate';
		}
		$schema = ! empty( $s['faq_schema'] ) && ! $ctx->editor;
		$faq    = array();

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		foreach ( $rows as $i => $row ) {
			$n       = $i + 1;
			$open    = 0 === $i && ! empty( $s['first_open'] );
			$rid     = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );
			$title   = $this->inline_html( $row['title'] ?? '' );
			$header  = $uid . '-header-' . $n;
			$content = $ctx->render_child( $i );

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
			echo '<details' . Utils::attrs(
				array(
					'id'    => $uid . '-' . $n,
					'class' => array( 'uncoder-accordion__item', '' !== $rid ? 'uncoder-ri-' . $rid : '' ),
					'name'  => $multiple ? null : $uid,
					'open'  => $open,
				)
			) . '>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<summary class="uncoder-accordion__header" id="' . esc_attr( $header ) . '">';
			$item_icon = $this->has_icon( $row['icon'] ?? null ) ? $this->render_icon( $row['icon'], array( 'class' => 'uncoder-accordion__item-icon' ) ) : '';
			$title_el  = '<' . $tag . ' class="uncoder-accordion__title"' . $ctx->inline( 'items.' . $i . '.title' ) . '>' . $title . '</' . $tag . '>';
			echo $item_icon . $title_el . $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is allow-listed, parts escaped; order is set by CSS.
			echo '</summary>';
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
			echo '<div' . Utils::attrs(
				array(
					'class'           => 'uncoder-accordion__panel',
					'role'            => $count <= 6 ? 'region' : null,
					'aria-labelledby' => $count <= 6 ? $header : null,
				)
			) . '><div class="uncoder-accordion__content">';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered child container.
			echo '</div></div></details>';

			if ( $schema ) {
				$question = self::plain_text( $title );
				$answer   = self::plain_text( $content );
				if ( '' !== $question && '' !== $answer ) {
					$faq[] = array(
						'@type'          => 'Question',
						'name'           => $question,
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $answer,
						),
					);
				}
			}
		}
		echo '</div>';

		if ( $faq ) {
			$json = wp_json_encode(
				array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => $faq,
				),
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
			if ( $json ) {
				wp_print_inline_script_tag( $json, array( 'type' => 'application/ld+json' ) );
			}
		}
	}

	/**
	 * Visible text of rendered HTML, whitespace collapsed.
	 */
	private static function plain_text( string $html ): string {
		$html = (string) preg_replace( '#<(script|style|template|button|svg)\b[^>]*>.*?</\1>#is', ' ', $html );
		// Keep words apart where blocks meet ("</p><p>"), not around inline tags ("<strong>").
		$html = (string) preg_replace( '#<(/?(?:p|div|br|li|ul|ol|h[1-6]|tr|td|th|table|blockquote|figcaption|section|article|dd|dt|details|summary)\b)#i', ' <$1', $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
