<?php
/**
 * PHPUnit bootstrap for service-level tests that do not require WordPress.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'LCTER_WCPL_TEXT_DOMAIN', 'lcter-wc-points-loyalty' );

$root = dirname( __DIR__ );

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Capture action registrations during isolated unit tests.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Hook priority.
	 * @param int    $accepted_args Accepted args.
	 */
	function add_action( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['lcter_wcpl_test_actions'][] = array(
			'hook_name'     => $hook_name,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Capture filter registrations during isolated unit tests.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Hook priority.
	 * @param int    $accepted_args Accepted args.
	 */
	function add_filter( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['lcter_wcpl_test_filters'][] = array(
			'hook_name'     => $hook_name,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		return $GLOBALS['lcter_wcpl_test_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( int $product_id ) {
		return $GLOBALS['lcter_wcpl_test_products'][ $product_id ] ?? false;
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	/**
	 * Return a deterministic WC_Order test double by ID.
	 */
	function wc_get_order( int $order_id ) {
		return $GLOBALS['lcter_wcpl_test_orders'][ $order_id ] ?? false;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Return a deterministic admin URL during isolated tests.
	 */
	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Minimal add_query_arg replacement for isolated URL tests.
	 *
	 * @param mixed  $key Query key or key/value map.
	 * @param mixed  $value Query value or URL.
	 * @param string $url Target URL.
	 */
	function add_query_arg( $key, $value = '', string $url = '' ): string {
		$args = is_array( $key ) ? $key : array( (string) $key => $value );
		if ( is_array( $key ) ) {
			$url = (string) $value;
		}
		$parts = parse_url( $url );
		$query = array();
		parse_str( $parts['query'] ?? '', $query );
		$query = array_merge( $query, $args );
		$base  = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? 'example.test' ) . ( $parts['path'] ?? '' );
		return $base . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $key ) ) ?? '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $value ): string {
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Return untranslated test text.
	 */
	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return $text;
	}
}

require_once $root . '/includes/class-database.php';
require_once $root . '/includes/class-settings.php';
require_once $root . '/includes/class-hpos-compatibility.php';
require_once $root . '/includes/repositories/class-customer-points-repository.php';
require_once $root . '/includes/repositories/class-transactions-repository.php';
require_once $root . '/includes/repositories/class-transaction-manager.php';
require_once $root . '/includes/repositories/class-rewards-repository.php';
require_once $root . '/includes/repositories/class-order-rewards-repository.php';
require_once $root . '/includes/services/class-points-service.php';
require_once $root . '/includes/services/class-rewards-service.php';
require_once $root . '/includes/services/class-reward-redemption-service.php';
require_once $root . '/includes/services/class-initial-bonus-service.php';
require_once $root . '/includes/services/class-order-cancellation-service.php';
require_once $root . '/includes/services/class-loyalty-movements-restoration-service.php';
require_once $root . '/includes/adapters/class-woocommerce-checkout-adapter.php';
require_once $root . '/includes/adapters/class-woocommerce-orders-adapter.php';
require_once $root . '/includes/admin/class-reward-catalog-admin.php';
