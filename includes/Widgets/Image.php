<?php
/**
 * Image widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Site\Performance;

defined( 'ABSPATH' ) || exit;

/**
 * Responsive image with caption, link and lightbox.
 */
class Image extends Widget_Base {

	public function name(): string {
		return 'image';
	}

	public function title(): string {
		return __( 'Image', 'uncoder' );
	}

	public function icon(): string {
		return 'image';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'image', 'photo', 'picture', 'img', 'visual' );
	}

	public function description(): string {
		return __( 'A single image from the media library or a URL, with alt text, caption, link and lightbox.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'lightbox' );
	}

	/**
	 * @return array<string,string>
	 */
	public static function size_options(): array {
		$out = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			$out[ $size ] = ucwords( str_replace( array( '_', '-' ), ' ', $size ) );
		}
		$out['full'] = __( 'Full', 'uncoder' );
		return $out;
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
				'ai'      => 'Use upload_media / search_images first so the image lives in the media library, then pass {"id": attachment_id}. Always set a meaningful alt.',
			)
		);
		$this->add_control(
			'decorative',
			array(
				'type'        => 'switch',
				'label'       => __( 'Decorative', 'uncoder' ),
				'description' => __( 'For flourishes that add no information (dividers, squiggles, background art): screen readers skip it.', 'uncoder' ),
				'ai'          => 'true only for purely ornamental images (underline squiggles, dividers); they get alt="" and the audit does not ask for alt text.',
			)
		);
		$this->add_control(
			'alt',
			array(
				'type'        => 'text',
				'label'       => __( 'Alt text', 'uncoder' ),
				'description' => __( 'Overrides the media library alt text.', 'uncoder' ),
				'dynamic'     => true,
				'condition'   => array( 'decorative!' => true ),
			)
		);
		$this->add_control(
			'size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'large',
				'options_dynamic' => true,
				'options'         => array(
					'thumbnail'    => 'Thumbnail',
					'medium'       => 'Medium',
					'medium_large' => 'Medium large',
					'large'        => 'Large',
					'full'         => 'Full',
				),
			)
		);
		$this->add_control(
			'caption_source',
			array(
				'type'    => 'select',
				'label'   => __( 'Caption', 'uncoder' ),
				'options' => array(
					''           => __( 'None', 'uncoder' ),
					'attachment' => __( 'From media library', 'uncoder' ),
					'custom'     => __( 'Custom', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'caption',
			array(
				'type'      => 'text',
				'label'     => __( 'Custom caption', 'uncoder' ),
				'condition' => array( 'caption_source' => 'custom' ),
				'dynamic'   => true,
			)
		);
		$this->add_control(
			'link_to',
			array(
				'type'    => 'select',
				'label'   => __( 'Link', 'uncoder' ),
				'options' => array(
					''         => __( 'None', 'uncoder' ),
					'file'     => __( 'Image file (lightbox)', 'uncoder' ),
					'custom'   => __( 'Custom URL', 'uncoder' ),
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
			'loading',
			array(
				'type'    => 'select',
				'label'   => __( 'Loading', 'uncoder' ),
				'options' => array(
					''      => __( 'Lazy (default)', 'uncoder' ),
					'eager' => __( 'Eager, high priority (hero image)', 'uncoder' ),
				),
				'ai'      => 'Use "eager" only for the main image visible without scrolling.',
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

		$this->start_section( 'style_image', array( 'label' => __( 'Image', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( '%', 'px', 'vw', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} img' => 'width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( '%', 'px', 'vw', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} img' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}} img' => 'height: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'aspect_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Aspect ratio', 'uncoder' ),
				'options'   => array(
					''     => __( 'Original', 'uncoder' ),
					'1/1'  => '1:1',
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'21/9' => '21:9',
					'3/4'  => '3:4',
					'4/5'  => '4:5',
					'2/3'  => '2:3',
					'9/16' => '9:16',
				),
				'selectors' => array( '{{WRAPPER}} img' => 'aspect-ratio: {{VALUE}}; height: auto' ),
			)
		);
		$this->add_responsive_control(
			'object_fit',
			array(
				'type'      => 'select',
				'label'     => __( 'Object fit', 'uncoder' ),
				'options'   => array( '' => __( 'Default', 'uncoder' ), 'cover' => __( 'Cover', 'uncoder' ), 'contain' => __( 'Contain', 'uncoder' ), 'fill' => __( 'Fill', 'uncoder' ) ),
				'selectors' => array( '{{WRAPPER}} img' => 'object-fit: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'object_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Object position', 'uncoder' ),
				'options'   => \Uncoder\Builder\Controls\Groups\Background::POSITIONS,
				'condition' => array( 'object_fit' => array( 'cover', 'contain' ) ),
				'selectors' => array( '{{WRAPPER}} img' => 'object-position: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} img' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} img' ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} img' ) );
		$this->add_group( 'filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} img' ) );
		$this->add_control(
			'hover_effect',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover effect', 'uncoder' ),
				'options' => array(
					''          => __( 'None', 'uncoder' ),
					'zoom'      => __( 'Zoom', 'uncoder' ),
					'lift'      => __( 'Lift', 'uncoder' ),
					'grayscale' => __( 'Grayscale to color', 'uncoder' ),
					'dim'       => __( 'Dim', 'uncoder' ),
				),
			)
		);
		$this->end_section();

		$this->start_section( 'style_caption', array( 'label' => __( 'Caption', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'caption_source!' => '' ) ) );
		$this->add_group( 'caption_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} figcaption' ) );
		$this->add_control(
			'caption_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} figcaption' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'caption_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} figcaption' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$media = is_array( $s['image'] ?? null ) ? $s['image'] : array();
		$size  = sanitize_key( (string) ( $s['size'] ?? 'large' ) );
		$attrs = array( 'class' => 'uncoder-image__img' );
		if ( ! empty( $s['decorative'] ) ) {
			// Empty alt (not missing): assistive tech skips the image instead of reading its file name.
			$attrs['alt'] = '';
			$media['alt'] = '';
		} elseif ( isset( $s['alt'] ) && '' !== $s['alt'] ) {
			$attrs['alt'] = (string) $s['alt'];
		}
		// The first image of a page's first section is its likely LCP: never lazy-load it.
		if ( 'eager' === ( $s['loading'] ?? '' ) || ( 'lazy' !== ( $s['loading'] ?? '' ) && Performance::is_lcp( $ctx ) ) ) {
			$attrs['loading']       = 'eager';
			$attrs['fetchpriority'] = 'high';
		}
		$img = $this->image( $media, $size, $attrs );
		if ( '' === $img ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$img = '<img class="uncoder-image__img uncoder-image__img--placeholder" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
		}

		$link_to = $s['link_to'] ?? '';
		$link    = array();
		if ( 'file' === $link_to && ! empty( $media['url'] ) ) {
			$full = ! empty( $media['id'] ) ? wp_get_attachment_image_url( (int) $media['id'], 'full' ) : $media['url'];
			$link = array(
				'href'            => $full ? $full : $media['url'],
				'data-uncoder-lightbox' => 'image',
			);
		} elseif ( 'custom' === $link_to ) {
			$link = $this->link_attrs( $s['link'] ?? array() );
		}

		$caption = '';
		if ( 'attachment' === ( $s['caption_source'] ?? '' ) && ! empty( $media['id'] ) ) {
			$caption = wp_get_attachment_caption( (int) $media['id'] );
		} elseif ( 'custom' === ( $s['caption_source'] ?? '' ) ) {
			$caption = (string) ( $s['caption'] ?? '' );
		}

		$classes = 'uncoder-image' . ( ! empty( $s['hover_effect'] ) ? ' uncoder-image--hover-' . sanitize_html_class( (string) $s['hover_effect'] ) : '' );
		echo '<figure class="' . esc_attr( $classes ) . '">';
		if ( $link ) {
			$link['class'] = 'uncoder-image__link';
			echo '<a' . Utils::attrs( $link ) . '>' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes, $img from core/escaped.
		} else {
			echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
		}
		if ( '' !== $caption ) {
			echo '<figcaption class="uncoder-image__caption">' . esc_html( $caption ) . '</figcaption>';
		}
		echo '</figure>';
	}
}
