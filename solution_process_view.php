<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = "문제 해결 과정";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. sid 확인
// ============================================================

if (!isset($_GET['sid']) || intval($_GET['sid']) <= 0) {

    $view_errors = "<h2>잘못된 제출 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$sid = intval($_GET['sid']);


// ============================================================
// 3. 현재 사용자 / 공통 권한
// ============================================================

$current_user = isset($_SESSION[$OJ_NAME.'_'.'user_id'])
    ? trim((string)$_SESSION[$OJ_NAME.'_'.'user_id'])
    : "";

$is_admin =
    isset($_SESSION[$OJ_NAME.'_'.'administrator']);

$is_source_browser =
    isset($_SESSION[$OJ_NAME.'_'.'source_browser']);


// ============================================================
// 4. 기준 제출 조회
//
// URL로 전달받은 sid가 실제 제출인지 먼저 확인
// ============================================================

$sql = "SELECT
        solution_id,
        user_id,
        problem_id,
        contest_id,
        result,
        language,
        in_date
    FROM solution
    WHERE solution_id=?
    LIMIT 1";

$result = pdo_query($sql, $sid);



if (!$result || count($result) == 0) {

    $view_errors = "<h2>해당 제출을 찾을 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$solution = $result[0];
$solution_user_id = isset($solution['user_id'])
    ? trim((string)$solution['user_id'])
    : "";

$problem_id       = intval($solution['problem_id']);
$contest_id       = intval($solution['contest_id']);


// ============================================================
// 5. 본인 여부
// ============================================================

$is_owner = (
    $current_user !== "" &&
    $solution_user_id !== "" &&
    strcasecmp($current_user, $solution_user_id) === 0
);


// ============================================================
// 6. 해당 대회 관리자 여부
//
// m2976 같은 해당 대회 관리자만 인정
// contest_creator 자체는 과정 열람 권한이 아님
// ============================================================

$is_contest_manager = false;

if ($contest_id > 0) {

    $is_contest_manager =
        isset(
            $_SESSION[
                $OJ_NAME.'_m'.$contest_id
            ]
        );
}


// ============================================================
// 7. 사고과정 열람 권한
//
// 허용
// - 학생 본인
// - administrator
// - source_browser
// - 해당 대회 관리자 m{contest_id}
//
// 허용하지 않음
// - 일반 사용자
// - contest_creator 권한만 가진 사용자
// - 다른 대회의 관리자
// ============================================================

$can_view_process = (
    $is_owner ||
    $is_admin ||
    $is_source_browser ||
    $is_contest_manager
);

if (!$can_view_process) {

    $view_errors =
        "<h2>이 제출의 문제 해결 과정을 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 선택한 sid에 과정 기록이 실제 존재하는지 확인
// ============================================================

$check_sql = "SELECT id
    FROM solution_process
    WHERE solution_id=?
    LIMIT 1";

$check_result =
    pdo_query(
        $check_sql,
        $sid
    );


if (!$check_result || count($check_result) == 0) {

    $view_errors =
        "<h2>이 제출에는 기록된 문제 해결 과정이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. 같은 학생 + 같은 문제의 전체 과정 조회
//
// 매우 중요:
//
// 일반 문제와 대회 문제를 섞지 않는다.
//
// 일반:
// contest_id = 0 또는 NULL
//
// 대회:
// 동일 contest_id
// ============================================================

if ($contest_id > 0) {

    $process_sql = "SELECT
            sp.id,
            sp.solution_id,
            sp.user_id,
            sp.problem_id,
            sp.contest_id,

            sp.plan_text,
            sp.ai_used,
            sp.ai_usage_type,
            sp.ai_prompt,
            sp.reflection,

            sp.created_at,
            sp.updated_at,

            s.result,
            s.language,
            s.in_date,
            s.code_length,
            s.memory,
            s.time

        FROM solution_process sp

        INNER JOIN solution s
            ON sp.solution_id = s.solution_id

        WHERE sp.user_id=?
          AND sp.problem_id=?
          AND sp.contest_id=?

        ORDER BY s.solution_id ASC";

    $process_result =
        pdo_query(
            $process_sql,
            $solution_user_id,
            $problem_id,
            $contest_id
        );

}
else {

    $process_sql = "SELECT
            sp.id,
            sp.solution_id,
            sp.user_id,
            sp.problem_id,
            sp.contest_id,

            sp.plan_text,
            sp.ai_used,
            sp.ai_usage_type,
            sp.ai_prompt,
            sp.reflection,

            sp.created_at,
            sp.updated_at,

            s.result,
            s.language,
            s.in_date,
            s.code_length,
            s.memory,
            s.time

        FROM solution_process sp

        INNER JOIN solution s
            ON sp.solution_id = s.solution_id

        WHERE sp.user_id=?
          AND sp.problem_id=?
          AND (
                sp.contest_id=0
                OR sp.contest_id IS NULL
              )

        ORDER BY s.solution_id ASC";

    $process_result =
        pdo_query(
            $process_sql,
            $solution_user_id,
            $problem_id
        );
}


if (!$process_result) {
    $process_result = array();
}


// ============================================================
// 10. 화면용 기본 정보
// ============================================================

$process_count =
    count($process_result);


// ============================================================
// 11. AI 사용 유형 표시 이름
//
// 실제 DB에는 영문 코드 저장
// 화면에서는 학생/교사가 이해하기 쉽게 표시
// ============================================================

$ai_usage_names = array(

    'none' =>
        '사용하지 않음',

    'understand' =>
        '문제 이해',

    'idea' =>
        '풀이 아이디어',

    'hint' =>
        '힌트',

    'syntax' =>
        '문법 도움',

    'debug' =>
        '오류 수정',

    'generate' =>
        '코드 생성',

    'explain' =>
        '코드 설명',

    'other' =>
        '기타'
);


// ============================================================
// 12. Template
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/solution_process_view.php"
);

?>