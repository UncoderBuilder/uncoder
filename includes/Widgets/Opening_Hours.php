<?php
/**
 * Opening Hours widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * A business's weekly hours: a live "Open now · until 6pm" / "Closed · opens Monday 8am" badge, the list of days, or
 * both. The server renders the state at render time (so it reads without JavaScript); the "opening-hours" module works
 * it out again in the visitor's browser in the site's timezone (page caches keep the HTML) and every minute after.
 */
class Opening_Hours extends Widget_Base {

	/** Monday first, as the rows are entered; values are the keys used in settings. */
	public const DAYS = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );

	public function name(): string {
		return 'opening-hours';
	}

	public function title(): string {
		return __( 'Opening Hours', 'uncoder' );
	}

	public function icon(): string {
		return 'clock';
	}

	public function category(): string {
		return 'basic';
	}

	public function keywords(): array {
		return array( 'opening hours', 'open now', 'business hours', 'hours', 'schedule', 'closed', 'office hours', 'shop' );
	}

	public function description(): string {
		return __( 'Your weekly hours as a live open or closed badge ("Open now · until 6pm"), a list of days, or both, worked out in the site\'s timezone.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'opening-hours' );
	}

	public function preset(): array {
		return array(
			'hours' => array(
				array(
					'from'   => 'mon',
					'to'     => 'fri',
					'opens'  => '09:00',
					'closes' => '17:00',
				),
			),
		);
	}

	/**
	 * Day options for the selects.
	 *
	 * @return array<string,string>
	 */
	private static function day_options( bool $none = false ): array {
		$out = $none ? array( '' => __( 'Same day', 'uncoder' ) ) : array();
		foreach ( self::DAYS as $i => $key ) {
			$out[ $key ] = self::day_name( $i );
		}
		return $out;
	}

	/** The weekday name of index 0 (Monday) … 6 (Sunday), in the site's language. */
	private static function day_name( int $i ): string {
		global $wp_locale;
		$wp = ( $i + 1 ) % 7; // WP_Locale counts from Sunday.
		return $wp_locale ? (string) $wp_locale->get_weekday( $wp ) : (string) ucfirst( self::DAYS[ $i ] );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Opening hours', 'uncoder' ) ) );
		$this->add_control(
			'hours',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Hours', 'uncoder' ),
				'title_field' => 'from',
				'description' => __( 'One row per day or run of days with the same hours. Days without a row are closed.', 'uncoder' ),
				'fields'      => array(
					'from'   => array(
						'type'    => 'select',
						'label'   => __( 'From', 'uncoder' ),
						'default' => 'mon',
						'options' => self::day_options(),
					),
					'to'     => array(
						'type'    => 'select',
						'label'   => __( 'To', 'uncoder' ),
						'default' => '',
						'options' => self::day_options( true ),
					),
					'opens'  => array(
						'type'        => 'text',
						'label'       => __( 'Opens', 'uncoder' ),
						'default'     => '09:00',
						'placeholder' => '09:00',
						'description' => __( '24-hour time (09:00) or 9am.', 'uncoder' ),
					),
					'closes' => array(
						'type'        => 'text',
						'label'       => __( 'Closes', 'uncoder' ),
						'default'     => '17:00',
						'placeholder' => '17:00',
						'description' => __( 'Earlier than the opening time for hours past midnight.', 'uncoder' ),
					),
				),
				'default'     => $this->preset()['hours'],
				'ai'          => 'Rows of {"from":"mon","to":"fri","opens":"08:00","closes":"18:00"}; "to" "" for one day; days without a row are closed. Times as "HH:MM" (24-hour).',
			)
		);
		$this->add_control(
			'display',
			array(
				'type'    => 'select',
				'label'   => __( 'Show', 'uncoder' ),
				'default' => 'status',
				'options' => array(
					'status' => __( 'Open or closed now', 'uncoder' ),
					'list'   => __( 'The list of days', 'uncoder' ),
					'both'   => __( 'Both', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'open_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Open text', 'uncoder' ),
				'default'     => __( 'Open now · until {close}', 'uncoder' ),
				'description' => __( '{close} is the closing time.', 'uncoder' ),
				'condition'   => array( 'display' => array( 'status', 'both' ) ),
			)
		);
		$this->add_control(
			'closed_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Closed text', 'uncoder' ),
				'default'     => __( 'Closed · opens {day} {open}', 'uncoder' ),
				'description' => __( '{day} is "today", "tomorrow" or the day; {open} the opening time.', 'uncoder' ),
				'condition'   => array( 'display' => array( 'status', 'both' ) ),
			)
		);
		$this->add_control(
			'time_format',
			array(
				'type'    => 'select',
				'label'   => __( 'Time format', 'uncoder' ),
				'default' => '12',
				'options' => array(
					'12' => __( '6pm, 8:30am', 'uncoder' ),
					'24' => __( '18:00, 08:30', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'show_closed',
			array(
				'type'        => 'switch',
				'label'       => __( 'List the closed days', 'uncoder' ),
				'description' => __( 'Days without hours appear as Closed.', 'uncoder' ),
				'default'     => true,
				'condition'   => array( 'display' => array( 'list', 'both' ) ),
			)
		);
		$this->add_control(
			'closed_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Closed label', 'uncoder' ),
				'default'   => __( 'Closed', 'uncoder' ),
				'condition' => array( 'display' => array( 'list', 'both' ) ),
			)
		);
		$this->add_control(
			'list_layout',
			array(
				'type'      => 'select',
				'label'     => __( 'List layout', 'uncoder' ),
				'default'   => 'columns',
				'options'   => array(
					'columns' => __( 'Days left, hours right', 'uncoder' ),
					'inline'  => __( 'One line per row', 'uncoder' ),
				),
				'condition' => array( 'display' => array( 'list', 'both' ) ),
			)
		);
		$this->add_control(
			'line_format',
			array(
				'type'        => 'text',
				'label'       => __( 'Line', 'uncoder' ),
				'default'     => '{days}: {hours}',
				'description' => __( '{days} and {hours} in your own words, such as "{days}, {hours}".', 'uncoder' ),
				'condition'   => array(
					'display'     => array( 'list', 'both' ),
					'list_layout' => 'inline',
				),
			)
		);
		$this->add_control(
			'range_word',
			array(
				'type'        => 'text',
				'label'       => __( 'Between', 'uncoder' ),
				'default'     => '–',
				'description' => __( 'Joins the first and last day and the opening and closing time: "Monday – Friday", or "to" for "7am to 6pm".', 'uncoder' ),
				'condition'   => array( 'display' => array( 'list', 'both' ) ),
			)
		);
		$this->add_control(
			'highlight_today',
			array(
				'type'      => 'switch',
				'label'     => __( 'Bold today', 'uncoder' ),
				'default'   => true,
				'condition' => array( 'display' => array( 'list', 'both' ) ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_status', array( 'label' => __( 'Open or closed', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'display' => array( 'status', 'both' ) ) ) );
		$this->add_group( 'status_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-opening-hours__status' ) );
		$this->add_control(
			'status_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-opening-hours__status' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'status_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-opening-hours__status' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'open_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Open dot', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-opening-hours-open: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'closed_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Closed dot', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-opening-hours-closed: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'dot_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Dot size', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-opening-hours-dot: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'status_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-opening-hours__status' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'status_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-opening-hours__status' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'status_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-opening-hours__status' ) );
		$this->end_section();

		$this->start_section( 'style_list', array( 'label' => __( 'List of days', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'display' => array( 'list', 'both' ) ) ) );
		$this->add_group( 'list_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-opening-hours__list' ) );
		$this->add_control(
			'day_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Day color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-opening-hours__day' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'time_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Time color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-opening-hours__time' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'today_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Today color', 'uncoder' ),
				'description' => __( 'Today\'s row, in bold.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}} .uncoder-opening-hours__row.is-today > *' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between rows', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-opening-hours-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'list_rule',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-opening-hours-rule: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/** Minutes after midnight from "08:00", "8:30", "8am", "6pm", "18.00"; null when unreadable. */
	public static function minutes( string $time ): ?int {
		$t = strtolower( trim( $time ) );
		if ( ! preg_match( '/^(\d{1,2})(?:[:.](\d{2}))?\s*(am|pm)?$/', $t, $m ) ) {
			return null;
		}
		$h   = (int) $m[1];
		$min = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
		if ( ! empty( $m[3] ) ) {
			$h = ( 12 === $h ? 0 : $h ) + ( 'pm' === $m[3] ? 12 : 0 );
		}
		if ( $h > 24 || $min > 59 || ( 24 === $h && $min > 0 ) ) {
			return null;
		}
		return $h * 60 + $min;
	}

	/**
	 * The rows as day runs with minutes: [ [ 'days' => [0..6], 'open' => int, 'close' => int ] ].
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<int,array{days:array<int,int>,open:int,close:int}>
	 */
	private function rows( array $s ): array {
		$out = array();
		foreach ( Repeater_Rows::get( $this, 'hours', $s['hours'] ?? array() ) as $row ) {
			$from  = array_search( (string) ( $row['from'] ?? '' ), self::DAYS, true );
			$to    = array_search( (string) ( $row['to'] ?? '' ), self::DAYS, true );
			$open  = self::minutes( (string) ( $row['opens'] ?? '' ) );
			$close = self::minutes( (string) ( $row['closes'] ?? '' ) );
			if ( false === $from || null === $open || null === $close ) {
				continue;
			}
			$to   = false === $to ? $from : $to;
			$days = array();
			for ( $d = (int) $from, $n = 0; $n < 7; $d = ( $d + 1 ) % 7, $n++ ) {
				$days[] = $d;
				if ( $d === (int) $to ) {
					break;
				}
			}
			$out[] = array(
				'days'  => $days,
				'open'  => $open,
				'close' => $close,
			);
		}
		return $out;
	}

	/** "6pm", "8:30am" or "18:00". */
	public static function format( int $minutes, string $format ): string {
		$minutes = $minutes % 1440;
		$h       = intdiv( $minutes, 60 );
		$m       = $minutes % 60;
		if ( '24' === $format ) {
			return sprintf( '%02d:%02d', $h, $m );
		}
		$suffix = $h < 12 ? 'am' : 'pm';
		$h12    = 0 === $h % 12 ? 12 : $h % 12;
		return $h12 . ( $m ? sprintf( ':%02d', $m ) : '' ) . $suffix;
	}

	/**
	 * Open or closed at a moment: [ open, close minutes | null, next [day offset, open minutes] | null ].
	 *
	 * @param array<int,array{days:array<int,int>,open:int,close:int}> $rows Rows.
	 * @return array{0:bool,1:?int,2:?array{0:int,1:int}}
	 */
	public static function state( array $rows, int $day, int $min ): array {
		foreach ( $rows as $r ) {
			$overnight = $r['close'] <= $r['open'];
			if ( in_array( $day, $r['days'], true ) && $min >= $r['open'] && ( $overnight || $min < $r['close'] ) ) {
				return array( true, $r['close'], null );
			}
			if ( $overnight && in_array( ( $day + 6 ) % 7, $r['days'], true ) && $min < $r['close'] ) {
				return array( true, $r['close'], null );
			}
		}
		for ( $offset = 0; $offset <= 7; $offset++ ) {
			$d     = ( $day + $offset ) % 7;
			$opens = array();
			foreach ( $rows as $r ) {
				if ( in_array( $d, $r['days'], true ) && ( $offset > 0 || $r['open'] > $min ) ) {
					$opens[] = $r['open'];
				}
			}
			if ( $opens ) {
				return array( false, null, array( $offset, min( $opens ) ) );
			}
		}
		return array( false, null, null );
	}

	/** The badge text for a state; $today is the weekday index (0 = Monday) the state was worked out for. */
	private function status_text( array $s, array $state, int $today ): string {
		$format = '24' === (string) ( $s['time_format'] ?? '12' ) ? '24' : '12';
		if ( $state[0] ) {
			$text = (string) ( $s['open_text'] ?? __( 'Open now · until {close}', 'uncoder' ) );
			return str_replace( '{close}', self::format( (int) $state[1], $format ), $text );
		}
		$text = (string) ( $s['closed_text'] ?? __( 'Closed · opens {day} {open}', 'uncoder' ) );
		if ( ! $state[2] ) {
			return trim( preg_replace( '/\s*·.*$/u', '', $text ) );
		}
		list( $offset, $open ) = $state[2];
		$day = 0 === $offset ? __( 'today', 'uncoder' ) : ( 1 === $offset ? __( 'tomorrow', 'uncoder' ) : self::day_name( ( $today + $offset ) % 7 ) );
		return str_replace( array( '{day}', '{open}' ), array( $day, self::format( $open, $format ) ), $text );
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$format = '24' === (string) ( $s['time_format'] ?? '12' ) ? '24' : '12';
		return array(
			'data-settings' => $this->json_attr(
				array(
					'tz'       => wp_timezone_string(),
					'rows'     => $this->rows( $s ),
					'open'     => (string) ( $s['open_text'] ?? __( 'Open now · until {close}', 'uncoder' ) ),
					'closed'   => (string) ( $s['closed_text'] ?? __( 'Closed · opens {day} {open}', 'uncoder' ) ),
					'format'   => $format,
					'today'    => __( 'today', 'uncoder' ),
					'tomorrow' => __( 'tomorrow', 'uncoder' ),
					'days'     => array_map( array( self::class, 'day_name' ), range( 0, 6 ) ),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows    = $this->rows( $s );
		$display = in_array( $s['display'] ?? 'status', array( 'status', 'list', 'both' ), true ) ? (string) $s['display'] : 'status';
		$format  = '24' === (string) ( $s['time_format'] ?? '12' ) ? '24' : '12';
		$now     = new \DateTimeImmutable( 'now', wp_timezone() );
		$day     = ( (int) $now->format( 'N' ) ) - 1;
		$min     = (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );
		$state   = self::state( $rows, $day, $min );

		$bold = ! array_key_exists( 'highlight_today', $s ) || ! empty( $s['highlight_today'] );
		echo '<div class="uncoder-opening-hours uncoder-opening-hours--' . esc_attr( $display ) . ( $bold ? ' uncoder-opening-hours--today' : '' ) . '">';
		if ( 'list' !== $display ) {
			$text = $this->status_text( $s, $state, $day );
			echo '<p class="uncoder-opening-hours__status ' . ( $state[0] ? 'is-open' : 'is-closed' ) . '"><span class="uncoder-opening-hours__dot" aria-hidden="true"></span><span class="uncoder-opening-hours__text">' . esc_html( trim( preg_replace( '/\s+/u', ' ', $text ) ) ) . '</span></p>';
		}
		if ( 'status' !== $display ) {
			$closed = (string) ( $s['closed_label'] ?? __( 'Closed', 'uncoder' ) );
			$range  = trim( (string) ( $s['range_word'] ?? '–' ) );
			$range  = '' === $range ? '–' : $range;
			$inline = 'inline' === ( $s['list_layout'] ?? 'columns' );
			$line   = (string) ( $s['line_format'] ?? '{days}: {hours}' );
			$items  = array();
			$seen   = array();
			foreach ( $rows as $r ) {
				$first   = $r['days'][0];
				$last    = $r['days'][ count( $r['days'] ) - 1 ];
				$items[] = array(
					'days'  => $r['days'],
					'label' => $first === $last ? self::day_name( $first ) : self::day_name( $first ) . ' ' . $range . ' ' . self::day_name( $last ),
					'hours' => self::format( $r['open'], $format ) . ' ' . $range . ' ' . self::format( $r['close'], $format ),
				);
				$seen    = array_merge( $seen, $r['days'] );
			}
			if ( ! empty( $s['show_closed'] ) || ! array_key_exists( 'show_closed', $s ) ) {
				foreach ( range( 0, 6 ) as $d ) {
					if ( ! in_array( $d, $seen, true ) ) {
						$items[] = array(
							'days'   => array( $d ),
							'label'  => self::day_name( $d ),
							'hours'  => $closed,
							'closed' => true,
						);
					}
				}
			}
			echo $inline ? '<ul class="uncoder-opening-hours__list is-inline">' : '<dl class="uncoder-opening-hours__list">';
			foreach ( $items as $item ) {
				$class = 'uncoder-opening-hours__row' . ( ! empty( $item['closed'] ) ? ' is-closed' : '' ) . ( in_array( $day, $item['days'], true ) ? ' is-today' : '' );
				$attrs = ' class="' . esc_attr( $class ) . '" data-days="' . esc_attr( implode( ',', $item['days'] ) ) . '"';
				if ( ! $inline ) {
					echo '<div' . $attrs . '><dt class="uncoder-opening-hours__day">' . esc_html( $item['label'] ) . '</dt><dd class="uncoder-opening-hours__time">' . esc_html( $item['hours'] ) . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is escaped above.
					continue;
				}
				// "{days}, {hours}": the words around them escaped, the day and the hours in their own spans for the colours.
				$html = '';
				foreach ( (array) preg_split( '/(\{days\}|\{hours\})/', $line, -1, PREG_SPLIT_DELIM_CAPTURE ) as $part ) {
					if ( '{days}' === $part ) {
						$html .= '<span class="uncoder-opening-hours__day">' . esc_html( $item['label'] ) . '</span>';
					} elseif ( '{hours}' === $part ) {
						$html .= '<span class="uncoder-opening-hours__time">' . esc_html( $item['hours'] ) . '</span>';
					} else {
						$html .= esc_html( (string) $part );
					}
				}
				echo '<li' . $attrs . '>' . $html . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			}
			echo $inline ? '</ul>' : '</dl>';
		}
		echo '</div>';
	}
}
