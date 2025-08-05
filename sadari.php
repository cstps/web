<?php 
$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = $MSG_SADARI;

require("template/".$OJ_TEMPLATE."/sadari.php");

if(file_exists('./include/cache_end.php'))
	require_once('./include/cache_end.php');
?>
