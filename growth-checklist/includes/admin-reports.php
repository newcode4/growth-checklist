<?php
// includes/admin-reports.php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
  add_submenu_page('gc3_crm','제출 통계','제출 통계','manage_options','gc3_reports','gc3_admin_reports');
});

function gc3_admin_reports(){
  if(!current_user_can('manage_options')) return;

  $forms = get_option('gc3_forms', []);
  $stats = get_option('gc3_stats', ['views'=>[],'submits'=>[],'consults'=>[]]);

  $form  = sanitize_text_field($_GET['form'] ?? 'default');
  $hash  = sanitize_text_field($_GET['hash'] ?? '');

  // form/version 선택 UI
  echo '<div class="wrap"><h1>제출 통계</h1>';
  echo '<form method="get" style="margin:8px 0 14px;display:flex;gap:8px;align-items:center">';
  echo '<input type="hidden" name="page" value="gc3_reports">';
  echo '<label>폼 <select name="form">';
  foreach($forms as $fid=>$meta){ echo '<option '.selected($form,$fid,false).' value="'.esc_attr($fid).'">'.esc_html($fid).'</option>'; }
  echo '</select></label>';

  // 사용 가능한 해시 목록(해당 폼의 제출에서 수집)
  $hashes = array_values(array_unique(array_map(function($r) use($form){
    return ($r['form']??'')===$form ? ($r['form_hash']??'') : '';
  }, $stats['submits'] ?? [])));
  echo '<label>버전 <select name="hash"><option value="">모두</option>';
  foreach($hashes as $h){ if(!$h) continue; echo '<option '.selected($hash,$h,false).' value="'.esc_attr($h).'">'.esc_html(substr($h,0,10)).'</option>'; }
  echo '</select></label>';
  submit_button('보기', 'secondary', '', false);
  echo '</form>';

  // 집계
  $form_json = $forms[$form]['json'] ?? '';
  $struct = $form_json ? json_decode($form_json,true) : ['sections'=>[]];

  $agg = []; // id => ['q'=>..., 'counts'=>[0,1,3], 'avg'=>...]
  foreach(($struct['sections']??[]) as $sec){
    foreach(($sec['items']??[]) as $it){
      $agg[$it['id']] = ['q'=>$it['q'],'counts'=>[0=>0,1=>0,3=>0],'sum'=>0,'n'=>0];
    }
  }

  foreach(($stats['submits'] ?? []) as $row){
    if(($row['form']??'')!==$form) continue;
    if($hash && ($row['form_hash']??'')!==$hash) continue;

    $ans = json_decode($row['answers'] ?? '{}', true);
    $a = $ans['answers'] ?? [];
    foreach($a as $id=>$v){
      if(!isset($agg[$id])) continue; // 폼이 바뀌어 빠진 질문은 무시
      $v = is_numeric($v) ? intval($v) : null;
      if($v===0 || $v===1 || $v===3){
        $agg[$id]['counts'][$v] += 1;
        $agg[$id]['sum'] += $v;
        $agg[$id]['n']   += 1;
      }
    }
  }

  // 출력
  echo '<h2>질문별 분포/평균</h2>';
  echo '<table class="widefat fixed striped"><thead><tr><th>질문</th><th>예(3)</th><th>부분(1)</th><th>아니오(0)</th><th>응답수</th><th>평균</th></tr></thead><tbody>';
  foreach($agg as $id=>$row){
    $n = $row['n']; $avg = $n ? round($row['sum']/$n,2) : 0;
    echo '<tr>';
    echo '<td>'.esc_html($row['q']).' <span style="color:#64748b">(#'.esc_html($id).')</span></td>';
    echo '<td>'.intval($row['counts'][3]).'</td>';
    echo '<td>'.intval($row['counts'][1]).'</td>';
    echo '<td>'.intval($row['counts'][0]).'</td>';
    echo '<td>'.intval($n).'</td>';
    echo '<td>'.esc_html($avg).'</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';

  echo '</div>';
}
