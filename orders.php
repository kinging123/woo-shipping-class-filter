<?php
/**
 * This file contains the orders filter functionality, which is a bit hacky.
 * It contains duplicate code from the WooCommerce plugins, because WC do not have
 * sufficient hooks and filters for this functionality.
 */

// Copied from protected function `WC_Shipping_Flat_Rate::evaluate_cost()`
function rk_evaluate_cost( $shipping_method, $sum, $args = array() ) {
		// Add warning for subclasses.
		if ( ! is_array( $args ) || ! array_key_exists( 'qty', $args ) || ! array_key_exists( 'cost', $args ) ) {
			wc_doing_it_wrong( __FUNCTION__, '$args must contain `cost` and `qty` keys.', '4.0.1' );
		}

		include_once WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php';

		// Allow 3rd parties to process shipping cost arguments.
		$args           = apply_filters( 'woocommerce_evaluate_shipping_cost_args', $args, $sum, $shipping_method );
		$locale         = localeconv();
		$decimals       = array( wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ',' );

		// Expand shortcodes.
		add_shortcode( 'fee', array( $shipping_method, 'fee' ) );

		$sum = do_shortcode(
			str_replace(
				array(
					'[qty]',
					'[cost]',
				),
				array(
					$args['qty'],
					$args['cost'],
				),
				$sum
			)
		);

		remove_shortcode( 'fee', array( $shipping_method, 'fee' ) );

		// Remove whitespace from string.
		$sum = preg_replace( '/\s+/', '', $sum );

		// Remove locale from string.
		$sum = str_replace( $decimals, '.', $sum );

		// Trim invalid start/end characters.
		$sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

		// Do the math.
		return $sum ? WC_Eval_Math::evaluate( $sum ) : 0;
	}


/**
 * Get the relevant shipping classes for an order
 *
 * @param WC_Order $order
 * @return array<WP_Term>
 */
function rk_get_shipping_classes( WC_Order $order ) {

  defined( 'WC_ABSPATH' ) || exit;

  // Load cart functions which are loaded only on the front-end.
  include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
  include_once WC_ABSPATH . 'includes/class-wc-cart.php';

  if ( is_null( WC()->cart ) ) {
    wc_load_cart();
  }

  $active_classes = array();

  $packages = rk_get_shipping_packages($order);

  // This code is a modification of the code in WooCommerce's function `WC_Shipping_Flat_Rate::calculate_shipping()`
  // This is because WooCommerce does not have this functionality yet in their code.

  $shipping_classes = WC()->shipping()->get_shipping_classes();

  $shipping_methods = WC()->shipping->load_shipping_methods( $packages[0] );

  foreach ( $shipping_methods as $shipping_method ) {
    if ( ! $shipping_method->supports( 'shipping-zones' ) || $shipping_method->get_instance_id() && 'flat_rate' === $shipping_method->id ) {
      // Add shipping class costs.

      if ( ! empty( $shipping_classes ) ) {
        $found_shipping_classes = $shipping_method->find_shipping_classes( $packages[0] );
        $highest_class_cost     = 0;

        foreach ( $found_shipping_classes as $shipping_class => $products ) {
          // Also handles BW compatibility when slugs were used instead of ids.
          $shipping_class_term = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );
          $class_cost_string   = $shipping_class_term && $shipping_class_term->term_id ? $shipping_method->get_option( 'class_cost_' . $shipping_class_term->term_id, $shipping_method->get_option( 'class_cost_' . $shipping_class, '' ) ) : $shipping_method->get_option( 'no_class_cost', '' );
          
          $has_costs  = true;
          $class_cost = rk_evaluate_cost($shipping_method,
          $class_cost_string,
          array(
            'qty'  => array_sum( wp_list_pluck( $products, 'quantity' ) ),
            'cost' => array_sum( wp_list_pluck( $products, 'line_total' ) ),
            )
          );
          
          if ( 'class' === $shipping_method->type ) {
            $active_classes[] = $shipping_class_term;
          } else {
            if ($class_cost >= $highest_class_cost) {
              $highest_class_cost = $class_cost;
              $highest_class = $shipping_class_term;
            }
          }
        }

        if ( 'order' === $shipping_method->type ) {
          $active_classes[] = $highest_class;
        }
      }
    }
  }


  return $active_classes;
}

