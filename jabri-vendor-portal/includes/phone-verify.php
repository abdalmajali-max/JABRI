<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * إرسال رسالة عبر Wawp (POST /wp-json/awp/v1/send)
 * يستخدم instance_id, access_token, chatId, message
 */
function jvp_send_wawp_message($phone, $text){
  $api = get_option('jvp_wawp_api_url','https://wawp.net/wp-json/awp/v1/send');
  $inst= get_option('jvp_wawp_instance_id','');
  $tok = get_option('jvp_wawp_access_token','');
  if ( empty($api) || empty($inst) || empty($tok) ) return 'Wawp config missing';

  $chatId = preg_replace('/\D+/','',$phone);
  if (empty($chatId)) return 'Invalid phone format';

  $res = wp_remote_post($api, [
    'timeout'=>20,
    'body'=>[
      'instance_id'=>$inst,
      'access_token'=>$tok,
      'chatId'=>$chatId,
      'message'=>wp_strip_all_tags($text),
    ],
  ]);
  if (is_wp_error($res)) return $res->get_error_message();
  $code=wp_remote_retrieve_response_code($res);
  if ($code>=200 && $code<300) return true;
  return 'Wawp error: '.wp_remote_retrieve_body($res);
}

add_action('wp_ajax_nopriv_jvp_send_phone_otp','jvp_send_phone_otp');
add_action('wp_ajax_jvp_send_phone_otp','jvp_send_phone_otp');
function jvp_send_phone_otp(){
  if ( ! isset($_POST['_ajax_nonce']) || ! wp_verify_nonce($_POST['_ajax_nonce'], 'jvp_phone_otp_nonce') ) {
    wp_send_json_error(['message'=>'Security error']); }
  $phone = sanitize_text_field($_POST['phone'] ?? '');
  if ( empty($phone) ) wp_send_json_error(['message'=>'أدخل رقم الواتساب الدولي']);
  $otp = wp_rand(100000, 999999);
  set_transient('jvp_phone_otp_' . md5($phone), (string)$otp, 5*60);
  $msg = 'رمز التحقق: ' . $otp . ' (صالح 5 دقائق)';
  $ok = jvp_send_wawp_message($phone, $msg);
  if ($ok === true) wp_send_json_success(['message'=>'تم إرسال الرمز']);
  wp_send_json_error(['message'=>$ok]);
}

add_action('wp_ajax_nopriv_jvp_verify_phone_otp','jvp_verify_phone_otp');
add_action('wp_ajax_jvp_verify_phone_otp','jvp_verify_phone_otp');
function jvp_verify_phone_otp(){
  if ( ! isset($_POST['_ajax_nonce']) || ! wp_verify_nonce($_POST['_ajax_nonce'], 'jvp_phone_verify_nonce') ) {
    wp_send_json_error(['message'=>'Security error']); }
  $phone = sanitize_text_field($_POST['phone'] ?? '');
  $code  = sanitize_text_field($_POST['code'] ?? '');
  $saved = get_transient('jvp_phone_otp_' . md5($phone));
  if ( empty($saved) || $saved !== $code ) wp_send_json_error(['message'=>'رمز غير صحيح أو منتهي']);
  set_transient('jvp_phone_verified_' . md5($phone), '1', 30*60);
  delete_transient('jvp_phone_otp_' . md5($phone));
  wp_send_json_success(['message'=>'تم التحقق بنجاح']);
}

/**
 * شورتكود: [vendor_phone_verification]
 * - يعرض حقل الهاتف + إرسال الكود + إدخال الرمز
 * - يحمّل jQuery + ملف jvp-verify.js ويستخدم localized vars (ajax + nonces + redirect)
 */
add_shortcode('vendor_phone_verification', function(){
  wp_enqueue_script('jquery');
  wp_enqueue_script('jvp-verify');

  ob_start(); ?>
  <style>
    .jvp-wrap{max-width:600px;margin:40px auto;padding:10px;color:#fff;font-family:'Tajawal',system-ui}
    .jvp-dark{background:linear-gradient(135deg,#2B2B2B,#3A3A3A)}
    .jvp-card{background:#454545;border:1px solid #EAEBD0;border-radius:16px;padding:18px;box-shadow:0 8px 22px rgba(0,0,0,.25)}
    .jvp-title{text-align:center;margin:0 0 12px;font-size:22px;font-weight:800}
    .jvp-field{display:flex;flex-direction:column;margin-bottom:12px}
    .jvp-card input{background:#222 !important;color:#fff !important;caret-color:#fff !important;border:1px solid #666 !important;border-radius:12px !important;padding:12px !important;line-height:1.4 !important;box-shadow:none !important;outline:none !important}
    .jvp-card input:focus{ background:#1f1f1f !important; }
    .jvp-btn{display:inline-block;background:linear-gradient(180deg,#AF3E3E,#C95656);color:#fff;border:1px solid #6b0810;border-radius:12px;padding:10px 16px;cursor:pointer}
    .jvp-info{padding:10px;background:#1e3a24;border:1px solid #2f6f40;border-radius:10px;margin:12px 0;color:#d3ffd3;display:none}
    .jvp-error{padding:10px;background:#5b1e20;border:1px solid #b04b51;border-radius:10px;margin:12px 0;color:#ffd7d7;display:none}
  </style>
  <div class="jvp-wrap jvp-dark" dir="rtl">
    <div class="jvp-card">
      <h3 class="jvp-title">توثيق رقم الواتساب</h3>
      <div class="jvp-field">
        <label>رقم واتساب (دولي)</label>
        <input type="text" id="jvp_phone" placeholder="+9627XXXXXXXX">
      </div>
      <p><button class="jvp-btn" id="jvp-send-otp">إرسال الكود</button></p>
      <div id="jvp-otp-area" style="display:none">
        <div class="jvp-field">
          <label>رمز التحقق</label>
          <input type="text" id="jvp_otp_code" maxlength="6" placeholder="6 أرقام">
        </div>
        <p><button class="jvp-btn" id="jvp-verify-otp">تحقق</button></p>
      </div>
      <div id="jvp-msg" class="jvp-info"></div>
    </div>
  </div>
  <?php
  return ob_get_clean();
});
