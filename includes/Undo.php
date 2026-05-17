<?php
namespace Nhrada\AIDeveloperAssistant;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Undo {

    public function revert_change( $change_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $change = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nhrada_log WHERE id = %d AND record_type = 'change' AND status = 'applied'",
            $change_id
        ) );

        if ( ! $change ) {
            return new WP_Error( 'undo_failed', 'Change not found or already undone.' );
        }

        if ( ! $change->snapshot_type ) {
            return new WP_Error( 'undo_failed', 'No snapshot found for this change.' );
        }

        if ( 'option' === $change->snapshot_type ) {
            $success = $this->revert_option( $change->target_key, $change->original_value );
        } elseif ( 'css' === $change->snapshot_type ) {
            $success = $this->revert_css( $change->original_value );
        } elseif ( 'snippets' === $change->snapshot_type ) {
            // Mark undone in DB first so the cache rebuild excludes this snippet.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'nhrada_log',
                array( 'status' => 'undone' ),
                array( 'id' => $change_id ),
                array( '%s' ),
                array( '%d' )
            );
            Executor::rebuild_snippets_cache();
            return true;
        } else {
            return new WP_Error( 'undo_failed', 'Unknown snapshot type.' );
        }

        if ( is_wp_error( $success ) ) {
            return $success;
        }

        if ( $success ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'nhrada_log',
                array( 'status' => 'undone' ),
                array( 'id' => $change_id ),
                array( '%s' ),
                array( '%d' )
            );
            return true;
        }

        return new WP_Error( 'undo_failed', 'Failed to revert the change.' );
    }

    private function revert_option( $option_name, $original_value ) {
        update_option( $option_name, $original_value );
        return true;
    }

    private function revert_css( $original_value ) {
        wp_update_custom_css_post( $original_value );
        return true;
    }
}
