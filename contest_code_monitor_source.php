<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/permission_functions.inc.php');
require_once('./include/course_functions.inc.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');


// ============================================================
// JSON 응답
// ============================================================

function code_monitor_source_json(
    $ok,
    $message,
    $status_code = 200,
    $extra = array()
) {

    http_response_code($status_code);

    echo json_encode(
        array_merge(
            array(
                'ok' => (bool)$ok,
                'message' => (string)$message
            ),
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    code_monitor_source_json(
        false,
        '로그인이 필요합니다.',
        401
    );
}


// ============================================================
// 2. 요청값 확인
// ============================================================

$cid =
    isset($_GET['cid'])
        ? intval($_GET['cid'])
        : 0;

$user_id =
    isset($_GET['user_id'])
        ? trim((string)$_GET['user_id'])
        : '';

$problem_id =
    isset($_GET['problem_id'])
        ? intval($_GET['problem_id'])
        : 0;


if (
    $cid <= 0 ||
    $user_id === '' ||
    $problem_id <= 0
) {

    code_monitor_source_json(
        false,
        '요청 정보가 올바르지 않습니다.',
        400
    );
}


// ============================================================
// 3. 교사용 권한 확인
// ============================================================

if (!oj_can_view_contest_process($cid)) {

    code_monitor_source_json(
        false,
        '학생 코드를 볼 권한이 없습니다.',
        403
    );
}


// ============================================================
// 4. 해당 문제가 Contest 문제인지 확인
// ============================================================

$problem_rows =
    pdo_query(
        "SELECT
            problem_id
         FROM contest_problem
         WHERE contest_id = ?
           AND problem_id = ?
         LIMIT 1",
        $cid,
        $problem_id
    );


if (
    !$problem_rows ||
    !isset($problem_rows[0]['problem_id'])
) {

    code_monitor_source_json(
        false,
        '대회와 문제 정보가 일치하지 않습니다.',
        400
    );
}


// ============================================================
// 5. 최신 draft 조회
// ============================================================

$draft_rows =
    pdo_query(
        "SELECT
            language,
            source,
            updated_at
         FROM student_code_draft
         WHERE user_id = ?
           AND problem_id = ?
           AND contest_id = ?
         LIMIT 1",
        $user_id,
        $problem_id,
        $cid
    );


if (
    !$draft_rows ||
    !isset($draft_rows[0]['source'])
) {

    code_monitor_source_json(
        false,
        '현재 저장된 코드가 없습니다.',
        404
    );
}


$row =
    $draft_rows[0];


// ============================================================
// 6. 결과
// ============================================================

code_monitor_source_json(
    true,
    '',
    200,
    array(
        'user_id' => $user_id,
        'problem_id' => $problem_id,
        'language' => intval($row['language']),

        'language_name' =>
            isset(
                $language_name[
                    intval($row['language'])
                ]
            )
                ? (string)$language_name[
                    intval($row['language'])
                ]
                : 'Language '.
                    intval($row['language']),

        'source' => (string)$row['source'],
        'updated_at' => (string)$row['updated_at']
    )
);
