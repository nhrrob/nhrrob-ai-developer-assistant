<?php
namespace NHR\AIAssistant;

use NHR\AIAssistant\Admin\Admin;
use NHR\AIAssistant\Api\Api;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    public function init() {
        if ( is_admin() ) {
            $admin = new Admin();
            $admin->init();
        }

        $api = new Api();
        $api->init();

        // Enqueue custom code
        add_action( 'wp_footer', array( $this, 'enqueue_custom_js' ), 99 );
        $this->load_php_snippets();
    }

    public function enqueue_custom_js() {
        $js = get_option( 'nhraa_custom_js', '' );
        if ( ! empty( $js ) ) {
            echo "<script type='text/javascript'>\n" . $js . "\n</script>\n";
        }
    }

    public function load_php_snippets() {
        $snippets_file = WP_CONTENT_DIR . '/nhraa-snippets.php';
        if ( file_exists( $snippets_file ) ) {
            require_once $snippets_file;
        }
    }
}
