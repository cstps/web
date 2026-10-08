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

$class_public_id =
    isset($_POST['class_public_id'])
    ? strtolower(
        trim(
            (string)$_POST[
                'class_public_id'
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
    )
) {
    http_response_code(400);
    exit('신청 주소가 올바르지 않습니다.');
}

if (
    $class_public_id !== '' &&
    !preg_match(
        '/^[a-f0-9]{32}$/D',
        $class_public_id
    )
) {
    http_response_code(400);
    exit('프로그램 신청 주소가 올바르지 않습니다.');
}

$redirect_url =
    '/class-share/apply.php?school=' .
    rawurlencode($school_slug) .
    '&event=' .
    rawurlencode($event_slug);

if ($class_public_id !== '') {
    $redirect_url .=
        '&class=' .
        rawurlencode(
            $class_public_id
        );
}

$store_error_and_redirect =
    function (
        $event_id,
        $class_id,
        $errors,
        $form_values
    ) use ($redirect_url) {
        $_SESSION[
            'class_share_application_form'
        ] =
            array(
                'event_id' =>
                    (int)$event_id,

                'class_id' =>
                    (int)$class_id,

                'errors' =>
                    array_values(
                        array_unique(
                            $errors
                        )
                    ),

                'values' =>
                    is_array($form_values)
                    ? $form_values
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
    $store_error_and_redirect(
        0,
        0,
        array(
            '보안 확인에 실패했습니다. 페이지를 새로고침한 뒤 다시 신청해 주세요.'
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
            event.title,
            event.status,
            event.application_mode,
            school.school_name

        FROM class_share_event AS event

        INNER JOIN class_share_school AS school
            ON school.id = event.school_id

        WHERE school.slug = ?
          AND school.status = 'active'
          AND event.slug = ?

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
    exit('신청할 행사를 찾을 수 없습니다.');
}

$event =
    $event_rows[0];

$class_item =
    null;

if ($class_public_id !== '') {
    $class_rows =
        pdo_query(
            "
            SELECT
                id,
                event_id,
                title

            FROM class_share_class

            WHERE event_id = ?
              AND public_id = ?

            LIMIT 1
            ",
            (int)$event['id'],
            $class_public_id
        );

    if (
        $class_rows === false ||
        !isset($class_rows[0])
    ) {
        http_response_code(404);
        exit('신청할 프로그램을 찾을 수 없습니다.');
    }

    $class_item =
        $class_rows[0];
}

$class_id =
    $class_item !== null
    ? (int)$class_item['id']
    : 0;

try {
    $configured_form = class_share_form_load((int)$event['id']);
    $validation = class_share_application_validate($_POST, $configured_form);
} catch (Throwable $exception) {
    error_log('[class-share] 신청서 검증 준비 실패: ' . $exception->getMessage());
    $store_error_and_redirect(
        (int)$event['id'], $class_id,
        array('신청서 항목을 불러올 수 없습니다. 잠시 후 다시 시도해 주세요.'), array()
    );
}

if (
    count($validation['errors']) > 0
) {
    $store_error_and_redirect(
        (int)$event['id'],
        $class_id,
        $validation['errors'],
        $validation['form_values']
    );
}

try {
    if ($class_item === null) {
        $result =
            class_share_create_event_application(
                (int)$event['school_id'],
                (int)$event['id'],
                $validation['data']
            );
    } else {
        $result =
            class_share_create_program_application(
                (int)$event['school_id'],
                (int)$event['id'],
                $class_id,
                $validation['data']
            );
    }

    class_share_application_rotate_csrf();

    $_SESSION[
        'class_share_application_success'
    ] =
        array(
            'event_id' =>
                (int)$event['id'],

            'participation_name' => isset($result['participation_name'])
                ? (string)$result['participation_name'] : '',

            'event_title' =>
                (string)$event['title'],

            'class_id' =>
                $class_id,

            'class_title' =>
                $class_item !== null
                ? (string)$class_item['title']
                : '',

            'application_scope' =>
                $class_item !== null
                ? 'program'
                : 'event',

            'application_code' =>
                (string)$result[
                    'application_code'
                ]
        );

    unset(
        $_SESSION[
            'class_share_application_form'
        ]
    );

    session_write_close();

    header(
        'Location: ' .
        $redirect_url .
        '&success=1',
        true,
        303
    );

    exit;
} catch (DomainException $e) {
    $store_error_and_redirect(
        (int)$event['id'],
        $class_id,
        array(
            $e->getMessage()
        ),
        $validation['form_values']
    );
} catch (Throwable $e) {
    error_log(
        '[class-share application] ' .
        $e->getMessage()
    );

    $store_error_and_redirect(
        (int)$event['id'],
        $class_id,
        array(
            '신청을 처리하지 못했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        $validation['form_values']
    );
}
