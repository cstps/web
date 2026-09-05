<?php

require_once("./include/db_info.inc.php");
require_once("./include/my_func.inc.php");
require_once("./include/course_functions.inc.php");

// ============================================================
// 기본값
// ============================================================

$cid = isset($_GET['cid'])
    ? intval($_GET['cid'])
    : 0;

$student_user_id = isset($_GET['user_id'])
    ? trim($_GET['user_id'])
    : "";


if (
    $cid <= 0 ||
    $student_user_id === ""
) {

    $view_errors =
        "대회 또는 학생 정보가 올바르지 않습니다.";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 로그인 확인
// ============================================================

if (
    !isset(
        $_SESSION[
            $OJ_NAME.'_'.'user_id'
        ]
    )
) {

    $view_errors =
        "로그인이 필요합니다.";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 교사용 열람 권한
//
// 허용:
// - administrator
// - 해당 대회의 m{cid}
// - Course에 연결된 Contest인 경우
//   해당 Course의 owner / teacher
//
// 단, Course 권한으로 조회할 때는
// 해당 Course에 등록된 학생만 조회 가능
// ============================================================

$is_admin =
    isset(
        $_SESSION[
            $OJ_NAME.'_administrator'
        ]
    );


$is_contest_manager =
    isset(
        $_SESSION[
            $OJ_NAME.'_m'.$cid
        ]
    );


// ============================================================
// 이 Contest가 Course 차시인지 확인
// ============================================================

$course_id = 0;

$course_contest_rows =
    pdo_query(
        "SELECT
            course_id
         FROM course_contest
         WHERE contest_id = ?
           AND status = 1
         LIMIT 1",
        $cid
    );


if (
    $course_contest_rows &&
    isset(
        $course_contest_rows[0]['course_id']
    )
) {

    $course_id =
        intval(
            $course_contest_rows[0]['course_id']
        );
}


// ============================================================
// Course 교사의 학생 기록 열람 권한
// owner / teacher / assistant는 조회 가능
// assistant는 조회만 가능하며 기록 작성·수정은 불가능
// ============================================================

$is_course_record_viewer = false;

if (
    $course_id > 0 &&
    course_can_view_student_records($course_id)
) {

    // --------------------------------------------------------
    // URL의 user_id를 임의로 변경하여
    // Course 외부 학생을 조회하는 것을 방지
    // --------------------------------------------------------

    $course_student_rows =
        pdo_query(
            "SELECT
                user_id
             FROM course_student
             WHERE course_id = ?
               AND user_id = ?
             LIMIT 1",
            $course_id,
            $student_user_id
        );


    if (
        $course_student_rows &&
        isset(
            $course_student_rows[0]['user_id']
        )
    ) {

        $is_course_record_viewer = true;
    }
}


$can_view_student_summary =
    (
        $is_admin ||
        $is_contest_manager ||
        $is_course_record_viewer
    );


if (!$can_view_student_summary) {

    $view_errors =
        "이 학생의 문제 해결 과정 요약을 볼 권한이 없습니다.";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 대회 정보
// ============================================================

$contest_result =
    pdo_query(
        "SELECT
            contest_id,
            title
         FROM contest
         WHERE contest_id=?
         LIMIT 1",
        $cid
    );


if (
    !$contest_result ||
    count($contest_result) == 0
) {

    $view_errors =
        "존재하지 않는 대회입니다.";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


$contest_title =
    isset($contest_result[0]['title'])
        ? $contest_result[0]['title']
        : $contest_result[0][1];


// ============================================================
// 학생 계정 확인
// ============================================================

$user_result =
    pdo_query(
        "SELECT
            user_id,
            nick
         FROM users
         WHERE user_id=?
         LIMIT 1",
        $student_user_id
    );


if (
    !$user_result ||
    count($user_result) == 0
) {

    $view_errors =
        "존재하지 않는 학생 계정입니다.";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


$student_nick =
    isset($user_result[0]['nick'])
        ? $user_result[0]['nick']
        : "";


// ============================================================
// 대회 문제 목록
//
// num = A/B/C...의 내부 번호
// ============================================================

$problem_result =
    pdo_query(
        "SELECT
            cp.num,
            cp.problem_id,
            p.title
         FROM contest_problem cp
         LEFT JOIN problem p
            ON p.problem_id=cp.problem_id
         WHERE cp.contest_id=?
         ORDER BY cp.num ASC",
        $cid
    );


$student_problem_summary =
    array();

$total_problem_count = 0;


if ($problem_result) {

    foreach ($problem_result as $row) {

        $problem_num =
            isset($row['num'])
                ? intval($row['num'])
                : intval($row[0]);

        $problem_id =
            isset($row['problem_id'])
                ? intval($row['problem_id'])
                : intval($row[1]);

        $problem_title =
            isset($row['title'])
                ? $row['title']
                : $row[2];


        // A, B, C ...
        $problem_label =
            chr(
                ord('A') +
                $problem_num
            );


        $student_problem_summary[
            $problem_num
        ] = array(

            'num' =>
                $problem_num,

            'label' =>
                $problem_label,

            'problem_id' =>
                $problem_id,

            'title' =>
                $problem_title,

            'submit_count' =>
                0,

            'latest_result' =>
            -1,

            'latest_solution_id' =>
            0,

            'has_ac' =>
            0,

            'ai_count' =>
            0,

            'has_plan' =>
                0,

            'attention' =>
                false,

            'repeat_type' =>
                ''
        );


        $total_problem_count++;
    }
}


// ============================================================
// 학생의 대회 제출 통계
//
// solution을 기준으로 실제 제출 횟수와 최종 결과를 가져온다.
// ============================================================

$solution_summary =
    pdo_query(
        "SELECT
        s.problem_id,
    
        COUNT(*) AS submit_count,
    
        MAX(
            CASE
                WHEN s.result = 4
                THEN 1
                ELSE 0
            END
        ) AS has_ac,
    
        MAX(s.solution_id)
            AS latest_solution_id
    
     FROM solution s

         WHERE
            s.contest_id=?
            AND s.user_id=?

         GROUP BY
            s.problem_id",
        $cid,
        $student_user_id
    );


$solution_by_problem =
    array();


if ($solution_summary) {

    foreach ($solution_summary as $row) {

        $pid =
            intval(
                $row['problem_id']
            );

        $solution_by_problem[$pid] =
            array(
                'submit_count' =>
                intval(
                    $row['submit_count']
                ),

                'has_ac' =>
                isset($row['has_ac'])
                    ? intval($row['has_ac'])
                    : 0,

                'latest_solution_id' =>
                intval(
                    $row['latest_solution_id']
                )
            );
    }
}


// ============================================================
// 최신 제출 결과 조회
// ============================================================

foreach (
    $student_problem_summary
    as
    $problem_num => $problem
) {

    $problem_id =
        intval(
            $problem['problem_id']
        );


    if (
        !isset(
            $solution_by_problem[
                $problem_id
            ]
        )
    ) {

        continue;
    }


    $submit_info =
        $solution_by_problem[
            $problem_id
        ];


    $latest_solution_id =
        intval(
            $submit_info[
                'latest_solution_id'
            ]
        );


    $latest_result =
        pdo_query(
            "SELECT result
             FROM solution
             WHERE solution_id=?
             LIMIT 1",
            $latest_solution_id
        );


    $result_num = -1;


    if (
        $latest_result &&
        count($latest_result) > 0
    ) {

        $result_num =
            intval(
                $latest_result[0]['result']
            );
    }

    

    $student_problem_summary[
        $problem_num
    ]['submit_count'] =
        intval(
            $submit_info[
                'submit_count'
            ]
        );


    $student_problem_summary[$problem_num]['has_ac'] =
        isset($submit_info['has_ac'])
        ? intval($submit_info['has_ac'])
        : 0;


    $student_problem_summary[$problem_num]['latest_solution_id'] =
        $latest_solution_id;


    $student_problem_summary[
        $problem_num
    ]['latest_result'] =
        $result_num;
}


// ============================================================
// solution_process 통계
//
// AI 사용 횟수
// 최초 계획 작성 여부
// ============================================================

$process_summary =
    pdo_query(
        "SELECT
            problem_id,

            SUM(
                CASE
                    WHEN ai_used=1
                    THEN 1
                    ELSE 0
                END
            ) AS ai_count,

            MAX(
                CASE
                    WHEN plan_text IS NOT NULL
                         AND plan_text <> ''
                    THEN 1
                    ELSE 0
                END
            ) AS has_plan

         FROM solution_process

         WHERE
            contest_id=?
            AND user_id=?

         GROUP BY
            problem_id",
        $cid,
        $student_user_id
    );


$process_by_problem =
    array();


if ($process_summary) {

    foreach ($process_summary as $row) {

        $pid =
            intval(
                $row['problem_id']
            );


        $process_by_problem[$pid] =
            array(

                'ai_count' =>
                    intval(
                        $row['ai_count']
                    ),

                'has_plan' =>
                    intval(
                        $row['has_plan']
                    )
            );
    }
}


// ============================================================
// 문제별 과정 정보 병합
// ============================================================

foreach (
    $student_problem_summary
    as
    $problem_num => $problem
) {

    $problem_id =
        intval(
            $problem['problem_id']
        );


    if (
        isset(
            $process_by_problem[
                $problem_id
            ]
        )
    ) {

        $student_problem_summary[
            $problem_num
        ]['ai_count'] =
            intval(
                $process_by_problem[
                    $problem_id
                ]['ai_count']
            );


        $student_problem_summary[
            $problem_num
        ]['has_plan'] =
            intval(
                $process_by_problem[
                    $problem_id
                ]['has_plan']
            );
    }
}


// ============================================================
// 학생 전체 요약 계산
// ============================================================

$total_submit_count = 0;
$total_ai_count = 0;

$solved_count = 0;
$plan_problem_count = 0;
$ai_problem_count = 0;
$attention_problem_count = 0;


foreach (
    $student_problem_summary
    as
    $problem_num => $problem
) {

    $submit_count =
        intval(
            $problem['submit_count']
        );

    $result_num =
        intval(
            $problem['latest_result']
        );

    $has_ac =
        isset($problem['has_ac'])
        ? intval($problem['has_ac'])
        : 0;

    $ai_count =
        intval(
            $problem['ai_count']
        );

    $has_plan =
        intval(
            $problem['has_plan']
        );


    $total_submit_count +=
        $submit_count;

    $total_ai_count +=
        $ai_count;


    if ($has_ac === 1) {

        $solved_count++;
    }


    if ($has_plan === 1) {

        $plan_problem_count++;
    }


    if ($ai_count > 0) {

        $ai_problem_count++;
    }


    // ========================================================
    // 확인 필요
    //
    // 현재 교사용 현황과 동일:
    // 5회 이상 제출 + 아직 AC 아님
    // ========================================================

    $attention = false;
    $repeat_type = "";


    if (
        $submit_count >= 5 &&
        $has_ac !== 1
    ) {

        $attention = true;
        $attention_problem_count++;


        // WA
        if ($result_num === 6) {

            $repeat_type =
                "wa";

        }
        // RE
        else if ($result_num === 10) {

            $repeat_type =
                "re";

        }
        // CE
        else if ($result_num === 11) {

            $repeat_type =
                "ce";

        }
        else {

            $repeat_type =
                "other";
        }
    }


    $student_problem_summary[
        $problem_num
    ]['attention'] =
        $attention;


    $student_problem_summary[
        $problem_num
    ]['repeat_type'] =
        $repeat_type;
}
// ============================================================
// 문제별 제출 결과 흐름 조회
//
// 예:
// WA → WA → AC
// CE → AC
//
// solution 테이블의 실제 제출 순서를 사용한다.
// ============================================================

$result_history_rows =
    pdo_query("SELECT
            s.problem_id,
            s.solution_id,
            s.result,
            s.in_date,

            COALESCE(sp.ai_used, 0)
                AS ai_used,

            COALESCE(sp.ai_usage_type, '')
                AS ai_usage_type

         FROM solution s

         LEFT JOIN solution_process sp
            ON sp.solution_id = s.solution_id

         WHERE
            s.contest_id=?
            AND s.user_id=?
            AND s.problem_id>0

         ORDER BY
            s.problem_id ASC,
            s.solution_id ASC",
        $cid,
        $student_user_id
    );


$result_history_by_problem =
    array();


if ($result_history_rows) {

    foreach ($result_history_rows as $row) {

        $pid =
            isset($row['problem_id'])
                ? intval($row['problem_id'])
                : intval($row[0]);

        $solution_id =
            isset($row['solution_id'])
                ? intval($row['solution_id'])
                : intval($row[1]);

        $result_num =
            isset($row['result'])
                ? intval($row['result'])
                : intval($row[2]);


        if (
            !isset(
                $result_history_by_problem[$pid]
            )
        ) {

            $result_history_by_problem[$pid] =
                array();
        }


        $ai_used =
            isset($row['ai_used'])
                ? intval($row['ai_used'])
                : 0;

        $ai_usage_type =
            isset($row['ai_usage_type'])
                ? trim($row['ai_usage_type'])
                : "";


        $result_history_by_problem[$pid][] =
            array(

                'solution_id' =>
                    $solution_id,

                'result' =>
                    $result_num,

                'ai_used' =>
                    $ai_used,

                'ai_usage_type' =>
                    $ai_usage_type
            );
    }
}


// ============================================================
// 문제별 요약에 결과 흐름 추가
// ============================================================

foreach (
    $student_problem_summary
    as
    $problem_num => $problem
) {

    $problem_id =
        intval(
            $problem['problem_id']
        );


    $history =
        isset(
            $result_history_by_problem[
                $problem_id
            ]
        )
            ? $result_history_by_problem[
                $problem_id
            ]
            : array();


    $result_history =
        array();

    $solved_at_attempt =
        0;

    $attempt_number =
        0;

    $ai_history = array();

    foreach ($history as $history_item) {

        $attempt_number++;

        $result_num =
            intval(
                $history_item['result']
            );


        $result_history[] =
            $result_num;

        $ai_history[] =
            array(

                'solution_id' =>
                    intval(
                        $history_item['solution_id']
                    ),

                'result' =>
                    $result_num,

                'ai_used' =>
                    intval(
                        $history_item['ai_used']
                    ),

                'ai_usage_type' =>
                    isset(
                        $history_item['ai_usage_type']
                    )
                        ? $history_item['ai_usage_type']
                        : ""
            );

        // 최초 AC가 나온 제출 횟수
        if (
            $solved_at_attempt == 0 &&
            $result_num === 4
        ) {

            $solved_at_attempt =
                $attempt_number;
        }
    }


    $student_problem_summary[
        $problem_num
    ]['result_history'] =
        $result_history;

    $student_problem_summary[
        $problem_num
    ]['ai_history'] =
        $ai_history;

    $student_problem_summary[
        $problem_num
    ]['solved_at_attempt'] =
        $solved_at_attempt;

}
// ============================================================
// 해결 문제의 평균 해결 시도 횟수
//
// AC를 받은 문제만 계산한다.
// ============================================================

$solved_attempt_total = 0;
$solved_attempt_count = 0;


foreach (
    $student_problem_summary
    as
    $problem_num => $problem
) {

    $solved_at_attempt =
        isset(
            $problem['solved_at_attempt']
        )
            ? intval(
                $problem['solved_at_attempt']
            )
            : 0;


    if ($solved_at_attempt > 0) {

        $solved_attempt_total +=
            $solved_at_attempt;

        $solved_attempt_count++;
    }
}


$average_solved_attempt = 0;


if ($solved_attempt_count > 0) {

    $average_solved_attempt =
        round(
            $solved_attempt_total /
            $solved_attempt_count,
            1
        );
}

// ============================================================
// Template
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/student_process_summary.php"
);

?>