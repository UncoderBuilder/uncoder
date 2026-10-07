<?php
/**
 * Breadcrumbs widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Trail from the home page to the current page, with BreadcrumbList structured data.
 */
class Breadcrumbs extends Widget_Base {

	/** Structured data is printed once per page. */
	private static bool $schema_queued = false;

	public function name(): string {
		return 'breadcrumbs';
	}

	public function title(): string {
		return __( 'Breadcrumbs', 'uncoder' );
	}

	public function icon(): string {
		return 'chevrons-right';
	}

	public function category(): string {
		return 'content';
	}

	public function keywords(): array {
		return array( 'breadcrumbs', 'trail', 'navigation', 'path', 'seo' );
	}

	public function description(): string {
		return __( 'Breadcrumb trail (Home › Category › Post) for any page type, with Google BreadcrumbList structured data.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Breadcrumbs', 'uncoder' ) ) );
		$this->add_control(
			'show_home',
			array(
				'type'    => 'switch',
				'label'   => __( 'Home link', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'home_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Home label', 'uncoder' ),
				'default'   => __( 'Home', 'uncoder' ),
				'condition' => array( 'show_home' => true ),
			)
		);
		$this->add_control(
			'home_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Home icon', 'uncoder' ),
				'condition' => array( 'show_home' => true ),
			)
		);
		$this->add_control(
			'home_icon_only',
			array(
				'type'        => 'switch',
				'label'       => __( 'Icon only', 'uncoder' ),
				'description' => __( 'The label stays available to screen readers.', 'uncoder' ),
				'condition'   => array( 'show_home' => true ),
			)
		);
		$this->add_control(
			'separator_type',
			array(
				'type'    => 'choose',
				'label'   => __( 'Separator', 'uncoder' ),
				'default' => 'text',
				'options' => array(
					'text' => array( 'label' => __( 'Text', 'uncoder' ), 'icon' => 'type' ),
					'icon' => array( 'label' => __( 'Icon', 'uncoder' ), 'icon' => 'chevron-right' ),
				),
			)
		);
		$this->add_control(
			'separator',
			array(
				'type'      => 'text',
				'label'     => __( 'Separator text', 'uncoder' ),
				'default'   => '/',
				'condition' => array( 'separator_type' => 'text' ),
			)
		);
		$this->add_control(
			'separator_icon',
			array(
				'type'      => 'icon',
				'label'     => __( 'Separator icon', 'uncoder' ),
				'default'   => array( 'library' => 'lucide', 'value' => 'chevron-right' ),
				'condition' => array( 'separator_type' => 'icon' ),
			)
		);
		$this->add_control(
			'show_current',
			array(
				'type'    => 'switch',
				'label'   => __( 'Current page', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'schema',
			array(
				'type'        => 'switch',
				'label'       => __( 'Structured data', 'uncoder' ),
				'description' => __( 'Adds BreadcrumbList JSON-LD for search engines. Skipped automatically when Yoast SEO is active.', 'uncoder' ),
				'default'     => true,
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
					'start'  => 'flex-start',
					'center' => 'center',
					'end'    => 'flex-end',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-breadcrumbs__list' => 'justify-content: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_items', array( 'label' => __( 'Breadcrumbs', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->start_tabs( 'item_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-breadcrumbs__link' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-breadcrumbs__link:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'current', __( 'Current', 'uncoder' ) );
		$this->add_control(
			'current_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-breadcrumbs__current' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_group( 'current_typography', array( 'type' => 'typography', 'label' => __( 'Current page typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-breadcrumbs__current' ) );
		$this->add_responsive_control(
			'current_max_width',
			array(
				'type'        => 'slider',
				'label'       => __( 'Current page max width', 'uncoder' ),
				'description' => __( 'Long titles are shortened with an ellipsis.', 'uncoder' ),
				'size_units'  => array( 'px', 'ch', '%', 'rem' ),
				'selectors'   => array( '{{WRAPPER}} .uncoder-breadcrumbs__current' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_separator', array( 'label' => __( 'Separator & icons', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'separator_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Separator color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-breadcrumbs__sep' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'separator_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Separator spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-bc-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-bc-icon: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Crumb for a term and its ancestors.
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	private function term_crumbs( \WP_Term $term, bool $link_last = true ): array {
		$out = array();
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( (int) $ancestor_id, $term->taxonomy );
			if ( $ancestor instanceof \WP_Term ) {
				$url   = get_term_link( $ancestor );
				$out[] = array(
					'label' => $ancestor->name,
					'url'   => is_string( $url ) ? $url : '',
				);
			}
		}
		$url   = $link_last ? get_term_link( $term ) : '';
		$out[] = array(
			'label' => $term->name,
			'url'   => is_string( $url ) ? $url : '',
		);
		return $out;
	}

	/**
	 * Primary term of a post: SEO plugin primary term, else the deepest assigned term.
	 */
	private function primary_term( \WP_Post $post, string $taxonomy ): ?\WP_Term {
		foreach ( array( '_yoast_wpseo_primary_' . $taxonomy, 'rank_math_primary_' . $taxonomy ) as $key ) {
			$id = (int) get_post_meta( $post->ID, $key, true );
			if ( $id && has_term( $id, $taxonomy, $post ) ) {
				$term = get_term( $id, $taxonomy );
				if ( $term instanceof \WP_Term ) {
					return $term;
				}
			}
		}
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) || ! $terms ) {
			return null;
		}
		$best  = null;
		$depth = -1;
		foreach ( $terms as $term ) {
			$d = count( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) );
			if ( $d > $depth ) {
				$best  = $term;
				$depth = $d;
			}
		}
		return $best;
	}

	/**
	 * Blog page crumb when the posts page is a static page.
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	private function blog_crumb(): array {
		$blog = (int) get_option( 'page_for_posts' );
		if ( $blog && 'page' === get_option( 'show_on_front' ) ) {
			return array(
				array(
					'label' => wp_strip_all_tags( get_the_title( $blog ) ),
					'url'   => (string) get_permalink( $blog ),
				),
			);
		}
		return array();
	}

	/**
	 * Crumbs between home and a singular post (excluding the post itself).
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	private function singular_parents( \WP_Post $post ): array {
		$out = array();
		if ( 'post' === $post->post_type ) {
			$out  = $this->blog_crumb();
			$term = $this->primary_term( $post, 'category' );
			if ( $term ) {
				$out = array_merge( $out, $this->term_crumbs( $term ) );
			}
			return $out;
		}
		if ( 'attachment' === $post->post_type ) {
			if ( $post->post_parent && get_post( $post->post_parent ) ) {
				$parent = get_post( $post->post_parent );
				$out    = $this->singular_parents( $parent );
				$out[]  = array(
					'label' => wp_strip_all_tags( get_the_title( $parent ) ),
					'url'   => (string) get_permalink( $parent ),
				);
			}
			return $out;
		}
		$type = get_post_type_object( $post->post_type );
		if ( $type && 'page' !== $post->post_type && $type->has_archive ) {
			$url = get_post_type_archive_link( $post->post_type );
			if ( $url ) {
				$out[] = array(
					'label' => (string) $type->labels->name,
					'url'   => $url,
				);
			}
		}
		if ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
				// A private or draft parent page is not named to visitors who cannot read it.
				if ( ! is_post_publicly_viewable( $ancestor ) && ! current_user_can( 'read_post', $ancestor ) ) {
					continue;
				}
				$out[] = array(
					'label' => wp_strip_all_tags( get_the_title( $ancestor ) ),
					'url'   => (string) get_permalink( $ancestor ),
				);
			}
			return $out;
		}
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) {
			if ( $tax->hierarchical && $tax->public ) {
				$term = $this->primary_term( $post, $tax->name );
				if ( $term ) {
					$out = array_merge( $out, $this->term_crumbs( $term ) );
				}
				break;
			}
		}
		return $out;
	}

	/**
	 * Full trail for the current request (home and current page included; current has no URL).
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	private function trail( string $home_label ): array {
		$home  = array(
			'label' => $home_label,
			'url'   => home_url( '/' ),
		);
		$trail = array( $home );

		if ( is_front_page() ) {
			$trail[0]['url'] = '';
			return $trail;
		}

		if ( is_home() ) {
			$blog    = (int) get_option( 'page_for_posts' );
			$trail[] = array(
				'label' => $blog ? wp_strip_all_tags( get_the_title( $blog ) ) : __( 'Blog', 'uncoder' ),
				'url'   => '',
			);
		} elseif ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$trail   = array_merge( $trail, $this->singular_parents( $post ) );
				$trail[] = array(
					'label' => wp_strip_all_tags( get_the_title( $post ) ),
					'url'   => '',
				);
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$tax = get_taxonomy( $term->taxonomy );
				if ( $tax && 1 === count( (array) $tax->object_type ) ) {
					$type = (string) $tax->object_type[0];
					if ( 'post' === $type ) {
						$trail = array_merge( $trail, $this->blog_crumb() );
					} else {
						$object = get_post_type_object( $type );
						$url    = $object && $object->has_archive ? get_post_type_archive_link( $type ) : '';
						if ( $url ) {
							$trail[] = array(
								'label' => (string) $object->labels->name,
								'url'   => $url,
							);
						}
					}
				}
				$trail = array_merge( $trail, $this->term_crumbs( $term, false ) );
			}
		} elseif ( is_post_type_archive() ) {
			$trail[] = array(
				'label' => (string) post_type_archive_title( '', false ),
				'url'   => '',
			);
		} elseif ( is_author() ) {
			$trail[] = array(
				'label' => (string) get_the_author_meta( 'display_name', (int) get_queried_object_id() ),
				'url'   => '',
			);
		} elseif ( is_date() ) {
			// Pretty permalinks use year/monthnum/day, plain ones the compact "m" (YYYYMMDD) var.
			$m     = preg_replace( '/\D/', '', (string) get_query_var( 'm' ) );
			$year  = (int) get_query_var( 'year' );
			$month = (int) get_query_var( 'monthnum' );
			$day   = (int) get_query_var( 'day' );
			$year  = $year ? $year : (int) substr( $m, 0, 4 );
			$month = $month ? $month : (int) substr( $m, 4, 2 );
			$day   = $day ? $day : (int) substr( $m, 6, 2 );
			if ( $year ) {
				$trail[] = array(
					'label' => (string) $year,
					'url'   => $month ? get_year_link( $year ) : '',
				);
			}
			if ( $year && $month ) {
				$trail[] = array(
					'label' => $GLOBALS['wp_locale']->get_month( $month ),
					'url'   => $day ? get_month_link( $year, $month ) : '',
				);
			}
			if ( $year && $month && $day ) {
				$trail[] = array(
					'label' => (string) $day,
					'url'   => '',
				);
			}
		} elseif ( is_search() ) {
			$trail[] = array(
				/* translators: %s: search query. */
				'label' => sprintf( __( 'Search results for “%s”', 'uncoder' ), get_search_query( false ) ),
				'url'   => '',
			);
		} elseif ( is_404() ) {
			$trail[] = array(
				'label' => __( 'Page not found', 'uncoder' ),
				'url'   => '',
			);
		}

		$paged = (int) get_query_var( 'paged' );
		if ( $paged > 1 && count( $trail ) > 1 && ! is_singular() ) {
			$last                  = count( $trail ) - 1;
			$trail[ $last ]['url'] = '' === $trail[ $last ]['url'] ? get_pagenum_link( 1 ) : $trail[ $last ]['url'];
			$trail[]               = array(
				/* translators: %s: page number. */
				'label' => sprintf( __( 'Page %s', 'uncoder' ), number_format_i18n( $paged ) ),
				'url'   => '',
			);
		}
		return $trail;
	}

	/**
	 * Editor trail: the previewed post when there is one, otherwise sample crumbs.
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	private function editor_trail( string $home_label, Render_Context $ctx ): array {
		$trail = array(
			array(
				'label' => $home_label,
				'url'   => '#',
			),
		);
		$post  = Theme_Context::post( $ctx );
		if ( $post && $post->ID !== $ctx->doc_id ) {
			$trail   = array_merge( $trail, $this->singular_parents( $post ) );
			$trail[] = array(
				'label' => wp_strip_all_tags( get_the_title( $post ) ),
				'url'   => '',
			);
			return $trail;
		}
		$demo    = Theme_Context::sample();
		$trail[] = array(
			'label' => __( 'Journal', 'uncoder' ),
			'url'   => '#',
		);
		$trail[] = array(
			'label' => $demo['category'],
			'url'   => '#',
		);
		$trail[] = array(
			'label' => $demo['title'],
			'url'   => '',
		);
		return $trail;
	}

	/**
	 * Queues BreadcrumbList JSON-LD in the footer (printed once per page).
	 *
	 * @param array<int, array{label:string,url:string}> $trail Full trail.
	 */
	private function queue_schema( array $trail ): void {
		/**
		 * Whether the breadcrumbs widget prints BreadcrumbList structured data.
		 *
		 * @param bool  $print Default: true unless Yoast SEO (which prints its own) is active.
		 * @param array $trail Crumbs: [ label, url ].
		 */
		if ( self::$schema_queued || count( $trail ) < 2 || ! apply_filters( 'uncoder_wb/breadcrumbs/schema', ! defined( 'WPSEO_VERSION' ), $trail ) ) {
			return;
		}
		self::$schema_queued = true;
		$items               = array();
		foreach ( array_values( $trail ) as $i => $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => wp_strip_all_tags( $crumb['label'] ),
			);
			if ( '' !== $crumb['url'] ) {
				$item['item'] = esc_url_raw( $crumb['url'] );
			}
			$items[] = $item;
		}
		$json  = (string) wp_json_encode(
			array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		$print = static function () use ( $json ): void {
			wp_print_inline_script_tag( $json, array( 'type' => 'application/ld+json' ) );
		};
		if ( did_action( 'wp_footer' ) ) {
			$print();
		} else {
			add_action( 'wp_footer', $print, 20 );
		}
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$home_label = trim( (string) ( $s['home_label'] ?? '' ) );
		$home_label = '' !== $home_label ? $home_label : __( 'Home', 'uncoder' );
		$full       = $ctx->editor ? $this->editor_trail( $home_label, $ctx ) : $this->trail( $home_label );

		if ( ! $ctx->editor && ! empty( $s['schema'] ) ) {
			$this->queue_schema( $full );
		}

		$trail = $full;
		if ( empty( $s['show_home'] ) ) {
			array_shift( $trail );
		}
		if ( empty( $s['show_current'] ) && count( $trail ) > 0 && '' === end( $trail )['url'] ) {
			array_pop( $trail );
		}
		if ( ! $trail ) {
			return;
		}

		if ( 'icon' === ( $s['separator_type'] ?? 'text' ) ) {
			$sep = $this->render_icon( $this->has_icon( $s['separator_icon'] ?? null ) ? $s['separator_icon'] : 'chevron-right' );
		} else {
			$sep = esc_html( trim( (string) ( $s['separator'] ?? '/' ) ) );
		}
		$home_icon = ! empty( $s['show_home'] ) && $this->has_icon( $s['home_icon'] ?? null ) ? $this->render_icon( $s['home_icon'], array( 'class' => 'uncoder-breadcrumbs__home-icon' ) ) : '';
		$last      = count( $trail ) - 1;

		echo '<nav class="uncoder-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'uncoder' ) . '"><ol class="uncoder-breadcrumbs__list">';
		foreach ( $trail as $i => $crumb ) {
			$is_home = 0 === $i && ! empty( $s['show_home'] );
			$label   = esc_html( $crumb['label'] );
			if ( $is_home && '' !== $home_icon ) {
				$label = $home_icon . ( ! empty( $s['home_icon_only'] ) ? '<span class="uncoder-sr-only">' . $label . '</span>' : '<span class="uncoder-breadcrumbs__text">' . $label . '</span>' );
			}
			echo '<li class="uncoder-breadcrumbs__item">';
			if ( $i > 0 && '' !== $sep ) {
				echo '<span class="uncoder-breadcrumbs__sep" aria-hidden="true">' . $sep . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped text or icon markup.
			}
			if ( '' !== $crumb['url'] ) {
				echo '<a class="uncoder-breadcrumbs__link" href="' . esc_url( $crumb['url'] ) . '">' . $label . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped label.
			} else {
				echo '<span class="uncoder-breadcrumbs__current"' . ( $i === $last ? ' aria-current="page"' : '' ) . '>' . $label . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped label.
			}
			echo '</li>';
		}
		echo '</ol></nav>';
	}
}
