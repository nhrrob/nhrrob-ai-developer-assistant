<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following:
 * - This setting should be used to clean up any database tables or settings
 *   that the plugin has created.
 * - This file is ONLY called when the plugin is DELETED, not deactivated.
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}nhrada_log");
// phpcs:enable

// Remove the managed snippets cache directory from uploads
$nhrada_upload_dir   = wp_upload_dir();
$nhrada_snippets_dir = $nhrada_upload_dir['basedir'] . '/nhrada-ai-developer-assistant';
if ( is_dir( $nhrada_snippets_dir ) ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    global $wp_filesystem;
    foreach ( glob( $nhrada_snippets_dir . '/{,.}*', GLOB_BRACE ) as $nhrada_file ) {
        if ( is_file( $nhrada_file ) ) {
            wp_delete_file( $nhrada_file );
        }
    }
    $wp_filesystem->rmdir( $nhrada_snippets_dir );
}

// Delete options
delete_option('nhrada_settings');
delete_option('nhrada_custom_js');
delete_option('nhrada_nginx_notice_dismissed');
