<?php
/**
 * Social icons widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Brand_Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Links to social profiles (and email / phone / website) as brand-coloured icons.
 */
class Social_Icons extends Widget_Base {

	public const HOVER = array(
		''       => 'None',
		'grow'   => 'Grow',
		'lift'   => 'Lift',
		'shrink' => 'Shrink',
		'rotate' => 'Rotate',
	);

	public function name(): string {
		return 'social-icons';
	}

	public function title(): string {
		return __( 'Social Icons', 'uncoder' );
	}

	public function icon(): string {
		return 'share-2';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'social', 'icons', 'facebook', 'instagram', 'linkedin', 'x', 'twitter', 'youtube', 'follow' );
	}

	public function description(): string {
		return __( 'Icon links to social profiles, email, phone or a website, in brand colours or your own. Every icon has an accessible label.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Social icons', 'uncoder' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Icons', 'uncoder' ),
				'title_field' => 'network',
				'fields'      => array(
					'network' => array(
						'type'    => 'select',
						'label'   => __( 'Network', 'uncoder' ),
						'default' => 'facebook',
						'options' => Brand_Icons::options(),
					),
					'link'    => array(
						'type'    => 'url',
						'label'   => __( 'Link', 'uncoder' ),
						'dynamic' => true,
						'ai'      => 'Profile URL; use "mailto:name@example.com" for email and "tel:+15551234567" for phone.',
					),
					'icon'    => array(
						'type'      => 'icon',
						'label'     => __( 'Icon', 'uncoder' ),
						'default'   => array( 'library' => 'lucide', 'value' => 'link' ),
						'condition' => array( 'network' => 'custom' ),
					),
					'label'   => array(
						'type'        => 'text',
						'label'       => __( 'Label', 'uncoder' ),
						'description' => __( 'Accessible name (and visible label when labels are shown). Defaults to the network name.', 'uncoder' ),
					),
					'color'   => array(
						'type'        => 'color',
						'label'       => __( 'Color', 'uncoder' ),
						'description' => __( 'Overrides the brand or custom color for this icon.', 'uncoder' ),
						'selectors'   => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => '--uncoder-si-brand: {{VALUE}}; --uncoder-si-color: {{VALUE}}' ),
					),
				),
				'default'     => array(
					array(
						'_id'     => 'soc0001',
						'network' => 'facebook',
						'link'    => array( 'url' => 'https://www.facebook.com/' ),
					),
					array(
						'_id'     => 'soc0002',
						'network' => 'x',
						'link'    => array( 'url' => 'https://x.com/' ),
					),
					array(
						'_id'     => 'soc0003',
						'network' => 'instagram',
						'link'    => array( 'url' => 'https://www.instagram.com/' ),
					),
					array(
						'_id'     => 'soc0004',
						'network' => 'linkedin',
						'link'    => array( 'url' => 'https://www.linkedin.com/' ),
					),
				),
			)
		);
		$this->add_control(
			'view',
			array(
				'type'    => 'select',
				'label'   => __( 'View', 'uncoder' ),
				'default' => 'stacked',
				'options' => array(
					'stacked' => __( 'Filled shape', 'uncoder' ),
					'framed'  => __( 'Outlined shape', 'uncoder' ),
					'minimal' => __( 'Icon only', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'shape',
			array(
				'type'      => 'select',
				'label'     => __( 'Shape', 'uncoder' ),
				'default'   => 'circle',
				'options'   => array(
					'circle'  => __( 'Circle', 'uncoder' ),
					'rounded' => __( 'Rounded', 'uncoder' ),
					'square'  => __( 'Square', 'uncoder' ),
				),
				'condition' => array( 'view!' => 'minimal' ),
			)
		);
		$this->add_control(
			'color_mode',
			array(
				'type'    => 'choose',
				'label'   => __( 'Colors', 'uncoder' ),
				'default' => 'brand',
				'options' => array(
					'brand'  => array( 'label' => __( 'Brand', 'uncoder' ) ),
					'custom' => array( 'label' => __( 'Custom', 'uncoder' ) ),
				),
			)
		);
		$this->add_control(
			'show_labels',
			array(
				'type'  => 'switch',
				'label' => __( 'Show labels', 'uncoder' ),
			)
		);
		$this->add_control(
			'new_tab',
			array(
				'type'    => 'switch',
				'label'   => __( 'Open in a new tab', 'uncoder' ),
				'default' => true,
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
					'left'   => 'flex-start',
					'center' => 'center',
					'right'  => 'flex-end',
				),
				'selectors'            => array( '{{WRAPPER}}' => 'justify-content: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_icons', array( 'label' => __( 'Icons', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 100 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-si-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_padding',
			array(
				'type'       => 'slider',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
				'condition'  => array( 'view!' => 'minimal' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-si-pad: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'column-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Rows gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'row-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'border_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Outline width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
				'condition'  => array( 'view' => 'framed' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-si-border: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'condition'  => array( 'view!' => 'minimal' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-social-icons__link' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'color_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'primary_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Primary color', 'uncoder' ),
				'description' => __( 'Shape color (filled) or icon and outline color.', 'uncoder' ),
				'condition'   => array( 'color_mode' => 'custom' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-si-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'secondary_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Secondary color', 'uncoder' ),
				'description' => __( 'Icon color on a filled shape.', 'uncoder' ),
				'condition'   => array( 'view' => 'stacked' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-si-icon: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-social-icons__link:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_shape_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Shape color', 'uncoder' ),
				'condition' => array( 'view!' => 'minimal' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-social-icons__link:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Outline color', 'uncoder' ),
				'condition' => array( 'view' => 'framed' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-social-icons__link:is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_animation',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover animation', 'uncoder' ),
				'options' => self::HOVER,
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-social-icons__link' ) );
		$this->end_section();

		$this->start_section(
			'style_labels',
			array(
				'label'     => __( 'Labels', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_labels' => 'yes' ),
			)
		);
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-social-icons__label' ) );
		$this->add_responsive_control(
			'label_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-social-icons__link' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Turns bare email addresses / phone numbers into mailto: / tel: links.
	 */
	private function contact_url( string $network, string $url ): string {
		if ( 'email' === $network && ! preg_match( '/^mailto:/i', $url ) ) {
			$candidate = rawurldecode( (string) preg_replace( '#^https?://#i', '', $url ) );
			if ( is_email( $candidate ) ) {
				return 'mailto:' . $candidate;
			}
		}
		if ( 'phone' === $network && ! preg_match( '/^(tel|sms):/i', $url ) ) {
			$candidate = rawurldecode( (string) preg_replace( '#^https?://#i', '', $url ) );
			if ( preg_match( '/^\+?[0-9 ().\-]{5,}$/', $candidate ) ) {
				return 'tel:' . preg_replace( '/[^0-9+]/', '', $candidate );
			}
		}
		return $url;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$items = is_array( $s['items'] ?? null ) ? array_values( $s['items'] ) : array();
		if ( ! $items ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-social-icons__placeholder">' . esc_html__( 'Add social profiles in the Content tab.', 'uncoder' ) . '</p>';
			}
			return;
		}

		$view   = in_array( $s['view'] ?? 'stacked', array( 'stacked', 'framed', 'minimal' ), true ) ? (string) $s['view'] : 'stacked';
		$shape  = in_array( $s['shape'] ?? 'circle', array( 'circle', 'rounded', 'square' ), true ) ? (string) $s['shape'] : 'circle';
		$mode   = 'custom' === ( $s['color_mode'] ?? 'brand' ) ? 'custom' : 'brand';
		$labels = ! empty( $s['show_labels'] );

		$classes = array( 'uncoder-social-icons', 'uncoder-social-icons--' . $view, 'uncoder-social-icons--' . $mode );
		if ( 'minimal' !== $view ) {
			$classes[] = 'uncoder-social-icons--' . $shape;
		}
		if ( $labels ) {
			$classes[] = 'uncoder-social-icons--labels';
		}
		if ( ! empty( $s['hover_animation'] ) && isset( self::HOVER[ $s['hover_animation'] ] ) ) {
			$classes[] = 'uncoder-social-icons--hover-' . $s['hover_animation'];
		}

		$items_html = '';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$network = (string) ( $item['network'] ?? 'custom' );
			if ( ! Brand_Icons::exists( $network ) ) {
				$network = 'custom';
			}
			if ( 'custom' === $network ) {
				$svg = $this->has_icon( $item['icon'] ?? null ) ? $this->render_icon( $item['icon'], array( 'class' => 'uncoder-social-icons__svg' ) ) : $this->render_icon( 'link', array( 'class' => 'uncoder-social-icons__svg' ) );
			} else {
				$svg = Brand_Icons::svg( $network, 'uncoder-social-icons__svg' );
			}
			$label = trim( (string) ( $item['label'] ?? '' ) );
			if ( '' === $label ) {
				$label = Brand_Icons::label( $network );
			}

			$link = $this->link_attrs( $item['link'] ?? array() );
			if ( ! $link && ! $ctx->editor ) {
				continue;
			}
			$inner = $svg . ( $labels ? '<span class="uncoder-social-icons__label">' . esc_html( $label ) . '</span>' : '' );
			$row   = isset( $item['_id'] ) ? sanitize_html_class( (string) $item['_id'] ) : '';

			$items_html .= '<li class="' . esc_attr( trim( 'uncoder-social-icons__item uncoder-social-icons__item--' . $network . ( '' !== $row ? ' uncoder-ri-' . $row : '' ) ) ) . '">';
			if ( $link ) {
				$link['href']  = $this->contact_url( $network, (string) $link['href'] );
				$link['class'] = 'uncoder-social-icons__link';
				$is_web        = (bool) preg_match( '#^(https?:)?//#i', (string) $link['href'] );
				if ( ! empty( $s['new_tab'] ) && $is_web && ! isset( $link['target'] ) ) {
					$link['target'] = '_blank';
					$link['rel']    = trim( ( $link['rel'] ?? '' ) . ' noopener' );
				}
				if ( 'mastodon' === $network ) {
					$link['rel'] = trim( ( $link['rel'] ?? '' ) . ' me' );
				}
				$new_tab = isset( $link['target'] ) && '_blank' === $link['target'];
				if ( ! $labels ) {
					$link['aria-label'] = $new_tab
						/* translators: %s: network or link name. */
						? sprintf( __( '%s (opens in a new tab)', 'uncoder' ), $label )
						: $label;
				} elseif ( $new_tab ) {
					$inner .= '<span class="uncoder-sr-only"> ' . esc_html__( '(opens in a new tab)', 'uncoder' ) . '</span>';
				}
				$items_html .= '<a' . Utils::attrs( $link ) . '>' . $inner . '</a>';
			} else {
				// Editor only: an item without a link yet.
				$items_html .= '<span class="uncoder-social-icons__link" role="img" aria-label="' . esc_attr( $label ) . '">' . $inner . '</span>';
			}
			$items_html .= '</li>';
		}

		if ( '' === $items_html ) {
			return;
		}
		echo '<ul class="' . esc_attr( implode( ' ', $classes ) ) . '" role="list">' . $items_html . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
