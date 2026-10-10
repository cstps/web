<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "학습 활동 생성";


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

$lesson_id = isset($_POST['lesson_id'])
    ? intval($_POST['lesson_id'])
    : 0;

$activity_type =
    isset($_POST['activity_type']) &&
    is_string($_POST['activity_type'])
        ? trim($_POST['activity_type'])
        : '';

$title =
    isset($_POST['title']) &&
    is_string($_POST['title'])
        ? trim($_POST['title'])
        : '';

$description =
    isset($_POST['description']) &&
    is_string($_POST['description'])
        ? trim($_POST['description'])
        : '';

if (
    $course_id <= 0 ||
    $lesson_id <= 0 ||
    !in_array(
        $activity_type,
        array('material', 'assignment'),
        true
    ) ||
    $title === '' ||
    strlen($title) > 255 ||
    strlen($description) > 65535
) {

    $view_errors =
        "<h2>학습 활동 입력값이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Course 존재 및 상태 확인
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

if (intval($course_rows[0]['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 활동을 추가할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. 수업 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>학습 활동을 생성할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. Lesson 소속 및 상태 확인
// ============================================================

$lesson = course_get_lesson($lesson_id);

if (
    !$lesson ||
    intval($lesson['course_id']) !== $course_id ||
    intval($lesson['status']) !== 1
) {

    $view_errors =
        "<h2>유효하지 않은 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 다음 Activity 순서 확인
// ============================================================

$order_rows = pdo_query(
    "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order
     FROM course_activity
     WHERE course_id = ?
       AND lesson_id = ?",
    $course_id,
    $lesson_id
);

$sort_order = 1;

if (
    $order_rows &&
    isset($order_rows[0]['next_order'])
) {
    $sort_order = max(
        1,
        intval($order_rows[0]['next_order'])
    );
}


// ============================================================
// 7. Activity 생성
//
// Contest 연결 및 학생 제출 기록은 변경하지 않는다.
// ============================================================

$new_activity_id = pdo_query(
    "INSERT INTO course_activity
    (
        course_id,
        lesson_id,
        activity_type,
        title,
        description,
        sort_order,
        visible,
        status,
        created_by
    )
    VALUES (?, ?, ?, ?, ?, ?, 0, 1, ?)",
    $course_id,
    $lesson_id,
    $activity_type,
    $title,
    $description,
    $sort_order,
    $user_id
);

if (intval($new_activity_id) <= 0) {

    $view_errors =
        "<h2>학습 활동 생성에 실패했습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. Course 관리 화면으로 이동
// ============================================================

header(
    "Location: course_view.php?course_id=" .
    $course_id
);

exit(0);
