<?php
}
// JSON first
        $json_body = wp_json_encode([
            'instance_id'  => $instance_id,
            'access_token' => $access_token,
            'to'           => $to,
            'message'      => $message,
        ]);
        $args_json = [
            'timeout'=>30,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'    => $json_body,
        ];
        $sent = jvp_send_wawp_message($phone, $msg);
        if ( is_wp_error($sent) ) {
            error_log('[JVP][Wawp] JSON request error: ' . $resp->get_error_message());
        } else {
            $code = 200;
            $body = '';
            if ( $code >= 200 && $code < 300 ) {
                // Assume success
                return true;
            }
            error_log('[JVP][Wawp] JSON request failed: HTTP '.$code.' Body: '.substr($body,0,300));
        }
        // Fallback: form-encoded
        $args_form = [
            'timeout'=>30,
            'body'    => [
                'instance_id'  => $instance_id,
                'access_token' => $access_token,
                'to'           => $to,
                'message'      => $message,
            ],
        ];
        $resp2 = wp_remote_post($api_url, $args_form);
        if ( is_wp_error($resp2) ) {
            error_log('[JVP][Wawp] FORM request error: ' . $resp2->get_error_message());
            return $resp2;
        }
        $code2 = wp_remote_retrieve_response_code($resp2);
        $body2 = wp_remote_retrieve_body($resp2);
        if ( $code2 >= 200 && $code2 < 300 ) {
            return true;
        }
        error_log('[JVP][Wawp] FORM request failed: HTTP '.$code2.' Body: '.substr($body2,0,300));
        return new WP_Error('wawp_http', 'تعذّر إرسال الرمز، كود HTTP: '.$code2.'، الرد: '.substr($body2,0,200));
    }
}


if ( ! function_exists('jvp_send_wawp') ) {
    function jvp_send_wawp( $api_url, $instance_id, $access_token, $to, $message ) {
        if ( empty($api_url) || empty($instance_id) || empty($access_token) ) {
            return new WP_Error('wawp_settings', 'إعدادات Wawp غير مكتملة.');
        }
        return jvp_send_wawp_message($to, $message);
    }
}
?>
<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Helpers to resolve cross-links
 */
function jvp_get_register_url() {
    // Option override
    $opt = trim( get_option('jvp_vendor_register_url', '') );
    if ( ! empty($opt) ) return esc_url($opt);
    // Try to resolve by common slugs
    $candidates = array('vendor-register','register-vendor','vendor-registration','تسجيل-التاجر','vendor');
    foreach ($candidates as $slug) {
        $p = get_page_by_path($slug);
        if ( $p && ! is_wp_error($p) ) return get_permalink($p);
    }
    // Fallback (adjust if needed)
    return home_url('/vendor-register/');
}
function jvp_get_login_url() {
    $opt = trim( get_option('jvp_vendor_login_url', '') );
    if ( ! empty($opt) ) return esc_url($opt);
    $candidates = array('vendor-login','login-vendor','تسجيل-دخول-التاجر','login');
    foreach ($candidates as $slug) {
        $p = get_page_by_path($slug);
        if ( $p && ! is_wp_error($p) ) return get_permalink($p);
    }
    return home_url('/vendor-login/');
}

/**
 * Brand palette + shared CSS
 */
function jvp_brand_css() {
    $c_bg  = '#EAEBD0';
    $c_p1  = '#DA6C6C';
    $c_p2  = '#CD5656';
    $c_p3  = '#AF3E3E';
    $c_txt = '#222';
    return '';
}

/**
 * Shortcode: [vendor_registration_form]
 * Flow: OTP via Wawp -> show full form -> create vendor (pending).
 */
