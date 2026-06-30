<?php
/**
 * Admin functionality class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

use LCTER_WCPL\Services\Points_Service as Application_Points_Service;
use LCTER_WCPL\Services\Rewards_Service;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class - manages dashboard and settings.
 */
class Admin {

	/**
	 * Instance of the admin class.
	 *
	 * @var Admin
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_settings();
		$this->register_hooks();
	}

	/**
	 * Register plugin settings.
	 */
	private function register_settings(): void {
		register_setting(
			'lcter_wcpl_settings_group',
			'lcter_wcpl_points_expiry_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'intval',
				'default'           => 0,
			)
		);

		register_setting(
			'lcter_wcpl_settings_group',
			'lcter_wcpl_enable_notifications',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => function( $value ) {
					return '1' === $value ? '1' : '0';
				},
				'default'           => '1',
			)
		);

		register_setting(
			'lcter_wcpl_settings_group',
			'lcter_wcpl_remove_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => function( $value ) {
					return '1' === $value ? '1' : '0';
				},
				'default'           => '0',
			)
		);
	}

	/**
	 * Register admin hooks.
	 */
	private function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_reward_product' ) );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_assets(): void {
		wp_enqueue_style(
			'lcter-wcpl-admin',
			LCTER_WCPL_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			LCTER_WCPL_VERSION
		);

		wp_enqueue_script(
			'lcter-wcpl-admin',
			LCTER_WCPL_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			LCTER_WCPL_VERSION,
			true
		);
	}

	/**
	 * Render dashboard page.
	 */
	public static function render_dashboard(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta pagina.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		$totals                = ( new Application_Points_Service() )->get_dashboard_totals();
		$total_customers       = $totals['customers'];
		$total_points_issued   = $totals['earned'];
		$total_points_redeemed = $totals['redeemed'];

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Panel de Control - LCTER Points Loyalty', LCTER_WCPL_TEXT_DOMAIN ); ?></h1>

			<div class="lcter-wcpl-dashboard">
				<div class="stat-box">
					<h3><?php esc_html_e( 'Clientes Activos', LCTER_WCPL_TEXT_DOMAIN ); ?></h3>
					<p class="stat-value"><?php echo intval( $total_customers ); ?></p>
				</div>

				<div class="stat-box">
					<h3><?php esc_html_e( 'Puntos Emitidos', LCTER_WCPL_TEXT_DOMAIN ); ?></h3>
					<p class="stat-value"><?php echo intval( $total_points_issued ); ?></p>
				</div>

				<div class="stat-box">
					<h3><?php esc_html_e( 'Puntos Canjeados', LCTER_WCPL_TEXT_DOMAIN ); ?></h3>
					<p class="stat-value"><?php echo intval( $total_points_redeemed ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta pagina.', LCTER_WCPL_TEXT_DOMAIN ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Configuracion - LCTER Points Loyalty', LCTER_WCPL_TEXT_DOMAIN ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'lcter_wcpl_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="lcter_wcpl_points_expiry_days">
								<?php esc_html_e( 'Dias de Expiracion de Puntos', LCTER_WCPL_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input
								type="number"
								id="lcter_wcpl_points_expiry_days"
								name="lcter_wcpl_points_expiry_days"
								value="<?php echo esc_attr( get_option( 'lcter_wcpl_points_expiry_days', 0 ) ); ?>"
								min="0"
							/>
							<p class="description">
								<?php esc_html_e( 'Numero de dias antes de que expiren los puntos (0 = nunca expiran).', LCTER_WCPL_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="lcter_wcpl_enable_notifications">
								<?php esc_html_e( 'Habilitar Notificaciones', LCTER_WCPL_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input
								type="checkbox"
								id="lcter_wcpl_enable_notifications"
								name="lcter_wcpl_enable_notifications"
								value="1"
								<?php checked( get_option( 'lcter_wcpl_enable_notifications', '1' ), '1' ); ?>
							/>
							<p class="description">
								<?php esc_html_e( 'Enviar notificaciones a los clientes cuando ganen o canjeen puntos.', LCTER_WCPL_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="lcter_wcpl_remove_data_on_uninstall">
								<?php esc_html_e( 'Eliminar datos en desinstalacion', LCTER_WCPL_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input
								type="checkbox"
								id="lcter_wcpl_remove_data_on_uninstall"
								name="lcter_wcpl_remove_data_on_uninstall"
								value="1"
								<?php checked( get_option( 'lcter_wcpl_remove_data_on_uninstall', '0' ), '1' ); ?>
							/>
							<p class="description">
								<?php esc_html_e( 'Elimina los datos del plugin solo si esta activada esta opcion.', LCTER_WCPL_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add reward panel in product edit page.
	 */
	public function product_panel(): void {
		global $post;

		$rewards_service = new Rewards_Service();
		$reward           = $post ? $rewards_service->get_reward_by_product( (int) $post->ID ) : null;

		?>
		<div id="lcter_wcpl_product_panel" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'                => 'lcter_wcpl_reward_points_cost',
						'label'             => __( 'Coste del Regalo en Puntos', LCTER_WCPL_TEXT_DOMAIN ),
						'description'       => __( 'Dejar en blanco o 0 para que este producto no sea un regalo.', LCTER_WCPL_TEXT_DOMAIN ),
						'type'              => 'number',
						'value'             => $reward ? (int) $reward['points_cost'] : '',
						'custom_attributes' => array(
							'step' => '1',
							'min'  => '0',
						),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => 'lcter_wcpl_reward_sort_order',
						'label'             => __( 'Orden del Regalo', LCTER_WCPL_TEXT_DOMAIN ),
						'type'              => 'number',
						'value'             => $reward ? (int) $reward['sort_order'] : 0,
						'custom_attributes' => array(
							'step' => '1',
						),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => 'lcter_wcpl_reward_active',
						'label'             => __( 'Regalo Activo', LCTER_WCPL_TEXT_DOMAIN ),
						'description'       => __( '1 = activo, 0 = inactivo.', LCTER_WCPL_TEXT_DOMAIN ),
						'type'              => 'number',
						'value'             => $reward ? (int) $reward['active'] : 1,
						'custom_attributes' => array(
							'step' => '1',
							'min'  => '0',
							'max'  => '1',
						),
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save reward product configuration.
	 *
	 * @param int $product_id Product ID.
	 */
	public function save_reward_product( $product_id ): void {
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		$product_id  = (int) $product_id;
		$points_cost = isset( $_POST['lcter_wcpl_reward_points_cost'] ) ? absint( wp_unslash( $_POST['lcter_wcpl_reward_points_cost'] ) ) : 0;
		$service     = new Rewards_Service();
		$reward      = $service->get_reward_by_product( $product_id );

		if ( $points_cost <= 0 ) {
			if ( $reward ) {
				$service->delete_reward( (int) $reward['id'] );
			}
			return;
		}

		$service->save_reward(
			array(
				'id'          => $reward ? (int) $reward['id'] : 0,
				'product_id'  => $product_id,
				'points_cost' => $points_cost,
				'active'      => isset( $_POST['lcter_wcpl_reward_active'] ) ? absint( wp_unslash( $_POST['lcter_wcpl_reward_active'] ) ) : 1,
				'sort_order'  => isset( $_POST['lcter_wcpl_reward_sort_order'] ) ? (int) wp_unslash( $_POST['lcter_wcpl_reward_sort_order'] ) : 0,
			)
		);
	}

}
