<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/course_content_sanitizer.inc.php');

$view_title = '학습 자료 보기';

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    $view_errors = '<h2>로그인이 필요합니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity_id_raw = isset($_GET['activity_id'])
    && is_string($_GET['activity_id'])
    ? $_GET['activity_id']
    : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $activity_id_raw) ||
    strlen($activity_id_raw) > 10
) {
    $view_errors = '<h2>잘못된 학습 자료 번호입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity_id = intval($activity_id_raw);

if ($activity_id > 2147483647) {
    $view_errors = '<h2>잘못된 학습 자료 번호입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// Course, Lesson, Activity의 소속과 상태를 함께 조회한다.
$rows = pdo_query(
    "SELECT
        ca.activity_id,
        ca.course_id,
        ca.lesson_id,
        ca.title,
        ca.description,
        ca.visible AS activity_visible,
        ca.status AS activity_status,
        cl.title AS lesson_title,
        cl.visible AS lesson_visible,
        cl.status AS lesson_status,
        c.status AS course_status
     FROM course_activity ca
     INNER JOIN course_lesson cl
       ON cl.lesson_id = ca.lesson_id
      AND cl.course_id = ca.course_id
     INNER JOIN course c
       ON c.course_id = ca.course_id
     WHERE ca.activity_id = ?
       AND ca.activity_type = 'material'
     LIMIT 1",
    $activity_id
);

if ($rows === false || !isset($rows[0])) {
    $view_errors = '<h2>학습 자료를 찾을 수 없습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_activity = $rows[0];
$course_id = intval($view_activity['course_id']);

// 비활성 자료나 차시는 누구에게도 노출하지 않는다.
if (
    intval($view_activity['activity_status']) !== 1 ||
    intval($view_activity['lesson_status']) !== 1
) {
    $view_errors = '<h2>열람할 수 없는 학습 자료입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$is_manager = (
    course_can_access($course_id) &&
    course_can_manage_contests($course_id)
);

$is_student = course_is_active_student($course_id);

if (!$is_manager && !$is_student) {
    $view_errors = '<h2>학습 자료 열람 권한이 없습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// 학생은 공개된 차시와 자료만 열람할 수 있다.
// Course 종료 후에도 활성 수강 기록이 있으면 열람 가능하다.
if (
    !$is_manager &&
    (
        intval($view_activity['lesson_visible']) !== 1 ||
        intval($view_activity['activity_visible']) !== 1
    )
) {
    $view_errors = '<h2>공개되지 않은 학습 자료입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$material_rows = pdo_query(
    "SELECT content_html
     FROM course_material
     WHERE activity_id = ?
     LIMIT 1",
    $activity_id
);

if ($material_rows === false) {
    $view_errors = '<h2>학습 자료를 불러오지 못했습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_material_html = '';

if (isset($material_rows[0])) {
    $view_material_html = course_content_sanitize_html(
        (string)($material_rows[0]['content_html'] ?? '')
    );
}

// ============================================================
// 학습 자료 참고 링크 조회
// ============================================================

// ============================================================
// 학습 자료 첨부파일 목록 조회
// ============================================================

$view_material_files = array();

$file_rows = pdo_query(
    "SELECT
        file_id,
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
        '<h2>학습 자료 첨부파일 목록을 불러오지 못했습니다.</h2>';

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_material_files = $file_rows;

require("template/".$OJ_TEMPLATE."/course_material_view.php");
