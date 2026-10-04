<?php
/**
 * Form widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;
use Uncoder\Builder\Forms\Fields;
use Uncoder\Builder\Forms\Forms;
use Uncoder\Builder\Forms\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Contact / lead form. Submissions are validated and processed server-side from the saved settings
 * (Forms\Forms); emails, webhook and redirect settings are never printed on the page.
 */
class Form extends Widget_Base {

	/** Column width option → span of a 12-column grid. */
	private const WIDTHS = array(
		'100' => '100%',
		'75'  => '75%',
		'66'  => '66%',
		'50'  => '50%',
		'33'  => '33%',
		'25'  => '25%',
	);

	private const SPANS = array(
		'100' => '--uncoder-form-span:12',
		'75'  => '--uncoder-form-span:9',
		'66'  => '--uncoder-form-span:8',
		'50'  => '--uncoder-form-span:6',
		'33'  => '--uncoder-form-span:4',
		'25'  => '--uncoder-form-span:3',
	);

	private const FIELD = '{{WRAPPER}} .uncoder-form__field';

	/** @var array<string,int> Render count per element (unique ids when a form is printed twice). */
	private static array $instances = array();

	public function name(): string {
		return 'form';
	}

	public function title(): string {
		return __( 'Form', 'uncoder' );
	}

	public function icon(): string {
		return 'clipboard-list';
	}

	public function category(): string {
		return 'forms';
	}

	public function keywords(): array {
		return array( 'form', 'contact', 'contact form', 'lead', 'newsletter', 'subscribe', 'input', 'fields', 'email', 'quote', 'booking' );
	}

