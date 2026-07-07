<?php
/**
 * WooCommerce classic checkout adapter for reward redemption.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Adapters;

use LCTER_WCPL\Services\Points_Service;
use LCTER_WCPL\Services\Reward_Redemption_Service;
use LCTER_WCPL\Services\Rewards_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerce_Checkout_Adapter {
	const NONCE_ACTION             = 'lcter_wcpl_checkout_rewards';
	const NONCE_NAME               = 'lcter_wcpl_rewards_nonce';
	const REWARD_STATE_SELECTED    = 'reward_selected';
	const REWARD_STATE_REDEEMED    = 'reward_redeemed';
	const REDEMPTION_STATUS_META   = '_lcter_wcpl_reward_redemption_status';
	const REDEMPTION_ERROR_META    = '_lcter_wcpl_reward_redemption_error';
	const REDEMPTION_ERROR_AT_META = '_lcter_wcpl_reward_redemption_error_at';

	private Reward_Redemption_Service $redemption;
	private Rewards_Service $rewards;
	private Points_Service $points;

	public function __construct(
		?Reward_Redemption_Service $redemption = null,
		?Rewards_Service $rewards = null,
		?Points_Service $points = null
	) {
		$this->redemption = $redemption ?? new Reward_Redemption_Service();
		$this->rewards    = $rewards ?? new Rewards_Service();
		$this->points     = $points ?? new Points_Service();
	}

	public function register_hooks(): void {
		add_action( 'woocommerce_after_order_notes', array( $this, 'render_reward_selector' ), 20, 1 );
		add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout_selection' ), 10, 0 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'force_reward_prices_to_zero' ), 20, 1 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_reward_cart_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_metadata' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'add_order_metadata' ), 10, 2 );

		// Redemption must run before WooCommerce transactional emails and before points are awarded at priority 10.
		add_action( 'woocommerce_payment_complete', array( $this, 'process_paid_order' ), 1, 1 );
		add_action( 'woocommerce_order_payment_status_changed', array( $this, 'process_paid_order' ), 1, 1 );
		add_action( 'woocommerce_order_status_pending_to_processing', array( $this, 'process_paid_order' ), 1, 1 );
		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'process_paid_order' ), 1, 1 );
		add_action( 'woocommerce_order_status_failed_to_processing', array( $this, 'process_paid_order' ), 1, 1 );
		add_action( 'woocommerce_order_status_pending_to_completed', array( $this, 'process_paid_order' ), 1, 1 );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'process_paid_order' ), 1, 1 );
		add_action( 'woocommerce_order_status_failed_to_completed', array( $this, 'process_paid_order' ), 1, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'process_paid_order' ), 1, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'process_paid_order' ), 1, 1 );
	}

	public function render_reward_selector(): void {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		$customer_id       = get_current_user_id();
		$eligible_subtotal = $this->get_eligible_subtotal_cents( $cart );
		$choice            = $this->get_posted_choice();
		$posted_quantities = $this->get_posted_quantities();
		$posted_quantities = is_array( $posted_quantities ) ? $posted_quantities : array();

		?>
		<section class="lcter-wcpl-checkout-rewards">
			<h3><?php esc_html_e( 'Canjear puntos por regalos', LCTER_WCPL_TEXT_DOMAIN ); ?></h3>
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

			<label>
				<input type="radio" name="lcter_wcpl_redemption_choice" value="none" <?php checked( 'none', $choice ); ?> />
				<?php esc_html_e( 'No canjear mis puntos', LCTER_WCPL_TEXT_DOMAIN ); ?>
			</label>

			<?php if ( $customer_id <= 0 ) : ?>
				<p><?php esc_html_e( 'Inicia sesión para canjear puntos.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
			<?php elseif ( $eligible_subtotal < Reward_Redemption_Service::MINIMUM_SUBTOTAL_CENTS ) : ?>
				<p><?php esc_html_e( 'El subtotal mínimo para canjear regalos es de 60 EUR, IVA incluido y sin portes.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
			<?php else : ?>
				<?php
				$balance = $this->points->get_balance( $customer_id );
				$rewards = $this->rewards->get_available_rewards();
				?>
				<p>
					<?php
					printf(
						esc_html__( 'Saldo disponible: %s puntos.', LCTER_WCPL_TEXT_DOMAIN ),
						esc_html( number_format_i18n( $balance ) )
					);
					?>
				</p>

				<?php if ( empty( $rewards ) ) : ?>
					<p><?php esc_html_e( 'No hay regalos disponibles en este momento.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
				<?php else : ?>
					<label>
						<input type="radio" name="lcter_wcpl_redemption_choice" value="redeem" <?php checked( 'redeem', $choice ); ?> />
						<?php esc_html_e( 'Quiero canjear puntos', LCTER_WCPL_TEXT_DOMAIN ); ?>
					</label>

					<div class="lcter-wcpl-reward-options">
						<?php foreach ( $rewards as $reward ) : ?>
							<?php
							$product = wc_get_product( (int) $reward['product_id'] );
							if ( ! $product ) {
								continue;
							}

							$reward_id    = (int) $reward['id'];
							$points_cost  = (int) $reward['points_cost'];
							$max_quantity = $points_cost > 0 ? intdiv( $balance, $points_cost ) : 0;
							$quantity     = $posted_quantities[ $reward_id ] ?? 0;
							?>
							<label class="lcter-wcpl-reward-option">
								<span><?php echo esc_html( $product->get_name() ); ?></span>
								<span>
									<?php
									printf(
										esc_html__( '%s puntos por unidad', LCTER_WCPL_TEXT_DOMAIN ),
										esc_html( number_format_i18n( $points_cost ) )
									);
									?>
								</span>
								<input
									type="number"
									name="lcter_wcpl_reward_quantities[<?php echo esc_attr( (string) $reward_id ); ?>]"
									value="<?php echo esc_attr( (string) $quantity ); ?>"
									min="0"
									max="<?php echo esc_attr( (string) $max_quantity ); ?>"
									step="1"
								/>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	public function process_checkout_selection(): void {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		$this->remove_reward_cart_items( $cart );

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wc_add_notice( __( 'No se pudo validar la selección de regalos. Recarga el checkout e inténtalo de nuevo.', LCTER_WCPL_TEXT_DOMAIN ), 'error' );
			return;
		}

		$choice = $this->get_posted_choice();
		if ( 'none' === $choice ) {
			$cart->calculate_totals();
			return;
		}

		if ( 'redeem' !== $choice ) {
			wc_add_notice( __( 'Selecciona si deseas canjear puntos.', LCTER_WCPL_TEXT_DOMAIN ), 'error' );
			return;
		}

		$quantities = $this->get_posted_quantities();
		if ( false === $quantities ) {
			wc_add_notice( __( 'Las cantidades de los regalos no son válidas.', LCTER_WCPL_TEXT_DOMAIN ), 'error' );
			return;
		}

		$validation = $this->redemption->validate_selection(
			get_current_user_id(),
			$quantities,
			$this->get_eligible_subtotal_cents( $cart )
		);

		if ( ! $validation['valid'] ) {
			wc_add_notice( $this->get_validation_message( $validation['error'] ), 'error' );
			return;
		}

		if ( ! $this->sync_reward_cart_items( $cart, $validation['items'] ) ) {
			$this->remove_reward_cart_items( $cart );
			wc_add_notice( __( 'No se pudieron añadir los regalos seleccionados al pedido.', LCTER_WCPL_TEXT_DOMAIN ), 'error' );
			return;
		}

		$cart->calculate_totals();
	}

	public function force_reward_prices_to_zero( $cart ): void {
		if ( ! $cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['_lcter_wcpl_is_reward'] ) && isset( $cart_item['data'] ) ) {
				$cart_item['data']->set_price( 0 );
			}
		}
	}

	public function display_reward_cart_data( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item['_lcter_wcpl_is_reward'] ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'key'   => __( 'REGALO', LCTER_WCPL_TEXT_DOMAIN ),
			'value' => sprintf(
				__( '%s puntos', LCTER_WCPL_TEXT_DOMAIN ),
				number_format_i18n( (int) $cart_item['_lcter_wcpl_points_cost_total'] )
			),
		);

		return $item_data;
	}

	public function add_order_item_metadata( $item, $cart_item_key, array $values, $order ): void {
		unset( $cart_item_key, $order );

		if ( empty( $values['_lcter_wcpl_is_reward'] ) ) {
			return;
		}

		$item->set_subtotal( 0 );
		$item->set_total( 0 );
		$item->set_subtotal_tax( 0 );
		$item->set_total_tax( 0 );
		$item->set_taxes(
			array(
				'subtotal' => array(),
				'total'    => array(),
			)
		);
		$item->add_meta_data( 'REGALO', __( 'PENDIENTE DE PAGO', LCTER_WCPL_TEXT_DOMAIN ), true );
		$item->add_meta_data( '_lcter_wcpl_is_reward', '1', true );
		$item->add_meta_data( '_lcter_wcpl_reward_state', self::REWARD_STATE_SELECTED, true );
		$item->add_meta_data( '_lcter_wcpl_reward_id', (int) $values['_lcter_wcpl_reward_id'], true );
		$item->add_meta_data( '_lcter_wcpl_points_cost_each', (int) $values['_lcter_wcpl_points_cost_each'], true );
		$item->add_meta_data( '_lcter_wcpl_points_cost_total', (int) $values['_lcter_wcpl_points_cost_total'], true );
	}

	public function add_order_metadata( $order, array $data ): void {
		unset( $data );

		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		$selection    = array();
		$total_points = 0;
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['_lcter_wcpl_is_reward'] ) ) {
				continue;
			}

			$reward_id               = (int) $cart_item['_lcter_wcpl_reward_id'];
			$selection[ $reward_id ] = array(
				'reward_id'         => $reward_id,
				'product_id'        => (int) $cart_item['product_id'],
				'quantity'          => (int) $cart_item['quantity'],
				'points_cost_each'  => (int) $cart_item['_lcter_wcpl_points_cost_each'],
				'points_cost_total' => (int) $cart_item['_lcter_wcpl_points_cost_total'],
			);
			$total_points           += (int) $cart_item['_lcter_wcpl_points_cost_total'];
		}

		if ( empty( $selection ) ) {
			return;
		}

		$order->update_meta_data( '_lcter_wcpl_has_rewards', '1' );
		$order->update_meta_data( '_lcter_wcpl_reward_state', self::REWARD_STATE_SELECTED );
		$order->update_meta_data( '_lcter_wcpl_reward_selection', array_values( $selection ) );
		$order->update_meta_data( '_lcter_wcpl_reward_points_total', $total_points );
		$order->update_meta_data( '_lcter_wcpl_redemption_eligible_subtotal', $this->get_eligible_subtotal_cents( $cart ) );
		$order->update_meta_data( '_lcter_wcpl_reward_redemption_status', 'pending_payment' );
	}

	public function process_paid_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() || 'completed' === $order->get_meta( '_lcter_wcpl_reward_redemption_status' ) ) {
			return;
		}

		$customer_id = (int) $order->get_customer_id();
		$items       = $this->get_reward_order_items( $order );
		if ( $customer_id <= 0 || empty( $items ) ) {
			return;
		}

		$already_redeemed = $this->redemption->is_order_redeemed( $order_id );
		if ( ! $already_redeemed ) {
			$quantities = array();
			foreach ( $items as $reward_id => $item ) {
				$quantities[ $reward_id ] = (int) $item['quantity'];
			}

			$validation = $this->redemption->validate_selection(
				$customer_id,
				$quantities,
				(int) $order->get_meta( '_lcter_wcpl_redemption_eligible_subtotal' ),
				$this->redemption->get_order_earned_points( $order_id )
			);

			if ( ! $validation['valid'] || ! $this->selection_matches_order( $validation['items'], $items ) ) {
				$this->reject_order_rewards( $order, $this->get_validation_message( $validation['error'] ?: 'catalog_changed' ) );
				return;
			}
		}

		$result = $this->redemption->redeem_paid_order( $customer_id, $order_id, $items );
		if ( ! $result['success'] ) {
			$order->update_meta_data( self::REDEMPTION_STATUS_META, 'processing_error' );
			$order->update_meta_data( self::REDEMPTION_ERROR_META, sanitize_key( (string) $result['error'] ) );
			$order->update_meta_data( self::REDEMPTION_ERROR_AT_META, current_time( 'mysql' ) );
			$order->add_order_note( __( 'El canje de regalos no pudo completarse y debe reintentarse antes de preparar el pedido.', LCTER_WCPL_TEXT_DOMAIN ) );
			$order->save();
			return;
		}

		$trace = array();
		foreach ( $items as $reward_id => $reward_item ) {
			$record_id  = (int) ( $result['record_ids'][ $reward_id ] ?? 0 );
			$order_item = $order->get_item( (int) $reward_item['order_item_id'] );
			if ( $order_item && $record_id > 0 ) {
				$order_item->update_meta_data( '_lcter_wcpl_order_reward_id', $record_id );
				$order_item->update_meta_data( '_lcter_wcpl_reward_state', self::REWARD_STATE_REDEEMED );
				$order_item->update_meta_data( 'REGALO', __( 'CANJEADO', LCTER_WCPL_TEXT_DOMAIN ) );
				$order_item->save();
			}

			$trace[] = array_merge(
				$reward_item,
				array(
					'order_reward_id' => $record_id,
					'label'           => 'REGALO CANJEADO',
				)
			);
		}

		$order->update_meta_data( '_lcter_wcpl_order_rewards', $trace );
		$order->update_meta_data( '_lcter_wcpl_reward_state', self::REWARD_STATE_REDEEMED );
		$order->update_meta_data( self::REDEMPTION_STATUS_META, 'completed' );
		$order->delete_meta_data( self::REDEMPTION_ERROR_META );
		$order->delete_meta_data( self::REDEMPTION_ERROR_AT_META );
		$order->add_order_note( __( 'Canje de puntos registrado correctamente.', LCTER_WCPL_TEXT_DOMAIN ) );
		$order->save();
	}

	private function get_eligible_subtotal_cents( $cart ): int {
		$subtotal = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();

		return max( 0, (int) round( $subtotal * 100 ) );
	}

	private function get_posted_choice(): string {
		if ( ! isset( $_POST['lcter_wcpl_redemption_choice'] ) ) {
			return 'none';
		}

		$choice = sanitize_text_field( wp_unslash( $_POST['lcter_wcpl_redemption_choice'] ) );

		return in_array( $choice, array( 'none', 'redeem' ), true ) ? $choice : '';
	}

	/**
	 * @return array|false
	 */
	private function get_posted_quantities() {
		if ( ! isset( $_POST['lcter_wcpl_reward_quantities'] ) ) {
			return array();
		}

		$raw = wp_unslash( $_POST['lcter_wcpl_reward_quantities'] );
		if ( ! is_array( $raw ) ) {
			return false;
		}

		$quantities = array();
		foreach ( $raw as $reward_id => $quantity ) {
			if ( ! is_scalar( $reward_id ) || ! ctype_digit( (string) $reward_id ) || ! is_scalar( $quantity ) ) {
				return false;
			}

			$quantity = filter_var( $quantity, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
			if ( false === $quantity ) {
				return false;
			}

			$quantities[ (int) $reward_id ] = $quantity;
		}

		return $quantities;
	}

	private function sync_reward_cart_items( $cart, array $items ): bool {
		foreach ( $items as $reward_id => $item ) {
			$product = wc_get_product( (int) $item['product_id'] );
			if ( ! $product || ! $product->is_purchasable() ) {
				return false;
			}

			$cart_item_key = $cart->add_to_cart(
				(int) $item['product_id'],
				(int) $item['quantity'],
				0,
				array(),
				array(
					'_lcter_wcpl_is_reward'         => '1',
					'_lcter_wcpl_reward_id'         => (int) $reward_id,
					'_lcter_wcpl_points_cost_each'  => (int) $item['points_cost_each'],
					'_lcter_wcpl_points_cost_total' => (int) $item['points_cost_total'],
				)
			);

			if ( ! $cart_item_key ) {
				return false;
			}
		}

		return true;
	}

	private function remove_reward_cart_items( $cart ): void {
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item['_lcter_wcpl_is_reward'] ) ) {
				$cart->remove_cart_item( $cart_item_key );
			}
		}
	}

	private function get_reward_order_items( $order ): array {
		$items = array();
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			if ( '1' !== (string) $item->get_meta( '_lcter_wcpl_is_reward' ) ) {
				continue;
			}

			$reward_id           = (int) $item->get_meta( '_lcter_wcpl_reward_id' );
			$product             = $item->get_product();
			$items[ $reward_id ] = array(
				'reward_id'         => $reward_id,
				'product_id'        => (int) $item->get_product_id(),
				'order_item_id'     => (int) $item_id,
				'quantity'          => (int) $item->get_quantity(),
				'points_cost_each'  => (int) $item->get_meta( '_lcter_wcpl_points_cost_each' ),
				'points_cost_total' => (int) $item->get_meta( '_lcter_wcpl_points_cost_total' ),
				'product_name'      => $item->get_name(),
				'sku'               => $product ? $product->get_sku() : '',
			);
		}

		return $items;
	}

	private function selection_matches_order( array $validated, array $order_items ): bool {
		if ( count( $validated ) !== count( $order_items ) ) {
			return false;
		}

		foreach ( $validated as $reward_id => $item ) {
			if (
				! isset( $order_items[ $reward_id ] ) ||
				(int) $item['product_id'] !== (int) $order_items[ $reward_id ]['product_id'] ||
				(int) $item['quantity'] !== (int) $order_items[ $reward_id ]['quantity'] ||
				(int) $item['points_cost_each'] !== (int) $order_items[ $reward_id ]['points_cost_each'] ||
				(int) $item['points_cost_total'] !== (int) $order_items[ $reward_id ]['points_cost_total']
			) {
				return false;
			}
		}

		return true;
	}

	private function reject_order_rewards( $order, string $message ): void {
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			if ( '1' === (string) $item->get_meta( '_lcter_wcpl_is_reward' ) ) {
				$order->remove_item( $item_id );
			}
		}

		$order->update_meta_data( self::REDEMPTION_STATUS_META, 'rejected' );
		$order->update_meta_data( self::REDEMPTION_ERROR_META, $message );
		$order->update_meta_data( self::REDEMPTION_ERROR_AT_META, current_time( 'mysql' ) );
		$order->delete_meta_data( '_lcter_wcpl_reward_state' );
		$order->add_order_note( $message );
		$order->calculate_totals();
		$order->save();
	}

	private function get_validation_message( string $error ): string {
		$messages = array(
			'invalid_customer'     => __( 'Debes iniciar sesión para canjear puntos.', LCTER_WCPL_TEXT_DOMAIN ),
			'minimum_not_met'      => __( 'El subtotal mínimo para canjear regalos es de 60 EUR, IVA incluido y sin portes.', LCTER_WCPL_TEXT_DOMAIN ),
			'invalid_quantity'     => __( 'Las cantidades de los regalos no son válidas.', LCTER_WCPL_TEXT_DOMAIN ),
			'reward_unavailable'   => __( 'Uno de los regalos seleccionados ya no está disponible.', LCTER_WCPL_TEXT_DOMAIN ),
			'invalid_cost'         => __( 'No se pudo validar el coste en puntos de los regalos.', LCTER_WCPL_TEXT_DOMAIN ),
			'empty_selection'      => __( 'Selecciona al menos una unidad de regalo o elige no canjear puntos.', LCTER_WCPL_TEXT_DOMAIN ),
			'insufficient_balance' => __( 'No tienes puntos suficientes para los regalos seleccionados.', LCTER_WCPL_TEXT_DOMAIN ),
			'catalog_changed'      => __( 'El catálogo de regalos cambió antes de completar el pago y el canje fue retirado del pedido.', LCTER_WCPL_TEXT_DOMAIN ),
		);

		return $messages[ $error ] ?? __( 'No se pudo validar el canje de regalos.', LCTER_WCPL_TEXT_DOMAIN );
	}
}
