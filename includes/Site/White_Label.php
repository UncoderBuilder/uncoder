<?php
/**
 * White-label (Agency licence): the builder under the agency's name, logo and icon in the WordPress admin, the
 * editor, "Edit with …" links, the plugins list and the AI connection, and optionally without links to
 * uncoderbuilder.com. Changing the settings needs a licence with the "white_label" feature; once saved they keep
 * applying when the licence ends (a client's site never switches back on its own).
 *
 * "Only me": the Licence and White-label settings show only to the administrator who saved them, so other admins
 * (the client) do not see them.
 *
 *   GET/POST uncoder/v1/white-label
 *
 * Option uncoder_wb_white_label: name, logo (attachment id), icon (attachment id), url, hide_links, owner (user id).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Licence\Licence;
use Uncoder\Builder\Rest\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class White_Label {

	public const OPTION = 'uncoder_wb_white_label';

	/** @var array<string,mixed>|null */
	private static $settings = null;

	public function register(): void {
		if ( Licence::enabled() ) {
			add_action( 'rest_api_init', array( $this, 'routes' ) );
		}
		if ( ! self::active() ) {
			return;
		}
		$rename = array( self::class, 'rename' );
		add_filter( 'gettext_uncoder', $rename, 10, 1 );
		add_filter( 'gettext_with_context_uncoder', $rename, 10, 1 );
		add_filter( 'ngettext_uncoder', $rename, 10, 1 );
		add_filter( 'all_plugins', array( self::class, 'plugins_list' ) );
		add_filter( 'plugin_row_meta', array( self::class, 'row_meta' ), 20, 2 );
		add_action( 'admin_head', array( self::class, 'menu_icon_css' ) );
	}

	/** @return array<string,mixed> */
	public static function settings(): array {
		if ( null === self::$settings ) {
			$s              = get_option( self::OPTION, array() );
			self::$settings = is_array( $s ) ? $s : array();
		}
		return self::$settings;
	}

	/** Whether a white-label brand is set (whatever the licence's state: see the class comment). */
	public static function active(): bool {
		$s = self::settings();
		return '' !== trim( (string) ( $s['name'] ?? '' ) ) || ! empty( $s['logo'] ) || ! empty( $s['icon'] );
	}

	public static function name(): string {
		$name = trim( (string) ( self::settings()['name'] ?? '' ) );
		return '' !== $name ? $name : 'Uncoder';
	}

	/** Whether links to uncoderbuilder.com (docs, support, changelog, pricing) are hidden. */
	public static function hide_links(): bool {
		return self::active() && ! empty( self::settings()['hide_links'] );
	}

	/** An attachment's URL (logo / icon), or ''. */
	public static function image( string $which ): string {
		$id = (int) ( self::settings()[ $which ] ?? 0 );
		if ( ! $id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, 'full' );
		return $url ? (string) $url : '';
	}

	/** The "only me" administrator, or 0: none, or one who left (account deleted, or no longer an administrator). */
	public static function owner(): int {
		$owner = (int) ( self::settings()['owner'] ?? 0 );
		return $owner > 0 && user_can( $owner, 'manage_options' ) ? $owner : 0;
	}

	/** Whether the current user may see and change the Licence and White-label settings. */
	public static function can_manage(): bool {
		if ( ! current_user_can( 'manage_options' ) || Handoff::restricts() ) {
			return false;
		}
		$owner = self::owner();
		return 0 === $owner || get_current_user_id() === $owner;
	}

	/**
	 * What the admin, the editor and the post editor show (also in their JS config).
	 *
	 * @return array<string,mixed>
	 */
	public static function brand(): array {
		$active = self::active();
		return array(
			'white'     => $active,
			'name'      => self::name(),
			'logo'      => $active ? self::image( 'logo' ) : '',
			'icon'      => $active ? self::image( 'icon' ) : '',
			'url'       => $active ? esc_url_raw( (string) ( self::settings()['url'] ?? '' ) ) : '',
			'hideLinks' => self::hide_links(),
		);
	}

	/** "Uncoder" → the brand name in the plugin's own texts. */
	public static function rename( $text ) {
		return is_string( $text ) && false !== strpos( $text, 'Uncoder' ) ? str_replace( 'Uncoder', self::name(), $text ) : $text;
	}

	/**
	 * rename() on every string in a structure (the AI tool list).
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function rename_deep( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( self::class, 'rename_deep' ), $value );
		}
		return self::rename( $value );
	}

	/**
	 * The plugin's entry under Plugins.
	 *
	 * @param array<string,array<string,string>> $plugins All plugins.
	 * @return array<string,array<string,string>>
	 */
	public static function plugins_list( array $plugins ): array {
		$file = plugin_basename( UNCODER_WB_FILE );
		if ( isset( $plugins[ $file ] ) ) {
			$name = self::name();
			$url  = (string) ( self::settings()['url'] ?? '' );
			$plugins[ $file ]['Name']        = $name;
			$plugins[ $file ]['Title']       = $name;
			$plugins[ $file ]['Description'] = __( 'Visual website builder with an AI connection.', 'uncoder' );
			$plugins[ $file ]['Author']      = $name;
			$plugins[ $file ]['AuthorName']  = $name;
			$plugins[ $file ]['AuthorURI']   = $url;
			$plugins[ $file ]['PluginURI']   = $url;
		}
		return $plugins;
	}

	/**
	 * @param array<int,string> $meta Links under the plugin's description.
	 * @return array<int,string>
	 */
	public static function row_meta( array $meta, string $file ): array {
		if ( plugin_basename( UNCODER_WB_FILE ) !== $file || ! self::hide_links() ) {
			return $meta;
		}
		return array_values( array_filter( $meta, static fn( string $m ): bool => false === stripos( $m, 'uncoderbuilder' ) && false === stripos( $m, 'github.com' ) ) );
	}

	/** WordPress shows an image menu icon at its own size: fit the agency's icon to the 20 px slot. */
	public static function menu_icon_css(): void {
		if ( '' === self::image( 'icon' ) ) {
			return;
		}
		echo '<style id="uncoder-white-label">#adminmenu #toplevel_page_uncoder .wp-menu-image img{width:20px;height:20px;padding:7px 0 0;object-fit:contain;opacity:1;border-radius:4px}</style>';
	}

	/* ------------------------------------------------------------------ REST */

	public function routes(): void {
		register_rest_route(
			Rest::NS,
			'/white-label',
			array(
				array( 'methods' => 'GET', 'callback' => array( $this, 'get' ), 'permission_callback' => array( self::class, 'can_manage' ) ),
				array( 'methods' => 'POST', 'callback' => array( $this, 'save' ), 'permission_callback' => array( self::class, 'can_manage' ) ),
			)
		);
	}

	public function get(): WP_REST_Response {
		$s = self::settings();
		return new WP_REST_Response(
			array(
				'name'      => (string) ( $s['name'] ?? '' ),
				'logo'      => array( 'id' => (int) ( $s['logo'] ?? 0 ), 'url' => self::image( 'logo' ) ),
				'icon'      => array( 'id' => (int) ( $s['icon'] ?? 0 ), 'url' => self::image( 'icon' ) ),
				'url'       => (string) ( $s['url'] ?? '' ),
				'hideLinks' => ! empty( $s['hide_links'] ),
				'onlyMe'    => ! empty( $s['owner'] ),
				'allowed'   => Licence::allows( 'white_label' ),
				'pricing'   => Licence::PRICING,
			)
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public function save( WP_REST_Request $request ) {
		$p = (array) $request->get_json_params();
		// Turning white-label off is always allowed; setting or changing a brand needs the Agency licence.
		$off = '' === trim( (string) ( $p['name'] ?? '' ) ) && empty( $p['logo'] ) && empty( $p['icon'] );
		if ( ! $off && ! Licence::allows( 'white_label' ) ) {
			return new WP_Error( 'uncoder_needs_agency', __( 'White-label comes with the Agency licence. Your saved brand keeps applying; changing it needs an active Agency licence.', 'uncoder' ), array( 'status' => 403 ) );
		}
		if ( $off && ! Licence::allows( 'white_label' ) ) {
			$p = array(); // Without the licence, off means everything back to the defaults.
		}
		$name = trim( wp_strip_all_tags( (string) ( $p['name'] ?? '' ) ) );
		$s    = array(
			'name'       => mb_substr( $name, 0, 40 ),
			'logo'       => self::own_image( (int) ( $p['logo'] ?? 0 ) ),
			'icon'       => self::own_image( (int) ( $p['icon'] ?? 0 ) ),
			'url'        => esc_url_raw( (string) ( $p['url'] ?? '' ) ),
			'hide_links' => ! empty( $p['hideLinks'] ),
			'owner'      => ! empty( $p['onlyMe'] ) ? get_current_user_id() : 0,
		);
		update_option( self::OPTION, $s, true );
		self::$settings = $s;
		return $this->get();
	}

	/** An image attachment id, or 0. */
	private static function own_image( int $id ): int {
		return $id > 0 && wp_attachment_is_image( $id ) ? $id : 0;
	}
}
