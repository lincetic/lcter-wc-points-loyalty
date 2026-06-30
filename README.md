# LCTER WC Points Loyalty

WooCommerce loyalty points and configurable reward redemption.

## Overview

LCTER WC Points Loyalty awards points to registered customers after successful WooCommerce payments. Customers can redeem their existing balance for products configured as rewards during the classic checkout.

The plugin uses dedicated tables, atomic balance operations and database-backed idempotency. Version `0.1.0` is under active development.

## Requirements

* WordPress 6.5+
* PHP 8.1+
* WooCommerce 8.0+
* MySQL/MariaDB with transactional table support

## Implemented Features

* Points awarded only after an order is paid.
* One cent equals one point.
* VAT included and shipping plus shipping tax excluded from earnings.
* Configurable reward catalog with manual point costs and availability dates.
* Multiple rewards and multiple quantities in classic checkout.
* Zero-priced order items clearly marked as pending or redeemed rewards.
* Atomic balances with complete transaction history.
* Idempotent earning, redemption, initial bonus and cancellation operations.
* Reward traceability by order and customer.
* Manual 10,000-point initial bonus for WordPress users with the `customer` role.
* Earned-point reversal for cancelled and fully refunded orders.
* Administrative diagnostics and safe retry for recoverable order errors.
* Optional safe data removal during uninstall.

## Current Limitations

* Classic checkout only; Checkout Blocks and Store API are not supported.
* Guest orders do not earn or redeem points.
* Partial refunds are not implemented.
* Point expiration and manual balance adjustments are not implemented.
* Clientify payload preparation exists, but no external synchronization, REST API or webhooks are implemented.
* No automated retries, bulk recovery, emails or WP-CLI commands.

## Architecture

```text
Admin / Frontend / WooCommerce Adapters
                  |
             Services
                  |
            Repositories
                  |
          Plugin Database Tables
```

* `includes/repositories/` contains business SQL.
* `includes/services/` contains application and domain operations.
* `includes/adapters/` integrates WooCommerce hooks and objects.
* `includes/admin/` contains administrative UI and protected actions.

Compatibility facades remain available, but the deprecated immediate-redemption API safely returns `false` and has no side effects.

## Database

The plugin creates four tables using the WordPress database prefix:

* `lcter_wcpl_customer_points`
* `lcter_wcpl_transactions`
* `lcter_wcpl_rewards`
* `lcter_wcpl_order_rewards`

Customer balances are not stored in post meta. Order and order-item metadata are used only for WooCommerce visibility and operational traceability.

## Installation

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Ensure WooCommerce is active.
3. Activate LCTER WC Points Loyalty.
4. Configure reward products from the WooCommerce product editor.
5. Review the `Points Loyalty` administration menu.

The initial bonus is never executed automatically; it requires explicit confirmation by a user with `manage_woocommerce`.

## Development

Install development dependencies:

```bash
composer install
```

Run quality checks:

```bash
composer test
composer phpstan
composer phpcs
composer qa
```

Configuration is included for PHPUnit 10, PHPStan level 5 and WordPress Coding Standards. WooCommerce integration, concurrency and HPOS test coverage remain pending.

## Security

Administrative actions use capability checks and nonces. Inputs are validated and sanitized, output is escaped, and dynamic SQL values are prepared. Balance-changing operations use row locking and never permit a negative balance.

## Documentation

Detailed architecture, business rules, use cases, database design, testing guidance, technical decisions and open questions are available in [`docs/`](docs/).

## License

GPL-2.0-or-later
