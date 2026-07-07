<?php
/**
 * WooCommerce checkout adapter hook registration tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Adapters\WooCommerce_Checkout_Adapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests WooCommerce checkout hook registration.
 */
final class WooCommerceCheckoutAdapterHookTest extends TestCase {
	/**
	 * Reset captured hooks.
	 */
	protected function setUp(): void {
		$GLOBALS['lcter_wcpl_test_actions'] = array();
		$GLOBALS['lcter_wcpl_test_filters'] = array();
	}

	/**
	 * Paid-order redemption must be registered before WooCommerce emails run.
	 */
	public function test_paid_order_redemption_runs_before_transactional_emails(): void {
		$adapter = new WooCommerce_Checkout_Adapter();

		$adapter->register_hooks();

		$expected_hooks = array(
			'woocommerce_payment_complete',
			'woocommerce_order_payment_status_changed',
			'woocommerce_order_status_pending_to_processing',
			'woocommerce_order_status_on-hold_to_processing',
			'woocommerce_order_status_failed_to_processing',
			'woocommerce_order_status_pending_to_completed',
			'woocommerce_order_status_on-hold_to_completed',
			'woocommerce_order_status_failed_to_completed',
			'woocommerce_order_status_processing',
			'woocommerce_order_status_completed',
		);

		foreach ( $expected_hooks as $hook_name ) {
			$registration = $this->find_action( $hook_name, 'process_paid_order' );

			self::assertNotNull( $registration, $hook_name );
			self::assertSame( 1, $registration['priority'], $hook_name );
			self::assertSame( 1, $registration['accepted_args'], $hook_name );
		}
	}

	/**
	 * Find a captured action registration.
	 *
	 * @param string $hook_name Hook name.
	 * @param string $method Method name.
	 * @return array<string,mixed>|null
	 */
	private function find_action( string $hook_name, string $method ): ?array {
		foreach ( $GLOBALS['lcter_wcpl_test_actions'] as $action ) {
			if (
				$hook_name === $action['hook_name'] &&
				is_array( $action['callback'] ) &&
				$method === $action['callback'][1]
			) {
				return $action;
			}
		}

		return null;
	}
}
