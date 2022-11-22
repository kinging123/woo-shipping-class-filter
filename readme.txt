=== Woo Shipping Class Filter ===
Plugin Name: Woo Shipping Class Filter
Contributors: kinging
Tags: woocommerce, shipping-class, ecommerce, e-commerce, commerce, wordpress ecommerce
Author URI: http://reuven.rocks
Author: Reuven Karasik
Requires at least: 3
Requires PHP: 5.5
Tested up to: 6.1
Stable tag: trunk
Version: 2.0

A WooCommerce Extension that enabled filtering products by Shipping class in the admin panel.


== Description ==
This plugin adds two sections to the Products table in WooCommerce:

1. A new column for for each product, showing the shipping class defined for that product (or a proper message is there isn't one defined) 
2. A new filter box above the table, where you can choose to filter only the products that have a specific shipping class attached to them. *You can also filter all the products with no shipping class at all*, as a convenient way of making sure all your products have a shipping class set.

It also adds filtering to the Orders table in WooCommerce with similar capabilities. This feature considers each order to have one shipping class.


== Screenshots ==
1. The WooCommerce Products table, after installing the plugin (notice the new column on the right)
2. The new filter added to the WooCommerce Products table

== Changelog ==

2.0 - Added orders functionality, allowing you to filter orders by their shipping method

1.1 - Removed "no shipping class" option (not working in new WC) and changed the old woocommerce_product_filters hook to the new woocommerce_products_admin_list_table_filters

1.0 - First full version of this plugin
