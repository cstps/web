<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "학습 활동 복구";


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

if (
    !preg_match('/^[1-9][0-9]*$/D', $course_id_raw) ||
    !preg_match('/^[1-9][0-9]*$/D', $activity_id_raw) ||
    strlen($course_id_raw) > 10 ||
    strlen($activity_id_raw) > 10
) {

    $view_errors = "<h2>잘못된 요청 정보입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($course_id_raw);
$activity_id = intval($activity_id_raw);

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
// 3. Course 관리 권한 검사
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>학습 활동을 복구할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course 및 Activity 조회
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

    $view_errors =
        "<h2>해당 수업의 학습 활동을 찾을 수 없습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity = $rows[0];


// ============================================================
// 5. Course / Lesson / Activity 상태 확인
// ============================================================

if (
    intval($activity['course_status']) !== 1 ||
    intval($activity['lesson_status']) !== 1
) {

    $view_errors =
        "<h2>활성 상태의 수업과 차시에서만 활동을 복구할 수 있습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if (intval($activity['activity_status']) !== 0) {

    $view_errors =
        "<h2>비활성 상태의 활동만 복구할 수 있습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 일반 Activity 유형만 복구 허용
// ============================================================

if (
    !in_array(
        $activity['activity_type'],
        array('material', 'assignment'),
        true
    )
) {

    $view_errors =
        "<h2>Contest 활동은 이 기능으로 복구할 수 없습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 일반 Activity 복구
//
// 복구 직후 visible=0으로 유지한다.
// ============================================================

$result = pdo_query(
    "UPDATE course_activity
     SET
         status = 1,
         visible = 0
     WHERE activity_id = ?
       AND course_id = ?
       AND lesson_id = ?
       AND status = 0
       AND activity_type IN ('material', 'assignment')",
    $activity_id,
    $course_id,
    intval($activity['lesson_id'])
);

if ($result === false) {

    $view_errors =
        "<h2>학습 활동 복구에 실패했습니다. 서버 로그를 확인하세요.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. Course 관리 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=" .
    $course_id
);

exit(0);
