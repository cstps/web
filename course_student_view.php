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

    require("template/" . $OJ_TEMPLATE . "/error.php");
    exit(0);
}


// ============================================================
// 3-1. 확인 필요 항목 바로가기
//
// missed : 미참여 차시
// retry  : 반복 미해결 문제
// ============================================================

$view_focus =
    isset($_GET['focus'])
    ? trim($_GET['focus'])
    : '';


if (
    $view_focus !== 'missed' &&
    $view_focus !== 'retry'
) {
    $view_focus = '';
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

        CASE
            WHEN c.start_time <= NOW()
            THEN 1
            ELSE 0
        END AS has_started,

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
    
            COUNT(
                DISTINCT s.solution_id
            ) AS submit_count,

            COUNT(
                DISTINCT CASE
                    WHEN s.result = 4
                    THEN s.solution_id
                    ELSE NULL
                END
            ) AS ac_count,

            MIN(s.in_date) AS first_submit_time,

            MAX(s.in_date) AS last_submit_time,


            MAX(
                CASE
                    WHEN sp.plan_text IS NOT NULL
                    AND TRIM(sp.plan_text) <> ''
                    THEN 1
                    ELSE 0
                END
            ) AS has_plan,


            MAX(
                CASE
                    WHEN sp.reflection IS NOT NULL
                    AND TRIM(sp.reflection) <> ''
                    THEN 1
                    ELSE 0
                END
            ) AS has_reflection,


            MAX(
                CASE
                    WHEN sp.ai_used = 1
                    THEN 1
                    ELSE 0
                END
            ) AS used_ai,


            latest.solution_id AS latest_solution_id,
            latest.result AS latest_result
        
             FROM contest_problem cp
        
             LEFT JOIN problem p
               ON p.problem_id = cp.problem_id
        
        
               LEFT JOIN solution s
            ON s.contest_id = cp.contest_id
            AND s.problem_id = cp.problem_id
            AND s.user_id = ?


            LEFT JOIN solution_process sp
            ON sp.solution_id = s.solution_id


            LEFT JOIN
            (
                SELECT
                    latest_solution.problem_id,
                     latest_solution.solution_id,
                     latest_solution.result
        
                 FROM solution latest_solution
        
                 INNER JOIN
                 (
                     SELECT
                         problem_id,
                         MAX(solution_id) AS latest_solution_id
        
                     FROM solution
        
                     WHERE user_id = ?
                       AND contest_id = ?
        
                     GROUP BY problem_id
                 ) latest_id
        
                   ON latest_id.latest_solution_id =
                      latest_solution.solution_id
        
             ) latest
        
               ON latest.problem_id =
                  cp.problem_id
        
        
             WHERE cp.contest_id = ?
        
             GROUP BY
                cp.problem_id,
                cp.num,
                cp.title,
                p.title,
                latest.solution_id,
                latest.result
        
             ORDER BY
                cp.num,
                cp.problem_id",

        $student_user_id,
        $student_user_id,
        $contest_id,
        $contest_id
    );


    if (!is_array($problem_rows)) {
        $problem_rows = array();
    }


    $view_contest_problems[$contest_id] =
        $problem_rows;
}


// ============================================================
// 11-1. Course 전체 문제 해결 과정 요약
//
// contest_problem 단위를 기준으로 계산한다.
//
// - 시도 문제: 제출이 1회 이상
// - 미시도 문제: 제출 없음
// - 진행 중: 제출은 있으나 AC 없음
// - 재시도 문제: 제출이 2회 이상
// ============================================================

$view_attempted_problem_count = 0;
$view_unattempted_problem_count = 0;
$view_in_progress_problem_count = 0;
$view_retry_problem_count = 0;
$view_plan_problem_count = 0;
$view_reflection_problem_count = 0;
$view_ai_problem_count = 0;
$view_persistent_unsolved_count = 0;

