<?php
/**
 * Image carousel widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Carousel_Engine;
use Uncoder\Builder\Widgets\Support\Gallery_Items;

defined( 'ABSPATH' ) || exit;

/**
 * Carousel of images from a gallery control, with captions and lightbox.
 */
class Image_Carousel extends Widget_Base {

	use Carousel_Engine;

	private const MEDIA = '{{WRAPPER}} > .uncoder-carousel__viewport > .uncoder-carousel__track > .uncoder-carousel__slide > .uncoder-image-carousel__item > .uncoder-image-carousel__media';
	private const CAPTION = '{{WRAPPER}} > .uncoder-carousel__viewport > .uncoder-carousel__track > .uncoder-carousel__slide > .uncoder-image-carousel__item > .uncoder-image-carousel__caption';

	public function name(): string {
		return 'image-carousel';
	}

	public function title(): string {
		return __( 'Image Carousel', 'uncoder' );
	}

	public function icon(): string {
		return 'gallery-horizontal';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'carousel', 'slider', 'images', 'gallery', 'photos', 'slideshow' );
	}

	public function description(): string {
		return __( 'Swipeable carousel of images with optional captions and lightbox; several slides per view per breakpoint.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'carousel', 'lightbox' );
	}

	public function frontend_styles(): array {
		return array( 'carousel' );
	}

	public function preset(): array {
		return array(
			'gallery'                => Gallery_Items::placeholders( 5 ),
			'slides_per_view'        => 3,
			'slides_per_view_tablet' => 2,
			'slides_per_view_mobile' => 1,
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Images', 'uncoder' ) ) );
		$this->add_control(
			'gallery',
			array(
				'type'    => 'gallery',
				'label'   => __( 'Images', 'uncoder' ),
				'default' => array(),
				'ai'      => 'Upload images first (upload_media / search_images) and pass [{"id": attachment_id}, …]. Set meaningful alt texts in the media library.',
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
		$this->add_control(
			'caption_source',
			array(
				'type'    => 'select',
				'label'   => __( 'Caption', 'uncoder' ),
				'options' => Gallery_Items::CAPTIONS,
			)
		);
		$this->add_control(
			'link_to',
			array(
				'type'    => 'select',
				'label'   => __( 'Link', 'uncoder' ),
				'default' => 'lightbox',
				'options' => array(
					''         => __( 'None', 'uncoder' ),
					'lightbox' => __( 'Lightbox', 'uncoder' ),
					'file'     => __( 'Image file', 'uncoder' ),
				),
			)
		);
		$this->end_section();

		$this->register_carousel_settings();

		$this->start_section( 'style_images', array( 'label' => __( 'Images', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Aspect ratio', 'uncoder' ),
				'options'   => array(
					''     => __( 'Default (4:3)', 'uncoder' ),
					'auto' => __( 'Original', 'uncoder' ),
					'1/1'  => '1:1',
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'21/9' => '21:9',
					'3/4'  => '3:4',
					'4/5'  => '4:5',
					'2/3'  => '2:3',
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-image-carousel-ratio: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'object_fit',
			array(
				'type'      => 'select',
				'label'     => __( 'Image fit', 'uncoder' ),
				'options'   => array(
					''        => __( 'Cover (crop)', 'uncoder' ),
					'contain' => __( 'Contain (letterbox)', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-image-carousel-fit: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'fit_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Letterbox color', 'uncoder' ),
				'condition' => array( 'object_fit' => 'contain' ),
				'selectors' => array( self::MEDIA => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( self::MEDIA => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::MEDIA ) );
		$this->add_group( 'filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => self::MEDIA . ' img' ) );
		$this->add_control(
			'hover_effect',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover effect', 'uncoder' ),
				'default' => 'zoom',
				'options' => array(
					''          => __( 'None', 'uncoder' ),
					'zoom'      => __( 'Zoom', 'uncoder' ),
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
				'condition' => array( 'caption_source!' => '' ),
			)
		);
		$this->add_group( 'caption_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::CAPTION ) );
		$this->add_control(
			'caption_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::CAPTION => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'caption_align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( self::CAPTION => 'text-align: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'caption_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( self::CAPTION => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->register_carousel_style();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array( 'data-settings' => $this->json_attr( $this->carousel_data( $s ) ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$items = Gallery_Items::items( $s['gallery'] ?? array() );
		if ( ! $items ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-image-carousel-placeholder">' . esc_html__( 'Choose images to build the carousel.', 'uncoder' ) . '</div>';
			}
			return;
		}

		$size    = sanitize_key( (string) ( $s['size'] ?? 'large' ) );
		$source  = (string) ( $s['caption_source'] ?? '' );
		$link_to = (string) ( $s['link_to'] ?? '' );
		$group   = 'uncoder-image-carousel-' . sanitize_html_class( $ctx->element_id );
		$total   = count( $items );
		$classes = array();
		if ( in_array( $s['hover_effect'] ?? 'zoom', array( 'zoom', 'grayscale', 'dim' ), true ) ) {
			$classes[] = 'uncoder-image-carousel--hover-' . $s['hover_effect'];
		}

		echo $this->carousel_start( $s, $ctx, array_merge( array( 'uncoder-image-carousel' ), $classes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
		foreach ( $items as $i => $media ) {
			$alt     = Gallery_Items::alt( $media );
			$caption = '' !== $source ? Gallery_Items::caption( $media, $source ) : '';
			$img     = $this->image( array_merge( $media, array( 'alt' => $alt ) ), $size, array( 'class' => 'uncoder-image-carousel__img' ) );
			if ( '' === $img ) {
				continue;
			}
			echo $this->carousel_slide_start( $i, $total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
			echo '<figure class="uncoder-image-carousel__item">';
			$href = in_array( $link_to, array( 'lightbox', 'file' ), true ) ? Gallery_Items::full_url( $media ) : '';
			if ( '' !== $href ) {
				$attrs = array(
					'class' => 'uncoder-image-carousel__media',
					'href'  => $href,
				);
				if ( 'lightbox' === $link_to ) {
					$attrs['data-uncoder-lightbox'] = 'image';
					$attrs['data-uncoder-gallery']  = $group;
					$lightbox_caption           = '' !== $caption ? $caption : Gallery_Items::caption( $media, 'caption' );
					$attrs['data-caption']      = '' !== $lightbox_caption ? $lightbox_caption : null;
				}
				if ( '' === $alt ) {
					/* translators: %d: image number. */
					$attrs['aria-label'] = sprintf( __( 'Open image %d', 'uncoder' ), $i + 1 );
				}
				echo '<a' . Utils::attrs( $attrs ) . '>' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes, core image markup.
			} else {
				echo '<div class="uncoder-image-carousel__media">' . $img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
			}
			if ( '' !== $caption ) {
				echo '<figcaption class="uncoder-image-carousel__caption">' . esc_html( $caption ) . '</figcaption>';
			}
			echo '</figure></div>';
		}
		echo $this->carousel_end( $s, $ctx, $total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	}
}
