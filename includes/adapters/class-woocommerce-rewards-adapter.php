<?php
/**
 * WooCommerce reward order-item adapter.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Adapters;

use LCTER_WCPL\Database;
use LCTER_WCPL\Services\Points_Service;
use LCTER_WCPL\Services\Rewards_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerce_Rewards_Adapter {
	private Rewards_Service $rewards_service;
	private Points_Service $points_service;

	public function __construct(
		?Rewards_Service $rewards_service = null,
		?Points_Service $points_service = null
	) {
		$this->rewards_service = $rewards_service ?? new Rewards_Service();
		$this->points_service  = $points_service ?? new Points_Service();
	}

	public function redeem_reward_for_order( int $customer_id, $order, int $reward_id, int $quantity = 1 ): bool {
		if (
			$customer_id <= 0 ||
			$quantity <= 0 ||
			! $order ||
			! $this->rewards_service->order_total_meets_minimum( (float) $order->get_total() )
		) {
			return false;
		}

		$reward = $this->find_available_reward( $reward_id );
		if ( ! $reward ) {
			return false;
		}

		$product = wc_get_product( (int) $reward['product_id'] );
		if ( ! $product ) {
			return false;
		}

		$points_cost_each  = (int) $reward['points_cost'];
		$points_cost_total = $points_cost_each * $quantity;
		if ( $this->points_service->get_balance( $customer_id ) < $points_cost_total ) {
			return false;
		}

		$item_id = $order->add_product( $product, $quantity, array( 'subtotal' => 0, 'total' => 0 ) );
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

		$redeemed = $this->points_service->redeem_points(
			$customer_id,
			$points_cost_total,
			(int) $order->get_id(),
			(int) $item_id,
			sprintf( 'Regalo canjeado: %s x %d.', $product->get_name(), $quantity ),
			array(
				'clientify' => array( 'type' => 'order_reward' ),
				'reward'    => array(
					'reward_id'         => $reward_id,
					'product_id'        => (int) $reward['product_id'],
					'quantity'          => $quantity,
					'points_cost_each'  => $points_cost_each,
					'points_cost_total' => $points_cost_total,
					'product_name'      => $product->get_name(),
					'sku'               => $product->get_sku(),
					'order_item'        => array( 'id' => (int) $item_id, 'label' => 'REGALO' ),
				),
			)
		);

		if ( ! $redeemed ) {
			$order->remove_item( $item_id );
			$order->save();
			return false;
		}

		$order_reward_id = $this->rewards_service->record_order_reward(
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
			$this->points_service->add_points(
				$customer_id,
				$points_cost_total,
				Database::TRANSACTION_REFUND,
				(int) $order->get_id(),
				(int) $item_id,
				'woocommerce_reward',
				sprintf( 'Devolucion de puntos por fallo al registrar regalo: %s x %d.', $product->get_name(), $quantity )
			);
			$order->remove_item( $item_id );
			$order->save();
			return false;
		}

		$this->add_order_reward_metadata(
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

	private function find_available_reward( int $reward_id ): ?array {
		foreach ( $this->rewards_service->get_available_rewards() as $reward ) {
			if ( (int) $reward['id'] === $reward_id ) {
				return $reward;
			}
		}

		return null;
	}

	private function add_order_reward_metadata( $order, array $reward_data ): void {
		$current = $order->get_meta( '_lcter_wcpl_order_rewards' );
		$current = is_array( $current ) ? $current : array();
		$current[] = $reward_data;

		$order->update_meta_data( '_lcter_wcpl_has_rewards', '1' );
		$order->update_meta_data( '_lcter_wcpl_order_rewards', $current );
	}
}
