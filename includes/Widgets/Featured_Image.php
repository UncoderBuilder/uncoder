<?php
/**
 * Featured image widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Featured image (post thumbnail) of the current post, with ratio, link, caption and a fallback image.
 */
class Featured_Image extends Widget_Base {

	public const HOVER = array( 'zoom', 'lift', 'grayscale', 'dim' );

	public function name(): string {
		return 'featured-image';
	}

	public function title(): string {
		return __( 'Featured Image', 'uncoder' );
	}

	public function icon(): string {
		return 'file-image';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'featured', 'image', 'thumbnail', 'post', 'cover', 'hero' );
	}

	public function description(): string {
		return __( 'The featured image of the current post, with aspect ratio, link, caption and a fallback image for posts without one.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'lightbox' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Image', 'uncoder' ) ) );
		$this->add_control(
			'size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'large',
				'options_dynamic' => true,
				'options'         => Theme_Context::image_sizes(),
			)
		);
		$this->add_control(
			'fallback',
			array(
				'type'        => 'media',
				'label'       => __( 'Fallback image', 'uncoder' ),
				'description' => __( 'Shown when the post has no featured image.', 'uncoder' ),
				'default'     => array( 'id' => 0, 'url' => '' ),
			)
		);
		$this->add_control(
			'link_to',
			array(
				'type'    => 'select',
				'label'   => __( 'Link', 'uncoder' ),
				'options' => array(
					''       => __( 'None', 'uncoder' ),
					'post'   => __( 'The post', 'uncoder' ),
					'file'   => __( 'Image file (lightbox)', 'uncoder' ),
					'custom' => __( 'Custom URL', 'uncoder' ),
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
		$this->add_control( 'caption', array( 'type' => 'switch', 'label' => __( 'Show caption', 'uncoder' ) ) );
		$this->add_control(
			'loading',
			array(
				'type'    => 'select',
				'label'   => __( 'Loading', 'uncoder' ),
				'options' => array(
					''      => __( 'Automatic (WordPress decides)', 'uncoder' ),
					'lazy'  => __( 'Lazy', 'uncoder' ),
					'eager' => __( 'Eager, high priority (hero image)', 'uncoder' ),
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

		$this->start_section( 'style_image', array( 'label' => __( 'Image', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( '%', 'px', 'vw', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-featured-image__frame' => 'width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( '%', 'px', 'vw', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-featured-image__frame' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-featured-image__img' => 'height: {{VALUE}}' ),
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
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-featured-image__img' => 'aspect-ratio: {{VALUE}}; height: auto' ),
			)
		);
		$this->add_responsive_control(
			'object_fit',
			array(
				'type'      => 'select',
				'label'     => __( 'Object fit', 'uncoder' ),
				'options'   => array(
					''        => __( 'Default', 'uncoder' ),
					'cover'   => __( 'Cover', 'uncoder' ),
					'contain' => __( 'Contain', 'uncoder' ),
					'fill'    => __( 'Fill', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-featured-image__img' => 'object-fit: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'object_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Focus point', 'uncoder' ),
				'options'   => \Uncoder\Builder\Controls\Groups\Background::POSITIONS,
				'selectors' => array( '{{WRAPPER}} .uncoder-featured-image__img' => 'object-position: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-featured-image__frame' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-featured-image__frame' ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-featured-image__frame' ) );
		$this->add_group( 'filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-featured-image__img' ) );
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

		$this->start_section(
			'style_caption',
			array(
				'label'     => __( 'Caption', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'caption' => true ),
			)
		);
		$this->add_group( 'caption_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-featured-image__caption' ) );
		$this->add_control(
			'caption_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-featured-image__caption' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'caption_align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => Heading::ALIGN,
				'selectors' => array( '{{WRAPPER}} .uncoder-featured-image__caption' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'caption_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-featured-image__caption' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$post     = Theme_Context::post( $ctx );
		$thumb_id = $post ? (int) get_post_thumbnail_id( $post ) : 0;
		$size     = sanitize_key( (string) ( $s['size'] ?? 'large' ) );
		$size     = '' !== $size ? $size : 'large';
		$link_to  = (string) ( $s['link_to'] ?? '' );
		$loading  = (string) ( $s['loading'] ?? '' );
		$title    = $post ? wp_strip_all_tags( get_the_title( $post ) ) : '';

		$attrs = array( 'class' => 'uncoder-featured-image__img' );
		if ( 'lazy' === $loading ) {
			$attrs['loading'] = 'lazy';
		} elseif ( 'eager' === $loading ) {
			$attrs['loading']       = 'eager';
			$attrs['fetchpriority'] = 'high';
		}

		$image_id = 0;
		$img      = '';
		if ( $thumb_id && wp_attachment_is_image( $thumb_id ) ) {
			$image_id = $thumb_id;
			$alt      = trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
			if ( '' === $alt && 'post' === $link_to ) {
				$attrs['alt'] = $title;
			}
			$img = (string) wp_get_attachment_image( $thumb_id, $size, false, $attrs );
		}
		if ( '' === $img ) {
			$fallback = is_array( $s['fallback'] ?? null ) ? $s['fallback'] : array();
			$image_id = (int) ( $fallback['id'] ?? 0 );
			if ( 'post' === $link_to && '' !== $title ) {
				$fallback['alt'] = $title;
			}
			$img = $this->image( $fallback, $size, $attrs );
		}
		if ( '' === $img ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$image_id = 0;
			$img      = '<img class="uncoder-featured-image__img uncoder-featured-image__img--placeholder" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
		}

		$link = array();
		if ( 'post' === $link_to ) {
			$link = array( 'href' => $post ? get_permalink( $post ) : '#' );
		} elseif ( 'file' === $link_to && $image_id ) {
			$full = wp_get_attachment_image_url( $image_id, 'full' );
			if ( $full ) {
				$link = array(
					'href'              => $full,
					'data-uncoder-lightbox' => 'image',
				);
			}
		} elseif ( 'custom' === $link_to ) {
			$link = $this->link_attrs( $s['link'] ?? array() );
		}

		$caption = '';
		if ( ! empty( $s['caption'] ) ) {
			$caption = $image_id ? (string) wp_get_attachment_caption( $image_id ) : '';
			if ( '' === $caption && $ctx->editor ) {
				$caption = __( 'The image caption from the media library appears here.', 'uncoder' );
			}
			if ( '' !== $caption && isset( $link['data-uncoder-lightbox'] ) ) {
				$link['data-caption'] = $caption;
			}
		}

		$hover   = in_array( $s['hover_effect'] ?? '', self::HOVER, true ) ? ' uncoder-featured-image--hover-' . $s['hover_effect'] : '';
		$wrapper = $caption ? 'figure' : 'div';
		echo '<' . $wrapper . ' class="' . esc_attr( 'uncoder-featured-image' . $hover ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.
		if ( $link ) {
			$link['class'] = 'uncoder-featured-image__frame uncoder-featured-image__link';
			echo '<a' . Utils::attrs( $link ) . '>' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes, core image markup.
		} else {
			echo '<span class="uncoder-featured-image__frame">' . $img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
		}
		if ( $caption ) {
			echo '<figcaption class="uncoder-featured-image__caption">' . esc_html( $caption ) . '</figcaption>';
		}
		echo '</' . $wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.
	}
}
