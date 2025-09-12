<?php
// admin-crm.php (교체)
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
  add_menu_page('체크리스트 CRM','체크리스트 CRM','manage_options','gc3_crm','gc3_admin_crm','dashicons-chart-line',26);
});

function gc3_admin_crm(){
  if(!current_user_can('manage_options')) return;

  // 리셋
  if(isset($_GET['gc3_reset']) && check_admin_referer('gc3_reset')){
    update_option('gc3_stats',['views'=>[],'submits'=>[],'consults'=>[]],false);
    echo '<div class="updated"><p>통계를 초기화했습니다.</p></div>';
  }

  $stat = get_option('gc3_stats', ['views'=>[],'submits'=>[],'consults'=>[]]);
  $forms = get_option('gc3_forms', []);
  $form_ids = array_keys($forms);

  // 필터
  $s   = sanitize_text_field($_GET['s']   ?? '');
  $fid = sanitize_text_field($_GET['form']?? '');
  $ds  = sanitize_text_field($_GET['ds']  ?? '');
  $de  = sanitize_text_field($_GET['de']  ?? '');

  $inRange = function($t) use($ds,$de){
    $ts = strtotime($t); if(!$ts) return true;
    if($ds && $ts < strtotime($ds.' 00:00:00')) return false;
    if($de && $ts > strtotime($de.' 23:59:59')) return false;
    return true;
  };

  // 필터링
  $views    = array_values(array_filter($stat['views'],    fn($r)=> (!$fid||($r['form']??'default')===$fid) && $inRange($r['t'])));
  $submits  = array_values(array_filter($stat['submits'],  fn($r)=> (!$fid||($r['form']??'default')===$fid) && $inRange($r['t'])));
  $consults = array_values(array_filter($stat['consults'], fn($r)=> (!$fid||($r['form']??'default')===$fid) && $inRange($r['t'])));

  // 전환율
  $v = max(1,count($views));
  $sN= count($submits);
  $cN= count($consults);
  $rate_submit  = round($sN/$v*100,1);
  $rate_consult = ($sN? round($cN/$sN*100,1) : 0);

  echo '<div class="wrap"><h1>체크리스트 CRM</h1>';

  // 필터 폼
  echo '<form method="get" style="margin:8px 0 14px;display:flex;gap:8px;align-items:center">';
  echo '<input type="hidden" name="page" value="gc3_crm"/>';
  echo '<label>시작일 <input type="date" name="ds" value="'.esc_attr($ds).'"></label>';
  echo '<label>종료일 <input type="date" name="de" value="'.esc_attr($de).'"></label>';
  echo '<label>폼 <select name="form"><option value="">전체</option>';
  foreach($form_ids as $id) echo '<option '.selected($fid,$id,false).' value="'.esc_attr($id).'">'.esc_html($id).'</option>';
  echo '</select></label>';
  echo '<label>검색 <input type="search" name="s" placeholder="이름/이메일/휴대폰/회사" value="'.esc_attr($s).'"></label>';
  submit_button('필터', 'secondary', '', false);
  echo wp_nonce_field('gc3_reset','_wpnonce',true,false);
  echo ' <a class="button" href="'.esc_url(admin_url('admin.php?page=gc3_crm')).'">초기화</a> ';
  echo ' <a class="button button-danger" href="'.esc_url(wp_nonce_url(admin_url('admin.php?page=gc3_crm&gc3_reset=1'),'gc3_reset')).'" onclick="return confirm(\'정말 초기화할까요?\')">통계 리셋</a>';
  echo '</form>';

  // 요약 카드
  echo '<div style="display:flex;gap:12px;flex-wrap:wrap">';
  $card = function($title,$value,$sub='') {
    echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;min-width:220px">';
    echo '<div style="font-weight:700">'.$title.'</div>';
    echo '<div style="font-size:26px;font-weight:800;margin:4px 0">'.$value.'</div>';
    if($sub) echo '<div style="color:#475569">'.$sub.'</div>';
    echo '</div>';
  };
  $card('유입(세션)', number_format(count($views)));
  $card('제출', number_format($sN), "전환율(유입→제출) <b>{$rate_submit}%</b>");
  $card('상담 신청/가입', number_format($cN), "전환율(제출→상담) <b>{$rate_consult}%</b>");
  echo '</div>';

  // 상담 신청자 목록
 echo '<h2 style="margin-top:18px">상담 신청자</h2>';
echo '<table class="widefat fixed striped"><thead><tr>
  <th>시각</th>
  <th>이름</th>
  <th>이메일</th>
  <th>휴대폰</th>
  <th>연락가능</th>
  <th>회사</th>
  <th>업종</th>
  <th>직원수</th>
  <th>유입</th>
  <th>참조 결과</th>
  <th>폼</th>
  <th>자세히</th>
</tr></thead><tbody>';
  

  foreach($consults as $row){
  $u = !empty($row['user']) ? get_user_by('id',$row['user']) : null;

  $name  = $u ? (get_user_meta($u->ID,'first_name',true) ?: $u->display_name) : ($row['name'] ?? '');
  $email = $u ? $u->user_email : ($row['email'] ?? '');
  $phone = $u ? get_user_meta($u->ID,'phone',true) : ($row['phone'] ?? '');
  $ct    = $row['contact_time'] ?? ($u ? get_user_meta($u->ID,'contact_time',true) : '');

  $form  = $row['form'] ?? 'default';
  $industry   = $row['industry'] ?? ($u ? get_user_meta($u->ID,'industry',true) : '');
  $employees  = $row['employees'] ?? ($u ? get_user_meta($u->ID,'employees',true) : '');
  $company    = $row['company_name'] ?? ($u ? get_user_meta($u->ID,'company_name',true) : '');
  $site_url   = $row['site_url'] ?? ($u ? get_user_meta($u->ID,'site_url',true) : '');
  $company_url= $row['company_url'] ?? ($u ? get_user_meta($u->ID,'company_url',true) : '');
  $cofounder  = $row['cofounder'] ?? ($u ? get_user_meta($u->ID,'cofounder',true) : '');
  $age_label  = $row['company_age'] ?? ($u ? get_user_meta($u->ID,'company_age',true) : '');
  $source     = $row['source'] ?? ($u ? get_user_meta($u->ID,'source',true) : '');
  $source_other = $row['source_other'] ?? ($u ? get_user_meta($u->ID,'source_other',true) : '');
  $notes      = $row['notes'] ?? ($u ? get_user_meta($u->ID,'notes',true) : '');

  // 검색 필터 유지
  if($s){
    $needle = $name.' '.$email.' '.$phone.' '.$company;
    if(stripos($needle,$s)===false) continue;
  }

  // 결과 URL(토큰 포함)
  $view_url = $row['view_url'] ?? '';
  if(!$view_url && !empty($row['ref']) && !empty($row['token'])){
    $view_url = add_query_arg(['gc_view'=>$row['ref'],'token'=>$row['token']], home_url('/'));
  }

  // 폼 보기(관리자 프리뷰)
  $forms_manage_url = admin_url('admin.php?page=gc3_forms');

  echo '<tr>';
  echo '<td>'.esc_html($row['t']).'</td>';
  $link = $u ? admin_url('user-edit.php?user_id='.$u->ID) : '#';
  echo '<td><a href="'.esc_url($link).'"><b>'.esc_html($name).'</b></a></td>';
  echo '<td>'.esc_html($email).'</td>';
  echo '<td>'.esc_html($phone).'</td>';
  echo '<td>'.esc_html($ct ?: '—').'</td>';
  echo '<td>'.esc_html($company ?: '—').'</td>';
  echo '<td>'.esc_html($industry ?: '—').'</td>';
  echo '<td>'.esc_html($employees ?: '—').'</td>';
  echo '<td>'.esc_html($source.($source==='other'&&$source_other?": $source_other":'')).'</td>';

  // 참조 결과
  echo '<td>';
  if($view_url){
    echo '<a class="button button-small" target="_blank" href="'.esc_url($view_url).'">보기</a>';
  } else { echo '—'; }
  echo '</td>';

  // 폼
  echo '<td>';
  echo '<code>'.esc_html($form).'</code> ';
  echo '<a class="button button-small" href="'.esc_url($forms_manage_url).'">편집</a>';
  echo '</td>';

  // 자세히(접힘)
  $detail = '';
  $detail .= '<div><b>홈페이지 URL:</b> '.($site_url? '<a target="_blank" href="'.esc_url($site_url).'">'.esc_html($site_url).'</a>':'—').'</div>';
  $detail .= '<div><b>회사/서비스 추가 URL:</b> '.($company_url? '<a target="_blank" href="'.esc_url($company_url).'">'.esc_html($company_url).'</a>':'—').'</div>';
  $detail .= '<div><b>공동대표:</b> '.($cofounder==='yes'?'있음':($cofounder==='no'?'없음':'—')).'</div>';
  $detail .= '<div><b>회사 연차:</b> '.( $age_label ?: '—').'</div>';
  $detail .= '<div><b>메모:</b> '.( $notes ? esc_html($notes) : '—').'</div>';

  echo '<td style="white-space:nowrap">';
  echo '<details><summary>열기</summary><div style="padding:8px 0">'.$detail.'</div></details>';
  echo '</td>';

  echo '</tr>';
}

if(!$consults) echo '<tr><td colspan="12">데이터가 없습니다.</td></tr>';
echo '</tbody></table>';

  echo '</div>';
}
