<?php
/** Resumable initial bonus administration. */
namespace LCTER_WCPL\Admin_UI;

use LCTER_WCPL\Repositories\Initial_Bonus_Runs_Repository;
use LCTER_WCPL\Services\Initial_Bonus_Batch_Service;
use LCTER_WCPL\Settings;
use LCTER_WCPL\Database;
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Initial_Bonus_Admin {
	public const NONCE       = 'lcter_wcpl_initial_bonus_batch';
	private static $instance = null;
	private Initial_Bonus_Runs_Repository $runs;
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		} return self::$instance; }
	private function __construct() {
		$this->runs = new Initial_Bonus_Runs_Repository();
		add_action( 'lcter_wcpl_dashboard_after_stats', array( $this, 'render_panel' ) );
		foreach ( array( 'preview', 'create', 'process', 'status', 'pause', 'resume' ) as $action ) {
			add_action( 'wp_ajax_lcter_wcpl_bonus_' . $action, array( $this, 'ajax_' . $action ) ); }
	}
	public function render_panel(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return; }
		$latest = $this->runs->latest_run(); ?>
		<div class="card lcter-wcpl-initial-bonus" id="lcter-wcpl-initial-bonus" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<h2><?php esc_html_e( 'Bonus inicial por lotes', LCTER_WCPL_TEXT_DOMAIN ); ?></h2>
		<p><?php esc_html_e( 'Solo incluye clientes con rol customer registrados antes de una fecha de corte fija. La vista previa no modifica datos.', LCTER_WCPL_TEXT_DOMAIN ); ?></p>
		<label><?php esc_html_e( 'Fecha y hora de corte', LCTER_WCPL_TEXT_DOMAIN ); ?> <input id="lcter-wcpl-bonus-cutoff" type="datetime-local" required /></label>
		<button type="button" class="button" id="lcter-wcpl-bonus-preview"><?php esc_html_e( 'Calcular vista previa', LCTER_WCPL_TEXT_DOMAIN ); ?></button>
		<div id="lcter-wcpl-bonus-preview-result" hidden></div>
		<p id="lcter-wcpl-bonus-confirm-wrap" hidden><label><input type="checkbox" id="lcter-wcpl-bonus-confirm" /> <?php esc_html_e( 'Confirmo la población, el corte y que deseo iniciar la ejecución manual.', LCTER_WCPL_TEXT_DOMAIN ); ?></label> <button type="button" class="button button-secondary" id="lcter-wcpl-bonus-start" disabled><?php esc_html_e( 'Preparar e iniciar', LCTER_WCPL_TEXT_DOMAIN ); ?></button></p>
		<div id="lcter-wcpl-bonus-progress" aria-live="polite"<?php echo $latest ? '' : ' hidden'; ?> data-run-id="<?php echo esc_attr( (string) ( $latest['id'] ?? 0 ) ); ?>"></div>
		<p><button type="button" class="button" id="lcter-wcpl-bonus-pause" hidden><?php esc_html_e( 'Pausar', LCTER_WCPL_TEXT_DOMAIN ); ?></button> <button type="button" class="button" id="lcter-wcpl-bonus-resume" hidden><?php esc_html_e( 'Reanudar', LCTER_WCPL_TEXT_DOMAIN ); ?></button></p>
		<?php
		foreach ( $this->runs->recent_runs() as $historical_run ) {
			$this->render_report( $historical_run ); }
		?>
		</div>
		<?php
	}
	private function authorize(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'No autorizado.' ), 403 );
		} check_ajax_referer( self::NONCE, 'nonce' ); }
	private function cutoff(): string {
		$value = isset( $_POST['cutoff'] ) ? sanitize_text_field( wp_unslash( $_POST['cutoff'] ) ) : '';
		$date  = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $value ) {
			wp_send_json_error( array( 'message' => 'Fecha de corte inválida.' ), 400 ); }
		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
	private function run_id(): int {
		return isset( $_POST['run_id'] ) ? absint( wp_unslash( $_POST['run_id'] ) ) : 0; }
	public function ajax_preview(): void {
		$this->authorize();
		if ( ! Database::initial_bonus_schema_is_safe() ) {
			wp_send_json_error( array( 'message' => 'El esquema del bonus inicial está incompleto o no usa las garantías requeridas.' ), 409 );
		}
		$cutoff = $this->cutoff();
		$points = Settings::get_initial_bonus_points();
		$run_id = $this->runs->create_run( get_current_user_id(), $cutoff, $points );
		if ( ! $run_id ) {
			wp_send_json_error( array( 'message' => 'Ya existe una ejecución activa o no se pudo preparar atómicamente la población.' ), 409 );
		}
		$run     = $this->runs->get_run( $run_id );
		$preview = $this->runs->run_preview( $run_id );
		wp_send_json_success(
			array_merge(
				$preview,
				array(
					'run_id'         => $run_id,
					'cutoff_at'      => $cutoff,
					'bonus_points'   => $points,
					'planned_points' => $preview['pending'] * $points,
					'confirmation_hash' => (string) ( $run['confirmation_hash'] ?? '' ),
				)
			)
		); }
	public function ajax_create(): void {
		$this->authorize();
		$confirmed         = isset( $_POST['confirmed'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmed'] ) ) : '';
		$confirmation_hash = isset( $_POST['confirmation_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation_hash'] ) ) : '';
		$run_id            = $this->run_id();
		if ( '1' !== $confirmed ) {
			wp_send_json_error( array( 'message' => 'Falta la confirmación expresa.' ), 400 ); }
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $confirmation_hash ) || ! $this->runs->confirm_run( $run_id, get_current_user_id(), $confirmation_hash ) ) {
			wp_send_json_error( array( 'message' => 'La confirmación no coincide con la vista previa preparada.' ), 409 );
		}
		wp_send_json_success( $this->runs->get_run( $run_id ) );
	}
	public function ajax_process(): void {
		$this->authorize();
		$token = isset( $_POST['request_token'] ) ? sanitize_key( wp_unslash( $_POST['request_token'] ) ) : '';
		if ( ! $token ) {
			wp_send_json_error( array( 'message' => 'Token de petición inválido.' ), 400 ); }
		try {
			wp_send_json_success( ( new Initial_Bonus_Batch_Service( $this->runs ) )->process( $this->run_id(), $token ) );
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 409 ); }
	}
	public function ajax_status(): void {
		$this->authorize();
		$run = $this->runs->get_run( $this->run_id() );
		if ( is_array( $run ) && 'prepared' === $run['status'] ) {
			$run = array_merge( $run, $this->runs->run_preview( (int) $run['id'] ) );
		}
		$run ? wp_send_json_success( $run ) : wp_send_json_error( array( 'message' => 'Ejecución inexistente.' ), 404 ); }
	public function ajax_pause(): void {
		$this->authorize();
		wp_send_json_success( array( 'updated' => $this->runs->set_status( $this->run_id(), 'paused' ) ) ); }
	public function ajax_resume(): void {
		$this->authorize();
		wp_send_json_success( array( 'updated' => $this->runs->set_status( $this->run_id(), 'running' ) ) ); }
	private function render_report( array $run ): void {
		$failures = $this->runs->failed_items( (int) $run['id'] );
		?>
		<details><summary><?php echo esc_html( sprintf( 'Último informe #%d (%s)', (int) $run['id'], (string) $run['status'] ) ); ?></summary><ul>
		<li><?php echo esc_html( 'Corte UTC: ' . $run['cutoff_at'] ); ?></li><li><?php echo esc_html( 'Objetivo: ' . (int) $run['total_targets'] ); ?></li>
		<li><?php echo esc_html( 'Procesados/bonificados/omitidos/fallidos: ' . (int) $run['processed_count'] . '/' . (int) $run['bonused_count'] . '/' . (int) $run['skipped_count'] . '/' . (int) $run['failed_count'] ); ?></li>
		<li><?php echo esc_html( 'Puntos reconciliados: ' . (int) $run['total_points'] ); ?></li><li><?php echo esc_html( 'Inicio/fin UTC: ' . ( $run['started_at'] ?: '-' ) . ' / ' . ( $run['finished_at'] ?: '-' ) ); ?></li>
		</ul>
		<?php
		if ( $failures ) {
			?>
			<h4><?php esc_html_e( 'Usuarios fallidos', LCTER_WCPL_TEXT_DOMAIN ); ?></h4><ul>
			<?php
			foreach ( $failures as $failure ) {
				?>
			<li><?php echo esc_html( sprintf( '#%d (%d intentos): %s', (int) $failure['customer_id'], (int) $failure['attempts'], (string) $failure['error_message'] ) ); ?></li><?php } ?></ul><?php } ?></details>
		<?php
	}
}
