=== Lamium Decentralized Crypto Payment Plugin ===

 - Contributors: Kryptolis AG
 - Tags: woocommerce, bitcoin, dash, payment gateway, gateway, manual payment
 - Requires at least: 3.8
 - Tested up to: 5.2.4
 - Requires WooCommerce at least: 2.1
 - Tested WooCommerce up to: 3.6.5
 - Stable Tag: 2.1.6
 - License: GPLv3
 - License URI: http://www.gnu.org/licenses/gpl-3.0.html



== Description ==

> **Requires: WooCommerce 2.1+**

This plugin integrates crypto currency payments into your webstore(Bitcoin and Dash).
The lamium API allows merchants to accept the bitcoin or dash directly to their wallet or convert them automatically into EUR, CHF or USD via our decentralized invoice service.

= More Details =
 - See the (https://www.lamium.io/) for full details and documentation

== Installation ==

1. Be sure you're running WooCommerce 2.1+ in your shop.
2. You can: (1) upload the entire `woocommerce-gateway-lamium-accept-crypto-api` folder to the `/wp-content/plugins/` directory, (2) upload the .zip file with the plugin under **Plugins &gt; Add New &gt; Upload**
3. Activate the plugin through the **Plugins** menu in WordPress
4. Go to **WooCommerce &gt; Settings &gt; Checkout** and select "Lamium accept bitcoin api " to configure

NOTE :Make sure Hold stock (for unpaid orders) for x minutes is set to null, otherwise by default woocommerce marks the pendging payment orders as cancelled after one hour"
You can change it here 
yourdomain/wp-admin/admin.php?page=wc-settings&tab=products&section=inventory

== Frequently Asked Questions ==

**Can I fork this?**
Please do! This is meant to be a simple starter offline gateway, and can be modified easily.
