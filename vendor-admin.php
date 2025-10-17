<?php
if ( ! defined('ABSPATH') ) { exit; }

add_action('admin_init', function(){
  if (!current_user_can('promote_users')) return;
  if (isset($_GET['action'], $_GET['user_id']) && $_GET['action']==='jvp_approve_vendor') {
    $uid = absint($_GET['user_id']);
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'jvp_approve_vendor_'.$uid)) wp_die('Invalid nonce');
    update_user_meta($uid,'account_status','approved');
    do_action('jvp_notify_status', $uid, 'approved', '');
    wp_redirect( admin_url('users.php?jvp_approved=1') ); exit;
  }
});

function jvp_render_vendors_page(){
  if (!current_user_can('list_users')) wp_die('Not allowed');
  $vendors = get_users(['role'=>'vendor','orderby'=>'registered','order'=>'DESC']);
  echo '<div class="wrap"><h1>Vendors</h1>';
  echo '<table class="widefat striped"><thead><tr><th>User</th><th>Phone</th><th>License</th><th>Status</th><th>Approve</th><th>Reject</th></tr></thead><tbody>';
  if (!$vendors) { echo '<tr><td colspan="6">No vendors yet.</td></tr>'; }
  foreach($vendors as $u){
    $uid=$u->ID; $phone=get_user_meta($uid,'phone',true);
    $st=get_user_meta($uid,'account_status',true)?:'pending';
    $att=intval(get_user_meta($uid,'license_attachment_id',true));
    $url=get_user_meta($uid,'license_file',true); if($att) $url=wp_get_attachment_url($att);
    $approve=wp_nonce_url(admin_url('users.php?action=jvp_approve_vendor&user_id='.$uid),'jvp_approve_vendor_'.$uid);
    echo '<tr>';
    echo '<td><strong><a href="'.esc_url(get_edit_user_link($uid)).'">'.esc_html($u->display_name?:$u->user_login).'</a></strong><br><span class="description">'.esc_html($u->user_email).'</span></td>';
    echo '<td>'.($phone?'<a href="https://wa.me/'.esc_attr(preg_replace('/\D+/','',$phone)).'" target="_blank">'.esc_html($phone).'</a>':'-').'</td>';
    echo '<td>'.($url?'<a href="'.esc_url($url).'" target="_blank">Open</a>':'-').'</td>';
    echo '<td><strong>'.esc_html($st).'</strong></td>';
    echo '<td>'.($st!=='approved'?'<a class="button button-primary" href="'.esc_url($approve).'">Approve</a>':'—').'</td>';
    echo '<td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:6px;align-items:flex-start;">';
    wp_nonce_field('jvp_reject_vendor_'.$uid);
    echo '<input type="hidden" name="action" value="jvp_reject_vendor"><input type="hidden" name="user_id" value="'.esc_attr($uid).'">';
    echo '<textarea name="jvp_reject_reason" rows="2" cols="24" placeholder="سبب (اختياري)"></textarea>';
    echo '<button type="submit" class="button">Reject</button></form></td>';
    echo '</tr>';
  }
  echo '</tbody></table></div>';
}

// Reject handler + notify
add_action('admin_post_jvp_reject_vendor', function(){
  if (!current_user_can('promote_users')) wp_die('Not allowed');
  $uid = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
  if (!$uid) wp_die('Invalid');
  if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'jvp_reject_vendor_'.$uid)) wp_die('Invalid nonce');
  $reason = isset($_POST['jvp_reject_reason']) ? wp_kses_post($_POST['jvp_reject_reason']) : '';
  update_user_meta($uid,'account_status','rejected');
  if ($reason) update_user_meta($uid,'jvp_reject_reason',$reason);
  do_action('jvp_notify_status', $uid, 'rejected', $reason);
  wp_safe_redirect( admin_url('users.php?jvp_rejected=1') ); exit;
});
