<?php
if (!defined('ABSPATH')) exit;

function gc3_default_forms_bootstrap(){
  $opt = get_option('gc3_forms');
  if (is_array($opt)) return;

  $default_json = json_encode([
    "sections"=>[
      ["title"=>"섹션 1: 메시지 및 차별화 (최대 9점)",
       "items"=>[
         ["id"=>"q1","q"=>"1-1. 한 줄로 고객에게 제공하는 가치를 명확히 설명할 수 있나요?","crit"=>"기준: 누구/문제/가치"],
         ["id"=>"q2","q"=>"1-2. 고객이 경쟁사 대신 선택해야 할 ‘단 하나의 이유’가 있나요?","crit"=>"모방 어려운 단 하나"],
         ["id"=>"q3","q"=>"1-3. 첫 화면이 고객의 ‘고통/욕망’을 직접 언급하나요?","crit"=>"문제·욕망 문장으로 시작"]
       ]],
      ["title"=>"섹션 2: UX / 디자인 진단 (최대 9점)",
       "items"=>[
         ["id"=>"q4","q"=>"2-1. 모바일에서 5분 이상 둘러봐도 불편 없나요?","crit"=>"텍스트/버튼/이미지 OK"],
         ["id"=>"q5","q"=>"2-2. 핵심 정보가 3클릭 내 접근 가능한가요?","crit"=>"가격/연락처/상품"],
         ["id"=>"q6","q"=>"2-3. CTA가 눈에 띄고 행동 유발 문구인가요?","crit"=>"예: 무료 컨설팅 받기"]
       ]],
      ["title"=>"섹션 3: 신뢰 및 전환 (최대 9점)",
       "items"=>[
         ["id"=>"q7","q"=>"3-1. 수치 있는 후기/사례가 있나요?","crit"=>"매출·문의·전환률"],
         ["id"=>"q8","q"=>"3-2. 사회적 증거(언론·수상·로고)를 보여주나요?","crit"=>"검증된 출처"],
         ["id"=>"q9","q"=>"3-3. 결제/신청이 3단계 이하인가요?","crit"=>"중복입력 없음"]
       ]],
      ["title"=>"섹션 4: 데이터 및 퍼널 (최대 9점)",
       "items"=>[
         ["id"=>"q10","q"=>"4-1. GA4 등 추적 도구가 설치됐나요?","crit"=>"핵심 이벤트/목표 수집"],
         ["id"=>"q11","q"=>"4-2. 퍼널 이탈 지점을 알고 있나요?","crit"=>"단계별 이탈률"],
         ["id"=>"q12","q"=>"4-3. 채널별 성과 비교로 예산 최적화 하나요?","crit"=>"비효율 삭감"]
       ]],
      ["title"=>"섹션 5: 장기적 관계 및 팬덤 (최대 9점)",
       "items"=>[
         ["id"=>"q13","q"=>"5-1. 고객 의견을 자동 수집·반영하나요?","crit"=>"설문·후속연락"],
         ["id"=>"q14","q"=>"5-2. 구매 후에도 가치 콘텐츠를 제공하나요?","crit"=>"블로그/뉴스레터"],
         ["id"=>"q15","q"=>"5-3. 추천 보상 장치가 있나요?","crit"=>"리퍼럴/쿠폰"]
       ]]
    ],
    "bonus"=>[
      ["id"=>"b1","label"=>"전/후 비교 스크린샷 있다"],
      ["id"=>"b2","label"=>"최근 30일 성과 기록 있다"],
      ["id"=>"b3","label"=>"A/B 테스트 기록 있다"],
      ["id"=>"b4","label"=>"고객 인터뷰 요약 있다"],
      ["id"=>"b5","label"=>"가격·패키지 실험 결과 있다"]
    ]
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

  update_option('gc3_forms', [
    'default' => ['title'=>'기본 체크리스트','json'=>$default_json]
  ], false);
}
add_action('init','gc3_default_forms_bootstrap');

// 폼별 bands에서 총점→band 찾아주는 헬퍼
function gc3_pick_band_for_score($form_id, $score){
  $forms = get_option('gc3_forms', []);
  $bands = $forms[$form_id]['bands'] ?? [];
  if ($bands && is_array($bands)) {
    foreach ($bands as $b) {
      $min = intval($b['min'] ?? 0);
      $max = intval($b['max'] ?? 9999);
      if ($score >= $min && $score <= $max) {
        return $b; // ['key'=>..,'min'=>..,'max'=>..,'page_id'=>..,'cta'=>...]
      }
    }
  }
  // 🔙 기존 하드코딩 로직으로 폴백 (구버전 호환)
  if ($score<=15) return ['key'=>'위험 단계','min'=>0,'max'=>15];
  if ($score<=30) return ['key'=>'성장 정체 단계','min'=>16,'max'=>30];
  return ['key'=>'성장 가속 단계','min'=>31,'max'=>50];
}

