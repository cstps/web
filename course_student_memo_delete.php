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


$current_user_id =
    $_SESSION[$OJ_NAME.'_user_id'];

$is_admin =
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
// 3. 입력값
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;

$student_user_id =
    isset($_POST['user_id'])
        ? trim($_POST['user_id'])
        : '';

$memo_id =
    isset($_POST['memo_id'])
        ? intval($_POST['memo_id'])
        : 0;


if (
    $course_id <= 0 ||
    $memo_id <= 0 ||
    $student_user_id === ''
) {

    $view_errors =
        "<h2>잘못된 메모 정보입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course 접근 권한 확인
// ============================================================

if (!course_can_manage_student_records($course_id)) {

    $view_errors =
        "<h2>이 수업의 메모를 삭제할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 메모 조회
// ============================================================

$memo_rows = pdo_query(
    "SELECT
        id,
        created_by
     FROM course_student_memo
     WHERE id = ?
       AND course_id = ?
       AND user_id = ?
     LIMIT 1",
    $memo_id,
    $course_id,
    $student_user_id
);


if (
    !$memo_rows ||
    !isset($memo_rows[0]['id'])
) {

    $view_errors =
        "<h2>존재하지 않는 메모입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 삭제 권한 확인
// ============================================================

if (
    !$is_admin &&
    $memo_rows[0]['created_by'] !== $current_user_id
) {

    $view_errors =
        "<h2>이 메모를 삭제할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 메모 삭제
// ============================================================

pdo_query(
    "DELETE FROM course_student_memo
     WHERE id = ?
       AND course_id = ?
       AND user_id = ?",
    $memo_id,
    $course_id,
    $student_user_id
);


// ============================================================
// 8. 학생 학습현황으로 복귀
// ============================================================

header(
    "Location: course_student_view.php?course_id="
    .$course_id
    ."&user_id="
    .urlencode($student_user_id)
);

exit(0);