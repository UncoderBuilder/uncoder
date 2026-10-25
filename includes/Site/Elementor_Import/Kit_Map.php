<?php
/**
 * Elementor import: Elementor Site Settings (the active kit) → a partial Uncoder Design System.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site\Elementor_Import;

use Uncoder\Builder\Core\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Global colors keep Elementor's system ids (primary, secondary, text, accent — Uncoder has the same) and
 * custom colors become "e-{id}"; global fonts become text styles with the same ids. Heading / body fonts feed
 * the Design System fonts, h1–h6 and body typography the matching text styles, plus theme colors, container
 * width, widget gap, buttons, form fields, breakpoints and the site custom CSS. The result goes through
 * Kit::sanitize() and Kit::update() (merge by id).
 */
final class Kit_Map {

	/**
	 * @param array<string,mixed> $k Elementor kit settings (_elementor_page_settings of the active kit).
	 * @return array<string,mixed> Partial Design System.
	 */
	public static function convert( array $k ): array {
		$globals = array_filter( is_array( $k['__globals__'] ?? null ) ? $k['__globals__'] : array(), static fn( $v ) => is_string( $v ) && '' !== $v );
		$out     = array();

		// Colors.
		$colors = array();
		foreach ( array( 'system_colors', 'custom_colors' ) as $list ) {
			foreach ( (array) ( $k[ $list ] ?? array() ) as $c ) {
				if ( ! is_array( $c ) || empty( $c['_id'] ) ) {
					continue;
				}
				$value = Utils::sanitize_color( (string) ( $c['color'] ?? '' ) );
				if ( '' === $value ) {
					continue;
				}
				$colors[] = array(
					'id'    => Converter::color_id( (string) $c['_id'] ),
					'name'  => sanitize_text_field( (string) ( $c['title'] ?? ucfirst( (string) $c['_id'] ) ) ),
					'value' => $value,
				);
			}
		}
		if ( $colors ) {
			$out['colors'] = $colors;
		}
		$color = static function ( string $key ) use ( $k, $globals ): string {
			$ref = $globals[ $key ] ?? '';
			$id  = '' !== $ref ? Converter::global_id( $ref, 'colors' ) : '';
			if ( '' !== $id ) {
				return 'var(--uncoder-c-' . Converter::color_id( $id ) . ')';
			}
			return Utils::sanitize_color( (string) ( $k[ $key ] ?? '' ) );
		};

		// Global fonts → text styles; primary / text families → the heading / body fonts.
		$typos = array();
		$by_id = array();
		foreach ( array( 'system_typography', 'custom_typography' ) as $list ) {
			foreach ( (array) ( $k[ $list ] ?? array() ) as $t ) {
				if ( ! is_array( $t ) || empty( $t['_id'] ) ) {
					continue;
				}
				$value = Source::typo_fields( $t, 'typography' );
				if ( ! $value ) {
					continue;
				}
				$by_id[ (string) $t['_id'] ] = $value;
				$typos[]                     = array(
					'id'    => Converter::typo_id( (string) $t['_id'] ),
					'name'  => sanitize_text_field( (string) ( $t['title'] ?? ucfirst( (string) $t['_id'] ) ) ),
					'value' => $value,
				);
			}
		}
		// Element typography of the kit (group "h1_typography": h1_typography_typography = custom,
		// h1_typography_font_size …): its own values or a copy of the global it uses.
		$typo = static function ( string $group ) use ( $k, $globals, $by_id ): ?array {
			$ref = $globals[ $group . '_typography' ] ?? '';
			$id  = '' !== $ref ? Converter::global_id( $ref, 'typography' ) : '';
			if ( '' !== $id ) {
				return $by_id[ $id ] ?? null;
			}
			return 'custom' === ( $k[ $group . '_typography' ] ?? '' ) ? Source::typo_fields( $k, $group ) : null;
		};
		$h1_family = '';
		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $h ) {
			$v = $typo( $h . '_typography' );
			if ( 'h1' === $h ) {
				$h1_family = (string) ( $v['family'] ?? '' );
			}
			if ( $v ) {
				$typos[] = array(
					'id'    => $h,
					'name'  => 'Heading ' . substr( $h, 1 ),
					'value' => $v,
				);
			}
		}
		$body = $typo( 'body_typography' );
		if ( $body ) {
			$typos[] = array(
				'id'    => 'body',
				'name'  => 'Body',
				'value' => $body,
			);
		}
		$fonts = array();
		// Kits that replace the system fonts with their own (no "primary") still style h1: its family leads.
		$heading_family = (string) ( $by_id['primary']['family'] ?? $h1_family );
		if ( '' !== $heading_family ) {
			$fonts[] = array(
				'id'     => 'heading',
				'name'   => 'Headings',
				'family' => $heading_family,
			);
		}
		$body_family = $by_id['text']['family'] ?? ( $body['family'] ?? '' );
		if ( '' !== $body_family ) {
			$fonts[] = array(
				'id'     => 'body',
				'name'   => 'Body',
				'family' => $body_family,
			);
		}
		if ( $fonts ) {
			$out['fonts'] = $fonts;
		}

