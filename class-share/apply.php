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

$class_public_id =
    isset($_GET['class'])
    ? strtolower(
        trim(
            (string)$_GET[
                'class'
            ]
        )
    )
    : '';

$page_error =
    '';

$event =
    null;

$class_item =
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
        '신청 주소가 올바르지 않습니다.';
} elseif (
    $class_public_id !== '' &&
    !preg_match(
        '/^[a-f0-9]{32}$/D',
        $class_public_id
    )
) {
    http_response_code(404);
    $page_error =
        '신청 주소가 올바르지 않습니다.';
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
                event.subtitle,
                event.event_type,
                event.application_mode,
                event.application_capacity,
                event.participation_enabled,
                event.event_start_at,
                event.event_end_at,
                event.application_start_at,
                event.application_end_at,
                event.privacy_policy_version,
                event.privacy_notice,
                event.retention_until,
                event.status,

                school.school_name,
                school.slug AS school_slug

            FROM class_share_event AS event

            INNER JOIN class_share_school AS school
                ON school.id = event.school_id

            WHERE school.slug = ?
              AND school.status = 'active'
              AND event.slug = ?
              AND event.status = 'published'

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
            '현재 직접 신청할 수 있는 행사를 찾을 수 없습니다.';
    } else {
        $event =
            $event_rows[0];

        if ($class_public_id === '') {
            if (
                (string)$event[
                    'application_mode'
                ] !== 'event'
            ) {
                http_response_code(404);
                $page_error =
                    '현재 직접 신청할 수 있는 행사를 찾을 수 없습니다.';
            }
        } elseif (
            (string)$event[
                'application_mode'
            ] !== 'program'
        ) {
            http_response_code(404);
            $page_error =
                '현재 신청할 수 있는 프로그램을 찾을 수 없습니다.';
        } else {
            $class_rows =
                pdo_query(
                    "
                    SELECT
                        id,
                        event_id,
                        public_id,
                        subject,
                        title,
                        teacher_name,
                        target,
                        class_start_at,
                        class_end_at,
                        place,
                        application_deadline,
                        capacity,
                        status

                    FROM class_share_class

                    WHERE event_id = ?
                      AND public_id = ?
                      AND status = 'published'

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
                $page_error =
                    '현재 신청할 수 있는 프로그램을 찾을 수 없습니다.';
            } else {
                $class_item =
                    $class_rows[0];
            }
        }
    }
}

$is_program_application =
    $class_item !== null;

$active_count =
    0;

if (
    $event !== null &&
    $page_error === ''
) {
    $count_condition =
        $is_program_application
        ? "
              AND class_id = ?
              AND application_scope = 'program'
          "
        : "
              AND application_scope = 'event'
          ";

    $count_sql =
        "
        SELECT
            COUNT(*) AS active_count

        FROM class_share_application

        WHERE event_id = ?
        " .
        $count_condition .
        "
          AND status IN (
              'applied',
              'approved',
              'waiting'
          )
        ";

    if ($is_program_application) {
        $count_rows =
            pdo_query(
                $count_sql,
                (int)$event['id'],
                (int)$class_item['id']
            );
    } else {
        $count_rows =
            pdo_query(
                $count_sql,
                (int)$event['id']
            );
    }

    if (
        $count_rows === false ||
        !isset($count_rows[0])
    ) {
        http_response_code(500);
        $page_error =
            '신청 현황을 불러올 수 없습니다.';
    } else {
        $active_count =
            (int)$count_rows[0][
                'active_count'
            ];
    }
}

if ($is_program_application) {
    $capacity =
        (int)$class_item['capacity'];
} else {
    $capacity =
        $event !== null &&
        $event['application_capacity'] !== null
        ? (int)$event['application_capacity']
        : null;
}

$remaining =
    $capacity === null
    ? null
    : max(
        0,
        $capacity - $active_count
    );

$is_open =
    $event !== null &&
    $page_error === '' &&
    (
        $is_program_application
        ? class_share_application_program_is_open(
            $event,
            $class_item
        )
        : class_share_application_event_is_open(
            $event
        )
    );

