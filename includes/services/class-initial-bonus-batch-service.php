<?php
/**
 * Resumable orchestration around the existing atomic bonus operation.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Services;

use LCTER_WCPL\Repositories\Initial_Bonus_Runs_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Initial_Bonus_Batch_Service {
	public const BATCH_SIZE = 25;
	private Initial_Bonus_Runs_Repository $runs;
	/** @var callable|null */
	private $bonus_factory;

	public function __construct( ?Initial_Bonus_Runs_Repository $runs = null, ?callable $bonus_factory = null ) {
		$this->runs          = $runs ?? new Initial_Bonus_Runs_Repository();
		$this->bonus_factory = $bonus_factory;
	}

	public function process( int $run_id, string $request_token ): array {
		$run = $this->runs->get_run( $run_id );
		if ( ! $run ) {
			throw new \RuntimeException( 'Ejecución inexistente.' );
		}
		if ( 'completed' === $run['status'] || 'paused' === $run['status'] ) {
			return $run;
		}
		if ( ! $this->runs->acquire_worker_lock( $run_id, $request_token ) ) {
			throw new \RuntimeException( 'Otro lote está procesando esta ejecución.' );
		}

		try {
			if ( $this->runs->request_was_completed( $run_id, $request_token ) ) {
				return $this->runs->get_run( $run_id ) ?? array();
			}

			$bonus = $this->bonus_factory ? ( $this->bonus_factory )( $run ) : new Initial_Bonus_Service( null, (int) $run['bonus_points'] );
			foreach ( $this->runs->pending_targets( $run_id, self::BATCH_SIZE ) as $customer_id ) {
				if ( ! get_userdata( $customer_id ) ) {
					if ( ! $this->runs->mark_target( $run_id, $customer_id, 'failed', 'La cuenta fue eliminada después de congelar la población.' ) ) {
						throw new \RuntimeException( 'No se pudo registrar la cuenta eliminada.' );
					}
					continue;
				}

				$result = $bonus->apply_one(
					$customer_id,
					(int) $run['admin_id'],
					array(
						'initial_bonus_run_id' => $run_id,
						'cutoff_at'            => $run['cutoff_at'],
					)
				);

				if ( Points_Service::RESULT_ADDED === $result || ( Points_Service::RESULT_DUPLICATE === $result && $this->runs->target_has_current_run_transaction( $run_id, $customer_id ) ) ) {
					$marked = $this->runs->mark_target( $run_id, $customer_id, 'bonused' );
				} elseif ( Points_Service::RESULT_DUPLICATE === $result ) {
					$marked = $this->runs->mark_target( $run_id, $customer_id, 'skipped' );
				} else {
					$marked = $this->runs->mark_target( $run_id, $customer_id, 'failed', 'La operación contable devolvió un error.' );
				}

				if ( ! $marked ) {
					throw new \RuntimeException( 'No se pudo actualizar el estado persistente del objetivo.' );
				}
			}

			return $this->runs->reconcile( $run_id, $request_token );
		} finally {
			$this->runs->release_worker_lock( $run_id, $request_token );
		}
	}
}
