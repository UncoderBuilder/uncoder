<?php
/**
 * Star rating widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Stars;

defined( 'ABSPATH' ) || exit;

/**
 * A rating shown as marked / unmarked stars (or any icon), with an optional title and value.
 */
class Star_Rating extends Widget_Base {

	public function name(): string {
		return 'star-rating';
	}

	public function title(): string {
		return __( 'Star Rating', 'uncoder' );
	}

	public function icon(): string {
		return 'star-half';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'star', 'rating', 'review', 'score', 'stars' );
	}

	public function description(): string {
		return __( 'A score shown as stars (supports half values), with an optional label and number. Out of 5 or 10.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Rating', 'uncoder' ) ) );
		$this->add_control(
			'rating_scale',
			array(
				'type'    => 'choose',
				'label'   => __( 'Scale', 'uncoder' ),
				'default' => '5',
				'options' => array(
					'5'  => array( 'label' => '0–5' ),
					'10' => array( 'label' => '0–10' ),
				),
			)
		);
		$this->add_control(
			'rating',
			array(
				'type'    => 'number',
				'label'   => __( 'Rating', 'uncoder' ),
				'default' => 5,
				'min'     => 0,
				'max'     => 10,
				'step'    => 0.1,
				'ai'      => 'Number between 0 and the scale (5 or 10). Decimals fill the last star partially.',
			)
		);
		$this->add_control(
			'icon',
			array(
				'type'    => 'icon',
				'label'   => __( 'Icon', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'star' ),
			)
		);
		$this->add_control(
			'unmarked_style',
			array(
				'type'    => 'choose',
				'label'   => __( 'Unmarked style', 'uncoder' ),
				'default' => 'solid',
				'options' => array(
					'solid'   => array( 'label' => __( 'Solid', 'uncoder' ) ),
					'outline' => array( 'label' => __( 'Outline', 'uncoder' ) ),
				),
			)
		);
		$this->add_control(
			'title',
			array(
				'type'        => 'text',
				'label'       => __( 'Title', 'uncoder' ),
				'placeholder' => __( 'Customer rating', 'uncoder' ),
				'dynamic'     => true,
				'inline'      => true,
			)
		);
		$this->add_control(
			'show_value',
			array(
				'type'  => 'switch',
				'label' => __( 'Show number', 'uncoder' ),
			)
		);
		$this->add_control(
			'value_format',
			array(
				'type'      => 'select',
				'label'     => __( 'Number format', 'uncoder' ),
				'default'   => 'fraction',
				'options'   => array(
					'rating'   => __( '4.8', 'uncoder' ),
					'fraction' => __( '4.8/5', 'uncoder' ),
				),
				'condition' => array( 'show_value' => 'yes' ),
			)
		);
		$this->add_responsive_control(
			'layout',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Layout', 'uncoder' ),
				'default'              => 'inline',
				'options'              => array(
					'inline'  => array( 'label' => __( 'Inline', 'uncoder' ), 'icon' => 'columns-2' ),
					'stacked' => array( 'label' => __( 'Stacked', 'uncoder' ), 'icon' => 'rows-2' ),
				),
				'selectors_dictionary' => array(
					'inline'  => '--uncoder-srating-title-basis:auto',
					'stacked' => '--uncoder-srating-title-basis:100%',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
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
					'left'   => 'justify-content:flex-start;text-align:left',
					'center' => 'justify-content:center;text-align:center',
					'right'  => 'justify-content:flex-end;text-align:right',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_stars', array( 'label' => __( 'Stars', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-star-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-star-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'marked_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Marked color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-star-marked: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'unmarked_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Unmarked color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-star-unmarked: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'icon_stroke',
			array(
				'type'      => 'number',
				'label'     => __( 'Corner roundness', 'uncoder' ),
				'description' => __( 'Stroke width of the icon; higher values give softer points.', 'uncoder' ),
				'min'       => 0,
				'max'       => 4,
				'step'      => 0.25,
				'selectors' => array( '{{WRAPPER}} .uncoder-star-rating__icon' => 'stroke-width: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Title & number', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-star-rating__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Title color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-star-rating__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'value_typography',
			array(
				'type'      => 'typography',
				'label'     => __( 'Number typography', 'uncoder' ),
				'selector'  => '{{WRAPPER}} .uncoder-star-rating__value',
				'condition' => array( 'show_value' => 'yes' ),
			)
		);
		$this->add_control(
			'value_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Number color', 'uncoder' ),
				'condition' => array( 'show_value' => 'yes' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-star-rating__value' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$scale  = '10' === (string) ( $s['rating_scale'] ?? '5' ) ? 10 : 5;
		$rating = Stars::normalize( $s['rating'] ?? 0, $scale );
		$title  = (string) ( $s['title'] ?? '' );

		echo '<div class="uncoder-star-rating">';
		if ( '' !== $title ) {
			echo '<span class="uncoder-star-rating__title"' . $ctx->inline( 'title' ) . '>' . esc_html( $title ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
		}
		echo Stars::render( $rating, $scale, $s['icon'] ?? 'star', (string) ( $s['unmarked_style'] ?? 'solid' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
		if ( ! empty( $s['show_value'] ) ) {
			$value = Stars::format( $rating ) . ( 'rating' === ( $s['value_format'] ?? 'fraction' ) ? '' : '/' . $scale );
			echo '<span class="uncoder-star-rating__value" aria-hidden="true">' . esc_html( $value ) . '</span>';
		}
		echo '</div>';
	}
}
