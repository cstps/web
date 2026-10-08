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

$page_error =
    '';

$event =
    null;

if (
    !class_share_public_valid_slug(
        $school_slug
    ) ||
    !class_share_public_valid_slug(
        $event_slug
    )
) {
    http_response_code(404);
    $page_error =
        '신청 확인 주소가 올바르지 않습니다.';
}

if ($page_error === '') {
    $event_rows =
        pdo_query(
            "
            SELECT
                event.id,
                event.school_id,
                event.slug,
                event.title,
                event.application_mode,
                event.status,

                school.school_name,
                school.slug AS school_slug

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
        $page_error =
            '신청 내역을 확인할 행사를 찾을 수 없습니다.';
    } else {
        $event =
            $event_rows[0];
    }
}

$lookup_errors =
    array();

$applications =
    array();

$saved_lookup =
    isset(
        $_SESSION[
            'class_share_application_lookup'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_application_lookup'
        ]
    )
    ? $_SESSION[
        'class_share_application_lookup'
    ]
    : null;

unset(
    $_SESSION[
        'class_share_application_lookup'
    ]
);

if (
    $event !== null &&
    is_array($saved_lookup)
) {
    $saved_event_id =
        isset($saved_lookup['event_id'])
        ? (int)$saved_lookup['event_id']
        : 0;

    if (
        $saved_event_id === 0 ||
        $saved_event_id ===
            (int)$event['id']
    ) {
        $lookup_errors =
            isset($saved_lookup['errors']) &&
            is_array($saved_lookup['errors'])
            ? $saved_lookup['errors']
            : array();

        $applications =
            isset(
                $saved_lookup[
                    'applications'
                ]
            ) &&
            is_array(
                $saved_lookup[
                    'applications'
                ]
            )
            ? $saved_lookup[
                'applications'
            ]
            : array();
    }
}

// 비밀번호 확인을 통과해 세션에 담긴 신청만 현재 파기 상태와 함께 다시 조회합니다.
$visible_applications = array();
if ($event !== null && is_array($saved_lookup)
    && isset($saved_lookup['event_id']) && (int)$saved_lookup['event_id'] === (int)$event['id']) {
    try {
        foreach ($applications as $application) {
            if (!isset($application['id'], $application['application_code'])) {
                continue;
            }
            $answers = class_share_form_load_response(
                (int)$event['id'], (int)$application['id'], $application['application_code']
            );
            if ($answers === null) {
                continue;
            }
            foreach (array('applicant_name', 'applicant_school') as $name_key) {
                if (!isset($application[$name_key]) || $application[$name_key] === '') {
                    $application[$name_key] = '미입력';
                }
            }
            $application['form_answers'] = $answers;
            $visible_applications[] = $application;
        }
    } catch (Throwable $exception) {
        error_log('[class-share] 신청 확인 추가 답변 조회 실패: ' . $exception->getMessage());
        http_response_code(500);
        $lookup_errors[] = '신청 내역을 불러올 수 없습니다. 잠시 후 다시 확인해 주세요.';
        $visible_applications = array();
    }
}
$applications = $visible_applications;

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

$csrf_token =
    class_share_application_csrf_token();

$event_url =
    class_share_public_event_url(
        $school_slug,
        $event_slug
    );

