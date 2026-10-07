<?php
/**
 * Code Highlight widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets;

use Uncoder\Builder\Core\Render_Context;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Core\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Code block without a third-party highlighter: escaped code in <pre><code>, a dark or light theme,
 * line numbers, highlighted lines and a copy button (module "code-copy").
 */
class Code_Highlight extends Widget_Base {

	public function name(): string {
		return 'code-highlight';
	}

	public function title(): string {
		return __( 'Code Highlight', 'uncoder' );
	}

	public function icon(): string {
		return 'code-xml';
	}

	public function category(): string {
		return 'content';
	}

	public function keywords(): array {
		return array( 'code', 'snippet', 'pre', 'syntax', 'developer', 'terminal' );
	}

	public function description(): string {
		return __( 'Code snippet in a styled block (dark or light) with file name, language label, line numbers, highlighted lines and a copy button. The code is shown exactly as typed.', 'uncoder' );
	}

	public function frontend_scripts(): array {
		return array( 'code-copy' );
	}

	/**
	 * @return array<string,string>
	 */
	public static function languages(): array {
		return array(
			'plaintext'  => __( 'Plain text', 'uncoder' ),
			'html'       => 'HTML',
			'css'        => 'CSS',
			'scss'       => 'SCSS',
			'javascript' => 'JavaScript',
			'typescript' => 'TypeScript',
			'jsx'        => 'JSX',
			'json'       => 'JSON',
			'php'        => 'PHP',
			'python'     => 'Python',
			'ruby'       => 'Ruby',
			'go'         => 'Go',
			'rust'       => 'Rust',
			'java'       => 'Java',
			'kotlin'     => 'Kotlin',
			'swift'      => 'Swift',
			'c'          => 'C',
			'cpp'        => 'C++',
			'csharp'     => 'C#',
			'bash'       => 'Bash',
			'powershell' => 'PowerShell',
			'sql'        => 'SQL',
			'yaml'       => 'YAML',
			'markdown'   => 'Markdown',
			'diff'       => 'Diff',
		);
	}

