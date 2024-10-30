=== Katapult ===
Contributors: Katapult
Plugin Name: Katapult
Plugin URI: https://docs.katapult.com/docs/woocommerce
Tags: e-commerce, store, sales, sell, woo, shop, cart, checkout, payments, woo commerce
Donate link: https://katapult.com/
Author URI: https://katapult.com/
Author: Katapult
Requires at least: 5.5
Tested up to: 6.7
Stable tag: 1.1.7
Requires PHP: 7.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Katapult offers a lease-to-own payment solution for your business. Getting started with Katapult is quick and easy.

== Description ==

This project wraps the Katapult pre-approval/checkout online plugin.

Katapult is a payment method that uses Katapult's JS plugin, as described here - https://cdn.katapult.com/developer/plugin.html

== Installation ==

Installation

Step 1. Download the extension provided by your integration team
Step 2. Log in to your Wordpress admin portal
Step 3. Go to > plugins> Add New Button
Step 4. Select > Upload Plugin Button and upload the Katapult zipfile
Step 5. Once the installation is successful you will see the following message Plugin installed successfully. Select > Activate Plugin Button to begin configuration.

Configuring Katapult Payment Method

Step 1. Login to your wordpress admin portal Go to > WooCommerce > select Settings
Step 2. Select > Payments
Step 3. Find Katapult in your payment list > Manage
Step 4. Complete configuration for testing

When configuring Katapult, confirm that the environment is pointed towards sandbox for development and testing.

Enable Katapult is selected

Environment: https://sandbox.katapult.com/
Private API Key: tokens supplied by Katapult
Public API Key: tokens supplied by Katapult
Minimum Order Total: Value provided in your integration onboarding email
Maximum Order Total: $4500.00

= Minimum Requirements =

* PHP 7.2 or greater is recommended
* MySQL 5.6 or greater is recommended

== FAQ ==

Katapult is not reflecting as a payment option on my checkout page?

Verify you are checking out with a valid address in the continental United States of America and are using a state Katapult can transact in. Katapult is not accessible in NJ, MN, WI, WY.

At least 1 item in your cart is leasable. Katapult will not reflect as a payment option if the customer has exclusively non leasable items in their cart.

Confirm Katapult has been enabled as a payment option.

Where do I find my public and private tokens?

Your tokens will be supplied by our integration team, if you have not received your tokens email integration@katapult.com to retrieve them.

Cancel Order isn’t working?

Ensure that you have whitelisted .katapult.com to retrieve the UID from the orders.

== Screenshots ==

1. Login to your wordpress admin portal Go to > WooCommerce > select Settings screenshot-1.(png|jpg|jpeg|gif).
2. Katapult, confirm that the environment is pointed towards sandbox for development and testing. screenshot-2.(png|jpg|jpeg|gif).
3. Katapult order cancellation screenshot-3.(png|jpg|jpeg|gif).
4. Katapult items cancellation screenshot-4.(png|jpg|jpeg|gif).
5. Katapult mark order to complete status screenshot-5.(png|jpg|jpeg|gif).
6. Mark bulk items to leasable and non leasable screenshot-6.(png|jpg|jpeg|gif).
7. Mark single item leasable using product edit. screenshot-7.(png|jpg|jpeg|gif).

== Changelog ==

= 0.2 =
* Add option to mark items as leasable and non-leasable using bulk-option.

= 0.1 =
* Add default checkbox on leasable checkbox.

== Upgrade Notice ==

= 0.2 =
Upgrade to get options to mark items as leasable and non leasable.

= 0.1 =
This version fixes added default checkbox on leasable items.


== Other Notes ==

For more details on the Katapult Woocommerce plugin visit the Katapult Dev Portal. 

== Stats ==

Completed, Katapult will continue to make improvements and updates as new releases of Woocommerce are made available. 

== Admin ==

If you encounter any issues during the installation or module reach out to us at - integration@katapult.com
