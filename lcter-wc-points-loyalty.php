<?php
/**
 * Plugin Name: LCTER WC Points Loyalty
 * Plugin URI: https://lcter.com/
 * Description: Sistema de puntuación por compra para clientes de WooCommerce. Los productos tienen una puntuación por precio, y los clientes ganan puntos que pueden canjear por regalos.
 * Version: 1.0.0
 * Author: LCTER
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lcter-wc-points-loyalty
 * Domain Path: /languages
 * Requires at least: 6.9
 * Requires PHP: 7.2.24
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package LCTER_WC_Points_Loyalty
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'LCTER_WCPL_VERSION', '1.0.0' );
define( 'LCTER_WCPL_PLUGIN_FILE', __FILE__ );
define( 'LCTER_WCPL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LCTER_WCPL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LCTER_WCPL_INCLUDES_DIR', LCTER_WCPL_PLUGIN_DIR . 'includes/' );

// Include the main loader class.
require_once LCTER_WCPL_INCLUDES_DIR . 'class-loader.php';

// Initialize the plugin on plugins_loaded hook.
add_action( 'plugins_loaded', 'lcter_wcpl_init' );

/**
 * Initialize the LCTER WC Points Loyalty plugin.
 */
function lcter_wcpl_init() {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'lcter_wcpl_woocommerce_missing_notice' );
		return;
	}

	// Start the plugin loader.
	LCTER_WCPL\Loader::instance();
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function lcter_wcpl_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<?php
			esc_html_e(
				'LCTER WC Points Loyalty requiere que WooCommerce esté activado.',
				'lcter-wc-points-loyalty'
			);
			?>
		</p>
	</div>
	<?php
}

// Register activation/deactivation hooks.
register_activation_hook( __FILE__, 'lcter_wcpl_activate' );
register_deactivation_hook( __FILE__, 'lcter_wcpl_deactivate' );

/**
 * Plugin activation hook.
 */
function lcter_wcpl_activate() {
	// Require autoloader before activation.
	require_once LCTER_WCPL_INCLUDES_DIR . 'class-loader.php';

	// Call activation method.
	if ( class_exists( 'LCTER_WCPL\Loader' ) ) {
		LCTER_WCPL\Loader::activate();
	}
}

/**
 * Plugin deactivation hook.
 */
function lcter_wcpl_deactivate() {
	// Require autoloader before deactivation.
	require_once LCTER_WCPL_INCLUDES_DIR . 'class-loader.php';

	// Call deactivation method.
	if ( class_exists( 'LCTER_WCPL\Loader' ) ) {
		LCTER_WCPL\Loader::deactivate();
	}
}
