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

require_once(
    __DIR__ .
    '/include/application_service.php'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Referrer-Policy: same-origin'
);

header(
    'Cache-Control: no-store, max-age=0'
);

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    http_response_code(405);
    header('Allow: POST');
    exit('POST 요청만 허용됩니다.');
}

$school_slug =
    isset($_POST['school_slug'])
    ? trim((string)$_POST['school_slug'])
    : '';

$event_slug =
    isset($_POST['event_slug'])
    ? trim((string)$_POST['event_slug'])
    : '';

$application_code =
    isset($_POST['application_code'])
    ? strtolower(
        trim(
            (string)$_POST[
                'application_code'
            ]
        )
    )
    : '';

if (
    !class_share_public_valid_slug(
        $school_slug
    ) ||
    !class_share_public_valid_slug(
        $event_slug
    ) ||
    !preg_match(
        '/^[a-f0-9]{32}$/D',
        $application_code
    )
) {
    http_response_code(400);
    exit('신청 취소 주소가 올바르지 않습니다.');
}

$redirect_url =
    '/class-share/application_cancel.php?school=' .
    rawurlencode($school_slug) .
    '&event=' .
    rawurlencode($event_slug) .
    '&application=' .
    rawurlencode($application_code);

$store_result_and_redirect =
    function (
        $event_id,
        $errors,
        $success
    ) use (
        $redirect_url,
        $application_code
    ) {
        $_SESSION[
            'class_share_application_cancel'
        ] =
            array(
                'event_id' =>
                    (int)$event_id,

                'application_code' =>
                    $application_code,

                'errors' =>
                    array_values(
                        array_unique(
                            is_array($errors)
                            ? $errors
                            : array()
                        )
                    ),

                'success' =>
                    (string)$success
            );

        session_write_close();

        header(
            'Location: ' .
            $redirect_url,
            true,
            303
        );

        exit;
    };

$csrf_token =
    isset($_POST['csrf_token'])
    ? (string)$_POST['csrf_token']
    : '';

if (
    !class_share_application_verify_csrf(
        $csrf_token
    )
) {
    $store_result_and_redirect(
        0,
        array(
            '보안 확인에 실패했습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.'
        ),
        ''
    );
}

$event_rows =
    pdo_query(
        "
        SELECT
            event.id,
            event.school_id

        FROM class_share_event AS event

        INNER JOIN class_share_school AS school
            ON school.id = event.school_id

        WHERE school.slug = ?
          AND school.status = 'active'
          AND event.slug = ?
          AND event.status IN (
              'published',
              'closed'
          )

        LIMIT 1
        ",
        $school_slug,
        $event_slug
    );

if (
    $event_rows === false ||
    !isset($event_rows[0])
) {
    http_response_code(404);
    exit('신청을 취소할 행사를 찾을 수 없습니다.');
}

$event =
    $event_rows[0];

$event_id =
    (int)$event['id'];

$phone =
    isset($_POST['phone'])
    ? trim((string)$_POST['phone'])
    : '';

$password =
    isset($_POST['password'])
    ? (string)$_POST['password']
    : '';

$errors =
    array();

try {
    class_share_normalize_phone(
        $phone
    );
} catch (InvalidArgumentException $e) {
    $errors[] =
        '연락처를 정확히 입력해 주세요.';
}

if (
    strlen($password) < 6 ||
    strlen($password) > 72
) {
    $errors[] =
        '신청 비밀번호를 정확히 입력해 주세요.';
}

if (count($errors) > 0) {
    $store_result_and_redirect(
        $event_id,
        $errors,
        ''
    );
}

$rate_key =
    'class_share_application_cancel_rate';

$now =
    time();

$attempts =
    isset($_SESSION[$rate_key]) &&
    is_array($_SESSION[$rate_key])
    ? $_SESSION[$rate_key]
    : array();

$recent_attempts =
    array();

foreach ($attempts as $attempt_time) {
    if (
        (int)$attempt_time >=
        $now - 900
    ) {
        $recent_attempts[] =
            (int)$attempt_time;
    }
}

if (count($recent_attempts) >= 10) {
    $_SESSION[$rate_key] =
        $recent_attempts;

    $store_result_and_redirect(
        $event_id,
        array(
            '신청 취소를 너무 많이 시도했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        ''
    );
}

$recent_attempts[] =
    $now;

$_SESSION[$rate_key] =
    $recent_attempts;

try {
    class_share_cancel_application(
        (int)$event['school_id'],
        $event_id,
        $application_code,
        $phone,
        $password
    );

    unset(
        $_SESSION[$rate_key]
    );

    class_share_application_rotate_csrf();

    $store_result_and_redirect(
        $event_id,
        array(),
        '신청을 취소했습니다.'
    );
} catch (DomainException $e) {
    $store_result_and_redirect(
        $event_id,
        array(
            $e->getMessage()
        ),
        ''
    );
} catch (Throwable $e) {
    error_log(
        '[class-share] 신청 취소 실패: ' .
        $e->getMessage()
    );

    $store_result_and_redirect(
        $event_id,
        array(
            '신청을 취소하는 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        ''
    );
}