foreach (
    $view_contest_problems
    as $contest_problem_rows
) {

    foreach (
        $contest_problem_rows
        as $problem
    ) {

        $submit_count =
            isset($problem['submit_count'])
            ? intval($problem['submit_count'])
            : 0;

        $ac_count =
            isset($problem['ac_count'])
            ? intval($problem['ac_count'])
            : 0;


        if ($submit_count > 0) {

            $view_attempted_problem_count++;


            if ($ac_count <= 0) {

                $view_in_progress_problem_count++;


                // 여러 번 시도했지만 아직 해결하지 못한 문제
                if ($submit_count >= 2) {
                    $view_persistent_unsolved_count++;
                }
            }


            if ($submit_count >= 2) {
                $view_retry_problem_count++;
            }


            if (
                isset($problem['has_plan']) &&
                intval($problem['has_plan']) === 1
            ) {
                $view_plan_problem_count++;
            }


            if (
                isset($problem['has_reflection']) &&
                intval($problem['has_reflection']) === 1
            ) {
                $view_reflection_problem_count++;
            }


            if (
                isset($problem['used_ai']) &&
                intval($problem['used_ai']) === 1
            ) {
                $view_ai_problem_count++;
            }
        } else {

            $view_unattempted_problem_count++;
        }
    }
}


$view_average_submit_count =
    $view_attempted_problem_count > 0
    ? round(
        $view_course_submit_count /
            $view_attempted_problem_count,
        1
    )
    : 0;


// ============================================================
// 11-2. 차시별 학습과정 요약
//
// 기존 $view_contests / $view_contest_problems 데이터를 사용한다.
// 추가 DB 조회는 하지 않는다.
// ============================================================

$view_lesson_process_summary =
    array();


foreach ($view_contests as $contest) {

    $contest_id =
        intval(
            $contest['contest_id']
        );


    $problem_rows =
        isset(
            $view_contest_problems[$contest_id]
        )
        ? $view_contest_problems[$contest_id]
        : array();


    $attempted_count = 0;
    $retry_count = 0;
    $in_progress_count = 0;
    $plan_count = 0;
    $reflection_count = 0;
    $ai_count = 0;
    $total_submit_count = 0;


    foreach ($problem_rows as $problem) {

        $submit_count =
            isset($problem['submit_count'])
            ? intval(
                $problem['submit_count']
            )
            : 0;


        $ac_count =
            isset($problem['ac_count'])
            ? intval(
                $problem['ac_count']
            )
            : 0;


        $total_submit_count +=
            $submit_count;


        if ($submit_count > 0) {

            $attempted_count++;


            if ($ac_count <= 0) {
                $in_progress_count++;
            }


            if ($submit_count >= 2) {
                $retry_count++;
            }


            if (
                isset($problem['has_plan']) &&
                intval($problem['has_plan']) === 1
            ) {
                $plan_count++;
            }


            if (
                isset($problem['has_reflection']) &&
                intval(
                    $problem['has_reflection']
                ) === 1
            ) {
                $reflection_count++;
            }


            if (
                isset($problem['used_ai']) &&
                intval($problem['used_ai']) === 1
            ) {
                $ai_count++;
            }
        }
    }


    $average_submit =
        $attempted_count > 0
        ? round(
            $total_submit_count /
                $attempted_count,
            1
        )
        : 0;


    $view_lesson_process_summary[$contest_id] =
        array(

            'lesson_no' =>
            intval(
                $contest['lesson_no']
            ),

            'title' =>
            isset($contest['title'])
                ? $contest['title']
                : '',

            'problem_count' =>
            count($problem_rows),

            'attempted_count' =>
            $attempted_count,

            'solved_count' =>
            isset($contest['solved_count'])
                ? intval(
                    $contest['solved_count']
                )
                : 0,

            'in_progress_count' =>
            $in_progress_count,

            'retry_count' =>
            $retry_count,

            'plan_count' =>
            $plan_count,

            'reflection_count' =>
            $reflection_count,

            'ai_count' =>
            $ai_count,

            'submit_count' =>
            $total_submit_count,

            'average_submit' =>
            $average_submit
        );
}

