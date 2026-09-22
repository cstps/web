<?php
$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
//require_once('./include/cache_start.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = isset($MSG_ULTILIST) ? $MSG_ULTILIST : "유틸리티 도구 모음";

// 템플릿 파일 불러오기 (View)
require("template/" . $OJ_TEMPLATE . "/tools.php");

if (file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
