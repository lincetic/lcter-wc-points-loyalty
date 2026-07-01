<?php
/**
 * Plugin activation class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL;

use function add_option;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator class.
 */
class Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate(): void {

		Database::install_or_upgrade();
		self::set_default_options();
	}
	/**
	 * Set default plugin options if they do not exist.
	 */
	private static function set_default_options(): void {
		$defaults = array(
			'lcter_wcpl_points_expiry_days'       => 0,
			'lcter_wcpl_enable_notifications'     => '1',
			'lcter_wcpl_remove_data_on_uninstall' => '0',
		);

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				add_option( $key, $value );
			}
		}
	}
}
