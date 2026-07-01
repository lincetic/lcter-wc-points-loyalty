<?php
/**
 * WooCommerce order adapter.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Adapters;

use LCTER_WCPL\Services\Order_Cancellation_Service;
use LCTER_WCPL\Services\Points_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerce_Orders_Adapter {
	const ORDER_POINTS_AWARDED_META               = '_lcter_wcpl_points_awarded';
	const ORDER_POINTS_CANCELLATION_STATUS_META   = '_lcter_wcpl_points_cancellation_status';
	const ORDER_POINTS_CANCELLATION_ERROR_META    = '_lcter_wcpl_points_cancellation_error';
	const ORDER_POINTS_CANCELLATION_ERROR_AT_META = '_lcter_wcpl_points_cancellation_error_at';
	const ORDER_POINTS_CANCELLATION_PENDING_META  = '_lcter_wcpl_points_cancellation_pending';
	const ORDER_POINTS_CANCELLATION_TRIGGER_META  = '_lcter_wcpl_points_cancellation_trigger';
	const ORDER_POINTS_CANCELLATION_CONTEXT_META  = '_lcter_wcpl_points_cancellation_context';
	const POINTS_PER_CURRENCY_UNIT                = 100;

	private Points_Service $points_service;
	private Order_Cancellation_Service $cancellation_service;

	public function __construct( ?Points_Service $points_service = null, ?Order_Cancellation_Service $cancellation_service = null ) {
		$this->points_service       = $points_service ?? new Points_Service();
		$this->cancellation_service = $cancellation_service ?? new Order_Cancellation_Service( $this->points_service );
	}

	public function register_hooks(): void {
		add_action( 'woocommerce_payment_complete', array( $this, 'on_order_paid' ), 10, 1 );
		add_action( 'woocommerce_order_payment_status_changed', array( $this, 'on_order_paid' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_paid' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_paid' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'on_order_cancelled' ), 10, 1 );
		add_action( 'woocommerce_order_fully_refunded', array( $this, 'on_order_fully_refunded' ), 10, 2 );
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
	 * Reverse earned points when an order is cancelled.
	 */
	public function on_order_cancelled( int $order_id ): void {
		$this->process_order_reversal( $order_id, 'order_cancelled' );
	}

	/**
	 * Reverse earned points when WooCommerce marks a fully refunded order.
	 */
	public function on_order_fully_refunded( int $order_id, int $refund_id ): void {
		$this->process_order_reversal( $order_id, 'order_fully_refunded', array( 'refund_id' => $refund_id ) );
	}

	/**
	 * Retry a previously failed full points reversal.
	 */
	public function retry_order_reversal( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'processing_error' !== $order->get_meta( self::ORDER_POINTS_CANCELLATION_STATUS_META ) ) {
			return;
		}

		$trigger                 = sanitize_key( (string) $order->get_meta( self::ORDER_POINTS_CANCELLATION_TRIGGER_META ) );
		$context                 = $order->get_meta( self::ORDER_POINTS_CANCELLATION_CONTEXT_META );
		$context                 = is_array( $context ) ? $context : array();
		$context['manual_retry'] = true;

		$this->process_order_reversal( $order_id, $trigger ?: 'manual_retry', $context );
	}

	/**
	 * Process one full order reversal and persist operational state on the order.
	 */
	private function process_order_reversal( int $order_id, string $trigger, array $context = array() ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$result = $this->cancellation_service->reverse_order( (int) $order->get_customer_id(), $order_id, $trigger, $context );

		if ( 'skipped' === $result['status'] ) {
			$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_STATUS_META, 'skipped' );
			$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_META, $result['error'] );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_AT_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_PENDING_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_TRIGGER_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_CONTEXT_META );
			$order->save();
			return;
		}

		if ( in_array( $result['status'], array( 'reversed', 'duplicate' ), true ) ) {
			$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_STATUS_META, 'completed' );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_AT_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_PENDING_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_TRIGGER_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_CONTEXT_META );
			$order->update_meta_data( '_lcter_wcpl_points_cancelled', (int) $result['points'] );
			if ( 'reversed' === $result['status'] ) {
				$order->add_order_note(
					sprintf(
						__( 'Se han revertido %s puntos generados por este pedido.', LCTER_WCPL_TEXT_DOMAIN ),
						number_format_i18n( (int) $result['points'] )
					)
				);
			}
			$order->save();
			return;
		}

		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_STATUS_META, 'processing_error' );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_META, $result['error'] );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_AT_META, current_time( 'mysql' ) );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_PENDING_META, (int) $result['points'] );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_TRIGGER_META, $trigger );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_CONTEXT_META, $context );
		$order->add_order_note(
			__( 'No se pudieron revertir los puntos del pedido. Revisión operativa necesaria antes de cerrar la cancelación o reembolso.', LCTER_WCPL_TEXT_DOMAIN )
		);
		$order->save();
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