		// Theme style colors.
		$theme = array_filter(
			array(
				'body_color'       => $color( 'body_color' ),
				'heading_color'    => $color( 'h1_color' ) ? $color( 'h1_color' ) : $color( 'h2_color' ),
				'link_color'       => $color( 'link_normal_color' ),
				'link_hover_color' => $color( 'link_hover_color' ),
				'background'       => 'classic' === ( $k['body_background_background'] ?? '' ) ? $color( 'body_background_color' ) : '',
			)
		);
		// Elementor's Site Settings style every page of the site: so do the imported ones ("Apply to the whole site"
		// starts off when Uncoder is installed on a site built with Elementor, see Install::activate()).
		$out['theme'] = array( 'enabled' => true ) + $theme;

		// Layout.
		$layout = array();
		foreach ( Source::SUFFIXES as $suffix ) {
			$w = Source::to_slider( $k[ 'container_width' . $suffix ] ?? null );
			if ( $w ) {
				$layout[ 'container_width' . $suffix ] = $w;
			}
			$gap = $k[ 'space_between_widgets' . $suffix ] ?? null;
			if ( is_array( $gap ) ) {
				$g = Source::to_slider(
					array(
						'size' => $gap['row'] ?? $gap['size'] ?? '',
						'unit' => $gap['unit'] ?? 'px',
					)
				);
				if ( $g ) {
					$layout[ 'gap' . $suffix ] = $g;
				}
			}
		}
		if ( $layout ) {
			$out['layout'] = $layout;
		}

		// Buttons.
		$buttons = array_filter(
			array(
				'color'              => $color( 'button_text_color' ),
				'background'         => $color( 'button_background_color' ),
				'hover_color'        => $color( 'button_hover_text_color' ),
				'hover_background'   => $color( 'button_hover_background_color' ),
				'hover_border_color' => $color( 'button_hover_border_color' ),
			)
		);
		$btn_ref = $globals['button_typography_typography'] ?? '';
		$btn_id  = '' !== $btn_ref ? Converter::global_id( $btn_ref, 'typography' ) : '';
		if ( '' !== $btn_id && isset( $by_id[ $btn_id ] ) && ! Converter::complete_typo( $by_id[ $btn_id ] ) ) {
			// A font without size / weight cannot stand as a text style reference (see Converter::global_typography()).
			$buttons['typography'] = $by_id[ $btn_id ];
		} elseif ( '' !== $btn_id ) {
			$buttons['typography'] = array( 'preset' => Converter::typo_id( $btn_id ) );
		} elseif ( 'custom' === ( $k['button_typography_typography'] ?? '' ) ) {
			$v = Source::typo_fields( $k, 'button_typography' );
			if ( $v ) {
				$typos[] = array(
					'id'    => 'button',
					'name'  => 'Button',
					'value' => $v,
				);
			}
		}
		foreach ( Source::SUFFIXES as $suffix ) {
			$p = Source::to_dims( $k[ 'button_padding' . $suffix ] ?? null );
			if ( $p ) {
				$buttons[ 'padding' . $suffix ] = $p;
			}
		}
		$radius = Source::to_dims( $k['button_border_radius'] ?? null );
		if ( $radius ) {
			$buttons['radius'] = $radius;
		}
		$style = (string) ( $k['button_border_border'] ?? '' );
		if ( '' !== $style ) {
			$buttons['border'] = array_filter(
				array(
					'style' => $style,
					'width' => Source::to_dims( $k['button_border_width'] ?? null ),
					'color' => $color( 'button_border_color' ),
				)
			);
		}
		if ( ! empty( $k['button_box_shadow_box_shadow_type'] ) && is_array( $k['button_box_shadow_box_shadow'] ?? null ) ) {
			$shadow = Source::shadow_value( $k['button_box_shadow_box_shadow'], true );
			if ( $shadow ) {
				$buttons['shadow'] = $shadow;
			}
		}
		if ( $buttons && defined( 'UNCODER_ELEMENTS_VERSION' ) && class_exists( 'WooCommerce' ) ) {
			// Uncoder Elements gives WooCommerce's buttons the Site Settings button style.
			$buttons['woocommerce'] = true;
		}
		if ( $buttons ) {
			$out['buttons'] = $buttons;
		}
		if ( $typos ) {
			$out['typography'] = $typos;
		}

