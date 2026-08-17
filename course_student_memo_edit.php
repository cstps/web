<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "학생 메모 수정";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$current_user_id =
    $_SESSION[$OJ_NAME.'_user_id'];

$is_admin =
    isset($_SESSION[$OJ_NAME.'_administrator']);


// ============================================================
// 2. 입력값
// ============================================================

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;

$student_user_id =
    isset($_GET['user_id'])
        ? trim($_GET['user_id'])
        : '';

$memo_id =
    isset($_GET['memo_id'])
        ? intval($_GET['memo_id'])
        : 0;


if (
    $course_id <= 0 ||
    $student_user_id === '' ||
    $memo_id <= 0
) {

    $view_errors =
        "<h2>잘못된 메모 정보입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Course 접근 권한
// ============================================================

if (!course_can_access($course_id)) {

    $view_errors =
        "<h2>이 수업의 메모를 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. 메모 조회
// ============================================================

$memo_rows = pdo_query(
    "SELECT
        id,
        course_id,
        user_id,
        contest_id,
        memo_text,
        created_by,
        created_at,
        updated_at
     FROM course_student_memo
     WHERE id = ?
       AND course_id = ?
       AND user_id = ?
     LIMIT 1",
    $memo_id,
    $course_id,
    $student_user_id
);


if (
    !$memo_rows ||
    !isset($memo_rows[0]['id'])
) {

    $view_errors =
        "<h2>존재하지 않는 메모입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$view_memo =
    $memo_rows[0];


// ============================================================
// 5. 수정 권한
// ============================================================

if (
    !$is_admin &&
    $view_memo['created_by'] !== $current_user_id
) {

    $view_errors =
        "<h2>이 메모를 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. Course 정보
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name
     FROM course
     WHERE course_id = ?
     LIMIT 1",
    $course_id
);

$view_course =
    $course_rows &&
    isset($course_rows[0])
        ? $course_rows[0]
        : array();


// ============================================================
// 7. 활성 차시 목록
// ============================================================

$view_contests = pdo_query(
    "SELECT
        cc.contest_id,
        cc.lesson_no,
        c.title
     FROM course_contest cc
     LEFT JOIN contest c
       ON c.contest_id = cc.contest_id
     WHERE cc.course_id = ?
       AND cc.status = 1
     ORDER BY
        cc.lesson_no,
        cc.contest_id",
    $course_id
);


if (!is_array($view_contests)) {
    $view_contests = array();
}


// ============================================================
// 8. 화면 출력
// ============================================================

require(
    "template/".$OJ_TEMPLATE."/course_student_memo_edit.php"
);