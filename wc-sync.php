<?php if ( ! defined('ABSPATH') ) { exit; } ?>
<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Sync store name and phone with WC billing fields upon new user registration.
 */
add_action('user_register', function($uid){
  // Check if the user is a vendor
  $user = get_userdata($uid);
  if ( ! in_array('vendor', (array)$user->roles) ) {
    return;
  }

  $store = get_user_meta($uid,'store_name',true);
  $phone = get_user_meta($uid,'phone',true);

  if ( function_exists('wc_update_user_meta') ) {
    if ($store) wc_update_user_meta($uid,'billing_company',$store);
    if ($phone) wc_update_user_meta($uid,'billing_phone',$phone);
  }
});


/**
 * Sync store name and phone with WC billing fields when a vendor's profile is updated.
 */
if ( ! function_exists('jvp_wc_sync_on_profile_update') ) {
  function jvp_wc_sync_on_profile_update($uid) {
    // Check if the user is a vendor
    $user = get_userdata($uid);
    if ( ! $user || ! in_array('vendor', (array)$user->roles, true) ) { 
      return; 
    }

    $store = get_user_meta($uid, 'store_name', true);
    $phone = get_user_meta($uid, 'phone', true);

    if ( function_exists('wc_update_user_meta') ) {
      if ($store) wc_update_user_meta($uid, 'billing_company', $store);
      if ($phone) wc_update_user_meta($uid, 'billing_phone', $phone);
    }
  }
  add_action('profile_update', 'jvp_wc_sync_on_profile_update', 10, 1);
}


add_action('updated_user_meta', function($meta_id, $user_id, $meta_key, $_){
    if ( ! in_array($meta_key, ['phone','store_name'], true) ) return;
    $u = get_user_by('id', $user_id);
    if ( ! $u || ! in_array('vendor', (array)$u->roles, true) ) return;
    if ( 'phone' === $meta_key ) wc_wc_update_user_meta($user_id, 'billing_phone', get_user_meta($user_id,'phone',true));
    if ( 'store_name' === $meta_key ) wc_wc_update_user_meta($user_id, 'billing_company', get_user_meta($user_id,'store_name',true));
}, 10, 4);
