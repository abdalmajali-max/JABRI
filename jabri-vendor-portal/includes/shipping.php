<?php
if ( ! defined('ABSPATH') ) { exit; }
function jvp_is_vendor(){ if(!is_user_logged_in())return false; $u=wp_get_current_user(); return in_array('vendor',(array)$u->roles,true); }
add_filter('woocommerce_package_rates', function($rates,$package){
  if ( ! jvp_is_vendor() ) return $rates;
  if ( ! (bool) get_option('jvp_only_free_shipping_vendor', true) ) return $rates;
  $free = null;
  foreach ($rates as $id=>$r) {
    if ( isset($r->method_id) && $r->method_id==='free_shipping' ) {
      $free = $r;
      if(isset($free->cost)) $free->cost = 0;
      if(is_array($free->taxes)) { foreach($free->taxes as $i=>$t) $free->taxes[$i]=0; }
      if(isset($free->label)) $free->label = 'Vendor Free Shipping';
      break;
    }
  }
  return $free ? ['jvp_vendor_free_only'=>$free] : $rates;
},10,2);
