<?php
/**
 * Uninstall GT Link Manager.
 *
 * @package GTLinkManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gt_link_license' );
delete_option( 'gt_link_license_last_check' );
delete_transient( 'gt_link_update_info' );
delete_transient( 'gt_link_update_check_result' );
