<?php
/**
 * Business logic for points management.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Points service class.
 */
class Points_Service {
	const ORDER_POINTS_AWARDED_META = '_lcter_wcpl_points_awarded';
	const POINTS_PER_CURRENCY_UNIT  = 100;

	/**
	 * Handle paid order event.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function handle_order_paid( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id <= 0 || $order->get_meta( self::ORDER_POINTS_AWARDED_META ) ) {
			return;
		}

		$total_points = self::calculate_points_for_order( $order );
		if ( $total_points <= 0 ) {
			return;
		}

		$added = Database::add_customer_points(
			$customer_id,
			$total_points,
			Database::TRANSACTION_EARNED,
			$order_id,
			null,
			'woocommerce_order',
			sprintf( 'Puntos generados por el pedido #%s.', $order->get_order_number() ),
			array(
				'order_total'    => (float) $order->get_total(),
				'shipping_total' => (float) $order->get_shipping_total(),
				'shipping_tax'   => (float) $order->get_shipping_tax(),
			)
		);

		if ( $added ) {
			$order->update_meta_data( self::ORDER_POINTS_AWARDED_META, $total_points );
			$order->save();
		}
	}

	/**
	 * Backward-compatible completed order handler.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function handle_order_completed( int $order_id ): void {
		self::handle_order_paid( $order_id );
	}

	/**
	 * Calculate earned points from order total including tax and excluding shipping.
	 *
	 * @param \WC_Order $order Order.
	 * @return int
	 */
	public static function calculate_points_for_order( $order ): int {
		$eligible_total = (float) $order->get_total() - (float) $order->get_shipping_total() - (float) $order->get_shipping_tax();
		$eligible_total = max( 0, $eligible_total );

		return (int) round( $eligible_total * self::POINTS_PER_CURRENCY_UNIT );
	}

	/**
	 * Redeem points from a customer.
	 *
	 * @param int         $customer_id Customer ID.
	 * @param int         $points Points to redeem.
	 * @param int|null    $order_id Order ID.
	 * @param int|null    $order_item_id Order item ID.
	 * @param string|null $description Optional description.
	 * @param mixed       $metadata Optional metadata.
	 * @return bool
	 */
	public static function redeem_points(
		int $customer_id,
		int $points,
		?int $order_id = null,
		?int $order_item_id = null,
		?string $description = null,
		$metadata = null
	): bool {
		if ( $points <= 0 || self::get_customer_points( $customer_id ) < $points ) {
			return false;
		}

		return Database::redeem_customer_points(
			$customer_id,
			$points,
			Database::TRANSACTION_REDEEMED,
			$order_id,
			$order_item_id,
			'woocommerce_reward',
			$description,
			$metadata
		);
	}

	/**
	 * Get customer points balance.
	 *
	 * @param int $customer_id Customer ID.
	 * @return int
	 */
	public static function get_customer_points( int $customer_id ): int {
		return Database::get_customer_points( $customer_id );
	}
}
