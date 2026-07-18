<?php
/**
 * Application service for reward selection and paid-order redemption.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Services;

use LCTER_WCPL\Database;
use LCTER_WCPL\Repositories\Order_Rewards_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reward_Redemption_Service {
	const MINIMUM_SUBTOTAL_CENTS = 6000;

	private Rewards_Service $rewards;
	private Points_Service $points;
	private Order_Rewards_Repository $order_rewards;

	public function __construct(
		?Rewards_Service $rewards = null,
		?Points_Service $points = null,
		?Order_Rewards_Repository $order_rewards = null
	) {
		$this->rewards       = $rewards ?? new Rewards_Service();
		$this->points        = $points ?? new Points_Service();
		$this->order_rewards = $order_rewards ?? new Order_Rewards_Repository();
	}

	/**
	 * Validate a customer selection using current database rewards and balance.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $quantities Reward ID => positive integer quantity.
	 * @param int   $eligible_subtotal_cents Cart subtotal including tax, excluding shipping.
	 * @return array
	 */
	public function validate_selection( int $customer_id, array $quantities, int $eligible_subtotal_cents, int $excluded_balance_points = 0 ): array {
		if ( $customer_id <= 0 ) {
			return $this->invalid_result( 'invalid_customer' );
		}

		if ( $eligible_subtotal_cents < self::MINIMUM_SUBTOTAL_CENTS ) {
			return $this->invalid_result( 'minimum_not_met' );
		}

		$available = array();
		foreach ( $this->rewards->get_available_rewards() as $reward ) {
			$available[ (int) $reward['id'] ] = $reward;
		}

		$items        = array();
		$total_points = 0;

		foreach ( $quantities as $reward_id => $quantity ) {
			$reward_id = (int) $reward_id;
			if ( ! is_int( $quantity ) || $quantity < 0 ) {
				return $this->invalid_result( 'invalid_quantity' );
			}

			if ( 0 === $quantity ) {
				continue;
			}

			if ( $reward_id <= 0 || ! isset( $available[ $reward_id ] ) ) {
				return $this->invalid_result( 'reward_unavailable' );
			}

			$reward    = $available[ $reward_id ];
			$cost_each = (int) $reward['points_cost'];
			if ( $cost_each <= 0 || $quantity > intdiv( PHP_INT_MAX, $cost_each ) ) {
				return $this->invalid_result( 'invalid_cost' );
			}

			$cost_total = $cost_each * $quantity;
			if ( $cost_total > PHP_INT_MAX - $total_points ) {
				return $this->invalid_result( 'invalid_cost' );
			}
			$total_points += $cost_total;

			$items[ $reward_id ] = array(
				'reward_id'         => $reward_id,
				'product_id'        => (int) $reward['product_id'],
				'quantity'          => $quantity,
				'points_cost_each'  => $cost_each,
				'points_cost_total' => $cost_total,
			);
		}

		if ( empty( $items ) ) {
			return $this->invalid_result( 'empty_selection' );
		}

		$balance = max( 0, $this->points->get_balance( $customer_id ) - max( 0, $excluded_balance_points ) );
		if ( $balance < $total_points ) {
			return $this->invalid_result( 'insufficient_balance', $balance );
		}

		return array(
			'valid'        => true,
			'error'        => '',
			'balance'      => $balance,
			'total_points' => $total_points,
			'items'        => $items,
		);
	}

	public function is_order_redeemed( int $order_id, int $cycle = 1 ): bool {
		$cycle = max( 1, $cycle );
		return $order_id > 0 && (
			$this->points->has_idempotency_key( Points_Service::cycle_key( 'redeemed_order', $order_id, $cycle ) ) ||
			( 1 === $cycle && ( $this->points->has_idempotency_key( 'redeemed_order:' . $order_id ) || $this->points->has_order_transaction( $order_id, Database::TRANSACTION_REDEEMED ) ) )
		);
	}

	public function get_order_earned_points( int $order_id, int $cycle = 1 ): int {
		return $this->points->get_order_earned_points_for_cycle( $order_id, $cycle );
	}

	/**
	 * Redeem a validated paid order and persist every reward idempotently.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param int   $order_id Order ID.
	 * @param array $items Enriched reward rows keyed by reward ID.
	 * @return array
	 */
	public function redeem_paid_order( int $customer_id, int $order_id, array $items, int $cycle = 1 ): array {
		if ( $customer_id <= 0 || $order_id <= 0 || empty( $items ) ) {
			return array(
				'success'    => false,
				'error'      => 'invalid_order',
				'record_ids' => array(),
			);
		}

		$total_points = 0;
		foreach ( $items as $item ) {
			$quantity   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$cost_each  = isset( $item['points_cost_each'] ) ? (int) $item['points_cost_each'] : 0;
			$cost_total = isset( $item['points_cost_total'] ) ? (int) $item['points_cost_total'] : 0;

			if ( $quantity <= 0 || $cost_each <= 0 || $cost_total !== $quantity * $cost_each ) {
				return array(
					'success'    => false,
					'error'      => 'invalid_items',
					'record_ids' => array(),
				);
			}

			if ( $cost_total > PHP_INT_MAX - $total_points ) {
				return array(
					'success'    => false,
					'error'      => 'invalid_items',
					'record_ids' => array(),
				);
			}

			$total_points += $cost_total;
		}

		$cycle    = max( 1, $cycle );
		$redeemed = $this->points->redeem_points(
			$customer_id,
			$total_points,
			$order_id,
			null,
			sprintf( 'Regalos canjeados en el pedido #%d.', $order_id ),
			array(
				'rewards'       => array_values( $items ),
				'loyalty_cycle' => $cycle,
			),
			Points_Service::cycle_key( 'redeemed_order', $order_id, $cycle )
		);

		if ( ! $redeemed ) {
			return array(
				'success'    => false,
				'error'      => 'points_not_redeemed',
				'record_ids' => array(),
			);
		}

		$record_ids = array();
		foreach ( $items as $item ) {
			$reward_id = (int) $item['reward_id'];
			$record_id = $this->order_rewards->insert(
				array_merge(
					$item,
					array(
						'order_id'        => $order_id,
						'customer_id'     => $customer_id,
						'idempotency_key' => Points_Service::cycle_key( 'redeemed_order', $order_id, $cycle ) . ':reward:' . $reward_id,
					)
				)
			);

			if ( $record_id <= 0 ) {
				return array(
					'success'    => false,
					'error'      => 'reward_not_recorded',
					'record_ids' => $record_ids,
				);
			}

			$record_ids[ $reward_id ] = $record_id;
		}

		return array(
			'success'    => true,
			'error'      => '',
			'record_ids' => $record_ids,
		);
	}

	private function invalid_result( string $error, int $balance = 0 ): array {
		return array(
			'valid'        => false,
			'error'        => $error,
			'balance'      => $balance,
			'total_points' => 0,
			'items'        => array(),
		);
	}
}