/**
 * Generate a virtual package based on the order specified
 *
 * @param WC_Order $order
 * @return array
 */
function rk_get_shipping_packages(WC_Order $order) {
  // Packages array for storing 'carts'
  $packages = array();
  $packages[0]['contents']                = array_map( function( WC_Order_Item $order_item ) {
    return array(
      'data' => $order_item->get_product(),
    );
  }, $order->get_items());
  $packages[0]['contents_cost']           = $order->get_total();
  $packages[0]['applied_coupons']         = $order->get_coupons();
  $packages[0]['destination']['country']  = $order->get_shipping_country();
  $packages[0]['destination']['state']    = $order->get_shipping_state();
  $packages[0]['destination']['postcode'] = $order->get_shipping_postcode();
  $packages[0]['destination']['city']     = $order->get_shipping_city();
  $packages[0]['destination']['address']  = $order->get_shipping_address_1();
  $packages[0]['destination']['address_2']= $order->get_shipping_address_2();


  return apply_filters('woocommerce_cart_shipping_packages', $packages);
}


add_action( 'restrict_manage_posts', 'rk_render_custom_orders_filters' );
/**
 * Render the select box filter for shipping classes
 *
 * @return void
 */
function rk_render_custom_orders_filters() {
	if ( ! isset( $_GET['post_type'] ) || 'shop_order' !== $_GET['post_type'] ) {
		return;
	}

	rk_render_shipping_class_filter('order');
}

/**
 *  Add Shipping Class Column to Products Table, using the same function as for the products
 */
add_filter( 'manage_edit-shop_order_columns', 'rk_product_add_shipping_class_column' );


add_action( 'manage_shop_order_posts_custom_column', 'rk_display_shipping_class_order_column', 10, 2 );
/**
 * Prints the shipping class(es) in the column
 *
 * @param string $column
 * @param int $post_id
 * @return void
 */
function rk_display_shipping_class_order_column( $column, $post_id ) {
  if ( 'shipping_class' !== $column ) {
    return;
  }

  $order = new WC_Order( $post_id );

  $shipping_methods = rk_get_shipping_classes( $order );
  if ( count( $shipping_methods ) && $shipping_methods[0] ) {
    echo implode( ', ',  wp_list_pluck( $shipping_methods, 'name' ) );
  } else {
    esc_html_e( 'No shipping class', 'woocommerce' );
  }

	return;
}



add_action( 'current_screen', 'rk_filter_orders_by_shipping_class', 99, 1 );
/**
 * Filters the orders table by the shipping class
 *
 * @param WP_Screen $screen
 * @return void
 */
function rk_filter_orders_by_shipping_class( $screen ) {
	if ( 'edit-shop_order' !== $screen->id ) {
		return;
  }

  if ( ! isset( $_GET['order_shipping_class'] ) || ! $_GET['order_shipping_class'] ) {
    return;
  }

  add_filter( 'pre_get_posts', function( WP_Query $query ) {
    if ( $query->is_main_query() ) {
      $query->set( 'posts_per_page', -1 );
    }
  } );
  
  add_filter( 'posts_results', function( $orders, $query ) {
    if ( ! $query->is_main_query() ) {
      return $orders;
    }

    $filtered_orders = array_filter( $orders, function( $order ) {
      return in_array( $_GET['order_shipping_class'], wp_list_pluck( rk_get_shipping_classes( new WC_Order( $order ) ), 'slug' ) );
    } );
    $query->found_posts = count($filtered_orders);

    return $filtered_orders;
  }, 1, 2 );
	
	return;
}