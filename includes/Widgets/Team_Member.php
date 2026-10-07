<?php
/**
 * Team Member widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Person card: photo, name, role, bio and social links, as a card or with the details over the photo.
 */
class Team_Member extends Widget_Base {

	public function name(): string {
		return 'team-member';
	}

	public function title(): string {
		return __( 'Team Member', 'uncoder' );
	}

	public function icon(): string {
		return 'contact-round';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'team', 'member', 'person', 'staff', 'profile', 'author', 'social' );
	}

	public function description(): string {
		return __( 'Person card with photo, name, role, short bio and social links. Layouts: card (text under the photo) or overlay (name on the photo, bio and links revealed on hover).', 'uncoder' );
	}

	/**
	 * @return array<string,string>
	 */
	public static function social_networks(): array {
		return array(
			'linkedin'  => 'LinkedIn',
			'x'         => 'X',
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'github'    => 'GitHub',
			'youtube'   => 'YouTube',
			'tiktok'    => 'TikTok',
			'pinterest' => 'Pinterest',
			'whatsapp'  => 'WhatsApp',
			'telegram'  => 'Telegram',
			'website'   => __( 'Website', 'uncoder' ),
			'email'     => __( 'Email', 'uncoder' ),
			'phone'     => __( 'Phone', 'uncoder' ),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content_member', array( 'label' => __( 'Member', 'uncoder' ) ) );
		$this->add_control(
			'image',
			array(
				'type'    => 'media',
				'label'   => __( 'Photo', 'uncoder' ),
				'default' => array( 'id' => 0, 'url' => '' ),
				'dynamic' => true,
				'ai'      => 'Portrait photo from the media library ({"id": …}).',
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
					'medium'       => 'Medium',
					'medium_large' => 'Medium large',
					'large'        => 'Large',
					'full'         => 'Full',
				),
			)
		);
		$this->add_control(
			'name',
			array(
				'type'    => 'text',
				'label'   => __( 'Name', 'uncoder' ),
				'default' => __( 'Maya Chen', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'name_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Name HTML tag', 'uncoder' ),
				'default' => 'h3',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control(
			'role',
			array(
				'type'    => 'text',
				'label'   => __( 'Role', 'uncoder' ),
				'default' => __( 'Head of Product', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'bio',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Bio', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 3,
				'default' => __( 'Maya turns customer research into clear roadmaps and has shipped products used by two million people.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'link',
			array(
				'type'        => 'url',
				'label'       => __( 'Profile link', 'uncoder' ),
				'description' => __( 'Links the name (and photo).', 'uncoder' ),
				'dynamic'     => true,
			)
		);
		$this->end_section();

		$this->start_section( 'content_social', array( 'label' => __( 'Social links', 'uncoder' ) ) );
		$this->add_control(
			'social',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Links', 'uncoder' ),
				'title_field' => 'network',
				'fields'      => array(
					'network' => array(
						'type'    => 'select',
						'label'   => __( 'Network', 'uncoder' ),
						'default' => 'linkedin',
						'options' => self::social_networks(),
					),
					'url'     => array(
						'type'    => 'url',
						'label'   => __( 'URL', 'uncoder' ),
						'default' => array( 'url' => '#' ),
					),
				),
				'default'     => array(
					array(
						'network' => 'linkedin',
						'url'     => array( 'url' => '#' ),
					),
					array(
						'network' => 'x',
						'url'     => array( 'url' => '#' ),
					),
				),
				'ai'          => 'Rows: {"network":"linkedin|x|facebook|instagram|github|youtube|tiktok|pinterest|whatsapp|telegram|website|email|phone","url":{"url":"https://…"}}. Use mailto:/tel: for email/phone.',
			)
		);
		$this->end_section();

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'card',
				'options' => array(
					'card'    => __( 'Card (text below photo)', 'uncoder' ),
					'overlay' => __( 'Overlay (text on photo)', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'reveal',
			array(
				'type'      => 'select',
				'label'     => __( 'Bio & links', 'uncoder' ),
				'default'   => 'hover',
				'options'   => array(
					'hover'  => __( 'Reveal on hover / focus', 'uncoder' ),
					'always' => __( 'Always visible', 'uncoder' ),
				),
				'condition' => array( 'layout' => 'overlay' ),
			)
		);
		$this->add_responsive_control(
			'image_ratio',
			array(
				'type'      => 'select',
				'label'     => __( 'Photo ratio', 'uncoder' ),
				'options'   => array(
					''     => __( 'Default (4:5)', 'uncoder' ),
					'1/1'  => '1:1',
					'3/4'  => '3:4',
					'2/3'  => '2:3',
					'4/3'  => '4:3',
					'16/9' => '16:9',
					'auto' => __( 'Original', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-team-member__image' => 'aspect-ratio: {{VALUE}}' ),
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
					'left'   => '--uncoder-tm-text:start;--uncoder-tm-justify:flex-start',
					'center' => '--uncoder-tm-text:center;--uncoder-tm-justify:center',
					'right'  => '--uncoder-tm-text:end;--uncoder-tm-justify:flex-end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'image_hover',
			array(
				'type'    => 'select',
				'label'   => __( 'Photo hover effect', 'uncoder' ),
				'default' => 'zoom',
				'options' => array(
					''          => __( 'None', 'uncoder' ),
					'zoom'      => __( 'Zoom', 'uncoder' ),
					'grayscale' => __( 'Grayscale to color', 'uncoder' ),
				),
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_card', array( 'label' => __( 'Card', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'card_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_group( 'card_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'card_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'card_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'content_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Text padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-team-member__content' => 'padding: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_image', array( 'label' => __( 'Photo', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'image_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-team-member__media' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'image_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( '%', 'px', 'rem' ),
				'condition'  => array( 'layout' => 'card' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-team-member__media' => 'width: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'object_position',
			array(
				'type'      => 'select',
				'label'     => __( 'Focal point', 'uncoder' ),
				'options'   => \Uncoder\Builder\Controls\Groups\Background::POSITIONS,
				'selectors' => array( '{{WRAPPER}} .uncoder-team-member__image' => 'object-position: {{VALUE}}' ),
			)
		);
		$this->add_group( 'image_filters', array( 'type' => 'css_filters', 'label' => __( 'CSS filters', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-team-member__image' ) );
		$this->add_group(
			'overlay_background',
			array(
				'type'      => 'background',
				'label'     => __( 'Overlay', 'uncoder' ),
				'types'     => array( 'classic', 'gradient' ),
				'condition' => array( 'layout' => 'overlay' ),
				'selector'  => '{{WRAPPER}} .uncoder-team-member__content',
			)
		);
		$this->end_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'name_typography', array( 'type' => 'typography', 'label' => __( 'Name typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-team-member__name' ) );
		$this->add_control(
			'name_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Name color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-team-member__name' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'role_typography', array( 'type' => 'typography', 'label' => __( 'Role typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-team-member__role' ) );
		$this->add_control(
			'role_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Role color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-team-member__role' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'bio_typography', array( 'type' => 'typography', 'label' => __( 'Bio typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-team-member__bio' ) );
		$this->add_control(
			'bio_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Bio color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-team-member__bio' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'text_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between lines', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-tm-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_social', array( 'label' => __( 'Social links', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'social_colors',
			array(
				'type'    => 'select',
				'label'   => __( 'Colors', 'uncoder' ),
				'default' => 'neutral',
				'options' => array(
					'neutral' => __( 'Neutral', 'uncoder' ),
					'brand'   => __( 'Brand colors', 'uncoder' ),
				),
			)
		);
		$this->add_responsive_control(
			'social_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Button size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 72 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-tm-social: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'social_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-team-member__social-link' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'social_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-team-member__social' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'social_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-team-member__social-link' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'social_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( 'social_color', array( 'type' => 'color', 'label' => __( 'Icon color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-team-member__social-link' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'social_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-team-member__social-link' => 'background-color: {{VALUE}}' ) ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( 'social_hover_color', array( 'type' => 'color', 'label' => __( 'Icon color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-team-member__social-link:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'social_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-team-member__social-link:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ) );
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();
	}

	/**
	 * Accessible name of a social link ("Maya Chen on LinkedIn", "Email Maya Chen"…).
	 */
	private function social_label( string $network, string $label, string $name ): string {
		if ( '' === $name ) {
			return $label;
		}
		switch ( $network ) {
			case 'email':
				/* translators: %s: person name. */
				return sprintf( __( 'Email %s', 'uncoder' ), $name );
			case 'phone':
				/* translators: %s: person name. */
				return sprintf( __( 'Call %s', 'uncoder' ), $name );
			case 'website':
				/* translators: %s: person name. */
				return sprintf( __( 'Website of %s', 'uncoder' ), $name );
		}
		/* translators: 1: person name, 2: network name (LinkedIn, GitHub…). */
		return sprintf( __( '%1$s on %2$s', 'uncoder' ), $name, $label );
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function social_html( array $s, string $name ): string {
		$rows     = is_array( $s['social'] ?? null ) ? $s['social'] : array();
		$networks = self::social_networks();
		$html     = '';
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$network = (string) ( $row['network'] ?? '' );
			$attrs   = $this->link_attrs( $row['url'] ?? array() );
			if ( ! isset( $networks[ $network ] ) || ! $attrs ) {
				continue;
			}
			$attrs['class']      = 'uncoder-team-member__social-link uncoder-team-member__social-link--' . $network;
			$attrs['aria-label'] = $this->social_label( $network, $networks[ $network ], $name );
			$html .= '<li><a' . Utils::attrs( $attrs ) . '>' . Share_Buttons::network_icon( $network ) . '</a></li>';
		}
		return '' !== $html ? '<ul class="uncoder-team-member__social">' . $html . '</ul>' : '';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$layout = 'overlay' === ( $s['layout'] ?? 'card' ) ? 'overlay' : 'card';
		$name   = trim( (string) ( $s['name'] ?? '' ) );
		$role   = trim( (string) ( $s['role'] ?? '' ) );
		$bio    = $this->inline_html( $s['bio'] ?? '' );
		$link   = $this->link_attrs( $s['link'] ?? array() );

		// The name sits right next to the photo, so an image without its own alt text stays decorative.
		$img = $this->image( $s['image'] ?? array(), sanitize_key( (string) ( $s['image_size'] ?? 'medium_large' ) ), array( 'class' => 'uncoder-team-member__image' ) );
		if ( '' === $img && $ctx->editor ) {
			$img = '<img class="uncoder-team-member__image uncoder-team-member__image--placeholder" src="' . esc_url( $this->placeholder_image() ) . '" alt="">';
		}
		if ( '' === $img && '' === $name && '' === $role && ! $ctx->editor ) {
			return;
		}

		if ( '' === $img ) {
			$layout = 'card';
		}
		$classes = array( 'uncoder-team-member', 'uncoder-team-member--' . $layout );
		if ( 'overlay' === $layout && 'always' === ( $s['reveal'] ?? 'hover' ) ) {
			$classes[] = 'uncoder-team-member--reveal-always';
		}
		if ( in_array( $s['image_hover'] ?? '', array( 'zoom', 'grayscale' ), true ) ) {
			$classes[] = 'uncoder-team-member--hover-' . $s['image_hover'];
		}
		if ( 'brand' === ( $s['social_colors'] ?? 'neutral' ) ) {
			$classes[] = 'uncoder-team-member--brand';
		}
		if ( '' === $img ) {
			$classes[] = 'uncoder-team-member--no-image';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( '' !== $img ) {
			if ( $link ) {
				// The name carries the accessible link; the photo link is a pointer-only duplicate.
				$photo_link                = $link;
				$photo_link['class']       = 'uncoder-team-member__media';
				$photo_link['tabindex']    = '-1';
				$photo_link['aria-hidden'] = 'true';
				echo '<a' . Utils::attrs( $photo_link ) . '>' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped attributes, core image markup.
			} else {
				echo '<div class="uncoder-team-member__media">' . $img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
			}
		}

		echo '<div class="uncoder-team-member__content">';
		if ( '' !== $name || $ctx->editor ) {
			$tag = Utils::tag( $s['name_tag'] ?? 'h3', Utils::HEADING_TAGS, 'h3' );
			if ( $link ) {
				$link['class'] = 'uncoder-team-member__link';
				echo '<' . $tag . ' class="uncoder-team-member__name"><a' . Utils::attrs( $link ) . $ctx->inline( 'name' ) . '>' . esc_html( $name ) . '</a></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, escaped parts.
			} else {
				echo '<' . $tag . ' class="uncoder-team-member__name"' . $ctx->inline( 'name' ) . '>' . esc_html( $name ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, escaped parts.
			}
		}
		if ( '' !== $role ) {
			echo '<p class="uncoder-team-member__role"' . $ctx->inline( 'role' ) . '>' . esc_html( $role ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
		}
		$social  = $this->social_html( $s, $name );
		$has_bio = '' !== trim( wp_strip_all_tags( $bio ) );
		if ( $has_bio || '' !== $social ) {
			echo '<div class="uncoder-team-member__details"><div class="uncoder-team-member__details-inner">';
			if ( $has_bio ) {
				echo '<p class="uncoder-team-member__bio"' . $ctx->inline( 'bio' ) . '>' . $bio . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
			}
			echo $social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '</div></div>';
		}
		echo '</div></div>';
	}
}
