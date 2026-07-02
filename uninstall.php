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

$remove_data = get_option( 'lcter_wcpl_remove_data_on_uninstall', '0' );

if ( '1' === $remove_data ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lcter_wcpl_customer_points" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lcter_wcpl_transactions" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lcter_wcpl_rewards" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lcter_wcpl_order_rewards" );

	delete_option( 'lcter_wcpl_points_expiry_days' );
	delete_option( 'lcter_wcpl_initial_bonus_points' );
	delete_option( 'lcter_wcpl_reward_cost_multiplier' );
	delete_option( 'lcter_wcpl_enable_notifications' );
	delete_option( 'lcter_wcpl_remove_data_on_uninstall' );
	delete_option( 'lcter_wcpl_schema_version' );
}
