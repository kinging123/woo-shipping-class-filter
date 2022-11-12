<?php
/**
 * Plugin Name: Woo Shipping Class Filter
 * Plugin URI: http://wordpress.org/plugins/woo-shipping-class-filter
 * Description: A WooCommerce Extension that enabled filtering products by Shipping class in the admin panel.
 * Version: 2.0
 * Author: Reuven Karasik
 * Author URI: http://reuven.karasik.org/
 *
 * @package Woo Shipping Class Filter
 * @author  Reuven Karasik
 * @since   1.0
 */

/**
 *  Add Shipping Class Column to Products Table
 */
add_filter( 'manage_product_posts_columns', 'rk_product_add_shipping_class_column' );
add_action( 'manage_product_posts_custom_column', 'rk_product_manage_shipping_class_column', 10, 2 );

/**
 * Add Shipping Class column to columns list
 *
 * @param array $post_columns An array of column names.
 * @since 1.0
 */
function rk_product_add_shipping_class_column( $post_columns ) {
	$post_columns['shipping_class'] = __( 'Shipping class', 'woocommerce' );
	return $post_columns;
}

add_action( 'admin_print_scripts', 'rk_print_shipping_class_column_style' );
/**
 * Print the CSS for Shipping Class column
 */
function rk_print_shipping_class_column_style() {
	?>
	<style>
		.fixed .column-shipping_class {
			width: 10%;
		}
	</style>
	<?php
}

/**
 * Display the Shipping Class in the column
 *
 * @param string $column_name The name of the column to display.
 * @param int    $post_id     The current post ID.
 * @since 1.0
 */
function rk_product_manage_shipping_class_column( $column_name, $post_id ) {
	if ( 'shipping_class' !== $column_name ) {
		return $column_name;
	}

	$product = new WC_Product( $post_id );
	$class_slug = $product->get_shipping_class();
	if ( $class_slug ) {
		$term = get_term_by( 'slug', $class_slug, 'product_shipping_class' );
		echo esc_html( $term->name );
		return true;
	}

	esc_html_e( 'No shipping class', 'woocommerce' );
	return true;
}

/**
 * Add the select box to filter row.
 */

add_filter( 'woocommerce_products_admin_list_table_filters', 'rk_add_shipping_class_filter_products' );
function rk_add_shipping_class_filter_products( $filters ) {
	$filters['shipping_class'] = 'rk_render_shipping_class_filter';
	return $filters;
}

/**
 * Echoes the shipping class filter select box (copied and modified from Woocommerce source code)
 */
function rk_render_shipping_class_filter($type = 'product') {
	global $woocommerce;
	$current_shipping_class = isset( $_REQUEST[$type . '_shipping_class'] ) ? wc_clean( wp_unslash( $_REQUEST[$type . '_shipping_class'] ) ) : false; // WPCS: input var ok, sanitization ok.
	$output = '<select name="' . $type . '_shipping_class"><option value="">' . esc_html__( 'Filter by', 'woocommerce' ) . ' ' . esc_html__( 'Shipping class', 'woocommerce' ) . '</option>';
	foreach ( $woocommerce->shipping->get_shipping_classes() as $shipping_class ) {
		$slug = $shipping_class->slug;
		$name = $shipping_class->name;
		$output .= '<option ' . selected( $slug, $current_shipping_class, false ) . ' value="' . esc_attr( $slug ) . '">' . esc_html( $name ) . '</option>';
	}
	$output .= '</select>';
	echo $output; // WPCS: XSS ok.
}

include( plugin_dir_path( __FILE__ ) . 'orders.php');