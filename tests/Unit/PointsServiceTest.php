<?php
/**
 * Points service unit tests.
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Repositories\Customer_Points_Repository;
use LCTER_WCPL\Repositories\Transactions_Repository;
use LCTER_WCPL\Repositories\Transaction_Manager;
use LCTER_WCPL\Services\Points_Service;
use PHPUnit\Framework\TestCase;

final class PointsServiceTest extends TestCase {
	public function test_earned_order_is_idempotent(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 0 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertTrue( $service->award_points_for_order( 7, 1250, 99 ) );
		self::assertTrue( $service->award_points_for_order( 7, 1250, 99 ) );
		self::assertSame( 1250, $service->get_balance( 7 ) );
		self::assertCount( 1, $transactions->rows );
		self::assertSame( 'earned_order:99', $transactions->rows[0]['idempotency_key'] );
		self::assertSame( 0, $transactions->rows[0]['balance_before'] );
		self::assertSame( 1250, $transactions->rows[0]['balance_after'] );
	}

	public function test_redemption_cannot_make_balance_negative_and_is_idempotent(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 1000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertFalse( $service->redeem_points( 7, 1001, 100, null, null, null, 'redeemed_order:100' ) );
		self::assertSame( 1000, $service->get_balance( 7 ) );
		self::assertTrue( $service->redeem_points( 7, 400, 100, null, null, null, 'redeemed_order:100' ) );
		self::assertTrue( $service->redeem_points( 7, 400, 100, null, null, null, 'redeemed_order:100' ) );
		self::assertSame( 600, $service->get_balance( 7 ) );
		self::assertCount( 1, $transactions->rows );
		self::assertSame( -400, $transactions->rows[0]['points'] );
		self::assertSame( 1000, $transactions->rows[0]['balance_before'] );
		self::assertSame( 600, $transactions->rows[0]['balance_after'] );
	}
}

final class In_Memory_Customer_Points_Repository extends Customer_Points_Repository {
	private int $balance;

	public function __construct( int $balance ) {
		$this->balance = $balance;
	}

	public function get_balance( int $customer_id ): int {
		return $this->balance;
	}

	public function lock_balance( int $customer_id ): ?int {
		return $this->balance;
	}

	public function add( int $customer_id, int $points ): bool {
		$this->balance += $points;
		return true;
	}

	public function redeem( int $customer_id, int $points ): bool {
		if ( $this->balance < $points ) {
			return false;
		}

		$this->balance -= $points;
		return true;
	}
}

final class In_Memory_Transactions_Repository extends Transactions_Repository {
	/** @var array<int,array<string,mixed>> */
	public array $rows = array();

	public function exists_for_order_and_type( int $order_id, string $type ): bool {
		foreach ( $this->rows as $row ) {
			if ( $order_id === (int) $row['order_id'] && $type === $row['type'] ) {
				return true;
			}
		}
		return false;
	}

	public function exists_by_idempotency_key( string $idempotency_key ): bool {
		foreach ( $this->rows as $row ) {
			if ( $idempotency_key === $row['idempotency_key'] ) {
				return true;
			}
		}
		return false;
	}

	public function insert( array $transaction ): bool {
		$this->rows[] = $transaction;
		return true;
	}
}

final class In_Memory_Transaction_Manager extends Transaction_Manager {
	public function begin(): bool {
		return true;
	}

	public function commit(): bool {
		return true;
	}

	public function rollback(): void {
	}
}
