<?php
namespace NHR\AIAssistant;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE {$wpdb->prefix}nhraa_changes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request text NOT NULL,
            description text NOT NULL,
            change_type varchar(50) NOT NULL,
            file_target varchar(255),
            created_at datetime NOT NULL,
            status varchar(20) DEFAULT 'applied',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql2 = "CREATE TABLE {$wpdb->prefix}nhraa_snapshots (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            change_id bigint(20) unsigned NOT NULL,
            snapshot_type varchar(20) NOT NULL,
            target_key varchar(500) NOT NULL,
            original_value longtext,
            new_value longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY change_id (change_id)
        ) $charset_collate;";

        $sql3 = "CREATE TABLE {$wpdb->prefix}nhraa_messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            role varchar(10) NOT NULL,
            content text NOT NULL,
            change_id bigint(20) unsigned,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
    }
}