$is_available =
    $is_open &&
    (
        $capacity === null ||
        $active_count < $capacity
    );

$participation_enabled = $event !== null &&
    !$is_program_application &&
    (int)$event['participation_enabled'] === 1;
$participation_options = array();
if ($participation_enabled && $page_error === '') {
    try {
        $participation_options = class_share_participation_list_options((int)$event['id']);
        $has_available_option = false;
        foreach ($participation_options as $option) {
            if ($option['capacity'] === null || (int)$option['active_count'] < (int)$option['capacity']) {
                $has_available_option = true;
            }
        }
        $is_available = $is_available && $has_available_option;
    } catch (Throwable $exception) {
        error_log('[class-share] 참여 구분 현황 조회 실패: ' . $exception->getMessage());
        http_response_code(500);
        $page_error = '참여 구분 현황을 불러올 수 없습니다.';
        $is_available = false;
    }
}

$form_errors =
    array();

$form_values =
    array(
        'name' => '',
        'school' => '',
        'phone' => '',
        'privacy_agreed' => '',
        'participation_option_id' => ''
    );

if (
    isset(
        $_SESSION[
            'class_share_application_form'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_application_form'
        ]
    )
) {
    $saved_form =
        $_SESSION[
            'class_share_application_form'
        ];

    $saved_class_id =
        isset($saved_form['class_id'])
        ? (int)$saved_form['class_id']
        : 0;

    $current_class_id =
        $is_program_application
        ? (int)$class_item['id']
        : 0;

    if (
        $event !== null &&
        (
            (int)$saved_form['event_id'] ===
                0 ||
            (int)$saved_form['event_id'] ===
                (int)$event['id']
        ) &&
        (
            $saved_class_id === 0 ||
            $saved_class_id ===
                $current_class_id
        )
    ) {
        $form_errors =
            isset($saved_form['errors']) &&
            is_array($saved_form['errors'])
            ? $saved_form['errors']
            : array();

        $saved_values =
            isset($saved_form['values']) &&
            is_array($saved_form['values'])
            ? $saved_form['values']
            : array();

        $form_values =
            array_merge(
                $form_values,
                $saved_values
            );
    }

    unset(
        $_SESSION[
            'class_share_application_form'
        ]
    );
}

$success =
    null;

if (
    isset($_GET['success']) &&
    (string)$_GET['success'] === '1' &&
    isset(
        $_SESSION[
            'class_share_application_success'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_application_success'
        ]
    )
) {
    $saved_success =
        $_SESSION[
            'class_share_application_success'
        ];

    $saved_success_class_id =
        isset($saved_success['class_id'])
        ? (int)$saved_success['class_id']
        : 0;

    $current_class_id =
        $is_program_application
        ? (int)$class_item['id']
        : 0;

    if (
        $event !== null &&
        (int)$saved_success['event_id'] ===
            (int)$event['id'] &&
        $saved_success_class_id ===
            $current_class_id
    ) {
        $success =
            $saved_success;
    }

    unset(
        $_SESSION[
            'class_share_application_success'
        ]
    );
}

$csrf_token =
    class_share_application_csrf_token();

$page_title =
    $event !== null
    ? (
        $is_program_application
        ? (string)$class_item['title'] .
            ' 신청'
        : (string)$event['title'] .
            ' 신청'
    )
    : '행사 신청';

$event_url =
    class_share_public_event_url(
        $school_slug,
        $event_slug
    );

