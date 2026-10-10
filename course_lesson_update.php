<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "차시 수정";


// ============================================================
// 1. 로그인 및 POST 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors = "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. 입력값 검증
// ============================================================

$course_id = isset($_POST['course_id'])
    ? intval($_POST['course_id'])
    : 0;

$lesson_id = isset($_POST['lesson_id'])
    ? intval($_POST['lesson_id'])
    : 0;

$lesson_no_raw = isset($_POST['lesson_no']) &&
                 is_string($_POST['lesson_no'])
    ? trim($_POST['lesson_no'])
    : '';

$title = isset($_POST['title']) &&
         is_string($_POST['title'])
    ? trim($_POST['title'])
    : '';

$description = isset($_POST['description']) &&
               is_string($_POST['description'])
    ? trim($_POST['description'])
    : '';

$visible_raw = isset($_POST['visible']) &&
               is_string($_POST['visible'])
    ? $_POST['visible']
    : '';


if (
    $course_id <= 0 ||
    $lesson_id <= 0 ||
    !preg_match('/^[1-9][0-9]*$/D', $lesson_no_raw) ||
    strlen($lesson_no_raw) > 10 ||
    $title === '' ||
    strlen($title) > 255 ||
    !in_array($visible_raw, array('0', '1'), true)
) {

    $view_errors =
        "<h2>차시 수정 입력값이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$lesson_no = intval($lesson_no_raw);
$visible = intval($visible_raw);

if ($lesson_no > 2147483647) {

    $view_errors = "<h2>차시 번호가 허용 범위를 초과했습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Lesson 조회 및 소속 확인
// ============================================================

$lesson = course_get_lesson($lesson_id);

if (
    !$lesson ||
    intval($lesson['course_id']) !== $course_id
) {

    $view_errors =
        "<h2>해당 수업의 차시를 찾을 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course 및 권한 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT course_id, status
     FROM course
     WHERE course_id = ?
     LIMIT 1",
    $course_id
);

if (!$course_rows || !isset($course_rows[0])) {

    $view_errors = "<h2>존재하지 않는 수업입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>차시를 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if (
    intval($course_rows[0]['status']) !== 1 ||
    intval($lesson['status']) !== 1
) {

    $view_errors =
        "<h2>비활성 상태의 수업 또는 차시는 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 차시 번호 변경 검증
// ============================================================

$old_lesson_no = intval($lesson['lesson_no']);

if ($lesson_no !== $old_lesson_no) {

    // 연결된 Contest가 있으면 번호 변경을 허용하지 않는다.
    $linked_rows = pdo_query(
        "SELECT 1
         FROM course_contest
         WHERE course_id = ?
           AND lesson_id = ?
         LIMIT 1",
        $course_id,
        $lesson_id
    );

    if (!empty($linked_rows)) {

        $view_errors =
            "<h2>Contest가 연결된 차시는 번호를 변경할 수 없습니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    // 다른 Lesson 또는 기존 Contest의 번호와 중복 방지
    $duplicate_rows = pdo_query(
        "SELECT 1
         FROM course_lesson
         WHERE course_id = ?
           AND lesson_no = ?
           AND lesson_id <> ?
         LIMIT 1",
        $course_id,
        $lesson_no,
        $lesson_id
    );

    $duplicate_contests = pdo_query(
        "SELECT 1
         FROM course_contest
         WHERE course_id = ?
           AND lesson_no = ?
         LIMIT 1",
        $course_id,
        $lesson_no
    );

    if (
        !empty($duplicate_rows) ||
        !empty($duplicate_contests)
    ) {

        $view_errors =
            "<h2>이미 사용 중인 차시 번호입니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }
}


// ============================================================
// 6. Lesson 정보 수정
//
// Contest 및 Activity는 변경하지 않는다.
// ============================================================

$result = pdo_query(
    "UPDATE course_lesson
     SET
         lesson_no = ?,
         title = ?,
         description = ?,
         visible = ?
     WHERE lesson_id = ?
       AND course_id = ?
       AND status = 1",
    $lesson_no,
    $title,
    $description,
    $visible,
    $lesson_id,
    $course_id
);

// HUSTOJ pdo_query()의 UPDATE 반환값은 구현에 따라
// 빈 배열일 수 있으므로 false만 실패로 판단한다.
if ($result === false) {

    $view_errors =
        "<h2>차시 수정에 실패했습니다. 서버 로그를 확인하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. Course 관리 화면으로 이동
// ============================================================

header(
    "Location: course_view.php?course_id=" . $course_id
);

exit(0);
