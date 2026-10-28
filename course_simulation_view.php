<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once(__DIR__ . '/scripts/simulation_execution_config.php');

$view_title = 'p5.js 시뮬레이션';

// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    $view_errors = '<h2>로그인이 필요합니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 2. Activity 번호 확인
// ============================================================

$activity_id_raw = (
    isset($_GET['activity_id']) &&
    is_string($_GET['activity_id'])
) ? $_GET['activity_id'] : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $activity_id_raw) ||
    strlen($activity_id_raw) > 10 ||
    (float)$activity_id_raw > 2147483647
) {
    $view_errors = '<h2>잘못된 시뮬레이션 번호입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity_id = intval($activity_id_raw);

// ============================================================
// 3. Course / Lesson / Activity 조회
// ============================================================

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
       AND ca.activity_type = 'simulation'

     LIMIT 1",
    $activity_id
);

if ($rows === false || !isset($rows[0])) {
    $view_errors = '<h2>시뮬레이션 활동을 찾을 수 없습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_activity = $rows[0];
$course_id = intval($view_activity['course_id']);

// ============================================================
// 4. Activity 및 Lesson 활성 상태 확인
// ============================================================

if (
    intval($view_activity['activity_status']) !== 1 ||
    intval($view_activity['lesson_status']) !== 1
) {
    $view_errors = '<h2>열람할 수 없는 시뮬레이션입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 5. 교사 / 학생 접근 권한 확인
// ============================================================

$is_manager = (
    course_can_access($course_id) &&
    course_can_manage_contests($course_id)
);

$is_student = course_is_active_student($course_id);

if (!$is_manager && !$is_student) {
    $view_errors = '<h2>시뮬레이션 열람 권한이 없습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// 학생은 공개된 Lesson과 Activity만 열람한다.
if (
    !$is_manager &&
    (
        intval($view_activity['lesson_visible']) !== 1 ||
        intval($view_activity['activity_visible']) !== 1
    )
) {
    $view_errors = '<h2>공개되지 않은 시뮬레이션입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 6. 시뮬레이션 실행 설정 조회
//
// grading_config는 학생 브라우저에 전달하지 않는다.
// ============================================================

$simulation_rows = pdo_query(
    "SELECT
        simulation_mode,
        starter_code,
        canvas_width,
        canvas_height,
        allow_edit,
        allow_submit,
        source_language,
        execution_engine,
        execution_version

     FROM course_simulation

     WHERE activity_id = ?
     LIMIT 1",
    $activity_id
);

if (
    $simulation_rows === false ||
    !isset($simulation_rows[0])
) {
    $view_errors = '<h2>시뮬레이션 설정을 찾을 수 없습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_simulation = $simulation_rows[0];

try {
    $view_simulation_execution = simulation_validate_execution_metadata(
        $view_simulation,
        true
    );

    if (
        $view_simulation_execution['source_language'] === 'python' &&
        (
            (int)$view_simulation['canvas_width'] !== 400 ||
            (int)$view_simulation['canvas_height'] !== 400
        )
    ) {
        throw new RuntimeException('Python 캔버스 설정 불일치');
    }

    $view_title = $view_simulation_execution['source_language'] === 'python'
        ? 'Python 시뮬레이션'
        : 'p5.js 시뮬레이션';

} catch (Throwable $exception) {
    error_log('[course_simulation_execution] ' . $exception->getMessage());
    http_response_code(500);
    $view_errors = '<h2>시뮬레이션 실행 설정을 확인하지 못했습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// 검사 완료된 최신 목표 그림의 표시 정보만 템플릿에 전달한다.
$view_simulation_target = null;

if ((string)$view_simulation['simulation_mode'] === 'drawing') {
    try {
        require_once(__DIR__ . '/scripts/course_simulation_target_image.php');

        $verified_target = course_simulation_target_image(
            $dbh, $activity_id, $view_simulation
        );

        if ($verified_target !== null) {
            $view_simulation_target = array(
                'canvas_width' => $verified_target['canvas_width'],
                'canvas_height' => $verified_target['canvas_height'],
                'target_ready' => 1,
            );
        }

        unset($verified_target);

    } catch (Throwable $error) {
        error_log('[course_simulation_view] ' . $error->getMessage());
    }
}

// ============================================================
// 학생 본인의 시뮬레이션 정식 제출 이력
// ============================================================

$view_simulation_submissions = [];
$view_latest_simulation_submission = null;

$view_simulation_performance_cutoff = null;

if ($is_student) {
    require_once(
        __DIR__ . '/scripts/course_simulation_performance_policy.php'
    );

    try {
        $view_simulation_performance_cutoff =
            simulation_performance_history_cutoff(
                (string)$_SESSION[$OJ_NAME.'_user_id'],
                isset($_SESSION[$OJ_NAME.'_source_browser'])
            );
    } catch (Throwable $e) {
        error_log('[simulation_performance_view] ' . $e->getMessage());
        http_response_code(500);
        $view_errors =
            '<h2>수행모드 적용 상태를 확인하지 못했습니다. 잠시 후 다시 시도해 주세요.</h2>';
        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    $submission_rows = pdo_query(
        "SELECT
            s.submission_id,
            s.submission_no,
            s.source_code,
            s.submitted_at,
            g.grading_status,
            g.score AS grade_score,
            g.max_score AS grade_max_score,
            JSON_EXTRACT(
                g.feedback_json, '$.result.metrics.region_comparison'
            ) AS grade_region_comparison_json
         FROM course_simulation_submission s
         LEFT JOIN course_simulation_grade g
           ON g.grade_id = (
               SELECT g2.grade_id
               FROM course_simulation_grade g2
               WHERE g2.submission_id = s.submission_id
               ORDER BY g2.grading_no DESC
               LIMIT 1
           )
         WHERE s.activity_id = ?
           AND s.user_id = ?
           AND (? IS NULL OR s.submitted_at >= ?)
         ORDER BY s.submission_no DESC
         LIMIT 10",
        $activity_id,
        (string)$_SESSION[$OJ_NAME.'_user_id'],
        $view_simulation_performance_cutoff,
        $view_simulation_performance_cutoff
    );

    if ($submission_rows === false) {
        $view_errors =
            '<h2>시뮬레이션 제출 기록을 불러오지 못했습니다.</h2>';

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    $view_simulation_submissions = $submission_rows;

    if (!empty($view_simulation_submissions)) {
        $view_latest_simulation_submission =
            $view_simulation_submissions[0];
    }
}


// 학생 본인의 최신 작업 코드 조회
// 제출 완료 안내는 본인의 실제 제출 기록과 일치할 때만 표시
$view_successful_submission = null;

$submitted_id = $_GET['submitted'] ?? null;

if (
    $is_student &&
    is_string($submitted_id) &&
    preg_match('/^[1-9][0-9]*$/D', $submitted_id) &&
    strlen($submitted_id) <= 19
) {
    foreach ($view_simulation_submissions as $submission) {
        if (
            (string)$submission['submission_id']
            === $submitted_id
        ) {
            $view_successful_submission = $submission;
            break;
        }
    }
}

$view_draft_code = null;
$view_draft_revision = '0';

if (
    $is_student &&
    intval($view_simulation['allow_edit']) === 1
) {
    require_once(
        __DIR__ . '/scripts/course_simulation_scoped_draft_store.php'
    );

    try {
        $scoped_draft = simulation_load_scoped_draft(
            $dbh,
            $activity_id,
            (string)$_SESSION[$OJ_NAME.'_user_id'],
            $view_simulation_performance_cutoff
        );
        $draft_rows = $scoped_draft === null
            ? array()
            : array($scoped_draft);
    } catch (Throwable $e) {
        error_log('[simulation_draft_load] ' . $e->getMessage());
        $draft_rows = false;
    }

    if ($draft_rows === false) {
        $view_errors =
            '<h2>저장된 작업 코드를 불러오지 못했습니다.</h2>';

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    if (isset($draft_rows[0])) {
        $view_draft_revision = (string)$draft_rows[0]['revision'];

        $view_draft_code =
            (string)$draft_rows[0]['source_code'];
    }
}

// ============================================================
// 7. 학생용 실행 화면 출력
// ============================================================

require(
    "template/" .
    $OJ_TEMPLATE .
    "/course_simulation_view.php"
);
