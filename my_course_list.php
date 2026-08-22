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
// 2. 현재 학생이 수강 중인 Course 목록
//
// 정책:
// - course_student.status=1인 Course만 표시
// - Course가 종료됐어도 지난 기록 확인을 위해 표시
// - 제거되거나 숨겨진 차시는 통계에서 제외
// - contest.defunct와 무관하게 Course 공개 차시 통계를 표시
// ============================================================

$sql = "
    SELECT
        c.course_id,
        c.course_name,
        c.school,
        c.school_year,
        c.semester,
        c.description,
        c.status,
        c.created_at,
        c.updated_at,

        (
            SELECT COUNT(*)

            FROM course_contest cc

            WHERE cc.course_id = c.course_id
              AND cc.status = 1
              AND cc.visible = 1
        ) AS lesson_count,

        (
            SELECT COUNT(*)

            FROM course_contest cc

            INNER JOIN contest ct
                ON ct.contest_id = cc.contest_id

            WHERE cc.course_id = c.course_id
              AND cc.status = 1
              AND cc.visible = 1
              AND ct.start_time <= NOW()
              AND ct.end_time >= NOW()
        ) AS ongoing_lesson_count,

        (
            SELECT COUNT(*)

            FROM course_contest cc

            INNER JOIN contest_problem cp
                ON cp.contest_id = cc.contest_id

            WHERE cc.course_id = c.course_id
              AND cc.status = 1
              AND cc.visible = 1
        ) AS problem_count,

        (
            SELECT COUNT(*)

            FROM solution s

            INNER JOIN course_contest cc
                ON cc.contest_id = s.contest_id
               AND cc.course_id = c.course_id
               AND cc.status = 1
               AND cc.visible = 1

            WHERE s.user_id = ?
        ) AS submission_count,

        (
            SELECT COUNT(
                DISTINCT
                s.contest_id,
                s.problem_id
            )

            FROM solution s

            INNER JOIN course_contest cc
                ON cc.contest_id = s.contest_id
               AND cc.course_id = c.course_id
               AND cc.status = 1
               AND cc.visible = 1

            WHERE s.user_id = ?
              AND s.result = 4
        ) AS solved_count

    FROM course c

    INNER JOIN course_student cs
        ON cs.course_id = c.course_id
       AND cs.user_id = ?
       AND cs.status = 1

    ORDER BY
        c.status DESC,
        c.school_year DESC,
        c.semester DESC,
        c.course_id DESC
";


$view_courses = pdo_query(
    $sql,
    $user_id,
    $user_id,
    $user_id
);


// ============================================================
// 3. 결과 배열 보정
// ============================================================

if (!is_array($view_courses)) {
    $view_courses = array();
}


// ============================================================
// 4. 화면 출력
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/my_course_list.php"
);