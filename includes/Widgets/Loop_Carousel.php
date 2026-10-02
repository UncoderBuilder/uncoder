<?php
/**
 * Loop Carousel widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Carousel_Engine;
use Uncoder\Builder\Widgets\Support\Template_Embed;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * The Loop Grid's cards (a "loop-item" template rendered for every post of a query) in the swipeable
 * carousel engine instead of a grid. Same query and template rules as the Loop Grid; no pagination.
 */
class Loop_Carousel extends Loop_Grid {

	use Carousel_Engine;

	public function name(): string {
		return 'loop-carousel';
	}

	public function title(): string {
		return __( 'Loop Carousel', 'uncoder' );
	}

	public function icon(): string {
		return 'gallery-horizontal-end';
	}

	public function keywords(): array {
		return array( 'loop', 'carousel', 'slider', 'posts', 'query', 'cards', 'products', 'template', 'swipe' );
	}

	public function description(): string {
		return __( 'Repeats a Loop item template (a card designed with dynamic tags) for every post of a query, in a swipeable carousel with arrows, dots and autoplay.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'carousel' );
	}

	public function frontend_styles(): array {
		return array( 'carousel', 'loop-grid' );
	}

	public function preset(): array {
		return array(
			'slides_per_view'        => 3,
			'slides_per_view_tablet' => 2,
			'slides_per_view_mobile' => 1.15,
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content_layout', array( 'label' => __( 'Loop', 'uncoder' ) ) );
		$this->add_control(
			'template_notice',
			array(
				'type'  => 'notice',
				'label' => __( 'Each slide is a Loop item template: create one in Uncoder → Theme Builder → Loop items, design it with dynamic tags, publish it, then choose it here.', 'uncoder' ),
			)
		);
		$this->add_control(
			'loop_template',
			array(
				'type'            => 'select',
				'label'           => __( 'Loop item template', 'uncoder' ),
				'default'         => '',
				'options_dynamic' => true,
				'options'         => array(),
				'source'          => 'templates-' . self::TEMPLATE_TYPE,
				'ai'              => 'Id of a published template of type "loop-item", e.g. "42". Its dynamic tags resolve to each post.',
			)
		);
		$this->add_group(
			'query',
			array(
				'type'    => 'query',
				'label'   => __( 'Query', 'uncoder' ),
				'default' => array(
					'source'         => 'posts',
					'post_type'      => 'post',
					'posts_per_page' => 8,
					'orderby'        => 'date',
					'order'          => 'desc',
				),
				'ai'      => 'A custom query, e.g. {"source":"posts","post_type":"post","posts_per_page":8}; related posts: {"source":"related"} when supported by the query group.',
			)
		);
		$this->add_control(
			'nothing_found',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Nothing found message', 'uncoder' ),
				'default' => '',
				'rows'    => 2,
			)
		);
		$this->add_control(
			'image_var',
			array(
				'type'        => 'switch',
				'label'       => __( 'Featured image as CSS variable', 'uncoder' ),
				'description' => __( 'Adds --uncoder-loop-image to every card for custom CSS backgrounds.', 'uncoder' ),
				'default'     => false,
			)
		);
		$this->end_section();

		$this->register_carousel_settings();
		$this->register_carousel_style();
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		return array( 'data-settings' => $this->json_attr( $this->carousel_data( $s ) ) );
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$tid   = absint( $s['loop_template'] ?? 0 );
		$found = $tid ? Template_Embed::document( $tid, self::TEMPLATE_TYPE, $ctx ) : array(
			'doc'   => null,
			'error' => 'none',
		);
		if ( null === $found['doc'] || Template_Embed::would_recurse( $tid, $ctx ) ) {
			if ( $ctx->editor ) {
				$messages = array(
					'none'    => __( 'Create a Loop item template in Theme Builder, design the card with dynamic tags, publish it, then pick it in Loop → Loop item template.', 'uncoder' ),
					'missing' => __( 'The selected loop item template no longer exists. Choose another one.', 'uncoder' ),
					/* translators: %d: template id. */
					'type'    => sprintf( __( 'Template #%d is not a Loop item template. Choose a template of type “Loop item”.', 'uncoder' ), $tid ),
					'status'  => __( 'The selected loop item template is not published.', 'uncoder' ),
				);
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in placeholder().
				echo $this->placeholder(
					__( 'Loop Carousel', 'uncoder' ),
					$messages[ $found['error'] ] ?? __( 'This loop item template is already shown here: a loop cannot display the template it is placed in.', 'uncoder' ),
					'none' === $found['error'] ? Template_Embed::theme_builder_url() : '',
					__( 'Open Theme Builder', 'uncoder' )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return;
		}
		$template = $found['doc'];
		if ( ! $ctx->editor ) {
			Assets::enqueue_late( $template );
		}

		$result = $this->query( $s, $ctx );
		$posts  = array_values( array_filter( array_map( 'get_post', (array) $result['query']->posts ), static fn( $p ) => $p instanceof \WP_Post ) );
		$css    = $ctx->editor ? Template_Embed::editor_css( $template ) : '';

		if ( ! $posts ) {
			$message = trim( (string) ( $s['nothing_found'] ?? '' ) );
			if ( '' === $message && $ctx->editor ) {
				$message = __( 'No posts match this query.', 'uncoder' );
			}
			if ( '' !== $message ) {
				echo '<div class="uncoder-loop-grid">' . $css . '<p class="uncoder-loop-grid__empty">' . esc_html( $message ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped stylesheet tag.
			}
			return;
		}

		$total  = count( $posts );
		$slides = Template_Embed::guard(
			array( $tid ),
			function () use ( $posts, $total, $template, $s, $ctx ) {
				$html = '';
				foreach ( $posts as $i => $post ) {
					self::$shown[] = (int) $post->ID;
					$html         .= $this->carousel_slide_start( $i, $total ) . Theme_Context::with_post( $post, fn() => $this->item( $template, $post, false, $s, $ctx ) ) . '</div>';
				}
				return $html;
			}
		);

		echo $this->carousel_start( $s, $ctx, array( 'uncoder-loop-carousel' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with Utils::attrs().
		// Inside the root, so the widget keeps a single root element (the stylesheet tag takes no room in the track).
		echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped stylesheet tag.
		echo $slides; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cards come from the escaping renderer.
		echo $this->carousel_end( $s, $ctx, $total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	}
}
