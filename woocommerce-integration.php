<?php
if ( ! defined('ABSPATH') ) { exit; }
if ( get_option('jvp_mode','orders') === 'orders' || JVP_WC_ORDERS_ONLY ) return; ?>
<?php
if ( ! defined('ABSPATH') ) { exit; }
if ( ! defined('ABSPATH') ) { exit; }

/**
 * =================================================================
 * WooCommerce My Account Integration: Vendor Status Tab
 * =================================================================
 */
if ( ! function_exists('jvp_has_wc') ) {
    function jvp_has_wc() {
        return class_exists('WooCommerce');
    }
}

// 1. Register the new endpoint so WordPress recognizes the URL.
add_action('init', function() {
    if (jvp_has_wc()) {
        add_rewrite_endpoint('vendor-status', EP_PAGES);
    }
});

// 2. Add a new menu item "Vendor Status" to the My Account page.
add_filter('woocommerce_account_menu_items', function($items) {
    if (!jvp_has_wc()) return $items;
    
    $user = wp_get_current_user();
    if (in_array('vendor', (array)$user->roles, true)) {
        $new_items = ['vendor-status' => __('حالة التاجر', 'jabri-vendor-portal')];
        // Insert the new item after the 'dashboard' item.
        return array_slice($items, 0, 1, true) + $new_items + array_slice($items, 1, null, true);
    }
    return $items;
}, 40);

// 3. Add content for our new "Vendor Status" tab.
add_action('woocommerce_account_vendor-status_endpoint', function() {
    $u = wp_get_current_user();
    $status = get_user_meta($u->ID, 'account_status', true) ?: 'pending';
    $badge = ($status === 'approved') ? __('مفعّل', 'jabri-vendor-portal') : (($status === 'rejected') ? __('مرفوض', 'jabri-vendor-portal') : __('بانتظار الموافقة', 'jabri-vendor-portal'));
    $reason = get_user_meta($u->ID, 'jvp_reject_reason', true);
    $att = intval(get_user_meta($u->ID, 'license_attachment_id', true));
    $url = get_user_meta($u->ID, 'license_file', true);
    if ($att) $url = wp_get_attachment_url($att);
    ?>
    <div class="woocommerce-MyAccount-content" dir="rtl">
        <h3><?php esc_html_e('معلومات حساب التاجر', 'jabri-vendor-portal'); ?></h3>
        <p>
            <strong><?php esc_html_e('الحالة:', 'jabri-vendor-portal'); ?></strong>
            <?php echo esc_html($badge); ?>
        </p>
        <?php if ($status === 'rejected' && $reason) : ?>
            <div class="woocommerce-error" role="alert">
                <strong><?php esc_html_e('سبب الرفض:', 'jabri-vendor-portal'); ?></strong><br>
                <?php echo wp_kses_post($reason); ?>
            </div>
        <?php endif; ?>
        <p>
            <strong><?php esc_html_e('الرخصة/السجل التجاري:', 'jabri-vendor-portal'); ?></strong>
            <?php echo $url ? '<a target="_blank" href="' . esc_url($url) . '">' . esc_html__('عرض الملف', 'jabri-vendor-portal') . '</a>' : '<em>' . esc_html__('لا يوجد', 'jabri-vendor-portal') . '</em>'; ?>
        </p>
    </div>
    <?php
});


/**
 * Ensure WooCommerce recognizes the 'vendor-status' query var.
 */
add_filter('woocommerce_get_query_vars', function($vars){
    $vars['vendor-status'] = 'vendor-status';
    return $vars;
}, 0);


/**
 * Vendor Orders Tab (My Account)
 */
add_filter('woocommerce_get_query_vars', function($vars){
    $vars['vendor-orders'] = 'vendor-orders';
    return $vars;
}, 0);

add_action('init', function(){
    if ( jvp_has_wc() ) {
        add_rewrite_endpoint('vendor-orders', EP_PAGES);
    }
});


