<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "기존 대회 연결";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors =
        "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$user_id =
    $_SESSION[$OJ_NAME.'_user_id'];

$is_administrator =
    isset($_SESSION[$OJ_NAME.'_administrator']);


// ============================================================
// 2. POST 요청 확인
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors =
        "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. 입력값 확인
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;

$contest_id =
    isset($_POST['contest_id'])
        ? intval($_POST['contest_id'])
        : 0;

$lesson_no =
    isset($_POST['lesson_no'])
        ? intval($_POST['lesson_no'])
        : 0;


if (
    $course_id <= 0 ||
    $contest_id <= 0
) {

    $view_errors =
        "<h2>잘못된 수업 또는 대회 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if ($lesson_no <= 0) {

    $view_errors =
        "<h2>차시 번호는 1 이상이어야 합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course 존재 및 상태 확인
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


$view_course =
    $course_rows[0];


if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 대회를 연결할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. Course 차시 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업에 대회를 연결할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 연결할 Contest 확인
//
// 연결 조건:
// - 실제 존재하는 대회
// - 삭제되지 않은 대회(defunct = N)
// - 시작 전·진행 중·종료 대회 모두 연결 가능
// - 현재 사용자가 생성한 대회
//   단, 사이트 관리자는 생성자 제한 제외
// ============================================================

$contest_rows = pdo_query(
    "SELECT
        contest_id,
        title,
        start_time,
        end_time,
        defunct,
        user_id
     FROM contest
     WHERE contest_id = ?
     LIMIT 1",
    $contest_id
);


if (
    !$contest_rows ||
    !isset($contest_rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>존재하지 않는 대회입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$contest =
    $contest_rows[0];


if (
    !isset($contest['defunct']) ||
    strtoupper(trim($contest['defunct'])) !== 'N'
) {

    $view_errors =
        "<h2>삭제된 대회는 수업에 연결할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}



// ============================================================
// 7. Contest 생성자 확인
//
// 일반 교사:
// 자신이 생성한 Contest만 연결 가능
//
// administrator:
// 생성자 제한 없이 연결 가능
// ============================================================

$contest_owner =
    isset($contest['user_id'])
        ? trim($contest['user_id'])
        : '';


if (
    !$is_administrator &&
    $contest_owner !== $user_id
) {

    $view_errors =
        "<h2>자신이 생성한 대회만 수업에 연결할 수 있습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 기존 Course 연결 확인
//
// contest_id는 Course 전체에서 한 번만 연결할 수 있다.
// status=0인 제거된 연결도 기존 연결로 판단한다.
// ============================================================

$existing_link_rows = pdo_query(
    "SELECT
        id,
        course_id,
        lesson_no,
        status
     FROM course_contest
     WHERE contest_id = ?
     LIMIT 1",
    $contest_id
);


if (
    $existing_link_rows &&
    isset($existing_link_rows[0]['id'])
) {

    $existing_link =
        $existing_link_rows[0];

    $existing_course_id =
        intval($existing_link['course_id']);

    $existing_status =
        intval($existing_link['status']);


    if ($existing_course_id === $course_id) {

        if ($existing_status === 0) {

            $view_errors =
                "<h2>이 대회는 이 수업에서 제거된 차시입니다.</h2>".
                "<p>새로 연결하지 말고 차시 복원 기능을 사용하세요.</p>";

        } else {

            $view_errors =
                "<h2>이 대회는 이미 이 수업에 등록되어 있습니다.</h2>";
        }

    } else {

        $view_errors =
            "<h2>이 대회는 이미 다른 수업에 연결되어 있습니다.</h2>";
    }


    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. 차시 번호 중복 확인
//
// 제거된 차시(status=0)도 복원될 수 있으므로
// 중복 검사에서 제외하지 않는다.
// ============================================================

$duplicate_lesson_rows = pdo_query(
    "SELECT
        contest_id,
        status
     FROM course_contest
     WHERE course_id = ?
       AND lesson_no = ?
     LIMIT 1",
    $course_id,
    $lesson_no
);


if (
    $duplicate_lesson_rows &&
    isset($duplicate_lesson_rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>".$lesson_no."차시는 이미 등록되어 있습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 10. 표시 순서 계산
//
// 기존 차시:
// 10, 20, 30 ...
//
// 새 연결:
// MAX(sort_order) + 10
// ============================================================

$sort_rows = pdo_query(
    "SELECT
        COALESCE(MAX(sort_order), 0) + 10
            AS next_sort_order
     FROM course_contest
     WHERE course_id = ?",
    $course_id
);


$sort_order =
    isset($sort_rows[0]['next_sort_order'])
        ? intval($sort_rows[0]['next_sort_order'])
        : 10;


if ($sort_order <= 0) {
    $sort_order = 10;
}


// ============================================================
// 11. 기존 Contest를 Course 차시로 연결
//
// 추가되는 데이터:
// - course_contest 관계 한 행
//
// 변경하지 않는 데이터:
// - contest
// - contest_problem
// - solution
// - source_code
// - runtimeinfo
// - privilege
//
// 기존 대회이므로 source_contest_id는 NULL이다.
// 처음 연결할 때는 교사가 확인 후 공개하도록 visible=0.
// ============================================================

try {

    $link_id = pdo_query(
        "INSERT INTO course_contest
        (
            course_id,
            contest_id,
            source_contest_id,
            link_type,
            lesson_no,
            sort_order,
            visible,
            status,
            created_by
        )
        VALUES
        (
            ?,
            ?,
            NULL,
            'linked',
            ?,
            ?,
            0,
            1,
            ?
        )",
        $course_id,
        $contest_id,
        $lesson_no,
        $sort_order,
        $user_id
    );


    $link_id =
        intval($link_id);


    if ($link_id <= 0) {

        throw new Exception(
            '기존 대회 연결에 실패했습니다.'
        );
    }

}
catch (Exception $e) {

    /*
     * 사전 검사 이후 다른 요청이 동시에 실행되어
     * contest_id 또는 lesson_no UNIQUE 제약에 걸린 경우도
     * 이 오류로 처리한다.
     *
     * 내부 DB 오류 내용은 사용자에게 노출하지 않는다.
     */

    $view_errors =
        "<h2>대회를 수업에 연결하지 못했습니다.</h2>".
        "<p>이미 연결된 대회이거나 차시 번호가 중복되었는지 확인하세요.</p>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 12. Course 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);