		// Form fields.
		$forms = array_filter(
			array(
				'label_color'        => $color( 'form_label_color' ),
				'field_color'        => $color( 'form_field_text_color' ),
				'field_background'   => $color( 'form_field_background_color' ),
				'field_border_color' => $color( 'form_field_border_color' ),
				'focus_border_color' => $color( 'form_field_focus_border_color' ),
				'label_typography'   => 'custom' === ( $k['form_label_typography_typography'] ?? '' ) ? Source::typo_fields( $k, 'form_label_typography' ) : null,
				'field_typography'   => 'custom' === ( $k['form_field_typography_typography'] ?? '' ) ? Source::typo_fields( $k, 'form_field_typography' ) : null,
				'field_radius'       => Source::to_dims( $k['form_field_border_radius'] ?? null ),
				'field_padding'      => Source::to_dims( $k['form_field_padding'] ?? null ),
			)
		);
		// Uncoder Elements' back to top button (on unless switched off, bottom right by default).
		if ( defined( 'UNCODER_ELEMENTS_VERSION' ) && 'yes' === ( $k['uncoder_kit_settings_back_to_top'] ?? 'yes' ) ) {
			$out['settings'] = array(
				'back_to_top'          => true,
				'back_to_top_position' => false !== strpos( (string) ( $k['uncoder_kit_settings_back_to_top_position'] ?? '' ), 'left: 30px' ) ? 'left' : 'right',
			);
		}
		if ( $forms && class_exists( 'WooCommerce' ) ) {
			// Elementor's form field style covers every field of the site, WooCommerce's too.
			$forms['woocommerce'] = true;
		}
		if ( $forms ) {
			$out['forms'] = $forms;
		}

		// Breakpoints (Elementor's are "max-width" values like Uncoder's).
		$bps = array();
		foreach ( array( 'tablet' => array( 'viewport_tablet', 'viewport_lg', 1 ), 'mobile' => array( 'viewport_mobile', 'viewport_md', 1 ) ) as $id => $keys ) {
			$v = $k[ $keys[0] ] ?? '';
			if ( ! is_numeric( $v ) && is_numeric( $k[ $keys[1] ] ?? '' ) ) {
				$v = (int) $k[ $keys[1] ] - $keys[2];
			}
			if ( is_numeric( $v ) && (int) $v >= 320 ) {
				$bps[ $id ] = array(
					'enabled' => true,
					'value'   => (int) $v,
				);
			}
		}
		if ( $bps ) {
			$out['breakpoints'] = $bps;
		}