add_shortcode('vendor_registration_form', function($atts = [], $content = ''){
    if ( is_user_logged_in() ) {
        $orders = function_exists('wc_get_account_endpoint_url') ?  ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : home_url('/my-account/orders/') ) : home_url('/my-account/orders/') ) : home_url('/my-account/orders/') ) : home_url('/my-account/orders/') )  : home_url('/my-account/orders/');
        return '<div class="woocommerce-message jvp" dir="rtl">أنت مسجّل الدخول بالفعل. <a href="'.esc_url($orders).'">اذهب إلى الطلبات</a></div>';
    }

    $out = jvp_brand_css();
    $errors = [];
    $success = '';

    // Settings for Wawp
    $api_url = trim( get_option('jvp_wawp_api_url', 'https://wawp.net/wp-json/awp/v1/send') );
    $inst_id = trim( get_option('jvp_wawp_instance_id', '') );
    $token   = trim( get_option('jvp_wawp_access_token', '') );

    $action = isset($_POST['jvp_action']) ? sanitize_key($_POST['jvp_action']) : '';

    // SEND OTP
    if ( 'send_otp' === $action && isset($_POST['jvp_phone']) && isset($_POST['jvp_nonce']) && wp_verify_nonce($_POST['jvp_nonce'], 'jvp_reg') ) {
        $default_cc = get_option('jvp_default_country_code', '+962');
        \1
        $rate_key = 'jvp_otp_rate_' . md5($phone);
        if ( get_transient($rate_key) ) {
            $errors[] = 'الرجاء المحاولة خلال دقيقة واحدة.';
        } else { set_transient($rate_key, 1, 60); }
        if ( empty($phone) ) {
            $errors[] = 'الرجاء إدخال رقم الهاتف.';
        } elseif ( empty($api_url) || empty($inst_id) || empty($token) ) {
            $errors[] = 'إعدادات Wawp غير مكتملة.';
        } else {
            $code = wp_rand(100000, 999999);
            $key = 'jvp_otp_' . wp_generate_password(12, false);
            set_transient($key, ['phone'=>$phone,'code'=>(string)$code,'time'=>time()], MINUTE_IN_SECONDS*10);

            $body = ['instance_id'=>$inst_id,'access_token'=>$token,'to'=>$phone,'message'=>'رمز OTP الخاص بك هو: '.$code];
            $sent = jvp_send_wawp_message($phone, $msg);
            if ( is_wp_error($sent) ) {
                $errors[] = 'تعذّر إرسال رمز التحقق عبر Wawp: '.$resp->get_error_message();
                delete_transient($key);
            } else {
                $success = 'تم إرسال رمز التحقق إلى واتساب.';
                $_POST['jvp_otp_key'] = $key;
            }
        }
    }

    // VERIFY OTP
    if ( 'verify_otp' === $action && isset($_POST['jvp_otp_key']) && isset($_POST['jvp_otp_code']) && isset($_POST['jvp_nonce']) && wp_verify_nonce($_POST['jvp_nonce'], 'jvp_reg') ) {
        $otp_key  = sanitize_text_field($_POST['jvp_otp_key']);
        $otp_code = preg_replace('/[^0-9]/', '', $_POST['jvp_otp_code']);
        $data = get_transient($otp_key);
        if ( ! $data ) {
            $errors[] = 'انتهت صلاحية الرمز. الرجاء طلب رمز جديد.';
        } elseif ( (string)$data['code'] !== (string)$otp_code ) {
            $errors[] = 'رمز غير صحيح.';
        } else {
            $verified_token = 'jvp_vtok_' . wp_generate_password(16, false);
            set_transient($verified_token, ['phone'=>$data['phone'],'time'=>time()], MINUTE_IN_SECONDS*20);
            delete_transient($otp_key);
            $_POST['jvp_verified'] = $verified_token;
            $success = 'تم التحقق من رقم الهاتف بنجاح. أكمل بيانات التسجيل.';
        }
    }

    // DO REGISTER
    if ( 'do_register' === $action && isset($_POST['jvp_verified']) && isset($_POST['jvp_nonce']) && wp_verify_nonce($_POST['jvp_nonce'], 'jvp_reg') ) {
        $vtok = sanitize_text_field($_POST['jvp_verified']);
        $vd = get_transient($vtok);
        if ( ! $vd ) {
            $errors[] = 'انتهت جلسة التحقق. الرجاء إعادة التحقق من الهاتف.';
        } else {
            $first  = sanitize_text_field($_POST['first_name'] ?? '');
            $last   = sanitize_text_field($_POST['last_name'] ?? '');
            $email  = sanitize_email($_POST['email'] ?? '');
            $pass   = $_POST['password'] ?? '';
            $agree  = isset($_POST['agree_terms']) ? 1 : 0;
            $phone  = $vd['phone'];

            if ( empty($first) ) $errors[] = 'الاسم الأول مطلوب.';
            if ( empty($last) )  $errors[] = 'اسم العائلة مطلوب.';
            if ( empty($pass) || strlen($pass) < 6 ) $errors[] = 'كلمة المرور مطلوبة (6 أحرف على الأقل).';
            if ( ! empty($email) && ! is_email($email) ) $errors[] = 'البريد الإلكتروني غير صالح.';
            if ( ! $agree ) $errors[] = 'يجب الموافقة على الشروط.';

            $username = 'wa.' . ltrim(preg_replace('/[^0-9]/', '', $phone), '0');
            if ( username_exists($username) ) $errors[] = 'رقم الهاتف مستخدم مسبقًا.';

            if ( empty($errors) ) {
                if ( empty($email) ) $email = 'wa.' . wp_generate_password(12, false) . '@example.com';
                $user_id = wp_create_user($username, $pass, $email);
                if ( is_wp_error($user_id) ) {
                    $errors[] = $user_id->get_error_message();
                } else {
                    wp_update_user(['ID'=>$user_id,'first_name'=>$first,'last_name'=>$last,'display_name'=>$first.' '.$last]);
                    update_user_meta($user_id, 'phone', $phone);
                    update_user_meta($user_id, 'account_status', 'pending');
                    $u = new WP_User($user_id); $u->set_role('vendor');

                    if ( ! empty($_FILES['trade_license']['name']) ) {
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                        $attach_id = media_handle_upload('trade_license', 0);
                        if ( ! is_wp_error($attach_id) ) update_user_meta($user_id, 'trade_license_attachment_id', (int)$attach_id);
                    }

                    $admin_email = get_option('admin_email');
                    wp_mail($admin_email, 'Vendor Registration Pending Approval', 'A new vendor registered and is pending approval: '.$username.' ('.$phone.')');

                    $msg = get_option('jvp_success_message', 'تم استلام طلب التسجيل كتاجر. سيتم تفعيل الحساب بعد التحقق من البيانات.');
                    $success = '<div class="jvp-success" dir="rtl">'.esc_html($msg).'</div>';
                    delete_transient($vtok);
                }
            }
        }
    }

    ob_start();
    echo '<div class="jvp-wrap"><div class="jvp-card">';

    if ( ! empty($errors) ) {
        echo '<div class="jvp-error"><ul>';
        foreach ($errors as $e) echo '<li>'.esc_html($e).'</li>';
        echo '</ul></div>';
    }
    if ( ! empty($success) ) echo '<div class="jvp-note">'.wp_kses_post($success).'</div>';

    $verified = isset($_POST['jvp_verified']) ? sanitize_text_field($_POST['jvp_verified']) : '';
    $vdata = $verified ? get_transient($verified) : false;

    if ( $vdata ) {
        $phone_display = esc_html( $vdata['phone'] );
        echo '<h3 class="jvp-title">تكملة تسجيل التاجر</h3>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('jvp_reg', 'jvp_nonce');
        echo '<input type="hidden" name="jvp_action" value="do_register">';
        echo '<input type="hidden" name="jvp_verified" value="'.esc_attr($verified).'">';
        echo '<div class="jvp-row">
                <div class="jvp-col">
                    <label class="jvp-label">الاسم الأول *</label>
                    <input class="jvp-input" type="text" name="first_name" required>
                </div>
                <div class="jvp-col">
                    <label class="jvp-label">اسم العائلة *</label>
                    <input class="jvp-input" type="text" name="last_name" required>
                </div>
              </div>';
        echo '<div class="jvp-row">
                <div class="jvp-col">
                    <label class="jvp-label">البريد الإلكتروني (اختياري)</label>
                    <input class="jvp-input" type="email" name="email" placeholder="example@email.com">
                </div>
                <div class="jvp-col">
                    <label class="jvp-label">رقم الهاتف الموثّق</label>
                    <input class="jvp-input" type="text" value="'.$phone_display.'" readonly>
                </div>
              </div>';
        echo '<div class="jvp-row">
                <div class="jvp-col">
                    <label class="jvp-label">كلمة المرور *</label>
                    <input class="jvp-input" type="password" name="password" minlength="6" required>
                </div>
                <div class="jvp-col">
                    <label class="jvp-label">رخصة المهن/السجل التجاري (اختياري)</label>
                    <input class="jvp-file" type="file" name="trade_license" accept="image/*,application/pdf">
                </div>
              </div>';
        echo '<p class="jvp-muted"><label><input type="checkbox" name="agree_terms" value="1" required> أوافق على الشروط والأحكام</label></p>';
        echo '<div class="jvp-actions">';
        echo '<button class="jvp-btn" type="submit">إنهاء التسجيل</button>';
        // Cross-link: login button
        echo '<a class="jvp-linkbtn" href="'.esc_url( jvp_get_login_url() ).'">دخول التاجر</a>';
        echo '</div>';
        echo '</form>';
    } else {
        echo '<h3 class="jvp-title">توثيق رقم الهاتف عبر واتساب</h3>';
        echo '<form method="post">';
        wp_nonce_field('jvp_reg', 'jvp_nonce');
        echo '<input type="hidden" name="jvp_action" value="send_otp">';
        $pref_phone = isset($_POST['jvp_phone']) ? esc_attr($_POST['jvp_phone']) : '';
        echo '<div class="jvp-row">
                <div class="jvp-col">
                    <label class="jvp-label">رقم الهاتف *</label>
                    <input class="jvp-input" type="text" name="jvp_phone" placeholder="+962..." value="'.$pref_phone.'" required>
                </div>
              </div>';
        echo '<div class="jvp-actions">';
        echo '<button class="jvp-btn" type="submit">إرسال رمز التحقق</button>';
        // Cross-link: login button
        echo '<a class="jvp-linkbtn" href="'.esc_url( jvp_get_login_url() ).'">دخول التاجر</a>';
        echo '</div>';
        echo '</form>';

        $otp_key = isset($_POST['jvp_otp_key']) ? sanitize_text_field($_POST['jvp_otp_key']) : '';
        if ( ! empty($otp_key) ) {
            echo '<form method="post" style="margin-top:12px;">';
            wp_nonce_field('jvp_reg', 'jvp_nonce');
            echo '<input type="hidden" name="jvp_action" value="verify_otp">';
            echo '<input type="hidden" name="jvp_otp_key" value="'.esc_attr($otp_key).'">';
            echo '<div class="jvp-row">
                    <div class="jvp-col">
                        <label class="jvp-label">أدخل رمز التحقق *</label>
                        <input class="jvp-input" type="text" name="jvp_otp_code" maxlength="6" pattern="[0-9]{6}" required>
                    </div>
                  </div>';
            echo '<div class="jvp-actions">';
            echo '<button class="jvp-btn" type="submit">توثيق</button>';
            echo '<form method="post" style="display:inline-block">';
            wp_nonce_field('jvp_reg', 'jvp_nonce');
            echo '<input type="hidden" name="jvp_action" value="send_otp">';
            echo '<input type="hidden" name="jvp_phone" value="'.esc_attr($pref_phone).'">';
            echo '<button class="jvp-linkbtn" type="submit">إعادة إرسال الرمز</button>';
            echo '</form>';
            echo '<a class="jvp-linkbtn" href="'.esc_url( jvp_get_login_url() ).'">دخول التاجر</a>';
            echo '</div>';
            echo '</form>';
        }
    }

    echo '</div></div>';
    return ob_get_clean();
});

