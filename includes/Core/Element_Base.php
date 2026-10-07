<?php
/**
 * Base class for every element (containers and widgets).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Declares controls once; the schema drives the inspector, sanitizer, CSS generator and MCP validator.
 *
 * Control definition keys (all optional except type):
 * - type        control type (text, textarea, wysiwyg, number, slider, dimensions, select, choose, switch, color,
 *               font, media, gallery, icon, url, repeater, multiselect, code, heading, notice, divider)
 *               or group (typography, background, border, box_shadow, text_shadow, css_filters, transform, query)
 * - label, description, placeholder, default, options, size_units, range, min, max, step
 * - responsive  true → accepts key_tablet / key_mobile variants
 * - selectors   [ '{{WRAPPER}} .x' => 'color: {{VALUE}};' ] (non-group) — placeholders VALUE, SIZE, UNIT, URL, TOP…
 * - selector    '{{WRAPPER}} .x' (groups)
 * - selectors_dictionary [ 'left' => 'flex-start' ]
 * - condition   [ 'other' => 'value', 'other!' => '' ]
 * - dynamic     true → accepts a dynamic tag
 * - inline      true → editable directly on the canvas (element must print data-uncoder-inline="key")
 * - render      'css' → changing it never needs a server re-render (auto when selectors/selector exist)
 * - ai          short hint for AI clients
 * - css_default true → emit CSS for the default value too
 */
abstract class Element_Base {

	/** @var array<string, array<string,mixed>>|null */
	private ?array $controls = null;

	/** @var array<string, array<string,mixed>> */
	private array $sections = array();

	private ?string $current_section = null;

	/** @var array<string,mixed>|null */
	private ?array $current_tabs = null;

	private ?string $current_tab = null;

	/** @var array<string, array<string,string>> */
	private array $ui_tabs = array();

	abstract public function name(): string;

	abstract public function title(): string;

	public function icon(): string {
		return 'box';
	}

	public function category(): string {
		return 'basic';
	}

	/**
	 * @return string[]
	 */
	public function keywords(): array {
		return array();
	}

	/** One-sentence description used by the widget panel tooltip and AI clients. */
	public function description(): string {
		return '';
	}

	public function is_container(): bool {
		return false;
	}

	/**
	 * Nested widgets (tabs, accordion…) keep containers as children, one per item.
	 * Return [ 'items' => repeater key ] or null.
	 *
	 * @return array<string,string>|null
	 */
	public function nested(): ?array {
		return null;
	}

	/**
	 * Front-end JS modules (assets/build/frontend/modules/{name}.js) required by this element.
	 *
	 * @return string[]
	 */
	public function frontend_scripts(): array {
		return array();
	}

	/**
	 * Front-end CSS modules (assets/build/frontend/widgets/{name}.css) required by this element.
	 *
	 * @return string[]
	 */
	public function frontend_styles(): array {
		return array();
	}

	/**
	 * "static": output depends only on settings. "dynamic": depends on context (current post, query, user)
	 * so the editor re-renders it when the preview context changes.
	 */
	public function render_mode(): string {
		return 'static';
	}

	public function has_common_controls(): bool {
		return true;
	}

	/**
	 * Default settings for a newly inserted element (only values the user sees immediately).
	 *
	 * @return array<string,mixed>
	 */
	public function preset(): array {
		return array();
	}

	abstract protected function register_controls(): void;

	/* ------------------------------------------------------------------ Registration API */

	/**
	 * @param array<string,mixed> $args label, tab (content|style|advanced), condition.
	 */
	public function start_section( string $id, array $args = array() ): void {
		$this->current_section   = $id;
		$this->sections[ $id ] = array(
			'id'        => $id,
			'label'     => $args['label'] ?? $id,
			'tab'       => $args['tab'] ?? 'content',
			'condition' => $args['condition'] ?? null,
			'open'      => $args['open'] ?? null,
			'controls'  => array(),
		);
	}

	public function end_section(): void {
		$this->current_section = null;
	}

	public function start_tabs( string $id ): void {
		$this->current_tabs    = array( 'id' => $id );
		$this->ui_tabs[ $id ]  = array();
		$this->sections[ $this->current_section ?? '_' ]['controls'][] = '@tabs:' . $id;
	}

	public function start_tab( string $id, string $label ): void {
		if ( null === $this->current_tabs ) {
			return;
		}
		$this->ui_tabs[ $this->current_tabs['id'] ][ $id ] = $label;
		$this->current_tab                                  = $id;
	}

	public function end_tab(): void {
		$this->current_tab = null;
	}

	public function end_tabs(): void {
		$this->current_tabs = null;
		$this->current_tab  = null;
	}

