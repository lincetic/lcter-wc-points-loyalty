<?php
/**
 * Plugin Name: LCTER WC Points Loyalty
 * Plugin URI: https://lincetic.es/
 * Description: Professional WooCommerce loyalty points system with configurable reward catalog.
 * Version: 0.1.0
 * Author: Eddie Rapallo
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lcter-wc-points-loyalty
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 10.0
 *
 * @package LCTER_WC_Points_Loyalty
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Define plugin constants.
define( 'LCTER_WCPL_VERSION', '0.1.0' );
define( 'LCTER_WCPL_PLUGIN_FILE', __FILE__ );
define( 'LCTER_WCPL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LCTER_WCPL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LCTER_WCPL_INCLUDES_DIR', LCTER_WCPL_PLUGIN_DIR . 'includes/' );
define( 'LCTER_WCPL_TEXT_DOMAIN', 'lcter-wc-points-loyalty' );
define( 'LCTER_WCPL_TABLE_PREFIX', 'lcter_wcpl_' );

// Include the main loader class.
require_once LCTER_WCPL_INCLUDES_DIR . 'class-loader.php';

// Initialize the plugin on plugins_loaded hook.
add_action( 'plugins_loaded', 'lcter_wcpl_init' );

/**
 * Initialize the LCTER WC Points Loyalty plugin.
 */
function lcter_wcpl_init() {
	load_plugin_textdomain(
		LCTER_WCPL_TEXT_DOMAIN,
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/'
	);

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'lcter_wcpl_woocommerce_missing_notice' );
		return;
	}

	LCTER_WCPL\Loader::instance();
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function lcter_wcpl_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( 'LCTER WC Points Loyalty requiere que WooCommerce esté activado.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
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
	if ( ! class_exists( 'LCTER_WCPL\Database' ) ) {
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-database.php';
	}

	if ( ! class_exists( 'LCTER_WCPL\Activator' ) ) {
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-activator.php';
	}

	LCTER_WCPL\Activator::activate();
}

/**
 * Plugin deactivation hook.
 */
function lcter_wcpl_deactivate() {
	if ( ! class_exists( 'LCTER_WCPL\Deactivator' ) ) {
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-deactivator.php';
	}

	LCTER_WCPL\Deactivator::deactivate();
}
