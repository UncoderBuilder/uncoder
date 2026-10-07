<?php
/**
 * Popups: templates of type "popup" rendered in the footer when their conditions match.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Popups;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Theme\Conditions;
use Uncoder\Builder\Theme\Theme_Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Popup behaviour lives in the template settings meta; see sanitize() for the shape.
 */
final class Popups {

	/** @var int[] */
	private array $matched = array();

	private bool $resolved = false;

	public function register(): void {
		add_action( 'uncoder_wb/frontend/enqueue', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 20 );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'layout'           => 'modal',
			'position'         => 'center',
			'width'            => array( 'size' => 560, 'unit' => 'px' ),
			'overlay'          => true,
			'overlay_color'    => 'rgba(15, 23, 42, 0.55)',
			'background'       => '#ffffff',
			'radius'           => 16,
			'padding'          => 32,
			'close_button'     => true,
			'close_on_overlay' => true,
			'close_on_esc'     => true,
			'animation'        => 'zoom',
			'triggers'         => array(
				'load'        => array( 'enabled' => true, 'delay' => 3 ),
				'scroll'      => array( 'enabled' => false, 'percent' => 50 ),
				'scroll_to'   => array( 'enabled' => false, 'selector' => '' ),
				'click'       => array( 'enabled' => false, 'selector' => '' ),
				'exit_intent' => array( 'enabled' => false ),
				'inactivity'  => array( 'enabled' => false, 'seconds' => 30 ),
				'page_views'  => array( 'enabled' => false, 'count' => 3 ),
			),
			'frequency'        => array( 'times' => 1, 'period' => 'day' ),
			'devices'          => array( 'desktop', 'tablet', 'mobile' ),
			'visitors'         => 'all',
			'avoid_multiple'   => true,
			// Who sees automatic triggers (links that open the popup always work). Checked in the browser, so
			// cached pages behave too.
			'rules'            => array(
				'referrer'       => '', // '' | search | external | internal | direct | contains.
				'referrer_value' => '',
				'url_param'      => '', // "name" or "name=value", on this page or the page the visit started on.
				'sessions'       => 0, // From the Nth visit (0 = any).
				'schedule'       => array(
					'enabled'  => false,
					'from'     => '',
					'until'    => '',
					'timezone' => 'site', // site | visitor.
				),
				'browsers'       => array(),
			),
		);
	}

	public const BROWSERS = array( 'chrome', 'safari', 'firefox', 'edge', 'opera', 'samsung' );

	/**
	 * Normalizes popup settings (lenient: accepts partial input from the admin UI and AI clients).
	 *
	 * @param mixed    $raw    Raw.
	 * @param string[] $errors Errors.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $raw, array &$errors = array(), array $current = array() ): array {
		$d   = self::defaults();
		$in  = is_array( $raw ) ? $raw : array();
		$cur = array_replace_recursive( $d, $current );
		// Lists replace, they do not merge by index (a removed device must stay removed).
		if ( isset( $current['devices'] ) && is_array( $current['devices'] ) ) {
			$cur['devices'] = array_values( $current['devices'] );
		}
		$out = $cur;

		$enum = static function ( string $key, array $allowed ) use ( $in, &$out, &$errors ) {
			if ( ! isset( $in[ $key ] ) ) {
				return;
			}
			$v = (string) $in[ $key ];
			if ( in_array( $v, $allowed, true ) ) {
				$out[ $key ] = $v;
			} else {
				$errors[] = sprintf( '%s must be one of: %s.', $key, implode( ', ', $allowed ) );
			}
		};
		$enum( 'layout', array( 'modal', 'slide_in', 'bar', 'fullscreen' ) );
		$enum( 'position', array( 'center', 'top', 'bottom', 'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right' ) );
		$enum( 'animation', array( 'none', 'fade', 'zoom', 'slide-up', 'slide-down', 'slide-left', 'slide-right' ) );
		$enum( 'visitors', array( 'all', 'logged_in', 'logged_out' ) );
		foreach ( array( 'overlay', 'close_button', 'close_on_overlay', 'close_on_esc', 'avoid_multiple' ) as $bool ) {
			if ( isset( $in[ $bool ] ) ) {
				$out[ $bool ] = (bool) $in[ $bool ];
			}
		}
		foreach ( array( 'radius', 'padding' ) as $num ) {
			if ( isset( $in[ $num ] ) ) {
				$out[ $num ] = max( 0, min( 200, (int) $in[ $num ] ) );
			}
		}
		if ( isset( $in['width'] ) ) {
			$w = Utils::parse_size( is_array( $in['width'] ) ? ( $in['width']['size'] ?? '' ) . ( $in['width']['unit'] ?? 'px' ) : $in['width'] );
			if ( $w && in_array( $w['unit'], array( 'px', '%', 'vw', 'rem' ), true ) ) {
				$out['width'] = $w;
			}
		}
		foreach ( array( 'overlay_color', 'background' ) as $color ) {
			if ( isset( $in[ $color ] ) ) {
				$c = Utils::sanitize_color( (string) $in[ $color ] );
				if ( '' !== $c ) {
					$out[ $color ] = $c;
				}
			}
		}
		if ( isset( $in['triggers'] ) && is_array( $in['triggers'] ) ) {
			foreach ( $in['triggers'] as $name => $t ) {
				if ( ! isset( $d['triggers'][ $name ] ) ) {
					$errors[] = sprintf( 'Unknown trigger "%s". Use: %s.', $name, implode( ', ', array_keys( $d['triggers'] ) ) );
					continue;
				}
				$t = is_array( $t ) ? $t : array( 'enabled' => (bool) $t );
				$o = $out['triggers'][ $name ];
				if ( isset( $t['enabled'] ) ) {
					$o['enabled'] = (bool) $t['enabled'];
				}
				foreach ( array( 'delay' => array( 0, 600 ), 'percent' => array( 1, 100 ), 'seconds' => array( 3, 3600 ), 'count' => array( 1, 100 ) ) as $k => $range ) {
					if ( isset( $t[ $k ] ) && array_key_exists( $k, $o ) ) {
						$o[ $k ] = max( $range[0], min( $range[1], (float) $t[ $k ] ) );
					}
				}
				if ( isset( $t['selector'] ) && array_key_exists( 'selector', $o ) ) {
					$o['selector'] = preg_replace( '/[^A-Za-z0-9_\-#.,\[\]=:"\' >+~*()]/', '', (string) $t['selector'] );
				}
				$out['triggers'][ $name ] = $o;
			}
		}
		if ( isset( $in['frequency'] ) && is_array( $in['frequency'] ) ) {
			if ( isset( $in['frequency']['times'] ) ) {
				$out['frequency']['times'] = max( 0, min( 100, (int) $in['frequency']['times'] ) );
			}
			if ( isset( $in['frequency']['period'] ) && in_array( $in['frequency']['period'], array( 'session', 'day', 'week', 'month', 'forever' ), true ) ) {
				$out['frequency']['period'] = $in['frequency']['period'];
			}
		}
		if ( isset( $in['devices'] ) && is_array( $in['devices'] ) ) {
			$out['devices'] = array_values( array_intersect( array( 'desktop', 'tablet', 'mobile' ), $in['devices'] ) );
		}
		if ( isset( $current['rules']['browsers'] ) && is_array( $current['rules']['browsers'] ) ) {
			$out['rules']['browsers'] = array_values( $current['rules']['browsers'] );
		}
		if ( isset( $in['rules'] ) && is_array( $in['rules'] ) ) {
			$r = $in['rules'];
			if ( isset( $r['referrer'] ) ) {
				if ( in_array( (string) $r['referrer'], array( '', 'search', 'external', 'internal', 'direct', 'contains' ), true ) ) {
					$out['rules']['referrer'] = (string) $r['referrer'];
				} else {
					$errors[] = 'rules.referrer must be one of: search, external, internal, direct, contains (or empty).';
				}
			}
			if ( isset( $r['referrer_value'] ) ) {
				$out['rules']['referrer_value'] = mb_substr( sanitize_text_field( (string) $r['referrer_value'] ), 0, 200 );
			}
			if ( isset( $r['url_param'] ) ) {
				$out['rules']['url_param'] = mb_substr( (string) preg_replace( '/[^A-Za-z0-9_\-\[\]=.%+]/', '', (string) $r['url_param'] ), 0, 200 );
			}
			if ( isset( $r['sessions'] ) ) {
				$out['rules']['sessions'] = max( 0, min( 1000, (int) $r['sessions'] ) );
			}
			if ( isset( $r['schedule'] ) && is_array( $r['schedule'] ) ) {
				$sc = $r['schedule'];
				if ( isset( $sc['enabled'] ) ) {
					$out['rules']['schedule']['enabled'] = (bool) $sc['enabled'];
				}
				foreach ( array( 'from', 'until' ) as $k ) {
					if ( isset( $sc[ $k ] ) ) {
						$v = str_replace( ' ', 'T', trim( (string) $sc[ $k ] ) );
						$out['rules']['schedule'][ $k ] = preg_match( '/^\d{4}-\d{2}-\d{2}(T([01]\d|2[0-3]):[0-5]\d)?$/', $v ) ? $v : '';
					}
				}
				if ( isset( $sc['timezone'] ) && in_array( $sc['timezone'], array( 'site', 'visitor' ), true ) ) {
					$out['rules']['schedule']['timezone'] = $sc['timezone'];
				}
			}
			if ( isset( $r['browsers'] ) && is_array( $r['browsers'] ) ) {
				$out['rules']['browsers'] = array_values( array_intersect( self::BROWSERS, array_map( 'strval', $r['browsers'] ) ) );
			}
		}
		return $out;
	}

	/**
	 * Schedule as the front end reads it: site time zone → UTC milliseconds; visitor time zone → local strings.
	 *
	 * @param array<string,mixed> $schedule Schedule settings.
	 * @return array<string,mixed>|null Null when no schedule is set.
	 */
	private static function schedule_data( array $schedule ): ?array {
		if ( empty( $schedule['enabled'] ) || ( '' === (string) $schedule['from'] && '' === (string) $schedule['until'] ) ) {
			return null;
		}
		$edge = static function ( string $value, bool $end ) {
			if ( '' === $value ) {
				return null;
			}
			// A date alone covers the whole day.
			return strlen( $value ) === 10 ? $value . ( $end ? 'T23:59:59' : 'T00:00:00' ) : $value . ( $end ? ':59' : ':00' );
		};
		$from  = $edge( (string) $schedule['from'], false );
		$until = $edge( (string) $schedule['until'], true );
		if ( 'visitor' === $schedule['timezone'] ) {
			return array( 'local' => true, 'from' => $from, 'until' => $until );
		}
		$ms = static function ( ?string $value ) {
			if ( null === $value ) {
				return null;
			}
			$d = date_create( $value, wp_timezone() );
			return $d ? $d->getTimestamp() * 1000 : null;
		};
		return array( 'local' => false, 'from' => $ms( $from ), 'until' => $ms( $until ) );
	}

	/**
	 * @return int[]
	 */
	private function matched(): array {
		if ( $this->resolved ) {
			return $this->matched;
		}
		$this->resolved = true;
		$builder        = Theme_Builder::instance();
		if ( ! $builder || is_admin() || is_feed() || is_embed() ) {
			return array();
		}
		$exclude = (array) apply_filters( 'uncoder_wb/theme/exclude_template', array() );
		foreach ( $builder->index()['popup'] ?? array() as $entry ) {
			$id = (int) $entry['id'];
			if ( in_array( $id, $exclude, true ) || \Uncoder\Builder\Site\Multilingual::has_local_version( $id ) || null === Conditions::match( (array) $entry['conditions'] ) ) {
				continue;
			}
			$settings = self::settings( $id );
			// A schedule that is over (site time zone): nothing to print.
			$schedule = self::schedule_data( (array) $settings['rules']['schedule'] );
			if ( $schedule && ! $schedule['local'] && null !== $schedule['until'] && $schedule['until'] < time() * 1000 ) {
				continue;
			}
			if ( 'logged_in' === $settings['visitors'] && ! is_user_logged_in() ) {
				continue;
			}
			if ( 'logged_out' === $settings['visitors'] && is_user_logged_in() ) {
				continue;
			}
			$this->matched[] = $id;
		}
		/**
		 * Filters the popups printed on this request (template previews show only the previewed one).
		 *
		 * @param int[] $matched Popup template ids.
		 */
		$this->matched = array_values( array_map( 'intval', (array) apply_filters( 'uncoder_wb/popups/matched', $this->matched ) ) );
		return $this->matched;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function settings( int $id ): array {
		$raw    = get_post_meta( $id, Utils::META_TPL, true );
		$errors = array();
		return self::sanitize( array(), $errors, is_array( $raw ) ? $raw : array() );
	}

	public function enqueue(): void {
		$ids = $this->matched();
		if ( ! $ids ) {
			return;
		}
		Assets::enqueue_base();
		Assets::enqueue_module( 'popup' );
		Assets::enqueue_widget_style( 'popup' );
		foreach ( $ids as $id ) {
			$doc = Plugin::instance()->documents()->get( $id );
			if ( $doc ) {
				Assets::enqueue_document( $doc );
			}
		}
	}

	public function render(): void {
		foreach ( $this->matched() as $id ) {
			$doc = Plugin::instance()->documents()->get( $id );
			if ( ! $doc ) {
				continue;
			}
			/**
			 * Filters a popup's settings as printed (template previews open it right away, every time).
			 *
			 * @param array<string,mixed> $s  Settings.
			 * @param int                 $id Popup template id.
			 */
			$s     = (array) apply_filters( 'uncoder_wb/popups/settings', self::settings( $id ), $id );
			$style = sprintf(
				'--uncoder-popup-width:%s;--uncoder-popup-bg:%s;--uncoder-popup-radius:%dpx;--uncoder-popup-pad:%dpx;--uncoder-popup-overlay:%s',
				esc_attr( $s['width']['size'] . $s['width']['unit'] ),
				esc_attr( $s['background'] ),
				(int) $s['radius'],
				(int) $s['padding'],
				esc_attr( $s['overlay_color'] )
			);
			$data  = array(
				'id'        => $id,
				'triggers'  => $s['triggers'],
				'frequency' => $s['frequency'],
				'devices'   => $s['devices'],
				'overlay'   => $s['overlay'],
				'closeOverlay' => $s['close_on_overlay'],
				'closeEsc'  => $s['close_on_esc'],
				'avoid'     => $s['avoid_multiple'],
				'rules'     => array(
					'referrer'      => $s['rules']['referrer'],
					'referrerValue' => $s['rules']['referrer_value'],
					'param'         => $s['rules']['url_param'],
					'sessions'      => $s['rules']['sessions'],
					'schedule'      => self::schedule_data( (array) $s['rules']['schedule'] ),
					'browsers'      => $s['rules']['browsers'],
					'host'          => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				),
			);
			printf(
				'<div class="uncoder-popup uncoder-popup--%1$s uncoder-popup--%2$s uncoder-popup--anim-%3$s" id="uncoder-popup-%4$d" data-uncoder-js="popup" data-settings="%5$s" style="%6$s" hidden>',
				esc_attr( str_replace( '_', '-', $s['layout'] ) ),
				esc_attr( $s['position'] ),
				esc_attr( $s['animation'] ),
				(int) $id,
				esc_attr( (string) wp_json_encode( $data ) ),
				$style // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
			);
			if ( $s['overlay'] ) {
				echo '<div class="uncoder-popup__overlay" data-uncoder-popup-overlay></div>';
			}
			echo '<div class="uncoder-popup__dialog" role="dialog" aria-modal="' . ( $s['overlay'] ? 'true' : 'false' ) . '" aria-label="' . esc_attr( get_the_title( $id ) ) . '" tabindex="-1">';
			if ( $s['close_button'] ) {
				echo '<button type="button" class="uncoder-popup__close" data-uncoder-popup-close aria-label="' . esc_attr__( 'Close', 'uncoder' ) . '"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>';
			}
			echo $doc->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by the escaping renderer.
			echo '</div></div>';
		}
	}
}
