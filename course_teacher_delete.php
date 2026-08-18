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

    $view_errors =
        "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


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
// 3. 입력값
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;

$teacher_user_id =
    isset($_POST['user_id'])
        ? trim($_POST['user_id'])
        : '';


// ============================================================
// 4. 기본값 검증
// ============================================================

if ($course_id <= 0) {

    $view_errors =
        "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    $teacher_user_id === '' ||
    strlen($teacher_user_id) > 48
) {

    $view_errors =
        "<h2>교사 아이디가 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. Course 존재 확인
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
// 6. 교사 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_teachers($course_id)
) {

    $view_errors =
        "<h2>이 수업의 교사를 관리할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 대상 교사 확인
// ============================================================

$teacher_rows = pdo_query(
    "SELECT
        user_id,
        role,
        status
     FROM course_teacher
     WHERE course_id = ?
       AND user_id = ?
     LIMIT 1",
    $course_id,
    $teacher_user_id
);


if (
    !$teacher_rows ||
    !isset($teacher_rows[0]['user_id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 교사입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$current_role =
    isset($teacher_rows[0]['role'])
        ? trim($teacher_rows[0]['role'])
        : '';

$current_status =
    isset($teacher_rows[0]['status'])
        ? intval($teacher_rows[0]['status'])
        : 0;


// ============================================================
// 8. 이미 제외된 교사 확인
// ============================================================

if ($current_status !== 1) {

    $view_errors =
        "<h2>이미 제외된 교사입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. owner 제외 방지
// ============================================================

if ($current_role === 'owner') {

    $view_errors =
        "<h2>책임교사는 제외할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 10. Course 교사 비활성화
// ============================================================

pdo_query(
    "UPDATE course_teacher
     SET
        status = 0,
        updated_at = CURRENT_TIMESTAMP
     WHERE course_id = ?
       AND user_id = ?
       AND status = 1",
    $course_id,
    $teacher_user_id
);


// ============================================================
// 11. 해당 Course의 Contest 관리 권한 회수
//
// 다른 Course나 일반 Contest의 m 권한은 건드리지 않는다.
// ============================================================

$contest_rows = pdo_query(
    "SELECT
        contest_id
     FROM course_contest
     WHERE course_id = ?
     ORDER BY contest_id",
    $course_id
);


if (!is_array($contest_rows)) {
    $contest_rows = array();
}


foreach ($contest_rows as $contest) {

    if (!isset($contest['contest_id'])) {
        continue;
    }


    $contest_id =
        intval($contest['contest_id']);


    if ($contest_id <= 0) {
        continue;
    }


    pdo_query(
        "DELETE FROM privilege
         WHERE user_id = ?
           AND rightstr = ?",
        $teacher_user_id,
        "m".$contest_id
    );
}


// ============================================================
// 12. 교사 관리 화면으로 복귀
// ============================================================

header(
    "Location: course_teachers.php?course_id=".$course_id
);

exit(0);