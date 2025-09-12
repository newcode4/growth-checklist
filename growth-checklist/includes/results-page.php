<?php
// includes/results-page.php
// 제출 후 /?gc_view=ID&token=TOKEN 에서 결과 렌더
if (!defined('ABSPATH')) exit;

/**
 * 점수구간(bands)에서 총점→band 선택
 */
if (!function_exists('gc3_pick_band_for_score')) {
  function gc3_pick_band_for_score($form_id, $score){
    $forms = get_option('gc3_forms', []);
    $bands = $forms[$form_id]['bands'] ?? [];
    if ($bands && is_array($bands)) {
      foreach ($bands as $b) {
        $min = intval($b['min'] ?? 0);
        $max = intval($b['max'] ?? 9999);
        if ($score >= $min && $score <= $max) return $b;
      }
    }
    // 폴백
    if ($score <= 15) return ['key'=>'위험 단계','min'=>0,'max'=>15];
    if ($score <= 30) return ['key'=>'성장 정체 단계','min'=>16,'max'=>30];
    return ['key'=>'성장 가속 단계','min'=>31,'max'=>50];
  }
}

/** 밴드별 상태 한 줄 메시지(폴백) */
if (!function_exists('gc3_band_message')) {
  function gc3_band_message($score){
    if ($score <= 15) return '메시지·신뢰·CTA가 분산돼 전환이 잘 안 나는 상태입니다.';
    if ($score <= 30) return '기반은 있으나 퍼널 중간 이탈이 커서 성장 속도가 눌려 있습니다.';
    return '기반은 준비됐고, 레버리지로 성장을 당길 수 있습니다.';
  }
}

/** 값→라벨/클래스 */
if (!function_exists('gc3_val_label')) {
  function gc3_val_label($v){
    if ($v === 3 || $v === '3') return ['예', 'good'];
    if ($v === 1 || $v === '1') return ['부분적으로', 'mid'];
    if ($v === 0 || $v === '0') return ['아니오', 'bad'];
    return ['—', 'mute'];
  }
}

