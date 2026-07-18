<?php
/**
 * Main plugin loader class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

use function add_action;
use function add_menu_page;
use function add_submenu_page;
use function class_exists;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function __;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loader class - bootstraps all plugin functionality.
 */
class Loader {

	/**
	 * Instance of the loader.
	 *
	 * @var Loader
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Loader
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_includes();
		Database::maybe_upgrade();
		$this->register_hooks();
	}

	/**
	 * Load plugin files.
	 */
	private function load_includes(): void {
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-settings.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-database.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-activator.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-deactivator.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'repositories/class-transaction-manager.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'repositories/class-customer-points-repository.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'repositories/class-transactions-repository.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'repositories/class-rewards-repository.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'repositories/class-order-rewards-repository.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'services/class-points-service.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'services/class-initial-bonus-service.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'services/class-order-cancellation-service.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'services/class-loyalty-movements-restoration-service.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'services/class-rewards-service.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'services/class-reward-redemption-service.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'services/class-reward-traceability-service.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'adapters/class-woocommerce-orders-adapter.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'adapters/class-woocommerce-rewards-adapter.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'adapters/class-woocommerce-checkout-adapter.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-customer.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-woocommerce.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-points-service.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-points.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-rewards.php';
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'init', array( $this, 'init_components' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Admin initialization.
	 */
	public function admin_init(): void {
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-admin.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'admin/class-reward-product-admin.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'admin/class-order-traceability-admin.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'admin/class-initial-bonus-admin.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'admin/class-order-processing-error-admin.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'admin/class-order-loyalty-restoration-admin.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'admin/class-customer-points-admin.php';
		Admin::instance();
		Admin_UI\Reward_Product_Admin::instance();
		Admin_UI\Order_Traceability_Admin::instance();
		Admin_UI\Initial_Bonus_Admin::instance();
		Admin_UI\Order_Processing_Error_Admin::instance();
		Admin_UI\Order_Loyalty_Restoration_Admin::instance();
		Admin_UI\Customer_Points_Admin::instance();
	}

	/**
	 * Initialize components.
	 */
	public function init_components(): void {
		Customer::init();

		if ( class_exists( 'WooCommerce' ) ) {
			WooCommerce::init();
		}
	}

	/**
	 * Admin menu.
	 */
	public function admin_menu(): void {
		add_menu_page(
			__( 'LCTER Points Loyalty', LCTER_WCPL_TEXT_DOMAIN ),
			__( 'Points Loyalty', LCTER_WCPL_TEXT_DOMAIN ),
			'manage_woocommerce',
			'lcter-wcpl-dashboard',
			array( 'LCTER_WCPL\Admin', 'render_dashboard' ),
			'dashicons-awards',
			56
		);

		add_submenu_page(
			'lcter-wcpl-dashboard',
			__( 'Configuración', LCTER_WCPL_TEXT_DOMAIN ),
			__( 'Configuración', LCTER_WCPL_TEXT_DOMAIN ),
			'manage_woocommerce',
			'lcter-wcpl-settings',
			array( 'LCTER_WCPL\Admin', 'render_settings' )
		);
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'lcter-wcpl-frontend',
			LCTER_WCPL_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			LCTER_WCPL_VERSION
		);
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		Database::install_or_upgrade();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		// No persistent task is registered during deactivation.
	}
}
