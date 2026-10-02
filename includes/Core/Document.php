<?php
/**
 * A builder document (page, post or template).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

use Uncoder\Builder\Core\Css\Generator;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Loads, saves and renders one post's element tree.
 */
final class Document {

	public const SCHEMA_VERSION = 1;
	public const META_ASSETS    = '_uncoder_wb_assets';
	public const META_REV       = '_uncoder_wb_rev';

	/**
	 * Who is changing documents in this request ('' = the visual editor). Set by the MCP server to the
	 * client name so an open editor can say "Updated by Claude".
	 */
	public static string $source = '';

	/** True while rendering the stored HTML copy: no request data or user context may leak into it. */
	public static bool $static_render = false;

	private int $id;

	/** @var array<int, array<string,mixed>>|null */
	private ?array $elements = null;

	/** Set by use_draft(): this request shows an unsaved tree, so CSS and assets are built in memory. */
	private bool $draft = false;

	/** @var array{css:string, assets:array<string,mixed>}|null Compiled draft (see compile()). */
	private ?array $compiled = null;

	public function __construct( int $post_id ) {
		$this->id = $post_id;
	}

	public function id(): int {
		return $this->id;
	}

	public function post(): ?\WP_Post {
		$post = get_post( $this->id );
		return $post instanceof \WP_Post ? $post : null;
	}

	public function is_builder(): bool {
		return Utils::is_builder_post( $this->id );
	}

