<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "새 수업 만들기";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors =
        "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$user_id =
    $_SESSION[$OJ_NAME.'_user_id'];


// ============================================================
// 2. Course 생성 권한
//
// 현재 1차 버전에서는
// administrator 또는 contest_creator에게 허용한다.
// ============================================================

$view_can_create_course =
    isset($_SESSION[$OJ_NAME.'_administrator']) ||
    isset($_SESSION[$OJ_NAME.'_contest_creator']);


if (!$view_can_create_course) {

    $view_errors =
        "<h2>수업을 생성할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. 기본값
// ============================================================

$view_default_year =
    intval(date('Y'));


// ============================================================
// 4. 화면 출력
// ============================================================

require(
    "template/".$OJ_TEMPLATE."/course_add.php"
);