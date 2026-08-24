<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "내 수업";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$user_id =
    $_SESSION[$OJ_NAME.'_user_id'];


// ============================================================
// 2. course_id 확인
// ============================================================

if (
    !isset($_GET['course_id']) ||
    intval($_GET['course_id']) <= 0
) {

    $view_errors =
        "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$course_id =
    intval($_GET['course_id']);


// ============================================================
// 3. Course 존재 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
        school,
        school_year,
        semester,
        description,
        status,
        created_by,
        created_at,
        updated_at

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


$view_course =
    $course_rows[0];


// ============================================================
// 4. 학생 수강 권한 확인
//
// 교사용 course_can_access()를 사용하지 않는다.
// Course가 종료됐더라도 활성 수강생은 기록을 열람할 수 있다.
// ============================================================

if (!course_is_active_student($course_id)) {

    $view_errors =
        "<h2>이 수업을 볼 수강 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 학생에게 공개된 활성 차시 조회
//
// - 제거된 차시 제외
// - 숨김 차시 제외
// - contest.defunct와 무관하게 Course 차시로 표시
// - 현재 학생의 제출·해결 기록만 조회
// ============================================================

$view_contests = pdo_query(
    "SELECT
        cc.id,
        cc.contest_id,
        cc.source_contest_id,
        cc.link_type,
        cc.lesson_no,
        cc.sort_order,
        cc.created_at,

        c.title,
        c.start_time,
        c.end_time,
        c.defunct,

        CASE
            WHEN c.start_time IS NULL
            OR c.end_time IS NULL
                THEN 'unscheduled'

            WHEN NOW() < c.start_time
                THEN 'upcoming'

            WHEN NOW() <= c.end_time
                THEN 'ongoing'

            ELSE 'ended'
        END AS lesson_status,

        (
            SELECT COUNT(*)

            FROM contest_problem cp

            WHERE cp.contest_id = cc.contest_id
        ) AS problem_count,

        (
            SELECT COUNT(*)

            FROM solution s

            WHERE s.contest_id = cc.contest_id
              AND s.user_id = ?
        ) AS submission_count,

        (
            SELECT COUNT(
                DISTINCT s.problem_id
            )

            FROM solution s

            WHERE s.contest_id = cc.contest_id
              AND s.user_id = ?
              AND s.result = 4
        ) AS solved_count,

        (
            SELECT MAX(s.in_date)

            FROM solution s

            WHERE s.contest_id = cc.contest_id
              AND s.user_id = ?
        ) AS last_activity

    FROM course_contest cc

    INNER JOIN contest c
        ON c.contest_id = cc.contest_id

    WHERE cc.course_id = ?
      AND cc.status = 1
      AND cc.visible = 1

    ORDER BY
        cc.lesson_no,
        cc.contest_id",
    $user_id,
    $user_id,
    $user_id,
    $course_id
);


if (!is_array($view_contests)) {
    $view_contests = array();
}


// ============================================================
// 6. Course 전체 학습 요약
// ============================================================

$view_summary = array(
    'lesson_count'         => 0,
    'ongoing_lesson_count' => 0,
    'problem_count'        => 0,
    'solved_count'         => 0,
    'submission_count'     => 0
);


foreach ($view_contests as $contest) {

    $view_summary['lesson_count']++;

    $view_summary['problem_count'] +=
        intval($contest['problem_count']);

    $view_summary['solved_count'] +=
        intval($contest['solved_count']);

    $view_summary['submission_count'] +=
        intval($contest['submission_count']);


    if (
        intval($view_course['status']) === 1 &&
        isset($contest['lesson_status']) &&
        $contest['lesson_status'] === 'ongoing'
    ) {

        $view_summary['ongoing_lesson_count']++;
    }
}


// ============================================================
// 7. 화면 출력
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/my_course_view.php"
);