// ============================================================
// 11-3. AI 활용 유형 / 수정 영역 누적 집계
//
// solution_process 기록 단위로 계산한다.
//
// AI 활용 유형:
// - 한 제출당 하나
//
// 수정 영역:
// - 한 제출에서 여러 항목 선택 가능
// ============================================================

$view_ai_usage_counts =
    array(
        'idea'       => 0,
        'syntax'     => 0,
        'debug'      => 0,
        'generate'   => 0,
        'understand' => 0,
        'explain'    => 0,
        'other'      => 0
    );


$view_change_type_counts =
    array(
        'input'     => 0,
        'output'    => 0,
        'condition' => 0,
        'loop'      => 0,
        'variable'  => 0,
        'function'  => 0,
        'data'      => 0,
        'other'     => 0
    );


$view_ai_usage_record_count = 0;
$view_change_record_count = 0;


$process_detail_rows =
    pdo_query(
        "SELECT
            sp.ai_used,
            sp.ai_usage_type,
            sp.change_type

         FROM solution_process sp

         INNER JOIN solution s
           ON s.solution_id = sp.solution_id

         INNER JOIN course_contest cc
           ON cc.contest_id = s.contest_id
          AND cc.status = 1

         WHERE cc.course_id = ?
           AND s.user_id = ?",

        $course_id,
        $student_user_id
    );


if (!is_array($process_detail_rows)) {
    $process_detail_rows = array();
}


foreach ($process_detail_rows as $process_row) {

    // --------------------------------------------------------
    // AI 활용 유형
    // --------------------------------------------------------

    if (
        isset($process_row['ai_used']) &&
        intval($process_row['ai_used']) === 1
    ) {

        $view_ai_usage_record_count++;


        $ai_usage_type =
            isset($process_row['ai_usage_type'])
            ? trim($process_row['ai_usage_type'])
            : '';


        if (
            $ai_usage_type !== '' &&
            $ai_usage_type !== 'none' &&
            array_key_exists(
                $ai_usage_type,
                $view_ai_usage_counts
            ) &&
            $ai_usage_type !== 'other'
        ) {

            $view_ai_usage_counts[$ai_usage_type]++;
        } else {

            $view_ai_usage_counts['other']++;
        }
    }


    // --------------------------------------------------------
    // 재제출 수정 영역
    // --------------------------------------------------------

    $change_type =
        isset($process_row['change_type'])
        ? trim($process_row['change_type'])
        : '';


    if ($change_type === '') {
        continue;
    }


    $view_change_record_count++;


    $change_types =
        explode(
            ',',
            $change_type
        );


    // 동일 제출에서 같은 항목이 중복되어도
    // 한 번만 계산
    $seen_change_types =
        array();


    foreach ($change_types as $type) {

        $type =
            trim($type);


        if (
            $type === '' ||
            !array_key_exists(
                $type,
                $view_change_type_counts
            ) ||
            isset($seen_change_types[$type])
        ) {
            continue;
        }


        $view_change_type_counts[$type]++;

        $seen_change_types[$type] =
            true;
    }
}


// ============================================================
// 11-4. 교사용 자동 학습 분석
//
// 현재 Course에서 확인 가능한 객관적 수치만 사용한다.
// AI 생성 문장이 아니라 규칙 기반 요약이다.
// ============================================================

$view_learning_analysis =
    array();


// ------------------------------------------------------------
// 1. 전체 참여
// ------------------------------------------------------------

if ($view_course_contest_count > 0) {

    if (
        $view_participated_contest_count ===
        $view_course_contest_count
    ) {

        $view_learning_analysis[] =
            "현재까지 모든 차시에 제출 기록이 있습니다.";
    } elseif ($view_participated_contest_count > 0) {

        $view_learning_analysis[] =
            "전체 " .
            intval($view_course_contest_count) .
            "개 차시 중 " .
            intval($view_participated_contest_count) .
            "개 차시에 제출 기록이 있습니다.";
    } else {

        $view_learning_analysis[] =
            "현재까지 차시 제출 기록이 없습니다.";
    }
}


