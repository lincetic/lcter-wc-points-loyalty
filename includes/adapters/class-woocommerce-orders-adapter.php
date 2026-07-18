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
	const LOYALTY_MOVEMENTS_STATE_META            = '_lcter_wcpl_loyalty_movements_state';
	const LOYALTY_CYCLE_META                      = '_lcter_wcpl_loyalty_cycle';
	const LOYALTY_STATE_APPLIED                   = 'loyalty_movements_applied';
	const LOYALTY_STATE_REVERSED                  = 'loyalty_movements_reversed';
	const LOYALTY_STATE_RESTORED                  = 'loyalty_movements_restored';
	const LOYALTY_STATE_RESTORE_ERROR             = 'loyalty_restore_error';
	const LOYALTY_RESTORE_ERROR_META              = '_lcter_wcpl_loyalty_restore_error';
	const LOYALTY_RESTORE_CURRENT_BALANCE_META    = '_lcter_wcpl_loyalty_restore_current_balance';
	const LOYALTY_RESTORE_PROJECTED_BALANCE_META  = '_lcter_wcpl_loyalty_restore_projected_balance';
	const LOYALTY_RESTORE_MISSING_POINTS_META     = '_lcter_wcpl_loyalty_restore_missing_points';
	const LOYALTY_RESTORE_EARNED_POINTS_META      = '_lcter_wcpl_loyalty_restore_earned_points';
	const LOYALTY_RESTORE_REDEEMED_POINTS_META    = '_lcter_wcpl_loyalty_restore_redeemed_points';
	const LOYALTY_RESTORE_REQUIRED_BALANCE_META   = '_lcter_wcpl_loyalty_restore_required_balance';
	const LOYALTY_RESTORE_AVAILABLE_BALANCE_META  = '_lcter_wcpl_loyalty_restore_available_balance';
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
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'on_order_cancelled' ), 1, 1 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'on_order_refunded' ), 1, 1 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'on_order_failed' ), 1, 1 );
		add_action( 'woocommerce_order_fully_refunded', array( $this, 'on_order_fully_refunded' ), 1, 2 );
	}

	public function on_order_paid( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->is_paid() ) {
			return;
		}

		if ( $this->has_pending_reversed_movements( $order ) || self::LOYALTY_STATE_RESTORED === sanitize_key( (string) $order->get_meta( self::LOYALTY_MOVEMENTS_STATE_META ) ) ) {
			WooCommerce_Checkout_Adapter::sync_order_reward_visual_state( $order );
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

		$cycle   = self::get_order_loyalty_cycle( $order );
		$awarded = $this->points_service->award_points_for_order(
			$customer_id,
			$total_points,
			$order_id,
			sprintf( 'Puntos generados por el pedido #%s.', $order->get_order_number() ),
			array(
				'order_total'    => (float) $order->get_total(),
				'shipping_total' => (float) $order->get_shipping_total(),
				'shipping_tax'   => (float) $order->get_shipping_tax(),
				'loyalty_cycle'  => $cycle,
			),
			$cycle
		);

		if ( $awarded && ! $order->get_meta( self::ORDER_POINTS_AWARDED_META ) ) {
			$order->update_meta_data( self::ORDER_POINTS_AWARDED_META, $total_points );
			$order->update_meta_data( self::LOYALTY_CYCLE_META, $cycle );
			$order->update_meta_data( self::LOYALTY_MOVEMENTS_STATE_META, self::LOYALTY_STATE_APPLIED );
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
		$this->process_order_terminal_status( $order_id, 'order_cancelled', 'cancelled' );
	}

	/**
	 * Reverse earned points when WooCommerce marks a fully refunded order.
	 */
	public function on_order_fully_refunded( int $order_id, int $refund_id ): void {
		$this->process_order_terminal_status( $order_id, 'order_fully_refunded', 'refunded', array( 'refund_id' => $refund_id ) );
	}

	public function on_order_refunded( int $order_id ): void {
		$this->process_order_terminal_status( $order_id, 'order_refunded', 'refunded' );
	}

	public function on_order_failed( int $order_id ): void {
		$this->process_order_terminal_status( $order_id, 'order_failed', 'failed' );
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
		$context['loyalty_cycle'] = self::get_order_loyalty_cycle( $order );

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

		$context['loyalty_cycle'] = self::get_order_loyalty_cycle( $order );
		$result                   = $this->cancellation_service->reverse_order( (int) $order->get_customer_id(), $order_id, $trigger, $context );

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

	private function process_order_terminal_status( int $order_id, string $trigger, string $woocommerce_status, array $context = array() ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		WooCommerce_Checkout_Adapter::sync_order_reward_visual_state( $order, false, $woocommerce_status );

		$context['woocommerce_status'] = $woocommerce_status;
		$context['loyalty_cycle']      = self::get_order_loyalty_cycle( $order );
		$return_result                 = $this->cancellation_service->return_redeemed_order( (int) $order->get_customer_id(), $order_id, $trigger, $context );
		if ( 'returned' === $return_result['status'] ) {
			$order->add_order_note(
				sprintf(
					__( 'Se han devuelto %s puntos canjeados en regalos de este pedido.', LCTER_WCPL_TEXT_DOMAIN ),
					number_format_i18n( (int) $return_result['points'] )
				)
			);
		}

		if ( 'processing_error' === $return_result['status'] ) {
			$order->update_meta_data( '_lcter_wcpl_reward_return_status', 'processing_error' );
			$order->update_meta_data( '_lcter_wcpl_reward_return_error', $return_result['error'] );
			$order->update_meta_data( '_lcter_wcpl_reward_return_error_at', current_time( 'mysql' ) );
			$order->add_order_note( __( 'No se pudieron devolver los puntos canjeados en regalos. Revision operativa necesaria.', LCTER_WCPL_TEXT_DOMAIN ) );
		} elseif ( in_array( $return_result['status'], array( 'returned', 'duplicate' ), true ) ) {
			$order->update_meta_data( '_lcter_wcpl_reward_return_status', 'completed' );
			$order->delete_meta_data( '_lcter_wcpl_reward_return_error' );
			$order->delete_meta_data( '_lcter_wcpl_reward_return_error_at' );
		}

		$earned_result = $this->cancellation_service->reverse_order( (int) $order->get_customer_id(), $order_id, $trigger, $context );
		$this->persist_earned_reversal_result( $order, $trigger, $context, $earned_result );
		if (
			in_array( $return_result['status'], array( 'returned', 'duplicate', 'skipped' ), true ) &&
			in_array( $earned_result['status'], array( 'reversed', 'duplicate', 'skipped' ), true ) &&
			( in_array( $return_result['status'], array( 'returned', 'duplicate' ), true ) || in_array( $earned_result['status'], array( 'reversed', 'duplicate' ), true ) )
		) {
			$order->update_meta_data( self::LOYALTY_MOVEMENTS_STATE_META, self::LOYALTY_STATE_REVERSED );
			$order->delete_meta_data( self::LOYALTY_RESTORE_ERROR_META );
			$order->delete_meta_data( self::LOYALTY_RESTORE_CURRENT_BALANCE_META );
			$order->delete_meta_data( self::LOYALTY_RESTORE_PROJECTED_BALANCE_META );
			$order->delete_meta_data( self::LOYALTY_RESTORE_MISSING_POINTS_META );
			$order->delete_meta_data( self::LOYALTY_RESTORE_EARNED_POINTS_META );
			$order->delete_meta_data( self::LOYALTY_RESTORE_REDEEMED_POINTS_META );
			$order->delete_meta_data( self::LOYALTY_RESTORE_REQUIRED_BALANCE_META );
			$order->delete_meta_data( self::LOYALTY_RESTORE_AVAILABLE_BALANCE_META );
		}
		$order->save();
	}

	private function persist_earned_reversal_result( $order, string $trigger, array $context, array $result ): void {
		if ( 'skipped' === $result['status'] ) {
			$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_STATUS_META, 'skipped' );
			$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_META, $result['error'] );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_AT_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_PENDING_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_TRIGGER_META );
			$order->delete_meta_data( self::ORDER_POINTS_CANCELLATION_CONTEXT_META );
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
			return;
		}

		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_STATUS_META, 'processing_error' );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_META, $result['error'] );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_ERROR_AT_META, current_time( 'mysql' ) );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_PENDING_META, (int) $result['points'] );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_TRIGGER_META, $trigger );
		$order->update_meta_data( self::ORDER_POINTS_CANCELLATION_CONTEXT_META, $context );
		$order->add_order_note(
			__( 'No se pudieron revertir los puntos del pedido. Revision operativa necesaria antes de cerrar la cancelacion o reembolso.', LCTER_WCPL_TEXT_DOMAIN )
		);
	}

	/**
	 * Convert a WooCommerce monetary amount to cents using the documented rate.
	 *
	 * @param mixed $amount Monetary amount.
	 */
	private function amount_to_cents( $amount ): int {
		return (int) round( (float) $amount * self::POINTS_PER_CURRENCY_UNIT );
	}

	public static function order_has_pending_reversed_movements( $order ): bool {
		if ( ! $order || ! is_callable( array( $order, 'get_meta' ) ) ) {
			return false;
		}

		return in_array(
			sanitize_key( (string) $order->get_meta( self::LOYALTY_MOVEMENTS_STATE_META ) ),
			array( self::LOYALTY_STATE_REVERSED, self::LOYALTY_STATE_RESTORE_ERROR ),
			true
		);
	}

	public static function get_order_loyalty_cycle( $order ): int {
		if ( ! $order || ! is_callable( array( $order, 'get_meta' ) ) ) {
			return 1;
		}

		return max( 1, (int) $order->get_meta( self::LOYALTY_CYCLE_META ) );
	}

	private function has_pending_reversed_movements( $order ): bool {
		return self::order_has_pending_reversed_movements( $order );
	}
}
