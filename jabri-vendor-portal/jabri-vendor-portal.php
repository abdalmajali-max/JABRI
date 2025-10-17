<?php
if ( ! defined('ABSPATH') ) { exit; }
if ( ! defined('JVP_WC_ORDERS_ONLY') ) define('JVP_WC_ORDERS_ONLY', true);
/**
 * Plugin Name: Jabri Vendor Register Pro
 * Description: Vendor registration with license upload, inline validation, admin approval, and WooCommerce integration.
* Version: 4.0.0
 * Author: Jabri + AI
 * Text Domain: jabri-vendor-portal
 */
if ( ! defined('ABSPATH') ) { exit; }

define('JVP_VER', '4.0.0');
define('JVP_TD', 'jabri-vendor-portal');

/**
 * Runs on plugin activation.
 */
function jvp_activate_plugin() {
    // Add the 'vendor' role with basic read capabilities.
    add_role('vendor', __('Vendor', 'jabri-vendor-portal'), ['read' => true]);
    // Flush rewrite rules to register the new 'vendor-status' endpoint.
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'jvp_activate_plugin');

/**
 * Runs on plugin deactivation.
 */
function jvp_deactivate_plugin() {
    // Flush rewrite rules to remove the endpoint.
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'jvp_deactivate_plugin');


// Includes: Load all plugin components.
require_once __DIR__ . '/includes/shortcodes.php';
require_once __DIR__ . '/includes/vendor-admin.php';
require_once __DIR__ . '/includes/shipping.php';
require_once __DIR__ . '/includes/columns.php';
require_once __DIR__ . '/includes/wc-sync.php';
require_once __DIR__ . '/includes/notify.php';
require_once __DIR__ . '/includes/phone-verify.php';
if ( class_exists('WooCommerce') ) { if ( get_option('jvp_mode','orders') !== 'orders' && ! JVP_WC_ORDERS_ONLY ) { require_once __DIR__ . '/includes/woocommerce-integration.php'; } } // We will move WC code here

/**
 * Register admin menus.
 */
add_action('admin_menu', function() {
    add_menu_page('Jabri Vendor', 'Jabri Vendor', 'manage_options', 'jvp-root', function() {
        echo '<div class="wrap"><h1>Jabri Vendor</h1><p>اختر القسم من القائمة اليسرى.</p></div>';
    }, 'dashicons-store', 56);
    add_submenu_page('jvp-root', 'Settings', 'Settings', 'manage_options', 'jvp-settings', 'jvp_settings_page_cb');
    add_submenu_page('jvp-root', 'Vendors', 'Vendors', 'list_users', 'jvp-vendors', 'jvp_render_vendors_page');
});

/**
 * Register plugin settings.
 */
add_action('admin_init', function() {
    register_setting('jvp_settings', 'jvp_success_message', ['type' => 'string', 'default' => 'تم استلام طلبك. جاري تفعيل حساب التاجر بعد التأكد من البيانات.']);
    register_setting('jvp_settings', 'jvp_only_free_shipping_vendor', ['type' => 'boolean', 'default' => true]);
    register_setting('jvp_settings', 'jvp_brand_logo_url', ['type' => 'string', 'default' => '']);
    // Wawp settings
    register_setting('jvp_settings','jvp_wawp_api_url',[ 'type'=>'string','default'=>'https://wawp.net/wp-json/awp/v1/send' ]);
    register_setting('jvp_settings', 'jvp_wawp_instance_id', ['type' => 'string', 'default' => '']);
    register_setting('jvp_settings', 'jvp_wawp_access_token', ['type' => 'string', 'default' => '']);
    register_setting('jvp_settings', 'jvp_registration_redirect', ['type' => 'string', 'default' => home_url('/vendor-register')]);
});

/**
 * Render the settings page.
 */
