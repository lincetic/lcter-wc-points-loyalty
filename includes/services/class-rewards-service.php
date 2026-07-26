<?php
/**
 * Application service for the reward catalog.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Services;

use LCTER_WCPL\Repositories\Customer_Points_Repository;
use LCTER_WCPL\Repositories\Order_Rewards_Repository;
use LCTER_WCPL\Repositories\Rewards_Repository;
use LCTER_WCPL\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewards_Service {
	const MINIMUM_ORDER_TOTAL_FOR_REDEMPTION = 60.0;
	const MAX_VISIBLE_REWARDS                = 12;

	private Rewards_Repository $rewards;
	private Customer_Points_Repository $points;
	private Order_Rewards_Repository $order_rewards;

	public function __construct(
		?Rewards_Repository $rewards = null,
		?Customer_Points_Repository $points = null,
		?Order_Rewards_Repository $order_rewards = null
	) {
		$this->rewards       = $rewards ?? new Rewards_Repository();
		$this->points        = $points ?? new Customer_Points_Repository();
		$this->order_rewards = $order_rewards ?? new Order_Rewards_Repository();
	}

	public function get_available_rewards(): array {
		return $this->get_available_checkout_rewards();
	}

	public function get_available_checkout_rewards( ?int $limit = null ): array {
		$limit   = null === $limit ? Settings::get_max_checkout_rewards() : max( 1, min( Settings::MAXIMUM_CHECKOUT_REWARDS, $limit ) );
		return array_slice( $this->get_checkout_eligible_rewards(), 0, $limit );
	}

	public function get_checkout_eligible_rewards(): array {
		$results = array();
		foreach ( $this->rewards->find_checkout_candidates() as $reward ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $reward['product_id'] ) : null;
			if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_purchasable() || ( $product->managing_stock() && ! $product->is_in_stock() ) ) {
				continue;
			}
			$results[] = $reward;
		}
		return $results;
	}

	public function get_all_rewards(): array {
		return $this->rewards->find_all();
	}

	public function get_reward( int $reward_id ): ?array {
		return $this->rewards->find( $reward_id );
	}

	public function get_reward_by_product( int $product_id ): ?array {
		return $this->rewards->find_by_product( $product_id );
	}

	public function save_reward( array $reward ): int {
		return $this->rewards->save( $reward );
	}

	public function delete_reward( int $reward_id ): bool {
		return $this->rewards->delete( $reward_id );
	}

	public function deactivate_reward( int $reward_id ): bool {
		return $this->rewards->deactivate( $reward_id );
	}

	public function update_reward_flags( int $reward_id, ?int $active = null, ?int $visible = null ): bool {
		return $this->rewards->update_flags( $reward_id, $active, $visible );
	}

	public function get_customer_available_rewards( int $customer_id ): array {
		$balance = $this->points->get_balance( $customer_id );

		return array_values(
			array_filter(
				$this->get_available_rewards(),
				static fn( array $reward ): bool => (int) $reward['points_cost'] <= $balance
			)
		);
	}

	public function get_order_rewards( int $order_id ): array {
		return $this->order_rewards->find_by_order( $order_id );
	}

	public function get_customer_rewards( int $customer_id ): array {
		return $this->order_rewards->find_by_customer( $customer_id );
	}

	public function record_order_reward( array $order_reward ): int {
		return $this->order_rewards->insert( $order_reward );
	}

	public function order_total_meets_minimum( float $order_total ): bool {
		return $order_total >= self::MINIMUM_ORDER_TOTAL_FOR_REDEMPTION;
	}
}
