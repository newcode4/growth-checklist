<?php
/*
Plugin Name: Growth Checklist 
Description: 비즈니스 홈페이지 진단 체크리스트 + CRM
Version: 1.1.9
Author: Berrywalk
*/
if (!defined('ABSPATH')) exit;


// 1) 로더
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// 2) GitHub 연결 (리포 public 이면 그대로, private 이면 3) 참고)
$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/newcode4/growth-checklist', // ← 끝에 슬래시 없어도 OK
    __FILE__,
    'growth-checklist' // 플러그인 폴더명(slug)과 동일
);

// 3) 기본 브랜치 명시 + 릴리즈 에셋(zip) 우선 사용
$updateChecker->setBranch('main');                       // 기본 브랜치
$updateChecker->getVcsApi()->enableReleaseAssets();      // 릴리즈 자산 zip 사용
// (private repo라면) $updateChecker->setAuthentication('ghp_your_github_token');



define('GC3_VER','3.7');
define('GC3_DIR', plugin_dir_path(__FILE__));
define('GC3_URL', plugin_dir_url(__FILE__));

require_once GC3_DIR.'includes/helpers.php';
require_once GC3_DIR.'includes/shortcode-checklist.php';
require_once GC3_DIR.'includes/ajax-submit.php';
require_once GC3_DIR.'includes/results-page.php';
require_once GC3_DIR.'includes/admin-crm.php';
require_once GC3_DIR.'includes/admin-forms.php';
require_once GC3_DIR.'includes/shortcode-mypage.php';
require_once GC3_DIR.'includes/admin-reports.php';


add_action('wp_enqueue_scripts', function(){
  wp_enqueue_style('gc3-checklist', GC3_URL.'public/css/checklist.css', [], GC3_VER);
});
