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
	 * The checkout requests the generated thumbnail and allows WooCommerce's placeholder.
	 */
	public function test_reward_thumbnail_uses_woocommerce_size_accessible_alt_and_placeholder(): void {
		$product = new class() {
			/** @var array<int,mixed> */
			public array $image_arguments = array();

			public function get_name(): string {
				return 'Taza & regalo';
			}

			public function get_image( $size, $attributes, $placeholder ): string {
				$this->image_arguments = array( $size, $attributes, $placeholder );

				return '<img src="thumbnail.jpg" alt="Taza &amp; regalo" class="lcter-wcpl-reward-image" />';
			}
		};

		$html = $this->invoke_thumbnail_renderer( $product );

		self::assertSame( 'woocommerce_thumbnail', $product->image_arguments[0] );
		self::assertSame( 'Taza & regalo', $product->image_arguments[1]['alt'] );
		self::assertSame( 'lazy', $product->image_arguments[1]['loading'] );
		self::assertTrue( $product->image_arguments[2] );
		self::assertStringContainsString( 'thumbnail.jpg', $html );
	}

	/**
	 * A product without an image returns the standard placeholder rather than broken markup.
	 */
	public function test_reward_without_image_keeps_woocommerce_placeholder(): void {
		$product = new class() {
			public function get_name(): string {
				return 'Regalo sin imagen';
			}

			public function get_image( $size, $attributes, $placeholder ): string {
				unset( $size, $attributes );

				return $placeholder ? '<img src="woocommerce-placeholder.png" alt="Regalo sin imagen" />' : '';
			}
		};

		self::assertStringContainsString( 'woocommerce-placeholder.png', $this->invoke_thumbnail_renderer( $product ) );
	}

	/**
	 * Reward images remain compact on mobile and visible in disabled cards.
	 */
	public function test_reward_thumbnail_styles_cover_mobile_and_disabled_states(): void {
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/frontend.css' );

		self::assertIsString( $css );
		self::assertStringContainsString( 'object-fit: cover', $css );
		self::assertStringContainsString( '@media (max-width: 480px)', $css );
		self::assertStringContainsString( '.lcter-wcpl-reward-option.is-disabled .lcter-wcpl-reward-thumbnail', $css );
		self::assertStringContainsString( 'visibility: visible', $css );
	}

	/**
	 * Invoke the private renderer without widening the adapter's public API.
	 *
	 * @param object $product Product test double.
	 */
	private function invoke_thumbnail_renderer( object $product ): string {
		$method = new \ReflectionMethod( WooCommerce_Checkout_Adapter::class, 'get_reward_thumbnail_html' );

		return $method->invoke( new WooCommerce_Checkout_Adapter(), $product );
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
