<?php
/**
 * Post info (meta data) widget.
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
 * A row (or list) of post meta: author, dates, terms, comments, reading time and custom text.
 */
class Post_Info extends Widget_Base {

	public const TYPES = array( 'author', 'date', 'time', 'modified', 'categories', 'tags', 'terms', 'comments', 'reading-time', 'custom' );

	public function name(): string {
		return 'post-info';
	}

	public function title(): string {
		return __( 'Post Info', 'uncoder' );
	}

	public function icon(): string {
		return 'calendar-clock';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'meta', 'author', 'date', 'category', 'tags', 'comments', 'reading time', 'byline' );
	}

	public function description(): string {
		return __( 'Post meta data of the current post (author, date, categories, tags, comments, reading time, custom text) as an inline row or a list, with icons.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private static function default_items(): array {
		return array(
			array(
				'type' => 'author',
				'icon' => array( 'library' => 'lucide', 'value' => 'user-round' ),
				'link' => true,
			),
			array(
				'type' => 'date',
				'icon' => array( 'library' => 'lucide', 'value' => 'calendar' ),
				'link' => false,
			),
			array(
				'type' => 'categories',
				'icon' => array( 'library' => 'lucide', 'value' => 'folder' ),
				'link' => true,
			),
			array(
				'type' => 'comments',
				'icon' => array( 'library' => 'lucide', 'value' => 'message-circle' ),
				'link' => true,
			),
		);
	}

	public function preset(): array {
		return array( 'items' => self::default_items() );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Meta data', 'uncoder' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Items', 'uncoder' ),
				'title_field' => 'type',
				'default'     => self::default_items(),
				'fields'      => array(
					'type'          => array(
						'type'    => 'select',
						'label'   => __( 'Type', 'uncoder' ),
						'default' => 'date',
						'options' => array(
							'author'       => __( 'Author', 'uncoder' ),
							'date'         => __( 'Date', 'uncoder' ),
							'time'         => __( 'Time', 'uncoder' ),
							'modified'     => __( 'Last modified', 'uncoder' ),
							'categories'   => __( 'Categories', 'uncoder' ),
							'tags'         => __( 'Tags', 'uncoder' ),
							'terms'        => __( 'Other taxonomy', 'uncoder' ),
							'comments'     => __( 'Comments', 'uncoder' ),
							'reading-time' => __( 'Reading time', 'uncoder' ),
							'custom'       => __( 'Custom text', 'uncoder' ),
						),
					),
					'taxonomy'      => array(
						'type'            => 'select',
						'label'           => __( 'Taxonomy', 'uncoder' ),
						'options_dynamic' => true,
						'options'         => Theme_Context::taxonomies(),
						'condition'       => array( 'type' => 'terms' ),
					),
					'text'          => array(
						'type'      => 'text',
						'label'     => __( 'Text', 'uncoder' ),
						'condition' => array( 'type' => 'custom' ),
					),
					'custom_link'   => array(
						'type'      => 'url',
						'label'     => __( 'Link', 'uncoder' ),
						'condition' => array( 'type' => 'custom' ),
					),
					'before'        => array(
						'type'        => 'text',
						'label'       => __( 'Text before', 'uncoder' ),
						'placeholder' => __( 'e.g. By', 'uncoder' ),
					),
					'date_format'   => array(
						'type'      => 'select',
						'label'     => __( 'Format', 'uncoder' ),
						'options'   => Theme_Context::date_formats(),
						'condition' => array( 'type' => array( 'date', 'modified' ) ),
					),
					'time_format'   => array(
						'type'      => 'select',
						'label'     => __( 'Format', 'uncoder' ),
						'options'   => array(
							''       => __( 'Site default', 'uncoder' ),
							'g:i a'  => '3:45 pm',
							'g:i A'  => '3:45 PM',
							'H:i'    => '15:45',
							'custom' => __( 'Custom', 'uncoder' ),
						),
						'condition' => array( 'type' => 'time' ),
					),
					'custom_format' => array(
						'type'        => 'text',
						'label'       => __( 'Custom format', 'uncoder' ),
						'description' => __( 'PHP date format, e.g. l, F jS.', 'uncoder' ),
						'condition'   => array( 'type' => array( 'date', 'modified', 'time' ) ),
					),
					'wpm'           => array(
						'type'      => 'number',
						'label'     => __( 'Words per minute', 'uncoder' ),
						'min'       => 50,
						'max'       => 1000,
						'condition' => array( 'type' => 'reading-time' ),
					),
					'link'          => array(
						'type'      => 'switch',
						'label'     => __( 'Link', 'uncoder' ),
						'default'   => true,
						'condition' => array( 'type' => array( 'author', 'date', 'modified', 'categories', 'tags', 'terms', 'comments' ) ),
					),
					'avatar'        => array(
						'type'      => 'switch',
						'label'     => __( 'Show avatar instead of the icon', 'uncoder' ),
						'condition' => array( 'type' => 'author' ),
					),
					'icon'          => array(
						'type'  => 'icon',
						'label' => __( 'Icon', 'uncoder' ),
					),
					'color'         => array(
						'type'      => 'color',
						'label'     => __( 'Color', 'uncoder' ),
						'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => 'color: {{VALUE}}; --uncoder-pinfo-icon-color: {{VALUE}}' ),
					),
				),
			)
		);
		$this->add_control(
			'layout',
			array(
				'type'    => 'choose',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'inline',
				'options' => array(
					'inline' => array( 'label' => __( 'Inline', 'uncoder' ), 'icon' => 'move-horizontal' ),
					'list'   => array( 'label' => __( 'List', 'uncoder' ), 'icon' => 'list' ),
				),
			)
		);
		$this->add_control(
			'separator',
			array(
				'type'      => 'text',
				'label'     => __( 'Separator', 'uncoder' ),
				'default'   => '·',
				'condition' => array( 'layout' => 'inline' ),
			)
		);
		$this->add_control(
			'term_separator',
			array(
				'type'    => 'text',
				'label'   => __( 'Terms separator', 'uncoder' ),
				'default' => ', ',
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'start'  => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'end'    => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'start'  => '--uncoder-pinfo-align:flex-start;text-align:start',
					'center' => '--uncoder-pinfo-align:center;text-align:center',
					'end'    => '--uncoder-pinfo-align:flex-end;text-align:end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_items', array( 'label' => __( 'Items', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} a' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} a:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between items', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-pinfo-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'separator_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Separator color', 'uncoder' ),
				'condition' => array( 'layout' => 'inline' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-post-info__sep' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'divider',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'condition' => array( 'layout' => 'list' ),
				'selectors' => array( '{{WRAPPER}}.uncoder-post-info--list > .uncoder-post-info__item + .uncoder-post-info__item' => 'border-top: 1px solid {{VALUE}}; padding-top: var(--uncoder-pinfo-gap, 8px)' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_icon', array( 'label' => __( 'Icons & avatar', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-pinfo-icon-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-pinfo-icon-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-pinfo-icon-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'avatar_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Avatar size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 96 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-pinfo-avatar: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'avatar_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Avatar radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-post-info__avatar' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Linked (or plain) list of terms.
	 */
	private function terms( \WP_Post $post, string $taxonomy, bool $link, string $sep ): string {
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) || ! $terms ) {
			return '';
		}
		$out = array();
		foreach ( $terms as $term ) {
			$url   = $link ? get_term_link( $term ) : '';
			$out[] = $link && is_string( $url )
				? '<a href="' . esc_url( $url ) . '" rel="tag">' . esc_html( $term->name ) . '</a>'
				: esc_html( $term->name );
		}
		return implode( esc_html( $sep ), $out );
	}

	/**
	 * Markup of one item's value, or '' to skip the item.
	 *
	 * @param array<string,mixed> $row Row.
	 */
	private function value( array $row, ?\WP_Post $post, string $term_sep, bool $sample ): string {
		$type = (string) ( $row['type'] ?? 'date' );
		$link = ! empty( $row['link'] );
		$demo = Theme_Context::sample();

		switch ( $type ) {
			case 'author':
				if ( $sample ) {
					return $link ? '<a href="#">' . esc_html( $demo['author'] ) . '</a>' : esc_html( $demo['author'] );
				}
				$author = (int) $post->post_author;
				$name   = (string) get_the_author_meta( 'display_name', $author );
				if ( '' === $name ) {
					return '';
				}
				return $link ? '<a href="' . esc_url( get_author_posts_url( $author ) ) . '" rel="author">' . esc_html( $name ) . '</a>' : esc_html( $name );

			case 'date':
			case 'modified':
			case 'time':
				$format = 'time' === $type ? (string) ( $row['time_format'] ?? '' ) : (string) ( $row['date_format'] ?? '' );
				$text   = Theme_Context::post_date( $sample ? null : $post, $format, (string) ( $row['custom_format'] ?? '' ), 'modified' === $type, 'time' === $type );
				$iso    = $sample ? wp_date( 'c' ) : ( 'modified' === $type ? get_the_modified_date( 'c', $post ) : get_the_date( 'c', $post ) );
				$time   = '<time datetime="' . esc_attr( (string) $iso ) . '">' . esc_html( $text ) . '</time>';
				if ( $link && 'time' !== $type ) {
					return '<a href="' . esc_url( $sample ? '#' : (string) get_permalink( $post ) ) . '">' . $time . '</a>';
				}
				return $time;

			case 'categories':
			case 'tags':
			case 'terms':
				$taxonomy = 'categories' === $type ? 'category' : ( 'tags' === $type ? 'post_tag' : sanitize_key( (string) ( $row['taxonomy'] ?? '' ) ) );
				if ( $sample ) {
					$name = 'post_tag' === $taxonomy ? $demo['tag'] : $demo['category'];
					return $link ? '<a href="#">' . esc_html( $name ) . '</a>' : esc_html( $name );
				}
				if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) || ! is_taxonomy_viewable( $taxonomy ) ) {
					return '';
				}
				return $this->terms( $post, $taxonomy, $link, $term_sep );

			case 'comments':
				if ( $sample ) {
					$count = 3;
				} else {
					$count = (int) get_comments_number( $post );
					if ( ! $count && ! comments_open( $post ) ) {
						return '';
					}
				}
				/* translators: %s: number of comments. */
				$text = $count ? sprintf( _n( '%s comment', '%s comments', $count, 'uncoder' ), number_format_i18n( $count ) ) : __( 'No comments', 'uncoder' );
				if ( $link ) {
					return '<a href="' . esc_url( $sample ? '#' : (string) get_comments_link( $post ) ) . '">' . esc_html( $text ) . '</a>';
				}
				return esc_html( $text );

			case 'reading-time':
				$wpm     = (int) ( $row['wpm'] ?? 200 );
				$minutes = $sample ? 4 : Theme_Context::reading_minutes( $post, $wpm > 0 ? $wpm : 200 );
				/* translators: %s: number of minutes. */
				return esc_html( sprintf( _n( '%s min read', '%s min read', $minutes, 'uncoder' ), number_format_i18n( $minutes ) ) );

			case 'custom':
				$text = trim( (string) ( $row['text'] ?? '' ) );
				if ( '' === $text ) {
					return '';
				}
				$attrs = $this->link_attrs( $row['custom_link'] ?? array() );
				return $attrs ? '<a' . Utils::attrs( $attrs ) . '>' . esc_html( $text ) . '</a>' : esc_html( $text );
		}
		return '';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$post   = Theme_Context::post( $ctx );
		$sample = null === $post;
		if ( $sample && ! $ctx->editor ) {
			return;
		}
		$rows     = is_array( $s['items'] ?? null ) ? $s['items'] : array();
		$inline   = 'list' !== ( $s['layout'] ?? 'inline' );
		$sep      = (string) ( $s['separator'] ?? '·' );
		// Text controls are trimmed on save: add the spacing back around the terms separator.
		$term_sep = trim( (string) ( $s['term_separator'] ?? ',' ) );
		$term_sep = in_array( $term_sep, array( ',', ';', '' ), true ) ? $term_sep . ' ' : ' ' . $term_sep . ' ';
		$items    = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! in_array( $row['type'] ?? '', self::TYPES, true ) ) {
				continue;
			}
			$value = $this->value( $row, $post, $term_sep, $sample );
			if ( '' === $value ) {
				continue;
			}
			$media = '';
			if ( 'author' === $row['type'] && ! empty( $row['avatar'] ) ) {
				$media = (string) get_avatar(
					$sample ? '' : (int) $post->post_author,
					64,
					$sample ? 'mystery' : '',
					'',
					array(
						'class'         => 'uncoder-post-info__avatar',
						'force_default' => $sample,
					)
				);
			} elseif ( $this->has_icon( $row['icon'] ?? null ) ) {
				$media = '<span class="uncoder-post-info__icon">' . $this->render_icon( $row['icon'] ) . '</span>';
			}
			$before = trim( (string) ( $row['before'] ?? '' ) );
			$class  = 'uncoder-post-info__item uncoder-post-info__item--' . $row['type'];
			if ( ! empty( $row['_id'] ) && is_string( $row['_id'] ) ) {
				$class .= ' uncoder-ri-' . sanitize_html_class( $row['_id'] );
			}
			$items[] = array(
				'class' => $class,
				'html'  => $media . '<span class="uncoder-post-info__text">' . ( '' !== $before ? '<span class="uncoder-post-info__before">' . esc_html( $before ) . '</span> ' : '' ) . $value . '</span>',
			);
		}
		if ( ! $items ) {
			return;
		}

		echo '<ul class="' . esc_attr( 'uncoder-post-info uncoder-post-info--' . ( $inline ? 'inline' : 'list' ) ) . '">';
		foreach ( $items as $i => $item ) {
			echo '<li class="' . esc_attr( $item['class'] ) . '">';
			if ( $inline && $i > 0 && '' !== trim( $sep ) ) {
				echo '<span class="uncoder-post-info__sep" aria-hidden="true">' . esc_html( $sep ) . '</span>';
			}
			echo $item['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '</li>';
		}
		echo '</ul>';
	}
}
