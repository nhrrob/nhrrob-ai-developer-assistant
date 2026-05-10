<?php
namespace NHR\AIAssistant;

use NHR\AIAssistant\Database\Changelog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Executor {

    private $changelog;
    private $safety;

    public function __construct() {
        $this->changelog = new Changelog();
        $this->safety = new Safety();
    }

    public function apply_change( $user_message, $ai_response ) {
        $change_type = isset($ai_response['change_type']) ? $ai_response['change_type'] : 'none';
        $file_target = isset($ai_response['file_target']) ? $ai_response['file_target'] : null;
        $code        = isset($ai_response['code']) ? $ai_response['code'] : '';
        $description = isset($ai_response['description']) ? $ai_response['description'] : '';

        if ( 'none' === $change_type || empty($ai_response['can_do']) ) {
            return true; // Nothing to apply
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
            $this->apply_php_change( $change_id, $code );
            return $change_id;
        }

        if ( 'option' === $change_type && !empty($file_target) ) {
            $this->apply_option_change( $change_id, $file_target, $code );
            return $change_id;
        }

        return false;
    }

    private function apply_css_change( $change_id, $code ) {
        $custom_css = wp_get_custom_css();
        $original_value = $custom_css;
        $new_value = $custom_css . "\n" . $code;
        
        $this->changelog->create_snapshot( $change_id, 'option', 'custom_css_post_id', $original_value, $new_value );
        
        wp_update_custom_css_post( $new_value );
        return true;
    }

    private function apply_js_change( $change_id, $code ) {
        $original_value = get_option( 'nhraa_custom_js', '' );
        $new_value = $original_value . "\n" . $code;

        $this->changelog->create_snapshot( $change_id, 'option', 'nhraa_custom_js', $original_value, $new_value );
        update_option( 'nhraa_custom_js', $new_value );
        return true;
    }

    private function apply_php_change( $change_id, $code ) {
        $snippets_file = WP_CONTENT_DIR . '/nhraa-snippets.php';
        $original_value = file_exists( $snippets_file ) ? file_get_contents( $snippets_file ) : "<?php\n// WP AI Developer — Managed Snippets File\n// Do not edit manually. Use the AI Developer plugin to manage.\n\n";
        
        $block = "\n// [NHRAA-SNIPPET-{$change_id} | " . gmdate('Y-m-d') . "]\n{$code}\n// [/NHRAA-SNIPPET-{$change_id}]\n";
        $new_value = $original_value . $block;

        $this->changelog->create_snapshot( $change_id, 'file', 'nhraa-snippets.php', $original_value, $new_value );
        file_put_contents( $snippets_file, $new_value );
        return true;
    }

    private function apply_option_change( $change_id, $option_name, $value ) {
        $original_value = get_option( $option_name );
        $this->changelog->create_snapshot( $change_id, 'option', $option_name, $original_value, $value );
        update_option( $option_name, $value );
        return true;
    }
}
