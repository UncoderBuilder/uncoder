<?php
/**
 * My account widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Widgets\Support\Woo_Page_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's account area ([woocommerce_my_account]): the account menu with dashboard, orders, downloads,
 * addresses and account details (and pages extensions add), or the login and registration forms for visitors.
 */
class My_Account extends Woo_Page_Widget {

	private const NAV = self::W . ' .woocommerce-MyAccount-navigation';

	public function name(): string {
		return 'my-account';
	}

	public function title(): string {
		return __( 'My Account', 'uncoder' );
	}

	public function icon(): string {
		return 'circle-user';
	}

	public function keywords(): array {
		return array( 'account', 'my account', 'login', 'register', 'orders', 'customer', 'woocommerce' );
	}

	public function description(): string {
		return __( 'The customer account: menu, orders, downloads, addresses and details, or login and registration for visitors. Place it on the My account page.', 'uncoder' );
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'My account', 'uncoder' ) ) );
		$this->woo_notice();
		$this->add_responsive_control(
			'layout',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Menu', 'uncoder' ),
				'options'              => array(
					'side' => array( 'label' => __( 'Beside the content', 'uncoder' ), 'icon' => 'panel-left' ),
					'top'  => array( 'label' => __( 'Above the content', 'uncoder' ), 'icon' => 'panel-top' ),
				),
				'selectors_dictionary' => array(
					'side' => '--uncoder-account-cols: var(--uncoder-account-nav) minmax(0, 1fr); --uncoder-account-content-col: 2; --uncoder-account-nav-dir: column',
					'top'  => '--uncoder-account-cols: minmax(0, 1fr); --uncoder-account-content-col: 1; --uncoder-account-nav-dir: row',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
				'description'          => __( 'Empty: beside on desktops, above on phones.', 'uncoder' ),
			)
		);
		$this->add_responsive_control(
			'nav_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Menu width', 'uncoder' ),
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-account-nav: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Gap', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-account-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'preview',
			array(
				'type'        => 'choose',
				'label'       => __( 'Preview as', 'uncoder' ),
				'default'     => 'customer',
				'options'     => array(
					'customer' => array( 'label' => __( 'Logged-in customer', 'uncoder' ), 'icon' => 'circle-user' ),
					'visitor'  => array( 'label' => __( 'Visitor (login form)', 'uncoder' ), 'icon' => 'log-in' ),
				),
				'description' => __( 'Editor only: which view to design. The site shows what fits the visitor.', 'uncoder' ),
			)
		);
		$this->end_section();

		$link = self::NAV . ' ul li a';
		$this->start_section( 'style_nav', array( 'label' => __( 'Menu', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'nav_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $link ) );
		$this->add_control( 'nav_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-account-link: {{VALUE}}' ) ) );
		$this->add_control( 'nav_active_color', array( 'type' => 'color', 'label' => __( 'Current page text', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-account-active: {{VALUE}}' ) ) );
		$this->add_control( 'nav_active_background', array( 'type' => 'color', 'label' => __( 'Current page background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}}' => '--uncoder-account-active-bg: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'nav_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Item padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $link => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'nav_radius',
			array(
				'type'       => 'slider',
				'label'      => __( 'Item radius', 'uncoder' ),
				'size_units' => array( 'px' ),
				'selectors'  => array( $link => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_content', array( 'label' => __( 'Content', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'content_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::W . ' .woocommerce-MyAccount-content' ) );
		$this->add_control( 'content_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( self::W . ' .woocommerce-MyAccount-content' => 'color: {{VALUE}}' ) ) );
		// "Login", "Register", "Order details", "Billing address"…
		$h2 = self::W . ' > .woocommerce h2';
		$this->add_control( 'headings_heading', array( 'type' => 'heading', 'label' => __( 'Headings', 'uncoder' ) ) );
		$this->add_group( 'heading_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => $h2 ) );
		$this->add_control( 'heading_color', array( 'type' => 'color', 'label' => __( 'Color', 'uncoder' ), 'selectors' => array( $h2 => 'color: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'heading_align',
			array(
				'type'      => 'choose',
				'label'     => __( 'Alignment', 'uncoder' ),
				'options'   => Heading::ALIGN,
				'selectors' => array( $h2 => 'text-align: {{VALUE}}' ),
			)
		);
		$this->add_control( 'forms_heading', array( 'type' => 'heading', 'label' => __( 'Login and registration boxes', 'uncoder' ) ) );
		$box = self::W . ' :is(form.login, form.register, form.lost_reset_password)';
		$this->add_control( 'box_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( $box => 'background-color: {{VALUE}}' ) ) );
		$this->add_group( 'box_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => $box ) );
		$this->add_responsive_control(
			'box_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $box => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'box_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $box => 'padding: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	protected function live( array $s ): string {
		return (string) \WC_Shortcodes::my_account( array() );
	}

	protected function sample( array $s, Render_Context $ctx ): string {
		ob_start();
		if ( 'visitor' === ( $s['preview'] ?? 'customer' ) || ! is_user_logged_in() ) {
			wc_get_template( 'myaccount/form-login.php' );
			$html = (string) ob_get_clean();
			// No logins from the editor canvas.
			return '<div class="woocommerce"><div class="woocommerce-notices-wrapper"></div>' . str_replace( '<form ', '<form inert ', $html ) . '</div>';
		}
		wc_get_template( 'myaccount/navigation.php' );
		$nav = (string) ob_get_clean();
		// The editor's request is not the account page: mark the dashboard as the page shown.
		if ( false === strpos( $nav, 'is-active' ) ) {
			$nav = preg_replace( '/woocommerce-MyAccount-navigation-link--dashboard/', 'woocommerce-MyAccount-navigation-link--dashboard is-active', $nav, 1 );
		}
		ob_start();
		wc_get_template( 'myaccount/dashboard.php', array( 'current_user' => wp_get_current_user() ) );
		$content = (string) ob_get_clean();
		return '<div class="woocommerce">' . $nav . '<div class="woocommerce-MyAccount-content"><div class="woocommerce-notices-wrapper"></div>' . $content . '</div></div>';
	}
}
