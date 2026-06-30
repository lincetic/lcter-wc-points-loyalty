<?php
/**
 * WooCommerce order adapter.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Adapters;

use LCTER_WCPL\Services\Points_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerce_Orders_Adapter {
	const ORDER_POINTS_AWARDED_META = '_lcter_wcpl_points_awarded';
	const POINTS_PER_CURRENCY_UNIT  = 100;

	private Points_Service $points_service;

	public function __construct( ?Points_Service $points_service = null ) {
		$this->points_service = $points_service ?? new Points_Service();
	}

	public function register_hooks(): void {
		add_action( 'woocommerce_payment_complete', array( $this, 'on_order_paid' ), 10, 1 );
		add_action( 'woocommerce_order_payment_status_changed', array( $this, 'on_order_paid' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_paid' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_paid' ), 10, 1 );
	}

	public function on_order_paid( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->is_paid() ) {
			return;
		}

		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id <= 0 ) {
			return;
		}

		$total_points = $this->calculate_points_for_order( $order );
		if ( $total_points <= 0 ) {
			return;
		}

		$awarded = $this->points_service->award_points_for_order(
			$customer_id,
			$total_points,
			$order_id,
			sprintf( 'Puntos generados por el pedido #%s.', $order->get_order_number() ),
			array(
				'order_total'    => (float) $order->get_total(),
				'shipping_total' => (float) $order->get_shipping_total(),
				'shipping_tax'   => (float) $order->get_shipping_tax(),
			)
		);

		if ( $awarded && ! $order->get_meta( self::ORDER_POINTS_AWARDED_META ) ) {
			$order->update_meta_data( self::ORDER_POINTS_AWARDED_META, $total_points );
			$order->save();
		}
	}

	public function calculate_points_for_order( $order ): int {
		$order_total_cents = $this->amount_to_cents( $order->get_total() );
		$shipping_cents    = $this->amount_to_cents( $order->get_shipping_total() );
		$shipping_tax      = $this->amount_to_cents( $order->get_shipping_tax() );

		return max( 0, $order_total_cents - $shipping_cents - $shipping_tax );
	}

	/**
	 * Convert a WooCommerce monetary amount to cents using the documented rate.
	 *
	 * @param mixed $amount Monetary amount.
	 */
	private function amount_to_cents( $amount ): int {
		return (int) round( (float) $amount * self::POINTS_PER_CURRENCY_UNIT );
	}
}
