<?php
if ( ! defined('ABSPATH') ) { exit; }
add_filter('manage_users_columns', function($c){ $c['jvp_status']='Vendor Status'; $c['jvp_phone']='Phone'; $c['jvp_license']='License'; return $c; });
add_filter('manage_users_custom_column', function($val,$col,$uid){
  if($col==='jvp_status'){ $u=get_userdata($uid); if(in_array('vendor',(array)$u->roles,true)){ $st=get_user_meta($uid,'account_status',true)?:'pending'; return '<strong>'.esc_html($st).'</strong>'; } return '-'; }
  if($col==='jvp_phone'){ $p=get_user_meta($uid,'phone',true); return $p?'<a href="https://wa.me/'.esc_attr(preg_replace('/\D+/','',$p)).'" target="_blank">'.esc_html($p).'</a>':'-'; }
  if($col==='jvp_license'){ $att=intval(get_user_meta($uid,'license_attachment_id',true)); $url=get_user_meta($uid,'license_file',true); if($att)$url=wp_get_attachment_url($att); return $url?'<a href="'.esc_url($url).'" target="_blank">View</a>':'-'; }
  return $val;
},10,3);