// ------------------------------------------------------------
// 2. 문제 시도 및 해결
// ------------------------------------------------------------

if ($view_course_problem_count > 0) {

    $view_learning_analysis[] =
        "전체 " .
        intval($view_course_problem_count) .
        "개 문제 중 " .
        intval($view_attempted_problem_count) .
        "개 문제를 시도했고, " .
        intval($view_course_solved_count) .
        "개 문제를 해결했습니다.";
}


// ------------------------------------------------------------
// 3. 재시도 과정
// ------------------------------------------------------------

if ($view_retry_problem_count > 0) {

    $view_learning_analysis[] =
        intval($view_retry_problem_count) .
        "개 문제에서 2회 이상 제출하며 재시도했습니다.";
}


if ($view_persistent_unsolved_count > 0) {

    $view_learning_analysis[] =
        intval($view_persistent_unsolved_count) .
        "개 문제는 여러 차례 시도했지만 아직 해결하지 못했습니다.";
}


// ------------------------------------------------------------
// 4. 풀이계획
// ------------------------------------------------------------

if ($view_attempted_problem_count > 0) {

    if (
        $view_plan_problem_count ===
        $view_attempted_problem_count
    ) {

        $view_learning_analysis[] =
            "시도한 모든 문제에서 풀이계획을 작성했습니다.";
    } elseif ($view_plan_problem_count > 0) {

        $view_learning_analysis[] =
            "시도한 " .
            intval($view_attempted_problem_count) .
            "개 문제 중 " .
            intval($view_plan_problem_count) .
            "개 문제에서 풀이계획을 작성했습니다.";
    }
}


// ------------------------------------------------------------
// 5. 수정 메모
// ------------------------------------------------------------

if ($view_reflection_problem_count > 0) {

    $view_learning_analysis[] =
        intval($view_reflection_problem_count) .
        "개 문제에서 재제출 과정의 수정 메모를 작성했습니다.";
}


// ------------------------------------------------------------
// 6. AI 활용
// ------------------------------------------------------------

if ($view_ai_problem_count > 0) {

    $view_learning_analysis[] =
        intval($view_ai_problem_count) .
        "개 문제에서 AI를 활용한 기록이 있습니다.";
}


// AI 활용 방식 세부 기록
if ($view_ai_usage_record_count > 0) {

    $ai_usage_parts =
        array();


    $ai_usage_labels =
        array(
            'idea'       => '힌트·아이디어',
            'syntax'     => '문법 도움',
            'debug'      => '오류 수정',
            'generate'   => '코드 생성',
            'understand' => '문제 이해',
            'explain'    => '설명 요청',
            'other'      => '기타'
        );


    foreach (
        $ai_usage_labels
        as $type => $label
    ) {

        $count =
            isset($view_ai_usage_counts[$type])
            ? intval(
                $view_ai_usage_counts[$type]
            )
            : 0;


        if ($count > 0) {

            $ai_usage_parts[] =
                $label .
                ' ' .
                $count .
                '회';
        }
    }


    if (!empty($ai_usage_parts)) {

        $view_learning_analysis[] =
            "AI 활용 기록 " .
            intval(
                $view_ai_usage_record_count
            ) .
            "회 중 " .
            implode(
                ', ',
                $ai_usage_parts
            ) .
            "로 기록되었습니다.";
    }
}


// ------------------------------------------------------------
// 7. 재제출 수정 기록
// ------------------------------------------------------------

