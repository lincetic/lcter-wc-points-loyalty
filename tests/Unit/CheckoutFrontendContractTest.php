<?php
/**
 * Classic checkout frontend contract tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CheckoutFrontendContractTest extends TestCase {
	public function test_catalog_defaults_to_hidden_and_has_local_feedback(): void {
		$php = file_get_contents( dirname( __DIR__, 2 ) . '/includes/adapters/class-woocommerce-checkout-adapter.php' );

		self::assertIsString( $php );
		self::assertStringContainsString( "'redeem' === \$choice ? '' : 'hidden'", $php );
		self::assertStringContainsString( 'class="lcter-wcpl-reward-feedback', $php );
		self::assertStringContainsString( 'aria-live="assertive"', $php );
	}

	public function test_checkout_script_clears_hidden_selection_and_rebinds_after_ajax(): void {
		$javascript = file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/checkout.js' );

		self::assertIsString( $javascript );
		self::assertStringContainsString( ".val( 0 )", $javascript );
		self::assertStringContainsString( 'update_checkout.lcterWcplCheckout', $javascript );
		self::assertStringContainsString( 'updated_checkout.lcterWcplCheckout', $javascript );
		self::assertStringContainsString( ".off( '.lcterWcplCheckout' )", $javascript );
	}
}
