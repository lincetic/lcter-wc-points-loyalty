<?php
/**
 * Uninstall LCTER WC Points Loyalty plugin.
 *
 * @package LCTER_WC_Points_Loyalty
 */

// Exit if uninstall.php is not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lcter_wcpl_customer_points" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lcter_wcpl_transactions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lcter_wcpl_product_points" );

// Delete plugin options.
delete_option( 'lcter_wcpl_default_points_rate' );
delete_option( 'lcter_wcpl_points_expiry_days' );
delete_option( 'lcter_wcpl_enable_notifications' );
