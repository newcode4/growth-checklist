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
    <th>시각</th><th>이름</th><th>이메일</th><th>휴대폰</th>
    <th>회사</th><th>업종</th><th>직원수</th><th>유입</th>
    <th>참조 결과</th><th>폼</th>
  </tr></thead><tbody>';

  

  foreach($consults as $row){
    $u = !empty($row['user']) ? get_user_by('id',$row['user']) : null;
    $name = $u ? (get_user_meta($u->ID,'first_name',true) ?: $u->display_name) : ($row['name'] ?? '');
    $email= $u ? $u->user_email : ($row['email'] ?? '');
    $phone= $u ? get_user_meta($u->ID,'phone',true) : ($row['phone'] ?? '');
    $form = $row['form'] ?? 'default';

    // 신규 메타
    $company = $u ? get_user_meta($u->ID,'company_name',true) : ($row['company_name'] ?? '');
    $industry= $u ? get_user_meta($u->ID,'industry',true)     : ($row['industry'] ?? '');
    $emps   = $u ? get_user_meta($u->ID,'employees',true)     : ($row['employees'] ?? '');
    $source = $u ? get_user_meta($u->ID,'source',true)        : ($row['source'] ?? '');
    $src_o  = $u ? get_user_meta($u->ID,'source_other',true)  : ($row['source_other'] ?? '');

    // (각 행 출력부에서 view_url 사용)
    $view_url = $row['view_url'] ?? '';
    if(!$view_url && !empty($row['ref']) && !empty($row['token'])){
      $view_url = add_query_arg(['gc_view'=>$row['ref'],'token'=>$row['token']], home_url('/'));
    }

    if($s){
      $needle = $name.' '.$email.' '.$phone.' '.$company;
      if(stripos($needle,$s)===false) continue;
    }

    $link = $u ? admin_url('user-edit.php?user_id='.$u->ID) : '#';

    echo '<tr>';
    echo '<td>'.esc_html($row['t']).'</td>';
    echo '<td><a href="'.esc_url($link).'"><b>'.esc_html($name).'</b></a></td>';
    echo '<td>'.esc_html($email).'</td>';
    echo '<td>'.esc_html($phone).'</td>';
    echo '<td>'.esc_html($company ?: '—').'</td>';
    echo '<td>'.esc_html($industry ?: '—').'</td>';
    echo '<td>'.esc_html($emps ?: '—').'</td>';
    echo '<td>'.esc_html($source.($src_o?": $src_o":'')).'</td>';
    echo '<td>'.esc_html($form).'</td>';
    echo '<td>';
    if($view_url){
      echo '<a class="button button-small" target="_blank" href="'.esc_url($view_url).'">보기</a>';
    } else {
      echo '—';
    }
    echo '</td>';
    echo '</tr>';
      }

  if(!$consults) echo '<tr><td colspan="9">데이터가 없습니다.</td></tr>';
  echo '</tbody></table>';

  echo '</div>';
}
