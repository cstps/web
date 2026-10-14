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
// 7-1. 학습 자료 상세 본문 조회
// ============================================================

$view_material_html = '';

if ($view_activity['activity_type'] === 'material') {

    $material_rows = pdo_query(
        "SELECT content_html
         FROM course_material
         WHERE activity_id = ?
         LIMIT 1",
        $activity_id
    );

    if ($material_rows === false) {

        $view_errors =
            "<h2>학습 자료 본문을 불러오지 못했습니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    if (isset($material_rows[0])) {
        $view_material_html =
            (string)($material_rows[0]['content_html'] ?? '');
    }
}


// ============================================================
// ============================================================
// 7-2. 과제 상세 본문 조회
// ============================================================

$view_assignment_html = '';

$view_assignment_due_at = '';
$view_assignment_allow_late = 0;
$view_assignment_allow_resubmit = 1;

if ($view_activity['activity_type'] === 'assignment') {

    $assignment_rows = pdo_query(
        "SELECT
            content_html,
            due_at,
            allow_late,
            allow_resubmit
         FROM course_assignment
         WHERE activity_id = ?
         LIMIT 1",
        $activity_id
    );

    if ($assignment_rows === false) {

        $view_errors =
            "<h2>과제 상세 본문을 불러오지 못했습니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    if (isset($assignment_rows[0])) {
        $view_assignment_html =
            (string)$assignment_rows[0]['content_html'];

        $due_at = $assignment_rows[0]['due_at'];

        if ($due_at !== null && $due_at !== '') {
            $view_assignment_due_at = str_replace(
                ' ',
                'T',
                substr((string)$due_at, 0, 16)
            );
        }

        $view_assignment_allow_late =
            intval($assignment_rows[0]['allow_late']);

        $view_assignment_allow_resubmit =
            intval($assignment_rows[0]['allow_resubmit']);
    }
}


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
    'material' => '학습 자료',
    'assignment' => '학습 과제'
);


// ============================================================
// 9-1. 학습 자료 첨부파일 목록 조회
// ============================================================

$view_material_files = array();

if ($view_activity['activity_type'] === 'material') {

    $file_rows = pdo_query(
        "SELECT
            file_id,
            activity_id,
            original_name,
            mime_type,
            file_size,
            sort_order,
            created_at
         FROM course_material_file
         WHERE activity_id = ?
         ORDER BY sort_order, file_id",
        $activity_id
    );

    if ($file_rows === false) {

        $view_errors =
            "<h2>학습 자료 첨부파일 목록을 불러오지 못했습니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    $view_material_files = $file_rows;
}


// ============================================================
// 10. 수정 화면 출력
// ============================================================

require(
    "template/" .
    $OJ_TEMPLATE .
    "/course_activity_edit.php"
);
