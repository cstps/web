<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. POST 요청 확인
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors = "<h2>잘못된 요청입니다.</h2>";

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
// 4. Course 존재 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
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


// ============================================================
// 5. Course 차시 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 차시를 제거할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. Course ↔ Contest 연결 확인
// ============================================================

$link_rows = pdo_query(
    "SELECT
        id,
        status,
        link_type
     FROM course_contest
     WHERE course_id = ?
       AND contest_id = ?
     LIMIT 1",
    $course_id,
    $contest_id
);


if (
    !$link_rows ||
    !isset($link_rows[0]['id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (intval($link_rows[0]['status']) !== 1) {

    $view_errors =
        "<h2>이미 제거된 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$link_type =
    isset($link_rows[0]['link_type'])
        ? trim($link_rows[0]['link_type'])
        : '';


if (
    !in_array(
        $link_type,
        array('created', 'linked'),
        true
    )
) {

    $view_errors =
        "<h2>차시 연결 유형이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 7. Course에서 차시 제거
//
// 실제 Contest/solution은 삭제하지 않는다.
// ============================================================

pdo_query(
    "UPDATE course_contest
     SET
        status = 0,
        visible = 0
     WHERE course_id = ?
       AND contest_id = ?
       AND status = 1",
    $course_id,
    $contest_id
);


/// ============================================================
// 8. Course 학생들의 Contest 참가권한 제거
//
// created:
// Course 전용 Contest이므로 기존 방식대로 권한 삭제
//
// linked:
// Course가 추가하고 추적한 권한만 회수
// 학생이 원래 가지고 있던 권한은 유지
//
// 수강 종료 학생도 포함하여 조회한다.
// 이전 처리 실패 등으로 남은 추적 권한까지 정리하기 위함이다.
// ============================================================

$student_rows = pdo_query(
    "SELECT user_id
     FROM course_student
     WHERE course_id = ?
     ORDER BY user_id",
    $course_id
);


if (!is_array($student_rows)) {
    $student_rows = array();
}


foreach ($student_rows as $student) {

    if (
        !isset($student['user_id']) ||
        trim($student['user_id']) === ''
    ) {
        continue;
    }


    $student_user_id =
        trim($student['user_id']);


    // --------------------------------------------------------
    // Course에서 생성한 Contest
    // --------------------------------------------------------

    if ($link_type === 'created') {

        pdo_query(
            "DELETE FROM privilege
             WHERE user_id = ?
               AND rightstr = ?",
            $student_user_id,
            "c".$contest_id
        );


        continue;
    }


    // --------------------------------------------------------
    // 기존 Contest 연결
    // --------------------------------------------------------

    if ($link_type === 'linked') {

        course_revoke_linked_student_right(
            $course_id,
            $contest_id,
            $student_user_id
        );
    }
}


// ============================================================
// 9. Course 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);