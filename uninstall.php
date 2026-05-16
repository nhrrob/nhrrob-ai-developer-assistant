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

// Delete options
delete_option('nhrada_ai_provider');
delete_option('nhrada_claude_api_key');
delete_option('nhrada_openai_api_key');
delete_option('nhrada_gemini_api_key');
delete_option('nhrada_claude_model');
delete_option('nhrada_openai_model');
delete_option('nhrada_gemini_model');
delete_option('nhrada_debug_mode');
delete_option('nhrada_custom_js');
