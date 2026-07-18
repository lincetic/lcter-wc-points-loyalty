<?php
/**
 * Manual order loyalty movement restoration administration.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL\Admin_UI;

use LCTER_WCPL\Adapters\WooCommerce_Checkout_Adapter;
use LCTER_WCPL\Adapters\WooCommerce_Orders_Adapter;
use LCTER_WCPL\Services\Loyalty_Movements_Restoration_Service;
use LCTER_WCPL\Services\Points_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows pending restore warnings and executes the explicit restore action.
 */
class Order_Loyalty_Restoration_Admin {
	const ACTION       = 'lcter_wcpl_restore_order_loyalty_movements';
	const NONCE_ACTION = 'lcter_wcpl_restore_order_loyalty_movements';
	const NONCE_NAME   = '_lcter_wcpl_restore_nonce';

	/** @var self|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_admin_order_data_after_payment_info', array( $this, 'render_section' ), 25, 1 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_restore' ) );
	}

	/**
	 * Render warning and explicit restore action in the order edit screen.
	 *
	 * @param mixed $order WooCommerce order.
	 */
	public function render_section( $order ): void {
		if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_id' ) ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$order_id = (int) $order->get_id();
		$this->render_action_notice( $order_id );

		if ( ! $order->is_paid() || ! WooCommerce_Orders_Adapter::order_has_pending_reversed_movements( $order ) ) {
			return;
		}

		$state             = sanitize_key( (string) $order->get_meta( WooCommerce_Orders_Adapter::LOYALTY_MOVEMENTS_STATE_META ) );
		$required_balance  = max( 0, (int) $order->get_meta( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_REQUIRED_BALANCE_META ) );
		$available_balance = max( 0, (int) $order->get_meta( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_AVAILABLE_BALANCE_META ) );
		$current_balance   = (int) $order->get_meta( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_CURRENT_BALANCE_META );
		$projected_balance = (int) $order->get_meta( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_PROJECTED_BALANCE_META );
		$missing_points    = max( 0, (int) $order->get_meta( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_MISSING_POINTS_META ) );
		?>
		<div class="lcter-wcpl-loyalty-restore">
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Este pedido volvió a un estado pagado después de haber revertido sus movimientos de fidelización. Los puntos no se han restaurado automáticamente.', LCTER_WCPL_TEXT_DOMAIN ); ?></strong></p>
				<?php if ( WooCommerce_Orders_Adapter::LOYALTY_STATE_RESTORE_ERROR === $state ) : ?>
					<p>
						<?php
						printf(
							esc_html__( 'Restauración pendiente de resolución administrativa. Saldo necesario: %1$s puntos. Saldo disponible: %2$s puntos.', LCTER_WCPL_TEXT_DOMAIN ),
							esc_html( number_format_i18n( $required_balance ) ),
							esc_html( number_format_i18n( $available_balance ) )
						);
						?>
					</p>
					<p>
						<?php
						printf(
							esc_html__( 'Saldo actual: %1$s. Saldo proyectado: %2$s. Puntos faltantes: %3$s.', LCTER_WCPL_TEXT_DOMAIN ),
							esc_html( number_format_i18n( $current_balance ) ),
							esc_html( number_format_i18n( $projected_balance ) ),
							esc_html( number_format_i18n( $missing_points ) )
						);
						?>
					</p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>" />
					<?php wp_nonce_field( $this->nonce_action( $order_id ), self::NONCE_NAME ); ?>
					<?php submit_button( __( 'Restaurar movimientos de fidelización', LCTER_WCPL_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Execute the protected restore request.
	 */
	public function handle_restore(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para restaurar movimientos de fidelización.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$order_value = isset( $_POST['order_id'] ) ? wp_unslash( $_POST['order_id'] ) : null;
		$nonce_value = isset( $_POST[ self::NONCE_NAME ] ) ? wp_unslash( $_POST[ self::NONCE_NAME ] ) : null;
		$order_id    = is_scalar( $order_value ) ? absint( $order_value ) : 0;
		$nonce       = is_scalar( $nonce_value ) ? sanitize_text_field( (string) $nonce_value ) : '';

		if ( $order_id <= 0 || ! wp_verify_nonce( $nonce, $this->nonce_action( $order_id ) ) ) {
			wp_die( esc_html__( 'No se pudo verificar la solicitud de restauración.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'No se encontró el pedido.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		if ( ! $order->is_paid() ) {
			wp_die( esc_html__( 'El pedido no está en un estado pagado.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		if ( ! WooCommerce_Orders_Adapter::order_has_pending_reversed_movements( $order ) ) {
			wp_die( esc_html__( 'El pedido no tiene movimientos revertidos pendientes de restauración.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$current_cycle = WooCommerce_Orders_Adapter::get_order_loyalty_cycle( $order );
		$result        = ( new Loyalty_Movements_Restoration_Service() )->restore_order(
			(int) $order->get_customer_id(),
			$order_id,
			$order->is_paid(),
			get_current_user_id(),
			$current_cycle
		);

		if ( Points_Service::RESULT_ADDED === $result['status'] ) {
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_CYCLE_META, (int) $result['cycle'] );
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_MOVEMENTS_STATE_META, WooCommerce_Orders_Adapter::LOYALTY_STATE_RESTORED );
			$order->delete_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_ERROR_META );
			$order->delete_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_CURRENT_BALANCE_META );
			$order->delete_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_PROJECTED_BALANCE_META );
			$order->delete_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_MISSING_POINTS_META );
			$order->delete_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_EARNED_POINTS_META );
			$order->delete_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_REDEEMED_POINTS_META );
			$order->delete_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_REQUIRED_BALANCE_META );
			$order->delete_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_AVAILABLE_BALANCE_META );
			$order->add_order_note(
				sprintf(
					__( 'Movimientos de fidelización restaurados manualmente por el administrador #%1$d. Puntos generados restaurados: %2$s. Puntos canjeados descontados de nuevo: %3$s. Clave de idempotencia: %4$s.', LCTER_WCPL_TEXT_DOMAIN ),
					get_current_user_id(),
					number_format_i18n( (int) $result['earned_points'] ),
					number_format_i18n( (int) $result['redeemed_points'] ),
					$result['idempotency_key']
				)
			);
			WooCommerce_Checkout_Adapter::sync_order_reward_visual_state( $order, false );
			$order->save();
			$this->redirect_with_notice( $order, 'restored' );
		}

		if ( Points_Service::RESULT_INSUFFICIENT_BALANCE === $result['status'] ) {
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_MOVEMENTS_STATE_META, WooCommerce_Orders_Adapter::LOYALTY_STATE_RESTORE_ERROR );
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_ERROR_META, 'insufficient_balance' );
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_REQUIRED_BALANCE_META, (int) $result['required_balance'] );
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_AVAILABLE_BALANCE_META, (int) $result['available_balance'] );
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_CURRENT_BALANCE_META, (int) $result['current_balance'] );
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_PROJECTED_BALANCE_META, (int) $result['projected_balance'] );
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_MISSING_POINTS_META, (int) $result['missing_points'] );
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_EARNED_POINTS_META, (int) $result['earned_points'] );
			$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_RESTORE_REDEEMED_POINTS_META, (int) $result['redeemed_points'] );
			$order->add_order_note(
				sprintf(
					__( 'No se restauraron los movimientos de fidelización: el saldo proyectado quedaría negativo. Saldo actual: %1$s. Puntos ganados a restaurar: %2$s. Puntos canjeados a descontar: %3$s. Saldo proyectado: %4$s. Puntos faltantes: %5$s.', LCTER_WCPL_TEXT_DOMAIN ),
					number_format_i18n( (int) $result['current_balance'] ),
					number_format_i18n( (int) $result['earned_points'] ),
					number_format_i18n( (int) $result['redeemed_points'] ),
					number_format_i18n( (int) $result['projected_balance'] ),
					number_format_i18n( (int) $result['missing_points'] )
				)
			);
			WooCommerce_Checkout_Adapter::sync_order_reward_visual_state( $order, false );
			$order->save();
			$this->redirect_with_notice( $order, 'insufficient_balance' );
		}

		set_transient( $this->notice_key( $order_id ), sanitize_key( $result['error'] ?: $result['status'] ), MINUTE_IN_SECONDS );
		$redirect_url = (string) $order->get_edit_order_url();
		wp_safe_redirect( $redirect_url ?: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id ) );
		exit;
	}

	private function render_action_notice( int $order_id ): void {
		$key    = $this->notice_key( $order_id );
		$notice = get_transient( $key );
		if ( ! is_string( $notice ) || '' === $notice ) {
			return;
		}

		delete_transient( $key );
		$message = __( 'Solicitud de restauración procesada. Revisa el estado contable del pedido.', LCTER_WCPL_TEXT_DOMAIN );
		if ( 'restored' === $notice ) {
			$message = __( 'Movimientos de fidelización restaurados correctamente.', LCTER_WCPL_TEXT_DOMAIN );
		} elseif ( 'insufficient_balance' === $notice ) {
			$message = __( 'No se restauró ningún movimiento porque el cliente no tiene saldo suficiente para descontar de nuevo los regalos.', LCTER_WCPL_TEXT_DOMAIN );
		}
		?>
		<div class="notice notice-info inline"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	private function redirect_with_notice( $order, string $notice ): void {
		$order_id = (int) $order->get_id();
		set_transient( $this->notice_key( $order_id ), $notice, MINUTE_IN_SECONDS );
		$redirect_url = (string) $order->get_edit_order_url();
		wp_safe_redirect( $redirect_url ?: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id ) );
		exit;
	}

	private function nonce_action( int $order_id ): string {
		return self::NONCE_ACTION . ':' . $order_id;
	}

	private function notice_key( int $order_id ): string {
		return 'lcter_wcpl_restore_' . get_current_user_id() . '_' . $order_id;
	}
}
