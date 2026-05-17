<?php
namespace Nhrada\AIDeveloperAssistant;

use Nhrada\AIDeveloperAssistant\Database\Changelog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Executor {

    private $changelog;
    private $safety;

    public function __construct() {
        $this->changelog = new Changelog();
        $this->safety    = new Safety();
    }

    public function apply_change( $user_message, $ai_response ) {
        $change_type = isset( $ai_response['change_type'] ) ? $ai_response['change_type'] : 'none';
        $file_target = isset( $ai_response['file_target'] ) ? $ai_response['file_target'] : null;
        $code        = isset( $ai_response['code'] ) ? $ai_response['code'] : '';
        $description = isset( $ai_response['description'] ) ? $ai_response['description'] : '';

        if ( 'none' === $change_type || empty( $ai_response['can_do'] ) ) {
            return true;
        }

        $validation = $this->safety->validate_code( $code, $change_type );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $change_id = $this->changelog->log_change( $user_message, $description, $change_type, $file_target, $code );

        if ( 'css' === $change_type && 'custom-css' === $file_target ) {
            $this->apply_css_change( $change_id, $code );
            return $change_id;
        }

        if ( 'js' === $change_type && 'custom-js' === $file_target ) {
            $this->apply_js_change( $change_id, $code );
            return $change_id;
        }

        if ( 'php' === $change_type && 'functions-snippet' === $file_target ) {
            $this->apply_php_change( $change_id );
            return $change_id;
        }

        if ( 'option' === $change_type && ! empty( $file_target ) ) {
            $this->apply_option_change( $change_id, $file_target, $code );
            return $change_id;
        }

        return false;
    }

    private function apply_css_change( $change_id, $code ) {
        $original_value = wp_get_custom_css();
        $new_value      = $original_value . "\n" . $code;

        $this->changelog->create_snapshot( $change_id, 'css', '', $original_value, $new_value );
        wp_update_custom_css_post( $new_value );
        return true;
    }

    private function apply_js_change( $change_id, $code ) {
        $original_value = get_option( 'nhrada_custom_js', '' );
        $new_value      = $original_value . "\n" . $code;

        $this->changelog->create_snapshot( $change_id, 'option', 'nhrada_custom_js', $original_value, $new_value );
        update_option( 'nhrada_custom_js', $new_value );
        return true;
    }

    private function apply_php_change( $change_id ) {
        // Code is already stored in the log row by log_change(); just tag the snapshot type.
        $this->changelog->create_snapshot( $change_id, 'snippets', '', '', '' );
        self::rebuild_snippets_cache();
        return true;
    }

    private function apply_option_change( $change_id, $option_name, $value ) {
        $original_value = get_option( $option_name );
        $this->changelog->create_snapshot( $change_id, 'option', $option_name, $original_value, $value );
        update_option( $option_name, $value );
        return true;
    }

    /**
     * Rebuild the snippets cache file from all applied PHP change rows in the DB.
     * Called after every apply or undo of a PHP snippet.
     * If no snippets are active, deletes the cache file so require_once skips it cleanly.
     */
    public static function rebuild_snippets_cache() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $snippets = $wpdb->get_results(
            "SELECT id, code FROM {$wpdb->prefix}nhrada_log
             WHERE record_type = 'change' AND change_type = 'php' AND status = 'applied'
             ORDER BY id ASC"
        );
        // phpcs:enable

        if ( empty( $snippets ) ) {
            if ( file_exists( NHRADA_SNIPPETS_FILE ) ) {
                wp_delete_file( NHRADA_SNIPPETS_FILE );
            }
            return;
        }

        self::ensure_cache_dir();

        $content = "<?php\n// NHR AI Developer Assistant — Managed Snippets Cache\n// Auto-generated. Do not edit manually.\n\n";
        foreach ( $snippets as $snippet ) {
            $content .= "\n// [NHRAA-SNIPPET-{$snippet->id}]\n";
            $content .= $snippet->code . "\n";
            $content .= "// [/NHRAA-SNIPPET-{$snippet->id}]\n";
        }

        file_put_contents( NHRADA_SNIPPETS_FILE, $content );
    }

    /**
     * Create the uploads subdirectory with protection files on first write.
     * The uploads root .htaccess already denies PHP on Apache; we add our own
     * as a belt-and-suspenders for the subdirectory and for nginx awareness.
     */
    private static function ensure_cache_dir() {
        $dir = NHRADA_SNIPPETS_DIR;

        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        $index = $dir . '/index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\n# Silence is golden.\n" );
        }

        $htaccess = $dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "<Files *.php>\nOrder deny,allow\nDeny from all\n</Files>\n" );
        }
    }
}
