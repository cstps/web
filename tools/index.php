<?php

// 기존 HUSTOJ 상대경로 호환을 위해 웹 루트를 작업 디렉터리로 사용
chdir(dirname(__DIR__));
$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once(__DIR__ . '/../include/db_info.inc.php');
require_once(__DIR__ . '/../include/const.inc.php');
//require_once(__DIR__ . '/../include/cache_start.php');
require_once(__DIR__ . '/../include/memcache.php');
require_once(__DIR__ . '/../include/setlang.php');

$view_title = isset($MSG_ULTILIST) ? $MSG_ULTILIST : "유틸리티 도구 모음";

// 유틸리티 전용 템플릿 파일 불러오기
require(
    __DIR__ .
    '/../template/' .
    $OJ_TEMPLATE .
    '/tools/index.php'
);

if (file_exists(__DIR__ . '/../include/cache_end.php')) {
    require_once(__DIR__ . '/../include/cache_end.php');
}
