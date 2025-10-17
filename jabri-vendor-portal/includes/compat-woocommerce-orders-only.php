<?php
if ( ! defined('ABSPATH') ) { exit; }
if ( ! defined('ABSPATH') ) { exit; }

// Hide any vendor-specific menu items
if ( get_option('jvp_mode','orders') === 'orders' || JVP_WC_ORDERS_ONLY ) add_filter('woocommerce_account_menu_items', function($items){
    unset($items['vendor-status']);
    unset($items['vendor-orders']);
    return $items;
}, 99);

// Redirect legacy endpoints to Woo Orders
if ( get_option('jvp_mode','orders') === 'orders' || JVP_WC_ORDERS_ONLY ) add_action('template_redirect', function(){
    global $wp_query;
    if ( isset($wp_query->query_vars['vendor-status']) || isset($wp_query->query_vars['vendor-orders']) ) {
        $orders_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : home_url('/my-account/orders/');
        wp_safe_redirect($orders_url, 301);
        exit;
    }
}, 0);

// Vendor login redirect to native Orders
if ( get_option('jvp_mode','orders') === 'orders' || JVP_WC_ORDERS_ONLY ) add_filter('login_redirect', function($redirect_to, $request, $user){
    if ( $user instanceof WP_User && in_array('vendor', (array) $user->roles, true) ) {
        return function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : home_url('/my-account/orders/');
    }
    return $redirect_to;
}, 99, 3);
