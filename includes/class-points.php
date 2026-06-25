<?php
/**
 * Points management class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Points class - manages point calculations and storage.
 */
class Points {

	/**
	 * Create database tables on plugin activation.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Customer points table.
		$customer_points_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lcter_wcpl_customer_points (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT(20) UNSIGNED NOT NULL,
			points INT(11) NOT NULL DEFAULT 0,
			total_earned INT(11) NOT NULL DEFAULT 0,
			total_redeemed INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY customer_id (customer_id),
			KEY updated_at (updated_at)
		) $charset_collate;";

		// Point transactions table (audit trail).
		$transactions_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lcter_wcpl_transactions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT(20) UNSIGNED NOT NULL,
			order_id BIGINT(20) UNSIGNED,
			reward_id BIGINT(20) UNSIGNED,
			points INT(11) NOT NULL,
			type VARCHAR(50) NOT NULL,
			description LONGTEXT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY customer_id (customer_id),
			KEY order_id (order_id),
			KEY type (type),
			KEY created_at (created_at)
		) $charset_collate;";

		// Product points configuration table.
		$product_points_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lcter_wcpl_product_points (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			points_per_currency DECIMAL(10, 4) NOT NULL DEFAULT 1.0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY product_id (product_id),
			KEY points_per_currency (points_per_currency)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $customer_points_sql );
		dbDelta( $transactions_sql );
		dbDelta( $product_points_sql );
	}

	/**
	 * Award points for a completed order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function award_points_for_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$customer_id = $order->get_customer_id();

		if ( ! $customer_id ) {
			return;
		}

		$total_points = 0;

		// Calculate points from order items.
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$quantity = $item->get_quantity();
			$item_total = $item->get_total();

			$points_per_currency = self::get_product_points_rate( $product_id );
			$item_points = (int) ( $item_total * $points_per_currency );

			$total_points += $item_points;
		}

		// Award points to customer.
		if ( $total_points > 0 ) {
			self::add_customer_points( $customer_id, $total_points, 'order_purchase', $order_id );
		}
	}

	/**
	 * Get points rate for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return float Points per currency unit.
	 */
	public static function get_product_points_rate( $product_id ) {
		global $wpdb;

		$rate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT points_per_currency FROM {$wpdb->prefix}lcter_wcpl_product_points WHERE product_id = %d",
				$product_id
			)
		);

		if ( $rate === null ) {
			// Use default rate from settings.
			$rate = (float) get_option( 'lcter_wcpl_default_points_rate', 1 );
		}

		return (float) $rate;
	}

	/**
	 * Add points to a customer account.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param int    $points Points to add.
	 * @param string $type Transaction type.
	 * @param int    $order_id Order ID (optional).
	 * @param int    $reward_id Reward ID (optional).
	 * @return bool
	 */
	public static function add_customer_points( $customer_id, $points, $type = 'manual', $order_id = null, $reward_id = null ) {
		global $wpdb;

		// Validate input.
		if ( $points <= 0 || ! is_numeric( $points ) ) {
			return false;
		}

		// Update or insert customer points.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}lcter_wcpl_customer_points (customer_id, points, total_earned, updated_at)
				VALUES (%d, %d, %d, NOW())
				ON DUPLICATE KEY UPDATE
				points = points + %d,
				total_earned = total_earned + %d,
				updated_at = NOW()",
				$customer_id,
				$points,
				$points,
				$points,
				$points
			)
		);

		// Record transaction.
		$wpdb->insert(
			"{$wpdb->prefix}lcter_wcpl_transactions",
			array(
				'customer_id' => $customer_id,
				'order_id'    => $order_id,
				'reward_id'   => $reward_id,
				'points'      => $points,
				'type'        => $type,
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);

		return true;
	}

	/**
	 * Redeem points from a customer account.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $points Points to redeem.
	 * @param int $reward_id Reward ID.
	 * @return bool
	 */
	public static function redeem_customer_points( $customer_id, $points, $reward_id ) {
		global $wpdb;

		// Validate input.
		if ( $points <= 0 || ! is_numeric( $points ) ) {
			return false;
		}

		// Check if customer has enough points.
		$current_points = self::get_customer_points( $customer_id );
		if ( $current_points < $points ) {
			return false;
		}

		// Deduct points.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}lcter_wcpl_customer_points
				SET points = points - %d, total_redeemed = total_redeemed + %d, updated_at = NOW()
				WHERE customer_id = %d",
				$points,
				$points,
				$customer_id
			)
		);

		// Record transaction.
		$wpdb->insert(
			"{$wpdb->prefix}lcter_wcpl_transactions",
			array(
				'customer_id' => $customer_id,
				'reward_id'   => $reward_id,
				'points'      => -$points,
				'type'        => 'reward_redemption',
			),
			array( '%d', '%d', '%d', '%s' )
		);

		return true;
	}

	/**
	 * Get customer current points.
	 *
	 * @param int $customer_id Customer ID.
	 * @return int
	 */
	public static function get_customer_points( $customer_id ) {
		global $wpdb;

		$points = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(points, 0) FROM {$wpdb->prefix}lcter_wcpl_customer_points WHERE customer_id = %d",
				$customer_id
			)
		);

		return (int) ( $points ?? 0 );
	}
}
