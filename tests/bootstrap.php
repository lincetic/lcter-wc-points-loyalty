<?php
/**
 * PHPUnit bootstrap for service-level tests that do not require WordPress.
 */

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$root = dirname( __DIR__ );

require_once $root . '/includes/class-database.php';
require_once $root . '/includes/repositories/class-customer-points-repository.php';
require_once $root . '/includes/repositories/class-transactions-repository.php';
require_once $root . '/includes/repositories/class-transaction-manager.php';
require_once $root . '/includes/services/class-points-service.php';
require_once $root . '/includes/services/class-initial-bonus-service.php';
require_once $root . '/includes/services/class-order-cancellation-service.php';
