<?php
/**
 * Backward-compatible WooCommerce adapter facade.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

use LCTER_WCPL\Adapters\WooCommerce_Orders_Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerce {
	private static ?WooCommerce_Orders_Adapter $orders_adapter = null;

	public static function init(): void {
		self::$orders_adapter = self::$orders_adapter ?? new WooCommerce_Orders_Adapter();
		self::$orders_adapter->register_hooks();
	}

	public static function on_order_paid( int $order_id ): void {
		( new WooCommerce_Orders_Adapter() )->on_order_paid( $order_id );
	}
}