add_action('template_redirect', function () {
  if (!isset($_GET['gc_view'], $_GET['token'])) return;

  $id    = sanitize_text_field($_GET['gc_view']);
  $token = sanitize_text_field($_GET['token']);
  $data  = get_transient("gc_v3_$id");

  if (!$data || empty($data['token']) || !hash_equals($data['token'], $token)) {
    status_header(403);
    wp_die('유효하지 않은 링크입니다.');
  }

  $score   = intval($data['score'] ?? 0);
  $form_id = (is_array($data) && !empty($data['form'])) ? sanitize_title_with_dashes($data['form']) : 'default';

  // 폼/밴드 결정
  $band_info = gc3_pick_band_for_score($form_id, $score);
  $band_key  = $band_info['key'] ?? '';
  $band_msg  = gc3_band_message($score);

  // 현재 폼 구조(내 답변 요약용)
  $forms = get_option('gc3_forms', []);
  $form_cfg = $forms[$form_id] ?? [];
  $current_form_json = $form_cfg['json'] ?? '';
  $current_form = $current_form_json ? json_decode($current_form_json, true) : ['sections'=>[]];

  // 점수대별 결과 콘텐츠(관리자 입력) 가져오기
  $results_cfg = $form_cfg['results'][$band_key] ?? null;

  // 사용자 응답 복원
  $answers_payload = json_decode($data['answers'] ?? '{}', true);
  $user_answers = $answers_payload['answers'] ?? [];
  $user_bonus   = $answers_payload['bonus']   ?? [];

  // 스타일/스크립트
  if (!defined('GC3_VER')) define('GC3_VER','3.4');
  if (!defined('GC3_URL'))  define('GC3_URL', plugin_dir_url(__FILE__));
  wp_enqueue_style('gc3-results', GC3_URL . 'public/css/results.css', [], GC3_VER);
  wp_enqueue_script('gc3-results-js', GC3_URL . 'public/js/results.js', [], GC3_VER, true);
  wp_localize_script('gc3-results-js', 'GC3_RESULTS', [
    'ajax' => admin_url('admin-ajax.php'),
    'ref'  => $id,
  ]);

  // 하단 PC sticky CTA 리디자인(1200px 중앙/큰 폰트) 오버라이드
  $inline_css = <<<CSS
  .gc-bottom-cta{position:fixed;left:0;right:0;bottom:0;background:#0f172a;z-index:9999;padding:18px 0;box-shadow:0 -6px 20px rgba(2,6,23,.25)}
  .gc-bottom-cta .inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:24px;padding:0 24px}
  .gc-bottom-cta .label{flex:1;text-align:center;color:#fff;font-weight:700;font-size:18px;line-height:1.4}
  .gc-bottom-cta .cta-btn{font-size:18px;font-weight:700;padding:14px 22px;border-radius:14px;background:#22c55e;color:#062}
  @media (max-width:768px){.gc-bottom-cta .label{font-size:16px}.gc-bottom-cta .cta-btn{font-size:16px;padding:12px 18px}}
CSS;
  wp_add_inline_style('gc3-results', $inline_css);

  // ===== 결과 데이터 세팅 =====
  $summary = '';
  $intro_paras = [];
  $problems = [];
  $actions  = [];
  $program  = '';
  $event_paras = [];

  if ($results_cfg) {
    // 관리자 입력 우선
    $summary     = $results_cfg['summary']     ?? '';
    $intro_paras = $results_cfg['intro_paras'] ?? [];
    $problems    = $results_cfg['problems']    ?? [];
    $actions     = $results_cfg['actions']     ?? [];
    $program     = $results_cfg['program']     ?? '';
    $event_paras = $results_cfg['event_paras'] ?? [];
  } else {
    // 폴백: 기본 카피
    if ($score <= 15) {
      $summary = '메시지·신뢰·CTA가 분산돼 전환이 잘 안 나는 상태입니다.';
      $intro_paras = [
        '점수가 15점 이하라면, 이제 막 시작했거나 아직 셋업이 덜 된 단계일 가능성이 큽니다. 핵심이 한 화면에 정리돼 있지 않아 방문자가 무엇을 해야 할지 헷갈립니다.',
        '간판도 가격표도 없는 가게와 비슷합니다. 지금은 유입보다 전환의 기반을 다지는 게 먼저입니다.'
      ];
      $problems = [
        '무엇을 파는지 불명확: 핵심 가치 제안이 한 줄로 정리돼 있지 않음.',
        '“다음에 할 일” 부재: 버튼/링크가 많아 선택지가 분산됨.',
        '신뢰 근거 부족: 후기·수치·로고·보도 등 판단 재료가 없음.',
        '유입 대비 전환 거의 0: 트래픽이 문의·구매로 이어지지 않음.'
      ];
      $actions = [
        '첫 화면 재구성: 문제–약속–증거–행동(CTA)을 스크롤 없이 한눈에.',
        '신뢰 요소 상단 배치: 로고·수치·수상 등 위험감소 장치를 즉시 노출.',
        '폼 간소화: 필드 3개(이름/휴대폰/이메일)로 진입장벽 최소화.',
        '한 줄 가치제안: 20자 내외로 “누구의 어떤 문제를 어떻게 해결” 명확히.'
      ];
      $program = '응급 구조 스프린트(1주) — 랜딩 구조/카피 즉시 개선 + 빠른 실험';
      $event_paras = [
        '근본부터 잡아야 광고비 누수가 멈춥니다.',
        '30분 무료 진단 콜에서 우선순위를 즉시 정리합니다.'
      ];
    } elseif ($score <= 30) {
      $summary = '기반은 있으나 퍼널 중간 이탈이 커서 성장 속도가 눌려 있습니다.';
      $intro_paras = [
        '16~30점이면 기반은 갖췄지만 전환까지의 길에서 새고 있을 확률이 큽니다.',
        '유입 확대보다 누수 지점을 먼저 막아야 합니다.'
      ];
      $problems = [
        '유입 대비 전환 정체',
        '고객 여정 가시성 부족',
        '예산 비효율',
        '일회성 경험으로 재구매·추천 안 이어짐'
      ];
      $actions = [
        'GA4 퍼널 리포트로 이탈 구간 가시화',
        '이탈 상위 구간 2주 집중 실험',
        '채널별 CPA·전환율 비교로 비효율 20%+ 절감',
        '온보딩 자동화 구축'
      ];
      $program = '병목 교정 스프린트(2주) — 퍼널 리포트 + 우선순위 3가지 실험';
      $event_paras = [
        '정체는 방치할수록 격차가 벌어집니다.',
        '30분 무료 진단 콜에서 바로 실행할 실험 2~3가지를 뽑아드립니다.'
      ];
    } else {
      $summary = '기반은 준비됐고, 레버리지로 성장을 당길 수 있습니다.';
      $intro_paras = [
        '30점 이상이면 궤도에 오른 상태입니다.',
        '메시지/오퍼/리퍼럴의 지렛대를 얹어 확장 속도를 올릴 시점입니다.'
      ];
      $problems = [
        '다음 성장의 벽',
        '객단가 정체',
        'CAC 상승',
        '의사결정 속도 둔화'
      ];
      $actions = [
        '오퍼/가격/보증 실험으로 전환+객단가 동시 개선',
        '리퍼럴/제휴 루프로 CAC 구조적 절감',
        '광고–랜딩 메시지/제안/증거 일치',
        '주간 리뷰·실험 로그로 의사결정 기준 고정'
      ];
      $program = '성장 가속 프로그램(4주) — 오퍼/가격/리퍼럴 실험 설계 & 실행';
      $event_paras = [
        '성장의 끝은 없습니다. 최단 경로만 있을 뿐.',
        '30분 무료 진단 콜에서 즉시 당길 수 있는 지렛대를 정리합니다.'
      ];
    }
  }

  // 렌더
  get_header(); ?>
  <main class="gc-container">
    <section class="gc-sticky">
      <div class="gc-sticky-head">
        <h1>진단 결과</h1>
        <span class="gc-chip">총점 <b><?php echo $score; ?></b>/50</span>
      </div>
      <div class="gc-bar"><span style="width:<?php echo round($score / 50 * 100); ?>%"></span></div>
      <div class="gc-sub">상태:
        <b class="gc-band <?php echo ($score <= 15 ? 'bad' : ($score <= 30 ? 'mid' : 'good')); ?>">
          <?php echo esc_html($band_key ?: '진단'); ?>
        </b>
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

    <?php if (!empty($program)): ?>
    <section class="gc-card">
      <h2>프로그램</h2>
      <p><?php echo esc_html($program); ?></p>
    </section>
    <?php endif; ?>

    <?php
    // bands에 page_id가 있으면 “맞춤형 결과”로 페이지 임베드
    $band_page_id = intval($band_info['page_id'] ?? 0);
    if ($band_page_id): ?>
      <section class="gc-card">
        <h2>맞춤형 결과</h2>
        <?php
          $post = get_post($band_page_id);
          if ($post && $post->post_status === 'publish') {
            echo apply_filters('the_content', $post->post_content);
          } else {
            echo '<p>결과 페이지가 아직 준비되지 않았습니다.</p>';
          }
        ?>
      </section>
    <?php endif; ?>

    <details class="gc-card gc-details">
      <summary>내 답변 요약</summary>
      <div class="gc-details-body">
        <?php if (!empty($current_form['sections'])): ?>
          <?php foreach ($current_form['sections'] as $sec): ?>
            <div style="margin:10px 0 14px">
              <div style="font-weight:700;margin-bottom:6px">
                <?php echo esc_html($sec['title'] ?? '섹션'); ?>
              </div>
              <ul style="margin:.25rem 0 .5rem 1.1rem">
                <?php foreach (($sec['items']??[]) as $it):
                  $val = $user_answers[$it['id']] ?? null;
                  [$lab,$cls] = gc3_val_label($val);
                ?>
                  <li style="margin:4px 0">
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
            보너스 항목 체크 수: <b><?php echo array_sum(array_map('intval',$user_bonus)); ?></b>
          </div>
        <?php else: ?>
          <p>폼 구조를 불러오지 못했습니다.</p>
        <?php endif; ?>
      </div>
    </details>

    <section class="gc-card">
      <h2>30분 무료 진단 콜</h2>
      <?php foreach ($event_paras as $p) : ?><p><?php echo wp_kses_post($p); ?></p><?php endforeach; ?>
      <p>이번 분기 <b>주 4팀 한정</b>으로 30분 무료 진단 콜을 제공합니다. 결과를 바탕으로 바로 실행 항목을 드립니다.</p>

      <form id="gc-consult" class="gc-form" onsubmit="return false">
        <input type="text"  name="name"        placeholder="이름(필수)" required>
        <input type="email" name="email"       placeholder="이메일(필수)" required>
        <input type="tel"   name="phone" placeholder="휴대폰(예: 01012345678)"  pattern="^010(?:-?\\d{4}-?\\d{4})$" inputmode="numeric" maxlength="13" required>
        <textarea name="contact_time" rows="2" placeholder="연락 가능 시간(예: 평일 09~12시)"></textarea>
        <input type="url"   name="site_url"     placeholder="홈페이지 URL(필수: https://…)" required>
        <input type="text"  name="company_name" placeholder="회사 상호(필수)" required>

        <select name="industry" required>
          <option value="">업종 선택(필수)</option>
          <option>교육/컨설팅</option><option>IT/SaaS</option><option>전자상거래</option>
          <option>제조/유통</option><option>부동산/건설</option><option>헬스케어/의료</option>
          <option>미디어/콘텐츠</option><option>기타</option>
        </select>

        <select name="employees" required>
          <option value="">직원 수(필수)</option>
          <option value="1">1명(대표 단독)</option><option value="2-5">2–5명</option>
          <option value="6-10">6–10명</option><option value="11-30">11–30명</option>
          <option value="31-100">31–100명</option><option value="100+">100명+</option>
        </select>

        <div class="gc-fieldrow span-2">
          <span class="gc-label">공동대표 유무</span>
          <label class="gc-inline"><input type="radio" name="cofounder" value="yes" required> 있음</label>
          <label class="gc-inline"><input type="radio" name="cofounder" value="no"  required> 없음</label>
        </div>

        <select name="company_age" required>
          <option value="">회사 연차(필수)</option>
          <option value="prelaunch">예비 창업/런칭 전</option>
          <option value="lt1y">1년 미만</option>
          <option value="y1_3">1–3년</option>
          <option value="y3_5">3–5년</option>
          <option value="gte5y">5년 이상</option>
        </select>

        <textarea name="company_url" rows="2" placeholder="회사/서비스 추가 URL(선택)"></textarea>

        <select name="source" id="gc-source" required>
          <option value="">어디서 알게 되었나요? (필수)</option>
          <option value="naver">네이버</option><option value="google">구글</option>
          <option value="youtube">유튜브</option><option value="instagram">인스타그램</option>
          <option value="blog">블로그</option><option value="referral">지인 소개</option>
          <option value="other">기타</option>
        </select>

        <textarea name="source_other" id="gc-source-other" rows="2" placeholder="기타 상세(선택)" style="display:none;"></textarea>
        <textarea name="notes" rows="2" placeholder="추가로 전하고 싶은 메모(선택)"></textarea>

        <button class="gc-btn" id="gc-consult-btn">30분 무료 진단 콜 예약</button>
        <div class="gc-hint" id="gc-hint">제출 시 계정이 생성되고 결과가 저장됩니다.</div>
      </form>
    </section>

    <div class="gc-bottom-spacer"></div>
  </main>

  <?php get_footer(); ?>

  <!-- 하단 고정 CTA -->
  <div class="gc-bottom-cta" id="gc-bottom-cta">
    <div class="inner">
      <div class="label">전문가 맞춤 피드백이 필요하시다면 지금 바로 신청해주세요</div>
      <button class="cta-btn" id="gc-bottom-cta-btn">30분 무료 진단 콜 예약</button>
    </div>
  </div>

  <script>
    (function(){
      var btn = document.getElementById('gc-bottom-cta-btn');
      var srcSel = document.getElementById('gc-source');
      var srcOther = document.getElementById('gc-source-other');

      if (btn) {
        btn.addEventListener('click', function(){
          var form = document.getElementById('gc-consult');
          if(form){ form.scrollIntoView({behavior:'smooth', block:'start'}); }
        });
      }
      if (srcSel) {
        srcSel.addEventListener('change', function(){
          if (srcSel.value === 'other') {
            srcOther.style.display = 'block';
          } else {
            srcOther.style.display = 'none';
            srcOther.value = '';
          }
        });
      }
    })();
  </script>
  <?php
  exit;
});
