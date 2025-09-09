<?php
if (!defined('ABSPATH')) exit;

add_shortcode('growth_checklist', function($atts){
  $a = shortcode_atts(['id'=>'default'], $atts, 'growth_checklist');
  $forms = get_option('gc3_forms');
  if (empty($forms[$a['id']])) return '<div>설정된 폼이 없습니다.</div>';
  $json = $forms[$a['id']]['json'];

  wp_enqueue_script('gc3-checklist-js', GC3_URL.'public/js/checklist.js', [], GC3_VER, true);
  wp_localize_script('gc3-checklist-js','GC3_DATA',[
    'ajax'=>admin_url('admin-ajax.php'),
    'form'=>$a['id'],
    'questions'=>$json
  ]);

  ob_start(); ?>
  <div id="gc3-wrap" class="sd-wrap">
    <div class="sd-sticky">
      <div class="sd-sticky-top">
        <h2 class="sd-title">진단 체크리스트 진행 상황</h2>
        <span class="sd-badge"><span id="sd-count">0</span> / 15</span>
      </div>
      <div class="sd-meter"><span id="sd-bar"></span></div>
      <div class="sd-help" id="sd-hint">응답</div>
    </div>

    <script type="application/json" id="gc3-questions"><?php echo $json; ?></script>
    <div id="gc-sections"></div>

    <div class="sd-card">
      <div class="sd-pill">보너스 (최대 5점)</div>
      <div id="gc-bonus" class="sd-bonus"></div>
      <div class="sd-help">보너스: <b><span id="sd-bonusVal">0</span>/5</b></div>
    </div>

    <div class="sd-card">
      <h3 class="sd-title small">진단 결과</h3>
      <p class="sd-help">모든 문항에 응답하면 제출할 수 있습니다.</p>
      <div class="sd-cta"><button class="sd-btn" id="sd-submit">결과 제출</button></div>
      <div class="sd-help">제출하면 결과 페이지로 이동합니다. 결과 페이지에서 원하실 경우에만 15분 무료 상담 신청을 진행합니다.</div>
    </div>
  </div>
  <?php return ob_get_clean();
});
