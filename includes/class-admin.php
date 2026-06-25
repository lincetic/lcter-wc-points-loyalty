<?php
/**
 * Admin functionality class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL;

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
	private function register_settings() {
		register_setting(
			'lcter_wcpl_settings_group',
			'lcter_wcpl_default_points_rate',
			array(
				'type'              => 'number',
				'sanitize_callback' => 'floatval',
				'default'           => 1,
			)
		);

		register_setting(
			'lcter_wcpl_settings_group',
			'lcter_wcpl_points_expiry_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'intval',
				'default'           => 0, // 0 = never expires.
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
	}

	/**
	 * Register admin hooks.
	 */
	private function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_points' ) );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_assets() {
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
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'lcter-wc-points-loyalty' ) );
		}

		global $wpdb;

		$total_customers = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lcter_wcpl_customer_points" );
		$total_points_issued = $wpdb->get_var( "SELECT SUM(total_earned) FROM {$wpdb->prefix}lcter_wcpl_customer_points" );
		$total_points_redeemed = $wpdb->get_var( "SELECT SUM(total_redeemed) FROM {$wpdb->prefix}lcter_wcpl_customer_points" );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Panel de Control - LCTER Points Loyalty', 'lcter-wc-points-loyalty' ); ?></h1>

			<div class="lcter-wcpl-dashboard">
				<div class="stat-box">
					<h3><?php esc_html_e( 'Clientes Activos', 'lcter-wc-points-loyalty' ); ?></h3>
					<p class="stat-value"><?php echo intval( $total_customers ); ?></p>
				</div>

				<div class="stat-box">
					<h3><?php esc_html_e( 'Puntos Emitidos', 'lcter-wc-points-loyalty' ); ?></h3>
					<p class="stat-value"><?php echo intval( $total_points_issued ); ?></p>
				</div>

				<div class="stat-box">
					<h3><?php esc_html_e( 'Puntos Canjeados', 'lcter-wc-points-loyalty' ); ?></h3>
					<p class="stat-value"><?php echo intval( $total_points_redeemed ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'lcter-wc-points-loyalty' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Configuración - LCTER Points Loyalty', 'lcter-wc-points-loyalty' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'lcter_wcpl_settings_group' ); ?>
				<?php do_settings_sections( 'lcter_wcpl_settings_group' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="lcter_wcpl_default_points_rate">
								<?php esc_html_e( 'Puntos por Unidad de Moneda', 'lcter-wc-points-loyalty' ); ?>
							</label>
						</th>
						<td>
							<input type="number" 
								id="lcter_wcpl_default_points_rate" 
								name="lcter_wcpl_default_points_rate" 
								value="<?php echo esc_attr( get_option( 'lcter_wcpl_default_points_rate', 1 ) ); ?>" 
								step="0.01" 
								min="0"
							/>
							<p class="description">
								<?php esc_html_e( 'Número de puntos que gana el cliente por cada unidad de moneda gastada.', 'lcter-wc-points-loyalty' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="lcter_wcpl_points_expiry_days">
								<?php esc_html_e( 'Días de Expiración de Puntos', 'lcter-wc-points-loyalty' ); ?>
							</label>
						</th>
						<td>
							<input type="number" 
								id="lcter_wcpl_points_expiry_days" 
								name="lcter_wcpl_points_expiry_days" 
								value="<?php echo esc_attr( get_option( 'lcter_wcpl_points_expiry_days', 0 ) ); ?>" 
								min="0"
							/>
							<p class="description">
								<?php esc_html_e( 'Número de días antes de que expiren los puntos (0 = nunca expiran).', 'lcter-wc-points-loyalty' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="lcter_wcpl_enable_notifications">
								<?php esc_html_e( 'Habilitar Notificaciones', 'lcter-wc-points-loyalty' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" 
								id="lcter_wcpl_enable_notifications" 
								name="lcter_wcpl_enable_notifications" 
								value="1"
								<?php checked( get_option( 'lcter_wcpl_enable_notifications', '1' ), '1' ); ?>
							/>
							<p class="description">
								<?php esc_html_e( 'Enviar notificaciones a los clientes cuando ganen o canjeen puntos.', 'lcter-wc-points-loyalty' ); ?>
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
	 * Add product points panel in product edit page.
	 */
	public function product_panel() {
		global $post;

		?>
		<div id="lcter_wcpl_product_panel" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'          => 'lcter_wcpl_product_points_rate',
						'label'       => __( 'Puntos por Unidad de Moneda', 'lcter-wc-points-loyalty' ),
						'description' => __( 'Dejar en blanco para usar la tasa por defecto.', 'lcter-wc-points-loyalty' ),
						'type'        => 'number',
						'custom_attributes' => array(
							'step' => '0.01',
							'min'  => '0',
						),
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product points configuration.
	 *
	 * @param int $product_id Product ID.
	 */
	public function save_product_points( $product_id ) {
		global $wpdb;

		// Validate nonce and capability.
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		$points_rate = isset( $_POST['lcter_wcpl_product_points_rate'] ) ? sanitize_text_field( wp_unslash( $_POST['lcter_wcpl_product_points_rate'] ) ) : '';

		if ( '' === $points_rate ) {
			// Delete custom rate if empty.
			$wpdb->delete(
				"{$wpdb->prefix}lcter_wcpl_product_points",
				array( 'product_id' => $product_id ),
				array( '%d' )
			);
		} else {
			$points_rate = (float) $points_rate;

			$wpdb->replace(
				"{$wpdb->prefix}lcter_wcpl_product_points",
				array(
					'product_id'         => $product_id,
					'points_per_currency' => $points_rate,
				),
				array( '%d', '%f' )
			);
		}
	}
}
