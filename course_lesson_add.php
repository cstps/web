<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "새 차시 만들기";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. Course ID 확인
// ============================================================

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;

if ($course_id <= 0) {

    $view_errors = "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Course 조회
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
        status
     FROM course
     WHERE course_id = ?
     LIMIT 1",
    $course_id
);

if (
    !$course_rows ||
    !isset($course_rows[0]['course_id'])
) {

    $view_errors = "<h2>존재하지 않는 수업입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_course = $course_rows[0];


// ============================================================
// 4. Course 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 차시를 생성할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 운영 중인 수업인지 확인
// ============================================================

if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 차시를 생성할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 다음 차시 번호 계산
//
// Lesson과 기존 Contest의 번호를 모두 확인한다.
// ============================================================

$next_rows = pdo_query(
    "SELECT
        GREATEST(
            COALESCE(
                (SELECT MAX(lesson_no)
                 FROM course_lesson
                 WHERE course_id = ?),
                0
            ),
            COALESCE(
                (SELECT MAX(lesson_no)
                 FROM course_contest
                 WHERE course_id = ?),
                0
            )
        ) + 1 AS next_lesson_no",
    $course_id,
    $course_id
);

$view_next_lesson_no = 1;

if (
    $next_rows &&
    isset($next_rows[0]['next_lesson_no'])
) {
    $view_next_lesson_no =
        max(1, intval($next_rows[0]['next_lesson_no']));
}


// ============================================================
// 7. CSRF 토큰 생성
//
// include/csrf_check.php와 동일한 세션 키 사용
// ============================================================

$csrf_session_key = $OJ_NAME . '_csrf_keys';

if (
    !isset($_SESSION[$csrf_session_key]) ||
    !is_array($_SESSION[$csrf_session_key])
) {
    $_SESSION[$csrf_session_key] = array();
}

$view_csrf_token = bin2hex(random_bytes(24));

$_SESSION[$csrf_session_key][] = $view_csrf_token;


// ============================================================
// 8. 화면 출력
// ============================================================

require(
    "template/" .
    $OJ_TEMPLATE .
    "/course_lesson_add.php"
);
