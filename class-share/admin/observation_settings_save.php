<?php

require_once(__DIR__ . '/include/admin_init.php');

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');
    http_response_code(405);
    exit('POST 요청만 허용됩니다.');
}

$admin = class_share_admin_require_login();
class_share_admin_require_post_csrf();

$event_id = isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

if ($event_id <= 0) {
    http_response_code(400);
    exit('행사 번호가 올바르지 않습니다.');
}

$rows = pdo_query(
    "
    SELECT id, school_id
    FROM class_share_event
    WHERE id = ?
    LIMIT 1
    ",
    $event_id
);

if ($rows === false) {
    http_response_code(500);
    exit('행사를 확인할 수 없습니다.');
}

if (!isset($rows[0])) {
    http_response_code(404);
    exit('행사를 찾을 수 없습니다.');
}

if (!class_share_admin_can_edit_school(
    (int)$rows[0]['school_id'],
    $admin
)) {
    http_response_code(403);
    exit('참관록 접수를 설정할 권한이 없습니다.');
}

$enabled_input = isset($_POST['enabled'])
    ? (string)$_POST['enabled']
    : '';

if (!in_array($enabled_input, array('0', '1'), true)) {
    http_response_code(400);
    exit('참관록 접수 상태를 선택해 주세요.');
}

$enabled = (int)$enabled_input;

$open_at = isset($_POST['open_at'])
    ? trim((string)$_POST['open_at'])
    : '';

$close_at = isset($_POST['close_at'])
    ? trim((string)$_POST['close_at'])
    : '';

$notice = isset($_POST['privacy_notice'])
    ? trim((string)$_POST['privacy_notice'])
    : '';

$retention = isset($_POST['retention_until'])
    ? trim((string)$_POST['retention_until'])
    : '';

$valid_datetime = function ($value) {
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $value
    );

    return $date !== false &&
        $date->format('Y-m-d\TH:i') === $value;
};

$valid_date = function ($value) {
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value
    );

    return $date !== false &&
        $date->format('Y-m-d') === $value;
};

if ($enabled === 1) {
    if (
        !$valid_datetime($open_at) ||
        !$valid_datetime($close_at) ||
        $open_at >= $close_at
    ) {
        http_response_code(400);
        exit('참관록 접수 시작·마감 시각을 확인해 주세요.');
    }

    if (
        $notice === '' ||
        strlen($notice) > 15000
    ) {
        http_response_code(400);
        exit('참관록 개인정보 안내를 확인해 주세요.');
    }

    if (
        !$valid_date($retention) ||
        $retention < substr($close_at, 0, 10)
    ) {
        http_response_code(400);
        exit('보관 기한은 접수 마감일 이후로 설정해 주세요.');
    }
}

$open_db = $open_at !== ''
    ? str_replace('T', ' ', $open_at) . ':00'
    : null;

$close_db = $close_at !== ''
    ? str_replace('T', ' ', $close_at) . ':00'
    : null;

$retention_db = $retention !== ''
    ? $retention
    : null;

$result = pdo_query(
    "
    INSERT INTO class_share_observation_setting (
        event_id,
        enabled,
        open_at,
        close_at,
        privacy_notice,
        retention_until
    ) VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        enabled = VALUES(enabled),
        open_at = VALUES(open_at),
        close_at = VALUES(close_at),
        privacy_notice = VALUES(privacy_notice),
        retention_until = VALUES(retention_until)
    ",
    $event_id,
    $enabled,
    $open_db,
    $close_db,
    $notice,
    $retention_db
);

if ($result === false) {
    http_response_code(500);
    exit('참관록 접수 설정을 저장하지 못했습니다.');
}

header(
    'Location: /class-share/admin/observation_settings.php?' .
    'event_id=' . $event_id . '&saved=1',
    true,
    303
);
exit;
