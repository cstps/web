<?php 
$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
//require_once('./include/cache_start.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = "Draw Problem Set";

if(isset($_GET['ajax'])){
	require("template/bs3/problemset.php");
}else{
	require("template/".$OJ_TEMPLATE."/drawproblemset.php");
}
if(file_exists('./include/cache_end.php'))
	require_once('./include/cache_end.php');
?>
