<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = "학생 문제 해결 과정 현황";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. cid 확인
// ============================================================

if (!isset($_GET['cid']) || intval($_GET['cid']) <= 0) {

    $view_errors = "<h2>잘못된 대회 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$cid = intval($_GET['cid']);


// ============================================================
// 3. 권한
// ============================================================

$is_admin =
    isset($_SESSION[$OJ_NAME.'_'.'administrator']);

$is_source_browser =
    isset($_SESSION[$OJ_NAME.'_'.'source_browser']);

$is_contest_manager =
    isset($_SESSION[$OJ_NAME.'_m'.$cid]);


$can_view_contest_process = (
    $is_admin ||
    $is_source_browser ||
    $is_contest_manager
);


if (!$can_view_contest_process) {

    $view_errors =
        "<h2>이 대회의 학생 과정 현황을 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. 대회 정보
// ============================================================

$contest_sql = "SELECT title
                FROM contest
                WHERE contest_id=?
                LIMIT 1";

$contest_result =
    pdo_query(
        $contest_sql,
        $cid
    );


if (
    !$contest_result ||
    count($contest_result) == 0
) {

    $view_errors =
        "<h2>대회를 찾을 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}



$contest_title =
    isset($contest_result[0]['title'])
        ? $contest_result[0]['title']
        : $contest_result[0][0];

// ============================================================
// 대회 문제 목록
// ============================================================

$contest_problem_sql = "SELECT
                            cp.num,
                            cp.problem_id
                        FROM contest_problem cp
                        WHERE cp.contest_id=?
                        ORDER BY cp.num ASC";

$contest_problem_result =
    pdo_query(
        $contest_problem_sql,
        $cid
    );

if (!$contest_problem_result) {
    $contest_problem_result = array();
}


$contest_problems = array();

foreach ($contest_problem_result as $row) {

    $num =
        isset($row['num'])
            ? intval($row['num'])
            : intval($row[0]);

    $problem_id =
        isset($row['problem_id'])
            ? intval($row['problem_id'])
            : intval($row[1]);

    $contest_problems[$num] = array(
        'num'        => $num,
        'label'      => isset($PID[$num]) ? $PID[$num] : chr(ord('A') + $num),
        'problem_id' => $problem_id
    );
} 
// ============================================================
// 대회 참가 학생 목록
//
// 1. privilege의 c{cid} 권한 사용자
// 2. 실제 대회 제출 사용자
//
// 두 목록을 합쳐서 사용한다.
// 이렇게 하면 아직 제출하지 않은 참가 학생도 표시된다.
// ============================================================

// ============================================================
// 운영 권한 계정 목록
//
// 학생 현황판에서 제외할 사용자
// ============================================================

$excluded_users = array();

$manager_right =
    "m".$cid;


// 전역 관리자/교사 권한 + 해당 대회 관리자
$staff_sql = "SELECT DISTINCT user_id
              FROM privilege
              WHERE rightstr IN (
                  'administrator',
                  'contest_creator',
                  'problem_editor',
                  'source_browser',
                  ?
              )";

$staff_result =
    pdo_query(
        $staff_sql,
        $manager_right
    );


if ($staff_result) {

    foreach ($staff_result as $row) {

        $uid =
            isset($row['user_id'])
                ? trim($row['user_id'])
                : trim($row[0]);

        if ($uid != "") {

            $excluded_users[
                strtolower($uid)
            ] = true;
        }
    }
}

$contest_students = array();


// ------------------------------------------------------------
// 1. 대회 참가 권한(c{cid}) 사용자
// ------------------------------------------------------------

$contest_right = "c".$cid;

$student_sql = "SELECT DISTINCT user_id
                FROM privilege
                WHERE rightstr=?
                ORDER BY user_id ASC";

$student_result =
    pdo_query(
        $student_sql,
        $contest_right
    );


if ($student_result) {

    foreach ($student_result as $row) {

        $uid =
            isset($row['user_id'])
                ? trim($row['user_id'])
                : trim($row[0]);

        if (
            $uid != "" &&
            !isset(
                $excluded_users[
                    strtolower($uid)
                ]
            )
        ) {

            $contest_students[$uid] = true;
        }
    }
}


// ------------------------------------------------------------
// 2. 실제 대회 제출 사용자도 포함
//
// 공개 대회 또는 privilege가 없는 경우에 대비
// ------------------------------------------------------------

$submitted_student_sql = "SELECT DISTINCT user_id
                          FROM solution
                          WHERE contest_id=?
                          AND problem_id>0
                          ORDER BY user_id ASC";

$submitted_student_result =
    pdo_query(
        $submitted_student_sql,
        $cid
    );


if ($submitted_student_result) {

    foreach ($submitted_student_result as $row) {

        $uid =
            isset($row['user_id'])
                ? trim($row['user_id'])
                : trim($row[0]);

        if (
            $uid != "" &&
            !isset(
                $excluded_users[
                    strtolower($uid)
                ]
            )
        ) {

            $contest_students[$uid] = true;
        }
    }
}


// 학생 ID순 정렬
ksort($contest_students);


// ============================================================
// 5. 학생 + 문제별 과정 요약
//
// - solution_process가 있는 실제 과정 제출만 집계
// - 최신 solution_id로 최종 결과 확인
// ============================================================

$sql = "SELECT
            sp.user_id,
            sp.problem_id,

            COUNT(*) AS submit_count,

            SUM(
                CASE
                    WHEN sp.ai_used=1 THEN 1
                    ELSE 0
                END
            ) AS ai_count,

            MAX(
                CASE
                    WHEN sp.plan_text IS NOT NULL
                     AND TRIM(sp.plan_text) <> ''
                    THEN 1
                    ELSE 0
                END
            ) AS has_plan,

            MAX(sp.solution_id) AS latest_solution_id

        FROM solution_process sp

        WHERE sp.contest_id=?

        GROUP BY
            sp.user_id,
            sp.problem_id

        ORDER BY
            sp.user_id ASC,
            sp.problem_id ASC";


$summary_result =
    pdo_query(
        $sql,
        $cid
    );


if (!$summary_result) {
    $summary_result = array();
}


// ============================================================
// 6. 최신 제출 결과를 한 번에 조회
// ============================================================

$latest_ids = array();

foreach ($summary_result as $row) {

    $latest_sid =
        isset($row['latest_solution_id'])
            ? intval($row['latest_solution_id'])
            : intval($row[5]);

    if ($latest_sid > 0) {
        $latest_ids[] = $latest_sid;
    }
}


$latest_result_map = array();

if (count($latest_ids) > 0) {

    $latest_ids =
        array_values(
            array_unique($latest_ids)
        );

    $id_list =
        implode(",", $latest_ids);


    $latest_sql = "SELECT
                        solution_id,
                        result,
                        num
                   FROM solution
                   WHERE solution_id IN ($id_list)";


    $latest_result =
        pdo_query($latest_sql);


    if ($latest_result) {

        foreach ($latest_result as $row) {

            $solution_id =
                isset($row['solution_id'])
                    ? intval($row['solution_id'])
                    : intval($row[0]);

            $latest_result_map[$solution_id] = array(
                'result' =>
                    isset($row['result'])
                        ? intval($row['result'])
                        : intval($row[1]),

                'num' =>
                    isset($row['num'])
                        ? intval($row['num'])
                        : intval($row[2])
            );
        }
    }
}


// ============================================================
// 7. 화면용 데이터 정리
// ============================================================

$view_process_list = array();

foreach ($summary_result as $row) {

    $user_id =
        isset($row['user_id'])
            ? $row['user_id']
            : $row[0];

    $problem_id =
        isset($row['problem_id'])
            ? intval($row['problem_id'])
            : intval($row[1]);

    $submit_count =
        isset($row['submit_count'])
            ? intval($row['submit_count'])
            : intval($row[2]);

    $ai_count =
        isset($row['ai_count'])
            ? intval($row['ai_count'])
            : intval($row[3]);

    $has_plan =
        isset($row['has_plan'])
            ? intval($row['has_plan'])
            : intval($row[4]);

    $latest_solution_id =
        isset($row['latest_solution_id'])
            ? intval($row['latest_solution_id'])
            : intval($row[5]);


    $latest_result_num = -1;
    $problem_num = -1;

    if (
        isset(
            $latest_result_map[
                $latest_solution_id
            ]
        )
    ) {

        $latest_result_num =
            $latest_result_map[
                $latest_solution_id
            ]['result'];

        $problem_num =
            $latest_result_map[
                $latest_solution_id
            ]['num'];
    }


    $view_process_list[] = array(
        'user_id'            => $user_id,
        'problem_id'         => $problem_id,
        'problem_num'        => $problem_num,
        'submit_count'       => $submit_count,
        'ai_count'           => $ai_count,
        'has_plan'           => $has_plan,
        'latest_solution_id' => $latest_solution_id,
        'latest_result'      => $latest_result_num
    );
}

// ============================================================
// 학생 × 문제 매트릭스 데이터 생성
// ============================================================

$student_matrix = array();


// ============================================================
// 참가 학생 전체를 먼저 등록
//
// 아직 한 번도 제출하지 않은 학생도 현황판에 표시
// ============================================================

foreach ($contest_students as $uid => $dummy) {

    $student_matrix[$uid] = array(
        'user_id'      => $uid,
        'problems'     => array(),
        'total_submit' => 0,
        'total_ai'     => 0,
        'solved_count' => 0
    );
}


// ============================================================
// 실제 과정 데이터 적용
// ============================================================

foreach ($view_process_list as $item) {

    $uid =
        $item['user_id'];

    $num =
        intval(
            $item['problem_num']
        );


    // 학생 기본 정보
    if (!isset($student_matrix[$uid])) {

        $student_matrix[$uid] = array(
            'user_id'       => $uid,
            'problems'      => array(),
            'total_submit'  => 0,
            'total_ai'      => 0,
            'solved_count'  => 0
        );
    }


    // 문제별 상태
    $student_matrix[$uid]['problems'][$num] = array(
        'problem_id'         => intval($item['problem_id']),
        'latest_solution_id' => intval($item['latest_solution_id']),
        'latest_result'      => intval($item['latest_result']),
        'submit_count'       => intval($item['submit_count']),
        'ai_count'           => intval($item['ai_count']),
        'has_plan'           => intval($item['has_plan'])
    );


    $student_matrix[$uid]['total_submit'] +=
        intval($item['submit_count']);

    $student_matrix[$uid]['total_ai'] +=
        intval($item['ai_count']);


    // HUSTOJ result 4 = Accepted
    if (intval($item['latest_result']) === 4) {

        $student_matrix[$uid]['solved_count']++;
    }
}


// 학생 ID순 정렬
ksort($student_matrix);

// ============================================================
// 8. Template
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/contest_process.php"
);

?>