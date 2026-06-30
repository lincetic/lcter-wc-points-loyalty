<?php
/**
 * Points transaction persistence.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Repositories;

use LCTER_WCPL\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Transactions_Repository {
	public function exists_for_order_and_type( int $order_id, string $type ): bool {
		global $wpdb;

		if ( $order_id <= 0 || '' === $type ) {
			return false;
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . $this->table_name() . ' WHERE order_id = %d AND type = %s LIMIT 1',
				$order_id,
				$type
			)
		);
	}

	public function exists_by_idempotency_key( string $idempotency_key ): bool {
		global $wpdb;

		if ( '' === $idempotency_key ) {
			return false;
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . $this->table_name() . ' WHERE idempotency_key = %s LIMIT 1',
				$idempotency_key
			)
		);
	}

	/**
	 * Insert an immutable balance transaction.
	 *
	 * @param array $transaction Transaction data.
	 */
	public function insert( array $transaction ): bool {
		global $wpdb;

		$metadata = $transaction['metadata'] ?? null;
		if ( null !== $metadata && ! is_string( $metadata ) ) {
			$metadata = wp_json_encode( $metadata );
			$metadata = false === $metadata ? null : $metadata;
		}

		return (bool) $wpdb->insert(
			$this->table_name(),
			array(
				'customer_id'     => (int) $transaction['customer_id'],
				'order_id'        => $transaction['order_id'] ?? null,
				'order_item_id'   => $transaction['order_item_id'] ?? null,
				'type'            => (string) $transaction['type'],
				'points'          => (int) $transaction['points'],
				'balance_before'  => (int) $transaction['balance_before'],
				'balance_after'   => (int) $transaction['balance_after'],
				'source'          => $transaction['source'] ?? null,
				'description'     => $transaction['description'] ?? null,
				'metadata'        => $metadata,
				'created_by'      => $transaction['created_by'] ?? null,
				'idempotency_key' => $transaction['idempotency_key'] ?? null,
			),
			array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	private function table_name(): string {
		return Database::get_table_name( Database::TABLE_SUFFIX_TRANSACTIONS );
	}
}
