<?php
/**
 * PHPUnit bootstrap for service-level tests that do not require WordPress.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$root = dirname( __DIR__ );

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Capture action registrations during isolated unit tests.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Hook priority.
	 * @param int    $accepted_args Accepted args.
	 */
	function add_action( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['lcter_wcpl_test_actions'][] = array(
			'hook_name'     => $hook_name,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Capture filter registrations during isolated unit tests.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Hook priority.
	 * @param int    $accepted_args Accepted args.
	 */
	function add_filter( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['lcter_wcpl_test_filters'][] = array(
			'hook_name'     => $hook_name,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}

require_once $root . '/includes/class-database.php';
require_once $root . '/includes/repositories/class-customer-points-repository.php';
require_once $root . '/includes/repositories/class-transactions-repository.php';
require_once $root . '/includes/repositories/class-transaction-manager.php';
require_once $root . '/includes/repositories/class-rewards-repository.php';
require_once $root . '/includes/repositories/class-order-rewards-repository.php';
require_once $root . '/includes/services/class-points-service.php';
require_once $root . '/includes/services/class-rewards-service.php';
require_once $root . '/includes/services/class-reward-redemption-service.php';
require_once $root . '/includes/services/class-initial-bonus-service.php';
require_once $root . '/includes/services/class-order-cancellation-service.php';
require_once $root . '/includes/services/class-loyalty-movements-restoration-service.php';
require_once $root . '/includes/adapters/class-woocommerce-checkout-adapter.php';
