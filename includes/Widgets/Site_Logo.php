<?php
/**
 * Site logo widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * The site logo (Customizer "custom_logo") or a custom image, linked to the home page.
 * Falls back to the site title when no logo is set.
 */
class Site_Logo extends Widget_Base {

	public function name(): string {
		return 'site-logo';
	}

	public function title(): string {
		return __( 'Site Logo', 'uncoder' );
	}

	public function icon(): string {
		return 'badge';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'logo', 'brand', 'site', 'identity', 'header' );
	}

	public function description(): string {
		return __( 'The site logo from Site Identity (or a custom image) linked to the home page; shows the site title when no logo is set. Use it in headers and footers.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Logo', 'uncoder' ) ) );
		$this->add_control(
			'source',
			array(
				'type'    => 'select',
				'label'   => __( 'Logo', 'uncoder' ),
				'default' => 'site',
				'options' => array(
					'site'   => __( 'Site logo (Site Identity)', 'uncoder' ),
					'custom' => __( 'Custom image', 'uncoder' ),
				),
				'ai'      => 'Keep "site" so the logo follows the site settings; use "custom" for an alternate version, e.g. a light logo on a dark footer.',
			)
		);
		$this->add_control(
			'image',
			array(
				'type'      => 'media',
				'label'     => __( 'Image', 'uncoder' ),
				'default'   => array( 'id' => 0, 'url' => '' ),
				'condition' => array( 'source' => 'custom' ),
				'dynamic'   => true,
			)
		);
		$this->add_control(
			'size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'full',
				'options_dynamic' => true,
				'options'         => array(
					'thumbnail' => __( 'Thumbnail', 'uncoder' ),
					'medium'    => __( 'Medium', 'uncoder' ),
					'large'     => __( 'Large', 'uncoder' ),
					'full'      => __( 'Full', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'alt',
			array(
				'type'        => 'text',
				'label'       => __( 'Alt text', 'uncoder' ),
				'description' => __( 'Defaults to the image alt text, then the site title.', 'uncoder' ),
			)
		);
		$this->add_control(
			'link_to',
			array(
				'type'    => 'select',
				'label'   => __( 'Link', 'uncoder' ),
				'default' => 'home',
				'options' => array(
					'home'   => __( 'Home page', 'uncoder' ),
					'custom' => __( 'Custom URL', 'uncoder' ),
					''       => __( 'None', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'link',
			array(
				'type'      => 'url',
				'label'     => __( 'URL', 'uncoder' ),
				'condition' => array( 'link_to' => 'custom' ),
				'dynamic'   => true,
			)
		);
		$this->add_control(
			'fallback',
			array(
				'type'    => 'select',
				'label'   => __( 'When there is no logo', 'uncoder' ),
				'default' => 'title',
				'options' => array(
					'title' => __( 'Show the site title', 'uncoder' ),
					''      => __( 'Show nothing', 'uncoder' ),
				),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_logo', array( 'label' => __( 'Logo', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'vw', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 600 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-site-logo__img' => 'width: {{VALUE}}' ),
				'ai'         => 'Typical header logos are 120–200px wide.',
			)
		);
		$this->add_responsive_control(
			'max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'vw', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-site-logo__img' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'max_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max height', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'vh' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 300 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-site-logo__img' => 'max-height: {{VALUE}}' ),
				'ai'         => 'Limits tall logos in compact headers, e.g. {"size":48,"unit":"px"}.',
			)
		);
		$this->start_tabs( 'logo_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'opacity',
			array(
				'type'      => 'number',
				'label'     => __( 'Opacity', 'uncoder' ),
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.01,
				'selectors' => array( '{{WRAPPER}} .uncoder-site-logo__img' => 'opacity: {{VALUE}}' ),
			)
		);
		$this->add_group( 'filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-site-logo__img' ) );
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
				'selectors' => array( '{{WRAPPER}} .uncoder-site-logo__link:hover .uncoder-site-logo__img' => 'opacity: {{VALUE}}' ),
			)
		);
		$this->add_group( 'hover_filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-site-logo__link:hover .uncoder-site-logo__img' ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-site-logo__img' ) );
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-site-logo__img' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-site-logo__img' ) );
		$this->end_section();

		$this->start_section(
			'style_fallback',
			array(
				'label'     => __( 'Site title fallback', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'fallback' => 'title' ),
			)
		);
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-site-logo__text' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-site-logo__text' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'title_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-site-logo__link:hover .uncoder-site-logo__text' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Logo media value for the settings.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<string,mixed>
	 */
	private function logo( array $s ): array {
		if ( 'custom' === ( $s['source'] ?? 'site' ) ) {
			return is_array( $s['image'] ?? null ) ? $s['image'] : array();
		}
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $id ) {
			return array();
		}
		$url = wp_get_attachment_image_url( $id, 'full' );
		return array(
			'id'  => $id,
			'url' => $url ? $url : '',
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$site  = (string) get_bloginfo( 'name', 'display' );
		$media = $this->logo( $s );
		$alt   = trim( (string) ( $s['alt'] ?? '' ) );
		if ( '' === $alt && ! empty( $media['id'] ) ) {
			$alt = trim( (string) get_post_meta( (int) $media['id'], '_wp_attachment_image_alt', true ) );
		}
		if ( '' === $alt ) {
			$alt = '' !== $site ? $site : __( 'Home', 'uncoder' );
		}
		$size = sanitize_key( (string) ( $s['size'] ?? 'full' ) );
		$img  = $this->image(
			$media,
			'' !== $size ? $size : 'full',
			array(
				'class'   => 'uncoder-site-logo__img',
				'alt'     => $alt,
				'loading' => 'eager',
			)
		);

		$inner = $img;
		// Transparent header with its own light logo: print both, CSS shows the one for the current state.
		$alt_logo = \Uncoder\Builder\Theme\Header_Behavior::$transparent_logo;
		if ( '' !== $img && $alt_logo && ! $ctx->editor ) {
			$alt_img = $this->image(
				array( 'id' => $alt_logo ),
				'' !== $size ? $size : 'full',
				array(
					'class'   => 'uncoder-site-logo__img uncoder-site-logo__img--alt',
					'alt'     => $alt,
					'loading' => 'eager',
				)
			);
			if ( '' !== $alt_img ) {
				$inner = str_replace( 'class="uncoder-site-logo__img', 'class="uncoder-site-logo__img uncoder-site-logo__img--normal', $img ) . $alt_img;
			}
		}
		if ( '' === $inner ) {
			if ( 'title' === ( $s['fallback'] ?? 'title' ) && ( '' !== $site || $ctx->editor ) ) {
				$inner = '<span class="uncoder-site-logo__text">' . esc_html( '' !== $site ? $site : __( 'Site title', 'uncoder' ) ) . '</span>';
			} elseif ( $ctx->editor ) {
				$inner = '<img class="uncoder-site-logo__img uncoder-site-logo__img--placeholder" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
			} else {
				return;
			}
		}

		$link_to = (string) ( $s['link_to'] ?? 'home' );
		$link    = array();
		if ( 'home' === $link_to ) {
			$link = array(
				'href' => home_url( '/' ),
				'rel'  => 'home',
			);
			if ( ! $ctx->editor && is_front_page() && ! is_paged() ) {
				$link['aria-current'] = 'page';
			}
		} elseif ( 'custom' === $link_to ) {
			$link = $this->link_attrs( $s['link'] ?? array() );
		}

		echo '<div class="uncoder-site-logo">';
		if ( $link ) {
			$link['class'] = 'uncoder-site-logo__link';
			echo '<a' . Utils::attrs( $link ) . '>' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes; $inner is core image markup or escaped text.
		} else {
			echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup or escaped text.
		}
		echo '</div>';
	}
}