		// Custom CSS written for Elementor (site-wide or on widgets) uses its global color variables: they stay
		// defined, pointing at the imported Design System colors.
		$alias = array();
		foreach ( array( 'system_colors', 'custom_colors' ) as $list ) {
			foreach ( (array) ( $k[ $list ] ?? array() ) as $c ) {
				if ( is_array( $c ) && preg_match( '/^[A-Za-z0-9_-]+$/', (string) ( $c['_id'] ?? '' ) ) ) {
					$alias[] = '--e-global-color-' . $c['_id'] . ':var(--uncoder-c-' . Converter::color_id( (string) $c['_id'] ) . ')';
				}
			}
		}
		// … and its global font variables (--e-global-typography-{id}-font-size…), from the imported text styles.
		$props = array( 'font-family' => 'ff', 'font-size' => 'fs', 'font-weight' => 'fw', 'text-transform' => 'tt', 'font-style' => 'fst', 'text-decoration' => 'td', 'line-height' => 'lh', 'letter-spacing' => 'ls' );
		foreach ( array( 'system_typography', 'custom_typography' ) as $list ) {
			foreach ( (array) ( $k[ $list ] ?? array() ) as $t ) {
				if ( is_array( $t ) && isset( $by_id[ (string) ( $t['_id'] ?? '' ) ] ) && preg_match( '/^[A-Za-z0-9_-]+$/', (string) $t['_id'] ) ) {
					foreach ( $props as $prop => $short ) {
						$alias[] = '--e-global-typography-' . $t['_id'] . '-' . $prop . ':var(--uncoder-t-' . Converter::typo_id( (string) $t['_id'] ) . '-' . $short . ')';
					}
				}
			}
		}
		// Uncoder Elements' "Blog Typography" (Site Settings → Typography): a global text style for each heading and the
		// text inside post content, kept as site CSS on .entry-content like Elementor prints it (Uncoder's Post Content
		// has that class, and the variables above are defined). The add-on wraps only single posts in .entry-content,
		// so pages keep the Site Settings headings.
		$blog = array();
		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ) as $tag ) {
			$ref = Converter::global_id( (string) ( $globals[ 'uncoder_kit_settings_blog_typography_' . $tag . '_typography' ] ?? '' ), 'typography' );
			if ( '' === $ref || ! isset( $by_id[ $ref ] ) || ! preg_match( '/^[A-Za-z0-9_-]+$/', $ref ) ) {
				continue;
			}
			$decl = array();
			foreach ( array_keys( $props ) as $prop ) {
				$decl[] = $prop . ':var(--e-global-typography-' . $ref . '-' . $prop . ')';
			}
			$blog[] = ( 'p' === $tag ? '.single .entry-content, .single .entry-content p' : '.single .entry-content ' . $tag ) . '{' . implode( ';', $decl ) . '}';
		}
		// Elementor colors every link of the site (WooCommerce and theme pages too) with the Site Settings link colors;
		// Uncoder's apply inside its own content. As low as a plain "a" rule (the state inside :where() too), so any
		// other link style wins: menus, buttons, widget link colours.
		foreach ( array( 'link_normal_color' => '', 'link_hover_color' => ':hover' ) as $key => $state ) {
			$link = $color( $key );
			if ( '' !== $link ) {
				$blog[] = ':where(body.uncoder-kit) a:where(:not(.uncoder-btn)' . $state . '){color:' . $link . '}';
			}
		}
		$css = Converter::css_locations( trim( (string) ( $k['custom_css'] ?? '' ) ) );
		if ( $blog ) {
			$css = trim( implode( "\n", $blog ) . "\n" . $css );
		}
		if ( $alias ) {
			$css = trim( ':root{' . implode( ';', $alias ) . "}\n" . $css );
		}
		if ( '' !== $css ) {
			$out['custom_css'] = $css;
		}
		return $out;
	}

	/**
	 * What the kit holds, for the admin screen.
	 *
	 * @param array<string,mixed> $k Elementor kit settings.
	 * @return array<string,int>
	 */
	public static function summary( array $k ): array {
		return array(
			'colors' => count( (array) ( $k['system_colors'] ?? array() ) ) + count( (array) ( $k['custom_colors'] ?? array() ) ),
			'fonts'  => count( (array) ( $k['system_typography'] ?? array() ) ) + count( (array) ( $k['custom_typography'] ?? array() ) ),
		);
	}
}
