<?php
/**
 * Image box widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * An image above or beside a title, a short description and an optional link.
 */
class Image_Box extends Widget_Base {

	public const POSITION_CSS = array(
		'top'   => '--uncoder-imgbox-dir:column;--uncoder-imgbox-items:stretch;--uncoder-imgbox-media-w:100%',
		'left'  => '--uncoder-imgbox-dir:row;--uncoder-imgbox-items:var(--uncoder-imgbox-v, flex-start);--uncoder-imgbox-media-w:35%',
		'right' => '--uncoder-imgbox-dir:row-reverse;--uncoder-imgbox-items:var(--uncoder-imgbox-v, flex-start);--uncoder-imgbox-media-w:35%',
	);

	public const IMAGE_HOVER = array(
		''          => 'None',
		'zoom'      => 'Zoom in',
		'zoom-out'  => 'Zoom out',
		'grayscale' => 'Grayscale to color',
		'dim'       => 'Dim',
	);

	public function name(): string {
		return 'image-box';
	}

	public function title(): string {
		return __( 'Image Box', 'uncoder' );
	}

	public function icon(): string {
		return 'layout-panel-top';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'image box', 'card', 'feature', 'service', 'team', 'picture' );
	}

	public function description(): string {
		return __( 'An image with a title, a description and an optional link. Use for service cards, team members and article teasers.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Image box', 'uncoder' ) ) );
		$this->add_control(
			'image',
			array(
				'type'    => 'media',
				'label'   => __( 'Image', 'uncoder' ),
				'default' => array( 'id' => 0, 'url' => '' ),
				'dynamic' => true,
				'ai'      => 'Upload the image first and pass {"id": attachment_id}. Set a meaningful alt text.',
			)
		);
		$this->add_control(
			'alt',
			array(
				'type'        => 'text',
				'label'       => __( 'Alt text', 'uncoder' ),
				'description' => __( 'Overrides the media library alt text. Leave empty when the image is decorative.', 'uncoder' ),
				'dynamic'     => true,
			)
		);
		$this->add_control(
			'image_size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'medium_large',
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
			'title',
			array(
				'type'    => 'text',
				'label'   => __( 'Title', 'uncoder' ),
				'html'    => 'inline',
				'default' => __( 'Brand strategy', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'description',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Description', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 4,
				'default' => __( 'We map your audience, sharpen your message and give every page a clear job to do.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'h3',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control( 'link', array( 'type' => 'url', 'label' => __( 'Link', 'uncoder' ), 'dynamic' => true ) );
		$this->add_control(
			'link_click',
			array(
				'type'      => 'select',
				'label'     => __( 'Clickable area', 'uncoder' ),
				'default'   => 'box',
				'options'   => array(
					'box'    => __( 'Whole box', 'uncoder' ),
					'title'  => __( 'Image and title', 'uncoder' ),
					'button' => __( 'Link text only', 'uncoder' ),
				),
				'condition' => array( 'link.url!' => '' ),
			)
		);
		$this->add_control(
			'link_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Link text', 'uncoder' ),
				'placeholder' => __( 'Learn more', 'uncoder' ),
				'description' => __( 'Optional call to action shown under the description.', 'uncoder' ),
				'inline'      => true,
				'dynamic'     => true,
			)
		);
		$this->add_control(
			'link_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Link icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'arrow-right' ),
				'condition' => array( 'link_text!' => '' ),
			)
		);
		$this->add_responsive_control(
			'position',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Image position', 'uncoder' ),
				'default'              => 'top',
				'options'              => Icon_Box::POSITIONS,
				'selectors_dictionary' => self::POSITION_CSS,
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'ai'                   => 'Use "left" for horizontal cards on desktop and "top" on mobile (position_mobile).',
			)
		);
		$this->add_responsive_control(
			'vertical_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Vertical alignment', 'uncoder' ),
				'description'          => __( 'Applies when the image sits left or right of the text.', 'uncoder' ),
				'options'              => Icon_Box::VALIGN,
				'selectors_dictionary' => array(
					'top'    => 'flex-start',
					'middle' => 'center',
					'bottom' => 'flex-end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '--uncoder-imgbox-v: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => Heading::ALIGN,
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->register_box_style();
		$this->register_image_style();
		$this->register_content_style();
	}

	private function register_box_style(): void {
		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'box_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'box_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'box_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_group( 'box_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_group( 'box_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_group( 'box_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_group( 'hover_box_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover' ) );
		$this->add_control(
			'hover_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'hover_box_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover' ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_control(
			'hover_effect',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover effect', 'uncoder' ),
				'options' => Icon_Box::BOX_HOVER,
			)
		);
		$this->add_control(
			'hover_lift',
			array(
				'type'       => 'slider',
				'label'      => __( 'Lift distance', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'condition'  => array( 'hover_effect' => array( 'lift', 'lift-shadow' ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-imgbox-lift: calc({{VALUE}} * -1)' ),
			)
		);
		$this->add_control(
			'transition',
			array(
				'type'      => 'number',
				'label'     => __( 'Transition (ms)', 'uncoder' ),
				'min'       => 0,
				'max'       => 3000,
				'step'      => 50,
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-imgbox-dur: {{VALUE}}ms' ),
			)
		);
		$this->end_section();
	}

	private function register_image_style(): void {
		$this->start_section( 'style_image', array( 'label' => __( 'Image', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'image_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( '%', 'px', 'rem' ),
				'range'      => array(
					'%'  => array( 'min' => 5, 'max' => 100 ),
					'px' => array( 'min' => 20, 'max' => 800 ),
				),
				'selectors'  => array( '{{WRAPPER}} .uncoder-image-box__media' => 'width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
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
					'21/9' => '21:9',
					'3/4'  => '3:4',
					'4/5'  => '4:5',
					'2/3'  => '2:3',
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-image-box__img' => 'aspect-ratio: {{VALUE}}; object-fit: cover' ),
			)
		);
		$this->add_control(
			'image_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Focal point', 'uncoder' ),
				'options'   => \Uncoder\Builder\Controls\Groups\Background::POSITIONS,
				'condition' => array( 'image_ratio!' => '' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-image-box__img' => 'object-position: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-image-box__media' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'image_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-image-box__media' ) );
		$this->add_group( 'image_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-image-box__media' ) );
		$this->add_responsive_control(
			'image_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-imgbox-gap: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'image_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_group( 'image_filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-image-box__img' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_group( 'hover_image_filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover .uncoder-image-box__img' ) );
		$this->add_control(
			'image_hover',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover animation', 'uncoder' ),
				'default' => 'zoom',
				'options' => self::IMAGE_HOVER,
			)
		);
		$this->add_control(
			'image_zoom',
			array(
				'type'      => 'number',
				'label'     => __( 'Zoom scale', 'uncoder' ),
				'min'       => 1,
				'max'       => 1.5,
				'step'      => 0.01,
				'condition' => array( 'image_hover' => array( 'zoom', 'zoom-out' ) ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-imgbox-zoom: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();
	}

	private function register_content_style(): void {
		$this->start_section( 'style_content', array( 'label' => __( 'Content', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'content_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Content padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-image-box__content' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_control( 'title_heading', array( 'type' => 'heading', 'label' => __( 'Title', 'uncoder' ) ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-image-box__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-image-box__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'title_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-image-box__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-image-box__title' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_control( 'description_heading', array( 'type' => 'heading', 'label' => __( 'Description', 'uncoder' ) ) );
		$this->add_group( 'description_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-image-box__description' ) );
		$this->add_control(
			'description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-image-box__description' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'description_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-image-box__description' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_heading',
			array(
				'type'      => 'heading',
				'label'     => __( 'Link text', 'uncoder' ),
				'condition' => array( 'link_text!' => '' ),
			)
		);
		$this->add_group(
			'link_typography',
			array(
				'type'      => 'typography',
				'label'     => __( 'Typography', 'uncoder' ),
				'selector'  => '{{WRAPPER}} .uncoder-image-box__cta',
				'condition' => array( 'link_text!' => '' ),
			)
		);
		$this->add_control(
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'condition' => array( 'link_text!' => '' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-image-box__cta' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'condition' => array( 'link_text!' => '' ),
				'selectors' => array( '{{WRAPPER}}:hover .uncoder-image-box__cta' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'link_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'condition'  => array( 'link_text!' => '' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-image-box__cta' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$title       = $this->inline_html( $s['title'] ?? '' );
		$description = $this->inline_html( $s['description'] ?? '' );
		$link_text   = (string) ( $s['link_text'] ?? '' );
		$has_title   = '' !== trim( wp_strip_all_tags( $title ) );
		$has_desc    = '' !== trim( wp_strip_all_tags( $description ) );

		$size  = sanitize_key( (string) ( $s['image_size'] ?? 'medium_large' ) );
		$attrs = array( 'class' => 'uncoder-image-box__img' );
		if ( isset( $s['alt'] ) && '' !== $s['alt'] ) {
			$attrs['alt'] = (string) $s['alt'];
		}
		$img = $this->image( $s['image'] ?? array(), '' !== $size ? $size : 'medium_large', $attrs );
		if ( '' === $img && $ctx->editor ) {
			$img = '<img class="uncoder-image-box__img uncoder-image-box__img--placeholder" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
		}

		if ( '' === $img && ! $has_title && ! $has_desc && '' === $link_text && ! $ctx->editor ) {
			return;
		}

		$link  = $this->link_attrs( $s['link'] ?? array() );
		$click = in_array( $s['link_click'] ?? 'box', array( 'box', 'title', 'button' ), true ) ? (string) ( $s['link_click'] ?? 'box' ) : 'box';
		if ( 'button' === $click && '' === $link_text ) {
			$click = 'title';
		}

		$classes = array( 'uncoder-image-box' );
		if ( ! empty( $s['hover_effect'] ) && isset( Icon_Box::BOX_HOVER[ $s['hover_effect'] ] ) ) {
			foreach ( explode( '-', (string) $s['hover_effect'] ) as $effect ) {
				$classes[] = 'uncoder-image-box--hover-' . $effect;
			}
		}
		$image_hover = (string) ( $s['image_hover'] ?? '' );
		if ( '' !== $image_hover && isset( self::IMAGE_HOVER[ $image_hover ] ) ) {
			$classes[] = 'uncoder-image-box--img-' . $image_hover;
		}
		if ( $link && 'box' === $click ) {
			$classes[] = 'uncoder-image-box--linked';
		}

		$stretch_on = '';
		if ( $link && 'box' === $click ) {
			$stretch_on = $has_title ? 'title' : ( '' !== $link_text ? 'cta' : 'overlay' );
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( '' !== $img ) {
			if ( $link && 'title' === $click ) {
				$media                = $link;
				$media['class']       = 'uncoder-image-box__media';
				$media['tabindex']    = '-1';
				$media['aria-hidden'] = 'true';
				echo '<a' . Utils::attrs( $media ) . '>' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes, image markup from core.
			} else {
				echo '<div class="uncoder-image-box__media">' . $img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- image markup from core.
			}
		}

		echo '<div class="uncoder-image-box__content">';
		if ( $has_title || $ctx->editor ) {
			$tag = Utils::tag( $s['title_tag'] ?? 'h3', Utils::HEADING_TAGS, 'h3' );
			if ( $link && ( 'title' === $click || 'title' === $stretch_on ) ) {
				$title_link          = $link;
				$title_link['class'] = 'uncoder-image-box__title-link' . ( 'title' === $stretch_on ? ' uncoder-image-box__stretched' : '' );
				echo '<' . $tag . ' class="uncoder-image-box__title"><a' . Utils::attrs( $title_link ) . $ctx->inline( 'title' ) . '>' . $title . '</a></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, attrs() escapes, kses'd title.
			} else {
				echo '<' . $tag . ' class="uncoder-image-box__title"' . $ctx->inline( 'title' ) . '>' . $title . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, kses'd title.
			}
		}
		if ( $has_desc || $ctx->editor ) {
			echo '<p class="uncoder-image-box__description"' . $ctx->inline( 'description' ) . '>' . $description . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
		}
		if ( '' !== $link_text ) {
			$icon = $this->has_icon( $s['link_icon'] ?? null ) ? $this->render_icon( $s['link_icon'], array( 'class' => 'uncoder-image-box__cta-icon' ) ) : '';
			$text = '<span class="uncoder-image-box__cta-text"' . $ctx->inline( 'link_text' ) . '>' . esc_html( $link_text ) . '</span>';
			if ( $link && ( 'box' !== $click || 'cta' === $stretch_on ) ) {
				$cta          = $link;
				$cta['class'] = 'uncoder-image-box__cta' . ( 'cta' === $stretch_on ? ' uncoder-image-box__stretched' : '' );
				if ( 'title' === $click && $has_title ) {
					$cta['tabindex']    = '-1';
					$cta['aria-hidden'] = 'true';
				}
				echo '<a' . Utils::attrs( $cta ) . '>' . $text . $icon . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			} else {
				echo '<span class="uncoder-image-box__cta">' . $text . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			}
		}
		if ( 'overlay' === $stretch_on ) {
			$overlay          = $link;
			$overlay['class'] = 'uncoder-image-box__stretched uncoder-image-box__overlay';
			$label            = trim( wp_strip_all_tags( $description ) );
			if ( '' === $label && isset( $attrs['alt'] ) ) {
				$label = (string) $attrs['alt'];
			}
			echo '<a' . Utils::attrs( $overlay ) . '><span class="uncoder-sr-only">' . esc_html( '' !== $label ? $label : __( 'Open link', 'uncoder' ) ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		}
		echo '</div></div>';
	}
}
