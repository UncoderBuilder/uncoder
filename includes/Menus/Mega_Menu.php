<?php
/**
 * Mega menus: a "mega-menu" template shown as the dropdown of a top-level menu item.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Menus;

use Uncoder\Builder\Core\Assets;
use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Stores `_uncoder_wb_mega` = {template, width} on nav_menu_item posts and feeds the Nav Menu widget
 * walker through the `uncoder_wb/nav_menu/item_content` filter.
 */
final class Mega_Menu {

	public const META   = '_uncoder_wb_mega';
	public const OPTION = 'uncoder_wb_mega_templates';
	public const WIDTHS = array( 'container', 'full', 'auto' );

	/** @var array<int,bool> Templates being rendered (recursion guard). */
	private array $rendering = array();

	public function register(): void {
		add_filter( 'uncoder_wb/nav_menu/item_content', array( $this, 'item_content' ), 10, 4 );
		add_action( 'uncoder_wb/frontend/enqueue', array( $this, 'enqueue' ) );
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'admin_field' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'admin_save' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
		add_action( 'after_setup_theme', array( $this, 'menus_screen' ), 20 );
	}

	/**
	 * Block themes hide Appearance → Menus, where the Nav Menu widget's menus and the mega menu fields
	 * live: bring the classic menus screen back for them.
	 */
	public function menus_screen(): void {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() && ! current_theme_supports( 'menus' ) ) {
			add_theme_support( 'menus' );
		}
	}

	/**
	 * @return array{template:int, width:string}|null
	 */
	public static function get( int $item_id ): ?array {
		$raw = get_post_meta( $item_id, self::META, true );
		if ( ! is_array( $raw ) || empty( $raw['template'] ) ) {
			return null;
		}
		return array(
			'template' => (int) $raw['template'],
			'width'    => in_array( $raw['width'] ?? '', self::WIDTHS, true ) ? $raw['width'] : 'container',
		);
	}

	public static function set( int $item_id, int $template_id, string $width = 'container' ): void {
		if ( $template_id ) {
			update_post_meta(
				$item_id,
				self::META,
				array(
					'template' => $template_id,
					'width'    => in_array( $width, self::WIDTHS, true ) ? $width : 'container',
				)
			);
		} else {
			delete_post_meta( $item_id, self::META );
		}
		self::rebuild_index();
	}

	/**
	 * Keeps the list of templates used as mega menus, so their CSS can load in <head>.
	 *
	 * @return int[]
	 */
	public static function rebuild_index(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META ) );
		$ids  = array();
		foreach ( (array) $rows as $row ) {
			$value = maybe_unserialize( $row );
			if ( is_array( $value ) && ! empty( $value['template'] ) ) {
				$ids[] = (int) $value['template'];
			}
		}
		$ids = array_values( array_unique( $ids ) );
		update_option( self::OPTION, $ids, true );
		return $ids;
	}

	/**
	 * @param string              $content  Content so far.
	 * @param object              $item     Menu item.
	 * @param int                 $depth    Depth.
	 * @param array<string,mixed> $settings Widget settings.
	 */
	public function item_content( $content, $item, $depth, $settings ): string {
		if ( '' !== (string) $content || (int) $depth > 0 || empty( $item->ID ) ) {
			return (string) $content;
		}
		$mega = self::get( (int) $item->ID );
		if ( ! $mega || isset( $this->rendering[ $mega['template'] ] ) ) {
			return '';
		}
		$template = get_post( \Uncoder\Builder\Site\Multilingual::translate_id( (int) $mega['template'] ) );
		if ( ! $template || Post_Types::TEMPLATE !== $template->post_type || 'mega-menu' !== get_post_meta( $template->ID, Utils::META_TYPE, true ) ) {
			return '';
		}
		$editing = defined( 'REST_REQUEST' ) && REST_REQUEST;
		if ( 'publish' !== $template->post_status && ! ( $editing && current_user_can( 'edit_post', $template->ID ) ) ) {
			return '';
		}
		$doc = Plugin::instance()->documents()->get( $template->ID );
		if ( ! $doc ) {
			return '';
		}
		$this->rendering[ $template->ID ] = true;
		Assets::enqueue_late( $doc );
		Assets::enqueue_widget_style( 'mega-menu' );
		Assets::enqueue_module( 'mega-menu' );
		$html = $doc->render();
		unset( $this->rendering[ $template->ID ] );
		if ( '' === trim( $html ) ) {
			return '';
		}
		return '<div class="uncoder-mega uncoder-mega--' . esc_attr( $mega['width'] ) . '" data-uncoder-js="mega-menu">' . $html . '</div>';
	}

	public function enqueue(): void {
		$ids = get_option( self::OPTION );
		if ( ! is_array( $ids ) || ! $ids ) {
			return;
		}
		foreach ( array_slice( $ids, 0, 12 ) as $id ) {
			$id = \Uncoder\Builder\Site\Multilingual::translate_id( (int) $id ); // The visitor's language version.
			if ( 'publish' === get_post_status( (int) $id ) ) {
				$doc = Plugin::instance()->documents()->get( (int) $id );
				if ( $doc ) {
					Assets::enqueue_document( $doc );
				}
			}
		}
		Assets::enqueue_widget_style( 'mega-menu' );
		Assets::enqueue_module( 'mega-menu' );
	}

	public function on_delete( int $post_id ): void {
		$type = get_post_type( $post_id );
		if ( 'nav_menu_item' === $type || Post_Types::TEMPLATE === $type ) {
			add_action( 'deleted_post', array( __CLASS__, 'rebuild_index' ) );
		}
	}

	/* ---------------------------------------------------------------- Appearance → Menus */

	/**
	 * @param int      $item_id Menu item id.
	 * @param \WP_Post $item    Item.
	 */
	public function admin_field( $item_id, $item ): void {
		if ( ! empty( $item->menu_item_parent ) ) {
			return;
		}
		$templates = get_posts(
			array(
				'post_type'      => Post_Types::TEMPLATE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				'meta_key'       => Utils::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'mega-menu', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$current = self::get( (int) $item_id );
		wp_nonce_field( 'uncoder_mega_' . $item_id, 'uncoder_mega_nonce[' . $item_id . ']' );
		?>
		<p class="field-uncoder-mega description description-wide">
			<label for="uncoder-mega-<?php echo esc_attr( (string) $item_id ); ?>">
				<?php esc_html_e( 'Uncoder mega menu', 'uncoder' ); ?><br />
				<select id="uncoder-mega-<?php echo esc_attr( (string) $item_id ); ?>" name="uncoder_mega[<?php echo esc_attr( (string) $item_id ); ?>]" class="widefat">
					<option value="0"><?php esc_html_e( '— None (regular dropdown) —', 'uncoder' ); ?></option>
					<?php foreach ( $templates as $template ) : ?>
						<option value="<?php echo esc_attr( (string) $template->ID ); ?>" <?php selected( $current['template'] ?? 0, $template->ID ); ?>><?php echo esc_html( get_the_title( $template ) . ( 'publish' !== $template->post_status ? ' (' . $template->post_status . ')' : '' ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label for="uncoder-mega-width-<?php echo esc_attr( (string) $item_id ); ?>">
				<?php esc_html_e( 'Panel width', 'uncoder' ); ?><br />
				<select id="uncoder-mega-width-<?php echo esc_attr( (string) $item_id ); ?>" name="uncoder_mega_width[<?php echo esc_attr( (string) $item_id ); ?>]">
					<option value="container" <?php selected( $current['width'] ?? 'container', 'container' ); ?>><?php esc_html_e( 'Site container', 'uncoder' ); ?></option>
					<option value="full" <?php selected( $current['width'] ?? '', 'full' ); ?>><?php esc_html_e( 'Full width', 'uncoder' ); ?></option>
					<option value="auto" <?php selected( $current['width'] ?? '', 'auto' ); ?>><?php esc_html_e( 'Fit content', 'uncoder' ); ?></option>
				</select>
			</label>
		</p>
		<?php
	}

	/**
	 * @param int $menu_id Menu id.
	 * @param int $item_id Item id.
	 */
	public function admin_save( $menu_id, $item_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below.
		if ( ! isset( $_POST['uncoder_mega'][ $item_id ], $_POST['uncoder_mega_nonce'][ $item_id ] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_theme_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['uncoder_mega_nonce'][ $item_id ] ) ), 'uncoder_mega_' . $item_id ) ) {
			return;
		}
		$template = absint( $_POST['uncoder_mega'][ $item_id ] );
		$width    = isset( $_POST['uncoder_mega_width'][ $item_id ] ) ? sanitize_key( wp_unslash( $_POST['uncoder_mega_width'][ $item_id ] ) ) : 'container';
		// phpcs:enable
		if ( $template && ( Post_Types::TEMPLATE !== get_post_type( $template ) || 'mega-menu' !== get_post_meta( $template, Utils::META_TYPE, true ) ) ) {
			$template = 0;
		}
		self::set( (int) $item_id, $template, $width );
	}
}
