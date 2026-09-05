<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');


// ============================================================
// 기본 설정
// ============================================================

$view_title = "세특 초안 저장";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {

    $view_errors =
        "<h2>로그인이 필요합니다.</h2>";

    require(
        "template/" .
        $OJ_TEMPLATE .
        "/error.php"
    );

    exit(0);
}


$current_user_id =
    $_SESSION[$OJ_NAME . '_user_id'];


// ============================================================
// 2. POST 요청 확인
// ============================================================

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    $view_errors =
        "<h2>잘못된 요청입니다.</h2>";

    require(
        "template/" .
        $OJ_TEMPLATE .
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 3. 입력값 확인
// ============================================================

// ------------------------------------------------------------
// course_id
// ------------------------------------------------------------

$course_id = 0;

if (
    isset($_POST['course_id']) &&
    is_scalar($_POST['course_id'])
) {

    $course_id =
        intval($_POST['course_id']);
}


// ------------------------------------------------------------
// 학생 user_id
// ------------------------------------------------------------

$student_user_id = '';

if (
    isset($_POST['user_id']) &&
    is_string($_POST['user_id'])
) {

    $student_user_id =
        trim($_POST['user_id']);
}


// ------------------------------------------------------------
// 세특 초안
// ------------------------------------------------------------

$draft_text = '';

if (
    isset($_POST['draft_text']) &&
    is_string($_POST['draft_text'])
) {

    $draft_text =
        $_POST['draft_text'];
}


// ============================================================
// 4. 기본값 검증
// ============================================================

if ($course_id <= 0) {

    $view_errors =
        "<h2>잘못된 수업 정보입니다.</h2>";

    require(
        "template/" .
        $OJ_TEMPLATE .
        "/error.php"
    );

    exit(0);
}


if (
    $student_user_id === '' ||
    strlen($student_user_id) > 48
) {

    $view_errors =
        "<h2>잘못된 학생 정보입니다.</h2>";

    require(
        "template/" .
        $OJ_TEMPLATE .
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 5. 초안 내용 정리
//
// 저장 시:
// - CRLF / CR → LF
// - 내용 자체는 HTML 변환하지 않음
//
// 화면 출력 시 htmlspecialchars() 처리한다.
// ============================================================

$draft_text =
    str_replace(
        array("\r\n", "\r"),
        "\n",
        $draft_text
    );


// ============================================================
// 6. 초안 길이 확인
//
// 화면 maxlength=5000만 믿지 않고
// 서버에서도 다시 검사한다.
// ============================================================

$draft_length =
    function_exists('mb_strlen')
    ? mb_strlen(
        $draft_text,
        'UTF-8'
    )
    : strlen($draft_text);


if ($draft_length > 5000) {

    $view_errors =
        "<h2>세특 초안은 5000자 이하로 작성해야 합니다.</h2>";

    require(
        "template/" .
        $OJ_TEMPLATE .
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 7. Course 존재 확인
// ============================================================

$course_rows =
    pdo_query(
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

    require(
        "template/" .
        $OJ_TEMPLATE .
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 8. Course 접근 권한 확인
//
// administrator
// owner
// teacher
//
// assistant는 조회만 가능하며
// 세특 초안 작성·변경은 허용하지 않는다.
// 학생 학습현황 페이지와 동일한 접근 기준을 사용한다.
// ============================================================

if (!course_can_manage_student_records($course_id)) {

    $view_errors =
        "<h2>이 학생의 세특 초안을 작성할 권한이 없습니다.</h2>";

    require(
        "template/" .
        $OJ_TEMPLATE .
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 9. 해당 학생이 실제 이 Course 소속인지 확인
//
// 중요:
// user_id만 검사하지 않는다.
// 반드시 course_id + user_id를 동시에 검사한다.
//
// 수강 종료(status=0) 학생도 과거 학습기록을 바탕으로
// 세특 작성이 필요할 수 있으므로 status=1 조건은 넣지 않는다.
// ============================================================

$student_rows =
    pdo_query(
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

    require(
        "template/" .
        $OJ_TEMPLATE .
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 10. 세특 초안 저장
//
// course_id + user_id UNIQUE KEY를 기준으로
//
// 최초 저장:
// INSERT
//
// 기존 초안 존재:
// UPDATE
//
// 형태로 처리한다.
// ============================================================

pdo_query(
    "INSERT INTO course_student_record_draft
    (
        course_id,
        user_id,
        draft_text,
        updated_by
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?
    )

    ON DUPLICATE KEY UPDATE

        draft_text =
            VALUES(draft_text),

        updated_by =
            VALUES(updated_by),

        updated_at =
            CURRENT_TIMESTAMP",

    $course_id,
    $student_user_id,
    $draft_text,
    $current_user_id
);


// ============================================================
// 11. 학생 학습현황으로 복귀
//
// 세특 초안 textarea의 id가
// student-record-draft 이므로 저장 후 해당 위치로 이동한다.
// ============================================================

$redirect_url =
    "course_student_view.php" .
    "?course_id=" .
    intval($course_id) .
    "&user_id=" .
    urlencode($student_user_id) .
    "#student-record-draft";


header(
    "Location: " . $redirect_url
);

exit(0);
