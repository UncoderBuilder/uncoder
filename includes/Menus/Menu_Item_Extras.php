<?php
/**
 * Menu item icons and descriptions for the Nav Menu widget.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Menus;

use Uncoder\Builder\Core\Icons;
use Uncoder\Builder\Editor\Editor;

defined( 'ABSPATH' ) || exit;

/**
 * An Icon field on every item in Appearance → Menus (stored in `_uncoder_wb_icon` as "users" or
 * "library:name"), and WordPress's own Description field, which the Nav Menu widget shows under the label
 * in dropdowns and the mobile menu. WordPress hides that field until it is turned on in Screen Options; new
 * users get it on, others get a button next to the icon field.
 */
final class Menu_Item_Extras {

	public const ICON = '_uncoder_wb_icon';

	/** The custom icon set that icons uploaded from the menus screen go into. */
	public const UPLOAD_SET = 'custom-menu-icons';

	public function register(): void {
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'admin_field' ), 5, 2 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'admin_save' ), 10, 2 );
		add_action( 'load-nav-menus.php', array( $this, 'show_descriptions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * The item's icon ("users", "phosphor:house"…), or ''.
	 */
	public static function icon( int $item_id ): string {
		$value = get_post_meta( $item_id, self::ICON, true );
		return is_string( $value ) ? $value : '';
	}

	public static function set_icon( int $item_id, string $icon ): void {
		$icon = self::clean( $icon );
		if ( '' === $icon ) {
			delete_post_meta( $item_id, self::ICON );
		} else {
			update_post_meta( $item_id, self::ICON, $icon );
		}
	}

	/**
	 * A known icon as stored ("lucide:users" → "users", "phosphor:house"), or '' for anything else.
	 */
	public static function clean( string $icon ): string {
		$icon = strtolower( trim( $icon ) );
		if ( '' === $icon ) {
			return '';
		}
		list( $library, $name ) = Icons::parse( $icon ) ?? array( 'lucide', $icon );
		if ( ! Icons::exists( $name, $library ) ) {
			return '';
		}
		return 'lucide' === $library ? $name : $library . ':' . $name;
	}

	/**
	 * The description typed on the menu item (WordPress keeps it as the item's description).
	 *
	 * @param object $item Menu item.
	 */
	public static function description( $item ): string {
		return trim( wp_strip_all_tags( (string) ( $item->description ?? '' ) ) );
	}

	/**
	 * WordPress hides the Description field of menu items for new users (it writes that preference on the
	 * first visit to Appearance → Menus). Write it first, without hiding descriptions.
	 */
	public function show_descriptions(): void {
		if ( false === get_user_option( 'managenav-menuscolumnshidden' ) ) {
			update_user_meta( get_current_user_id(), 'managenav-menuscolumnshidden', array( 'link-target', 'css-classes', 'xfn', 'title-attribute' ) );
		}
	}

	public function enqueue( string $hook ): void {
		if ( 'nav-menus.php' !== $hook ) {
			return;
		}
		$base = UNCODER_WB_URL . 'assets/build/wp/';
		wp_enqueue_style( 'uncoder-nav-menus', $base . 'nav-menus.css', array(), UNCODER_WB_VERSION );
		wp_enqueue_script( 'uncoder-nav-menus', $base . 'nav-menus.js', array(), UNCODER_WB_VERSION, true );
		// Every icon library: Lucide, the bundled sets and the custom sets (Design System → Custom icons).
		$libraries = array(
			array(
				'id'    => 'lucide',
				'title' => 'Lucide',
				'url'   => Editor::data_url( 'lucide.json' ),
				'tags'  => Editor::data_url( 'lucide-tags.json' ),
			),
		);
		foreach ( Icons::libraries() as $lib ) {
			$libraries[] = array(
				'id'    => (string) $lib['id'],
				'title' => (string) $lib['title'],
				'url'   => ! empty( $lib['url'] ) ? (string) $lib['url'] : Editor::data_url( 'icons/' . $lib['id'] . '.json' ),
			);
		}
		wp_localize_script(
			'uncoder-nav-menus',
			'UncoderNavMenus',
			array(
				'libraries' => $libraries,
				// Uploads go through Custom_Icons, which rebuilds each SVG from its shapes (nothing is kept as-is).
				'upload'    => current_user_can( 'manage_options' ) ? array(
					'url'   => rest_url( \Uncoder\Builder\Rest\Rest::NS . '/icon-sets' ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'set'   => self::UPLOAD_SET,
					'title' => __( 'Menu icons', 'uncoder' ),
				) : null,
				'i18n'      => array(
					'choose'      => __( 'Choose icon', 'uncoder' ),
					'search'      => __( 'Search icons', 'uncoder' ),
					'library'     => __( 'Icon library', 'uncoder' ),
					'remove'      => __( 'Remove', 'uncoder' ),
					'none'        => __( 'No icons found.', 'uncoder' ),
					'loading'     => __( 'Loading icons…', 'uncoder' ),
					'upload'      => __( 'Upload SVG', 'uncoder' ),
					'uploading'   => __( 'Uploading…', 'uncoder' ),
					'uploadHint'  => __( 'Single-color SVG files work best: they take the menu’s colors.', 'uncoder' ),
					'failed'      => __( 'The upload failed.', 'uncoder' ),
					'description' => __( 'Show the Description field', 'uncoder' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------- Appearance → Menus */

	/**
	 * @param int      $item_id Menu item id.
	 * @param \WP_Post $item    Item.
	 */
	public function admin_field( $item_id, $item ): void {
		$item_id = (int) $item_id;
		$icon    = self::icon( $item_id );
		$svg     = '' !== $icon ? Icons::render( $icon ) : '';
		wp_nonce_field( 'uncoder_icon_' . $item_id, 'uncoder_icon_nonce[' . $item_id . ']' );
		?>
		<div class="field-uncoder-icon description description-wide">
			<span class="uncoder-mi__label" id="uncoder-icon-label-<?php echo esc_attr( (string) $item_id ); ?>"><?php esc_html_e( 'Icon', 'uncoder' ); ?></span>
			<span class="uncoder-mi" data-uncoder-icon-field>
				<input type="hidden" name="uncoder_icon[<?php echo esc_attr( (string) $item_id ); ?>]" value="<?php echo esc_attr( $icon ); ?>" />
				<button type="button" class="button uncoder-mi__pick" aria-expanded="false" aria-describedby="uncoder-icon-label-<?php echo esc_attr( (string) $item_id ); ?>">
					<span class="uncoder-mi__preview" aria-hidden="true"><?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from the bundled icon data. ?></span>
					<span class="uncoder-mi__name"><?php echo esc_html( '' !== $icon ? $icon : __( 'Choose icon', 'uncoder' ) ); ?></span>
				</button>
				<button type="button" class="button-link uncoder-mi__clear"<?php echo '' === $icon ? ' hidden' : ''; ?>><?php esc_html_e( 'Remove', 'uncoder' ); ?></button>
			</span>
			<span class="uncoder-mi__hint"><?php esc_html_e( 'Shown before the label by the Uncoder Nav Menu. Add a short Description to show it under the label in dropdowns.', 'uncoder' ); ?></span>
		</div>
		<?php
	}

	/**
	 * @param int $menu_id Menu id.
	 * @param int $item_id Item id.
	 */
	public function admin_save( $menu_id, $item_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below.
		if ( ! isset( $_POST['uncoder_icon'][ $item_id ], $_POST['uncoder_icon_nonce'][ $item_id ] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_theme_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['uncoder_icon_nonce'][ $item_id ] ) ), 'uncoder_icon_' . $item_id ) ) {
			return;
		}
		self::set_icon( (int) $item_id, sanitize_text_field( wp_unslash( $_POST['uncoder_icon'][ $item_id ] ) ) );
		// phpcs:enable
	}
}
