<?php
/**
 * Share Buttons widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Social share links built from the current post (no third-party scripts). Copy link and the
 * native share sheet are progressive enhancements handled by the "share" module.
 */
class Share_Buttons extends Widget_Base {

	/**
	 * Simplified 24×24 brand glyphs (fill, even-odd), drawn for this plugin in the style of Simple Icons.
	 * Also used by the Team Member widget.
	 */
	public const BRAND_PATHS = array(
		'facebook'  => 'M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.33-.04-1.57-.14-2.88-.14C11.9 2 10 3.66 10 6.7v2.8H7v4h3V22h4v-8.5z',
		'x'         => 'M17.75 3h3.07l-6.7 7.66L22 21h-6.17l-4.83-6.32L5.47 21H2.4l7.17-8.19L2 3h6.33l4.37 5.77L17.75 3zm-1.08 16.2h1.7L7.4 4.73H5.58L16.67 19.2z',
		'linkedin'  => 'M2.88 5.5a2.1 2.1 0 1 0 4.2 0 2.1 2.1 0 1 0-4.2 0zM3.2 9h3.6v12H3.2zM9.4 9h3.45v1.64h.05c.48-.91 1.66-1.87 3.41-1.87 3.65 0 4.32 2.4 4.32 5.52V21h-3.6v-5.93c0-1.42-.03-3.24-1.97-3.24-1.98 0-2.28 1.54-2.28 3.13V21H9.4z',
		'whatsapp'  => 'M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.39-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35zM12.04 21.5h-.01a9.47 9.47 0 0 1-4.83-1.32l-.35-.21-3.59.94.96-3.5-.23-.36a9.46 9.46 0 0 1-1.45-5.05c0-5.23 4.26-9.49 9.5-9.49 2.54 0 4.92.99 6.71 2.79a9.43 9.43 0 0 1 2.78 6.71c0 5.24-4.26 9.49-9.49 9.49zm8.08-17.58A11.35 11.35 0 0 0 12.04.58C5.74.58.62 5.7.62 12c0 2.01.53 3.98 1.53 5.71L.52 23.63l6.05-1.59a11.4 11.4 0 0 0 5.46 1.39h.01c6.29 0 11.42-5.12 11.42-11.42 0-3.05-1.19-5.92-3.34-8.08z',
		'telegram'  => 'M12 1.5C6.2 1.5 1.5 6.2 1.5 12S6.2 22.5 12 22.5 22.5 17.8 22.5 12 17.8 1.5 12 1.5zm4.87 7.14-1.72 8.12c-.13.57-.47.71-.95.44l-2.62-1.93-1.26 1.22c-.14.14-.26.26-.53.26l.19-2.67 4.86-4.39c.21-.19-.05-.29-.33-.1l-6 3.78-2.59-.81c-.56-.18-.57-.56.12-.83l10.12-3.9c.47-.17.88.11.71.83z',
		'pinterest' => 'M12 1.5C6.2 1.5 1.5 6.2 1.5 12c0 4.45 2.77 8.25 6.68 9.78-.09-.83-.18-2.1.04-3 .19-.82 1.23-5.22 1.23-5.22s-.31-.63-.31-1.56c0-1.46.85-2.55 1.9-2.55.9 0 1.33.67 1.33 1.48 0 .9-.57 2.25-.87 3.5-.25 1.05.52 1.9 1.55 1.9 1.87 0 3.3-1.97 3.3-4.8 0-2.51-1.8-4.27-4.38-4.27-2.99 0-4.74 2.24-4.74 4.56 0 .9.35 1.87.78 2.4.09.1.1.19.07.3l-.29 1.18c-.05.19-.15.23-.35.14-1.31-.61-2.13-2.52-2.13-4.06 0-3.3 2.4-6.34 6.93-6.34 3.63 0 6.46 2.59 6.46 6.05 0 3.62-2.28 6.53-5.44 6.53-1.06 0-2.06-.55-2.4-1.2l-.65 2.49c-.24.91-.88 2.05-1.31 2.75.99.3 2.03.47 3.12.47 5.8 0 10.5-4.7 10.5-10.5S17.8 1.5 12 1.5z',
		'reddit'    => 'M22.5 12.1a2.3 2.3 0 0 0-3.9-1.63 11.3 11.3 0 0 0-6.07-1.93l1.03-4.86 3.37.72a1.64 1.64 0 1 0 .17-.8l-3.77-.8a.4.4 0 0 0-.48.31l-1.15 5.42a11.3 11.3 0 0 0-6.15 1.93 2.3 2.3 0 1 0-2.53 3.76 4.5 4.5 0 0 0-.05.69c0 3.5 4.08 6.34 9.1 6.34s9.1-2.84 9.1-6.34a4.5 4.5 0 0 0-.05-.69 2.3 2.3 0 0 0 1.38-2.12zM6.9 13.74a1.64 1.64 0 1 1 3.28 0 1.64 1.64 0 0 1-3.28 0zm9.14 4.33a6 6 0 0 1-3.94 1.23 6 6 0 0 1-3.94-1.23.43.43 0 0 1 .6-.6 5.14 5.14 0 0 0 3.33.99 5.15 5.15 0 0 0 3.34-.97.43.43 0 1 1 .6.61zm-.29-2.69a1.64 1.64 0 1 1 0-3.28 1.64 1.64 0 0 1 0 3.28z',
		'instagram' => 'M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm5.25-3.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z',
		'github'    => 'M12 1.5a10.5 10.5 0 0 0-3.32 20.46c.53.1.72-.23.72-.5v-1.8c-2.92.64-3.54-1.4-3.54-1.4-.48-1.22-1.17-1.54-1.17-1.54-.95-.65.07-.64.07-.64 1.06.07 1.61 1.08 1.61 1.08.94 1.6 2.46 1.14 3.06.87.1-.68.37-1.14.66-1.4-2.33-.27-4.78-1.17-4.78-5.19 0-1.14.41-2.08 1.08-2.81-.11-.27-.47-1.33.1-2.78 0 0 .88-.28 2.89 1.08a10 10 0 0 1 5.26 0c2-1.36 2.88-1.08 2.88-1.08.57 1.45.21 2.51.1 2.78.68.73 1.08 1.67 1.08 2.81 0 4.03-2.46 4.92-4.8 5.18.38.33.72.97.72 1.96v2.9c0 .28.19.61.73.5A10.5 10.5 0 0 0 12 1.5z',
		'youtube'   => 'M23 7.2a3 3 0 0 0-2.1-2.12C19.03 4.58 12 4.58 12 4.58s-7.03 0-8.9.5A3 3 0 0 0 1 7.2 31.4 31.4 0 0 0 .5 12c0 1.63.17 3.24.5 4.8a3 3 0 0 0 2.1 2.12c1.87.5 8.9.5 8.9.5s7.03 0 8.9-.5a3 3 0 0 0 2.1-2.12c.33-1.56.5-3.17.5-4.8s-.17-3.24-.5-4.8zM9.75 15.02V8.98L15.5 12l-5.75 3.02z',
		'tiktok'    => 'M16.6 2h-3.35v13.36a2.87 2.87 0 1 1-2.87-2.87c.3 0 .58.05.85.13V9.2a6.26 6.26 0 1 0 5.37 6.2V8.66a8.1 8.1 0 0 0 4.73 1.51V6.84A4.74 4.74 0 0 1 16.6 2z',
	);

