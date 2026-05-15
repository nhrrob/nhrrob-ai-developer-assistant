<?php
/**
 * Plugin Name: NHR AI Developer Assistant
 * Description: Gives site owners a personal AI developer inside their WordPress admin. Describe a change in plain English and the assistant implements it — CSS, JS, PHP snippets, or site options — with full undo support.
 * Version: 1.1.0
 * Author: Nazmul Hasan Robin
 * Author URI: https://nhrrob.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nhrrob-ai-developer-assistant
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'NHRADA_VERSION', '1.1.0' );
define( 'NHRADA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NHRADA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( NHRADA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once NHRADA_PLUGIN_DIR . 'vendor/autoload.php';
}

use NHR\AIDeveloperAssistant\Activator;
use NHR\AIDeveloperAssistant\Plugin;

// Registration hooks
register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );

/**
 * Deactivate the plugin
 */
function nhrada_deactivate_plugin() {
    // We do not delete tables on deactivation. Handled in uninstall.php
}
register_deactivation_hook( __FILE__, 'nhrada_deactivate_plugin' );

// Initialize the plugin
function nhrada_init() {
    if ( class_exists( Plugin::class ) ) {
        $plugin = new Plugin();
        $plugin->init();
    }
}
add_action( 'plugins_loaded', 'nhrada_init' );
