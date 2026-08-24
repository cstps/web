<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "학생 학습현황";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


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
// 3. user_id 확인
// ============================================================

$student_user_id =
    isset($_GET['user_id'])
        ? trim($_GET['user_id'])
        : '';

if (
    $student_user_id === '' ||
    strlen($student_user_id) > 48
) {

    $view_errors = "<h2>잘못된 학생 정보입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course 존재 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
        school,
        school_year,
        semester,
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
// 5. Course 접근 권한 확인
// ============================================================

if (!course_can_access($course_id)) {

    $view_errors =
        "<h2>이 수업의 학생 정보를 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 해당 학생이 실제 이 Course 소속인지 확인
//
// 중요:
// user_id만으로 학생 정보를 조회하지 않는다.
// 반드시 course_id + user_id를 동시에 확인한다.
// ============================================================

$student_rows = pdo_query(
    "SELECT
        cs.user_id,
        cs.student_no,
        cs.status,
        cs.joined_at,
        cs.left_at,

        u.nick,
        u.school,
        u.email,
        u.defunct

     FROM course_student cs

     LEFT JOIN users u
       ON u.user_id = cs.user_id

     WHERE cs.course_id = ?
       AND cs.user_id = ?

     LIMIT 1",
    $course_id,
    $student_user_id
);


if (
    !$student_rows ||
    !isset($student_rows[0]['user_id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 학생입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$view_student = $student_rows[0];


// ============================================================
// 7. 수업 전체 제출 수
// ============================================================

$submit_rows = pdo_query(
    "SELECT COUNT(*) AS cnt

     FROM solution s

     INNER JOIN course_contest cc
        ON cc.contest_id = s.contest_id
        AND cc.status = 1

        WHERE cc.course_id = ?
        AND s.user_id = ?",
    $course_id,
    $student_user_id
);


$view_course_submit_count =
    isset($submit_rows[0]['cnt'])
        ? intval($submit_rows[0]['cnt'])
        : 0;


// ============================================================
// 8. 수업 전체 해결 문제 수
// - AC = result 4
// ============================================================

$solved_rows = pdo_query(
    "SELECT
        COUNT(
            DISTINCT CONCAT(
                s.contest_id,
                ':',
                s.problem_id
            )
        ) AS cnt

     FROM solution s

     INNER JOIN course_contest cc
        ON cc.contest_id = s.contest_id
        AND cc.status = 1

        WHERE cc.course_id = ?
        AND s.user_id = ?
        AND s.result = 4",
    $course_id,
    $student_user_id
);


$view_course_solved_count =
    isset($solved_rows[0]['cnt'])
        ? intval($solved_rows[0]['cnt'])
        : 0;

// ============================================================
// 8-1. 수업 전체 문제 수
//
// 활성 차시(status=1)의 전체 문제 수
// ============================================================

$problem_count_rows = pdo_query(
    "SELECT COUNT(*) AS cnt

     FROM course_contest cc

     INNER JOIN contest_problem cp
       ON cp.contest_id = cc.contest_id

     WHERE cc.course_id = ?
       AND cc.status = 1",
    $course_id
);


$view_course_problem_count =
    isset($problem_count_rows[0]['cnt'])
        ? intval($problem_count_rows[0]['cnt'])
        : 0;



// ============================================================
// 9. 참여한 대회 수
//
// 제출이 한 번이라도 있는 대회만 "참여"로 계산
// ============================================================

$participated_rows = pdo_query(
    "SELECT
        COUNT(DISTINCT s.contest_id) AS cnt

     FROM solution s

     INNER JOIN course_contest cc
        ON cc.contest_id = s.contest_id
        AND cc.status = 1

        WHERE cc.course_id = ?
        AND s.user_id = ?",
    $course_id,
    $student_user_id
);


$view_participated_contest_count =
    isset($participated_rows[0]['cnt'])
        ? intval($participated_rows[0]['cnt'])
        : 0;


// ============================================================
// 9-1. 전체 활성 차시 수
// ============================================================

$contest_count_rows = pdo_query(
    "SELECT COUNT(*) AS cnt

     FROM course_contest

     WHERE course_id = ?
       AND status = 1",
    $course_id
);


$view_course_contest_count =
    isset($contest_count_rows[0]['cnt'])
        ? intval($contest_count_rows[0]['cnt'])
        : 0;

// ============================================================
// 10. Course 연결 대회별 학생 현황
// ============================================================

$view_contests = pdo_query(
    "SELECT
        cc.contest_id,
        cc.source_contest_id,
        cc.lesson_no,
        cc.sort_order,
        cc.visible,

        c.title,
        c.start_time,
        c.end_time,

        (
            SELECT COUNT(*)
            FROM contest_problem cp
            WHERE cp.contest_id = cc.contest_id
        ) AS problem_count,

        COUNT(s.solution_id) AS submit_count,

        COUNT(
            DISTINCT CASE
                WHEN s.result = 4
                THEN s.problem_id
                ELSE NULL
            END
        ) AS solved_count,

        MAX(s.in_date) AS last_submit_time

     FROM course_contest cc

        LEFT JOIN contest c
        ON c.contest_id = cc.contest_id

        LEFT JOIN solution s
        ON s.contest_id = cc.contest_id
        AND s.user_id = ?

        WHERE cc.course_id = ?
        AND cc.status = 1

     GROUP BY
        cc.contest_id,
        cc.source_contest_id,
        cc.lesson_no,
        cc.sort_order,
        cc.visible,
        c.title,
        c.start_time,
        c.end_time

     ORDER BY
        cc.lesson_no ASC,
        cc.contest_id ASC",
    $student_user_id,
    $course_id
);


if (!is_array($view_contests)) {
    $view_contests = array();
}

// ============================================================
// 11. 대회별 문제 현황 조회
//
// - contest_problem 기준으로 전체 문제를 가져온다.
// - 학생이 제출하지 않은 문제도 표시한다.
// - 최신 solution_id를 이용해 해결과정으로 연결한다.
// ============================================================

$view_contest_problems = array();


foreach ($view_contests as $contest) {

    $contest_id =
        intval($contest['contest_id']);


    $problem_rows = pdo_query(
        "SELECT
            cp.problem_id,
            cp.num,

            COALESCE(
                NULLIF(cp.title, ''),
                p.title
            ) AS title,

            COUNT(s.solution_id) AS submit_count,

            SUM(
                CASE
                    WHEN s.result = 4
                    THEN 1
                    ELSE 0
                END
            ) AS ac_count,

            MIN(s.in_date) AS first_submit_time,

            MAX(s.in_date) AS last_submit_time,

            MAX(s.solution_id) AS latest_solution_id

        FROM contest_problem cp

        LEFT JOIN problem p
        ON p.problem_id = cp.problem_id

        LEFT JOIN solution s
            ON s.contest_id = cp.contest_id
            AND s.problem_id = cp.problem_id
            AND s.user_id = ?

        WHERE cp.contest_id = ?

        GROUP BY
            cp.problem_id,
            cp.num,
            cp.title,
            p.title

        ORDER BY
            cp.num,
            cp.problem_id",
        $student_user_id,
        $contest_id
    );


    if (!is_array($problem_rows)) {
        $problem_rows = array();
    }


    // --------------------------------------------------------
    // 각 문제의 최신 제출 결과 조회
    // --------------------------------------------------------

    foreach ($problem_rows as $index => $problem) {

        $latest_solution_id =
            isset($problem['latest_solution_id'])
                ? intval($problem['latest_solution_id'])
                : 0;


        $latest_result = null;


        if ($latest_solution_id > 0) {

            $latest_rows = pdo_query(
                "SELECT result
                   FROM solution
                  WHERE solution_id = ?
                  LIMIT 1",
                $latest_solution_id
            );


            if (
                $latest_rows &&
                isset($latest_rows[0]['result'])
            ) {

                $latest_result =
                    intval($latest_rows[0]['result']);
            }
        }


        $problem_rows[$index]['latest_result'] =
            $latest_result;
    }


    $view_contest_problems[$contest_id] =
        $problem_rows;
}

// ============================================================
// 12. 학생 누적 메모 조회
// ============================================================

$view_student_memos = pdo_query(
    "SELECT
        m.id,
        m.course_id,
        m.user_id,
        m.contest_id,
        m.memo_text,
        m.created_by,
        m.created_at,
        m.updated_at,

        c.title AS contest_title,

        cc.lesson_no

     FROM course_student_memo m

     LEFT JOIN course_contest cc
       ON cc.course_id = m.course_id
      AND cc.contest_id = m.contest_id

     LEFT JOIN contest c
       ON c.contest_id = m.contest_id

     WHERE m.course_id = ?
       AND m.user_id = ?

     ORDER BY
        m.created_at DESC,
        m.id DESC",
    $course_id,
    $student_user_id
);


if (!is_array($view_student_memos)) {
    $view_student_memos = array();
}


// ============================================================
// 13. 화면 출력
// ============================================================


require("template/".$OJ_TEMPLATE."/course_student_view.php");