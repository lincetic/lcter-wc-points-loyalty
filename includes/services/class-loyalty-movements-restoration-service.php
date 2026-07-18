<?php
/**
 * Application service for explicitly restoring reversed order loyalty movements.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Services;

use LCTER_WCPL\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and coordinates manual restoration of a reversed accounting cycle.
 */
class Loyalty_Movements_Restoration_Service {
	private Points_Service $points;

	public function __construct( ?Points_Service $points = null ) {
		$this->points = $points ?? new Points_Service();
	}

	/**
	 * Restore one reversed order cycle.
	 *
	 * @return array{status:string,error:string,earned_points:int,redeemed_points:int,current_balance:int,projected_balance:int,missing_points:int,required_balance:int,available_balance:int,idempotency_key:string,cycle:int}
	 */
	public function restore_order( int $customer_id, int $order_id, bool $is_paid, int $admin_id, int $current_cycle = 1 ): array {
		$current_cycle = max( 1, $current_cycle );
		$restore_cycle = $current_cycle + 1;
		$empty_key     = Points_Service::cycle_key( 'restored_order', $order_id, $restore_cycle );
		if ( $customer_id <= 0 || $order_id <= 0 || $admin_id <= 0 ) {
			return $this->result( Points_Service::RESULT_FAILED, 'invalid_restore_request', 0, 0, 0, 0, 0, 0, $empty_key, $restore_cycle );
		}

		if ( ! $is_paid ) {
			return $this->result( Points_Service::RESULT_FAILED, 'order_not_paid', 0, 0, 0, 0, 0, 0, $empty_key, $restore_cycle );
		}

		$earned_points   = $this->points->get_order_earned_points_for_cycle( $order_id, $current_cycle );
		$redeemed_points = $this->points->get_order_redeemed_points_for_cycle( $order_id, $current_cycle );
		if ( 0 === $earned_points && 0 === $redeemed_points ) {
			return $this->result( Points_Service::RESULT_FAILED, 'original_movements_missing', 0, 0, 0, 0, 0, 0, $empty_key, $restore_cycle );
		}

		$reversal = $this->points->get_latest_order_reversal_transaction( $order_id, $current_cycle );
		if ( ! is_array( $reversal ) || empty( $reversal['id'] ) ) {
			return $this->result( Points_Service::RESULT_FAILED, 'reversal_missing', $earned_points, $redeemed_points, 0, 0, 0, 0, $empty_key, $restore_cycle );
		}

		$reversal_transaction_id = (int) $reversal['id'];
		$idempotency_key        = Points_Service::cycle_key( 'restored_order', $order_id, $restore_cycle );
		if (
			$this->points->has_idempotency_key( $idempotency_key ) ||
			$this->points->has_idempotency_key( Points_Service::cycle_key( 'restored_earned_order', $order_id, $restore_cycle ) ) ||
			$this->points->has_idempotency_key( Points_Service::cycle_key( 'restored_redeemed_order', $order_id, $restore_cycle ) )
		) {
			$current_balance = $this->points->get_balance( $customer_id );
			return $this->result( Points_Service::RESULT_DUPLICATE, '', $earned_points, $redeemed_points, $current_balance, $current_balance + $earned_points - $redeemed_points, 0, $current_balance, $idempotency_key, $restore_cycle );
		}

		return $this->points->restore_order_loyalty_movements(
			$customer_id,
			$order_id,
			$earned_points,
			$redeemed_points,
			$reversal_transaction_id,
			$admin_id,
			$restore_cycle
		);
	}

	/**
	 * Build a stable service response.
	 *
	 * @return array{status:string,error:string,earned_points:int,redeemed_points:int,current_balance:int,projected_balance:int,missing_points:int,required_balance:int,available_balance:int,idempotency_key:string,cycle:int}
	 */
	private function result( string $status, string $error, int $earned_points, int $redeemed_points, int $current_balance, int $projected_balance, int $missing_points, int $available_balance, string $idempotency_key, int $cycle ): array {
		return array(
			'status'            => $status,
			'error'             => $error,
			'earned_points'     => $earned_points,
			'redeemed_points'   => $redeemed_points,
			'current_balance'   => $current_balance,
			'projected_balance' => $projected_balance,
			'missing_points'    => $missing_points,
			'required_balance'  => $redeemed_points,
			'available_balance' => $available_balance,
			'idempotency_key'   => $idempotency_key,
			'cycle'             => $cycle,
		);
	}
}
