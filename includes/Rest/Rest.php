<?php
/**
 * REST API bootstrap (namespace uncoder/v1).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every controller.
 */
final class Rest {

	public const NS = 'uncoder/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$controllers = array(
			new Documents_Controller(),
			new Notes_Controller(),
			new Prefs_Controller(),
			new Render_Controller(),
			new Kit_Controller(),
			new Lookup_Controller(),
			new Templates_Controller(),
			new Submissions_Controller(),
			new Settings_Controller(),
			new Overview_Controller(),
		);
		/**
		 * Filters REST controllers (each must have register_routes()).
		 *
		 * @param object[] $controllers Controllers.
		 */
		foreach ( apply_filters( 'uncoder_wb/rest/controllers', $controllers ) as $controller ) {
			$controller->register_routes();
		}
	}
}
