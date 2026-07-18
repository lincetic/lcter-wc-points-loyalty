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
	public function exists_for_customer_and_type( int $customer_id, string $type ): bool {
		global $wpdb;

		if ( $customer_id <= 0 || '' === $type ) {
			return false;
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . $this->table_name() . ' WHERE customer_id = %d AND type = %s LIMIT 1',
				$customer_id,
				$type
			)
		);
	}

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

	public function get_points_for_order_and_type( int $order_id, string $type ): int {
		global $wpdb;

		if ( $order_id <= 0 || '' === $type ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(points), 0) FROM ' . $this->table_name() . ' WHERE order_id = %d AND type = %s',
				$order_id,
				$type
			)
		);
	}

	public function get_points_by_idempotency_key( string $idempotency_key ): int {
		global $wpdb;

		if ( '' === $idempotency_key ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(points, 0) FROM ' . $this->table_name() . ' WHERE idempotency_key = %s LIMIT 1',
				$idempotency_key
			)
		);
	}

	public function find_first_for_order_and_type( int $order_id, string $type ): ?array {
		global $wpdb;

		if ( $order_id <= 0 || '' === $type ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table_name() . ' WHERE order_id = %d AND type = %s ORDER BY created_at ASC, id ASC LIMIT 1',
				$order_id,
				$type
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find the latest transaction for one order among a trusted set of types.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $types Transaction types.
	 */
	public function find_latest_for_order_and_types( int $order_id, array $types ): ?array {
		global $wpdb;

		$types = array_values(
			array_filter(
				array_map(
					static function ( $type ): string {
						return is_scalar( $type ) ? sanitize_key( (string) $type ) : '';
					},
					$types
				)
			)
		);

		if ( $order_id <= 0 || empty( $types ) ) {
			return null;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$sql          = 'SELECT * FROM ' . $this->table_name() . ' WHERE order_id = %d AND type IN (' . $placeholders . ') ORDER BY created_at DESC, id DESC LIMIT 1';
		$row          = $wpdb->get_row(
			$wpdb->prepare( $sql, array_merge( array( $order_id ), $types ) ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find the latest transaction matching one of the provided idempotency keys.
	 *
	 * @param array $idempotency_keys Idempotency keys.
	 */
	public function find_latest_by_idempotency_keys( array $idempotency_keys ): ?array {
		global $wpdb;

		$idempotency_keys = array_values(
			array_filter(
				array_map(
					static function ( $key ): string {
						return is_scalar( $key ) ? (string) $key : '';
					},
					$idempotency_keys
				)
			)
		);

		if ( empty( $idempotency_keys ) ) {
			return null;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $idempotency_keys ), '%s' ) );
		$sql          = 'SELECT * FROM ' . $this->table_name() . ' WHERE idempotency_key IN (' . $placeholders . ') ORDER BY created_at DESC, id DESC LIMIT 1';
		$row          = $wpdb->get_row(
			$wpdb->prepare( $sql, $idempotency_keys ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
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
