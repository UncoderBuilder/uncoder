<?php
/**
 * Logo grid widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Gallery_Items;

defined( 'ABSPATH' ) || exit;

/**
 * Client / partner logos as a grid or an endless CSS marquee (shares widgets/marquee.css).
 */
class Logo_Grid extends Widget_Base {

	private const ROOT = '{{WRAPPER}}';
	private const ITEM = '{{WRAPPER}} .uncoder-logo-grid__item';

	public function name(): string {
		return 'logo-grid';
	}

	public function title(): string {
		return __( 'Logo Grid', 'uncoder' );
	}

	public function icon(): string {
		return 'grid-3x3';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'logos', 'clients', 'partners', 'brands', 'trusted by', 'marquee', 'ticker' );
	}

	public function description(): string {
		return __( 'Client or partner logos as a grid (optionally with divider lines) or an endless scrolling marquee; grayscale until hovered.', 'uncoder' );
	}

	/** Loads the images of the moving strip once it nears the screen (frontend/modules/marquee.ts). */
	public function frontend_scripts(): array {
		return array( 'marquee' );
	}

	public function frontend_styles(): array {
		return array( 'marquee' );
	}

	public function preset(): array {
		return array(
			'logos'          => Gallery_Items::placeholders( 6 ),
			'columns'        => 6,
			'columns_tablet' => 3,
			'columns_mobile' => 2,
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Logos', 'uncoder' ) ) );
		$this->add_control(
			'logos',
			array(
				'type'    => 'gallery',
				'label'   => __( 'Logos', 'uncoder' ),
				'default' => array(),
				'ai'      => 'SVG or transparent PNG logos. The alt text (or the media title) should be the company name.',
			)
		);
		$this->add_control(
			'mode',
			array(
				'type'    => 'choose',
				'label'   => __( 'Display', 'uncoder' ),
				'default' => 'grid',
				'options' => array(
					'grid'    => array( 'label' => __( 'Grid', 'uncoder' ), 'icon' => 'layout-grid' ),
					'marquee' => array( 'label' => __( 'Marquee', 'uncoder' ), 'icon' => 'move-horizontal' ),
				),
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'type'      => 'number',
				'label'     => __( 'Columns', 'uncoder' ),
				'min'       => 1,
				'max'       => 12,
				'step'      => 1,
				'condition' => array( 'mode' => 'grid' ),
				'selectors' => array( self::ROOT => '--uncoder-logos-cols: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'dividers',
			array(
				'type'      => 'switch',
				'label'     => __( 'Divider lines', 'uncoder' ),
				'condition' => array( 'mode' => 'grid' ),
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
				'condition'   => array( 'mode' => 'marquee' ),
				'selectors'   => array( self::ROOT => '--uncoder-marquee-duration: {{VALUE}}s' ),
			)
		);
		$this->add_control(
			'direction',
			array(
				'type'      => 'choose',
				'label'     => __( 'Direction', 'uncoder' ),
				'default'   => 'left',
				'options'   => array(
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'arrow-left' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'arrow-right' ),
				),
				'condition' => array( 'mode' => 'marquee' ),
			)
		);
		$this->add_control(
			'pause_on_hover',
			array(
				'type'      => 'switch',
				'label'     => __( 'Pause on hover', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'mode' => 'marquee' ),
			)
		);
		$this->add_control(
			'fade_edges',
			array(
				'type'      => 'switch',
				'label'     => __( 'Fade edges', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'mode' => 'marquee' ),
			)
		);
		$this->add_control(
			'size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'medium',
				'options_dynamic' => true,
				'options'         => Gallery_Items::SIZES,
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: logos */
		$this->start_section( 'style_logos', array( 'label' => __( 'Logos', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'logo_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Logo max height', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 200 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-logos-h: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'logo_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Logo max width', 'uncoder' ),
				'size_units' => array( '%', 'px' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 400 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-logos-w: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 160 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-logos-gap: {{VALUE}};--uncoder-marquee-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'item_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Cell padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::ITEM => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'grayscale',
			array(
				'type'    => 'switch',
				'label'   => __( 'Grayscale until hovered', 'uncoder' ),
				'default' => true,
			)
		);
		$this->start_tabs( 'logo_states' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.01,
				'selectors' => array( self::ROOT => '--uncoder-logos-opacity: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.01,
				'selectors' => array( self::ROOT => '--uncoder-logos-opacity-hover: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();

		/* ---------------------------------------------------------------- Style: cells */
		$this->start_section( 'style_cells', array( 'label' => __( 'Cells', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'item_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::ITEM => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'item_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::ITEM ) );
		$this->add_responsive_control(
			'item_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( self::ITEM => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'condition' => array(
					'mode'     => 'grid',
					'dividers' => 'yes',
				),
				'selectors' => array( self::ROOT => '--uncoder-logos-divider: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Divider width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 8 ) ),
				'condition'  => array(
					'mode'     => 'grid',
					'dividers' => 'yes',
				),
				'selectors'  => array( self::ROOT => '--uncoder-logos-divider-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'fade_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Edge fade width', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 300 ) ),
				'condition'  => array(
					'mode'       => 'marquee',
					'fade_edges' => 'yes',
				),
				'selectors'  => array( self::ROOT => '--uncoder-marquee-fade: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$logos = Gallery_Items::items( $s['logos'] ?? array() );
		if ( ! $logos ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-logo-grid-placeholder">' . esc_html__( 'Choose logos to build the logo grid.', 'uncoder' ) . '</div>';
			}
			return;
		}

		$size    = sanitize_key( (string) ( $s['size'] ?? 'medium' ) );
		$marquee = 'marquee' === ( $s['mode'] ?? 'grid' );
		$classes = array( 'uncoder-logo-grid', 'uncoder-logo-grid--' . ( $marquee ? 'marquee' : 'grid' ) );
		if ( ! empty( $s['grayscale'] ) ) {
			$classes[] = 'uncoder-logo-grid--grayscale';
		}
		if ( $marquee ) {
			$classes[] = 'uncoder-marquee';
			$classes[] = 'uncoder-marquee--' . ( 'right' === ( $s['direction'] ?? 'left' ) ? 'right' : 'left' );
			if ( ! empty( $s['pause_on_hover'] ) ) {
				$classes[] = 'uncoder-marquee--pause';
			}
			if ( ! empty( $s['fade_edges'] ) ) {
				$classes[] = 'uncoder-marquee--fade';
			}
		} elseif ( ! empty( $s['dividers'] ) ) {
			$classes[] = 'uncoder-logo-grid--dividers';
		}

		$items = '';
		foreach ( $logos as $media ) {
			$alt = Gallery_Items::alt( $media );
			if ( '' === $alt && ! empty( $media['id'] ) ) {
				// Logos carry the company name: fall back to the media title.
				$alt = trim( (string) get_the_title( (int) $media['id'] ) );
			}
			$img = $this->image( array_merge( $media, array( 'alt' => $alt ) ), $size, array( 'class' => 'uncoder-logo-grid__img' ) );
			if ( '' !== $img ) {
				$items .= '<li class="uncoder-logo-grid__item">' . $img . '</li>';
			}
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( $marquee ) {
			echo '<div class="uncoder-marquee__track">';
			echo '<ul class="uncoder-marquee__group uncoder-logo-grid__list" role="list">' . $items . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup; the duplicate only makes the loop seamless.
			echo '<ul' . Utils::attrs(
				array(
					'class'       => 'uncoder-marquee__group uncoder-logo-grid__list',
					'role'        => 'list',
					'aria-hidden' => 'true',
					'inert'       => true,
				)
			) . '>' . $items . '</ul>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
		} else {
			echo '<ul class="uncoder-logo-grid__list" role="list">' . $items . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
		}
		echo '</div>';
	}
}
