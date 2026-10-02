<?php
/**
 * Language switcher widget (WPML / Polylang).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Site\Multilingual;

defined( 'ABSPATH' ) || exit;

/**
 * Links to the current page in the site's other languages, as a row of links or a dropdown (a native
 * <details>, so it works without JavaScript). Needs WPML or Polylang; the editor shows sample languages
 * until one is set up.
 */
class Language_Switcher extends Widget_Base {

	public function name(): string {
		return 'language-switcher';
	}

	public function title(): string {
		return __( 'Language Switcher', 'uncoder' );
	}

	public function icon(): string {
		return 'languages';
	}

	public function category(): string {
		return 'site';
	}

	public function keywords(): array {
		return array( 'language', 'translation', 'multilingual', 'wpml', 'polylang', 'switcher', 'locale' );
	}

	public function description(): string {
		return __( 'Links to this page in the other languages of a WPML or Polylang site, as links or a dropdown.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Languages', 'uncoder' ) ) );
		if ( '' === Multilingual::plugin() ) {
			$this->add_control(
				'plugin_notice',
				array(
					'type'  => 'notice',
					'label' => __( 'Install WPML or Polylang and add your languages: this widget then lists them. Until then the editor shows sample languages.', 'uncoder' ),
				)
			);
		}
		$this->add_control(
			'layout',
			array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'uncoder' ),
				'default' => 'inline',
				'options' => array(
					'inline'   => __( 'Links in a row', 'uncoder' ),
					'dropdown' => __( 'Dropdown', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'label_type',
			array(
				'type'    => 'select',
				'label'   => __( 'Show', 'uncoder' ),
				'default' => 'name',
				'options' => array(
					'name'   => __( 'Name in the current language', 'uncoder' ),
					'native' => __( 'Native name (Deutsch, Français…)', 'uncoder' ),
					'code'   => __( 'Code (EN, DE…)', 'uncoder' ),
					'none'   => __( 'Nothing (flags only)', 'uncoder' ),
				),
			)
		);
		$this->add_control( 'flags', array( 'type' => 'switch', 'label' => __( 'Flags', 'uncoder' ) ) );
		$this->add_control( 'hide_current', array( 'type' => 'switch', 'label' => __( 'Hide the current language', 'uncoder' ), 'condition' => array( 'layout' => 'inline' ) ) );
		$this->add_control(
			'hide_missing',
			array(
				'type'        => 'switch',
				'label'       => __( 'Hide languages without a translation', 'uncoder' ),
				'description' => __( 'Otherwise they link to that language’s home page.', 'uncoder' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => array(
					'flex-start' => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center'     => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'flex-end'   => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'justify-content: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_links', array( 'label' => __( 'Links', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-language-switcher__link' ) );
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-lang-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-language-switcher__link' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-language-switcher__link:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'current_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Current language color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-language-switcher__link[aria-current]' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'menu_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Dropdown background', 'uncoder' ),
				'condition' => array( 'layout' => 'dropdown' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-language-switcher__menu' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'flag_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Flag width', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'condition'  => array( 'flags' => true ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-lang-flag: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * @return array<int, array{code:string, name:string, native:string, url:string, flag:string, current:bool, missing:bool}>
	 */
	private static function sample(): array {
		$rows = array();
		foreach ( array( array( 'en', 'English', 'English' ), array( 'de', 'German', 'Deutsch' ), array( 'fr', 'French', 'Français' ) ) as $i => $l ) {
			$rows[] = array(
				'code'    => $l[0],
				'name'    => $l[1],
				'native'  => $l[2],
				'url'     => '#',
				'flag'    => '',
				'current' => 0 === $i,
				'missing' => false,
			);
		}
		return $rows;
	}

	/**
	 * @param array{code:string, name:string, native:string, url:string, flag:string, current:bool} $l Language.
	 * @param array<string,mixed>                                                              $s Settings.
	 */
	private function link( array $l, array $s ): string {
		$type  = in_array( $s['label_type'] ?? 'name', array( 'name', 'native', 'code', 'none' ), true ) ? $s['label_type'] : 'name';
		$text  = 'code' === $type ? strtoupper( $l['code'] ) : ( 'native' === $type ? $l['native'] : $l['name'] );
		$flag  = ! empty( $s['flags'] ) && '' !== $l['flag'] ? '<img class="uncoder-language-switcher__flag" src="' . esc_url( $l['flag'] ) . '" alt="" width="18" height="12" loading="lazy">' : '';
		$label = 'none' === $type ? '<span class="uncoder-sr-only">' . esc_html( $l['native'] ) . '</span>' : '<span class="uncoder-language-switcher__text">' . esc_html( $text ) . '</span>';
		return '<a' . Utils::attrs(
			array(
				'class'        => 'uncoder-language-switcher__link',
				'href'         => $l['url'],
				'hreflang'     => $l['code'],
				'lang'         => $l['code'],
				'aria-current' => $l['current'] ? 'true' : null,
				'title'        => 'code' === $type || 'none' === $type ? $l['native'] : null,
			)
		) . '>' . $flag . $label . '</a>';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$langs = Multilingual::languages();
		if ( ! $langs ) {
			if ( ! $ctx->editor ) {
				return;
			}
			$langs = self::sample();
		}
		if ( ! empty( $s['hide_missing'] ) ) {
			$langs = array_values( array_filter( $langs, static fn( $l ) => $l['current'] || empty( $l['missing'] ) ) );
		}
		$current  = current( array_filter( $langs, static fn( $l ) => $l['current'] ) );
		$dropdown = 'dropdown' === ( $s['layout'] ?? 'inline' );
		$label    = esc_attr__( 'Language', 'uncoder' );

		if ( $dropdown && $current ) {
			$items = '';
			foreach ( $langs as $l ) {
				$items .= '<li>' . $this->link( $l, $s ) . '</li>';
			}
			$type    = in_array( $s['label_type'] ?? 'name', array( 'code', 'none' ), true ) ? 'code' : ( $s['label_type'] ?? 'name' );
			$summary = 'code' === $type ? strtoupper( $current['code'] ) : ( 'native' === $type ? $current['native'] : $current['name'] );
			echo '<nav class="uncoder-language-switcher uncoder-language-switcher--dropdown" aria-label="' . $label . '"><details class="uncoder-language-switcher__details"><summary class="uncoder-language-switcher__link uncoder-language-switcher__toggle">' . esc_html( $summary ) . '<span class="uncoder-language-switcher__caret" aria-hidden="true"></span></summary><ul class="uncoder-language-switcher__menu">' . $items . '</ul></details></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			return;
		}
		$items = '';
		foreach ( $langs as $l ) {
			if ( $l['current'] && ! empty( $s['hide_current'] ) ) {
				continue;
			}
			$items .= '<li>' . $this->link( $l, $s ) . '</li>';
		}
		echo '<nav class="uncoder-language-switcher uncoder-language-switcher--inline" aria-label="' . $label . '"><ul class="uncoder-language-switcher__list">' . $items . '</ul></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	}
}