if ($view_change_record_count > 0) {

    $change_parts =
        array();


    $change_labels =
        array(
            'input'     => '입력',
            'output'    => '출력',
            'condition' => '조건문',
            'loop'      => '반복문',
            'variable'  => '변수',
            'function'  => '함수',
            'data'      => '배열·자료구조',
            'other'     => '기타'
        );


    foreach (
        $change_labels
        as $type => $label
    ) {

        $count =
            isset(
                $view_change_type_counts[$type]
            )
            ? intval(
                $view_change_type_counts[$type]
            )
            : 0;


        if ($count > 0) {

            $change_parts[] =
                $label .
                ' ' .
                $count .
                '회';
        }
    }


    if (!empty($change_parts)) {

        $view_learning_analysis[] =
            "재제출 과정에서 수정 영역을 기록한 제출은 " .
            intval(
                $view_change_record_count
            ) .
            "회이며, " .
            implode(
                ', ',
                $change_parts
            ) .
            "가 선택되었습니다.";
    }
}

if ($view_attempted_problem_count > 0) {

    $view_learning_analysis[] =
        "시도한 문제 기준 문제당 평균 제출 횟수는 " .
        $view_average_submit_count .
        "회입니다.";
}

// ------------------------------------------------------------
// 8. 평균 제출
// ------------------------------------------------------------


$active_lesson_summaries =
    array();


foreach (
    $view_lesson_process_summary
    as $lesson_summary
) {

    if (
        intval(
            $lesson_summary['attempted_count']
        ) > 0
    ) {

        $active_lesson_summaries[] =
            $lesson_summary;
    }
}

// ------------------------------------------------------------
// 8. 초기 참여 차시 / 최근 참여 차시 통계
//
// 차시 간 증가·감소를 평가하지 않고
// 실제 기록만 객관적으로 표시한다.
//
// 제출이 한 번이라도 있는 차시만 대상으로 한다.
// ------------------------------------------------------------

