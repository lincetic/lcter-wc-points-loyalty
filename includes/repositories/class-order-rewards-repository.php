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
		$idempotency_key = isset( $order_reward['idempotency_key'] ) ? sanitize_text_field( (string) $order_reward['idempotency_key'] ) : null;

		if ( $order_id <= 0 || $customer_id <= 0 || $product_id <= 0 || $cost_each <= 0 || $cost_total <= 0 ) {
			return 0;
		}

		if ( $idempotency_key ) {
			$existing_id = $this->find_id_by_idempotency_key( $idempotency_key );
			if ( $existing_id > 0 ) {
				return $existing_id;
			}
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
				'idempotency_key'   => $idempotency_key,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false !== $inserted ) {
			return (int) $wpdb->insert_id;
		}

		return $idempotency_key ? $this->find_id_by_idempotency_key( $idempotency_key ) : 0;
	}

	public function find_id_by_idempotency_key( string $idempotency_key ): int {
		global $wpdb;

		if ( '' === $idempotency_key ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->table_name() . ' WHERE idempotency_key = %s LIMIT 1',
				$idempotency_key
			)
		);
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
