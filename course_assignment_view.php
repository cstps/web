<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/course_content_sanitizer.inc.php');

$view_title = '학습 과제 보기';

// ============================================================
// 1. 로그인 검사
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    $view_errors = '<h2>로그인이 필요합니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 2. Activity ID 검증
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
    $view_errors = '<h2>잘못된 과제 번호입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity_id = intval($activity_id_raw);

// ============================================================
// 3. Course / Lesson / Assignment 조회
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
       AND ca.activity_type = 'assignment'
     LIMIT 1",
    $activity_id
);

if ($rows === false || !isset($rows[0])) {
    $view_errors = '<h2>학습 과제를 찾을 수 없습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_activity = $rows[0];
$course_id = intval($view_activity['course_id']);

// ============================================================
// 4. 활성 상태 확인
// ============================================================

if (
    intval($view_activity['activity_status']) !== 1 ||
    intval($view_activity['lesson_status']) !== 1
) {
    $view_errors = '<h2>열람할 수 없는 학습 과제입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 5. Course 접근 권한 확인
// ============================================================

$is_manager = (
    course_can_access($course_id) &&
    course_can_manage_contests($course_id)
);

$is_student = course_is_active_student($course_id);

if (!$is_manager && !$is_student) {
    $view_errors = '<h2>학습 과제 열람 권한이 없습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// 학생은 공개된 차시 및 과제만 열람한다.
// Course 종료 후에도 활성 수강 기록이 있으면 열람 가능하다.
if (
    !$is_manager &&
    (
        intval($view_activity['lesson_visible']) !== 1 ||
        intval($view_activity['activity_visible']) !== 1
    )
) {
    $view_errors = '<h2>공개되지 않은 학습 과제입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 6. 과제 상세 본문 조회
// ============================================================

$assignment_rows = pdo_query(
    "SELECT
        content_html,
        due_at,
        allow_late,
        allow_resubmit,
        CASE
            WHEN due_at IS NOT NULL
             AND NOW() > due_at
            THEN 1
            ELSE 0
        END AS is_late
     FROM course_assignment
     WHERE activity_id = ?
     LIMIT 1",
    $activity_id
);

if ($assignment_rows === false) {
    $view_errors = '<h2>학습 과제를 불러오지 못했습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_assignment_html = '';
$view_assignment_settings = null;

if (isset($assignment_rows[0])) {
    $view_assignment_html =
        (string)$assignment_rows[0]['content_html'];

    $view_assignment_settings = array(
        'due_at' => $assignment_rows[0]['due_at'],
        'allow_late' => intval($assignment_rows[0]['allow_late']),
        'allow_resubmit' => intval($assignment_rows[0]['allow_resubmit']),
        'is_late' => intval($assignment_rows[0]['is_late'])
    );
}

// 저장된 HTML도 출력 전 다시 정제한다.
$view_assignment_html = course_content_sanitize_html(
    $view_assignment_html
);

// ============================================================
// 6-1. 학생별 제출 현황 및 제출 가능 여부
// ============================================================

$view_latest_submission = null;
$view_can_submit = false;
$view_submit_message = '';

if (!$is_manager && $is_student) {

    $user_id = (string)$_SESSION[$OJ_NAME.'_user_id'];

    $submission_rows = pdo_query(
        "SELECT
            submission_no,
            submitted_at
         FROM course_assignment_submission
         WHERE activity_id = ?
           AND user_id = ?
         ORDER BY submission_no DESC
         LIMIT 1",
        $activity_id,
        $user_id
    );

    if ($submission_rows === false) {
        $view_errors = '<h2>제출 현황을 불러오지 못했습니다.</h2>';
        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    if (isset($submission_rows[0])) {
        $view_latest_submission = $submission_rows[0];
    }

    if ($view_assignment_settings === null) {
        $view_submit_message = '과제 제출 설정이 없습니다.';

    } elseif (intval($view_activity['course_status']) !== 1) {
        $view_submit_message = '현재 종료되었거나 비활성화된 수업입니다.';

    } elseif (
        $view_latest_submission !== null &&
        $view_assignment_settings['allow_resubmit'] !== 1
    ) {
        $view_submit_message = '이 과제는 재제출할 수 없습니다.';

    } elseif (
        $view_assignment_settings['is_late'] === 1 &&
        $view_assignment_settings['allow_late'] !== 1
    ) {
        $view_submit_message = '과제 제출 기한이 지났습니다.';

    } else {
        $view_can_submit = true;
    }
}


// ============================================================
// 6-2. 학생 본인의 제출 이력 조회
// ============================================================

$view_submission_history = array();

if (!$is_manager && $is_student) {

    $user_id = (string)$_SESSION[$OJ_NAME.'_user_id'];

    $history_rows = pdo_query(
        "SELECT
            submission_id,
            submission_no,
            content_text,
            submitted_at
         FROM course_assignment_submission
         WHERE activity_id = ?
           AND user_id = ?
         ORDER BY submission_no DESC
         LIMIT 50",
        $activity_id,
        $user_id
    );

    if ($history_rows === false) {

        $view_errors =
            '<h2>과제 제출 이력을 불러오지 못했습니다.</h2>';

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    $view_submission_history = $history_rows;
}


// 학생 본인의 제출 파일만 조회한다.
$view_submission_files = array();

if (!$is_manager && $is_student) {
    $file_rows = pdo_query(
        "SELECT
            f.file_id,
            f.submission_id,
            f.original_name
         FROM course_assignment_submission_file f
         INNER JOIN course_assignment_submission s
           ON s.submission_id = f.submission_id
         WHERE s.activity_id = ?
           AND s.user_id = ?
         ORDER BY f.file_id",
        $activity_id,
        (string)$_SESSION[$OJ_NAME.'_user_id']
    );

    if ($file_rows === false) {
        $view_errors =
            '<h2>제출 첨부파일을 불러오지 못했습니다.</h2>';
        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    foreach ($file_rows as $file) {
        $sid = (string)$file['submission_id'];

        if (!isset($view_submission_files[$sid])) {
            $view_submission_files[$sid] = array();
        }

        $view_submission_files[$sid][] = $file;
    }
}


// ============================================================
// 7. 화면 출력
// ============================================================

require(
    "template/" .
    $OJ_TEMPLATE .
    "/course_assignment_view.php"
);
