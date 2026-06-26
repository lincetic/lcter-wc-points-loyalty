<?php
/**
 * Customer functionality class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

use function add_action;
use function add_shortcode;
use function esc_html__;
use function esc_html_e;
use function get_current_user_id;
use function ob_get_clean;
use function ob_start;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer class - manages customer point-related features.
 */
class Customer {

	/**
	 * Initialize customer hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'display_points_on_dashboard' ) );
		add_shortcode( 'lcter_wcpl_customer_points', array( __CLASS__, 'points_shortcode' ) );
	}

	/**
	 * Display customer points on account dashboard.
	 */
	public static function display_points_on_dashboard() {
		$customer_id = get_current_user_id();

		if ( ! $customer_id ) {
			return;
		}

		$points = Points::get_customer_points( $customer_id );

		?>
		<div class="lcter-wcpl-dashboard-section">
			<h2><?php esc_html_e( 'Tus Puntos de Lealtad', LCTER_WCPL_TEXT_DOMAIN ); ?></h2>
			<p><?php printf( esc_html__( 'Tienes %d puntos disponibles.', LCTER_WCPL_TEXT_DOMAIN ), intval( $points ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Shortcode to display customer points.
	 *
	 * @return string
	 */
	public static function points_shortcode() {
		$customer_id = get_current_user_id();

		if ( ! $customer_id ) {
			return esc_html__( 'Por favor inicia sesión para ver tus puntos.', 'lcter-wc-points-loyalty' );
		}

		$points = Points::get_customer_points( $customer_id );

		ob_start();
		?>
		<div class="lcter-wcpl-points-display">
			<p class="lcter-wcpl-points-label"><?php esc_html_e( 'Mis Puntos:', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
			<p class="lcter-wcpl-points-value"><?php echo intval( $points ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
