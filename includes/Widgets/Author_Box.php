<?php
/**
 * Author box widget.
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
 * Avatar, name, biography and links of the current post's author (or of the author archive).
 */
class Author_Box extends Widget_Base {

	public function name(): string {
		return 'author-box';
	}

	public function title(): string {
		return __( 'Author Box', 'uncoder' );
	}

	public function icon(): string {
		return 'square-user-round';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'author', 'bio', 'avatar', 'profile', 'writer', 'about' );
	}

	public function description(): string {
		return __( 'The author of the current post (or of an author archive): avatar, name linked to their posts, biography and website.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Author', 'uncoder' ) ) );
		$this->add_responsive_control(
			'layout',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Avatar position', 'uncoder' ),
				'default'              => 'left',
				'options'              => array(
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'panel-left' ),
					'top'   => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'panel-top' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'panel-right' ),
				),
				'selectors_dictionary' => array(
					'left'  => '--uncoder-author-dir:row;--uncoder-author-media-w:auto',
					'top'   => '--uncoder-author-dir:column;--uncoder-author-media-w:100%',
					'right' => '--uncoder-author-dir:row-reverse;--uncoder-author-media-w:auto',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'ai'                   => 'Use "top" on mobile ({"layout_mobile":"top"}) for narrow columns.',
			)
		);
		$this->add_control(
			'show_avatar',
			array(
				'type'    => 'switch',
				'label'   => __( 'Avatar', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'show_name',
			array(
				'type'    => 'switch',
				'label'   => __( 'Name', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'name_tag',
			array(
				'type'      => 'select',
				'label'     => __( 'Name HTML tag', 'uncoder' ),
				'default'   => 'h4',
				'options'   => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
				'condition' => array( 'show_name' => true ),
			)
		);
		$this->add_control(
			'link_name',
			array(
				'type'      => 'switch',
				'label'     => __( 'Link name to the author\'s posts', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'show_name' => true ),
			)
		);
		$this->add_control(
			'show_bio',
			array(
				'type'    => 'switch',
				'label'   => __( 'Biography', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'show_website',
			array(
				'type'  => 'switch',
				'label' => __( 'Website link', 'uncoder' ),
			)
		);
		$this->add_control(
			'website_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Website link text', 'uncoder' ),
				'default'   => __( 'Visit website', 'uncoder' ),
				'condition' => array( 'show_website' => true ),
			)
		);
		$this->add_control(
			'show_archive',
			array(
				'type'  => 'switch',
				'label' => __( 'All posts link', 'uncoder' ),
			)
		);
		$this->add_control(
			'archive_text',
			array(
				'type'      => 'text',
				'label'     => __( 'All posts link text', 'uncoder' ),
				'default'   => __( 'View all posts', 'uncoder' ),
				'condition' => array( 'show_archive' => true ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Text alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'box_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
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
		$this->add_responsive_control(
			'box_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'box_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->end_section();

		$this->start_section(
			'style_avatar',
			array(
				'label'     => __( 'Avatar', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_avatar' => true ),
			)
		);
		$this->add_responsive_control(
			'avatar_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 240 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-author-avatar: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'avatar_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-author-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'avatar_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-author-box__avatar' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'avatar_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-author-box__avatar' ) );
		$this->end_section();

		$this->start_section(
			'style_name',
			array(
				'label'     => __( 'Name', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_name' => true ),
			)
		);
		$this->add_group( 'name_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-author-box__name' ) );
		$this->add_control(
			'name_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-author-box__name' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'name_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-author-box__name a:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'name_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-author-box__name' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_bio',
			array(
				'label'     => __( 'Biography', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_bio' => true ),
			)
		);
		$this->add_group( 'bio_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-author-box__bio' ) );
		$this->add_control(
			'bio_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-author-box__bio' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_links', array( 'label' => __( 'Links', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'links_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-author-box__link' ) );
		$this->add_control(
			'links_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-author-box__link' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'links_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-author-box__link:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'links_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-author-box__links' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Author id for the context: the queried author on author archives, else the post author.
	 */
	private function author_id( Render_Context $ctx ): int {
		if ( ! $ctx->editor && is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof \WP_User ) {
				return (int) $author->ID;
			}
		}
		$post = Theme_Context::post( $ctx );
		return $post ? (int) $post->post_author : 0;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$author = $this->author_id( $ctx );
		$user   = $author ? get_userdata( $author ) : false;
		$sample = ! $user instanceof \WP_User;
		if ( $sample && ! $ctx->editor ) {
			return;
		}
		$demo = Theme_Context::sample();
		$name = $sample ? $demo['author'] : (string) $user->display_name;
		$bio  = $sample ? $demo['bio'] : (string) get_the_author_meta( 'description', $author );
		$url  = $sample ? '#' : get_author_posts_url( $author );
		$site = $sample ? '#' : (string) $user->user_url;

		echo '<div class="uncoder-author-box">';
		if ( ! empty( $s['show_avatar'] ) ) {
			$avatar = get_avatar(
				$sample ? '' : $author,
				192,
				$sample ? 'mystery' : '',
				'',
				array(
					'class'         => 'uncoder-author-box__avatar',
					'force_default' => $sample,
					'loading'       => 'lazy',
				)
			);
			if ( $avatar ) {
				echo '<div class="uncoder-author-box__media">' . $avatar . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core avatar markup.
			}
		}
		echo '<div class="uncoder-author-box__body">';
		if ( ! empty( $s['show_name'] ) ) {
			$tag  = Utils::tag( $s['name_tag'] ?? 'h4', Utils::HEADING_TAGS, 'h4' );
			$text = esc_html( $name );
			if ( ! empty( $s['link_name'] ) ) {
				$text = '<a href="' . esc_url( $url ) . '" rel="author">' . $text . '</a>';
			}
			echo '<' . $tag . ' class="uncoder-author-box__name">' . $text . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, escaped text.
		}
		if ( ! empty( $s['show_bio'] ) && '' !== trim( $bio ) ) {
			echo '<div class="uncoder-author-box__bio">' . wpautop( wp_kses_post( $bio ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd.
		}
		$links = array();
		if ( ! empty( $s['show_website'] ) && '' !== $site ) {
			$text    = trim( (string) ( $s['website_text'] ?? '' ) );
			$links[] = '<a class="uncoder-author-box__link uncoder-author-box__link--website" href="' . esc_url( $site ) . '" rel="noopener me" target="_blank">' . esc_html( '' !== $text ? $text : __( 'Visit website', 'uncoder' ) ) . '<span class="uncoder-sr-only"> ' . esc_html__( '(opens in a new tab)', 'uncoder' ) . '</span></a>';
		}
		if ( ! empty( $s['show_archive'] ) ) {
			$text    = trim( (string) ( $s['archive_text'] ?? '' ) );
			$links[] = '<a class="uncoder-author-box__link uncoder-author-box__link--archive" href="' . esc_url( $url ) . '">' . esc_html( '' !== $text ? $text : __( 'View all posts', 'uncoder' ) ) . '</a>';
		}
		if ( $links ) {
			echo '<div class="uncoder-author-box__links">' . implode( '', $links ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}
		echo '</div></div>';
	}
}
