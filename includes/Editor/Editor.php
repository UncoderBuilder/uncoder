<?php
/**
 * Full-screen editor bootstrap.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Editor;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Brand;
use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Opens at wp-admin/post.php?post={id}&action=uncoder.
 */
final class Editor {

	public const ACTION = 'uncoder';

	/** admin-post action that hands a post back to the WordPress editor. */
	public const WP_EDITOR_ACTION = 'uncoder_wp_editor';

	public function register(): void {
		add_action( 'admin_action_' . self::ACTION, array( $this, 'load' ) );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 80 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_button' ) );
		add_action( 'edit_form_after_title', array( $this, 'classic_editor' ) );
		add_action( 'admin_post_' . self::WP_EDITOR_ACTION, array( $this, 'use_wp_editor' ) );
		add_filter( 'display_post_states', array( $this, 'post_state' ), 10, 2 );
	}

	/**
	 * Editor link. It carries a nonce because opening a classic post converts it to the builder.
	 */
	public static function url( int $post_id ): string {
		return add_query_arg(
			array(
				'post'     => $post_id,
				'action'   => self::ACTION,
				'_wpnonce' => wp_create_nonce( 'uncoder_edit_' . $post_id ),
			),
			admin_url( 'post.php' )
		);
	}

	private function can_edit( int $post_id ): bool {
		return $post_id > 0
			&& current_user_can( 'edit_post', $post_id )
			&& Plugin::instance()->documents()->is_supported( $post_id )
			&& \Uncoder\Builder\Site\Role_Manager::can_use();
	}

