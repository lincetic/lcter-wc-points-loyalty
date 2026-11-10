<?php
/**
 * Persistent initial-bonus runs and frozen target populations.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Repositories;

use LCTER_WCPL\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Initial_Bonus_Runs_Repository {
	public const ACTIVE_STATUSES = array( 'prepared', 'running', 'paused', 'errors' );

	public function create_run( int $admin_id, string $cutoff_at, int $bonus_points ): int {
		global $wpdb;

		$lock_name = $this->preparation_lock_name();
		if ( ! $this->get_named_lock( $lock_name ) ) {
			return 0;
		}

		$started = false !== $wpdb->query( 'START TRANSACTION' );
		if ( ! $started ) {
			$this->release_named_lock( $lock_name );
			return 0;
		}

		try {
			$placeholders = implode( ',', array_fill( 0, count( self::ACTIVE_STATUSES ), '%s' ) );
			$active       = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $this->runs_table() . " WHERE status IN ({$placeholders}) ORDER BY id DESC LIMIT 1 FOR UPDATE", self::ACTIVE_STATUSES ) );
			if ( $active ) {
				throw new \RuntimeException( 'An active initial bonus run already exists.' );
			}

			$inserted = $wpdb->insert(
				$this->runs_table(),
				array(
					'admin_id'     => $admin_id,
					'cutoff_at'    => $cutoff_at,
					'bonus_points' => $bonus_points,
					'status'       => 'prepared',
				),
				array( '%d', '%s', '%d', '%s' )
			);
			if ( false === $inserted ) {
				throw new \RuntimeException( 'Could not create the initial bonus run.' );
			}

			$run_id = (int) $wpdb->insert_id;
			$users  = $wpdb->users;
			$meta   = $wpdb->usermeta;
			$sql    = 'INSERT INTO ' . $this->targets_table() . " (run_id,customer_id)
				SELECT DISTINCT %d,u.ID FROM {$users} u INNER JOIN {$meta} m ON m.user_id=u.ID AND m.meta_key=%s AND m.meta_value LIKE %s
				WHERE u.user_registered < %s";
			if ( false === $wpdb->query( $wpdb->prepare( $sql, $run_id, $wpdb->prefix . 'capabilities', '%"customer"%', $cutoff_at ) ) ) {
				throw new \RuntimeException( 'Could not freeze the initial bonus population.' );
			}

			$target_ids = array_map(
				'intval',
				$wpdb->get_col( $wpdb->prepare( 'SELECT customer_id FROM ' . $this->targets_table() . ' WHERE run_id=%d ORDER BY customer_id', $run_id ) )
			);
			$fingerprint = hash( 'sha256', (string) wp_json_encode( array( $cutoff_at, $bonus_points, $target_ids ) ) );
			$updated     = $wpdb->update(
				$this->runs_table(),
				array(
					'total_targets'     => count( $target_ids ),
					'confirmation_hash' => $fingerprint,
				),
				array( 'id' => $run_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			if ( false === $updated || false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'Could not finalize the frozen initial bonus population.' );
			}

			return $run_id;
		} catch ( \Throwable $exception ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		} finally {
			$this->release_named_lock( $lock_name );
		}
	}

	public function confirm_run( int $run_id, int $admin_id, string $confirmation_hash ): bool {
		global $wpdb;

		return 1 === $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->runs_table()} SET status='running',started_at=UTC_TIMESTAMP() WHERE id=%d AND admin_id=%d AND status='prepared' AND confirmation_hash=%s",
				$run_id,
				$admin_id,
				$confirmation_hash
			)
		);
	}

	public function get_run( int $run_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->runs_table() . ' WHERE id=%d', $run_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public function latest_run(): ?array {
		global $wpdb;
		$row = $wpdb->get_row( 'SELECT * FROM ' . $this->runs_table() . ' ORDER BY id DESC LIMIT 1', ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public function recent_runs( int $limit = 20 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $this->runs_table() . ' ORDER BY id DESC LIMIT %d', max( 1, min( 100, $limit ) ) ), ARRAY_A );
	}

	public function run_preview( int $run_id ): array {
		global $wpdb;
		$tx  = Database::get_table_name( Database::TABLE_SUFFIX_TRANSACTIONS );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT bt.customer_id) eligible, COUNT(DISTINCT CASE WHEN tr.id IS NOT NULL THEN bt.customer_id END) already_bonused
				FROM {$this->targets_table()} bt LEFT JOIN {$tx} tr ON tr.customer_id=bt.customer_id AND tr.type=%s
				WHERE bt.run_id=%d",
				Database::TRANSACTION_INITIAL_BONUS,
				$run_id
			),
			ARRAY_A
		);
		$eligible = (int) ( $row['eligible'] ?? 0 );
		$already  = (int) ( $row['already_bonused'] ?? 0 );
		return array( 'eligible' => $eligible, 'already_bonused' => $already, 'pending' => max( 0, $eligible - $already ) );
	}

	public function acquire_worker_lock( int $run_id, string $token ): bool {
		global $wpdb;
		$lock_name = $this->worker_lock_name( $run_id );
		if ( ! $this->get_named_lock( $lock_name ) ) {
			return false;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->runs_table()} SET lock_token=%s,locked_until=NULL WHERE id=%d AND status IN ('running','errors')",
				$token,
				$run_id
			)
		);
		if ( 1 !== $updated ) {
			$this->release_named_lock( $lock_name );
			return false;
		}

		return true;
	}

	public function release_worker_lock( int $run_id, string $token ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $this->runs_table() . ' SET lock_token=NULL WHERE id=%d AND lock_token=%s', $run_id, $token ) );
		$this->release_named_lock( $this->worker_lock_name( $run_id ) );
	}

	public function request_was_completed( int $run_id, string $token ): bool {
		$run = $this->get_run( $run_id );
		return is_array( $run ) && hash_equals( (string) ( $run['last_request_token'] ?? '' ), $token );
	}

	public function pending_targets( int $run_id, int $limit ): array {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT customer_id FROM ' . $this->targets_table() . " WHERE run_id=%d AND status IN ('pending','failed') ORDER BY id ASC LIMIT %d", $run_id, $limit ) ) );
	}

	public function mark_target( int $run_id, int $customer_id, string $status, ?string $error = null ): bool {
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare( 'UPDATE ' . $this->targets_table() . ' SET status=%s,attempts=attempts+1,error_message=%s,processed_at=UTC_TIMESTAMP() WHERE run_id=%d AND customer_id=%d', $status, $error, $run_id, $customer_id ) );
		return 1 === $result;
	}

	public function target_has_current_run_transaction( int $run_id, int $customer_id ): bool {
		global $wpdb;
		$tx = Database::get_table_name( Database::TABLE_SUFFIX_TRANSACTIONS );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$tx} WHERE customer_id=%d AND initial_bonus_run_id=%d AND type=%s LIMIT 1", $customer_id, $run_id, Database::TRANSACTION_INITIAL_BONUS ) );
	}

	public function reconcile( int $run_id, string $token ): array {
		global $wpdb;
		$tx = Database::get_table_name( Database::TABLE_SUFFIX_TRANSACTIONS );

		$recovered = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->targets_table()} bt INNER JOIN {$tx} tr ON tr.customer_id=bt.customer_id AND tr.initial_bonus_run_id=bt.run_id AND tr.type=%s
				SET bt.status='bonused',bt.error_message=NULL,bt.processed_at=COALESCE(bt.processed_at,UTC_TIMESTAMP()) WHERE bt.run_id=%d AND bt.status<>'bonused'",
				Database::TRANSACTION_INITIAL_BONUS,
				$run_id
			)
		);
		if ( false === $recovered ) {
			throw new \RuntimeException( 'Could not recover target attribution from accounting evidence.' );
		}

		$counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(CASE WHEN status<>'pending' THEN 1 ELSE 0 END) processed_count,
				SUM(status='bonused') bonused_count,SUM(status='skipped') skipped_count,SUM(status='failed') failed_count,
				SUM(status IN ('pending','failed')) remaining FROM {$this->targets_table()} WHERE run_id=%d",
				$run_id
			),
			ARRAY_A
		) ?: array();
		$total_points = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points),0) FROM {$tx} WHERE initial_bonus_run_id=%d AND type=%s", $run_id, Database::TRANSACTION_INITIAL_BONUS ) );
		$remaining    = (int) ( $counts['remaining'] ?? 0 );
		$status       = $remaining ? ( (int) ( $counts['failed_count'] ?? 0 ) ? 'errors' : 'running' ) : 'completed';

		$updated = $wpdb->update(
			$this->runs_table(),
			array(
				'processed_count'   => (int) ( $counts['processed_count'] ?? 0 ),
				'bonused_count'     => (int) ( $counts['bonused_count'] ?? 0 ),
				'skipped_count'     => (int) ( $counts['skipped_count'] ?? 0 ),
				'failed_count'      => (int) ( $counts['failed_count'] ?? 0 ),
				'total_points'      => $total_points,
				'status'            => $status,
				'last_request_token'=> $token,
				'finished_at'       => 'completed' === $status ? gmdate( 'Y-m-d H:i:s' ) : null,
			),
			array( 'id' => $run_id, 'lock_token' => $token )
		);
		if ( 1 !== $updated ) {
			throw new \RuntimeException( 'A stale worker could not persist reconciliation.' );
		}

		return $this->get_run( $run_id ) ?? array();
	}

	public function set_status( int $run_id, string $status ): bool {
		global $wpdb;
		if ( ! in_array( $status, array( 'paused', 'running' ), true ) ) {
			return false;
		}
		$lock_name = $this->worker_lock_name( $run_id );
		if ( ! $this->get_named_lock( $lock_name ) ) {
			return false;
		}
		try {
			return false !== $wpdb->update( $this->runs_table(), array( 'status' => $status ), array( 'id' => $run_id ) );
		} finally {
			$this->release_named_lock( $lock_name );
		}
	}

	public function failed_items( int $run_id, int $limit = 100 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT customer_id,attempts,error_message FROM ' . $this->targets_table() . " WHERE run_id=%d AND status='failed' ORDER BY id LIMIT %d", $run_id, $limit ), ARRAY_A );
	}

	private function get_named_lock( string $name ): bool {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $name ) );
	}

	private function release_named_lock( string $name ): void {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
	}

	private function preparation_lock_name(): string {
		return 'lcter_wcpl_bonus_prepare_' . get_current_blog_id();
	}

	private function worker_lock_name( int $run_id ): string {
		return 'lcter_wcpl_bonus_run_' . get_current_blog_id() . '_' . $run_id;
	}

	private function runs_table(): string {
		return Database::get_table_name( Database::TABLE_SUFFIX_BONUS_RUNS );
	}

	private function targets_table(): string {
		return Database::get_table_name( Database::TABLE_SUFFIX_BONUS_TARGETS );
	}
}
