<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "차시 수정";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. course_id / contest_id 확인
// ============================================================

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;

$contest_id =
    isset($_GET['contest_id'])
        ? intval($_GET['contest_id'])
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
// 3. Course 접근 / 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 차시를 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course + Contest 연결 정보 조회
// ============================================================

$rows = pdo_query(
    "SELECT
        cc.id,
        cc.course_id,
        cc.contest_id,
        cc.source_contest_id,
        cc.link_type,
        cc.lesson_no,
        cc.sort_order,
        cc.visible,

        c.title,
        c.start_time,
        c.end_time

     FROM course_contest cc

     INNER JOIN contest c
       ON c.contest_id = cc.contest_id

     WHERE cc.course_id = ?
       AND cc.contest_id = ?
       AND cc.status = 1

     LIMIT 1",
    $course_id,
    $contest_id
);


if (
    !$rows ||
    !isset($rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$view_contest = $rows[0];

$view_link_type =
    isset($view_contest['link_type'])
        ? trim($view_contest['link_type'])
        : 'created';


if (
    !in_array(
        $view_link_type,
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
// 5. Course 정보 조회
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

$view_course = $course_rows[0];


if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업의 차시는 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 화면 출력
// ============================================================

require(
    "template/".$OJ_TEMPLATE."/course_contest_edit.php"
);