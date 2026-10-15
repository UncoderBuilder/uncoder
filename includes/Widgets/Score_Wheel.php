<?php
/**
 * Score Wheel widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * A wheel of scored areas (a "wheel of life"): each area is a coloured wedge whose length is its score. As a
 * check-in, visitors score every area from 1 to the maximum and the wheel, the lowest area and the average follow
 * (in the browser only: nothing is saved or sent). As a chart, the wheel shows fixed scores, optionally with an earlier
 * set drawn as a dashed outline (before and after). The "score-wheel" module redraws the wheel when a score changes.
 */
class Score_Wheel extends Widget_Base {

	/** The hub: an empty centre of this share of the radius, so a score of 1 is still a visible wedge. */
	private const HUB = 0.14;

	public function name(): string {
		return 'score-wheel';
	}

	public function title(): string {
		return __( 'Score Wheel', 'uncoder' );
	}

	public function icon(): string {
		return 'chart-pie';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'wheel of life', 'score', 'check-in', 'self assessment', 'quiz', 'radial', 'polar', 'chart', 'coaching', 'balance' );
	}

	public function description(): string {
		return __( 'A wheel of scored areas (a wheel of life). As a check-in, visitors score each area and the wheel, the lowest area and the average update as they go. As a chart, it shows fixed scores, with an earlier set as a dashed outline.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'score-wheel' );
	}

	/** The template's eight areas. */
	public function preset(): array {
		$areas = array(
			array( __( 'Career', 'uncoder' ), __( 'work, purpose, ambition', 'uncoder' ), '#FFD98A', 8 ),
			array( __( 'Money', 'uncoder' ), __( 'security, spending, saving', 'uncoder' ), '#BFE6C8', 6 ),
			array( __( 'Health', 'uncoder' ), __( 'energy, sleep, movement', 'uncoder' ), '#B8DDF1', 5 ),
			array( __( 'Growth', 'uncoder' ), __( 'learning, creativity, confidence', 'uncoder' ), '#D2C6F4', 7 ),
			array( __( 'Relationships', 'uncoder' ), __( 'family and friends', 'uncoder' ), '#F7B9C7', 6 ),
			array( __( 'Love', 'uncoder' ), __( 'partner, romance or dating', 'uncoder' ), '#EFBDE4', 5 ),
			array( __( 'Fun & rest', 'uncoder' ), __( 'play, hobbies, real downtime', 'uncoder' ), '#FFCBA6', 3 ),
			array( __( 'Home', 'uncoder' ), __( 'where you live, how it feels', 'uncoder' ), '#D8E9B4', 6 ),
		);
		return array(
			'areas' => array_map(
				static fn( $a ) => array(
					'label' => $a[0],
					'hint'  => $a[1],
					'color' => $a[2],
					'score' => $a[3],
				),
				$areas
			),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Wheel', 'uncoder' ) ) );
		$this->add_control(
			'mode',
			array(
				'type'    => 'select',
				'label'   => __( 'Show', 'uncoder' ),
				'default' => 'checkin',
				'options' => array(
					'checkin' => __( 'A check-in visitors fill in', 'uncoder' ),
					'chart'   => __( 'The wheel only (a chart)', 'uncoder' ),
				),
				'ai'      => '"checkin": a score row per area beside the wheel; "chart": the wheel alone (decoration, a client\'s before/after).',
			)
		);
		$this->add_control(
			'areas',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Areas', 'uncoder' ),
				'title_field' => 'label',
				'description' => __( 'One wedge per area, clockwise from the top. Three to twelve areas read best.', 'uncoder' ),
				'fields'      => array(
					'label'  => array(
						'type'    => 'text',
						'label'   => __( 'Name', 'uncoder' ),
						'default' => __( 'Area', 'uncoder' ),
					),
					'hint'   => array(
						'type'    => 'text',
						'label'   => __( 'Hint', 'uncoder' ),
						'default' => '',
					),
					'color'  => array(
						'type'    => 'color',
						'label'   => __( 'Color', 'uncoder' ),
						'default' => '#D2C6F4',
					),
					'score'  => array(
						'type'    => 'number',
						'label'   => __( 'Score', 'uncoder' ),
						'min'     => 0,
						'max'     => 100,
						'default' => 5,
					),
					'before' => array(
						'type'        => 'number',
						'label'       => __( 'Earlier score', 'uncoder' ),
						'min'         => 0,
						'max'         => 100,
						'description' => __( 'Drawn as a dashed outline when Show earlier scores is on.', 'uncoder' ),
					),
				),
				'default'     => $this->preset()['areas'],
				'ai'          => 'Rows of {"label":"Career","hint":"work, purpose, ambition","color":"#FFD98A","score":8,"before":4}.',
			)
		);
		$this->add_control(
			'max',
			array(
				'type'    => 'number',
				'label'   => __( 'Highest score', 'uncoder' ),
				'min'     => 3,
				'max'     => 20,
				'default' => 10,
			)
		);
		$this->add_control(
			'show_labels',
			array(
				'type'    => 'switch',
				'label'   => __( 'Names around the wheel', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'show_lowest',
			array(
				'type'        => 'switch',
				'label'       => __( 'Outline the lowest area', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'show_before',
			array(
				'type'      => 'switch',
				'label'     => __( 'Show earlier scores', 'uncoder' ),
				'default'   => false,
				'condition' => array( 'mode' => 'chart' ),
			)
		);
		$this->add_control(
			'before_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Earlier label', 'uncoder' ),
				'default'   => __( 'Before', 'uncoder' ),
				'condition' => array(
					'mode'        => 'chart',
					'show_before' => true,
				),
			)
		);
		$this->add_control(
			'after_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Now label', 'uncoder' ),
				'default'   => __( 'Now', 'uncoder' ),
				'condition' => array(
					'mode'        => 'chart',
					'show_before' => true,
				),
			)
		);
		$this->add_control(
			'chart_label',
			array(
				'type'        => 'text',
				'label'       => __( 'Name for screen readers', 'uncoder' ),
				'default'     => __( 'Wheel of life', 'uncoder' ),
				'description' => __( 'Read out with each area and its score.', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_checkin', array( 'label' => __( 'Check-in', 'uncoder' ), 'condition' => array( 'mode' => 'checkin' ) ) );
		$this->add_control(
			'card_note',
			array(
				'type'    => 'text',
				'label'   => __( 'Note over the wheel', 'uncoder' ),
				'default' => __( 'Example wheel. Change any score to make it yours.', 'uncoder' ),
			)
		);
		$this->add_control(
			'lowest_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Lowest area', 'uncoder' ),
				'default'     => __( 'Lowest right now: {area}, {score} out of {max}.', 'uncoder' ),
				'description' => __( '{area}, {score} and {max} are filled in as visitors score.', 'uncoder' ),
			)
		);
		$this->add_control(
			'lowest_note',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Under the lowest area', 'uncoder' ),
				'rows'    => 2,
				'default' => __( 'That’s usually the spoke we’d start with. A small change there tends to steady everything else.', 'uncoder' ),
			)
		);
		$this->add_control(
			'button_text',
			array(
				'type'    => 'text',
				'label'   => __( 'Button text', 'uncoder' ),
				'default' => __( 'Talk it through', 'uncoder' ),
			)
		);
		$this->add_control(
			'link',
			array(
				'type'    => 'url',
				'label'   => __( 'Button link', 'uncoder' ),
				'default' => array( 'url' => '' ),
			)
		);
		$this->add_control(
			'average_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Average', 'uncoder' ),
				'default'     => __( 'Average score: {average} out of {max}', 'uncoder' ),
				'description' => __( 'Leave empty to hide it.', 'uncoder' ),
			)
		);
		$this->add_control(
			'group_label',
			array(
				'type'        => 'text',
				'label'       => __( 'Score buttons', 'uncoder' ),
				'default'     => __( 'Score {area} from 1 to {max}', 'uncoder' ),
				'description' => __( 'What screen readers hear for each row of numbers.', 'uncoder' ),
			)
		);
		$this->add_control(
			'stack_order',
			array(
				'type'        => 'select',
				'label'       => __( 'When stacked', 'uncoder' ),
				'default'     => '',
				'options'     => array(
					''       => __( 'The wheel, then the scores', 'uncoder' ),
					'scores' => __( 'The scores, then the wheel', 'uncoder' ),
				),
				'description' => __( 'On tablets and phones the scores and the wheel card stack in one column.', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_wheel', array( 'label' => __( 'Wheel', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'wheel_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 120, 'max' => 1200 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-wheel-width: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'ink',
			array(
				'type'        => 'color',
				'label'       => __( 'Lines and names', 'uncoder' ),
				'description' => __( 'The rim, the spokes, the centre, the lowest outline and the names.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-wheel-ink: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'disc',
			array(
				'type'        => 'color',
				'label'       => __( 'Wheel background', 'uncoder' ),
				'description' => __( 'Also the thin gap between the wedges.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-wheel-disc: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'rings',
			array(
				'type'      => 'color',
				'label'     => __( 'Rings', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-wheel-ring: {{VALUE}}' ),
			)
		);
		$this->add_group( 'name_typography', array( 'type' => 'typography', 'label' => __( 'Names', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-score-wheel__name' ) );
		$this->add_group( 'legend_typography', array( 'type' => 'typography', 'label' => __( 'Legend', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-score-wheel__legend', 'condition' => array( 'show_before' => true ) ) );
		$this->end_section();

		$this->start_section( 'style_rows', array( 'label' => __( 'Score rows', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'mode' => 'checkin' ) ) );
		$this->add_control(
			'row_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Row background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-wheel-row-bg: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Row corners', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-wheel-row-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between rows', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-wheel-row-gap: {{VALUE}}' ),
			)
		);
		$this->add_group( 'area_typography', array( 'type' => 'typography', 'label' => __( 'Area name', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-score-wheel__area' ) );
		$this->add_group( 'hint_typography', array( 'type' => 'typography', 'label' => __( 'Hint', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-score-wheel__hint' ) );
		$this->add_group( 'pill_typography', array( 'type' => 'typography', 'label' => __( 'Numbers', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-score-wheel__pill' ) );
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-wheel-text: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'muted_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Soft text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-wheel-muted: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pill_background',
			array(
				'type'        => 'color',
				'label'       => __( 'Unscored numbers', 'uncoder' ),
				'description' => __( 'The numbers above the score. Those up to it take the area\'s color; the score itself the lines color.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-wheel-pill-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'pill_border',
			array(
				'type'      => 'color',
				'label'     => __( 'Unscored border', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-wheel-pill-border: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_card', array( 'label' => __( 'Wheel card', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'mode' => 'checkin' ) ) );
		$this->add_control(
			'card_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-wheel-card-bg: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-score-wheel__card' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Corners', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-wheel-card-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'note_typography', array( 'type' => 'typography', 'label' => __( 'Note', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-score-wheel__note' ) );
		$this->add_group( 'lowest_typography', array( 'type' => 'typography', 'label' => __( 'Lowest area', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-score-wheel__lowest' ) );
		$this->add_control(
			'accent',
			array(
				'type'      => 'color',
				'label'     => __( 'Lowest area name', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-wheel-accent: {{VALUE}}' ),
			)
		);
		$this->add_group( 'body_typography', array( 'type' => 'typography', 'label' => __( 'Text under it', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-score-wheel__body' ) );
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Button', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-score-wheel__button' ) );
		$this->add_control(
			'button_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Button text', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-score-wheel__button' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Button background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-score-wheel__button' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Button hover', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-score-wheel__button:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * The areas, cleaned: [ label, hint, color, score, before|null ].
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<int,array{label:string,hint:string,color:string,score:int,before:?int}>
	 */
	private function areas( array $s, int $max ): array {
		$out = array();
		foreach ( Repeater_Rows::get( $this, 'areas', $s['areas'] ?? array() ) as $row ) {
			$color  = Utils::sanitize_color( (string) ( $row['color'] ?? '' ) );
			$before = $row['before'] ?? '';
			$out[]  = array(
				'label'  => trim( (string) ( $row['label'] ?? '' ) ),
				'hint'   => trim( (string) ( $row['hint'] ?? '' ) ),
				'color'  => '' !== $color ? $color : '#D2C6F4',
				'score'  => max( 0, min( $max, (int) ( $row['score'] ?? 0 ) ) ),
				'before' => ( '' === $before || null === $before ) ? null : max( 0, min( $max, (int) $before ) ),
			);
		}
		return array_slice( $out, 0, 16 );
	}

	/** The wedge of area $i of $n for a score: an SVG path from the centre. */
	public static function wedge( int $i, int $n, float $score, int $max, float $cx, float $cy, float $r ): string {
		$rr   = $r * self::HUB + $r * ( 1 - self::HUB ) * max( 0, min( 1, $score / max( 1, $max ) ) );
		$step = 360 / max( 1, $n );
		$a0   = deg2rad( -90 - $step / 2 + $i * $step );
		$a1   = deg2rad( -90 + $step / 2 + $i * $step );
		$p    = static fn( $a ) => sprintf( '%.2f,%.2f', $cx + $rr * cos( $a ), $cy + $rr * sin( $a ) );
		return sprintf( 'M%.2f,%.2f L%s A%.2f,%.2f 0 %d 1 %s Z', $cx, $cy, $p( $a0 ), $rr, $rr, $step > 180 ? 1 : 0, $p( $a1 ) );
	}

	/**
	 * The wheel as SVG.
	 *
	 * @param array<int,array{label:string,hint:string,color:string,score:int,before:?int}> $areas Areas.
	 */
	private function svg( array $areas, int $max, array $s, string $label ): string {
		$n      = count( $areas );
		$labels = ! array_key_exists( 'show_labels', $s ) || ! empty( $s['show_labels'] );
		$before = 'chart' === ( $s['mode'] ?? 'checkin' ) && ! empty( $s['show_before'] );
		$chart  = 'chart' === ( $s['mode'] ?? 'checkin' );
		// A chart without names fills its square like a small picture: a little margin and a hub that grows with it.
		$r      = $labels ? 190.0 : 184.0;
		$cx     = $labels ? 320.0 : 200.0;
		$cy     = $labels ? 280.0 : 200.0;
		$view   = $labels ? '-70 0 780 560' : '0 0 400 400';
		$parts  = array( sprintf( '<circle class="uncoder-score-wheel__disc" cx="%.0f" cy="%.0f" r="%.0f"/>', $cx, $cy, $r ) );
		$rings  = '';
		for ( $k = 2; $k <= $max; $k += max( 1, (int) round( $max / 5 ) ) ) {
			$rings .= sprintf( '<circle cx="%.0f" cy="%.0f" r="%.2f"/>', $cx, $cy, $r * self::HUB + $r * ( 1 - self::HUB ) * $k / $max );
		}
		$parts[] = '<g class="uncoder-score-wheel__rings">' . $rings . '</g>';
		$wedges  = '';
		foreach ( $areas as $i => $a ) {
			$wedges .= '<path class="uncoder-score-wheel__wedge" data-area="' . (int) $i . '" d="' . esc_attr( self::wedge( $i, $n, $a['score'], $max, $cx, $cy, $r ) ) . '" style="fill:' . esc_attr( $a['color'] ) . '"/>';
		}
		$parts[] = '<g class="uncoder-score-wheel__wedges">' . $wedges . '</g>';
		$spokes  = '';
		$step    = 360 / max( 1, $n );
		for ( $i = 0; $i < $n; $i++ ) {
			$a       = deg2rad( -90 - $step / 2 + $i * $step );
			$spokes .= sprintf( '<line x1="%.0f" y1="%.0f" x2="%.2f" y2="%.2f"/>', $cx, $cy, $cx + $r * cos( $a ), $cy + $r * sin( $a ) );
		}
		$parts[] = '<g class="uncoder-score-wheel__spokes">' . $spokes . '</g>';
		if ( $before ) {
			$dashed = '';
			foreach ( $areas as $i => $a ) {
				if ( null !== $a['before'] ) {
					$dashed .= '<path d="' . esc_attr( self::wedge( $i, $n, $a['before'], $max, $cx, $cy, $r ) ) . '"/>';
				}
			}
			$parts[] = '<g class="uncoder-score-wheel__before">' . $dashed . '</g>';
		}
		$low = $this->lowest( $areas );
		if ( ( ! array_key_exists( 'show_lowest', $s ) || ! empty( $s['show_lowest'] ) ) && null !== $low ) {
			$parts[] = '<path class="uncoder-score-wheel__low" d="' . esc_attr( self::wedge( $low, $n, $areas[ $low ]['score'], $max, $cx, $cy, $r ) ) . '"/>';
		}
		$parts[] = sprintf( '<circle class="uncoder-score-wheel__rim" cx="%.0f" cy="%.0f" r="%.0f"/><circle class="uncoder-score-wheel__hub" cx="%.0f" cy="%.0f" r="%.0f"/>', $cx, $cy, $r, $cx, $cy, $chart && ! $labels ? 10 : 6 );
		if ( $labels ) {
			$names = '';
			foreach ( $areas as $i => $a ) {
				$ang    = deg2rad( -90 + $i * $step );
				$x      = $cx + ( $r + 30 ) * cos( $ang );
				$y      = $cy + ( $r + 30 ) * sin( $ang );
				$anchor = abs( cos( $ang ) ) < 0.3 ? 'middle' : ( cos( $ang ) > 0 ? 'start' : 'end' );
				// The lowest area's name is marked (accent, bold) when the lowest area is outlined.
				$mark   = $i === $low && ( ! array_key_exists( 'show_lowest', $s ) || ! empty( $s['show_lowest'] ) ) ? ' is-low' : '';
				$names .= sprintf( '<text class="uncoder-score-wheel__name%s" data-area="%d" x="%.2f" y="%.2f" text-anchor="%s" dominant-baseline="middle">%s</text>', $mark, (int) $i, $x, $y, $anchor, esc_html( $a['label'] ) );
			}
			$parts[] = '<g aria-hidden="true">' . $names . '</g>';
		}
		$summary = implode(
			', ',
			array_map(
				/* translators: 1: area, 2: score, 3: highest score. */
				static fn( $a ) => sprintf( __( '%1$s %2$d of %3$d', 'uncoder' ), $a['label'], $a['score'], $max ),
				$areas
			)
		);
		return '<svg class="uncoder-score-wheel__svg" viewBox="' . esc_attr( $view ) . '" role="img" aria-label="' . esc_attr( $label . ': ' . $summary ) . '">' . implode( '', $parts ) . '</svg>';
	}

	/**
	 * The index of the lowest-scored area (the first of equals), or null.
	 *
	 * @param array<int,array{score:int}> $areas Areas.
	 */
	private function lowest( array $areas ): ?int {
		$low = null;
		foreach ( $areas as $i => $a ) {
			if ( null === $low || $a['score'] < $areas[ $low ]['score'] ) {
				$low = $i;
			}
		}
		return $low;
	}

	/** "Lowest right now: <span>Fun & rest</span>, 3 out of 10." with the area marked. */
	private static function fill( string $template, array $values, string $accent_key = '' ): string {
		$html = esc_html( $template );
		foreach ( $values as $key => $value ) {
			$v    = esc_html( (string) $value );
			$v    = $key === $accent_key ? '<span class="uncoder-score-wheel__accent">' . $v . '</span>' : $v;
			$html = str_replace( '{' . $key . '}', $v, $html );
		}
		return $html;
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$max = max( 3, min( 20, (int) ( $s['max'] ?? 10 ) ) );
		return array(
			'data-settings' => $this->json_attr(
				array(
					'max'     => $max,
					'lowest'  => (string) ( $s['lowest_text'] ?? '' ),
					'average' => (string) ( $s['average_text'] ?? '' ),
					'label'   => (string) ( $s['chart_label'] ?? __( 'Wheel of life', 'uncoder' ) ),
					/* translators: 1: area, 2: score, 3: highest score. */
					'of'      => __( '%1$s %2$d of %3$d', 'uncoder' ),
					'outline' => ! array_key_exists( 'show_lowest', $s ) || ! empty( $s['show_lowest'] ),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$max   = max( 3, min( 20, (int) ( $s['max'] ?? 10 ) ) );
		$areas = $this->areas( $s, $max );
		$mode  = 'chart' === ( $s['mode'] ?? 'checkin' ) ? 'chart' : 'checkin';
		$label = trim( (string) ( $s['chart_label'] ?? '' ) );
		$label = '' !== $label ? $label : __( 'Wheel of life', 'uncoder' );
		if ( ! $areas ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-score-wheel uncoder-score-wheel--empty">' . esc_html__( 'Add areas to draw the wheel.', 'uncoder' ) . '</div>';
			}
			return;
		}
		$svg = $this->svg( $areas, $max, $s, $label );

		if ( 'chart' === $mode ) {
			echo '<div class="uncoder-score-wheel uncoder-score-wheel--chart">' . $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			if ( ! empty( $s['show_before'] ) ) {
				// The "now" swatch carries the wheel's own colours, left to right.
				$stops = array();
				$last  = max( 1, count( $areas ) - 1 );
				foreach ( array_values( $areas ) as $i => $area ) {
					$stops[] = $area['color'] . ' ' . round( 100 * $i / $last ) . '%';
				}
				$swatch = count( $stops ) > 1 ? ' style="background-image: linear-gradient(90deg, ' . esc_attr( implode( ', ', $stops ) ) . ')"' : '';
				echo '<p class="uncoder-score-wheel__legend"><span class="uncoder-score-wheel__key uncoder-score-wheel__key--before" aria-hidden="true"></span>' . esc_html( (string) ( $s['before_label'] ?? __( 'Before', 'uncoder' ) ) ) . '<span class="uncoder-score-wheel__key uncoder-score-wheel__key--after" aria-hidden="true"' . $swatch . '></span>' . esc_html( (string) ( $s['after_label'] ?? __( 'Now', 'uncoder' ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $swatch is escaped above.
			}
			echo '</div>';
			return;
		}

		$uid   = 'uncoder-wheel-' . sanitize_html_class( $ctx->element_id );
		$group = (string) ( $s['group_label'] ?? __( 'Score {area} from 1 to {max}', 'uncoder' ) );
		$order = 'scores' === ( $s['stack_order'] ?? '' ) ? ' uncoder-score-wheel--scores-first' : '';
		echo '<div class="uncoder-score-wheel uncoder-score-wheel--checkin' . esc_attr( $order ) . '"><div class="uncoder-score-wheel__rows">';
		foreach ( $areas as $i => $a ) {
			$legend = str_replace( array( '{area}', '{max}' ), array( $a['label'], (string) $max ), $group );
			echo '<fieldset class="uncoder-score-wheel__row" data-area="' . (int) $i . '" style="--uncoder-wheel-area:' . esc_attr( $a['color'] ) . '">';
			echo '<legend class="uncoder-score-wheel__sr">' . esc_html( $legend ) . '</legend>';
			echo '<div class="uncoder-score-wheel__head" aria-hidden="true"><span class="uncoder-score-wheel__area"><span class="uncoder-score-wheel__dot"></span>' . esc_html( $a['label'] ) . '</span>';
			if ( '' !== $a['hint'] ) {
				echo '<span class="uncoder-score-wheel__hint">' . esc_html( $a['hint'] ) . '</span>';
			}
			echo '</div><div class="uncoder-score-wheel__pills" style="--uncoder-wheel-steps:' . (int) $max . '">';
			for ( $k = 1; $k <= $max; $k++ ) {
				$id    = $uid . '-' . $i . '-' . $k;
				$state = $k < $a['score'] ? ' is-filled' : ( $k === $a['score'] ? ' is-score' : '' );
				echo '<input class="uncoder-score-wheel__sr" type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( $uid . '-' . $i ) . '" value="' . (int) $k . '"' . checked( $k, $a['score'], false ) . '>';
				echo '<label class="uncoder-score-wheel__pill' . esc_attr( $state ) . '" for="' . esc_attr( $id ) . '">' . (int) $k . '</label>';
			}
			echo '</div></fieldset>';
		}
		echo '</div><div class="uncoder-score-wheel__card">';
		$note = trim( (string) ( $s['card_note'] ?? '' ) );
		if ( '' !== $note ) {
			echo '<p class="uncoder-score-wheel__note"><span class="uncoder-score-wheel__note-dot" aria-hidden="true"></span>' . esc_html( $note ) . '</p>';
		}
		echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		$low     = $this->lowest( $areas );
		$average = number_format_i18n( array_sum( array_column( $areas, 'score' ) ) / count( $areas ), 1 );
		echo '<div class="uncoder-score-wheel__result">';
		$lowest = (string) ( $s['lowest_text'] ?? '' );
		if ( '' !== trim( $lowest ) && null !== $low ) {
			echo '<p class="uncoder-score-wheel__lowest" aria-live="polite">' . self::fill( $lowest, array( 'area' => $areas[ $low ]['label'], 'score' => $areas[ $low ]['score'], 'max' => $max ), 'area' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in fill().
		}
		$body = trim( (string) ( $s['lowest_note'] ?? '' ) );
		if ( '' !== $body ) {
			echo '<p class="uncoder-score-wheel__body">' . esc_html( $body ) . '</p>';
		}
		$text = trim( (string) ( $s['button_text'] ?? '' ) );
		$link = $this->link_attrs( $s['link'] ?? array() );
		if ( '' !== $text && $link ) {
			$link['class'] = array( 'uncoder-score-wheel__button' );
			echo '<a' . Utils::attrs( $link ) . '><span>' . esc_html( $text ) . '</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		}
		$avg = (string) ( $s['average_text'] ?? '' );
		if ( '' !== trim( $avg ) ) {
			echo '<p class="uncoder-score-wheel__average">' . self::fill( $avg, array( 'average' => $average, 'max' => $max ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in fill().
		}
		echo '</div></div></div>';
	}
}