	/** Networks that fall back to a Lucide icon. */
	public const LUCIDE = array(
		'email'   => 'mail',
		'copy'    => 'link',
		'native'  => 'share-2',
		'website' => 'globe',
		'phone'   => 'phone',
	);

	public function name(): string {
		return 'share-buttons';
	}

	public function title(): string {
		return __( 'Share Buttons', 'uncoder' );
	}

	public function icon(): string {
		return 'share-2';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'share', 'social', 'facebook', 'linkedin', 'whatsapp', 'copy link' );
	}

	public function description(): string {
		return __( 'Share links for the current page (Facebook, X, LinkedIn, WhatsApp, Telegram, Pinterest, Reddit, email) plus "copy link" and the device share sheet. No third-party scripts.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'share' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	/**
	 * @return array<string,string>
	 */
	public static function networks(): array {
		return array(
			'facebook'  => 'Facebook',
			'x'         => 'X',
			'linkedin'  => 'LinkedIn',
			'whatsapp'  => 'WhatsApp',
			'telegram'  => 'Telegram',
			'pinterest' => 'Pinterest',
			'reddit'    => 'Reddit',
			'email'     => __( 'Email', 'uncoder' ),
			'copy'      => __( 'Copy link', 'uncoder' ),
			'native'    => __( 'Share…', 'uncoder' ),
		);
	}

	/**
	 * Brand or Lucide icon for a network ('' when unknown).
	 *
	 * @param array<string,mixed> $attrs Extra SVG attributes.
	 */
	public static function network_icon( string $network, array $attrs = array() ): string {
		if ( isset( self::BRAND_PATHS[ $network ] ) ) {
			$class = trim( 'uncoder-svg ' . ( $attrs['class'] ?? '' ) );
			unset( $attrs['class'] );
			$base = array(
				'class'       => $class,
				'xmlns'       => 'http://www.w3.org/2000/svg',
				'viewBox'     => '0 0 24 24',
				'fill'        => 'currentColor',
				'aria-hidden' => 'true',
				'focusable'   => 'false',
			);
			return '<svg' . Utils::attrs( array_merge( $base, $attrs ) ) . '><path fill-rule="evenodd" clip-rule="evenodd" d="' . esc_attr( self::BRAND_PATHS[ $network ] ) . '"/></svg>';
		}
		if ( isset( self::LUCIDE[ $network ] ) ) {
			return \Uncoder\Builder\Core\Icons::render( self::LUCIDE[ $network ], $attrs );
		}
		return '';
	}

	protected function register_controls(): void {
		$networks = self::networks();

		$this->start_section( 'content_networks', array( 'label' => __( 'Networks', 'uncoder' ) ) );
		$this->add_control(
			'networks',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Buttons', 'uncoder' ),
				'title_field' => 'network',
				'fields'      => array(
					'network' => array(
						'type'    => 'select',
						'label'   => __( 'Network', 'uncoder' ),
						'default' => 'facebook',
						'options' => $networks,
					),
					'label'   => array(
						'type'        => 'text',
						'label'       => __( 'Custom label', 'uncoder' ),
						'placeholder' => __( 'Network name', 'uncoder' ),
					),
					'color'   => array(
						'type'      => 'color',
						'label'     => __( 'Custom color', 'uncoder' ),
						'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => '--uncoder-share-brand: {{VALUE}}' ),
					),
				),
				'default'     => array(
					array( 'network' => 'facebook' ),
					array( 'network' => 'x' ),
					array( 'network' => 'linkedin' ),
					array( 'network' => 'email' ),
					array( 'network' => 'copy' ),
				),
				'ai'          => 'Rows: {"network":"facebook|x|linkedin|whatsapp|telegram|pinterest|reddit|email|copy|native"}. "native" only appears on devices with a share sheet.',
			)
		);
		$this->add_control(
			'share_source',
			array(
				'type'    => 'select',
				'label'   => __( 'Share', 'uncoder' ),
				'default' => 'current',
				'options' => array(
					'current' => __( 'Current page', 'uncoder' ),
					'custom'  => __( 'Custom URL', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'custom_url',
			array(
				'type'      => 'url',
				'label'     => __( 'URL to share', 'uncoder' ),
				'condition' => array( 'share_source' => 'custom' ),
				'dynamic'   => true,
			)
		);
		$this->add_control(
			'share_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Share text', 'uncoder' ),
				'description' => __( 'Defaults to the page title.', 'uncoder' ),
				'dynamic'     => true,
			)
		);
		$this->add_control(
			'copied_text',
			array(
				'type'    => 'text',
				'label'   => __( '"Copied" feedback', 'uncoder' ),
				'default' => __( 'Link copied', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_layout', array( 'label' => __( 'Layout', 'uncoder' ) ) );
		$this->add_control(
			'view',
			array(
				'type'    => 'select',
				'label'   => __( 'View', 'uncoder' ),
				'default' => 'icon',
				'options' => array(
					'icon'      => __( 'Icon', 'uncoder' ),
					'text'      => __( 'Text', 'uncoder' ),
					'icon-text' => __( 'Icon and text', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'skin',
			array(
				'type'    => 'select',
				'label'   => __( 'Skin', 'uncoder' ),
				'default' => 'solid',
				'options' => array(
					'solid'   => __( 'Solid', 'uncoder' ),
					'soft'    => __( 'Soft tint', 'uncoder' ),
					'outline' => __( 'Outline', 'uncoder' ),
					'minimal' => __( 'Minimal', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'shape',
			array(
				'type'    => 'select',
				'label'   => __( 'Shape', 'uncoder' ),
				'default' => 'rounded',
				'options' => array(
					'square'  => __( 'Square', 'uncoder' ),
					'rounded' => __( 'Rounded', 'uncoder' ),
					'circle'  => __( 'Circle / pill', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'size',
			array(
				'type'    => 'choose',
				'label'   => __( 'Size', 'uncoder' ),
				'default' => 'md',
				'options' => array(
					'sm' => array( 'label' => 'S' ),
					'md' => array( 'label' => 'M' ),
					'lg' => array( 'label' => 'L' ),
				),
			)
		);
		$this->add_control(
			'color_source',
			array(
				'type'    => 'select',
				'label'   => __( 'Colors', 'uncoder' ),
				'default' => 'brand',
				'options' => array(
					'brand'  => __( 'Official brand colors', 'uncoder' ),
					'custom' => __( 'One custom color', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'custom_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'condition' => array( 'color_source' => 'custom' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-share-custom: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'type'                 => 'select',
				'label'                => __( 'Columns', 'uncoder' ),
				'options'              => array(
					''  => __( 'Auto (inline)', 'uncoder' ),
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'selectors_dictionary' => array(
					'1' => 'display:grid;grid-template-columns:repeat(1,minmax(0,1fr))',
					'2' => 'display:grid;grid-template-columns:repeat(2,minmax(0,1fr))',
					'3' => 'display:grid;grid-template-columns:repeat(3,minmax(0,1fr))',
					'4' => 'display:grid;grid-template-columns:repeat(4,minmax(0,1fr))',
					'5' => 'display:grid;grid-template-columns:repeat(5,minmax(0,1fr))',
					'6' => 'display:grid;grid-template-columns:repeat(6,minmax(0,1fr))',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-share__list' => '{{VALUE}}' ),
				'ai'                   => 'Leave empty for an inline row; use a number for equal-width buttons in a grid.',
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'left'    => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center'  => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'   => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
					'justify' => array( 'label' => __( 'Stretch', 'uncoder' ), 'icon' => 'align-justify' ),
				),
				'selectors_dictionary' => array(
					'left'    => 'justify-content:flex-start;--uncoder-share-grow:0',
					'center'  => 'justify-content:center;--uncoder-share-grow:0',
					'right'   => 'justify-content:flex-end;--uncoder-share-grow:0',
					'justify' => 'justify-content:stretch;--uncoder-share-grow:1',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-share__list' => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_buttons', array( 'label' => __( 'Buttons', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-share__list' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Button height', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 96 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-share-h: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-share-icon: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding (with text)', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'condition'  => array( 'view!' => 'icon' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-share__btn' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'typography', array( 'type' => 'typography', 'label' => __( 'Label typography', 'uncoder' ), 'condition' => array( 'view!' => 'icon' ), 'selector' => '{{WRAPPER}} .uncoder-share__btn' ) );
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-share__btn' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'border_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Border width', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 6 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-share-bw: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( 'button_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-share__btn' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_color', array( 'type' => 'color', 'label' => __( 'Icon & text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-share__btn' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-share__btn' => 'border-color: {{VALUE}}' ) ) );
		$this->add_group( 'button_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-share__btn' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( 'button_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-share__btn:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_color', array( 'type' => 'color', 'label' => __( 'Icon & text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-share__btn:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-share__btn:is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ) ) );
		$this->add_control(
			'hover_effect',
			array(
				'type'    => 'select',
				'label'   => __( 'Hover effect', 'uncoder' ),
				'default' => 'lift',
				'options' => array(
					''     => __( 'None', 'uncoder' ),
					'lift' => __( 'Lift', 'uncoder' ),
					'grow' => __( 'Grow', 'uncoder' ),
				),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();
	}

	/**
	 * URL and title to share.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array{url:string,title:string,image:string}
	 */
	private function target( array $s, Render_Context $ctx ): array {
		$post_id = $ctx->post_id;
		$url     = '';
		$title   = '';
		$image   = '';
		if ( 'custom' === ( $s['share_source'] ?? 'current' ) && is_array( $s['custom_url'] ?? null ) ) {
			$url = (string) ( $s['custom_url']['url'] ?? '' );
		}
		if ( $post_id > 0 ) {
			if ( '' === $url ) {
				$permalink = get_permalink( $post_id );
				$url       = $permalink ? $permalink : '';
			}
			$title = html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' );
			$thumb = get_the_post_thumbnail_url( $post_id, 'large' );
			$image = $thumb ? $thumb : '';
		}
		if ( '' === $url ) {
			$url = home_url( '/' );
		}
		if ( '' === $title ) {
			$title = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
		}
		$custom = trim( (string) ( $s['share_text'] ?? '' ) );
		if ( '' !== $custom ) {
			$title = $custom;
		}
		return array(
			'url'   => esc_url_raw( $url ),
			'title' => $title,
			'image' => $image,
		);
	}

	/**
	 * Share endpoint for a network ('' for copy / native).
	 */
	public static function share_url( string $network, string $url, string $title, string $image = '' ): string {
		$u = rawurlencode( $url );
		$t = rawurlencode( $title );
		switch ( $network ) {
			case 'facebook':
				return 'https://www.facebook.com/sharer/sharer.php?u=' . $u;
			case 'x':
				return 'https://x.com/intent/tweet?url=' . $u . '&text=' . $t;
			case 'linkedin':
				return 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u;
			case 'whatsapp':
				return 'https://api.whatsapp.com/send?text=' . rawurlencode( $title . ' ' . $url );
			case 'telegram':
				return 'https://t.me/share/url?url=' . $u . '&text=' . $t;
			case 'pinterest':
				return 'https://pinterest.com/pin/create/button/?url=' . $u . '&description=' . $t . ( '' !== $image ? '&media=' . rawurlencode( $image ) : '' );
			case 'reddit':
				return 'https://www.reddit.com/submit?url=' . $u . '&title=' . $t;
			case 'email':
				return 'mailto:?subject=' . $t . '&body=' . rawurlencode( $title . "\n\n" . $url );
		}
		return '';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$rows     = is_array( $s['networks'] ?? null ) ? $s['networks'] : array();
		$networks = self::networks();
		$target   = $this->target( $s, $ctx );
		$view     = in_array( $s['view'] ?? 'icon', array( 'icon', 'text', 'icon-text' ), true ) ? $s['view'] : 'icon';
		$skin     = in_array( $s['skin'] ?? 'solid', array( 'solid', 'soft', 'outline', 'minimal' ), true ) ? $s['skin'] : 'solid';
		$shape    = in_array( $s['shape'] ?? 'rounded', array( 'square', 'rounded', 'circle' ), true ) ? $s['shape'] : 'rounded';
		$size     = in_array( $s['size'] ?? 'md', array( 'sm', 'md', 'lg' ), true ) ? $s['size'] : 'md';

		$items = '';
		foreach ( $rows as $row ) {
			$network = is_array( $row ) ? (string) ( $row['network'] ?? '' ) : '';
			if ( ! isset( $networks[ $network ] ) ) {
				continue;
			}
			$name  = $networks[ $network ];
			$label = trim( (string) ( $row['label'] ?? '' ) );
			$label = '' !== $label ? $label : $name;
			$li    = array( 'uncoder-share__item', 'uncoder-share__item--' . $network );
			if ( ! empty( $row['_id'] ) && is_string( $row['_id'] ) ) {
				$li[] = 'uncoder-ri-' . sanitize_html_class( $row['_id'] );
			}
			$inner = '';
			if ( 'text' !== $view ) {
				$inner .= '<span class="uncoder-share__icon">' . self::network_icon( $network ) . '</span>';
			}
			if ( 'icon' !== $view ) {
				$inner .= '<span class="uncoder-share__label">' . esc_html( $label ) . '</span>';
			}

			if ( in_array( $network, array( 'copy', 'native' ), true ) ) {
				// Enhanced by the share module; hidden until it confirms support.
				$attrs = array(
					'type'           => 'button',
					'class'          => 'uncoder-share__btn',
					'data-uncoder-share' => $network,
					'data-url'       => $target['url'],
					'data-title'     => $target['title'],
					'aria-label'     => 'icon' === $view ? $label : null,
				);
				if ( 'copy' === $network ) {
					$attrs['data-copied'] = (string) ( $s['copied_text'] ?? __( 'Link copied', 'uncoder' ) );
				}
				$items .= '<li class="' . esc_attr( implode( ' ', $li ) ) . '"' . ( $ctx->editor ? '' : ' hidden' ) . '><button' . Utils::attrs( $attrs ) . '>' . $inner . '</button></li>';
				continue;
			}

			$attrs = array(
				'class' => 'uncoder-share__btn',
				'href'  => self::share_url( $network, $target['url'], $target['title'], $target['image'] ),
			);
			if ( 'email' !== $network ) {
				$attrs['target'] = '_blank';
				$attrs['rel']    = 'noopener noreferrer';
			}
			if ( 'icon' === $view ) {
				if ( $label !== $name ) {
					$attrs['aria-label'] = $label;
				} elseif ( 'email' === $network ) {
					$attrs['aria-label'] = __( 'Share by email', 'uncoder' );
				} else {
					/* translators: %s: network name. */
					$attrs['aria-label'] = sprintf( __( 'Share on %s', 'uncoder' ), $name );
				}
			}
			$items .= '<li class="' . esc_attr( implode( ' ', $li ) ) . '"><a' . Utils::attrs( $attrs ) . '>' . $inner . '</a></li>';
		}

		if ( '' === $items ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-share__empty">' . esc_html__( 'Add at least one network.', 'uncoder' ) . '</p>';
			}
			return;
		}

		$classes = array( 'uncoder-share', 'uncoder-share--' . $skin, 'uncoder-share--' . $shape, 'uncoder-share--view-' . $view, 'uncoder-share--' . $size );
		if ( 'custom' === ( $s['color_source'] ?? 'brand' ) ) {
			$classes[] = 'uncoder-share--custom';
		}
		if ( ! empty( $s['hover_effect'] ) && in_array( $s['hover_effect'], array( 'lift', 'grow' ), true ) ) {
			$classes[] = 'uncoder-share--hover-' . $s['hover_effect'];
		}
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		echo '<ul class="uncoder-share__list">' . $items . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '<span class="uncoder-sr-only uncoder-share__status" role="status" aria-live="polite"></span>';
		echo '</div>';
	}
}
