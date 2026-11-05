<?php
// Удаление плагина (не отключение): убираем таблицы и настройки.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}marmar_carts" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}marmar_review_requests" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}marmar_optout" ); // phpcs:ignore
delete_option( 'marmar_emails_settings' );
delete_option( 'marmar_emails_db_version' );
delete_option( 'marmar_emails_migrated' );
