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

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 2. POST 요청만 허용
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors =
        "<h2>잘못된 요청입니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 3. course_id 확인
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;


if ($course_id <= 0) {

    $view_errors =
        "<h2>잘못된 수업 번호입니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}



$return_to =
    isset($_POST['return_to'])
        ? trim((string)$_POST['return_to'])
        : '';

$return_url =
    ($return_to === 'course_list')
        ? '/course_list.php'
        : '/course_view.php?course_id='.$course_id;

// ============================================================
// 4. Course 존재 확인
//
// 종료 처리에서는 course.status를 제한하지 않는다.
// 수업이 먼저 종료됐더라도 남아 있는 수행모드는
// 반드시 해제할 수 있어야 한다.
// ============================================================

$course_rows = pdo_query(
    "SELECT course_id
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

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 5. 수행모드 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_performance($course_id)
) {

    $view_errors =
        "<h2>이 수업의 수행모드를 종료할 권한이 없습니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 6. 수행모드 종료
// ============================================================

$result =
    course_end_performance_session(
        $course_id
    );


if (!$result) {

    $view_errors =
        "<h2>수행모드를 종료하지 못했습니다.</h2>".
        "<p>현재 진행 중인 수행모드가 있는지 확인해 주세요.</p>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 7. Course 화면으로 복귀
// ============================================================

header("Location: ".$return_url);

exit;
