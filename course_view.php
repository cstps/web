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
        block_code_clipboard,
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
        cc.lesson_id,
        cc.contest_id,
        cc.source_contest_id,
        cc.link_type,

        COALESCE(
            cl.lesson_no,
            cc.lesson_no
        ) AS lesson_no,

        COALESCE(
            cl.sort_order,
            cc.sort_order
        ) AS sort_order,

        cc.visible AS visible,

        cc.created_by,
        cc.created_at,

        COALESCE(
            NULLIF(cl.title, ''),
            c.title
        ) AS title,

        c.start_time AS start_time,

        c.end_time AS end_time,

        c.defunct

     FROM course_contest cc

     LEFT JOIN course_lesson cl
       ON cl.lesson_id = cc.lesson_id

     LEFT JOIN contest c
       ON c.contest_id = cc.contest_id

     WHERE cc.course_id = ?
       AND cc.status = 1

     ORDER BY
        COALESCE(
            cl.lesson_no,
            cc.lesson_no
        ),
        COALESCE(
            cl.sort_order,
            cc.sort_order
        ),
        cc.contest_id",
    $course_id
);


if (!is_array($view_contests)) {
    $view_contests = array();
}


// ============================================================
// 8-1. Course Lesson / Activity 목록
//
// Lesson은 Contest 연결 여부와 관계없이 조회한다.
// 기존 Contest 목록은 그대로 유지한다.
// ============================================================

$view_lessons = course_get_lessons($course_id);

$view_activities = course_get_activities($course_id);


// ============================================================
// 8-1. 비활성 일반 Activity 조회
//
// 관리자에게만 제공하며 Contest Activity는 제외한다.
// 기존 활성 Activity 조회 함수는 변경하지 않는다.
// ============================================================

$view_inactive_general_activities = array();

if ($view_can_manage_contests) {

    $inactive_rows = pdo_query(
        "SELECT
            ca.activity_id,
            ca.course_id,
            ca.lesson_id,
            ca.activity_type,
            ca.title,
            ca.description,
            ca.sort_order,
            ca.visible,
            ca.status,
            cl.lesson_no,
            cl.title AS lesson_title,
            cl.status AS lesson_status
         FROM course_activity ca
         LEFT JOIN course_lesson cl
           ON cl.lesson_id = ca.lesson_id
          AND cl.course_id = ca.course_id
         WHERE ca.course_id = ?
           AND ca.status = 0
           AND ca.activity_type IN ('material', 'assignment')
         ORDER BY
            cl.lesson_no,
            ca.sort_order,
            ca.activity_id",
        $course_id
    );

    if (is_array($inactive_rows)) {
        $view_inactive_general_activities = $inactive_rows;
    }
}


// ============================================================
// 8-2. Lesson별 Activity 그룹 구성
//
// Lesson이 없는 기존 활동은 별도로 관리한다.
// 기존 Contest 목록과 관리 기능은 변경하지 않는다.
// ============================================================

$view_lesson_groups = array();

$view_unassigned_activities = array();

foreach ($view_lessons as $lesson) {

    $lesson_id = intval($lesson['lesson_id']);

    if ($lesson_id <= 0) {
        continue;
    }

    $view_lesson_groups[$lesson_id] = array(
        'lesson' => $lesson,
        'activities' => array()
    );
}

foreach ($view_activities as $activity) {

    $lesson_id =
        isset($activity['lesson_id'])
            ? intval($activity['lesson_id'])
            : 0;

    if (
        $lesson_id > 0 &&
        isset($view_lesson_groups[$lesson_id])
    ) {

        $view_lesson_groups[$lesson_id]['activities'][] =
            $activity;

    } else {

        $view_unassigned_activities[] = $activity;
    }
}


// ============================================================
// 9. 현재 진행 중인 수행모드
//
// 한 Course에서는 동시에 하나의 수행모드만 진행한다.
// ============================================================

$view_performance_session = null;

$performance_rows = pdo_query(
    "SELECT
        id,
        course_id,
        status,
        started_at,
        started_by

     FROM course_performance_session

     WHERE course_id = ?
       AND status = 1

     ORDER BY id DESC

     LIMIT 1",
    $course_id
);


if (
    is_array($performance_rows) &&
    isset($performance_rows[0]['id'])
) {
    $view_performance_session =
        $performance_rows[0];
}


// ============================================================
// 10. 제거된 차시 목록
// ============================================================

$view_removed_contests = pdo_query(
    "SELECT
        cc.id,
        cc.lesson_id,
        cc.contest_id,
        cc.source_contest_id,
        cc.link_type,

        COALESCE(
            cl.lesson_no,
            cc.lesson_no
        ) AS lesson_no,

        COALESCE(
            cl.sort_order,
            cc.sort_order
        ) AS sort_order,

        COALESCE(
            cl.visible,
            cc.visible
        ) AS visible,

        cc.status,
        cc.created_by,
        cc.created_at,

        COALESCE(
            NULLIF(cl.title, ''),
            c.title
        ) AS title,

        COALESCE(
            cl.start_time,
            c.start_time
        ) AS start_time,

        COALESCE(
            cl.end_time,
            c.end_time
        ) AS end_time

     FROM course_contest cc

     LEFT JOIN course_lesson cl
       ON cl.lesson_id = cc.lesson_id

     LEFT JOIN contest c
       ON c.contest_id = cc.contest_id

     WHERE cc.course_id = ?
       AND cc.status = 0

     ORDER BY
        COALESCE(
            cl.lesson_no,
            cc.lesson_no
        ),
        COALESCE(
            cl.sort_order,
            cc.sort_order
        ),
        cc.contest_id",
    $course_id
);


if (!is_array($view_removed_contests)) {
    $view_removed_contests = array();
}


// ============================================================
// 11. 화면 출력
// ============================================================

// 이 페이지의 모든 관리 폼이 공유할 CSRF 필드 1개 생성
ob_start();
include("./csrf.php");
$view_csrf_input = ob_get_clean();

require("template/".$OJ_TEMPLATE."/course_view.php");