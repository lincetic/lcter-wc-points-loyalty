<?php
/**
 * Initial bonus service unit tests.
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Database;
use LCTER_WCPL\Services\Initial_Bonus_Service;
use LCTER_WCPL\Services\Points_Service;
use PHPUnit\Framework\TestCase;

final class InitialBonusServiceTest extends TestCase {
	public function test_bonus_is_idempotent_and_summary_distinguishes_skipped_customers(): void {
		$points  = new In_Memory_Initial_Bonus_Points_Service();
		$service = new Initial_Bonus_Service( $points, 12500 );

		$first = $service->apply_to_customers( array( 7, 8 ), 99 );
		self::assertSame(
			array( 'processed' => 2, 'bonused' => 2, 'skipped' => 0, 'errors' => 0 ),
			$first
		);

		$second = $service->apply_to_customers( array( 7, 8 ), 99 );
		self::assertSame(
			array( 'processed' => 2, 'bonused' => 0, 'skipped' => 2, 'errors' => 0 ),
			$second
		);

		self::assertSame( 12500, $points->balances[7] );
		self::assertSame( 12500, $points->balances[8] );
		self::assertSame( 'initial_bonus:7', $points->operations[0]['idempotency_key'] );
		self::assertSame( 12500, $points->operations[0]['points'] );
		self::assertSame( Database::TRANSACTION_INITIAL_BONUS, $points->operations[0]['type'] );
	}
}

final class In_Memory_Initial_Bonus_Points_Service extends Points_Service {
	/** @var array<int,int> */
	public array $balances = array();

	/** @var array<int,array<string,mixed>> */
	public array $operations = array();

	/** @var array<string,bool> */
	private array $keys = array();

	public function __construct() {
	}

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
		unset( $order_id, $order_item_id, $source, $description, $metadata, $created_by );

		if ( ! $idempotency_key || isset( $this->keys[ $idempotency_key ] ) ) {
			return $idempotency_key ? self::RESULT_DUPLICATE : self::RESULT_FAILED;
		}

		$this->keys[ $idempotency_key ] = true;
		$this->balances[ $customer_id ] = ( $this->balances[ $customer_id ] ?? 0 ) + $points;
		$this->operations[]              = array(
			'customer_id'     => $customer_id,
			'points'          => $points,
			'type'            => $type,
			'idempotency_key' => $idempotency_key,
		);

		return self::RESULT_ADDED;
	}
}
