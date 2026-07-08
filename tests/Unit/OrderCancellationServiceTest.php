<?php
/**
 * Order cancellation service unit tests.
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Database;
use LCTER_WCPL\Services\Order_Cancellation_Service;
use LCTER_WCPL\Services\Points_Service;
use PHPUnit\Framework\TestCase;

final class OrderCancellationServiceTest extends TestCase {
	public function test_order_without_earned_transaction_is_skipped(): void {
		$points  = new Cancellation_Points_Service( 0, 0, Points_Service::RESULT_ADDED, Points_Service::RESULT_ADDED );
		$service = new Order_Cancellation_Service( $points );

		self::assertSame(
			array( 'status' => 'skipped', 'error' => 'order_did_not_earn_points', 'points' => 0 ),
			$service->reverse_order( 7, 55, 'order_cancelled' )
		);
		self::assertSame( 0, $points->reverse_calls );
	}

	public function test_earned_transaction_is_reversed_with_original_points(): void {
		$points  = new Cancellation_Points_Service( 6015, 0, Points_Service::RESULT_ADDED, Points_Service::RESULT_ADDED );
		$service = new Order_Cancellation_Service( $points );

		self::assertSame(
			array( 'status' => 'reversed', 'error' => '', 'points' => 6015 ),
			$service->reverse_order( 7, 55, 'order_cancelled' )
		);
		self::assertSame( 1, $points->reverse_calls );
		self::assertSame( 6015, $points->last_reversed_points );
		self::assertSame( 'cancelled_order:55', $points->last_reverse_idempotency_key );
		self::assertSame( Database::TRANSACTION_CANCELLED, $points->last_reverse_transaction_type );
	}

	public function test_insufficient_balance_becomes_processing_error(): void {
		$points  = new Cancellation_Points_Service( 6015, 0, Points_Service::RESULT_INSUFFICIENT_BALANCE, Points_Service::RESULT_ADDED );
		$service = new Order_Cancellation_Service( $points );

		self::assertSame(
			array( 'status' => 'processing_error', 'error' => 'insufficient_balance', 'points' => 6015 ),
			$service->reverse_order( 7, 55, 'order_fully_refunded' )
		);
	}

	public function test_refunded_order_uses_refund_transaction_key(): void {
		$points  = new Cancellation_Points_Service( 6015, 0, Points_Service::RESULT_ADDED, Points_Service::RESULT_ADDED );
		$service = new Order_Cancellation_Service( $points );

		self::assertSame(
			array( 'status' => 'reversed', 'error' => '', 'points' => 6015 ),
			$service->reverse_order( 7, 55, 'order_fully_refunded', array( 'woocommerce_status' => 'refunded' ) )
		);
		self::assertSame( 'refunded_order:55', $points->last_reverse_idempotency_key );
		self::assertSame( Database::TRANSACTION_REFUND, $points->last_reverse_transaction_type );
	}

	public function test_redeemed_points_are_returned_with_terminal_status(): void {
		$points  = new Cancellation_Points_Service( 0, -400, Points_Service::RESULT_ADDED, Points_Service::RESULT_ADDED );
		$service = new Order_Cancellation_Service( $points );

		self::assertSame(
			array( 'status' => 'returned', 'error' => '', 'points' => 400 ),
			$service->return_redeemed_order( 7, 55, 'order_failed', array( 'woocommerce_status' => 'failed' ) )
		);
		self::assertSame( 1, $points->return_calls );
		self::assertSame( 400, $points->last_returned_points );
		self::assertSame( 'failed', $points->last_return_status );
	}
}

final class Cancellation_Points_Service extends Points_Service {
	public int $reverse_calls = 0;
	public int $return_calls = 0;
	public int $last_reversed_points = 0;
	public int $last_returned_points = 0;
	public string $last_reverse_idempotency_key = '';
	public string $last_reverse_transaction_type = '';
	public string $last_return_status = '';

	private int $earned_points;
	private int $redeemed_points;
	private string $reverse_result;
	private string $return_result;

	public function __construct( int $earned_points, int $redeemed_points, string $reverse_result, string $return_result ) {
		$this->earned_points   = $earned_points;
		$this->redeemed_points = $redeemed_points;
		$this->reverse_result  = $reverse_result;
		$this->return_result   = $return_result;
	}

	public function get_order_transaction_points( int $order_id, string $type ): int {
		if ( $order_id <= 0 ) {
			throw new \RuntimeException( 'Unexpected transaction lookup.' );
		}

		if ( Database::TRANSACTION_EARNED === $type ) {
			return $this->earned_points;
		}

		if ( Database::TRANSACTION_REDEEMED === $type ) {
			return $this->redeemed_points;
		}

		throw new \RuntimeException( 'Unexpected transaction type lookup.' );
	}

	public function reverse_order_earned_points( int $customer_id, int $points, int $order_id, string $reason, $metadata = null, string $idempotency_key = '', string $transaction_type = Database::TRANSACTION_CANCELLED, string $source = 'woocommerce_order_cancellation' ): string {
		unset( $customer_id, $order_id, $reason, $metadata, $source );
		++$this->reverse_calls;
		$this->last_reversed_points = $points;
		$this->last_reverse_idempotency_key = $idempotency_key;
		$this->last_reverse_transaction_type = $transaction_type;
		return $this->reverse_result;
	}

	public function return_order_redeemed_points( int $customer_id, int $points, int $order_id, string $woocommerce_status, string $trigger, $metadata = null ): string {
		unset( $customer_id, $order_id, $trigger, $metadata );
		++$this->return_calls;
		$this->last_returned_points = $points;
		$this->last_return_status   = $woocommerce_status;
		return $this->return_result;
	}
}
