<?php
/**
 * Template widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Site\Components;
use Uncoder\Builder\Widgets\Support\Template_Embed;

defined( 'ABSPATH' ) || exit;

/**
 * Embeds a saved "section" template: edit the template once, every page using it updates.
 */
class Template extends Widget_Base {

	public const TEMPLATE_TYPE = 'section';

	public function name(): string {
		return 'template';
	}

	public function title(): string {
		return __( 'Template', 'uncoder' );
	}

	public function icon(): string {
		return 'layout-template';
	}

	public function category(): string {
		return 'layout';
	}

	public function keywords(): array {
		return array( 'template', 'section', 'saved', 'reusable', 'global', 'block', 'embed' );
	}

	public function description(): string {
		return __( 'Shows a saved Section template. Edit the template once and every page that embeds it updates. Sections with component properties let each placement change their texts, images and links.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Template', 'uncoder' ) ) );
		$this->add_control(
			'template_id',
			array(
				'type'            => 'select',
				'label'           => __( 'Section template', 'uncoder' ),
				'default'         => '',
				'options_dynamic' => true,
				'options'         => array(),
				'source'          => 'templates-' . self::TEMPLATE_TYPE,
				'ai'              => 'Id of a published template of type "section" (create_template {"type":"section"}), e.g. "42".',
			)
		);
		$this->add_control(
			'overrides',
			array(
				'type'      => 'overrides',
				'label'     => __( 'Content of this copy', 'uncoder' ),
				'condition' => array( 'template_id!' => '' ),
				'ai'        => 'Component properties of the section (get_template → page_settings.component_props), e.g. {"title":"Faster installs","image":{"url":"…"}}. Unset keys keep the section\'s own content.',
			)
		);
		$this->add_control(
			'template_notice',
			array(
				'type'  => 'notice',
				'label' => __( 'Create sections in Uncoder → Theme Builder → Saved sections, or right-click a section in the builder → Save as template. Changes to the template show everywhere it is used.', 'uncoder' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Editor-only placeholder.
	 */
	private function placeholder( string $text ): string {
		return '<div class="uncoder-template__placeholder">' . $this->render_icon( 'layout-template', array( 'class' => 'uncoder-template__placeholder-icon' ) ) . '<span>' . esc_html( $text ) . '</span></div>';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$id = absint( $s['template_id'] ?? 0 );
		if ( ! $id ) {
			if ( $ctx->editor ) {
				echo $this->placeholder( __( 'Choose a section template in the settings.', 'uncoder' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in placeholder().
			}
			return;
		}

		$found = Template_Embed::document( $id, self::TEMPLATE_TYPE, $ctx );
		if ( null === $found['doc'] || Template_Embed::would_recurse( $id, $ctx ) ) {
			if ( $ctx->editor ) {
				$messages = array(
					'missing' => __( 'The selected template no longer exists. Choose another one.', 'uncoder' ),
					/* translators: %d: template id. */
					'type'    => sprintf( __( 'Template #%d is not a Section template.', 'uncoder' ), $id ),
					'status'  => __( 'The selected template is not published.', 'uncoder' ),
				);
				echo $this->placeholder( $messages[ $found['error'] ] ?? __( 'A template cannot be placed inside itself.', 'uncoder' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in placeholder().
			}
			return;
		}
		$doc = $found['doc'];

		if ( ! $ctx->editor ) {
			Assets::enqueue_late( $doc );
		}
		// Dynamic tags inside the section refer to the post the widget is shown for. A component renders
		// its own tree with this placement's overrides.
		$overrides = is_array( $s['overrides'] ?? null ) ? $s['overrides'] : array();
		$props     = $overrides ? Components::props( $id ) : array();
		$html      = (string) Template_Embed::guard(
			array( $id ),
			static fn() => $props
				? $doc->render_tree( Components::apply( $doc->elements(), $props, $overrides ), array( 'post_id' => $ctx->post_id ) )
				: $doc->render( array( 'post_id' => $ctx->post_id ) )
		);

		if ( ! $ctx->editor ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by the escaping renderer.
			return;
		}

		$edit  = Template_Embed::edit_url( $id );
		$title = get_the_title( $id );
		$bar   = '<div class="uncoder-template__bar"><span class="uncoder-template__name">' . esc_html( '' !== $title ? $title : __( 'Untitled template', 'uncoder' ) ) . ( 'publish' !== get_post_status( $id ) ? ' · ' . esc_html__( 'not published', 'uncoder' ) : '' ) . '</span>';
		if ( '' !== $edit ) {
			$bar .= '<a class="uncoder-template__edit" href="' . esc_url( $edit ) . '" target="_blank" rel="noopener">' . esc_html__( 'Edit template', 'uncoder' ) . '</a>';
		}
		$bar .= '</div>';
		if ( ! $doc->elements() ) {
			$html = $this->placeholder( __( 'This template is empty. Edit it to add content.', 'uncoder' ) );
		}
		// The widget's root is the embedded document's root (merged with this element), so the stylesheet
		// and the bar go inside it; a <link> first would become the root in the canvas.
		$inner = Template_Embed::editor_css( $doc ) . $bar;
		$html  = Template_Embed::for_canvas( $html );
		if ( preg_match( '/^\s*<(?!(?:img|input|br|hr|link|meta|style|script)\b)[a-z][a-z0-9-]*\b[^>]*>/i', $html, $m ) ) {
			$html = $m[0] . $inner . substr( $html, strlen( $m[0] ) );
		} else {
			$html = '<div>' . $inner . $html . '</div>';
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped stylesheet tag, bar and renderer output.
	}
}
