<?php
/**
 * Initial bonus administration UI and action.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL\Admin_UI;

use LCTER_WCPL\Services\Initial_Bonus_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the manual, confirmed initial bonus operation.
 */
class Initial_Bonus_Admin {
	const ACTION = 'lcter_wcpl_run_initial_bonus';
	const NONCE  = 'lcter_wcpl_initial_bonus';

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
		add_action( 'lcter_wcpl_dashboard_after_stats', array( $this, 'render_panel' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
	}

	/**
	 * Render the confirmation form and the latest execution result.
	 */
	public function render_panel(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$summary = get_transient( $this->result_transient_key() );
		if ( is_array( $summary ) ) {
			delete_transient( $this->result_transient_key() );
			$this->render_summary( $summary );
		}
		?>
		<div class="card lcter-wcpl-initial-bonus">
			<h2><?php esc_html_e( 'Bonus inicial de clientes', LCTER_WCPL_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Asigna 10.000 puntos una sola vez a cada usuario WordPress que tenga el rol customer.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
			<p><strong><?php esc_html_e( 'La operación no se ejecuta automáticamente y puede tardar si existen muchos clientes.', LCTER_WCPL_TEXT_DOMAIN ); ?></strong></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE ); ?>
				<p>
					<label>
						<input type="checkbox" name="lcter_wcpl_confirm_initial_bonus" value="1" required />
						<?php esc_html_e( 'Confirmo que deseo procesar el bonus inicial para todos los usuarios con rol customer.', LCTER_WCPL_TEXT_DOMAIN ); ?>
					</label>
				</p>
				<?php submit_button( __( 'Ejecutar bonus inicial', LCTER_WCPL_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the confirmed administrative action.
	 */
	public function handle_action(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para ejecutar esta acción.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		check_admin_referer( self::NONCE );

		$confirmed = isset( $_POST['lcter_wcpl_confirm_initial_bonus'] )
			? sanitize_text_field( wp_unslash( $_POST['lcter_wcpl_confirm_initial_bonus'] ) )
			: '';
		if ( '1' !== $confirmed ) {
			wp_die( esc_html__( 'Debes confirmar explícitamente la ejecución del bonus.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$summary = array(
			'processed' => 0,
			'bonused'   => 0,
			'skipped'   => 0,
			'errors'    => 0,
		);

		try {
			$customer_ids = get_users(
				array(
					'role'   => 'customer',
					'fields' => 'ID',
				)
			);
			$summary      = ( new Initial_Bonus_Service() )->apply_to_customers( $customer_ids, get_current_user_id() );
		} catch ( \Throwable $exception ) {
			$summary['errors'] = 1;
		}

		set_transient( $this->result_transient_key(), $summary, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=lcter-wcpl-dashboard' ) );
		exit;
	}

	/**
	 * Render the latest summary.
	 *
	 * @param array $summary Execution counters.
	 */
	private function render_summary( array $summary ): void {
		?>
		<div class="notice notice-info inline">
			<p><strong><?php esc_html_e( 'Resultado del bonus inicial', LCTER_WCPL_TEXT_DOMAIN ); ?></strong></p>
			<ul>
				<li><?php echo esc_html( sprintf( __( 'Clientes procesados: %d', LCTER_WCPL_TEXT_DOMAIN ), (int) ( $summary['processed'] ?? 0 ) ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Clientes bonificados: %d', LCTER_WCPL_TEXT_DOMAIN ), (int) ( $summary['bonused'] ?? 0 ) ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Clientes omitidos: %d', LCTER_WCPL_TEXT_DOMAIN ), (int) ( $summary['skipped'] ?? 0 ) ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Errores: %d', LCTER_WCPL_TEXT_DOMAIN ), (int) ( $summary['errors'] ?? 0 ) ) ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Return the current administrator result key.
	 */
	private function result_transient_key(): string {
		return 'lcter_wcpl_initial_bonus_result_' . get_current_user_id();
	}
}
