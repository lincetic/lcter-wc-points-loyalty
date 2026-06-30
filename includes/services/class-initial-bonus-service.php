<?php
/**
 * Application service for the controlled initial customer bonus.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Services;

use LCTER_WCPL\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies the one-time initial bonus to explicit customer IDs.
 */
class Initial_Bonus_Service {
	const BONUS_POINTS = 10000;

	private Points_Service $points;

	public function __construct( ?Points_Service $points = null ) {
		$this->points = $points ?? new Points_Service();
	}

	/**
	 * Apply the bonus and return an execution summary.
	 *
	 * @param array $customer_ids Explicit WordPress customer IDs.
	 * @param int   $created_by Administrator user ID.
	 * @return array{processed:int,bonused:int,skipped:int,errors:int}
	 */
	public function apply_to_customers( array $customer_ids, int $created_by ): array {
		$summary = array(
			'processed' => 0,
			'bonused'   => 0,
			'skipped'   => 0,
			'errors'    => 0,
		);

		$customer_ids = array_values( array_unique( array_map( 'intval', $customer_ids ) ) );

		foreach ( $customer_ids as $customer_id ) {
			++$summary['processed'];

			if ( $customer_id <= 0 ) {
				++$summary['errors'];
				continue;
			}

			$result = $this->points->add_points_with_status(
				$customer_id,
				self::BONUS_POINTS,
				Database::TRANSACTION_INITIAL_BONUS,
				null,
				null,
				'admin_initial_bonus',
				'Bonus inicial de 10.000 puntos.',
				array( 'customer_criterion' => 'wordpress_role_customer' ),
				$created_by,
				'initial_bonus:' . $customer_id . ':' . self::BONUS_POINTS
			);

			if ( Points_Service::RESULT_ADDED === $result ) {
				++$summary['bonused'];
			} elseif ( Points_Service::RESULT_DUPLICATE === $result ) {
				++$summary['skipped'];
			} else {
				++$summary['errors'];
			}
		}

		return $summary;
	}
}
