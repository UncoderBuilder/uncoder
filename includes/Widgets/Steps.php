<?php
/**
 * Steps widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Numbered process steps (or icons) joined by a connector line, horizontal or vertical.
 */
class Steps extends Widget_Base {

	public function name(): string {
		return 'steps';
	}

	public function title(): string {
		return __( 'Steps', 'uncoder' );
	}

	public function icon(): string {
		return 'list-ordered';
	}

	public function category(): string {
		return 'content';
	}

	public function keywords(): array {
		return array( 'steps', 'process', 'how it works', 'workflow', 'numbered', 'stages' );
	}

	public function description(): string {
		return __( '"How it works" process: numbered (or icon) markers with a title and text, joined by a connector line. Horizontal (stacks on phones) or vertical.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content_items', array( 'label' => __( 'Steps', 'uncoder' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Steps', 'uncoder' ),
				'title_field' => 'title',
				'fields'      => array(
					'title'       => array(
						'type'    => 'text',
						'label'   => __( 'Title', 'uncoder' ),
						'default' => __( 'Step', 'uncoder' ),
					),
					'description' => array(
						'type'  => 'textarea',
						'label' => __( 'Description', 'uncoder' ),
						'html'  => 'inline',
						'rows'  => 3,
					),
					'icon'        => array(
						'type'        => 'icon',
						'label'       => __( 'Icon', 'uncoder' ),
						'description' => __( 'Used when the marker is set to "Icon".', 'uncoder' ),
					),
				),
				'default'     => array(
					array(
						'title'       => __( 'Share your brief', 'uncoder' ),
						'description' => __( 'Tell us about your goals, audience and timeline in a short form.', 'uncoder' ),
						'icon'        => array( 'library' => 'lucide', 'value' => 'message-square' ),
					),
					array(
						'title'       => __( 'Get a tailored plan', 'uncoder' ),
						'description' => __( 'We map pages, content and milestones within two business days.', 'uncoder' ),
						'icon'        => array( 'library' => 'lucide', 'value' => 'clipboard-check' ),
					),
					array(
						'title'       => __( 'Review the design', 'uncoder' ),
						'description' => __( 'Comment right on the draft until every detail feels right.', 'uncoder' ),
						'icon'        => array( 'library' => 'lucide', 'value' => 'pen-tool' ),
					),
					array(
						'title'       => __( 'Launch', 'uncoder' ),
						'description' => __( 'We run the go-live checklist, redirects and analytics setup.', 'uncoder' ),
						'icon'        => array( 'library' => 'lucide', 'value' => 'rocket' ),
					),
				),
				'ai'          => 'Rows: {"title":"…","description":"…","icon":"lucide-name"}. 3–5 steps read best horizontally.',
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'h3',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_control(
			'layout',
			array(
				'type'    => 'choose',
				'label'   => __( 'Direction', 'uncoder' ),
				'default' => 'horizontal',
				'options' => array(
					'horizontal' => array( 'label' => __( 'Horizontal', 'uncoder' ), 'icon' => 'columns-3' ),
					'vertical'   => array( 'label' => __( 'Vertical', 'uncoder' ), 'icon' => 'rows-3' ),
				),
			)
		);
		$this->add_control(
			'stack_on',
			array(
				'type'      => 'select',
				'label'     => __( 'Stack vertically on', 'uncoder' ),
				'default'   => 'mobile',
				'options'   => array(
					'mobile' => __( 'Phones (below 768px)', 'uncoder' ),
					'tablet' => __( 'Tablets and phones (below 1025px)', 'uncoder' ),
					'never'  => __( 'Never', 'uncoder' ),
				),
				'condition' => array( 'layout' => 'horizontal' ),
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
				),
				'selectors_dictionary' => array(
					'left'   => '--uncoder-steps-align:flex-start;--uncoder-steps-text:start;--uncoder-steps-line-start:calc(var(--uncoder-steps-marker) + var(--uncoder-steps-line-gap));--uncoder-steps-line-end:calc(var(--uncoder-steps-line-gap) - var(--uncoder-steps-gap))',
					'center' => '--uncoder-steps-align:center;--uncoder-steps-text:center;--uncoder-steps-line-start:calc(50% + var(--uncoder-steps-marker) / 2 + var(--uncoder-steps-line-gap));--uncoder-steps-line-end:calc(var(--uncoder-steps-marker) / 2 + var(--uncoder-steps-line-gap) - 50% - var(--uncoder-steps-gap))',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'condition'            => array( 'layout' => 'horizontal' ),
			)
		);
		$this->add_control(
			'marker',
			array(
				'type'    => 'select',
				'label'   => __( 'Marker', 'uncoder' ),
				'default' => 'number',
				'options' => array(
					'number' => __( 'Number', 'uncoder' ),
					'icon'   => __( 'Icon', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'numbering',
			array(
				'type'      => 'select',
				'label'     => __( 'Numbering', 'uncoder' ),
				'default'   => 'decimal',
				'options'   => array(
					'decimal' => '1, 2, 3',
					'zero'    => '01, 02, 03',
					'alpha'   => 'A, B, C',
					'roman'   => 'I, II, III',
				),
				'condition' => array( 'marker' => 'number' ),
			)
		);
		$this->add_control(
			'connector',
			array(
				'type'    => 'switch',
				'label'   => __( 'Connector line', 'uncoder' ),
				'default' => true,
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_layout', array( 'label' => __( 'Spacing', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between steps', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-steps-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'content_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between marker and text', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-steps-content-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_marker', array( 'label' => __( 'Marker', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'marker_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 140 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-steps-marker: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'marker_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'marker_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-steps__marker' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'marker_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Number / icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-steps__marker' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'marker_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-steps__item:hover .uncoder-steps__marker' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'marker_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Number / icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-steps__item:hover .uncoder-steps__marker' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_group( 'marker_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-steps__marker' ) );
		$this->add_responsive_control(
			'marker_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-steps__marker' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'marker_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-steps__marker' ) );
		$this->add_group( 'number_typography', array( 'type' => 'typography', 'label' => __( 'Number typography', 'uncoder' ), 'condition' => array( 'marker' => 'number' ), 'selector' => '{{WRAPPER}} .uncoder-steps__marker' ) );
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'marker' => 'icon' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-steps__marker .uncoder-svg' => 'width: {{VALUE}}; height: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_connector', array( 'label' => __( 'Connector', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'connector' => true ) ) );
		$this->add_control(
			'line_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-steps-line: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'line_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Thickness', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-steps-line-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'line_style',
			array(
				'type'      => 'select',
				'label'     => __( 'Style', 'uncoder' ),
				'options'   => array(
					''       => __( 'Solid', 'uncoder' ),
					'dashed' => __( 'Dashed', 'uncoder' ),
					'dotted' => __( 'Dotted', 'uncoder' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-steps-line-style: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'line_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap to marker', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-steps-line-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-steps__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Title color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-steps__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Title spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-steps__title' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_group( 'description_typography', array( 'type' => 'typography', 'label' => __( 'Description typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-steps__description' ) );
		$this->add_control(
			'description_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Description color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-steps__description' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'text_max_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Text max width', 'uncoder' ),
				'size_units' => array( 'px', 'ch', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-steps__content' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Label of the nth step (1-based) for a numbering style.
	 */
	public static function label( int $n, string $style ): string {
		switch ( $style ) {
			case 'zero':
				return str_pad( (string) $n, 2, '0', STR_PAD_LEFT );
			case 'alpha':
				$out = '';
				while ( $n > 0 ) {
					--$n;
					$out = chr( 65 + ( $n % 26 ) ) . $out;
					$n   = intdiv( $n, 26 );
				}
				return $out;
			case 'roman':
				$map = array(
					'M'  => 1000,
					'CM' => 900,
					'D'  => 500,
					'CD' => 400,
					'C'  => 100,
					'XC' => 90,
					'L'  => 50,
					'XL' => 40,
					'X'  => 10,
					'IX' => 9,
					'V'  => 5,
					'IV' => 4,
					'I'  => 1,
				);
				$out = '';
				foreach ( $map as $roman => $value ) {
					while ( $n >= $value ) {
						$out .= $roman;
						$n   -= $value;
					}
				}
				return $out;
			default:
				return (string) $n;
		}
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows = is_array( $s['items'] ?? null ) ? array_values( array_filter( $s['items'], 'is_array' ) ) : array();
		if ( ! $rows ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-steps__empty">' . esc_html__( 'Add steps to show the process.', 'uncoder' ) . '</p>';
			}
			return;
		}
		$layout    = 'vertical' === ( $s['layout'] ?? 'horizontal' ) ? 'vertical' : 'horizontal';
		$marker    = 'icon' === ( $s['marker'] ?? 'number' ) ? 'icon' : 'number';
		$numbering = in_array( $s['numbering'] ?? 'decimal', array( 'decimal', 'zero', 'alpha', 'roman' ), true ) ? $s['numbering'] : 'decimal';
		$tag       = Utils::tag( $s['title_tag'] ?? 'h3', Utils::HEADING_TAGS, 'h3' );

		$classes = array( 'uncoder-steps', 'uncoder-steps--' . $layout, 'uncoder-steps--marker-' . $marker );
		if ( 'horizontal' === $layout ) {
			$stack     = in_array( $s['stack_on'] ?? 'mobile', array( 'mobile', 'tablet', 'never' ), true ) ? $s['stack_on'] : 'mobile';
			$classes[] = 'uncoder-steps--stack-' . $stack;
		}
		if ( ! empty( $s['connector'] ) ) {
			$classes[] = 'uncoder-steps--connector';
		}

		echo '<ol class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		foreach ( $rows as $i => $row ) {
			$item = array( 'uncoder-steps__item' );
			if ( ! empty( $row['_id'] ) && is_string( $row['_id'] ) ) {
				$item[] = 'uncoder-ri-' . sanitize_html_class( $row['_id'] );
			}
			$inner = 'icon' === $marker && $this->has_icon( $row['icon'] ?? null ) ? $this->render_icon( $row['icon'] ) : esc_html( self::label( $i + 1, $numbering ) );
			$title = trim( (string) ( $row['title'] ?? '' ) );
			$desc  = $this->inline_html( $row['description'] ?? '' );

			echo '<li class="' . esc_attr( implode( ' ', $item ) ) . '">';
			echo '<div class="uncoder-steps__marker" aria-hidden="true">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped icon / label.
			echo '<div class="uncoder-steps__content">';
			if ( '' !== $title ) {
				echo '<' . $tag . ' class="uncoder-steps__title">' . esc_html( $title ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag.
			}
			if ( '' !== trim( wp_strip_all_tags( $desc ) ) ) {
				echo '<p class="uncoder-steps__description">' . $desc . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
			}
			echo '</div></li>';
		}
		echo '</ol>';
	}
}
