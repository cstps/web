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

header(
    "Content-Security-Policy: " .
        "default-src 'self'; " .
        "style-src 'self'; " .
        "img-src 'self' data:; " .
        "script-src 'self'; " .
        "form-action 'self'; " .
        "frame-ancestors 'none'; " .
        "base-uri 'self'; " .
        "object-src 'none'"
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

$school_slug =
    isset($_GET['school'])
    ? trim((string)$_GET['school'])
    : '';

$event_slug =
    isset($_GET['event'])
    ? trim((string)$_GET['event'])
    : '';

$application_code =
    isset($_GET['application'])
    ? strtolower(
        trim(
            (string)$_GET['application']
        )
    )
    : '';

$page_error =
    '';

$application =
    null;

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
    http_response_code(404);
    $page_error =
        '신청 취소 주소가 올바르지 않습니다.';
}

if ($page_error === '') {
    $application_rows =
        pdo_query(
            "
            SELECT
                application.id,
                application.event_id,
                application.application_code,
                application.application_scope,
                application.status,

                event.school_id,
                event.title AS event_title,
                event.status AS event_status,

                school.school_name,
                school.slug AS school_slug,

                class_item.title AS program_title

            FROM class_share_application AS application

            INNER JOIN class_share_event AS event
                ON event.id =
                   application.event_id

            INNER JOIN class_share_school AS school
                ON school.id =
                   event.school_id

            LEFT JOIN class_share_class AS class_item
                ON class_item.id =
                   application.class_id

            WHERE school.slug = ?
              AND school.status = 'active'
              AND event.slug = ?
              AND event.status IN (
                  'published',
                  'closed'
              )
              AND application.application_code = ?

            LIMIT 1
            ",
            $school_slug,
            $event_slug,
            $application_code
        );

    if (
        $application_rows === false ||
        !isset($application_rows[0])
    ) {
        http_response_code(404);
        $page_error =
            '취소할 신청 내역을 찾을 수 없습니다.';
    } else {
        $application =
            $application_rows[0];
    }
}

$cancel_errors =
    array();

$success_message =
    '';

$saved_result =
    isset(
        $_SESSION[
            'class_share_application_cancel'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_application_cancel'
        ]
    )
    ? $_SESSION[
        'class_share_application_cancel'
    ]
    : null;

unset(
    $_SESSION[
        'class_share_application_cancel'
    ]
);

if (
    $application !== null &&
    is_array($saved_result)
) {
    $saved_event_id =
        isset($saved_result['event_id'])
        ? (int)$saved_result['event_id']
        : 0;

    $saved_code =
        isset(
            $saved_result[
                'application_code'
            ]
        )
        ? (string)$saved_result[
            'application_code'
        ]
        : '';

    if (
        (
            $saved_event_id === 0 ||
            $saved_event_id ===
                (int)$application['event_id']
        ) &&
        hash_equals(
            $application_code,
            $saved_code
        )
    ) {
        $cancel_errors =
            isset($saved_result['errors']) &&
            is_array($saved_result['errors'])
            ? $saved_result['errors']
            : array();

        $success_message =
            isset($saved_result['success'])
            ? (string)$saved_result[
                'success'
            ]
            : '';
    }
}

$status_names =
    array(
        'applied' => '신청 완료',
        'approved' => '승인',
        'waiting' => '대기',
        'rejected' => '거절',
        'attended' => '참석',
        'absent' => '미참석',
        'cancelled' => '취소'
    );

$current_status =
    $application !== null
    ? (string)$application['status']
    : '';

$status_name =
    isset($status_names[$current_status])
    ? $status_names[$current_status]
    : $current_status;

$can_cancel =
    $application !== null &&
    in_array(
        $current_status,
        class_share_application_active_statuses(),
        true
    );

$csrf_token =
    class_share_application_csrf_token();

$applications_url =
    '/class-share/applications.php?school=' .
    rawurlencode($school_slug) .
    '&event=' .
    rawurlencode($event_slug);

$page_title =
    $application !== null
    ? (string)$application['event_title'] .
        ' 신청 취소'
    : '행사 신청 취소';

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <link
        rel="stylesheet"
        href="/class-share/assets/public.css?v=20260923">

    <title>
        <?php
        echo class_share_public_escape(
            $page_title
        );
        ?>
    </title>
