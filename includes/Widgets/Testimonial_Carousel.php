<?php
/**
 * Testimonial carousel widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Icons;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Carousel_Engine;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * Carousel of quote cards (quote, rating, avatar, name, role).
 */
class Testimonial_Carousel extends Widget_Base {

	use Carousel_Engine;

	private const CARD = '{{WRAPPER}} > .uncoder-carousel__viewport > .uncoder-carousel__track > .uncoder-carousel__slide > .uncoder-testimonial-carousel__card';

	public function name(): string {
		return 'testimonial-carousel';
	}

	public function title(): string {
		return __( 'Testimonial Carousel', 'uncoder' );
	}

	public function icon(): string {
		return 'message-square-quote';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'testimonial', 'review', 'quote', 'carousel', 'slider', 'customers', 'rating' );
	}

	public function description(): string {
		return __( 'Swipeable carousel of testimonial cards: quote, star rating, avatar, name and role.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'carousel' );
	}

	public function frontend_styles(): array {
		return array( 'carousel' );
	}

	public function preset(): array {
		return array(
			'items'                  => $this->default_items(),
			'slides_per_view'        => 3,
			'slides_per_view_tablet' => 2,
			'slides_per_view_mobile' => 1,
		);
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private function default_items(): array {
		return array(
			array(
				'quote'  => __( 'The team understood our goals from the first call and delivered ahead of schedule. Our sign-ups doubled in the first month.', 'uncoder' ),
				'name'   => __( 'Sarah Lindqvist', 'uncoder' ),
				'role'   => __( 'Head of Marketing, Northwind', 'uncoder' ),
				'rating' => 5,
			),
			array(
				'quote'  => __( 'Clear communication, thoughtful design and zero surprises on the invoice. Exactly what we needed.', 'uncoder' ),
				'name'   => __( 'Daniel Okafor', 'uncoder' ),
				'role'   => __( 'Founder, Brightline Studio', 'uncoder' ),
				'rating' => 5,
			),
			array(
				'quote'  => __( 'Support answers within minutes and actually solves the problem. We moved our whole team over in a week.', 'uncoder' ),
				'name'   => __( 'Mei Tanaka', 'uncoder' ),
				'role'   => __( 'Operations Lead, Harbor & Co.', 'uncoder' ),
				'rating' => 4.5,
			),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Testimonials', 'uncoder' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Testimonials', 'uncoder' ),
				'title_field' => 'name',
				'fields'      => array(
					'quote'  => array(
						'type'    => 'textarea',
						'label'   => __( 'Quote', 'uncoder' ),
						'rows'    => 4,
						'default' => __( 'Write what the customer said about you here.', 'uncoder' ),
					),
					'name'   => array(
						'type'    => 'text',
						'label'   => __( 'Name', 'uncoder' ),
						'default' => __( 'Customer name', 'uncoder' ),
					),
					'role'   => array(
						'type'    => 'text',
						'label'   => __( 'Role / company', 'uncoder' ),
						'default' => '',
					),
					'avatar' => array(
						'type'  => 'media',
						'label' => __( 'Photo', 'uncoder' ),
					),
					'rating' => array(
						'type'    => 'number',
						'label'   => __( 'Rating (0–5)', 'uncoder' ),
						'min'     => 0,
						'max'     => 5,
						'step'    => 0.5,
						'default' => 5,
						'ai'      => '0 hides the stars.',
					),
				),
				'default'     => $this->default_items(),
			)
		);
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Card layout', 'uncoder' ),
				'default' => 'classic',
				'options' => array(
					'classic'  => __( 'Quote, then author row', 'uncoder' ),
					'centered' => __( 'Centered, photo above the name', 'uncoder' ),
					'author'   => __( 'Author first, then quote', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'show_rating',
			array(
				'type'    => 'switch',
				'label'   => __( 'Star rating', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'quote_icon',
			array(
				'type'    => 'icon',
				'label'   => __( 'Quote icon', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'quote' ),
			)
		);
		$this->add_control(
			'name_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Name HTML tag', 'uncoder' ),
				'default' => 'div',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->end_section();

		$this->register_carousel_settings();

		/* ---------------------------------------------------------------- Style: card */
		$this->start_section( 'style_card', array( 'label' => __( 'Card', 'uncoder' ), 'tab' => 'style' ) );
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
				'selectors_dictionary' => array(
					'left'   => 'text-align:left;--uncoder-tcar-justify:flex-start',
					'center' => 'text-align:center;--uncoder-tcar-justify:center',
					'right'  => 'text-align:right;--uncoder-tcar-justify:flex-end',
				),
				'selectors' => array( self::CARD => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between parts', 'uncoder' ),
				'size_units' => array( 'px', 'rem', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( self::CARD => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::CARD => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'card_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => self::CARD ) );
		$this->add_group( 'card_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::CARD ) );
		$this->add_responsive_control(
			'card_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( self::CARD => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'card_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::CARD ) );
		$this->add_control(
			'shadow_space',
			array(
				'type'        => 'slider',
				'label'       => __( 'Shadow room', 'uncoder' ),
				'description' => __( 'Space kept above and below the cards so shadows are not cut off.', 'uncoder' ),
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-carousel-bleed: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: quote */
		$this->start_section( 'style_quote', array( 'label' => __( 'Quote', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'quote_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::CARD . ' > .uncoder-testimonial-carousel__quote' ) );
		$this->add_control(
			'quote_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::CARD . ' > .uncoder-testimonial-carousel__quote' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'quote_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Quote icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 120 ) ),
				'selectors'  => array( self::CARD . ' > .uncoder-testimonial-carousel__mark' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'quote_icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Quote icon color', 'uncoder' ),
				'selectors' => array( self::CARD . ' > .uncoder-testimonial-carousel__mark' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: author */
		$this->start_section( 'style_author', array( 'label' => __( 'Author', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'avatar_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Photo size', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 16, 'max' => 160 ) ),
				'selectors'  => array( self::CARD => '--uncoder-tcar-avatar: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'avatar_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Photo radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( self::CARD . ' .uncoder-testimonial-carousel__avatar' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'author_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Photo spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( self::CARD . ' > .uncoder-testimonial-carousel__author' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_group( 'name_typography', array( 'type' => 'typography', 'label' => __( 'Name typography', 'uncoder' ), 'selector' => self::CARD . ' .uncoder-testimonial-carousel__name' ) );
		$this->add_control(
			'name_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Name color', 'uncoder' ),
				'selectors' => array( self::CARD . ' .uncoder-testimonial-carousel__name' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'role_typography', array( 'type' => 'typography', 'label' => __( 'Role typography', 'uncoder' ), 'selector' => self::CARD . ' .uncoder-testimonial-carousel__role' ) );
		$this->add_control(
			'role_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Role color', 'uncoder' ),
				'selectors' => array( self::CARD . ' .uncoder-testimonial-carousel__role' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: rating */
		$this->start_section(
			'style_rating',
			array(
				'label'     => __( 'Rating', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_rating' => 'yes' ),
			)
		);
		$this->add_control(
			'star_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Star size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 48 ) ),
				'selectors'  => array( self::CARD . ' > .uncoder-testimonial-carousel__rating' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'star_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Star spacing', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
				'selectors'  => array( self::CARD . ' > .uncoder-testimonial-carousel__rating' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'star_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( self::CARD . ' > .uncoder-testimonial-carousel__rating' => '--uncoder-tcar-star: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'star_empty_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Empty star color', 'uncoder' ),
				'selectors' => array( self::CARD . ' > .uncoder-testimonial-carousel__rating' => '--uncoder-tcar-star-empty: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->register_carousel_style();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array( 'data-settings' => $this->json_attr( $this->carousel_data( $s ) ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = Repeater_Rows::get( $this, 'items', $s['items'] ?? array() );
		if ( ! $rows ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-testimonial-carousel-placeholder">' . esc_html__( 'Add a testimonial to build the carousel.', 'uncoder' ) . '</div>';
			}
			return;
		}

		$layout   = in_array( $s['layout'] ?? 'classic', array( 'classic', 'centered', 'author' ), true ) ? $s['layout'] : 'classic';
		$tag      = Utils::tag( $s['name_tag'] ?? 'div', Utils::HEADING_TAGS, 'div' );
		$mark     = $this->has_icon( $s['quote_icon'] ?? null ) ? '<span class="uncoder-testimonial-carousel__mark" aria-hidden="true">' . $this->render_icon( $s['quote_icon'] ) . '</span>' : '';
		$ratings  = ! empty( $s['show_rating'] );
		$total    = count( $rows );

		echo $this->carousel_start( $s, $ctx, array( 'uncoder-testimonial-carousel', 'uncoder-testimonial-carousel--' . $layout ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
		foreach ( $rows as $i => $row ) {
			$rid   = sanitize_html_class( (string) ( $row['_id'] ?? '' ) );
			$quote = trim( (string) ( $row['quote'] ?? '' ) );
			$name  = trim( (string) ( $row['name'] ?? '' ) );
			$role  = trim( (string) ( $row['role'] ?? '' ) );

			$avatar = '';
			if ( is_array( $row['avatar'] ?? null ) ) {
				$media = $row['avatar'];
				unset( $media['alt'] );
				// The name is printed next to the photo, so the photo itself is decorative.
				$avatar = $this->image( $media, 'thumbnail', array( 'class' => 'uncoder-testimonial-carousel__avatar', 'alt' => '' ) );
			}

			$author = '';
			if ( '' !== $avatar || '' !== $name || '' !== $role ) {
				$author = '<figcaption class="uncoder-testimonial-carousel__author">' . $avatar . '<div class="uncoder-testimonial-carousel__meta">'
					. ( '' !== $name ? '<' . $tag . ' class="uncoder-testimonial-carousel__name">' . esc_html( $name ) . '</' . $tag . '>' : '' )
					. ( '' !== $role ? '<span class="uncoder-testimonial-carousel__role">' . esc_html( $role ) . '</span>' : '' )
					. '</div></figcaption>';
			}
			$body = '' !== $quote ? '<blockquote class="uncoder-testimonial-carousel__quote"><p>' . nl2br( esc_html( $quote ) ) . '</p></blockquote>' : '';
			$stars = $ratings ? $this->stars( $row['rating'] ?? 0 ) : '';

			echo $this->carousel_slide_start( $i, $total, '' !== $rid ? array( 'uncoder-ri-' . $rid ) : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
			echo '<figure class="uncoder-testimonial-carousel__card">';
			echo 'author' === $layout ? $author . $mark . $stars . $body : $mark . $stars . $body . $author; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts; $tag is allow-listed.
			echo '</figure></div>';
		}
		echo $this->carousel_end( $s, $ctx, $total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	}

	/**
	 * Five stars with full / half / empty states, announced as one image.
	 *
	 * @param mixed $rating Rating value.
	 */
	private function stars( $rating ): string {
		$rating = is_numeric( $rating ) ? round( min( max( (float) $rating, 0 ), 5 ) * 2 ) / 2 : 0;
		if ( $rating <= 0 ) {
			return '';
		}
		$star  = Icons::render( 'star', array( 'class' => 'uncoder-testimonial-carousel__star' ) );
		$half  = Icons::render( 'star-half', array( 'class' => 'uncoder-testimonial-carousel__star-half' ) );
		$label = sprintf(
			/* translators: %s: rating, e.g. 4.5. */
			__( 'Rated %s out of 5', 'uncoder' ),
			number_format_i18n( $rating, floor( $rating ) === $rating ? 0 : 1 )
		);
		$out = '<div class="uncoder-testimonial-carousel__rating" role="img" aria-label="' . esc_attr( $label ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$state = $rating >= $i ? 'full' : ( $rating >= $i - 0.5 ? 'half' : 'empty' );
			$out  .= '<span class="uncoder-testimonial-carousel__star-wrap is-' . $state . '">' . $star . ( 'half' === $state ? $half : '' ) . '</span>';
		}
		return $out . '</div>';
	}
}
