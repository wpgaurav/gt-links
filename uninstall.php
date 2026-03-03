<?php
/**
 * Uninstall GT Link Manager.
 *
 * Removes plugin options and custom tables when the plugin is deleted.
 *
 * @package GTLinkManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'gt_link_manager_settings' );
delete_option( 'gt_link_manager_db_version' );
delete_option( 'gt_link_manager_diagnostics' );

// License options.
delete_option( 'gt_link_license' );
delete_option( 'gt_link_license_last_check' );
delete_transient( 'gt_link_update_info' );
delete_transient( 'gt_link_update_check_result' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gt_links" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gt_link_categories" );
