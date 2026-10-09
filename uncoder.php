<?php
/**
 * Plugin Name:       Uncoder – AI-Powered Website Builder
 * Plugin URI:        https://uncoderbuilder.com/
 * Description:       Visual drag & drop website builder with a full theme builder (headers, footers, single, archive, 404, popups, mega menus, loop items) and a built-in MCP server so AI assistants like Claude, ChatGPT and Cursor can design and build your site.
 * Version:           0.1.2
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            Uncoder
 * Author URI:        https://uncoderbuilder.com/
 * Update URI:        https://github.com/UncoderBuilder/uncoder
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       uncoder
 * Domain Path:       /languages
 *
 * @package Uncoder\Builder
 */

defined( 'ABSPATH' ) || exit;

define( 'UNCODER_WB_VERSION', '0.1.2' );
define( 'UNCODER_WB_FILE', __FILE__ );
define( 'UNCODER_WB_PATH', plugin_dir_path( __FILE__ ) );
define( 'UNCODER_WB_URL', plugin_dir_url( __FILE__ ) );
define( 'UNCODER_WB_BASENAME', plugin_basename( __FILE__ ) );
define( 'UNCODER_WB_MIN_PHP', '8.0' );

if ( version_compare( PHP_VERSION, UNCODER_WB_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: required PHP version. */
					__( 'Uncoder requires PHP %s or newer.', 'uncoder' ),
					UNCODER_WB_MIN_PHP
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

require_once UNCODER_WB_PATH . 'includes/Autoloader.php';
\Uncoder\Builder\Autoloader::register();

register_activation_hook( __FILE__, array( \Uncoder\Builder\Install::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Uncoder\Builder\Install::class, 'deactivate' ) );

\Uncoder\Builder\Plugin::instance()->boot();
