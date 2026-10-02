<?php
/**
 * Text editor widget (rich text).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Paragraphs, lists, links and inline formatting.
 */
class Text_Editor extends Widget_Base {

	public function name(): string {
		return 'text-editor';
	}

	public function title(): string {
		return __( 'Text', 'uncoder' );
	}

	public function icon(): string {
		return 'type';
	}

	public function keywords(): array {
		return array( 'text', 'paragraph', 'editor', 'rich text', 'content', 'list' );
	}

	public function description(): string {
		return __( 'Rich text: paragraphs, lists, links, bold/italic. Body copy of any length.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Text', 'uncoder' ) ) );
		$this->add_control(
			'content',
			array(
				'type'    => 'wysiwyg',
				'label'   => __( 'Content', 'uncoder' ),
				'default' => '<p>' . __( 'Add your text here. Click to edit it and tell visitors what makes you different.', 'uncoder' ) . '</p>',
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'type'      => 'number',
				'label'     => __( 'Columns', 'uncoder' ),
				'min'       => 1,
				'max'       => 6,
				'selectors' => array( '{{WRAPPER}}' => 'columns: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'column_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Columns gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'condition'  => array( 'columns!' => '' ),
				'selectors'  => array( '{{WRAPPER}}' => 'column-gap: {{VALUE}}' ),
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

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
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
			'paragraph_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Paragraph spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} > :not(:last-child)' => 'margin-bottom: {{VALUE}}' ),
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
		$this->add_control(
			'drop_cap',
			array(
				'type'  => 'switch',
				'label' => __( 'Drop cap', 'uncoder' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$content = wp_kses( (string) ( $s['content'] ?? '' ), Utils::kses_rich() );
		$class   = 'uncoder-text' . ( ! empty( $s['drop_cap'] ) ? ' uncoder-text--dropcap' : '' );
		$center  = in_array( $s['align'] ?? '', array( 'center' ), true ) && ! empty( $s['max_width']['size'] ) ? ' uncoder-text--centered' : '';
		echo '<div class="' . esc_attr( $class . $center ) . '"' . $ctx->inline( 'content' ) . '>' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd.
	}
}
