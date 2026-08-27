<?php
/**
 * Shared purchase points calculator tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Services\Purchase_Points_Calculator;
use PHPUnit\Framework\TestCase;

final class PurchasePointsCalculatorTest extends TestCase {
	public function test_sixty_euros_and_fifteen_cents_generate_six_thousand_and_fifteen_points(): void {
		self::assertSame( 6015, ( new Purchase_Points_Calculator() )->calculate( '60.15', '0', '0' ) );
	}

	public function test_shipping_and_shipping_tax_do_not_generate_points(): void {
		self::assertSame( 6015, ( new Purchase_Points_Calculator() )->calculate( '72.25', '10.00', '2.10' ) );
	}

	public function test_negative_eligible_total_is_clamped_to_zero(): void {
		self::assertSame( 0, ( new Purchase_Points_Calculator() )->calculate( '5.00', '6.00', '1.26' ) );
	}
}
