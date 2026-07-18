<?php
/**
 * Loyalty movements restoration service unit tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Services\Loyalty_Movements_Restoration_Service;
use LCTER_WCPL\Services\Points_Service;
use PHPUnit\Framework\TestCase;

final class LoyaltyMovementsRestorationServiceTest extends TestCase {
	public function test_unpaid_order_cannot_be_restored(): void {
		$points  = new Restoration_Points_Service();
		$service = new Loyalty_Movements_Restoration_Service( $points );

		$result = $service->restore_order( 7, 55, false, 3 );

		self::assertSame( Points_Service::RESULT_FAILED, $result['status'] );
		self::assertSame( 'order_not_paid', $result['error'] );
		self::assertSame( 0, $points->restore_calls );
	}

	public function test_restoration_requires_original_movements(): void {
		$points  = new Restoration_Points_Service();
		$service = new Loyalty_Movements_Restoration_Service( $points );

		$result = $service->restore_order( 7, 55, true, 3 );

		self::assertSame( Points_Service::RESULT_FAILED, $result['status'] );
		self::assertSame( 'original_movements_missing', $result['error'] );
		self::assertSame( 0, $points->restore_calls );
	}

	public function test_restoration_requires_reversal_transaction(): void {
		$points                 = new Restoration_Points_Service();
		$points->earned_points  = 6015;
		$points->redeemed_points = -400;
		$service                = new Loyalty_Movements_Restoration_Service( $points );

		$result = $service->restore_order( 7, 55, true, 3 );

		self::assertSame( Points_Service::RESULT_FAILED, $result['status'] );
		self::assertSame( 'reversal_missing', $result['error'] );
		self::assertSame( 0, $points->restore_calls );
	}

	public function test_successful_restoration_uses_reversal_cycle_key(): void {
		$points                  = new Restoration_Points_Service();
		$points->earned_points   = 6015;
		$points->redeemed_points = -400;
		$points->reversal        = array( 'id' => 88 );
		$points->restore_result  = Points_Service::RESULT_ADDED;
		$service                 = new Loyalty_Movements_Restoration_Service( $points );

		$result = $service->restore_order( 7, 55, true, 3 );

		self::assertSame( Points_Service::RESULT_ADDED, $result['status'] );
		self::assertSame( 1, $points->restore_calls );
		self::assertSame( 6015, $points->last_earned_points );
		self::assertSame( 400, $points->last_redeemed_points );
		self::assertSame( 88, $points->last_reversal_transaction_id );
		self::assertSame( 3, $points->last_admin_id );
		self::assertSame( 'restored_order:55:cycle:2', $result['idempotency_key'] );
		self::assertSame( 2, $result['cycle'] );
	}

	public function test_existing_restoration_is_duplicate(): void {
		$points                  = new Restoration_Points_Service();
		$points->earned_points   = 6015;
		$points->redeemed_points = -400;
		$points->reversal        = array( 'id' => 88 );
		$points->existing_keys   = array( 'restored_order:55:cycle:2' );
		$service                 = new Loyalty_Movements_Restoration_Service( $points );

		$result = $service->restore_order( 7, 55, true, 3 );

		self::assertSame( Points_Service::RESULT_DUPLICATE, $result['status'] );
		self::assertSame( 0, $points->restore_calls );
	}

	public function test_insufficient_balance_is_reported_without_partial_restore(): void {
		$points                  = new Restoration_Points_Service();
		$points->earned_points   = 6015;
		$points->redeemed_points = -400;
		$points->reversal        = array( 'id' => 88 );
		$points->restore_result  = Points_Service::RESULT_INSUFFICIENT_BALANCE;
		$points->required        = 400;
		$points->available       = 150;
		$service                 = new Loyalty_Movements_Restoration_Service( $points );

		$result = $service->restore_order( 7, 55, true, 3 );

		self::assertSame( Points_Service::RESULT_INSUFFICIENT_BALANCE, $result['status'] );
		self::assertSame( 'insufficient_balance', $result['error'] );
		self::assertSame( 400, $result['required_balance'] );
		self::assertSame( 150, $result['available_balance'] );
		self::assertSame( 1, $points->restore_calls );
	}
}

final class Restoration_Points_Service extends Points_Service {
	public int $earned_points = 0;
	public int $redeemed_points = 0;
	public ?array $reversal = null;
	public array $existing_keys = array();
	public array $existing_types = array();
	public string $restore_result = Points_Service::RESULT_FAILED;
	public int $required = 0;
	public int $available = 0;
	public int $restore_calls = 0;
	public int $last_earned_points = 0;
	public int $last_redeemed_points = 0;
	public int $last_reversal_transaction_id = 0;
	public int $last_admin_id = 0;
	public int $last_cycle = 0;

	public function get_order_earned_points_for_cycle( int $order_id, int $cycle ): int {
		if ( 55 !== $order_id ) {
			throw new \RuntimeException( 'Unexpected order lookup.' );
		}
		unset( $cycle );
		return $this->earned_points;
	}

	public function get_order_redeemed_points_for_cycle( int $order_id, int $cycle ): int {
		if ( 55 !== $order_id ) {
			throw new \RuntimeException( 'Unexpected order lookup.' );
		}
		unset( $cycle );
		return abs( min( 0, $this->redeemed_points ) );
	}

	public function get_latest_order_reversal_transaction( int $order_id, int $cycle = 1 ): ?array {
		if ( 55 !== $order_id ) {
			throw new \RuntimeException( 'Unexpected reversal lookup.' );
		}
		unset( $cycle );

		return $this->reversal;
	}

	public function has_idempotency_key( string $idempotency_key ): bool {
		return in_array( $idempotency_key, $this->existing_keys, true );
	}

	public function has_order_transaction( int $order_id, string $type ): bool {
		return 55 === $order_id && in_array( $type, $this->existing_types, true );
	}

	public function get_balance( int $customer_id ): int {
		if ( 7 !== $customer_id ) {
			throw new \RuntimeException( 'Unexpected balance lookup.' );
		}

		return $this->available;
	}

	public function restore_order_loyalty_movements( int $customer_id, int $order_id, int $earned_points, int $redeemed_points, int $reversal_transaction_id, int $admin_id, int $cycle ): array {
		if ( 7 !== $customer_id || 55 !== $order_id ) {
			throw new \RuntimeException( 'Unexpected restore call.' );
		}

		++$this->restore_calls;
		$this->last_earned_points            = $earned_points;
		$this->last_redeemed_points          = $redeemed_points;
		$this->last_reversal_transaction_id  = $reversal_transaction_id;
		$this->last_admin_id                 = $admin_id;
		$this->last_cycle                    = $cycle;

		return array(
			'status'            => $this->restore_result,
			'error'             => Points_Service::RESULT_INSUFFICIENT_BALANCE === $this->restore_result ? 'insufficient_balance' : '',
			'earned_points'     => $earned_points,
			'redeemed_points'   => $redeemed_points,
			'current_balance'   => $this->available,
			'projected_balance' => $this->available + $earned_points - $redeemed_points,
			'missing_points'    => max( 0, -1 * ( $this->available + $earned_points - $redeemed_points ) ),
			'required_balance'  => $this->required,
			'available_balance' => $this->available,
			'idempotency_key'   => 'restored_order:' . $order_id . ':cycle:' . $cycle,
			'cycle'             => $cycle,
		);
	}
}
