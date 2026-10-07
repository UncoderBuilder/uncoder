<?php
/**
 * Text Path widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Svg_Path;

defined( 'ABSPATH' ) || exit;

/**
 * Text written along an SVG path (wave, arc, circle, oval, line, spiral or a custom path).
 *
 * The shape is an inline SVG whose viewBox width is its natural size: at that width one unit is one
 * pixel, so the text keeps the font size set in Typography; narrower screens scale the whole drawing.
 */
class Text_Path extends Widget_Base {

	/**
	 * Built-in shapes: viewBox, path data and (for closed shapes) the reversed path. Closed shapes start
	 * at the bottom so "center" sits at the top; reversed they start at the top and run counter-clockwise,
	 * which puts centred text inside the shape at the bottom, upright.
	 */
	public const PATHS = array(
		'wave'   => array(
			'box' => '0 0 500 150',
			'd'   => 'M0,95 Q62.5,35 125,95 T250,95 T375,95 T500,95',
		),
		'arc'    => array(
			'box' => '0 0 500 220',
			'd'   => 'M30,200 A270,270 0 0 1 470,200',
		),
		'circle' => array(
			'box'     => '0 0 250 250',
			'd'       => 'M125,220 A95,95 0 1 1 125,30 A95,95 0 1 1 125,220',
			'reverse' => 'M125,30 A95,95 0 1 0 125,220 A95,95 0 1 0 125,30',
		),
		'oval'   => array(
			'box'     => '0 0 400 250',
			'd'       => 'M200,220 A170,95 0 1 1 200,30 A170,95 0 1 1 200,220',
			'reverse' => 'M200,30 A170,95 0 1 0 200,220 A170,95 0 1 0 200,30',
		),
		'line'   => array(
			'box' => '0 0 500 60',
			'd'   => 'M0,42 L500,42',
		),
		'spiral' => array(
			'box' => '0 0 300 300',
			'd'   => 'M18,150 A126,126 0 0 1 270,150 A114,114 0 0 1 42,150 A102,102 0 0 1 246,150 A90,90 0 0 1 66,150 A78,78 0 0 1 222,150 A66,66 0 0 1 90,150 A54,54 0 0 1 198,150 A42,42 0 0 1 114,150',
		),
	);

	/** Instances rendered in this request (ids stay unique when an element repeats in a loop). */
	private static int $instances = 0;

	public function name(): string {
		return 'text-path';
	}

	public function title(): string {
		return __( 'Text Path', 'uncoder' );
	}

