<?php
declare( strict_types=1 );
namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Repositories\Initial_Bonus_Runs_Repository;
use LCTER_WCPL\Services\Initial_Bonus_Batch_Service;
use LCTER_WCPL\Services\Initial_Bonus_Service;
use LCTER_WCPL\Services\Points_Service;
use PHPUnit\Framework\TestCase;

final class InitialBonusBatchServiceTest extends TestCase {
	public function test_multiple_batches_resume_and_large_population(): void {
		$repo    = new Memory_Bonus_Runs( 63 );
		$points  = new Memory_Batch_Points();
		$service = new Initial_Bonus_Batch_Service( $repo, static fn( array $run ) => new Initial_Bonus_Service( $points, (int) $run['bonus_points'] ) );
		$first   = $service->process( 1, 'one' );
		self::assertSame( 25, $first['processed_count'] );
		$second = $service->process( 1, 'two' );
		self::assertSame( 50, $second['processed_count'] );
		$final = $service->process( 1, 'three' );
		self::assertSame( 'completed', $final['status'] );
		self::assertSame( 63, $final['bonused_count'] );
		self::assertSame( 630000, $final['total_points'] );
	}

	public function test_duplicate_ajax_effect_is_idempotent_and_failure_does_not_lose_rest(): void {
		$repo            = new Memory_Bonus_Runs( 3 );
		$points          = new Memory_Batch_Points();
		$points->fail[2] = true;
		$service         = new Initial_Bonus_Batch_Service( $repo, static fn( array $run ) => new Initial_Bonus_Service( $points, (int) $run['bonus_points'] ) );
		$result          = $service->process( 1, 'same' );
		self::assertSame( 2, $result['bonused_count'] );
		self::assertSame( 1, $result['failed_count'] );
		self::assertSame( 20000, $result['total_points'] );
		$points->fail = array();
		$result       = $service->process( 1, 'retry' );
		self::assertSame( 'completed', $result['status'] );
		self::assertSame( 3, count( $points->keys ) );
		self::assertSame( 30000, $result['total_points'] );
	}

	public function test_concurrent_request_with_different_token_is_rejected(): void {
		$this->expectException( \RuntimeException::class );
		$repo         = new Memory_Bonus_Runs( 1 );
		$repo->locked = true;
		( new Initial_Bonus_Batch_Service( $repo, static fn() => new Initial_Bonus_Service( new Memory_Batch_Points(), 10000 ) ) )->process( 1, 'other' );
	}

	public function test_concurrent_request_with_same_token_is_rejected(): void {
		$this->expectException( \RuntimeException::class );
		$repo = new Memory_Bonus_Runs( 1 );
		$repo->locked = true;
		( new Initial_Bonus_Batch_Service( $repo, static fn() => new Initial_Bonus_Service( new Memory_Batch_Points(), 10000 ) ) )->process( 1, 'same' );
	}

	public function test_active_worker_lock_does_not_expire_by_elapsed_time(): void {
		$repo = new Memory_Bonus_Runs( 1 );
		$repo->locked = true;
		self::assertFalse( $repo->acquire_worker_lock( 1, 'later-worker' ) );
	}

	public function test_repeated_completed_request_does_not_advance_another_batch(): void {
		$repo = new Memory_Bonus_Runs( 30 );
		$points = new Memory_Batch_Points();
		$service = new Initial_Bonus_Batch_Service( $repo, static fn( array $run ) => new Initial_Bonus_Service( $points, (int) $run['bonus_points'] ) );
		$first = $service->process( 1, 'same-request' );
		$repeated = $service->process( 1, 'same-request' );
		self::assertSame( 25, $first['processed_count'] );
		self::assertSame( 25, $repeated['processed_count'] );
		self::assertCount( 25, $points->keys );
	}

	public function test_deleted_user_is_traced_without_creating_points(): void {
		$GLOBALS['lcter_wcpl_test_deleted_users'] = array( 2 );
		$repo = new Memory_Bonus_Runs( 3 );
		$points = new Memory_Batch_Points();
		$service = new Initial_Bonus_Batch_Service( $repo, static fn( array $run ) => new Initial_Bonus_Service( $points, (int) $run['bonus_points'] ) );
		$result = $service->process( 1, 'deleted' );
		self::assertSame( 2, $result['bonused_count'] );
		self::assertSame( 1, $result['failed_count'] );
		self::assertArrayNotHasKey( 'initial_bonus:2', $points->keys );
		$GLOBALS['lcter_wcpl_test_deleted_users'] = array();
	}

