<?php
/**
 * Backward-compatible rewards API facade.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

use LCTER_WCPL\Services\Reward_Traceability_Service;
use LCTER_WCPL\Services\Rewards_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewards {
	const MINIMUM_ORDER_TOTAL_FOR_REDEMPTION = Rewards_Service::MINIMUM_ORDER_TOTAL_FOR_REDEMPTION;
	const MAX_VISIBLE_REWARDS                = Rewards_Service::MAX_VISIBLE_REWARDS;

	public static function create_reward( $args ): int {
		return ( new Rewards_Service() )->save_reward( (array) $args );
	}

	public static function get_available_rewards(): array {
		return ( new Rewards_Service() )->get_available_rewards();
	}

	public static function get_reward( int $reward_id ): ?array {
		return ( new Rewards_Service() )->get_reward( $reward_id );
	}

	public static function get_reward_by_product( int $product_id ): ?array {
		return ( new Rewards_Service() )->get_reward_by_product( $product_id );
	}

	public static function save_reward( array $reward ): int {
		return ( new Rewards_Service() )->save_reward( $reward );
	}

	public static function delete_reward( int $reward_id ): bool {
		return ( new Rewards_Service() )->delete_reward( $reward_id );
	}

	public static function deactivate_reward( int $reward_id ): bool {
		return ( new Rewards_Service() )->deactivate_reward( $reward_id );
	}

	public static function get_customer_available_rewards( $customer_id ): array {
		return ( new Rewards_Service() )->get_customer_available_rewards( (int) $customer_id );
	}

	public static function get_order_rewards( int $order_id ): array {
		return ( new Reward_Traceability_Service() )->get_rewards_by_order( $order_id );
	}

	public static function get_customer_rewards( int $customer_id ): array {
		return ( new Reward_Traceability_Service() )->get_rewards_by_customer( $customer_id );
	}

	public static function get_integration_payload_by_order( int $order_id ): array {
		return ( new Reward_Traceability_Service() )->get_integration_payload_by_order( $order_id );
	}

	public static function get_integration_payload_by_customer( int $customer_id ): array {
		return ( new Reward_Traceability_Service() )->get_integration_payload_by_customer( $customer_id );
	}

	public static function get_rewards_by_order_id( int $order_id ): array {
		return self::get_order_rewards( $order_id );
	}

	public static function order_meets_redemption_minimum( $order ): bool {
		return $order && ( new Rewards_Service() )->order_total_meets_minimum( (float) $order->get_total() );
	}

	/**
	 * Legacy immediate redemption entrypoint.
	 *
	 * @deprecated 0.1.0 Use the paid-order checkout flow instead.
	 */
	public static function redeem_reward_for_order( int $customer_id, $order, int $reward_id, int $quantity = 1 ): bool {
		unset( $customer_id, $order, $reward_id, $quantity );

		_deprecated_function(
			__METHOD__,
			'0.1.0',
			'Reward_Redemption_Service mediante los hooks de pago del checkout'
		);

		return false;
	}
}
