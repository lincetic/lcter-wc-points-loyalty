<?php
/**
 * Points management compatibility class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Points class - keeps the public API while delegating to the database layer.
 */
class Points {

	/**
	 * Create database tables on plugin activation.
	 */
	public static function create_tables(): void {
		Database::create_tables();
	}

	/**
	 * Award points for a paid order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function award_points_for_order( $order_id ): void {
		Points_Service::handle_order_paid( (int) $order_id );
	}

	/**
	 * Add points to a customer account.
	 *
	 * @param int         $customer_id Customer ID.
	 * @param int         $points Points to add.
	 * @param string      $type Transaction type.
	 * @param int|null    $order_id Order ID.
	 * @param int|null    $order_item_id Order item ID.
	 * @param string|null $description Optional description.
	 * @return bool
	 */
	public static function add_customer_points( $customer_id, $points, $type = Database::TRANSACTION_MANUAL_ADJUSTMENT, $order_id = null, $order_item_id = null, $description = null ): bool {
		return Database::add_customer_points(
			(int) $customer_id,
			(int) $points,
			(string) $type,
			null === $order_id ? null : (int) $order_id,
			null === $order_item_id ? null : (int) $order_item_id,
			'points_api',
			$description
		);
	}

	/**
	 * Redeem points from a customer account.
	 *
	 * @param int         $customer_id Customer ID.
	 * @param int         $points Points to redeem.
	 * @param int|null    $order_id Order ID.
	 * @param int|null    $order_item_id Order item ID.
	 * @param string|null $description Optional description.
	 * @return bool
	 */
	public static function redeem_customer_points( $customer_id, $points, $order_id = null, $order_item_id = null, $description = null ): bool {
		return Points_Service::redeem_points(
			(int) $customer_id,
			(int) $points,
			null === $order_id ? null : (int) $order_id,
			null === $order_item_id ? null : (int) $order_item_id,
			$description
		);
	}

	/**
	 * Get customer current points.
	 *
	 * @param int $customer_id Customer ID.
	 * @return int
	 */
	public static function get_customer_points( $customer_id ): int {
		return Database::get_customer_points( (int) $customer_id );
	}
}
