<?php
namespace NHR\AIAssistant\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_footer', array( $this, 'render_chat_widget' ) );
    }

    public function add_admin_menu() {
        $svg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="black" xmlns="http://www.w3.org/2000/svg"><path d="M11.5 2.5L13.8 8.2L19.5 10.5L13.8 12.8L11.5 18.5L9.2 12.8L3.5 10.5L9.2 8.2L11.5 2.5Z"/><path d="M18.5 16.5L19.5 19L22 20L19.5 21L18.5 23.5L17.5 21L15 20L17.5 19L18.5 16.5Z"/></svg>';
        $icon_url = 'data:image/svg+xml;base64,' . base64_encode( $svg );

        // Single main menu, no submenus since it's an SPA
        add_menu_page(
            __( 'AI Developer', 'nhrrob-ai-assistant' ),
            __( 'AI Developer', 'nhrrob-ai-assistant' ),
            'manage_options',
            'nhraa-settings',
            array( $this, 'render_react_app' ),
            $icon_url,
            30
        );
    }

    public function enqueue_scripts( $hook ) {
        // Always enqueue chat widget scripts globally
        wp_enqueue_style( 'nhraa-admin-css', NHRAA_PLUGIN_URL . 'admin/css/admin.css', array(), NHRAA_VERSION );
        wp_enqueue_script( 'nhraa-chat-js', NHRAA_PLUGIN_URL . 'admin/js/chat.js', array(), NHRAA_VERSION, true );

        wp_localize_script( 'nhraa-chat-js', 'nhraaChatData', array(
            'apiUrl'  => esc_url_raw( rest_url( 'nhraa/v1/chat' ) ),
            'undoUrl' => esc_url_raw( rest_url( 'nhraa/v1/undo' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ) );

        // Enqueue React SPA only on our pages
        if ( 'toplevel_page_nhraa-settings' === $hook || 'ai-developer_page_nhraa-history' === $hook ) {
            $asset_file = NHRAA_PLUGIN_DIR . 'admin/build/index.asset.php';
            
            if ( file_exists( $asset_file ) ) {
                $assets = require $asset_file;
                wp_enqueue_script(
                    'nhraa-react-app',
                    NHRAA_PLUGIN_URL . 'admin/build/index.js',
                    $assets['dependencies'],
                    $assets['version'],
                    true
                );

                wp_enqueue_style(
                    'nhraa-react-app-css',
                    NHRAA_PLUGIN_URL . 'admin/build/style-index.css',
                    array(),
                    $assets['version']
                );

                // Setup api-fetch
                wp_set_script_translations( 'nhraa-react-app', 'nhrrob-ai-assistant' );
                wp_enqueue_script( 'wp-api-fetch' );
            }
        }
    }

    public function render_react_app() {
        echo '<div id="nhraa-admin-app"></div>';
    }

    public function render_chat_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include NHRAA_PLUGIN_DIR . 'admin/views/chat.php';
    }
}