	public function description(): string {
		return __( 'Contact or lead form with configurable fields (text, email, phone, select, checkboxes, file…). Submissions are validated on the server, saved, emailed to the site owner and optionally sent to a webhook; built-in spam protection.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'form' );
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private static function default_fields(): array {
		return array(
			array(
				'_id'          => 'fname01',
				'type'         => 'text',
				'label'        => __( 'Name', 'uncoder' ),
				'field_id'     => 'name',
				'placeholder'  => __( 'Your name', 'uncoder' ),
				'required'     => true,
				'autocomplete' => 'name',
				'width'        => '50',
			),
			array(
				'_id'         => 'femail1',
				'type'        => 'email',
				'label'       => __( 'Email', 'uncoder' ),
				'field_id'    => 'email',
				'placeholder' => __( 'you@example.com', 'uncoder' ),
				'required'    => true,
				'width'       => '50',
			),
			array(
				'_id'         => 'fmsg001',
				'type'        => 'textarea',
				'label'       => __( 'Message', 'uncoder' ),
				'field_id'    => 'message',
				'placeholder' => __( 'How can we help?', 'uncoder' ),
				'required'    => true,
				'rows'        => 5,
			),
		);
	}

	public function preset(): array {
		return array( 'fields' => self::default_fields() );
	}

	protected function register_controls(): void {
		$text_like = array( 'text', 'email', 'tel', 'url', 'number', 'textarea', 'select', 'date' );

		/* ---------------------------------------------------------------- Fields */
		$this->start_section( 'content', array( 'label' => __( 'Form fields', 'uncoder' ) ) );
		$this->add_control(
			'form_name',
			array(
				'type'        => 'text',
				'label'       => __( 'Form name', 'uncoder' ),
				'default'     => __( 'Contact form', 'uncoder' ),
				'description' => __( 'Shown in submissions and notification emails.', 'uncoder' ),
			)
		);
		$this->add_control(
			'fields',
			array(
				'type'        => 'repeater',
				'label'       => __( 'Fields', 'uncoder' ),
				'title_field' => 'label',
				'fields'      => array(
					'type'            => array(
						'type'    => 'select',
						'label'   => __( 'Type', 'uncoder' ),
						'default' => 'text',
						'options' => array(
							'text'       => __( 'Text', 'uncoder' ),
							'email'      => __( 'Email', 'uncoder' ),
							'tel'        => __( 'Phone', 'uncoder' ),
							'url'        => __( 'URL', 'uncoder' ),
							'number'     => __( 'Number', 'uncoder' ),
							'textarea'   => __( 'Textarea', 'uncoder' ),
							'select'     => __( 'Select', 'uncoder' ),
							'radio'      => __( 'Radio buttons', 'uncoder' ),
							'checkbox'   => __( 'Checkboxes', 'uncoder' ),
							'acceptance' => __( 'Acceptance (consent)', 'uncoder' ),
							'date'       => __( 'Date', 'uncoder' ),
							'hidden'     => __( 'Hidden', 'uncoder' ),
							'file'       => __( 'File upload', 'uncoder' ),
							'step'       => __( '— Step break —', 'uncoder' ),
						),
					),
					'label'           => array(
						'type'    => 'text',
						'label'   => __( 'Label', 'uncoder' ),
						'default' => __( 'Field', 'uncoder' ),
					),
					'field_id'        => array(
						'type'        => 'text',
						'label'       => __( 'Field ID', 'uncoder' ),
						'placeholder' => __( 'Automatic', 'uncoder' ),
						'description' => __( 'Letters, numbers and underscores. Used as [field_id] in emails and as the key in webhooks. Empty: made from the label.', 'uncoder' ),
					),
					'placeholder'     => array(
						'type'      => 'text',
						'label'     => __( 'Placeholder', 'uncoder' ),
						'condition' => array( 'type' => $text_like ),
					),
					'required'        => array(
						'type'      => 'switch',
						'label'     => __( 'Required', 'uncoder' ),
						'default'   => false,
						'condition' => array( 'type!' => array( 'hidden', 'step' ) ),
					),
					'options'         => array(
						'type'        => 'textarea',
						'label'       => __( 'Options', 'uncoder' ),
						'description' => __( 'One per line. Use "Label|value" to store a different value.', 'uncoder' ),
						'condition'   => array( 'type' => array( 'select', 'radio', 'checkbox' ) ),
					),
					'inline_options'  => array(
						'type'      => 'switch',
						'label'     => __( 'Options side by side', 'uncoder' ),
						'condition' => array( 'type' => array( 'radio', 'checkbox' ) ),
					),
					'acceptance_text' => array(
						'type'        => 'text',
						'label'       => __( 'Consent text', 'uncoder' ),
						'html'        => 'inline',
						'placeholder' => __( 'I agree to the privacy policy.', 'uncoder' ),
						'description' => __( 'Shown next to the checkbox; links allowed. Empty: the label.', 'uncoder' ),
						'condition'   => array( 'type' => 'acceptance' ),
					),
					'default_value'   => array(
						'type'        => 'text',
						'label'       => __( 'Default value', 'uncoder' ),
						'description' => __( 'For checkboxes, separate several values with commas.', 'uncoder' ),
						'condition'   => array( 'type!' => array( 'file', 'acceptance', 'step' ) ),
					),
					'min'             => array(
						'type'      => 'number',
						'label'     => __( 'Minimum', 'uncoder' ),
						'condition' => array( 'type' => 'number' ),
					),
					'max'             => array(
						'type'      => 'number',
						'label'     => __( 'Maximum', 'uncoder' ),
						'condition' => array( 'type' => 'number' ),
					),
					'rows'            => array(
						'type'      => 'number',
						'label'     => __( 'Rows', 'uncoder' ),
						'default'   => 4,
						'min'       => 2,
						'max'       => 30,
						'condition' => array( 'type' => 'textarea' ),
					),
					'file_types'      => array(
						'type'        => 'text',
						'label'       => __( 'Allowed file types', 'uncoder' ),
						'default'     => Fields::DEFAULT_FILE_TYPES,
						'description' => __( 'Comma-separated extensions. Only types WordPress allows are accepted; scripts and SVG never are.', 'uncoder' ),
						'condition'   => array( 'type' => 'file' ),
					),
					'file_size'       => array(
						'type'      => 'number',
						'label'     => __( 'Max file size (MB)', 'uncoder' ),
						'default'   => 2,
						'min'       => 1,
						'max'       => Fields::MAX_FILE_MB,
						'condition' => array( 'type' => 'file' ),
					),
					'autocomplete'    => array(
						'type'        => 'select',
						'label'       => __( 'Autofill', 'uncoder' ),
						'options'     => Fields::AUTOCOMPLETE,
						'description' => __( 'Tells browsers what the field is for, so visitors can autofill it.', 'uncoder' ),
						'condition'   => array( 'type' => array( 'text', 'email', 'tel', 'url' ) ),
					),
					'help'            => array(
						'type'      => 'text',
						'label'     => __( 'Help text', 'uncoder' ),
						'condition' => array( 'type!' => array( 'hidden', 'step' ) ),
					),
					'show_if_field'   => array(
						'type'        => 'text',
						'label'       => __( 'Show only if field', 'uncoder' ),
						'placeholder' => __( 'Field ID', 'uncoder' ),
						'description' => __( 'Conditional field: hidden (and not required) unless this other field matches.', 'uncoder' ),
						'condition'   => array( 'type!' => array( 'hidden', 'step' ) ),
					),
					'show_if_op'      => array(
						'type'      => 'select',
						'label'     => __( 'Condition', 'uncoder' ),
						'default'   => 'is',
						'options'   => array(
							'is'       => __( 'is', 'uncoder' ),
							'is_not'   => __( 'is not', 'uncoder' ),
							'contains' => __( 'contains', 'uncoder' ),
							'filled'   => __( 'is filled in', 'uncoder' ),
							'empty'    => __( 'is empty', 'uncoder' ),
						),
						'condition' => array( 'show_if_field!' => '' ),
					),
					'show_if_value'   => array(
						'type'      => 'text',
						'label'     => __( 'Value', 'uncoder' ),
						'condition' => array(
							'show_if_field!' => '',
							'show_if_op'     => array( 'is', 'is_not', 'contains' ),
						),
					),
					'width'           => array(
						'type'                 => 'select',
						'label'                => __( 'Column width', 'uncoder' ),
						'responsive'           => true,
						'default'              => '100',
						'options'              => self::WIDTHS,
						'selectors_dictionary' => self::SPANS,
						'selectors'            => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => '{{VALUE}}' ),
						'condition'            => array( 'type!' => array( 'hidden', 'step' ) ),
					),
				),
				'default'     => self::default_fields(),
				'ai'          => 'Rows: {"type":"text|email|tel|url|number|textarea|select|radio|checkbox|acceptance|date|hidden|file|step","label":"…","field_id":"snake_case","required":true,"placeholder":"…","options":"One\nTwo|two","width":"100|75|66|50|33|25"}. Fields stack full width on phones unless width_mobile is set. File fields need allow_uploads. Multi-step: a {"type":"step","label":"Step title"} row starts the next step. Conditional field: "show_if_field":"<field_id>","show_if_op":"is|is_not|contains|filled|empty","show_if_value":"…".',
			)
		);
		$this->add_control(
			'show_labels',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show labels', 'uncoder' ),
				'default' => true,
				'description' => __( 'Hidden labels stay available to screen readers.', 'uncoder' ),
			)
		);
		$this->add_control(
			'required_mark',
			array(
				'type'    => 'switch',
				'label'   => __( 'Required mark (*)', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control( 'steps_heading', array( 'type' => 'heading', 'label' => __( 'Multi-step (with Step break fields)', 'uncoder' ) ) );
		$this->add_control(
			'step_progress',
			array(
				'type'    => 'select',
				'label'   => __( 'Progress', 'uncoder' ),
				'default' => 'bar',
				'options' => array(
					'bar'   => __( 'Bar with step name', 'uncoder' ),
					'steps' => __( 'Numbered steps', 'uncoder' ),
					'none'  => __( 'None', 'uncoder' ),
				),
			)
		);
		$this->add_control( 'next_text', array( 'type' => 'text', 'label' => __( 'Next button', 'uncoder' ), 'placeholder' => __( 'Next', 'uncoder' ) ) );
		$this->add_control( 'prev_text', array( 'type' => 'text', 'label' => __( 'Back button', 'uncoder' ), 'placeholder' => __( 'Back', 'uncoder' ) ) );
		$this->end_section();

		/* ---------------------------------------------------------------- Submit button */
		$this->start_section( 'content_button', array( 'label' => __( 'Submit button', 'uncoder' ) ) );
		$this->add_control(
			'button_text',
			array(
				'type'    => 'text',
				'label'   => __( 'Text', 'uncoder' ),
				'default' => __( 'Send message', 'uncoder' ),
				'inline'  => true,
			)
		);
		$this->add_control( 'button_icon', array( 'type' => 'icon', 'label' => __( 'Icon', 'uncoder' ) ) );
		$this->add_control(
			'button_icon_position',
			array(
				'type'      => 'choose',
				'label'     => __( 'Icon position', 'uncoder' ),
				'default'   => 'after',
				'options'   => array(
					'before' => array( 'label' => __( 'Before', 'uncoder' ), 'icon' => 'arrow-left-to-line' ),
					'after'  => array( 'label' => __( 'After', 'uncoder' ), 'icon' => 'arrow-right-to-line' ),
				),
				'condition' => array( 'button_icon.library!' => 'none' ),
			)
		);
		$this->add_control(
			'button_variant',
			array(
				'type'    => 'select',
				'label'   => __( 'Style', 'uncoder' ),
				'default' => 'primary',
				'options' => array(
					'primary'   => __( 'Primary', 'uncoder' ),
					'secondary' => __( 'Secondary', 'uncoder' ),
					'outline'   => __( 'Outline', 'uncoder' ),
					'ghost'     => __( 'Ghost', 'uncoder' ),
				),
			)
		);
		$this->add_control(
			'button_size',
			array(
				'type'    => 'choose',
				'label'   => __( 'Size', 'uncoder' ),
				'default' => 'md',
				'options' => array(
					'sm' => array( 'label' => 'S' ),
					'md' => array( 'label' => 'M' ),
					'lg' => array( 'label' => 'L' ),
					'xl' => array( 'label' => 'XL' ),
				),
			)
		);
		$this->add_responsive_control(
			'button_width',
			array(
				'type'                 => 'select',
				'label'                => __( 'Column width', 'uncoder' ),
				'options'              => self::WIDTHS,
				'selectors_dictionary' => self::SPANS,
				'selectors'            => array( '{{WRAPPER}} .uncoder-form__group--submit' => '{{VALUE}}' ),
				'ai'                   => 'Width of the button cell; e.g. an email field at 66 + button at 33 makes an inline newsletter form.',
			)
		);
		$this->add_responsive_control(
			'button_align',
			array(
				'type'                 => 'choose',
				'label'                => __( 'Alignment', 'uncoder' ),
				'options'              => array(
					'left'    => array( 'label' => __( 'Left', 'uncoder' ), 'icon' => 'align-left' ),
					'center'  => array( 'label' => __( 'Center', 'uncoder' ), 'icon' => 'align-center' ),
					'right'   => array( 'label' => __( 'Right', 'uncoder' ), 'icon' => 'align-right' ),
					'stretch' => array( 'label' => __( 'Full width', 'uncoder' ), 'icon' => 'align-justify' ),
				),
				'selectors_dictionary' => array(
					'left'    => 'justify-content:flex-start;--uncoder-btn-width:auto',
					'center'  => 'justify-content:center;--uncoder-btn-width:auto',
					'right'   => 'justify-content:flex-end;--uncoder-btn-width:auto',
					'stretch' => 'justify-content:stretch;--uncoder-btn-width:100%',
				),
				'selectors'            => array( '{{WRAPPER}} .uncoder-form__group--submit' => '{{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Messages */
		$this->start_section( 'content_messages', array( 'label' => __( 'Messages', 'uncoder' ) ) );
		$this->add_control(
			'success_message',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Success message', 'uncoder' ),
				'default' => __( 'Thanks! Your message has been sent. We will get back to you soon.', 'uncoder' ),
			)
		);
		$this->add_control(
			'error_message',
			array(
				'type'    => 'textarea',
				'label'   => __( 'Error message', 'uncoder' ),
				'default' => __( 'Something went wrong. Please try again.', 'uncoder' ),
			)
		);
		$this->add_control(
			'required_message',
			array(
				'type'        => 'text',
				'label'       => __( 'Required field message', 'uncoder' ),
				'placeholder' => __( 'This field is required.', 'uncoder' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Actions */
		$this->start_section( 'content_actions', array( 'label' => __( 'Actions after submit', 'uncoder' ) ) );
		$this->add_control(
			'actions_notice',
			array(
				'type'  => 'notice',
				'label' => __( 'These settings stay on the server: they are never printed on the page.', 'uncoder' ),
			)
		);
		$this->add_control(
			'store',
			array(
				'type'        => 'switch',
				'label'       => __( 'Save submissions', 'uncoder' ),
				'default'     => true,
				'description' => __( 'Listed under Uncoder → Submissions.', 'uncoder' ),
			)
		);
		$this->add_control(
			'email',
			array(
				'type'    => 'switch',
				'label'   => __( 'Email notification', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'email_to',
			array(
				'type'        => 'text',
				'label'       => __( 'Send to', 'uncoder' ),
				'placeholder' => __( 'Site admin email', 'uncoder' ),
				'description' => __( 'Separate several addresses with commas.', 'uncoder' ),
				'condition'   => array( 'email' => true ),
			)
		);
		$this->add_control(
			'email_subject',
			array(
				'type'        => 'text',
				'label'       => __( 'Subject', 'uncoder' ),
				'default'     => __( 'New submission: [form_name]', 'uncoder' ),
				'description' => __( 'Placeholders: [form_name], [site_name], [field_id].', 'uncoder' ),
				'condition'   => array( 'email' => true ),
			)
		);
		$this->add_control(
			'email_reply_to',
			array(
				'type'        => 'text',
				'label'       => __( 'Reply-to field', 'uncoder' ),
				'placeholder' => 'email',
				'description' => __( 'ID of the field holding the visitor\'s email. Empty: the first email field.', 'uncoder' ),
				'condition'   => array( 'email' => true ),
			)
		);
		$this->add_control(
			'email_from_name',
			array(
				'type'        => 'text',
				'label'       => __( 'From name', 'uncoder' ),
				'placeholder' => __( 'Site title', 'uncoder' ),
				'condition'   => array( 'email' => true ),
			)
		);
		$this->add_control(
			'email_content',
			array(
				'type'      => 'select',
				'label'     => __( 'Email content', 'uncoder' ),
				'default'   => 'all',
				'options'   => array(
					'all'      => __( 'All fields', 'uncoder' ),
					'template' => __( 'Custom template', 'uncoder' ),
				),
				'condition' => array( 'email' => true ),
			)
		);
		$this->add_control(
			'email_template',
			array(
				'type'        => 'textarea',
				'label'       => __( 'Template', 'uncoder' ),
				'default'     => '[all-fields]',
				'description' => __( 'Placeholders: [field_id], [all-fields], [form_name], [page_url], [site_name].', 'uncoder' ),
				'condition'   => array(
					'email'         => true,
					'email_content' => 'template',
				),
			)
		);
		$this->add_control(
			'email_format',
			array(
				'type'      => 'select',
				'label'     => __( 'Format', 'uncoder' ),
				'default'   => 'html',
				'options'   => array(
					'html'  => 'HTML',
					'plain' => __( 'Plain text', 'uncoder' ),
				),
				'condition' => array( 'email' => true ),
			)
		);
		$this->add_control(
			'redirect_url',
			array(
				'type'        => 'text',
				'label'       => __( 'Redirect after success', 'uncoder' ),
				'placeholder' => 'https://',
				'description' => __( 'Optional page to open after a successful submission.', 'uncoder' ),
			)
		);
		$this->add_control(
			'webhook_url',
			array(
				'type'        => 'text',
				'label'       => __( 'Webhook URL', 'uncoder' ),
				'placeholder' => 'https://',
				'description' => __( 'Optional. Each submission is POSTed here as JSON (https only).', 'uncoder' ),
				'ai'          => 'Zapier/Make/n8n style catch hook. Must be a public https:// URL.',
			)
		);
		$this->add_control(
			'slack_webhook',
			array(
				'type'        => 'text',
				'label'       => __( 'Slack webhook', 'uncoder' ),
				'placeholder' => 'https://hooks.slack.com/services/…',
				'description' => __( 'Optional. Posts each submission to a Slack channel (incoming webhook URL).', 'uncoder' ),
			)
		);
		$this->add_control(
			'discord_webhook',
			array(
				'type'        => 'text',
				'label'       => __( 'Discord webhook', 'uncoder' ),
				'placeholder' => 'https://discord.com/api/webhooks/…',
				'description' => __( 'Optional. Posts each submission to a Discord channel.', 'uncoder' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Auto-reply */
		$this->start_section( 'content_autoreply', array( 'label' => __( 'Auto-reply email', 'uncoder' ) ) );
		$this->add_control(
			'autoreply',
			array(
				'type'        => 'switch',
				'label'       => __( 'Email the visitor a confirmation', 'uncoder' ),
				'description' => __( 'Sent to the address the visitor typed.', 'uncoder' ),
			)
		);
		$this->add_control(
			'autoreply_to',
			array(
				'type'        => 'text',
				'label'       => __( 'Email field', 'uncoder' ),
				'placeholder' => 'email',
				'description' => __( 'ID of the field holding the visitor\'s email. Empty: the first email field.', 'uncoder' ),
				'condition'   => array( 'autoreply' => true ),
			)
		);
		$this->add_control(
			'autoreply_subject',
			array(
				'type'      => 'text',
				'label'     => __( 'Subject', 'uncoder' ),
				'default'   => __( 'Thanks for getting in touch with [site_name]', 'uncoder' ),
				'condition' => array( 'autoreply' => true ),
			)
		);
		$this->add_control(
			'autoreply_message',
			array(
				'type'        => 'textarea',
				'label'       => __( 'Message', 'uncoder' ),
				'default'     => __( "Hi [name],\n\nThanks for your message. We have received it and will get back to you soon.\n\n[site_name]", 'uncoder' ),
				'description' => __( 'Placeholders: [field_id], [all-fields], [form_name], [site_name].', 'uncoder' ),
				'condition'   => array( 'autoreply' => true ),
			)
		);
		$this->add_control(
			'autoreply_from_name',
			array(
				'type'        => 'text',
				'label'       => __( 'From name', 'uncoder' ),
				'placeholder' => __( 'Site title', 'uncoder' ),
				'condition'   => array( 'autoreply' => true ),
			)
		);
		$this->add_control(
			'autoreply_reply_to',
			array(
				'type'        => 'text',
				'label'       => __( 'Reply-to address', 'uncoder' ),
				'placeholder' => 'hello@example.com',
				'condition'   => array( 'autoreply' => true ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Newsletter */
		$this->start_section( 'content_newsletter', array( 'label' => __( 'Newsletter', 'uncoder' ) ) );
		$this->add_control(
			'newsletter',
			array(
				'type'        => 'switch',
				'label'       => __( 'Add to a mailing list', 'uncoder' ),
				'description' => __( 'Connect Mailchimp, MailerLite, Brevo or ActiveCampaign in Uncoder → Settings → Forms.', 'uncoder' ),
			)
		);
		$this->add_control(
			'newsletter_service',
			array(
				'type'      => 'select',
				'label'     => __( 'Service', 'uncoder' ),
				'default'   => 'mailchimp',
				'options'   => \Uncoder\Builder\Forms\Integrations::SERVICES,
				'condition' => array( 'newsletter' => true ),
			)
		);
		$this->add_control(
			'newsletter_list',
			array(
				'type'        => 'text',
				'label'       => __( 'List / group / audience ID', 'uncoder' ),
				'description' => __( 'Mailchimp: audience ID. MailerLite: group ID. Brevo and ActiveCampaign: list number.', 'uncoder' ),
				'condition'   => array( 'newsletter' => true ),
			)
		);
		$this->add_control(
			'newsletter_email',
			array(
				'type'        => 'text',
				'label'       => __( 'Email field', 'uncoder' ),
				'placeholder' => 'email',
				'description' => __( 'Empty: the first email field.', 'uncoder' ),
				'condition'   => array( 'newsletter' => true ),
			)
		);
		$this->add_control(
			'newsletter_name',
			array(
				'type'        => 'text',
				'label'       => __( 'Name field', 'uncoder' ),
				'placeholder' => 'name',
				'condition'   => array( 'newsletter' => true ),
			)
		);
		$this->add_control(
			'newsletter_consent',
			array(
				'type'        => 'text',
				'label'       => __( 'Consent checkbox field', 'uncoder' ),
				'description' => __( 'Optional. When set, only visitors who tick this field are added.', 'uncoder' ),
				'condition'   => array( 'newsletter' => true ),
			)
		);
		$this->add_control(
			'newsletter_double',
			array(
				'type'        => 'switch',
				'label'       => __( 'Double opt-in (Mailchimp)', 'uncoder' ),
				'description' => __( 'New subscribers confirm by email first.', 'uncoder' ),
				'condition'   => array(
					'newsletter'         => true,
					'newsletter_service' => 'mailchimp',
				),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Security */
		$this->start_section( 'content_security', array( 'label' => __( 'Spam protection', 'uncoder' ) ) );
		$this->add_control(
			'security_notice',
			array(
				'type'  => 'notice',
				'label' => __( 'A hidden honeypot field, a signed timestamp and a per-visitor rate limit are always on.', 'uncoder' ),
			)
		);
		$this->add_control(
			'min_time',
			array(
				'type'        => 'number',
				'label'       => __( 'Minimum fill time (seconds)', 'uncoder' ),
				'default'     => 3,
				'min'         => 0,
				'max'         => 60,
				'description' => __( 'Faster submissions are treated as spam. 0 turns the check off.', 'uncoder' ),
			)
		);
		$this->add_control(
			'block_links',
			array(
				'type'        => 'switch',
				'label'       => __( 'Block links in messages', 'uncoder' ),
				'description' => __( 'Rejects text and textarea answers that contain links.', 'uncoder' ),
			)
		);
		$this->add_control(
			'captcha',
			array(
				'type'        => 'switch',
				'label'       => __( 'CAPTCHA', 'uncoder' ),
				'description' => \Uncoder\Builder\Site\Captcha::ready()
					? __( 'Adds the CAPTCHA set up in Uncoder → Settings → Forms.', 'uncoder' )
					: __( 'Set up Turnstile, hCaptcha or reCAPTCHA v3 keys in Uncoder → Settings → Forms first.', 'uncoder' ),
			)
		);
		$this->add_control(
			'allow_uploads',
			array(
				'type'        => 'switch',
				'label'       => __( 'Allow file uploads', 'uncoder' ),
				'description' => __( 'File upload fields only work when this is on. Files are stored privately and linked in the notification email.', 'uncoder' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: layout */
		$this->start_section( 'style_form', array( 'label' => __( 'Form', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'column_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Column gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-form-col-gap: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'type'       => 'slider',
				'label'      => __( 'Row gap', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-form-row-gap: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: labels */
		$this->start_section( 'style_labels', array( 'label' => __( 'Labels', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'label_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-form__label' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'label_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__label' ) );
		$this->add_control(
			'label_spacing',
			array(
				'type'       => 'slider',
				'label'      => __( 'Space below', 'uncoder' ),
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-form-label-gap: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'mark_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Required mark color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-form__req' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'option_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Option text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-form__option-label' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'option_typography', array( 'type' => 'typography', 'label' => __( 'Option typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__option-label' ) );
		$this->add_control(
			'help_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Help text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-form__help' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'help_typography', array( 'type' => 'typography', 'label' => __( 'Help text typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__help' ) );
		$this->end_section();

		/* ---------------------------------------------------------------- Style: fields */
		$this->start_section( 'style_fields', array( 'label' => __( 'Fields', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'field_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => self::FIELD ) );
		$this->start_tabs( 'field_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'field_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				// The select's drawn chevron follows the text (it stayed grey, unseen on a dark form).
				'selectors' => array(
					self::FIELD                                => 'color: {{VALUE}}',
					'{{WRAPPER}} .uncoder-form__select::after' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_control(
			'placeholder_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Placeholder color', 'uncoder' ),
				'selectors' => array(
					self::FIELD . '::placeholder' => 'color: {{VALUE}}; opacity: 1',
					// A select showing its placeholder option.
					'{{WRAPPER}} .uncoder-form__select select.uncoder-form__field:has(> option[value=""]:checked)' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_control(
			'field_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::FIELD => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'field_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => self::FIELD ) );
		$this->add_group( 'field_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => self::FIELD ) );
		$this->end_tab();
		$this->start_tab( 'focus', __( 'Focus', 'uncoder' ) );
		$this->add_control(
			'focus_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( self::FIELD . ':focus' => 'background-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'focus_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Focus color', 'uncoder' ),
				'selectors' => array(
					self::FIELD . ':focus' => 'border-color: {{VALUE}}; outline-color: {{VALUE}}',
					'{{WRAPPER}}' => '--uncoder-form-accent: {{VALUE}}',
				),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'field_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( self::FIELD => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'field_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( self::FIELD => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'choice_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Checkbox & radio color', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-form-accent: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: button */
		$this->start_section( 'style_button', array( 'label' => __( 'Button', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'button_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__submit' ) );
		$this->start_tabs( 'button_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control(
			'button_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-form__submit' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'button_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__submit' ) );
		$this->add_group( 'button_border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__submit' ) );
		$this->add_group( 'button_shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__submit' ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control(
			'button_hover_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-form__submit:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ),
			)
		);
		$this->add_group( 'button_hover_background', array( 'type' => 'background', 'label' => __( 'Background', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__submit:is(:hover, :focus-visible)' ) );
		$this->add_control(
			'button_hover_border_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}} .uncoder-form__submit:is(:hover, :focus-visible)' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->end_tab();
		$this->end_tabs();
		$this->add_responsive_control(
			'button_radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-form__submit' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'button_padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-form__submit' => 'padding: {{VALUE}}' ),
			)
		);
		$this->end_section();

		/* ---------------------------------------------------------------- Style: messages */
		$this->start_section( 'style_messages', array( 'label' => __( 'Messages', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_group( 'message_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__message, {{WRAPPER}} .uncoder-form__notice' ) );
		$this->add_control(
			'success_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Success color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-form-success: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'success_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Success background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-form-success-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'error_color',
			array(
				'type'        => 'color',
				'label'       => __( 'Error color', 'uncoder' ),
				'description' => __( 'Also used for field errors and invalid field borders.', 'uncoder' ),
				'selectors'   => array( '{{WRAPPER}}' => '--uncoder-form-error: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'error_bg',
			array(
				'type'      => 'color',
				'label'     => __( 'Error background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-form-error-bg: {{VALUE}}' ),
			)
		);
		$this->add_group( 'field_error_typography', array( 'type' => 'typography', 'label' => __( 'Field error typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-form__error' ) );
		$this->end_section();
	}

	/**
	 * Data for the front-end module (client-side validation mirrors the server). No action settings.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	public function wrapper_attributes( array $s, Render_Context $ctx ): array {
		$min = is_numeric( $s['min_time'] ?? null ) ? max( 0, min( 60, (int) $s['min_time'] ) ) : 3;
		return array(
			'data-settings' => $this->json_attr(
				array(
					'minTime'  => $min,
					'noLinks'  => ! empty( $s['block_links'] ),
					'maxLen'   => Fields::MAX_LENGTH,
					'tokenUrl' => Forms::token_url(),
					'messages' => Fields::messages( $s ),
				)
			),
		);
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$fields = Fields::from_settings( $s );
		if ( ! $fields ) {
			if ( $ctx->editor ) {
				echo '<div class="uncoder-form__empty">' . esc_html__( 'Add fields to this form in the Form fields section.', 'uncoder' ) . '</div>';
			}
			return;
		}

		$el   = $ctx->element_id;
		$n    = self::$instances[ $el ] = ( self::$instances[ $el ] ?? 0 ) + 1;
		$base = 'uncoder-form-' . $el . ( $n > 1 ? '-' . $n : '' );

		$doc_id  = $ctx->doc_id;
		$post_id = $ctx->post_id;
		$name    = trim( (string) ( $s['form_name'] ?? '' ) );
		$labels  = ! array_key_exists( 'show_labels', $s ) || ! empty( $s['show_labels'] );
		$mark    = ! array_key_exists( 'required_mark', $s ) || ! empty( $s['required_mark'] );
		$has_file = false;
		foreach ( $fields as $field ) {
			if ( 'file' === $field['type'] ) {
				$has_file = true;
			}
		}

		$attrs = array(
			'class'          => 'uncoder-form',
			'method'         => 'post',
			'accept-charset' => 'UTF-8',
			'enctype'        => $has_file ? 'multipart/form-data' : null,
			'aria-label'     => '' !== $name ? $name : null,
		);
		if ( ! $ctx->editor ) {
			$back = $post_id > 0 && is_post_publicly_viewable( $post_id ) ? (string) get_permalink( $post_id ) : '';
			$attrs['action'] = '' !== $back ? add_query_arg( '_redirect', rawurlencode( $back ), Forms::submit_url() ) : Forms::submit_url();
		}

		$steps    = Fields::steps( $s, $fields );
		$multi    = count( $steps ) > 1;
		$progress = in_array( $s['step_progress'] ?? 'bar', array( 'bar', 'steps', 'none' ), true ) ? ( $s['step_progress'] ?? 'bar' ) : 'bar';
		if ( $multi ) {
			$attrs['class']     .= ' uncoder-form--steps';
			$attrs['data-steps'] = (string) count( $steps );
		}

		echo '<form' . Utils::attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attrs().
		if ( $multi && 'none' !== $progress ) {
			echo $this->progress_html( $steps, $progress ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		}
		echo '<div class="uncoder-form__fields">';
		if ( $multi ) {
			// Every step is a fieldset; without JavaScript they all show as one long form.
			$n = 0;
			foreach ( $steps as $index => $title ) {
				echo '<fieldset class="uncoder-form__step" data-step="' . (int) $n . '">';
				echo '' !== $title ? '<legend class="uncoder-form__step-title">' . esc_html( $title ) . '</legend>' : '<legend class="uncoder-sr-only">' . esc_html( sprintf( /* translators: %d: step number */ __( 'Step %d', 'uncoder' ), $n + 1 ) ) . '</legend>';
				foreach ( $fields as $field ) {
					if ( (int) $field['step'] === (int) $index ) {
						echo $this->field_html( $field, $base, $labels, $mark ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in field_html().
					}
				}
				echo '</fieldset>';
				++$n;
			}
		} else {
			foreach ( $fields as $field ) {
				echo $this->field_html( $field, $base, $labels, $mark ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in field_html().
			}
		}
		if ( ! empty( $s['captcha'] ) && ! $ctx->editor ) {
			echo \Uncoder\Builder\Site\Captcha::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		}
		if ( $multi ) {
			// Back / Next appear with JavaScript; the submit button always works (all steps show without JS).
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			echo '<div class="uncoder-form__nav">'
				. '<button type="button" class="uncoder-btn uncoder-btn--ghost uncoder-form__prev" hidden>' . esc_html( trim( (string) ( $s['prev_text'] ?? '' ) ) ?: __( 'Back', 'uncoder' ) ) . '</button>'
				. '<button type="button" class="uncoder-btn uncoder-btn--primary uncoder-form__next" hidden>' . esc_html( trim( (string) ( $s['next_text'] ?? '' ) ) ?: __( 'Next', 'uncoder' ) ) . '</button>'
				. $this->submit_html( $s, $ctx ) . '</div>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo $this->submit_html( $s, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in submit_html().
		}
		echo '</div>';

		// Honeypot: invisible to people and assistive tech, tempting to bots.
		echo '<div class="uncoder-form__hp" aria-hidden="true"><label for="' . esc_attr( $base . '-hp' ) . '">' . esc_html__( 'Leave this field empty', 'uncoder' ) . '</label>'
			. '<input type="text" id="' . esc_attr( $base . '-hp' ) . '" name="' . esc_attr( Security::HONEYPOT ) . '" value="" tabindex="-1" autocomplete="off"></div>';

		$token = Security::token( $doc_id, $post_id, $el );
		$meta  = array(
			'doc_id'     => (string) $doc_id,
			'post_id'    => (string) $post_id,
			'element_id' => $el,
			'ts'         => (string) $token['ts'],
			'token'      => $token['token'],
		);
		foreach ( $meta as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}

		$success = trim( (string) ( $s['success_message'] ?? '' ) );
		$success = '' !== $success ? $success : __( 'Thanks! Your message has been sent.', 'uncoder' );
		// No-JS confirmation: the submit endpoint redirects back to #uncoder-form-{id}-sent.
		echo '<div class="uncoder-form__notice" id="' . esc_attr( 'uncoder-form-' . $el . '-sent' ) . '" tabindex="-1">' . esc_html( $success ) . '</div>';
		echo '<div class="uncoder-form__message uncoder-form__message--success" role="status" aria-live="polite"></div>';
		echo '<div class="uncoder-form__message uncoder-form__message--error" role="alert"></div>';
		echo '</form>';
	}

	/**
	 * Markup of one field.
	 *
	 * @param array<string,mixed> $f Normalized field.
	 */
	private function field_html( array $f, string $base, bool $labels, bool $mark ): string {
		$type = (string) $f['type'];
		$id   = $base . '-' . $f['id'];
		$name = 'fields[' . $f['id'] . ']';

		if ( 'hidden' === $type ) {
			return '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $f['default'] ) . '">';
		}

		$req      = (bool) $f['required'];
		$label    = (string) $f['label'];
		$help     = (string) $f['help'];
		$max_size = (int) round( (float) $f['file_size'] * MB_IN_BYTES );
		if ( 'file' === $type ) {
			$hint = implode( ', ', array_map( 'strtoupper', $f['file_types'] ) ) . ' · ' . sprintf(
				/* translators: %s: maximum file size, e.g. "2 MB" */
				__( 'max. %s', 'uncoder' ),
				Fields::size_label( (float) $f['file_size'] )
			);
			$help = '' !== $help ? $help . ' (' . $hint . ')' : $hint;
		}
		$help_id  = $id . '-help';
		$error_id = $id . '-error';
		$describe = trim( ( '' !== $help ? $help_id . ' ' : '' ) . $error_id );

		$group_classes = array( 'uncoder-form__group', 'uncoder-form__group--' . $type );
		if ( '' !== $f['row'] ) {
			$group_classes[] = 'uncoder-ri-' . $f['row'];
		}
		if ( $f['mobile_width'] ) {
			$group_classes[] = 'uncoder-form__group--mw';
		}
		if ( $req ) {
			$group_classes[] = 'is-required';
		}
		$group_attrs = array(
			'class'         => $group_classes,
			'data-field'    => $f['id'],
			'data-type'     => $type,
			'data-required' => $req ? '1' : null,
			'data-show-if'  => ! empty( $f['show_if'] ) ? (string) wp_json_encode( $f['show_if'] ) : null,
		);
		$mark_html  = $req && $mark ? '<span class="uncoder-form__req" aria-hidden="true">*</span>' : '';
		$label_cls  = $labels ? 'uncoder-form__label' : 'uncoder-form__label uncoder-sr-only';
		$help_html  = '' !== $help ? '<p class="uncoder-form__help" id="' . esc_attr( $help_id ) . '">' . esc_html( $help ) . '</p>' : '';
		$error_html = '<p class="uncoder-form__error" id="' . esc_attr( $error_id ) . '" aria-live="polite"></p>';

		/* Radio buttons and checkbox groups: fieldset + legend. */
		if ( ( 'radio' === $type || 'checkbox' === $type ) && $f['options'] ) {
			$defaults = array_map( 'trim', explode( ',', (string) $f['default'] ) );
			$options  = '';
			foreach ( $f['options'] as $i => $opt ) {
				$oid      = $id . '-' . $i;
				$options .= '<div class="uncoder-form__option">'
					. '<input' . Utils::attrs(
						array(
							'type'             => $type,
							'class'            => 'uncoder-form__choice',
							'id'               => $oid,
							'name'             => 'checkbox' === $type ? $name . '[]' : $name,
							'value'            => $opt['value'],
							'checked'          => in_array( $opt['value'], $defaults, true ),
							'required'         => 'radio' === $type && $req,
							'aria-describedby' => $describe,
						)
					) . '>'
					. '<label class="uncoder-form__option-label" for="' . esc_attr( $oid ) . '">' . esc_html( $opt['label'] ) . '</label></div>';
			}
			$legend = ( '' !== $label ? esc_html( $label ) : esc_html( $f['id'] ) ) . $mark_html
				. ( 'checkbox' === $type && $req ? '<span class="uncoder-sr-only"> ' . esc_html__( '(required)', 'uncoder' ) . '</span>' : '' );
			return '<fieldset' . Utils::attrs( $group_attrs ) . '>'
				. '<legend class="' . esc_attr( $label_cls ) . '">' . $legend . '</legend>'
				. '<div class="' . esc_attr( 'uncoder-form__options' . ( $f['inline'] ? ' uncoder-form__options--inline' : '' ) ) . '">' . $options . '</div>'
				. $help_html . $error_html . '</fieldset>';
		}

		/* Acceptance, or a checkbox without options: one checkbox with its text as the label. */
		if ( 'acceptance' === $type || 'checkbox' === $type ) {
			$text = 'acceptance' === $type ? trim( (string) $f['acceptance'] ) : '';
			$text = '' !== $text ? wp_kses( $text, Utils::kses_inline() ) : esc_html( '' !== $label ? $label : $f['id'] );
			$box  = '<input' . Utils::attrs(
				array(
					'type'             => 'checkbox',
					'class'            => 'uncoder-form__choice',
					'id'               => $id,
					'name'             => $name,
					'value'            => 'yes',
					'checked'          => in_array( strtolower( (string) $f['default'] ), array( 'yes', 'on', '1', 'true', 'checked' ), true ),
					'required'         => $req,
					'aria-required'    => $req ? 'true' : null,
					'aria-describedby' => $describe,
				)
			) . '>';
			$group_attrs['class'][] = 'uncoder-form__group--single';
			return '<div' . Utils::attrs( $group_attrs ) . '><div class="uncoder-form__option">' . $box
				. '<label class="uncoder-form__option-label" for="' . esc_attr( $id ) . '">' . $text . $mark_html . '</label></div>'
				. $help_html . $error_html . '</div>';
		}

		/* Single controls with a <label>. */
		$common = array(
			'id'               => $id,
			'name'             => $name,
			'class'            => 'uncoder-form__field uncoder-form__field--' . $type,
			'required'         => $req,
			'aria-required'    => $req ? 'true' : null,
			'aria-describedby' => $describe,
			'aria-label'       => '' === $label ? ( '' !== $f['placeholder'] ? $f['placeholder'] : $f['id'] ) : null,
		);

		if ( 'textarea' === $type ) {
			$control = '<textarea' . Utils::attrs(
				array_merge(
					$common,
					array(
						'rows'        => (string) $f['rows'],
						'placeholder' => '' !== $f['placeholder'] ? $f['placeholder'] : null,
						'maxlength'   => (string) Fields::MAX_LENGTH,
					)
				)
			) . '>' . esc_textarea( (string) $f['default'] ) . '</textarea>';
		} elseif ( 'select' === $type ) {
			$first   = '' !== $f['placeholder'] ? $f['placeholder'] : __( 'Select an option', 'uncoder' );
			$options = '<option value="">' . esc_html( $first ) . '</option>';
			foreach ( $f['options'] as $opt ) {
				$options .= '<option' . Utils::attrs(
					array(
						'value'    => $opt['value'],
						'selected' => $opt['value'] === $f['default'],
					)
				) . '>' . esc_html( $opt['label'] ) . '</option>';
			}
			$control = '<div class="uncoder-form__select"><select' . Utils::attrs( $common ) . '>' . $options . '</select></div>';
		} elseif ( 'file' === $type ) {
			$control = '<input' . Utils::attrs(
				array_merge(
					$common,
					array(
						'type'          => 'file',
						'accept'        => $f['file_types'] ? '.' . implode( ',.', $f['file_types'] ) : null,
						'data-max-size' => (string) $max_size,
					)
				)
			) . '>';
		} else {
			$auto = (string) $f['autocomplete'];
			if ( '' === $auto && in_array( $type, array( 'email', 'tel', 'url' ), true ) ) {
				$auto = $type;
			}
			$extra = array(
				'type'         => $type,
				'value'        => '' !== $f['default'] ? $f['default'] : null,
				'placeholder'  => '' !== $f['placeholder'] ? $f['placeholder'] : null,
				'autocomplete' => '' !== $auto ? $auto : null,
				'maxlength'    => in_array( $type, array( 'number', 'date' ), true ) ? null : (string) Fields::MAX_LENGTH,
			);
			if ( 'number' === $type ) {
				$extra['min']  = null !== $f['min'] ? (string) $f['min'] : null;
				$extra['max']  = null !== $f['max'] ? (string) $f['max'] : null;
				$extra['step'] = 'any';
			}
			if ( 'tel' === $type ) {
				$extra['inputmode'] = 'tel';
			}
			$control = '<input' . Utils::attrs( array_merge( $common, $extra ) ) . '>';
		}

		$label_html = '' !== $label ? '<label class="' . esc_attr( $label_cls ) . '" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $mark_html . '</label>' : '';
		return '<div' . Utils::attrs( $group_attrs ) . '>' . $label_html . $control . $help_html . $error_html . '</div>';
	}

	/**
	 * Submit button cell.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	/**
	 * Progress of a multi-step form (updated by the form module).
	 *
	 * @param array<int,string> $steps Step titles.
	 */
	private function progress_html( array $steps, string $style ): string {
		$titles = array_values( $steps );
		$total  = count( $titles );
		if ( 'steps' === $style ) {
			$html = '<ol class="uncoder-form__progress uncoder-form__progress--steps">';
			foreach ( $titles as $i => $title ) {
				$html .= '<li class="uncoder-form__progress-step' . ( 0 === $i ? ' is-current' : '' ) . '"' . ( 0 === $i ? ' aria-current="step"' : '' ) . '><span class="uncoder-form__progress-num">' . ( $i + 1 ) . '</span>' . ( '' !== $title ? '<span class="uncoder-form__progress-name">' . esc_html( $title ) . '</span>' : '' ) . '</li>';
			}
			return $html . '</ol>';
		}
		/* translators: 1: current step, 2: number of steps. */
		$label = sprintf( __( 'Step %1$d of %2$d', 'uncoder' ), 1, $total );
		/* translators: 1: current step, 2: number of steps (filled in by the browser). */
		return '<div class="uncoder-form__progress uncoder-form__progress--bar" data-label="' . esc_attr__( 'Step %1$d of %2$d', 'uncoder' ) . '">'
			. '<div class="uncoder-form__progress-text"><span class="uncoder-form__progress-count">' . esc_html( $label ) . '</span><span class="uncoder-form__progress-name">' . esc_html( $titles[0] ) . '</span></div>'
			. '<div class="uncoder-form__progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="' . (int) $total . '" aria-valuenow="1"><span style="width:' . esc_attr( (string) round( 100 / $total, 2 ) ) . '%"></span></div></div>';
	}

	private function submit_html( array $s, Render_Context $ctx ): string {
		$variant = in_array( $s['button_variant'] ?? 'primary', array( 'primary', 'secondary', 'outline', 'ghost' ), true ) ? $s['button_variant'] : 'primary';
		$size    = in_array( $s['button_size'] ?? 'md', array( 'sm', 'md', 'lg', 'xl' ), true ) ? $s['button_size'] : 'md';
		$text    = trim( (string) ( $s['button_text'] ?? '' ) );
		$text    = '' !== $text ? $text : __( 'Send', 'uncoder' );
		$icon    = $this->has_icon( $s['button_icon'] ?? null ) ? $this->render_icon( $s['button_icon'], array( 'class' => 'uncoder-btn__icon' ) ) : '';
		$label   = '<span class="uncoder-btn__text"' . $ctx->inline( 'button_text' ) . '>' . esc_html( $text ) . '</span>';
		$inner   = 'before' === ( $s['button_icon_position'] ?? 'after' ) ? $icon . $label : $label . $icon;
		$classes = array( 'uncoder-form__group', 'uncoder-form__group--submit' );
		if ( isset( $s['button_width_mobile'] ) && '' !== $s['button_width_mobile'] ) {
			$classes[] = 'uncoder-form__group--mw';
		}
		return '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">'
			. '<button type="submit" class="' . esc_attr( 'uncoder-btn uncoder-btn--' . $variant . ' uncoder-btn--' . $size . ' uncoder-form__submit' ) . '">' . $inner . '</button></div>';
	}
}
