<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "차시 추가";


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
// 3. Course 존재 확인
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
// 4. Course 접근 권한 확인
// ============================================================

if (!course_can_access($course_id)) {

    $view_errors =
        "<h2>이 수업을 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 차시 관리 권한 확인
// ============================================================

if (!course_can_manage_contests($course_id)) {

    $view_errors =
        "<h2>이 수업의 차시를 관리할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 종료된 Course 확인
// ============================================================

if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 새로운 차시를 추가할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 다음 차시 번호 계산
// ============================================================

$lesson_rows = pdo_query(
    "SELECT
        COALESCE(MAX(lesson_no), 0) + 1 AS next_lesson_no
     FROM course_contest
     WHERE course_id = ?",
    $course_id
);

$view_next_lesson_no =
    isset($lesson_rows[0]['next_lesson_no'])
        ? intval($lesson_rows[0]['next_lesson_no'])
        : 1;

if ($view_next_lesson_no <= 0) {
    $view_next_lesson_no = 1;
}

// ============================================================
// 8. 기존 Contest 연결 미리보기
// ============================================================

$view_link_preview_requested =
    isset($_GET['preview_existing']) &&
    intval($_GET['preview_existing']) === 1;

$view_link_lesson_no =
    isset($_GET['link_lesson_no'])
        ? intval($_GET['link_lesson_no'])
        : $view_next_lesson_no;

$view_existing_contest_id =
    isset($_GET['existing_contest_id'])
        ? intval($_GET['existing_contest_id'])
        : 0;

$view_link_error_message = '';

$view_link_candidate = null;

$view_link_stats = array(
    'active_student_count' => 0,
    'problem_count' => 0,
    'total_submitter_count' => 0,
    'total_submission_count' => 0,
    'course_submitter_count' => 0,
    'course_submission_count' => 0,
    'outside_submitter_count' => 0
);


// ------------------------------------------------------------
// 미리보기 요청 처리
// ------------------------------------------------------------

if ($view_link_preview_requested) {

    if ($view_link_lesson_no <= 0) {

        $view_link_error_message =
            "차시 번호는 1 이상이어야 합니다.";
    }
    elseif ($view_existing_contest_id <= 0) {

        $view_link_error_message =
            "연결할 기존 대회 번호를 입력하세요.";
    }


    // --------------------------------------------------------
    // 차시 번호 중복 확인
    //
    // 제거된 차시도 복원할 수 있으므로 status와 관계없이 검사한다.
    // --------------------------------------------------------

    if ($view_link_error_message === '') {

        $duplicate_lesson_rows = pdo_query(
            "SELECT
                contest_id
             FROM course_contest
             WHERE course_id = ?
               AND lesson_no = ?
             LIMIT 1",
            $course_id,
            $view_link_lesson_no
        );


        if (
            $duplicate_lesson_rows &&
            isset($duplicate_lesson_rows[0]['contest_id'])
        ) {

            $view_link_error_message =
                $view_link_lesson_no.
                "차시는 이미 등록되어 있습니다.";
        }
    }


    // --------------------------------------------------------
    // 기존 Contest 조회
    // --------------------------------------------------------

    if ($view_link_error_message === '') {

        $candidate_rows = pdo_query(
            "SELECT
                contest_id,
                title,
                start_time,
                end_time,
                defunct,
                user_id,

                CASE
                    WHEN end_time IS NOT NULL
                    AND end_time < NOW()
                    THEN 1
                    ELSE 0
                END AS is_ended,

                CASE
                    WHEN start_time IS NULL
                    OR end_time IS NULL
                    THEN 'unscheduled'

                    WHEN NOW() < start_time
                    THEN 'upcoming'

                    WHEN NOW() >= end_time
                    THEN 'ended'

                    ELSE 'running'
                END AS contest_state

             FROM contest
             WHERE contest_id = ?
             LIMIT 1",
            $view_existing_contest_id
        );


        if (
            !$candidate_rows ||
            !isset($candidate_rows[0]['contest_id'])
        ) {

            $view_link_error_message =
                "존재하지 않는 대회입니다.";
        }
        else {

            $candidate = $candidate_rows[0];


            if (
                strtoupper(
                    trim($candidate['defunct'])
                ) !== 'N'
            ) {

                $view_link_error_message =
                    "삭제 또는 비활성화된 대회는 연결할 수 없습니다.";
            }
            elseif (
                !isset($_SESSION[$OJ_NAME.'_administrator']) &&
                trim($candidate['user_id']) !== $user_id
            ) {

                $view_link_error_message =
                    "현재 사용자가 생성한 대회만 연결할 수 있습니다.";
            }
        }
    }


    // --------------------------------------------------------
    // 다른 Course 연결 여부 확인
    //
    // status=0도 복원 가능한 기존 관계이므로 중복 연결을 허용하지 않는다.
    // --------------------------------------------------------

    if ($view_link_error_message === '') {

        $existing_link_rows = pdo_query(
            "SELECT
                course_id,
                lesson_no,
                status,
                link_type
             FROM course_contest
             WHERE contest_id = ?
             LIMIT 1",
            $view_existing_contest_id
        );


        if (
            $existing_link_rows &&
            isset($existing_link_rows[0]['course_id'])
        ) {

            $existing_course_id =
                intval($existing_link_rows[0]['course_id']);

            $existing_status =
                intval($existing_link_rows[0]['status']);


            if (
                $existing_course_id === $course_id &&
                $existing_status === 0
            ) {

                $view_link_error_message =
                    "이 대회는 현재 수업에서 제거된 차시입니다. ".
                    "새로 연결하지 말고 제거된 차시 목록에서 복원하세요.";
            }
            elseif ($existing_course_id === $course_id) {

                $view_link_error_message =
                    "이 대회는 이미 현재 수업에 연결되어 있습니다.";
            }
            else {

                $view_link_error_message =
                    "이 대회는 이미 다른 수업에 연결되어 있습니다.";
            }
        }
    }


    // --------------------------------------------------------
    // 연결 전 미리보기 통계
    // --------------------------------------------------------

    if ($view_link_error_message === '') {

        $student_count_rows = pdo_query(
            "SELECT
                COUNT(*) AS active_student_count
             FROM course_student
             WHERE course_id = ?
               AND status = 1",
            $course_id
        );


        $problem_count_rows = pdo_query(
            "SELECT
                COUNT(*) AS problem_count
             FROM contest_problem
             WHERE contest_id = ?",
            $view_existing_contest_id
        );


        $total_submission_rows = pdo_query(
            "SELECT
                COUNT(*) AS total_submission_count,
                COUNT(DISTINCT user_id) AS total_submitter_count
             FROM solution
             WHERE contest_id = ?",
            $view_existing_contest_id
        );


        $course_submission_rows = pdo_query(
            "SELECT
                COUNT(*) AS course_submission_count,
                COUNT(DISTINCT s.user_id) AS course_submitter_count

             FROM solution s

             INNER JOIN course_student cs
               ON cs.user_id = s.user_id
              AND cs.course_id = ?
              AND cs.status = 1

             WHERE s.contest_id = ?",
            $course_id,
            $view_existing_contest_id
        );


        $view_link_stats['active_student_count'] =
            isset($student_count_rows[0]['active_student_count'])
                ? intval(
                    $student_count_rows[0]['active_student_count']
                )
                : 0;

        $view_link_stats['problem_count'] =
            isset($problem_count_rows[0]['problem_count'])
                ? intval(
                    $problem_count_rows[0]['problem_count']
                )
                : 0;

        $view_link_stats['total_submission_count'] =
            isset(
                $total_submission_rows[0]['total_submission_count']
            )
                ? intval(
                    $total_submission_rows[0]['total_submission_count']
                )
                : 0;

        $view_link_stats['total_submitter_count'] =
            isset(
                $total_submission_rows[0]['total_submitter_count']
            )
                ? intval(
                    $total_submission_rows[0]['total_submitter_count']
                )
                : 0;

        $view_link_stats['course_submission_count'] =
            isset(
                $course_submission_rows[0]['course_submission_count']
            )
                ? intval(
                    $course_submission_rows[0]['course_submission_count']
                )
                : 0;

        $view_link_stats['course_submitter_count'] =
            isset(
                $course_submission_rows[0]['course_submitter_count']
            )
                ? intval(
                    $course_submission_rows[0]['course_submitter_count']
                )
                : 0;

        $view_link_stats['outside_submitter_count'] =
            max(
                0,
                $view_link_stats['total_submitter_count'] -
                $view_link_stats['course_submitter_count']
            );


        $view_link_candidate = $candidate;
    }
}


// ============================================================
// 9. 화면 출력
// ============================================================

require("template/".$OJ_TEMPLATE."/course_contest_add.php");