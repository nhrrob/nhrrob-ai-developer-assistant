<?php
namespace NHR\AIAssistant\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Changelog {

    public function log_change( $request_msg, $description, $change_type, $file_target ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'nhraa_changes',
            array(
                'request'     => $request_msg,
                'description' => $description,
                'change_type' => $change_type,
                'file_target' => $file_target,
                'created_at'  => current_time( 'mysql' ),
                'status'      => 'applied'
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    public function create_snapshot( $change_id, $snapshot_type, $target_key, $original_value, $new_value ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'nhraa_snapshots',
            array(
                'change_id'      => $change_id,
                'snapshot_type'  => $snapshot_type,
                'target_key'     => $target_key,
                'original_value' => $original_value,
                'new_value'      => $new_value,
                'created_at'     => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }
}
