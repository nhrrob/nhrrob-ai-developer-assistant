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
$upload_dir   = wp_upload_dir();
$snippets_dir = $upload_dir['basedir'] . '/nhrada-ai-developer-assistant';
if (is_dir($snippets_dir)) {
    foreach (glob($snippets_dir . '/{,.}*', GLOB_BRACE) as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($snippets_dir);
}

// Delete options
delete_option('nhrada_settings');
delete_option('nhrada_custom_js');
delete_option('nhrada_nginx_notice_dismissed');
