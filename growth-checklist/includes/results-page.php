<?php
// results-page.php (교체)
if (!defined('ABSPATH')) exit;

add_action('template_redirect', function () {
  if (!isset($_GET['gc_view'], $_GET['token'])) return;

  $id    = sanitize_text_field($_GET['gc_view']);
  $token = sanitize_text_field($_GET['token']);
  $data  = get_transient("gc_v3_$id");

  if (!$data || !hash_equals($data['token'], $token)) {
    status_header(403);
    wp_die('유효하지 않은 링크입니다.');
  }

  $score = intval($data['score']);
  [$band, $band_msg] = gc3_band_text($score);

  // 에셋
  wp_enqueue_style('gc3-results', GC3_URL . 'public/css/results.css', [], GC3_VER);
  wp_enqueue_script('gc3-results-js', GC3_URL . 'public/js/results.js', [], GC3_VER, true);
  wp_localize_script('gc3-results-js', 'GC3_RESULTS', [
    'ajax' => admin_url('admin-ajax.php'),
    'ref'  => $id
  ]);

  // 점수대별 콘텐츠 (기존 그대로)
  $summary = '';
  $intro_paras = [];
  $problems = [];
  $actions  = [];
  $program  = '';
  $event_paras = [];

  // 점수/구간 계산 직후 아래 추가
  $form_id = is_array($data) && !empty($data['form']) ? $data['form'] : 'default';
  $forms = get_option('gc3_forms', []);
  $current_form_json = $forms[$form_id]['json'] ?? '';
  $current_form = $current_form_json ? json_decode($current_form_json, true) : ['sections'=>[]];

  // 사용자의 응답 복원
  $answers_payload = json_decode($data['answers'] ?? '{}', true);
  $user_answers = $answers_payload['answers'] ?? [];
  $user_bonus   = $answers_payload['bonus']   ?? [];

  // 값→라벨
  function gc3_val_label($v){
    if ($v === 3 || $v === '3') return ['예', 'good'];
    if ($v === 1 || $v === '1') return ['부분적으로', 'mid'];
    if ($v === 0 || $v === '0') return ['아니오', 'bad'];
    return ['—', 'mute'];
  }

  if ($score <= 15) {
    $summary = '메시지·신뢰·CTA가 분산돼 전환이 잘 안 나는 상태입니다.';
    $intro_paras = [
      '점수가 15점 이하라면, 이제 막 시작했거나 아직 셋업이 덜 된 단계일 가능성이 큽니다. 핵심이 한 화면에 정리돼 있지 않아 방문자가 무엇을 해야 할지 헷갈립니다.',
      '간판도 가격표도 없는 가게와 비슷합니다. 지나가다 한 번 보긴 하지만, 뭘 파는지 몰라 그냥 지나치는 상황이죠. 지금은 유입보다 전환의 기반을 다지는 게 먼저입니다.'
    ];
    $problems = [
      '무엇을 파는지 불명확: 핵심 가치 제안이 한 줄로 정리돼 있지 않음.',
      '“다음에 할 일” 부재: 버튼/링크가 많아 선택지가 분산됨.',
      '신뢰 근거 부족: 후기·수치·로고·보도 등 판단 재료가 없음.',
      '유입 대비 전환 거의 0: 트래픽이 문의·구매로 이어지지 않음.'
    ];
    $actions = [
      '첫 화면 재구성: <b>문제–약속–증거–행동(CTA)</b>를 스크롤 없이 한눈에.',
      '신뢰 요소 상단 배치: 로고·수치·수상 등 <b>위험감소 장치</b>를 즉시 노출.',
      '폼 간소화: 필드 <b>3개(이름/휴대폰/이메일)</b>로 진입장벽 최소화.',
      '한 줄 가치제안: <b>20자 내외</b>로 “누구의 어떤 문제를 어떻게 해결하는지” 명확히.'
    ];
    $program = '응급 구조 스프린트(1주) — 랜딩 구조/카피 즉시 개선 + 빠른 실험';
    $event_paras = [
      '근본부터 잡지 않으면 광고비만 새어 나갑니다. 지금은 기반을 단단히 깔 때입니다.',
      '이번 <b>30분 무료 진단 콜</b>에서는 현재 상황을 빠르게 점검하고, 당장 손대면 효과가 큰 영역부터 우선순위를 정리합니다.'
    ];
  } elseif ($score <= 30) {
    $summary = '기반은 있으나 퍼널 중간 이탈이 커서 성장 속도가 눌려 있습니다.';
    $intro_paras = [
      '16~30점이면, 기반은 갖췄지만 전환까지 이어지는 길에서 새고 있을 확률이 큽니다.',
      '물을 아무리 공급해도 중간에서 새는 수도관과 비슷합니다. 유입 확대보다 누수 지점을 먼저 막아야 합니다.'
    ];
    $problems = [
      '유입 대비 전환 정체: 광고는 집행되지만 구매/신청 비율이 멈춰 있음.',
      '고객 여정 가시성 부족: 어디서 이탈하는지 정확히 모름.',
      '예산 비효율: 채널별 성과 구분이 어려워 낭비 발생.',
      '일회성 경험: 구매/신청 이후 후속 케어가 약해 재구매·추천으로 안 이어짐.'
    ];
    $actions = [
      '병목 찾기: GA4 <b>퍼널 리포트</b>로 이탈 구간 가시화.',
      '집중 실험: 이탈 상위 1~2구간에 <b>2주</b> 집중 UI/카피 실험.',
      '예산 최적화: 채널별 CPA·전환율 비교로 비효율 <b>20%+</b> 절감.',
      '온보딩 자동화: 구매/신청 직후 <b>감사·다음 단계</b> 안내를 자동 발송.'
    ];
    $program = '병목 교정 스프린트(2주) — 퍼널 리포트 + 우선순위 3가지 실험';
    $event_paras = [
      '정체는 자연스럽지만, 방치하면 격차가 벌어집니다. 큰 효과가 나는 곳부터 고쳐야 합니다.',
      '이번 <b>30분 무료 진단 콜</b>에서 현재 병목을 함께 짚고, 바로 실행할 실험 2~3가지를 뽑아드립니다.'
    ];
  } else {
    $summary = '기반은 준비됐고, 레버리지(보증·패키지·추천)로 성장을 당길 수 있습니다.';
    $intro_paras = [
      '30점 이상이면 궤도에 올라탄 상태입니다. 이제는 “무엇을 할까”보다 “어떻게 더 빠르고 크게 할까”가 핵심입니다.',
      '이미 쌓인 고객·신뢰·시스템에 지렛대를 얹어 확장 속도를 올릴 시점입니다.'
    ];
    $problems = [
      '다음 성장의 벽: 익숙한 방식에 머물러 기회를 놓침.',
      '객단가 정체: ARPU/객단가를 올릴 설계가 부족함.',
      'CAC 상승: 유료 채널 의존도가 높아 비용 압박.',
      '실행 속도 둔화: 팀 규모가 커지며 의사결정이 느려짐.'
    ];
    $actions = [
      '오퍼/가격/보증 테스트: 구성·가격·보증 실험으로 <b>전환+객단가 동시 개선</b>.',
      '리퍼럴/제휴 루프: 추천·제휴 프로그램으로 <b>CAC 구조적 절감</b>.',
      '메시지 일치 점검: 광고–랜딩 간 <b>메시지/제안/증거</b> 완전 일치 확인.',
      '실행 체계화: 주간 리뷰·실험 로그로 의사결정 기준을 데이터로 고정.'
    ];
    $program = '성장 가속 프로그램(4주) — 오퍼/가격/리퍼럴 실험 설계 & 실행';

    $event_paras = [
      '성장의 끝은 없습니다. 다음 단계로 가는 최단 경로만 있을 뿐입니다.',
      '이번 <b>30분 무료 진단 콜</b>에서 지금 당길 수 있는 지렛대가 무엇인지 함께 정리합니다.'
    ];
  }

  get_header(); ?>
  <main class="gc-container">
    <section class="gc-sticky">
      <div class="gc-sticky-head">
        <h1>진단 결과</h1>
        <span class="gc-chip">총점 <b><?php echo $score; ?></b>/50</span>
      </div>
      <div class="gc-bar"><span style="width:<?php echo round($score / 50 * 100); ?>%"></span></div>
      <div class="gc-sub">상태:
        <b class="gc-band <?php echo ($score <= 15 ? 'bad' : ($score <= 30 ? 'mid' : 'good')); ?>"><?php echo esc_html($band); ?></b>
      </div>
    </section>

    <section class="gc-card">
      <h2>핵심 요약</h2>
      <p><?php echo esc_html($band_msg); ?></p>
      <p><?php echo wp_kses_post($summary); ?></p>
    </section>

    <section class="gc-card">
      <h2>점수대별 맞춤형 진단 및 제안</h2>
      <?php foreach ($intro_paras as $p) : ?><p><?php echo wp_kses_post($p); ?></p><?php endforeach; ?>
    </section>

    <section class="gc-card">
      <h2>이 단계에서 자주 겪는 문제</h2>
      <ul><?php foreach ($problems as $li) : ?><li><?php echo wp_kses_post($li); ?></li><?php endforeach; ?></ul>
    </section>

    <section class="gc-card">
      <h2>지금 바로 손댈 포인트</h2>
      <ul><?php foreach ($actions as $li) : ?><li><?php echo wp_kses_post($li); ?></li><?php endforeach; ?></ul>
    </section>

    <section class="gc-card">
      <h2>내 답변 요약</h2>
      <?php if (!empty($current_form['sections'])): ?>
        <?php foreach ($current_form['sections'] as $sec): ?>
          <div style="margin:10px 0 14px">
            <div style="font-weight:700;margin-bottom:6px"><?php echo esc_html($sec['title'] ?? '섹션'); ?></div>
            <ul style="margin:.25rem 0 .75rem 1.1rem">
              <?php foreach (($sec['items']??[]) as $it): 
                $val = $user_answers[$it['id']] ?? null;
                [$lab,$cls] = gc3_val_label($val);
              ?>
                <li>
                  <span><?php echo esc_html($it['q']); ?></span>
                  <span style="margin-left:8px;padding:2px 8px;border-radius:999px;border:1px solid #e5e7eb;font-size:12px" class="gc-badge <?php echo esc_attr($cls); ?>">
                    <?php echo esc_html($lab); ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>

        <div style="font-size:13px;color:#475569">
          보너스 항목 체크 수: 
          <b><?php echo array_sum(array_map('intval',$user_bonus)); ?></b>
        </div>
      <?php else: ?>
        <p>폼 구조를 불러오지 못했습니다.</p>
      <?php endif; ?>
    </section>


    <section class="gc-card">
      <h2>30분 무료 진단 콜</h2>
      <?php foreach ($event_paras as $p) : ?><p><?php echo wp_kses_post($p); ?></p><?php endforeach; ?>
      <p>이번 분기 <b>주 4팀 한정</b>으로 30분 무료 진단 콜을 제공합니다. 결과를 바탕으로 바로 실행 항목을 드립니다.</p>

      <!-- 🔹 확장된 상담 신청 폼 -->
      <!-- (중략)… 30분 무료 진단 콜 섹션 내부의 폼만 교체 -->
      <form id="gc-consult" class="gc-form" onsubmit="return false">
        <input type="text"  name="name"        placeholder="이름(필수)" required>
        <input type="email" name="email"       placeholder="이메일(필수)" required>

        <input type="tel"   name="phone" placeholder="휴대폰(예: 01012345678)" pattern="^010\d{8}$" inputmode="numeric" maxlength="11" required>

        <!-- 2줄 입력 -->
        <textarea name="contact_time" rows="2" placeholder="연락 가능 시간(예: 평일 09~12시)"></textarea>

        <input type="url"   name="site_url"     placeholder="홈페이지 URL(필수: https://…)" required>
        <input type="text"  name="company_name" placeholder="회사 상호(필수)" required>

        <select name="industry" required>
          <option value="">업종 선택(필수)</option>
          <option>교육/컨설팅</option>
          <option>IT/SaaS</option>
          <option>전자상거래</option>
          <option>제조/유통</option>
          <option>부동산/건설</option>
          <option>헬스케어/의료</option>
          <option>미디어/콘텐츠</option>
          <option>기타</option>
        </select>

        <select name="employees" required>
          <option value="">직원 수(필수)</option>
          <option value="1">1명(대표 단독)</option>
          <option value="2-5">2–5명</option>
          <option value="6-10">6–10명</option>
          <option value="11-30">11–30명</option>
          <option value="31-100">31–100명</option>
          <option value="100+">100명+</option>
        </select>

        <div class="gc-fieldrow span-2">
          <span class="gc-label">공동대표 유무</span>
          <label class="gc-inline"><input type="radio" name="cofounder" value="yes" required> 있음</label>
          <label class="gc-inline"><input type="radio" name="cofounder" value="no"  required> 없음</label>
        </div>

        <!-- 값은 안전한 코드, 라벨은 한국어 -->
        <select name="company_age" required>
          <option value="">회사 연차(필수)</option>
          <option value="prelaunch">예비 창업/런칭 전</option>
          <option value="lt1y">1년 미만</option>
          <option value="y1_3">1–3년</option>
          <option value="y3_5">3–5년</option>
          <option value="gte5y">5년 이상</option>
        </select>

        <!-- 전체폭(가로 2칸) -->
        <input class="span-2" type="url" name="company_url" placeholder="회사/서비스 추가 URL(선택)">

        <select name="source" id="gc-source" required>
          <option value="">어디서 알게 되었나요? (필수)</option>
          <option value="naver">네이버</option>
          <option value="google">구글</option>
          <option value="youtube">유튜브</option>
          <option value="instagram">인스타그램</option>
          <option value="blog">블로그</option>
          <option value="referral">지인 소개</option>
          <option value="other">기타</option>
        </select>

        <!-- 2줄 입력 -->
        <textarea name="source_other" id="gc-source-other" rows="2" placeholder="기타 상세(선택)" style="display:none;"></textarea>

        <!-- 선택: 메모 -->
        <textarea class="span-2" name="notes" rows="3" placeholder="추가로 전하고 싶은 메모(선택)"></textarea>

        <button class="gc-btn" id="gc-consult-btn">30분 무료 진단 콜 예약</button>
        <div class="gc-hint" id="gc-hint">제출 시 계정이 생성되고 결과가 저장됩니다.</div>
      </form>

    </section>
    <div class="gc-bottom-spacer"></div>
  </main>

  <?php get_footer(); ?>

<!-- 하단 고정 CTA 바 -->
<div class="gc-bottom-cta" id="gc-bottom-cta">
  <div class="label">결과 기반 맞춤 피드백이 필요하면 지금 예약해</div>
  <button class="cta-btn" id="gc-bottom-cta-btn">30분 무료 진단 콜 예약</button>
</div>

<script>
  (function(){
    // 버튼 → 결과 페이지 내부의 예약 폼(#gc-consult)로 부드럽게 스크롤
    var btn = document.getElementById('gc-bottom-cta-btn');
    if(btn){
      btn.addEventListener('click', function(){
        var form = document.getElementById('gc-consult');
        if(form){ form.scrollIntoView({behavior:'smooth', block:'start'}); }
      });
    }
  })();
</script>
  <?php get_footer(); exit;
});
