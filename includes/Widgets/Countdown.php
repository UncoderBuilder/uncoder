<?php
/**
 * Countdown widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Countdown to a fixed date or an evergreen per-visitor deadline. The server renders the current
 * remaining time so the timer is meaningful without JavaScript; the "countdown" module keeps it ticking.
 */
class Countdown extends Widget_Base {

	public const UNITS = array( 'days', 'hours', 'minutes', 'seconds' );

	public function name(): string {
		return 'countdown';
	}

	public function title(): string {
		return __( 'Countdown', 'uncoder' );
	}

	public function icon(): string {
		return 'timer';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'countdown', 'timer', 'deadline', 'launch', 'offer', 'clock' );
	}

	public function description(): string {
		return __( 'Countdown to a due date (site timezone) or an evergreen deadline per visitor (e.g. 24 hours after their first visit). On expiry it can hide the timer, show a message and/or redirect.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'countdown' );
	}

	public function preset(): array {
		return array(
			'due_date' => wp_date( 'Y-m-d H:00', time() + 7 * DAY_IN_SECONDS ),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content_timer', array( 'label' => __( 'Timer', 'uncoder' ) ) );
		$this->add_control(
			'mode',
			array(
				'type'    => 'select',
				'label'   => __( 'Type', 'uncoder' ),
				'default' => 'due',
				'options' => array(
					'due'       => __( 'Due date', 'uncoder' ),
					'evergreen' => __( 'Evergreen (per visitor)', 'uncoder' ),
				),
				'ai'      => 'due = everyone counts down to the same date; evergreen = each visitor gets their own deadline starting at their first visit (stored in their browser).',
			)
		);
		$this->add_control(
			'due_date',
			array(
				'type'        => 'date_time',
				'label'       => __( 'Due date', 'uncoder' ),
				'description' => __( 'In the site timezone (Settings → General).', 'uncoder' ),
				'default'     => '',
				'condition'   => array( 'mode' => 'due' ),
				'ai'          => 'Local site time as "YYYY-MM-DD HH:MM", e.g. "2026-11-27 09:00".',
			)
		);
		$this->add_control(
			'evergreen_hours',
			array(
				'type'      => 'number',
				'label'     => __( 'Hours', 'uncoder' ),
				'default'   => 24,
				'min'       => 0,
				'max'       => 8760,
				'condition' => array( 'mode' => 'evergreen' ),
			)
		);
		$this->add_control(
			'evergreen_minutes',
			array(
				'type'      => 'number',
				'label'     => __( 'Minutes', 'uncoder' ),
				'default'   => 0,
				'min'       => 0,
				'max'       => 59,
				'condition' => array( 'mode' => 'evergreen' ),
			)
		);
		$this->add_control(
			'evergreen_restart',
			array(
				'type'        => 'switch',
				'label'       => __( 'Restart when finished', 'uncoder' ),
				'description' => __( 'Starts a new period for the visitor instead of expiring.', 'uncoder' ),
				'default'     => false,
				'condition'   => array( 'mode' => 'evergreen' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_units', array( 'label' => __( 'Units & labels', 'uncoder' ) ) );
		$this->add_control(
			'view',
			array(
				'type'    => 'select',
				'label'   => __( 'View', 'uncoder' ),
				'default' => 'boxes',
				'options' => array(
					'boxes'  => __( 'Boxes', 'uncoder' ),
					'plain'  => __( 'Plain (no boxes)', 'uncoder' ),
					'inline' => __( 'Inline (label beside digits)', 'uncoder' ),
				),
			)
		);
		foreach ( self::UNITS as $unit ) {
			$this->add_control(
				'show_' . $unit,
				array(
					'type'    => 'switch',
					'label'   => $this->show_label( $unit ),
					'default' => true,
				)
			);
		}
		$this->add_control(
			'show_labels',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show labels', 'uncoder' ),
				'default' => true,
			)
		);
		foreach ( self::UNITS as $unit ) {
			$this->add_control(
				'label_' . $unit,
				array(
					'type'      => 'text',
					/* translators: %s: time unit (Days, Hours…). */
					'label'     => sprintf( __( '%s label', 'uncoder' ), $this->unit_title( $unit ) ),
					'default'   => $this->unit_title( $unit ),
					'condition' => array(
						'show_labels'   => true,
						'show_' . $unit => true,
					),
				)
			);
		}
		$this->add_control(
			'separator',
			array(
				'type'        => 'text',
				'label'       => __( 'Separator', 'uncoder' ),
				'placeholder' => ':',
				'description' => __( 'Character shown between units, e.g. ":". Leave empty for none.', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_expire', array( 'label' => __( 'After expiry', 'uncoder' ) ) );
		$this->add_control(
			'expire_actions',
			array(
				'type'    => 'multiselect',
				'label'   => __( 'Actions', 'uncoder' ),
				'default' => array( 'message' ),
				'options' => array(
					'hide'     => __( 'Hide the timer', 'uncoder' ),
					'message'  => __( 'Show a message', 'uncoder' ),
					'redirect' => __( 'Redirect', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'expire_message',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Message', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 2,
				'default' => __( 'This offer has ended.', 'uncoder' ),
				'inline'  => true,
			)
		);
		$this->add_control(
			'expire_redirect',
			array(
				'type'        => 'url',
				'label'       => __( 'Redirect URL', 'uncoder' ),
				'description' => __( 'Used when "Redirect" is selected. Never redirects inside the editor.', 'uncoder' ),
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_layout', array( 'label' => __( 'Layout', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'left'    => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center'  => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'   => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
					'stretch' => array( 'label' => __( 'Full width', 'uncoder' ), 'icon' => 'align-justify' ),
				),
				'selectors_dictionary' => array(
					'left'    => '--uncoder-cd-justify:flex-start;--uncoder-cd-text:start;--uncoder-cd-grow:0',
					'center'  => '--uncoder-cd-justify:center;--uncoder-cd-text:center;--uncoder-cd-grow:0',
					'right'   => '--uncoder-cd-justify:flex-end;--uncoder-cd-text:end;--uncoder-cd-grow:0',
					'stretch' => '--uncoder-cd-justify:stretch;--uncoder-cd-text:center;--uncoder-cd-grow:1',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between units', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-countdown__units' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'box_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Unit min width', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 40, 'max' => 240 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-countdown__unit' => 'min-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'box_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Unit padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-countdown__unit' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'box_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Unit background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-countdown__unit' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'box_border', array( 'type' => 'border', 'label' => __( 'Unit border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-countdown__unit' ) );
		$this->add_responsive_control(
			'box_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Unit radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-countdown__unit' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'box_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Unit shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-countdown__unit' ) );
		$this->end_section();

		$this->start_section( 'style_digits', array( 'label' => __( 'Digits', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'digits_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-countdown__digits' ) );
		$this->add_control(
			'digits_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-countdown__digits' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_labels', array( 'label' => __( 'Labels', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'show_labels' => true ) ) );
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-countdown__label' ) );
		$this->add_control(
			'label_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-countdown__label' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'label_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-countdown__unit' => 'gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_separator', array( 'label' => __( 'Separator', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'separator!' => '' ) ) );
		$this->add_control(
			'separator_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-countdown__separator' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'separator_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-countdown__separator' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_message', array( 'label' => __( 'Expiry message', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'message_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-countdown__message' ) );
		$this->add_control(
			'message_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-countdown__message' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'message_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing above', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-countdown__message' => 'margin-top: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	private function show_label( string $unit ): string {
		switch ( $unit ) {
			case 'days':
				return __( 'Show days', 'uncoder' );
			case 'hours':
				return __( 'Show hours', 'uncoder' );
			case 'minutes':
				return __( 'Show minutes', 'uncoder' );
			default:
				return __( 'Show seconds', 'uncoder' );
		}
	}

	private function unit_title( string $unit ): string {
		switch ( $unit ) {
			case 'days':
				return __( 'Days', 'uncoder' );
			case 'hours':
				return __( 'Hours', 'uncoder' );
			case 'minutes':
				return __( 'Minutes', 'uncoder' );
			default:
				return __( 'Seconds', 'uncoder' );
		}
	}

	/**
	 * Unix timestamp of the due date (site timezone unless the value carries its own), 0 when unset/invalid.
	 */
	public static function due_timestamp( $value ): int {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return 0;
		}
		try {
			$date = new \DateTimeImmutable( str_replace( 'T', ' ', $value ), wp_timezone() );
		} catch ( \Exception $e ) {
			return 0;
		}
		return $date->getTimestamp();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 * @return string[]
	 */
	private function units( array $s ): array {
		$units = array();
		foreach ( self::UNITS as $unit ) {
			if ( ! array_key_exists( 'show_' . $unit, $s ) || ! empty( $s[ 'show_' . $unit ] ) ) {
				$units[] = $unit;
			}
		}
		return $units ? $units : array( 'seconds' );
	}

	/**
	 * Splits seconds into the visible units; the largest visible unit absorbs hidden larger ones.
	 *
	 * @param string[] $units Visible units.
	 * @return array<string,int>
	 */
	public static function split( int $seconds, array $units ): array {
		$size = array(
			'days'    => 86400,
			'hours'   => 3600,
			'minutes' => 60,
			'seconds' => 1,
		);
		$out  = array();
		foreach ( self::UNITS as $unit ) {
			if ( in_array( $unit, $units, true ) ) {
				$out[ $unit ] = intdiv( $seconds, $size[ $unit ] );
				$seconds     -= $out[ $unit ] * $size[ $unit ];
			}
		}
		return $out;
	}

	/**
	 * Plural templates for the screen-reader sentence (shared with the JS module).
	 *
	 * @return array<string, mixed>
	 */
	private function sr_strings(): array {
		return array(
			/* translators: %d: number of days. */
			'days'      => array( __( '%d day', 'uncoder' ), __( '%d days', 'uncoder' ) ),
			/* translators: %d: number of hours. */
			'hours'     => array( __( '%d hour', 'uncoder' ), __( '%d hours', 'uncoder' ) ),
			/* translators: %d: number of minutes. */
			'minutes'   => array( __( '%d minute', 'uncoder' ), __( '%d minutes', 'uncoder' ) ),
			/* translators: %d: number of seconds. */
			'seconds'   => array( __( '%d second', 'uncoder' ), __( '%d seconds', 'uncoder' ) ),
			/* translators: %s: list of durations, e.g. "2 days, 5 hours". */
			'remaining' => __( '%s remaining', 'uncoder' ),
			'expired'   => __( 'The countdown has ended.', 'uncoder' ),
		);
	}

	/**
	 * @param array<string,int> $parts Unit values.
	 */
	private function sr_text( array $parts, bool $expired ): string {
		if ( $expired ) {
			return __( 'The countdown has ended.', 'uncoder' );
		}
		$spoken = array_keys( $parts );
		if ( count( $spoken ) > 1 ) {
			$spoken = array_diff( $spoken, array( 'seconds' ) );
		}
		$chunks = array();
		foreach ( $spoken as $unit ) {
			$n = $parts[ $unit ];
			switch ( $unit ) {
				case 'days':
					/* translators: %d: number of days. */
					$chunks[] = sprintf( _n( '%d day', '%d days', $n, 'uncoder' ), $n );
					break;
				case 'hours':
					/* translators: %d: number of hours. */
					$chunks[] = sprintf( _n( '%d hour', '%d hours', $n, 'uncoder' ), $n );
					break;
				case 'minutes':
					/* translators: %d: number of minutes. */
					$chunks[] = sprintf( _n( '%d minute', '%d minutes', $n, 'uncoder' ), $n );
					break;
				default:
					/* translators: %d: number of seconds. */
					$chunks[] = sprintf( _n( '%d second', '%d seconds', $n, 'uncoder' ), $n );
			}
		}
		/* translators: %s: list of durations, e.g. "2 days, 5 hours". */
		return sprintf( __( '%s remaining', 'uncoder' ), implode( ', ', $chunks ) );
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function duration( array $s ): int {
		return max( 0, (int) ( $s['evergreen_hours'] ?? 0 ) ) * HOUR_IN_SECONDS + max( 0, min( 59, (int) ( $s['evergreen_minutes'] ?? 0 ) ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 * @return string[]
	 */
	private function actions( array $s ): array {
		$actions = is_array( $s['expire_actions'] ?? null ) ? $s['expire_actions'] : array();
		return array_values( array_intersect( array( 'hide', 'message', 'redirect' ), array_map( 'strval', $actions ) ) );
	}

	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$mode     = 'evergreen' === ( $s['mode'] ?? 'due' ) ? 'evergreen' : 'due';
		$redirect = is_array( $s['expire_redirect'] ?? null ) ? (string) ( $s['expire_redirect']['url'] ?? '' ) : '';
		return array(
			'data-settings' => $this->json_attr(
				array(
					'mode'     => $mode,
					'due'      => 'due' === $mode ? self::due_timestamp( $s['due_date'] ?? '' ) : 0,
					'duration' => $this->duration( $s ),
					'restart'  => ! empty( $s['evergreen_restart'] ),
					'key'      => 'uncoder-countdown-' . $ctx->doc_id . '-' . $ctx->element_id,
					'units'    => $this->units( $s ),
					'actions'  => $this->actions( $s ),
					'redirect' => '' !== $redirect ? esc_url_raw( $redirect ) : '',
					'sr'       => $this->sr_strings(),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$mode  = 'evergreen' === ( $s['mode'] ?? 'due' ) ? 'evergreen' : 'due';
		$units = $this->units( $s );
		if ( 'due' === $mode ) {
			$due = self::due_timestamp( $s['due_date'] ?? '' );
			if ( ! $due ) {
				if ( $ctx->editor ) {
					echo '<p class="uncoder-countdown__empty">' . esc_html__( 'Set a due date to start the countdown.', 'uncoder' ) . '</p>';
				}
				return;
			}
			$remaining = max( 0, $due - time() );
		} else {
			$remaining = $this->duration( $s );
		}
		$expired = 'due' === $mode && 0 === $remaining;
		$actions = $this->actions( $s );
		$parts   = self::split( $remaining, $units );
		$view    = in_array( $s['view'] ?? 'boxes', array( 'boxes', 'plain', 'inline' ), true ) ? $s['view'] : 'boxes';
		$labels  = ! array_key_exists( 'show_labels', $s ) || ! empty( $s['show_labels'] );
		$sep     = trim( (string) ( $s['separator'] ?? '' ) );

		$classes = array( 'uncoder-countdown', 'uncoder-countdown--' . $view );
		if ( $expired ) {
			$classes[] = 'uncoder-countdown--expired';
		}
		$hide_units   = $expired && in_array( 'hide', $actions, true ) && ! $ctx->editor;
		$show_message = $expired && in_array( 'message', $actions, true );

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" role="timer" aria-live="off" aria-atomic="true">';
		echo '<span class="uncoder-sr-only uncoder-countdown__sr">' . esc_html( $this->sr_text( $parts, $expired ) ) . '</span>';
		echo '<div class="uncoder-countdown__units" aria-hidden="true"' . ( $hide_units ? ' hidden' : '' ) . '>';
		$i = 0;
		foreach ( $parts as $unit => $value ) {
			if ( $i > 0 && '' !== $sep ) {
				echo '<span class="uncoder-countdown__separator">' . esc_html( $sep ) . '</span>';
			}
			echo '<div class="uncoder-countdown__unit uncoder-countdown__unit--' . esc_attr( $unit ) . '">';
			echo '<span class="uncoder-countdown__digits" data-unit="' . esc_attr( $unit ) . '">' . esc_html( str_pad( (string) $value, 2, '0', STR_PAD_LEFT ) ) . '</span>';
			if ( $labels ) {
				$label = trim( (string) ( $s[ 'label_' . $unit ] ?? $this->unit_title( $unit ) ) );
				if ( '' !== $label ) {
					echo '<span class="uncoder-countdown__label">' . esc_html( $label ) . '</span>';
				}
			}
			echo '</div>';
			++$i;
		}
		echo '</div>';

		$message = $this->inline_html( $s['expire_message'] ?? '' );
		if ( in_array( 'message', $actions, true ) && '' !== trim( wp_strip_all_tags( $message ) ) ) {
			echo '<p class="uncoder-countdown__message"' . ( $show_message ? '' : ' hidden' ) . $ctx->inline( 'expire_message' ) . '>' . $message . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML; inline() is escaped.
		}
		echo '</div>';
	}
}
