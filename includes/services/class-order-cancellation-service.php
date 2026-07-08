<?php
/**
 * Application service for reversing points from terminal orders.
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
 * Coordinates earned-points reversals and redeemed-points returns for one order.
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

		$reversal_context = $this->get_reversal_context( $order_id, $trigger, $context );
		$earned_points    = max( 0, $this->points->get_order_transaction_points( $order_id, Database::TRANSACTION_EARNED ) );
		if ( 0 === $earned_points ) {
			return $this->result( 'skipped', 'order_did_not_earn_points', 0 );
		}

		$result = $this->points->reverse_order_earned_points(
			$customer_id,
			$earned_points,
			$order_id,
			sprintf( 'Reversion de puntos del pedido #%d por %s.', $order_id, $trigger ),
			array_merge(
				array(
					'trigger'                => $trigger,
					'earned_points_reversed' => $earned_points,
					'earned_idempotency_key' => 'earned_order:' . $order_id,
					'woocommerce_status'     => $reversal_context['woocommerce_status'],
				),
				$context
			),
			$reversal_context['idempotency_key'],
			$reversal_context['transaction_type'],
			$reversal_context['source']
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
	 * Return points spent on redeemed rewards when the order reaches a terminal status.
	 *
	 * @return array{status:string,error:string,points:int}
	 */
	public function return_redeemed_order( int $customer_id, int $order_id, string $trigger, array $context = array() ): array {
		if ( $customer_id <= 0 || $order_id <= 0 ) {
			return $this->result( 'skipped', 'invalid_customer_or_order', 0 );
		}

		$redeemed_points = abs( min( 0, $this->points->get_order_transaction_points( $order_id, Database::TRANSACTION_REDEEMED ) ) );
		if ( 0 === $redeemed_points ) {
			return $this->result( 'skipped', 'order_did_not_redeem_points', 0 );
		}

		$reversal_context = $this->get_reversal_context( $order_id, $trigger, $context );
		$result           = $this->points->return_order_redeemed_points(
			$customer_id,
			$redeemed_points,
			$order_id,
			$reversal_context['woocommerce_status'],
			$trigger,
			array_merge(
				array(
					'order_id'                 => $order_id,
					'trigger'                  => $trigger,
					'redeemed_points_returned' => $redeemed_points,
					'redeemed_idempotency_key' => 'redeemed_order:' . $order_id,
					'return_idempotency_key'   => 'returned_redeemed_order:' . $order_id . ':' . $reversal_context['woocommerce_status'],
					'woocommerce_status'       => $reversal_context['woocommerce_status'],
				),
				$context
			)
		);

		if ( Points_Service::RESULT_ADDED === $result ) {
			return $this->result( 'returned', '', $redeemed_points );
		}

		if ( Points_Service::RESULT_DUPLICATE === $result ) {
			return $this->result( 'duplicate', '', $redeemed_points );
		}

		return $this->result( 'processing_error', $result, $redeemed_points );
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

	/**
	 * Resolve transaction settings for an order terminal status.
	 *
	 * @return array{idempotency_key:string,transaction_type:string,source:string,woocommerce_status:string}
	 */
	private function get_reversal_context( int $order_id, string $trigger, array $context ): array {
		$status = isset( $context['woocommerce_status'] ) ? strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $context['woocommerce_status'] ) ?? '' ) : '';
		if ( '' === $status ) {
			if ( false !== strpos( $trigger, 'refund' ) ) {
				$status = 'refunded';
			} elseif ( false !== strpos( $trigger, 'failed' ) ) {
				$status = 'failed';
			} else {
				$status = 'cancelled';
			}
		}

		if ( 'refunded' === $status ) {
			return array(
				'idempotency_key'   => 'refunded_order:' . $order_id,
				'transaction_type'  => Database::TRANSACTION_REFUND,
				'source'            => 'woocommerce_order_refund',
				'woocommerce_status' => $status,
			);
		}

		if ( 'failed' === $status ) {
			return array(
				'idempotency_key'   => 'failed_order:' . $order_id,
				'transaction_type'  => Database::TRANSACTION_FAILED,
				'source'            => 'woocommerce_order_failure',
				'woocommerce_status' => $status,
			);
		}

		return array(
			'idempotency_key'   => 'cancelled_order:' . $order_id,
			'transaction_type'  => Database::TRANSACTION_CANCELLED,
			'source'            => 'woocommerce_order_cancellation',
			'woocommerce_status' => 'cancelled',
		);
	}
}
