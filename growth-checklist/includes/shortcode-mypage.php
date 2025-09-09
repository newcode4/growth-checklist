<?php
if (!defined('ABSPATH')) exit;

add_shortcode('gc_my_results', function(){
  if(!is_user_logged_in()) return '<p>로그인 후 이용해 주세요.</p>';
  $u = wp_get_current_user();
  $hist = get_user_meta($u->ID,'gc_results',true);
  if(!$hist || !is_array($hist)) return '<p>저장된 진단 결과가 없습니다.</p>';

  ob_start();
  echo '<div class="gc-my"><h3>나의 진단 결과</h3><ul>';
  foreach(array_reverse($hist) as $row){
    printf(
      '<li><b>%s</b> — 점수 %d/50 · 상태 %s</li>',
      esc_html($row['time']),
      intval($row['score']),
      esc_html($row['band'])
    );
  }
  echo '</ul></div>';
  return ob_get_clean();
});
