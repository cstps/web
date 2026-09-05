<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/permission_functions.inc.php');
require_once('./include/course_functions.inc.php');

$view_title = "학생 문제 해결 과정 현황";

// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. cid 확인
// ============================================================

if (!isset($_GET['cid']) || intval($_GET['cid']) <= 0) {

    $view_errors = "<h2>잘못된 대회 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$cid = intval($_GET['cid']);


// ============================================================
// 3. 권한
//
// 허용:
// - administrator
// - 해당 Contest의 m{cid}
// - 해당 Course의 활성 owner/teacher
//
// source_browser와 contest_creator만으로는
// 학생 문제 해결과정 현황을 열람할 수 없다.
// ============================================================
/*
 * Course의 활성 owner/teacher인지 DB에서 직접 확인한다.
 *
 * 담당교사 등록 직후 세션의 m{cid}가 갱신되지 않았거나,
 * linked Contest의 기존 소유권이 별도로 유지되는 경우에도
 * Course 역할을 기준으로 열람할 수 있다.
 */
$can_view_contest_process =
    oj_can_view_contest_process(
        $cid
    );

if (!$can_view_contest_process) {

    $view_errors =
        "<h2>이 대회의 학생 과정 현황을 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. 대회 정보
// ============================================================

$contest_sql = "SELECT title
                FROM contest
                WHERE contest_id=?
                LIMIT 1";

$contest_result =
    pdo_query(
        $contest_sql,
        $cid
    );


if (
    !$contest_result ||
    count($contest_result) == 0
) {

    $view_errors =
        "<h2>대회를 찾을 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}



$contest_title =
    isset($contest_result[0]['title'])
        ? $contest_result[0]['title']
        : $contest_result[0][0];

// ============================================================
// 5. 학생 과정 공통 데이터 생성
//
// 생성:
// $contest_problems
// $excluded_users
// $contest_students
// $view_process_list
// $student_matrix
// $teacher_note_count_map
// $attention_student_count
// $total_student_count
// $problem_class_summary
// ============================================================

require(
    "./include/contest_process_data.inc.php"
);


// ============================================================
// 6. Template
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/contest_process.php"
);

?>