<?php
/**
 * Backward-compatible points service facade.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

use LCTER_WCPL\Adapters\WooCommerce_Orders_Adapter;
use LCTER_WCPL\Services\Points_Service as Application_Points_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Points_Service {
	public static function handle_order_paid( int $order_id ): void {
		( new WooCommerce_Orders_Adapter() )->on_order_paid( $order_id );
	}

	public static function handle_order_completed( int $order_id ): void {
		self::handle_order_paid( $order_id );
	}

	public static function calculate_points_for_order( $order ): int {
		return ( new WooCommerce_Orders_Adapter() )->calculate_points_for_order( $order );
	}

	public static function redeem_points(
		int $customer_id,
		int $points,
		?int $order_id = null,
		?int $order_item_id = null,
		?string $description = null,
		$metadata = null
	): bool {
		return ( new Application_Points_Service() )->redeem_points(
			$customer_id,
			$points,
			$order_id,
			$order_item_id,
			$description,
			$metadata
		);
	}

	public static function get_customer_points( int $customer_id ): int {
		return ( new Application_Points_Service() )->get_balance( $customer_id );
	}
}
