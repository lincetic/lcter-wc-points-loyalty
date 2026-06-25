<?php
/**
 * Main plugin loader class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

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
		$this->register_hooks();
	}

	/**
	 * Load plugin files.
	 */
	private function load_includes() {
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-points.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-admin.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-customer.php';
		require_once LCTER_WCPL_INCLUDES_DIR . 'class-rewards.php';
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin initialization.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Frontend hooks.
		add_action( 'woocommerce_order_status_completed', array( 'LCTER_WCPL\Points', 'award_points_for_order' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Admin initialization.
	 */
	public function admin_init() {
		Admin::instance();
	}

	/**
	 * Admin menu.
	 */
	public function admin_menu() {
		add_menu_page(
			__( 'LCTER Points Loyalty', 'lcter-wc-points-loyalty' ),
			__( 'Points Loyalty', 'lcter-wc-points-loyalty' ),
			'manage_woocommerce',
			'lcter-wcpl-dashboard',
			array( 'LCTER_WCPL\Admin', 'render_dashboard' ),
			'dashicons-star',
			56
		);

		add_submenu_page(
			'lcter-wcpl-dashboard',
			__( 'Configuración', 'lcter-wc-points-loyalty' ),
			__( 'Configuración', 'lcter-wc-points-loyalty' ),
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
		// Create database tables if needed.
		Points::create_tables();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