	/**
	 * Template type for theme builder documents, or "page"/"post"/post type for content.
	 */
	public function type(): string {
		$post = $this->post();
		if ( ! $post ) {
			return '';
		}
		if ( Post_Types::TEMPLATE === $post->post_type ) {
			$type = get_post_meta( $this->id, Utils::META_TYPE, true );
			return is_string( $type ) && '' !== $type ? $type : 'section';
		}
		return $post->post_type;
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	public function elements(): array {
		if ( null === $this->elements ) {
			$raw            = get_post_meta( $this->id, Utils::META_DATA, true );
			$data           = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
			$this->elements = Tree::upgrade( is_array( $data['elements'] ?? null ) ? $data['elements'] : ( is_array( $data ) && isset( $data[0] ) ? $data : array() ) );
		}
		return $this->elements;
	}

	/**
	 * Shows `$elements` instead of the stored tree for the rest of this request (preview of unsaved changes,
	 * see Editor\Draft_Preview). Nothing is written: CSS is inlined and assets are worked out in memory.
	 *
	 * @param array<int, array<string,mixed>> $elements Sanitized tree.
	 */
	public function use_draft( array $elements ): void {
		$this->elements = $elements;
		$this->draft    = true;
		$this->compiled = null;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function page_settings(): array {
		$raw = get_post_meta( $this->id, Utils::META_PAGE, true );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Sanitizes and stores a tree. Returns the stored tree.
	 *
	 * @param mixed $elements Raw tree.
	 * @param array<string,mixed> $opts mode (sanitize|normalize), errors (by ref via return), content_fallback.
	 * @return array{elements: array<int, array<string,mixed>>, errors: string[], saved?: bool}
	 */
	public function save( $elements, array $opts = array() ): array {
		$tree  = new Tree( $opts['mode'] ?? 'sanitize' );
		$clean = $tree->process( $elements );
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			// Users who may not add code cannot change custom CSS, but saving must not wipe the CSS an
			// administrator gave these elements.
			$clean = self::keep_custom_css( $clean, $this->elements() );
		}

		$payload = array(
			'version'  => self::SCHEMA_VERSION,
			'elements' => $clean,
		);
		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
		if ( false === $json ) {
			// A value JSON cannot hold (e.g. an infinite number) would store an empty page: keep the saved one.
			return array(
				'elements' => $this->elements(),
				'errors'   => array_merge( $tree->errors, array( __( 'The page was not saved because a setting contains a value that cannot be stored (for example an infinite number).', 'uncoder' ) ) ),
				'saved'    => false,
			);
		}
		update_post_meta( $this->id, Utils::META_DATA, wp_slash( $json ) );
		update_post_meta( $this->id, Utils::META_MODE, 'builder' );
		$this->elements = $clean;

		$this->regenerate();
		$this->touch();

		if ( ! empty( $opts['content_fallback'] ) ) {
			$this->write_content_fallback();
		}

		/**
		 * Fires after a document was saved.
		 *
		 * @param Document $document Document.
		 */
		do_action( 'uncoder_wb/document/saved', $this );

		return array(
			'elements' => $clean,
			'errors'   => $tree->errors,
		);
	}

	/**
	 * Copies each element's stored custom CSS (every device) back into the new tree, by element id.
	 *
	 * @param array<int, array<string,mixed>> $clean New tree (sanitized).
	 * @param array<int, array<string,mixed>> $old   Stored tree.
	 * @return array<int, array<string,mixed>>
	 */
	private static function keep_custom_css( array $clean, array $old ): array {
		$previous = array();
		Tree::walk(
			$old,
			static function ( $node ) use ( &$previous ) {
				foreach ( (array) ( $node['settings'] ?? array() ) as $key => $value ) {
					if ( is_string( $key ) && preg_match( '/^_custom_css(_[a-z0-9_]+)?$/', $key ) && is_string( $value ) && '' !== $value ) {
						$previous[ (string) ( $node['id'] ?? '' ) ][ $key ] = $value;
					}
				}
			}
		);
		if ( ! $previous ) {
			return $clean;
		}
		Tree::walk(
			$clean,
			static function ( &$node ) use ( $previous ) {
				$id = (string) ( $node['id'] ?? '' );
				if ( '' === $id || ! isset( $previous[ $id ] ) ) {
					return;
				}
				$settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : array();
				foreach ( $settings as $key => $value ) {
					if ( is_string( $key ) && preg_match( '/^_custom_css(_[a-z0-9_]+)?$/', $key ) ) {
						unset( $settings[ $key ] );
					}
				}
				$node['settings'] = array_merge( $settings, $previous[ $id ] );
			}
		);
		return $clean;
	}

	/**
	 * @param array<string,mixed> $settings Page settings.
	 */
	public function save_page_settings( array $settings ): void {
		$settings = Migrations::legacy( $settings );
		$clean    = array();
		if ( isset( $settings['hide_title'] ) ) {
			$clean['hide_title'] = (bool) $settings['hide_title'];
		}
		if ( isset( $settings['background'] ) ) {
			$clean['background'] = Utils::sanitize_color( (string) $settings['background'] );
		}
		if ( isset( $settings['scroll_snap'] ) ) {
			$clean['scroll_snap'] = in_array( $settings['scroll_snap'], array( 'proximity', 'mandatory' ), true ) ? $settings['scroll_snap'] : '';
		}
		if ( isset( $settings['scroll_snap_align'] ) ) {
			$clean['scroll_snap_align'] = in_array( $settings['scroll_snap_align'], array( 'start', 'center' ), true ) ? $settings['scroll_snap_align'] : 'start';
		}
		if ( current_user_can( 'unfiltered_html' ) ) {
			foreach ( Breakpoints::devices() as $device ) {
				$key = 'custom_css' . Breakpoints::suffix( $device );
				if ( isset( $settings[ $key ] ) ) {
					$clean[ $key ] = Utils::sanitize_custom_css( $settings[ $key ] );
				}
			}
		}
		if ( isset( $settings['preview_post'] ) ) {
			$clean['preview_post'] = absint( $settings['preview_post'] );
		}
		if ( 'section' === $this->type() && array_key_exists( 'component_props', $settings ) ) {
			$clean['component_props'] = \Uncoder\Builder\Site\Components::sanitize_props( $settings['component_props'], $this->elements() );
		}
		if ( 'header' === $this->type() ) {
			$clean = array_merge( $clean, \Uncoder\Builder\Theme\Header_Behavior::sanitize( $settings ) );
		} elseif ( Post_Types::TEMPLATE !== get_post_type( $this->id ) && array_key_exists( 'header_transparent', $settings ) ) {
			// Pages opt in or out of a transparent header.
			$clean['header_transparent'] = in_array( $settings['header_transparent'], array( 'yes', 'no' ), true ) ? $settings['header_transparent'] : '';
		}
		$existing = $this->page_settings();
		update_post_meta( $this->id, Utils::META_PAGE, array_merge( $existing, $clean ) );
		$this->regenerate();
		$this->touch();
	}

	/**
	 * Records a new revision stamp (open editors poll it to pick up changes made elsewhere).
	 */
	public function touch(): void {
		update_post_meta(
			$this->id,
			self::META_REV,
			array(
				'id'   => wp_generate_password( 10, false, false ),
				'by'   => '' !== self::$source ? self::$source : 'editor',
				'user' => get_current_user_id(),
				'time' => time(),
			)
		);
	}

	/**
	 * @return array{id:string, by:string, user:int, time:int}
	 */
	public function rev(): array {
		$rev = get_post_meta( $this->id, self::META_REV, true );
		return array(
			'id'   => (string) ( $rev['id'] ?? '' ),
			'by'   => (string) ( $rev['by'] ?? '' ),
			'user' => (int) ( $rev['user'] ?? 0 ),
			'time' => (int) ( $rev['time'] ?? 0 ),
		);
	}

	/**
	 * Regenerates CSS and the assets manifest.
	 */
	public function regenerate(): void {
		if ( $this->draft ) {
			return;
		}
		$result = $this->compile();
		$css    = $result['css'];

		$uploads = Utils::uploads();
		$written = false;
		if ( $uploads ) {
			if ( ! is_dir( $uploads['dir'] . '/css' ) ) {
				wp_mkdir_p( $uploads['dir'] . '/css' );
			}
			$file    = $uploads['dir'] . '/css/doc-' . $this->id . '.css';
			$written = false !== file_put_contents( $file, $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		update_post_meta( $this->id, '_uncoder_wb_css_inline', $written ? '' : wp_slash( $css ) );
		update_post_meta( $this->id, Utils::META_CSS, $this->css_signature() . '|' . time() );
		update_post_meta( $this->id, self::META_ASSETS, $result['assets'] );
	}

	/**
	 * Builds the document's CSS and assets manifest from the current tree, without storing anything.
	 *
	 * @return array{css:string, assets:array<string,mixed>}
	 */
	private function compile(): array {
		if ( $this->draft && null !== $this->compiled ) {
			return $this->compiled;
		}
		$generator = new Generator( $this->id );
		$result    = $generator->document( $this->elements() );
		$css       = $result['css'];

		$page = $this->page_settings();
		if ( ! empty( $page['background'] ) ) {
			$css = '.uncoder-' . $this->id . '{background-color:' . $page['background'] . '}' . $css;
		}
		// Scroll snap: the page stops at the start (or middle) of each top-level section. Live site only: the
		// editor canvas never snaps.
		if ( ! empty( $page['scroll_snap'] ) && in_array( $page['scroll_snap'], array( 'proximity', 'mandatory' ), true ) ) {
			$align = 'center' === ( $page['scroll_snap_align'] ?? '' ) ? 'center' : 'start';
			$css  .= 'html:has(.uncoder-' . $this->id . '){scroll-snap-type:y ' . $page['scroll_snap'] . '}'
				. '.uncoder-' . $this->id . '>*{scroll-snap-align:' . $align . '}'
				. '@media (prefers-reduced-motion:reduce){html:has(.uncoder-' . $this->id . '){scroll-snap-type:none}}';
		}
		$page_rules = new Css\Rules();
		Generator::custom_css( $page, 'custom_css', '.uncoder-' . $this->id, $page_rules );
		$css .= $page_rules->render();

		$types   = Tree::types( $this->elements() );
		$scripts = array();
		$styles  = array();
		foreach ( $types as $name ) {
			$el = Plugin::instance()->elements()->get( $name );
			if ( $el ) {
				$scripts = array_merge( $scripts, $el->frontend_scripts() );
				$styles  = array_merge( $styles, $el->frontend_styles() );
			}
		}
		$refs = array();
		Tree::walk(
			$this->elements,
			static function ( $node ) use ( &$refs, &$scripts, &$styles ) {
				$s = $node['settings'] ?? array();
				foreach ( array( 'template_id', 'loop_template', 'alternate_template' ) as $key ) {
					if ( ! empty( $s[ $key ] ) && is_numeric( $s[ $key ] ) ) {
						$refs[] = (int) $s[ $key ];
					}
				}
				if ( ! empty( $s['_animation'] ) ) {
					$scripts[] = 'animations';
				}
				if ( is_array( $s ) && Common_Controls::animations( $s ) ) {
					$scripts[] = 'animate';
				}
				if ( ! empty( $s['_sticky'] ) ) {
					$scripts[] = 'sticky';
				}
				if ( is_array( $s ) && '' !== Common_Controls::reveal( $s ) ) {
					$scripts[] = 'text-reveal';
				}
				$bg_type = is_array( $s['background'] ?? null ) ? (string) ( $s['background']['type'] ?? '' ) : '';
				if ( 'video' === $bg_type ) {
					$scripts[] = 'bg-video';
				} elseif ( 'slideshow' === $bg_type ) {
					$scripts[] = 'bg-slideshow';
				}
				if ( ! empty( $s['bg_motion'] ) && in_array( $bg_type, array( 'classic', 'slideshow' ), true ) ) {
					$scripts[] = 'bg-motion';
				}
				$animated = is_array( $s ) ? Animated_Backgrounds::name( $s ) : '';
				if ( '' !== $animated ) {
					$styles[] = 'bg-animated';
					if ( Animated_Backgrounds::is_shader( $animated ) ) {
						$scripts[] = 'bg-animated';
					}
				}
				if ( is_array( $s ) && Common_Controls::motion( $s ) ) {
					$scripts[] = 'motion';
				}
				if ( ! empty( $s['_interactions'] ) ) {
					$scripts[] = 'interactions';
				}
			}
		);
		$compiled = array(
			'css'    => $css,
			'assets' => array(
				'types'   => $types,
				'scripts' => array_values( array_unique( $scripts ) ),
				'styles'  => array_values( array_unique( $styles ) ),
				'fonts'   => $result['fonts'],
				'refs'    => array_values( array_unique( $refs ) ),
				'lcp'     => \Uncoder\Builder\Site\Performance::detect( $this->elements() ),
			),
		);
		if ( $this->draft ) {
			$this->compiled = $compiled;
		}
		return $compiled;
	}

	private function css_signature(): string {
		return UNCODER_WB_VERSION . '.' . Css\Generator::REVISION . '|' . Plugin::instance()->kit()->version();
	}

	/**
	 * CSS file for the document, regenerated when stale.
	 *
	 * @return array{url?:string, ver:string, inline?:string}
	 */
	public function css(): array {
		if ( $this->draft ) {
			$css = $this->compile()['css'];
			return array(
				'ver'    => substr( md5( $css ), 0, 10 ),
				'inline' => $css,
			);
		}
		$stored = (string) get_post_meta( $this->id, Utils::META_CSS, true );
		if ( '' === $stored || 0 !== strpos( $stored, $this->css_signature() . '|' ) ) {
			$this->regenerate();
			$stored = (string) get_post_meta( $this->id, Utils::META_CSS, true );
		}
		$ver    = substr( md5( $stored ), 0, 10 );
		$inline = (string) get_post_meta( $this->id, '_uncoder_wb_css_inline', true );
		if ( '' !== $inline ) {
			return array(
				'ver'    => $ver,
				'inline' => $inline,
			);
		}
		$uploads = Utils::uploads();
		if ( ! $uploads ) {
			return array( 'ver' => $ver );
		}
		$file = $uploads['dir'] . '/css/doc-' . $this->id . '.css';
		if ( ! file_exists( $file ) ) {
			$this->regenerate();
		}
		return array(
			'url'  => $uploads['url'] . '/css/doc-' . $this->id . '.css',
			'path' => $file,
			'ver'  => $ver,
		);
	}

	/**
	 * @return array{types:string[],scripts:string[],styles:string[],fonts:array<string,string[]>,refs:int[]}
	 */
	public function assets(): array {
		$assets = $this->draft ? $this->compile()['assets'] : get_post_meta( $this->id, self::META_ASSETS, true );
		if ( ! is_array( $assets ) ) {
			$this->regenerate();
			$assets = get_post_meta( $this->id, self::META_ASSETS, true );
		}
		return array_merge(
			array(
				'types'   => array(),
				'scripts' => array(),
				'styles'  => array(),
				'fonts'   => array(),
				'refs'    => array(),
			),
			is_array( $assets ) ? $assets : array()
		);
	}

	/**
	 * @param array<string,mixed> $args Renderer::render_document args + post_id.
	 */
	public function render( array $args = array() ): string {
		return $this->render_tree( $this->elements(), $args );
	}

	/**
	 * Renders another tree in this document's scope (e.g. a component with overrides applied).
	 *
	 * @param array<int, array<string,mixed>> $elements Tree.
	 * @param array<string,mixed>             $args     Renderer::render_document args + post_id.
	 */
	public function render_tree( array $elements, array $args = array() ): string {
		$renderer      = new Renderer( $this->id, false, (int) ( $args['post_id'] ?? 0 ) );
		$args['class'] = trim( ( $args['class'] ?? '' ) . ' uncoder--' . sanitize_html_class( $this->type() ) );
		return $renderer->render_document( $elements, $args );
	}

	/**
	 * Writes a plain HTML version of the page to post_content so content survives deactivation
	 * and core search finds it.
	 */
	public function write_content_fallback(): void {
		$post = $this->post();
		if ( ! $post || Post_Types::TEMPLATE === $post->post_type ) {
			return;
		}
		// Render as a logged-out visitor without request data (the copy is public post_content).
		$user                = get_current_user_id();
		self::$static_render = true;
		wp_set_current_user( 0 );
		try {
			$html = $this->render();
		} finally {
			wp_set_current_user( $user );
			self::$static_render = false;
		}
		// Keep only content tags: no builder wrappers, scripts, forms or inline styles.
		$html = preg_replace( '#<(script|style|form|svg)[^>]*>.*?</\1>#is', '', $html );
		// Buttons are UI that does nothing without the plugin (menu toggles, close buttons, tab lists,
		// arrows); the titles of stacked tabs are content and stay, on a line of their own.
		$html = preg_replace_callback(
			'#<button\b([^>]*)>(.*?)</button>#is',
			static fn( array $m ): string => false !== strpos( $m[1], 'uncoder-tabs__acc' ) ? "\n" . $m[2] . "\n" : '',
			(string) $html
		);
		// Line breaks where layout tags open or close and before text parts shown on a line of their own
		// (a quote's author and role), so the texts of neighbouring elements do not run together.
		$html = preg_replace( '#</?(?:div|section|article|header|footer|nav|aside|main|figure|figcaption|details|summary|dialog|dl|dt|dd|table|thead|tbody|tfoot|tr|td|th|caption|address|fieldset|legend|label|hr)\b[^>]*>#i', "\n", (string) $html );
		$html = preg_replace( '#<span\b[^>]*\bclass="[^"]*__(?:name|role|title|subtitle|label|desc|item|tip|percent)\b#i', "\n" . '$0', (string) $html );
		$html = wp_kses(
			(string) $html,
			array(
				'h1'         => array(),
				'h2'         => array(),
				'h3'         => array(),
				'h4'         => array(),
				'h5'         => array(),
				'h6'         => array(),
				'p'          => array(),
				'a'          => array( 'href' => true ),
				'ul'         => array(),
				'ol'         => array(),
				'li'         => array(),
				'strong'     => array(),
				'em'         => array(),
				'blockquote' => array(),
				'img'        => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true ),
				'br'         => array(),
				'pre'        => array(),
				'code'       => array(),
			)
		);
		// Neighbouring links (a row of buttons) each get their own line.
		$html = preg_replace( '#</a>\s*<a\b#i', "</a>\n<a", (string) $html );
		$html = self::fallback_paragraphs( (string) $html );

		// The fallback is derived data: never create a revision for it.
		remove_action( 'post_updated', 'wp_save_post_revision' );
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $this->id,
					'post_content' => $html,
				)
			)
		);
		add_action( 'post_updated', 'wp_save_post_revision' );
	}

