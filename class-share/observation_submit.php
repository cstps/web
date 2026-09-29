<?php

require_once(
    dirname(__DIR__) .
    '/include/db_info.inc.php'
);

require_once(
    dirname(__DIR__) .
    '/include/pdo.php'
);

require_once(
    __DIR__ .
    '/include/public_functions.php'
);

require_once(
    __DIR__ .
    '/include/application_functions.php'
);

header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: same-origin');

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');
    http_response_code(405);
    exit('POST 요청만 허용됩니다.');
}

if (
    isset($_SERVER['CONTENT_LENGTH']) &&
    (float)$_SERVER['CONTENT_LENGTH'] > 65536
) {
    http_response_code(413);
    exit('제출 내용이 너무 큽니다.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$fail = function ($status, $message) {
    http_response_code($status);
    exit(
        $message .
        "\n브라우저의 뒤로 가기를 눌러 입력 내용을 확인해 주세요."
    );
};

$school_slug = isset($_POST['school'])
    ? trim((string)$_POST['school'])
    : '';

$event_slug = isset($_POST['event'])
    ? trim((string)$_POST['event'])
    : '';

if (
    !class_share_public_valid_slug($school_slug) ||
    !class_share_public_valid_slug($event_slug)
) {
    $fail(404, '행사 주소가 올바르지 않습니다.');
}

if (
    !class_share_application_verify_csrf(
        isset($_POST['csrf_token'])
            ? $_POST['csrf_token']
            : ''
    )
) {
    $fail(400, '화면이 만료되었습니다. 참관록 화면을 새로 열어 주세요.');
}

$submission_key = isset($_POST['submission_key'])
    ? (string)$_POST['submission_key']
    : '';

if (
    !preg_match('/\A[a-f0-9]{64}\z/D', $submission_key)
) {
    $fail(400, '제출 정보가 올바르지 않습니다.');
}

$redirect_url =
    '/class-share/observation.php?school=' .
    rawurlencode($school_slug) .
    '&event=' .
    rawurlencode($event_slug);

$completed = isset(
    $_SESSION['class_share_observation_completed']
) && is_array(
    $_SESSION['class_share_observation_completed']
)
    ? $_SESSION['class_share_observation_completed']
    : array();

if (
    isset($completed[$submission_key]) &&
    (int)$completed[$submission_key] >= time() - 3600
) {
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

$keys = isset(
    $_SESSION['class_share_observation_keys']
) && is_array(
    $_SESSION['class_share_observation_keys']
)
    ? $_SESSION['class_share_observation_keys']
    : array();

if (
    !isset($keys[$submission_key]) ||
    (int)$keys[$submission_key] < time() - 3600
) {
    $fail(400, '제출 화면이 만료되었습니다. 참관록 화면을 새로 열어 주세요.');
}

$attempts = isset(
    $_SESSION['class_share_observation_attempts']
) && is_array(
    $_SESSION['class_share_observation_attempts']
)
    ? $_SESSION['class_share_observation_attempts']
    : array();

$attempts = array_values(
    array_filter(
        $attempts,
        function ($value) {
            return (int)$value >= time() - 900;
        }
    )
);

if (count($attempts) >= 5) {
    $_SESSION['class_share_observation_attempts'] = $attempts;
    $fail(429, '제출 횟수가 많습니다. 15분 후 다시 시도해 주세요.');
}

$attempts[] = time();
$_SESSION['class_share_observation_attempts'] = $attempts;

if (
    isset($_POST['website']) &&
    trim((string)$_POST['website']) !== ''
) {
    $fail(400, '제출 내용을 확인해 주세요.');
}

$author_name = isset($_POST['author_name'])
    ? trim((string)$_POST['author_name'])
    : '';

$affiliation = isset($_POST['affiliation'])
    ? trim((string)$_POST['affiliation'])
    : '';

$body = isset($_POST['body'])
    ? trim((string)$_POST['body'])
    : '';

if (
    $author_name === '' ||
    class_share_application_text_length($author_name) > 100 ||
    $affiliation === '' ||
    class_share_application_text_length($affiliation) > 150 ||
    $body === '' ||
    class_share_application_text_length($body) > 5000
) {
    $fail(400, '성명, 소속, 참관 내용을 입력 길이에 맞게 확인해 주세요.');
}

if (
    !isset($_POST['privacy_consent']) ||
    (string)$_POST['privacy_consent'] !== '1'
) {
    $fail(400, '개인정보 수집·이용 동의가 필요합니다.');
}

$event_rows = pdo_query(
    "
    SELECT
        event.id,
        event.application_mode,
        setting.privacy_notice,
        setting.retention_until

    FROM class_share_event AS event

    INNER JOIN class_share_school AS school
        ON school.id = event.school_id

    INNER JOIN class_share_observation_setting AS setting
        ON setting.event_id = event.id

    WHERE school.slug = ?
      AND school.status = 'active'
      AND event.slug = ?
      AND event.status IN ('published', 'closed')
      AND setting.enabled = 1
      AND (setting.open_at IS NULL OR setting.open_at <= NOW())
      AND (setting.close_at IS NULL OR setting.close_at >= NOW())
      AND setting.retention_until >= CURRENT_DATE()

    LIMIT 1
    ",
    $school_slug,
    $event_slug
);

if ($event_rows === false) {
    $fail(500, '행사 정보를 확인할 수 없습니다.');
}

if (!isset($event_rows[0])) {
    $fail(403, '현재 참관록을 접수하지 않습니다.');
}

$event = $event_rows[0];
$event_id = (int)$event['id'];
$class_id = null;

$class_input = isset($_POST['class_id'])
    ? trim((string)$_POST['class_id'])
    : '';

if ((string)$event['application_mode'] === 'program') {
    if (
        !ctype_digit($class_input) ||
        (int)$class_input <= 0
    ) {
        $fail(400, '참관한 프로그램을 선택해 주세요.');
    }

    $class_rows = pdo_query(
        "
        SELECT id
        FROM class_share_class
        WHERE id = ?
          AND event_id = ?
          AND status IN ('published', 'closed')
        LIMIT 1
        ",
        (int)$class_input,
        $event_id
    );

    if ($class_rows === false) {
        $fail(500, '프로그램을 확인할 수 없습니다.');
    }

    if (!isset($class_rows[0])) {
        $fail(400, '이 행사에 속한 프로그램을 선택해 주세요.');
    }

    $class_id = (int)$class_rows[0]['id'];
} elseif ($class_input !== '') {
    $fail(400, '이 행사는 프로그램 선택을 받지 않습니다.');
}

$privacy_notice_snapshot =
    (string)$event['privacy_notice'] .
    "\n참관록 보관 기한: " .
    (string)$event['retention_until'];

$result = pdo_query(
    "
    INSERT INTO class_share_observation (
        event_id,
        class_id,
        author_name,
        affiliation,
        body,
        privacy_notice_snapshot,
        retention_until,
        privacy_consented_at,
        submission_key
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, NOW(), ?
    )
    ",
    $event_id,
    $class_id,
    $author_name,
    $affiliation,
    $body,
    $privacy_notice_snapshot,
    (string)$event['retention_until'],
    $submission_key
);

if ($result === false) {
    error_log('[class-share] 참관록 저장 실패: 행사 ' . $event_id);
    $fail(500, '참관록을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.');
}

unset($keys[$submission_key]);
$_SESSION['class_share_observation_keys'] = $keys;

$completed[$submission_key] = time();
$_SESSION['class_share_observation_completed'] =
    array_slice($completed, -10, null, true);

$_SESSION['class_share_observation_success'] = array(
    'event_id' => $event_id,
    'at' => time()
);

session_write_close();

header('Location: ' . $redirect_url, true, 303);
exit;
