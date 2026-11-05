<?php
/**
 * WooCommerce checkout adapter hook registration tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Adapters\WooCommerce_Checkout_Adapter;
use LCTER_WCPL\Repositories\Rewards_Repository;
use LCTER_WCPL\Services\Rewards_Service;
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
		$GLOBALS['lcter_wcpl_test_woocommerce'] = (object) array( 'cart' => null, 'session' => null );
		$GLOBALS['lcter_wcpl_test_notices'] = array();
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
	 * The same estimate renderer is attached immediately before both order totals.
	 */
	public function test_earning_estimate_is_registered_for_cart_and_checkout(): void {
		$adapter = new WooCommerce_Checkout_Adapter();
		$adapter->register_hooks();

		self::assertNotNull( $this->find_action( 'woocommerce_cart_totals_before_order_total', 'render_earning_estimate' ) );
		self::assertNotNull( $this->find_action( 'woocommerce_review_order_before_order_total', 'render_earning_estimate' ) );
	}

	public function test_exclusive_product_protection_is_registered_for_all_cart_routes(): void {
		$adapter = new WooCommerce_Checkout_Adapter();
		$adapter->register_hooks();

		self::assertNotNull( $this->find_filter( 'woocommerce_add_to_cart_validation', 'validate_exclusive_product_addition' ) );
		self::assertNotNull( $this->find_action( 'woocommerce_check_cart_items', 'validate_exclusive_reward_cart_items' ) );
	}

	public function test_normal_addition_of_exclusive_product_is_blocked_without_affecting_other_products(): void {
		$repository = new class() extends Rewards_Repository {
			public function find_by_product( int $product_id ): ?array {
				return 10 === $product_id
					? array( 'id' => 7, 'product_id' => 10, 'points_cost' => 500, 'exclusive_redemption' => 1 )
					: null;
			}
		};
		$adapter = new WooCommerce_Checkout_Adapter( null, new Rewards_Service( $repository ) );

		self::assertFalse( $adapter->validate_exclusive_product_addition( true, 10 ) );
		self::assertTrue( $adapter->validate_exclusive_product_addition( true, 11 ) );
		self::assertCount( 1, $GLOBALS['lcter_wcpl_test_notices'] );
		self::assertSame( 'error', $GLOBALS['lcter_wcpl_test_notices'][0]['type'] );
	}

	public function test_exclusive_product_already_in_cart_requires_server_signed_reward_line(): void {
		$repository = new class() extends Rewards_Repository {
			public function find_by_product( int $product_id ): ?array {
				return 10 === $product_id
					? array( 'id' => 7, 'product_id' => 10, 'points_cost' => 500, 'exclusive_redemption' => 1 )
					: null;
			}
			public function find( int $reward_id ): ?array {
				return 7 === $reward_id ? $this->find_by_product( 10 ) : null;
			}
		};
		$GLOBALS['lcter_wcpl_test_woocommerce']->cart = new class() {
			public function get_cart(): array {
				return array(
					array(
						'product_id'                    => 10,
						'quantity'                      => 1,
						'_lcter_wcpl_is_reward'         => '1',
						'_lcter_wcpl_reward_id'         => 7,
						'_lcter_wcpl_points_cost_each'  => 500,
						'_lcter_wcpl_points_cost_total' => 500,
						'_lcter_wcpl_reward_signature'  => 'forged-client-value',
					),
				);
			}
		};

		$adapter = new WooCommerce_Checkout_Adapter( null, new Rewards_Service( $repository ) );
		$adapter->validate_exclusive_reward_cart_items();

		self::assertCount( 1, $GLOBALS['lcter_wcpl_test_notices'] );
		self::assertStringContainsString( 'canje válido', $GLOBALS['lcter_wcpl_test_notices'][0]['message'] );
	}

	/**
	 * The totals row uses the shared rule and excludes shipping and its tax.
	 */
	public function test_earning_estimate_outputs_shared_calculation(): void {
		$GLOBALS['lcter_wcpl_test_woocommerce']->cart = new class() {
			public function get_total( string $context ): string {
				unset( $context );
				return '72.25';
			}
			public function get_shipping_total(): string { return '10.00'; }
			public function get_shipping_tax(): string { return '2.10'; }
		};

		ob_start();
		( new WooCommerce_Checkout_Adapter() )->render_earning_estimate();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'Puntos que conseguirás', $html );
		self::assertStringContainsString( '6.015', $html );
		self::assertStringContainsString( 'cuando el pedido haya sido pagado', $html );
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

	private function find_filter( string $hook_name, string $method ): ?array {
		foreach ( $GLOBALS['lcter_wcpl_test_filters'] as $filter ) {
			if ( $hook_name === $filter['hook_name'] && is_array( $filter['callback'] ) && $method === $filter['callback'][1] ) {
				return $filter;
			}
		}

		return null;
	}
}
