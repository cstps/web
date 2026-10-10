<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "차시 수정";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. Lesson ID 확인
// ============================================================

$lesson_id =
    isset($_GET['lesson_id'])
        ? intval($_GET['lesson_id'])
        : 0;

if ($lesson_id <= 0) {

    $view_errors = "<h2>잘못된 차시 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Lesson 조회
// ============================================================

$view_lesson = course_get_lesson($lesson_id);

if (!$view_lesson) {

    $view_errors = "<h2>존재하지 않는 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($view_lesson['course_id']);


// ============================================================
// 4. Course 조회
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
// 5. Course 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 차시를 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. Course와 Lesson 상태 확인
// ============================================================

if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업의 차시는 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if (intval($view_lesson['status']) !== 1) {

    $view_errors =
        "<h2>비활성화된 차시는 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 기존 Contest 연결 확인
//
// Lesson과 Contest의 번호 동기화에 사용한다.
// ============================================================

$contest_rows = pdo_query(
    "SELECT COUNT(*) AS cnt
     FROM course_contest
     WHERE course_id = ?
       AND lesson_id = ?",
    $course_id,
    $lesson_id
);

$view_linked_contest_count =
    $contest_rows &&
    isset($contest_rows[0]['cnt'])
        ? intval($contest_rows[0]['cnt'])
        : 0;


// ============================================================
// 8. 수정 화면 출력
// ============================================================

require(
    "template/" .
    $OJ_TEMPLATE .
    "/course_lesson_edit.php"
);
