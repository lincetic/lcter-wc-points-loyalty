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
		$points  = new Cancellation_Points_Service( 0, Points_Service::RESULT_ADDED );
		$service = new Order_Cancellation_Service( $points );

		self::assertSame(
			array( 'status' => 'skipped', 'error' => 'order_did_not_earn_points', 'points' => 0 ),
			$service->reverse_order( 7, 55, 'order_cancelled' )
		);
		self::assertSame( 0, $points->reverse_calls );
	}

	public function test_earned_transaction_is_reversed_with_original_points(): void {
		$points  = new Cancellation_Points_Service( 6015, Points_Service::RESULT_ADDED );
		$service = new Order_Cancellation_Service( $points );

		self::assertSame(
			array( 'status' => 'reversed', 'error' => '', 'points' => 6015 ),
			$service->reverse_order( 7, 55, 'order_cancelled' )
		);
		self::assertSame( 1, $points->reverse_calls );
		self::assertSame( 6015, $points->last_reversed_points );
	}

	public function test_insufficient_balance_becomes_processing_error(): void {
		$points  = new Cancellation_Points_Service( 6015, Points_Service::RESULT_INSUFFICIENT_BALANCE );
		$service = new Order_Cancellation_Service( $points );

		self::assertSame(
			array( 'status' => 'processing_error', 'error' => 'insufficient_balance', 'points' => 6015 ),
			$service->reverse_order( 7, 55, 'order_fully_refunded' )
		);
	}
}

final class Cancellation_Points_Service extends Points_Service {
	public int $reverse_calls = 0;
	public int $last_reversed_points = 0;

	private int $earned_points;
	private string $result;

	public function __construct( int $earned_points, string $result ) {
		$this->earned_points = $earned_points;
		$this->result        = $result;
	}

	public function get_order_transaction_points( int $order_id, string $type ): int {
		self::assertValidLookup( $order_id, $type );
		return $this->earned_points;
	}

	public function reverse_order_earned_points( int $customer_id, int $points, int $order_id, string $reason, $metadata = null ): string {
		unset( $customer_id, $order_id, $reason, $metadata );
		++$this->reverse_calls;
		$this->last_reversed_points = $points;
		return $this->result;
	}

	private static function assertValidLookup( int $order_id, string $type ): void {
		if ( $order_id <= 0 || Database::TRANSACTION_EARNED !== $type ) {
			throw new \RuntimeException( 'Unexpected earned transaction lookup.' );
		}
	}
}
