<?php
/**
 * Database schema management.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs and upgrades the plugin schema without deleting existing data.
 */
class Database {
	const SCHEMA_VERSION_OPTION = 'lcter_wcpl_schema_version';
	const SCHEMA_VERSION        = '1.6.0';

	const TABLE_SUFFIX_CUSTOMER_POINTS = 'customer_points';
	const TABLE_SUFFIX_TRANSACTIONS    = 'transactions';
	const TABLE_SUFFIX_REWARDS         = 'rewards';
	const TABLE_SUFFIX_ORDER_REWARDS   = 'order_rewards';
	const TABLE_SUFFIX_BONUS_RUNS      = 'initial_bonus_runs';
	const TABLE_SUFFIX_BONUS_TARGETS   = 'initial_bonus_targets';

	const TRANSACTION_EARNED            = 'earned';
	const TRANSACTION_REDEEMED          = 'redeemed';
	const TRANSACTION_INITIAL_BONUS     = 'initial_bonus';
	const TRANSACTION_MANUAL_ADJUSTMENT = 'manual_adjustment';
	const TRANSACTION_REFUND            = 'refund';
	const TRANSACTION_CANCELLED         = 'cancelled';
	const TRANSACTION_FAILED            = 'failed';
	const TRANSACTION_RETURNED_REDEEMED = 'returned_redeemed';
	const TRANSACTION_RESTORED_EARNED   = 'restored_earned';
	const TRANSACTION_RESTORED_REDEEMED = 'restored_redeemed';

	/**
	 * Get a trusted plugin table name.
	 *
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	public static function get_table_name( string $suffix ): string {
		global $wpdb;

		$allowed_suffixes = array(
			self::TABLE_SUFFIX_CUSTOMER_POINTS,
			self::TABLE_SUFFIX_TRANSACTIONS,
			self::TABLE_SUFFIX_REWARDS,
			self::TABLE_SUFFIX_ORDER_REWARDS,
			self::TABLE_SUFFIX_BONUS_RUNS,
			self::TABLE_SUFFIX_BONUS_TARGETS,
		);

		if ( ! in_array( $suffix, $allowed_suffixes, true ) ) {
			throw new \InvalidArgumentException( 'Unknown LCTER WCPL table suffix.' );
		}

		return $wpdb->prefix . LCTER_WCPL_TABLE_PREFIX . $suffix;
	}

	/**
	 * Upgrade the schema when the stored version differs.
	 */
	public static function maybe_upgrade(): void {
		if ( self::SCHEMA_VERSION !== get_option( self::SCHEMA_VERSION_OPTION, '' ) ) {
			self::install_or_upgrade();
		}
	}

