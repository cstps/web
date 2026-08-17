<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "차시 문제 선택";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$user_id = $_SESSION[$OJ_NAME.'_user_id'];


// ============================================================
// 2. course_id 확인
// ============================================================

if (
    !isset($_GET['course_id']) ||
    intval($_GET['course_id']) <= 0
) {

    $view_errors = "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($_GET['course_id']);


// ============================================================
// 3. lesson_no 확인
// ============================================================

if (
    !isset($_GET['lesson_no']) ||
    intval($_GET['lesson_no']) <= 0
) {

    $view_errors = "<h2>잘못된 차시 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$lesson_no = intval($_GET['lesson_no']);


// ============================================================
// 4. source_contest_id 확인
// ============================================================

if (
    !isset($_GET['source_contest_id']) ||
    intval($_GET['source_contest_id']) <= 0
) {

    $view_errors = "<h2>잘못된 원본 대회 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$source_contest_id = intval($_GET['source_contest_id']);


// ============================================================
// 5. Course 존재 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
        school,
        school_year,
        semester,
        status,
        created_by
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
// 6. Course 접근 권한 확인
// ============================================================

if (!course_can_access($course_id)) {

    $view_errors =
        "<h2>이 수업을 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 차시 관리 권한 확인
// ============================================================

if (!course_can_manage_contests($course_id)) {

    $view_errors =
        "<h2>이 수업의 차시를 관리할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 종료된 Course 확인
// ============================================================

if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 새로운 차시를 추가할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. 원본 Contest 존재 확인
// ============================================================

$contest_rows = pdo_query(
    "SELECT
        contest_id,
        title,
        start_time,
        end_time,
        defunct
     FROM contest
     WHERE contest_id = ?
     LIMIT 1",
    $source_contest_id
);

if (
    !$contest_rows ||
    !isset($contest_rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>존재하지 않는 원본 대회입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_source_contest = $contest_rows[0];

// ============================================================
// 10. 원본 Contest 사용 권한 확인
// ============================================================

$can_use_source_contest =
    isset($_SESSION[$OJ_NAME.'_administrator']) ||
    isset($_SESSION[$OJ_NAME.'_contest_creator']) ||
    isset($_SESSION[$OJ_NAME.'_m'.$source_contest_id]);

if (!$can_use_source_contest) {

    $view_errors =
        "<h2>이 대회의 문제 구성을 가져올 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 11. 원본 Contest 문제 목록 조회
// ============================================================

$view_problems = pdo_query(
    "SELECT
        cp.problem_id,
        cp.num,
        cp.score,

        COALESCE(
            NULLIF(cp.title, ''),
            p.title
        ) AS title

     FROM contest_problem cp

     LEFT JOIN problem p
       ON p.problem_id = cp.problem_id

     WHERE cp.contest_id = ?

     ORDER BY
        cp.num,
        cp.problem_id",
    $source_contest_id
);

if (!is_array($view_problems)) {
    $view_problems = array();
}


// ============================================================
// 12. 문제 없음 확인
// ============================================================

if (empty($view_problems)) {

    $view_errors =
        "<h2>이 대회에는 가져올 문제가 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 13. 화면 출력
// ============================================================

require(
    "template/".$OJ_TEMPLATE."/course_contest_problem_select.php"
);