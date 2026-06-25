<?php
/**
 * Rewards management class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewards class - manages rewards catalog and redemptions.
 */
class Rewards {

	/**
	 * Create a new reward.
	 *
	 * @param array $args Reward arguments.
	 * @return int Reward ID or 0 on failure.
	 */
	public static function create_reward( $args ) {
		$defaults = array(
			'title'       => '',
			'description' => '',
			'points_cost' => 0,
			'product_id'  => null,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate input.
		if ( empty( $args['title'] ) || $args['points_cost'] <= 0 ) {
			return 0;
		}

		// Save as custom post type (if we decide to use CPT later).
		return 0; // Placeholder for future implementation.
	}

	/**
	 * Get available rewards.
	 *
	 * @return array
	 */
	public static function get_available_rewards() {
		// Placeholder for retrieving available rewards.
		return array();
	}

	/**
	 * Get customer available rewards.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public static function get_customer_available_rewards( $customer_id ) {
		$customer_points = Points::get_customer_points( $customer_id );
		$available_rewards = self::get_available_rewards();

		return array_filter(
			$available_rewards,
			function( $reward ) use ( $customer_points ) {
				return $reward['points_cost'] <= $customer_points;
			}
		);
	}
}
