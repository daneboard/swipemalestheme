<?php
/**
 * Uninstall handler.
 *
 * @package TikSwipe_Ad_Removal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'tsar_requests';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'tsar_settings' );
delete_option( 'tsar_db_version' );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s OR meta_key = %s", '_tsar_premium_expires', '_tsar_last_submission' ) );
