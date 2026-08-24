<?php

require_once("../include/db_info.inc.php");
require_once("../include/const.inc.php");
require_once("../include/setlang.php");



// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    echo "Please Login First!";
    exit(1);
}


// ============================================================
// 2. POST 요청만 허용
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo "Invalid Request!";
    exit(1);
}

require_once("../include/check_post_key.php");

// ============================================================
// 3. contest_id 확인
// ============================================================

$cid =
    isset($_POST['cid'])
        ? intval($_POST['cid'])
        : 0;


if ($cid <= 0) {

    echo "No Such Contest!";
    exit(1);
}


// ============================================================
// 4. Contest 존재 확인
// ============================================================

$contest_rows = pdo_query(
    "SELECT
        contest_id,
        title,
        user_id,
        defunct
     FROM contest
     WHERE contest_id = ?
     LIMIT 1",
    $cid
);


if (
    !$contest_rows ||
    !isset($contest_rows[0]['contest_id'])
) {

    echo "No Such Contest!";
    exit(1);
}


$contest =
    $contest_rows[0];


$current_user_id =
    $_SESSION[$OJ_NAME.'_user_id'];

$is_admin =
    isset($_SESSION[$OJ_NAME.'_administrator']);

$is_owner =
    isset($contest['user_id']) &&
    trim($contest['user_id']) ===
    $current_user_id;


// ============================================================
// 5. 완전 삭제 권한
//
// 수정 권한(m{cid})과 삭제 권한은 다르게 본다.
//
// - 관리자
// - 실제 Contest 생성자
//
// 만 완전 삭제 가능
// ============================================================

if (
    !$is_admin &&
    !$is_owner
) {

    echo "이 대회를 삭제할 권한이 없습니다.";
    exit(1);
}


// ============================================================
// 6. 제출 기록 존재 확인
//
// 제출이 하나라도 있으면 학습/평가 기록이므로
// 완전 삭제하지 않는다.
// ============================================================

$solution_rows = pdo_query(
    "SELECT COUNT(*) AS cnt
     FROM solution
     WHERE contest_id = ?",
    $cid
);


$solution_count =
    isset($solution_rows[0]['cnt'])
        ? intval($solution_rows[0]['cnt'])
        : 0;


if ($solution_count > 0) {

    echo
        "제출 기록이 존재하는 대회는 완전 삭제할 수 없습니다. ".
        "대회를 사용 중지 상태로 변경하세요.";

    exit(1);
}


// ============================================================
// 7. Course 연결 여부 확인
//
// status=0으로 제거된 Course 차시도 관계 기록이 남아 있으므로
// 삭제를 허용하지 않는다.
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        lesson_no,
        status
     FROM course_contest
     WHERE contest_id = ?
     LIMIT 1",
    $cid
);


if (
    $course_rows &&
    isset($course_rows[0]['course_id'])
) {

    echo
        "수업 차시와 연결된 대회는 완전 삭제할 수 없습니다. ".
        "Course 관계를 먼저 확인하세요.";

    exit(1);
}


// ============================================================
// 8. 관련 데이터 삭제
//
// solution은 0건임을 위에서 확인했다.
//
// 삭제 순서:
// contest_problem
// privilege
// ranking_cache (존재하는 경우)
// contest
// ============================================================


// ------------------------------------------------------------
// 8-1. Contest 문제 구성
// ------------------------------------------------------------

pdo_query(
    "DELETE FROM contest_problem
     WHERE contest_id = ?",
    $cid
);


// ------------------------------------------------------------
// 8-2. Contest 참가 / 관리 권한
//
// c{cid}
// m{cid}
// ------------------------------------------------------------

pdo_query(
    "DELETE FROM privilege
     WHERE rightstr = ?
        OR rightstr = ?",
    "c".$cid,
    "m".$cid
);


// ------------------------------------------------------------
// 8-3. ranking_cache
//
// 현재 1024.kr에 ranking_cache가 존재하는 경우 정리
// ------------------------------------------------------------

$ranking_table_rows = pdo_query(
    "SELECT COUNT(*) AS cnt
     FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = 'ranking_cache'"
);


$ranking_table_exists =
    isset($ranking_table_rows[0]['cnt']) &&
    intval($ranking_table_rows[0]['cnt']) > 0;


if ($ranking_table_exists) {

    pdo_query(
        "DELETE FROM ranking_cache
         WHERE contest_id = ?",
        $cid
    );
}


// ------------------------------------------------------------
// 8-4. Contest 본체
// ------------------------------------------------------------

pdo_query(
    "DELETE FROM contest
     WHERE contest_id = ?",
    $cid
);


// ============================================================
// 9. 세션 m{cid} 정리
// ============================================================

$session_key =
    $OJ_NAME.'_m'.$cid;


if (isset($_SESSION[$session_key])) {
    unset($_SESSION[$session_key]);
}


// ============================================================
// 10. 목록으로 복귀
// ============================================================

header(
    "Location: contest_list.php"
);

exit(0);