	/**
	 * @param array<string,string> $actions Actions.
	 */
	public function row_action( array $actions, \WP_Post $post ): array {
		if ( $this->can_edit( $post->ID ) && 'trash' !== $post->post_status ) {
			$actions['uncoder'] = '<a href="' . esc_url( self::url( $post->ID ) ) . '">' . Brand::mark( 12, 'vertical-align:-1px;margin-right:4px' ) . esc_html__( 'Edit with Uncoder', 'uncoder' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * @param array<string,string> $states States.
	 */
	public function post_state( array $states, \WP_Post $post ): array {
		if ( Utils::is_builder_post( $post->ID ) ) {
			$states['uncoder'] = __( 'Uncoder', 'uncoder' );
		}
		return $states;
	}

	public function admin_bar( \WP_Admin_Bar $bar ): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( ! $this->can_edit( $id ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'uncoder-edit',
				'title' => Brand::mark( 15, 'vertical-align:-3px;margin-right:6px' ) . esc_html__( 'Edit with Uncoder', 'uncoder' ),
				'href'  => self::url( $id ),
			)
		);
	}

	public function block_editor_button(): void {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $post_id && 'post-new.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
			// A new post (e.g. a translation Polylang just created): the auto-draft is the global post.
			$post_id = (int) get_the_ID();
		}
		if ( ! $post_id || ! $this->can_edit( $post_id ) ) {
			return;
		}
		$this->enqueue_post_editor( $post_id );
	}

	/**
	 * Button, built-with panel and styles for WordPress's post editors (assets/build/wp/post-editor.*).
	 */
	private function enqueue_post_editor( int $post_id ): void {
		$base = UNCODER_WB_URL . 'assets/build/wp/';
		wp_enqueue_style( 'uncoder-post-editor', $base . 'post-editor.css', array(), UNCODER_WB_VERSION );
		wp_enqueue_script( 'uncoder-post-editor', $base . 'post-editor.js', array(), UNCODER_WB_VERSION, true );
		$type  = get_post_type_object( (string) get_post_type( $post_id ) );
		$noun  = $type ? strtolower( (string) $type->labels->singular_name ) : __( 'page', 'uncoder' );
		wp_add_inline_script(
			'uncoder-post-editor',
			'window.UncoderPostEditor = ' . wp_json_encode(
				array(
					'editUrl'   => self::url( $post_id ),
					'switchUrl' => self::wp_editor_url( $post_id ),
					'builder'   => Utils::is_builder_post( $post_id ),
					// Not saved yet: the button saves first (a translation is linked to its original on save).
					'isNew'     => 'auto-draft' === get_post_status( $post_id ),
					'i18n'      => $this->panel_text( $noun ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * @return array<string,string>
	 */
	private function panel_text( string $noun ): array {
		return array(
			'edit'    => __( 'Edit with Uncoder', 'uncoder' ),
			/* translators: %s: post type name, e.g. "page". */
			'title'   => sprintf( __( 'This %s is built with Uncoder', 'uncoder' ), $noun ),
			'text'    => __( 'Its design, content and layout are edited in Uncoder. Title, status, permalink and other settings still work here.', 'uncoder' ),
			'switch'  => __( 'Switch back to the WordPress editor', 'uncoder' ),
			/* translators: %s: post type name, e.g. "page". */
			'confirm' => sprintf( __( 'Show the WordPress editor content on this %s instead of the Uncoder design? The design is kept: choose Edit with Uncoder to bring it back (it then replaces what you wrote in the WordPress editor).', 'uncoder' ), $noun ),
		);
	}

	/**
	 * Link that hands a post back to the WordPress editor (nonce protected: it changes the live page).
	 */
	public static function wp_editor_url( int $post_id ): string {
		return add_query_arg(
			array(
				'action'   => self::WP_EDITOR_ACTION,
				'post'     => $post_id,
				'_wpnonce' => wp_create_nonce( 'uncoder_wp_editor_' . $post_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Classic editor: the built-with panel instead of the content box, or the button for other posts.
	 */
	public function classic_editor( \WP_Post $post ): void {
		if ( ! $this->can_edit( $post->ID ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		$this->enqueue_post_editor( $post->ID );
		if ( ! Utils::is_builder_post( $post->ID ) ) {
			echo '<p class="uncoder-pe-classic-bar"><a class="uncoder-pe-btn" href="' . esc_url( self::url( $post->ID ) ) . '">' . Brand::mark( 14 ) . '<span>' . esc_html__( 'Edit with Uncoder', 'uncoder' ) . '</span></a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup.
			return;
		}
		$type = get_post_type_object( $post->post_type );
		$text = $this->panel_text( $type ? strtolower( (string) $type->labels->singular_name ) : __( 'page', 'uncoder' ) );
		// The content box is hidden by this class (post-editor.css); added right away so it never flashes.
		wp_print_inline_script_tag( 'document.body.classList.add("uncoder-pe-built");' );
		echo '<div class="uncoder-pe-classic"><div class="uncoder-pe-takeover"><div class="uncoder-pe-card" role="region" aria-label="' . esc_attr( $text['title'] ) . '">'
			. '<span class="uncoder-pe-tile">' . Brand::app_icon( 64 ) . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup.
			. '<h2>' . esc_html( $text['title'] ) . '</h2><p>' . esc_html( $text['text'] ) . '</p>'
			. '<div class="uncoder-pe-actions"><a class="uncoder-pe-btn uncoder-pe-btn--lg" href="' . esc_url( self::url( $post->ID ) ) . '">' . Brand::mark( 18 ) . '<span>' . esc_html( $text['edit'] ) . '</span></a></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup.
			. '<a class="uncoder-pe-switch" href="' . esc_url( self::wp_editor_url( $post->ID ) ) . '" onclick="return confirm(' . esc_attr( (string) wp_json_encode( $text['confirm'] ) ) . ')">' . esc_html( $text['switch'] ) . '</a>'
			. '</div></div></div>';
	}

	/**
	 * admin-post.php?action=uncoder_wp_editor: the post renders its WordPress editor content again.
	 * The Uncoder design stays stored; Edit with Uncoder restores it.
	 */
	public function use_wp_editor(): void {
		$post_id = absint( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
		check_admin_referer( 'uncoder_wp_editor_' . $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this item.', 'uncoder' ), 403 );
		}
		update_post_meta( $post_id, Utils::META_MODE, 'wp' );
		clean_post_cache( $post_id );
		wp_safe_redirect( (string) get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}

	/**
	 * Handles post.php?action=uncoder.
	 */
	public function load(): void {
		$post_id = absint( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- capability-checked navigation, no state change without the REST nonce.
		if ( ! $this->can_edit( $post_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this item with Uncoder.', 'uncoder' ), 403 );
		}
		$post = get_post( $post_id );
		if ( ! $post || 'trash' === $post->post_status ) {
			wp_die( esc_html__( 'This item cannot be edited.', 'uncoder' ), 404 );
		}

		$doc = Plugin::instance()->documents()->get( $post_id );
		if ( ! $doc->is_builder() ) {
			// Converting changes the live page: require the nonce from our own links (no CSRF by link).
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'uncoder_edit_' . $post_id ) ) {
				wp_die(
					'<h1>' . esc_html__( 'Edit with Uncoder?', 'uncoder' ) . '</h1><p>' . esc_html(
						sprintf(
							/* translators: %s: post title. */
							__( '"%s" is not built with Uncoder yet. Opening it in Uncoder converts its current content into editable elements.', 'uncoder' ),
							get_the_title( $post )
						)
					) . '</p><p><a class="button button-primary" href="' . esc_url( self::url( $post_id ) ) . '">' . esc_html__( 'Edit with Uncoder', 'uncoder' ) . '</a> <a class="button" href="' . esc_url( (string) get_edit_post_link( $post_id, 'raw' ) ) . '">' . esc_html__( 'Cancel', 'uncoder' ) . '</a></p>',
					esc_html__( 'Edit with Uncoder', 'uncoder' ),
					array( 'response' => 200 )
				);
			}
			$this->convert_to_builder( $post );
		}

		if ( 'auto-draft' === $post->post_status ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
						'post_title'  => '' !== $post->post_title ? $post->post_title : __( 'Untitled', 'uncoder' ),
					)
				)
			);
			$post = get_post( $post_id );
		}

		wp_set_post_lock( $post_id );
		$this->render_page( $post );
		exit;
	}

	/**
	 * Starts the builder document from existing post content, if any.
	 */
	private function convert_to_builder( \WP_Post $post ): void {
		$doc = Plugin::instance()->documents()->get( $post->ID );
		if ( 'wp' === get_post_meta( $post->ID, Utils::META_MODE, true ) && $doc->elements() ) {
			$doc->save( $doc->elements() );
			return;
		}
		$content  = trim( (string) $post->post_content );
		$elements = array();
		if ( '' !== $content && Post_Types::TEMPLATE !== $post->post_type ) {
			$html       = apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
			$elements[] = array(
				'type'     => 'container',
				'children' => array(
					array(
						'type'     => 'text-editor',
						'settings' => array( 'content' => wp_kses_post( (string) $html ) ),
					),
				),
			);
		}
		Plugin::instance()->documents()->get( $post->ID )->save( $elements );

		// New builder pages default to the Full Width template (the theme's header/footer, no content box).
		$template = get_page_template_slug( $post );
		if ( 'page' === $post->post_type && ( '' === $template || 'default' === $template ) ) {
			/**
			 * Filters the page template assigned when a page is first opened with Uncoder.
			 *
			 * @param string   $template Template slug ('' keeps the theme default).
			 * @param \WP_Post $post     Post.
			 */
			$default = (string) apply_filters( 'uncoder_wb/default_page_template', 'uncoder-full-width', $post );
			if ( '' !== $default ) {
				update_post_meta( $post->ID, '_wp_page_template', $default );
			}
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function config( \WP_Post $post ): array {
		$plugin = Plugin::instance();
		$doc    = $plugin->documents()->get( $post->ID );
		$user   = wp_get_current_user();
		$type   = get_post_type_object( $post->post_type );

		return array(
			'version'     => UNCODER_WB_VERSION,
			'post'        => array(
				'id'           => $post->ID,
				'title'        => $post->post_title, // Raw: the editable title (get_the_title() is texturized and entity-encoded).
				'status'       => $post->post_status,
				'type'         => $post->post_type,
				'typeLabel'    => $type ? $type->labels->singular_name : $post->post_type,
				'docType'      => $doc->type(),
				'permalink'    => get_permalink( $post ),
				'previewUrl'   => Preview::url( $post->ID ),
				'draftNonce'   => wp_create_nonce( Draft_Preview::NONCE . $post->ID ),
				'exitUrl'      => Post_Types::TEMPLATE === $post->post_type ? admin_url( 'admin.php?page=uncoder-templates#' . $doc->type() ) : get_edit_post_link( $post->ID, 'raw' ),
				'modified'     => get_post_modified_time( 'U', true, $post ),
				'pageTemplate' => get_page_template_slug( $post ),
				'rev'          => $doc->rev()['id'],
			),
			'kitVersion'  => $plugin->kit()->version(),
			'elements'    => $doc->elements(),
			'pageSettings' => (object) $doc->page_settings(),
			'schema'      => array(
				'elements'    => $plugin->elements()->schema(),
				'categories'  => $plugin->elements()->categories(),
				'dynamicTags' => $plugin->tags()->schema(),
				'tagGroups'   => \Uncoder\Builder\Dynamic\Tags::GROUPS,
				'templateTypes' => Post_Types::TEMPLATE_TYPES,
				// Turned off in Settings → Elements: left out of the Insert panel (existing uses still render).
				'disabled'    => \Uncoder\Builder\Rest\Prefs_Controller::disabled_widgets(),
			),
			'prefs'       => \Uncoder\Builder\Rest\Prefs_Controller::get(),
			'kit'         => $plugin->kit()->export(),
			'breakpoints' => Breakpoints::export(),
			'customFonts' => array_merge( \Uncoder\Builder\Site\Custom_Fonts::catalog(), \Uncoder\Builder\Site\Adobe_Fonts::catalog() ),
			'iconSets'    => \Uncoder\Builder\Site\Custom_Icons::index(),
			'rest'        => array(
				'root'  => esc_url_raw( rest_url( 'uncoder/v1/' ) ),
				'wp'    => esc_url_raw( rest_url( 'wp/v2/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			),
			'urls'        => array(
				'admin'   => admin_url(),
				'assets'  => UNCODER_WB_URL . 'assets/',
				'icons'   => self::data_url( 'lucide.json' ),
				'fonts'   => self::data_url( 'google-fonts.json' ),
				'site'    => home_url( '/' ),
				'mcp'     => admin_url( 'admin.php?page=uncoder-ai' ),
				'styleBook' => \Uncoder\Builder\Site\Style_Book::url(),
			),
			'styleBookNonce' => wp_create_nonce( \Uncoder\Builder\Site\Style_Book::NONCE ),
			'safeMode'    => defined( 'UNCODER_SAFE_MODE' ) && UNCODER_SAFE_MODE,
			'user'        => array(
				'id'     => $user->ID,
				'name'   => $user->display_name,
				'avatar' => get_avatar_url( $user->ID, array( 'size' => 64 ) ),
				'caps'   => array(
					'unfiltered_html' => current_user_can( 'unfiltered_html' ),
					'publish'         => current_user_can( $type ? $type->cap->publish_posts : 'publish_posts' ),
					'manage_options'  => current_user_can( 'manage_options' ),
					'upload_files'    => current_user_can( 'upload_files' ),
					'edit_theme'      => current_user_can( 'edit_theme_options' ),
				),
				'contentOnly' => \Uncoder\Builder\Site\Role_Manager::content_only(),
			),
			'site'        => array(
				'name' => get_bloginfo( 'name' ),
				'lang' => get_bloginfo( 'language' ),
			),
			'ai'          => array(
				'enabled' => \Uncoder\Builder\Site\Ai_Writer::ready(),
				'images'  => \Uncoder\Builder\Site\Ai_Images::ready() && current_user_can( 'upload_files' ),
			),
		);
	}

	/**
	 * URL of a bundled data file with a cache-busting version.
	 */
	public static function data_url( string $file ): string {
		$path = UNCODER_WB_PATH . 'assets/data/' . $file;
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : UNCODER_WB_VERSION;
		return UNCODER_WB_URL . 'assets/data/' . $file . '?ver=' . $ver;
	}

	private function render_page( \WP_Post $post ): void {
		Assets::register_handles();
		wp_enqueue_media( array( 'post' => $post->ID ) );
		wp_enqueue_style( 'uncoder-editor', Assets::url( 'editor/editor.css' ), array(), Assets::ver( 'editor/editor.css' ) );
		// Adobe Fonts previews in the font picker.
		\Uncoder\Builder\Site\Adobe_Fonts::enqueue_all();
		wp_enqueue_script( 'uncoder-editor', Assets::url( 'editor/editor.js' ), array( 'media-editor' ), Assets::ver( 'editor/editor.js' ), true );
		wp_add_inline_script( 'uncoder-editor', 'window.UncoderEditor=' . wp_json_encode( self::config( $post ) ) . ';', 'before' );

		$title = sprintf(
			/* translators: %s: post title. */
			__( 'Uncoder | %s', 'uncoder' ),
			get_the_title( $post )
		);
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?><!doctype html>
<html <?php language_attributes(); ?> class="uncoder-ui-editor-html">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?></title>
	<link rel="icon" href="<?php echo esc_url( \Uncoder\Builder\Core\Brand::asset( 'uncoder-favicon.svg' ) ); ?>" type="image/svg+xml">
	<link rel="alternate icon" href="<?php echo esc_url( \Uncoder\Builder\Core\Brand::asset( 'favicon-32.png' ) ); ?>" type="image/png">
	<?php
	wp_print_styles( array( 'uncoder-editor', 'media-views', 'imgareaselect' ) );
	wp_print_head_scripts();
	?>
</head>
<body class="uncoder-ui-editor-body">
	<div id="uncoder-ui-root">
		<div class="uncoder-ui-boot"><?php echo \Uncoder\Builder\Core\Brand::loader( __( 'Loading Uncoder…', 'uncoder' ), 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup, label escaped inside. ?></div>
	</div>
	<?php
	wp_print_media_templates();
	wp_print_footer_scripts();
	?>
</body>
</html>
		<?php
	}
}
