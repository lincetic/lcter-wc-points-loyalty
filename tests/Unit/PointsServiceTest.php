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
		self::assertSame( 'earned_order:99:cycle:1', $transactions->rows[0]['idempotency_key'] );
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
		self::assertSame( 'reversed_earned_order:101:cycle:1', $transactions->rows[0]['idempotency_key'] );

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
		self::assertSame( 'returned_redeemed_order:103:cycle:1', $transactions->rows[1]['idempotency_key'] );
	}

	public function test_cancelled_then_refunded_only_reverses_accounting_once_in_same_cycle(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 5000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertSame( Points_Service::RESULT_ADDED, $service->reverse_order_earned_points( 7, 2000, 301, 'Cancelled.', array( 'trigger_status' => 'cancelled', 'trigger_hook' => 'woocommerce_order_status_cancelled', 'cycle' => 1, 'order_id' => 301 ), 'cancelled_order:301:cycle:1', 'cancelled', 'woocommerce_order_cancellation', 1 ) );
		self::assertSame( Points_Service::RESULT_DUPLICATE, $service->reverse_order_earned_points( 7, 2000, 301, 'Refunded.', array( 'trigger_status' => 'refunded', 'trigger_hook' => 'woocommerce_order_status_refunded', 'cycle' => 1, 'order_id' => 301 ), 'refunded_order:301:cycle:1', 'refund', 'woocommerce_order_refund', 1 ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->return_order_redeemed_points( 7, 600, 301, 'cancelled', 'woocommerce_order_status_cancelled', array(), 1 ) );
		self::assertSame( Points_Service::RESULT_DUPLICATE, $service->return_order_redeemed_points( 7, 600, 301, 'refunded', 'woocommerce_order_status_refunded', array(), 1 ) );

		self::assertSame( 3600, $service->get_balance( 7 ) );
		self::assertCount( 2, $transactions->rows );
		self::assertSame( 'reversed_earned_order:301:cycle:1', $transactions->rows[0]['idempotency_key'] );
		self::assertSame( 'cancelled', $transactions->rows[0]['metadata']['trigger_status'] );
		self::assertSame( 'woocommerce_order_status_cancelled', $transactions->rows[0]['metadata']['trigger_hook'] );
		self::assertSame( 1, $transactions->rows[0]['metadata']['cycle'] );
		self::assertSame( 301, $transactions->rows[0]['metadata']['order_id'] );
		self::assertSame( 'cancelled_order:301:cycle:1', $transactions->rows[0]['metadata']['event_idempotency_key'] );
		self::assertSame( 'returned_redeemed_order:301:cycle:1', $transactions->rows[1]['idempotency_key'] );
	}

	public function test_failed_then_cancelled_only_reverses_earned_once_in_same_cycle(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 5000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertSame( Points_Service::RESULT_ADDED, $service->reverse_order_earned_points( 7, 2000, 302, 'Failed.', array( 'trigger_status' => 'failed', 'trigger_hook' => 'woocommerce_order_status_failed', 'cycle' => 1, 'order_id' => 302 ), 'failed_order:302:cycle:1', 'failed', 'woocommerce_order_failure', 1 ) );
		self::assertSame( Points_Service::RESULT_DUPLICATE, $service->reverse_order_earned_points( 7, 2000, 302, 'Cancelled.', array( 'trigger_status' => 'cancelled', 'trigger_hook' => 'woocommerce_order_status_cancelled', 'cycle' => 1, 'order_id' => 302 ), 'cancelled_order:302:cycle:1', 'cancelled', 'woocommerce_order_cancellation', 1 ) );

		self::assertSame( 3000, $service->get_balance( 7 ) );
		self::assertCount( 1, $transactions->rows );
		self::assertSame( 'reversed_earned_order:302:cycle:1', $transactions->rows[0]['idempotency_key'] );
		self::assertSame( 'failed', $transactions->rows[0]['metadata']['trigger_status'] );
	}

	public function test_duplicate_refund_hooks_only_return_and_reverse_once(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 5000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertSame( Points_Service::RESULT_ADDED, $service->return_order_redeemed_points( 7, 800, 303, 'refunded', 'woocommerce_order_fully_refunded', array(), 1 ) );
		self::assertSame( Points_Service::RESULT_DUPLICATE, $service->return_order_redeemed_points( 7, 800, 303, 'refunded', 'woocommerce_order_status_refunded', array(), 1 ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->reverse_order_earned_points( 7, 2000, 303, 'Fully refunded.', array( 'trigger_status' => 'refunded', 'trigger_hook' => 'woocommerce_order_fully_refunded', 'cycle' => 1, 'order_id' => 303 ), 'refunded_order:303:cycle:1', 'refund', 'woocommerce_order_refund', 1 ) );
		self::assertSame( Points_Service::RESULT_DUPLICATE, $service->reverse_order_earned_points( 7, 2000, 303, 'Status refunded.', array( 'trigger_status' => 'refunded', 'trigger_hook' => 'woocommerce_order_status_refunded', 'cycle' => 1, 'order_id' => 303 ), 'refunded_order:303:cycle:1', 'refund', 'woocommerce_order_refund', 1 ) );

		self::assertSame( 3800, $service->get_balance( 7 ) );
		self::assertCount( 2, $transactions->rows );
		self::assertSame( 'returned_redeemed_order:303:cycle:1', $transactions->rows[0]['idempotency_key'] );
		self::assertSame( 'reversed_earned_order:303:cycle:1', $transactions->rows[1]['idempotency_key'] );
	}

	public function test_restored_cycle_can_be_cancelled_again_without_duplicate_cycle_one_keys(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 10000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertTrue( $service->award_points_for_order( 7, 5000, 201, 'Earned.', null, 1 ) );
		self::assertTrue( $service->redeem_points( 7, 1000, 201, null, 'Redeemed.', null, 'redeemed_order:201:cycle:1' ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->return_order_redeemed_points( 7, 1000, 201, 'cancelled', 'order_cancelled', array(), 1 ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->reverse_order_earned_points( 7, 5000, 201, 'Cancelled.', null, '', 'cancelled', 'woocommerce_order_cancellation', 1 ) );
		self::assertSame( 10000, $service->get_balance( 7 ) );

		$reversal = $service->get_latest_order_reversal_transaction( 201, 1 );
		self::assertIsArray( $reversal );
		self::assertSame(
			Points_Service::RESULT_ADDED,
			$service->restore_order_loyalty_movements( 7, 201, 5000, 1000, (int) $reversal['id'], 3, 2 )['status']
		);
		self::assertSame( 14000, $service->get_balance( 7 ) );

		self::assertSame( Points_Service::RESULT_ADDED, $service->return_order_redeemed_points( 7, 1000, 201, 'cancelled', 'order_cancelled', array(), 2 ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->reverse_order_earned_points( 7, 5000, 201, 'Cancelled again.', null, '', 'cancelled', 'woocommerce_order_cancellation', 2 ) );
		self::assertSame( 10000, $service->get_balance( 7 ) );
		self::assertSame( 'reversed_earned_order:201:cycle:1', $transactions->rows[3]['idempotency_key'] );
		self::assertSame( 'restored_earned_order:201:cycle:2', $transactions->rows[4]['idempotency_key'] );
		self::assertSame( 'restored_redeemed_order:201:cycle:2', $transactions->rows[5]['idempotency_key'] );
		self::assertSame( 'returned_redeemed_order:201:cycle:2', $transactions->rows[6]['idempotency_key'] );
		self::assertSame( 'reversed_earned_order:201:cycle:2', $transactions->rows[7]['idempotency_key'] );
	}

	public function test_cancelled_cycle_one_restored_cycle_two_refunded_reverses_each_cycle_once(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 10000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertTrue( $service->award_points_for_order( 7, 5000, 304, 'Earned.', null, 1 ) );
		self::assertTrue( $service->redeem_points( 7, 1000, 304, null, 'Redeemed.', null, 'redeemed_order:304:cycle:1' ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->return_order_redeemed_points( 7, 1000, 304, 'cancelled', 'woocommerce_order_status_cancelled', array(), 1 ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->reverse_order_earned_points( 7, 5000, 304, 'Cancelled.', array( 'trigger_status' => 'cancelled', 'trigger_hook' => 'woocommerce_order_status_cancelled', 'cycle' => 1, 'order_id' => 304 ), 'cancelled_order:304:cycle:1', 'cancelled', 'woocommerce_order_cancellation', 1 ) );
		$reversal = $service->get_latest_order_reversal_transaction( 304, 1 );
		self::assertIsArray( $reversal );

		self::assertSame( Points_Service::RESULT_ADDED, $service->restore_order_loyalty_movements( 7, 304, 5000, 1000, (int) $reversal['id'], 3, 2 )['status'] );
		self::assertSame( Points_Service::RESULT_ADDED, $service->return_order_redeemed_points( 7, 1000, 304, 'refunded', 'woocommerce_order_status_refunded', array(), 2 ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->reverse_order_earned_points( 7, 5000, 304, 'Refunded.', array( 'trigger_status' => 'refunded', 'trigger_hook' => 'woocommerce_order_status_refunded', 'cycle' => 2, 'order_id' => 304 ), 'refunded_order:304:cycle:2', 'refund', 'woocommerce_order_refund', 2 ) );

		self::assertSame( 10000, $service->get_balance( 7 ) );
		self::assertSame( 'reversed_earned_order:304:cycle:1', $transactions->rows[3]['idempotency_key'] );
		self::assertSame( 'restored_earned_order:304:cycle:2', $transactions->rows[4]['idempotency_key'] );
		self::assertSame( 'restored_redeemed_order:304:cycle:2', $transactions->rows[5]['idempotency_key'] );
		self::assertSame( 'returned_redeemed_order:304:cycle:2', $transactions->rows[6]['idempotency_key'] );
		self::assertSame( 'reversed_earned_order:304:cycle:2', $transactions->rows[7]['idempotency_key'] );
	}

	public function test_repeating_cancelled_inside_same_cycle_is_duplicate(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 10000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertSame( Points_Service::RESULT_ADDED, $service->reverse_order_earned_points( 7, 3000, 202, 'Cancelled.', null, '', 'cancelled', 'woocommerce_order_cancellation', 2 ) );
		self::assertSame( Points_Service::RESULT_DUPLICATE, $service->reverse_order_earned_points( 7, 3000, 202, 'Cancelled again.', null, '', 'cancelled', 'woocommerce_order_cancellation', 2 ) );
		self::assertSame( 7000, $service->get_balance( 7 ) );
		self::assertCount( 1, $transactions->rows );
	}

	public function test_restore_uses_projected_balance_not_current_balance_only(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 6000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		$result = $service->restore_order_loyalty_movements( 7, 203, 5000, 10000, 88, 3, 2 );

		self::assertSame( Points_Service::RESULT_ADDED, $result['status'] );
		self::assertSame( 1000, $service->get_balance( 7 ) );
		self::assertSame( 6000, $result['current_balance'] );
		self::assertSame( 1000, $result['projected_balance'] );
		self::assertCount( 2, $transactions->rows );
	}

	public function test_restore_rejects_negative_projected_balance_without_partial_movements(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 4000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		$result = $service->restore_order_loyalty_movements( 7, 204, 5000, 10000, 88, 3, 2 );

		self::assertSame( Points_Service::RESULT_INSUFFICIENT_BALANCE, $result['status'] );
		self::assertSame( 4000, $service->get_balance( 7 ) );
		self::assertSame( 4000, $result['current_balance'] );
		self::assertSame( -1000, $result['projected_balance'] );
		self::assertSame( 1000, $result['missing_points'] );
		self::assertCount( 0, $transactions->rows );
	}

	public function test_cycle_lookups_do_not_mix_movements_from_other_cycles(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 10000 );
		$transactions = new In_Memory_Transactions_Repository();
		$service      = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertTrue( $service->award_points_for_order( 7, 5000, 305, 'Earned.', null, 1 ) );
		self::assertTrue( $service->redeem_points( 7, 1000, 305, null, 'Redeemed.', null, 'redeemed_order:305:cycle:1' ) );
		self::assertSame( Points_Service::RESULT_ADDED, $service->restore_order_loyalty_movements( 7, 305, 3000, 700, 88, 3, 2 )['status'] );

		self::assertSame( 5000, $service->get_order_earned_points_for_cycle( 305, 1 ) );
		self::assertSame( 1000, $service->get_order_redeemed_points_for_cycle( 305, 1 ) );
		self::assertSame( 3000, $service->get_order_earned_points_for_cycle( 305, 2 ) );
		self::assertSame( 700, $service->get_order_redeemed_points_for_cycle( 305, 2 ) );
	}

	public function test_legacy_uncycled_terminal_key_is_cycle_one_duplicate(): void {
		$balances     = new In_Memory_Customer_Points_Repository( 10000 );
		$transactions = new In_Memory_Transactions_Repository();
		$transactions->insert(
			array(
				'id'              => 1,
				'customer_id'     => 7,
				'order_id'        => 205,
				'type'            => 'cancelled',
				'points'          => -3000,
				'balance_before'  => 10000,
				'balance_after'   => 7000,
				'idempotency_key' => 'cancelled_order:205',
			)
		);
		$service = new Points_Service( $balances, $transactions, new In_Memory_Transaction_Manager() );

		self::assertSame( Points_Service::RESULT_DUPLICATE, $service->reverse_order_earned_points( 7, 3000, 205, 'Cancelled.', null, '', 'cancelled', 'woocommerce_order_cancellation', 1 ) );
		self::assertCount( 1, $transactions->rows );
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

	public function get_points_for_order_and_type( int $order_id, string $type ): int {
		$total = 0;
		foreach ( $this->rows as $row ) {
			if ( $order_id === (int) $row['order_id'] && $type === $row['type'] ) {
				$total += (int) $row['points'];
			}
		}
		return $total;
	}

	public function insert( array $transaction ): bool {
		if ( ! isset( $transaction['id'] ) ) {
			$transaction['id'] = count( $this->rows ) + 1;
		}
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

	public function get_points_by_idempotency_key( string $idempotency_key ): int {
		foreach ( $this->rows as $row ) {
			if ( $idempotency_key === (string) $row['idempotency_key'] ) {
				return (int) $row['points'];
			}
		}
		return 0;
	}

	public function find_latest_by_idempotency_keys( array $idempotency_keys ): ?array {
		$latest = null;
		foreach ( $this->rows as $row ) {
			if ( in_array( (string) $row['idempotency_key'], $idempotency_keys, true ) ) {
				if ( null === $latest || (int) $row['id'] > (int) $latest['id'] ) {
					$latest = $row;
				}
			}
		}
		return $latest;
	}

	public function find_latest_for_order_and_types( int $order_id, array $types ): ?array {
		$latest = null;
		foreach ( $this->rows as $row ) {
			if ( $order_id === (int) $row['order_id'] && in_array( $row['type'], $types, true ) ) {
				if ( null === $latest || (int) $row['id'] > (int) $latest['id'] ) {
					$latest = $row;
				}
			}
		}
		return $latest;
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
