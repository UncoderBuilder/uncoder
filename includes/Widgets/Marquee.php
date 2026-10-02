<?php
/**
 * Marquee widget.
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
 * Endless ticker of short texts, CSS-only animation with a duplicated (aria-hidden, inert) copy.
 */
class Marquee extends Widget_Base {

	private const ROOT = '{{WRAPPER}}';
	private const ITEM = '{{WRAPPER}} .uncoder-marquee__item';

	public function name(): string {
		return 'marquee';
	}

	public function title(): string {
		return __( 'Marquee', 'uncoder' );
	}

	public function icon(): string {
		return 'move-horizontal';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'marquee', 'ticker', 'scrolling text', 'banner', 'announcement', 'loop' );
	}

	public function description(): string {
		return __( 'Endless horizontally scrolling line of short texts with icon separators; pauses on hover and stops for reduced motion.', 'uncoder' );
	}

	/**
	 * @return array<int, array<string,string>>
	 */
	private function default_items(): array {
		return array(
			array( 'text' => __( 'Free shipping over $50', 'uncoder' ) ),
			array( 'text' => __( '30-day returns', 'uncoder' ) ),
			array( 'text' => __( 'Carbon-neutral delivery', 'uncoder' ) ),
			array( 'text' => __( 'Secure checkout', 'uncoder' ) ),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Marquee', 'uncoder' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Items', 'uncoder' ),
				'title_field' => 'text',
				'fields'      => array(
					'text' => array(
						'type'    => 'text',
						'label'   => __( 'Text', 'uncoder' ),
						'html'    => 'inline',
						'default' => __( 'Marquee item', 'uncoder' ),
						'inline'  => true,
					),
					'icon' => array( 'type' => 'icon', 'label' => __( 'Icon', 'uncoder' ) ),
					'link' => array( 'type' => 'url', 'label' => __( 'Link', 'uncoder' ) ),
				),
				'default'     => $this->default_items(),
			)
		);
		$this->add_control(
			'separator',
			array(
				'type'    => 'icon',
				'label'   => __( 'Separator icon', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'sparkle' ),
			)
		);
		$this->add_control(
			'speed',
			array(
				'type'        => 'number',
				'label'       => __( 'Loop duration (seconds)', 'uncoder' ),
				'description' => __( 'Time for one full loop; lower is faster.', 'uncoder' ),
				'min'         => 2,
				'max'         => 300,
				'step'        => 1,
				'selectors'   => array( self::ROOT => '--uncoder-marquee-duration: {{VALUE}}s' ),
			)
		);
		$this->add_control(
			'direction',
			array(
				'type'    => 'choose',
				'label'   => __( 'Direction', 'uncoder' ),
				'default' => 'left',
				'options' => array(
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'arrow-left' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'arrow-right' ),
				),
			)
		);
		$this->add_control(
			'pause_on_hover',
			array(
				'type'    => 'switch',
				'label'   => __( 'Pause on hover', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'fade_edges',
			array(
				'type'  => 'switch',
				'label' => __( 'Fade edges', 'uncoder' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: text */
		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::ITEM ) );
		$this->start_tabs( 'text_states' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::ITEM => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Link color', 'uncoder' ),
				'description' => __( 'Items with a link.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}} a.uncoder-marquee__item:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_group( 'text_shadow', array( 'type' => 'text_shadow', 'label' => __( 'Text shadow', 'uncoder' ), 'selector' => self::ITEM ) );
		$this->add_control(
			'stroke',
			array(
				'type'        => 'color',
				'label'       => __( 'Outline text color', 'uncoder' ),
				'description' => __( 'Hollow letters: set the text color to transparent and pick an outline color.', 'uncoder' ),
				'selectors'   => array( self::ITEM => '-webkit-text-stroke: 1px {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 160 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-marquee-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Item icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 120 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-marquee-icon-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Item icon color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-marquee-icon-color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: separator */
		$this->start_section( 'style_separator', array( 'label' => __( 'Separator', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'separator_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 6, 'max' => 120 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-marquee-sep-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'separator_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-marquee-sep-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'fade_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Edge fade width', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 300 ) ),
				'condition'  => array( 'fade_edges' => 'yes' ),
				'selectors'  => array( self::ROOT => '--uncoder-marquee-fade: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = Repeater_Rows::get( $this, 'items', $s['items'] ?? array() );
		if ( ! $rows ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-marquee-placeholder">' . esc_html__( 'Add items to build the marquee.', 'uncoder' ) . '</div>';
			}
			return;
		}

		$classes = array( 'uncoder-marquee', 'uncoder-marquee--' . ( 'right' === ( $s['direction'] ?? 'left' ) ? 'right' : 'left' ) );
		if ( ! empty( $s['pause_on_hover'] ) ) {
			$classes[] = 'uncoder-marquee--pause';
		}
		if ( ! empty( $s['fade_edges'] ) ) {
			$classes[] = 'uncoder-marquee--fade';
		}
		$sep = $this->has_icon( $s['separator'] ?? null ) ? '<span class="uncoder-marquee__sep" aria-hidden="true">' . $this->render_icon( $s['separator'] ) . '</span>' : '';

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><div class="uncoder-marquee__track">';
		echo '<div class="uncoder-marquee__group">' . $this->items( $rows, $sep, $ctx, false ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '<div class="uncoder-marquee__group" aria-hidden="true" inert>' . $this->items( $rows, $sep, $ctx, true ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts; the copy only makes the loop seamless.
		echo '</div></div>';
	}

	/**
	 * One copy of the items.
	 *
	 * @param array<int, array<string,mixed>> $rows Rows.
	 */
	private function items( array $rows, string $sep, Render_Context $ctx, bool $copy ): string {
		$out = '';
		foreach ( $rows as $i => $row ) {
			$text = $this->inline_html( $row['text'] ?? '' );
			if ( '' === trim( wp_strip_all_tags( $text ) ) && ! $this->has_icon( $row['icon'] ?? null ) ) {
				continue;
			}
			$rid   = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );
			$icon  = $this->has_icon( $row['icon'] ?? null ) ? $this->render_icon( $row['icon'], array( 'class' => 'uncoder-marquee__icon' ) ) : '';
			$inner = $icon . '<span class="uncoder-marquee__text"' . ( $copy ? '' : $ctx->inline( 'items.' . $i . '.text' ) ) . '>' . $text . '</span>';
			$link  = $this->link_attrs( $row['link'] ?? array() );
			$class = array( 'uncoder-marquee__item', '' !== $rid ? 'uncoder-ri-' . $rid : '' );
			if ( $link && ! $ctx->editor ) {
				$link['class'] = $class;
				if ( $copy ) {
					$link['tabindex'] = '-1';
				}
				$out .= '<a' . Utils::attrs( $link ) . '>' . $inner . '</a>';
			} else {
				$out .= '<span' . Utils::attrs( array( 'class' => $class ) ) . '>' . $inner . '</span>';
			}
			$out .= $sep;
		}
		return $out;
	}
}
