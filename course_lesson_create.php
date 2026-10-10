<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "차시 생성";


// ============================================================
// 1. 로그인 및 요청 방식 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$user_id = $_SESSION[$OJ_NAME.'_user_id'];


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors = "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. 입력값 확인
// ============================================================

$course_id = isset($_POST['course_id'])
    ? intval($_POST['course_id'])
    : 0;

$lesson_no = isset($_POST['lesson_no'])
    ? intval($_POST['lesson_no'])
    : 0;

$title = isset($_POST['title']) &&
         is_string($_POST['title'])
    ? trim($_POST['title'])
    : '';

$description = isset($_POST['description']) &&
               is_string($_POST['description'])
    ? trim($_POST['description'])
    : '';


if (
    $course_id <= 0 ||
    $lesson_no <= 0 ||
    $lesson_no > 2147483647 ||
    $title === '' ||
    strlen($title) > 255
) {

    $view_errors =
        "<h2>차시 번호 또는 제목이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Course 존재 및 관리 권한 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT course_id, status
     FROM course
     WHERE course_id = ?
     LIMIT 1",
    $course_id
);


if (!$course_rows || !isset($course_rows[0])) {

    $view_errors = "<h2>존재하지 않는 수업입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>차시를 생성할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (intval($course_rows[0]['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 차시를 생성할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. 기존 Lesson 및 Contest 차시 번호 중복 확인
// ============================================================

$existing = pdo_query(
    "SELECT 1
     FROM course_lesson
     WHERE course_id = ?
       AND lesson_no = ?
     LIMIT 1",
    $course_id,
    $lesson_no
);

$existing_contest = pdo_query(
    "SELECT 1
     FROM course_contest
     WHERE course_id = ?
       AND lesson_no = ?
     LIMIT 1",
    $course_id,
    $lesson_no
);


if (!empty($existing) || !empty($existing_contest)) {

    $view_errors =
        "<h2>이미 사용 중인 차시 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. Lesson 독립 생성
//
// Contest 및 Activity는 생성하지 않는다.
// ============================================================

$new_lesson_id = pdo_query(
    "INSERT INTO course_lesson
    (
        course_id,
        lesson_no,
        title,
        description,
        sort_order,
        visible,
        status,
        created_by
    )
    VALUES (?, ?, ?, ?, ?, 0, 1, ?)",
    $course_id,
    $lesson_no,
    $title,
    $description,
    $lesson_no,
    $user_id
);


if (intval($new_lesson_id) <= 0) {

    $view_errors =
        "<h2>차시 생성에 실패했습니다. 번호 중복 또는 DB 오류를 확인하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. Course 관리 화면으로 이동
// ============================================================

header(
    "Location: course_view.php?course_id=" .
    $course_id
);

exit(0);
