<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "대회 찾기";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors =
        "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$user_id =
    $_SESSION[$OJ_NAME.'_user_id'];

$is_admin =
    isset($_SESSION[$OJ_NAME.'_administrator']);

$is_contest_creator =
    isset($_SESSION[$OJ_NAME.'_contest_creator']);


// ============================================================
// 2. 입력값
// ============================================================

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;

$lesson_no =
    isset($_GET['lesson_no'])
        ? intval($_GET['lesson_no'])
        : 0;

$mode =
    isset($_GET['mode'])
        ? trim($_GET['mode'])
        : '';

$keyword =
    isset($_GET['keyword'])
        ? trim($_GET['keyword'])
        : '';


// ============================================================
// 3. 기본값 검증
// ============================================================

if ($course_id <= 0) {

    $view_errors =
        "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if ($lesson_no <= 0) {

    $view_errors =
        "<h2>차시 번호는 1 이상이어야 합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    !in_array(
        $mode,
        array('copy', 'link'),
        true
    )
) {

    $view_errors =
        "<h2>대회 선택 방식이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
        status,
        created_by

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


$view_course =
    $course_rows[0];


// ============================================================
// 5. Course 권한
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 차시를 관리할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 종료된 Course 차단
// ============================================================

if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 새로운 차시를 추가할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 차시 번호 중복 확인
//
// 제거된 차시도 복원 대상으로 남아 있으므로
// status 조건 없이 확인한다.
// ============================================================

$duplicate_rows = pdo_query(
    "SELECT contest_id
     FROM course_contest
     WHERE course_id = ?
       AND lesson_no = ?
     LIMIT 1",
    $course_id,
    $lesson_no
);


if (
    $duplicate_rows &&
    isset($duplicate_rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>".$lesson_no.
        "차시는 이미 등록되어 있습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 대회 검색 조건
//
// mode=copy
// - 관리자: 모든 활성 대회
// - 본인 대회: allow_copy와 관계없이 표시
// - 다른 사람 대회: allow_copy=1만 표시
//
// mode=link
// - 관리자: 모든 활성 대회
// - 일반 사용자: 자신이 생성한 대회만 표시
//
// 중요:
// 검색 화면은 편의를 위한 필터일 뿐.
// 실제 생성/연결 처리 파일에서도 반드시 다시 검증한다.
// ============================================================


$where = array();


$params = array();


// 기존 대회 자체를 연결할 때만
// 활성 Contest로 제한한다.
if ($mode === 'link') {

    $where[] =
        "c.defunct = 'N'";
}

if (!$is_admin) {

    if ($mode === 'copy') {

        // 기존 contest_add.php와 동일하게
        // contest_creator가 다른 사용자의 복사 허용 대회를
        // 사용할 수 있도록 한다.
        $where[] =
        "(
            c.user_id = ?
            OR c.allow_copy = 1
        )";

        $params[] =
            $user_id;

    }
    elseif ($mode === 'link') {

        // 기존 Contest 자체를 Course에 연결하는 것은
        // 복사보다 강한 행위이므로 생성자 본인만 허용
        $where[] =
            "c.user_id = ?";

        $params[] =
            $user_id;
    }
}


// ============================================================
// 9. 검색어
//
// 숫자만 입력:
// - contest_id 정확 일치
// - 제목에도 동일 문자열이 있다면 검색
//
// 문자:
// - 제목 검색
// ============================================================

if ($keyword !== '') {

    if (ctype_digit($keyword)) {

        $where[] =
            "(
                c.contest_id = ?
                OR c.title LIKE ?
            )";

        $params[] =
            intval($keyword);

        $params[] =
            '%'.$keyword.'%';

    }
    else {

        $where[] =
            "c.title LIKE ?";

        $params[] =
            '%'.$keyword.'%';
    }
}


$where_sql =
    implode(
        " AND ",
        $where
    );


// ============================================================
// 10. 검색 결과
//
// 최근 Contest부터 최대 100개
// ============================================================

$sql =
    "SELECT
        c.contest_id,
        c.title,
        c.user_id,
        c.start_time,
        c.end_time,
        c.private,
        c.allow_copy,

        (
            SELECT COUNT(*)
            FROM contest_problem cp
            WHERE cp.contest_id = c.contest_id
        ) AS problem_count,

        CASE
            WHEN c.start_time IS NULL
              OR c.end_time IS NULL
                THEN 'unscheduled'

            WHEN NOW() < c.start_time
                THEN 'upcoming'

            WHEN NOW() >= c.end_time
                THEN 'ended'

            ELSE 'running'
        END AS contest_state,

        (
            SELECT COUNT(*)
            FROM course_contest cc
            WHERE cc.contest_id = c.contest_id
        ) AS course_link_count

     FROM contest c

     WHERE ".$where_sql."

     ORDER BY
        c.contest_id DESC

     LIMIT 100";


$view_contests =
    pdo_query(
        $sql,
        ...$params
    );


if (!is_array($view_contests)) {
    $view_contests = array();
}


// ============================================================
// 11. 화면 표시용 제목
// ============================================================

if ($mode === 'copy') {

    $view_mode_title =
        "대회에서 문제 가져오기";

    $view_mode_description =
        "기존 대회의 문제 구성을 선택하여 ".
        "새로운 Course 전용 차시를 만듭니다.";

}
else {

    $view_mode_title =
        "기존 대회 연결";

    $view_mode_description =
        "기존 대회와 기존 제출 기록을 ".
        "그대로 Course 차시에 연결합니다.";
}


// ============================================================
// 12. 화면 출력
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/course_contest_select.php"
);