	protected function register_controls(): void {
		$this->start_section( 'content_code', array( 'label' => __( 'Code', 'uncoder' ) ) );
		$this->add_control(
			'code',
			array(
				'type'     => 'code',
				'label'    => __( 'Code', 'uncoder' ),
				'language' => 'html',
				'escaped'  => true,
				'default'  => "// Load the three latest posts from the REST API\nconst response = await fetch('/wp-json/wp/v2/posts?per_page=3');\nconst posts = await response.json();\n\nposts.forEach((post) => {\n  console.log(post.title.rendered);\n});",
				'ai'       => 'Raw code as a plain string with \\n line breaks. It is escaped on output; do not HTML-encode it.',
			)
		);
		$this->add_control(
			'language',
			array(
				'type'    => 'select',
				'label'   => __( 'Language', 'uncoder' ),
				'default' => 'javascript',
				'options' => self::languages(),
			)
		);
		$this->add_control(
			'title',
			array(
				'type'        => 'text',
				'label'       => __( 'File name / title', 'uncoder' ),
				'placeholder' => 'fetch-posts.js',
				'inline'      => true,
			)
		);
		$this->add_control(
			'show_language',
			array(
				'type'    => 'switch',
				'label'   => __( 'Show language label', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'line_numbers',
			array(
				'type'    => 'switch',
				'label'   => __( 'Line numbers', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'start_line',
			array(
				'type'      => 'number',
				'label'     => __( 'First line number', 'uncoder' ),
				'default'   => 1,
				'min'       => 0,
				'max'       => 99999,
				'condition' => array( 'line_numbers' => true ),
			)
		);
		$this->add_control(
			'highlight_lines',
			array(
				'type'        => 'text',
				'label'       => __( 'Highlight lines', 'uncoder' ),
				'placeholder' => '2, 4-6',
				'description' => __( 'Line numbers or ranges, separated by commas.', 'uncoder' ),
			)
		);
		$this->add_control(
			'wrap_lines',
			array(
				'type'    => 'switch',
				'label'   => __( 'Wrap long lines', 'uncoder' ),
				'default' => false,
			)
		);
		$this->add_control(
			'copy_button',
			array(
				'type'    => 'switch',
				'label'   => __( 'Copy button', 'uncoder' ),
				'default' => true,
			)
		);
		$this->add_control(
			'copy_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Copy label', 'uncoder' ),
				'default'   => __( 'Copy', 'uncoder' ),
				'condition' => array( 'copy_button' => true ),
			)
		);
		$this->add_control(
			'copied_label',
			array(
				'type'      => 'text',
				'label'     => __( 'Copied label', 'uncoder' ),
				'default'   => __( 'Copied!', 'uncoder' ),
				'condition' => array( 'copy_button' => true ),
			)
		);
		$this->add_control(
			'theme',
			array(
				'type'    => 'choose',
				'label'   => __( 'Theme', 'uncoder' ),
				'default' => 'dark',
				'options' => array(
					'dark'  => array( 'label' => __( 'Dark', 'uncoder' ), 'icon' => 'moon' ),
					'light' => array( 'label' => __( 'Light', 'uncoder' ), 'icon' => 'sun' ),
				),
			)
		);
		$this->end_section();

		/* ------------------------------------------------------------ Style */

		$this->start_section( 'style_box', array( 'label' => __( 'Box', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_responsive_control(
			'font_size',
			array(
				'type'       => 'slider',
				'label'      => __( 'Font size', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 24 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--uncoder-code-size: {{VALUE}}' ),
			)
		);
		$this->add_group( 'code_typography', array( 'type' => 'typography', 'label' => __( 'Code typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-code__pre' ) );
		$this->add_responsive_control(
			'max_height',
			array(
				'type'       => 'slider',
				'label'      => __( 'Max height', 'uncoder' ),
				'size_units' => array( 'px', 'vh', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-code__pre' => 'max-height: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'tab_size',
			array(
				'type'      => 'number',
				'label'     => __( 'Tab size', 'uncoder' ),
				'min'       => 1,
				'max'       => 8,
				'selectors' => array( '{{WRAPPER}} .uncoder-code__pre' => 'tab-size: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-code-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'text_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-code-fg: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Code padding', 'uncoder' ),
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .uncoder-code__pre' => 'padding: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'uncoder' ),
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}}' => 'border-radius: {{VALUE}}' ),
			)
		);
		$this->add_group( 'border', array( 'type' => 'border', 'label' => __( 'Border', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->add_group( 'shadow', array( 'type' => 'box_shadow', 'label' => __( 'Shadow', 'uncoder' ), 'selector' => '{{WRAPPER}}' ) );
		$this->end_section();

		$this->start_section( 'style_header', array( 'label' => __( 'Header', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'header_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-code-head-bg: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'header_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Text color', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-code-head-fg: {{VALUE}}' ),
			)
		);
		$this->add_group( 'header_typography', array( 'type' => 'typography', 'label' => __( 'Typography', 'uncoder' ), 'selector' => '{{WRAPPER}} .uncoder-code__header' ) );
		$this->end_section();

		$this->start_section( 'style_lines', array( 'label' => __( 'Lines', 'uncoder' ), 'tab' => 'style' ) );
		$this->add_control(
			'number_color',
			array(
				'type'      => 'color',
				'label'     => __( 'Line number color', 'uncoder' ),
				'condition' => array( 'line_numbers' => true ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-code-num: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'highlight_background',
			array(
				'type'      => 'color',
				'label'     => __( 'Highlighted line background', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-code-hl: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'highlight_accent',
			array(
				'type'      => 'color',
				'label'     => __( 'Highlighted line marker', 'uncoder' ),
				'selectors' => array( '{{WRAPPER}}' => '--uncoder-code-hl-bar: {{VALUE}}' ),
			)
		);
		$this->end_section();

		$this->start_section( 'style_copy', array( 'label' => __( 'Copy button', 'uncoder' ), 'tab' => 'style', 'condition' => array( 'copy_button' => true ) ) );
		$this->start_tabs( 'copy_tabs' );
		$this->start_tab( 'normal', __( 'Normal', 'uncoder' ) );
		$this->add_control( 'copy_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-code__copy' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'copy_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-code__copy' => 'background-color: {{VALUE}}' ) ) );
		$this->end_tab();
		$this->start_tab( 'hover', __( 'Hover', 'uncoder' ) );
		$this->add_control( 'copy_hover_color', array( 'type' => 'color', 'label' => __( 'Text color', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-code__copy:is(:hover, :focus-visible)' => 'color: {{VALUE}}' ) ) );
		$this->add_control( 'copy_hover_background', array( 'type' => 'color', 'label' => __( 'Background', 'uncoder' ), 'selectors' => array( '{{WRAPPER}} .uncoder-code__copy:is(:hover, :focus-visible)' => 'background-color: {{VALUE}}' ) ) );
		$this->end_tab();
		$this->end_tabs();
		$this->end_section();
	}

	/**
	 * Parses "2, 4-6" into a lookup of line numbers (bounded to keep it cheap).
	 *
	 * @return array<int,bool>
	 */
	private function highlighted( string $spec, int $max ): array {
		$out = array();
		foreach ( explode( ',', $spec ) as $part ) {
			if ( ! preg_match( '/^\s*(\d+)\s*(?:-\s*(\d+))?\s*$/', $part, $m ) ) {
				continue;
			}
			$from = (int) $m[1];
			$to   = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : $from;
			if ( $to < $from ) {
				list( $from, $to ) = array( $to, $from );
			}
			for ( $n = $from; $n <= min( $to, $from + $max ); $n++ ) {
				$out[ $n ] = true;
			}
		}
		return $out;
	}

	protected function render( array $s, Render_Context $ctx ): void {
		$code = str_replace( array( "\r\n", "\r" ), "\n", (string) ( $s['code'] ?? '' ) );
		$code = rtrim( $code, "\n" );
		if ( '' === trim( $code ) ) {
			if ( $ctx->editor ) {
				echo '<p class="uncoder-code__empty">' . esc_html__( 'Paste some code to display it here.', 'uncoder' ) . '</p>';
			}
			return;
		}

		$languages = self::languages();
		$language  = isset( $languages[ $s['language'] ?? '' ] ) ? (string) $s['language'] : 'plaintext';
		$numbers   = ! array_key_exists( 'line_numbers', $s ) || ! empty( $s['line_numbers'] );
		$start     = max( 0, (int) ( $s['start_line'] ?? 1 ) );
		$lines     = explode( "\n", $code );
		$hl        = $this->highlighted( (string) ( $s['highlight_lines'] ?? '' ), count( $lines ) + $start );
		$title     = trim( (string) ( $s['title'] ?? '' ) );
		$show_lang = ( ! array_key_exists( 'show_language', $s ) || ! empty( $s['show_language'] ) ) && 'plaintext' !== $language;
		$copy      = ! array_key_exists( 'copy_button', $s ) || ! empty( $s['copy_button'] );

		$html = array();
		foreach ( $lines as $i => $line ) {
			$n     = $start + $i;
			$attrs = array(
				'class'     => 'uncoder-code__line' . ( isset( $hl[ $n ] ) ? ' uncoder-code__line--hl' : '' ),
				'data-line' => $numbers ? (string) $n : null,
			);
			// htmlspecialchars (not esc_html) so existing entities in the sample are shown literally.
			$html[] = '<span' . Utils::attrs( $attrs ) . '>' . htmlspecialchars( $line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</span>';
		}

		$classes = array( 'uncoder-code', 'uncoder-code--' . ( 'light' === ( $s['theme'] ?? 'dark' ) ? 'light' : 'dark' ) );
		if ( $numbers ) {
			$classes[] = 'uncoder-code--numbers';
		}
		if ( ! empty( $s['wrap_lines'] ) ) {
			$classes[] = 'uncoder-code--wrap';
		}
		$label = '' !== $title ? $title : $languages[ $language ];

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( '' !== $title || $show_lang || $copy ) {
			echo '<div class="uncoder-code__header">';
			if ( '' !== $title || $ctx->editor ) {
				echo '<span class="uncoder-code__title"' . $ctx->inline( 'title' ) . '>' . esc_html( $title ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline() is escaped.
			}
			if ( $show_lang ) {
				echo '<span class="uncoder-code__lang">' . esc_html( $languages[ $language ] ) . '</span>';
			}
			if ( $copy ) {
				$button = array(
					'type'        => 'button',
					'class'       => 'uncoder-code__copy',
					'data-copied' => (string) ( $s['copied_label'] ?? __( 'Copied!', 'uncoder' ) ),
					'hidden'      => ! $ctx->editor,
				);
				echo '<button' . Utils::attrs( $button ) . '>' . $this->render_icon( 'copy', array( 'class' => 'uncoder-code__copy-icon' ) ) . '<span class="uncoder-code__copy-label">' . esc_html( (string) ( $s['copy_label'] ?? __( 'Copy', 'uncoder' ) ) ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
				echo '<span class="uncoder-sr-only uncoder-code__status" role="status" aria-live="polite"></span>';
			}
			echo '</div>';
		}
		$pre = array(
			'class'      => 'uncoder-code__pre',
			'tabindex'   => '0',
			'role'       => 'region',
			/* translators: %s: file name or language, e.g. "JavaScript". */
			'aria-label' => sprintf( __( 'Code: %s', 'uncoder' ), $label ),
		);
		echo '<pre' . Utils::attrs( $pre ) . '><code class="' . esc_attr( 'uncoder-code__code language-' . $language ) . '">' . implode( "\n", $html ) . '</code></pre>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lines escaped with htmlspecialchars above.
		echo '</div>';
	}
}