$page_title =
    $event !== null
    ? (string)$event['title'] .
        ' 신청 확인'
    : '행사 신청 확인';

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
        <h1>학교 행사 신청 확인</h1>

        <?php if ($event !== null) { ?>
            <p>
                <?php
                echo class_share_public_escape(
                    $event['school_name']
                );
                ?>
            </p>
        <?php } ?>
    </header>

    <main>
        <?php if ($page_error !== '') { ?>
            <section>
                <h2>신청 내역을 확인할 수 없습니다.</h2>

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
                        $event['title']
                    );
                    ?>
                </h2>

                <p>
                    신청할 때 입력한 연락처와 신청 비밀번호를 입력해 주세요.
                </p>

                <?php if (count($lookup_errors) > 0) { ?>
                    <div
                        class="public-form-errors"
                        role="alert">

                        <ul>
                            <?php foreach ($lookup_errors as $error) { ?>
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
                    action="/class-share/application_lookup_process.php"
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
                        <a
                            href="<?php
                            echo class_share_public_escape(
                                $event_url
                            );
                            ?>">
                            행사 안내로 돌아가기
                        </a>

                        <button type="submit">
                            신청 내역 확인
                        </button>
                    </div>
                </form>
            </section>

            <?php if (count($applications) > 0) { ?>
                <section>
                    <h2>나의 신청 내역</h2>

                    <?php foreach ($applications as $application) { ?>
                        <?php
                        $status =
                            (string)$application[
                                'status'
                            ];

                        $status_name =
                            isset($status_names[$status])
                            ? $status_names[$status]
                            : $status;
                        ?>

                        <article>
                            <h3>
                                <?php
                                echo class_share_public_escape(
                                    $application[
                                        'application_scope'
                                    ] === 'program' &&
                                    $application[
                                        'program_title'
                                    ] !== ''
                                    ? $application[
                                        'program_title'
                                    ]
                                    : $application[
                                        'event_title'
                                    ]
                                );
                                ?>
                            </h3>

                            <dl>
                                <dt>신청번호</dt>
                                <dd>
                                    <code>
                                        <?php
                                        echo class_share_public_escape(
                                            $application[
                                                'application_code'
                                            ]
                                        );
                                        ?>
                                    </code>
                                </dd>

                                <dt>신청자</dt>
                                <dd>
                                    <?php
                                    echo class_share_public_escape(
                                        $application[
                                            'applicant_name'
                                        ]
                                    );
                                    ?>
                                </dd>

                                <dt>소속</dt>
                                <dd>
                                    <?php
                                    echo class_share_public_escape(
                                        $application[
                                            'applicant_school'
                                        ]
                                    );
                                    ?>
                                </dd>

                                <dt>연락처</dt>
                                <dd>
                                    ***-****-<?php
                                    echo class_share_public_escape(
                                        $application[
                                            'phone_last4'
                                        ]
                                    );
                                    ?>
                                </dd>

                                <?php if ($application['application_scope'] === 'event') { ?>
                                <dt>참여 구분</dt>
                                <dd><?php echo class_share_public_escape(
                                    isset($application['participation_name'])
                                        ? $application['participation_name'] : '미구분'
                                ); ?></dd>
                                <?php } ?>
                                <dt>신청 상태</dt>
                                <dd>
                                    <?php
                                    echo class_share_public_escape(
                                        $status_name
                                    );
                                    ?>
                                </dd>

                                <dt>신청일시</dt>
                                <dd>
                                    <?php
                                    echo class_share_public_escape(
                                        class_share_public_format_datetime(
                                            $application[
                                                'created_at'
                                            ]
                                        )
                                    );
                                    ?>
                                </dd>

                                <?php
                                if (
                                    $application[
                                        'cancelled_at'
                                    ] !== null
                                ) {
                                ?>
                                    <dt>취소일시</dt>
                                    <dd>
                                        <?php
                                        echo class_share_public_escape(
                                            class_share_public_format_datetime(
                                                $application[
                                                    'cancelled_at'
                                                ]
                                            )
                                        );
                                        ?>
                                    </dd>
                                <?php } ?>
                            </dl>

                            <?php if (count($application['form_answers']) > 0) { ?>
                            <h4>추가 질문 답변</h4>
                            <dl>
                            <?php foreach ($application['form_answers'] as $answer) { ?>
                                <dt><?php echo class_share_public_escape($answer['label']); ?></dt>
                                <dd><?php $answer_text = class_share_form_answer_text($answer);
                                    echo $answer_text === '' ? '미입력' : nl2br(class_share_public_escape($answer_text)); ?></dd>
                            <?php } ?>
                            </dl>
                            <?php } ?>

                            <?php
                            if (
                                in_array(
                                    $status,
                                    class_share_application_active_statuses(),
                                    true
                                )
                            ) {
                            ?>
                                <div class="public-form-actions">
                                    <a
                                        class="public-button"
                                        href="/class-share/application_cancel.php?school=<?php
                                        echo rawurlencode(
                                            $school_slug
                                        );
                                        ?>&amp;event=<?php
                                        echo rawurlencode(
                                            $event_slug
                                        );
                                        ?>&amp;application=<?php
                                        echo rawurlencode(
                                            $application[
                                                'application_code'
                                            ]
                                        );
                                        ?>">
                                        신청 취소
                                    </a>
                                </div>
                            <?php } ?>
                        </article>
                    <?php } ?>
                </section>
            <?php } ?>
        <?php } ?>
    </main>
</body>
</html>
