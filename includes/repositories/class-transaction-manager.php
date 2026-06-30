<?php
/**
 * Database transaction boundary.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Transaction_Manager {
	public function begin(): bool {
		global $wpdb;

		return false !== $wpdb->query( 'START TRANSACTION' );
	}

	public function commit(): bool {
		global $wpdb;

		return false !== $wpdb->query( 'COMMIT' );
	}

	public function rollback(): void {
		global $wpdb;

		$wpdb->query( 'ROLLBACK' );
	}
}
