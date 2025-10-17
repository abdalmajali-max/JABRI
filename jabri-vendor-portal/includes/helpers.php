<?php
if ( ! defined('ABSPATH') ) { exit; }
<?php
if ( ! defined('ABSPATH') ) { exit; }

if ( ! function_exists('jvp_get_dashboard_url') ) {
    /**
     * Always direct vendors to WooCommerce native Orders endpoint.
     */
    function jvp_get_dashboard_url() {
        if ( function_exists('wc_get_account_endpoint_url') ) {
            return wc_get_account_endpoint_url('orders');
        }
        return home_url('/my-account/orders/');
    }
}


if ( ! function_exists('jvp_has_wc') ) { function jvp_has_wc(){ return class_exists('WooCommerce'); } }

if ( ! function_exists('jvp_get_dashboard_url') ) {
    function jvp_get_dashboard_url() {
        if ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ) {
            return wc_get_account_endpoint_url('orders');
        }
        return home_url('/my-account/orders/');
    }
}

if ( ! function_exists('jvp_normalize_msisdn') ) {
    function jvp_normalize_msisdn( $raw, $default_cc = '+962' ) {
        $s = preg_replace('/[^0-9+]/', '', (string)$raw);
        if ($s === '') return $s;
        if (strpos($s, '00') === 0) $s = '+'.substr($s, 2);
        if ($s[0] === '+') return $s;
        $s = ltrim($s, '0');
        return $default_cc . $s;
    }
}

if ( ! function_exists('jvp_send_wawp_message') ) {
    function jvp_send_wawp_message( $to, $message ) {
        $api  = trim( get_option('jvp_wawp_api_url', 'https://wawp.net/wp-json/awp/v1/send') );
        $inst = trim( get_option('jvp_wawp_instance_id', '') );
        $tok  = trim( get_option('jvp_wawp_access_token', '') );
        if ( empty($api) || empty($inst) || empty($tok) ) {
            return new WP_Error('wawp_settings', 'إعدادات Wawp غير مكتملة.');
        }
        $payload = [
            'instance_id'  => $inst,
            'access_token' => $tok,
            'to'           => $to,
            'message'      => wp_strip_all_tags($message),
        ];
        $resp = wp_remote_post($api, [
            'timeout'=>30,
            'headers'=>['Content-Type'=>'application/json; charset=utf-8'],
            'body'=>wp_json_encode($payload),
        ]);
        if ( ! is_wp_error($resp) ) {
            $code = wp_remote_retrieve_response_code($resp);
            if ($code >= 200 && $code < 300) return true;
            error_log('[JVP][Wawp][JSON] HTTP '.$code.' BODY: '.substr(wp_remote_retrieve_body($resp),0,300));
        } else {
            error_log('[JVP][Wawp][JSON] '.$resp->get_error_message());
        }
        $resp2 = wp_remote_post($api, ['timeout'=>30,'body'=>$payload]);
        if ( is_wp_error($resp2) ) {
            error_log('[JVP][Wawp][FORM] '.$resp2->get_error_message());
            return $resp2;
        }
        $code2 = wp_remote_retrieve_response_code($resp2);
        if ($code2 >= 200 && $code2 < 300) return true;
        return new WP_Error('wawp_http', 'Wawp HTTP '.$code2.': '.substr(wp_remote_retrieve_body($resp2),0,200));
    }
}
