<?php
/**
 * Theme builder: applies header, footer and body templates according to display conditions.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Theme;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Classic themes: get_header / get_footer interception + template_include for body templates.
 * Block themes: header/footer template parts are replaced at render time; body templates take over the page.
 */
final class Theme_Builder {

	public const INDEX_OPTION = 'uncoder_wb_templates_index';

	/** Body template types tried for each request context, in order. */
	private const BODY_TYPES = array( 'single-post', 'single-page', 'single', 'archive', 'search-results', 'error-404' );

	/** @var array<string, int> type => template id (0 = none) for this request */
	private array $resolved = array();

	private bool $header_done = false;

	private bool $footer_done = false;

	private static ?Theme_Builder $instance = null;

	public static function instance(): ?Theme_Builder {
		return self::$instance;
	}

	public function register(): void {
		self::$instance = $this;
		add_action( 'uncoder_wb/document/saved', array( $this, 'maybe_rebuild' ) );
		add_action( 'transition_post_status', array( $this, 'on_status' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'rebuild_index' ) );
		add_action( 'updated_post_meta', array( $this, 'on_meta' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'on_meta' ), 10, 3 );

		add_filter( 'template_include', array( $this, 'template_include' ), 95 );
		add_action( 'get_header', array( $this, 'classic_header' ), 0 );
		add_action( 'get_footer', array( $this, 'classic_footer' ), 0 );
		add_filter( 'render_block', array( $this, 'block_template_part' ), 10, 2 );
		add_filter( 'uncoder_wb/theme/print_location', array( $this, 'print_location' ), 10, 2 );
		add_action( 'uncoder_wb/frontend/enqueue', array( $this, 'enqueue' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/* ------------------------------------------------------------------ Index */

	/**
	 * @return array<string, array<int, array{id:int, conditions:array, modified:int}>>
	 */
	public function index(): array {
		$index = get_option( self::INDEX_OPTION );
		if ( ! is_array( $index ) ) {
			$index = $this->rebuild_index();
		}
		return $index;
	}

	/**
	 * @return array<string, array<int, array<string,mixed>>>
	 */
	public function rebuild_index(): array {
		$posts = get_posts(
			array(
				'post_type'      => Post_Types::TEMPLATE,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'no_found_rows'  => true,
				'lang'           => '', // Polylang: every language (resolution picks the visitor's).
			)
		);
		$index = array();
		foreach ( $posts as $post ) {
			$type  = (string) get_post_meta( $post->ID, Utils::META_TYPE, true );
			$conds = get_post_meta( $post->ID, Utils::META_CONDS, true );
			if ( '' === $type || ! is_array( $conds ) || ! $conds ) {
				continue;
			}
			$index[ $type ][] = array(
				'id'         => $post->ID,
				'conditions' => $conds,
				'modified'   => (int) get_post_modified_time( 'U', true, $post ),
			);
		}
		update_option( self::INDEX_OPTION, $index, true );
		return $index;
	}

	/**
	 * @param mixed $document Document.
	 */
	public function maybe_rebuild( $document ): void {
		if ( $document && Post_Types::TEMPLATE === get_post_type( $document->id() ) ) {
			$this->rebuild_index();
		}
	}

	public function on_status( $new, $old, $post ): void {
		if ( $post instanceof \WP_Post && Post_Types::TEMPLATE === $post->post_type && $new !== $old ) {
			$this->rebuild_index();
		}
	}

	public function on_meta( $meta_id, $post_id, $key ): void {
		if ( in_array( $key, array( Utils::META_CONDS, Utils::META_TYPE ), true ) ) {
			$this->rebuild_index();
		}
	}

	/* ------------------------------------------------------------------ Resolution */

	/**
	 * Best published template of a type for the current request (0 = none).
	 */
	public function resolve( string $type ): int {
		if ( isset( $this->resolved[ $type ] ) ) {
			return $this->resolved[ $type ];
		}
		$exclude = (array) apply_filters( 'uncoder_wb/theme/exclude_template', array() );
		$best    = 0;
		$score   = -1;
		$mod     = 0;
		foreach ( $this->index()[ $type ] ?? array() as $entry ) {
			// Another language's copy of a template that exists in the visitor's language.
			if ( in_array( (int) $entry['id'], $exclude, true ) || \Uncoder\Builder\Site\Multilingual::has_local_version( (int) $entry['id'] ) ) {
				continue;
			}
			$s = Conditions::match( (array) $entry['conditions'] );
			if ( null === $s ) {
				continue;
			}
			if ( $s > $score || ( $s === $score && $entry['modified'] > $mod ) ) {
				$best  = (int) $entry['id'];
				$score = $s;
				$mod   = (int) $entry['modified'];
			}
		}
		/**
		 * Filters the template chosen for a location.
		 *
		 * @param int    $best Template id (0 = none).
		 * @param string $type Template type.
		 */
		$best                    = (int) apply_filters( 'uncoder_wb/theme/resolve', $best, $type );
		$this->resolved[ $type ] = $best;
		return $best;
	}

	/**
	 * Body template for the current request: [ template id, type ].
	 *
	 * @return array{0:int,1:string}
	 */
	public function resolve_body(): array {
		if ( is_singular( Post_Types::TEMPLATE ) ) {
			return array( 0, '' );
		}
		$candidates = array();
		if ( is_404() ) {
			$candidates = array( 'error-404' );
		} elseif ( is_search() ) {
			$candidates = array( 'search-results', 'archive' );
		} elseif ( is_singular() ) {
			$pt         = get_post_type( (int) get_queried_object_id() );
			$candidates = 'post' === $pt ? array( 'single-post', 'single' ) : ( 'page' === $pt ? array( 'single-page', 'single' ) : array( 'single' ) );
		} elseif ( is_archive() || is_home() ) {
			$candidates = array( 'archive' );
		}
		foreach ( $candidates as $type ) {
			$id = $this->resolve( $type );
			if ( $id ) {
				return array( $id, $type );
			}
		}
		return array( 0, '' );
	}

	/* ------------------------------------------------------------------ Output */

	public function render_template( int $id, array $args = array() ): string {
		$doc = Plugin::instance()->documents()->get( $id );
		if ( ! $doc ) {
			return '';
		}
		Assets::enqueue_late( $doc );
		return $doc->render( $args );
	}

	/**
	 * Body templates take over the page through a shell template.
	 *
	 * @param string $template Template file.
	 */
	public function template_include( $template ): string {
		if ( is_embed() || is_feed() ) {
			return (string) $template;
		}
		list( $id ) = $this->resolve_body();
		if ( ! $id ) {
			return (string) $template;
		}
		return UNCODER_WB_PATH . 'templates/theme-shell.php';
	}

	/**
	 * Prints the body template (called from templates/theme-shell.php).
	 */
	public function print_body(): void {
		list( $id, $type ) = $this->resolve_body();
		if ( ! $id ) {
			return;
		}
		$post_id = 0;
		if ( is_singular() && have_posts() ) {
			the_post();
			$post_id = (int) get_the_ID();
		}
		echo $this->render_template( $id, array( 'post_id' => $post_id, 'class' => 'uncoder-location uncoder-location--' . $type, 'tag' => 'div' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by the escaping renderer.
		if ( $post_id ) {
			rewind_posts();
		}
	}

	/**
	 * Shell helper: prints header/footer when a template applies.
	 */
	public function print_location( bool $printed, string $location ): bool {
		if ( $printed ) {
			return true;
		}
		$id = $this->resolve( $location );
		if ( ! $id ) {
			return false;
		}
		$args = array( 'class' => 'uncoder-location uncoder-location--' . $location, 'tag' => 'header' === $location ? 'header' : ( 'footer' === $location ? 'footer' : 'div' ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by the escaping renderer.
		echo 'header' === $location ? Header_Behavior::render( $this, $id, $args ) : $this->render_template( $id, $args );
		return true;
	}

	/**
	 * Classic themes: replace the theme header.
	 *
	 * @param string|null $name Header name.
	 */
	public function classic_header( $name ): void {
		if ( wp_is_block_theme() || $this->header_done || ! $this->resolve( 'header' ) ) {
			return;
		}
		$this->header_done = true;
		require UNCODER_WB_PATH . 'templates/header.php';
		$this->swallow( 'header', $name, 'wp_head' );
	}

	/**
	 * @param string|null $name Footer name.
	 */
	public function classic_footer( $name ): void {
		if ( wp_is_block_theme() || $this->footer_done || ! $this->resolve( 'footer' ) ) {
			return;
		}
		$this->footer_done = true;
		require UNCODER_WB_PATH . 'templates/footer.php';
		$this->swallow( 'footer', $name, 'wp_footer' );
	}

	/**
	 * Loads the theme's header.php/footer.php once into a discarded buffer so get_header()/get_footer()
	 * (which use require_once) do not print it again.
	 */
	private function swallow( string $part, $name, string $hook ): void {
		$templates = array();
		$name      = (string) $name;
		if ( '' !== $name ) {
			$templates[] = "{$part}-{$name}.php";
		}
		$templates[] = "{$part}.php";
		remove_all_actions( $hook );
		ob_start();
		locate_template( $templates, true );
		ob_end_clean();
	}

	/**
	 * Block themes: replace header/footer template parts.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 */
	public function block_template_part( $content, $block ): string {
		if ( 'core/template-part' !== ( $block['blockName'] ?? '' ) ) {
			return (string) $content;
		}
		$attrs = $block['attrs'] ?? array();
		$area  = (string) ( $attrs['area'] ?? '' );
		$slug  = (string) ( $attrs['slug'] ?? '' );
		if ( '' === $area && '' !== $slug ) {
			$part = get_block_template( ( $attrs['theme'] ?? get_stylesheet() ) . '//' . $slug, 'wp_template_part' );
			$area = $part ? (string) $part->area : '';
			if ( 'uncategorized' === $area || '' === $area ) {
				$area = false !== strpos( $slug, 'header' ) ? 'header' : ( false !== strpos( $slug, 'footer' ) ? 'footer' : '' );
			}
		}
		if ( ! in_array( $area, array( 'header', 'footer' ), true ) ) {
			return (string) $content;
		}
		$id = $this->resolve( $area );
		if ( ! $id ) {
			return (string) $content;
		}
		$args = array( 'class' => 'uncoder-location uncoder-location--' . $area, 'tag' => $area );
		return 'header' === $area ? Header_Behavior::render( $this, $id, $args ) : $this->render_template( $id, $args );
	}

	public function enqueue(): void {
		$ids = array( $this->resolve( 'header' ), $this->resolve( 'footer' ), $this->resolve_body()[0] );
		foreach ( array_filter( $ids ) as $id ) {
			$doc = Plugin::instance()->documents()->get( $id );
			if ( $doc ) {
				Assets::enqueue_document( $doc );
			}
		}
	}

	/**
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		if ( $this->resolve( 'header' ) ) {
			$classes[] = 'uncoder-has-header';
		}
		if ( $this->resolve( 'footer' ) ) {
			$classes[] = 'uncoder-has-footer';
		}
		return Header_Behavior::body_class( $classes, $this->resolve( 'header' ) );
	}

	/**
	 * Which templates apply to a sample of site locations (for the admin and AI clients).
	 *
	 * @return array<string, array<string, array{id:int,title:string}>>
	 */
	public function overview(): array {
		$out = array();
		foreach ( $this->index() as $type => $entries ) {
			foreach ( $entries as $entry ) {
				$out[ $type ][] = array(
					'id'         => (int) $entry['id'],
					'title'      => get_the_title( (int) $entry['id'] ),
					'conditions' => $entry['conditions'],
					'summary'    => Conditions::summary( (array) $entry['conditions'] ),
				);
			}
		}
		return $out;
	}
}
