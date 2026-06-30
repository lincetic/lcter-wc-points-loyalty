<?php
/**
 * Application service for atomic point balance operations.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Services;

use LCTER_WCPL\Database;
use LCTER_WCPL\Repositories\Customer_Points_Repository;
use LCTER_WCPL\Repositories\Transactions_Repository;
use LCTER_WCPL\Repositories\Transaction_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Points_Service {
	const RESULT_ADDED     = 'added';
	const RESULT_DUPLICATE = 'duplicate';
	const RESULT_FAILED    = 'failed';

	private Customer_Points_Repository $points;
	private Transactions_Repository $transactions;
	private Transaction_Manager $transaction_manager;

	public function __construct(
		?Customer_Points_Repository $points = null,
		?Transactions_Repository $transactions = null,
		?Transaction_Manager $transaction_manager = null
	) {
		$this->points              = $points ?? new Customer_Points_Repository();
		$this->transactions        = $transactions ?? new Transactions_Repository();
		$this->transaction_manager = $transaction_manager ?? new Transaction_Manager();
	}

	public function get_balance( int $customer_id ): int {
		return $this->points->get_balance( $customer_id );
	}

	public function has_idempotency_key( string $idempotency_key ): bool {
		return $this->transactions->exists_by_idempotency_key( $idempotency_key );
	}

	public function has_order_transaction( int $order_id, string $type ): bool {
		return $this->transactions->exists_for_order_and_type( $order_id, $type );
	}

	public function get_order_transaction_points( int $order_id, string $type ): int {
		return $this->transactions->get_points_for_order_and_type( $order_id, $type );
	}

	/**
	 * Add points and the corresponding audit transaction atomically.
	 */
	public function add_points(
		int $customer_id,
		int $points,
		string $type = Database::TRANSACTION_EARNED,
		?int $order_id = null,
		?int $order_item_id = null,
		?string $source = null,
		?string $description = null,
		$metadata = null,
		?int $created_by = null,
		?string $idempotency_key = null
	): bool {
		return self::RESULT_FAILED !== $this->add_points_with_status(
			$customer_id,
			$points,
			$type,
			$order_id,
			$order_item_id,
			$source,
			$description,
			$metadata,
			$created_by,
			$idempotency_key
		);
	}

	/**
	 * Add points atomically and report whether the operation was new or duplicate.
	 *
	 * @return string One of the RESULT_* constants.
	 */
	public function add_points_with_status(
		int $customer_id,
		int $points,
		string $type = Database::TRANSACTION_EARNED,
		?int $order_id = null,
		?int $order_item_id = null,
		?string $source = null,
		?string $description = null,
		$metadata = null,
		?int $created_by = null,
		?string $idempotency_key = null
	): string {
		if ( $customer_id <= 0 || $points <= 0 || '' === $type ) {
			return self::RESULT_FAILED;
		}

		if ( ! $this->transaction_manager->begin() ) {
			return self::RESULT_FAILED;
		}

		try {
			$balance_before = $this->points->lock_balance( $customer_id );
			if ( null === $balance_before ) {
				throw new \RuntimeException( 'Could not lock the customer balance.' );
			}

			if ( $idempotency_key && $this->transactions->exists_by_idempotency_key( $idempotency_key ) ) {
				if ( ! $this->transaction_manager->commit() ) {
					throw new \RuntimeException( 'Could not commit the duplicate points transaction check.' );
				}

				return self::RESULT_DUPLICATE;
			}

			if ( Database::TRANSACTION_EARNED === $type && $order_id && $this->transactions->exists_for_order_and_type( $order_id, $type ) ) {
				if ( ! $this->transaction_manager->commit() ) {
					throw new \RuntimeException( 'Could not commit the legacy duplicate transaction check.' );
				}

				return self::RESULT_DUPLICATE;
			}

			$balance_after = $balance_before + $points;
			if ( ! $this->points->add( $customer_id, $points ) ) {
				throw new \RuntimeException( 'Could not update the customer balance.' );
			}

			if ( ! $this->transactions->insert(
				array(
					'customer_id'     => $customer_id,
					'order_id'        => $order_id,
					'order_item_id'   => $order_item_id,
					'type'            => $type,
					'points'          => $points,
					'balance_before'  => $balance_before,
					'balance_after'   => $balance_after,
					'source'          => $source,
					'description'     => $description,
					'metadata'        => $metadata,
					'created_by'      => $created_by,
					'idempotency_key' => $idempotency_key,
				)
			) ) {
				throw new \RuntimeException( 'Could not insert the points transaction.' );
			}

			if ( ! $this->transaction_manager->commit() ) {
				throw new \RuntimeException( 'Could not commit the points transaction.' );
			}

			return self::RESULT_ADDED;
		} catch ( \Throwable $exception ) {
			$this->transaction_manager->rollback();
			return self::RESULT_FAILED;
		}
	}

	/**
	 * Redeem points and register the audit transaction atomically.
	 */
	public function redeem_points(
		int $customer_id,
		int $points,
		?int $order_id = null,
		?int $order_item_id = null,
		?string $description = null,
		$metadata = null,
		?string $idempotency_key = null
	): bool {
		if ( $customer_id <= 0 || $points <= 0 || ! $this->transaction_manager->begin() ) {
			return false;
		}

		try {
			$balance_before = $this->points->lock_balance( $customer_id );
			if ( null === $balance_before ) {
				throw new \RuntimeException( 'Could not lock the customer balance.' );
			}

			if ( $idempotency_key && $this->transactions->exists_by_idempotency_key( $idempotency_key ) ) {
				return $this->transaction_manager->commit();
			}

			if ( $idempotency_key && $order_id && $this->transactions->exists_for_order_and_type( $order_id, Database::TRANSACTION_REDEEMED ) ) {
				return $this->transaction_manager->commit();
			}

			if ( $balance_before < $points ) {
				throw new \RuntimeException( 'Insufficient customer balance.' );
			}

			$balance_after = $balance_before - $points;
			if ( $balance_after < 0 || ! $this->points->redeem( $customer_id, $points ) ) {
				throw new \RuntimeException( 'Could not redeem the customer balance.' );
			}

			if ( ! $this->transactions->insert(
				array(
					'customer_id'    => $customer_id,
					'order_id'       => $order_id,
					'order_item_id'  => $order_item_id,
					'type'           => Database::TRANSACTION_REDEEMED,
					'points'         => -1 * $points,
					'balance_before' => $balance_before,
					'balance_after'  => $balance_after,
					'source'         => 'woocommerce_reward',
					'description'    => $description,
					'metadata'       => $metadata,
					'idempotency_key' => $idempotency_key,
				)
			) ) {
				throw new \RuntimeException( 'Could not insert the redemption transaction.' );
			}

			if ( ! $this->transaction_manager->commit() ) {
				throw new \RuntimeException( 'Could not commit the redemption transaction.' );
			}

			return true;
		} catch ( \Throwable $exception ) {
			$this->transaction_manager->rollback();
			return false;
		}
	}

	public function award_points_for_order(
		int $customer_id,
		int $points,
		int $order_id,
		?string $description = null,
		$metadata = null
	): bool {
		return $this->add_points(
			$customer_id,
			$points,
			Database::TRANSACTION_EARNED,
			$order_id,
			null,
			'woocommerce_order',
			$description,
			$metadata,
			null,
			'earned_order:' . $order_id
		);
	}

	public function get_dashboard_totals(): array {
		return array(
			'customers' => $this->points->count_customers(),
			'earned'    => $this->points->sum_total_earned(),
			'redeemed'  => $this->points->sum_total_redeemed(),
		);
	}
}