if (
    count($active_lesson_summaries) >= 2
) {

    $first_lesson =
        $active_lesson_summaries[0];


    $last_lesson =
        $active_lesson_summaries[count($active_lesson_summaries) - 1];


    // --------------------------------------------------------
    // 초기 참여 차시
    // --------------------------------------------------------

    $first_lesson_no =
        intval(
            $first_lesson['lesson_no']
        );


    $first_problem_count =
        intval(
            $first_lesson['problem_count']
        );


    $first_solved_count =
        intval(
            $first_lesson['solved_count']
        );


    $first_solved_percent =
        $first_problem_count > 0
        ? round(
            (
                $first_solved_count /
                $first_problem_count
            ) * 100,
            1
        )
        : 0;


    $first_average_submit =
        floatval(
            $first_lesson['average_submit']
        );


    // --------------------------------------------------------
    // 최근 참여 차시
    // --------------------------------------------------------

    $last_lesson_no =
        intval(
            $last_lesson['lesson_no']
        );


    $last_problem_count =
        intval(
            $last_lesson['problem_count']
        );


    $last_solved_count =
        intval(
            $last_lesson['solved_count']
        );


    $last_solved_percent =
        $last_problem_count > 0
        ? round(
            (
                $last_solved_count /
                $last_problem_count
            ) * 100,
            1
        )
        : 0;


    $last_average_submit =
        floatval(
            $last_lesson['average_submit']
        );


    // --------------------------------------------------------
    // 출력 문구
    // --------------------------------------------------------

    $view_learning_analysis[] =
        "초기 참여 차시(" .
        $first_lesson_no .
        "차시): " .
        $first_problem_count .
        "개 문제 중 " .
        $first_solved_count .
        "개 해결(" .
        $first_solved_percent .
        "%), 시도 문제당 평균 " .
        $first_average_submit .
        "회 제출";


    $view_learning_analysis[] =
        "최근 참여 차시(" .
        $last_lesson_no .
        "차시): " .
        $last_problem_count .
        "개 문제 중 " .
        $last_solved_count .
        "개 해결(" .
        $last_solved_percent .
        "%), 시도 문제당 평균 " .
        $last_average_submit .
        "회 제출";
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
// 13. 세특 작성 참고자료
//
// 자동 평가 문장을 생성하지 않는다.
// 학습 기록 요약 + 교사 관찰 메모를
// 교사가 참고할 수 있도록 단순 정리한다.
// ============================================================

$view_student_record_reference =
    array();


// ------------------------------------------------------------
// 13-1. 객관적 학습 기록
// ------------------------------------------------------------

foreach (
    $view_learning_analysis
    as $analysis_text
) {

    $analysis_text =
        trim($analysis_text);


    if ($analysis_text !== '') {

        $view_student_record_reference[] =
            '[학습기록] ' .
            $analysis_text;
    }
}


// ------------------------------------------------------------
// 13-2. 교사 관찰 메모
// ------------------------------------------------------------

foreach (
    $view_student_memos
    as $memo
) {

    $memo_text =
        isset($memo['memo_text'])
        ? trim($memo['memo_text'])
        : '';


    if ($memo_text === '') {
        continue;
    }


    $memo_label =
        '수업 전체';


    if (
        isset($memo['contest_id']) &&
        intval($memo['contest_id']) > 0
    ) {

        $memo_label =
            intval(
                $memo['lesson_no']
            ) .
            '차시';
    }


    $view_student_record_reference[] =
        '[교사메모/' .
        $memo_label .
        '] ' .
        $memo_text;
}


// textarea용 문자열
$view_student_record_reference_text =
    implode(
        "\n",
        $view_student_record_reference
    );

// ============================================================
// 14. 세특 초안 생성용 참고자료 분류
//
// 실제 AI 호출은 아직 하지 않는다.
// 교사가 어떤 자료를 사용할지 선택할 수 있도록
// 영역별 텍스트를 미리 구성한다.
// ============================================================

$view_record_ai_sources =
    array(
        'participation' => array(),
        'plan'          => array(),
        'retry'         => array(),
        'ai'            => array(),
        'memo'          => array()
    );


// ------------------------------------------------------------
// 1. 수업 참여 / 문제 해결
// ------------------------------------------------------------

if ($view_course_contest_count > 0) {

    $view_record_ai_sources['participation'][] =
        "전체 " .
        intval($view_course_contest_count) .
        "개 차시 중 " .
        intval($view_participated_contest_count) .
        "개 차시에 제출 기록이 있음.";
}


if ($view_course_problem_count > 0) {

    $view_record_ai_sources['participation'][] =
        "전체 " .
        intval($view_course_problem_count) .
        "개 문제 중 " .
        intval($view_attempted_problem_count) .
        "개 문제를 시도하고 " .
        intval($view_course_solved_count) .
        "개 문제를 해결함.";
}


if ($view_attempted_problem_count > 0) {

    $view_record_ai_sources['participation'][] =
        "시도 문제당 평균 제출 횟수는 " .
        $view_average_submit_count .
        "회임.";
}


// ------------------------------------------------------------
// 2. 풀이계획
// ------------------------------------------------------------

if ($view_attempted_problem_count > 0) {

    $view_record_ai_sources['plan'][] =
        "시도한 " .
        intval($view_attempted_problem_count) .
        "개 문제 중 " .
        intval($view_plan_problem_count) .
        "개 문제에서 풀이계획을 작성함.";
}


// ------------------------------------------------------------
// 3. 재시도 / 수정 과정
// ------------------------------------------------------------

if ($view_retry_problem_count > 0) {

    $view_record_ai_sources['retry'][] =
        intval($view_retry_problem_count) .
        "개 문제에서 2회 이상 제출하며 재시도함.";
}


if ($view_persistent_unsolved_count > 0) {

    $view_record_ai_sources['retry'][] =
        intval($view_persistent_unsolved_count) .
        "개 문제는 여러 차례 시도했으나 아직 해결되지 않음.";
}


if ($view_reflection_problem_count > 0) {

    $view_record_ai_sources['retry'][] =
        intval($view_reflection_problem_count) .
        "개 문제에서 재제출 과정의 수정 메모를 작성함.";
}


if ($view_change_record_count > 0) {

    $record_change_labels =
        array(
            'input'     => '입력',
            'output'    => '출력',
            'condition' => '조건문',
            'loop'      => '반복문',
            'variable'  => '변수',
            'function'  => '함수',
            'data'      => '배열·자료구조',
            'other'     => '기타'
        );


    $record_change_parts =
        array();


    foreach (
        $record_change_labels
        as $type => $label
    ) {

        $count =
            isset(
                $view_change_type_counts[$type]
            )
            ? intval(
                $view_change_type_counts[$type]
            )
            : 0;


        if ($count > 0) {

            $record_change_parts[] =
                $label .
                ' ' .
                $count .
                '회';
        }
    }


    if (!empty($record_change_parts)) {

        $view_record_ai_sources['retry'][] =
            "재제출 수정 영역: " .
            implode(
                ', ',
                $record_change_parts
            ) .
            ".";
    }
}


// ------------------------------------------------------------
// 4. AI 활용
// ------------------------------------------------------------

if ($view_ai_problem_count > 0) {

    $view_record_ai_sources['ai'][] =
        intval($view_ai_problem_count) .
        "개 문제에서 AI 활용 기록이 있음.";
}


if ($view_ai_usage_record_count > 0) {

    $record_ai_labels =
        array(
            'idea'       => '힌트·아이디어',
            'syntax'     => '문법 도움',
            'debug'      => '오류 수정',
            'generate'   => '코드 생성',
            'understand' => '문제 이해',
            'explain'    => '설명 요청',
            'other'      => '기타'
        );


    $record_ai_parts =
        array();


    foreach (
        $record_ai_labels
        as $type => $label
    ) {

        $count =
            isset(
                $view_ai_usage_counts[$type]
            )
            ? intval(
                $view_ai_usage_counts[$type]
            )
            : 0;


        if ($count > 0) {

            $record_ai_parts[] =
                $label .
                ' ' .
                $count .
                '회';
        }
    }


    if (!empty($record_ai_parts)) {

        $view_record_ai_sources['ai'][] =
            "AI 활용 유형: " .
            implode(
                ', ',
                $record_ai_parts
            ) .
            ".";
    }
}


// ------------------------------------------------------------
// 5. 교사 관찰 메모
// ------------------------------------------------------------

foreach ($view_student_memos as $memo) {

    $memo_text =
        isset($memo['memo_text'])
        ? trim($memo['memo_text'])
        : '';


    if ($memo_text === '') {
        continue;
    }


    $memo_label =
        '수업 전체';


    if (
        isset($memo['contest_id']) &&
        intval($memo['contest_id']) > 0
    ) {

        $memo_label =
            intval(
                $memo['lesson_no']
            ) .
            '차시';
    }


    $view_record_ai_sources['memo'][] =
        '[' .
        $memo_label .
        '] ' .
        $memo_text;
}


// 배열 → 화면용 문자열
foreach (
    $view_record_ai_sources
    as $source_key => $source_rows
) {

    $view_record_ai_sources[$source_key] =
        implode(
            "\n",
            $source_rows
        );
}


// ============================================================
// 15. 세특 초안 조회
// ============================================================

$record_draft_rows =
    pdo_query(
        "SELECT
                draft_text,
                updated_by,
                created_at,
                updated_at
    
             FROM course_student_record_draft
    
             WHERE course_id = ?
               AND user_id = ?
    
             LIMIT 1",

        $course_id,
        $student_user_id
    );


$view_record_draft = '';

$view_record_draft_updated_at = '';
$view_record_draft_updated_by = '';


if (
    is_array($record_draft_rows) &&
    isset($record_draft_rows[0])
) {

    $view_record_draft =
        isset($record_draft_rows[0]['draft_text'])
        ? $record_draft_rows[0]['draft_text']
        : '';

    $view_record_draft_updated_at =
        isset($record_draft_rows[0]['updated_at'])
        ? $record_draft_rows[0]['updated_at']
        : '';

    $view_record_draft_updated_by =
        isset($record_draft_rows[0]['updated_by'])
        ? $record_draft_rows[0]['updated_by']
        : '';
}


// ============================================================
// 15. 화면 출력
// ============================================================


require("template/".$OJ_TEMPLATE."/course_student_view.php");