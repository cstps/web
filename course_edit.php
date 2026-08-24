<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "수업 정보 수정";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors =
        "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. course_id 확인
// ============================================================

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;


if ($course_id <= 0) {

    $view_errors =
        "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Course 존재 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
        school,
        school_year,
        semester,
        description,
        status,
        created_by,
        created_at,
        updated_at
     FROM course
     WHERE course_id = ?
     LIMIT 1",
    $course_id
);


if (
    !$course_rows ||
    !isset($course_rows[0]['course_id'])
) {

    $view_errors =
        "<h2>존재하지 않는 수업입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$view_course =
    $course_rows[0];


// ============================================================
// 4. Course 접근 권한 확인
// ============================================================

if (!course_can_access($course_id)) {

    $view_errors =
        "<h2>이 수업을 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. Course 수정 권한 확인
// ============================================================

if (!course_can_edit($course_id)) {

    $view_errors =
        "<h2>이 수업의 정보를 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 6. 종료된 Course 수정 방지
// ============================================================

if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업의 정보는 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 7. 화면 출력
// ============================================================

require(
    "template/".$OJ_TEMPLATE."/course_edit.php"
);