	/**
	 * Create or update all tables non-destructively.
	 */
	public static function install_or_upgrade(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$customer_points_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ) . ' (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT(20) UNSIGNED NOT NULL,
			balance INT(11) NOT NULL DEFAULT 0,
			total_earned INT(11) NOT NULL DEFAULT 0,
			total_redeemed INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY customer_id (customer_id),
			KEY updated_at (updated_at)
		) ENGINE=InnoDB ' . $charset_collate . ';';

		$transactions_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_TRANSACTIONS ) . ' (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT(20) UNSIGNED NOT NULL,
			order_id BIGINT(20) UNSIGNED NULL,
			order_item_id BIGINT(20) UNSIGNED NULL,
			type VARCHAR(50) NOT NULL,
			points INT(11) NOT NULL,
			balance_before INT(11) NOT NULL DEFAULT 0,
			balance_after INT(11) NOT NULL DEFAULT 0,
			source VARCHAR(50) NULL,
			description LONGTEXT NULL,
			metadata LONGTEXT NULL,
			created_by BIGINT(20) UNSIGNED NULL,
			idempotency_key VARCHAR(191) NULL,
			initial_bonus_run_id BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY initial_bonus_run_id (initial_bonus_run_id),
			KEY customer_id (customer_id),
			KEY order_id (order_id),
			KEY order_item_id (order_item_id),
			KEY type (type),
			KEY source (source),
			KEY created_at (created_at)
		) ENGINE=InnoDB ' . $charset_collate . ';';

		$rewards_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_REWARDS ) . ' (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			points_cost INT(11) NOT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			visible_in_checkout TINYINT(1) NOT NULL DEFAULT 1,
			exclusive_redemption TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT(11) NOT NULL DEFAULT 0,
			starts_at DATETIME NULL,
			ends_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY product_id (product_id),
			KEY active (active),
			KEY visible_in_checkout (visible_in_checkout),
			KEY exclusive_redemption (exclusive_redemption),
			KEY starts_at (starts_at),
			KEY ends_at (ends_at),
			KEY sort_order (sort_order)
		) ENGINE=InnoDB ' . $charset_collate . ';';

		$order_rewards_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_ORDER_REWARDS ) . ' (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			customer_id BIGINT(20) UNSIGNED NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			order_item_id BIGINT(20) UNSIGNED NULL,
			reward_id BIGINT(20) UNSIGNED NULL,
			quantity INT(11) NOT NULL DEFAULT 1,
			points_cost_each INT(11) NOT NULL,
			points_cost_total INT(11) NOT NULL,
			product_name VARCHAR(255) NULL,
			sku VARCHAR(100) NULL,
			idempotency_key VARCHAR(191) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY order_id (order_id),
			KEY customer_id (customer_id),
			KEY product_id (product_id),
			KEY order_item_id (order_item_id),
			KEY reward_id (reward_id),
			KEY created_at (created_at)
		) ENGINE=InnoDB ' . $charset_collate . ';';

		$bonus_runs_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_BONUS_RUNS ) . ' (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			admin_id BIGINT(20) UNSIGNED NOT NULL,
			cutoff_at DATETIME NOT NULL,
			bonus_points INT(11) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT \'prepared\',
			total_targets INT(11) NOT NULL DEFAULT 0,
			processed_count INT(11) NOT NULL DEFAULT 0,
			bonused_count INT(11) NOT NULL DEFAULT 0,
			skipped_count INT(11) NOT NULL DEFAULT 0,
			failed_count INT(11) NOT NULL DEFAULT 0,
			total_points BIGINT(20) NOT NULL DEFAULT 0,
			lock_token VARCHAR(64) NULL,
			locked_until DATETIME NULL,
			confirmation_hash CHAR(64) NULL,
			last_request_token VARCHAR(64) NULL,
			started_at DATETIME NULL,
			finished_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY locked_until (locked_until),
			KEY confirmation_hash (confirmation_hash),
			KEY created_at (created_at)
		) ENGINE=InnoDB ' . $charset_collate . ';';

		$bonus_targets_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_BONUS_TARGETS ) . ' (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id BIGINT(20) UNSIGNED NOT NULL,
			customer_id BIGINT(20) UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT \'pending\',
			attempts SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			error_message TEXT NULL,
			processed_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY run_customer (run_id, customer_id),
			KEY run_status (run_id, status),
			KEY customer_id (customer_id)
		) ENGINE=InnoDB ' . $charset_collate . ';';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $customer_points_sql );
		dbDelta( $transactions_sql );
		dbDelta( $rewards_sql );
		dbDelta( $order_rewards_sql );
		dbDelta( $bonus_runs_sql );
		dbDelta( $bonus_targets_sql );

		self::migrate_legacy_data_non_destructively();
		if ( self::initial_bonus_schema_is_safe() ) {
			update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false );
		}
	}

	/** Verify the database guarantees required before a mass accounting run. */
	public static function initial_bonus_schema_is_safe(): bool {
		global $wpdb;
		$tables = array(
			self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ),
			self::get_table_name( self::TABLE_SUFFIX_TRANSACTIONS ),
			self::get_table_name( self::TABLE_SUFFIX_BONUS_RUNS ),
			self::get_table_name( self::TABLE_SUFFIX_BONUS_TARGETS ),
		);
		foreach ( $tables as $table ) {
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
			if ( 'InnoDB' !== $engine ) {
				return false; }
		}
		$transaction_table = self::get_table_name( self::TABLE_SUFFIX_TRANSACTIONS );
		$targets_table     = self::get_table_name( self::TABLE_SUFFIX_BONUS_TARGETS );
		$runs_table        = self::get_table_name( self::TABLE_SUFFIX_BONUS_RUNS );
		$unique_key        = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s AND NON_UNIQUE=0', $transaction_table, 'idempotency_key' ) );
		$run_customer      = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s AND NON_UNIQUE=0', $targets_table, 'run_customer' ) );
		$run_status        = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s', $targets_table, 'run_status' ) );
		$attribution       = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s', $transaction_table, 'initial_bonus_run_id' ) );
		$attribution_index = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s', $transaction_table, 'initial_bonus_run_id' ) );
		$run_columns       = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME IN ('confirmation_hash','last_request_token')", $runs_table ) );
		$runs_primary      = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s', $runs_table, 'PRIMARY' ) );
		$runs_status       = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s', $runs_table, 'status' ) );
		$runs_confirmation = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s', $runs_table, 'confirmation_hash' ) );
		$targets_primary   = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s', $targets_table, 'PRIMARY' ) );
		$targets_customer  = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s', $targets_table, 'customer_id' ) );

		return (int) $unique_key > 0
			&& 2 === (int) $run_customer
			&& 2 === (int) $run_status
			&& (int) $attribution > 0
			&& (int) $attribution_index > 0
			&& 2 === (int) $run_columns
			&& 1 === (int) $runs_primary
			&& 1 === (int) $runs_status
			&& 1 === (int) $runs_confirmation
			&& 1 === (int) $targets_primary
			&& 1 === (int) $targets_customer;
	}

	/**
	 * Backfill known legacy data while preserving legacy columns and tables.
	 */
	private static function migrate_legacy_data_non_destructively(): void {
		global $wpdb;

		$customer_table = self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS );

		if ( self::column_exists( $customer_table, 'points' ) && self::column_exists( $customer_table, 'balance' ) ) {
			$wpdb->query( 'UPDATE ' . $customer_table . ' SET balance = points WHERE balance = 0 AND points <> 0' );
		}

		$rewards_table = self::get_table_name( self::TABLE_SUFFIX_REWARDS );
		if ( self::column_exists( $rewards_table, 'visible_in_checkout' ) ) {
			$stored_version = (string) get_option( self::SCHEMA_VERSION_OPTION, '0.0.0' );
			if ( version_compare( $stored_version, '1.3.0', '<' ) ) {
				$wpdb->query( 'UPDATE ' . $rewards_table . ' SET visible_in_checkout = active' );
			}
			$wpdb->query( 'UPDATE ' . $rewards_table . ' SET visible_in_checkout = 0 WHERE active = 0 AND visible_in_checkout = 1' );
		}
	}

	/**
	 * Check whether a column exists in a trusted table.
	 *
	 * @param string $table_name Table name.
	 * @param string $column_name Column name.
	 * @return bool
	 */
	private static function column_exists( string $table_name, string $column_name ): bool {
		global $wpdb;

		$column = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW COLUMNS FROM ' . $table_name . ' LIKE %s',
				$column_name
			)
		);

		return $column === $column_name;
	}
}
