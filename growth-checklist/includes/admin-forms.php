<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
  add_submenu_page('gc3_crm','질문/폼 관리','질문/폼 관리','manage_options','gc3_forms','gc3_admin_forms');
});

function gc3_admin_forms(){
  if (!current_user_can('manage_options')) return;
  $forms = get_option('gc3_forms',[]);
  if (isset($_POST['gc3_save'])) {
    check_admin_referer('gc3_forms');
    $id = sanitize_title_with_dashes($_POST['form_id'] ?? 'default');
    $title = sanitize_text_field($_POST['form_title'] ?? '');
    $json = wp_unslash($_POST['form_json'] ?? '');
    $forms[$id] = ['title'=>$title?:$id, 'json'=>$json];
    update_option('gc3_forms',$forms,false);
    echo '<div class="updated"><p>저장되었습니다.</p></div>';
  }
  echo '<div class="wrap"><h1>질문/폼 관리</h1><form method="post">';
  wp_nonce_field('gc3_forms');
  $current = $forms['default'] ?? ['title'=>'기본 체크리스트','json'=>''];
  echo '<p><label>폼 ID: <input name="form_id" value="default"/></label></p>';
  echo '<p><label>폼 제목: <input name="form_title" value="'.esc_attr($current['title']).'" style="width:320px"/></label></p>';
  echo '<p><textarea name="form_json" rows="18" style="width:100%;font-family:ui-monospace">'.esc_textarea($current['json']).'</textarea></p>';
  echo '<p><button class="button button-primary" name="gc3_save" value="1">저장</button></p>';
  echo '<h2>등록된 폼</h2><ul>';
  foreach($forms as $k=>$v){
    echo '<li><code>'.$k.'</code> — '.esc_html($v['title']).' — 숏코드: <code>[growth_checklist id="'.$k.'"]</code></li>';
  }
  echo '</ul></form></div>';
}
// includes/admin-forms.php 파일 맨 아래에 추가
add_action('admin_post_gc3_preview_form', function(){
  if (!current_user_can('manage_options')) wp_die('권한 없음');
  $fid = sanitize_text_field($_GET['form'] ?? 'default');
  // 간단 프리뷰 페이지
  echo '<!doctype html><html><head><meta charset="utf-8"><title>폼 미리보기: '.esc_html($fid).'</title>';
  wp_head();
  echo '</head><body style="padding:24px;max-width:980px;margin:0 auto;background:#f8fafc">';
  echo do_shortcode('[growth_checklist id="'.esc_attr($fid).'"]');
  wp_footer();
  echo '</body></html>';
  exit;
});
