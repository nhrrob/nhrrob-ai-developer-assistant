<?php
namespace Nhrada\AIDeveloperAssistant\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    private $hook;

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
        add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'maybe_show_nginx_notice' ) );
        add_action( 'wp_ajax_nhrada_dismiss_nginx_notice', array( $this, 'dismiss_nginx_notice' ) );
    }

    public function add_admin_menu() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path d="M14.6 16.6l4.6-4.6-4.6-4.6 1.4-1.4 6 6-6 6-1.4-1.4zm-5.2 0L4.8 12l4.6-4.6L8 6 2 12l6 6 1.4-1.4z"/>
            <path d="M9.5 4.5l1.9-.5 3.6 15-1.9.5z"/>
        </svg>';
        $icon = 'data:image/svg+xml;base64,' . base64_encode( $svg );

        $this->hook = add_menu_page(
            __( 'AI Developer', 'nhrrob-ai-developer-assistant' ),
            __( 'AI Developer', 'nhrrob-ai-developer-assistant' ),
            'manage_options',
            'nhrada-assistant',
            array( $this, 'render_app' ),
            $icon,
            30
        );
    }

    public function maybe_enqueue_assets( $hook ) {
        if ( $hook !== $this->hook ) {
            return;
        }
        wp_enqueue_script( 'nhrada-app' );
        wp_enqueue_style( 'nhrada-app-css' );
    }

    public function render_app() {
        echo '<div id="nhrada-admin-app" style="margin:0 -20px -10px;"></div>';
    }

    public function maybe_show_nginx_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
        if ( stripos( $server, 'nginx' ) === false ) {
            return;
        }

        if ( ! file_exists( NHRADA_SNIPPETS_DIR ) ) {
            return;
        }

        if ( get_option( 'nhrada_nginx_notice_dismissed' ) ) {
            return;
        }

        $upload        = wp_upload_dir();
        $snippets_path = wp_parse_url( $upload['baseurl'] . '/nhrada-ai-developer-assistant', PHP_URL_PATH );
        $nonce         = wp_create_nonce( 'nhrada_dismiss_nginx' );
        ?>
        <div class="notice notice-warning is-dismissible" id="nhrada-nginx-notice">
            <p><strong><?php esc_html_e( 'NHR AI Developer Assistant — nginx configuration required', 'nhrrob-ai-developer-assistant' ); ?></strong></p>
            <p><?php esc_html_e( 'This site runs nginx, which ignores .htaccess rules. The plugin stores managed PHP snippets in the uploads folder and relies on .htaccess to block direct HTTP access to those files. Add this block to your nginx server configuration:', 'nhrrob-ai-developer-assistant' ); ?></p>
            <pre style="background:#f6f7f7;border:1px solid #ddd;padding:10px 14px;margin:8px 0;overflow-x:auto;font-size:13px;">location ~* ^<?php echo esc_html( $snippets_path ); ?>/.*\.php$ {
    deny all;
}</pre>
            <p><?php esc_html_e( 'After adding the rule and reloading nginx, dismiss this notice.', 'nhrrob-ai-developer-assistant' ); ?></p>
        </div>
        <script>
        (function () {
            var el = document.getElementById('nhrada-nginx-notice');
            if (!el) return;
            el.addEventListener('click', function (e) {
                if (!e.target.classList.contains('notice-dismiss')) return;
                var data = new URLSearchParams();
                data.append('action', 'nhrada_dismiss_nginx_notice');
                data.append('_wpnonce', '<?php echo esc_js( $nonce ); ?>');
                fetch(ajaxurl, { method: 'POST', body: data });
            });
        }());
        </script>
        <?php
    }

    public function dismiss_nginx_notice() {
        check_ajax_referer( 'nhrada_dismiss_nginx' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }
        update_option( 'nhrada_nginx_notice_dismissed', 1 );
        wp_send_json_success();
    }

    public function plugin_action_links( $links, $file ) {
        if ( plugin_basename( NHRADA_FILE ) === $file ) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                admin_url( 'admin.php?page=nhrada-assistant' ),
                __( 'Open', 'nhrrob-ai-developer-assistant' )
            );
            array_push( $links, $settings_link );
        }
        return $links;
    }
}
