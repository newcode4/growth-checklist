<?php
// includes/admin-forms.php
if (!defined('ABSPATH')) exit;

/**
 * 관리자 메뉴 등록
 */
add_action('admin_menu', function(){
  // 상위 메뉴가 'gc3_crm'인 것으로 가정. 다르면 맞춰 변경.
  add_submenu_page(
    'gc3_crm',
    '질문/폼 관리',
    '질문/폼 관리',
    'manage_options',
    'gc3_forms',
    'gc3_admin_forms'
  );
});

/**
 * 폼 관리 화면
 */
function gc3_admin_forms(){
  if (!current_user_can('manage_options')) return;

  // 현재 편집 대상 폼 ID
  $edit_id = sanitize_title_with_dashes($_GET['form'] ?? 'default');

  // 저장 처리
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
          // 정렬 안전망
          $b['min'] = intval($b['min']);
          $b['max'] = intval($b['max']);
          $bands[] = $b;
        }
        if ($ok) {
          // 점수구간 겹침 최소 검사(선택)
          usort($bands, function($a,$b){ return ($a['min'] <=> $b['min']); });
        }
      }
    }

    // 점수대별 결과 콘텐츠(results) 파싱
    $results_in = $_POST['results'] ?? [];
    $results = [];
    if (is_array($results_in)) {
      foreach ($results_in as $band_key => $row) {
        $band_key = sanitize_text_field($band_key);
        $summary  = wp_kses_post($row['summary'] ?? '');
        $program  = sanitize_text_field($row['program'] ?? '');

        // 줄바꿈 → 배열
        $intro_paras = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', wp_unslash($row['intro_paras'] ?? ''))));
        $problems    = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', wp_unslash($row['problems'] ?? ''))));
        $actions     = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', wp_unslash($row['actions'] ?? ''))));
        $event_paras = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', wp_unslash($row['event_paras'] ?? ''))));

        $results[$band_key] = [
          'summary'     => $summary,
          'intro_paras' => $intro_paras,
          'problems'    => $problems,
          'actions'     => $actions,
          'program'     => $program,
          'event_paras' => $event_paras,
        ];
      }
    }

    $forms = get_option('gc3_forms',[]);
    $forms[$id] = [
      'title'   => $title ?: $id,
      'json'    => $json,
      'bands'   => $bands,
      'results' => $results,
    ];
    update_option('gc3_forms',$forms,false);

    // 저장 후 해당 폼을 계속 편집
    $edit_id = $id;
    echo '<div class="updated"><p>저장되었습니다.</p></div>';
  }

  // 데이터 로드
  $forms = get_option('gc3_forms', []);
  $current = $forms[$edit_id] ?? ['title'=>$edit_id,'json'=>'','bands'=>[], 'results'=>[]];
  $bands  = $current['bands'] ?? [];
  $results = $current['results'] ?? [];

  // bands pretty 템플릿
  $bands_pretty = $bands
    ? json_encode($bands, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)
    : "[\n  {\"key\":\"위험 단계\",\"min\":0,\"max\":15,\"page_id\":0},\n  {\"key\":\"성장 정체 단계\",\"min\":16,\"max\":30,\"page_id\":0},\n  {\"key\":\"성장 가속 단계\",\"min\":31,\"max\":50,\"page_id\":0}\n]";

  // 리스트 → 텍스트 변환 헬퍼
  $to_text = function($arr){ return esc_textarea(implode("\n",(array)$arr)); };

  ?>
  <div class="wrap">
    <h1>질문/폼 관리</h1>

    <div style="margin:14px 0 18px;padding:12px;background:#fff;border:1px solid #e5e7eb;border-radius:8px">
      <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="page" value="gc3_forms">
        <label>편집할 폼 선택:
          <select name="form">
            <?php
            $keys = array_keys($forms);
            if (!in_array($edit_id,$keys)) array_unshift($keys,$edit_id);
            $keys = array_unique($keys);
            foreach ($keys as $k){
              echo '<option value="'.esc_attr($k).'" '.selected($k,$edit_id,false).'>'.esc_html($k).'</option>';
            }
            ?>
          </select>
        </label>
        <button class="button">불러오기</button>
        <span style="margin-left:12px;color:#475569">새 폼을 만들려면 아래 “폼 ID”에 새 슬러그를 입력하고 저장하세요.</span>
      </form>
    </div>

    <form method="post">
      <?php wp_nonce_field('gc3_forms'); ?>

      <h2>기본 정보</h2>
      <p><label>폼 ID(슬러그): <input name="form_id" value="<?php echo esc_attr($edit_id); ?>" style="width:240px"/></label></p>
      <p><label>폼 제목: <input name="form_title" value="<?php echo esc_attr($current['title'] ?? $edit_id); ?>" style="width:420px"/></label></p>

      <h2>질문 JSON</h2>
      <p><textarea name="form_json" rows="16" style="width:100%;font-family:ui-monospace"><?php echo esc_textarea($current['json']); ?></textarea></p>

      <hr>
      <h2>점수 구간/결과 매핑 (bands)</h2>
      <p class="description">예) [{"key":"위험 단계","min":0,"max":15,"page_id":123},{"key":"성장 정체 단계","min":16,"max":30,"page_id":124},{"key":"성장 가속 단계","min":31,"max":50,"page_id":125}]</p>
      <p><textarea name="form_bands" rows="10" style="width:100%;font-family:ui-monospace"><?php echo esc_textarea($bands_pretty); ?></textarea></p>

      <hr>
      <h2>점수대별 결과 콘텐츠</h2>
      <p class="description">밴드별 문구를 입력하세요. 리스트는 한 줄에 하나씩.</p>

      <?php if (empty($bands)) : ?>
        <p>먼저 상단의 <b>bands</b>를 저장하세요. 저장 후 이 영역이 생성됩니다.</p>
      <?php else: ?>
        <div style="display:grid;gap:24px">
          <?php foreach ($bands as $b):
            $k = $b['key'] ?? '';
            $r = $results[$k] ?? ['summary'=>'','intro_paras'=>[],'problems'=>[],'actions'=>[],'program'=>'','event_paras'=>[]];
          ?>
            <fieldset style="border:1px solid #e5e7eb;padding:16px;border-radius:8px;background:#fff">
              <legend style="font-weight:700"><?php echo esc_html($k); ?> (<?php echo intval($b['min']); ?>~<?php echo intval($b['max']); ?>)</legend>

              <p><label>핵심 요약<br>
                <textarea name="results[<?php echo esc_attr($k); ?>][summary]" rows="3" style="width:100%"><?php echo esc_textarea($r['summary'] ?? ''); ?></textarea>
              </label></p>

              <p><label>인트로 단락(한 줄=한 문단)<br>
                <textarea name="results[<?php echo esc_attr($k); ?>][intro_paras]" rows="5" style="width:100%"><?php echo $to_text($r['intro_paras'] ?? []); ?></textarea>
              </label></p>

              <p><label>자주 겪는 문제(한 줄=한 항목)<br>
                <textarea name="results[<?php echo esc_attr($k); ?>][problems]" rows="5" style="width:100%"><?php echo $to_text($r['problems'] ?? []); ?></textarea>
              </label></p>

              <p><label>지금 바로 손댈 포인트(한 줄=한 항목)<br>
                <textarea name="results[<?php echo esc_attr($k); ?>][actions]" rows="5" style="width:100%"><?php echo $to_text($r['actions'] ?? []); ?></textarea>
              </label></p>

              <p><label>프로그램/한 줄 설명<br>
                <input type="text" name="results[<?php echo esc_attr($k); ?>][program]" value="<?php echo esc_attr($r['program'] ?? ''); ?>" style="width:100%">
              </label></p>

              <p><label>콜 안내 문단(한 줄=한 문단)<br>
                <textarea name="results[<?php echo esc_attr($k); ?>][event_paras]" rows="4" style="width:100%"><?php echo $to_text($r['event_paras'] ?? []); ?></textarea>
              </label></p>
            </fieldset>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p style="margin-top:18px">
        <button class="button button-primary" name="gc3_save" value="1">저장</button>
      </p>

      <hr>
      <h2>등록된 폼</h2>
      <ul>
        <?php foreach ($forms as $k=>$v): ?>
          <li>
            <code><?php echo esc_html($k); ?></code>
            — <?php echo esc_html($v['title'] ?? $k); ?>
            — 숏코드: <code>[growth_checklist id="<?php echo esc_attr($k); ?>"]</code>
            — <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=gc3_forms&form='.$k) ); ?>">편집</a>
            — <a class="button button-small" target="_blank" href="<?php echo esc_url( admin_url('admin-post.php?action=gc3_preview_form&form='.$k) ); ?>">미리보기</a>
          </li>
        <?php endforeach; ?>
      </ul>
    </form>
  </div>
  <?php
}

/**
 * 폼 미리보기 (숏코드 렌더)
 */
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
