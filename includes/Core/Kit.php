<?php
/**
 * Design System: the global design system (colors, fonts, text styles, layout, buttons, forms, theme style).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Controls\Groups\Typography;
use Uncoder\Builder\Core\Css\Generator;
use Uncoder\Builder\Core\Css\Rules;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in the `uncoder_wb_kit` option; compiled to uploads/uncoder/css/kit.css.
 */
final class Kit {

	public const OPTION        = 'uncoder_wb_kit';
	public const SNAPSHOTS     = 'uncoder_wb_kit_snapshots';
	public const VERSION_OPT   = 'uncoder_wb_kit_version';
	/** Plugin + kit version the kit.css file was written with (rebuilt after plugin updates). */
	public const CSS_BUILD     = 'uncoder_wb_kit_css_build';
	public const MAX_SNAPSHOTS = 20;

	/** @var array<string,mixed>|null */
	private ?array $data = null;

	/**
	 * @return array<string,mixed>
	 */
	/** Groups of design variables (which fields offer them: spacing → padding / margin / gap, radius → corners…). */
	public const VARIABLE_GROUPS = array( 'spacing', 'size', 'radius', 'other' );

	public static function defaults(): array {
		$px  = static fn( $n ) => array( 'size' => $n, 'unit' => 'px' );
		$em  = static fn( $n ) => array( 'size' => $n, 'unit' => '' );
		$typ = static function ( $family, $d, $t, $m, $weight, $lh, $ls = null, $extra = array() ) use ( $px, $em ) {
			$v = array(
				'family'      => $family,
				'size'        => $px( $d ),
				'size_tablet' => $px( $t ),
				'size_mobile' => $px( $m ),
				'weight'      => (string) $weight,
				'line_height' => $em( $lh ),
			);
			if ( null !== $ls ) {
				$v['letter_spacing'] = $px( $ls );
			}
			return array_merge( $v, $extra );
		};

		return array(
			// Design variables: named sizes (--uncoder-v-{id}) for spacing, sizes, radii… (Styles → Variables).
			'variables'   => array(),
			'colors'      => array(
				array( 'id' => 'primary', 'name' => 'Primary', 'value' => '#2b59ff' ),
				array( 'id' => 'secondary', 'name' => 'Secondary', 'value' => '#0b1b3f' ),
				array( 'id' => 'accent', 'name' => 'Accent', 'value' => '#ff7a45' ),
				array( 'id' => 'heading', 'name' => 'Headings', 'value' => '#0f172a' ),
				array( 'id' => 'text', 'name' => 'Text', 'value' => '#374151' ),
				array( 'id' => 'muted', 'name' => 'Muted text', 'value' => '#6b7280' ),
				array( 'id' => 'surface', 'name' => 'Surface', 'value' => '#f5f7fb' ),
				array( 'id' => 'border', 'name' => 'Border', 'value' => '#e5e7eb' ),
				array( 'id' => 'white', 'name' => 'White', 'value' => '#ffffff' ),
				array( 'id' => 'black', 'name' => 'Black', 'value' => '#0b0b0f' ),
			),
			'fonts'       => array(
				array( 'id' => 'heading', 'name' => 'Headings', 'family' => '' ),
				array( 'id' => 'body', 'name' => 'Body', 'family' => '' ),
			),
			'typography'  => array(
				array( 'id' => 'display', 'name' => 'Display', 'value' => $typ( 'var(--uncoder-f-heading)', 72, 56, 42, 700, 1.05, -1.5 ) ),
				array( 'id' => 'h1', 'name' => 'Heading 1', 'value' => $typ( 'var(--uncoder-f-heading)', 56, 44, 34, 700, 1.1, -1 ) ),
				array( 'id' => 'h2', 'name' => 'Heading 2', 'value' => $typ( 'var(--uncoder-f-heading)', 42, 34, 28, 700, 1.15, -0.5 ) ),
				array( 'id' => 'h3', 'name' => 'Heading 3', 'value' => $typ( 'var(--uncoder-f-heading)', 30, 26, 23, 600, 1.25 ) ),
				array( 'id' => 'h4', 'name' => 'Heading 4', 'value' => $typ( 'var(--uncoder-f-heading)', 23, 21, 19, 600, 1.3 ) ),
				array( 'id' => 'h5', 'name' => 'Heading 5', 'value' => $typ( 'var(--uncoder-f-heading)', 19, 18, 17, 600, 1.4 ) ),
				array( 'id' => 'h6', 'name' => 'Heading 6', 'value' => $typ( 'var(--uncoder-f-heading)', 16, 16, 15, 600, 1.4 ) ),
				array( 'id' => 'lead', 'name' => 'Lead paragraph', 'value' => $typ( 'var(--uncoder-f-body)', 20, 19, 18, 400, 1.6 ) ),
				array( 'id' => 'body', 'name' => 'Body', 'value' => $typ( 'var(--uncoder-f-body)', 17, 16, 16, 400, 1.65 ) ),
				array( 'id' => 'small', 'name' => 'Small', 'value' => $typ( 'var(--uncoder-f-body)', 14, 14, 14, 400, 1.5 ) ),
				array( 'id' => 'eyebrow', 'name' => 'Eyebrow', 'value' => $typ( 'var(--uncoder-f-body)', 13, 13, 12, 600, 1.4, 1.5, array( 'transform' => 'uppercase' ) ) ),
				array( 'id' => 'button', 'name' => 'Button', 'value' => $typ( 'var(--uncoder-f-body)', 15, 15, 15, 600, 1.2 ) ),
			),
			'layout'      => array(
				'container_width'   => $px( 1200 ),
				'gutter'            => $px( 24 ),
				'gutter_mobile'     => $px( 16 ),
				'gap'               => $px( 20 ),
				'section_space'     => $px( 96 ),
				'section_space_tablet' => $px( 72 ),
				'section_space_mobile' => $px( 56 ),
				'scroll_offset'     => $px( 16 ),
			),
			'buttons'     => array(
				'typography'       => array( 'preset' => 'button' ),
				'color'            => '#ffffff',
				'background'       => 'var(--uncoder-c-primary)',
				'hover_color'      => '#ffffff',
				'hover_background' => 'color-mix(in srgb, var(--uncoder-c-primary) 86%, #000)',
				'padding'          => array( 'top' => 14, 'right' => 26, 'bottom' => 14, 'left' => 26, 'unit' => 'px', 'linked' => false ),
				'radius'           => array( 'top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'unit' => 'px', 'linked' => true ),
				'hover_effect'     => '',
			),
			'forms'       => array(
				'label_color'        => 'var(--uncoder-c-heading)',
				'field_color'        => 'var(--uncoder-c-text)',
				'field_background'   => '#ffffff',
				'field_border_color' => 'var(--uncoder-c-border)',
				'focus_border_color' => 'var(--uncoder-c-primary)',
				'field_radius'       => array( 'top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'unit' => 'px', 'linked' => true ),
				'field_padding'      => array( 'top' => 12, 'right' => 14, 'bottom' => 12, 'left' => 14, 'unit' => 'px', 'linked' => false ),
			),
			'theme'       => array(
				'enabled'          => true,
				'body_color'       => 'var(--uncoder-c-text)',
				'heading_color'    => 'var(--uncoder-c-heading)',
				'link_color'       => 'var(--uncoder-c-primary)',
				'link_hover_color' => '',
				'background'       => '',
				'background_image' => array( 'id' => 0, 'url' => '' ),
				'background_size'  => 'auto',
				'optical_sizing'   => '',
			),
			'breakpoints' => array(),
			'classes'     => array(),
			'settings'    => array(
				'font_delivery'        => 'google',
				'smooth_scroll'        => true,
				'back_to_top'          => false,
				'back_to_top_position' => 'right',
				'color_scheme'         => 'light',
				'preloader'            => '',
				'preloader_once'       => true,
				'page_transition'      => '',
				// Lightbox: every image link on the site (post content, WordPress galleries too), look and buttons.
				'lightbox_auto'        => true,
				'lightbox_bg'          => '',
				'lightbox_ui'          => '',
				'lightbox_caption'     => true,
				'lightbox_counter'     => true,
				'lightbox_download'    => false,
			),
			'custom_css'  => '',
		);
	}

	/**
	 * Control schemas for the structured kit sections (reused for sanitizing and CSS).
	 *
	 * @return array<string, array<string, array<string,mixed>>>
	 */
	public static function schemas(): array {
		return array(
			'layout'  => array(
				'container_width' => array( 'type' => 'slider', 'responsive' => true, 'size_units' => array( 'px', '%', 'vw', 'rem' ), 'selectors' => array( ':root' => '--uncoder-container: {{VALUE}}' ) ),
				'gutter'          => array( 'type' => 'slider', 'responsive' => true, 'size_units' => array( 'px', 'rem', 'vw' ), 'selectors' => array( ':root' => '--uncoder-gutter: {{VALUE}}' ) ),
				'gap'             => array( 'type' => 'slider', 'responsive' => true, 'size_units' => array( 'px', 'rem' ), 'selectors' => array( ':root' => '--uncoder-kit-gap: {{VALUE}}' ) ),
				'section_space'   => array( 'type' => 'slider', 'responsive' => true, 'size_units' => array( 'px', 'rem', 'vh' ), 'selectors' => array( ':root' => '--uncoder-section-space: {{VALUE}}' ) ),
				// Space kept above #anchor targets, in addition to the sticky header height.
				'scroll_offset'   => array( 'type' => 'slider', 'size_units' => array( 'px' ), 'selectors' => array( ':root' => '--uncoder-scroll-offset: {{VALUE}}' ) ),
			),
			'buttons' => array(
				'typography'       => array( 'type' => 'typography', 'selector' => '.uncoder-btn' ),
				'color'            => array( 'type' => 'color', 'selectors' => array( '.uncoder-btn' => 'color: {{VALUE}}' ) ),
				'background'       => array( 'type' => 'color', 'selectors' => array( '.uncoder-btn' => 'background-color: {{VALUE}}' ) ),
				'hover_color'      => array( 'type' => 'color', 'selectors' => array( '.uncoder-btn:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ) ),
				'hover_background' => array( 'type' => 'color', 'selectors' => array( '.uncoder-btn:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ),
				'padding'          => array( 'type' => 'dimensions', 'responsive' => true, 'selectors' => array( '.uncoder-btn' => 'padding: {{VALUE}}' ) ),
				'radius'           => array( 'type' => 'dimensions', 'selectors' => array( '.uncoder-btn' => 'border-radius: {{VALUE}}' ) ),
				'border'           => array( 'type' => 'border', 'selector' => '.uncoder-btn' ),
				'hover_border_color' => array( 'type' => 'color', 'selectors' => array( '.uncoder-btn:hover' => 'border-color: {{VALUE}}' ) ),
				'shadow'           => array( 'type' => 'box_shadow', 'selector' => '.uncoder-btn' ),
				// Default hover effect for every button without its own (button_fx_css()).
				'hover_effect'     => array( 'type' => 'select', 'options' => array( '' => 'None', 'lift' => 'Lift', 'grow' => 'Grow', 'shrink' => 'Shrink', 'pulse' => 'Pulse', 'shine' => 'Shine', 'arrow' => 'Nudge icon', 'swap' => 'Slide icon through', 'reveal' => 'Reveal icon', 'fill' => 'Fill from left', 'fill-up' => 'Fill from bottom', 'underline' => 'Underline', 'flip' => 'Text roll' ) ),
				// Colour of the Fill hover effects.
				'hover_fill'       => array( 'type' => 'color', 'selectors' => array( '.uncoder-btn' => '--uncoder-btn-fill: {{VALUE}}' ) ),
			),
			'forms'   => array(
				'label_color'        => array( 'type' => 'color', 'selectors' => array( '.uncoder .uncoder-form__label' => 'color: {{VALUE}}' ) ),
				'label_typography'   => array( 'type' => 'typography', 'selector' => '.uncoder .uncoder-form__label' ),
				'field_typography'   => array( 'type' => 'typography', 'selector' => '.uncoder .uncoder-form__field' ),
				'field_color'        => array( 'type' => 'color', 'selectors' => array( '.uncoder .uncoder-form__field' => 'color: {{VALUE}}' ) ),
				'field_background'   => array( 'type' => 'color', 'selectors' => array( '.uncoder .uncoder-form__field' => 'background-color: {{VALUE}}' ) ),
				'field_border_color' => array( 'type' => 'color', 'selectors' => array( '.uncoder .uncoder-form__field' => 'border-color: {{VALUE}}' ) ),
				'focus_border_color' => array( 'type' => 'color', 'selectors' => array( '.uncoder .uncoder-form__field:focus' => 'border-color: {{VALUE}}; outline-color: {{VALUE}}' ) ),
				'field_radius'       => array( 'type' => 'dimensions', 'selectors' => array( '.uncoder .uncoder-form__field' => 'border-radius: {{VALUE}}' ) ),
				'field_padding'      => array( 'type' => 'dimensions', 'selectors' => array( '.uncoder .uncoder-form__field' => 'padding: {{VALUE}}' ) ),
			),
			'theme'   => array(
				'enabled'          => array( 'type' => 'switch' ),
				'body_color'       => array( 'type' => 'color' ),
				'heading_color'    => array( 'type' => 'color' ),
				'link_color'       => array( 'type' => 'color' ),
				'link_hover_color' => array( 'type' => 'color' ),
				'background'       => array( 'type' => 'color' ),
				'background_image' => array( 'type' => 'media' ),
				'background_size'  => array( 'type' => 'select', 'options' => array( 'auto' => 'Tile', 'cover' => 'Cover' ) ),
				'optical_sizing'   => array( 'type' => 'select', 'options' => array( '' => 'Auto', 'none' => 'Off' ) ),
			),
			'settings' => array(
				'font_delivery'        => array( 'type' => 'select', 'options' => array( 'google' => 'Google Fonts CDN', 'local' => 'Self-hosted (downloaded once, no requests to Google)', 'none' => 'Do not load fonts' ) ),
				'smooth_scroll'        => array( 'type' => 'switch' ),
				'back_to_top'          => array( 'type' => 'switch' ),
				'back_to_top_position' => array( 'type' => 'select', 'options' => array( 'right' => 'Right', 'left' => 'Left' ) ),
				// light: no dark mode · toggle: light until the visitor switches · auto: follows the device, switchable.
				'color_scheme'         => array( 'type' => 'select', 'options' => array( 'light' => 'Light only', 'toggle' => 'Dark mode with a switch', 'auto' => 'Follow the device (with a switch)' ) ),
				'preloader'            => array( 'type' => 'select', 'options' => array( '' => 'None', 'spinner' => 'Spinner', 'bar' => 'Loading bar', 'logo' => 'Pulsing logo' ) ),
				'preloader_once'       => array( 'type' => 'switch' ),
				'page_transition'      => array( 'type' => 'select', 'options' => array( '' => 'None', 'fade' => 'Cross-fade', 'slide' => 'Slide up' ) ),
				'lightbox_auto'        => array( 'type' => 'switch' ),
				'lightbox_bg'          => array( 'type' => 'color' ),
				'lightbox_ui'          => array( 'type' => 'color' ),
				'lightbox_caption'     => array( 'type' => 'switch' ),
				'lightbox_counter'     => array( 'type' => 'switch' ),
				'lightbox_download'    => array( 'type' => 'switch' ),
			),
		);
	}

	/**
	 * Controls a global class can hold for an element type: everything that becomes CSS
	 * (style, layout and design settings), never content, repeaters or custom code.
	 *
	 * @return array<string, array<string,mixed>>
	 */
	public static function class_controls( Element_Base $element ): array {
		$registry = Plugin::instance()->controls();
		$out      = array();
		foreach ( $element->get_controls() as $key => $control ) {
			$type = $registry->get( (string) ( $control['type'] ?? '' ) );
			if ( ! $type || 'repeater' === ( $control['type'] ?? '' ) || '_custom_css' === $key ) {
				continue;
			}
			if ( ! empty( $control['selectors'] ) || ( $type->is_group() && ! empty( $control['selector'] ) ) ) {
				$out[ $key ] = $control;
			}
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function all(): array {
		if ( null === $this->data ) {
			$stored     = get_option( self::OPTION, array() );
			$this->data = $this->merge_defaults( is_array( $stored ) ? $stored : array() );
		}
		return $this->data;
	}

	/**
	 * @param mixed $default Default.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$all = $this->all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * @param mixed $default Default.
	 * @return mixed
	 */
	public function setting( string $key, $default = null ) {
		return $this->all()['settings'][ $key ] ?? $default;
	}

	/**
	 * @param array<string,mixed> $stored Stored.
	 * @return array<string,mixed>
	 */
	private function merge_defaults( array $stored ): array {
		$defaults = self::defaults();
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				$stored[ $key ] = $value;
			} elseif ( in_array( $key, array( 'layout', 'buttons', 'forms', 'theme', 'settings' ), true ) && is_array( $stored[ $key ] ) ) {
				$stored[ $key ] = array_merge( $value, $stored[ $key ] );
			}
		}
		return $stored;
	}

	/**
	 * @return string[]
	 */
	public function color_ids(): array {
		return array_column( $this->get( 'colors', array() ), 'id' );
	}

	/**
	 * @return array<string,string> preset id => name
	 */
	public function presets(): array {
		$out = array();
		foreach ( $this->get( 'typography', array() ) as $preset ) {
			$out[ $preset['id'] ] = $preset['name'];
		}
		return $out;
	}

	/**
	 * Sanitizes a full or partial kit.
	 *
	 * @param array<string,mixed> $data  Data.
	 * @param string              $mode  sanitize|normalize.
	 * @param string[]            $errors Errors.
	 * @return array<string,mixed>
	 */
	public function sanitize( array $data, string $mode = 'sanitize', array &$errors = array() ): array {
		$data     = Migrations::legacy( $data );
		$registry = Plugin::instance()->controls();
		$out      = array();

		if ( isset( $data['colors'] ) && is_array( $data['colors'] ) ) {
			$out['colors'] = array();
			$color_type    = $registry->get( 'color' );
			foreach ( array_values( $data['colors'] ) as $i => $color ) {
				if ( ! is_array( $color ) ) {
					continue;
				}
				$id    = sanitize_key( (string) ( $color['id'] ?? '' ) );
				$value = $color_type->normalize( $color['value'] ?? '', array() );
				if ( '' === $id || null === $value || '' === $value ) {
					$errors[] = sprintf( 'colors[%d]: needs an "id" (slug) and a valid "value".', $i );
					continue;
				}
				$item = array(
					'id'    => $id,
					'name'  => sanitize_text_field( (string) ( $color['name'] ?? ucfirst( $id ) ) ),
					'value' => $value,
				);
				// Dark mode value (optional).
				$dark = isset( $color['dark'] ) && '' !== $color['dark'] ? $color_type->normalize( $color['dark'], array() ) : '';
				if ( is_string( $dark ) && '' !== $dark ) {
					$item['dark'] = $dark;
				}
				$out['colors'][] = $item;
			}
		}
		if ( isset( $data['variables'] ) && is_array( $data['variables'] ) ) {
			$out['variables'] = array();
			$seen             = array();
			foreach ( array_values( $data['variables'] ) as $i => $var ) {
				if ( ! is_array( $var ) ) {
					continue;
				}
				$id    = trim( (string) preg_replace( '/[^a-z0-9-]+/', '-', strtolower( (string) ( $var['id'] ?? $var['name'] ?? '' ) ) ), '-' );
				$value = Utils::css_value( $var['value'] ?? '' );
				if ( '' === $id || '' === $value || isset( $seen[ $id ] ) ) {
					$errors[] = sprintf( 'variables[%d]: needs a unique "id" (slug, e.g. "space-md") and a CSS "value" (e.g. "24px" or "clamp(1rem, 3vw, 2rem)").', $i );
					continue;
				}
				$seen[ $id ]         = true;
				$group               = (string) ( $var['group'] ?? 'other' );
				$out['variables'][] = array(
					'id'    => $id,
					'name'  => sanitize_text_field( (string) ( $var['name'] ?? $id ) ),
					'group' => in_array( $group, self::VARIABLE_GROUPS, true ) ? $group : 'other',
					'value' => $value,
				);
			}
		}
		if ( isset( $data['fonts'] ) && is_array( $data['fonts'] ) ) {
			$out['fonts'] = array();
			$font_type    = $registry->get( 'font' );
			foreach ( array_values( $data['fonts'] ) as $font ) {
				if ( ! is_array( $font ) ) {
					continue;
				}
				$id = sanitize_key( (string) ( $font['id'] ?? '' ) );
				if ( '' === $id ) {
					continue;
				}
				$family         = $font_type->sanitize( $font['family'] ?? '', array() );
				$out['fonts'][] = array(
					'id'     => $id,
					'name'   => sanitize_text_field( (string) ( $font['name'] ?? ucfirst( $id ) ) ),
					'family' => is_string( $family ) ? $family : '',
				);
			}
		}
		if ( isset( $data['typography'] ) && is_array( $data['typography'] ) ) {
			$out['typography'] = array();
			$typo              = $registry->get( 'typography' );
			foreach ( array_values( $data['typography'] ) as $i => $preset ) {
				if ( ! is_array( $preset ) ) {
					continue;
				}
				$id = sanitize_key( (string) ( $preset['id'] ?? '' ) );
				if ( '' === $id ) {
					$errors[] = sprintf( 'typography[%d]: missing "id".', $i );
					continue;
				}
				$value = 'normalize' === $mode ? $typo->normalize( $preset['value'] ?? array(), array() ) : $typo->sanitize( $preset['value'] ?? array(), array() );
				unset( $value['preset'] );
				$out['typography'][] = array(
					'id'    => $id,
					'name'  => sanitize_text_field( (string) ( $preset['name'] ?? strtoupper( $id ) ) ),
					'value' => is_array( $value ) ? $value : array(),
				);
			}
		}
		foreach ( self::schemas() as $section => $controls ) {
			if ( isset( $data[ $section ] ) && is_array( $data[ $section ] ) ) {
				$section_errors  = array();
				$out[ $section ] = $registry->process_settings( $data[ $section ], $controls, $mode, $section_errors, $section . '.' );
				$errors          = array_merge( $errors, $section_errors );
			}
		}
		if ( isset( $data['breakpoints'] ) && is_array( $data['breakpoints'] ) ) {
			$out['breakpoints'] = array();
			foreach ( self::breakpoint_map( $data['breakpoints'] ) as $id => $bp ) {
				if ( isset( Breakpoints::DEFAULTS[ $id ] ) && is_array( $bp ) ) {
					$entry = array();
					// A change that only gives a width keeps the breakpoint's on/off state (update() merges per field).
					if ( array_key_exists( 'enabled', $bp ) ) {
						$entry['enabled'] = ! empty( $bp['enabled'] );
					}
					if ( isset( $bp['value'] ) || ! isset( $entry['enabled'] ) ) {
						$entry['value'] = max( 320, min( 3840, absint( $bp['value'] ?? Breakpoints::DEFAULTS[ $id ]['value'] ) ) );
					}
					$out['breakpoints'][ $id ] = $entry;
				}
			}
		}
		if ( isset( $data['classes'] ) && is_array( $data['classes'] ) ) {
			$out['classes'] = array();
			foreach ( array_values( $data['classes'] ) as $i => $class ) {
				if ( ! is_array( $class ) ) {
					continue;
				}
				$id      = sanitize_key( (string) ( $class['id'] ?? '' ) );
				$element = Plugin::instance()->elements()->get( sanitize_key( (string) ( $class['type'] ?? '' ) ) );
				if ( '' === $id || ! $element ) {
					$errors[] = sprintf( 'classes[%d]: needs an "id" (slug) and the element "type" it styles (e.g. "heading", "container").', $i );
					continue;
				}
				$class_errors      = array();
				$settings          = $registry->process_settings( is_array( $class['settings'] ?? null ) ? $class['settings'] : array(), self::class_controls( $element ), $mode, $class_errors, 'classes.' . $id . '.' );
				$errors            = array_merge( $errors, $class_errors );
				$out['classes'][] = array(
					'id'       => $id,
					'name'     => sanitize_text_field( (string) ( $class['name'] ?? ucfirst( $id ) ) ),
					'type'     => $element->name(),
					'settings' => $settings,
				);
			}
		}
		if ( isset( $data['custom_css'] ) ) {
			$out['custom_css'] = current_user_can( 'unfiltered_html' ) ? Utils::sanitize_custom_css( $data['custom_css'] ) : ( $this->get( 'custom_css' ) ?? '' );
		}
		return $out;
	}

	/**
	 * Fields of a text style that have a value on at least one device.
	 *
	 * A family set to a Design System font without a family (`var(--uncoder-f-heading)` while Headings is
	 * empty) is not a value: that font means "the theme's font", and writing font-family would override the
	 * theme's body and heading fonts with nothing (the browser default serif).
	 *
	 * @param array<string,mixed> $value       Text style value.
	 * @param string[]            $unset_fonts `var(--uncoder-f-{id})` of the fonts without a family.
	 * @return array<string,true>
	 */
	private static function preset_fields( array $value, array $unset_fonts = array() ): array {
		$out = array();
		foreach ( array_keys( Typography::PRESET_VARS ) as $field ) {
			foreach ( Breakpoints::devices() as $device ) {
				$v = $value[ $field . Breakpoints::suffix( $device ) ] ?? null;
				if ( 'family' === $field && in_array( $v, $unset_fonts, true ) ) {
					continue;
				}
				if ( is_array( $v ) ? ( isset( $v['size'] ) && '' !== $v['size'] ) : ( null !== $v && '' !== $v ) ) {
					$out[ $field ] = true;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * `property:var(--uncoder-t-{id}-{x})` declarations for the fields a text style defines.
	 *
	 * @param array<string,true> $fields Defined fields.
	 * @return string[]
	 */
	private static function preset_decls( string $id, array $fields ): array {
		$decls = array();
		foreach ( Typography::PRESET_VARS as $field => $var ) {
			if ( isset( $fields[ $field ] ) ) {
				$decls[] = $var[0] . ':var(--uncoder-t-' . $id . '-' . $var[1] . ')';
			}
		}
		return $decls;
	}

	/**
	 * Merges a partial update. Lists (colors, fonts, typography) merge by id; sections merge by key.
	 *
	 * @param array<string,mixed> $partial Partial kit (already sanitized).
	 * @return array<string,mixed> The new kit.
	 */
	public function update( array $partial, string $label = '', bool $snapshot = true ): array {
		$current = $this->all();
		if ( $snapshot ) {
			$this->snapshot( '' !== $label ? $label : 'Before update' );
		}
		foreach ( array( 'colors', 'fonts', 'typography', 'classes', 'variables' ) as $list ) {
			if ( ! isset( $partial[ $list ] ) ) {
				continue;
			}
			$current[ $list ] = (array) ( $current[ $list ] ?? array() );
			$index = array();
			foreach ( $current[ $list ] as $i => $item ) {
				$index[ $item['id'] ] = $i;
			}
			foreach ( $partial[ $list ] as $item ) {
				if ( isset( $index[ $item['id'] ] ) ) {
					$existing = $current[ $list ][ $index[ $item['id'] ] ];
					if ( 'typography' === $list ) {
						$item['value'] = array_merge( $existing['value'] ?? array(), $item['value'] );
						$current[ $list ][ $index[ $item['id'] ] ] = $item;
					} elseif ( 'classes' === $list ) {
						$current[ $list ][ $index[ $item['id'] ] ] = $item; // A class is replaced as a whole.
					} else {
						$current[ $list ][ $index[ $item['id'] ] ] = array_merge( $existing, $item );
					}
				} else {
					$current[ $list ][] = $item;
				}
			}
		}
		foreach ( array( 'layout', 'buttons', 'forms', 'theme', 'settings' ) as $section ) {
			if ( isset( $partial[ $section ] ) ) {
				$current[ $section ] = array_merge( (array) $current[ $section ], $partial[ $section ] );
			}
		}
		// Breakpoints merge per breakpoint and per field: a new width keeps the on/off state and the reverse.
		if ( isset( $partial['breakpoints'] ) && is_array( $partial['breakpoints'] ) ) {
			$current['breakpoints'] = (array) ( $current['breakpoints'] ?? array() );
			foreach ( $partial['breakpoints'] as $id => $bp ) {
				$current['breakpoints'][ $id ] = array_merge( (array) ( $current['breakpoints'][ $id ] ?? array() ), (array) $bp );
			}
		}
		if ( isset( $partial['custom_css'] ) ) {
			$current['custom_css'] = $partial['custom_css'];
		}
		$this->save( $current );
		return $current;
	}

	/**
	 * Replaces the whole kit (already sanitized) and rebuilds CSS.
	 *
	 * @param array<string,mixed> $data Kit.
	 */
	public function save( array $data ): void {
		update_option( self::OPTION, $data, true );
		$this->data = null;
		Breakpoints::flush();
		update_option( self::VERSION_OPT, (string) time(), true );
		$this->write_css();
		/**
		 * Fires after the Design System changed. Document CSS depends on breakpoints, so it is regenerated lazily.
		 */
		do_action( 'uncoder_wb/kit/saved', $this->all() );
	}

	public function version(): string {
		$v = get_option( self::VERSION_OPT );
		return $v ? (string) $v : '1';
	}

	/**
	 * Removes items from lists by id.
	 *
	 * @param array<string,string[]> $ids e.g. ['colors' => ['accent']].
	 */
	public function remove( array $ids ): void {
		$current = $this->all();
		foreach ( array( 'colors', 'fonts', 'typography', 'classes' ) as $list ) {
			if ( empty( $ids[ $list ] ) ) {
				continue;
			}
			$current[ $list ] = array_values(
				array_filter( $current[ $list ], static fn( $item ) => ! in_array( $item['id'], (array) $ids[ $list ], true ) )
			);
		}
		$this->save( $current );
	}

	/* ---------------------------------------------------------------- Snapshots */

	public function snapshot( string $label ): string {
		$snaps = get_option( self::SNAPSHOTS, array() );
		$snaps = is_array( $snaps ) ? $snaps : array();
		$id    = gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
		array_unshift(
			$snaps,
			array(
				'id'    => $id,
				'label' => sanitize_text_field( $label ),
				'time'  => time(),
				'user'  => get_current_user_id(),
				'data'  => $this->all(),
			)
		);
		update_option( self::SNAPSHOTS, array_slice( $snaps, 0, self::MAX_SNAPSHOTS ), false );
		return $id;
	}

	/**
	 * @return array<int, array{id:string,label:string,time:int,user:int}>
	 */
	public function snapshots(): array {
		$snaps = get_option( self::SNAPSHOTS, array() );
		return array_map(
			static fn( $s ) => array(
				'id'    => $s['id'],
				'label' => $s['label'],
				'time'  => $s['time'],
				'user'  => $s['user'],
			),
			is_array( $snaps ) ? $snaps : array()
		);
	}

	public function restore( string $id = '' ): bool {
		$snaps = get_option( self::SNAPSHOTS, array() );
		foreach ( (array) $snaps as $snap ) {
			if ( '' === $id || $snap['id'] === $id ) {
				$this->snapshot( 'Before restore' );
				$this->save( $snap['data'] );
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------- CSS */

	/**
	 * @param array<string,mixed>|null $kit Kit to render (default: the saved one).
	 */
	public function css( ?array $kit = null ): string {
		$kit   = $kit ?? $this->all();
		$rules = new Rules();
		$root  = array();

		foreach ( $kit['colors'] as $color ) {
			$value = Utils::sanitize_color( $color['value'] );
			if ( '' !== $value ) {
				$root[] = '--uncoder-c-' . sanitize_key( $color['id'] ) . ':' . $value;
			}
		}
		$unset_fonts = array();
		foreach ( $kit['fonts'] as $font ) {
			$root[] = '--uncoder-f-' . sanitize_key( $font['id'] ) . ':' . ( '' !== $font['family'] ? Fonts::css_stack( $font['family'] ) : 'inherit' );
			if ( '' === $font['family'] ) {
				$unset_fonts[] = 'var(--uncoder-f-' . sanitize_key( $font['id'] ) . ')';
			}
		}
		foreach ( (array) ( $kit['variables'] ?? array() ) as $var ) {
			$value = Utils::css_value( $var['value'] ?? '' );
			if ( '' !== $value ) {
				$root[] = '--uncoder-v-' . $var['id'] . ':' . $value;
			}
		}
		$rules->add( ':root', $root );

		// Text styles → CSS variables (responsive) + utility classes. Only fields a style defines are used:
		// var() of an undefined variable would unset the property (e.g. a heading falling back to the body size).
		$defined = array();
		foreach ( $kit['typography'] as $preset ) {
			$id             = sanitize_key( $preset['id'] );
			$defined[ $id ] = self::preset_fields( (array) $preset['value'], $unset_fonts );
			$this->preset_vars( $id, (array) $preset['value'], $rules );
			$rules->add( '.uncoder-t-' . $id, self::preset_decls( $id, $defined[ $id ] ) );
		}

		$generator = new Generator( 0 );
		foreach ( array( 'layout', 'buttons', 'forms' ) as $section ) {
			$generator->settings_css( (array) $kit[ $section ], self::schemas()[ $section ], '', $rules );
		}

		// Global classes: (0,2,0) beats widget base styles; the element's own CSS (same weight, loaded
		// later in the document stylesheet) still overrides a class.
		foreach ( (array) ( $kit['classes'] ?? array() ) as $class ) {
			$element = Plugin::instance()->elements()->get( (string) ( $class['type'] ?? '' ) );
			if ( $element && is_array( $class['settings'] ?? null ) ) {
				$generator->settings_css( $class['settings'], $element->get_controls(), '.uncoder .uncoder-gc-' . sanitize_key( (string) $class['id'] ), $rules );
			}
		}

		$theme = (array) $kit['theme'];
		if ( ! empty( $theme['enabled'] ) ) {
			$body = isset( $defined['body'] ) ? self::preset_decls( 'body', $defined['body'] ) : array();
			foreach ( array( 'body_color' => 'color', 'background' => 'background-color' ) as $key => $prop ) {
				$c = Utils::sanitize_color( (string) ( $theme[ $key ] ?? '' ) );
				if ( '' !== $c ) {
					$body[] = $prop . ':' . $c;
				}
			}
			// A page texture or pattern behind every section without its own background (header, footer and
			// coloured bands keep theirs): tiled at its own size, or one image covering the page.
			$image = is_array( $theme['background_image'] ?? null ) ? Utils::css_url( (string) ( $theme['background_image']['url'] ?? '' ) ) : '';
			if ( '' !== $image ) {
				$body[] = 'background-image:url("' . $image . '")';
				$body[] = 'cover' === ( $theme['background_size'] ?? '' ) ? 'background-size:cover;background-position:center top' : 'background-repeat:repeat';
			}
			// Fonts with an optical-size axis (Inter, Fraunces…) draw large text with their display cut; "Off" keeps
			// the text cut at every size (the look and width of the static font files many designs were made with).
			// Google fonts are then also loaded without the axis (Fonts::enqueue()), which pins their standard cut;
			// this rule covers uploaded variable fonts.
			if ( 'none' === ( $theme['optical_sizing'] ?? '' ) ) {
				$body[] = 'font-optical-sizing:none';
			}
			$rules->add( 'body.uncoder-kit', $body );
			$heading_color = Utils::sanitize_color( (string) ( $theme['heading_color'] ?? '' ) );
			foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $h ) {
				$decls = isset( $defined[ $h ] ) ? self::preset_decls( $h, $defined[ $h ] ) : array();
				if ( '' !== $heading_color ) {
					$decls[] = 'color:var(--uncoder-heading-color,' . $heading_color . ')';
				}
				$rules->add( '.uncoder-kit ' . $h, $decls );
			}
			$link = Utils::sanitize_color( (string) ( $theme['link_color'] ?? '' ) );
			if ( '' !== $link ) {
				$rules->add( '.uncoder :where(a:not(.uncoder-btn))', 'color:' . $link ); // (0,1,0): widget link styles (loaded later) win.
			}
			$hover = Utils::sanitize_color( (string) ( $theme['link_hover_color'] ?? '' ) );
			if ( '' !== $hover ) {
				$rules->add( '.uncoder :where(a:not(.uncoder-btn):hover)', 'color:' . $hover );
			}
		}

		$css = $rules->render() . self::dark_css( $kit ) . self::button_fx_css( $kit ) . self::visibility_css();
		if ( ! empty( $kit['custom_css'] ) ) {
			$css .= "\n" . Utils::sanitize_custom_css( $kit['custom_css'] );
		}
		return $css;
	}

	/**
	 * Dark mode: the kit colors' dark values, active while <html data-uncoder-scheme="dark"> (set before the
	 * first paint by Frontend::color_scheme_script()). Twin of darkCss() in src/shared/kit.ts.
	 *
	 * @param array<string,mixed> $kit Kit.
	 */
	public static function dark_css( array $kit ): string {
		if ( 'light' === ( $kit['settings']['color_scheme'] ?? 'light' ) ) {
			return '';
		}
		$decls = array();
		foreach ( (array) ( $kit['colors'] ?? array() ) as $color ) {
			$value = Utils::sanitize_color( (string) ( $color['dark'] ?? '' ) );
			if ( '' !== $value ) {
				$decls[] = '--uncoder-c-' . sanitize_key( (string) $color['id'] ) . ':' . $value;
			}
		}
		$decls[] = 'color-scheme:dark';
		return 'html[data-uncoder-scheme=dark]{' . implode( ';', $decls ) . '}';
	}

	/**
	 * The site-wide button hover effect (buttons.hover_effect) for buttons without their own effect,
	 * from assets/data/button-fx.json. Twin of buttonFxCss() in src/shared/kit.ts.
	 *
	 * @param array<string,mixed> $kit Kit.
	 */
	public static function button_fx_css( array $kit ): string {
		static $fx = null;
		$effect = (string) ( $kit['buttons']['hover_effect'] ?? '' );
		if ( '' === $effect ) {
			return '';
		}
		if ( null === $fx ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled data file.
			$fx = json_decode( (string) file_get_contents( UNCODER_WB_PATH . 'assets/data/button-fx.json' ), true );
			$fx = is_array( $fx ) ? $fx : array();
		}
		if ( empty( $fx[ $effect ] ) ) {
			return '';
		}
		$css = '';
		foreach ( $fx[ $effect ] as $rule ) {
			$css .= str_replace( '&', '.uncoder-btn:not([class*="uncoder-hover-"],.uncoder-btn--link)', $rule[0] ) . '{' . $rule[1] . '}';
		}
		return '@media (prefers-reduced-motion:no-preference){' . $css . '}';
	}

	/**
	 * .uncoder-hide-{device} classes for the active breakpoints (each device is an exclusive width range).
	 */
	public static function visibility_css(): string {
		$css = '';
		foreach ( self::device_queries() as $device => $query ) {
			$rules = '.uncoder-hide-' . $device . '{display:none!important}.uncoder-sticky-off-' . $device . '{position:relative!important;top:auto!important;bottom:auto!important}'
				// A transparent header that is not sticky on this device overlays the page and scrolls away with it.
				. '.uncoder-header-overlay-' . $device . '{position:absolute!important;top:var(--wp-admin--admin-bar--height,0px)!important}';
			$css  .= '' !== $query ? '@media ' . $query . '{' . $rules . '}' : $rules;
		}
		return $css;
	}

	/**
	 * Exclusive width range of every active device ('' = all widths), desktop last.
	 *
	 * @return array<string,string>
	 */
	public static function device_queries(): array {
		$active = Breakpoints::active();
		$max    = array();
		$min    = null;
		foreach ( $active as $id => $bp ) {
			if ( 'min' === $bp['direction'] ) {
				$min = array( $id, (int) $bp['value'] );
			} else {
				$max[ $id ] = (int) $bp['value'];
			}
		}
		arsort( $max );
		$out = array();
		$ids = array_keys( $max );
		foreach ( $ids as $i => $id ) {
			$lower      = isset( $ids[ $i + 1 ] ) ? $max[ $ids[ $i + 1 ] ] + 1 : 0;
			$out[ $id ] = $lower > 0 ? '(min-width:' . $lower . 'px) and (max-width:' . $max[ $id ] . 'px)' : '(max-width:' . $max[ $id ] . 'px)';
		}
		$desktop_min = $ids ? $max[ $ids[0] ] + 1 : 0;
		$desktop     = $desktop_min ? '(min-width:' . $desktop_min . 'px)' : '';
		if ( $min ) {
			$desktop        .= ( $desktop ? ' and ' : '' ) . '(max-width:' . ( $min[1] - 1 ) . 'px)';
			$out[ $min[0] ] = '(min-width:' . $min[1] . 'px)';
		}
		$out['desktop'] = $desktop;
		return $out;
	}

	/**
	 * @param array<string,mixed> $value Typography value.
	 */
	private function preset_vars( string $id, array $value, Rules $rules ): void {
		foreach ( Breakpoints::devices() as $device ) {
			$suffix = Breakpoints::suffix( $device );
			$decls  = array();
			foreach ( Typography::PRESET_VARS as $field => $var ) {
				$v = $value[ $field . $suffix ] ?? null;
				if ( null === $v || '' === $v ) {
					continue;
				}
				if ( is_array( $v ) ) {
					if ( ! isset( $v['size'] ) || '' === $v['size'] ) {
						continue;
					}
					$css = 'custom' === ( $v['unit'] ?? '' ) ? Utils::css_value( $v['size'] ) : $v['size'] . ( $v['unit'] ?? '' );
				} elseif ( 'family' === $field ) {
					$css = Fonts::css_stack( (string) $v );
				} else {
					$css = Utils::css_value( $v );
				}
				$decls[] = '--uncoder-t-' . $id . '-' . $var[1] . ':' . $css;
			}
			if ( $decls ) {
				$rules->add( ':root', $decls, $device );
			}
		}
	}

	/**
	 * Fonts used by the kit (for enqueueing).
	 *
	 * @return array<string,string[]>
	 */
	public function fonts(): array {
		$fonts = array();
		foreach ( $this->get( 'fonts', array() ) as $font ) {
			if ( ! empty( $font['family'] ) ) {
				$fonts[ $font['family'] ] = array( '400', '500', '600', '700' );
			}
		}
		foreach ( $this->get( 'typography', array() ) as $preset ) {
			$family = $preset['value']['family'] ?? '';
			if ( is_string( $family ) && '' !== $family && 0 !== strpos( $family, 'var(' ) ) {
				$fonts[ $family ][] = (string) ( $preset['value']['weight'] ?? '400' );
			}
		}
		// Typography groups inside global classes.
		foreach ( (array) $this->get( 'classes', array() ) as $class ) {
			foreach ( (array) ( $class['settings'] ?? array() ) as $value ) {
				if ( is_array( $value ) && ! empty( $value['family'] ) && is_string( $value['family'] ) && 0 !== strpos( $value['family'], 'var(' ) ) {
					$fonts[ $value['family'] ][] = (string) ( $value['weight'] ?? '400' );
					$fonts[ $value['family'] ][] = '400';
				}
			}
		}
		return $fonts;
	}

	/**
	 * Writes kit.css to uploads.
	 */
	public function write_css(): bool {
		$uploads = Utils::uploads();
		if ( ! $uploads ) {
			return false;
		}
		if ( ! is_dir( $uploads['dir'] . '/css' ) ) {
			wp_mkdir_p( $uploads['dir'] . '/css' );
		}
		$ok = false !== file_put_contents( $uploads['dir'] . '/css/kit.css', $this->css() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( $ok ) {
			update_option( self::CSS_BUILD, $this->css_build(), false );
		}
		return $ok;
	}

	private function css_build(): string {
		return UNCODER_WB_VERSION . '.' . Css\Generator::REVISION . '|' . $this->version();
	}

	/**
	 * @return array{url:string,ver:string}|null
	 */
	public function css_file(): ?array {
		$uploads = Utils::uploads();
		if ( ! $uploads ) {
			return null;
		}
		$file  = $uploads['dir'] . '/css/kit.css';
		$stale = ! file_exists( $file ) || get_option( self::CSS_BUILD ) !== $this->css_build();
		if ( $stale && ! $this->write_css() && ! file_exists( $file ) ) {
			return null;
		}
		return array(
			'url'  => $uploads['url'] . '/css/kit.css',
			'path' => $file,
			'ver'  => substr( md5( $this->css_build() ), 0, 10 ),
		);
	}

	/**
	 * Breakpoints as stored ({"tablet":{"value":1199}}), also from the list the kit exports
	 * ([{"id":"desktop",…},{"id":"tablet","value":1199,…}], a Site Kit's design.json). A full list, with its
	 * "desktop" entry, holds every active breakpoint, so the ones it leaves out are turned off; a shorter list (an
	 * AI or script changing a width) only changes the ones it names.
	 *
	 * @param array<int|string,mixed> $breakpoints Breakpoints in either shape.
	 * @return array<string,mixed>
	 */
	public static function breakpoint_map( array $breakpoints ): array {
		if ( ! isset( $breakpoints[0] ) ) {
			return $breakpoints;
		}
		$map  = array();
		$full = false;
		foreach ( $breakpoints as $bp ) {
			if ( is_array( $bp ) && isset( $bp['id'] ) ) {
				if ( 'desktop' === $bp['id'] ) {
					$full = true;
					continue;
				}
				$map[ (string) $bp['id'] ] = array_intersect_key( $bp, array( 'value' => 1, 'enabled' => 1 ) );
			}
		}
		if ( $full ) {
			foreach ( array_keys( Breakpoints::DEFAULTS ) as $id ) {
				$map[ $id ] = isset( $map[ $id ] ) ? array( 'enabled' => true ) + $map[ $id ] : array( 'enabled' => false );
			}
		}
		return $map;
	}

	/**
	 * Kit exported to the editor / AI.
	 *
	 * @return array<string,mixed>
	 */
	public function export(): array {
		$kit                = $this->all();
		$kit['breakpoints'] = Breakpoints::export();
		return $kit;
	}
}
