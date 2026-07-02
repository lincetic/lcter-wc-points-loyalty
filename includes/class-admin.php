<?php
/**
 * Admin functionality class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

use LCTER_WCPL\Services\Points_Service as Application_Points_Service;

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
			Settings::OPTION_INITIAL_BONUS_POINTS,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( Settings::class, 'sanitize_initial_bonus_points' ),
				'default'           => Settings::DEFAULT_INITIAL_BONUS_POINTS,
			)
		);

		register_setting(
			'lcter_wcpl_settings_group',
			Settings::OPTION_REWARD_COST_MULTIPLIER,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( Settings::class, 'sanitize_reward_cost_multiplier' ),
				'default'           => Settings::DEFAULT_REWARD_COST_MULTIPLIER,
			)
		);

		add_settings_section(
			'lcter_wcpl_general_settings',
			__( 'Ajustes generales', LCTER_WCPL_TEXT_DOMAIN ),
			array( $this, 'render_general_settings_description' ),
			'lcter-wcpl-settings'
		);

		add_settings_field(
			Settings::OPTION_INITIAL_BONUS_POINTS,
			__( 'Puntos de bonus inicial', LCTER_WCPL_TEXT_DOMAIN ),
			array( $this, 'render_initial_bonus_points_field' ),
			'lcter-wcpl-settings',
			'lcter_wcpl_general_settings'
		);

		add_settings_field(
			Settings::OPTION_REWARD_COST_MULTIPLIER,
			__( 'Multiplicador de coste de rewards', LCTER_WCPL_TEXT_DOMAIN ),
			array( $this, 'render_reward_cost_multiplier_field' ),
			'lcter-wcpl-settings',
			'lcter_wcpl_general_settings'
		);

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
				'sanitize_callback' => function ( $value ) {
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
				'sanitize_callback' => function ( $value ) {
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
		add_filter( 'option_page_capability_lcter_wcpl_settings_group', array( $this, 'settings_capability' ) );
	}

	/**
	 * Use the same capability for the settings page and Settings API save action.
	 */
	public function settings_capability(): string {
		return 'manage_woocommerce';
	}

	/**
	 * Render the general settings section introduction.
	 */
	public function render_general_settings_description(): void {
		echo '<p>' . esc_html__( 'Valores de negocio aplicados a nuevas operaciones del programa de fidelidad.', LCTER_WCPL_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Render the configurable initial bonus field.
	 */
	public function render_initial_bonus_points_field(): void {
		?>
		<input type="number" class="small-text" min="1" step="1" id="<?php echo esc_attr( Settings::OPTION_INITIAL_BONUS_POINTS ); ?>" name="<?php echo esc_attr( Settings::OPTION_INITIAL_BONUS_POINTS ); ?>" value="<?php echo esc_attr( (string) Settings::get_initial_bonus_points() ); ?>" required />
		<p class="description"><?php esc_html_e( 'Importe usado al ejecutar manualmente el bonus inicial. Valor por defecto: 10.000.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
		<?php
	}

	/**
	 * Render the configurable reward multiplier field.
	 */
	public function render_reward_cost_multiplier_field(): void {
		?>
		<input type="number" class="small-text" min="1" step="1" id="<?php echo esc_attr( Settings::OPTION_REWARD_COST_MULTIPLIER ); ?>" name="<?php echo esc_attr( Settings::OPTION_REWARD_COST_MULTIPLIER ); ?>" value="<?php echo esc_attr( (string) Settings::get_reward_cost_multiplier() ); ?>" required />
		<p class="description"><?php esc_html_e( 'Se multiplica por el precio del producto con IVA incluido para sugerir su coste en puntos. Valor por defecto: 2.000.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
		<?php
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

			<?php do_action( 'lcter_wcpl_dashboard_after_stats' ); ?>
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
			<?php settings_errors( 'lcter_wcpl_settings' ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'lcter_wcpl_settings_group' ); ?>
				<?php do_settings_sections( 'lcter-wcpl-settings' ); ?>

				<h2><?php esc_html_e( 'Opciones reservadas', LCTER_WCPL_TEXT_DOMAIN ); ?></h2>
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
								<?php esc_html_e( 'Configuracion reservada: la caducidad todavia no esta implementada (0 = nunca expiran).', LCTER_WCPL_TEXT_DOMAIN ); ?>
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
								<?php esc_html_e( 'Configuracion reservada: las notificaciones todavia no estan implementadas.', LCTER_WCPL_TEXT_DOMAIN ); ?>
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
	 * Backward-compatible proxy for the reward product tab.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function product_tab( array $tabs ): array {
		return Admin_UI\Reward_Product_Admin::instance()->product_tab( $tabs );
	}

	/**
	 * Backward-compatible proxy for the reward product panel.
	 */
	public function product_panel(): void {
		Admin_UI\Reward_Product_Admin::instance()->product_panel();
	}

	/**
	 * Backward-compatible proxy for saving reward product fields.
	 *
	 * @param int $product_id Product ID.
	 */
	public function save_reward_product( $product_id ): void {
		Admin_UI\Reward_Product_Admin::instance()->save_reward_product( $product_id );
	}

	/**
	 * Backward-compatible proxy for order reward traceability.
	 *
	 * @param mixed $order WooCommerce order.
	 */
	public function render_order_rewards( $order ): void {
		Admin_UI\Order_Traceability_Admin::instance()->render_order_rewards( $order );
	}
}
