<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "학습 활동 공개 설정";


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
// 2. 입력값 검증
// ============================================================

$course_id_raw =
    isset($_POST['course_id']) &&
    is_string($_POST['course_id'])
        ? trim($_POST['course_id'])
        : '';

$activity_id_raw =
    isset($_POST['activity_id']) &&
    is_string($_POST['activity_id'])
        ? trim($_POST['activity_id'])
        : '';

$visible_raw =
    isset($_POST['visible']) &&
    is_string($_POST['visible'])
        ? trim($_POST['visible'])
        : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $course_id_raw) ||
    !preg_match('/^[1-9][0-9]*$/D', $activity_id_raw) ||
    strlen($course_id_raw) > 10 ||
    strlen($activity_id_raw) > 10 ||
    !in_array($visible_raw, array('0', '1'), true)
) {

    $view_errors = "<h2>잘못된 요청 정보입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($course_id_raw);
$activity_id = intval($activity_id_raw);
$visible = intval($visible_raw);

if (
    $course_id <= 0 ||
    $course_id > 2147483647 ||
    $activity_id <= 0 ||
    $activity_id > 2147483647
) {

    $view_errors = "<h2>잘못된 번호입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Course 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>학습 활동 공개 상태를 변경할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course 및 Activity 확인
// ============================================================

$rows = pdo_query(
    "SELECT
        ca.activity_id,
        ca.course_id,
        ca.lesson_id,
        ca.activity_type,
        ca.status AS activity_status,
        c.status AS course_status,
        cl.status AS lesson_status
     FROM course_activity ca
     INNER JOIN course c
       ON c.course_id = ca.course_id
     INNER JOIN course_lesson cl
       ON cl.lesson_id = ca.lesson_id
      AND cl.course_id = ca.course_id
     WHERE ca.activity_id = ?
       AND ca.course_id = ?
     LIMIT 1",
    $activity_id,
    $course_id
);

if (
    !$rows ||
    !isset($rows[0]['activity_id'])
) {

    $view_errors = "<h2>학습 활동을 찾을 수 없습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity = $rows[0];

if (
    intval($activity['course_status']) !== 1 ||
    intval($activity['lesson_status']) !== 1 ||
    intval($activity['activity_status']) !== 1
) {

    $view_errors =
        "<h2>비활성 상태의 수업·차시·활동은 변경할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 일반 Activity 유형만 허용
// ============================================================

if (
    !in_array(
        $activity['activity_type'],
        array('material', 'assignment'),
        true
    )
) {

    $view_errors =
        "<h2>Contest 활동은 이 화면에서 변경할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. Activity 공개 상태 변경
//
// Lesson과 Contest는 변경하지 않는다.
// ============================================================

$result = pdo_query(
    "UPDATE course_activity
     SET visible = ?
     WHERE activity_id = ?
       AND course_id = ?
       AND lesson_id = ?
       AND status = 1
       AND activity_type IN ('material', 'assignment')",
    $visible,
    $activity_id,
    $course_id,
    intval($activity['lesson_id'])
);

if ($result === false) {

    $view_errors =
        "<h2>공개 상태 변경에 실패했습니다. 서버 로그를 확인하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. Course 관리 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=" .
    $course_id
);

exit(0);