add_filter('woocommerce_account_menu_items', function($items){
    if ( ! jvp_has_wc() ) return $items;
    $user = wp_get_current_user();
    if ( ! in_array('vendor', (array)$user->roles, true) ) return $items;

    if ( ! isset($items['vendor-orders']) ){
        $new = array('vendor-orders' => __('طلبات التاجر', 'jabri-vendor-portal'));
        if ( isset($items['orders']) ){
            // Insert after 'orders'
            $out = array();
            $inserted = false;
            foreach ($items as $k=>$v){
                $out[$k] = $v;
                if ($k === 'orders' && ! $inserted){
                    $out = $out + $new;
                    $inserted = true;
                }
            }
            if ( ! $inserted ){
                $out = $items + $new;
            }
            return $out;
        } else {
            // Append if 'orders' missing
            $items = $items + $new;
        }
    }
    return $items;
}, 41);


add_action('woocommerce_account_vendor-orders_endpoint', function(){
    if ( ! is_user_logged_in() ){
        echo '<div class="woocommerce-error">'.esc_html__('الرجاء تسجيل الدخول.', 'jabri-vendor-portal').'</div>'; return;
    }
    $u = wp_get_current_user();
    if ( ! in_array('vendor', (array)$u->roles, true) ){
        echo '<div class="woocommerce-error">'.esc_html__('هذه الصفحة مخصصة للتجار.', 'jabri-vendor-portal').'</div>'; return;
    }
    if ( ! class_exists('WooCommerce') ){
        echo '<div class="woocommerce-error">'.esc_html__('يجب تفعيل WooCommerce لعرض الطلبات.', 'jabri-vendor-portal').'</div>'; return;
    }

    $orders = wc_get_orders(array(
        'customer_id' => $u->ID,
        'limit'       => -1,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ));

    $current_statuses = array('processing','on-hold','pending');
    $current = array(); $past = array();
    foreach ($orders as $order){
        $st = $order->get_status();
        if ( in_array($st, $current_statuses, true) ) $current[] = $order; else $past[] = $order;
    }

    ?>
    <div class="woocommerce-MyAccount-content" dir="rtl">
      <h3><?php esc_html_e('طلبات التاجر', 'jabri-vendor-portal'); ?></h3>

      <div class="jvp-tabs-wrapper" style="margin-top:10px;">
        <div class="jvp-tabs-nav">
          <button class="jvp-tab-link active" onclick="jvpOpenOrdersTab(event, 'jvp-current-orders')">
            <?php echo esc_html__('الطلبات الحالية', 'jabri-vendor-portal'); ?> (<?php echo count($current); ?>)
          </button>
          <button class="jvp-tab-link" onclick="jvpOpenOrdersTab(event, 'jvp-past-orders')">
            <?php echo esc_html__('سجل الطلبات', 'jabri-vendor-portal'); ?> (<?php echo count($past); ?>)
          </button>
        </div>

        <div id="jvp-current-orders" class="jvp-tab-content" style="display:block;">
          <?php if ( empty($current) ) : ?>
            <p><?php echo esc_html__('لا توجد طلبات حالية.', 'jabri-vendor-portal'); ?></p>
          <?php else : ?>
            <table class="jvp-orders-table">
              <thead>
                <tr>
                  <th><?php echo esc_html__('رقم الطلب', 'jabri-vendor-portal'); ?></th>
                  <th><?php echo esc_html__('التاريخ', 'jabri-vendor-portal'); ?></th>
                  <th><?php echo esc_html__('الحالة', 'jabri-vendor-portal'); ?></th>
                  <th><?php echo esc_html__('الإجمالي', 'jabri-vendor-portal'); ?></th>
                  <th><?php echo esc_html__('الإجراءات', 'jabri-vendor-portal'); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($current as $order): ?>
                  <tr>
                    <td>#<?php echo esc_html($order->get_order_number()); ?></td>
                    <td><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></td>
                    <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                    <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                    <td><a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="jvp-btn-view"><?php echo esc_html__('عرض', 'jabri-vendor-portal'); ?></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div id="jvp-past-orders" class="jvp-tab-content" style="display:none;">
          <?php if ( empty($past) ) : ?>
            <p><?php echo esc_html__('لا يوجد سجل للطلبات السابقة.', 'jabri-vendor-portal'); ?></p>
          <?php else : ?>
            <table class="jvp-orders-table">
              <thead>
                <tr>
                  <th><?php echo esc_html__('رقم الطلب', 'jabri-vendor-portal'); ?></th>
                  <th><?php echo esc_html__('التاريخ', 'jabri-vendor-portal'); ?></th>
                  <th><?php echo esc_html__('الحالة', 'jabri-vendor-portal'); ?></th>
                  <th><?php echo esc_html__('الإجمالي', 'jabri-vendor-portal'); ?></th>
                  <th><?php echo esc_html__('الإجراءات', 'jabri-vendor-portal'); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($past as $order): ?>
                  <tr>
                    <td>#<?php echo esc_html($order->get_order_number()); ?></td>
                    <td><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></td>
                    <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                    <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                    <td><a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="jvp-btn-view"><?php echo esc_html__('عرض', 'jabri-vendor-portal'); ?></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <style>
        .jvp-tabs-nav { overflow:hidden; border-bottom:1px solid #666; margin-bottom: 10px; }
        .jvp-tabs-nav .jvp-tab-link { background:transparent; border:none; cursor:pointer; padding:10px 12px; color:#111; }
        .jvp-tabs-nav .jvp-tab-link.active { background:#eee; color:#111; border-radius:8px; }
        .jvp-orders-table { width:100%; border-collapse:collapse; margin-top:10px; }
        .jvp-orders-table th, .jvp-orders-table td { border:1px solid #ddd; padding:8px 12px; text-align:right; }
        .jvp-orders-table thead { background:#f3f4f6; }
        .jvp-btn-view { text-decoration:none; background:#c2410c; color:#fff; padding:6px 10px; border-radius:6px; font-size:13px; }
      </style>
      <script>
        function jvpOpenOrdersTab(evt, tabId){
          var i, c = document.querySelectorAll('.woocommerce-MyAccount-content .jvp-tab-content');
          for(i=0;i<c.length;i++){ c[i].style.display = 'none'; }
          var btns = document.querySelectorAll('.woocommerce-MyAccount-content .jvp-tab-link');
          for(i=0;i<btns.length;i++){ btns[i].className = btns[i].className.replace(' active',''); }
          document.getElementById(tabId).style.display = 'block';
          evt.currentTarget.className += ' active';
        }
      </script>
    </div>
    <?php
});


// ===== Robust Vendor Status Tab registration (v3.7.5) =====

// Ensure WC query vars include vendor-status
add_filter('woocommerce_get_query_vars', function($vars){
    $vars['vendor-status'] = 'vendor-status';
    return $vars;
}, 0);

// Register endpoints early
add_action('init', function(){
    if ( function_exists('class_exists') && class_exists('WooCommerce') ) {
        add_rewrite_endpoint('vendor-status', EP_PAGES);
        // also re-register vendor-orders if present in older versions
        add_rewrite_endpoint('vendor-orders', EP_PAGES);
    }
}, 0);

// Late-priority menu injection to beat themes overriding items
add_filter('woocommerce_account_menu_items', function($items){
    if ( ! class_exists('WooCommerce') ) return $items;
    $u = wp_get_current_user();
    if ( ! $u || ! in_array('vendor', (array)$u->roles, true) ) return $items;

    // Insert vendor-status if missing
    if ( ! isset($items['vendor-status']) ){
        $new = array('vendor-status' => __('حالة التاجر', 'jabri-vendor-portal'));
        if ( isset($items['dashboard']) ){
            $out = array(); $inserted=false;
            foreach ($items as $k=>$v){
                $out[$k]=$v;
                if ($k==='dashboard' && ! $inserted){ $out = $out + $new; $inserted=true; }
            }
            if ( ! $inserted ){ $out = $items + $new; }
            $items = $out;
        } else {
            $items = $items + $new;
        }
    }
    return $items;
}, 999);

// Runtime fallback: if on My Account and vendor-status not registered in menu due to theme filters, add it via global nav filter
add_action('wp', function(){
    if ( function_exists('is_account_page') && is_account_page() ) {
        add_filter('woocommerce_account_menu_items', function($items){
            $u = wp_get_current_user();
            if ( $u && in_array('vendor', (array)$u->roles, true) && ! isset($items['vendor-status']) ){
                $items['vendor-status'] = __('حالة التاجر', 'jabri-vendor-portal');
            }
            return $items;
        }, 1000);
    }
});
