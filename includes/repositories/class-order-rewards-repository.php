<?php
/**
 * Redeemed order reward persistence.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Repositories;

use LCTER_WCPL\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Order_Rewards_Repository {
	public function insert( array $order_reward ): int {
		global $wpdb;

		$order_id    = isset( $order_reward['order_id'] ) ? absint( $order_reward['order_id'] ) : 0;
		$customer_id = isset( $order_reward['customer_id'] ) ? absint( $order_reward['customer_id'] ) : 0;
		$product_id  = isset( $order_reward['product_id'] ) ? absint( $order_reward['product_id'] ) : 0;
		$quantity    = max( 1, isset( $order_reward['quantity'] ) ? absint( $order_reward['quantity'] ) : 1 );
		$cost_each   = isset( $order_reward['points_cost_each'] ) ? absint( $order_reward['points_cost_each'] ) : 0;
		$cost_total  = isset( $order_reward['points_cost_total'] ) ? absint( $order_reward['points_cost_total'] ) : $cost_each * $quantity;

		if ( $order_id <= 0 || $customer_id <= 0 || $product_id <= 0 || $cost_each <= 0 || $cost_total <= 0 ) {
			return 0;
		}

		$inserted = $wpdb->insert(
			$this->table_name(),
			array(
				'order_id'          => $order_id,
				'customer_id'       => $customer_id,
				'product_id'        => $product_id,
				'order_item_id'     => isset( $order_reward['order_item_id'] ) ? absint( $order_reward['order_item_id'] ) : null,
				'reward_id'         => isset( $order_reward['reward_id'] ) ? absint( $order_reward['reward_id'] ) : null,
				'quantity'          => $quantity,
				'points_cost_each'  => $cost_each,
				'points_cost_total' => $cost_total,
				'product_name'      => isset( $order_reward['product_name'] ) ? sanitize_text_field( (string) $order_reward['product_name'] ) : null,
				'sku'               => isset( $order_reward['sku'] ) ? sanitize_text_field( (string) $order_reward['sku'] ) : null,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	public function find_by_order( int $order_id ): array {
		global $wpdb;

		if ( $order_id <= 0 ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table_name() . ' WHERE order_id = %d ORDER BY created_at ASC, id ASC', $order_id ),
			ARRAY_A
		);
	}

	public function find_by_customer( int $customer_id ): array {
		global $wpdb;

		if ( $customer_id <= 0 ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table_name() . ' WHERE customer_id = %d ORDER BY created_at DESC, id DESC', $customer_id ),
			ARRAY_A
		);
	}

	private function table_name(): string {
		return Database::get_table_name( Database::TABLE_SUFFIX_ORDER_REWARDS );
	}
}
