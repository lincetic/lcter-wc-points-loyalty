<?php
/**
 * WooCommerce integration class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL;

use function add_action;
use function wc_get_order;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce integration class.
 */
class WooCommerce {

	/**
	 * Register WooCommerce-specific hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_order_paid' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_order_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_paid' ) );
	}

	/**
	 * When an order is paid, award points.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_order_paid( int $order_id ): void {
		Points_Service::handle_order_paid( $order_id );
	}
}
