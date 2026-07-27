<?php
/**
 * Central reward catalogue administration.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Admin_UI;

use LCTER_WCPL\Services\Rewards_Service;
use LCTER_WCPL\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reward_Catalog_Admin {
	const ACTION = 'lcter_wcpl_reward_catalog';

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
	}

	public function handle_action(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para gestionar regalos.', LCTER_WCPL_TEXT_DOMAIN ) );
		}
		check_admin_referer( self::ACTION );

		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$ids       = isset( $_POST['reward_ids'] ) && is_array( $_POST['reward_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['reward_ids'] ) ) ) ) ) : array();
		$service   = new Rewards_Service();
		$changed   = 0;

		if ( 'save' === $operation ) {
			$ids = isset( $_POST['reward_id'] ) ? array( absint( wp_unslash( $_POST['reward_id'] ) ) ) : array();
		}

		foreach ( $ids as $reward_id ) {
			$reward = $service->get_reward( $reward_id );
			if ( ! $reward ) {
				continue;
			}
			$ok = false;
			switch ( $operation ) {
				case 'activate':
					$ok = $service->update_reward_flags( $reward_id, 1, null );
					break;
				case 'deactivate':
					$ok = $service->update_reward_flags( $reward_id, 0, 0 );
					break;
				case 'show':
					$ok = $service->update_reward_flags( $reward_id, null, 1 );
					break;
				case 'hide':
					$ok = $service->update_reward_flags( $reward_id, null, 0 );
					break;
				case 'delete':
					$confirmed = isset( $_POST['confirm_delete'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['confirm_delete'] ) );
					$ok        = $confirmed && $service->delete_reward( $reward_id );
					break;
				case 'save':
					$ok = $this->save_row( $service, $reward );
					break;
			}
			if ( $ok ) {
				++$changed;
				error_log( sprintf( 'LCTER WCPL reward admin=%d product=%d operation=%s before=%s after=%s', get_current_user_id(), (int) $reward['product_id'], $operation, wp_json_encode( $reward ), wp_json_encode( $service->get_reward( $reward_id ) ) ) );
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'lcter-wcpl-reward-catalog', 'changed' => $changed ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function save_row( Rewards_Service $service, array $reward ): bool {
		$points = isset( $_POST['points_cost'] ) ? absint( wp_unslash( $_POST['points_cost'] ) ) : 0;
		$order  = isset( $_POST['sort_order'] ) ? filter_var( wp_unslash( $_POST['sort_order'] ), FILTER_VALIDATE_INT ) : false;
		$start  = $this->datetime( 'starts_at' );
		$end    = $this->datetime( 'ends_at' );
		if ( $points <= 0 || false === $order || false === $start || false === $end || ( $start && $end && $start > $end ) ) {
			return false;
		}
		$active  = isset( $_POST['active'] ) ? 1 : 0;
		$visible = $active && isset( $_POST['visible_in_checkout'] ) ? 1 : 0;
		return 0 < $service->save_reward(
			array_merge(
				$reward,
				array(
					'points_cost'        => $points,
					'active'             => $active,
					'visible_in_checkout'=> $visible,
					'sort_order'         => (int) $order,
					'starts_at'          => $start,
					'ends_at'            => $end,
				)
			)
		);
	}

	private function datetime( string $key ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		if ( '' === $value ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value );
		return $date && $date->format( 'Y-m-d\TH:i' ) === $value ? $date->format( 'Y-m-d H:i:s' ) : false;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para gestionar regalos.', LCTER_WCPL_TEXT_DOMAIN ) );
		}
		$service   = new Rewards_Service();
		$all       = $service->get_all_rewards();
		$effective = $service->get_checkout_eligible_rewards();
		$limit     = Settings::get_max_checkout_rewards();
		$inside    = array_flip( array_map( 'intval', array_column( array_slice( $effective, 0, $limit ), 'id' ) ) );
		$eligible  = array_flip( array_map( 'intval', array_column( $effective, 'id' ) ) );
		$all       = self::filter_rows( $all, $inside, $eligible );
		$total     = count( $all );
		$per_page  = 20;
		$page      = max( 1, isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1 );
		$rows      = array_slice( $all, ( $page - 1 ) * $per_page, $per_page );
		$visible   = count( $effective );
		$pagination_base = self::get_pagination_base( $_GET );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Catálogo de regalos', LCTER_WCPL_TEXT_DOMAIN ); ?></h1>
			<?php if ( isset( $_GET['changed'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( sprintf( __( '%d configuraciones actualizadas.', LCTER_WCPL_TEXT_DOMAIN ), absint( wp_unslash( $_GET['changed'] ) ) ) ); ?></p></div><?php endif; ?>
			<div class="notice notice-info"><p><?php echo esc_html( sprintf( __( 'Configurados: %1$d · activos: %2$d · visibles y disponibles: %3$d · máximo en checkout: %4$d · aparecen ahora: %5$d · fuera del límite: %6$d.', LCTER_WCPL_TEXT_DOMAIN ), count( $service->get_all_rewards() ), count( array_filter( $service->get_all_rewards(), static fn( $r ) => ! empty( $r['active'] ) ) ), $visible, $limit, min( $visible, $limit ), max( 0, $visible - $limit ) ) ); ?></p></div>
			<form method="get"><input type="hidden" name="page" value="lcter-wcpl-reward-catalog" /><?php wp_nonce_field( self::ACTION, '_wpnonce', false ); ?>
				<p class="search-box"><input type="search" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' ); ?>" /><select name="status"><option value=""><?php esc_html_e( 'Todos los estados', LCTER_WCPL_TEXT_DOMAIN ); ?></option><?php foreach ( array( 'active'=>'Activos','inactive'=>'Inactivos','visible'=>'Visibles','hidden'=>'Ocultos','scheduled'=>'Programados','expired'=>'Caducados','inside'=>'Dentro del límite','outside'=>'Fuera del límite' ) as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '', $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select> <?php submit_button( __( 'Filtrar', LCTER_WCPL_TEXT_DOMAIN ), 'secondary', '', false ); ?></p>
			</form>
			<form id="lcter-wcpl-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" /><?php wp_nonce_field( self::ACTION ); ?>
				<div class="tablenav top"><select name="operation"><option value="activate">Activar como regalo</option><option value="deactivate">Desactivar como regalo</option><option value="show">Mostrar en checkout</option><option value="hide">Ocultar del checkout</option><option value="delete">Eliminar configuración</option></select> <label><input type="checkbox" name="confirm_delete" value="1" /> Confirmar eliminación</label> <?php submit_button( __( 'Aplicar', LCTER_WCPL_TEXT_DOMAIN ), 'secondary', '', false ); ?></div>
			</form>
				<table class="wp-list-table widefat fixed striped"><thead><tr><td class="check-column"></td><th>Imagen / producto</th><th>SKU / precio</th><th>Puntos</th><th>Activo</th><th>Visible</th><th>Orden</th><th>Inicio / fin</th><th>Disponibilidad</th><th>Acciones</th></tr></thead><tbody>
				<?php foreach ( $rows as $reward ) : self::render_row( $reward, isset( $inside[ (int) $reward['id'] ] ), isset( $eligible[ (int) $reward['id'] ] ) ); endforeach; ?>
				<?php if ( empty( $rows ) ) : ?><tr><td colspan="10"><?php esc_html_e( 'No se encontraron regalos.', LCTER_WCPL_TEXT_DOMAIN ); ?></td></tr><?php endif; ?>
				</tbody></table>
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => $pagination_base,
						'format'    => '',
						'total'     => max( 1, (int) ceil( $total / $per_page ) ),
						'current'   => $page,
						'prev_text' => __( '&laquo; Anterior', LCTER_WCPL_TEXT_DOMAIN ),
						'next_text' => __( 'Siguiente &raquo;', LCTER_WCPL_TEXT_DOMAIN ),
					)
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Build an admin query-string pagination base while preserving current filters.
	 *
	 * @param array $request Current query arguments.
	 * @return string Pagination URL containing the paginate_links placeholder.
	 */
	public static function get_pagination_base( array $request ): string {
		$query_args = array(
			'page' => 'lcter-wcpl-reward-catalog',
		);

		foreach ( $request as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || 'page' === $key || 'paged' === $key || ! is_scalar( $value ) ) {
				continue;
			}

			$query_args[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
		}

		$base = add_query_arg( $query_args, admin_url( 'admin.php' ) );
		$base = add_query_arg( 'paged', 999999999, $base );

		return str_replace( '999999999', '%#%', $base );
	}

	private static function filter_rows( array $rows, array $inside, array $eligible ): array {
		$search = isset( $_GET['s'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$now    = current_time( 'mysql' );
		return array_values( array_filter( $rows, static function ( $row ) use ( $search, $status, $inside, $eligible, $now ) {
			$product = wc_get_product( (int) $row['product_id'] );
			$haystack = strtolower( (string) $row['product_id'] . ' ' . ( $product ? $product->get_name() . ' ' . $product->get_sku() : '' ) );
			if ( '' !== $search && false === strpos( $haystack, $search ) ) return false;
			$id = (int) $row['id'];
			$tests = array(
				'active' => ! empty( $row['active'] ), 'inactive' => empty( $row['active'] ),
				'visible' => ! empty( $row['active'] ) && ! empty( $row['visible_in_checkout'] ),
				'hidden' => empty( $row['visible_in_checkout'] ), 'scheduled' => ! empty( $row['starts_at'] ) && $row['starts_at'] > $now,
				'expired' => ! empty( $row['ends_at'] ) && $row['ends_at'] < $now,
				'inside' => isset( $inside[ $id ] ), 'outside' => isset( $eligible[ $id ] ) && ! isset( $inside[ $id ] ),
			);
			return '' === $status || ! isset( $tests[ $status ] ) || $tests[ $status ];
		} ) );
	}

	private static function render_row( array $reward, bool $inside, bool $eligible ): void {
		$product = wc_get_product( (int) $reward['product_id'] );
		$status  = self::status_label( $reward, $product, $inside, $eligible );
		?>
		<tr><th class="check-column"><input form="lcter-wcpl-bulk-form" type="checkbox" name="reward_ids[]" value="<?php echo esc_attr( (string) $reward['id'] ); ?>" /></th>
		<td><?php echo $product ? wp_kses_post( $product->get_image( array( 40, 40 ) ) ) . ' ' . esc_html( $product->get_name() ) : esc_html__( 'Producto eliminado', LCTER_WCPL_TEXT_DOMAIN ); ?></td>
		<td><?php echo $product ? esc_html( $product->get_sku() ) . '<br>' . wp_kses_post( $product->get_price_html() ) : '—'; ?></td>
		<td><?php echo esc_html( number_format_i18n( (int) $reward['points_cost'] ) ); ?></td><td><?php echo ! empty( $reward['active'] ) ? 'Sí' : 'No'; ?></td><td><?php echo ! empty( $reward['visible_in_checkout'] ) ? 'Sí' : 'No'; ?></td><td><?php echo esc_html( (string) $reward['sort_order'] ); ?></td>
		<td><?php echo esc_html( ( $reward['starts_at'] ?: '—' ) . ' / ' . ( $reward['ends_at'] ?: '—' ) ); ?></td><td><strong><?php echo esc_html( $status ); ?></strong></td>
		<td><a href="<?php echo esc_url( get_edit_post_link( (int) $reward['product_id'] ) ); ?>">Editar producto</a><details><summary>Edición rápida</summary><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" /><input type="hidden" name="operation" value="save" /><input type="hidden" name="reward_id" value="<?php echo esc_attr( (string) $reward['id'] ); ?>" /><?php wp_nonce_field( self::ACTION ); ?><label><input type="checkbox" name="active" <?php checked( ! empty( $reward['active'] ) ); ?> /> Activo</label> <label><input type="checkbox" name="visible_in_checkout" <?php checked( ! empty( $reward['visible_in_checkout'] ) ); ?> /> Visible</label><br><input type="number" min="1" name="points_cost" value="<?php echo esc_attr( (string) $reward['points_cost'] ); ?>" /><input type="number" name="sort_order" value="<?php echo esc_attr( (string) $reward['sort_order'] ); ?>" /><input type="datetime-local" name="starts_at" value="<?php echo esc_attr( $reward['starts_at'] ? str_replace( ' ', 'T', substr( $reward['starts_at'], 0, 16 ) ) : '' ); ?>" /><input type="datetime-local" name="ends_at" value="<?php echo esc_attr( $reward['ends_at'] ? str_replace( ' ', 'T', substr( $reward['ends_at'], 0, 16 ) ) : '' ); ?>" /><?php submit_button( 'Guardar', 'small', '', false ); ?></form></details></td></tr>
		<?php
	}

	private static function status_label( array $reward, $product, bool $inside, bool $eligible ): string {
		$now = current_time( 'mysql' );
		if ( empty( $reward['active'] ) ) return 'Inactivo';
		if ( empty( $reward['visible_in_checkout'] ) ) return 'Oculto en checkout';
		if ( ! empty( $reward['starts_at'] ) && $reward['starts_at'] > $now ) return 'Programado';
		if ( ! empty( $reward['ends_at'] ) && $reward['ends_at'] < $now ) return 'Caducado';
		if ( ! $product || 'publish' !== $product->get_status() ) return 'Producto no publicado';
		if ( $product->managing_stock() && ! $product->is_in_stock() ) return 'Producto sin stock';
		if ( $eligible && ! $inside ) return 'Fuera del límite visible';
		return $inside ? 'Disponible' : 'No disponible';
	}
}
