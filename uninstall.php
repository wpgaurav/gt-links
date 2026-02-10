<?php
/**
 * Uninstall GT Link Manager.
 *
 * @package GTLinkManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$links_table = $wpdb->prefix . 'gt_links';
$cats_table  = $wpdb->prefix . 'gt_link_categories';

$wpdb->query(
	$wpdb->prepare(
		'DROP TABLE IF EXISTS %i',
		$links_table
	)
);

$wpdb->query(
	$wpdb->prepare(
		'DROP TABLE IF EXISTS %i',
		$cats_table
	)
);

delete_option( 'gt_link_manager_settings' );
