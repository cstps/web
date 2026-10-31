<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "학습 활동 추가";


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
        "<h2>이 수업의 학습 활동을 추가할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. Course 및 Lesson 상태 확인
// ============================================================

if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 학습 활동을 추가할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if (intval($view_lesson['status']) !== 1) {

    $view_errors =
        "<h2>비활성화된 차시에는 학습 활동을 추가할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. Activity 유형
//
// ============================================================

$view_activity_types = array(
    'material' => '학습 자료 안내',
    'assignment' => '학습 활동·과제 안내',
    'contest' => '코딩 실습 (Contest)',
    'simulation' => '시뮬레이션 직접 만들기',
    'simulation_import' => '시뮬레이션 문제 가져오기'
);


// ============================================================
// 8. 생성 화면 출력
// ============================================================

// 가져오기는 생성 POST와 분리하여 수업·차시를 유지한 GET 화면으로 이동한다.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    $view_errors = '<h2>GET 요청으로 활동 추가 화면을 열어 주세요.</h2>';
    require('template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

$view_selected_activity_type = $_GET['activity_type'] ?? 'material';
if (!is_string($view_selected_activity_type) ||
    !array_key_exists($view_selected_activity_type, $view_activity_types)) {
    http_response_code(400);
    $view_errors = '<h2>올바른 활동 유형을 선택해 주세요.</h2>';
    require('template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

if ($view_selected_activity_type === 'contest') {
    header('Cache-Control: no-store, private');
    header('Location: course_contest_add.php?' . http_build_query(array(
        'course_id' => (string)$course_id,
        'lesson_id' => (string)$view_lesson['lesson_id'],
    )), true, 302);
    exit;
}

if ($view_selected_activity_type === 'simulation_import') {
    header('Cache-Control: no-store, private');
    header('Location: course_simulation_problem_import_list.php?' .
        http_build_query(array(
            'course_id' => (string)$course_id,
            'lesson_id' => (string)$view_lesson['lesson_id'],
        )), true, 302);
    exit;
}

require(
    "template/" .
    $OJ_TEMPLATE .
    "/course_activity_add.php"
);
