<?php
/**
 * Login form widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Widgets\Support\Theme_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end login form posting to wp-login.php, with redirect, remember me, lost password and
 * register links; logged-in visitors see a "Logged in as …" message.
 */
class Login extends Widget_Base {

	private const FIELDS = '{{WRAPPER}} .uncoder-login__input';

	public function name(): string {
		return 'login';
	}

	public function title(): string {
		return __( 'Login', 'uncoder' );
	}

	public function icon(): string {
		return 'log-in';
	}

	public function category(): string {
		return 'forms';
	}

	public function keywords(): array {
		return array( 'login', 'log in', 'sign in', 'account', 'user', 'password', 'member' );
	}

	public function description(): string {
		return __( 'A login form for site users (posts to wp-login.php) with remember me, lost password and register links. Logged-in visitors see who they are logged in as, with a log out link.', 'uncoder' );
	}

	public function render_mode(): string {
		return 'dynamic';
	}

	protected function register_controls(): void {
		$this->start_section( 'content', array( 'label' => __( 'Form', 'uncoder' ) ) );
		$this->add_control(
			'show_labels',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show labels', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'user_label',
			array(
				'type'    => 'text',
				'label'   => __( 'Username label', 'uncoder' ),
				'default' => __( 'Username or email address', 'uncoder' ),
			)
		);
		$this->add_control(
			'user_placeholder',
			array(
				'type'  => 'text',
				'label' => __( 'Username placeholder', 'uncoder' ),
			)
		);
		$this->add_control(
			'pass_label',
			array(
				'type'    => 'text',
				'label'   => __( 'Password label', 'uncoder' ),
				'default' => __( 'Password', 'uncoder' ),
			)
		);
		$this->add_control(
			'pass_placeholder',
			array(
				'type'  => 'text',
				'label' => __( 'Password placeholder', 'uncoder' ),
			)
		);
		$this->add_control(
			'show_remember',
			array(
				'type'    => 'switch',
				'label'   => __( 'Remember me', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'remember_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Remember me label', 'uncoder' ),
				'default'   => __( 'Remember me', 'uncoder' ),
				'condition' => array( 'show_remember' => true ),
			)
		);
		$this->add_control(
			'button_text',
			array(
				'type'    => 'text',
				'label'   => __( 'Button text', 'uncoder' ),
				'default' => __( 'Log in', 'uncoder' ),
				'inline'  => true,
			)
		);
		$this->add_control(
			'redirect',
			array(
				'type'    => 'select',
				'label'   => __( 'After login, go to', 'uncoder' ),
				'default' => 'current',
				'options' => array(
					'current' => __( 'The same page', 'uncoder' ),
					'home'    => __( 'Home page', 'uncoder' ),
					'admin'   => __( 'Dashboard', 'uncoder' ),
					'custom'  => __( 'Custom URL', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'redirect_url',
			array(
				'type'      => 'url',
				'label'     => __( 'Redirect URL', 'uncoder' ),
				'condition' => array( 'redirect' => 'custom' ),
			)
		);
		$this->add_control(
			'show_lost',
			array(
				'type'    => 'switch',
				'label'   => __( 'Lost password link', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'lost_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Lost password text', 'uncoder' ),
				'default'   => __( 'Forgot your password?', 'uncoder' ),
				'condition' => array( 'show_lost' => true ),
			)
		);
		$this->add_control(
			'show_register',
			array(
				'type'        => 'switch',
				'label'       => __( 'Register link', 'uncoder' ),
				'description' => __( 'Shown only when "Anyone can register" is enabled in Settings → General.', 'uncoder' ),
				'default'     => true,
			)
		);
		$this->add_control(
			'register_text',
			array(
				'type'      => 'text',
				'label'     => __( 'Register text', 'uncoder' ),
				'default'   => __( 'Create an account', 'uncoder' ),
				'condition' => array( 'show_register' => true ),
			)
		);
		$this->end_section();

		$this->start_section( 'logged_in', array( 'label' => __( 'Logged-in visitors', 'uncoder' ) ) );
		$this->add_control(
			'logged_in',
			array(
				'type'    => 'select',
				'label'   => __( 'Show', 'uncoder' ),
				'default' => 'message',
				'options' => array(
					'message' => __( '"Logged in as" message with log out link', 'uncoder' ),
					'nothing' => __( 'Nothing', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'logout_redirect',
			array(
				'type'      => 'select',
				'label'     => __( 'After log out, go to', 'uncoder' ),
				'default'   => 'current',
				'options'   => array(
					'current' => __( 'The same page', 'uncoder' ),
					'home'    => __( 'Home page', 'uncoder' ),
					'custom'  => __( 'Custom URL', 'uncoder' ),
				),
				'condition' => array( 'logged_in' => 'message' ),
			)
		);
		$this->add_control(
			'logout_url',
			array(
				'type'      => 'url',
				'label'     => __( 'Log out redirect URL', 'uncoder' ),
				'condition' => array(
					'logged_in'       => 'message',
					'logout_redirect' => 'custom',
				),
			)
		);
		$this->add_control(
			'preview_logged_in',
			array(
				'type'        => 'switch',
				'label'       => __( 'Preview as logged in', 'uncoder' ),
				'description' => __( 'Editor only: shows what logged-in visitors see.', 'uncoder' ),
				'condition'   => array( 'logged_in' => 'message' ),
			)
		);
		$this->end_section();

		$this->register_style_controls();
	}

	private function register_style_controls(): void {
		$this->start_section( 'style_form', array( 'label' => __( 'Form', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'form_width',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'uncoder' ),
				'size_units' => array( 'px', '%', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'max-width: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'form_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Position', 'uncoder' ),
				'options'              => array(
					'left'   => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center' => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'  => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => 'margin-inline:0 auto',
					'center' => 'margin-inline:auto',
					'right'  => 'margin-inline:auto 0',
				),
				'selectors'            => array( '{{WRAPPER}}' => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space between fields', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-login-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section(
			'style_labels',
			array(
				'label'     => __( 'Labels', 'uncoder' ),
				'tab'       => 'style',
				'condition' => array( 'show_labels' => true ),
			)
		);
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-login__label' ) );
		$this->add_control(
			'label_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-login__label' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'label_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Spacing', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-login__label' => 'margin-bottom: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_fields', array( 'label' => __( 'Fields', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'field_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::FIELDS ) );
		$this->start_tabs( 'field_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'field_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( self::FIELDS => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'field_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::FIELDS => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'field_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::FIELDS ) );
		$this->end_tab();
		$this->start_tab( 'focus', __( 'Focus', 'uncoder' ) );
		$this->add_control(
			'field_focus_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::FIELDS . ':focus' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'field_focus_border',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( self::FIELDS . ':focus' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'field_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::FIELDS => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'field_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::FIELDS => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'remember_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Remember me color', 'uncoder' ),
				'condition' => array( 'show_remember' => true ),
				'selectors' => array( '{{WRAPPER}} .uncoder-login__remember' => 'color: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_button', array( 'label' => __( 'Button', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-login__button' ) );
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'button_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-login__button' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-login__button' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'button_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-login__button' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'button_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-login__button:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_hover_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-login__button:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'button_hover_border',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-login__button:is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'button_width',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Width', 'uncoder' ),
				'options'              => array(
					'full' => array( 'label' => __( 'Full width', 'uncoder' ), 'icon' => 'move-horizontal' ),
					'auto' => array( 'label' => __( 'Fit content', 'uncoder' ), 'icon' => 'minimize-2' ),
				),
				'selectors_dictionary' => array(
					'full' => 'width:100%',
					'auto' => 'width:auto',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-login__button' => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-login__button' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-login__button' => 'padding: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_links', array( 'label' => __( 'Links & message', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'links_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} :is(.uncoder-login__links, .uncoder-login-status)' ) );
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} :is(.uncoder-login__links, .uncoder-login-status)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'links_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} :is(.uncoder-login__links, .uncoder-login-status) a' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'links_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Link hover color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} :is(.uncoder-login__links, .uncoder-login-status) a:hover' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'links_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'start'   => array( 'label' => __( 'Start', 'uncoder' ), 'icon' => 'align-left' ),
					'center'  => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'end'     => array( 'label' => __( 'End', 'uncoder' ), 'icon' => 'align-right' ),
					'between' => array( 'label' => __( 'Space between', 'uncoder' ), 'icon' => 'align-justify' ),
				),
				'selectors_dictionary' => array(
					'start'   => 'flex-start',
					'center'  => 'center',
					'end'     => 'flex-end',
					'between' => 'space-between',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-login__links' => 'justify-content: {{VALUE}}' ),
			)
		);
		$this->end_section();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function redirect_url( array $s ): string {
		switch ( $s['redirect'] ?? 'current' ) {
			case 'home':
				return home_url( '/' );
			case 'admin':
				return admin_url();
			case 'custom':
				$url = (string) ( $s['redirect_url']['url'] ?? '' );
				return '' !== $url ? $url : Theme_Context::current_url();
		}
		return Theme_Context::current_url();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function logged_in( array $s ): void {
		if ( 'message' !== ( $s['logged_in'] ?? 'message' ) ) {
			return;
		}
		$user = wp_get_current_user();
		switch ( $s['logout_redirect'] ?? 'current' ) {
			case 'home':
				$after = home_url( '/' );
				break;
			case 'custom':
				$after = (string) ( $s['logout_url']['url'] ?? '' );
				$after = '' !== $after ? $after : Theme_Context::current_url();
				break;
			default:
				$after = Theme_Context::current_url();
		}
		/* translators: %s: user display name. */
		$message = sprintf( esc_html__( 'Logged in as %s', 'uncoder' ), '<strong class="uncoder-login-status__name">' . esc_html( $user->display_name ) . '</strong>' );
		echo '<p class="uncoder-login-status">' . $message . ' <span class="uncoder-login-status__sep" aria-hidden="true">·</span> <a class="uncoder-login-status__logout" href="' . esc_url( wp_logout_url( $after ) ) . '">' . esc_html__( 'Log out', 'uncoder' ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$logged_in = is_user_logged_in();
		if ( $logged_in && ( ! $ctx->editor || ( ! empty( $s['preview_logged_in'] ) && 'message' === ( $s['logged_in'] ?? 'message' ) ) ) ) {
			$this->logged_in( $s );
			return;
		}

		$id          = 'uncoder-login-' . $ctx->element_id;
		$labels      = ! empty( $s['show_labels'] );
		$label_class = $labels ? 'uncoder-login__label' : 'uncoder-login__label uncoder-sr-only';
		$user_label  = trim( (string) ( $s['user_label'] ?? '' ) );
		$user_label  = '' !== $user_label ? $user_label : __( 'Username or email address', 'uncoder' );
		$pass_label  = trim( (string) ( $s['pass_label'] ?? '' ) );
		$pass_label  = '' !== $pass_label ? $pass_label : __( 'Password', 'uncoder' );
		$button      = trim( (string) ( $s['button_text'] ?? '' ) );
		$button      = '' !== $button ? $button : __( 'Log in', 'uncoder' );
		$redirect    = $this->redirect_url( $s );

		// Same extension points as wp_login_form(), so login plugins (2FA, captcha…) can add fields.
		$args = array(
			'echo'           => false,
			'redirect'       => $redirect,
			'form_id'        => $id,
			'label_username' => $user_label,
			'label_password' => $pass_label,
			'label_remember' => (string) ( $s['remember_label'] ?? '' ),
			'label_log_in'   => $button,
			'id_username'    => $id . '-user',
			'id_password'    => $id . '-pass',
			'id_remember'    => $id . '-remember',
			'id_submit'      => $id . '-submit',
			'remember'       => ! empty( $s['show_remember'] ),
			'value_username' => '',
			'value_remember' => false,
		);

		echo '<form class="uncoder-login" action="' . esc_url( site_url( 'wp-login.php', 'login_post' ) ) . '" method="post">';
		echo apply_filters( 'login_form_top', '', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core wp_login_form() hook, plugin markup.

		echo '<p class="uncoder-login__field uncoder-login__field--user">';
		echo '<label class="' . esc_attr( $label_class ) . '" for="' . esc_attr( $id . '-user' ) . '">' . esc_html( $user_label ) . '</label>';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '<input' . Utils::attrs(
			array(
				'type'           => 'text',
				'name'           => 'log',
				'id'             => $id . '-user',
				'class'          => 'uncoder-login__input',
				'autocomplete'   => 'username',
				'autocapitalize' => 'off',
				'spellcheck'     => 'false',
				'placeholder'    => (string) ( $s['user_placeholder'] ?? '' ),
				'required'       => true,
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</p>';

		echo '<p class="uncoder-login__field uncoder-login__field--pass">';
		echo '<label class="' . esc_attr( $label_class ) . '" for="' . esc_attr( $id . '-pass' ) . '">' . esc_html( $pass_label ) . '</label>';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		echo '<input' . Utils::attrs(
			array(
				'type'         => 'password',
				'name'         => 'pwd',
				'id'           => $id . '-pass',
				'class'        => 'uncoder-login__input',
				'autocomplete' => 'current-password',
				'spellcheck'   => 'false',
				'placeholder'  => (string) ( $s['pass_placeholder'] ?? '' ),
				'required'     => true,
			)
		) . '>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</p>';

		echo apply_filters( 'login_form_middle', '', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core wp_login_form() hook, plugin markup.

		if ( ! empty( $s['show_remember'] ) ) {
			$remember = trim( (string) ( $s['remember_label'] ?? '' ) );
			echo '<p class="uncoder-login__remember"><label for="' . esc_attr( $id . '-remember' ) . '"><input type="checkbox" name="rememberme" id="' . esc_attr( $id . '-remember' ) . '" value="forever"> ' . esc_html( '' !== $remember ? $remember : __( 'Remember me', 'uncoder' ) ) . '</label></p>';
		}

		echo '<p class="uncoder-login__submit">';
		echo '<button type="submit" name="wp-submit" id="' . esc_attr( $id . '-submit' ) . '" class="uncoder-login__button"><span' . $ctx->inline( 'button_text' ) . '>' . esc_html( $button ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
		echo '<input type="hidden" name="redirect_to" value="' . esc_url( $redirect ) . '">';
		echo '</p>';

		$links = array();
		if ( ! empty( $s['show_lost'] ) ) {
			$text    = trim( (string) ( $s['lost_text'] ?? '' ) );
			$links[] = '<a class="uncoder-login__link uncoder-login__link--lost" href="' . esc_url( wp_lostpassword_url( $redirect ) ) . '">' . esc_html( '' !== $text ? $text : __( 'Forgot your password?', 'uncoder' ) ) . '</a>';
		}
		if ( ! empty( $s['show_register'] ) && get_option( 'users_can_register' ) ) {
			$text    = trim( (string) ( $s['register_text'] ?? '' ) );
			$links[] = '<a class="uncoder-login__link uncoder-login__link--register" href="' . esc_url( wp_registration_url() ) . '">' . esc_html( '' !== $text ? $text : __( 'Create an account', 'uncoder' ) ) . '</a>';
		}
		if ( $links ) {
			echo '<p class="uncoder-login__links">' . implode( '', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}

		echo apply_filters( 'login_form_bottom', '', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core wp_login_form() hook, plugin markup.
		echo '</form>';
	}
}
