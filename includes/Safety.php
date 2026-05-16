<?php
namespace Nhrada\AIDeveloperAssistant;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Safety {

    public function validate_code( $code, $change_type ) {
        if ( empty( $code ) || 'none' === $change_type ) {
            return true;
        }

        // Length limit
        if ( strlen( $code ) > 5000 ) {
            return new WP_Error( 'safety_failed', 'Generated code is too long (over 5000 characters).' );
        }

        if ( 'php' === $change_type ) {
            return $this->validate_php( $code );
        }

        return true;
    }

    private function validate_php( $code ) {
        // Blacklist patterns
        $blacklist = array(
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
            '/system\s*\(/i',
            '/passthru\s*\(/i',
            '/DROP\s+TABLE/i',
            '/TRUNCATE\s+TABLE/i',
            '/DELETE\s+FROM/i',
            '/wp-config\.php/i',
            '/define\s*\(/i',
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i' // Basic obfuscation prevention
        );

        foreach ( $blacklist as $pattern ) {
            if ( preg_match( $pattern, $code ) ) {
                return new WP_Error( 'safety_failed', 'Generated code contains unsafe functions or patterns.' );
            }
        }

        // Simple syntax check if PHP CLI is available (optional, but good for safety)
        // Since we can't reliably do `php -l` on all environments from web server, we rely on the blacklist and Claude's own constraints.

        return true;
    }
}
