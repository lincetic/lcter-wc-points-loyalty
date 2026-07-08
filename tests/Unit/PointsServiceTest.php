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

	public function test_initial_bonus_remains_unique_when_configured_amount_changes(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 0 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertSame(
			Points_Service::RESULT_ADDED,
			$service->add_points_with_status( 7, 10000, 'initial_bonus', null, null, null, 'Inicial.', null, 99, 'initial_bonus:7:10000' )
		);
		self::assertSame(
			Points_Service::RESULT_DUPLICATE,
			$service->add_points_with_status( 7, 12500, 'initial_bonus', null, null, null, 'Nuevo.', null, 99, 'initial_bonus:7' )
		);
		self::assertSame( 10000, $service->get_balance( 7 ) );
		self::assertCount( 1, $transactions->rows );
	}

	public function test_cancelled_order_reversal_is_atomic_and_idempotent(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 1000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		$result = $service->reverse_order_earned_points( 7, 400, 101, 'Cancelled.' );
		self::assertSame( Points_Service::RESULT_ADDED, $result );
		self::assertSame( 600, $service->get_balance( 7 ) );
		self::assertSame( 'cancelled', $transactions->rows[0]['type'] );
		self::assertSame( -400, $transactions->rows[0]['points'] );
		self::assertSame( 1000, $transactions->rows[0]['balance_before'] );
		self::assertSame( 600, $transactions->rows[0]['balance_after'] );
		self::assertSame( 'cancelled_order:101', $transactions->rows[0]['idempotency_key'] );

		self::assertSame(
			Points_Service::RESULT_DUPLICATE,
			$service->reverse_order_earned_points( 7, 400, 101, 'Cancelled again.' )
		);
		self::assertSame( 600, $service->get_balance( 7 ) );
		self::assertCount( 1, $transactions->rows );
	}

	public function test_cancelled_order_reversal_fails_without_negative_balance(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 399 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertSame(
			Points_Service::RESULT_INSUFFICIENT_BALANCE,
			$service->reverse_order_earned_points( 7, 400, 102, 'Cancelled.' )
		);
		self::assertSame( 399, $service->get_balance( 7 ) );
		self::assertCount( 0, $transactions->rows );
	}

	public function test_returned_redeemed_points_are_idempotent_across_terminal_statuses(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 600 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertTrue( $service->redeem_points( 7, 400, 103, null, 'Redeemed.', null, 'redeemed_order:103' ) );
		self::assertSame( 200, $service->get_balance( 7 ) );
		self::assertSame(
			Points_Service::RESULT_ADDED,
			$service->return_order_redeemed_points( 7, 400, 103, 'cancelled', 'order_cancelled', array( 'woocommerce_status' => 'cancelled' ) )
		);
		self::assertSame( 600, $service->get_balance( 7 ) );
		self::assertSame(
			Points_Service::RESULT_DUPLICATE,
			$service->return_order_redeemed_points( 7, 400, 103, 'refunded', 'order_refunded', array( 'woocommerce_status' => 'refunded' ) )
		);
		self::assertSame( 600, $service->get_balance( 7 ) );
		self::assertCount( 2, $transactions->rows );
		self::assertSame( 'returned_redeemed', $transactions->rows[1]['type'] );
		self::assertSame( 400, $transactions->rows[1]['points'] );
		self::assertSame( 200, $transactions->rows[1]['balance_before'] );
		self::assertSame( 600, $transactions->rows[1]['balance_after'] );
		self::assertSame( 'returned_redeemed_order:103:cancelled', $transactions->rows[1]['idempotency_key'] );
	}

	public function test_manual_adjustment_records_signed_delta_and_balances(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 1000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertSame( Points_Service::RESULT_ADDED, $service->adjust_points( 7, 250, 'Corrección aprobada.', 99 ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->adjust_points( 7, -400, 'Incidencia corregida.', 99 ) );
		self::assertSame( 850, $service->get_balance( 7 ) );
		self::assertCount( 2, $transactions->rows );
		self::assertSame( 'manual_adjustment', $transactions->rows[1]['type'] );
		self::assertSame( -400, $transactions->rows[1]['points'] );
		self::assertSame( 1250, $transactions->rows[1]['balance_before'] );
		self::assertSame( 850, $transactions->rows[1]['balance_after'] );
		self::assertSame( 'Incidencia corregida.', $transactions->rows[1]['description'] );
		self::assertSame( 99, $transactions->rows[1]['created_by'] );
	}

	public function test_manual_adjustment_requires_description_and_cannot_make_balance_negative(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 100 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertSame( Points_Service::RESULT_FAILED, $service->adjust_points( 7, 25, '', 99 ) );
		self::assertSame( Points_Service::RESULT_INSUFFICIENT_BALANCE, $service->adjust_points( 7, -101, 'Demasiado.', 99 ) );
		self::assertSame( 100, $service->get_balance( 7 ) );
		self::assertCount( 0, $transactions->rows );
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

	public function reverse_earned( int $customer_id, int $points ): bool {
		if ( $this->balance < $points ) {
			return false;
		}

		$this->balance -= $points;
		return true;
	}

	public function adjust( int $customer_id, int $adjustment ): bool {
		if ( $this->balance + $adjustment < 0 ) {
			return false;
		}

		$this->balance += $adjustment;
		return true;
	}
}

final class In_Memory_Transactions_Repository extends Transactions_Repository {
	/** @var array<int,array<string,mixed>> */
	public array $rows = array();

	public function exists_for_customer_and_type( int $customer_id, string $type ): bool {
		foreach ( $this->rows as $row ) {
			if ( $customer_id === (int) $row['customer_id'] && $type === $row['type'] ) {
				return true;
			}
		}
		return false;
	}

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

	public function find_first_for_order_and_type( int $order_id, string $type ): ?array {
		foreach ( $this->rows as $row ) {
			if ( $order_id === (int) $row['order_id'] && $type === $row['type'] ) {
				return $row;
			}
		}
		return null;
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
