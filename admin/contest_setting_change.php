<?php

require_once(
    __DIR__ . '/admin-init.php'
);


function contest_setting_response(
    $status_code,
    $payload
) {
    http_response_code(
        intval($status_code)
    );

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        $json =
            '{"ok":false,"message":"응답을 생성하지 못했습니다."}';
    }

    echo $json;
    exit;
}


function contest_setting_error(
    $status_code,
    $message
) {
    contest_setting_response(
        $status_code,
        array(
            'ok' => false,
            'message' => (string)$message
        )
    );
}


if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');

    contest_setting_error(
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
    contest_setting_error(
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
    contest_setting_error(
        400,
        '잘못된 대회 번호입니다.'
    );
}

$cid =
    intval($cid_raw);

$setting =
    isset($_POST['setting']) &&
    is_scalar($_POST['setting'])
    ? trim((string)$_POST['setting'])
    : '';

$setting_columns =
    array(
        'private' => 'private',
        'codevisible' => 'codevisible',
        'allow_copy' => 'allow_copy',
        'defunct' => 'defunct',
        'is_stopped' => 'is_stopped'
    );

if (
    !isset($setting_columns[$setting])
) {
    contest_setting_error(
        400,
        '변경할 대회 설정이 올바르지 않습니다.'
    );
}


// ============================================================
// 대회 및 권한 확인
// ============================================================

$contest_rows =
    pdo_query(
        "SELECT
            contest_id,
            private,
            codevisible,
            allow_copy,
            defunct,
            is_stopped,
            is_archived
         FROM contest
         WHERE contest_id=?
         LIMIT 1",
        $cid
    );

if ($contest_rows === false) {
    contest_setting_error(
        500,
        '대회 정보를 확인하지 못했습니다.'
    );
}

if (
    !is_array($contest_rows) ||
    count($contest_rows) === 0 ||
    !isset($contest_rows[0]['contest_id'])
) {
    contest_setting_error(
        404,
        '대회를 찾을 수 없습니다.'
    );
}

$contest =
    $contest_rows[0];

if (
    intval($contest['is_archived']) === 1
) {
    contest_setting_error(
        409,
        '보관된 대회는 설정을 변경할 수 없습니다. 먼저 복원하세요.'
    );
}

if (
    !oj_can_manage_contest($cid)
) {
    contest_setting_error(
        403,
        '이 대회의 설정을 변경할 권한이 없습니다.'
    );
}


// ============================================================
// 변경값 결정
// ============================================================

$column =
    $setting_columns[$setting];

$current_value =
    $contest[$column];

$next_value = null;
$label = '';
$badge_class = 'no';

switch ($setting) {
    case 'private':
        $next_value =
            intval($current_value) === 0
            ? 1
            : 0;

        $label =
            $next_value === 0
            ? '공개'
            : '비공개';

        $badge_class =
            $next_value === 0
            ? 'ok'
            : 'no';
        break;

    case 'codevisible':
        $next_value =
            intval($current_value) === 0
            ? 1
            : 0;

        $label =
            $next_value === 0
            ? '공개'
            : '비공개';

        $badge_class =
            $next_value === 0
            ? 'ok'
            : 'no';
        break;

    case 'allow_copy':
        $next_value =
            intval($current_value) === 1
            ? 0
            : 1;

        $label =
            $next_value === 1
            ? '허용'
            : '금지';

        $badge_class =
            $next_value === 1
            ? 'ok'
            : 'no';
        break;

    case 'defunct':
        $next_value =
            (string)$current_value === 'N'
            ? 'Y'
            : 'N';

        $label =
            $next_value === 'N'
            ? '표시'
            : '숨김';

        $badge_class =
            $next_value === 'N'
            ? 'ok'
            : 'no';
        break;

    case 'is_stopped':
        $next_value =
            intval($current_value) === 0
            ? 1
            : 0;

        $label =
            $next_value === 0
            ? '운영 중'
            : '중지됨';

        $badge_class =
            $next_value === 0
            ? 'ok'
            : 'no';
        break;
}


// ============================================================
// 설정 변경
//
// column은 위의 고정 allowlist에서만 선택된다.
// 현재값 조건을 함께 사용하여 중복 요청·동시 변경을 감지한다.
// ============================================================

$updated =
    pdo_query(
        "UPDATE contest
         SET `" . $column . "`=?
         WHERE contest_id=?
           AND `" . $column . "`=?
           AND is_archived=0",
        $next_value,
        $cid,
        $current_value
    );

if ($updated === false) {
    contest_setting_error(
        500,
        '대회 설정을 변경하지 못했습니다.'
    );
}

if (intval($updated) !== 1) {
    contest_setting_error(
        409,
        '대회 설정이 이미 변경되었습니다. 목록을 새로고침한 뒤 다시 시도하세요.'
    );
}


contest_setting_response(
    200,
    array(
        'ok' => true,
        'contest_id' => $cid,
        'setting' => $setting,
        'value' => $next_value,
        'label' => $label,
        'badge_class' => $badge_class
    )
);
