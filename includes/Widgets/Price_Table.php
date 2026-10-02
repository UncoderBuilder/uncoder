<?php
/**
 * Price Table widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * One pricing plan: header, price, feature list with included/excluded states and tooltips, button, badge.
 */
class Price_Table extends Widget_Base {

	public function name(): string {
		return 'price-table';
	}

	public function title(): string {
		return __( 'Price Table', 'uncoder' );
	}

	public function icon(): string {
		return 'badge-dollar-sign';
	}

	public function category(): string {
		return 'marketing';
	}

	public function keywords(): array {
		return array( 'price', 'pricing', 'plan', 'table', 'package', 'subscription' );
	}

	public function description(): string {
		return __( 'One pricing plan card: title, price with currency/period/original price, feature list (included or not, optional tooltip), button, footer note and a "Most popular" badge. Place 2–4 side by side in a row container.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content_header', array( 'label' => __( 'Header', 'uncoder' ) ) );
		$this->add_control( 'header_icon', array( 'type' => 'icon', 'label' => __( 'Icon', 'uncoder' ) ) );
		$this->add_control(
			'title',
			array(
				'type'    => 'text',
				'label'   => __( 'Title', 'uncoder' ),
				'default' => __( 'Pro', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'subtitle',
			array(
				'type'    => 'text',
				'label'   => __( 'Subtitle', 'uncoder' ),
				'default' => __( 'For growing teams', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'type'    => 'select',
				'label'   => __( 'Title HTML tag', 'uncoder' ),
				'default' => 'h3',
				'options' => array_combine( Utils::HEADING_TAGS, array_map( 'strtoupper', Utils::HEADING_TAGS ) ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_pricing', array( 'label' => __( 'Pricing', 'uncoder' ) ) );
		$this->add_control(
			'currency',
			array(
				'type'    => 'text',
				'label'   => __( 'Currency symbol', 'uncoder' ),
				'default' => '$',
			)
		);
		$this->add_control(
			'currency_position',
			array(
				'type'    => 'choose',
				'label'   => __( 'Currency position', 'uncoder' ),
				'default' => 'before',
				'options' => array(
					'before' => array( 'label' => __( 'Before', 'uncoder' ), 'icon' => 'arrow-left-to-line' ),
					'after'  => array( 'label' => __( 'After', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
				),
			)
		);
		$this->add_control(
			'price',
			array(
				'type'    => 'text',
				'label'   => __( 'Price', 'uncoder' ),
				'default' => '49',
				'dynamic' => true,
				'inline'  => true,
				'ai'      => 'Number without currency, e.g. "49" or "1,299". Put cents in "fraction".',
			)
		);
		$this->add_control(
			'fraction',
			array(
				'type'        => 'text',
				'label'       => __( 'Fractional part', 'uncoder' ),
				'placeholder' => '99',
				'description' => __( 'Shown raised after the price, e.g. 99 for 49.99.', 'uncoder' ),
			)
		);
		$this->add_control(
			'period',
			array(
				'type'    => 'text',
				'label'   => __( 'Period', 'uncoder' ),
				'default' => __( '/month', 'uncoder' ),
				'inline'  => true,
			)
		);
		$this->add_control(
			'period_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Period position', 'uncoder' ),
				'default'   => 'beside',
				'options'   => array(
					'beside' => array( 'label' => __( 'Beside', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
					'below'  => array( 'label' => __( 'Below', 'uncoder' ), 'icon' => 'arrow-down-to-line' ),
				),
				'condition' => array( 'period!' => '' ),
			)
		);
		$this->add_control(
			'original_price',
			array(
				'type'        => 'text',
				'label'       => __( 'Original price', 'uncoder' ),
				'placeholder' => '79',
				'description' => __( 'Shown struck through before the price (for discounts).', 'uncoder' ),
			)
		);
		$this->end_section();

		$this->start_section( 'content_features', array( 'label' => __( 'Features', 'uncoder' ) ) );
		$this->add_control(
			'features',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Features', 'uncoder' ),
				'title_field' => 'text',
				'fields'      => array(
					'text'     => array(
						'type'    => 'text',
						'label'   => __( 'Text', 'uncoder' ),
						'default' => __( 'Feature', 'uncoder' ),
					),
					'included' => array(
						'type'    => 'switch',
						'label'   => __( 'Included', 'uncoder' ),
						'default' => true,
					),
					'icon'     => array(
						'type'        => 'icon',
						'label'       => __( 'Icon override', 'uncoder' ),
						'description' => __( 'Leave empty to use the included / not included icon.', 'uncoder' ),
					),
					'tooltip'  => array(
						'type'  => 'text',
						'label' => __( 'Tooltip', 'uncoder' ),
					),
					'color'    => array(
						'type'      => 'color',
						'label'     => __( 'Icon color', 'uncoder' ),
						'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .uncoder-price-table__feature-icon' => 'color: {{VALUE}}' ),
					),
				),
				'default'     => array(
					array(
						'text'     => __( 'Unlimited pages', 'uncoder' ),
						'included' => true,
					),
					array(
						'text'     => __( 'Custom domain and SSL', 'uncoder' ),
						'included' => true,
					),
					array(
						'text'     => __( 'Advanced analytics', 'uncoder' ),
						'included' => true,
						'tooltip'  => __( 'Funnels, cohorts and CSV exports.', 'uncoder' ),
					),
					array(
						'text'     => __( 'Priority email support', 'uncoder' ),
						'included' => true,
					),
					array(
						'text'     => __( 'Dedicated account manager', 'uncoder' ),
						'included' => false,
					),
				),
				'ai'          => 'Rows: {"text":"…","included":true|false,"tooltip":"optional"}. List features in the same order on every plan.',
			)
		);
		$this->add_control(
			'included_icon',
			array(
				'type'    => 'icon',
				'label'   => __( 'Included icon', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'check' ),
			)
		);
		$this->add_control(
			'excluded_icon',
			array(
				'type'    => 'icon',
				'label'   => __( 'Not included icon', 'uncoder' ),
				'default' => array( 'library' => 'lucide', 'value' => 'x' ),
			)
		);
		$this->add_control(
			'strike_excluded',
			array(
				'type'    => 'switch',
				'label'   => __( 'Strike through features not included', 'uncoder' ),
				'default' => false,
			)
		);
		$this->end_section();

		$this->start_section( 'content_button', array( 'label' => __( 'Button', 'uncoder' ) ) );
		$this->add_control(
			'button_text',
			array(
				'type'    => 'text',
				'label'   => __( 'Text', 'uncoder' ),
				'default' => __( 'Choose Pro', 'uncoder' ),
				'dynamic' => true,
				'inline'  => true,
			)
		);
		$this->add_control(
			'link',
			array(
				'type'    => 'url',
				'label'   => __( 'Link', 'uncoder' ),
				'default' => array( 'url' => '#' ),
				'dynamic' => true,
			)
		);
		$this->add_control(
			'button_variant',
			array(
				'type'    => 'select',
				'label'   => __( 'Style', 'uncoder' ),
				'default' => 'primary',
				'options' => Call_To_Action::variant_options(),
				'ai'      => 'Use "primary" on the highlighted plan and "outline" on the others.',
			)
		);
		$this->add_control(
			'button_size',
			array(
				'type'    => 'choose',
				'label'   => __( 'Size', 'uncoder' ),
				'default' => 'md',
				'options' => Call_To_Action::size_options(),
			)
		);
		$this->add_control(
			'button_full',
			array(
				'type'    => 'switch',
				'label'   => __( 'Full width', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'button_position',
			array(
				'type'    => 'select',
				'label'   => __( 'Position', 'uncoder' ),
				'default' => 'bottom',
				'options' => array(
					'top'    => __( 'Below the price', 'uncoder' ),
					'bottom' => __( 'After the features', 'uncoder' ),
				),
			)
		);
		$this->end_section();

		$this->start_section( 'content_footer', array( 'label' => __( 'Footer & badge', 'uncoder' ) ) );
		$this->add_control(
			'footer_note',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Footer note', 'uncoder' ),
				'html'    => 'inline',
				'rows'    => 2,
				'default' => __( '14-day free trial. No credit card required.', 'uncoder' ),
				'inline'  => true,
			)
		);
		$this->add_control(
			'highlighted',
			array(
				'type'        => 'switch',
				'label'       => __( 'Highlight this plan', 'uncoder' ),
				'description' => __( 'Accent border and shadow for the recommended plan.', 'uncoder' ),
				'default'     => false,
			)
		);
		$this->add_control(
			'badge_text',
			array(
				'type'        => 'text',
				'label'       => __( 'Badge', 'uncoder' ),
				'placeholder' => __( 'Most popular', 'uncoder' ),
				'inline'      => true,
			)
		);
		$this->add_control(
			'badge_style',
			array(
				'type'      => 'select',
				'label'     => __( 'Badge style', 'uncoder' ),
				'default'   => 'pill',
				'options'   => array(
					'pill'   => __( 'Pill on the top edge', 'uncoder' ),
					'corner' => __( 'Tag in the corner', 'uncoder' ),
					'ribbon' => __( 'Diagonal ribbon', 'uncoder' ),
				),
				'condition' => array( 'badge_text!' => '' ),
			)
		);
		$this->add_control(
			'badge_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Badge side', 'uncoder' ),
				'default'   => 'right',
				'options'   => array(
					'left'  => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'arrow-left-to-line' ),
					'right' => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
				),
				'condition' => array(
					'badge_text!' => '',
					'badge_style' => array( 'corner', 'ribbon' ),
				),
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => '--uncoder-pt-align:flex-start;--uncoder-pt-text:start',
					'center' => '--uncoder-pt-align:center;--uncoder-pt-text:center',
					'right'  => '--uncoder-pt-align:flex-end;--uncoder-pt-text:end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_group( 'background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->start_tabs( 'box_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_group( 'hover_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}:hover' ) );
		$this->add_control(
			'hover_lift',
			array(
				'type'      => 'slider',
				'label'     => __( 'Lift', 'uncoder' ),
				'size_units' => array( 'px' ),
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
				'selectors' => array( '{{WRAPPER}}:hover' => 'transform: translateY(calc(-1 * {{VALUE}}))' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_control(
			'accent_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Accent color', 'uncoder' ),
				'description' => __( 'Highlight border, badge and included icons.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-pt-accent: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'highlight_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Highlighted background', 'uncoder' ),
				'condition' => array( 'highlighted' => true ),
				'selectors' => array( '{{WRAPPER}}.uncoder-price-table--highlighted' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_header', array( 'label' => __( 'Header', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'icon_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Icon color', 'uncoder' ),
				'condition' => array( 'header_icon.value!' => '' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__icon' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'condition'  => array( 'header_icon.value!' => '' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__icon' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_group( 'title_typography', array( 'type' => 'typography', 'label' => __( 'Title typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-table__title' ) );
		$this->add_control(
			'title_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Title color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__title' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'subtitle_typography', array( 'type' => 'typography', 'label' => __( 'Subtitle typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-table__subtitle' ) );
		$this->add_control(
			'subtitle_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Subtitle color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__subtitle' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'header_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__header' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_price', array( 'label' => __( 'Price', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'price_typography', array( 'type' => 'typography', 'label' => __( 'Price typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-table__amount' ) );
		$this->add_control(
			'price_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Price color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__amount' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'currency_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Currency size', 'uncoder' ),
				'size_units' => array( 'em', 'px' ),
				'range'      => array( 'em' => array( 'min' => 0.2, 'max' => 1, 'step' => 0.05 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__currency' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'currency_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Currency & fraction position', 'uncoder' ),
				'options'              => array(
					'top'      => array( 'label' => __( 'Raised', 'uncoder' ), 'icon' => 'align-start-vertical' ),
					'baseline' => array( 'label' => __( 'Baseline', 'uncoder' ), 'icon' => 'align-end-vertical' ),
				),
				'selectors_dictionary' => array(
					'top'      => 'align-items:flex-start',
					'baseline' => 'align-items:baseline',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-price-table__amount' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'fraction_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Fraction size', 'uncoder' ),
				'size_units' => array( 'em', 'px' ),
				'range'      => array( 'em' => array( 'min' => 0.2, 'max' => 1, 'step' => 0.05 ) ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__fraction' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_group( 'period_typography', array( 'type' => 'typography', 'label' => __( 'Period typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-table__period' ) );
		$this->add_control(
			'period_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Period color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__period' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'original_typography', array( 'type' => 'typography', 'label' => __( 'Original price typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-table__original', 'condition' => array( 'original_price!' => '' ) ) );
		$this->add_control(
			'original_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Original price color', 'uncoder' ),
				'condition' => array( 'original_price!' => '' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__original' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'price_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__pricing' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_features', array( 'label' => __( 'Features', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'features_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-table__features' ) );
		$this->add_control(
			'features_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__features' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'included_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Included icon color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-pt-yes: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'excluded_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Not included color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-pt-no: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'feature_icon_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Icon size', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__feature-icon' => 'font-size: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'features_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between rows', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__features' => 'row-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'features_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'List alignment', 'uncoder' ),
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
				),
				'selectors_dictionary' => array(
					'left'   => 'align-self:stretch;--uncoder-pt-feature-justify:flex-start',
					'center' => 'align-self:center;--uncoder-pt-feature-justify:center',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-price-table__features' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'features_divider',
			array(
				'type'    => 'switch',
				'label'   => __( 'Dividers between rows', 'uncoder' ),
				'default' => false,
			)
		);
		$this->add_control(
			'divider_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Divider color', 'uncoder' ),
				'condition' => array( 'features_divider' => true ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-pt-divider: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'features_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below list', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__features' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->add_control( 'tooltip_heading', array( 'type' => 'heading', 'label' => __( 'Tooltip', 'uncoder' ) ) );
		$this->add_control(
			'tooltip_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-pt-tip-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'tooltip_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__tip-text' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_button', array( 'label' => __( 'Button', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'button_text!' => '' ) ) );
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-table__button' ) );
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( 'button_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-price-table__button' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-price-table__button' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-price-table__button' => 'box-shadow: inset 0 0 0 1.5px {{VALUE}}' ) ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( 'button_hover_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-price-table__button:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-price-table__button:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ) );
		$this->add_control( 'button_hover_border_color', array( 'type' => 'color', 'label' => __( 'Border color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-price-table__button:is(:hover, :focus-visible)' => 'box-shadow: inset 0 0 0 1.5px {{VALUE}}' ) ) );
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__button' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__button' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing below', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__action' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_footer', array( 'label' => __( 'Footer note', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'footer_note!' => '' ) ) );
		$this->add_group( 'footer_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-table__footer' ) );
		$this->add_control(
			'footer_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__footer' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_badge', array( 'label' => __( 'Badge', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'badge_text!' => '' ) ) );
		$this->add_control(
			'badge_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__badge-text' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'badge_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-price-table__badge-text' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'badge_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-price-table__badge-text' ) );
		$this->add_responsive_control(
			'badge_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em' ),
				'condition'  => array( 'badge_style!' => 'ribbon' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-price-table__badge-text' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * Splits the price parts into visual markup and a screen-reader sentence.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array{visual:string, sr:string}
	 */
	private function price_parts( array $s ): array {
		$currency = (string) ( $s['currency'] ?? '' );
		$price    = trim( (string) ( $s['price'] ?? '' ) );
		$fraction = trim( (string) ( $s['fraction'] ?? '' ) );
		$period   = trim( (string) ( $s['period'] ?? '' ) );
		$original = trim( (string) ( $s['original_price'] ?? '' ) );
		$after    = 'after' === ( $s['currency_position'] ?? 'before' );

		$money = static function ( string $amount, string $frac ) use ( $currency, $after ): string {
			$full = $amount . ( '' !== $frac ? '.' . $frac : '' );
			return $after ? $full . $currency : $currency . $full;
		};

		$cur_html = '' !== $currency ? '<span class="uncoder-price-table__currency">' . esc_html( $currency ) . '</span>' : '';
		$amount   = '<span class="uncoder-price-table__amount">' . ( $after ? '' : $cur_html ) . '<span class="uncoder-price-table__integer">' . esc_html( $price ) . '</span>';
		if ( '' !== $fraction ) {
			$amount .= '<span class="uncoder-price-table__fraction">' . esc_html( $fraction ) . '</span>';
		}
		$amount .= ( $after ? $cur_html : '' ) . '</span>';

		$visual = '';
		if ( '' !== $original ) {
			$visual .= '<del class="uncoder-price-table__original">' . esc_html( $money( $original, '' ) ) . '</del>';
		}
		$visual .= $amount;
		if ( '' !== $period ) {
			$visual .= '<span class="uncoder-price-table__period">' . esc_html( $period ) . '</span>';
		}

		$spoken_period = preg_replace( '#^/\s*#', __( 'per', 'uncoder' ) . ' ', $period );
		$sr            = trim( $money( $price, $fraction ) . ' ' . $spoken_period );
		if ( '' !== $original ) {
			/* translators: 1: original price, 2: current price. */
			$sr = sprintf( __( 'Original price %1$s, now %2$s', 'uncoder' ), $money( $original, '' ), $sr );
		}
		return array(
			'visual' => $visual,
			'sr'     => $sr,
		);
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function button_html( array $s, Render_Context $ctx ): string {
		$text = trim( (string) ( $s['button_text'] ?? '' ) );
		if ( '' === $text ) {
			return '';
		}
		$variant        = in_array( $s['button_variant'] ?? 'primary', Call_To_Action::VARIANTS, true ) ? $s['button_variant'] : 'primary';
		$size           = in_array( $s['button_size'] ?? 'md', Call_To_Action::SIZES, true ) ? $s['button_size'] : 'md';
		$attrs          = $this->link_attrs( $s['link'] ?? array() );
		$attrs['class'] = array( 'uncoder-btn', 'uncoder-btn--' . $variant, 'uncoder-btn--' . $size, 'uncoder-price-table__button' );
		$tag            = isset( $attrs['href'] ) ? 'a' : 'span';
		return '<div class="uncoder-price-table__action"><' . $tag . Utils::attrs( $attrs ) . '><span class="uncoder-btn__text"' . $ctx->inline( 'button_text' ) . '>' . esc_html( $text ) . '</span></' . $tag . '></div>';
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function features_html( array $s, Render_Context $ctx ): string {
		$rows = is_array( $s['features'] ?? null ) ? $s['features'] : array();
		$html = '';
		foreach ( array_values( $rows ) as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$text = trim( (string) ( $row['text'] ?? '' ) );
			if ( '' === $text ) {
				continue;
			}
			$included = ! array_key_exists( 'included', $row ) || ! empty( $row['included'] );
			$icon_val = $this->has_icon( $row['icon'] ?? null ) ? $row['icon'] : ( $included ? ( $s['included_icon'] ?? 'check' ) : ( $s['excluded_icon'] ?? 'x' ) );
			$icon     = $this->render_icon( $icon_val );
			$classes  = array( 'uncoder-price-table__feature', 'uncoder-price-table__feature--' . ( $included ? 'included' : 'excluded' ) );
			if ( ! empty( $row['_id'] ) && is_string( $row['_id'] ) ) {
				$classes[] = 'uncoder-ri-' . sanitize_html_class( $row['_id'] );
			}
			$html .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			if ( '' !== $icon ) {
				$html .= '<span class="uncoder-price-table__feature-icon">' . $icon . '</span>';
			}
			$html .= '<span class="uncoder-price-table__feature-text">';
			if ( ! $included ) {
				$html .= '<span class="uncoder-sr-only">' . esc_html__( 'Not included:', 'uncoder' ) . ' </span>';
			}
			$html   .= esc_html( $text ) . '</span>';
			$tooltip = trim( (string) ( $row['tooltip'] ?? '' ) );
			if ( '' !== $tooltip ) {
				$tip_id = 'uncoder-pt-' . sanitize_html_class( $ctx->element_id ) . '-' . $i;
				$html  .= '<span class="uncoder-price-table__tip">'
					. '<button type="button" class="uncoder-price-table__tip-button" aria-describedby="' . esc_attr( $tip_id ) . '">'
					. $this->render_icon( 'info' )
					. '<span class="uncoder-sr-only">' . esc_html__( 'More information', 'uncoder' ) . '</span></button>'
					. '<span class="uncoder-price-table__tip-text" role="tooltip" id="' . esc_attr( $tip_id ) . '">' . esc_html( $tooltip ) . '</span>'
					. '</span>';
			}
			$html .= '</li>';
		}
		return '' !== $html ? '<ul class="uncoder-price-table__features">' . $html . '</ul>' : '';
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$classes = array( 'uncoder-price-table' );
		if ( ! empty( $s['highlighted'] ) ) {
			$classes[] = 'uncoder-price-table--highlighted';
		}
		if ( ! empty( $s['button_full'] ) ) {
			$classes[] = 'uncoder-price-table--button-full';
		}
		if ( ! empty( $s['features_divider'] ) ) {
			$classes[] = 'uncoder-price-table--dividers';
		}
		if ( ! empty( $s['strike_excluded'] ) ) {
			$classes[] = 'uncoder-price-table--strike';
		}
		if ( 'below' === ( $s['period_position'] ?? 'beside' ) ) {
			$classes[] = 'uncoder-price-table--period-below';
		}

		$badge = trim( (string) ( $s['badge_text'] ?? '' ) );
		if ( '' !== $badge ) {
			$style     = in_array( $s['badge_style'] ?? 'pill', array( 'pill', 'corner', 'ribbon' ), true ) ? $s['badge_style'] : 'pill';
			$side      = 'left' === ( $s['badge_position'] ?? 'right' ) ? 'left' : 'right';
			$classes[] = 'uncoder-price-table--badge-' . $style;
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( '' !== $badge ) {
			$badge_class = 'uncoder-price-table__badge uncoder-price-table__badge--' . $style . ( 'pill' !== $style ? ' uncoder-price-table__badge--' . $side : '' );
			echo '<div class="' . esc_attr( $badge_class ) . '"><span class="uncoder-price-table__badge-text"' . $ctx->inline( 'badge_text' ) . '>' . esc_html( $badge ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
		}

		// Header.
		$title    = trim( (string) ( $s['title'] ?? '' ) );
		$subtitle = trim( (string) ( $s['subtitle'] ?? '' ) );
		$icon     = $this->has_icon( $s['header_icon'] ?? null ) ? '<span class="uncoder-price-table__icon">' . $this->render_icon( $s['header_icon'] ) . '</span>' : '';
		if ( '' !== $title || '' !== $subtitle || '' !== $icon || $ctx->editor ) {
			$tag = Utils::tag( $s['title_tag'] ?? 'h3', Utils::HEADING_TAGS, 'h3' );
			echo '<div class="uncoder-price-table__header">' . $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup is escaped.
			if ( '' !== $title || $ctx->editor ) {
				echo '<' . $tag . ' class="uncoder-price-table__title"' . $ctx->inline( 'title' ) . '>' . esc_html( $title ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allow-listed tag.
			}
			if ( '' !== $subtitle ) {
				echo '<p class="uncoder-price-table__subtitle"' . $ctx->inline( 'subtitle' ) . '>' . esc_html( $subtitle ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
			}
			echo '</div>';
		}

		// Price.
		if ( '' !== trim( (string) ( $s['price'] ?? '' ) ) ) {
			$parts = $this->price_parts( $s );
			echo '<div class="uncoder-price-table__pricing"><p class="uncoder-price-table__price">';
			echo '<span class="uncoder-sr-only">' . esc_html( $parts['sr'] ) . '</span>';
			echo '<span class="uncoder-price-table__price-visual" aria-hidden="true">' . $parts['visual'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			echo '</p></div>';
		}

		$button = $this->button_html( $s, $ctx );
		$top    = 'top' === ( $s['button_position'] ?? 'bottom' );
		if ( $top ) {
			echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}
		echo $this->features_html( $s, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		if ( ! $top ) {
			echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}

		$footer = $this->inline_html( $s['footer_note'] ?? '' );
		if ( '' !== trim( wp_strip_all_tags( $footer ) ) ) {
			echo '<p class="uncoder-price-table__footer"' . $ctx->inline( 'footer_note' ) . '>' . $footer . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd inline HTML.
		}
		echo '</div>';
	}
}
