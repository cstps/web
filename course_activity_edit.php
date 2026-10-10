<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "학습 활동 수정";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. Activity ID 확인
// ============================================================

$activity_id =
    isset($_GET['activity_id'])
        ? intval($_GET['activity_id'])
        : 0;

if ($activity_id <= 0) {

    $view_errors =
        "<h2>잘못된 학습 활동 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Activity 조회
// ============================================================

$activity_rows = pdo_query(
    "SELECT
        activity_id,
        course_id,
        lesson_id,
        activity_type,
        title,
        description,
        sort_order,
        visible,
        status
     FROM course_activity
     WHERE activity_id = ?
     LIMIT 1",
    $activity_id
);

if (
    !$activity_rows ||
    !isset($activity_rows[0]['activity_id'])
) {

    $view_errors =
        "<h2>존재하지 않는 학습 활동입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_activity = $activity_rows[0];

$course_id = intval($view_activity['course_id']);


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

    $view_errors =
        "<h2>존재하지 않는 수업입니다.</h2>";

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
        "<h2>이 학습 활동을 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. Course 및 Activity 상태 확인
// ============================================================

if (
    intval($view_course['status']) !== 1 ||
    intval($view_activity['status']) !== 1
) {

    $view_errors =
        "<h2>비활성 상태의 수업 또는 활동은 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 일반 Activity 유형 확인
//
// Contest Activity는 기존 Contest 관리 기능을 사용한다.
// ============================================================

if (
    !in_array(
        $view_activity['activity_type'],
        array('material', 'assignment'),
        true
    )
) {

    $view_errors =
        "<h2>이 유형의 활동은 일반 학습 활동 수정 화면에서 관리할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 소속 Lesson 확인
// ============================================================

$lesson_id =
    isset($view_activity['lesson_id'])
        ? intval($view_activity['lesson_id'])
        : 0;

if ($lesson_id <= 0) {

    $view_errors =
        "<h2>차시에 배정되지 않은 활동은 현재 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_lesson = course_get_lesson($lesson_id);

if (
    !$view_lesson ||
    intval($view_lesson['course_id']) !== $course_id ||
    intval($view_lesson['status']) !== 1
) {

    $view_errors =
        "<h2>유효하지 않은 소속 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. 수정 가능한 Activity 유형
// ============================================================

$view_activity_types = array(
    'material' => '학습 자료 안내',
    'assignment' => '학습 활동·과제 안내'
);


// ============================================================
// 10. 수정 화면 출력
// ============================================================

require(
    "template/" .
    $OJ_TEMPLATE .
    "/course_activity_edit.php"
);
