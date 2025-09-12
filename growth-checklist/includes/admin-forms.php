<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
  add_submenu_page('gc3_crm','질문/폼 관리','질문/폼 관리','manage_options','gc3_forms','gc3_admin_forms');
});

function gc3_admin_forms(){
  if (!current_user_can('manage_options')) return;

  // 저장 로직
  if (isset($_POST['gc3_save'])) {
    check_admin_referer('gc3_forms');

    $id    = sanitize_title_with_dashes($_POST['form_id'] ?? 'default');
    $title = sanitize_text_field($_POST['form_title'] ?? '');
    $json  = wp_unslash($_POST['form_json'] ?? '');

    // bands JSON 파싱
    $bands_json = wp_unslash($_POST['form_bands'] ?? '');
    $bands = [];
    if ($bands_json) {
      $tmp = json_decode($bands_json, true);
      if (is_array($tmp)) {
        $ok = true;
        foreach ($tmp as $b) {
          if (!isset($b['key'],$b['min'],$b['max']) || !is_numeric($b['min']) || !is_numeric($b['max'])) { $ok=false; break; }
        }
        if ($ok) $bands = $tmp;
      }
    }

    $forms = get_option('gc3_forms',[]);
    $forms[$id] = [
      'title' => $title ?: $id,
      'json'  => $json,
      'bands' => $bands,
    ];
    update_option('gc3_forms',$forms,false);

    echo '<div class="updated"><p>저장되었습니다.</p></div>';
  }

  // 렌더: 현재 폼 불러오기
  $forms = get_option('gc3_forms', []);
  $current = $forms['default'] ?? ['title'=>'기본 체크리스트','json'=>'','bands'=>[]];
  $bands_pretty = $current['bands']
    ? json_encode($current['bands'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)
    : "[\n  {\"key\":\"위험 단계\",\"min\":0,\"max\":15,\"page_id\":0},\n  {\"key\":\"성장 정체 단계\",\"min\":16,\"max\":30,\"page_id\":0},\n  {\"key\":\"성장 가속 단계\",\"min\":31,\"max\":50,\"page_id\":0}\n]";

  ?>
  <div class="wrap">
    <h1>질문/폼 관리</h1>
    <form method="post">
      <?php wp_nonce_field('gc3_forms'); ?>
      <p><label>폼 ID: <input name="form_id" value="default"/></label></p>
      <p><label>폼 제목: <input name="form_title" value="<?php echo esc_attr($current['title'] ?? ''); ?>" style="width:320px"/></label></p>
      <p><label>질문 JSON</label></p>
      <p><textarea name="form_json" rows="18" style="width:100%;font-family:ui-monospace"><?php echo esc_textarea($current['json']); ?></textarea></p>

      <hr>
      <h2>점수 구간/결과 매핑 (bands)</h2>
      <p class="description">예) [{"key":"위험 단계","min":0,"max":15,"page_id":123},{"key":"성장 정체 단계","min":16,"max":30,"page_id":124},{"key":"성장 가속 단계","min":31,"max":50,"page_id":125}]</p>
      <p><textarea name="form_bands" rows="10" style="width:100%;font-family:ui-monospace"><?php echo esc_textarea($bands_pretty); ?></textarea></p>

      <p><button class="button button-primary" name="gc3_save" value="1">저장</button></p>

      <h2>등록된 폼</h2>
      <ul>
        <?php foreach ($forms as $k=>$v): ?>
          <li>
            <code><?php echo esc_html($k); ?></code> — <?php echo esc_html($v['title'] ?? $k); ?>
            — 숏코드: <code>[growth_checklist id="<?php echo esc_attr($k); ?>"]</code>
            — <a class="button button-small" target="_blank" href="<?php echo esc_url( admin_url('admin-post.php?action=gc3_preview_form&form='.$k) ); ?>">미리보기</a>
          </li>
        <?php endforeach; ?>
      </ul>
    </form>
  </div>
  <?php
}

// 미리보기 액션 (함수 바깥에 두는 건 OK)
add_action('admin_post_gc3_preview_form', function(){
  if (!current_user_can('manage_options')) wp_die('권한 없음');
  $fid = sanitize_text_field($_GET['form'] ?? 'default');
  echo '<!doctype html><html><head><meta charset="utf-8"><title>폼 미리보기: '.esc_html($fid).'</title>';
  wp_head();
  echo '</head><body style="padding:24px;max-width:980px;margin:0 auto;background:#f8fafc">';
  echo do_shortcode('[growth_checklist id="'.esc_attr($fid).'"]');
  wp_footer();
  echo '</body></html>';
  exit;
});
