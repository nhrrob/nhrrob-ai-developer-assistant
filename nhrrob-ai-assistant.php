<?php
/**
 * Plugin Name: NHR AI Developer Assistant
 * Description: Gives site owners a personal AI developer inside their WordPress admin.
 * Version: 1.0.0
 * Author: Nazmul Hasan Robin
 * Text Domain: nhrrob-ai-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'NHRAA_VERSION', '1.0.0' );
define( 'NHRAA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NHRAA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( NHRAA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once NHRAA_PLUGIN_DIR . 'vendor/autoload.php';
}

use NHR\AIAssistant\Activator;
use NHR\AIAssistant\Plugin;

// Registration hooks
register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );

/**
 * Deactivate the plugin
 */
function nhraa_deactivate_plugin() {
    // We do not delete tables on deactivation. Handled in uninstall.php
}
register_deactivation_hook( __FILE__, 'nhraa_deactivate_plugin' );

// Initialize the plugin
function nhraa_init() {
    if ( class_exists( Plugin::class ) ) {
        $plugin = new Plugin();
        $plugin->init();
    }
}
add_action( 'plugins_loaded', 'nhraa_init' );
