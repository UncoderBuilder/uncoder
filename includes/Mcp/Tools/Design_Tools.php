<?php
/**
 * Design System (design system) tools.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Fonts;
use Uncoder\Builder\Mcp\Call;
use Uncoder\Builder\Mcp\Registry;
use Uncoder\Builder\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * get_design_system, update_design_system, revert_design_system.
 */
final class Design_Tools {

	public function register( Registry $r ): void {
		$r->add(
			array(
				'name'        => 'get_design_system',
				'title'       => 'Get design system',
				'description' => 'The Design System: global colors, fonts, text styles (typography presets), buttons, form fields, layout (container width, gutters, section spacing), theme style and breakpoints, plus how to reference them in element settings and a contrast report.',
				'scope'       => 'read',
				'annotations' => array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'callback'    => array( $this, 'get' ),
			)
		);

		$list = static fn( string $desc, array $props, bool $map = false ) => array(
			'type'        => $map ? array( 'array', 'object' ) : 'array',
			'description' => $desc,
			'items'       => array(
				'type'       => 'object',
				'properties' => $props,
				'required'   => array( 'id' ),
			),
		);

		$r->add(
			array(
				'name'        => 'update_design_system',
				'title'       => 'Update design system',
				'description' => 'Merges changes into the Design System; every page updates at once. Lists (colors, fonts, typography) merge by id, sections (buttons, layout, forms, theme) merge by key. Set the brand here BEFORE building pages. Returns warnings for invalid values and a contrast report. Undo with revert_design_system. See get_build_guide("design-system").',
				'scope'       => 'design',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'colors'     => $list(
							'Colors: [{"id":"primary","name":"Primary","value":"#1f4d3a","dark":"#7fd1a8"}] (or a map {"primary":"#1f4d3a"}). Standard ids: primary, secondary, accent, heading, text, muted, surface, border, white, black. "dark" is the value in dark mode (settings.color_scheme "toggle" or "auto"): give heading/text/surface/border/background colors a dark value.',
							array(
								'id'    => array( 'type' => 'string' ),
								'name'  => array( 'type' => 'string' ),
								'value' => array( 'type' => 'string' ),
								'dark'  => array( 'type' => 'string' ),
							),
							true
						),
						'fonts'      => $list(
							'Font roles: [{"id":"heading","family":"Fraunces"},{"id":"body","family":"Inter"}] (or a map {"heading":"Fraunces"}). Any Google Font by name, or one of the site\'s custom_fonts (get_design_system).',
							array(
								'id'     => array( 'type' => 'string' ),
								'name'   => array( 'type' => 'string' ),
								'family' => array( 'type' => 'string' ),
							),
							true
						),
						'typography' => $list(
							'Text styles: [{"id":"h1","value":{"size":"56px","size_mobile":"36px","weight":"700","line_height":"1.1","letter_spacing":"-1px"}}]. Ids: display, h1–h6, lead, body, small, eyebrow, button. value keys: family, size, weight, line_height, letter_spacing, transform, style, decoration (+ _tablet/_mobile).',
							array(
								'id'    => array( 'type' => 'string' ),
								'name'  => array( 'type' => 'string' ),
								'value' => array( 'type' => 'object' ),
							)
						),
						'buttons'    => array(
							'type'        => 'object',
							'description' => 'Default button style: color, background, hover_color, hover_background, padding ("14px 28px"), radius ("999px"), border, hover_border_color, shadow, typography ({"preset":"button"}), hover_effect (site-wide hover animation for buttons without their own: "" | "lift" | "grow" | "shrink" | "pulse" | "shine" | "arrow" | "fill" | "fill-up" | "underline" | "flip" = text roll), hover_fill (colour of the fill effects).',
						),
						'layout'     => array(
							'type'        => 'object',
							'description' => 'container_width ("1200px"), gutter, gutter_mobile, gap, section_space (+ _tablet/_mobile).',
						),
						'forms'      => array(
							'type'        => 'object',
							'description' => 'Form field style: label_color, field_color, field_background, field_border_color, focus_border_color, field_radius, field_padding, label_typography, field_typography.',
						),
						'theme'      => array(
							'type'        => 'object',
							'description' => 'Page-wide defaults: body_color, heading_color, link_color, link_hover_color, background, background_image (a texture or pattern behind sections without their own background: attachment id, URL or {"id","url"}), background_size ("auto" = tile, "cover"), optical_sizing ("" = auto, "none" = fonts with an optical-size axis such as Inter keep their text cut at large sizes, matching designs made with the static font files).',
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => 'Site behaviour: smooth_scroll (bool, default true — smooth #anchor scrolling that stops below a sticky header), back_to_top (bool), back_to_top_position ("right" | "left"), font_delivery ("google" | "local" | "none"), color_scheme ("light" | "toggle" = dark mode with a switch | "auto" = follows the device), preloader ("" | "spinner" | "bar" | "logo"; covers the page until it loads — use sparingly), preloader_once (bool, default true: only the first page of a visit), page_transition ("" | "fade" | "slide"; View Transitions between pages, no delay). The gap above anchor targets is layout.scroll_offset ("16px").',
						),
						'classes'    => $list(
							'Global classes — shared styles for one element type, applied with the element setting "_classes": ["card"]. [{"id":"card","name":"Card","type":"container","settings":{"_padding":"32px","background":{"type":"classic","color":"var(--uncoder-c-surface)"},"radius":"16px","shadow":{…}}}]. settings take the element type\'s style/layout keys (anything that becomes CSS). A class with the same id is replaced as a whole. Elements still override class values with their own settings.',
							array(
								'id'       => array( 'type' => 'string' ),
								'name'     => array( 'type' => 'string' ),
								'type'     => array( 'type' => 'string' ),
								'settings' => array( 'type' => 'object' ),
							)
						),
						'custom_css' => array(
							'type'        => 'string',
							'description' => 'Site-wide custom CSS (replaces the previous value). Prefer settings; use only for what controls cannot express.',
						),
						'variables'   => $list(
							'Design variables (sizes): [{"id":"space-md","name":"Space md","group":"spacing|size|radius|other","value":"24px"}]. Printed as --uncoder-v-<id>; use them in size fields ({"size":"var(--uncoder-v-space-md)","unit":"custom"}) and per side in spacing / radius fields. Values may be fluid: "clamp(1rem, 3vw, 2rem)", "max(40px, calc(50vw - 600px))".',
							array(
								'id'    => array( 'type' => 'string' ),
								'name'  => array( 'type' => 'string' ),
								'group' => array( 'type' => 'string' ),
								'value' => array( 'type' => 'string' ),
							)
						),
						'breakpoints' => array(
							'type'        => array( 'array', 'object' ),
							'description' => 'Responsive breakpoints (max widths in px): [{"id":"tablet","value":1024},{"id":"mobile","value":767}] or {"tablet":{"value":1024}}. Optional ones: laptop, tablet_extra, mobile_extra (max) and widescreen (min) — turn one on with "enabled":true. Changing a width keeps whether it is on.',
						),
						'remove'     => array(
							'type'        => 'object',
							'description' => 'Remove items by id: {"colors":["tertiary"],"typography":["quote"],"classes":["card"]}.',
						),
					),
				),
				'callback'    => array( $this, 'update' ),
			)
		);

		$r->add(
			array(
				'name'        => 'revert_design_system',
				'title'       => 'Revert design system',
				'description' => 'Restores the Design System to the version before the last change, or to a specific snapshot. Call with list:true to see snapshots.',
				'scope'       => 'design',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'snapshot' => array( 'type' => 'string', 'description' => 'Snapshot id (default: the latest).' ),
						'list'     => array( 'type' => 'boolean', 'description' => 'Only list the available snapshots.' ),
					),
				),
				'callback'    => array( $this, 'revert' ),
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get(): array {
		$kit = Plugin::instance()->kit();
		$all = $kit->export();
		unset( $all['settings'] );
		$custom = array();
		foreach ( \Uncoder\Builder\Site\Custom_Fonts::catalog() as $family => $font ) {
			$custom[] = array(
				'family'   => $family,
				'category' => $font['c'],
				'weights'  => $font['w'],
			);
		}
		return array(
			'kit'          => $all,
			'custom_fonts' => $custom,
			'usage'        => array(
				'colors'     => 'In any color setting use "var(--uncoder-c-<id>)" (e.g. "var(--uncoder-c-primary)") so the page follows the kit. Plain hex/rgba values are allowed for one-offs.',
				'fonts'      => 'In typography family use "var(--uncoder-f-heading)" or "var(--uncoder-f-body)". custom_fonts are the brand fonts uploaded to this site (Uncoder → Design System → Custom fonts): use them by family name in the kit fonts, preferring them over Google Fonts, and only with the weights listed.',
				'typography' => 'Apply a text style with {"preset":"h2"} in a typography setting; override single keys next to it, e.g. {"preset":"h2","size_mobile":"26px"}.',
				'layout'     => 'Boxed containers use --uncoder-container; sections use --uncoder-section-space padding by default.',
			),
			'contrast'     => $this->contrast_report( $kit->all() ),
			'snapshots'    => array_slice( $kit->snapshots(), 0, 5 ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function update( array $a, Call $call ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'forbidden', 'Changing the design system requires the edit_theme_options capability.' );
		}
		$kit     = Plugin::instance()->kit();
		$errors  = array();
		$known   = array( 'colors', 'fonts', 'typography', 'buttons', 'layout', 'forms', 'theme', 'custom_css', 'settings', 'classes', 'variables', 'breakpoints', 'remove' );
		$unknown = array_diff( array_keys( $a ), $known );
		if ( $unknown ) {
			// Never drop input silently: the caller would believe it was saved.
			$errors[] = sprintf( 'Ignored unknown key(s): %s. Allowed: %s.', implode( ', ', $unknown ), implode( ', ', $known ) );
		}
		$input = array_intersect_key( $a, array_flip( array_diff( $known, array( 'remove' ) ) ) );
		// Breakpoints may come as a list [{"id":"tablet","value":1199}] or a map {"tablet":{"value":1199}}.
		if ( isset( $input['breakpoints'] ) && is_array( $input['breakpoints'] ) && isset( $input['breakpoints'][0] ) ) {
			$map = array();
			foreach ( $input['breakpoints'] as $bp ) {
				if ( is_array( $bp ) && isset( $bp['id'] ) ) {
					$map[ (string) $bp['id'] ] = array_diff_key( $bp, array( 'id' => 1 ) );
				}
			}
			$input['breakpoints'] = $map;
		}
		if ( isset( $input['breakpoints'] ) && is_array( $input['breakpoints'] ) ) {
			foreach ( array_keys( $input['breakpoints'] ) as $id ) {
				if ( ! isset( \Uncoder\Builder\Core\Breakpoints::DEFAULTS[ $id ] ) ) {
					$errors[] = sprintf( 'breakpoints: unknown id "%s" (allowed: %s).', $id, implode( ', ', array_keys( \Uncoder\Builder\Core\Breakpoints::DEFAULTS ) ) );
				}
			}
		}
		// Accept {"primary":"#123456"} maps for convenience.
		foreach ( array( 'colors', 'fonts' ) as $list ) {
			if ( isset( $input[ $list ] ) && is_array( $input[ $list ] ) && ! isset( $input[ $list ][0] ) && $input[ $list ] ) {
				$items = array();
				foreach ( $input[ $list ] as $id => $value ) {
					$items[] = is_array( $value ) ? array_merge( array( 'id' => $id ), $value ) : array( 'id' => $id, ( 'colors' === $list ? 'value' : 'family' ) => $value );
				}
				$input[ $list ] = $items;
			}
		}
		// {"id":"text","dark":"#e6e6e6"} only adds a dark value: keep the current light one.
		if ( isset( $input['colors'] ) && is_array( $input['colors'] ) ) {
			$current = array_column( (array) $kit->get( 'colors', array() ), null, 'id' );
			foreach ( $input['colors'] as $i => $color ) {
				if ( is_array( $color ) && ! isset( $color['value'] ) && isset( $current[ $color['id'] ?? '' ] ) ) {
					$input['colors'][ $i ] = array_merge( $current[ $color['id'] ], $color );
				}
			}
		}
		if ( isset( $input['theme'] ) && is_array( $input['theme'] ) ) {
			$theme = array();
			foreach ( array( 'enabled', 'body_color', 'heading_color', 'link_color', 'link_hover_color', 'background' ) as $key ) {
				if ( array_key_exists( $key, $input['theme'] ) ) {
					$theme[ $key ] = 'enabled' === $key ? (bool) $input['theme'][ $key ] : $this->color( $input['theme'][ $key ], 'theme.' . $key, $errors );
				}
			}
			if ( array_key_exists( 'background_image', $input['theme'] ) ) {
				$image = $input['theme']['background_image'];
				$image = is_numeric( $image ) ? array( 'id' => (int) $image ) : $image;
				$theme['background_image'] = Plugin::instance()->controls()->get( 'media' )->normalize( $image, array() );
			}
			if ( isset( $input['theme']['background_size'] ) ) {
				$theme['background_size'] = 'cover' === $input['theme']['background_size'] ? 'cover' : 'auto';
			}
			if ( isset( $input['theme']['optical_sizing'] ) ) {
				$theme['optical_sizing'] = 'none' === $input['theme']['optical_sizing'] ? 'none' : '';
			}
			unset( $input['theme'] );
		}
		if ( isset( $input['custom_css'] ) && ! current_user_can( 'unfiltered_html' ) ) {
			$errors[] = 'custom_css ignored: your account cannot add unfiltered CSS.';
			unset( $input['custom_css'] );
		}
		$clean = $kit->sanitize( $input, 'normalize', $errors );
		if ( isset( $theme ) ) {
			$clean['theme'] = array_filter( $theme, static fn( $v ) => null !== $v );
		}
		foreach ( (array) ( $clean['fonts'] ?? array() ) as $font ) {
			if ( '' !== $font['family'] && 0 !== strpos( $font['family'], 'var(' ) && ! Fonts::is_google( $font['family'] ) && ! isset( Fonts::SYSTEM[ $font['family'] ] ) && ! \Uncoder\Builder\Site\Custom_Fonts::get( $font['family'] ) ) {
				$call->warn( sprintf( 'Font "%s" is neither a Google Font nor one of this site\'s custom fonts (get_design_system → custom_fonts). Check spelling with search_fonts.', $font['family'] ) );
			}
		}
		$remove = array_filter( (array) ( $a['remove'] ?? array() ), 'is_array' );
		if ( ! $clean && ! $remove ) {
			return new WP_Error( 'invalid', 'Nothing to update.' . ( $errors ? ' Problems: ' . implode( ' ', $errors ) : ' Pass colors, fonts, typography, buttons, layout, forms, theme or custom_css.' ) );
		}

		$call->snapshot_kit();
		if ( $clean ) {
			$kit->update( $clean, '', false );
		}
		if ( $remove ) {
			$protected = array( 'primary', 'secondary', 'accent', 'heading', 'text', 'body', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
			foreach ( $remove as $list => $ids ) {
				$remove[ $list ] = array_values( array_diff( array_map( 'sanitize_key', $ids ), $protected ) );
			}
			$kit->remove( $remove );
		}
		foreach ( $errors as $e ) {
			$call->warn( $e );
		}
		$call->summary = 'Updated Design System: ' . implode( ', ', array_keys( $clean + $remove ) );

		$after = $kit->all();
		return array(
			'updated'  => array_keys( $clean + $remove ),
			'colors'   => array_column( $after['colors'], 'value', 'id' ),
			'fonts'    => array_column( $after['fonts'], 'family', 'id' ),
			'contrast' => $this->contrast_report( $after ),
			'next'     => 'Pages already use the kit through var(--uncoder-c-*) colors and {"preset":…} text styles, so no page edits are needed.',
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function revert( array $a, Call $call ) {
		$kit = Plugin::instance()->kit();
		if ( ! empty( $a['list'] ) ) {
			return array( 'snapshots' => $kit->snapshots() );
		}
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'forbidden', 'Changing the design system requires the edit_theme_options capability.' );
		}
		$id = sanitize_text_field( (string) ( $a['snapshot'] ?? '' ) );
		$id = 0 === strpos( $id, 'kit:' ) ? substr( $id, 4 ) : $id;
		if ( ! $kit->snapshots() ) {
			return new WP_Error( 'not_found', 'There is no earlier version of the design system.' );
		}
		if ( ! $kit->restore( $id ) ) {
			return new WP_Error( 'not_found', 'No design system snapshot "' . $id . '". Call with list:true.' );
		}
		$call->summary = 'Reverted Design System' . ( '' !== $id ? ' to ' . $id : '' );
		$after         = $kit->all();
		return array(
			'reverted' => '' !== $id ? $id : 'latest',
			'colors'   => array_column( $after['colors'], 'value', 'id' ),
			'fonts'    => array_column( $after['fonts'], 'family', 'id' ),
			'note'     => 'The state before this revert was saved too; call revert_design_system again to undo it.',
		);
	}

	/**
	 * @param mixed    $value  Raw color.
	 * @param string[] $errors Errors.
	 */
	private function color( $value, string $path, array &$errors ): ?string {
		if ( '' === $value || null === $value ) {
			return '';
		}
		$clean = Plugin::instance()->controls()->get( 'color' )->normalize( $value, array() );
		if ( null === $clean || '' === $clean ) {
			$errors[] = $path . ': invalid color.';
			return null;
		}
		return (string) $clean;
	}

	/**
	 * Contrast of the most common text/background pairs.
	 *
	 * @param array<string,mixed> $kit Kit.
	 * @return array<int, array<string,mixed>>
	 */
	private function contrast_report( array $kit ): array {
		$colors  = array_column( (array) $kit['colors'], 'value', 'id' );
		$resolve = static function ( $v ) use ( $colors ) {
			$v = (string) $v;
			for ( $i = 0; $i < 3 && preg_match( '/^var\(--uncoder-c-([a-z0-9_\-]+)\)$/', $v, $m ); $i++ ) {
				$v = (string) ( $colors[ $m[1] ] ?? '' );
			}
			return $v;
		};
		$bg      = '' !== (string) ( $kit['theme']['background'] ?? '' ) ? $resolve( $kit['theme']['background'] ) : '#ffffff';
		$pairs   = array(
			array( 'Body text on page background', $resolve( $kit['theme']['body_color'] ?? 'var(--uncoder-c-text)' ), $bg, 4.5 ),
			array( 'Headings on page background', $resolve( $kit['theme']['heading_color'] ?? 'var(--uncoder-c-heading)' ), $bg, 3.0 ),
			array( 'Muted text on page background', $resolve( 'var(--uncoder-c-muted)' ), $bg, 4.5 ),
			array( 'Body text on surface', $resolve( 'var(--uncoder-c-text)' ), $resolve( 'var(--uncoder-c-surface)' ), 4.5 ),
			array( 'Button text on button background', $resolve( $kit['buttons']['color'] ?? '#ffffff' ), $resolve( $kit['buttons']['background'] ?? 'var(--uncoder-c-primary)' ), 4.5 ),
			array( 'Links on page background', $resolve( $kit['theme']['link_color'] ?? 'var(--uncoder-c-primary)' ), $bg, 4.5 ),
			array( 'White text on secondary (dark sections)', '#ffffff', $resolve( 'var(--uncoder-c-secondary)' ), 4.5 ),
		);
		$out     = array();
		foreach ( $pairs as $p ) {
			$fg_rgb = Audit::rgb( $p[1] );
			$bg_rgb = Audit::rgb( $p[2] );
			if ( ! $fg_rgb || ! $bg_rgb ) {
				continue;
			}
			$ratio = round( Audit::contrast( $fg_rgb, $bg_rgb ), 2 );
			$out[] = array(
				'pair'  => $p[0],
				'fg'    => $p[1],
				'bg'    => $p[2],
				'ratio' => $ratio,
				'ok'    => $ratio >= $p[3],
			);
		}
		return $out;
	}
}
