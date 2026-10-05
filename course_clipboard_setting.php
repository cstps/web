<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "코드 입력 보안 설정";


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
// 3. 입력값 확인
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;

$block_code_clipboard =
    isset($_POST['block_code_clipboard'])
        ? intval($_POST['block_code_clipboard'])
        : 0;



$return_to =
    isset($_POST['return_to'])
        ? trim((string)$_POST['return_to'])
        : '';

$return_url =
    ($return_to === 'course_list')
        ? '/course_list.php'
        : '/course_view.php?course_id='.$course_id;

// ============================================================
// 4. 기본값 검증
// ============================================================

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

if (
    !in_array(
        $block_code_clipboard,
        array(0, 1),
        true
    )
) {
    $view_errors =
        "<h2>코드 입력 보안 설정값이 올바르지 않습니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 5. Course 존재 확인
// ============================================================

$course_rows =
    pdo_query(
        "SELECT
            course_id,
            block_code_clipboard
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
// 6. Course 수정 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_performance($course_id)
) {
    $view_errors =
        "<h2>이 수업의 코드 입력 보안 설정을 변경할 권한이 없습니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 7. 현재 값과 같으면 바로 복귀
// ============================================================

$current_value =
    intval(
        $course_rows[0][
            'block_code_clipboard'
        ]
    );

if ($current_value === $block_code_clipboard) {
    header("Location: ".$return_url);

    exit(0);
}


// ============================================================
// 8. 설정 저장
// ============================================================

pdo_query(
    "UPDATE course
     SET
        block_code_clipboard = ?,
        updated_at = CURRENT_TIMESTAMP
     WHERE course_id = ?",
    $block_code_clipboard,
    $course_id
);


// ============================================================
// 9. Course 화면으로 복귀
// ============================================================

header("Location: ".$return_url);

exit(0);
