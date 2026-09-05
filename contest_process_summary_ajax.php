<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/permission_functions.inc.php');
require_once('./include/course_functions.inc.php');

// ============================================================
// 1. 로그인 확인
// ============================================================

if (
    !isset(
        $_SESSION[
            $OJ_NAME.'_user_id'
        ]
    )
) {

    http_response_code(403);
    exit;
}


// ============================================================
// 2. cid 확인
// ============================================================

$cid =
    isset($_GET['cid'])
        ? intval($_GET['cid'])
        : 0;


if ($cid <= 0) {

    http_response_code(400);
    exit;
}


// ============================================================
// 3. 권한 확인
//
// administrator
// 또는 해당 대회의 m{cid}
// ============================================================


if (
    !oj_can_view_contest_process(
        $cid
    )
) {

    http_response_code(403);
    exit;
}

// ============================================================
// 4. 대회 존재 확인
// ============================================================

$contest_result =
    pdo_query(
        "SELECT contest_id
         FROM contest
         WHERE contest_id=?
         LIMIT 1",
        $cid
    );


if (
    !$contest_result ||
    count($contest_result) == 0
) {

    http_response_code(404);
    exit;
}


// ============================================================
// 공통 데이터 생성
// ============================================================

require(
    "./include/contest_process_data.inc.php"
);


// ============================================================
// AJAX 상단 요약 템플릿
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/contest_process_summary_ajax.php"
);

?>