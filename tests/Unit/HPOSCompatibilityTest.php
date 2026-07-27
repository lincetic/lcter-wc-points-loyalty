<?php
/**
 * HPOS compatibility declaration tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\HPOS_Compatibility;
use PHPUnit\Framework\TestCase;

/**
 * Tests the declaration with and without WooCommerce's utility class.
 */
final class HPOSCompatibilityTest extends TestCase {
	/**
	 * Missing WooCommerce must not cause an error or a false declaration.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_declaration_is_safe_without_woocommerce(): void {
		self::assertFalse( HPOS_Compatibility::declare( '/plugins/lcter/lcter.php' ) );
	}

	/**
	 * Available FeaturesUtil receives the real plugin file and true compatibility.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_declaration_calls_features_util_with_expected_arguments(): void {
		require_once dirname( __DIR__ ) . '/fixtures/class-features-util.php';

		self::assertTrue( HPOS_Compatibility::declare( '/plugins/lcter/lcter.php' ) );
		self::assertSame(
			array( array( 'custom_order_tables', '/plugins/lcter/lcter.php', true ) ),
			\Automattic\WooCommerce\Utilities\FeaturesUtil::$calls
		);
	}
}
