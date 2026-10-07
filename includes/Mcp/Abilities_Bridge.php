<?php
/**
 * Exposes the MCP tools as WordPress Abilities (WordPress 6.9+ Abilities API).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

use Uncoder\Builder\Install;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Every tool becomes an ability named `uncoder/<tool-name>` (underscores → dashes) in the category
 * `uncoder`, so other AI integrations built on the Abilities API (for example the WordPress MCP
 * Adapter) can use them. Abilities run as the logged-in user with the same checks as the MCP endpoint:
 * the `uncoder_wb_use_mcp` capability, rate limit, audit log and undo snapshots.
 */
final class Abilities_Bridge {

	public const CATEGORY = 'uncoder';

	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Uncoder', 'uncoder' ),
				'description' => __( 'Build and edit pages, theme templates, the design system, menus and media with Uncoder.', 'uncoder' ),
			)
		);
	}

	public function register_abilities(): void {
		if ( ! Settings::get( 'enabled' ) ) {
			return;
		}
		/**
		 * Whether to expose Uncoder tools through the Abilities API.
		 *
		 * @param bool $enabled Enabled.
		 */
		if ( ! apply_filters( 'uncoder_wb/abilities/enabled', true ) ) {
			return;
		}
		foreach ( Registry::instance()->all() as $name => $tool ) {
			$annotations = (array) ( $tool['annotations'] ?? array() );
			$args        = array(
				'label'               => (string) ( $tool['title'] ?? $name ),
				'description'         => (string) ( $tool['description'] ?? '' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => static fn( $input = null ) => self::execute( $name, $input ),
				'permission_callback' => static fn() => is_user_logged_in() && current_user_can( Install::MCP_CAP ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => ! empty( $annotations['readOnlyHint'] ),
						'destructive' => ! empty( $annotations['destructiveHint'] ),
						'idempotent'  => ! empty( $annotations['idempotentHint'] ),
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			);
			$schema = $this->input_schema( (array) ( $tool['input'] ?? array() ) );
			if ( $schema ) {
				$args['input_schema'] = $schema;
			}
			wp_register_ability( self::CATEGORY . '/' . str_replace( '_', '-', $name ), $args );
		}
	}

	/**
	 * Tool schema → ability input schema (no input for tools without parameters).
	 *
	 * @param array<string,mixed> $schema Tool input schema.
	 * @return array<string,mixed>|null
	 */
	private function input_schema( array $schema ): ?array {
		$props = $schema['properties'] ?? array();
		if ( $props instanceof \stdClass ) {
			$props = (array) $props;
		}
		if ( ! $props ) {
			return null;
		}
		$out = array(
			'type'       => 'object',
			'properties' => $props,
		);
		if ( ! empty( $schema['required'] ) ) {
			$out['required'] = array_values( (array) $schema['required'] );
		}
		return $out;
	}

	/**
	 * @param mixed $input Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function execute( string $tool, $input ) {
		$user = wp_get_current_user();
		$ctx  = new Context( (int) $user->ID, array_keys( Tokens::SCOPES ), 0, 'WordPress Abilities', 'abilities' );
		return Server::execute( $ctx, $tool, is_array( $input ) ? $input : array() );
	}
}
