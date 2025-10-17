<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Sends a WhatsApp notification to the vendor when their account status changes.
 *
 * @param int    $user_id The user ID.
 * @param string $type    The status type ('approved' or 'rejected').
 * @param string $reason  The reason for rejection (if any).
 */
if ( ! function_exists('jvp_notify_vendor_on_status_change') ) {
  function jvp_notify_vendor_on_status_change( $user_id, $type, $reason ) {
    if ( ! function_exists('jvp_send_wawp_text') ) { return; }

    $phone = get_user_meta( $user_id, 'phone', true );
    if ( empty( $phone ) ) { return; }

    $message = '';
    if ( 'approved' === $type ) {
      $message = __('تهانينا! تم تفعيل حساب التاجر الخاص بك بنجاح في متجر جبري. يمكنك الآن تسجيل الدخول والبدء في استخدام حسابك.', 'jabri-vendor-register-pro');
    } elseif ( 'rejected' === $type ) {
      $message = __('نأسف لإبلاغك بأنه تم رفض طلب تسجيلك كتاجر في متجر جبري.', 'jabri-vendor-register-pro');
      if ( ! empty( $reason ) ) {
        $message .= "\n\n" . __('سبب الرفض:', 'jabri-vendor-register-pro') . ' ' . wp_strip_all_tags($reason);
      }
    }

    if ( ! empty( $message ) ) {
      jvp_send_wawp_message( $phone, $message );
    }
  }
}
add_action( 'jvp_notify_status', 'jvp_notify_vendor_on_status_change', 10, 3 );
