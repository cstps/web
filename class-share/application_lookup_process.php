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

if (
    !class_share_public_valid_slug(
        $school_slug
    ) ||
    !class_share_public_valid_slug(
        $event_slug
    )
) {
    http_response_code(400);
    exit('신청 확인 주소가 올바르지 않습니다.');
}

$redirect_url =
    '/class-share/applications.php?school=' .
    rawurlencode($school_slug) .
    '&event=' .
    rawurlencode($event_slug);

$store_result_and_redirect =
    function (
        $event_id,
        $errors,
        $applications
    ) use ($redirect_url) {
        $_SESSION[
            'class_share_application_lookup'
        ] =
            array(
                'event_id' =>
                    (int)$event_id,

                'errors' =>
                    array_values(
                        array_unique(
                            is_array($errors)
                            ? $errors
                            : array()
                        )
                    ),

                'applications' =>
                    is_array($applications)
                    ? $applications
                    : array()
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
        array()
    );
}

$event_rows =
    pdo_query(
        "
        SELECT
            event.id,
            event.school_id,
            event.status,
            event.application_mode

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
    exit('신청 내역을 확인할 행사를 찾을 수 없습니다.');
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
        array()
    );
}

$rate_key =
    'class_share_application_lookup_rate';

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
    $attempt_time =
        (int)$attempt_time;

    if (
        $attempt_time >=
        $now - 900
    ) {
        $recent_attempts[] =
            $attempt_time;
    }
}

if (count($recent_attempts) >= 10) {
    $_SESSION[$rate_key] =
        $recent_attempts;

    $store_result_and_redirect(
        $event_id,
        array(
            '신청 확인을 너무 많이 시도했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        array()
    );
}

$recent_attempts[] =
    $now;

$_SESSION[$rate_key] =
    $recent_attempts;

try {
    $applications =
        class_share_find_applications(
            (int)$event['school_id'],
            $event_id,
            $phone,
            $password
        );

    if (count($applications) === 0) {
        $store_result_and_redirect(
            $event_id,
            array(
                '입력한 정보와 일치하는 신청 내역을 찾을 수 없습니다.'
            ),
            array()
        );
    }

    unset(
        $_SESSION[$rate_key]
    );

    class_share_application_rotate_csrf();

    $store_result_and_redirect(
        $event_id,
        array(),
        $applications
    );
} catch (Throwable $e) {
    error_log(
        '[class-share] 신청 조회 실패: ' .
        $e->getMessage()
    );

    $store_result_and_redirect(
        $event_id,
        array(
            '신청 내역을 확인하는 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        array()
    );
}
