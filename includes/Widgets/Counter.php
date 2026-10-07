<?php
/**
 * Counter widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A number that counts up when it scrolls into view. The final value is always in the HTML.
 */
class Counter extends Widget_Base {

	public const SEPARATORS = array(
		','      => '1,000',
		'.'      => '1.000',
		'space'  => '1 000',
		'apos'   => "1'000",
	);

	public function name(): string {
		return 'counter';
	}

	public function title(): string {
		return __( 'Counter', 'uncoder' );
	}

	public function icon(): string {
		return 'hash';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'counter', 'number', 'stats', 'statistic', 'count up', 'figure' );
	}

	public function description(): string {
		return __( 'A statistic that counts up when visible, with prefix, suffix and a label. The final number stays in the HTML for SEO.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'counter' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Counter', 'uncoder' ) ) );
		$this->add_control(
			'start',
			array(
				'type'    => 'number',
				'label'   => __( 'Starting number', 'uncoder' ),
				'default' => 0,
			)
		);
		$this->add_control(
			'end',
			array(
				'type'    => 'number',
				'label'   => __( 'Ending number', 'uncoder' ),
				'default' => 250,
				'ai'      => 'The number displayed (and indexed). Use prefix/suffix for "$", "+", "%", "k".',
			)
		);
		$this->add_control(
			'prefix',
			array(
				'type'        => 'text',
				'label'       => __( 'Prefix', 'uncoder' ),
				'placeholder' => '$',
			)
		);
		$this->add_control(
			'suffix',
			array(
				'type'        => 'text',
				'label'       => __( 'Suffix', 'uncoder' ),
				'default'     => '+',
				'placeholder' => '%',
			)
		);
		$this->add_control(
			'decimals',
			array(
				'type'    => 'number',
				'label'   => __( 'Decimal places', 'uncoder' ),
				'default' => 0,
				'min'     => 0,
				'max'     => 3,
				'step'    => 1,
			)
		);
		$this->add_control(
			'thousand_separator',
			array(
				'type'    => 'switch',
				'label'   => __( 'Thousand separator', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'separator',
			array(
				'type'      => 'select',
				'label'     => __( 'Separator', 'uncoder' ),
				'default'   => ',',
				'options'   => self::SEPARATORS,
				'condition' => array( 'thousand_separator' => 'yes' ),
			)
		);
		$this->add_control(
			'duration',
			array(
				'type'    => 'number',
				'label'   => __( 'Animation duration (ms)', 'uncoder' ),
				'default' => 2000,
				'min'     => 0,
				'max'     => 10000,
				'step'    => 100,
			)
		);
		$this->add_control(
			'title',
			array(
				'type'    => 'text',
				'label'   => __( 'Title', 'uncoder' ),
				'default' => __( 'Projects delivered', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'div',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->add_control(
			'title_position',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Title position', 'uncoder' ),
				'default'              => 'below',
				'options'              => array(
					'above' => array( 'label' => __( 'Above', 'uncoder' ), 'icon' => 'arrow-up-to-line' ),
					'below' => array( 'label' => __( 'Below', 'uncoder' ), 'icon' => 'arrow-down-to-line' ),
				),
				'selectors_dictionary' => array(
					'above' => '--uncoder-counter-dir:column-reverse',
					'below' => '--uncoder-counter-dir:column',
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
					'left'   => 'align-items:flex-start;text-align:left',
					'center' => 'align-items:center;text-align:center',
					'right'  => 'align-items:flex-end;text-align:right',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_number', array( 'label' => __( 'Number', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'number_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-counter__number-wrap' ) );
		$this->add_control(
			'number_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-counter__number-wrap' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'number_digits',
			array(
				'type'        => 'select',
				'label'       => __( 'Digits', 'uncoder' ),
				'description' => __( 'Even-width digits keep the number steady while it counts; proportional ones keep the font’s own spacing.', 'uncoder' ),
				'options'     => array(
					''                  => __( 'Even width', 'uncoder' ),
					'proportional-nums' => __( 'Proportional', 'uncoder' ),
				),
				'selectors'   => array( '{{WRAPPER}} .uncoder-counter__number' => 'font-variant-numeric: {{VALUE}}' ),
			)
		);
		$this->add_group( 'number_shadow', array( 'type' => 'text_shadow', 'label' => __( 'Text shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-counter__number-wrap' ) );
		$this->add_control( 'affix_heading', array( 'type' => 'heading', 'label' => __( 'Prefix & suffix', 'uncoder' ) ) );
		$this->add_responsive_control(
			'affix_size',
			array(
				'type'        => 'slider',
				'label'       => __( 'Size', 'uncoder' ),
				'description' => __( 'Relative to the number, e.g. 0.6em.', 'uncoder' ),
				'size_units'  => array( 'em', 'px', '%' ),
				'range'       => array( 'em' => array( 'min' => 0.2, 'max' => 2, 'step' => 0.05 ) ),
				'selectors'   => array( '{{WRAPPER}} .uncoder-counter__affix' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'affix_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-counter__affix' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'affix_weight',
			array(
				'type'      => 'select',
				'label'     => __( 'Weight', 'uncoder' ),
				'options'   => \Uncoder\Builder\Controls\Groups\Typography::WEIGHTS,
				'selectors' => array( '{{WRAPPER}} .uncoder-counter__affix' => 'font-weight: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'affix_align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Vertical position', 'uncoder' ),
				'options'   => array(
					'baseline'   => array( 'label' => __( 'Baseline', 'uncoder' ), 'icon' => 'align-end-vertical' ),
					'center'     => array( 'label' => __( 'Middle', 'uncoder' ), 'icon' => 'align-center-vertical' ),
					'flex-start' => array( 'label' => __( 'Top', 'uncoder' ), 'icon' => 'align-start-vertical' ),
				),
				'selectors' => array( '{{WRAPPER}} .uncoder-counter__number-wrap' => 'align-items: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'affix_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-counter__number-wrap' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_title', array( 'label' => __( 'Title', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-counter__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-counter__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'title_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Formats a number exactly like the front-end module does.
	 */
	public static function format_number( float $value, int $decimals, string $thousands, string $point ): string {
		return number_format( $value, $decimals, $point, $thousands );
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 * @return array{0:string,1:string} thousands separator, decimal point.
	 */
	private function separators( array $s ): array {
		if ( empty( $s['thousand_separator'] ) ) {
			return array( '', '.' );
		}
		switch ( (string) ( $s['separator'] ?? ',' ) ) {
			case '.':
				return array( '.', ',' );
			case 'space':
				return array( "\u{202F}", ',' );
			case 'apos':
				return array( "'", '.' );
			default:
				return array( ',', '.' );
		}
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$end      = is_numeric( $s['end'] ?? null ) ? (float) $s['end'] : 0.0;
		$start    = is_numeric( $s['start'] ?? null ) ? (float) $s['start'] : 0.0;
		$decimals = max( 0, min( 3, (int) ( $s['decimals'] ?? 0 ) ) );
		list( $thousands, $point ) = $this->separators( $s );
		$final = self::format_number( $end, $decimals, $thousands, $point );

		$number = array(
			'class'           => 'uncoder-counter__number',
			'data-from'       => (string) $start,
			'data-to'         => (string) $end,
			'data-decimals'   => (string) $decimals,
			'data-thousands'  => $thousands,
			'data-point'      => $point,
			'data-duration'   => (string) max( 0, (int) ( $s['duration'] ?? 2000 ) ),
		);

		$prefix = (string) ( $s['prefix'] ?? '' );
		$suffix = (string) ( $s['suffix'] ?? '' );
		$title  = (string) ( $s['title'] ?? '' );
		$tag    = Utils::tag( $s['title_tag'] ?? 'div', Utils::HEADING_TAGS, 'div' );

		echo '<div class="uncoder-counter">';
		echo '<div class="uncoder-counter__number-wrap">';
		if ( '' !== $prefix ) {
			echo '<span class="uncoder-counter__affix uncoder-counter__prefix">' . esc_html( $prefix ) . '</span>';
		}
		echo '<span' . Utils::attrs( $number ) . '>' . esc_html( $final ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		if ( '' !== $suffix ) {
			echo '<span class="uncoder-counter__affix uncoder-counter__suffix">' . esc_html( $suffix ) . '</span>';
		}
		echo '</div>';
		if ( '' !== $title || $ctx->editor ) {
			echo '<' . $tag . ' class="uncoder-counter__title"' . $ctx->inline( 'title' ) . '>' . esc_html( $title ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag, inline() escaped.
		}
		echo '</div>';
	}
}
