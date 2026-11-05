<?php
// Удаление плагина: таблица разговоров и настройки.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}marmar_chat" ); // phpcs:ignore
delete_option( 'marmar_chat_settings' );
delete_option( 'marmar_chat_db_version' );
delete_option( 'marmar_chat_catalog_stale' );
delete_transient( 'marmar_chat_catalog_v1' );
