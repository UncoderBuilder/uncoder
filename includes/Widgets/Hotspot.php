<?php
/**
 * Hotspot widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Gallery_Items;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * Image with positioned markers; each marker is a button that discloses a tooltip card
 * (or a plain link when it has no text).
 */
class Hotspot extends Widget_Base {

	private const ROOT    = '{{WRAPPER}}';
	private const MARKER  = '{{WRAPPER}} .uncoder-hotspot__marker';
	private const TOOLTIP = '{{WRAPPER}} .uncoder-hotspot__tooltip';

	public const POSITIONS = array( 'top', 'bottom', 'left', 'right' );

	public function name(): string {
		return 'hotspot';
	}

	public function title(): string {
		return __( 'Hotspot', 'uncoder' );
	}

	public function icon(): string {
		return 'map-pin';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'hotspot', 'image map', 'tooltip', 'pin', 'marker', 'points', 'product tour' );
	}

	public function description(): string {
		return __( 'Image with pulsing markers placed in % coordinates; each opens a tooltip with a title, text and link.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'hotspot' );
	}

	public function preset(): array {
		return array(
			'image'    => Gallery_Items::placeholders( 1 )[0],
			'hotspots' => $this->default_hotspots(),
		);
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private function default_hotspots(): array {
		return array(
			array(
				'label'   => __( 'Recycled aluminium frame', 'uncoder' ),
				'content' => __( 'Light, rigid and fully recyclable at the end of its life.', 'uncoder' ),
				'x'       => array( 'size' => 28, 'unit' => '%' ),
				'y'       => array( 'size' => 36, 'unit' => '%' ),
			),
			array(
				'label'   => __( 'All-day battery', 'uncoder' ),
				'content' => __( 'Up to 18 hours of use on a single charge.', 'uncoder' ),
				'x'       => array( 'size' => 64, 'unit' => '%' ),
				'y'       => array( 'size' => 28, 'unit' => '%' ),
			),
			array(
				'label'   => __( 'Fast charging', 'uncoder' ),
				'content' => __( '50% charge in 30 minutes with the included adapter.', 'uncoder' ),
				'x'       => array( 'size' => 50, 'unit' => '%' ),
				'y'       => array( 'size' => 70, 'unit' => '%' ),
			),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Image', 'uncoder' ) ) );
		$this->add_control(
			'image',
			array(
				'type'    => 'media',
				'label'   => __( 'Image', 'uncoder' ),
				'default' => array( 'id' => 0, 'url' => '' ),
				'dynamic' => true,
			)
		);
		$this->add_control(
			'size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'large',
				'options_dynamic' => true,
				'options'         => Gallery_Items::SIZES,
			)
		);
		$this->end_section();

		$this->start_section( 'hotspots_section', array( 'label' => __( 'Hotspots', 'uncoder' ) ) );
		$this->add_control(
			'hotspots',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Hotspots', 'uncoder' ),
				'title_field' => 'label',
				'fields'      => array(
					'label'     => array(
						'type'    => 'text',
						'label'   => __( 'Title', 'uncoder' ),
						'default' => __( 'Feature', 'uncoder' ),
						'ai'      => 'Also the accessible name of the marker.',
					),
					'content'   => array(
						'type'    => 'textarea',
						'label'   => __( 'Text', 'uncoder' ),
						'rows'    => 3,
						'default' => __( 'Describe this detail in a sentence.', 'uncoder' ),
					),
					'x'         => array(
						'type'       => 'slider',
						'label'      => __( 'Horizontal position', 'uncoder' ),
						'responsive' => true,
						'size_units' => array( '%' ),
						'range'      => array( '%' => array( 'min' => 0, 'max' => 100, 'step' => 0.5 ) ),
						'default'    => array( 'size' => 50, 'unit' => '%' ),
						'selectors'  => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => '--uncoder-hs-x: {{SIZE}}%' ),
					),
					'y'         => array(
						'type'       => 'slider',
						'label'      => __( 'Vertical position', 'uncoder' ),
						'responsive' => true,
						'size_units' => array( '%' ),
						'range'      => array( '%' => array( 'min' => 0, 'max' => 100, 'step' => 0.5 ) ),
						'default'    => array( 'size' => 50, 'unit' => '%' ),
						'selectors'  => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => '--uncoder-hs-y: {{SIZE}}%' ),
					),
					'icon'      => array(
						'type'    => 'icon',
						'label'   => __( 'Icon', 'uncoder' ),
						'default' => array( 'library' => 'lucide', 'value' => 'plus' ),
					),
					'link'      => array(
						'type'  => 'url',
						'label' => __( 'Link', 'uncoder' ),
					),
					'link_text' => array(
						'type'        => 'text',
						'label'       => __( 'Link text', 'uncoder' ),
						'default'     => __( 'Learn more', 'uncoder' ),
						'description' => __( 'Without text, the marker itself becomes the link.', 'uncoder' ),
					),
					'position'  => array(
						'type'    => 'select',
						'label'   => __( 'Tooltip position', 'uncoder' ),
						'options' => array(
							''       => __( 'Default', 'uncoder' ),
							'top'    => __( 'Top', 'uncoder' ),
							'bottom' => __( 'Bottom', 'uncoder' ),
							'left'   => __( 'Left', 'uncoder' ),
							'right'  => __( 'Right', 'uncoder' ),
						),
					),
				),
				'default'     => $this->default_hotspots(),
				'ai'          => 'x / y are percentages of the image width / height, e.g. {"size":40,"unit":"%"}.',
			)
		);
		$this->add_control(
			'trigger',
			array(
				'type'    => 'select',
				'label'   => __( 'Open tooltip on', 'uncoder' ),
				'default' => 'click',
				'options' => array(
					'click' => __( 'Click / tap', 'uncoder' ),
					'hover' => __( 'Hover and focus', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'tooltip_position',
			array(
				'type'    => 'select',
				'label'   => __( 'Tooltip position', 'uncoder' ),
				'default' => 'top',
				'options' => array(
					'top'    => __( 'Top', 'uncoder' ),
					'bottom' => __( 'Bottom', 'uncoder' ),
					'left'   => __( 'Left', 'uncoder' ),
					'right'  => __( 'Right', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'show_label',
			array(
				'type'        => 'switch',
				'label'       => __( 'Show title on the marker', 'uncoder' ),
				'description' => __( 'Otherwise the title is only read by screen readers and shown in the tooltip.', 'uncoder' ),
			)
		);
		$this->add_control(
			'pulse',
			array(
				'type'    => 'switch',
				'label'   => __( 'Pulse animation', 'uncoder' ),
				'default' => true,
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: image */
		$this->start_section( 'style_image', array( 'label' => __( 'Image', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-hotspot__img' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'image_filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-hotspot__img' ) );
		$this->end_section();

		/* ---------------------------------------------------------------- Style: markers */
		$this->start_section( 'style_marker', array( 'label' => __( 'Markers', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'marker_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 16, 'max' => 96 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-hs-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'marker_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 60 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-hs-icon: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'marker_typography',
			array(
				'type'      => 'typography',
				'label'     => __( 'Title typography', 'uncoder' ),
				'condition' => array( 'show_label' => 'yes' ),
				'selector'  => self::MARKER,
			)
		);
		$this->start_tabs( 'marker_states' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'marker_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-hs-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'marker_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-hs-bg: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'active', __( 'Hover / open', 'uncoder' ) );
		$this->add_control(
			'marker_active_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-hs-color-active: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'marker_active_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-hs-bg-active: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_group( 'marker_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::MARKER ) );
		$this->add_responsive_control(
			'marker_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( self::MARKER => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'marker_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::MARKER ) );
		$this->add_control(
			'pulse_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Pulse color', 'uncoder' ),
				'condition' => array( 'pulse' => 'yes' ),
				'selectors' => array( self::ROOT => '--uncoder-hs-pulse: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: tooltip */
		$this->start_section( 'style_tooltip', array( 'label' => __( 'Tooltip', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'tooltip_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 120, 'max' => 480 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-hs-tip-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'tooltip_offset',
			array(
				'type'       => 'slider',
				'label'      => __( 'Distance from marker', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-hs-tip-offset: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'tooltip_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::ROOT => '--uncoder-hs-tip-bg: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'tooltip_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::TOOLTIP => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'tooltip_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( self::TOOLTIP => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'tooltip_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::TOOLTIP ) );
		$this->add_group( 'tooltip_title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => self::TOOLTIP . ' .uncoder-hotspot__title' ) );
		$this->add_control(
			'tooltip_title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Title color', 'uncoder' ),
				'selectors' => array( self::TOOLTIP . ' .uncoder-hotspot__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'tooltip_text_typography', array( 'type' => 'typography', 'label' => __( 'Text typography', 'uncoder' ), 'selector' => self::TOOLTIP . ' .uncoder-hotspot__text' ) );
		$this->add_control(
			'tooltip_text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( self::TOOLTIP . ' .uncoder-hotspot__text' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'tooltip_link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( self::TOOLTIP . ' .uncoder-hotspot__link' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array( 'data-settings' => $this->json_attr( array( 'trigger' => 'hover' === ( $s['trigger'] ?? 'click' ) ? 'hover' : 'click' ) ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$img = $this->image( $s['image'] ?? array(), sanitize_key( (string) ( $s['size'] ?? 'large' ) ), array( 'class' => 'uncoder-hotspot__img' ) );
		if ( '' === $img ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$img = '<img class="uncoder-hotspot__img" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
		}

		$uid     = 'uncoder-hotspot-' . sanitize_html_class( $ctx->element_id );
		$trigger = 'hover' === ( $s['trigger'] ?? 'click' ) ? 'hover' : 'click';
		$default = in_array( $s['tooltip_position'] ?? 'top', self::POSITIONS, true ) ? $s['tooltip_position'] : 'top';
		$classes = array( 'uncoder-hotspot', 'uncoder-hotspot--' . $trigger );
		if ( ! empty( $s['pulse'] ) ) {
			$classes[] = 'uncoder-hotspot--pulse';
		}
		if ( ! empty( $s['show_label'] ) ) {
			$classes[] = 'uncoder-hotspot--labels';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
		$rows = Repeater_Rows::get( $this, 'hotspots', $s['hotspots'] ?? array() );
		foreach ( $rows as $i => $row ) {
			$n        = $i + 1;
			$rid      = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );
			$label    = trim( (string) ( $row['label'] ?? '' ) );
			$text     = trim( (string) ( $row['content'] ?? '' ) );
			$link     = $this->link_attrs( $row['link'] ?? array() );
			$link_txt = trim( (string) ( $row['link_text'] ?? '' ) );
			$position = in_array( $row['position'] ?? '', self::POSITIONS, true ) ? $row['position'] : $default;
			$icon     = $this->has_icon( $row['icon'] ?? null ) ? $this->render_icon( $row['icon'], array( 'class' => 'uncoder-hotspot__icon' ) ) : '';
			if ( '' === $label ) {
				/* translators: %d: hotspot number. */
				$label = sprintf( __( 'Hotspot %d', 'uncoder' ), $n );
			}
			$marker_inner = $icon . '<span class="' . ( ! empty( $s['show_label'] ) ? 'uncoder-hotspot__label' : 'uncoder-hotspot__label uncoder-sr-only' ) . '">' . esc_html( $label ) . '</span>';
			$item_classes = array( 'uncoder-hotspot__item', 'uncoder-hotspot__item--' . $position, '' !== $rid ? 'uncoder-ri-' . $rid : '' );

			echo '<div' . Utils::attrs( array( 'class' => $item_classes ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
			$has_tip = '' !== $text || ( $link && '' !== $link_txt );
			if ( ! $has_tip && $link ) {
				// Nothing to disclose: the marker is the link.
				$link['class'] = 'uncoder-hotspot__marker';
				echo '<a' . Utils::attrs( $link ) . '>' . $marker_inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			} elseif ( ! $has_tip ) {
				echo '<span class="uncoder-hotspot__marker" role="img" aria-label="' . esc_attr( $label ) . '">' . $icon . ( ! empty( $s['show_label'] ) ? '<span class="uncoder-hotspot__label" aria-hidden="true">' . esc_html( $label ) . '</span>' : '' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			} else {
				$tip_id = $uid . '-tip-' . $n;
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
				echo '<button' . Utils::attrs(
					array(
						'type'          => 'button',
						'class'         => 'uncoder-hotspot__marker',
						'aria-expanded' => 'false',
						'aria-controls' => $tip_id,
					)
				) . '>' . $marker_inner . '</button>';
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<div class="uncoder-hotspot__tooltip" id="' . esc_attr( $tip_id ) . '">';
				echo '<div class="uncoder-hotspot__title">' . esc_html( $label ) . '</div>';
				if ( '' !== $text ) {
					echo '<p class="uncoder-hotspot__text">' . nl2br( esc_html( $text ) ) . '</p>';
				}
				if ( $link && '' !== $link_txt ) {
					$link['class'] = 'uncoder-hotspot__link';
					echo '<a' . Utils::attrs( $link ) . '>' . esc_html( $link_txt ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
