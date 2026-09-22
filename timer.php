<?php
$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = "수업용 멀티 타이머";

require("template/" . $OJ_TEMPLATE . "/timer.php");

if (file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
