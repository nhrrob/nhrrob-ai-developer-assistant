<?php
namespace Nhrada\AIDeveloperAssistant;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function maybe_migrate_settings() {
        if ( false !== get_option( 'nhrada_settings' ) ) {
            return;
        }
        $legacy = array(
            'ai_provider'         => 'nhrada_ai_provider',
            'claude_api_key'      => 'nhrada_claude_api_key',
            'openai_api_key'      => 'nhrada_openai_api_key',
            'gemini_api_key'      => 'nhrada_gemini_api_key',
            'claude_model'        => 'nhrada_claude_model',
            'openai_model'        => 'nhrada_openai_model',
            'gemini_model'        => 'nhrada_gemini_model',
            'custom_instructions' => 'nhrada_custom_instructions',
            'debug_mode'          => 'nhrada_debug_mode',
        );
        $settings = array();
        foreach ( $legacy as $new_key => $old_key ) {
            $value = get_option( $old_key, null );
            if ( null !== $value ) {
                $settings[ $new_key ] = $value;
            }
            delete_option( $old_key );
        }
        update_option( 'nhrada_settings', $settings );
    }

    public static function activate() {
        self::maybe_migrate_settings();
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$wpdb->prefix}nhrada_log (
            id                bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            record_type       varchar(20) NOT NULL,
            request           text DEFAULT NULL,
            description       text DEFAULT NULL,
            change_type       varchar(50) DEFAULT NULL,
            file_target       varchar(255) DEFAULT NULL,
            code              longtext DEFAULT NULL,
            status            varchar(20) DEFAULT NULL,
            snapshot_type     varchar(20) DEFAULT NULL,
            target_key        varchar(500) DEFAULT NULL,
            original_value    longtext DEFAULT NULL,
            new_value         longtext DEFAULT NULL,
            role              varchar(10) DEFAULT NULL,
            content           text DEFAULT NULL,
            change_id         bigint(20) unsigned DEFAULT NULL,
            created_at        datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY record_type (record_type),
            KEY change_id (change_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
