<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "차시 문제 구성";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. course_id / contest_id 확인
// ============================================================

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;

$contest_id =
    isset($_GET['contest_id'])
        ? intval($_GET['contest_id'])
        : 0;


if (
    $course_id <= 0 ||
    $contest_id <= 0
) {

    $view_errors =
        "<h2>잘못된 차시 정보입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Course 접근 및 차시 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 문제 구성을 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course ↔ Contest 연결 확인
// ============================================================

$link_rows = pdo_query(
    "SELECT
        cc.id,
        cc.course_id,
        cc.contest_id,
        cc.source_contest_id,
        cc.lesson_no,
        cc.visible,

        c.title

     FROM course_contest cc

     INNER JOIN contest c
       ON c.contest_id = cc.contest_id

     WHERE cc.course_id = ?
       AND cc.contest_id = ?

     LIMIT 1",
    $course_id,
    $contest_id
);


if (
    !$link_rows ||
    !isset($link_rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$view_contest = $link_rows[0];


// ============================================================
// 5. Course 정보
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


if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업의 문제 구성은 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 현재 Course Contest 문제 구성
// ============================================================

$view_current_problems = pdo_query(
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
    $contest_id
);


if (!is_array($view_current_problems)) {
    $view_current_problems = array();
}


// ============================================================
// 7. 현재 문제 ID Map
// ============================================================

$view_current_problem_map = array();


foreach ($view_current_problems as $problem) {

    $problem_id =
        intval($problem['problem_id']);

    $view_current_problem_map[$problem_id] = array(
        'num'   => intval($problem['num']),
        'score' => isset($problem['score'])
            ? intval($problem['score'])
            : 100
    );
}


// ============================================================
// 8. 원본 Contest 문제 목록
//
// source_contest_id가 있는 경우:
// 원본 Contest의 문제들을 추가 후보로 표시한다.
// ============================================================

$view_source_problems = array();

$source_contest_id =
    isset($view_contest['source_contest_id'])
        ? intval($view_contest['source_contest_id'])
        : 0;


if ($source_contest_id > 0) {

    $view_source_problems = pdo_query(
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


    if (!is_array($view_source_problems)) {
        $view_source_problems = array();
    }
}


// ============================================================
// 9. 화면 출력
// ============================================================

require(
    "template/".$OJ_TEMPLATE."/course_contest_problem_edit.php"
);