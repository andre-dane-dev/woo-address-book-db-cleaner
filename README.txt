=== DB Cleaner for WooCommerce Address Book ===
Contributors:
Tags: WooCommerce, woocommerce address book, database clean, bug
Requires at least: 6.0.1
Tested up to: 6.0.1
Stable tag: 1.0.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Short description ==

This plugin has the function of cleaning up the database following the WooCommerce
Address Book plugin bug, which created additional usermeta if the billing or
shipping address was disabled in checkout.
More info on the bug here: https://github.com/hallme/woo-address-book/issues/128#issuecomment-1199717624

== Description ==

This plugin has the function to clean up the database filled with additional and unnecessary billing user meta (e.g. billing2_first_name, billing3_first_name, billing4_first_name...) for each order placed, due to a bug in the WooCommerce Address Book plugin (https://wordpress.org/plugins/woo-address-book/) from version 2.0.0 up to version 2.1.2 (more information here: https://github.com/hallme/woo-address-book/issues/128#issuecomment-1199717624).
This bug consisted in generating additional billing user meta when billing address or shipping address was not enabled in checkout (to check, go to WooCommerce -> Settings -> General -> "Enable billing address book" & "Enable shipping address book" ).
Another issue was that by always saving the new addresses in the additional user meta, the original user meta was not updated, which is why users found the original user meta data in checkout each time.

I would like to point out that this bug has been fixed in version 2.1.3 of the plugin, so from now on new user meta will no longer be added and each user's address will be correctly updated; however, the database still contains all additional user meta, which in some cases may be a lot.

To perform database cleaning, simply go to the administration panel, click on Tools -> Available Tools -> DB Cleaner for WooCommerce Address Book -> Run DB cleaning.
This will execute the function that deletes all additional user meta and updates the original user meta with the values from the last user meta created.
Therefore, the best time to launch this function is just before upgrading to version 2.1.3 of the WooCommerce Address Book plugin, to bring things back to normal.

Use this plugin if and only if the WooCommerce Address Book plugin is installed on your site and your site has been affected by the bug.
You can find out if your site has been affected by the bug in 2 ways by checking your site's DataBase with this query:
"SELECT * FROM `wp_usermeta` WHERE meta_key LIKE 'billing2_%';" (make sure you write the table prefix correctly).

If you get at least one result, it means that your site is affected by the bug, and to find out how many additional user meta have been added over time you can repeat the query by always increasing the number after 'billing' by 1 (for example, "billing3_%", "billing4_%" ...).

ADVICE
Before using the plugin on a live site, be sure to save a backup of the database and test it on a dev or staging site first.
You run this at your own risk and I am not liable for any loss of data (see LICENSE.txt for more info).

== Installation ==

1. Download `woo-address-book-db-cleaner.zip` and upload it to the `/wp-content/plugins/` directory
OR Download `woo-address-book-db-cleaner.zip` and upload it directly in the admin panel of your site going to to the `Plugin -> Add new -> Upload plugin`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools -> Available tools -> DB Cleaner for WooCommerce Address Book

== CHANGELOG ==

v1.0.1
- Added affected users log & delete wc_address_book_billing meta
