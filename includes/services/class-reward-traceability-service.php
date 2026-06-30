<?php
/**
 * Read-only traceability service for redeemed rewards.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Services;

use LCTER_WCPL\Repositories\Order_Rewards_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reward_Traceability_Service {
	private Order_Rewards_Repository $order_rewards;

	public function __construct( ?Order_Rewards_Repository $order_rewards = null ) {
		$this->order_rewards = $order_rewards ?? new Order_Rewards_Repository();
	}

	public function get_rewards_by_order( int $order_id ): array {
		if ( $order_id <= 0 ) {
			return array();
		}

		return array_map( array( $this, 'normalize_reward' ), $this->order_rewards->find_by_order( $order_id ) );
	}

	public function get_rewards_by_customer( int $customer_id ): array {
		if ( $customer_id <= 0 ) {
			return array();
		}

		return array_map( array( $this, 'normalize_reward' ), $this->order_rewards->find_by_customer( $customer_id ) );
	}

	/**
	 * Build a transport-neutral payload for a future integration.
	 */
	public function get_integration_payload_by_order( int $order_id ): array {
		$rewards = $this->get_rewards_by_order( $order_id );

		return array(
			'source'       => 'lcter_wcpl_order_rewards',
			'order_id'     => $order_id,
			'total_items'  => array_sum( array_column( $rewards, 'quantity' ) ),
			'total_points' => array_sum( array_column( $rewards, 'points_cost_total' ) ),
			'rewards'      => $rewards,
		);
	}

	/**
	 * Build a transport-neutral payload for a future integration.
	 */
	public function get_integration_payload_by_customer( int $customer_id ): array {
		$rewards = $this->get_rewards_by_customer( $customer_id );

		return array(
			'source'       => 'lcter_wcpl_order_rewards',
			'customer_id'  => $customer_id,
			'total_items'  => array_sum( array_column( $rewards, 'quantity' ) ),
			'total_points' => array_sum( array_column( $rewards, 'points_cost_total' ) ),
			'rewards'      => $rewards,
		);
	}

	private function normalize_reward( array $reward ): array {
		return array(
			'order_reward_id'  => isset( $reward['id'] ) ? (int) $reward['id'] : 0,
			'order_id'         => isset( $reward['order_id'] ) ? (int) $reward['order_id'] : 0,
			'customer_id'      => isset( $reward['customer_id'] ) ? (int) $reward['customer_id'] : 0,
			'product_id'       => isset( $reward['product_id'] ) ? (int) $reward['product_id'] : 0,
			'order_item_id'    => isset( $reward['order_item_id'] ) ? (int) $reward['order_item_id'] : 0,
			'reward_id'        => isset( $reward['reward_id'] ) ? (int) $reward['reward_id'] : 0,
			'quantity'         => isset( $reward['quantity'] ) ? (int) $reward['quantity'] : 0,
			'points_cost_each' => isset( $reward['points_cost_each'] ) ? (int) $reward['points_cost_each'] : 0,
			'points_cost_total' => isset( $reward['points_cost_total'] ) ? (int) $reward['points_cost_total'] : 0,
			'product_name'     => isset( $reward['product_name'] ) ? (string) $reward['product_name'] : '',
			'sku'              => isset( $reward['sku'] ) ? (string) $reward['sku'] : '',
			'created_at'       => isset( $reward['created_at'] ) ? (string) $reward['created_at'] : '',
		);
	}
}
