<?php
/**
 * Order reward traceability admin UI.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL\Admin_UI;

use LCTER_WCPL\Adapters\WooCommerce_Checkout_Adapter;
use LCTER_WCPL\Services\Reward_Traceability_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders redeemed rewards from the canonical traceability table.
 */
class Order_Traceability_Admin {

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
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'woocommerce_admin_order_data_after_payment_info', array( $this, 'render_order_rewards' ), 20, 1 );
	}

	/**
	 * Render redeemed reward traceability in the WooCommerce order screen.
	 *
	 * @param mixed $order WooCommerce order.
	 */
	public function render_order_rewards( $order ): void {
		if ( ! $order || ! is_callable( array( $order, 'get_id' ) ) ) {
			return;
		}

		$order_id = (int) $order->get_id();
		if ( ! current_user_can( 'edit_shop_order', $order_id ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$traceability = new Reward_Traceability_Service();
		$rewards      = $traceability->get_rewards_by_order( $order_id );
		$total_points = array_sum( array_column( $rewards, 'points_cost_total' ) );
		$reward_state = sanitize_key( (string) $order->get_meta( '_lcter_wcpl_reward_state' ) );
		$redemption_status = sanitize_key( (string) $order->get_meta( '_lcter_wcpl_reward_redemption_status' ) );
		$is_selected  = WooCommerce_Checkout_Adapter::REWARD_STATE_SELECTED === $reward_state ||
			( '' === $reward_state && in_array( $redemption_status, array( 'pending_payment', 'processing_error' ), true ) );
		$is_redeemed  = WooCommerce_Checkout_Adapter::REWARD_STATE_REDEEMED === $reward_state;
		?>
		<div class="lcter-wcpl-order-rewards">
			<?php if ( $is_selected ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'REGALO PENDIENTE DE PAGO.', LCTER_WCPL_TEXT_DOMAIN ); ?></strong>
						<?php esc_html_e( 'La selección todavía no está canjeada: no preparar ni entregar estos artículos hasta completar el pago y el canje.', LCTER_WCPL_TEXT_DOMAIN ); ?>
					</p>
				</div>
			<?php elseif ( $is_redeemed ) : ?>
				<div class="notice notice-success inline">
					<p><strong><?php esc_html_e( 'REGALO CANJEADO.', LCTER_WCPL_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( 'El pago, el descuento de puntos y la trazabilidad se completaron.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
				</div>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Regalos canjeados', LCTER_WCPL_TEXT_DOMAIN ); ?></h3>
			<p class="description"><?php esc_html_e( 'Fuente principal: tabla de trazabilidad de rewards del plugin.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>

			<?php if ( empty( $rewards ) ) : ?>
				<p><?php esc_html_e( 'Este pedido no tiene regalos canjeados registrados.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Producto', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'SKU', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Cantidad', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Puntos/unidad', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Puntos totales', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Fecha', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $rewards as $reward ) : ?>
							<tr>
								<td><?php echo esc_html( $reward['product_name'] ); ?></td>
								<td><?php echo esc_html( $reward['sku'] ?: '—' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $reward['quantity'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $reward['points_cost_each'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $reward['points_cost_total'] ) ); ?></td>
								<td><?php echo esc_html( $this->format_date( $reward['created_at'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot><tr>
						<th colspan="4"><?php esc_html_e( 'Total canjeado', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
						<th><?php echo esc_html( number_format_i18n( $total_points ) ); ?></th>
						<th></th>
					</tr></tfoot>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Format a stored database datetime for administration.
	 */
	private function format_date( string $datetime ): string {
		if ( '' === $datetime ) {
			return '—';
		}

		$formatted = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $datetime, true );

		return $formatted ?: $datetime;
	}
}
