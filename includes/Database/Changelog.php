<?php
namespace Nhrada\AIDeveloperAssistant\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Changelog {

    public function log_change( $request_msg, $description, $change_type, $file_target, $code = '' ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $wpdb->prefix . 'nhrada_log',
            array(
                'record_type' => 'change',
                'request'     => $request_msg,
                'description' => $description,
                'change_type' => $change_type,
                'file_target' => $file_target,
                'code'        => $code,
                'status'      => 'applied',
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    public function create_snapshot( $change_id, $snapshot_type, $target_key, $original_value, $new_value ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'nhrada_log',
            array(
                'snapshot_type'  => $snapshot_type,
                'target_key'     => $target_key,
                'original_value' => $original_value,
                'new_value'      => $new_value,
            ),
            array( 'id' => $change_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }
}
