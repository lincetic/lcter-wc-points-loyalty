<?php
/**
 * Operational order error administration and retry actions.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL\Admin_UI;

use LCTER_WCPL\Adapters\WooCommerce_Checkout_Adapter;
use LCTER_WCPL\Adapters\WooCommerce_Orders_Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows plugin processing errors on orders and retries recoverable operations.
 */
class Order_Processing_Error_Admin {
	const ACTION       = 'lcter_wcpl_retry_order_operation';
	const NONCE_ACTION = 'lcter_wcpl_retry_order_operation';
	const NONCE_NAME   = '_lcter_wcpl_retry_nonce';

	/** @var self|null */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register admin-only hooks.
	 */
	private function __construct() {
		add_action( 'woocommerce_admin_order_data_after_payment_info', array( $this, 'render_section' ), 30, 1 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_retry' ) );
	}

	/**
	 * Render current plugin operational errors.
	 *
	 * @param mixed $order WooCommerce order.
	 */
	public function render_section( $order ): void {
		if ( ! $order || ! is_callable( array( $order, 'get_id' ) ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$order_id = (int) $order->get_id();
		$this->render_retry_notice( $order_id );

		$errors = $this->get_order_errors( $order );
		if ( empty( $errors ) ) {
			return;
		}
		?>
		<div class="lcter-wcpl-processing-errors">
			<h3><?php esc_html_e( 'Incidencias operativas de puntos y regalos', LCTER_WCPL_TEXT_DOMAIN ); ?></h3>
			<?php foreach ( $errors as $error ) : ?>
				<div class="notice notice-<?php echo esc_attr( $error['recoverable'] ? 'warning' : 'error' ); ?> inline">
					<p><strong><?php echo esc_html( $error['type'] ); ?></strong></p>
					<table class="widefat striped">
						<tbody>
							<tr><th><?php esc_html_e( 'Mensaje', LCTER_WCPL_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( $error['message'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Puntos afectados', LCTER_WCPL_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( number_format_i18n( $error['points'] ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Fecha', LCTER_WCPL_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( $this->format_date( $error['date'] ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Acción recomendada', LCTER_WCPL_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( $error['recommendation'] ); ?></td></tr>
						</tbody>
					</table>

					<?php if ( $error['recoverable'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
							<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>" />
							<input type="hidden" name="operation" value="<?php echo esc_attr( $error['operation'] ); ?>" />
							<?php wp_nonce_field( $this->nonce_action( $order_id, $error['operation'] ), self::NONCE_NAME ); ?>
							<?php submit_button( __( 'Reintentar operación', LCTER_WCPL_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Handle a protected manual retry.
	 */
	public function handle_retry(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para reintentar esta operación.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$order_value = isset( $_POST['order_id'] ) ? wp_unslash( $_POST['order_id'] ) : null;
		$operation_value = isset( $_POST['operation'] ) ? wp_unslash( $_POST['operation'] ) : null;
		$nonce_value = isset( $_POST[ self::NONCE_NAME ] ) ? wp_unslash( $_POST[ self::NONCE_NAME ] ) : null;
		$order_id   = is_scalar( $order_value ) ? absint( $order_value ) : 0;
		$operation  = is_scalar( $operation_value ) ? sanitize_key( (string) $operation_value ) : '';
		$nonce      = is_scalar( $nonce_value ) ? sanitize_text_field( (string) $nonce_value ) : '';

		if ( $order_id <= 0 || ! in_array( $operation, array( 'reward_redemption', 'points_cancellation' ), true ) ) {
			wp_die( esc_html__( 'La operación de reintento no es válida.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $order_id, $operation ) ) ) {
			wp_die( esc_html__( 'No se pudo verificar la solicitud de reintento.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'No se encontró el pedido.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		if ( 'reward_redemption' === $operation ) {
			if ( 'processing_error' !== $order->get_meta( WooCommerce_Checkout_Adapter::REDEMPTION_STATUS_META ) ) {
				wp_die( esc_html__( 'El canje ya no está en estado recuperable.', LCTER_WCPL_TEXT_DOMAIN ) );
			}
			( new WooCommerce_Checkout_Adapter() )->process_paid_order( $order_id );
		} else {
			if ( 'processing_error' !== $order->get_meta( WooCommerce_Orders_Adapter::ORDER_POINTS_CANCELLATION_STATUS_META ) ) {
				wp_die( esc_html__( 'La reversión ya no está en estado recuperable.', LCTER_WCPL_TEXT_DOMAIN ) );
			}
			( new WooCommerce_Orders_Adapter() )->retry_order_reversal( $order_id );
		}

		set_transient( $this->retry_notice_key( $order_id ), $operation, MINUTE_IN_SECONDS );
		$redirect_url = (string) $order->get_edit_order_url();
		wp_safe_redirect( $redirect_url ?: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id ) );
		exit;
	}

	/**
	 * Collect normalized error rows from order metadata.
	 */
	private function get_order_errors( $order ): array {
		$errors            = array();
		$redemption_status = sanitize_key( (string) $order->get_meta( WooCommerce_Checkout_Adapter::REDEMPTION_STATUS_META ) );

		if ( in_array( $redemption_status, array( 'processing_error', 'rejected' ), true ) ) {
			$error_value = (string) $order->get_meta( WooCommerce_Checkout_Adapter::REDEMPTION_ERROR_META );
			$recoverable = 'processing_error' === $redemption_status;
			$error_code  = $recoverable ? sanitize_key( $error_value ) : '';
			$errors[]    = array(
				'type'           => $recoverable
					? sprintf(
						/* translators: %s: internal error code. */
						__( 'Error recuperable de canje (%s)', LCTER_WCPL_TEXT_DOMAIN ),
						$error_code ?: 'unknown'
					)
					: __( 'Canje rechazado', LCTER_WCPL_TEXT_DOMAIN ),
				'message'        => $this->redemption_error_message( $error_value, $redemption_status ),
				'points'         => max( 0, (int) $order->get_meta( '_lcter_wcpl_reward_points_total' ) ),
				'date'           => (string) $order->get_meta( WooCommerce_Checkout_Adapter::REDEMPTION_ERROR_AT_META ),
				'recommendation' => $recoverable
					? __( 'Corregir la causa indicada y reintentar. La idempotencia completará únicamente los movimientos o filas pendientes.', LCTER_WCPL_TEXT_DOMAIN )
					: __( 'Revisar manualmente el pedido. Las líneas de regalo fueron retiradas y este rechazo no se reintenta automáticamente.', LCTER_WCPL_TEXT_DOMAIN ),
				'recoverable'    => $recoverable,
				'operation'      => 'reward_redemption',
			);
		}

		$cancellation_status = sanitize_key( (string) $order->get_meta( WooCommerce_Orders_Adapter::ORDER_POINTS_CANCELLATION_STATUS_META ) );
		if ( 'processing_error' === $cancellation_status ) {
			$error_code = sanitize_key( (string) $order->get_meta( WooCommerce_Orders_Adapter::ORDER_POINTS_CANCELLATION_ERROR_META ) );
			$errors[]   = array(
				'type'           => sprintf(
					/* translators: %s: internal error code. */
					__( 'Error recuperable de reversión de puntos (%s)', LCTER_WCPL_TEXT_DOMAIN ),
					$error_code ?: 'unknown'
				),
				'message'        => $this->cancellation_error_message( $error_code ),
				'points'         => max( 0, (int) $order->get_meta( WooCommerce_Orders_Adapter::ORDER_POINTS_CANCELLATION_PENDING_META ) ),
				'date'           => (string) $order->get_meta( WooCommerce_Orders_Adapter::ORDER_POINTS_CANCELLATION_ERROR_AT_META ),
				'recommendation' => 'insufficient_balance' === $error_code
					? __( 'Revisar el saldo del cliente. La reversión solo se completará cuando pueda descontarse el importe íntegro.', LCTER_WCPL_TEXT_DOMAIN )
					: __( 'Revisar la persistencia de saldo y transacciones y volver a ejecutar la operación.', LCTER_WCPL_TEXT_DOMAIN ),
				'recoverable'    => true,
				'operation'      => 'points_cancellation',
			);
		}

		return $errors;
	}

	/**
	 * Return a human-readable redemption error.
	 */
	private function redemption_error_message( string $error, string $status ): string {
		if ( 'rejected' === $status && '' !== $error ) {
			return $error;
		}

		$messages = array(
			'points_not_redeemed' => __( 'No se pudo completar el descuento idempotente de puntos.', LCTER_WCPL_TEXT_DOMAIN ),
			'reward_not_recorded' => __( 'Los puntos pudieron descontarse, pero falta completar una o más filas de order_rewards.', LCTER_WCPL_TEXT_DOMAIN ),
			'invalid_order'       => __( 'El pedido o su selección de regalos no son válidos.', LCTER_WCPL_TEXT_DOMAIN ),
			'invalid_items'       => __( 'Los metadatos de las líneas de regalo no son coherentes.', LCTER_WCPL_TEXT_DOMAIN ),
		);

		return $messages[ $error ] ?? __( 'El canje no pudo completarse por una interrupción recuperable.', LCTER_WCPL_TEXT_DOMAIN );
	}

	/**
	 * Return a human-readable points cancellation error.
	 */
	private function cancellation_error_message( string $error ): string {
		if ( 'insufficient_balance' === $error ) {
			return __( 'El saldo actual no alcanza para revertir todos los puntos generados por el pedido.', LCTER_WCPL_TEXT_DOMAIN );
		}

		return __( 'La reversión atómica de puntos no pudo completarse.', LCTER_WCPL_TEXT_DOMAIN );
	}

	/**
	 * Display a one-time retry acknowledgement.
	 */
	private function render_retry_notice( int $order_id ): void {
		$key       = $this->retry_notice_key( $order_id );
		$operation = get_transient( $key );
		if ( ! is_string( $operation ) || '' === $operation ) {
			return;
		}

		delete_transient( $key );
		?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'Reintento ejecutado. Revisa el estado actualizado de la incidencia.', LCTER_WCPL_TEXT_DOMAIN ); ?></p></div>
		<?php
	}

	/**
	 * Format the stored WordPress-local error date.
	 */
	private function format_date( string $datetime ): string {
		if ( '' === $datetime ) {
			return __( 'No disponible', LCTER_WCPL_TEXT_DOMAIN );
		}

		$formatted = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $datetime, true );

		return $formatted ?: $datetime;
	}

	private function nonce_action( int $order_id, string $operation ): string {
		return self::NONCE_ACTION . ':' . $order_id . ':' . $operation;
	}

	private function retry_notice_key( int $order_id ): string {
		return 'lcter_wcpl_retry_' . get_current_user_id() . '_' . $order_id;
	}
}
