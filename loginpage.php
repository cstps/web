<?php
$cache_time = 1;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
$view_title = "LOGIN";

if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])) {
    echo "<a href='logout.php'>Please logout First!</a>";
    exit(1);
}

/* 서버측만 사용: 자동 XHR/뒤로가기 제거 */
if (!empty($OJ_LONG_LOGIN)
    && !empty($OJ_COOKIE_LOGIN)
    && isset($_COOKIE[$OJ_NAME."_user"], $_COOKIE[$OJ_NAME."_check"])) {
    header("Location: login.php?auto=cookie", true, 302);
    exit;
}

/* 템플릿 렌더 */
require("template/".$OJ_TEMPLATE."/loginpage.php");

if (file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