$applications_url =
    '/class-share/applications.php?school=' .
    rawurlencode($school_slug) .
    '&event=' .
    rawurlencode($event_slug);

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
        <h1>학교 행사 신청</h1>

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
                <h2>신청할 수 없습니다.</h2>

                <p>
                    <?php
                    echo class_share_public_escape(
                        $page_error
                    );
                    ?>
                </p>
            </section>

        <?php } elseif ($success !== null) { ?>
            <section>
                <h2>
                    <?php
                    echo $is_program_application
                        ? '프로그램 신청이 완료되었습니다.'
                        : '행사 신청이 완료되었습니다.';
                    ?>
                </h2>

                <p>
                    <?php
                    echo class_share_public_escape(
                        $is_program_application
                        ? $success['class_title']
                        : $success['event_title']
                    );
                    ?>
                </p>

                <dl>
                    <dt>신청 고유번호</dt>
                    <dd>
                        <code>
                            <?php
                            echo class_share_public_escape(
                                $success[
                                    'application_code'
                                ]
                            );
                            ?>
                        </code>
                    </dd>

                    <?php if (!empty($success['participation_name'])) { ?>
                    <dt>참여 구분</dt>
                    <dd><?php echo class_share_public_escape($success['participation_name']); ?></dd>
                    <?php } ?>
                    <dt>신청 상태</dt>
                    <dd>신청 완료</dd>
                </dl>

                <p>
                    신청 확인과 취소에는 입력한 연락처와 신청 비밀번호가 필요합니다.
                </p>

                <div class="public-form-actions">
                    <a
                        href="<?php
                        echo class_share_public_escape(
                            $event_url
                        );
                        ?>">
                        행사 안내로 돌아가기
                    </a>

                    <a
                        class="public-button"
                        href="<?php
                        echo class_share_public_escape(
                            $applications_url
                        );
                        ?>">
                        내 신청 확인
                    </a>
                </div>
            </section>

        <?php } else { ?>
            <section>
                <p>
                    <?php
                    echo class_share_public_escape(
                        $event['school_name']
                    );
                    ?>
                </p>

                <h2>
                    <?php
                    echo class_share_public_escape(
                        $event['title']
                    );
                    ?>
                </h2>

                <?php if ($is_program_application) { ?>
                    <p>신청 프로그램</p>

                    <h3>
                        <?php
                        echo class_share_public_escape(
                            $class_item['title']
                        );
                        ?>
                    </h3>

                    <dl>
                        <dt>교과</dt>
                        <dd>
                            <?php
                            echo class_share_public_escape(
                                $class_item['subject']
                            );
                            ?>
                        </dd>

                        <dt>교사</dt>
                        <dd>
                            <?php
                            echo class_share_public_escape(
                                $class_item[
                                    'teacher_name'
                                ]
                            );
                            ?>
                        </dd>

                        <dt>대상</dt>
                        <dd>
                            <?php
                            echo class_share_public_escape(
                                $class_item['target']
                            );
                            ?>
                        </dd>

                        <dt>수업일시</dt>
                        <dd>
                            <?php
                            echo class_share_public_escape(
                                class_share_public_format_datetime(
                                    $class_item[
                                        'class_start_at'
                                    ]
                                )
                            );
                            ?>
                            <?php if (
                                $class_item[
                                    'class_end_at'
                                ] !== null
                            ) { ?>
                                ~
                                <?php
                                echo class_share_public_escape(
                                    class_share_public_format_datetime(
                                        $class_item[
                                            'class_end_at'
                                        ]
                                    )
                                );
                                ?>
                            <?php } ?>
                        </dd>

                        <dt>장소</dt>
                        <dd>
                            <?php
                            echo class_share_public_escape(
                                $class_item['place']
                            );
                            ?>
                        </dd>
                    </dl>
                <?php } ?>

                <?php if (
                    trim(
                        (string)$event['subtitle']
                    ) !== ''
                ) { ?>
                    <p>
                        <?php
                        echo class_share_public_escape(
                            $event['subtitle']
                        );
                        ?>
                    </p>
                <?php } ?>

                <dl>
                    <dt>신청기간</dt>
                    <dd>
                        <?php
                        echo class_share_public_escape(
                            class_share_public_format_datetime(
                                $event[
                                    'application_start_at'
                                ]
                            )
                        );
                        ?>
                        ~
                        <?php
                        echo class_share_public_escape(
                            class_share_public_format_datetime(
                                $event[
                                    'application_end_at'
                                ]
                            )
                        );
                        ?>
                    </dd>

                    <?php if ($is_program_application) { ?>
                        <dt>프로그램 신청 마감</dt>
                        <dd>
                            <?php
                            echo class_share_public_escape(
                                class_share_public_format_datetime(
                                    $class_item[
                                        'application_deadline'
                                    ]
                                )
                            );
                            ?>
                        </dd>
                    <?php } ?>

                    <?php if ($participation_enabled) { ?>
                    <dt>참여 구분별 모집 현황</dt>
                    <dd>
                        <ul>
                            <?php if (count($participation_options) === 0) { ?>
                            <li>모집 중인 참여 구분이 없습니다.</li>
                            <?php } ?>
                            <?php foreach ($participation_options as $option) { ?>
                            <li>
                                <strong><?php echo class_share_public_escape($option['name']); ?></strong>:
                                <?php if ($option['capacity'] === null) { ?>
                                    정원 제한 없음
                                <?php } else {
                                    $option_remaining = max(
                                        0,
                                        (int)$option['capacity'] - (int)$option['active_count']
                                    );
                                ?>
                                    <?php echo $option_remaining === 0
                                        ? '모집 마감'
                                        : '잔여 ' . $option_remaining . '명'; ?>
                                <?php } ?>
                            </li>
                            <?php } ?>
                        </ul>
                    </dd>

                    <?php if ($capacity !== null) { ?>
                    <dt>전체 잔여</dt>
                    <dd>
                        <?php echo $remaining === 0
                            ? '모집 마감'
                            : $remaining . '명'; ?>
                    </dd>
                    <dt>정원 적용 안내</dt>
                    <dd>전체 정원이 차면 구분별 자리가 남아 있어도 신청이 마감됩니다.</dd>
                    <?php } ?>

                    <?php } else { ?>
                    <dt>모집 현황</dt>
                    <dd>
                        <?php echo $capacity === null
                            ? '정원 제한 없음'
                            : ($remaining === 0
                                ? '모집 마감'
                                : '잔여 ' . $remaining . '명'); ?>
                    </dd>
                    <?php } ?>
                </dl>
            </section>

            <?php if (count($form_errors) > 0) { ?>
                <section
                    class="public-error"
                    role="alert">

                    <h2>입력 내용을 확인해 주세요.</h2>

                    <ul>
                        <?php foreach (
                            $form_errors as $form_error
                        ) { ?>
                            <li>
                                <?php
                                echo class_share_public_escape(
                                    $form_error
                                );
                                ?>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>

            <?php if (!$is_available) { ?>
                <section>
                    <h2>현재 신청할 수 없습니다.</h2>

                    <p>
                        <?php
                        echo $is_open
                            ? (
                                $is_program_application
                                ? '프로그램 신청 정원이 마감되었습니다.'
                                : '행사 신청 정원이 마감되었습니다.'
                            )
                            : (
                                $is_program_application
                                ? '현재 프로그램 신청 기간이 아닙니다.'
                                : '현재 행사 신청 기간이 아닙니다.'
                            );
                        ?>
                    </p>

                    <p>
                        <a
                            href="<?php
                            echo class_share_public_escape(
                                $event_url
                            );
                            ?>">
                            행사 안내로 돌아가기
                        </a>
                    </p>
                </section>

            <?php } else { ?>
                <section>
                    <h2>신청자 정보</h2>

                    <form
                        method="post"
                        action="/class-share/apply_process.php"
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
                            name="class_public_id"
                            value="<?php
                            echo class_share_public_escape(
                                $class_public_id
                            );
                            ?>">

                        <div
                            class="public-honeypot"
                            hidden
                            aria-hidden="true">
                            <label for="website">
                                웹사이트
                            </label>

                            <input
                                type="text"
                                id="website"
                                name="website"
                                tabindex="-1"
                                autocomplete="off">
                        </div>

                        <?php if ($participation_enabled) { ?>
                        <div class="public-field">
                            <label for="participation_option_id">참여 구분 *</label>
                            <select id="participation_option_id" name="participation_option_id" required>
                                <option value="">참여 구분을 선택해 주세요.</option>
                                <?php foreach ($participation_options as $option) {
                                    $option_remaining = $option['capacity'] === null ? null
                                        : max(0, (int)$option['capacity'] - (int)$option['active_count']);
                                    $option_full = $option_remaining === 0;
                                    $option_label = (string)$option['name'] . ($option_remaining === null
                                        ? ' · 정원 제한 없음' : ($option_full ? ' · 마감' : ' · 잔여 ' . $option_remaining . '명'));
                                ?>
                                <option value="<?php echo (int)$option['id']; ?>"<?php
                                    echo (string)$form_values['participation_option_id'] === (string)$option['id'] ? ' selected' : '';
                                    echo $option_full ? ' disabled' : '';
                                ?>><?php echo class_share_public_escape($option_label); ?></option>
                                <?php } ?>
                            </select>
                            <small>선택한 구분의 정원과 행사 전체 정원을 함께 확인합니다.</small>
                        </div>
                        <?php } ?>

                        <div class="public-field">
                            <label for="name">
                                성명 *
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                required
                                minlength="2"
                                maxlength="60"
                                autocomplete="name"
                                value="<?php
                                echo class_share_public_escape(
                                    $form_values['name']
                                );
                                ?>">
                        </div>

                        <div class="public-field">
                            <label for="school">
                                소속 학교 또는 기관 *
                            </label>

                            <input
                                type="text"
                                id="school"
                                name="school"
                                required
                                minlength="2"
                                maxlength="100"
                                autocomplete="organization"
                                value="<?php
                                echo class_share_public_escape(
                                    $form_values['school']
                                );
                                ?>">
                        </div>

                        <div class="public-field">
                            <label for="phone">
                                연락처 *
                            </label>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                required
                                minlength="13"
                                maxlength="13"
                                pattern="010-[0-9]{4}-[0-9]{4}"
                                title="010-0000-0000 형식으로 입력해 주세요."
                                aria-describedby="phone-format-help"
                                inputmode="tel"
                                autocomplete="tel"
                                placeholder="010-1234-5678"
                                value="<?php
                                echo class_share_public_escape(
                                    $form_values['phone']
                                );
                                ?>">
                            <small id="phone-format-help" aria-live="polite">
                                하이픈을 포함하여 010-0000-0000 형식으로 입력해 주세요.
                            </small>
                        </div>

                        <div class="public-field">
                            <label for="password">
                                신청 비밀번호 *
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                minlength="6"
                                maxlength="72"
                                autocomplete="new-password">

                            <small>
                                신청 확인과 취소에 사용할 6자 이상의 비밀번호입니다.
                            </small>
                        </div>

                        <div class="public-field">
                            <label for="password_confirm">
                                신청 비밀번호 확인 *
                            </label>

                            <input
                                type="password"
                                id="password_confirm"
                                name="password_confirm"
                                required
                                minlength="6"
                                maxlength="72"
                                autocomplete="new-password">
                        </div>

                        <div class="public-privacy">
                            <h3>개인정보 수집·이용 안내</h3>

                            <p>
                                <?php
                                echo nl2br(
                                    class_share_public_escape(
                                        $event[
                                            'privacy_notice'
                                        ]
                                    ),
                                    false
                                );
                                ?>
                            </p>

                            <p>
                                개인정보 보관 기한:
                                <?php
                                echo class_share_public_escape(
                                    $event[
                                        'retention_until'
                                    ]
                                );
                                ?>
                            </p>

                            <label>
                                <input
                                    type="checkbox"
                                    name="privacy_agreed"
                                    value="1"
                                    required<?php
                                    echo
                                    $form_values[
                                        'privacy_agreed'
                                    ] === '1'
                                    ? ' checked'
                                    : '';
                                    ?>>
                                개인정보 수집·이용에 동의합니다.
                            </label>
                        </div>

                        <div class="public-form-actions">
                            <a
                                href="<?php
                                echo class_share_public_escape(
                                    $event_url
                                );
                                ?>">
                                취소
                            </a>

                            <button type="submit">
                                <?php
                                echo $is_program_application
                                    ? '프로그램 신청'
                                    : '행사 신청';
                                ?>
                            </button>
                        </div>
                    </form>
                </section>
            <?php } ?>
        <?php } ?>
    </main>
<script src="/class-share/assets/application-phone.js?v=20261008-1" defer></script>
</body>
</html>
