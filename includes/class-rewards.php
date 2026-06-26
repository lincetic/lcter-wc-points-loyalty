<?php
/**
 * Rewards management class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewards class - manages rewards catalog and redemptions.
 */
class Rewards {
	const MINIMUM_ORDER_TOTAL_FOR_REDEMPTION = 60.0;

	/**
	 * Create a new reward.
	 *
	 * @param array $args Reward arguments.
	 * @return int Reward ID or 0 on failure.
	 */
	public static function create_reward( $args ): int {
		return Database::save_reward( (array) $args );
	}

	/**
	 * Get available rewards.
	 *
	 * @return array
	 */
	public static function get_available_rewards(): array {
		return Database::get_active_rewards();
	}

	/**
	 * Get one reward.
	 *
	 * @param int $reward_id Reward ID.
	 * @return array|null
	 */
	public static function get_reward( int $reward_id ): ?array {
		return Database::get_reward( $reward_id );
	}

	/**
	 * Save a reward.
	 *
	 * @param array $reward Reward data.
	 * @return int
	 */
	public static function save_reward( array $reward ): int {
		return Database::save_reward( $reward );
	}

	/**
	 * Delete a reward.
	 *
	 * @param int $reward_id Reward ID.
	 * @return bool
	 */
	public static function delete_reward( int $reward_id ): bool {
		return Database::delete_reward( $reward_id );
	}

	/**
	 * Get customer available rewards.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public static function get_customer_available_rewards( $customer_id ): array {
		$customer_points   = Database::get_customer_points( (int) $customer_id );
		$available_rewards = self::get_available_rewards();

		return array_values(
			array_filter(
				$available_rewards,
				function( $reward ) use ( $customer_points ) {
					return (int) $reward['points_cost'] <= $customer_points;
				}
			)
		);
	}

	/**
	 * Get redeemed rewards for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function get_order_rewards( int $order_id ): array {
		return Database::get_order_rewards( $order_id );
	}

	/**
	 * Get redeemed rewards for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public static function get_customer_rewards( int $customer_id ): array {
		return Database::get_customer_rewards( $customer_id );
	}

	/**
	 * Get redeemed rewards by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function get_rewards_by_order_id( int $order_id ): array {
		return Database::get_rewards_by_order_id( $order_id );
	}

	/**
	 * Check whether an order can redeem rewards.
	 *
	 * @param mixed $order WooCommerce order.
	 * @return bool
	 */
	public static function order_meets_redemption_minimum( $order ): bool {
		if ( ! $order ) {
			return false;
		}

		return (float) $order->get_total() >= self::MINIMUM_ORDER_TOTAL_FOR_REDEMPTION;
	}

	/**
	 * Redeem a reward and add it to an order as a zero-cost gift line.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param mixed $order WooCommerce order.
	 * @param int   $reward_id Reward ID.
	 * @param int   $quantity Quantity.
	 * @return bool
	 */
	public static function redeem_reward_for_order( int $customer_id, $order, int $reward_id, int $quantity = 1 ): bool {
		if ( $customer_id <= 0 || $quantity <= 0 || ! self::order_meets_redemption_minimum( $order ) ) {
			return false;
		}

		$reward = Database::get_reward( $reward_id );
		if ( ! $reward || empty( $reward['active'] ) ) {
			return false;
		}

		$product = wc_get_product( (int) $reward['product_id'] );
		if ( ! $product ) {
			return false;
		}

		$points_cost_each  = (int) $reward['points_cost'];
		$points_cost_total = $points_cost_each * $quantity;
		if ( Database::get_customer_points( $customer_id ) < $points_cost_total ) {
			return false;
		}

		$item_id = $order->add_product(
			$product,
			$quantity,
			array(
				'subtotal' => 0,
				'total'    => 0,
			)
		);

		if ( ! $item_id ) {
			return false;
		}

		$item = $order->get_item( $item_id );
		if ( $item ) {
			$item->add_meta_data( 'REGALO', '1', true );
			$item->add_meta_data( '_lcter_wcpl_reward_id', $reward_id, true );
			$item->add_meta_data( '_lcter_wcpl_points_cost_each', $points_cost_each, true );
			$item->add_meta_data( '_lcter_wcpl_points_cost_total', $points_cost_total, true );
			$item->add_meta_data( '_lcter_wcpl_clientify_reward', '1', true );
			$item->save();
		}

		$redeemed = Points_Service::redeem_points(
			$customer_id,
			$points_cost_total,
			(int) $order->get_id(),
			(int) $item_id,
			sprintf( 'Regalo canjeado: %s x %d.', $product->get_name(), $quantity ),
			array(
				'clientify' => array(
					'type' => 'order_reward',
				),
				'reward'    => array(
					'reward_id'         => $reward_id,
					'product_id'        => (int) $reward['product_id'],
					'quantity'          => $quantity,
					'points_cost_each'  => $points_cost_each,
					'points_cost_total' => $points_cost_total,
					'product_name'      => $product->get_name(),
					'sku'               => $product->get_sku(),
					'order_item'        => array(
						'id'    => (int) $item_id,
						'label' => 'REGALO',
					),
				),
			)
		);

		if ( ! $redeemed ) {
			$order->remove_item( $item_id );
			$order->save();
			return false;
		}

		$order_reward_id = Database::insert_order_reward(
			array(
				'order_id'          => (int) $order->get_id(),
				'customer_id'       => $customer_id,
				'product_id'        => (int) $reward['product_id'],
				'order_item_id'     => (int) $item_id,
				'reward_id'         => $reward_id,
				'quantity'          => $quantity,
				'points_cost_each'  => $points_cost_each,
				'points_cost_total' => $points_cost_total,
				'product_name'      => $product->get_name(),
				'sku'               => $product->get_sku(),
			)
		);

		if ( $order_reward_id <= 0 ) {
			Database::add_customer_points(
				$customer_id,
				$points_cost_total,
				Database::TRANSACTION_REFUND,
				(int) $order->get_id(),
				(int) $item_id,
				'woocommerce_reward',
				sprintf( 'Devolucion de puntos por fallo al registrar regalo: %s x %d.', $product->get_name(), $quantity ),
				array(
					'reward_id'         => $reward_id,
					'product_id'        => (int) $reward['product_id'],
					'quantity'          => $quantity,
					'points_cost_total' => $points_cost_total,
				)
			);

			$order->remove_item( $item_id );
			$order->save();
			return false;
		}

		self::add_order_reward_metadata(
			$order,
			array(
				'id'                => $order_reward_id,
				'reward_id'         => $reward_id,
				'product_id'        => (int) $reward['product_id'],
				'order_item_id'     => (int) $item_id,
				'quantity'          => $quantity,
				'points_cost_each'  => $points_cost_each,
				'points_cost_total' => $points_cost_total,
				'product_name'      => $product->get_name(),
				'sku'               => $product->get_sku(),
				'label'             => 'REGALO',
			)
		);

		if ( $item ) {
			$item->add_meta_data( '_lcter_wcpl_order_reward_id', $order_reward_id, true );
			$item->save();
		}

		$order->calculate_totals();
		$order->save();

		return true;
	}

	/**
	 * Add structured order metadata for external integrations.
	 *
	 * @param mixed $order WooCommerce order.
	 * @param array $reward_data Reward data.
	 */
	private static function add_order_reward_metadata( $order, array $reward_data ): void {
		$current = $order->get_meta( '_lcter_wcpl_order_rewards' );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$current[] = $reward_data;

		$order->update_meta_data( '_lcter_wcpl_has_rewards', '1' );
		$order->update_meta_data( '_lcter_wcpl_order_rewards', $current );
	}
}
