<?php
// 기존 HUSTOJ 상대경로 호환을 위한 작업 디렉터리 변경
chdir(dirname(__DIR__));

$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$path_fix = "../";
$view_title = "워드 클라우드 생성기";

require("template/" . $OJ_TEMPLATE . "/tools/wordcloud.php");

if (file_exists('./include/cache_end.php')) {
    require_once('./include/cache_end.php');
}