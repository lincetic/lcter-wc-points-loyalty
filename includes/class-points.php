<?php
/**
 * Backward-compatible points API facade.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

use LCTER_WCPL\Adapters\WooCommerce_Orders_Adapter;
use LCTER_WCPL\Services\Points_Service as Application_Points_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Points {
	public static function create_tables(): void {
		Database::install_or_upgrade();
	}

	public static function award_points_for_order( $order_id ): void {
		( new WooCommerce_Orders_Adapter() )->on_order_paid( (int) $order_id );
	}

	public static function add_customer_points(
		$customer_id,
		$points,
		$type = Database::TRANSACTION_MANUAL_ADJUSTMENT,
		$order_id = null,
		$order_item_id = null,
		$description = null
	): bool {
		return ( new Application_Points_Service() )->add_points(
			(int) $customer_id,
			(int) $points,
			(string) $type,
			null === $order_id ? null : (int) $order_id,
			null === $order_item_id ? null : (int) $order_item_id,
			'points_api',
			$description
		);
	}

	public static function redeem_customer_points(
		$customer_id,
		$points,
		$order_id = null,
		$order_item_id = null,
		$description = null
	): bool {
		return ( new Application_Points_Service() )->redeem_points(
			(int) $customer_id,
			(int) $points,
			null === $order_id ? null : (int) $order_id,
			null === $order_item_id ? null : (int) $order_item_id,
			$description
		);
	}

	public static function get_customer_points( $customer_id ): int {
		return ( new Application_Points_Service() )->get_balance( (int) $customer_id );
	}
}
