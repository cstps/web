<?php

// 기존 HUSTOJ 상대경로 호환을 위해 웹 루트를 작업 디렉터리로 사용
chdir(dirname(__DIR__));
$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once(__DIR__ . '/../include/db_info.inc.php');
require_once(__DIR__ . '/../include/const.inc.php');
require_once(__DIR__ . '/../include/memcache.php');
require_once(__DIR__ . '/../include/setlang.php');

$view_title = "휴먼벤치마크 (인지능력 테스트)";

// 웹 루트 기준 template/syzoj/ 뷰 템플릿 로드
require(__DIR__ . "/../template/".$OJ_TEMPLATE."/tools/humanbenchmark.php");

if(file_exists(__DIR__ . '/../include/cache_end.php'))
    require_once(__DIR__ . '/../include/cache_end.php');
?>