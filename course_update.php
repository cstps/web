<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "수업 정보 수정";


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

$course_name =
    isset($_POST['course_name'])
        ? trim($_POST['course_name'])
        : '';

$school =
    isset($_POST['school'])
        ? trim($_POST['school'])
        : '';

$school_year =
    isset($_POST['school_year'])
        ? intval($_POST['school_year'])
        : 0;

$semester =
    isset($_POST['semester'])
        ? intval($_POST['semester'])
        : 0;

$description =
    isset($_POST['description'])
        ? trim($_POST['description'])
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
    $course_name === '' ||
    mb_strlen($course_name, 'UTF-8') > 100
) {

    $view_errors =
        "<h2>수업명은 1자 이상 100자 이내로 입력하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (mb_strlen($school, 'UTF-8') > 100) {

    $view_errors =
        "<h2>학교명은 100자 이내로 입력하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    $school_year < 2000 ||
    $school_year > 2100
) {

    $view_errors =
        "<h2>학년도가 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    !in_array(
        $semester,
        array(1, 2),
        true
    )
) {

    $view_errors =
        "<h2>학기 정보가 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (mb_strlen($description, 'UTF-8') > 1000) {

    $view_errors =
        "<h2>수업 설명은 1000자 이내로 입력하세요.</h2>";

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
// 6. Course 접근 / 수정 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_edit($course_id)
) {

    $view_errors =
        "<h2>이 수업의 정보를 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 종료된 Course 수정 방지
//
// 현재 정책:
// 종료된 수업은 기본정보를 수정하지 않는다.
// 재개 후 수정하도록 한다.
// ============================================================

if (intval($course_rows[0]['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업의 정보는 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. Course 기본정보 수정
// ============================================================

pdo_query(
    "UPDATE course
     SET
        course_name = ?,
        school = ?,
        school_year = ?,
        semester = ?,
        description = ?,
        updated_at = CURRENT_TIMESTAMP
     WHERE course_id = ?",
    $course_name,
    $school,
    $school_year,
    $semester,
    $description,
    $course_id
);


// ============================================================
// 9. Course 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);