	public function icon(): string {
		return 'spline';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'text path', 'curved text', 'circle text', 'wave', 'arc', 'svg', 'badge', 'spiral', 'rotating text' );
	}

	public function description(): string {
		return __( 'Short text written along a curve: wave, arc, circle, oval, line, spiral or your own SVG path. Use it for round badges ("Scroll down • Scroll down •", optionally spinning), curved captions and decorative headings; keep the text short and put long copy in a Heading or Text widget.', 'uncoder' );
	}

	public function preset(): array {
		return array(
			// Sized to fill the ring: about 95% of the circle with a body font.
			'text'       => __( 'Text along a path • Text along a path • ', 'uncoder' ),
			'path'       => 'circle',
			'typography' => array(
				'size'           => array( 'size' => 20, 'unit' => 'px' ),
				'weight'         => '600',
				'transform'      => 'uppercase',
				'letter_spacing' => array( 'size' => 4, 'unit' => 'px' ),
			),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Text path', 'uncoder' ) ) );
		$this->add_control(
			'text',
			array(
				'type'    => 'text',
				'label'   => __( 'Text', 'uncoder' ),
				'default' => __( 'Words that follow the curve', 'uncoder' ),
				'dynamic' => true,
				'ai'      => 'Short plain text (no HTML). For a full circle, repeat a phrase with separators so it fills the ring, e.g. "Scroll down • Scroll down • Scroll down • ".',
			)
		);
		$this->add_control(
			'path',
			array(
				'type'    => 'select',
				'label'   => __( 'Path', 'uncoder' ),
				'default' => 'wave',
				'options' => array(
					'wave'   => __( 'Wave', 'uncoder' ),
					'arc'    => __( 'Arc', 'uncoder' ),
					'circle' => __( 'Circle', 'uncoder' ),
					'oval'   => __( 'Oval', 'uncoder' ),
					'line'   => __( 'Line', 'uncoder' ),
					'spiral' => __( 'Spiral', 'uncoder' ),
					'custom' => __( 'Custom SVG path', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'custom_path',
			array(
				'type'        => 'textarea',
				'label'       => __( 'Path data', 'uncoder' ),
				'rows'        => 3,
				'placeholder' => 'M0,100 C150,0 350,200 500,100',
				'description' => __( 'The "d" attribute of an SVG <path>, drawn inside the view box below.', 'uncoder' ),
				'condition'   => array( 'path' => 'custom' ),
				'ai'          => 'SVG path data only, e.g. "M0,100 C150,0 350,200 500,100". Coordinates must fit custom_viewbox.',
			)
		);
		$this->add_control(
			'custom_viewbox',
			array(
				'type'        => 'text',
				'label'       => __( 'View box', 'uncoder' ),
				'default'     => '0 0 500 200',
				'description' => __( 'min-x min-y width height of the drawing. The width is the natural size in pixels.', 'uncoder' ),
				'condition'   => array( 'path' => 'custom' ),
			)
		);
		$this->add_control( 'link', array( 'type' => 'url', 'label' => __( 'Link', 'uncoder' ), 'dynamic' => true ) );
		$this->add_control(
			'text_align',
			array(
				'type'    => 'choose',
				'label'   => __( 'Text alignment', 'uncoder' ),
				'default' => 'center',
				'options' => array(
					'start'  => array( 'label' => __( 'Start of the path', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Middle of the path', 'uncoder' ), 'icon' => 'align-center' ),
					'end'    => array( 'label' => __( 'End of the path', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'ai'      => 'Where the text sits along the path. On circles and ovals "center" is the top (the bottom when reverse is on).',
			)
		);
		$this->add_control(
			'start_offset',
			array(
				'type'        => 'slider',
				'label'       => __( 'Starting point', 'uncoder' ),
				'size_units'  => array( '%' ),
				'range'       => array( '%' => array( 'min' => -100, 'max' => 100 ) ),
				'description' => __( 'Moves the text along the path, as a percentage of its length.', 'uncoder' ),
			)
		);
		$this->add_control(
			'reverse',
			array(
				'type'        => 'switch',
				'label'       => __( 'Reverse direction', 'uncoder' ),
				'description' => __( 'Runs the text the other way along the path. Circles and ovals then carry it inside, centred at the bottom.', 'uncoder' ),
			)
		);
		$this->add_control(
			'baseline',
			array(
				'type'                 => 'select',
				'label'                => __( 'Text position', 'uncoder' ),
				'options'              => array(
					''       => __( 'On top of the path', 'uncoder' ),
					'center' => __( 'Centred on the path', 'uncoder' ),
					'below'  => __( 'Below the path', 'uncoder' ),
				),
				'selectors_dictionary' => array(
					'center' => 'central',
					'below'  => 'hanging',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-text-path__text' => 'dominant-baseline: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'show_path',
			array(
				'type'  => 'switch',
				'label' => __( 'Show the path', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_text', array( 'label' => __( 'Text', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group(
			'typography',
			array(
				'type'     => 'typography',
				'label'    => __( 'Typography', 'uncoder' ),
				'selector' => '{{WRAPPER}} .uncoder-text-path__text',
				'ai'       => 'Sizes are at the natural width (1 unit = 1px); the drawing scales down on small screens. Letter and word spacing spread the text along the path.',
			)
		);
		$this->start_tabs( 'text_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-text-path__text' => 'fill: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-text-path__shape:is(:hover, :focus-visible) .uncoder-text-path__text' => 'fill: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();

		$this->start_section(
			'style_path',
			array(
				'label'     => __( 'Path', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_path' => 'yes' ),
			)
		);
		$this->add_control(
			'path_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-tp-stroke: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'path_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0.5, 'max' => 10, 'step' => 0.5 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-tp-stroke-w: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'path_dashed',
			array(
				'type'      => 'switch',
				'label'     => __( 'Dashed', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-text-path__path' => 'stroke-dasharray: 4 6' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_shape', array( 'label' => __( 'Shape', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'width',
			array(
				'type'        => 'slider',
				'label'       => __( 'Size', 'uncoder' ),
				'size_units'  => array( 'px', '%', 'vw', 'rem' ),
				'range'       => array( 'px' => array( 'min' => 40, 'max' => 1200 ) ),
				'description' => __( 'Width of the drawing. Text scales with it.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-tp-w: {{VALUE}}' ),
				'ai'          => 'Default is the natural width (wave/arc/line 500px, oval 400px, spiral 300px, circle 250px). Never wider than its column.',
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'rotation',
			array(
				'type'       => 'slider',
				'label'      => __( 'Rotation', 'uncoder' ),
				'size_units' => array( 'deg' ),
				'range'      => array( 'deg' => array( 'min' => -180, 'max' => 180 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-text-path__svg' => 'rotate: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'spin',
			array(
				'type'        => 'switch',
				'label'       => __( 'Spin continuously', 'uncoder' ),
				'description' => __( 'Best with circles. Stays still for visitors who prefer reduced motion.', 'uncoder' ),
			)
		);
		$this->add_control(
			'spin_duration',
			array(
				'type'        => 'number',
				'label'       => __( 'Seconds per turn', 'uncoder' ),
				'min'         => 2,
				'max'         => 120,
				'step'        => 1,
				'placeholder' => '20',
				'condition'   => array( 'spin' => 'yes' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-tp-spin: {{VALUE}}s' ),
			)
		);
		$this->add_control(
			'spin_reverse',
			array(
				'type'      => 'switch',
				'label'     => __( 'Spin counter-clockwise', 'uncoder' ),
				'condition' => array( 'spin' => 'yes' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Viewbox numbers (x, y, width, height) or null.
	 *
	 * @return float[]|null
	 */
	private static function viewbox( string $box ): ?array {
		$parts = preg_split( '/[\s,]+/', trim( $box ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) || 4 !== count( $parts ) ) {
			return null;
		}
		foreach ( $parts as $p ) {
			if ( ! is_numeric( $p ) ) {
				return null;
			}
		}
		$nums = array_map( 'floatval', $parts );
		return $nums[2] > 0 && $nums[3] > 0 ? $nums : null;
	}

	private function editor_notice( string $message ): void {
		echo '<div class="uncoder-text-path"><p class="uncoder-text-path__placeholder">' . esc_html( $message ) . '</p></div>';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$text = trim( (string) ( $s['text'] ?? '' ) );
		if ( '' === $text ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$text = __( 'Add your text', 'uncoder' );
		}

		$type    = (string) ( $s['path'] ?? 'wave' );
		$reverse = ! empty( $s['reverse'] );
		$natural = '';
		if ( 'custom' === $type ) {
			$d   = Svg_Path::clean( (string) ( $s['custom_path'] ?? '' ) );
			$box = self::viewbox( (string) ( $s['custom_viewbox'] ?? '' ) );
			if ( '' === $d || null === $box ) {
				if ( $ctx->editor ) {
					$this->editor_notice( '' === $d ? __( 'Paste valid SVG path data (starting with M) in the Content tab.', 'uncoder' ) : __( 'The view box needs four numbers: min-x min-y width height.', 'uncoder' ) );
				}
				return;
			}
			if ( $reverse ) {
				$d = Svg_Path::reverse( $d );
			}
			$viewbox = implode( ' ', array_map( static fn( $n ) => (string) round( $n, 3 ), $box ) );
			$natural = '--uncoder-tp-w0:' . round( $box[2], 2 ) . 'px';
		} else {
			$type    = isset( self::PATHS[ $type ] ) ? $type : 'wave';
			$shape   = self::PATHS[ $type ];
			$viewbox = $shape['box'];
			$d       = $shape['d'];
			if ( $reverse ) {
				$d = $shape['reverse'] ?? Svg_Path::reverse( $d );
			}
		}

		$align  = in_array( $s['text_align'] ?? 'center', array( 'start', 'center', 'end' ), true ) ? (string) $s['text_align'] : 'center';
		$base   = array(
			'start'  => 0,
			'center' => 50,
			'end'    => 100,
		)[ $align ];
		$offset = $s['start_offset']['size'] ?? '';
		$offset = is_numeric( $offset ) ? max( -100, min( 100, (float) $offset ) ) : 0;
		$anchor = array(
			'start'  => 'start',
			'center' => 'middle',
			'end'    => 'end',
		)[ $align ];

		$uid = 'uncoder-tp-' . sanitize_html_class( '' !== $ctx->element_id ? $ctx->element_id : 'x' ) . '-' . ( ++self::$instances );

		$classes = array( 'uncoder-text-path', 'uncoder-text-path--' . $type );
		if ( ! empty( $s['show_path'] ) ) {
			$classes[] = 'uncoder-text-path--path';
		}
		if ( ! empty( $s['spin'] ) ) {
			$classes[] = 'uncoder-text-path--spin';
			if ( ! empty( $s['spin_reverse'] ) ) {
				$classes[] = 'uncoder-text-path--spin-ccw';
			}
		}

		$link = $this->link_attrs( $s['link'] ?? array() );
		$svg  = array(
			'class'      => 'uncoder-text-path__svg' . ( $link ? '' : ' uncoder-text-path__shape' ),
			'xmlns'      => 'http://www.w3.org/2000/svg',
			'viewBox'    => $viewbox,
			'role'       => 'img',
			'aria-label' => $text,
		);

		$root = array( 'class' => implode( ' ', $classes ) );
		if ( '' !== $natural ) {
			$root['style'] = $natural;
		}

		echo '<div' . Utils::attrs( $root ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		if ( $link ) {
			$link['class'] = 'uncoder-text-path__link uncoder-text-path__shape';
			echo '<a' . Utils::attrs( $link ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		}
		echo '<svg' . Utils::attrs( $svg ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs() escapes.
		echo '<path id="' . esc_attr( $uid ) . '" class="uncoder-text-path__path" d="' . esc_attr( $d ) . '"/>';
		echo '<text class="uncoder-text-path__text" text-anchor="' . esc_attr( $anchor ) . '">';
		echo '<textPath href="#' . esc_attr( $uid ) . '" startOffset="' . esc_attr( ( $base + $offset ) . '%' ) . '">' . esc_html( $text ) . '</textPath>';
		echo '</text></svg>';
		if ( $link ) {
			echo '</a>';
		}
		echo '</div>';
	}
}
