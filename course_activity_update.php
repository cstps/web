<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "학습 활동 수정";


// ============================================================
// 1. 로그인 및 POST 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors = "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. 입력값 확인
// ============================================================

$course_id = isset($_POST['course_id']) &&
             is_string($_POST['course_id'])
    ? trim($_POST['course_id'])
    : '';

$activity_id = isset($_POST['activity_id']) &&
               is_string($_POST['activity_id'])
    ? trim($_POST['activity_id'])
    : '';

$activity_type = isset($_POST['activity_type']) &&
                 is_string($_POST['activity_type'])
    ? trim($_POST['activity_type'])
    : '';

$title = isset($_POST['title']) &&
         is_string($_POST['title'])
    ? trim($_POST['title'])
    : '';

$description = isset($_POST['description']) &&
               is_string($_POST['description'])
    ? trim($_POST['description'])
    : '';

$visible_raw = isset($_POST['visible']) &&
               is_string($_POST['visible'])
    ? trim($_POST['visible'])
    : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $course_id) ||
    !preg_match('/^[1-9][0-9]*$/D', $activity_id) ||
    strlen($course_id) > 10 ||
    strlen($activity_id) > 10 ||
    !in_array(
        $activity_type,
        array('material', 'assignment'),
        true
    ) ||
    $title === '' ||
    strlen($title) > 255 ||
    strlen($description) > 65535 ||
    !in_array($visible_raw, array('0', '1'), true)
) {

    $view_errors =
        "<h2>학습 활동 수정 입력값이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($course_id);
$activity_id = intval($activity_id);
$visible = intval($visible_raw);

if (
    $course_id > 2147483647 ||
    $activity_id > 2147483647
) {

    $view_errors =
        "<h2>학습 활동 번호가 허용 범위를 초과했습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Activity 조회 및 소속 확인
// ============================================================

$activity_rows = pdo_query(
    "SELECT
        activity_id,
        course_id,
        lesson_id,
        activity_type,
        status
     FROM course_activity
     WHERE activity_id = ?
       AND course_id = ?
     LIMIT 1",
    $activity_id,
    $course_id
);

if (
    !$activity_rows ||
    !isset($activity_rows[0]['activity_id'])
) {

    $view_errors =
        "<h2>해당 수업의 학습 활동을 찾을 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity = $activity_rows[0];


// ============================================================
// 4. Course 확인 및 관리 권한 검사
// ============================================================

$course_rows = pdo_query(
    "SELECT course_id, status
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

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 학습 활동을 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. Course / Activity 상태 및 유형 검사
// ============================================================

if (
    intval($course_rows[0]['status']) !== 1 ||
    intval($activity['status']) !== 1
) {

    $view_errors =
        "<h2>비활성 상태의 수업 또는 활동은 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if (
    !in_array(
        $activity['activity_type'],
        array('material', 'assignment'),
        true
    )
) {

    $view_errors =
        "<h2>Contest 활동은 이 화면에서 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 소속 Lesson 확인
// ============================================================

$lesson_id = isset($activity['lesson_id'])
    ? intval($activity['lesson_id'])
    : 0;

$lesson = $lesson_id > 0
    ? course_get_lesson($lesson_id)
    : null;

if (
    !$lesson ||
    intval($lesson['course_id']) !== $course_id ||
    intval($lesson['status']) !== 1
) {

    $view_errors =
        "<h2>유효하지 않은 소속 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 일반 Activity 정보 수정
//
// 소속 Lesson, sort_order, Contest 연결은 변경하지 않는다.
// ============================================================

$result = pdo_query(
    "UPDATE course_activity
     SET
         activity_type = ?,
         title = ?,
         description = ?,
         visible = ?
     WHERE activity_id = ?
       AND course_id = ?
       AND lesson_id = ?
       AND status = 1
       AND activity_type IN ('material', 'assignment')",
    $activity_type,
    $title,
    $description,
    $visible,
    $activity_id,
    $course_id,
    $lesson_id
);

if ($result === false) {

    $view_errors =
        "<h2>학습 활동 수정에 실패했습니다. 서버 로그를 확인하세요.</h2>";

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
