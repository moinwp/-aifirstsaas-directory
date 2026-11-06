<?php
/**
 * Plugin Name:       AI First SaaS Directory
 * Plugin URI:        https://aifirstsaas.com
 * Description:       Data and presentation layer for aifirstsaas.com — a directory of AI features across SaaS tools. Registers the Tool CPT, taxonomies, and custom tables for structured tool data.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            aifirstsaas.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aifirstsaas
 * Domain Path:       /languages
 *
 * @package AIFirst
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 *
 * @since 0.1.0
 */
define( 'AIFIRST_VERSION', '0.1.0' );
define( 'AIFIRST_PLUGIN_FILE', __FILE__ );
define( 'AIFIRST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIFIRST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIFIRST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'AIFIRST_INCLUDES_DIR', AIFIRST_PLUGIN_DIR . 'includes/' );
define( 'AIFIRST_ADMIN_DIR', AIFIRST_PLUGIN_DIR . 'admin/' );
define( 'AIFIRST_PUBLIC_DIR', AIFIRST_PLUGIN_DIR . 'public/' );
define( 'AIFIRST_TEMPLATES_DIR', AIFIRST_PLUGIN_DIR . 'templates/' );
define( 'AIFIRST_DB_VERSION', '0.1.0' );

require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-autoloader.php';
AIFirst_Autoloader::register();

require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-activator.php';
require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-deactivator.php';

register_activation_hook( __FILE__, array( 'AIFirst_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AIFirst_Deactivator', 'deactivate' ) );

/**
 * Bootstrap the plugin.
 *
 * @since 0.1.0
 * @return AIFirst_Plugin
 */
function aifirst_plugin() {
	return AIFirst_Plugin::instance();
}

aifirst_plugin()->run();
