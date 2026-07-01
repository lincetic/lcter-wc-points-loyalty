<?php
/**
 * Reward product administration UI.
 *
 * @package LCTER_WC_Points_Loyalty
 */

namespace LCTER_WCPL\Admin_UI;

use LCTER_WCPL\Services\Rewards_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the WooCommerce product fields used by the reward catalogue.
 */
class Reward_Product_Admin {

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
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_reward_product' ) );
	}

	/**
	 * Add the loyalty tab to WooCommerce product data.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function product_tab( array $tabs ): array {
		$tabs['lcter_wcpl_reward'] = array(
			'label'    => __( 'Puntos de Lealtad', LCTER_WCPL_TEXT_DOMAIN ),
			'target'   => 'lcter_wcpl_product_panel',
			'class'    => array(),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Render reward fields in the product edit screen.
	 */
	public function product_panel(): void {
		global $post;

		$reward = $post ? ( new Rewards_Service() )->get_reward_by_product( (int) $post->ID ) : null;
		?>
		<div id="lcter_wcpl_product_panel" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'lcter_wcpl_is_reward',
						'label'       => __( 'Regalo canjeable', LCTER_WCPL_TEXT_DOMAIN ),
						'description' => __( 'Activa la configuración de este producto como reward.', LCTER_WCPL_TEXT_DOMAIN ),
						'value'       => $reward ? 'yes' : 'no',
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'                => 'lcter_wcpl_reward_points_cost',
						'label'             => __( 'Coste del Regalo en Puntos', LCTER_WCPL_TEXT_DOMAIN ),
						'description'       => __( 'Introduce el coste manualmente. Referencia documentada: precio con IVA incluido × 2.000.', LCTER_WCPL_TEXT_DOMAIN ),
						'type'              => 'number',
						'value'             => $reward ? (int) $reward['points_cost'] : '',
						'wrapper_class'     => 'lcter-wcpl-reward-field',
						'custom_attributes' => array(
							'step' => '1',
							'min'  => '1',
						),
					)
				);
				woocommerce_wp_checkbox(
					array(
						'id'            => 'lcter_wcpl_reward_active',
						'label'         => __( 'Regalo activo', LCTER_WCPL_TEXT_DOMAIN ),
						'description'   => __( 'Permite mostrar el reward cuando también cumple sus fechas de disponibilidad.', LCTER_WCPL_TEXT_DOMAIN ),
						'value'         => ! $reward || ! empty( $reward['active'] ) ? 'yes' : 'no',
						'wrapper_class' => 'lcter-wcpl-reward-field',
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'                => 'lcter_wcpl_reward_sort_order',
						'label'             => __( 'Orden del Regalo', LCTER_WCPL_TEXT_DOMAIN ),
						'type'              => 'number',
						'value'             => $reward ? (int) $reward['sort_order'] : 0,
						'wrapper_class'     => 'lcter-wcpl-reward-field',
						'custom_attributes' => array( 'step' => '1' ),
					)
				);
				$this->render_datetime_field( 'lcter_wcpl_reward_starts_at', __( 'Disponible desde', LCTER_WCPL_TEXT_DOMAIN ), __( 'Opcional. Fecha y hora local de inicio de disponibilidad.', LCTER_WCPL_TEXT_DOMAIN ), $reward['starts_at'] ?? null );
				$this->render_datetime_field( 'lcter_wcpl_reward_ends_at', __( 'Disponible hasta', LCTER_WCPL_TEXT_DOMAIN ), __( 'Opcional. Fecha y hora local de fin de disponibilidad.', LCTER_WCPL_TEXT_DOMAIN ), $reward['ends_at'] ?? null );
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

		$product_id = absint( $product_id );
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		$service   = new Rewards_Service();
		$reward    = $service->get_reward_by_product( $product_id );
		$is_reward = isset( $_POST['lcter_wcpl_is_reward'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['lcter_wcpl_is_reward'] ) );

		if ( ! $is_reward ) {
			if ( $reward ) {
				$service->delete_reward( (int) $reward['id'] );
			}
			return;
		}

		$points_cost = isset( $_POST['lcter_wcpl_reward_points_cost'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['lcter_wcpl_reward_points_cost'] ) ) ) : 0;
		$active      = isset( $_POST['lcter_wcpl_reward_active'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['lcter_wcpl_reward_active'] ) ) ? 1 : 0;
		$sort_order  = isset( $_POST['lcter_wcpl_reward_sort_order'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['lcter_wcpl_reward_sort_order'] ) ) : 0;
		$starts_at   = $this->get_datetime_from_request( 'lcter_wcpl_reward_starts_at' );
		$ends_at     = $this->get_datetime_from_request( 'lcter_wcpl_reward_ends_at' );

		if ( $points_cost <= 0 || false === $starts_at || false === $ends_at || ( $starts_at && $ends_at && $starts_at > $ends_at ) ) {
			return;
		}

		$service->save_reward(
			array(
				'id'          => $reward ? (int) $reward['id'] : 0,
				'product_id'  => $product_id,
				'points_cost' => $points_cost,
				'active'      => $active,
				'sort_order'  => $sort_order,
				'starts_at'   => $starts_at,
				'ends_at'     => $ends_at,
			)
		);
	}

	/**
	 * Render one datetime-local product field.
	 */
	private function render_datetime_field( string $id, string $label, string $description, ?string $value ): void {
		woocommerce_wp_text_input(
			array(
				'id'                => $id,
				'label'             => $label,
				'description'       => $description,
				'type'              => 'datetime-local',
				'value'             => $this->format_datetime_for_input( $value ),
				'wrapper_class'     => 'lcter-wcpl-reward-field',
				'custom_attributes' => array( 'step' => '60' ),
			)
		);
	}

	/**
	 * Format a stored MySQL datetime for a datetime-local field.
	 */
	private function format_datetime_for_input( ?string $datetime ): string {
		if ( ! $datetime ) {
			return '';
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $datetime );

		return $date && $date->format( 'Y-m-d H:i:s' ) === $datetime ? $date->format( 'Y-m-d\TH:i' ) : '';
	}

	/**
	 * Read and validate one explicit datetime-local request field.
	 *
	 * @return string|false|null Canonical MySQL datetime, false if invalid, null if empty.
	 */
	private function get_datetime_from_request( string $field ) {
		if ( ! isset( $_POST[ $field ] ) ) {
			return null;
		}

		$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
		if ( '' === $value ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value );
		if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $value ) {
			return false;
		}

		return $date->format( 'Y-m-d H:i:s' );
	}
}
