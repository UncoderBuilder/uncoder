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
 * Endless ticker of texts, texts with icons and images, horizontal or vertical, in one or more rows (columns when
 * vertical). CSS-only animation with a duplicated (aria-hidden, inert) copy; extra rows are decorative copies.
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
		return array( 'marquee', 'ticker', 'scrolling text', 'banner', 'announcement', 'loop', 'logos', 'images', 'vertical' );
	}

	/** Loads the images of the moving strip once it nears the screen (frontend/modules/marquee.ts). */
	public function frontend_scripts(): array {
		return array( 'marquee' );
	}

	public function description(): string {
		return __( 'Endless scrolling strip of texts, texts with icons and images (mixed), moving left, right, up or down, in up to three rows or columns; pauses on hover and stops for reduced motion.', 'uncoder' );
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
					'type'         => array(
						'type'    => 'choose',
						'label'   => __( 'Item', 'uncoder' ),
						'default' => 'text',
						'options' => array(
							'text'  => array( 'label' => __( 'Text', 'uncoder' ), 'icon' => 'type' ),
							'image' => array( 'label' => __( 'Image', 'uncoder' ), 'icon' => 'image' ),
						),
					),
					'text'         => array(
						'type'      => 'text',
						'label'     => __( 'Text', 'uncoder' ),
						'html'      => 'inline',
						'default'   => __( 'Marquee item', 'uncoder' ),
						'inline'    => true,
						'condition' => array( 'type!' => 'image' ),
					),
					'icon'         => array(
						'type'        => 'icon',
						'label'       => __( 'Icon', 'uncoder' ),
						'description' => __( 'Optional, shown before the text.', 'uncoder' ),
						'condition'   => array( 'type!' => 'image' ),
					),
					'image'        => array(
						'type'      => 'media',
						'label'     => __( 'Image', 'uncoder' ),
						'condition' => array( 'type' => 'image' ),
					),
					'link'         => array( 'type' => 'url', 'label' => __( 'Link', 'uncoder' ) ),
					// Per item: a brand word in its own colour, or hollow letters next to filled ones.
					'item_color'   => array(
						'type'      => 'color',
						'label'     => __( 'Item color', 'uncoder' ),
						'condition' => array( 'type!' => 'image' ),
						'selectors' => array( '{{WRAPPER}} .uncoder-marquee__item{{CURRENT_ITEM}}' => 'color: {{VALUE}}' ),
					),
					'item_outline' => array(
						'type'        => 'color',
						'label'       => __( 'Hollow outline', 'uncoder' ),
						'description' => __( 'Outlined letters with no fill, in this color.', 'uncoder' ),
						'condition'   => array( 'type!' => 'image' ),
						'selectors'   => array( '{{WRAPPER}} .uncoder-marquee__item{{CURRENT_ITEM}}' => 'color: transparent; -webkit-text-stroke: 1px {{VALUE}}' ),
					),
				),
				'default'     => $this->default_items(),
				'ai'          => 'Rows: {"text":"…","icon":{"library":"lucide","value":"…"}} for text (icon optional) or {"type":"image","image":{"id":123}} for an image (logos, photos); mix freely. Optional "link"; per text row "item_color" and "item_outline" (hollow letters in that color).',
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
			'repeat',
			array(
				'type'        => 'number',
				'label'       => __( 'Repeat items', 'uncoder' ),
				'description' => __( 'For short content: the items appear this many times in a row, so the strip stays full at its gap instead of spreading them out. A longer row also takes longer to loop.', 'uncoder' ),
				'min'         => 1,
				'max'         => 10,
				'step'        => 1,
				'default'     => 1,
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
					'up'    => array( 'label' => __( 'Up', 'uncoder' ), 'icon' => 'arrow-up' ),
					'down'  => array( 'label' => __( 'Down', 'uncoder' ), 'icon' => 'arrow-down' ),
				),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'type'        => 'slider',
				'label'       => __( 'Height', 'uncoder' ),
				'description' => __( 'The visible height of an up / down marquee. Default: 420px.', 'uncoder' ),
				'size_units'  => array( 'px', 'vh', 'em' ),
				'range'       => array( 'px' => array( 'min' => 80, 'max' => 1200 ) ),
				'condition'   => array( 'direction' => array( 'up', 'down' ) ),
				'selectors'   => array( self::ROOT => '--uncoder-marquee-height: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'lanes',
			array(
				'type'        => 'choose',
				'label'       => __( 'Rows', 'uncoder' ),
				'description' => __( 'Columns when the marquee moves up or down. Each row shows all the items.', 'uncoder' ),
				'default'     => '1',
				'options'     => array(
					'1' => array( 'label' => '1' ),
					'2' => array( 'label' => '2' ),
					'3' => array( 'label' => '3' ),
				),
			)
		);
		$this->add_control(
			'alternate',
			array(
				'type'        => 'switch',
				'label'       => __( 'Alternate direction', 'uncoder' ),
				'description' => __( 'Every other row moves the opposite way.', 'uncoder' ),
				'default'     => true,
				'condition'   => array( 'lanes' => array( '2', '3' ) ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Item alignment', 'uncoder' ),
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
				'condition'            => array( 'direction' => array( 'up', 'down' ) ),
				'selectors'            => array( self::ROOT => '--uncoder-marquee-align: {{VALUE}}' ),
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
		$this->add_responsive_control(
			'lane_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between rows', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'condition'  => array( 'lanes' => array( '2', '3' ) ),
				'selectors'  => array( self::ROOT => '--uncoder-marquee-lane-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: images */
		$this->start_section( 'style_image', array( 'label' => __( 'Images', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'image_height',
			array(
				'type'        => 'slider',
				'label'       => __( 'Image height', 'uncoder' ),
				'description' => __( 'Left / right marquees. Default: 64px.', 'uncoder' ),
				'size_units'  => array( 'px', 'em', 'vh' ),
				'range'       => array( 'px' => array( 'min' => 16, 'max' => 600 ) ),
				'condition'   => array( 'direction!' => array( 'up', 'down' ) ),
				'selectors'   => array( self::ROOT => '--uncoder-marquee-img-h: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'image_width',
			array(
				'type'        => 'slider',
				'label'       => __( 'Image width', 'uncoder' ),
				'description' => __( 'Up / down marquees. Default: the full column width.', 'uncoder' ),
				'size_units'  => array( '%', 'px' ),
				'range'       => array( 'px' => array( 'min' => 16, 'max' => 800 ) ),
				'condition'   => array( 'direction' => array( 'up', 'down' ) ),
				'selectors'   => array( self::ROOT => '--uncoder-marquee-img-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'image_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Aspect ratio', 'uncoder' ),
				'options'   => array(
					''     => __( 'Original', 'uncoder' ),
					'1/1'  => '1:1',
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'3/4'  => '3:4',
					'2/3'  => '2:3',
				),
				'selectors' => array( self::ROOT => '--uncoder-marquee-img-ratio: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-marquee__img' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'image_gray',
			array(
				'type'        => 'switch',
				'label'       => __( 'Grey until hovered', 'uncoder' ),
				'description' => __( 'Logos and photos in greyscale that turn to colour under the pointer.', 'uncoder' ),
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

		$direction = in_array( $s['direction'] ?? 'left', array( 'left', 'right', 'up', 'down' ), true ) ? $s['direction'] : 'left';
		$vertical  = in_array( $direction, array( 'up', 'down' ), true );
		$lanes     = max( 1, min( 3, (int) ( $s['lanes'] ?? 1 ) ) );
		$alternate = $lanes > 1 && ( ! array_key_exists( 'alternate', $s ) || ! empty( $s['alternate'] ) );
		$opposite  = array(
			'left'  => 'right',
			'right' => 'left',
			'up'    => 'down',
			'down'  => 'up',
		);
		$sep       = $this->has_icon( $s['separator'] ?? null ) ? '<span class="uncoder-marquee__sep" aria-hidden="true">' . $this->render_icon( $s['separator'] ) . '</span>' : '';
		// Short content is repeated inside each group (the repeats are decoration, hidden from screen readers).
		$repeat    = max( 1, min( 10, (int) ( $s['repeat'] ?? 1 ) ) );
		$items     = $this->items( $rows, $sep, $ctx, false ) . str_repeat( $this->items( $rows, $sep, $ctx, true, true ), $repeat - 1 );
		$copy      = str_repeat( $this->items( $rows, $sep, $ctx, true ), $repeat );

		// The modifiers every lane shares.
		$shared = array();
		if ( $vertical ) {
			$shared[] = 'uncoder-marquee--vertical';
		}
		if ( ! empty( $s['fade_edges'] ) ) {
			$shared[] = 'uncoder-marquee--fade';
		}
		if ( ! empty( $s['image_gray'] ) ) {
			$shared[] = 'uncoder-marquee--gray';
		}
		$pause = ! empty( $s['pause_on_hover'] ) ? array( 'uncoder-marquee--pause' ) : array();

		if ( 1 === $lanes ) {
			$classes = array_merge( array( 'uncoder-marquee', 'uncoder-marquee--' . $direction ), $shared, $pause );
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $this->track( $items, $copy ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			return;
		}

		// Several rows (columns when vertical): the first is the real one, the others are decorative copies.
		$wrap = array_merge( array( 'uncoder-marquee-lanes', 'uncoder-marquee-lanes--' . ( $vertical ? 'columns' : 'rows' ) ), $pause );
		echo '<div class="' . esc_attr( implode( ' ', $wrap ) ) . '" style="--uncoder-marquee-lanes:' . (int) $lanes . '">';
		for ( $lane = 0; $lane < $lanes; $lane++ ) {
			$dir     = $alternate && 1 === $lane % 2 ? $opposite[ $direction ] : $direction;
			$classes = array_merge( array( 'uncoder-marquee', 'uncoder-marquee__lane', 'uncoder-marquee--' . $dir ), $shared );
			$attrs   = array(
				'class' => implode( ' ', $classes ),
				'style' => '--uncoder-marquee-lane:' . $lane,
			);
			if ( $lane > 0 ) {
				$attrs['aria-hidden'] = 'true';
				$attrs['inert']       = true;
			}
			echo '<div' . Utils::attrs( $attrs ) . '>' . $this->track( 0 === $lane ? $items : $copy, $copy ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}
		echo '</div>';
	}

	/**
	 * The moving track: the items, then a hidden copy that makes the loop seamless.
	 */
	private function track( string $items, string $copy ): string {
		return '<div class="uncoder-marquee__track"><div class="uncoder-marquee__group">' . $items . '</div><div class="uncoder-marquee__group" aria-hidden="true" inert>' . $copy . '</div></div>';
	}

	/**
	 * One copy of the items.
	 *
	 * @param array<int, array<string,mixed>> $rows Rows.
	 * @param bool                            $copy A decorative copy (no alt text, links out of the tab order).
	 * @param bool                            $hide Also hide each item from screen readers (repeats in the real group).
	 */
	private function items( array $rows, string $sep, Render_Context $ctx, bool $copy, bool $hide = false ): string {
		$out = '';
		foreach ( $rows as $i => $row ) {
			$rid   = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );
			$class = array( 'uncoder-marquee__item', '' !== $rid ? 'uncoder-ri-' . $rid : '' );
			if ( 'image' === ( $row['type'] ?? 'text' ) ) {
				// Copies are decoration: no alt text to read twice.
				$inner = $this->image( $row['image'] ?? null, 'large', array_merge( array( 'class' => 'uncoder-marquee__img' ), $copy ? array( 'alt' => '' ) : array() ) );
				if ( '' === $inner ) {
					if ( ! $ctx->editor ) {
						continue;
					}
					$inner = '<img class="uncoder-marquee__img" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
				}
				$class[] = 'uncoder-marquee__item--image';
			} else {
				$text = $this->inline_html( $row['text'] ?? '' );
				if ( '' === trim( wp_strip_all_tags( $text ) ) && ! $this->has_icon( $row['icon'] ?? null ) ) {
					continue;
				}
				$icon  = $this->has_icon( $row['icon'] ?? null ) ? $this->render_icon( $row['icon'], array( 'class' => 'uncoder-marquee__icon' ) ) : '';
				$inner = $icon . '<span class="uncoder-marquee__text"' . ( $copy ? '' : $ctx->inline( 'items.' . $i . '.text' ) ) . '>' . $text . '</span>';
			}
			$link = $this->link_attrs( $row['link'] ?? array() );
			if ( $link && ! $ctx->editor ) {
				$link['class'] = $class;
				if ( $copy ) {
					$link['tabindex'] = '-1';
				}
				if ( $hide ) {
					$link['aria-hidden'] = 'true';
				}
				$out .= '<a' . Utils::attrs( $link ) . '>' . $inner . '</a>';
			} else {
				$out .= '<span' . Utils::attrs( array_filter( array( 'class' => $class, 'aria-hidden' => $hide ? 'true' : '' ) ) ) . '>' . $inner . '</span>';
			}
			$out .= $sep;
		}
		return $out;
	}
}