	/**
	 * Turns the filtered copy into clean blocks: loose text between block tags becomes one paragraph per
	 * line (a line break inside list items, a space inside headings and paragraphs), and blocks left
	 * empty by the removed markup go.
	 */
	private static function fallback_paragraphs( string $html ): string {
		$out    = '';
		$buffer = '';
		$stack  = array();
		$inline = 0;
		$flush  = static function () use ( &$out, &$buffer, &$stack ): void {
			$lines  = array_filter(
				array_map( 'trim', explode( "\n", $buffer ) ),
				static fn( string $line ): bool => '' !== trim( wp_strip_all_tags( $line ) ) || false !== stripos( $line, '<img' )
			);
			$buffer = '';
			if ( ! $lines ) {
				return;
			}
			$parent = end( $stack );
			if ( 'li' === $parent ) {
				$out .= implode( '<br>', $lines );
			} elseif ( in_array( $parent, array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
				$out .= implode( ' ', $lines );
			} else {
				$out .= '<p>' . implode( "</p>\n<p>", $lines ) . "</p>\n";
			}
		};
		// A code block (<pre>…</pre>) is one token and keeps its lines as they are.
		foreach ( (array) preg_split( '#(<pre>.*?</pre>|<[^>]+>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) as $token ) {
			$token = (string) $token;
			if ( 0 === stripos( $token, '<pre>' ) ) {
				$flush();
				$out .= $token . "\n";
			} elseif ( preg_match( '#^<(/?)(h[1-6]|p|ul|ol|li|blockquote)\b#i', $token, $m ) ) {
				$flush();
				if ( '' === $m[1] ) {
					$stack[] = strtolower( $m[2] );
					$out    .= $token;
				} else {
					array_pop( $stack );
					$out .= $token . "\n";
				}
			} elseif ( preg_match( '#^<(/?)(a|strong|em)\b#i', $token, $m ) ) {
				$inline += '' === $m[1] ? 1 : -1;
				$buffer .= $token;
			} else {
				// Inside a link or emphasis a line break is only a space (a card link keeps its texts together).
				$buffer .= $inline > 0 && '<' !== $token[0] ? (string) preg_replace( '/\s*\n\s*/', ' ', $token ) : $token;
			}
		}
		$flush();
		do {
			$out = (string) preg_replace( '#<(p|h[1-6]|li|ul|ol|blockquote|strong|em)>\s*</\1>\n?#', '', $out, -1, $count );
		} while ( $count > 0 );
		return trim( $out );
	}

	/**
	 * Deletes generated CSS.
	 */
	public function delete_css(): void {
		$uploads = Utils::uploads();
		if ( $uploads ) {
			wp_delete_file( $uploads['dir'] . '/css/doc-' . $this->id . '.css' );
		}
	}
}
