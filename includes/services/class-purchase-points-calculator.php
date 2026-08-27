<?php
/**
 * Shared purchase-points calculation.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts eligible WooCommerce purchase totals into loyalty points.
 */
class Purchase_Points_Calculator {
	/**
	 * Calculate eligible cents from totals including tax and discounts.
	 *
	 * @param mixed $total Total including tax and discounts.
	 * @param mixed $shipping_total Shipping total excluding tax.
	 * @param mixed $shipping_tax Shipping tax.
	 */
	public function calculate( $total, $shipping_total, $shipping_tax ): int {
		return max(
			0,
			$this->amount_to_cents( $total ) -
			$this->amount_to_cents( $shipping_total ) -
			$this->amount_to_cents( $shipping_tax )
		);
	}

	/**
	 * Convert a WooCommerce decimal amount to integer cents.
	 *
	 * @param mixed $amount Monetary amount.
	 */
	private function amount_to_cents( $amount ): int {
		return (int) round( (float) $amount * 100 );
	}
}
