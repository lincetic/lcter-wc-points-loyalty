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
	const RESULT_ADDED                = 'added';
	const RESULT_DUPLICATE            = 'duplicate';
	const RESULT_FAILED               = 'failed';
	const RESULT_INSUFFICIENT_BALANCE = 'insufficient_balance';

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

	public function get_order_transaction( int $order_id, string $type ): ?array {
		return $this->transactions->find_first_for_order_and_type( $order_id, $type );
	}

	public function get_order_earned_points_for_cycle( int $order_id, int $cycle ): int {
		$cycle = max( 1, $cycle );
		if ( 1 === $cycle ) {
			return max( 0, $this->get_order_transaction_points( $order_id, Database::TRANSACTION_EARNED ) );
		}

		return max( 0, $this->transactions->get_points_by_idempotency_key( self::cycle_key( 'restored_earned_order', $order_id, $cycle ) ) );
	}

	public function get_order_redeemed_points_for_cycle( int $order_id, int $cycle ): int {
		$cycle = max( 1, $cycle );
		if ( 1 === $cycle ) {
			return abs( min( 0, $this->get_order_transaction_points( $order_id, Database::TRANSACTION_REDEEMED ) ) );
		}

		return abs( min( 0, $this->transactions->get_points_by_idempotency_key( self::cycle_key( 'restored_redeemed_order', $order_id, $cycle ) ) ) );
	}

	public function get_latest_order_reversal_transaction( int $order_id, int $cycle = 1 ): ?array {
		$cycle = max( 1, $cycle );
		$keys  = array(
			self::cycle_key( 'reversed_earned_order', $order_id, $cycle ),
			self::cycle_key( 'returned_redeemed_order', $order_id, $cycle ),
		);

		if ( 1 === $cycle ) {
			$legacy = $this->transactions->find_latest_for_order_and_types(
				$order_id,
				array(
					Database::TRANSACTION_CANCELLED,
					Database::TRANSACTION_REFUND,
					Database::TRANSACTION_FAILED,
					Database::TRANSACTION_RETURNED_REDEEMED,
				)
			);
			if ( is_array( $legacy ) && empty( $legacy['idempotency_key'] ) ) {
				return $legacy;
			}
			$keys[] = 'cancelled_order:' . $order_id;
			$keys[] = 'refunded_order:' . $order_id;
			$keys[] = 'failed_order:' . $order_id;
			$keys[] = 'returned_redeemed_order:' . $order_id . ':cancelled';
			$keys[] = 'returned_redeemed_order:' . $order_id . ':refunded';
			$keys[] = 'returned_redeemed_order:' . $order_id . ':failed';
		}

		return $this->transactions->find_latest_by_idempotency_keys( $keys );
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

			if (
				Database::TRANSACTION_INITIAL_BONUS === $type &&
				$this->transactions->exists_for_customer_and_type( $customer_id, $type )
			) {
				if ( ! $this->transaction_manager->commit() ) {
					throw new \RuntimeException( 'Could not commit the initial bonus duplicate check.' );
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
	 * Apply a signed administrative adjustment and register its audit transaction atomically.
	 *
	 * @return string One of the RESULT_* constants.
	 */
	public function adjust_points( int $customer_id, int $adjustment, string $description, int $created_by ): string {
		$description = trim( $description );
		if ( $customer_id <= 0 || 0 === $adjustment || '' === $description || $created_by <= 0 ) {
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

			$balance_after = $balance_before + $adjustment;
			if ( $balance_after < 0 ) {
				$this->transaction_manager->rollback();
				return self::RESULT_INSUFFICIENT_BALANCE;
			}

			if ( $balance_after > 2147483647 || ! $this->points->adjust( $customer_id, $adjustment ) ) {
				throw new \RuntimeException( 'Could not adjust the customer balance.' );
			}

			if ( ! $this->transactions->insert(
				array(
					'customer_id'     => $customer_id,
					'order_id'        => null,
					'order_item_id'   => null,
					'type'            => Database::TRANSACTION_MANUAL_ADJUSTMENT,
					'points'          => $adjustment,
					'balance_before'  => $balance_before,
					'balance_after'   => $balance_after,
					'source'          => 'woocommerce_customer_admin',
					'description'     => $description,
					'metadata'        => null,
					'created_by'      => $created_by,
					'idempotency_key' => null,
				)
			) ) {
				throw new \RuntimeException( 'Could not insert the manual adjustment transaction.' );
			}

			if ( ! $this->transaction_manager->commit() ) {
				throw new \RuntimeException( 'Could not commit the manual adjustment transaction.' );
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
					'customer_id'     => $customer_id,
					'order_id'        => $order_id,
					'order_item_id'   => $order_item_id,
					'type'            => Database::TRANSACTION_REDEEMED,
					'points'          => -1 * $points,
					'balance_before'  => $balance_before,
					'balance_after'   => $balance_after,
					'source'          => 'woocommerce_reward',
					'description'     => $description,
					'metadata'        => $metadata,
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
		$metadata = null,
		int $cycle = 1
	): bool {
		$cycle = max( 1, $cycle );
		if ( is_array( $metadata ) ) {
			$metadata['loyalty_cycle'] = $cycle;
		}

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
			self::cycle_key( 'earned_order', $order_id, $cycle )
		);
	}

	/**
	 * Reverse the points earned by a cancelled or fully refunded order.
	 *
	 * @return string One of the RESULT_* constants.
	 */
	public function reverse_order_earned_points(
		int $customer_id,
		int $points,
		int $order_id,
		string $reason,
		$metadata = null,
		string $idempotency_key = '',
		string $transaction_type = Database::TRANSACTION_CANCELLED,
		string $source = 'woocommerce_order_cancellation',
		int $cycle = 1
	): string {
		if ( $customer_id <= 0 || $points <= 0 || $order_id <= 0 ) {
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

			$cycle           = max( 1, $cycle );
			$event_idempotency_key = '' !== $idempotency_key ? $idempotency_key : self::cycle_key( 'cancelled_order', $order_id, $cycle );
			$idempotency_key       = self::cycle_key( 'reversed_earned_order', $order_id, $cycle );
			$terminal_keys         = array( $idempotency_key );
			if ( 1 === $cycle ) {
				$terminal_keys[] = 'cancelled_order:' . $order_id;
				$terminal_keys[] = 'refunded_order:' . $order_id;
				$terminal_keys[] = 'failed_order:' . $order_id;
			}

			$duplicate = false;
			foreach ( $terminal_keys as $terminal_key ) {
				if ( $this->transactions->exists_by_idempotency_key( $terminal_key ) ) {
					$duplicate = true;
					break;
				}
			}
			if (
				$duplicate ||
				(
					1 === $cycle &&
					(
						$this->transactions->exists_for_order_and_type( $order_id, Database::TRANSACTION_CANCELLED ) ||
						$this->transactions->exists_for_order_and_type( $order_id, Database::TRANSACTION_REFUND ) ||
						$this->transactions->exists_for_order_and_type( $order_id, Database::TRANSACTION_FAILED )
					)
				)
			) {
				if ( ! $this->transaction_manager->commit() ) {
					throw new \RuntimeException( 'Could not commit the cancelled order duplicate check.' );
				}

				return self::RESULT_DUPLICATE;
			}

			if ( $balance_before < $points ) {
				$this->transaction_manager->rollback();
				return self::RESULT_INSUFFICIENT_BALANCE;
			}

			if ( is_array( $metadata ) ) {
				$metadata['event_idempotency_key'] = $event_idempotency_key;
			}

			$balance_after = $balance_before - $points;
			if ( $balance_after < 0 || ! $this->points->reverse_earned( $customer_id, $points ) ) {
				throw new \RuntimeException( 'Could not reverse the earned customer balance.' );
			}

			if ( ! $this->transactions->insert(
				array(
					'customer_id'     => $customer_id,
					'order_id'        => $order_id,
					'order_item_id'   => null,
					'type'            => $transaction_type,
					'points'          => -1 * $points,
					'balance_before'  => $balance_before,
					'balance_after'   => $balance_after,
					'source'          => $source,
					'description'     => $reason,
					'metadata'        => $metadata,
					'idempotency_key' => $idempotency_key,
				)
			) ) {
				throw new \RuntimeException( 'Could not insert the cancelled order transaction.' );
			}

			if ( ! $this->transaction_manager->commit() ) {
				throw new \RuntimeException( 'Could not commit the cancelled order transaction.' );
			}

			return self::RESULT_ADDED;
		} catch ( \Throwable $exception ) {
			$this->transaction_manager->rollback();
			return self::RESULT_FAILED;
		}
	}

	public function return_order_redeemed_points(
		int $customer_id,
		int $points,
		int $order_id,
		string $woocommerce_status,
		string $trigger,
		$metadata = null,
		int $cycle = 1
	): string {
		if ( $customer_id <= 0 || $points <= 0 || $order_id <= 0 || '' === $woocommerce_status ) {
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

			$cycle           = max( 1, $cycle );
			$status_key      = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $woocommerce_status ) ?? '' );
			$idempotency_key = self::cycle_key( 'returned_redeemed_order', $order_id, $cycle );
			if (
				$this->transactions->exists_by_idempotency_key( $idempotency_key ) ||
				(
					1 === $cycle &&
					(
						$this->transactions->exists_by_idempotency_key( 'returned_redeemed_order:' . $order_id . ':' . $status_key ) ||
						$this->transactions->exists_for_order_and_type( $order_id, Database::TRANSACTION_RETURNED_REDEEMED )
					)
				)
			) {
				if ( ! $this->transaction_manager->commit() ) {
					throw new \RuntimeException( 'Could not commit the returned redeemed duplicate check.' );
				}

				return self::RESULT_DUPLICATE;
			}

			$balance_after = $balance_before + $points;
			if ( ! $this->points->adjust( $customer_id, $points ) ) {
				throw new \RuntimeException( 'Could not return redeemed points to the customer balance.' );
			}

			$original = $this->get_order_transaction( $order_id, Database::TRANSACTION_REDEEMED );
			if ( is_array( $metadata ) ) {
				$metadata['original_redeemed_transaction'] = $original;
				$metadata['points_returned']                = $points;
				$metadata['woocommerce_status']             = $woocommerce_status;
				$metadata['trigger']                        = $trigger;
				$metadata['trigger_status']                 = $woocommerce_status;
				$metadata['trigger_hook']                   = $trigger;
				$metadata['cycle']                          = $cycle;
				$metadata['order_id']                       = $order_id;
				$metadata['loyalty_cycle']                  = $cycle;
			}

			if ( ! $this->transactions->insert(
				array(
					'customer_id'     => $customer_id,
					'order_id'        => $order_id,
					'order_item_id'   => null,
					'type'            => Database::TRANSACTION_RETURNED_REDEEMED,
					'points'          => $points,
					'balance_before'  => $balance_before,
					'balance_after'   => $balance_after,
					'source'          => 'woocommerce_reward_return',
					'description'     => sprintf( 'DevoluciÃ³n de puntos canjeados del pedido #%d por %s.', $order_id, $trigger ),
					'metadata'        => $metadata,
					'idempotency_key' => $idempotency_key,
				)
			) ) {
				throw new \RuntimeException( 'Could not insert the returned redeemed transaction.' );
			}

			if ( ! $this->transaction_manager->commit() ) {
				throw new \RuntimeException( 'Could not commit the returned redeemed transaction.' );
			}

			return self::RESULT_ADDED;
		} catch ( \Throwable $exception ) {
			$this->transaction_manager->rollback();
			return self::RESULT_FAILED;
		}
	}

	/**
	 * Restore previously reversed order movements in one recoverable transaction.
	 *
	 * @return array{status:string,error:string,earned_points:int,redeemed_points:int,current_balance:int,projected_balance:int,missing_points:int,required_balance:int,available_balance:int,idempotency_key:string,cycle:int}
	 */
	public function restore_order_loyalty_movements(
		int $customer_id,
		int $order_id,
		int $earned_points,
		int $redeemed_points,
		int $reversal_transaction_id,
		int $admin_id,
		int $cycle
	): array {
		$cycle           = max( 1, $cycle );
		$idempotency_key = self::cycle_key( 'restored_order', $order_id, $cycle );

		if ( $customer_id <= 0 || $order_id <= 0 || $admin_id <= 0 || $reversal_transaction_id <= 0 || ( $earned_points <= 0 && $redeemed_points <= 0 ) ) {
			return $this->restore_result( self::RESULT_FAILED, 'invalid_restore_request', $earned_points, $redeemed_points, 0, 0, 0, 0, $idempotency_key, $cycle );
		}

		if ( ! $this->transaction_manager->begin() ) {
			return $this->restore_result( self::RESULT_FAILED, 'transaction_not_started', $earned_points, $redeemed_points, 0, 0, 0, 0, $idempotency_key, $cycle );
		}

		try {
			$balance_before = $this->points->lock_balance( $customer_id );
			if ( null === $balance_before ) {
				throw new \RuntimeException( 'Could not lock the customer balance.' );
			}

			if (
				$this->transactions->exists_by_idempotency_key( $idempotency_key ) ||
				$this->transactions->exists_by_idempotency_key( self::cycle_key( 'restored_earned_order', $order_id, $cycle ) ) ||
				$this->transactions->exists_by_idempotency_key( self::cycle_key( 'restored_redeemed_order', $order_id, $cycle ) )
			) {
				if ( ! $this->transaction_manager->commit() ) {
					throw new \RuntimeException( 'Could not commit the duplicate restore check.' );
				}

				return $this->restore_result( self::RESULT_DUPLICATE, '', $earned_points, $redeemed_points, $balance_before, $balance_before + $earned_points - $redeemed_points, 0, 0, $idempotency_key, $cycle );
			}

			$net_change        = $earned_points - $redeemed_points;
			$projected_balance = $balance_before + $net_change;
			if ( $projected_balance < 0 ) {
				$this->transaction_manager->rollback();
				return $this->restore_result( self::RESULT_INSUFFICIENT_BALANCE, 'insufficient_balance', $earned_points, $redeemed_points, $balance_before, $projected_balance, abs( $projected_balance ), $balance_before, $idempotency_key, $cycle );
			}

			$balance_after = $projected_balance;
			if ( $balance_after > 2147483647 ) {
				throw new \RuntimeException( 'Invalid balance after restoration.' );
			}

			if ( 0 !== $net_change && ! $this->points->adjust( $customer_id, $net_change ) ) {
				throw new \RuntimeException( 'Could not restore the customer balance.' );
			}

			$running_balance = $balance_before;
			if ( $earned_points > 0 ) {
				$earned_after = $running_balance + $earned_points;
				if ( ! $this->transactions->insert(
					array(
						'customer_id'     => $customer_id,
						'order_id'        => $order_id,
						'order_item_id'   => null,
						'type'            => Database::TRANSACTION_RESTORED_EARNED,
						'points'          => $earned_points,
						'balance_before'  => $running_balance,
						'balance_after'   => $earned_after,
						'source'          => 'woocommerce_order_loyalty_restore',
						'description'     => sprintf( 'Restauracion de puntos generados del pedido #%d.', $order_id ),
						'metadata'        => array(
							'order_id'                => $order_id,
							'reversal_transaction_id' => $reversal_transaction_id,
							'base_idempotency_key'    => $idempotency_key,
							'loyalty_cycle'           => $cycle,
						),
						'created_by'      => $admin_id,
						'idempotency_key' => self::cycle_key( 'restored_earned_order', $order_id, $cycle ),
					)
				) ) {
					throw new \RuntimeException( 'Could not insert the restored earned transaction.' );
				}
				$running_balance = $earned_after;
			}

			if ( $redeemed_points > 0 ) {
				$redeemed_after = $running_balance - $redeemed_points;
				if ( $redeemed_after < 0 || ! $this->transactions->insert(
					array(
						'customer_id'     => $customer_id,
						'order_id'        => $order_id,
						'order_item_id'   => null,
						'type'            => Database::TRANSACTION_RESTORED_REDEEMED,
						'points'          => -1 * $redeemed_points,
						'balance_before'  => $running_balance,
						'balance_after'   => $redeemed_after,
						'source'          => 'woocommerce_order_loyalty_restore',
						'description'     => sprintf( 'Restauracion de puntos canjeados del pedido #%d.', $order_id ),
						'metadata'        => array(
							'order_id'                => $order_id,
							'reversal_transaction_id' => $reversal_transaction_id,
							'base_idempotency_key'    => $idempotency_key,
							'loyalty_cycle'           => $cycle,
						),
						'created_by'      => $admin_id,
						'idempotency_key' => self::cycle_key( 'restored_redeemed_order', $order_id, $cycle ),
					)
				) ) {
					throw new \RuntimeException( 'Could not insert the restored redeemed transaction.' );
				}
			}

			if ( ! $this->transaction_manager->commit() ) {
				throw new \RuntimeException( 'Could not commit the restored order movements.' );
			}

			return $this->restore_result( self::RESULT_ADDED, '', $earned_points, $redeemed_points, $balance_before, $projected_balance, 0, $balance_after, $idempotency_key, $cycle );
		} catch ( \Throwable $exception ) {
			$this->transaction_manager->rollback();
			return $this->restore_result( self::RESULT_FAILED, 'restore_failed', $earned_points, $redeemed_points, 0, 0, 0, 0, $idempotency_key, $cycle );
		}
	}

	public function get_dashboard_totals(): array {
		return array(
			'customers' => $this->points->count_customers(),
			'earned'    => $this->points->sum_total_earned(),
			'redeemed'  => $this->points->sum_total_redeemed(),
		);
	}

	/**
	 * Build a stable restoration result payload.
	 *
	 * @return array{status:string,error:string,earned_points:int,redeemed_points:int,current_balance:int,projected_balance:int,missing_points:int,required_balance:int,available_balance:int,idempotency_key:string,cycle:int}
	 */
	private function restore_result( string $status, string $error, int $earned_points, int $redeemed_points, int $current_balance, int $projected_balance, int $missing_points, int $available_balance, string $idempotency_key, int $cycle ): array {
		return array(
			'status'            => $status,
			'error'             => $error,
			'earned_points'     => max( 0, $earned_points ),
			'redeemed_points'   => max( 0, $redeemed_points ),
			'current_balance'   => $current_balance,
			'projected_balance' => $projected_balance,
			'missing_points'    => max( 0, $missing_points ),
			'required_balance'  => max( 0, $redeemed_points ),
			'available_balance' => max( 0, $available_balance ),
			'idempotency_key'   => $idempotency_key,
			'cycle'             => max( 1, $cycle ),
		);
	}

	public static function cycle_key( string $operation, int $order_id, int $cycle ): string {
		$operation = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $operation ) ?? '' );

		return $operation . ':' . $order_id . ':cycle:' . max( 1, $cycle );
	}
}
