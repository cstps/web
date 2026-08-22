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


// ============================================================
// 2. POST 요청 확인
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors = "<h2>잘못된 요청입니다.</h2>";

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

$contest_id =
    isset($_POST['contest_id'])
        ? intval($_POST['contest_id'])
        : 0;

$selected_problem_ids =
    isset($_POST['problem_ids']) &&
    is_array($_POST['problem_ids'])
        ? $_POST['problem_ids']
        : array();

$score_input =
    isset($_POST['score']) &&
    is_array($_POST['score'])
        ? $_POST['score']
        : array();


// ============================================================
// 4. 기본값 검증
// ============================================================

if (
    $course_id <= 0 ||
    $contest_id <= 0
) {

    $view_errors =
        "<h2>잘못된 차시 정보입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (empty($selected_problem_ids)) {

    $view_errors =
        "<h2>최소 한 개 이상의 문제를 선택해야 합니다.</h2>";

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


if (intval($course_rows[0]['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업의 문제 구성은 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. Course 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 문제 구성을 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. Course ↔ Contest 연결 확인
// ============================================================

$link_rows = pdo_query(
    "SELECT
        cc.id,
        cc.source_contest_id,
        cc.link_type,
        cc.status

     FROM course_contest cc

     INNER JOIN contest c
       ON c.contest_id = cc.contest_id

     WHERE cc.course_id = ?
       AND cc.contest_id = ?

     LIMIT 1",
    $course_id,
    $contest_id
);

if (
    !$link_rows ||
    !isset($link_rows[0]['id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않았거나 실제 대회가 존재하지 않는 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 차시 활성 상태 확인
// ============================================================

if (intval($link_rows[0]['status']) !== 1) {

    $view_errors =
        "<h2>제거된 차시의 문제 구성은 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. 연결 유형 확인
//
// created:
// Course에서 생성한 Contest이므로 문제 구성 수정 가능
//
// linked:
// 기존 Contest 원본이므로 문제 구성 수정 금지
// ============================================================

$link_type =
    isset($link_rows[0]['link_type'])
        ? $link_rows[0]['link_type']
        : '';


if (
    !in_array(
        $link_type,
        array('created', 'linked'),
        true
    )
) {

    $view_errors =
        "<h2>차시 연결 유형이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if ($link_type !== 'created') {

    $view_errors =
        "<h2>기존 대회로 연결된 차시는 문제 구성을 변경할 수 없습니다.</h2>".
        "<p>원본 대회의 문제 구성과 제출 기록은 그대로 유지됩니다.</p>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$source_contest_id =
    isset($link_rows[0]['source_contest_id'])
        ? intval($link_rows[0]['source_contest_id'])
        : 0;


// ============================================================
// 8. 선택 문제 ID 정리
// ============================================================

$selected_map = array();

foreach ($selected_problem_ids as $problem_id) {

    $problem_id = intval($problem_id);

    if ($problem_id > 0) {
        $selected_map[$problem_id] = true;
    }
}


if (empty($selected_map)) {

    $view_errors =
        "<h2>유효한 문제가 선택되지 않았습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. 현재 Contest 문제 목록
// ============================================================

$current_rows = pdo_query(
    "SELECT
        problem_id,
        num,
        score
     FROM contest_problem
     WHERE contest_id = ?
     ORDER BY
        num,
        problem_id",
    $contest_id
);


if (!is_array($current_rows)) {
    $current_rows = array();
}


// ============================================================
// 10. 원본 Contest 문제 목록
//
// 현재 문제 + 원본 문제만 허용
// 임의의 다른 problem_id를 POST로 추가하는 것을 막는다.
// ============================================================

$allowed_problem_map = array();


// 현재 등록된 문제
foreach ($current_rows as $row) {

    $problem_id =
        intval($row['problem_id']);

    $allowed_problem_map[$problem_id] = true;
}


// 원본 Contest의 문제
if ($source_contest_id > 0) {

    $source_rows = pdo_query(
        "SELECT problem_id
         FROM contest_problem
         WHERE contest_id = ?",
        $source_contest_id
    );


    if (is_array($source_rows)) {

        foreach ($source_rows as $row) {

            $problem_id =
                intval($row['problem_id']);

            if ($problem_id > 0) {
                $allowed_problem_map[$problem_id] = true;
            }
        }
    }
}


// ============================================================
// 11. 선택 문제 검증
// ============================================================

foreach ($selected_map as $problem_id => $dummy) {

    if (!isset($allowed_problem_map[$problem_id])) {

        $view_errors =
            "<h2>허용되지 않은 문제가 포함되어 있습니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }
}


// ============================================================
// 12. 최종 문제 순서 생성
//
// 1) 현재 Contest에 이미 있던 문제 순서를 우선 유지
// 2) 새로 추가한 문제는 뒤에 붙임
// ============================================================

$final_problem_ids = array();


// 기존 문제 중 계속 사용할 문제
foreach ($current_rows as $row) {

    $problem_id =
        intval($row['problem_id']);

    if (isset($selected_map[$problem_id])) {

        $final_problem_ids[] =
            $problem_id;
    }
}


// 새로 추가된 문제
foreach ($selected_map as $problem_id => $dummy) {

    if (!in_array(
        $problem_id,
        $final_problem_ids,
        true
    )) {

        $final_problem_ids[] =
            $problem_id;
    }
}


if (empty($final_problem_ids)) {

    $view_errors =
        "<h2>최소 한 개 이상의 문제가 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 13. 각 문제 실제 존재 확인
// ============================================================

foreach ($final_problem_ids as $problem_id) {

    $problem_rows = pdo_query(
        "SELECT problem_id
         FROM problem
         WHERE problem_id = ?
         LIMIT 1",
        $problem_id
    );


    if (
        !$problem_rows ||
        !isset($problem_rows[0]['problem_id'])
    ) {

        $view_errors =
            "<h2>존재하지 않는 문제가 포함되어 있습니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }
}


// ============================================================
// 14. 기존 solution.num 임시 초기화
//
// 제출 자체는 삭제하지 않는다.
// ============================================================

pdo_query(
    "UPDATE solution
     SET num = -1
     WHERE contest_id = ?",
    $contest_id
);


// ============================================================
// 15. 기존 contest_problem 제거
// ============================================================

pdo_query(
    "DELETE FROM contest_problem
     WHERE contest_id = ?",
    $contest_id
);


// ============================================================
// 16. 새 문제 구성 저장
// ============================================================

$new_num = 0;


foreach ($final_problem_ids as $problem_id) {

    $score = 100;


    if (
        isset($score_input[$problem_id]) &&
        $score_input[$problem_id] !== '' &&
        is_numeric($score_input[$problem_id])
    ) {

        $score =
            intval($score_input[$problem_id]);
    }


    if ($score < 0) {
        $score = 0;
    }


    pdo_query(
        "INSERT INTO contest_problem
        (
            contest_id,
            problem_id,
            num,
            score
        )
        VALUES (?, ?, ?, ?)",
        $contest_id,
        $problem_id,
        $new_num,
        $score
    );


    // --------------------------------------------------------
    // 기존 제출의 문제 순서 갱신
    // --------------------------------------------------------

    pdo_query(
        "UPDATE solution
         SET num = ?
         WHERE contest_id = ?
           AND problem_id = ?",
        $new_num,
        $contest_id,
        $problem_id
    );


    // --------------------------------------------------------
    // 기존 제출 수 / 정답 수 반영
    // --------------------------------------------------------

    pdo_query(
        "UPDATE contest_problem
         SET c_accepted = (
                SELECT COUNT(*)
                FROM solution
                WHERE contest_id = ?
                  AND problem_id = ?
                  AND result = 4
             ),
             c_submit = (
                SELECT COUNT(*)
                FROM solution
                WHERE contest_id = ?
                  AND problem_id = ?
             )
         WHERE contest_id = ?
           AND problem_id = ?",
        $contest_id,
        $problem_id,
        $contest_id,
        $problem_id,
        $contest_id,
        $problem_id
    );


    $new_num++;
}


// ============================================================
// 17. Course 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);