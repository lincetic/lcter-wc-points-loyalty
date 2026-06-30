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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewards_Service {
	const MINIMUM_ORDER_TOTAL_FOR_REDEMPTION = 60.0;

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
		return $this->rewards->find_active();
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
