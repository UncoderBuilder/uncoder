<?php
/**
 * Canvas preview mode (the page loaded inside the editor iframe).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Editor;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the real front-end page with an empty mount point that the editor fills.
 */
final class Preview {

	public const QUERY_VAR = 'uncoder_preview';
	public const NONCE_VAR = 'uncoder_nonce';

	private ?bool $active = null;

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'setup' ), 1 );
	}

	public static function url( int $post_id ): string {
		$post = get_post( $post_id );
		$args = array(
			self::QUERY_VAR => $post_id,
			self::NONCE_VAR => wp_create_nonce( 'uncoder_preview_' . $post_id ),
			'preview'       => 'true',
		);
		if ( $post && 'page' === $post->post_type ) {
			$args['page_id'] = $post_id;
		} else {
			$args['p']         = $post_id;
			$args['post_type'] = $post ? $post->post_type : 'post';
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	public function active(): bool {
		if ( null !== $this->active ) {
			return $this->active;
		}
		$this->active = false;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is verified right here.
		if ( empty( $_GET[ self::QUERY_VAR ] ) || empty( $_GET[ self::NONCE_VAR ] ) ) {
			return false;
		}
		$post_id = absint( $_GET[ self::QUERY_VAR ] );
		$nonce   = sanitize_text_field( wp_unslash( $_GET[ self::NONCE_VAR ] ) );
		// phpcs:enable
		if ( $post_id && wp_verify_nonce( $nonce, 'uncoder_preview_' . $post_id ) && current_user_can( 'edit_post', $post_id ) ) {
			$this->active = true;
		}
		return $this->active;
	}

	public function post_id(): int {
		return $this->active() ? absint( $_GET[ self::QUERY_VAR ] ?? 0 ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public function setup(): void {
		if ( ! $this->active() ) {
			return;
		}
		$post_id = $this->post_id();
		nocache_headers();
		show_admin_bar( false );
		add_filter( 'show_admin_bar', '__return_false' );

		add_filter(
			'uncoder_wb/preview/mount',
			static function ( $html, $id ) {
				$doc  = \Uncoder\Builder\Plugin::instance()->documents()->get( (int) $id );
				$type = $doc ? sanitize_html_class( $doc->type() ) : 'page';
				return '<div id="uncoder-canvas-root" class="uncoder uncoder-' . (int) $id . ' uncoder--' . $type . ' uncoder-canvas-root" data-uncoder-doc="' . (int) $id . '"></div>';
			},
			10,
			2
		);
		add_filter(
			'body_class',
			static function ( $classes ) {
				$classes[] = 'uncoder-editing';
				return $classes;
			}
		);
		// The edited template must not be applied around itself by the theme builder.
		add_filter( 'uncoder_wb/theme/exclude_template', static fn( $ids ) => array_merge( (array) $ids, array( $post_id ) ) );

		add_action(
			'wp_enqueue_scripts',
			static function () {
				Assets::enqueue_all();
				wp_enqueue_style( 'uncoder-canvas', Assets::url( 'editor/canvas.css' ), array( 'uncoder-frontend' ), Assets::ver( 'editor/canvas.css' ) );
			},
			30
		);

		// Templates always render on the blank canvas.
		if ( Post_Types::TEMPLATE === get_post_type( $post_id ) ) {
			add_filter( 'template_include', static fn() => UNCODER_WB_PATH . 'templates/canvas.php', 999 );
		}
	}
}
