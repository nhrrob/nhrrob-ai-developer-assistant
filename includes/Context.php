<?php
namespace Nhrada\AIDeveloperAssistant;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Context {

    public function get_context() {
        $theme = wp_get_theme();
        
        $context = array(
            'wp_version'     => get_bloginfo( 'version' ),
            'php_version'    => phpversion(),
            'theme_name'     => $theme->get( 'Name' ),
            'theme_version'  => $theme->get( 'Version' ),
            'child_theme'    => is_child_theme() ? 'yes' : 'no',
            'site_url'       => get_site_url(),
            'admin_email'    => get_option( 'admin_email' ),
            'woocommerce'    => class_exists( 'WooCommerce' ) ? 'yes' : 'no',
            'plugin_list'    => $this->get_active_plugins_list(),
            'error_log'      => $this->get_recent_errors(),
            'customizer'     => $this->get_customizer_settings()
        );

        return $context;
    }

    private function get_active_plugins_list() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins' );
        $list = array();

        if ( is_array( $active_plugins ) ) {
            foreach ( $active_plugins as $plugin_path ) {
                if ( isset( $all_plugins[ $plugin_path ] ) ) {
                    $list[] = $all_plugins[ $plugin_path ]['Name'] . ' v' . $all_plugins[ $plugin_path ]['Version'];
                }
            }
        }

        return implode( ', ', $list );
    }

    private function get_recent_errors() {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
            return 'Debug log is not enabled.';
        }

        $log_file = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';

        if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
            return 'No debug.log found or readable.';
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        $contents = $wp_filesystem->get_contents( $log_file );
        if ( false === $contents ) {
            return 'Could not read debug log.';
        }

        $lines = array_filter( array_slice( explode( "\n", trim( $contents ) ), -10 ) );
        return empty( $lines ) ? 'No recent errors.' : implode( "\n", array_map( 'trim', $lines ) );
    }

    private function get_customizer_settings() {
        // Collect basic common customizer options
        return array(
            'background_color' => get_theme_mod( 'background_color' ),
            'header_textcolor' => get_theme_mod( 'header_textcolor' ),
        );
    }
}
