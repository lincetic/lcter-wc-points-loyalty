# LCTER WC Points Loyalty

Professional WooCommerce loyalty plugin based on points and reward redemption.

---

## Overview

LCTER WC Points Loyalty allows WooCommerce stores to reward customers with loyalty points earned after successful purchases.

Customers can later redeem their points for configurable rewards directly during checkout.

The plugin has been designed following SOLID principles, WordPress Coding Standards and a clean separation between business logic and infrastructure.

---

## Main Features

* Loyalty points based on completed purchases.
* Reward catalog.
* Multiple reward redemption.
* Transaction history.
* Manual point adjustments.
* Initial bonus campaigns.
* Dedicated database layer.
* Clientify integration support.
* Secure uninstall.
* WooCommerce native integration.

---

## Architecture

The plugin follows a layered architecture.

```
Admin
        │
WooCommerce
        │
Business Services
        │
Repositories
        │
Database
```

Business rules are completely separated from persistence.

---

## Database

The plugin uses dedicated custom tables.

* customer_points
* transactions
* rewards
* order_rewards

No customer balance is stored in WordPress post meta.

---

## Project Documentation

Detailed documentation can be found in the `docs` directory.

* vision.md
* architecture.md
* business-rules.md
* use-cases.md
* database.md
* domain-model.md
* roadmap.md
* testing.md
* integrations.md
* technical-decisions.md
* open-questions.md

---

## Coding Standards

* PHP 8.1+
* WordPress Coding Standards
* PSR-12 where compatible
* SOLID principles
* Secure coding practices
* Database queries using `$wpdb->prepare()`

---

## Development

Recommended environment:

* Docker
* WP-CLI
* PHPStan
* PHPUnit

---

## Security

The plugin always uses:

* Nonces
* Capability checks
* Sanitization
* Escaping
* Prepared SQL statements

---

## Roadmap

Current development status:

* ✔ Plugin architecture
* ✔ Database design
* ✔ Documentation
* ⬜ Point accumulation
* ⬜ Reward redemption
* ⬜ Checkout integration
* ⬜ Administration panel
* ⬜ Clientify integration
* ⬜ Automated tests

---

## License

GPL-2.0-or-later
