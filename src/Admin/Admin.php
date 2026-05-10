<?php
namespace NHR\AIAssistant\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
    }

    public function add_admin_menu() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path d="M14.6 16.6l4.6-4.6-4.6-4.6 1.4-1.4 6 6-6 6-1.4-1.4zm-5.2 0L4.8 12l4.6-4.6L8 6 2 12l6 6 1.4-1.4z"/>
            <path d="M9.5 4.5l1.9-.5 3.6 15-1.9.5z"/>
        </svg>';
        $icon = 'data:image/svg+xml;base64,' . base64_encode( $svg );

        add_menu_page(
            __( 'AI Developer', 'nhrrob-ai-assistant' ),
            __( 'AI Developer', 'nhrrob-ai-assistant' ),
            'manage_options',
            'nhraa-settings',
            array( $this, 'render_app' ),
            $icon,
            30
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( 'toplevel_page_nhraa-settings' !== $hook ) {
            return;
        }

        $asset_file = NHRAA_PLUGIN_DIR . 'admin/build/index.asset.php';
        if ( ! file_exists( $asset_file ) ) {
            return;
        }

        $assets = require $asset_file;

        wp_enqueue_script(
            'nhraa-app',
            NHRAA_PLUGIN_URL . 'admin/build/index.js',
            $assets['dependencies'],
            $assets['version'],
            true
        );

        wp_enqueue_style(
            'nhraa-app-css',
            NHRAA_PLUGIN_URL . 'admin/build/style-index.css',
            array(),
            $assets['version']
        );
    }

    public function render_app() {
        echo '<div id="nhraa-admin-app" style="margin:0 -20px -10px;"></div>';
    }

    public function plugin_action_links( $links, $file ) {
        if ( plugin_basename( NHRAA_PLUGIN_DIR . 'nhrrob-ai-assistant.php' ) === $file ) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                admin_url( 'admin.php?page=nhraa-settings' ),
                __( 'Open', 'nhrrob-ai-assistant' )
            );
            array_unshift( $links, $settings_link );
        }
        return $links;
    }
}
