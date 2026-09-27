<?php
$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = "휴먼벤치마크 (인지능력 테스트)";

// 웹 루트 기준 template/syzoj/ 뷰 템플릿 로드
require("template/".$OJ_TEMPLATE."/humanbenchmark.php");

if(file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
?>