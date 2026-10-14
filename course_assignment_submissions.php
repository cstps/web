<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = '과제 제출 현황';

function assignment_submissions_error($message, $status = 400)
{
    global $view_errors, $OJ_TEMPLATE;

    http_response_code($status);

    $view_errors = '<h2>' .
        htmlspecialchars($message, ENT_QUOTES, 'UTF-8') .
        '</h2>';

    require("template/".$OJ_TEMPLATE."/error.php");
    exit;
}

// ============================================================
// 1. 로그인 및 Activity 번호 검사
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    assignment_submissions_error(
        '로그인이 필요합니다.',
        403
    );
}

$activity_raw = (
    isset($_GET['activity_id']) &&
    is_string($_GET['activity_id'])
) ? $_GET['activity_id'] : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $activity_raw) ||
    strlen($activity_raw) > 10 ||
    (float)$activity_raw > 2147483647
) {
    assignment_submissions_error(
        '잘못된 과제 번호입니다.'
    );
}

$activity_id = intval($activity_raw);

// ============================================================
// 2. 과제 및 Course 확인
// ============================================================

$rows = pdo_query(
    "SELECT
        a.activity_id,
        a.course_id,
        a.lesson_id,
        a.title,
        a.description,
        a.status AS activity_status,
        l.title AS lesson_title,
        l.status AS lesson_status
     FROM course_activity a
     INNER JOIN course_lesson l
       ON l.lesson_id = a.lesson_id
      AND l.course_id = a.course_id
     INNER JOIN course_assignment ca
       ON ca.activity_id = a.activity_id
     WHERE a.activity_id = ?
       AND a.activity_type = 'assignment'
     LIMIT 1",
    $activity_id
);

if ($rows === false || !isset($rows[0])) {
    assignment_submissions_error(
        '과제를 찾을 수 없습니다.',
        404
    );
}

$view_activity = $rows[0];
$course_id = intval($view_activity['course_id']);

// ============================================================
// 3. Course 관리자 권한 검사
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {
    assignment_submissions_error(
        '과제 제출 현황 조회 권한이 없습니다.',
        403
    );
}

// ============================================================
// 4. 학생별 제출 현황 조회
// ============================================================

// 현재 활성 수강생을 기준으로 목록 구성.
// 제출하지 않은 학생도 조회한다.
$student_rows = pdo_query(
    "SELECT
        cs.user_id,
        cs.student_no,
        COALESCE(s.submission_count, 0) AS submission_count,
        s.latest_no,
        s.latest_at
     FROM course_student cs
     LEFT JOIN (
         SELECT
             user_id,
             COUNT(*) AS submission_count,
             MAX(submission_no) AS latest_no,
             MAX(submitted_at) AS latest_at
         FROM course_assignment_submission
         WHERE activity_id = ?
         GROUP BY user_id
     ) s
       ON s.user_id = cs.user_id
     WHERE cs.course_id = ?
       AND cs.status = 1
     ORDER BY
         cs.student_no,
         cs.user_id",
    $activity_id,
    $course_id
);

if ($student_rows === false) {
    assignment_submissions_error(
        '학생별 제출 현황을 불러오지 못했습니다.',
        500
    );
}

$view_students = $student_rows;

$view_total_students = count($view_students);
$view_submitted_students = 0;

foreach ($view_students as $student) {
    if (intval($student['submission_count']) > 0) {
        $view_submitted_students++;
    }
}

$view_not_submitted_students =
    $view_total_students - $view_submitted_students;

// ============================================================
// 5. 학생별 제출 답안 조회
// ============================================================

// 전체 제출 이력을 조회한다.
// 화면에서 학생별로 구분하여 표시한다.
$history_rows = pdo_query(
    "SELECT
        s.submission_id,
        s.user_id,
        s.submission_no,
        s.content_text,
        s.submitted_at
     FROM course_assignment_submission s
     INNER JOIN course_student cs
       ON cs.course_id = ?
      AND cs.user_id = s.user_id
      AND cs.status = 1
     WHERE s.activity_id = ?
     ORDER BY
        s.user_id,
        s.submission_no DESC",
    $course_id,
    $activity_id
);

if ($history_rows === false) {
    assignment_submissions_error(
        '과제 제출 답안을 불러오지 못했습니다.',
        500
    );
}

$view_submission_history = array();

foreach ($history_rows as $submission) {
    $user_id = (string)$submission['user_id'];

    if (!isset($view_submission_history[$user_id])) {
        $view_submission_history[$user_id] = array();
    }

    $view_submission_history[$user_id][] = $submission;
}

// 해당 Course의 활성 수강생이 제출한 파일만 조회한다.
$view_submission_files = array();

$file_rows = pdo_query(
    "SELECT
        f.file_id,
        f.submission_id,
        f.original_name
     FROM course_assignment_submission_file f
     INNER JOIN course_assignment_submission s
       ON s.submission_id = f.submission_id
     INNER JOIN course_student cs
       ON cs.course_id = ?
      AND cs.user_id = s.user_id
      AND cs.status = 1
     WHERE s.activity_id = ?
     ORDER BY f.file_id",
    $course_id,
    $activity_id
);

if ($file_rows === false) {
    assignment_submissions_error(
        '제출 첨부파일을 불러오지 못했습니다.',
        500
    );
}

foreach ($file_rows as $file) {
    $sid = (string)$file['submission_id'];

    if (!isset($view_submission_files[$sid])) {
        $view_submission_files[$sid] = array();
    }

    $view_submission_files[$sid][] = $file;
}


// ============================================================
// 6. 화면 출력
// ============================================================

require(
    "template/" .
    $OJ_TEMPLATE .
    "/course_assignment_submissions.php"
);
