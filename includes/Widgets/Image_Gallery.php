<?php
/**
 * Image gallery widget.
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
 * Grid, masonry (CSS columns) or justified (flex rows) gallery with captions and lightbox.
 */
class Image_Gallery extends Widget_Base {

	private const ROOT    = '{{WRAPPER}}';
	private const MEDIA   = '{{WRAPPER}} .uncoder-image-gallery__media';

	/** The rounded box: the image link when captions sit below, the whole item when they overlay it. */
	private const BOX = '{{WRAPPER}}.uncoder-image-gallery--caption-below > .uncoder-image-gallery__item > .uncoder-image-gallery__media, {{WRAPPER}}:not(.uncoder-image-gallery--caption-below) > .uncoder-image-gallery__item';
	private const CAPTION = '{{WRAPPER}} .uncoder-image-gallery__caption';

	public function name(): string {
		return 'image-gallery';
	}

	public function title(): string {
		return __( 'Image Gallery', 'uncoder' );
	}

	public function icon(): string {
		return 'layout-grid';
	}

	public function category(): string {
		return 'media';
	}

	public function keywords(): array {
		return array( 'gallery', 'images', 'photos', 'grid', 'masonry', 'justified', 'lightbox', 'portfolio' );
	}

	public function description(): string {
		return __( 'Image gallery as a grid, masonry or justified rows, with captions, hover effects and a lightbox.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'lightbox', 'gallery-filter' );
	}

	public function preset(): array {
		return array(
			'gallery'        => Gallery_Items::placeholders( 6 ),
			'columns_mobile' => 2,
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Gallery', 'uncoder' ) ) );
		$this->add_control(
			'gallery',
			array(
				'type'    => 'gallery',
				'label'   => __( 'Images', 'uncoder' ),
				'default' => array(),
				'ai'      => 'Upload images first (upload_media / search_images) and pass [{"id": attachment_id}, …]. Captions come from the media library.',
				'condition' => array( 'filterable!' => true ),
			)
		);
		$this->add_control(
			'filterable',
			array(
				'type'        => 'switch',
				'label'       => __( 'Filterable (portfolio)', 'uncoder' ),
				'description' => __( 'Images in named groups with filter buttons above the gallery.', 'uncoder' ),
			)
		);
		$this->add_control(
			'groups',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Groups', 'uncoder' ),
				'title_field' => 'title',
				'condition'   => array( 'filterable' => true ),
				'fields'      => array(
					'title'  => array( 'type' => 'text', 'label' => __( 'Group name', 'uncoder' ), 'default' => __( 'Group', 'uncoder' ) ),
					'images' => array( 'type' => 'gallery', 'label' => __( 'Images', 'uncoder' ) ),
				),
				'default'     => array(),
				'ai'          => 'Portfolio: [{"title":"Residential","images":[{"id":1},{"id":2}]},{"title":"Commercial","images":[…]}]. An image in several groups shows once and matches each of them.',
			)
		);
		$this->add_control(
			'all_label',
			array(
				'type'        => 'text',
				'label'       => __( '“All” label', 'uncoder' ),
				'placeholder' => __( 'All', 'uncoder' ),
				'condition'   => array( 'filterable' => true ),
			)
		);
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'grid',
				'options' => array(
					'grid'      => __( 'Grid', 'uncoder' ),
					'masonry'   => __( 'Masonry', 'uncoder' ),
					'justified' => __( 'Justified rows', 'uncoder' ),
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
				'condition' => array( 'layout' => array( 'grid', 'masonry' ) ),
				'selectors' => array( self::ROOT => '--uncoder-gallery-cols: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Row height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 80, 'max' => 600 ) ),
				'condition'  => array( 'layout' => 'justified' ),
				'selectors'  => array( self::ROOT => '--uncoder-gallery-row-h: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Aspect ratio', 'uncoder' ),
				'options'   => array(
					''     => __( 'Default (1:1)', 'uncoder' ),
					'auto' => __( 'Original', 'uncoder' ),
					'1/1'  => '1:1',
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'3/4'  => '3:4',
					'4/5'  => '4:5',
					'2/3'  => '2:3',
				),
				'condition' => array( 'layout' => 'grid' ),
				'selectors' => array( self::ROOT => '--uncoder-gallery-ratio: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( self::ROOT => '--uncoder-gallery-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'medium_large',
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
			'caption_position',
			array(
				'type'        => 'select',
				'label'       => __( 'Caption position', 'uncoder' ),
				'description' => __( 'Justified rows always show captions over the image.', 'uncoder' ),
				'default'     => 'below',
				'options'     => array(
					'below'   => __( 'Below the image', 'uncoder' ),
					'overlay' => __( 'Over the image', 'uncoder' ),
					'hover'   => __( 'Over the image, on hover', 'uncoder' ),
				),
				'condition'   => array( 'caption_source!' => '' ),
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
		$this->add_control(
			'overlay',
			array(
				'type'      => 'switch',
				'label'     => __( 'Hover overlay', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'link_to!' => '' ),
			)
		);
		$this->add_control(
			'overlay_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Overlay icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'zoom-in' ),
				'condition' => array(
					'link_to!' => '',
					'overlay'  => 'yes',
				),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: images */
		$this->start_section( 'style_images', array( 'label' => __( 'Images', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'hover_effect',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover effect', 'uncoder' ),
				'default' => 'zoom',
				'options' => array(
					''          => __( 'None', 'uncoder' ),
					'zoom'      => __( 'Zoom', 'uncoder' ),
					'lift'      => __( 'Lift', 'uncoder' ),
					'grayscale' => __( 'Grayscale to color', 'uncoder' ),
					'dim'       => __( 'Dim others', 'uncoder' ),
				),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( self::BOX => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::BOX ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::BOX ) );
		$this->add_group( 'filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => self::MEDIA . ' > img' ) );
		$this->add_control(
			'overlay_heading',
			array(
				'type'      => 'heading',
				'label'     => __( 'Overlay', 'uncoder' ),
				'condition' => array( 'overlay' => 'yes' ),
			)
		);
		$this->add_control(
			'overlay_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Overlay color', 'uncoder' ),
				'condition' => array( 'overlay' => 'yes' ),
				'selectors' => array( self::ROOT => '--uncoder-gallery-overlay: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'overlay_icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'condition' => array( 'overlay' => 'yes' ),
				'selectors' => array( self::ROOT => '--uncoder-gallery-overlay-icon: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'overlay_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 80 ) ),
				'condition'  => array( 'overlay' => 'yes' ),
				'selectors'  => array( self::ROOT => '--uncoder-gallery-overlay-size: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: caption */
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
		$this->add_control(
			'caption_background',
			array(
				'type'        => 'color',
				'label'       => __( 'Background', 'uncoder' ),
				'description' => __( 'Captions over the image use a dark gradient by default.', 'uncoder' ),
				'selectors'   => array( self::CAPTION => 'background: {{VALUE}}' ),
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
		$this->add_responsive_control(
			'caption_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::CAPTION => 'padding: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Images of a filterable gallery, each once, with the slugs of the groups it belongs to.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array{0: array<int, array<string,mixed>>, 1: array<string,string>, 2: array<int, string[]>} Items, groups (slug => name), item groups.
	 */
	private function grouped( array $s ): array {
		$items  = array();
		$groups = array();
		$member = array();
		$index  = array();
		foreach ( (array) ( $s['groups'] ?? array() ) as $row ) {
			$name = trim( (string) ( $row['title'] ?? '' ) );
			$slug = sanitize_title( $name );
			if ( '' === $slug ) {
				continue;
			}
			$groups[ $slug ] = $name;
			foreach ( Gallery_Items::items( $row['images'] ?? array() ) as $media ) {
				$key = ! empty( $media['id'] ) ? 'id' . $media['id'] : (string) ( $media['url'] ?? '' );
				if ( ! isset( $index[ $key ] ) ) {
					$index[ $key ] = count( $items );
					$items[]       = $media;
				}
				$member[ $index[ $key ] ][] = $slug;
			}
		}
		return array( $items, $groups, $member );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$filterable = ! empty( $s['filterable'] );
		$groups     = array();
		$member     = array();
		if ( $filterable ) {
			list( $items, $groups, $member ) = $this->grouped( $s );
		} else {
			$items = Gallery_Items::items( $s['gallery'] ?? array() );
		}
		if ( ! $items ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-image-gallery-placeholder">' . esc_html__( 'Choose images to build the gallery.', 'uncoder' ) . '</div>';
			}
			return;
		}

		$layout   = in_array( $s['layout'] ?? 'grid', array( 'grid', 'masonry', 'justified' ), true ) ? $s['layout'] : 'grid';
		$size     = sanitize_key( (string) ( $s['size'] ?? 'medium_large' ) );
		$source   = (string) ( $s['caption_source'] ?? '' );
		$position = in_array( $s['caption_position'] ?? 'below', array( 'below', 'overlay', 'hover' ), true ) ? $s['caption_position'] : 'below';
		if ( 'justified' === $layout && 'below' === $position ) {
			$position = 'overlay';
		}
		$link_to = in_array( $s['link_to'] ?? '', array( 'lightbox', 'file' ), true ) ? $s['link_to'] : '';
		$group   = 'uncoder-image-gallery-' . sanitize_html_class( $ctx->element_id );
		$overlay = '' !== $link_to && ! empty( $s['overlay'] );
		$icon    = $overlay && $this->has_icon( $s['overlay_icon'] ?? null ) ? $this->render_icon( $s['overlay_icon'], array( 'class' => 'uncoder-image-gallery__overlay-icon' ) ) : '';

		$classes = array( 'uncoder-image-gallery', 'uncoder-image-gallery--' . $layout, 'uncoder-image-gallery--caption-' . $position );
		if ( in_array( $s['hover_effect'] ?? 'zoom', array( 'zoom', 'lift', 'grayscale', 'dim' ), true ) ) {
			$classes[] = 'uncoder-image-gallery--hover-' . $s['hover_effect'];
		}

		// The gallery itself is the widget's root (merged with the element); the filter bar is its first,
		// full-width row, so the element's styles and the filter module both reach the images.
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( $filterable && count( $groups ) > 1 ) {
			// Shown by the gallery-filter module; without JavaScript every image stays visible.
			echo '<div class="uncoder-image-gallery__filters" role="group" aria-label="' . esc_attr__( 'Filter the gallery', 'uncoder' ) . '" hidden>';
			echo '<button type="button" class="uncoder-image-gallery__filter is-active" data-group="" aria-pressed="true">' . esc_html( trim( (string) ( $s['all_label'] ?? '' ) ) ?: __( 'All', 'uncoder' ) ) . '</button>';
			foreach ( $groups as $slug => $name ) {
				echo '<button type="button" class="uncoder-image-gallery__filter" data-group="' . esc_attr( $slug ) . '" aria-pressed="false">' . esc_html( $name ) . '</button>';
			}
			echo '</div>';
		}
		foreach ( $items as $i => $media ) {
			$alt     = Gallery_Items::alt( $media );
			$caption = '' !== $source ? Gallery_Items::caption( $media, $source ) : '';
			$img     = $this->image( array_merge( $media, array( 'alt' => $alt ) ), $size, array( 'class' => 'uncoder-image-gallery__img' ) );
			if ( '' === $img ) {
				continue;
			}
			$inner = $img . ( $overlay ? '<span class="uncoder-image-gallery__overlay" aria-hidden="true">' . $icon . '</span>' : '' );
			echo '<figure class="uncoder-image-gallery__item"' . ( $filterable ? ' data-groups="' . esc_attr( implode( ' ', $member[ $i ] ?? array() ) ) . '"' : '' ) . '>';
			$href = '' !== $link_to ? Gallery_Items::full_url( $media ) : '';
			if ( '' !== $href ) {
				$attrs = array(
					'class' => 'uncoder-image-gallery__media',
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
				echo '<a' . Utils::attrs( $attrs ) . '>' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes, core image markup.
			} else {
				echo '<div class="uncoder-image-gallery__media">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
			}
			if ( '' !== $caption ) {
				echo '<figcaption class="uncoder-image-gallery__caption">' . esc_html( $caption ) . '</figcaption>';
			}
			echo '</figure>';
		}
		echo '</div>';
	}
}
