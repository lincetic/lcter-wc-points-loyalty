<?php
/**
 * WooCommerce classic checkout adapter for reward redemption.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Adapters;

use LCTER_WCPL\Services\Points_Service;
use LCTER_WCPL\Services\Purchase_Points_Calculator;
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
	const REWARD_STATE_CANCELLED   = 'reward_cancelled';
	const REWARD_STATE_REFUNDED    = 'reward_refunded';
	const REWARD_STATE_FAILED      = 'reward_failed';
	const REWARD_STATE_RESTORE_PENDING = 'reward_restore_pending';
	const REDEMPTION_STATUS_META   = '_lcter_wcpl_reward_redemption_status';
	const REDEMPTION_ERROR_META    = '_lcter_wcpl_reward_redemption_error';
	const REDEMPTION_ERROR_AT_META = '_lcter_wcpl_reward_redemption_error_at';

	private Reward_Redemption_Service $redemption;
	private Rewards_Service $rewards;
	private Points_Service $points;
	private Purchase_Points_Calculator $points_calculator;

	public function __construct(
		?Reward_Redemption_Service $redemption = null,
		?Rewards_Service $rewards = null,
		?Points_Service $points = null,
		?Purchase_Points_Calculator $points_calculator = null
	) {
		$this->redemption = $redemption ?? new Reward_Redemption_Service();
		$this->rewards    = $rewards ?? new Rewards_Service();
		$this->points     = $points ?? new Points_Service();
		$this->points_calculator = $points_calculator ?? new Purchase_Points_Calculator();
	}

	public function register_hooks(): void {
		add_action( 'woocommerce_after_order_notes', array( $this, 'render_reward_selector' ), 20, 1 );
		add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'render_earning_estimate' ), 10, 0 );
		add_action( 'woocommerce_review_order_before_order_total', array( $this, 'render_earning_estimate' ), 10, 0 );
		add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout_selection' ), 10, 0 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'force_reward_prices_to_zero' ), 20, 1 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_reward_cart_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_metadata' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'add_order_metadata' ), 10, 2 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'derive_reward_item_formatted_meta' ), 10, 2 );

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
		add_action( 'woocommerce_order_status_pending', array( $this, 'sync_reward_visual_state' ), 1, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'sync_reward_visual_state' ), 1, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'sync_reward_visual_state' ), 1, 1 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'sync_reward_visual_state' ), 1, 1 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'sync_reward_visual_state' ), 1, 1 );
	}

	/**
	 * Render the points estimate inside cart and classic-checkout totals tables.
	 */
	public function render_earning_estimate(): void {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		$points = $this->points_calculator->calculate(
			$cart->get_total( 'edit' ),
			$cart->get_shipping_total(),
			$cart->get_shipping_tax()
		);
		?>
		<tr class="lcter-wcpl-earned-points">
			<th><?php esc_html_e( 'Puntos que conseguirás', LCTER_WCPL_TEXT_DOMAIN ); ?></th>
			<td data-title="<?php echo esc_attr__( 'Puntos que conseguirás', LCTER_WCPL_TEXT_DOMAIN ); ?>">
				<strong><?php echo esc_html( number_format_i18n( $points ) ); ?> <?php esc_html_e( 'puntos', LCTER_WCPL_TEXT_DOMAIN ); ?></strong>
				<small><?php esc_html_e( 'Estos puntos se añadirán a tu saldo cuando el pedido haya sido pagado.', LCTER_WCPL_TEXT_DOMAIN ); ?></small>
			</td>
		</tr>
		<?php
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
		$balance           = $customer_id > 0 ? $this->points->get_balance( $customer_id ) : 0;
		$server_feedback    = WC()->session ? (string) WC()->session->get( 'lcter_wcpl_reward_feedback', '' ) : '';
		if ( '' !== $server_feedback && WC()->session ) {
			WC()->session->set( 'lcter_wcpl_reward_feedback', null );
		}

		?>
		<section
			class="lcter-wcpl-checkout-rewards"
			data-balance="<?php echo esc_attr( (string) $balance ); ?>"
			data-valid-message="<?php echo esc_attr__( 'Puedes canjear los regalos seleccionados.', LCTER_WCPL_TEXT_DOMAIN ); ?>"
			data-insufficient-message="<?php echo esc_attr__( 'No hay puntos suficientes para añadir otra unidad.', LCTER_WCPL_TEXT_DOMAIN ); ?>"
		>
			<noscript><style>.lcter-wcpl-reward-catalog[hidden]{display:block}</style></noscript>
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
				$rewards = $this->rewards->get_available_rewards();
				?>
				<?php if ( empty( $rewards ) ) : ?>
					<p><?php esc_html_e( 'No hay regalos disponibles en este momento.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
				<?php else : ?>
					<label>
						<input type="radio" name="lcter_wcpl_redemption_choice" value="redeem" <?php checked( 'redeem', $choice ); ?> />
						<?php esc_html_e( 'Quiero canjear puntos', LCTER_WCPL_TEXT_DOMAIN ); ?>
					</label>

					<div class="lcter-wcpl-reward-catalog" <?php echo 'redeem' === $choice ? '' : 'hidden'; ?>>
						<div class="lcter-wcpl-redemption-summary" aria-live="polite">
							<p><strong><?php esc_html_e( 'Saldo disponible:', LCTER_WCPL_TEXT_DOMAIN ); ?></strong> <span data-lcter-wcpl-balance><?php echo esc_html( number_format_i18n( $balance ) ); ?></span> <?php esc_html_e( 'puntos', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
							<p><strong><?php esc_html_e( 'Puntos seleccionados:', LCTER_WCPL_TEXT_DOMAIN ); ?></strong> <span data-lcter-wcpl-selected>0</span> <?php esc_html_e( 'puntos', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
							<p><strong><?php esc_html_e( 'Saldo restante:', LCTER_WCPL_TEXT_DOMAIN ); ?></strong> <span data-lcter-wcpl-remaining><?php echo esc_html( number_format_i18n( $balance ) ); ?></span> <?php esc_html_e( 'puntos', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
						</div>
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
							<label class="lcter-wcpl-reward-option" data-reward-id="<?php echo esc_attr( (string) $reward_id ); ?>" data-points-cost="<?php echo esc_attr( (string) $points_cost ); ?>">
								<span class="lcter-wcpl-reward-thumbnail">
									<?php echo $this->get_reward_thumbnail_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized by wp_kses_post(). ?>
								</span>
								<span class="lcter-wcpl-reward-details">
									<span class="lcter-wcpl-reward-name"><?php echo esc_html( $product->get_name() ); ?></span>
									<span class="lcter-wcpl-reward-cost">
									<?php
									printf(
										esc_html__( '%s puntos por unidad', LCTER_WCPL_TEXT_DOMAIN ),
										esc_html( number_format_i18n( $points_cost ) )
									);
									?>
									</span>
									<span id="lcter-wcpl-reward-availability-<?php echo esc_attr( (string) $reward_id ); ?>" class="lcter-wcpl-reward-availability" aria-live="polite"></span>
								</span>
								<input
									type="number"
									name="lcter_wcpl_reward_quantities[<?php echo esc_attr( (string) $reward_id ); ?>]"
									value="<?php echo esc_attr( (string) $quantity ); ?>"
									min="0"
									max="<?php echo esc_attr( (string) $max_quantity ); ?>"
									step="1"
									aria-describedby="lcter-wcpl-reward-availability-<?php echo esc_attr( (string) $reward_id ); ?>"
								/>
							</label>
						<?php endforeach; ?>
						</div>
						<p class="lcter-wcpl-reward-feedback<?php echo '' !== $server_feedback ? ' is-error' : ''; ?>" role="status" aria-live="assertive" tabindex="-1"><?php echo esc_html( $server_feedback ); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Build a compact product thumbnail with WooCommerce's native placeholder fallback.
	 *
	 * @param object $product WooCommerce product.
	 */
	private function get_reward_thumbnail_html( $product ): string {
		$image = $product->get_image(
			'woocommerce_thumbnail',
			array(
				'class'   => 'lcter-wcpl-reward-image',
				'alt'     => $product->get_name(),
				'loading' => 'lazy',
			),
			true
		);

		return wp_kses_post( $image );
	}

	public function process_checkout_selection(): void {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		$this->remove_reward_cart_items( $cart );

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->add_checkout_error( __( 'No se pudo validar la selección de regalos. Recarga el checkout e inténtalo de nuevo.', LCTER_WCPL_TEXT_DOMAIN ) );
			return;
		}

		$choice = $this->get_posted_choice();
		if ( 'none' === $choice ) {
			$cart->calculate_totals();
			return;
		}

		if ( 'redeem' !== $choice ) {
			$this->add_checkout_error( __( 'Selecciona si deseas canjear puntos.', LCTER_WCPL_TEXT_DOMAIN ) );
			return;
		}

		$quantities = $this->get_posted_quantities();
		if ( false === $quantities ) {
			$this->add_checkout_error( __( 'Las cantidades de los regalos no son válidas.', LCTER_WCPL_TEXT_DOMAIN ) );
			return;
		}

		$validation = $this->redemption->validate_selection(
			get_current_user_id(),
			$quantities,
			$this->get_eligible_subtotal_cents( $cart )
		);

		if ( ! $validation['valid'] ) {
			$this->add_checkout_error( $this->get_validation_message( $validation['error'] ) );
			return;
		}

		if ( ! $this->sync_reward_cart_items( $cart, $validation['items'] ) ) {
			$this->remove_reward_cart_items( $cart );
			$this->add_checkout_error( __( 'No se pudieron añadir los regalos seleccionados al pedido.', LCTER_WCPL_TEXT_DOMAIN ) );
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

	public function derive_reward_item_formatted_meta( array $formatted_meta, $item ): array {
		if ( ! is_object( $item ) || '1' !== (string) $item->get_meta( '_lcter_wcpl_is_reward' ) ) {
			return $formatted_meta;
		}

		$order_id = method_exists( $item, 'get_order_id' ) ? (int) $item->get_order_id() : 0;
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return $formatted_meta;
		}

		$label = self::get_reward_label_for_order( $order, $order->get_status() );
		foreach ( $formatted_meta as $meta ) {
			if ( is_object( $meta ) && isset( $meta->key ) && 'REGALO' === (string) $meta->key ) {
				$meta->value         = $label;
				$meta->display_value = esc_html( $label );
			}
		}

		return $formatted_meta;
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

	public function sync_reward_visual_state( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::sync_order_reward_visual_state( $order );
		}
	}

	public function process_paid_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() || 'completed' === $order->get_meta( '_lcter_wcpl_reward_redemption_status' ) ) {
			return;
		}

		if (
			WooCommerce_Orders_Adapter::order_has_pending_reversed_movements( $order ) ||
			WooCommerce_Orders_Adapter::LOYALTY_STATE_RESTORED === sanitize_key( (string) $order->get_meta( WooCommerce_Orders_Adapter::LOYALTY_MOVEMENTS_STATE_META ) )
		) {
			self::sync_order_reward_visual_state( $order );
			return;
		}

		$customer_id = (int) $order->get_customer_id();
		$items       = $this->get_reward_order_items( $order );
		if ( $customer_id <= 0 || empty( $items ) ) {
			return;
		}

		$cycle            = WooCommerce_Orders_Adapter::get_order_loyalty_cycle( $order );
		$already_redeemed = $this->redemption->is_order_redeemed( $order_id, $cycle );
		if ( ! $already_redeemed ) {
			$quantities = array();
			foreach ( $items as $reward_id => $item ) {
				$quantities[ $reward_id ] = (int) $item['quantity'];
			}

			$validation = $this->redemption->validate_selection(
				$customer_id,
				$quantities,
				(int) $order->get_meta( '_lcter_wcpl_redemption_eligible_subtotal' ),
				$this->redemption->get_order_earned_points( $order_id, $cycle )
			);

			if ( ! $validation['valid'] || ! $this->selection_matches_order( $validation['items'], $items ) ) {
				$this->reject_order_rewards( $order, $this->get_validation_message( $validation['error'] ?: 'catalog_changed' ) );
				return;
			}
		}

		$result = $this->redemption->redeem_paid_order( $customer_id, $order_id, $items, $cycle );
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
		$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_CYCLE_META, $cycle );
		$order->update_meta_data( self::REDEMPTION_STATUS_META, 'completed' );
		$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_MOVEMENTS_STATE_META, WooCommerce_Orders_Adapter::LOYALTY_STATE_APPLIED );
		$order->delete_meta_data( self::REDEMPTION_ERROR_META );
		$order->delete_meta_data( self::REDEMPTION_ERROR_AT_META );
		$order->add_order_note( __( 'Canje de puntos registrado correctamente.', LCTER_WCPL_TEXT_DOMAIN ) );
		self::sync_order_reward_visual_state( $order, false );
		$order->save();
	}

	public static function sync_order_reward_visual_state( $order, bool $save_order = true, ?string $status = null ): void {
		if ( ! $order ) {
			return;
		}

		$status = $status ?: $order->get_status();
		$state  = self::get_reward_state_for_order( $order, $status );
		$label  = self::get_reward_label_for_order( $order, $status );
		$has_rewards = false;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( '1' !== (string) $item->get_meta( '_lcter_wcpl_is_reward' ) ) {
				continue;
			}

			$has_rewards = true;
			$item->update_meta_data( '_lcter_wcpl_reward_state', $state );
			$item->update_meta_data( 'REGALO', $label );
			$item->save();
		}

		if ( $has_rewards ) {
			$order->update_meta_data( '_lcter_wcpl_reward_state', $state );
			if ( $save_order ) {
				$order->save();
			}
		}
	}

	public static function get_reward_state_for_order_status( string $status ): string {
		switch ( $status ) {
			case 'processing':
			case 'completed':
				return self::REWARD_STATE_REDEEMED;
			case 'cancelled':
				return self::REWARD_STATE_CANCELLED;
			case 'refunded':
				return self::REWARD_STATE_REFUNDED;
			case 'failed':
				return self::REWARD_STATE_FAILED;
			default:
				return self::REWARD_STATE_SELECTED;
		}
	}

	public static function get_reward_state_for_order( $order, string $status = '' ): string {
		$status = '' !== $status ? $status : ( $order && is_callable( array( $order, 'get_status' ) ) ? (string) $order->get_status() : '' );
		if ( in_array( $status, array( 'processing', 'completed' ), true ) && WooCommerce_Orders_Adapter::order_has_pending_reversed_movements( $order ) ) {
			return self::REWARD_STATE_RESTORE_PENDING;
		}

		return self::get_reward_state_for_order_status( $status );
	}

	public static function get_reward_label_for_order_status( string $status ): string {
		switch ( $status ) {
			case 'processing':
			case 'completed':
				return __( 'CANJEADO', LCTER_WCPL_TEXT_DOMAIN );
			case 'cancelled':
				return __( 'CANCELADO', LCTER_WCPL_TEXT_DOMAIN );
			case 'refunded':
				return __( 'REEMBOLSADO', LCTER_WCPL_TEXT_DOMAIN );
			case 'failed':
				return __( 'FALLIDO', LCTER_WCPL_TEXT_DOMAIN );
			default:
				return __( 'PENDIENTE DE PAGO', LCTER_WCPL_TEXT_DOMAIN );
		}
	}

	public static function get_reward_label_for_order( $order, string $status = '' ): string {
		$status = '' !== $status ? $status : ( $order && is_callable( array( $order, 'get_status' ) ) ? (string) $order->get_status() : '' );
		if ( in_array( $status, array( 'processing', 'completed' ), true ) && WooCommerce_Orders_Adapter::order_has_pending_reversed_movements( $order ) ) {
			return __( 'PENDIENTE DE RESTAURAR PUNTOS', LCTER_WCPL_TEXT_DOMAIN );
		}

		return self::get_reward_label_for_order_status( $status );
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
			if ( ! ctype_digit( (string) $reward_id ) || ! is_scalar( $quantity ) ) {
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
			'reward_unavailable'   => __( 'Uno de los regalos seleccionados ya no está disponible. Revisa tu selección.', LCTER_WCPL_TEXT_DOMAIN ),
			'invalid_cost'         => __( 'No se pudo validar el coste en puntos de los regalos.', LCTER_WCPL_TEXT_DOMAIN ),
			'empty_selection'      => __( 'Selecciona al menos una unidad de regalo o elige no canjear puntos.', LCTER_WCPL_TEXT_DOMAIN ),
			'insufficient_balance' => __( 'No tienes puntos suficientes para los regalos seleccionados.', LCTER_WCPL_TEXT_DOMAIN ),
			'catalog_changed'      => __( 'El catálogo de regalos cambió antes de completar el pago y el canje fue retirado del pedido.', LCTER_WCPL_TEXT_DOMAIN ),
		);

		return $messages[ $error ] ?? __( 'No se pudo validar el canje de regalos.', LCTER_WCPL_TEXT_DOMAIN );
	}

	/**
	 * Add the blocking WooCommerce notice and retain it for local feedback.
	 */
	private function add_checkout_error( string $message ): void {
		wc_add_notice( $message, 'error' );
		if ( WC()->session ) {
			WC()->session->set( 'lcter_wcpl_reward_feedback', $message );
		}
	}
}
