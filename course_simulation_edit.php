<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once(__DIR__ . '/scripts/simulation_execution_config.php');

$view_title = "시뮬레이션 편집";

// ============================================================
// 1. 로그인 및 Activity 번호 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    $view_errors = "<h2>로그인이 필요합니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity_id = filter_input(
    INPUT_GET,
    'activity_id',
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
);

if (!$activity_id || $activity_id > 2147483647) {
    $view_errors = "<h2>잘못된 학습 활동 번호입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 2. Activity 및 Course 조회
// ============================================================

$activity_rows = pdo_query(
    "SELECT
        activity_id,
        course_id,
        lesson_id,
        activity_type,
        title,
        description,
        visible,
        status
     FROM course_activity
     WHERE activity_id = ?
     LIMIT 1",
    $activity_id
);

if (!$activity_rows || !isset($activity_rows[0])) {
    $view_errors = "<h2>존재하지 않는 학습 활동입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_activity = $activity_rows[0];
$course_id = intval($view_activity['course_id']);
$lesson_id = intval($view_activity['lesson_id']);

if ($view_activity['activity_type'] !== 'simulation') {
    $view_errors = "<h2>시뮬레이션 활동이 아닙니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_rows = pdo_query(
    "SELECT course_id, course_name, status
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

$view_course = $course_rows[0];

// ============================================================
// 3. 교사 관리 권한 및 활성 상태 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {
    $view_errors = "<h2>시뮬레이션 수정 권한이 없습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if (
    intval($view_course['status']) !== 1 ||
    intval($view_activity['status']) !== 1 ||
    $lesson_id <= 0
) {
    $view_errors = "<h2>수정할 수 없는 학습 활동입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_lesson = course_get_lesson($lesson_id);

if (
    !$view_lesson ||
    intval($view_lesson['course_id']) !== $course_id ||
    intval($view_lesson['status']) !== 1
) {
    $view_errors = "<h2>유효하지 않은 차시입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 4. 시뮬레이션 설정 조회
// 채점 기준 grading_config는 이 화면으로 전달하지 않는다.
// ============================================================

$simulation_rows = pdo_query(
    "SELECT
        simulation_mode,
        starter_code,
        canvas_width,
        canvas_height,
        allow_edit,
        allow_submit,
        source_language, execution_engine, execution_version
     FROM course_simulation
     WHERE activity_id = ?
     LIMIT 1",
    $activity_id
);

if (!$simulation_rows || !isset($simulation_rows[0])) {
    $view_errors = "<h2>시뮬레이션 설정을 찾을 수 없습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_simulation = $simulation_rows[0];

try {
    $view_simulation_execution = simulation_validate_execution_metadata(
        $view_simulation, true
    );
    $view_simulation_is_python =
        $view_simulation_execution['source_language'] === 'python';

    if (
        $view_simulation_is_python &&
        ((int)$view_simulation['canvas_width'] !== 400 ||
         (int)$view_simulation['canvas_height'] !== 400)
    ) {
        throw new RuntimeException('Python 캔버스 설정 불일치');
    }

    $view_simulation_language_label =
        $view_simulation_is_python ? 'Python' : 'p5.js';
    $view_simulation_fixed_canvas = $view_simulation_is_python ||
        $view_simulation['simulation_mode'] === 'drawing';

} catch (Throwable $error) {
    error_log('[course_simulation_edit] ' . $error->getMessage());
    http_response_code(409);
    $view_errors = '<h2>시뮬레이션의 실행 설정을 확인해 주세요.</h2>';
    require('template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

// ============================================================
// 5. 편집 화면 출력
// ============================================================

require(
    "template/" .
    $OJ_TEMPLATE .
    "/course_simulation_edit.php"
);
