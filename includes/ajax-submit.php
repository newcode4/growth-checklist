<?php
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

/* 결과 제출 → 결과페이지 링크 발급 */
add_action('wp_ajax_gc_submit','gc_submit');
add_action('wp_ajax_nopriv_gc_submit','gc_submit');
function gc_submit(){
  $form   = sanitize_text_field($_POST['form'] ?? 'default');
  $score   = intval($_POST['score'] ?? 0);
  $band    = sanitize_text_field($_POST['band'] ?? '');
  $answers = wp_unslash($_POST['answers'] ?? '{}');

  $id = time().wp_rand(1000,9999);
  $token = wp_generate_password(16,false,false);
  set_transient("gc_v3_$id", ['score'=>$score,'band'=>$band,'answers'=>$answers,'token'=>$token,'ts'=>current_time('mysql')  ,'form'=>$form ], 60*60*24*90);

  $stat = get_option('gc3_stats', ['views'=>[],'submits'=>[],'consults'=>[]]);
  $stat['submits'][] = ['t'=>current_time('mysql'),'id'=>$id ,'form'=>$form];
  update_option('gc3_stats',$stat,false);

  wp_mail(get_option('admin_email'),'[체크리스트] 새 제출', "점수: {$score}/50 ({$band})\n보기: ".home_url("/?gc_view=$id&token=$token"), ['Content-Type:text/plain; charset=UTF-8']);

  wp_send_json_success(['redirect'=> add_query_arg(['gc_view'=>$id,'token'=>$token], home_url('/')) ]);
}

/* 결과 페이지에서 상담 신청(가입/중복검사/통계) */
add_action('wp_ajax_gc_consult_signup','gc_consult_signup');
add_action('wp_ajax_nopriv_gc_consult_signup','gc_consult_signup');
function gc_consult_signup(){
  $name  = sanitize_text_field($_POST['name'] ?? '');
  $email = sanitize_email($_POST['email'] ?? '');
  $phone = preg_replace('/\D/','', $_POST['phone'] ?? ''); // 숫자만
  $timepref = sanitize_text_field($_POST['contact_time'] ?? ''); // ★ 연락 가능 시간
  $ref   = sanitize_text_field($_POST['ref'] ?? '');

  if(!$name||!$email||!$phone) wp_send_json_error(['msg'=>'이름/이메일/휴대폰은 필수입니다.'],400);
  if(!preg_match('/^010\d{8}$/',$phone)) wp_send_json_error(['msg'=>'휴대폰은 010으로 시작하는 숫자 11자리여야 합니다.'],400); // ★ 포맷 강제

  if (email_exists($email)) wp_send_json_error(['msg'=>'이미 사용 중인 이메일입니다. 다른 이메일을 입력해주세요.'],409);
  $dup = get_users(['meta_key'=>'phone','meta_value'=>$phone,'number'=>1]);
  if(!empty($dup)) wp_send_json_error(['msg'=>'이미 사용 중인 휴대폰 번호입니다. 다른 번호를 입력해주세요.'],409);

  /* 가입/로그인 처리 동일 */
  update_user_meta($user_id,'phone',$phone);
  if($timepref) update_user_meta($user_id,'contact_time',$timepref); // ★ 저장

  // 제출 결과 끌어와 메타 누적(동일)
  $data = get_transient("gc_v3_$ref");
  $form = is_array($data) && !empty($data['form']) ? $data['form'] : 'default'; // ★ 폼ID
  /* 결과 저장 동일 */

  // 🔹 회원 생성 (없으면)
    $username = sanitize_user(current(explode('@',$email)));
    if (username_exists($username)) {
        $username .= '_'.wp_generate_password(4,false,false);
    }
    $password = wp_generate_password(12,true,false);
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        wp_send_json_error(['msg'=>'회원가입 실패: '.$user_id->get_error_message()],500);
    }

    // 🔹 메타 저장
    update_user_meta($user_id,'first_name',$name);
    update_user_meta($user_id,'phone',$phone);
    if($timepref) update_user_meta($user_id,'contact_time',$timepref);

    // 🔹 통계 저장 (user_id 포함)
    $stat = get_option('gc3_stats',['views'=>[],'submits'=>[],'consults'=>[]]);
    $stat['consults'][] = [
      't'=>current_time('mysql'),
      'user'=>$user_id,
      'ref'=>$ref,
      'form'=>$form,
      'contact_time'=>$timepref,
      'name'=>$name,     // fallback 저장
      'email'=>$email,
      'phone'=>$phone
    ];
    update_option('gc3_stats',$stat,false);


  // 메일에 연락가능시간 포함
  $headers=['Content-Type:text/plain; charset=UTF-8'];
  wp_mail(get_option('admin_email'),'[체크리스트] 무료 진단 콜 신청',
    "이름: {$name}\n이메일: {$email}\n휴대폰: {$phone}\n연락 가능 시간: {$timepref}\n참조 결과: ".home_url("/?gc_view={$ref}"),
    $headers);

  wp_send_json_success(['redirect'=>home_url('/thank-you/')]);
}

