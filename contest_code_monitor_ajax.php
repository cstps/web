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

function code_monitor_json(
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

    code_monitor_json(
        false,
        '로그인이 필요합니다.',
        401
    );
}


// ============================================================
// 2. Contest 번호 확인
// ============================================================

$cid =
    isset($_GET['cid'])
        ? intval($_GET['cid'])
        : 0;


if ($cid <= 0) {

    code_monitor_json(
        false,
        '잘못된 대회 번호입니다.',
        400
    );
}


// ============================================================
// 3. 교사용 열람 권한 확인
// ============================================================

if (!oj_can_view_contest_process($cid)) {

    code_monitor_json(
        false,
        '학생 코드를 모니터링할 권한이 없습니다.',
        403
    );
}


// ============================================================
// 4. Contest 문제 목록 확인
// ============================================================

$problem_map =
    array();

$problem_rows =
    pdo_query(
        "SELECT
            problem_id
         FROM contest_problem
         WHERE contest_id = ?",
        $cid
    );


if ($problem_rows) {

    foreach ($problem_rows as $row) {

        $problem_id =
            intval($row['problem_id']);

        if ($problem_id > 0) {

            $problem_map[$problem_id] =
                true;
        }
    }
}


// ============================================================
// 5. 최신 draft 메타정보 조회
//
// 코드 본문(source)은 조회하지 않는다.
// ============================================================

$drafts =
    array();

$rows =
    pdo_query(
        "SELECT
            user_id,
            problem_id,
            language,
            updated_at
         FROM student_code_draft
         WHERE contest_id = ?
         ORDER BY user_id ASC,
                  problem_id ASC",
        $cid
    );


if ($rows) {

    foreach ($rows as $row) {

        $user_id =
            trim(
                (string)$row['user_id']
            );

        $problem_id =
            intval(
                $row['problem_id']
            );


        if (
            $user_id === '' ||
            !isset($problem_map[$problem_id])
        ) {
            continue;
        }


        $drafts[] =
            array(
                'user_id' =>
                    $user_id,

                'problem_id' =>
                    $problem_id,

                'language' =>
                    intval($row['language']),

                'updated_at' =>
                    (string)$row['updated_at']
            );
    }
}


// ============================================================
// 6. 결과
// ============================================================

code_monitor_json(
    true,
    '',
    200,
    array(
        'contest_id' => $cid,
        'server_time' => date('Y-m-d H:i:s'),
        'drafts' => $drafts
    )
);