/**
 * Shortcode: [vendor_login_form]
 * Brand-styled RTL login for vendors. Accepts phone or username/email.
 */
add_shortcode('vendor_login_form', function($atts = [], $content = ''){
    if ( is_user_logged_in() ) {
        $url = function_exists('wc_get_account_endpoint_url') ?  ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : home_url('/my-account/orders/') ) : home_url('/my-account/orders/') ) : home_url('/my-account/orders/') ) : home_url('/my-account/orders/') )  : home_url('/my-account/orders/');
        return '<div class="woocommerce-message" dir="rtl">أنت مسجّل الدخول. <a href="'.esc_url($url).'">اذهب إلى الطلبات</a></div>';
    }

    $out = jvp_brand_css();
    $errors = [];

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['jvp_login_nonce']) && wp_verify_nonce($_POST['jvp_login_nonce'], 'jvp_login') ) {
        $identity = trim( (string) ($_POST['identity'] ?? '') );
        $password = (string) ($_POST['password'] ?? '');

        if ( empty($identity) || empty($password) ) {
            $errors[] = 'الرجاء إدخال بيانات الدخول كاملة.';
        } else {
            $user_login = $identity;
            if ( preg_match('/^[+0-9][0-9\s\-()+]*$/', $identity) ) {
                $clean = preg_replace('/[^0-9+]/', '', $identity);
                $u = get_users([ 'meta_key'=>'phone', 'meta_value'=>$clean, 'number'=>1, 'count_total'=>false ]);
                if ( ! empty($u) && $u[0] instanceof WP_User ) {
                    $user_login = $u[0]->user_login;
                } else {
                    $digits = preg_replace('/[^0-9]/', '', $clean);
                    $candidate = 'wa.' . ltrim($digits, '0');
                    $user_login = $candidate;
                }
            }
            $creds = ['user_login'=>$user_login,'user_password'=>$password,'remember'=>!empty($_POST['remember_me'])];
            $user = wp_signon($creds, is_ssl());
            if ( is_wp_error($user) ) {
                $errors[] = 'بيانات الدخول غير صحيحة.';
            } else {
                $target = function_exists('wc_get_account_endpoint_url') ?  ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? ( jvp_has_wc() && function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : home_url('/my-account/orders/') ) : home_url('/my-account/orders/') ) : home_url('/my-account/orders/') ) : home_url('/my-account/orders/') )  : home_url('/my-account/orders/');
                wp_safe_redirect($target);
                exit;
            }
        }
    }

    ob_start();
    echo $out;
    echo '<div class="jvp-wrap"><div class="jvp-card">';
    if ( ! empty($errors) ) {
        echo '<div class="jvp-error"><ul>';
        foreach ($errors as $e) echo '<li>'.esc_html($e).'</li>';
        echo '</ul></div>';
    }
    echo '<h3 class="jvp-title">تسجيل دخول التاجر</h3>';
    echo '<form method="post">';
    wp_nonce_field('jvp_login', 'jvp_login_nonce');
    echo '<p><label class="jvp-label">رقم الهاتف أو اسم المستخدم/البريد</label>
          <input class="jvp-input" type="text" name="identity" placeholder="+962... أو اسم المستخدم" required></p>';
    echo '<p><label class="jvp-label">كلمة المرور</label>
          <input class="jvp-input" type="password" name="password" required></p>';
    echo '<p class="jvp-muted"><label><input type="checkbox" name="remember_me" value="1"> تذكّرني</label></p>';
    echo '<div class="jvp-actions">';
    echo '<button class="jvp-btn" type="submit">تسجيل الدخول</button>';
    // Cross-link: register button
    echo '<a class="jvp-linkbtn" href="'.esc_url( jvp_get_register_url() ).'">تسجيل كتاجر</a>';
    echo '</div>';
    echo '<p class="jvp-muted" style="margin-top:8px;"><a href="'.esc_url( wp_lostpassword_url() ).'">نسيت كلمة المرور؟</a></p>';
    echo '</form>';
    echo '</div></div>';
    return ob_get_clean();
});
