<?php
/**
 * Archive title widget.
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
 * Title of the current archive (category, tag, author, date, post type) or search results page.
 */
class Archive_Title extends Widget_Base {

	public function name(): string {
		return 'archive-title';
	}

	public function title(): string {
		return __( 'Archive Title', 'uncoder' );
	}

	public function icon(): string {
		return 'heading';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'archive', 'title', 'category', 'tag', 'search', 'heading' );
	}

	public function description(): string {
		return __( 'The title of the current archive or search results page (e.g. "Category: Design"), with an option to drop the "Category:" prefix. For archive and search templates.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Title', 'uncoder' ) ) );
		$this->add_control(
			'tag',
			array(
				'type'    => 'select',
				'label'   => __( 'HTML tag', 'uncoder' ),
				'default' => 'h1',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control(
			'remove_prefix',
			array(
				'type'        => 'switch',
				'label'       => __( 'Remove prefix', 'uncoder' ),
				'description' => __( 'Shows "Design" instead of "Category: Design".', 'uncoder' ),
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

		$this->start_section( 'style_title', array( 'label' => __( 'Title', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'highlight_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Name color', 'uncoder' ),
				'description' => __( 'Colors the archive name after the prefix, or the search terms.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}} span' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', 'ch', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->add_group( 'text_shadow', array( 'type' => 'text_shadow', 'label' => __( 'Text shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'text_stroke_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Text stroke', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 10, 'step' => 0.5 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '-webkit-text-stroke-width: {{VALUE}}; stroke-width: {{VALUE}}' ),
				'ai'         => 'Outline around the letters, e.g. {"size":1,"unit":"px"}; with a transparent text color it gives outlined text.',
			)
		);
		$this->add_control(
			'text_stroke_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Stroke color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '-webkit-text-stroke-color: {{VALUE}}; stroke: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Archive title markup (inline HTML), same rules as the archive-title dynamic tag.
	 */
	public static function current_title( bool $remove_prefix ): string {
		if ( is_search() ) {
			$query = '<span>' . esc_html( get_search_query( false ) ) . '</span>';
			/* translators: %s: search query. */
			return $remove_prefix ? $query : sprintf( esc_html__( 'Search results for “%s”', 'uncoder' ), $query );
		}
		if ( is_home() && ! is_front_page() ) {
			return esc_html( get_the_title( (int) get_option( 'page_for_posts' ) ) );
		}
		if ( ! is_archive() ) {
			return '';
		}
		if ( $remove_prefix ) {
			add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		}
		$title = (string) get_the_archive_title();
		if ( $remove_prefix ) {
			remove_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		}
		return $title;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$remove = ! empty( $s['remove_prefix'] );
		$title  = $ctx->editor ? '' : self::current_title( $remove );
		if ( '' === trim( wp_strip_all_tags( $title ) ) ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$name  = '<span>' . esc_html( Theme_Context::sample()['category'] ) . '</span>';
			/* translators: %s: category name. */
			$title = $remove ? $name : sprintf( esc_html__( 'Category: %s', 'uncoder' ), $name );
		}
		$tag   = Utils::tag( $s['tag'] ?? 'h1', Utils::HEADING_TAGS, 'h1' );
		$class = 'uncoder-archive-title' . ( 'center' === ( $s['align'] ?? '' ) ? ' uncoder-archive-title--centered' : '' );
		echo '<' . $tag . ' class="' . esc_attr( $class ) . '">' . wp_kses( $title, Utils::kses_inline() ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, kses'd title.
	}
}
