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
	const ACTION       = 'lcter_wcpl_adjust_customer_points';
	const FORM_ID      = 'lcter-wcpl-adjust-customer-points-form';
	const NONCE_ACTION = 'lcter_wcpl_adjust_customer_points';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Customer ID whose external form must be rendered.
	 *
	 * @var int|null
	 */
	private ?int $form_user_id = null;

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
		add_action( 'edit_user_profile', array( $this, 'render_customer_panel' ) );
		add_action( 'admin_footer-user-edit.php', array( $this, 'render_adjustment_form' ) );
		add_action( 'admin_post_lcter_wcpl_adjust_customer_points', array( $this, 'handle_action' ) );
	}

	/**
	 * Show the balance and adjustment controls in the customer profile.
	 *
	 * The controls belong to the standalone form rendered in the admin footer,
	 * avoiding a nested form inside WordPress' user edit form.
	 *
	 * @param \WP_User $user Edited user.
	 */
	public function render_customer_panel( $user ): void {
		if ( ! $this->can_adjust_customer( $user ) ) {
			return;
		}

		$user_id            = (int) $user->ID;
		$this->form_user_id = $user_id;
		$balance            = ( new Points_Service() )->get_balance( $user_id );
		$notice             = get_transient( $this->notice_key( $user_id ) );

		if ( is_array( $notice ) ) {
			delete_transient( $this->notice_key( $user_id ) );
			$notice_class = 'success' === ( $notice['type'] ?? '' ) ? 'notice-success' : 'notice-error';
			?>
			<div class="notice <?php echo esc_attr( $notice_class ); ?> inline"><p><?php echo esc_html( (string) ( $notice['message'] ?? '' ) ); ?></p></div>
			<?php
		}
		?>
		<h2><?php esc_html_e( 'Puntos de fidelidad', LCTER_WCPL_TEXT_DOMAIN ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Saldo actual', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
				<td><strong><?php echo esc_html( number_format_i18n( $balance ) ); ?></strong></td>
			</tr>
			<tr>
				<th><label for="lcter_wcpl_points_delta"><?php esc_html_e( 'Ajuste manual', LCTER_WCPL_TEXT_DOMAIN ); ?></label></th>
				<td>
					<input type="number" class="regular-text" step="1" id="lcter_wcpl_points_delta" name="delta" form="<?php echo esc_attr( self::FORM_ID ); ?>" required />
					<p class="description"><?php esc_html_e( 'Usa un entero positivo para sumar o negativo para restar.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="lcter_wcpl_adjustment_reason"><?php esc_html_e( 'Motivo del ajuste', LCTER_WCPL_TEXT_DOMAIN ); ?></label></th>
				<td>
					<textarea class="regular-text" rows="3" id="lcter_wcpl_adjustment_reason" name="reason" form="<?php echo esc_attr( self::FORM_ID ); ?>" required></textarea>
					<p class="description"><?php esc_html_e( 'Obligatorio. Se guardará como descripción de la transacción.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td><button type="submit" class="button button-secondary" form="<?php echo esc_attr( self::FORM_ID ); ?>"><?php esc_html_e( 'Registrar ajuste', LCTER_WCPL_TEXT_DOMAIN ); ?></button></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the actual admin-post form outside WordPress' user edit form.
	 */
	public function render_adjustment_form(): void {
		if ( null === $this->form_user_id ) {
			return;
		}

		?>
		<form id="<?php echo esc_attr( self::FORM_ID ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $this->form_user_id ); ?>" />
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
		</form>
		<?php
	}

	/**
	 * Validate and execute the standalone adjustment action.
	 */
	public function handle_action(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para ajustar puntos.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		if ( ! $this->can_adjust_customer( $user ) ) {
			wp_die( esc_html__( 'El usuario indicado no es un cliente válido o no puedes editarlo.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$delta  = $this->get_requested_delta();
		$reason = isset( $_POST['reason'] )
			? trim( sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) )
			: '';

		if ( null === $delta || '' === $reason ) {
			$this->set_notice(
				$user_id,
				'error',
				__( 'El ajuste debe ser un entero distinto de cero y el motivo es obligatorio.', LCTER_WCPL_TEXT_DOMAIN )
			);
			$this->redirect_to_user( $user_id );
		}

		$result = ( new Points_Service() )->adjust_points( $user_id, $delta, $reason, get_current_user_id() );

		if ( Points_Service::RESULT_ADDED === $result ) {
			$this->set_notice(
				$user_id,
				'success',
				__( 'El saldo de puntos se ajustó correctamente y la transacción quedó registrada.', LCTER_WCPL_TEXT_DOMAIN )
			);
		} elseif ( Points_Service::RESULT_INSUFFICIENT_BALANCE === $result ) {
			$this->set_notice(
				$user_id,
				'error',
				__( 'No se aplicó el ajuste porque dejaría el saldo en negativo.', LCTER_WCPL_TEXT_DOMAIN )
			);
		} else {
			$this->set_notice(
				$user_id,
				'error',
				__( 'No se pudo aplicar el ajuste de puntos.', LCTER_WCPL_TEXT_DOMAIN )
			);
		}

		$this->redirect_to_user( $user_id );
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
	 * Read a signed, non-zero schema-safe delta from the action request.
	 */
	private function get_requested_delta(): ?int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The admin-post handler verifies the nonce before calling this method.
		$value = isset( $_POST['delta'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['delta'] ) ) ) : '';
		if ( ! preg_match( '/^-?[0-9]+$/', $value ) ) {
			return null;
		}

		$delta = (int) $value;
		if ( 0 === $delta || $delta < -2147483647 || $delta > 2147483647 ) {
			return null;
		}

		return $delta;
	}

	/**
	 * Store one administrator-specific notice for the next user edit request.
	 *
	 * @param int    $user_id User receiving the balance adjustment.
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 */
	private function set_notice( int $user_id, string $type, string $message ): void {
		set_transient(
			$this->notice_key( $user_id ),
			array(
				'type'    => $type,
				'message' => $message,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Redirect back to the canonical WordPress user edit screen.
	 *
	 * @param int $user_id User to edit.
	 */
	private function redirect_to_user( int $user_id ): never {
		$url = add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Return the current administrator/customer notice key.
	 *
	 * @param int $user_id Edited customer ID.
	 */
	private function notice_key( int $user_id ): string {
		return 'lcter_wcpl_manual_adjustment_' . get_current_user_id() . '_' . $user_id;
	}
}
