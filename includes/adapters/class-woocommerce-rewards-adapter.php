<?php
/**
 * WooCommerce reward order-item adapter.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Adapters;

use LCTER_WCPL\Services\Points_Service;
use LCTER_WCPL\Services\Rewards_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerce_Rewards_Adapter {
	/**
	 * Keep the historical constructor signature for external callers.
	 */
	public function __construct(
		?Rewards_Service $rewards_service = null,
		?Points_Service $points_service = null
	) {
		unset( $rewards_service, $points_service );
	}

	/**
	 * Legacy immediate redemption entrypoint.
	 *
	 * @deprecated 0.1.0 Use the paid-order checkout flow instead.
	 */
	public function redeem_reward_for_order( int $customer_id, $order, int $reward_id, int $quantity = 1 ): bool {
		unset( $customer_id, $order, $reward_id, $quantity );

		_deprecated_function(
			__METHOD__,
			'0.1.0',
			'Reward_Redemption_Service mediante los hooks de pago del checkout'
		);

		return false;
	}
}
