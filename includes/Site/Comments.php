<?php
/**
 * Comments control (Settings → Forms → Comments). Spam bots post straight to wp-comments-post.php, which WordPress
 * accepts whenever a post's comments are open, even when its page shows no comment form at all.
 *
 *   uncoder_wb_settings['comments'] = 'wp' (WordPress decides, the default) | 'shown' | 'off'
 *
 * "shown": a post takes comments only when its page has a comments section: the Uncoder template that displays it,
 * or its own Uncoder content, holds a Post Comments widget. Posts the theme displays on its own (no Uncoder template,
 * no Uncoder page template) keep WordPress's behaviour, since themes print their own comments section.
 * "off": no new comments anywhere. Both close pingbacks and trackbacks (XML-RPC pingbacks and the X-Pingback header
 * too). WooCommerce product reviews are left alone: WooCommerce has its own review settings.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Tree;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Theme\Theme_Builder;

defined( 'ABSPATH' ) || exit;

final class Comments {

	public const MODES = array( 'wp', 'shown', 'off' );

	/** @var array<int,bool> Whether a post's page has a comments section, per post id (this request). */
	private array $shown = array();

	public static function mode(): string {
		$settings = get_option( 'uncoder_wb_settings', array() );
		return self::sanitize( is_array( $settings ) ? ( $settings['comments'] ?? 'wp' ) : 'wp' );
	}

	/**
	 * @param mixed $value Raw value.
	 */
	public static function sanitize( $value ): string {
		$value = sanitize_key( (string) $value );
		return in_array( $value, self::MODES, true ) ? $value : 'wp';
	}

	public function register(): void {
		if ( 'wp' === self::mode() ) {
			return;
		}
		add_filter( 'comments_open', array( $this, 'comments_open' ), 20, 2 );
		add_filter( 'pings_open', array( $this, 'pings_open' ), 20, 2 );
		add_filter( 'xmlrpc_methods', array( $this, 'xmlrpc_methods' ) );
		add_filter( 'wp_headers', array( $this, 'headers' ) );
	}

	/**
	 * @param bool        $open    Whether comments are open (the post's own setting).
	 * @param int|WP_Post $post_id Post.
	 */
	public function comments_open( $open, $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $open || ! $post ) {
			return (bool) $open;
		}
		if ( 'product' === $post->post_type ) {
			return true;
		}
		if ( 'off' === self::mode() ) {
			return false;
		}
		return $this->has_comments_section( (int) $post->ID );
	}

	/**
	 * @param bool        $open    Whether pings are open.
	 * @param int|WP_Post $post_id Post.
	 */
	public function pings_open( $open, $post_id ): bool {
		return false;
	}

	/**
	 * @param array<string,mixed> $methods XML-RPC methods.
	 * @return array<string,mixed>
	 */
	public function xmlrpc_methods( $methods ): array {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return (array) $methods;
	}

	/**
	 * @param array<string,string> $headers Response headers.
	 * @return array<string,string>
	 */
	public function headers( $headers ): array {
		unset( $headers['X-Pingback'] );
		return (array) $headers;
	}

	/**
	 * Whether the page that shows a post has a comments section (see the class comment).
	 */
	public function has_comments_section( int $post_id ): bool {
		if ( isset( $this->shown[ $post_id ] ) ) {
			return $this->shown[ $post_id ];
		}
		$docs     = Plugin::instance()->documents();
		$builder  = Theme_Builder::instance();
		$template = $builder ? $builder->body_for_post( $post_id )[0] : 0;
		$own      = $docs->get( $post_id );
		$own      = $own && $own->is_builder() ? $own->elements() : array();
		if ( $template ) {
			$doc   = $docs->get( $template );
			$shown = self::holds_comments( $doc ? $doc->elements() : array() ) || self::holds_comments( $own );
		} elseif ( in_array( get_page_template_slug( $post_id ), array( 'uncoder-canvas', 'uncoder-full-width' ), true ) ) {
			// Uncoder's page templates print the post's content only, no comments section of the theme's.
			$shown = self::holds_comments( $own );
		} else {
			$shown = true;
		}
		$this->shown[ $post_id ] = $shown;
		return $shown;
	}

	/**
	 * Whether an element tree holds a Post Comments widget, embedded templates included (two levels deep).
	 *
	 * @param array<int, array<string,mixed>> $elements Tree.
	 */
	private static function holds_comments( array $elements, int $depth = 0 ): bool {
		if ( in_array( 'post-comments', Tree::types( $elements ), true ) ) {
			return true;
		}
		if ( $depth > 1 ) {
			return false;
		}
		$embedded = array();
		Tree::walk(
			$elements,
			static function ( $node ) use ( &$embedded ) {
				if ( 'template' === ( $node['type'] ?? '' ) && ! empty( $node['settings']['template_id'] ) ) {
					$embedded[] = absint( $node['settings']['template_id'] );
				}
			}
		);
		foreach ( array_unique( $embedded ) as $id ) {
			$doc = Plugin::instance()->documents()->get( $id );
			if ( $doc && self::holds_comments( $doc->elements(), $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}
}
