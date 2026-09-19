<?php

require_once(
    __DIR__ . '/admin-init.php'
);


function contest_archive_error(
    $status_code,
    $message
) {
    http_response_code(
        intval($status_code)
    );

    header(
        'Content-Type: text/plain; charset=utf-8'
    );

    echo (string)$message;
    exit;
}


if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');

    contest_archive_error(
        405,
        'POST 요청만 허용됩니다.'
    );
}


// ============================================================
// CSRF 확인
// ============================================================

$postkey_session_name =
    $OJ_NAME . '_postkey';

$session_postkey =
    isset($_SESSION[$postkey_session_name])
    ? (string)$_SESSION[$postkey_session_name]
    : '';

$request_postkey =
    isset($_POST['postkey']) &&
    is_scalar($_POST['postkey'])
    ? (string)$_POST['postkey']
    : '';

if (
    $session_postkey === '' ||
    $request_postkey === '' ||
    !hash_equals(
        $session_postkey,
        $request_postkey
    )
) {
    contest_archive_error(
        403,
        '요청 보안키가 올바르지 않습니다.'
    );
}


// ============================================================
// 요청값 확인
// ============================================================

$cid_raw =
    isset($_POST['cid']) &&
    is_scalar($_POST['cid'])
    ? trim((string)$_POST['cid'])
    : '';

if (
    preg_match(
        '/^[1-9][0-9]*$/D',
        $cid_raw
    ) !== 1 ||
    strlen($cid_raw) > 10 ||
    (
        strlen($cid_raw) === 10 &&
        strcmp(
            $cid_raw,
            '2147483647'
        ) > 0
    )
) {
    contest_archive_error(
        400,
        '잘못된 대회 번호입니다.'
    );
}

$cid =
    intval($cid_raw);

$action =
    isset($_POST['action']) &&
    is_scalar($_POST['action'])
    ? trim((string)$_POST['action'])
    : '';

if (
    !in_array(
        $action,
        array('archive', 'restore'),
        true
    )
) {
    contest_archive_error(
        400,
        '보관 요청이 올바르지 않습니다.'
    );
}


// ============================================================
// 대회 및 권한 확인
// ============================================================

$contest_rows =
    pdo_query(
        "SELECT
            contest_id,
            user_id,
            is_stopped,
            is_archived
         FROM contest
         WHERE contest_id=?
         LIMIT 1",
        $cid
    );

if ($contest_rows === false) {
    contest_archive_error(
        500,
        '대회 정보를 확인하지 못했습니다.'
    );
}

if (
    !is_array($contest_rows) ||
    count($contest_rows) === 0
) {
    contest_archive_error(
        404,
        '대회를 찾을 수 없습니다.'
    );
}

$contest =
    $contest_rows[0];

$current_user_id =
    isset($_SESSION[$OJ_NAME . '_user_id'])
    ? trim(
        (string)$_SESSION[$OJ_NAME . '_user_id']
    )
    : '';

$is_owner =
    isset($contest['user_id']) &&
    trim((string)$contest['user_id']) ===
        $current_user_id;

if (
    !oj_is_admin() &&
    !$is_owner
) {
    contest_archive_error(
        403,
        '대회를 보관하거나 복원할 권한이 없습니다.'
    );
}


// ============================================================
// 보관 또는 복원
// ============================================================

if ($action === 'archive') {
    if (
        intval($contest['is_archived']) !== 0
    ) {
        contest_archive_error(
            409,
            '이미 보관된 대회입니다.'
        );
    }

    if (
        intval($contest['is_stopped']) !== 1
    ) {
        contest_archive_error(
            409,
            '대회를 먼저 중지한 뒤 보관하세요.'
        );
    }

    $updated =
        pdo_query(
            "UPDATE contest
             SET is_archived=1,
                 archived_at=NOW(),
                 archived_by=?
             WHERE contest_id=?
               AND is_stopped=1
               AND is_archived=0",
            $current_user_id,
            $cid
        );

    $redirect_view =
        'archived';
} else {
    if (
        intval($contest['is_archived']) !== 1
    ) {
        contest_archive_error(
            409,
            '보관된 대회가 아닙니다.'
        );
    }

    $updated =
        pdo_query(
            "UPDATE contest
             SET is_archived=0,
                 archived_at=NULL,
                 archived_by=NULL
             WHERE contest_id=?
               AND is_archived=1",
            $cid
        );

    $redirect_view =
        'mine';
}

if (
    $updated === false ||
    intval($updated) !== 1
) {
    contest_archive_error(
        500,
        '대회 보관 상태를 변경하지 못했습니다.'
    );
}


header(
    'Location: contest_list.php?view=' .
        rawurlencode($redirect_view),
    true,
    303
);

exit;
