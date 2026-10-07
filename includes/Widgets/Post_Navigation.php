<?php
/**
 * Post navigation widget.
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
 * Links to the previous and next posts, optionally within the same category or term.
 */
class Post_Navigation extends Widget_Base {

	public function name(): string {
		return 'post-navigation';
	}

	public function title(): string {
		return __( 'Post Navigation', 'uncoder' );
	}

	public function icon(): string {
		return 'arrow-left-right';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'navigation', 'next', 'previous', 'prev', 'post', 'pagination' );
	}

	public function description(): string {
		return __( 'Previous and next post links with labels, titles and arrows, for single post templates.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Navigation', 'uncoder' ) ) );
		$this->add_control(
			'show_label',
			array(
				'type'    => 'switch',
				'label'   => __( 'Labels', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'prev_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Previous label', 'uncoder' ),
				'default'   => __( 'Previous', 'uncoder' ),
				'condition' => array( 'show_label' => true ),
			)
		);
		$this->add_control(
			'next_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Next label', 'uncoder' ),
				'default'   => __( 'Next', 'uncoder' ),
				'condition' => array( 'show_label' => true ),
			)
		);
		$this->add_control(
			'show_title',
			array(
				'type'    => 'switch',
				'label'   => __( 'Post titles', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'show_arrow',
			array(
				'type'    => 'switch',
				'label'   => __( 'Arrows', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'prev_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Previous icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'arrow-left' ),
				'condition' => array( 'show_arrow' => true ),
			)
		);
		$this->add_control(
			'next_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Next icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'arrow-right' ),
				'condition' => array( 'show_arrow' => true ),
			)
		);
		$this->add_control(
			'in_same_term',
			array(
				'type'  => 'switch',
				'label' => __( 'Stay in the same term', 'uncoder' ),
			)
		);
		$this->add_control(
			'taxonomy',
			array(
				'type'            => 'select',
				'label'           => __( 'Taxonomy', 'uncoder' ),
				'default'         => 'category',
				'options_dynamic' => true,
				'options'         => Theme_Context::taxonomies(),
				'condition'       => array( 'in_same_term' => true ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_layout', array( 'label' => __( 'Layout', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_label',
			array(
				'label'     => __( 'Label', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_label' => true ),
			)
		);
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-post-nav__label' ) );
		$this->add_control(
			'label_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-nav__label' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_title',
			array(
				'label'     => __( 'Title', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_title' => true ),
			)
		);
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-post-nav__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-nav__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'title_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-nav__link:hover .uncoder-post-nav__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_arrow',
			array(
				'label'     => __( 'Arrows', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_arrow' => true ),
			)
		);
		$this->add_responsive_control(
			'arrow_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-post-nav__arrow' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'arrow_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-nav__arrow' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'arrow_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-nav__link:hover .uncoder-post-nav__arrow' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'arrow_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-post-nav__link' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * One side of the navigation.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function side( array $s, string $dir, string $title, string $url ): string {
		$prev  = 'prev' === $dir;
		$label = trim( (string) ( $s[ $dir . '_label' ] ?? '' ) );
		$label = '' !== $label ? $label : ( $prev ? __( 'Previous', 'uncoder' ) : __( 'Next', 'uncoder' ) );
		$arrow = '';
		if ( ! empty( $s['show_arrow'] ) ) {
			$icon  = $this->has_icon( $s[ $dir . '_icon' ] ?? null ) ? $s[ $dir . '_icon' ] : ( $prev ? 'arrow-left' : 'arrow-right' );
			$arrow = '<span class="uncoder-post-nav__arrow">' . $this->render_icon( $icon ) . '</span>';
		}
		$content = '';
		if ( ! empty( $s['show_label'] ) ) {
			$content .= '<span class="uncoder-post-nav__label">' . esc_html( $label ) . '</span>';
		} else {
			$content .= '<span class="uncoder-sr-only">' . esc_html( $label ) . ': </span>';
		}
		if ( ! empty( $s['show_title'] ) ) {
			$content .= '<span class="uncoder-post-nav__title">' . esc_html( $title ) . '</span>';
		} elseif ( empty( $s['show_label'] ) ) {
			$content .= '<span class="uncoder-sr-only">' . esc_html( $title ) . '</span>';
		}
		$link = '<a' . Utils::attrs(
			array(
				'class' => 'uncoder-post-nav__link uncoder-post-nav__link--' . $dir,
				'href'  => $url,
				'rel'   => $dir,
			)
		) . '>' . ( $prev ? $arrow : '' ) . '<span class="uncoder-post-nav__content">' . $content . '</span>' . ( $prev ? '' : $arrow ) . '</a>';
		return $link;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$post = Theme_Context::post( $ctx );
		$prev = null;
		$next = null;
		if ( $post ) {
			$same = ! empty( $s['in_same_term'] );
			$tax  = sanitize_key( (string) ( $s['taxonomy'] ?? 'category' ) );
			$tax  = '' !== $tax && taxonomy_exists( $tax ) ? $tax : 'category';
			list( $prev, $next ) = Theme_Context::with_post(
				$post,
				static function () use ( $same, $tax ): array {
					return array(
						get_adjacent_post( $same, '', true, $tax ),
						get_adjacent_post( $same, '', false, $tax ),
					);
				}
			);
		}
		$demo  = Theme_Context::sample();
		$items = array( 'prev' => '', 'next' => '' );
		if ( $prev instanceof \WP_Post ) {
			$items['prev'] = $this->side( $s, 'prev', wp_strip_all_tags( get_the_title( $prev ) ), (string) get_permalink( $prev ) );
		}
		if ( $next instanceof \WP_Post ) {
			$items['next'] = $this->side( $s, 'next', wp_strip_all_tags( get_the_title( $next ) ), (string) get_permalink( $next ) );
		}
		if ( '' === $items['prev'] && '' === $items['next'] ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$items['prev'] = $this->side( $s, 'prev', $demo['prev'], '#' );
			$items['next'] = $this->side( $s, 'next', $demo['next'], '#' );
		}
		echo '<nav class="uncoder-post-nav" aria-label="' . esc_attr__( 'Posts', 'uncoder' ) . '">';
		echo '<div class="uncoder-post-nav__item uncoder-post-nav__item--prev">' . $items['prev'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '<div class="uncoder-post-nav__item uncoder-post-nav__item--next">' . $items['next'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '</nav>';
	}
}
