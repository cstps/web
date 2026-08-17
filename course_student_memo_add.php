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

$created_by =
    $_SESSION[$OJ_NAME.'_user_id'];


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

$memo_text =
    isset($_POST['memo_text'])
        ? trim($_POST['memo_text'])
        : '';

$contest_id = null;

if (
    isset($_POST['contest_id']) &&
    trim($_POST['contest_id']) !== ''
) {

    $contest_id =
        intval($_POST['contest_id']);
}


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
    $student_user_id === '' ||
    strlen($student_user_id) > 48
) {

    $view_errors =
        "<h2>잘못된 학생 정보입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if ($memo_text === '') {

    $view_errors =
        "<h2>메모 내용을 입력하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    mb_strlen($memo_text, 'UTF-8') > 5000
) {

    $view_errors =
        "<h2>메모는 5000자 이내로 입력하세요.</h2>";

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
// 6. Course 접근 권한 확인
// ============================================================

if (!course_can_access($course_id)) {

    $view_errors =
        "<h2>이 수업의 학생 메모를 작성할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 학생이 실제 Course 소속인지 확인
// ============================================================

$student_rows = pdo_query(
    "SELECT
        user_id,
        status
     FROM course_student
     WHERE course_id = ?
       AND user_id = ?
     LIMIT 1",
    $course_id,
    $student_user_id
);


if (
    !$student_rows ||
    !isset($student_rows[0]['user_id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 학생입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 특정 차시 메모인 경우 Contest 검증
// ============================================================

if ($contest_id !== null) {

    if ($contest_id <= 0) {

        $view_errors =
            "<h2>잘못된 차시 정보입니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }


    $contest_rows = pdo_query(
        "SELECT
            contest_id
         FROM course_contest
         WHERE course_id = ?
           AND contest_id = ?
           AND status = 1
         LIMIT 1",
        $course_id,
        $contest_id
    );


    if (
        !$contest_rows ||
        !isset($contest_rows[0]['contest_id'])
    ) {

        $view_errors =
            "<h2>이 수업에 등록되지 않은 차시입니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }
}


// ============================================================
// 9. 메모 저장
// ============================================================

pdo_query(
    "INSERT INTO course_student_memo
    (
        course_id,
        user_id,
        contest_id,
        memo_text,
        created_by
    )
    VALUES (?, ?, ?, ?, ?)",
    $course_id,
    $student_user_id,
    $contest_id,
    $memo_text,
    $created_by
);


// ============================================================
// 10. 학생 학습현황으로 복귀
// ============================================================

header(
    "Location: course_student_view.php?course_id="
    .$course_id
    ."&user_id="
    .urlencode($student_user_id)
);

exit(0);