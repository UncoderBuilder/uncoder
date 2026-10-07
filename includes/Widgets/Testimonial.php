<?php
/**
 * Testimonial widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Stars;

defined( 'ABSPATH' ) || exit;

/**
 * A customer quote with name, role, avatar and an optional star rating.
 */
class Testimonial extends Widget_Base {

	public function name(): string {
		return 'testimonial';
	}

	public function title(): string {
		return __( 'Testimonial', 'uncoder' );
	}

	public function icon(): string {
		return 'message-square-quote';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'testimonial', 'review', 'quote', 'customer', 'feedback', 'social proof' );
	}

	public function description(): string {
		return __( 'A customer quote with name, role, photo and optional star rating. Photo above, beside the name, or in a column next to the quote.', 'uncoder' );
	}

	public function frontend_styles(): array {
		return array( 'star-rating' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Testimonial', 'uncoder' ) ) );
		$this->add_control(
			'quote',
			array(
				'type'    => 'wysiwyg',
				'label'   => __( 'Quote', 'uncoder' ),
				'default' => '<p>' . __( 'They rebuilt our site in three weeks and our enquiries doubled the following month. Clear, calm and always a step ahead.', 'uncoder' ) . '</p>',
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'name',
			array(
				'type'    => 'text',
				'label'   => __( 'Name', 'uncoder' ),
				'default' => __( 'Maya Thompson', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'role',
			array(
				'type'    => 'text',
				'label'   => __( 'Role', 'uncoder' ),
				'default' => __( 'Founder, Northwind Studio', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'image',
			array(
				'type'    => 'media',
				'label'   => __( 'Photo', 'uncoder' ),
				'default' => array( 'id' => 0, 'url' => '' ),
				'dynamic' => true,
			)
		);
		$this->add_control(
			'image_size',
			array(
				'type'            => 'select',
				'label'           => __( 'Resolution', 'uncoder' ),
				'default'         => 'thumbnail',
				'options_dynamic' => true,
				'options'         => array(
					'thumbnail'    => 'Thumbnail',
					'medium'       => 'Medium',
					'medium_large' => 'Medium large',
					'large'        => 'Large',
					'full'         => 'Full',
				),
				'condition'       => array( 'image.url!' => '' ),
			)
		);
		$this->add_control( 'link', array( 'type' => 'url', 'label' => __( 'Name link', 'uncoder' ), 'dynamic' => true ) );
		$this->add_control(
			'show_rating',
			array(
				'type'  => 'switch',
				'label' => __( 'Star rating', 'uncoder' ),
			)
		);
		$this->add_control(
			'rating',
			array(
				'type'      => 'number',
				'label'     => __( 'Rating (0–5)', 'uncoder' ),
				'default'   => 5,
				'min'       => 0,
				'max'       => 5,
				'step'      => 0.5,
				'condition' => array( 'show_rating' => 'yes' ),
			)
		);
		$this->add_control(
			'show_quote_icon',
			array(
				'type'  => 'switch',
				'label' => __( 'Quotation mark', 'uncoder' ),
			)
		);
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'left',
				'options' => array(
					'above' => __( 'Photo above', 'uncoder' ),
					'left'  => __( 'Photo next to name', 'uncoder' ),
					'aside' => __( 'Photo beside quote', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'stack_mobile',
			array(
				'type'        => 'switch',
				'label'       => __( 'Stack on mobile', 'uncoder' ),
				'description' => __( 'Moves the photo above the quote on small screens.', 'uncoder' ),
				'default'     => true,
				'condition'   => array( 'layout' => 'aside' ),
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
					'left'   => 'text-align:left;--uncoder-testi-justify:start;--uncoder-testi-flex:flex-start',
					'center' => 'text-align:center;--uncoder-testi-justify:center;--uncoder-testi-flex:center',
					'right'  => 'text-align:right;--uncoder-testi-justify:end;--uncoder-testi-flex:flex-end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'box_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'box_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'box_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'box_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'box_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing between parts', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-testi-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_quote', array( 'label' => __( 'Quote', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'quote_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-testimonial__quote' ) );
		$this->add_control(
			'quote_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-testimonial__quote' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'quote_max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', 'ch', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-testimonial__quote' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'quote_icon_heading',
			array(
				'type'      => 'heading',
				'label'     => __( 'Quotation mark', 'uncoder' ),
				'condition' => array( 'show_quote_icon' => 'yes' ),
			)
		);
		$this->add_control(
			'quote_icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'condition' => array( 'show_quote_icon' => 'yes' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-testimonial__mark' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'quote_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 120 ) ),
				'condition'  => array( 'show_quote_icon' => 'yes' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-testimonial__mark' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_author', array( 'label' => __( 'Name & role', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'name_typography', array( 'type' => 'typography', 'label' => __( 'Name typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-testimonial__name' ) );
		$this->add_control(
			'name_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Name color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-testimonial__name' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'name_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Name link hover color', 'uncoder' ),
				'condition' => array( 'link.url!' => '' ),
				'selectors' => array( '{{WRAPPER}} a.uncoder-testimonial__name:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'role_typography', array( 'type' => 'typography', 'label' => __( 'Role typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-testimonial__role' ) );
		$this->add_control(
			'role_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Role color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-testimonial__role' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'role_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Name to role spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-testimonial__meta' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_image', array( 'label' => __( 'Photo', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'image_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 240 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-testi-avatar: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-testimonial__avatar' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'image_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-testimonial__avatar' ) );
		$this->add_group( 'image_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-testimonial__avatar' ) );
		$this->add_responsive_control(
			'image_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-testi-avatar-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_rating',
			array(
				'label'     => __( 'Stars', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_rating' => 'yes' ),
			)
		);
		$this->add_responsive_control(
			'star_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-star-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'star_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-star-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'star_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Marked color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-star-marked: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'star_unmarked_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Unmarked color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-star-unmarked: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$quote = wp_kses( (string) ( $s['quote'] ?? '' ), Utils::kses_rich() );
		$name  = (string) ( $s['name'] ?? '' );
		$role  = (string) ( $s['role'] ?? '' );
		if ( '' === trim( wp_strip_all_tags( $quote ) ) && '' === $name && ! $ctx->editor ) {
			return;
		}
		$layout = in_array( $s['layout'] ?? 'left', array( 'above', 'left', 'aside' ), true ) ? (string) $s['layout'] : 'left';

		$size   = sanitize_key( (string) ( $s['image_size'] ?? 'thumbnail' ) );
		$avatar = $this->image( $s['image'] ?? array(), '' !== $size ? $size : 'thumbnail', array( 'class' => 'uncoder-testimonial__img' ) );
		if ( '' !== $avatar ) {
			$avatar = '<span class="uncoder-testimonial__avatar">' . $avatar . '</span>';
		}

		$classes = array( 'uncoder-testimonial', 'uncoder-testimonial--' . $layout );
		if ( '' !== $avatar ) {
			$classes[] = 'uncoder-testimonial--has-avatar';
		}
		if ( 'aside' === $layout && ! empty( $s['stack_mobile'] ) ) {
			$classes[] = 'uncoder-testimonial--stack-mobile';
		}

		echo '<figure class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( 'left' !== $layout ) {
			echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- image markup from core.
		}
		if ( ! empty( $s['show_quote_icon'] ) ) {
			echo '<span class="uncoder-testimonial__mark" aria-hidden="true">' . $this->render_icon( 'quote' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup.
		}
		if ( ! empty( $s['show_rating'] ) ) {
			echo Stars::render( Stars::normalize( $s['rating'] ?? 5, 5 ), 5, 'star', 'solid', 'uncoder-testimonial__rating' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
		}
		echo '<blockquote class="uncoder-testimonial__quote"' . $ctx->inline( 'quote' ) . '>' . $quote . '</blockquote>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd rich text.

		if ( '' !== $name || '' !== $role || ( 'left' === $layout && '' !== $avatar ) || $ctx->editor ) {
			echo '<figcaption class="uncoder-testimonial__author">';
			if ( 'left' === $layout ) {
				echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- image markup from core.
			}
			echo '<span class="uncoder-testimonial__meta">';
			$link = $this->link_attrs( $s['link'] ?? array() );
			if ( $link ) {
				$link['class'] = 'uncoder-testimonial__name';
				echo '<a' . Utils::attrs( $link ) . $ctx->inline( 'name' ) . '>' . esc_html( $name ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
			} else {
				echo '<span class="uncoder-testimonial__name"' . $ctx->inline( 'name' ) . '>' . esc_html( $name ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() escaped.
			}
			if ( '' !== $role || $ctx->editor ) {
				echo '<span class="uncoder-testimonial__role"' . $ctx->inline( 'role' ) . '>' . esc_html( $role ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() escaped.
			}
			echo '</span></figcaption>';
		}
		echo '</figure>';
	}
}
