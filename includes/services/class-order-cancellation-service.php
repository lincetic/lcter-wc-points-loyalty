<?php
/**
 * Application service for reversing points from cancelled orders.
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
 * Coordinates a full earned-points reversal for one order.
 */
class Order_Cancellation_Service {
	private Points_Service $points;

	public function __construct( ?Points_Service $points = null ) {
		$this->points = $points ?? new Points_Service();
	}

	/**
	 * Reverse points only when an earned transaction exists for the order.
	 *
	 * @return array{status:string,error:string,points:int}
	 */
	public function reverse_order( int $customer_id, int $order_id, string $trigger, array $context = array() ): array {
		if ( $customer_id <= 0 || $order_id <= 0 ) {
			return $this->result( 'skipped', 'invalid_customer_or_order', 0 );
		}

		$earned_points = max( 0, $this->points->get_order_transaction_points( $order_id, Database::TRANSACTION_EARNED ) );
		if ( 0 === $earned_points ) {
			return $this->result( 'skipped', 'order_did_not_earn_points', 0 );
		}

		$result = $this->points->reverse_order_earned_points(
			$customer_id,
			$earned_points,
			$order_id,
			sprintf( 'Reversión de puntos del pedido #%d por %s.', $order_id, $trigger ),
			array_merge(
				array(
					'trigger'                => $trigger,
					'earned_points_reversed' => $earned_points,
					'earned_idempotency_key' => 'earned_order:' . $order_id,
				),
				$context
			)
		);

		if ( Points_Service::RESULT_ADDED === $result ) {
			return $this->result( 'reversed', '', $earned_points );
		}

		if ( Points_Service::RESULT_DUPLICATE === $result ) {
			return $this->result( 'duplicate', '', $earned_points );
		}

		return $this->result( 'processing_error', $result, $earned_points );
	}

	/**
	 * Build a stable result payload.
	 *
	 * @return array{status:string,error:string,points:int}
	 */
	private function result( string $status, string $error, int $points ): array {
		return array(
			'status' => $status,
			'error'  => $error,
			'points' => $points,
		);
	}
}
