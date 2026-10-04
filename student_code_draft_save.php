<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/course_functions.inc.php');

header('Content-Type: application/json; charset=utf-8');


// ============================================================
// JSON 응답
// ============================================================

function draft_json_response(
    $ok,
    $message,
    $status_code = 200,
    $extra = array()
) {

    http_response_code($status_code);

    $response = array_merge(
        array(
            'ok' => (bool)$ok,
            'message' => (string)$message
        ),
        $extra
    );

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


// ============================================================
// 1. POST 요청만 허용
// ============================================================

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    draft_json_response(
        false,
        '잘못된 요청입니다.',
        405
    );
}


// ============================================================
// 2. 로그인 확인
// ============================================================

if (
    !isset(
        $_SESSION[
            $OJ_NAME.'_user_id'
        ]
    )
) {

    draft_json_response(
        false,
        '로그인이 필요합니다.',
        401
    );
}


$user_id =
    trim(
        (string)$_SESSION[
            $OJ_NAME.'_user_id'
        ]
    );


if (
    $user_id === '' ||
    $user_id === 'Guest'
) {

    draft_json_response(
        false,
        '로그인이 필요합니다.',
        401
    );
}


// ============================================================
// 3. 자동저장 전용 CSRF 확인
//
// 일반 제출용 csrf는 1회용이므로 자동저장에서 사용하지 않는다.
// 자동저장은 별도의 세션 토큰을 반복 사용한다.
// ============================================================

$draft_csrf =
    isset($_POST['draft_csrf'])
        ? (string)$_POST['draft_csrf']
        : '';

$session_draft_csrf =
    isset(
        $_SESSION[
            $OJ_NAME.'_draft_csrf'
        ]
    )
        ? (string)$_SESSION[
            $OJ_NAME.'_draft_csrf'
        ]
        : '';


if (
    $draft_csrf === '' ||
    $session_draft_csrf === '' ||
    !hash_equals(
        $session_draft_csrf,
        $draft_csrf
    )
) {

    draft_json_response(
        false,
        '자동저장 인증정보가 올바르지 않습니다.',
        403
    );
}


// ============================================================
// 4. 입력값 확인
// ============================================================

$problem_id =
    isset($_POST['problem_id'])
        ? intval($_POST['problem_id'])
        : 0;

$contest_id =
    isset($_POST['contest_id'])
        ? intval($_POST['contest_id'])
        : 0;

$language =
    isset($_POST['language'])
        ? intval($_POST['language'])
        : 0;

$source =
    isset($_POST['source'])
        ? (string)$_POST['source']
        : '';


if ($problem_id <= 0) {

    draft_json_response(
        false,
        '문제 번호가 올바르지 않습니다.',
        400
    );
}


if ($contest_id < 0) {

    draft_json_response(
        false,
        '대회 번호가 올바르지 않습니다.',
        400
    );
}


if ($language < 0) {

    draft_json_response(
        false,
        '언어 정보가 올바르지 않습니다.',
        400
    );
}


// 지나치게 큰 요청으로 인한 서버 부하를 방지한다.
if (strlen($source) > 1024 * 1024) {

    draft_json_response(
        false,
        '임시저장할 코드가 너무 큽니다.',
        413
    );
}


// ============================================================
// 4. 문제 존재 확인
// ============================================================

$problem_rows =
    pdo_query(
        "SELECT
            problem_id
         FROM problem
         WHERE problem_id = ?
         LIMIT 1",
        $problem_id
    );


if (
    !$problem_rows ||
    !isset(
        $problem_rows[0]['problem_id']
    )
) {

    draft_json_response(
        false,
        '존재하지 않는 문제입니다.',
        404
    );
}


// ============================================================
// 5. Contest 문제라면 연결 관계 확인
// ============================================================

if ($contest_id > 0) {

    $contest_problem_rows =
        pdo_query(
            "SELECT
                contest_id
             FROM contest_problem
             WHERE contest_id = ?
               AND problem_id = ?
             LIMIT 1",
            $contest_id,
            $problem_id
        );


    if (
        !$contest_problem_rows ||
        !isset(
            $contest_problem_rows[0]['contest_id']
        )
    ) {

        draft_json_response(
            false,
            '대회와 문제 정보가 일치하지 않습니다.',
            400
        );
    }
}


// ============================================================
// 6. 학생 코드 최신본 저장
//
// 같은 사용자 + 문제 + Contest 조합은
// 한 행만 유지하며 최신 코드로 갱신한다.
// ============================================================

$result =
    pdo_query(
        "INSERT INTO student_code_draft (
            user_id,
            problem_id,
            contest_id,
            language,
            source,
            created_at,
            updated_at
         )
         VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW(),
            NOW()
         )
         ON DUPLICATE KEY UPDATE
            language = VALUES(language),
            source = VALUES(source),
            updated_at = NOW()",
        $user_id,
        $problem_id,
        $contest_id,
        $language,
        $source
    );


// ============================================================
// 7. 저장 결과 반환
// ============================================================

draft_json_response(
    true,
    '자동저장되었습니다.',
    200,
    array(
        'problem_id' => $problem_id,
        'contest_id' => $contest_id,
        'saved_at' => date('Y-m-d H:i:s')
    )
);
