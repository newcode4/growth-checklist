<?php
// ajax-submit.php (교체)
if (!defined('ABSPATH')) exit;

/* 유입 트래킹 */
add_action('wp_ajax_gc3_track_view','gc3_track_view');
add_action('wp_ajax_nopriv_gc3_track_view','gc3_track_view');
function gc3_track_view(){
  $form = sanitize_text_field($_POST['form'] ?? 'default');
  $stat = get_option('gc3_stats', ['views'=>[],'submits'=>[],'consults'=>[]]);
  $stat['views'][] = ['t'=>current_time('mysql'),'form'=>$form,'ip'=>$_SERVER['REMOTE_ADDR']??''];
  update_option('gc3_stats',$stat,false);
  wp_send_json_success();
}

/* 결과 제출 → 링크 발급 */
add_action('wp_ajax_gc_submit','gc_submit');
add_action('wp_ajax_nopriv_gc_submit','gc_submit');
function gc_submit(){
  $form    = sanitize_text_field($_POST['form'] ?? 'default');
  $score   = intval($_POST['score'] ?? 0);
  $band    = sanitize_text_field($_POST['band'] ?? '');
  $answers = wp_unslash($_POST['answers'] ?? '{}');

  $id = time().wp_rand(1000,9999);
  $token = wp_generate_password(16,false,false);
  set_transient("gc_v3_$id", [
    'score'=>$score, 'band'=>$band, 'answers'=>$answers, 'token'=>$token,
    'ts'=>current_time('mysql'), 'form'=>$form
  ], 60*60*24*90);

  $stat = get_option('gc3_stats', ['views'=>[],'submits'=>[],'consults'=>[]]);
  $stat['submits'][] = ['t'=>current_time('mysql'),'id'=>$id ,'form'=>$form];
  update_option('gc3_stats',$stat,false);

  wp_mail(get_option('admin_email'),'[체크리스트] 새 제출', "점수: {$score}/50 ({$band})\n보기: ".home_url("/?gc_view=$id&token=$token"), ['Content-Type:text/plain; charset=UTF-8']);

  wp_send_json_success(['redirect'=> add_query_arg(['gc_view'=>$id,'token'=>$token], home_url('/')) ]);
}

