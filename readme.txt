=== LCTER WC Points Loyalty ===
Contributors: Eddie Rapallo
Donate link: https://lincetic.es/
Tags: woocommerce, loyalty, points, rewards, customer loyalty
Requires at least: 6.5
Requires PHP: 8.1
Tested up to: 6.9
Requires Plugins: woocommerce
WC requires at least: 8.0
WC tested up to: 10.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional WooCommerce loyalty points system with configurable reward catalog and customer point redemption.

== Description ==

LCTER WC Points Loyalty is a professional loyalty program for WooCommerce.

Customers earn points after successful purchases and redeem them for configurable rewards directly during checkout.

The plugin has been designed with scalability and performance in mind by using dedicated database tables instead of post meta.

== Features ==

* Automatic point accumulation after successful payment.
* 1 cent = 1 point.
* Points calculated from order total including VAT and excluding shipping.
* Configurable reward catalog.
* Multiple reward redemption.
* Protection against negative balances.
* Complete transaction history.
* Initial bonus campaigns.
* Manual point adjustments.
* Dedicated database tables.
* Clientify-ready reward tracking.
* Safe uninstall option.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/lcter-wc-points-loyalty`
2. Activate the plugin.
3. Make sure WooCommerce is active.
4. Configure the plugin from the admin menu.

== Requirements ==

* WordPress 6.5+
* PHP 8.1+
* WooCommerce 8.0+

== Database ==

The plugin creates the following tables:

* customer_points
* transactions
* rewards
* order_rewards

== Frequently Asked Questions ==

= When are points earned? =

Only after the order has been successfully paid.

= How are points calculated? =

One cent equals one point.

Points are calculated using the order total including VAT and excluding shipping.

= Can rewards be redeemed together with coupons? =

Yes.

The plugin is designed to work alongside WooCommerce promotions and coupons.

= Does the current order count towards available points? =

No.

Only the customer's current balance is considered.

= Are plugin data removed after uninstall? =

No.

Data are removed only if the administrator explicitly enables the "Delete plugin data on uninstall" option.

== Upgrade Notice ==

= 0.1.0 =

Initial development release.

== Changelog ==

= 0.1.0 =

* Initial development release.
