<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "수업 정보";


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
        description,
        status,
        created_by,
        created_at,
        updated_at
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
// 4. 접근 권한 확인
// ============================================================

if (!course_can_access($course_id)) {

    $view_errors =
        "<h2>이 수업을 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$view_course_role =
    course_get_role($course_id);


// ============================================================
// 5. 기능별 권한
// ============================================================

$view_can_edit =
    course_can_edit($course_id);

$view_can_manage_teachers =
    course_can_manage_teachers($course_id);

$view_can_manage_students =
    course_can_manage_students($course_id);

$view_can_manage_contests =
    course_can_manage_contests($course_id);


// ============================================================
// 6. 담당 교사 목록
// ============================================================

$view_teachers = pdo_query(
    "SELECT
        ct.user_id,
        ct.role,
        ct.status,
        ct.joined_at,

        u.nick,
        u.school

     FROM course_teacher ct

     LEFT JOIN users u
       ON u.user_id = ct.user_id

     WHERE ct.course_id = ?
       AND ct.status = 1

     ORDER BY
        CASE ct.role
            WHEN 'owner' THEN 1
            WHEN 'teacher' THEN 2
            WHEN 'assistant' THEN 3
            ELSE 9
        END,
        ct.user_id",
    $course_id
);


if (!is_array($view_teachers)) {
    $view_teachers = array();
}


// ============================================================
// 7. 현재 학생 수
// ============================================================

$student_rows = pdo_query(
    "SELECT COUNT(*) AS cnt
     FROM course_student
     WHERE course_id = ?
       AND status = 1",
    $course_id
);


$view_student_count =
    isset($student_rows[0]['cnt'])
        ? intval($student_rows[0]['cnt'])
        : 0;


// ============================================================
// 8. 연결된 대회 목록
// ============================================================

$view_contests = pdo_query(
    "SELECT
        cc.id,
        cc.contest_id,
        cc.source_contest_id,
        cc.link_type,
        cc.lesson_no,
        cc.sort_order,
        cc.visible,
        cc.created_by,
        cc.created_at,

        c.title,
        c.start_time,
        c.end_time,
        c.defunct

     FROM course_contest cc

    LEFT JOIN contest c
    ON c.contest_id = cc.contest_id

    WHERE cc.course_id = ?
        AND cc.status = 1

    ORDER BY
        cc.lesson_no,
        cc.contest_id",
    $course_id
);


if (!is_array($view_contests)) {
    $view_contests = array();
}

// ============================================================
// 9. 제거된 차시 목록
// ============================================================

$view_removed_contests = pdo_query(
    "SELECT
        cc.id,
        cc.contest_id,
        cc.source_contest_id,
        cc.link_type,
        cc.lesson_no,
        cc.sort_order,
        cc.visible,
        cc.status,
        cc.created_by,
        cc.created_at,

        c.title,
        c.start_time,
        c.end_time

     FROM course_contest cc

     LEFT JOIN contest c
       ON c.contest_id = cc.contest_id

     WHERE cc.course_id = ?
       AND cc.status = 0

     ORDER BY
        cc.sort_order,
        cc.contest_id",
    $course_id
);


if (!is_array($view_removed_contests)) {
    $view_removed_contests = array();
}


// ============================================================
// 10. 화면 출력
// ============================================================

// 이 페이지의 모든 관리 폼이 공유할 CSRF 필드 1개 생성
ob_start();
include("./csrf.php");
$view_csrf_input = ob_get_clean();

require("template/".$OJ_TEMPLATE."/course_view.php");