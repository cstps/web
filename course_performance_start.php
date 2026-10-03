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


// ============================================================
// 4. Course 존재 및 진행 상태 확인
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

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


if (intval($course_rows[0]['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에서는 수행모드를 시작할 수 없습니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 5. 수행모드 관리 권한 확인
//
// administrator / owner / teacher
// assistant는 허용하지 않는다.
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_performance($course_id)
) {

    $view_errors =
        "<h2>이 수업의 수행모드를 시작할 권한이 없습니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 6. 수행모드 시작
//
// 함수 내부에서 Course 행을 FOR UPDATE로 잠그고
// 같은 Course의 중복 시작 요청을 다시 검증한다.
// ============================================================

$session_id =
    course_start_performance_session(
        $course_id
    );


if ($session_id === false) {

    $view_errors =
        "<h2>수행모드를 시작하지 못했습니다.</h2>".
        "<p>이미 수행모드가 진행 중인지 확인해 주세요.</p>";

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

header(
    'Location: /course_view.php?course_id='.
    intval($course_id)
);

exit;