function jvp_settings_page_cb() {
    if (!current_user_can('manage_options')) return;
    require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>
    <div class="wrap"><h1>Jabri Vendor Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('jvp_settings'); require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>
            <h2>Brand</h2>
            <table class="form-table">
                <tr><th>Brand logo URL</th><td><input type="text" name="jvp_brand_logo_url" value="<?php echo esc_attr(get_option('jvp_brand_logo_url', '')); require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>" class="regular-text"></td></tr>
            </table>

            <h2>Registration</h2>
            <table class="form-table">
                <tr><th>After registration text</th><td><input type="text" name="jvp_success_message" value="<?php echo esc_attr(get_option('jvp_success_message')); require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>" class="regular-text"></td></tr>
                <tr><th>Registration Redirect URL</th><td><input type="text" name="jvp_registration_redirect" value="<?php echo esc_attr(get_option('jvp_registration_redirect', home_url('/vendor-register'))); require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>" class="regular-text"></td></tr>
            </table>

            <h2>Wawp API</h2>
            <table class="form-table">
                <tr><th>API URL</th><td><input type="text" name="jvp_wawp_api_url" value="<?php echo esc_attr(get_option('jvp_wawp_api_url', '')); require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>" class="regular-text"></td></tr>
                <tr><th>Instance ID</th><td><input type="text" name="jvp_wawp_instance_id" value="<?php echo esc_attr(get_option('jvp_wawp_instance_id', '')); require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>" class="regular-text"></td></tr>
                <tr><th>Access Token</th><td><input type="text" name="jvp_wawp_access_token" value="<?php echo esc_attr(get_option('jvp_wawp_access_token', '')); require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>" class="regular-text"></td></tr>
            </table>
            
            <h2>Shipping</h2>
            <table class="form-table">
                <tr><th>Show only Free Shipping for vendors</th><td><label><input type="checkbox" name="jvp_only_free_shipping_vendor" value="1" <?php checked(1, get_option('jvp_only_free_shipping_vendor', 1)); require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>> إخفاء كل طرق الشحن وإبقاء المجاني فقط للتاجر</label></td></tr>
            </table>

            <?php submit_button(); require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/compat-woocommerce-orders-only.php';
?>
        </form>
    </div>
    <?php
}

/**
 * Enqueue scripts and styles.
 */
add_action('wp_enqueue_scripts', function() {
    // Register verification script
    wp_register_script('jvp-verify', plugins_url('assets/jvp-verify.js', __FILE__), ['jquery'], JVP_VER, true);
    wp_localize_script('jvp-verify', 'JVPVARS', [
        'ajax'         => admin_url('admin-ajax.php'),
        'nonce_send'   => wp_create_nonce('jvp_phone_otp_nonce'),
        'nonce_verify' => wp_create_nonce('jvp_phone_verify_nonce'),
        'redirect'     => get_option('jvp_registration_redirect', home_url('/vendor-register')),
    ]);

    // Register frontend CSS
    wp_register_style('jvp-frontend', plugins_url('assets/jvp-frontend.css', __FILE__), [], JVP_VER);
});

add_action('admin_init', function(){
    register_setting('jvp_settings','jvp_default_country_code',[ 'type'=>'string','default'=>'+962' ]);
    register_setting('jvp_settings','jvp_vendor_register_url',[ 'type'=>'string','default'=>'' ]);
    register_setting('jvp_settings','jvp_vendor_login_url',[ 'type'=>'string','default'=>'' ]);
});



add_action('admin_init', function(){
    register_setting('jvp_settings','jvp_mode',[ 'type'=>'string','default'=>'orders' ]); // 'orders' or 'classic'
    register_setting('jvp_settings','jvp_default_country_code',[ 'type'=>'string','default'=>'+962' ]);
    register_setting('jvp_settings','jvp_vendor_register_url',[ 'type'=>'string','default'=>'' ]);
    register_setting('jvp_settings','jvp_vendor_login_url',[ 'type'=>'string','default'=>'' ]);
});
