<?php
/**
 * Plugin settings for the admin "Settings" screen.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

use Uncoder\Builder\Core\Kit;
use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use Uncoder\Builder\Site\Maintenance;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /settings, POST /settings/regenerate-css.
 *
 * Plugin options live in `uncoder_wb_settings` (post_types is read by Documents::post_types()).
 * Font delivery and "apply the kit everywhere" are Design System settings (kit settings.font_delivery and
 * theme.enabled), so they are read and written through the Kit to keep a single source of truth.
 */
final class Settings_Controller {

	public const OPTION = 'uncoder_wb_settings';

	public function register_routes(): void {
		$admin = static fn() => current_user_can( 'manage_options' );
		register_rest_route(
			Rest::NS,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			Rest::NS,
			'/settings/regenerate-css',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'regenerate' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			Rest::NS,
			'/settings/clear-cache',
			array(
				'methods'             => 'POST',
				'callback'            => static fn() => new WP_REST_Response( \Uncoder\Builder\Site\Cache::clear() ),
				'permission_callback' => $admin,
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function stored(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Public post types that can be enabled for the builder.
	 *
	 * @return array<string,string> name => label
	 */
	private function available_post_types(): array {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( in_array( $pt->name, array( Post_Types::TEMPLATE, 'attachment' ), true ) ) {
				continue;
			}
			$out[ $pt->name ] = $pt->labels->name;
		}
		return $out;
	}

	/**
	 * @return array<string,string> value => label
	 */
	private function font_delivery_options(): array {
		$schemas = Kit::schemas();
		$options = $schemas['settings']['font_delivery']['options'] ?? array();
		return is_array( $options ) ? $options : array();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function payload(): array {
		$stored    = $this->stored();
		$kit       = Plugin::instance()->kit();
		$available = array();
		foreach ( $this->available_post_types() as $name => $label ) {
			$available[] = array(
				'name'  => $name,
				'label' => $label,
			);
		}
		$fonts = array();
		foreach ( $this->font_delivery_options() as $value => $label ) {
			$fonts[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}
		$theme = (array) $kit->get( 'theme', array() );
		return array(
			'postTypes'           => array_values( is_array( $stored['post_types'] ?? null ) ? $stored['post_types'] : array( 'page', 'post' ) ),
			'availablePostTypes'  => $available,
			'fontDelivery'        => (string) $kit->setting( 'font_delivery', 'google' ),
			'fontDeliveryOptions' => $fonts,
			'applyKit'            => ! empty( $theme['enabled'] ),
			'removeData'          => ! empty( $stored['remove_data'] ),
			'formRetentionDays'   => (int) ( $stored['form_retention_days'] ?? 0 ),
			'cssWritable'         => null !== Utils::uploads(),
			'maintenance'         => Maintenance::get(),
			'roleAccess'          => \Uncoder\Builder\Site\Role_Manager::settings(),
			'captcha'             => \Uncoder\Builder\Site\Captcha::public_settings(),
			'integrations'        => \Uncoder\Builder\Forms\Integrations::public_settings(),
			'disabledWidgets'     => Prefs_Controller::disabled_widgets(),
			'widgets'             => array_values(
				array_map(
					static fn( $el ) => array(
						'name'     => $el->name(),
						'title'    => $el->title(),
						'category' => $el->category(),
						'icon'     => $el->icon(),
					),
					array_filter( \Uncoder\Builder\Plugin::instance()->elements()->all(), static fn( $el ) => 'container' !== $el->name() )
				)
			),
			'widgetCategories'    => \Uncoder\Builder\Plugin::instance()->elements()->categories(),
			'consent'             => \Uncoder\Builder\Site\Consent::get(),
			'performance'         => \Uncoder\Builder\Site\Performance::get(),
			'business'            => \Uncoder\Builder\Site\Schema::get(),
			'seoPlugin'           => array( 'yoast' => 'Yoast SEO', 'rankmath' => 'Rank Math', 'aioseo' => 'All in One SEO', 'seopress' => 'SEOPress', 'tsf' => 'The SEO Framework' )[ \Uncoder\Builder\Core\Seo::plugin() ] ?? '',
			'businessTypes'       => \Uncoder\Builder\Site\Schema::TYPES,
			'maintenancePage'     => $this->page_ref( Maintenance::get()['page'] ),
			'roles'               => $this->roles(),
		);
	}

	/**
	 * @return array<int, array{value:string,label:string}>
	 */
	private function roles(): array {
		$out = array();
		foreach ( wp_roles()->get_names() as $role => $name ) {
			$out[] = array(
				'value' => (string) $role,
				'label' => translate_user_role( $name ),
				'edits' => (bool) ( get_role( $role ) && get_role( $role )->has_cap( 'edit_posts' ) ),
			);
		}
		return $out;
	}

	/**
	 * @return array{id:int,title:string,status:string,edit:string}|null
	 */
	private function page_ref( int $id ): ?array {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) {
			return null;
		}
		return array(
			'id'     => $post->ID,
			'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'status' => $post->post_status,
			'edit'   => admin_url( 'post.php?action=uncoder&post=' . $post->ID ),
		);
	}

	public function get(): WP_REST_Response {
		return new WP_REST_Response( $this->payload() );
	}

	/**
	 * Body (all optional): { postTypes: string[], fontDelivery: string, applyKit: bool, removeData: bool,
	 *   maintenance: { mode, page, access, roles } }.
	 */
	public function save( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'uncoder_bad_request', __( 'Invalid JSON body.', 'uncoder' ), array( 'status' => 400 ) );
		}
		// Unknown keys (owned by other modules) are preserved.
		$stored  = $this->stored();
		$changed = false;
		if ( isset( $body['postTypes'] ) ) {
			if ( ! is_array( $body['postTypes'] ) ) {
				return new WP_Error( 'uncoder_invalid', __( 'postTypes must be a list.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$stored['post_types'] = array_values( array_intersect( array_keys( $this->available_post_types() ), array_map( 'sanitize_key', array_map( 'strval', $body['postTypes'] ) ) ) );
			$changed              = true;
		}
		if ( array_key_exists( 'removeData', $body ) ) {
			$stored['remove_data'] = (bool) $body['removeData'];
			$changed               = true;
		}
		if ( array_key_exists( 'formRetentionDays', $body ) ) {
			$stored['form_retention_days'] = max( 0, min( 3650, absint( $body['formRetentionDays'] ) ) );
			$changed                       = true;
		}
		if ( isset( $body['business'] ) && is_array( $body['business'] ) ) {
			$stored['business'] = \Uncoder\Builder\Site\Schema::sanitize( $body['business'] );
			$changed            = true;
		}
		if ( isset( $body['performance'] ) && is_array( $body['performance'] ) ) {
			$stored['performance'] = \Uncoder\Builder\Site\Performance::sanitize( $body['performance'] );
			$changed               = true;
		}
		if ( isset( $body['consent'] ) && is_array( $body['consent'] ) ) {
			// "renew": ask every visitor again (new consent version).
			$stored['consent'] = \Uncoder\Builder\Site\Consent::sanitize( array_merge( array( 'version' => \Uncoder\Builder\Site\Consent::get()['version'] ), $body['consent'] ), ! empty( $body['consent']['renew'] ) );
			$changed           = true;
		}
		if ( isset( $body['captcha'] ) && is_array( $body['captcha'] ) ) {
			$stored['captcha'] = \Uncoder\Builder\Site\Captcha::sanitize( $body['captcha'] );
			$changed           = true;
		}
		if ( isset( $body['disabledWidgets'] ) && is_array( $body['disabledWidgets'] ) ) {
			$stored['disabled_widgets'] = Prefs_Controller::sanitize_disabled( $body['disabledWidgets'] );
			$changed                    = true;
		}
		if ( isset( $body['integrations'] ) && is_array( $body['integrations'] ) ) {
			$stored['integrations'] = \Uncoder\Builder\Forms\Integrations::sanitize( $body['integrations'] );
			$changed                = true;
		}
		if ( isset( $body['roleAccess'] ) ) {
			if ( ! is_array( $body['roleAccess'] ) ) {
				return new WP_Error( 'uncoder_invalid', __( 'roleAccess must be an object.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$stored['roles'] = \Uncoder\Builder\Site\Role_Manager::sanitize( $body['roleAccess'] );
			$changed         = true;
		}
		if ( isset( $body['maintenance'] ) ) {
			if ( ! is_array( $body['maintenance'] ) ) {
				return new WP_Error( 'uncoder_invalid', __( 'maintenance must be an object.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$stored['maintenance'] = Maintenance::sanitize( $body['maintenance'] );
			$changed               = true;
		}

		$partial = array();
		if ( isset( $body['fontDelivery'] ) ) {
			$value = sanitize_key( (string) $body['fontDelivery'] );
			if ( ! array_key_exists( $value, $this->font_delivery_options() ) ) {
				return new WP_Error( 'uncoder_invalid', __( 'Unknown font delivery option.', 'uncoder' ), array( 'status' => 400 ) );
			}
			$partial['settings'] = array( 'font_delivery' => $value );
		}
		if ( array_key_exists( 'applyKit', $body ) ) {
			$partial['theme'] = array( 'enabled' => (bool) $body['applyKit'] );
		}

		$kit   = Plugin::instance()->kit();
		$clean = array();
		if ( $partial ) {
			$errors = array();
			$clean  = $kit->sanitize( $partial, 'sanitize', $errors );
			if ( $errors ) {
				return new WP_Error( 'uncoder_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
			}
		}
		if ( $changed ) {
			update_option( self::OPTION, $stored );
		}
		if ( $clean ) {
			$kit->update( $clean, '', false );
		}
		return new WP_REST_Response( $this->payload() );
	}

	/**
	 * Rebuilds every document stylesheet and kit.css.
	 */
	public function regenerate(): WP_REST_Response {
		$started   = microtime( true );
		$documents = Plugin::instance()->documents()->regenerate_all();
		$kit       = Plugin::instance()->kit()->write_css();
		return new WP_REST_Response(
			array(
				'documents' => $documents,
				'kit'       => $kit,
				'ms'        => (int) round( ( microtime( true ) - $started ) * 1000 ),
			)
		);
	}
}
