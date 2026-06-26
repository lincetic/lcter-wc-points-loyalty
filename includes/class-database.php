<?php
/**
 * Database abstraction class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database class.
 */
class Database {
	const TABLE_SUFFIX_CUSTOMER_POINTS = 'customer_points';
	const TABLE_SUFFIX_TRANSACTIONS    = 'transactions';
	const TABLE_SUFFIX_REWARDS         = 'rewards';
	const TABLE_SUFFIX_ORDER_REWARDS   = 'order_rewards';

	const TRANSACTION_EARNED            = 'earned';
	const TRANSACTION_REDEEMED          = 'redeemed';
	const TRANSACTION_INITIAL_BONUS     = 'initial_bonus';
	const TRANSACTION_MANUAL_ADJUSTMENT = 'manual_adjustment';
	const TRANSACTION_REFUND            = 'refund';
	const TRANSACTION_CANCELLED         = 'cancelled';

	/**
	 * Get the plugin table name.
	 *
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	public static function get_table_name( string $suffix ): string {
		global $wpdb;
		return $wpdb->prefix . 'lcter_wcpl_' . $suffix;
	}

	/**
	 * Create or update required tables.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$customer_points_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ) . " (
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
		) " . $charset_collate . ';';

		$transactions_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_TRANSACTIONS ) . " (
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
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY order_id (order_id),
			KEY order_item_id (order_item_id),
			KEY type (type),
			KEY source (source),
			KEY created_at (created_at)
		) " . $charset_collate . ';';

		$rewards_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_REWARDS ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			points_cost INT(11) NOT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT(11) NOT NULL DEFAULT 0,
			starts_at DATETIME NULL,
			ends_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY product_id (product_id),
			KEY active (active),
			KEY starts_at (starts_at),
			KEY ends_at (ends_at),
			KEY sort_order (sort_order)
		) " . $charset_collate . ';';

		$order_rewards_sql = 'CREATE TABLE ' . self::get_table_name( self::TABLE_SUFFIX_ORDER_REWARDS ) . " (
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
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY customer_id (customer_id),
			KEY product_id (product_id),
			KEY order_item_id (order_item_id),
			KEY reward_id (reward_id),
			KEY created_at (created_at)
		) " . $charset_collate . ';';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $customer_points_sql );
		dbDelta( $transactions_sql );
		dbDelta( $rewards_sql );
		dbDelta( $order_rewards_sql );

		self::migrate_legacy_schema();
	}

	/**
	 * Get customer points row count.
	 *
	 * @return int
	 */
	public static function get_total_customers(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ) );
	}

	/**
	 * Get total points issued.
	 *
	 * @return int
	 */
	public static function get_total_points_issued(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COALESCE(SUM(total_earned), 0) FROM ' . self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ) );
	}

	/**
	 * Get total points redeemed.
	 *
	 * @return int
	 */
	public static function get_total_points_redeemed(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COALESCE(SUM(total_redeemed), 0) FROM ' . self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ) );
	}

	/**
	 * Get customer point balance.
	 *
	 * @param int $customer_id Customer ID.
	 * @return int
	 */
	public static function get_customer_points( int $customer_id ): int {
		global $wpdb;

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(balance, 0) FROM ' . self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ) . ' WHERE customer_id = %d',
				$customer_id
			)
		);

		return (int) ( $balance ?? 0 );
	}

	/**
	 * Add earned points to the customer balance and record the transaction.
	 *
	 * @param int         $customer_id Customer ID.
	 * @param int         $points Points to add.
	 * @param string      $type Transaction type.
	 * @param int|null    $order_id Order ID.
	 * @param int|null    $order_item_id Order item ID.
	 * @param string|null $source Source label.
	 * @param string|null $description Optional description.
	 * @param mixed       $metadata Optional JSON-serializable metadata.
	 * @param int|null    $created_by User ID that created the transaction.
	 * @return bool
	 */
	public static function add_customer_points(
		int $customer_id,
		int $points,
		string $type = self::TRANSACTION_EARNED,
		?int $order_id = null,
		?int $order_item_id = null,
		?string $source = null,
		?string $description = null,
		$metadata = null,
		?int $created_by = null
	): bool {
		global $wpdb;

		if ( $customer_id <= 0 || $points <= 0 ) {
			return false;
		}

		$wpdb->query( 'START TRANSACTION' );

		$balance_before = self::get_customer_points( $customer_id );
		$balance_after  = $balance_before + $points;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ) . ' (customer_id, balance, total_earned, updated_at)
				VALUES (%d, %d, %d, NOW())
				ON DUPLICATE KEY UPDATE
				balance = balance + %d,
				total_earned = total_earned + %d,
				updated_at = NOW()',
				$customer_id,
				$points,
				$points,
				$points,
				$points
			)
		);

		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$inserted = self::insert_transaction(
			$customer_id,
			$points,
			$type,
			$order_id,
			$order_item_id,
			$source,
			$description,
			$metadata,
			$created_by,
			$balance_before,
			$balance_after
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Redeem points from the customer balance and record the transaction.
	 *
	 * @param int         $customer_id Customer ID.
	 * @param int         $points Points to redeem.
	 * @param string      $type Transaction type.
	 * @param int|null    $order_id Order ID.
	 * @param int|null    $order_item_id Order item ID.
	 * @param string|null $source Source label.
	 * @param string|null $description Optional description.
	 * @param mixed       $metadata Optional JSON-serializable metadata.
	 * @param int|null    $created_by User ID that created the transaction.
	 * @return bool
	 */
	public static function redeem_customer_points(
		int $customer_id,
		int $points,
		string $type = self::TRANSACTION_REDEEMED,
		?int $order_id = null,
		?int $order_item_id = null,
		?string $source = null,
		?string $description = null,
		$metadata = null,
		?int $created_by = null
	): bool {
		global $wpdb;

		if ( $customer_id <= 0 || $points <= 0 ) {
			return false;
		}

		$wpdb->query( 'START TRANSACTION' );

		$balance_before = self::get_customer_points( $customer_id );
		if ( $balance_before < $points ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$balance_after = $balance_before - $points;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ) . '
				SET balance = balance - %d, total_redeemed = total_redeemed + %d, updated_at = NOW()
				WHERE customer_id = %d AND balance >= %d',
				$points,
				$points,
				$customer_id,
				$points
			)
		);

		if ( false === $updated || $wpdb->rows_affected <= 0 ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$inserted = self::insert_transaction(
			$customer_id,
			-1 * $points,
			$type,
			$order_id,
			$order_item_id,
			$source,
			$description,
			$metadata,
			$created_by,
			$balance_before,
			$balance_after
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Insert a transaction record.
	 *
	 * @param int         $customer_id Customer ID.
	 * @param int         $points Points changed.
	 * @param string      $type Transaction type.
	 * @param int|null    $order_id Order ID.
	 * @param int|null    $order_item_id Order item ID.
	 * @param string|null $source Source label.
	 * @param string|null $description Optional description.
	 * @param mixed       $metadata Optional JSON-serializable metadata.
	 * @param int|null    $created_by User ID that created the transaction.
	 * @param int|null    $balance_before Balance before transaction.
	 * @param int|null    $balance_after Balance after transaction.
	 * @return bool
	 */
	public static function insert_transaction(
		int $customer_id,
		int $points,
		string $type,
		?int $order_id = null,
		?int $order_item_id = null,
		?string $source = null,
		?string $description = null,
		$metadata = null,
		?int $created_by = null,
		?int $balance_before = null,
		?int $balance_after = null
	): bool {
		global $wpdb;

		if ( null === $balance_before ) {
			$balance_before = self::get_customer_points( $customer_id );
		}

		if ( null === $balance_after ) {
			$balance_after = $balance_before + $points;
		}

		return (bool) $wpdb->insert(
			self::get_table_name( self::TABLE_SUFFIX_TRANSACTIONS ),
			array(
				'customer_id'     => $customer_id,
				'order_id'        => $order_id,
				'order_item_id'   => $order_item_id,
				'type'            => $type,
				'points'          => $points,
				'balance_before'  => $balance_before,
				'balance_after'   => $balance_after,
				'source'          => $source,
				'description'     => $description,
				'metadata'        => self::normalize_metadata( $metadata ),
				'created_by'      => $created_by,
			),
			array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Get active rewards for the current date.
	 *
	 * @return array
	 */
	public static function get_active_rewards(): array {
		global $wpdb;

		return $wpdb->get_results(
			'SELECT * FROM ' . self::get_table_name( self::TABLE_SUFFIX_REWARDS ) . "
			WHERE active = 1
			AND (starts_at IS NULL OR starts_at <= NOW())
			AND (ends_at IS NULL OR ends_at >= NOW())
			ORDER BY sort_order ASC, id ASC",
			ARRAY_A
		);
	}

	/**
	 * Get one reward by ID.
	 *
	 * @param int $reward_id Reward ID.
	 * @return array|null
	 */
	public static function get_reward( int $reward_id ): ?array {
		global $wpdb;

		$reward = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::get_table_name( self::TABLE_SUFFIX_REWARDS ) . ' WHERE id = %d',
				$reward_id
			),
			ARRAY_A
		);

		return $reward ?: null;
	}

	/**
	 * Get one reward by product ID.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null
	 */
	public static function get_reward_by_product( int $product_id ): ?array {
		global $wpdb;

		$reward = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::get_table_name( self::TABLE_SUFFIX_REWARDS ) . ' WHERE product_id = %d',
				$product_id
			),
			ARRAY_A
		);

		return $reward ?: null;
	}

	/**
	 * Save a reward.
	 *
	 * @param array $reward Reward data.
	 * @return int Reward ID, or 0 on failure.
	 */
	public static function save_reward( array $reward ): int {
		global $wpdb;

		$reward_id   = isset( $reward['id'] ) ? absint( $reward['id'] ) : 0;
		$product_id  = isset( $reward['product_id'] ) ? absint( $reward['product_id'] ) : 0;
		$points_cost = isset( $reward['points_cost'] ) ? absint( $reward['points_cost'] ) : 0;

		if ( $product_id <= 0 || $points_cost <= 0 ) {
			return 0;
		}

		$data = array(
			'product_id'   => $product_id,
			'points_cost' => $points_cost,
			'active'      => empty( $reward['active'] ) ? 0 : 1,
			'sort_order'  => isset( $reward['sort_order'] ) ? (int) $reward['sort_order'] : 0,
			'starts_at'   => self::normalize_datetime( $reward['starts_at'] ?? null ),
			'ends_at'     => self::normalize_datetime( $reward['ends_at'] ?? null ),
		);

		$formats = array( '%d', '%d', '%d', '%d', '%s', '%s' );

		if ( 0 === $reward_id ) {
			$existing = self::get_reward_by_product( $product_id );
			if ( $existing ) {
				$reward_id = (int) $existing['id'];
			}
		}

		if ( $reward_id > 0 ) {
			$updated = $wpdb->update(
				self::get_table_name( self::TABLE_SUFFIX_REWARDS ),
				$data,
				array( 'id' => $reward_id ),
				$formats,
				array( '%d' )
			);

			return false === $updated ? 0 : $reward_id;
		}

		$saved = $wpdb->insert(
			self::get_table_name( self::TABLE_SUFFIX_REWARDS ),
			$data,
			$formats
		);

		if ( false === $saved ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a reward.
	 *
	 * @param int $reward_id Reward ID.
	 * @return bool
	 */
	public static function delete_reward( int $reward_id ): bool {
		global $wpdb;

		if ( $reward_id <= 0 ) {
			return false;
		}

		return (bool) $wpdb->delete(
			self::get_table_name( self::TABLE_SUFFIX_REWARDS ),
			array( 'id' => $reward_id ),
			array( '%d' )
		);
	}

	/**
	 * Insert a redeemed order reward record.
	 *
	 * @param array $order_reward Order reward data.
	 * @return int Inserted record ID, or 0 on failure.
	 */
	public static function insert_order_reward( array $order_reward ): int {
		global $wpdb;

		$order_id    = isset( $order_reward['order_id'] ) ? absint( $order_reward['order_id'] ) : 0;
		$customer_id = isset( $order_reward['customer_id'] ) ? absint( $order_reward['customer_id'] ) : 0;
		$product_id  = isset( $order_reward['product_id'] ) ? absint( $order_reward['product_id'] ) : 0;

		if ( $order_id <= 0 || $customer_id <= 0 || $product_id <= 0 ) {
			return 0;
		}

		$quantity          = max( 1, isset( $order_reward['quantity'] ) ? absint( $order_reward['quantity'] ) : 1 );
		$points_cost_each  = isset( $order_reward['points_cost_each'] ) ? absint( $order_reward['points_cost_each'] ) : 0;
		$points_cost_total = isset( $order_reward['points_cost_total'] ) ? absint( $order_reward['points_cost_total'] ) : $points_cost_each * $quantity;

		if ( $points_cost_each <= 0 || $points_cost_total <= 0 ) {
			return 0;
		}

		$inserted = $wpdb->insert(
			self::get_table_name( self::TABLE_SUFFIX_ORDER_REWARDS ),
			array(
				'order_id'          => $order_id,
				'customer_id'       => $customer_id,
				'product_id'        => $product_id,
				'order_item_id'     => isset( $order_reward['order_item_id'] ) ? absint( $order_reward['order_item_id'] ) : null,
				'reward_id'         => isset( $order_reward['reward_id'] ) ? absint( $order_reward['reward_id'] ) : null,
				'quantity'          => $quantity,
				'points_cost_each'  => $points_cost_each,
				'points_cost_total' => $points_cost_total,
				'product_name'      => isset( $order_reward['product_name'] ) ? sanitize_text_field( (string) $order_reward['product_name'] ) : null,
				'sku'               => isset( $order_reward['sku'] ) ? sanitize_text_field( (string) $order_reward['sku'] ) : null,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get redeemed rewards for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function get_order_rewards( int $order_id ): array {
		return self::get_rewards_by_order_id( $order_id );
	}

	/**
	 * Get redeemed rewards for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public static function get_customer_rewards( int $customer_id ): array {
		global $wpdb;

		if ( $customer_id <= 0 ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::get_table_name( self::TABLE_SUFFIX_ORDER_REWARDS ) . ' WHERE customer_id = %d ORDER BY created_at DESC, id DESC',
				$customer_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get redeemed rewards by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function get_rewards_by_order_id( int $order_id ): array {
		global $wpdb;

		if ( $order_id <= 0 ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::get_table_name( self::TABLE_SUFFIX_ORDER_REWARDS ) . ' WHERE order_id = %d ORDER BY created_at ASC, id ASC',
				$order_id
			),
			ARRAY_A
		);
	}

	/**
	 * Drop plugin tables.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS ) );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::get_table_name( self::TABLE_SUFFIX_TRANSACTIONS ) );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::get_table_name( self::TABLE_SUFFIX_REWARDS ) );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::get_table_name( self::TABLE_SUFFIX_ORDER_REWARDS ) );
	}

	/**
	 * Migrate away from legacy columns and tables.
	 */
	private static function migrate_legacy_schema(): void {
		global $wpdb;

		$customer_table = self::get_table_name( self::TABLE_SUFFIX_CUSTOMER_POINTS );
		if ( self::column_exists( $customer_table, 'points' ) && self::column_exists( $customer_table, 'balance' ) ) {
			$wpdb->query( 'UPDATE ' . $customer_table . ' SET balance = points WHERE balance = 0 AND points <> 0' );
			$wpdb->query( 'ALTER TABLE ' . $customer_table . ' DROP COLUMN points' );
		}

		$transactions_table = self::get_table_name( self::TABLE_SUFFIX_TRANSACTIONS );
		if ( self::column_exists( $transactions_table, 'reward_id' ) ) {
			$wpdb->query( 'ALTER TABLE ' . $transactions_table . ' DROP COLUMN reward_id' );
		}

		$legacy_product_points_table = $wpdb->prefix . 'lcter_wcpl_product_points';
		if ( self::table_exists( $legacy_product_points_table ) ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . $legacy_product_points_table );
		}
	}

	/**
	 * Check whether a table exists.
	 *
	 * @param string $table_name Table name.
	 * @return bool
	 */
	private static function table_exists( string $table_name ): bool {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $found === $table_name;
	}

	/**
	 * Check whether a column exists.
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

	/**
	 * Normalize metadata for storage.
	 *
	 * @param mixed $metadata Metadata value.
	 * @return string|null
	 */
	private static function normalize_metadata( $metadata ): ?string {
		if ( null === $metadata || '' === $metadata ) {
			return null;
		}

		if ( is_string( $metadata ) ) {
			return $metadata;
		}

		$encoded = wp_json_encode( $metadata );
		return false === $encoded ? null : $encoded;
	}

	/**
	 * Normalize datetime value.
	 *
	 * @param mixed $datetime Datetime value.
	 * @return string|null
	 */
	private static function normalize_datetime( $datetime ): ?string {
		if ( empty( $datetime ) || ! is_string( $datetime ) ) {
			return null;
		}

		return sanitize_text_field( $datetime );
	}
}
