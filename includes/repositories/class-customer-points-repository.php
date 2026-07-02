<?php
/**
 * Customer points persistence.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Repositories;

use LCTER_WCPL\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Customer_Points_Repository {
	public function get_balance( int $customer_id ): int {
		global $wpdb;

		if ( $customer_id <= 0 ) {
			return 0;
		}

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(balance, 0) FROM ' . $this->table_name() . ' WHERE customer_id = %d',
				$customer_id
			)
		);

		return (int) ( $balance ?? 0 );
	}

	/**
	 * Ensure and lock the customer balance row for the current transaction.
	 */
	public function lock_balance( int $customer_id ): ?int {
		global $wpdb;

		$created = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . $this->table_name() . ' (customer_id, balance, total_earned, total_redeemed, created_at, updated_at) VALUES (%d, 0, 0, 0, NOW(), NOW())',
				$customer_id
			)
		);

		if ( false === $created ) {
			return null;
		}

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT balance FROM ' . $this->table_name() . ' WHERE customer_id = %d FOR UPDATE',
				$customer_id
			)
		);

		return null === $balance ? null : (int) $balance;
	}

	public function add( int $customer_id, int $points ): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->table_name() . ' SET balance = balance + %d, total_earned = total_earned + %d, updated_at = NOW() WHERE customer_id = %d',
				$points,
				$points,
				$customer_id
			)
		);

		return false !== $result && 1 === $wpdb->rows_affected;
	}

	public function redeem( int $customer_id, int $points ): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->table_name() . ' SET balance = balance - %d, total_redeemed = total_redeemed + %d, updated_at = NOW() WHERE customer_id = %d AND balance >= %d',
				$points,
				$points,
				$customer_id,
				$points
			)
		);

		return false !== $result && 1 === $wpdb->rows_affected;
	}

	/**
	 * Subtract previously earned points without counting them as redeemed.
	 */
	public function reverse_earned( int $customer_id, int $points ): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->table_name() . ' SET balance = balance - %d, updated_at = NOW() WHERE customer_id = %d AND balance >= %d',
				$points,
				$customer_id,
				$points
			)
		);

		return false !== $result && 1 === $wpdb->rows_affected;
	}

	/**
	 * Apply a signed manual balance delta without changing historical gross totals.
	 */
	public function adjust( int $customer_id, int $adjustment ): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->table_name() . ' SET balance = balance + %d, updated_at = NOW() WHERE customer_id = %d AND balance + %d >= 0',
				$adjustment,
				$customer_id,
				$adjustment
			)
		);

		return false !== $result && 1 === $wpdb->rows_affected;
	}

	public function count_customers(): int {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table_name() );
	}

	public function sum_total_earned(): int {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COALESCE(SUM(total_earned), 0) FROM ' . $this->table_name() );
	}

	public function sum_total_redeemed(): int {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COALESCE(SUM(total_redeemed), 0) FROM ' . $this->table_name() );
	}

	private function table_name(): string {
		return Database::get_table_name( Database::TABLE_SUFFIX_CUSTOMER_POINTS );
	}
}