</head>

<body class="class-share-public">
    <header>
        <h1>학교 행사 신청 취소</h1>

        <?php if ($application !== null) { ?>
            <p>
                <?php
                echo class_share_public_escape(
                    $application[
                        'school_name'
                    ]
                );
                ?>
            </p>
        <?php } ?>
    </header>

    <main>
        <?php if ($page_error !== '') { ?>
            <section class="public-error">
                <h2>신청을 취소할 수 없습니다.</h2>

                <p>
                    <?php
                    echo class_share_public_escape(
                        $page_error
                    );
                    ?>
                </p>
            </section>
        <?php } else { ?>
            <section>
                <h2>
                    <?php
                    echo class_share_public_escape(
                        $application[
                            'application_scope'
                        ] === 'program' &&
                        $application[
                            'program_title'
                        ] !== null
                        ? $application[
                            'program_title'
                        ]
                        : $application[
                            'event_title'
                        ]
                    );
                    ?>
                </h2>

                <dl>
                    <dt>신청번호</dt>
                    <dd>
                        <code>
                            <?php
                            echo class_share_public_escape(
                                $application_code
                            );
                            ?>
                        </code>
                    </dd>

                    <dt>현재 상태</dt>
                    <dd>
                        <?php
                        echo class_share_public_escape(
                            $status_name
                        );
                        ?>
                    </dd>
                </dl>
            </section>

            <?php if ($success_message !== '') { ?>
                <section>
                    <h2>취소 완료</h2>

                    <p>
                        <?php
                        echo class_share_public_escape(
                            $success_message
                        );
                        ?>
                    </p>

                    <p>
                        <a href="<?php
                        echo class_share_public_escape(
                            $applications_url
                        );
                        ?>">
                            신청 확인으로 돌아가기
                        </a>
                    </p>
                </section>
            <?php } elseif (!$can_cancel) { ?>
                <section class="public-error">
                    <h2>취소할 수 없는 상태입니다.</h2>

                    <p>
                        현재 상태에서는 신청자가 직접 취소할 수 없습니다.
                    </p>
                </section>
            <?php } else { ?>
                <section>
                    <h2>본인 확인</h2>

                    <p>
                        신청할 때 입력한 연락처와 신청 비밀번호를 입력하면 신청이 즉시 취소됩니다.
                    </p>

                    <?php if (count($cancel_errors) > 0) { ?>
                        <div
                            class="public-error"
                            role="alert">

                            <ul>
                                <?php foreach ($cancel_errors as $error) { ?>
                                    <li>
                                        <?php
                                        echo class_share_public_escape(
                                            $error
                                        );
                                        ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                    <?php } ?>

                    <form
                        method="post"
                        action="/class-share/application_cancel_process.php"
                        autocomplete="on">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                            echo class_share_public_escape(
                                $csrf_token
                            );
                            ?>">

                        <input
                            type="hidden"
                            name="school_slug"
                            value="<?php
                            echo class_share_public_escape(
                                $school_slug
                            );
                            ?>">

                        <input
                            type="hidden"
                            name="event_slug"
                            value="<?php
                            echo class_share_public_escape(
                                $event_slug
                            );
                            ?>">

                        <input
                            type="hidden"
                            name="application_code"
                            value="<?php
                            echo class_share_public_escape(
                                $application_code
                            );
                            ?>">

                        <div class="public-form-grid">
                            <div class="public-field">
                                <label for="phone">
                                    연락처
                                </label>

                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    required
                                    maxlength="20"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    placeholder="010-1234-5678">
                            </div>

                            <div class="public-field">
                                <label for="password">
                                    신청 비밀번호
                                </label>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    required
                                    minlength="6"
                                    maxlength="72"
                                    autocomplete="current-password">
                            </div>
                        </div>

                        <div class="public-form-actions">
                            <a href="<?php
                            echo class_share_public_escape(
                                $applications_url
                            );
                            ?>">
                                돌아가기
                            </a>

                            <button type="submit">
                                신청 취소
                            </button>
                        </div>
                    </form>
                </section>
            <?php } ?>
        <?php } ?>
    </main>
</body>
</html>
