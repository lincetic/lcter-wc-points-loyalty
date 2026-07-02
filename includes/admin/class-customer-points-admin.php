<?php
/**
 * Customer point balance administration.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL\Admin_UI;

use LCTER_WCPL\Services\Points_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a controlled manual adjustment action to WooCommerce customer editing.
 */
class Customer_Points_Admin {
	const ACTION       = 'lcter_wcpl_manual_points_adjustment';
	const NONCE_ACTION = 'lcter_wcpl_manual_points_adjustment';
	const PAGE_SLUG    = 'lcter-wcpl-customer-points-adjustment';

	/** @var self|null Singleton instance. */
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
	 * Register customer administration hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'edit_user_profile', array( $this, 'render_customer_panel' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
	}

	/**
	 * Register a hidden page containing the standalone adjustment form.
	 */
	public function register_page(): void {
		add_submenu_page(
			'lcter-wcpl-dashboard',
			__( 'Ajustar puntos del cliente', LCTER_WCPL_TEXT_DOMAIN ),
			__( 'Ajustar puntos', LCTER_WCPL_TEXT_DOMAIN ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_adjustment_page' )
		);
		remove_submenu_page( 'lcter-wcpl-dashboard', self::PAGE_SLUG );
	}

	/**
	 * Show the current balance and explicit action link in the customer profile.
	 *
	 * @param \WP_User $user Edited user.
	 */
	public function render_customer_panel( $user ): void {
		if ( ! $this->can_adjust_customer( $user ) ) {
			return;
		}

		$customer_id = (int) $user->ID;
		$balance     = ( new Points_Service() )->get_balance( $customer_id );
		$notice      = get_transient( $this->notice_key( $customer_id ) );
		if ( is_array( $notice ) ) {
			delete_transient( $this->notice_key( $customer_id ) );
			$notice_class = 'success' === ( $notice['type'] ?? '' ) ? 'notice-success' : 'notice-error';
			?>
			<div class="notice <?php echo esc_attr( $notice_class ); ?> inline"><p><?php echo esc_html( (string) ( $notice['message'] ?? '' ) ); ?></p></div>
			<?php
		}

		$url = add_query_arg(
			array(
				'page'        => self::PAGE_SLUG,
				'customer_id' => $customer_id,
			),
			admin_url( 'admin.php' )
		);
		?>
		<h2><?php esc_html_e( 'Puntos de fidelidad', LCTER_WCPL_TEXT_DOMAIN ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Saldo actual', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
				<td>
					<strong><?php echo esc_html( number_format_i18n( $balance ) ); ?></strong>
					<p><a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Ajustar puntos', LCTER_WCPL_TEXT_DOMAIN ); ?></a></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the standalone, protected adjustment form.
	 */
	public function render_adjustment_page(): void {
		$customer = $this->get_requested_customer_from_query();
		if ( ! $this->can_adjust_customer( $customer ) ) {
			wp_die( esc_html__( 'No tienes permiso para ajustar los puntos de este cliente.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$customer_id = (int) $customer->ID;
		$balance     = ( new Points_Service() )->get_balance( $customer_id );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ajustar puntos del cliente', LCTER_WCPL_TEXT_DOMAIN ); ?></h1>
			<p><?php echo esc_html( sprintf( __( 'Cliente: %1$s (ID %2$d). Saldo actual: %3$s puntos.', LCTER_WCPL_TEXT_DOMAIN ), $customer->display_name, $customer_id, number_format_i18n( $balance ) ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="customer_id" value="<?php echo esc_attr( (string) $customer_id ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="lcter_wcpl_manual_points_amount"><?php esc_html_e( 'Ajuste manual', LCTER_WCPL_TEXT_DOMAIN ); ?></label></th>
						<td>
							<input type="number" class="regular-text" step="1" id="lcter_wcpl_manual_points_amount" name="lcter_wcpl_manual_points_amount" required />
							<p class="description"><?php esc_html_e( 'Usa un entero positivo para sumar o negativo para restar.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="lcter_wcpl_manual_points_description"><?php esc_html_e( 'Motivo del ajuste', LCTER_WCPL_TEXT_DOMAIN ); ?></label></th>
						<td><textarea class="regular-text" rows="3" id="lcter_wcpl_manual_points_description" name="lcter_wcpl_manual_points_description" required></textarea></td>
					</tr>
				</table>
				<?php submit_button( __( 'Registrar ajuste', LCTER_WCPL_TEXT_DOMAIN ) ); ?>
				<a href="<?php echo esc_url( get_edit_user_link( $customer_id ) ); ?>"><?php esc_html_e( 'Volver al cliente', LCTER_WCPL_TEXT_DOMAIN ); ?></a>
			</form>
		</div>
		<?php
	}

	/**
	 * Validate and execute the standalone adjustment action.
	 */
	public function handle_action(): void {
		check_admin_referer( self::NONCE_ACTION );

		$customer_id = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
		$customer    = get_user_by( 'id', $customer_id );
		if ( ! $this->can_adjust_customer( $customer ) ) {
			wp_die( esc_html__( 'No tienes permiso para ajustar los puntos de este cliente.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$adjustment  = $this->get_requested_adjustment();
		$description = isset( $_POST['lcter_wcpl_manual_points_description'] )
			? trim( sanitize_textarea_field( wp_unslash( $_POST['lcter_wcpl_manual_points_description'] ) ) )
			: '';

		if ( null === $adjustment || '' === $description ) {
			wp_die( esc_html__( 'El ajuste debe ser un entero distinto de cero y el motivo es obligatorio.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$result = ( new Points_Service() )->adjust_points( $customer_id, $adjustment, $description, get_current_user_id() );
		if ( Points_Service::RESULT_ADDED === $result ) {
			$notice = array(
				'type'    => 'success',
				'message' => __( 'El saldo de puntos se ajustó correctamente y la transacción quedó registrada.', LCTER_WCPL_TEXT_DOMAIN ),
			);
		} elseif ( Points_Service::RESULT_INSUFFICIENT_BALANCE === $result ) {
			$notice = array(
				'type'    => 'error',
				'message' => __( 'No se aplicó el ajuste porque dejaría el saldo en negativo.', LCTER_WCPL_TEXT_DOMAIN ),
			);
		} else {
			$notice = array(
				'type'    => 'error',
				'message' => __( 'No se pudo aplicar el ajuste de puntos.', LCTER_WCPL_TEXT_DOMAIN ),
			);
		}

		set_transient( $this->notice_key( $customer_id ), $notice, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( get_edit_user_link( $customer_id ) );
		exit;
	}

	/**
	 * Check capability, edit permission and the WooCommerce customer role.
	 *
	 * @param mixed $user Candidate WordPress user.
	 */
	private function can_adjust_customer( $user ): bool {
		return $user instanceof \WP_User
			&& current_user_can( 'manage_woocommerce' )
			&& current_user_can( 'edit_user', (int) $user->ID )
			&& in_array( 'customer', (array) $user->roles, true );
	}

	/**
	 * Resolve the customer from the explicit query parameter.
	 *
	 * @return \WP_User|false
	 */
	private function get_requested_customer_from_query() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation; capabilities are checked before output.
		$customer_id = isset( $_GET['customer_id'] ) ? absint( wp_unslash( $_GET['customer_id'] ) ) : 0;

		return get_user_by( 'id', $customer_id );
	}

	/**
	 * Read a signed, non-zero schema-safe integer from the action request.
	 */
	private function get_requested_adjustment(): ?int {
		$value = isset( $_POST['lcter_wcpl_manual_points_amount'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['lcter_wcpl_manual_points_amount'] ) ) ) : '';
		if ( ! preg_match( '/^-?[0-9]+$/', $value ) ) {
			return null;
		}

		$adjustment = (int) $value;
		if ( 0 === $adjustment || $adjustment < -2147483647 || $adjustment > 2147483647 ) {
			return null;
		}

		return $adjustment;
	}

	/**
	 * Return the current administrator/customer notice key.
	 */
	private function notice_key( int $customer_id ): string {
		return 'lcter_wcpl_manual_adjustment_' . get_current_user_id() . '_' . $customer_id;
	}
}