/* 결과 페이지에서 상담 신청 */
add_action('wp_ajax_gc_consult_signup','gc_consult_signup');
add_action('wp_ajax_nopriv_gc_consult_signup','gc_consult_signup');
function gc_consult_signup(){
  // 기본
  $name   = sanitize_text_field($_POST['name'] ?? '');
  $email  = sanitize_email($_POST['email'] ?? '');
  $phone  = preg_replace('/\D/','', $_POST['phone'] ?? '');
  $timepref = sanitize_text_field($_POST['contact_time'] ?? '');

  // 비즈니스 확장 필드
  $site_url    = esc_url_raw($_POST['site_url'] ?? '');
  $company_nm  = sanitize_text_field($_POST['company_name'] ?? '');
  $industry    = sanitize_text_field($_POST['industry'] ?? '');
  $employees   = sanitize_text_field($_POST['employees'] ?? '');
  $cofounder   = sanitize_text_field($_POST['cofounder'] ?? '');
  $company_age = sanitize_text_field($_POST['company_age'] ?? '');
  $company_url = esc_url_raw($_POST['company_url'] ?? '');
  $source      = sanitize_text_field($_POST['source'] ?? '');
  $source_other= sanitize_text_field($_POST['source_other'] ?? '');
  $ref   = sanitize_text_field($_POST['ref'] ?? '');

  // 필수 체크
  if(!$name||!$email||!$phone) wp_send_json_error(['msg'=>'이름/이메일/휴대폰은 필수입니다.'],400);
  if(!preg_match('/^010\d{8}$/',$phone)) wp_send_json_error(['msg'=>'휴대폰은 010으로 시작하는 숫자 11자리여야 합니다.'],400);

  if(!$site_url || !filter_var($site_url, FILTER_VALIDATE_URL)) wp_send_json_error(['msg'=>'유효한 홈페이지 URL을 입력해 주세요.'],400);
  if(!$company_nm)  wp_send_json_error(['msg'=>'회사 상호를 입력해 주세요.'],400);
  if(!$industry)    wp_send_json_error(['msg'=>'업종을 선택해 주세요.'],400);
  if(!$employees)   wp_send_json_error(['msg'=>'직원 수를 선택해 주세요.'],400);
  if(!$cofounder)   wp_send_json_error(['msg'=>'공동대표 유무를 선택해 주세요.'],400);
  if(!$company_age) wp_send_json_error(['msg'=>'회사 연차를 선택해 주세요.'],400);
  if(!$source)      wp_send_json_error(['msg'=>'유입 경로를 선택해 주세요.'],400);
  if($source==='other' && strlen($source_other)<2) wp_send_json_error(['msg'=>'유입 경로의 기타 내용을 적어 주세요.'],400);
  if($company_url && !filter_var($company_url, FILTER_VALIDATE_URL)) wp_send_json_error(['msg'=>'회사/서비스 추가 URL 형식을 확인해 주세요.'],400);

  // 중복 검사
  if (email_exists($email)) wp_send_json_error(['msg'=>'이미 사용 중인 이메일입니다. 다른 이메일을 입력해주세요.'],409);
  $dup = get_users(['meta_key'=>'phone','meta_value'=>$phone,'number'=>1]);
  if(!empty($dup)) wp_send_json_error(['msg'=>'이미 사용 중인 휴대폰 번호입니다. 다른 번호를 입력해주세요.'],409);

  // 회원 생성
  $username = sanitize_user(current(explode('@',$email)));
  if (username_exists($username)) $username .= '_'.wp_generate_password(4,false,false);
  $password = wp_generate_password(12,true,false);
  $user_id  = wp_create_user($username, $password, $email);
  if (is_wp_error($user_id)) wp_send_json_error(['msg'=>'회원가입 실패: '.$user_id->get_error_message()],500);

  // 메타 저장
  update_user_meta($user_id,'first_name',$name);
  update_user_meta($user_id,'phone',$phone);
  if($timepref)    update_user_meta($user_id,'contact_time',$timepref);

  update_user_meta($user_id,'site_url',$site_url);
  update_user_meta($user_id,'company_name',$company_nm);
  update_user_meta($user_id,'industry',$industry);
  update_user_meta($user_id,'employees',$employees);
  update_user_meta($user_id,'cofounder',$cofounder); // yes/no
  update_user_meta($user_id,'company_age',$company_age);
  if($company_url) update_user_meta($user_id,'company_url',$company_url);
  update_user_meta($user_id,'source',$source);
  if($source_other) update_user_meta($user_id,'source_other',$source_other);

  // 제출 결과 끌어와 폼 ID
  $data = get_transient("gc_v3_$ref");
  $form = (is_array($data) && !empty($data['form'])) ? $data['form'] : 'default';

  // 통계 저장
  $stat = get_option('gc3_stats',['views'=>[],'submits'=>[],'consults'=>[]]);
  $stat['consults'][] = [
    't'=>current_time('mysql'),
    'user'=>$user_id,
    'ref'=>$ref,
    'form'=>$form,
    'contact_time'=>$timepref,
    'name'=>$name,
    'email'=>$email,
    'phone'=>$phone,
    // 신규 필드도 기록(요약)
    'site_url'=>$site_url,
    'company_name'=>$company_nm,
    'industry'=>$industry,
    'employees'=>$employees,
    'cofounder'=>$cofounder,
    'company_age'=>$company_age,
    'company_url'=>$company_url,
    'source'=>$source,
    'source_other'=>$source_other,
  ];
  update_option('gc3_stats',$stat,false);

  // 알림 메일
  $lines = [
    "이름: {$name}",
    "이메일: {$email}",
    "휴대폰: {$phone}",
    "연락 가능 시간: ".($timepref ?: '—'),
    "홈페이지 URL: {$site_url}",
    "회사 상호: {$company_nm}",
    "업종: {$industry}",
    "직원 수: {$employees}",
    "공동대표: ".($cofounder==='yes'?'있음':'없음'),
    "회사 연차: {$company_age}",
    "회사/서비스 추가 URL: ".($company_url ?: '—'),
    "유입 경로: {$source}".($source==='other'?" ({$source_other})":''),
    "참조 결과: ".home_url("/?gc_view={$ref}"),
  ];
  wp_mail(get_option('admin_email'),'[체크리스트] 무료 진단 콜 신청', implode("\n",$lines), ['Content-Type:text/plain; charset=UTF-8']);

  wp_send_json_success(['redirect'=>home_url('/thank-you/')]);
}