	public function test_committed_bonus_is_recovered_after_target_mark_failure(): void {
		$repo = new Memory_Bonus_Runs( 1 );
		$repo->fail_next_mark = true;
		$points = new Memory_Batch_Points();
		$service = new Initial_Bonus_Batch_Service( $repo, static fn( array $run ) => new Initial_Bonus_Service( $points, (int) $run['bonus_points'] ) );

		try {
			$service->process( 1, 'interrupted' );
			self::fail( 'The target mark failure should interrupt the batch.' );
		} catch ( \RuntimeException $exception ) {
			self::assertSame( 'No se pudo actualizar el estado persistente del objetivo.', $exception->getMessage() );
		}

		$repo->evidence[1] = true;
		$result = $service->process( 1, 'recovery' );
		self::assertSame( 'completed', $result['status'] );
		self::assertSame( 1, $result['bonused_count'] );
		self::assertSame( 1, count( $points->keys ) );
	}
}

final class Memory_Bonus_Runs extends Initial_Bonus_Runs_Repository {
	public array $run;
	public array $targets = array();
	public bool $locked   = false;
	public string $last_token = '';
	public bool $fail_next_mark = false;
	public array $evidence = array();
	public function __construct( int $count ) {
		$this->run = array(
			'id'              => 1,
			'admin_id'        => 9,
			'cutoff_at'       => '2025-01-01 00:00:00',
			'bonus_points'    => 10000,
			'status'          => 'prepared',
			'total_targets'   => $count,
			'processed_count' => 0,
			'bonused_count'   => 0,
			'skipped_count'   => 0,
			'failed_count'    => 0,
			'total_points'    => 0,
		); for ( $i = 1; $i <= $count; $i++ ) {
			$this->targets[ $i ] = 'pending'; } }
	public function get_run( int $run_id ): ?array {
		return 1 === $run_id ? $this->run : null; }
	public function acquire_worker_lock( int $run_id, string $token ): bool {
		unset( $run_id, $token );
		if ( $this->locked ) {
			return false;
		} $this->locked      = true;
		$this->run['status'] = 'running';
		return true; }
	public function release_worker_lock( int $run_id, string $token ): void { unset( $run_id, $token ); $this->locked = false; }
	public function request_was_completed( int $run_id, string $token ): bool { unset( $run_id ); return $this->last_token === $token; }
	public function pending_targets( int $run_id, int $limit ): array {
		unset( $run_id );
		return array_slice( array_keys( array_filter( $this->targets, static fn( $s ) => in_array( $s, array( 'pending', 'failed' ), true ) ) ), 0, $limit ); }
	public function mark_target( int $run_id, int $customer_id, string $status, ?string $error = null ): bool {
		unset( $run_id, $error );
		if ( $this->fail_next_mark ) { $this->fail_next_mark = false; return false; }
		$this->targets[ $customer_id ] = $status; return true; }
	public function target_has_current_run_transaction( int $run_id, int $customer_id ): bool { unset( $run_id ); return (bool) ( $this->evidence[ $customer_id ] ?? false ); }
	public function reconcile( int $run_id, string $token ): array {
		unset( $run_id );
		$this->last_token             = $token;
		$counts                       = array_count_values( $this->targets );
		$this->run['bonused_count']   = $counts['bonused'] ?? 0;
		$this->run['skipped_count']   = $counts['skipped'] ?? 0;
		$this->run['failed_count']    = $counts['failed'] ?? 0;
		$this->run['processed_count'] = $this->run['bonused_count'] + $this->run['skipped_count'] + $this->run['failed_count'];
		$this->run['total_points']    = $this->run['bonused_count'] * 10000;
		$this->run['status']          = ( $counts['pending'] ?? 0 ) || ( $counts['failed'] ?? 0 ) ? ( ( $counts['failed'] ?? 0 ) ? 'errors' : 'running' ) : 'completed';
		return $this->run; }
}

final class Memory_Batch_Points extends Points_Service {
	public array $keys = array();
	public array $fail = array(); public function __construct() {}
	public function add_points_with_status( int $customer_id, int $points, string $type = 'earned', ?int $order_id = null, ?int $order_item_id = null, ?string $source = null, ?string $description = null, $metadata = null, ?int $created_by = null, ?string $idempotency_key = null ): string {
		unset( $points, $type, $order_id, $order_item_id, $source, $description, $metadata, $created_by );
		if ( isset( $this->fail[ $customer_id ] ) ) {
			return self::RESULT_FAILED;
		} if ( isset( $this->keys[ $idempotency_key ] ) ) {
			return self::RESULT_DUPLICATE;
		} $this->keys[ $idempotency_key ] = true;
		return self::RESULT_ADDED; }
}
