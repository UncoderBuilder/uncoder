<?php
/**
 * Link in Bio widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Brand_Icons;
use Uncoder\Builder\Widgets\Support\Repeater_Rows;

defined( 'ABSPATH' ) || exit;

/**
 * A mobile-first "link in bio" page: round photo, name, short bio, social icons, a stack of link
 * buttons (Design System button styles) and a footer line, centred in a narrow column.
 */
class Link_In_Bio extends Widget_Base {

	public const STYLES = array( 'filled', 'outline', 'soft' );

	/** Link style => Design System button variant. */
	private const VARIANTS = array(
		'filled'  => 'primary',
		'outline' => 'outline',
		'soft'    => 'ghost',
	);

	public const NAME_TAGS = array( 'h1', 'h2', 'h3', 'p', 'div' );

	public function name(): string {
		return 'link-in-bio';
	}

	public function title(): string {
		return __( 'Link in Bio', 'uncoder' );
	}

	public function icon(): string {
		return 'contact';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'link in bio', 'linktree', 'bio', 'links', 'profile', 'social', 'creator', 'landing', 'mobile' );
	}

	public function description(): string {
		return __( 'A complete mobile-first "link in bio" page in one widget: round profile photo, name, handle, short bio, social icons, a stack of big link buttons (one can be highlighted) and a footer line, centred in a narrow column over a color, gradient or image background. Put it alone on a page with the Uncoder Canvas template and turn on Fill the screen.', 'uncoder' );
	}

	public function preset(): array {
		return array(
			'name'       => 'Alex Morgan',
			'handle'     => '@alexmorgan',
			'bio'        => __( 'Designer and creator. Sharing what I make, learn and love.', 'uncoder' ),
			'links'      => array(
				array(
					'_id'       => 'lib0001',
					'label'     => __( 'My latest work', 'uncoder' ),
					'link'      => array( 'url' => '#' ),
					'icon'      => array( 'library' => 'lucide', 'value' => 'sparkles' ),
					'highlight' => true,
				),
				array(
					'_id'   => 'lib0002',
					'label' => __( 'Book a call', 'uncoder' ),
					'link'  => array( 'url' => '#' ),
					'icon'  => array( 'library' => 'lucide', 'value' => 'calendar' ),
				),
				array(
					'_id'   => 'lib0003',
					'label' => __( 'Read the blog', 'uncoder' ),
					'link'  => array( 'url' => '#' ),
					'icon'  => array( 'library' => 'lucide', 'value' => 'newspaper' ),
				),
				array(
					'_id'   => 'lib0004',
					'label' => __( 'Shop my favourites', 'uncoder' ),
					'link'  => array( 'url' => '#' ),
					'icon'  => array( 'library' => 'lucide', 'value' => 'shopping-bag' ),
				),
				array(
					'_id'   => 'lib0005',
					'label' => __( 'Get in touch', 'uncoder' ),
					'link'  => array( 'url' => 'mailto:hello@example.com' ),
					'icon'  => array( 'library' => 'lucide', 'value' => 'mail' ),
				),
			),
			'social'     => array(
				array(
					'_id'     => 'libs001',
					'network' => 'instagram',
					'link'    => array( 'url' => 'https://www.instagram.com/' ),
				),
				array(
					'_id'     => 'libs002',
					'network' => 'youtube',
					'link'    => array( 'url' => 'https://www.youtube.com/' ),
				),
				array(
					'_id'     => 'libs003',
					'network' => 'tiktok',
					'link'    => array( 'url' => 'https://www.tiktok.com/' ),
				),
				array(
					'_id'     => 'libs004',
					'network' => 'linkedin',
					'link'    => array( 'url' => 'https://www.linkedin.com/' ),
				),
			),
			'footer'     => __( 'Thanks for stopping by!', 'uncoder' ),
			'background' => array(
				'type'           => 'gradient',
				'color'          => 'color-mix(in srgb, var(--uncoder-c-primary) 14%, #fff)',
				'color_b'        => 'color-mix(in srgb, var(--uncoder-c-accent) 14%, #fff)',
				'gradient_angle' => array( 'size' => 160, 'unit' => 'deg' ),
			),
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content_profile', array( 'label' => __( 'Profile', 'uncoder' ) ) );
		$this->add_control(
			'image',
			array(
				'type'        => 'media',
				'label'       => __( 'Profile photo', 'uncoder' ),
				'default'     => array( 'id' => 0, 'url' => '' ),
				'dynamic'     => true,
				'description' => __( 'Shown round. Without a photo the initials of the name are shown.', 'uncoder' ),
			)
		);
		$this->add_control(
			'show_avatar',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show photo or initials', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'name',
			array(
				'type'    => 'text',
				'label'   => __( 'Name', 'uncoder' ),
				'default' => __( 'Your Name', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'name_tag',
			array(
				'type'        => 'select',
				'label'       => __( 'Name HTML tag', 'uncoder' ),
				'default'     => 'h1',
				'options'     => array_combine( self::NAME_TAGS, array_map( 'strtoupper', self::NAME_TAGS ) ),
				'description' => __( 'H1 on a page of its own; H2 when the page already has a main title.', 'uncoder' ),
			)
		);
		$this->add_control(
			'verified',
			array(
				'type'  => 'switch',
				'label' => __( 'Verified badge', 'uncoder' ),
			)
		);
		$this->add_control(
			'handle',
			array(
				'type'        => 'text',
				'label'       => __( 'Handle', 'uncoder' ),
				'placeholder' => '@username',
				'inline'      => true,
			)
		);
		$this->add_control(
			'bio',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Bio', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 3,
				'default' => __( 'A short line about who you are and what you do.', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
				'ai'      => 'One or two short sentences.',
			)
		);
		$this->end_section();

		$this->start_section( 'content_links', array( 'label' => __( 'Links', 'uncoder' ) ) );
		$this->add_control(
			'links',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Links', 'uncoder' ),
				'title_field' => 'label',
				'fields'      => array(
					'label'     => array(
						'type'    => 'text',
						'label'   => __( 'Label', 'uncoder' ),
						'default' => __( 'My link', 'uncoder' ),
						'dynamic' => true,
						'inline'  => true,
					),
					'link'      => array(
						'type'    => 'url',
						'label'   => __( 'Link', 'uncoder' ),
						'default' => array( 'url' => '#' ),
						'dynamic' => true,
					),
					'icon'      => array(
						'type'  => 'icon',
						'label' => __( 'Icon', 'uncoder' ),
					),
					'highlight' => array(
						'type'        => 'switch',
						'label'       => __( 'Highlight', 'uncoder' ),
						'description' => __( 'Makes this link stand out (accent color, optional animation).', 'uncoder' ),
					),
				),
				'default'     => array(
					array(
						'_id'   => 'lib0001',
						'label' => __( 'My website', 'uncoder' ),
						'link'  => array( 'url' => '#' ),
					),
				),
				'ai'          => 'Rows: {"label":"Book a call","link":{"url":"https://…"},"icon":"calendar","highlight":false}. Three to six short labels; highlight at most one.',
			)
		);
		$this->add_control(
			'link_style',
			array(
				'type'    => 'choose',
				'label'   => __( 'Button style', 'uncoder' ),
				'default' => 'filled',
				'options' => array(
					'filled'  => array( 'label' => __( 'Filled', 'uncoder' ) ),
					'outline' => array( 'label' => __( 'Outline', 'uncoder' ) ),
					'soft'    => array( 'label' => __( 'Soft', 'uncoder' ) ),
				),
				'ai'      => 'Uses the Design System buttons: filled = primary button, outline, soft = tinted.',
			)
		);
		$this->add_control(
			'link_align',
			array(
				'type'    => 'choose',
				'label'   => __( 'Label alignment', 'uncoder' ),
				'default' => 'center',
				'options' => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
				),
			)
		);
		$this->add_control(
			'show_arrow',
			array(
				'type'  => 'switch',
				'label' => __( 'Arrow at the end', 'uncoder' ),
			)
		);
		$this->add_control(
			'highlight_animation',
			array(
				'type'    => 'select',
				'label'   => __( 'Highlight animation', 'uncoder' ),
				'options' => array(
					''       => __( 'None', 'uncoder' ),
					'pulse'  => __( 'Pulse', 'uncoder' ),
					'wobble' => __( 'Wobble', 'uncoder' ),
				),
			)
		);
		$this->end_section();

		$this->start_section( 'content_social', array( 'label' => __( 'Social icons', 'uncoder' ) ) );
		$this->add_control(
			'social',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Profiles', 'uncoder' ),
				'title_field' => 'network',
				'fields'      => array(
					'network' => array(
						'type'    => 'select',
						'label'   => __( 'Network', 'uncoder' ),
						'default' => 'instagram',
						'options' => Brand_Icons::options(),
					),
					'link'    => array(
						'type'    => 'url',
						'label'   => __( 'Link', 'uncoder' ),
						'dynamic' => true,
						'ai'      => 'Profile URL; "mailto:…" for email, "tel:…" for phone.',
					),
					'icon'    => array(
						'type'      => 'icon',
						'label'     => __( 'Icon', 'uncoder' ),
						'default'   => array( 'library' => 'lucide', 'value' => 'link' ),
						'condition' => array( 'network' => 'custom' ),
					),
					'label'   => array(
						'type'        => 'text',
						'label'       => __( 'Label', 'uncoder' ),
						'description' => __( 'Accessible name. Defaults to the network name.', 'uncoder' ),
					),
				),
				'default'     => array(),
				'ai'          => 'Rows: {"network":"instagram","link":{"url":"https://instagram.com/…"}}. Networks as in social-icons.',
			)
		);
		$this->add_control(
			'social_position',
			array(
				'type'    => 'choose',
				'label'   => __( 'Position', 'uncoder' ),
				'default' => 'top',
				'options' => array(
					'top'    => array( 'label' => __( 'Above the links', 'uncoder' ) ),
					'bottom' => array( 'label' => __( 'Below the links', 'uncoder' ) ),
				),
			)
		);
		$this->add_control(
			'social_style',
			array(
				'type'    => 'select',
				'label'   => __( 'Style', 'uncoder' ),
				'default' => 'icons',
				'options' => array(
					'icons'   => __( 'Icons', 'uncoder' ),
					'brand'   => __( 'Icons in brand colors', 'uncoder' ),
					'circles' => __( 'Icons in circles', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'social_new_tab',
			array(
				'type'    => 'switch',
				'label'   => __( 'Open in a new tab', 'uncoder' ),
				'default' => true,
			)
		);
		$this->end_section();

		$this->start_section( 'content_footer', array( 'label' => __( 'Footer', 'uncoder' ) ) );
		$this->add_control(
			'footer',
			array(
				'type'    => 'text',
				'label'   => __( 'Footer text', 'uncoder' ),
				'html'    => 'inline',
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style */

		$this->start_section( 'style_layout', array( 'label' => __( 'Layout & background', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Column width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 280, 'max' => 800 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-lib-width: {{VALUE}}' ),
				'ai'         => 'Default 480px; the column is centred.',
			)
		);
		$this->add_control(
			'full_height',
			array(
				'type'        => 'switch',
				'label'       => __( 'Fill the screen', 'uncoder' ),
				'description' => __( 'At least as tall as the screen, content centred vertically.', 'uncoder' ),
			)
		);
		$this->add_control(
			'text_align',
			array(
				'type'    => 'choose',
				'label'   => __( 'Profile alignment', 'uncoder' ),
				'default' => 'center',
				'options' => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
				),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem', 'vh' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'background',
			array(
				'type'     => 'background',
				'label'    => __( 'Background', 'uncoder' ),
				'selector' => '{{WRAPPER}}',
				'ai'       => 'Page background behind the column: color, gradient or photo (with overlay_color for contrast).',
			)
		);
		$this->add_control(
			'overlay_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Overlay', 'uncoder' ),
				'description' => __( 'A see-through color over a background photo, for readable text.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-lib-overlay: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}}; --uncoder-heading-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between blocks', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-lib-space: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_card', array( 'label' => __( 'Card', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'card',
			array(
				'type'        => 'switch',
				'label'       => __( 'Show as a card', 'uncoder' ),
				'description' => __( 'Puts the column on a panel over the background.', 'uncoder' ),
			)
		);
		$this->add_control(
			'card_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Card color', 'uncoder' ),
				'condition' => array( 'card' => 'yes' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__inner' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Card padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'condition'  => array( 'card' => 'yes' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-link-in-bio__inner' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'card_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Card radius', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'condition'  => array( 'card' => 'yes' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-link-in-bio__inner' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group(
			'card_shadow',
			array(
				'type'      => 'box_shadow',
				'label'     => __( 'Card shadow', 'uncoder' ),
				'condition' => array( 'card' => 'yes' ),
				'selector'  => '{{WRAPPER}} .uncoder-link-in-bio__inner',
			)
		);
		$this->end_section();

		$this->start_section( 'style_profile', array( 'label' => __( 'Profile', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'avatar_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Photo size', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 40, 'max' => 240 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-lib-avatar: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'avatar_shape',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Photo shape', 'uncoder' ),
				'options'              => array(
					'circle'  => array( 'label' => __( 'Circle', 'uncoder' ) ),
					'rounded' => array( 'label' => __( 'Rounded', 'uncoder' ) ),
					'square'  => array( 'label' => __( 'Square', 'uncoder' ) ),
				),
				'selectors_dictionary' => array(
					'circle'  => '50%',
					'rounded' => '22%',
					'square'  => '0',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-link-in-bio__avatar' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'avatar_ring',
			array(
				'type'      => 'color',
				'label'     => __( 'Photo ring', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__avatar' => 'box-shadow: 0 0 0 var(--uncoder-lib-ring-w, 4px) {{VALUE}}' ),
			)
		);
		$this->add_control(
			'name_heading',
			array(
				'type'  => 'heading',
				'label' => __( 'Name', 'uncoder' ),
			)
		);
		$this->add_group( 'name_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-link-in-bio__name' ) );
		$this->add_control(
			'name_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__name' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'handle_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Handle color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__handle' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'badge_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Badge color', 'uncoder' ),
				'condition' => array( 'verified' => 'yes' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__badge' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'bio_heading',
			array(
				'type'  => 'heading',
				'label' => __( 'Bio', 'uncoder' ),
			)
		);
		$this->add_group( 'bio_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-link-in-bio__bio' ) );
		$this->add_control(
			'bio_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__bio' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_links', array( 'label' => __( 'Links', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'link_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-link-in-bio__link' ) );
		$this->start_tabs( 'link_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'link_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__link:not(.uncoder-link-in-bio__link--highlight)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__link:not(.uncoder-link-in-bio__link--highlight)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'link_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__link:not(.uncoder-link-in-bio__link--highlight):is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_hover_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__link:not(.uncoder-link-in-bio__link--highlight):is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'link_hover_border',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__link:is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_group( 'link_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-link-in-bio__link' ) );
		$this->add_responsive_control(
			'link_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-link-in-bio__link' => 'border-radius: {{VALUE}}' ),
				'ai'         => 'Default comes from the Design System buttons; {"top":999,"right":999,"bottom":999,"left":999,"unit":"px"} for pills.',
			)
		);
		$this->add_responsive_control(
			'link_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Minimum height', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 32, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-lib-link-h: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'link_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between links', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-lib-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'link_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-link-in-bio__link .uncoder-svg' => 'width: {{VALUE}}; height: {{VALUE}}' ),
			)
		);
		$this->add_group( 'link_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-link-in-bio__link' ) );
		$this->add_control(
			'highlight_heading',
			array(
				'type'  => 'heading',
				'label' => __( 'Highlighted link', 'uncoder' ),
			)
		);
		$this->add_control(
			'highlight_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-lib-hl: {{VALUE}}' ),
				'ai'        => 'Defaults to the accent color.',
			)
		);
		$this->add_control(
			'highlight_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-lib-hl-text: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_social', array( 'label' => __( 'Social icons', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'social_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-lib-social: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'social_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-link-in-bio__social' => 'gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'social_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'condition' => array( 'social_style!' => 'brand' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__social-link' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'social_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__social-link:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_footer', array( 'label' => __( 'Footer', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'footer_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-link-in-bio__footer' ) );
		$this->add_control(
			'footer_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-link-in-bio__footer' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Up to two initials of a name.
	 */
	private static function initials( string $name ): string {
		$words = preg_split( '/\s+/u', trim( wp_strip_all_tags( $name ) ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) || ! $words ) {
			return '';
		}
		$words = array_values( array_filter( $words, static fn( $w ) => (bool) preg_match( '/^[\p{L}\p{N}]/u', $w ) ) );
		$first = $words ? array( $words[0] ) : array();
		if ( count( $words ) > 1 ) {
			$first[] = $words[ count( $words ) - 1 ];
		}
		$out = '';
		foreach ( $first as $word ) {
			$out .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
		}
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $out ) : strtoupper( $out );
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function social_html( array $s, Render_Context $ctx ): string {
		$rows  = Repeater_Rows::get( $this, 'social', $s['social'] ?? array() );
		$items = '';
		$brand = 'brand' === ( $s['social_style'] ?? 'icons' );
		foreach ( $rows as $row ) {
			$network = (string) ( $row['network'] ?? 'custom' );
			if ( ! Brand_Icons::exists( $network ) ) {
				$network = 'custom';
			}
			$svg = 'custom' === $network
				? $this->render_icon( $this->has_icon( $row['icon'] ?? null ) ? $row['icon'] : 'link', array( 'class' => 'uncoder-link-in-bio__social-svg' ) )
				: Brand_Icons::svg( $network, 'uncoder-link-in-bio__social-svg' );
			$label = trim( (string) ( $row['label'] ?? '' ) );
			$label = '' !== $label ? $label : Brand_Icons::label( $network );
			$link  = $this->link_attrs( $row['link'] ?? array() );
			if ( ! $link && ! $ctx->editor ) {
				continue;
			}
			$style = $brand && '' !== Brand_Icons::color( $network ) ? '--uncoder-lib-brand:' . Brand_Icons::color( $network ) : '';
			if ( $link ) {
				$href = (string) $link['href'];
				// Bare addresses and numbers (saved as "http://name@example.com") become mailto: / tel: links.
				$bare = rawurldecode( (string) preg_replace( '#^https?://#i', '', $href ) );
				if ( 'email' === $network && ! preg_match( '/^mailto:/i', $href ) && is_email( $bare ) ) {
					$href = 'mailto:' . $bare;
				} elseif ( 'phone' === $network && ! preg_match( '/^(tel|sms):/i', $href ) && preg_match( '/^\+?[0-9 ().\-]{5,}$/', $bare ) ) {
					$href = 'tel:' . preg_replace( '/[^0-9+]/', '', $bare );
				}
				$link['href'] = $href;
				if ( ! empty( $s['social_new_tab'] ) && ! isset( $link['target'] ) && preg_match( '#^(https?:)?//#i', $href ) ) {
					$link['target'] = '_blank';
					$link['rel']    = trim( ( $link['rel'] ?? '' ) . ' noopener' );
				}
				if ( 'mastodon' === $network ) {
					$link['rel'] = trim( ( $link['rel'] ?? '' ) . ' me' );
				}
				$link['class']      = 'uncoder-link-in-bio__social-link';
				$link['style']      = '' !== $style ? $style : null;
				$link['aria-label'] = isset( $link['target'] ) && '_blank' === $link['target']
					/* translators: %s: network or link name. */
					? sprintf( __( '%s (opens in a new tab)', 'uncoder' ), $label )
					: $label;
				$items .= '<li><a' . Utils::attrs( $link ) . '>' . $svg . '</a></li>';
			} else {
				$items .= '<li><span class="uncoder-link-in-bio__social-link" role="img" aria-label="' . esc_attr( $label ) . '"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>' . $svg . '</span></li>';
			}
		}
		return '' === $items ? '' : '<ul class="uncoder-link-in-bio__social" role="list">' . $items . '</ul>';
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function links_html( array $s, Render_Context $ctx ): string {
		$rows    = Repeater_Rows::get( $this, 'links', $s['links'] ?? array() );
		$style   = in_array( $s['link_style'] ?? 'filled', self::STYLES, true ) ? (string) $s['link_style'] : 'filled';
		$variant = self::VARIANTS[ $style ];
		$arrow   = ! empty( $s['show_arrow'] ) ? '<span class="uncoder-link-in-bio__arrow">' . $this->render_icon( 'arrow-up-right' ) . '</span>' : '';
		$items   = '';
		foreach ( $rows as $i => $row ) {
			$label = trim( (string) ( $row['label'] ?? '' ) );
			$link  = $this->link_attrs( $row['link'] ?? array() );
			if ( ( '' === $label || ! $link ) && ! $ctx->editor ) {
				continue;
			}
			$classes = array( 'uncoder-btn', 'uncoder-btn--' . $variant, 'uncoder-link-in-bio__link' );
			if ( ! empty( $row['highlight'] ) ) {
				$classes[] = 'uncoder-link-in-bio__link--highlight';
			}
			$icon  = $this->has_icon( $row['icon'] ?? null ) ? '<span class="uncoder-link-in-bio__icon">' . $this->render_icon( $row['icon'] ) . '</span>' : '';
			$inner = $icon . '<span class="uncoder-link-in-bio__label"' . $ctx->inline( 'links.' . $i . '.label' ) . '>' . esc_html( $label ) . '</span>' . $arrow;
			$row_id = isset( $row['_id'] ) ? sanitize_html_class( (string) $row['_id'] ) : '';
			$items .= '<li class="' . esc_attr( trim( 'uncoder-link-in-bio__item' . ( '' !== $row_id ? ' uncoder-ri-' . $row_id : '' ) ) ) . '">';
			if ( $link ) {
				$link['class'] = implode( ' ', $classes );
				$items        .= '<a' . Utils::attrs( $link ) . '>' . $inner . '</a>';
			} else {
				$items .= '<span class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $inner . '</span>';
			}
			$items .= '</li>';
		}
		return '' === $items ? '' : '<ul class="uncoder-link-in-bio__links" role="list">' . $items . '</ul>';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$name   = trim( (string) ( $s['name'] ?? '' ) );
		$handle = trim( (string) ( $s['handle'] ?? '' ) );
		$bio    = $this->inline_html( $s['bio'] ?? '' );
		$footer = $this->inline_html( $s['footer'] ?? '' );
		$tag    = Utils::tag( $s['name_tag'] ?? 'h1', self::NAME_TAGS, 'h1' );

		$avatar = '';
		if ( ! empty( $s['show_avatar'] ) ) {
			$img = $this->image(
				$s['image'] ?? array(),
				'medium',
				array(
					'class'   => 'uncoder-link-in-bio__img',
					'alt'     => '',
					'loading' => 'eager',
				)
			);
			if ( '' !== $img ) {
				$avatar = '<div class="uncoder-link-in-bio__avatar">' . $img . '</div>';
			} elseif ( '' !== self::initials( $name ) ) {
				$avatar = '<div class="uncoder-link-in-bio__avatar uncoder-link-in-bio__avatar--initials" aria-hidden="true">' . esc_html( self::initials( $name ) ) . '</div>';
			}
		}

		$social = $this->social_html( $s, $ctx );
		$links  = $this->links_html( $s, $ctx );
		if ( '' === $name && '' === $links && '' === $social && ! $ctx->editor ) {
			return;
		}

		$classes = array( 'uncoder-link-in-bio', 'uncoder-link-in-bio--' . ( in_array( $s['link_style'] ?? 'filled', self::STYLES, true ) ? $s['link_style'] : 'filled' ) );
		if ( ! empty( $s['full_height'] ) ) {
			$classes[] = 'uncoder-link-in-bio--full';
		}
		if ( ! empty( $s['card'] ) ) {
			$classes[] = 'uncoder-link-in-bio--card';
		}
		if ( 'left' === ( $s['text_align'] ?? 'center' ) ) {
			$classes[] = 'uncoder-link-in-bio--left';
		}
		if ( 'left' === ( $s['link_align'] ?? 'center' ) ) {
			$classes[] = 'uncoder-link-in-bio--links-left';
		}
		if ( in_array( $s['social_style'] ?? 'icons', array( 'brand', 'circles' ), true ) ) {
			$classes[] = 'uncoder-link-in-bio--social-' . $s['social_style'];
		}
		if ( in_array( $s['highlight_animation'] ?? '', array( 'pulse', 'wobble' ), true ) ) {
			$classes[] = 'uncoder-link-in-bio--hl-' . $s['highlight_animation'];
		}

		$out  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><div class="uncoder-link-in-bio__inner">';
		$out .= $avatar;
		if ( '' !== $name || $ctx->editor ) {
			$badge = ! empty( $s['verified'] )
				? '<span class="uncoder-link-in-bio__badge">' . $this->render_icon( 'badge-check' ) . '<span class="uncoder-sr-only">' . esc_html__( 'Verified', 'uncoder' ) . '</span></span>'
				: '';
			$out  .= '<' . $tag . ' class="uncoder-link-in-bio__name"><span' . $ctx->inline( 'name' ) . '>' . esc_html( $name ) . '</span>' . $badge . '</' . $tag . '>';
		}
		if ( '' !== $handle ) {
			$out .= '<p class="uncoder-link-in-bio__handle"' . $ctx->inline( 'handle' ) . '>' . esc_html( $handle ) . '</p>';
		}
		if ( '' !== trim( wp_strip_all_tags( $bio ) ) ) {
			$out .= '<p class="uncoder-link-in-bio__bio"' . $ctx->inline( 'bio' ) . '>' . $bio . '</p>';
		}
		$bottom = 'bottom' === ( $s['social_position'] ?? 'top' );
		if ( ! $bottom ) {
			$out .= $social;
		}
		if ( '' !== $links ) {
			$out .= $links;
		} elseif ( $ctx->editor ) {
			$out .= '<p class="uncoder-link-in-bio__placeholder">' . esc_html__( 'Add links in the Content tab.', 'uncoder' ) . '</p>';
		}
		if ( $bottom ) {
			$out .= $social;
		}
		if ( '' !== trim( wp_strip_all_tags( $footer ) ) ) {
			$out .= '<p class="uncoder-link-in-bio__footer"' . $ctx->inline( 'footer' ) . '>' . $footer . '</p>';
		}
		$out .= '</div></div>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
