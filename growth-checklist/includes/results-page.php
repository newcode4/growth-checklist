<?php
if (!defined('ABSPATH')) exit;

add_action('template_redirect', function(){
  if (!isset($_GET['gc_view'], $_GET['token'])) return;
  $id = sanitize_text_field($_GET['gc_view']);
  $token = sanitize_text_field($_GET['token']);
  $data = get_transient("gc_v3_$id");
  if (!$data || !hash_equals($data['token'],$token)) { status_header(403); wp_die('유효하지 않은 링크입니다.'); }

  $score = intval($data['score']);
  [$band,$band_msg] = gc3_band_text($score);

  wp_enqueue_style('gc3-results', GC3_URL.'public/css/results.css', [], GC3_VER);
  wp_enqueue_script('gc3-results-js', GC3_URL.'public/js/results.js', [], GC3_VER, true);
  wp_localize_script('gc3-results-js','GC3_RESULTS',[
    'ajax'=>admin_url('admin-ajax.php'),
    'ref'=>$id
  ]);

  get_header(); ?>
  <main class="gc-container">
    <section class="gc-sticky">
      <div class="gc-sticky-head">
        <h1>진단 결과</h1>
        <span class="gc-chip">총점 <b><?php echo $score; ?></b>/50</span>
      </div>
      <div class="gc-bar"><span style="width:<?php echo round($score/50*100); ?>%"></span></div>
      <div class="gc-sub">상태:
        <b class="gc-band <?php echo ($score<=15?'bad':($score<=30?'mid':'good')); ?>"><?php echo esc_html($band); ?></b>
      </div>
    </section>

    <section class="gc-card"><h2>핵심 요약</h2><p><?php echo esc_html($band_msg); ?></p></section>

    <?php
      // 상태별 카피 업그레이드
      if($score<=15){
        $summary = '핵심 페이지의 메시지·신뢰·행동 유도가 분산되어 전환이 발생하기 어려운 상태입니다.';
        $bullets = [
          '첫 화면: “문제–약속–증거–행동(CTA)” 4요소로 재구성 (스크롤 없이 핵심 전달)',
          '상단에 신뢰 증거(수치/로고/수상) 배치하여 위험감소',
          '문의/신청 폼 필드 3개 이하로 간소화(이름/휴대폰/이메일)',
          '한 문장 가치제안(20자 이내)으로 카피 정제'
        ];
        $offer  = '응급 구조 스프린트(1주) — 랜딩 구조/카피 즉시 개선 + 빠른 실험';
      } elseif($score<=30){
        $summary = '기반은 있으나 퍼널 중간 단계에서의 이탈이 커서 성장이 정체된 상태입니다.';
        $bullets = [
          'GA4 이벤트/목표 점검 후 퍼널(유입→스크롤→CTA→제출) 리포트 구축',
          '이탈 상위 2구간에 집중한 UI/카피 실험 3건(2주)',
          '채널별 CPA/전환률 비교로 비효율 예산 20% 절감',
          '구매/신청 이후 온보딩 시퀀스(이메일/문자) 도입'
        ];
        $offer  = '병목 교정 스프린트(2주) — 퍼널 리포트 + 우선순위 3가지 실험';
      } else {
        $summary = '기반은 준비되었고, 레버리지(보증/패키지/추천)로 성장 속도를 올릴 수 있습니다.';
        $bullets = [
          '오퍼/가격/보증 테스트로 객단가 및 전환 동시 개선',
          '리퍼럴/제휴 루프 설계로 CAC 절감',
          '승자 크리에이티브 확장 전 메시지–제안–증거 일치 점검',
          '주간 리뷰/실험 로그로 실행 속도 체계화'
        ];
        $offer  = '성장 가속 프로그램(4주) — 오퍼/가격/리퍼럴 실험 설계 & 실행';
      }
    ?>
    <section class="gc-card">
      <h2>바로 하면 효과 큰 조치</h2>
      <ul><?php foreach($bullets as $b) echo '<li>'.esc_html($b).'</li>'; ?></ul>
    </section>

    <section class="gc-card">
      <h2>지금 예약 가능</h2>
      <p><b><?php echo esc_html($offer); ?></b></p>
      <p>이번 분기 <b>주 3팀 한정</b>으로 15분 무료 진단 콜을 제공합니다. 결과를 바탕으로 바로 실행 항목을 드립니다.</p>
      <form id="gc-consult" class="gc-form" onsubmit="return false">
      <input type="text"  name="name"  placeholder="이름(필수)" required>
      <input type="email" name="email" placeholder="이메일(필수)" required>
      <!-- 010으로 시작 + 숫자 8개 = 총 11자리. 모바일 키패드 유도 -->
      <input type="tel"   name="phone"
            placeholder="휴대폰(예: 01012345678)"
            pattern="^010\d{8}$" inputmode="numeric" maxlength="11" required>
      <input type="text"  name="contact_time"
            placeholder="연락 가능 시간(예: 평일 09~12시)" aria-label="연락 가능 시간">
      <button class="gc-btn" id="gc-consult-btn">15분 무료 진단 콜 예약</button>
      <div class="gc-hint" id="gc-hint">제출 시 계정이 생성되고 결과가 저장됩니다.</div>
    </form>

    </section>
  </main>
  <?php get_footer(); exit;
});