	/**
	 * @param array<string,mixed> $args Control definition.
	 */
	public function add_control( string $key, array $args ): void {
		if ( null === $this->controls ) {
			$this->controls = array();
		}
		$args['section'] = $this->current_section;
		$args['tab']     = $this->sections[ $this->current_section ]['tab'] ?? 'content';
		if ( null !== $this->current_tabs && null !== $this->current_tab ) {
			$args['ui_tab'] = array( $this->current_tabs['id'], $this->current_tab );
		}
		if ( ! isset( $args['render'] ) && ( ! empty( $args['selectors'] ) || ! empty( $args['selector'] ) ) ) {
			$args['render'] = 'css';
		}
		$this->controls[ $key ] = $args;
		if ( null !== $this->current_section && null === $this->current_tabs ) {
			$this->sections[ $this->current_section ]['controls'][] = $key;
		} elseif ( null !== $this->current_tabs ) {
			$this->sections[ $this->current_section ]['tab_controls'][ $this->current_tabs['id'] ][ $this->current_tab ][] = $key;
		}
	}

	/**
	 * @param array<string,mixed> $args Control definition.
	 */
	public function add_responsive_control( string $key, array $args ): void {
		$args['responsive'] = true;
		$this->add_control( $key, $args );
	}

	/**
	 * Adds a group control (typography, background, border, box_shadow…).
	 *
	 * @param array<string,mixed> $args Must include `type` and usually `selector`.
	 */
	public function add_group( string $key, array $args ): void {
		$this->add_control( $key, $args );
	}

	/**
	 * @param array<string,mixed> $args Partial definition merged into the control.
	 */
	public function update_control( string $key, array $args ): void {
		$this->ensure();
		if ( isset( $this->controls[ $key ] ) ) {
			$this->controls[ $key ] = array_merge( $this->controls[ $key ], $args );
		}
	}

	public function remove_control( string $key ): void {
		$this->ensure();
		unset( $this->controls[ $key ] );
		foreach ( $this->sections as &$section ) {
			$section['controls'] = array_values( array_diff( $section['controls'], array( $key ) ) );
		}
	}

	private function ensure(): void {
		if ( null !== $this->controls ) {
			return;
		}
		$this->controls = array();
		$this->register_controls();
		if ( $this->has_common_controls() ) {
			Common_Controls::register( $this );
		}
		/**
		 * Lets extensions add controls to any element.
		 *
		 * @param Element_Base $element Element.
		 */
		do_action( 'uncoder_wb/element/controls', $this );
		do_action( 'uncoder_wb/element/' . $this->name() . '/controls', $this );
	}

	/**
	 * @return array<string, array<string,mixed>>
	 */
	final public function get_controls(): array {
		$this->ensure();
		return $this->controls;
	}

	final public function get_control( string $key ): ?array {
		$this->ensure();
		return $this->controls[ $key ] ?? null;
	}

	/**
	 * @return array<string,mixed> key => default (only declared defaults).
	 */
	public function defaults(): array {
		$out = array();
		foreach ( $this->get_controls() as $key => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$out[ $key ] = $control['default'];
			}
		}
		return $out;
	}

	/**
	 * Settings saved by an older version in today's form (merged or renamed settings). Runs when a document
	 * is loaded (Document::elements()) and when settings are sanitized (Tree), so the editor, the page and AI
	 * clients all see the same values.
	 *
	 * @param array<string,mixed> $settings Saved settings.
	 * @return array<string,mixed>
	 */
	public function upgrade_settings( array $settings ): array {
		return $settings;
	}

	/**
	 * Settings with defaults applied (saved values win).
	 *
	 * @param array<string,mixed> $settings Saved settings.
	 * @return array<string,mixed>
	 */
	public function effective_settings( array $settings ): array {
		return array_merge( $this->defaults(), $settings );
	}

	/**
	 * Schema for the editor and AI clients.
	 *
	 * @return array<string,mixed>
	 */
	public function schema(): array {
		$registry = Plugin::instance()->controls();
		$controls = array();
		$inline   = array();
		foreach ( $this->get_controls() as $key => $control ) {
			$controls[ $key ] = $registry->export_control( $control );
			if ( ! empty( $control['inline'] ) ) {
				$inline[] = $key;
			}
		}
		$sections = array();
		foreach ( $this->sections as $section ) {
			if ( '_' === $section['id'] ) {
				continue;
			}
			$sections[] = $section;
		}
		return array(
			'name'        => $this->name(),
			'title'       => $this->title(),
			'icon'        => $this->icon(),
			'category'    => $this->category(),
			'keywords'    => $this->keywords(),
			'description' => $this->description(),
			'container'   => $this->is_container(),
			'nested'      => $this->nested(),
			'render'      => $this->render_mode(),
			'sections'    => $sections,
			'controls'    => $controls,
			'ui_tabs'     => $this->ui_tabs,
			'inline'      => $inline,
			'preset'      => $this->preset(),
		);
	}
}
