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
    'Referrer-Policy: strict-origin-when-cross-origin'
);

header(
    'Cache-Control: no-store, max-age=0'
);

$school_slug =
    isset($_GET['school'])
    ? trim(
        (string)$_GET['school']
    )
    : '';

$event_slug =
    isset($_GET['event'])
    ? trim(
        (string)$_GET['event']
    )
    : '';

if (
    !class_share_public_valid_slug(
        $school_slug
    ) ||
    (
        $event_slug !== '' &&
        !class_share_public_valid_slug(
            $event_slug
        )
    )
) {
    http_response_code(404);
    $page_error =
        '요청한 학교 행사 주소를 찾을 수 없습니다.';
} else {
    $page_error =
        '';
}

$school =
    null;

$events =
    array();

$event =
    null;

$notices =
    array();

$classes =
    array();

$event_type_names =
    array(
        'class_share' => '수업나눔',
        'school_event' => '학교행사',
        'briefing' => '설명회',
        'experience' => '체험행사',
        'training' => '연수',
        'other' => '기타'
    );

$application_mode_names =
    array(
        'none' => '안내',
        'event' => '행사 신청',
        'program' => '프로그램 선택'
    );

if ($page_error === '') {
    $school_rows =
        pdo_query(
            "
            SELECT
                id,
                school_code,
                school_name,
                slug,
                status

            FROM class_share_school

            WHERE slug = ?
              AND status = 'active'

            LIMIT 1
            ",
            $school_slug
        );

    if ($school_rows === false) {
        http_response_code(500);
        $page_error =
            '학교 정보를 불러올 수 없습니다.';
    } elseif (!isset($school_rows[0])) {
        http_response_code(404);
        $page_error =
            '공개된 학교 행사 안내 페이지를 찾을 수 없습니다.';
    } else {
        $school =
            $school_rows[0];
    }
}

if (
    $page_error === '' &&
    $school !== null &&
    $event_slug === ''
) {
    $events =
        pdo_query(
            "
            SELECT
                id,
                academic_year,
                event_type,
                application_mode,
                slug,
                title,
                subtitle,
                event_start_at,
                event_end_at,
                application_start_at,
                application_end_at,
                status

            FROM class_share_event

            WHERE school_id = ?
              AND status IN (
                  'published',
                  'closed'
              )

            ORDER BY
                academic_year DESC,
                event_start_at DESC,
                id DESC
            ",
            (int)$school['id']
        );

    if ($events === false) {
        http_response_code(500);
        $page_error =
            '공개 행사 목록을 불러올 수 없습니다.';

        $events =
            array();
    }
}

if (
    $page_error === '' &&
    $school !== null &&
    $event_slug !== ''
) {
    $event_rows =
        pdo_query(
            "
            SELECT
                id,
                school_id,
                academic_year,
                event_type,
                application_mode,
                slug,
                title,
                subtitle,
                event_start_at,
                event_end_at,
                application_start_at,
                application_end_at,
                privacy_policy_version,
                privacy_notice,
                retention_until,
                status

            FROM class_share_event

            WHERE school_id = ?
              AND slug = ?
              AND status IN (
                  'published',
                  'closed'
              )

            LIMIT 1
            ",
            (int)$school['id'],
            $event_slug
        );

    if ($event_rows === false) {
        http_response_code(500);
        $page_error =
            '행사 정보를 불러올 수 없습니다.';
    } elseif (!isset($event_rows[0])) {
        http_response_code(404);
        $page_error =
            '공개된 행사를 찾을 수 없습니다.';
    } else {
        $event =
            $event_rows[0];
    }
}

if (
    $page_error === '' &&
    $event !== null
) {
    $notices =
        pdo_query(
            "
            SELECT
                id,
                title,
                content,
                important,
                published_at,
                sort_order

            FROM class_share_notice

            WHERE event_id = ?
              AND status = 'published'

            ORDER BY
                important DESC,
                sort_order,
                published_at DESC,
                id DESC
            ",
            (int)$event['id']
        );

    if ($notices === false) {
        http_response_code(500);
        $page_error =
            '공지사항을 불러올 수 없습니다.';

        $notices =
            array();
    }
}

if (
    $page_error === '' &&
    $event !== null &&
    isset($event['application_mode']) &&
    (string)$event[
        'application_mode'
    ] === 'program'
) {
    $classes =
        pdo_query(
            "
            SELECT
                class_item.id,
                class_item.public_id,
                class_item.subject,
                class_item.title,
                class_item.teacher_name,
                class_item.target,
                class_item.class_start_at,
                class_item.class_end_at,
                class_item.place,
                class_item.application_deadline,
                class_item.capacity,
                class_item.description,
                class_item.sort_order,
                class_item.status,

                (
                    SELECT COUNT(*)
                    FROM class_share_application AS application
                    WHERE application.class_id =
                          class_item.id
                      AND application.status IN (
                          'applied',
                          'approved',
                          'waiting'
                      )
                ) AS active_application_count

            FROM class_share_class AS class_item

            WHERE class_item.event_id = ?
              AND class_item.status IN (
                  'published',
                  'closed'
              )

            ORDER BY
                class_item.sort_order,
                class_item.class_start_at,
                class_item.id
            ",
            (int)$event['id']
        );

    if ($classes === false) {
        http_response_code(500);
        $page_error =
            '수업 목록을 불러올 수 없습니다.';

        $classes =
            array();
    }
}

$observation_accepting = false;

if ($page_error === '' && $event !== null) {
    $observation_rows = pdo_query(
        "
        SELECT event_id
        FROM class_share_observation_setting
        WHERE event_id = ?
          AND enabled = 1
          AND (open_at IS NULL OR open_at <= NOW())
          AND (close_at IS NULL OR close_at >= NOW())
          AND retention_until >= CURRENT_DATE()
        LIMIT 1
        ",
        (int)$event['id']
    );

    if ($observation_rows === false) {
        error_log(
            '[class-share] 참관록 접수 상태 조회 실패: 행사 ' .
            (int)$event['id']
        );
    } else {
        $observation_accepting =
            isset($observation_rows[0]);
    }
}

$page_title =
    $school !== null
    ? (string)$school['school_name']
    : '학교별 행사 안내';

if ($event !== null) {
    $page_title =
        (string)$event['title'] .
        ' | ' .
        (string)$school['school_name'];
}

$now_timestamp =
    (
        new DateTimeImmutable(
            'now',
            new DateTimeZone(
                'Asia/Seoul'
            )
        )
    )->getTimestamp();

?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <link
        rel="stylesheet"
        href="/class-share/assets/public.css?v=20261008-types-1">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

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
        <div>
            <a href="/class-share/<?php
            echo rawurlencode(
                $school_slug
            );
            ?>">
                학교 행사 안내
            </a>

            <?php if ($school !== null) { ?>
                <span>
                    <?php
                    echo class_share_public_escape(
                        $school['school_name']
                    );
                    ?>
                </span>
            <?php } ?>
        </div>
    </header>

    <main>
        <?php if ($page_error !== '') { ?>
            <section>
                <h1>페이지를 찾을 수 없습니다.</h1>

                <p>
                    <?php
                    echo class_share_public_escape(
                        $page_error
                    );
                    ?>
                </p>
            </section>

        <?php } elseif ($event === null) { ?>
            <section>
                <p>학교별 행사 안내</p>

                <h1>
                    <?php
                    echo class_share_public_escape(
                        $school['school_name']
                    );
                    ?>
                </h1>

                <p>
                    공개된 행사를 선택해 주세요.
                </p>
            </section>

            <section>
                <h2>공개 행사</h2>

                <?php if (count($events) === 0) { ?>
                    <p>
                        현재 공개된 행사가 없습니다.
                    </p>
                <?php } else { ?>
                    <?php foreach ($events as $event_item) { ?>
                        <?php
                        $event_item_type =
                            (string)$event_item[
                                'event_type'
                            ];

                        $event_item_type_name =
                            isset(
                                $event_type_names[
                                    $event_item_type
                                ]
                            )
                            ? $event_type_names[
                                $event_item_type
                            ]
                            : $event_item_type;
                        ?>

                        <article>
                            <p>
                                <span class="public-badge public-event-type" data-event-type="<?php echo class_share_public_escape(isset($event_type_names[$event_item_type]) ? $event_item_type : 'other'); ?>">
                                    <?php
                                    echo class_share_public_escape(
                                        $event_item_type_name
                                    );
                                    ?>
                                </span>
                                ·
                                <?php
                                echo (int)$event_item['academic_year'];
                                ?>학년도
                                ·
                                <?php
                                echo (string)$event_item['status'] === 'closed'
                                    ? '종료'
                                    : '진행 중';
                                ?>
                            </p>

                            <h3>
                                <a href="<?php
                                            echo class_share_public_escape(
                                                class_share_public_event_url(
                                                    $school['slug'],
                                                    $event_item['slug']
                                                )
                                            );
                                            ?>">
                                    <?php
                                    echo class_share_public_escape(
                                        $event_item['title']
                                    );
                                    ?>
                                </a>
                            </h3>

                            <?php
                            if (
                                trim(
                                    (string)$event_item['subtitle']
                                ) !== ''
                            ) {
                            ?>
                                <p>
                                    <?php
                                    echo class_share_public_escape(
                                        $event_item['subtitle']
                                    );
                                    ?>
                                </p>
                            <?php } ?>

                            <p>
                                행사:
                                <?php
                                echo class_share_public_escape(
                                    class_share_public_format_datetime(
                                        $event_item['event_start_at']
                                    )
                                );
                                ?>
                                ~
                                <?php
                                echo class_share_public_escape(
                                    class_share_public_format_datetime(
                                        $event_item['event_end_at']
                                    )
                                );
                                ?>
                            </p>
                        </article>
                    <?php } ?>
                <?php } ?>
            </section>

        <?php } else { ?>
            <?php
            $current_event_type =
                (string)$event['event_type'];

            $current_event_type_name =
                isset(
                    $event_type_names[
                        $current_event_type
                    ]
                )
                ? $event_type_names[
                    $current_event_type
                ]
                : $current_event_type;

            $current_application_mode =
                (string)$event[
                    'application_mode'
                ];

            $current_application_mode_name =
                isset(
                    $application_mode_names[
                        $current_application_mode
                    ]
                )
                ? $application_mode_names[
                    $current_application_mode
                ]
                : $current_application_mode;
            ?>

            <section>
                <p>
                    <span class="public-badge public-event-type" data-event-type="<?php echo class_share_public_escape(isset($event_type_names[$current_event_type]) ? $current_event_type : 'other'); ?>">
                        <?php
                        echo class_share_public_escape(
                            $current_event_type_name
                        );
                        ?>
                    </span>
                    ·
                    <?php
                    echo (int)$event['academic_year'];
                    ?>학년도
                    ·
                    <?php
                    echo (string)$event['status'] ===
                        'closed'
                        ? '종료된 행사'
                        : '공개 행사';
                    ?>
                </p>

                <h1>
                    <?php
                    echo class_share_public_escape(
                        $event['title']
                    );
                    ?>
                </h1>

                <?php
                if (
                    trim(
                        (string)$event['subtitle']
                    ) !== ''
                ) {
                ?>
                    <p>
                        <?php
                        echo class_share_public_escape(
                            $event['subtitle']
                        );
                        ?>
                    </p>
                <?php } ?>

                <dl>
                    <dt>행사 운영</dt>
                    <dd>
                        <?php
                        echo class_share_public_escape(
                            class_share_public_format_datetime(
                                $event['event_start_at']
                            )
                        );
                        ?>
                        ~
                        <?php
                        echo class_share_public_escape(
                            class_share_public_format_datetime(
                                $event['event_end_at']
                            )
                        );
                        ?>
                    </dd>

                    <dt>신청 방식</dt>
                    <dd>
                        <?php
                        echo class_share_public_escape(
                            $current_application_mode_name
                        );
                        ?>
                    </dd>

                    <?php if (
                        $current_application_mode !== 'none'
                    ) { ?>
                    <dt>전체 신청기간</dt>
                    <dd>
                        <?php
                        echo class_share_public_escape(
                            class_share_public_format_datetime(
                                $event['application_start_at']
                            )
                        );
                        ?>
                        ~
                        <?php
                        echo class_share_public_escape(
                            class_share_public_format_datetime(
                                $event['application_end_at']
                            )
                        );
                        ?>
                    </dd>
                    <?php } ?>
                </dl>
            </section>

            <?php if (count($notices) > 0) { ?>
                <section>
                    <h2>공지사항</h2>

                    <?php foreach ($notices as $notice) { ?>
                        <article>
                            <p>
                                <?php
                                echo (int)$notice['important'] === 1
                                    ? '중요 공지'
                                    : '공지';
                                ?>
                                ·
                                <?php
                                echo class_share_public_escape(
                                    class_share_public_format_datetime(
                                        $notice['published_at']
                                    )
                                );
                                ?>
                            </p>

                            <h3>
                                <?php
                                echo class_share_public_escape(
                                    $notice['title']
                                );
                                ?>
                            </h3>

                            <div>
                                <?php
                                echo class_share_content_sanitize_html(
                                    $notice['content'], true
                                );
                                ?>
                            </div>
                        </article>
                    <?php } ?>
                </section>
            <?php } ?>

            <?php if (
                $current_application_mode ===
                'program'
            ) { ?>
            <section>
                <h2>
                    <?php
                    echo $current_event_type ===
                        'class_share'
                        ? '공개 수업'
                        : '세부 프로그램';
                    ?>
                </h2>

                <p>
                    총
                    <?php echo count($classes); ?>개의
                    <?php
                    echo $current_event_type ===
                        'class_share'
                        ? '수업'
                        : '프로그램';
                    ?>이 있습니다.
                </p>

                <?php if (count($classes) === 0) { ?>
                    <p>
                        현재 공개된
                        <?php
                        echo $current_event_type ===
                            'class_share'
                            ? '수업'
                            : '프로그램';
                        ?>이 없습니다.
                    </p>
                <?php } else { ?>
                    <div class="public-program-grid">
                    <?php foreach ($classes as $class_item) { ?>
                        <?php
                        $is_available =
                            class_share_public_is_class_available(
                                $class_item,
                                $event,
                                $now_timestamp
                            );

                        $remaining =
                            max(
                                0,
                                (int)$class_item['capacity'] -
                                    (int)$class_item['active_application_count']
                            );
                        ?>

                        <article class="public-program-card">
                            <p class="public-program-subject">
                                <?php
                                echo class_share_public_escape(
                                    $class_item['subject']
                                );
                                ?>
                            </p>

                            <h3>
                                <?php
                                echo class_share_public_escape(
                                    $class_item['title']
                                );
                                ?>
                            </h3>

                            <dl>
                                <dt>
                                    <?php
                                    echo $current_event_type ===
                                        'class_share'
                                        ? '교사'
                                        : '담당자';
                                    ?>
                                </dt>
                                <dd>
                                    <?php
                                    echo class_share_public_escape(
                                        $class_item['teacher_name']
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

                                <dt>
                                    <?php
                                    echo $current_event_type ===
                                        'class_share'
                                        ? '수업일시'
                                        : '운영일시';
                                    ?>
                                </dt>
                                <dd>
                                    <?php
                                    echo class_share_public_escape(
                                        class_share_public_format_datetime(
                                            $class_item['class_start_at']
                                        )
                                    );
                                    ?>
                                    <?php
                                    if (
                                        $class_item['class_end_at'] !== null
                                    ) {
                                    ?>
                                        ~
                                        <?php
                                        echo class_share_public_escape(
                                            class_share_public_format_datetime(
                                                $class_item['class_end_at']
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

                                <dt>신청 마감</dt>
                                <dd>
                                    <?php
                                    echo class_share_public_escape(
                                        class_share_public_format_datetime(
                                            $class_item['application_deadline']
                                        )
                                    );
                                    ?>
                                </dd>

                                <dt>모집 현황</dt>
                                <dd>
                                    <?php echo $remaining === 0
                                        ? '모집 마감'
                                        : '잔여 ' . $remaining . '명'; ?>
                                </dd>
                            </dl>

                            <?php
                            if (
                                trim(
                                    (string)$class_item['description']
                                ) !== ''
                            ) {
                            ?>
                                <div class="public-program-description">
                                    <?php
                                    echo class_share_content_sanitize_html(
                                        $class_item['description']
                                    );
                                    ?>
                                </div>
                            <?php } ?>

                            <?php if ($is_available) { ?>
                                <p class="public-program-action">
                                    <a
                                        class="public-button"
                                        href="/class-share/apply.php?school=<?php
                                        echo rawurlencode(
                                            $school['slug']
                                        );
                                        ?>&amp;event=<?php
                                        echo rawurlencode(
                                            $event['slug']
                                        );
                                        ?>&amp;class=<?php
                                        echo rawurlencode(
                                            $class_item[
                                                'public_id'
                                            ]
                                        );
                                        ?>">
                                        프로그램 신청
                                    </a>
                                </p>
                            <?php } else { ?>
                                <p class="public-program-action">
                                    신청 마감
                                </p>
                            <?php } ?>
                        </article>
                    <?php } ?>
                    </div>
                <?php } ?>
            </section>

            <?php } elseif (
                $current_application_mode ===
                'event'
            ) { ?>
                <section>
                    <h2>행사 신청</h2>

                    <p>
                        이 행사는 행사 전체에 직접 신청하는 방식입니다.
                    </p>

                    <p>
                        <a
                            class="public-button"
                            href="/class-share/apply.php?school=<?php
                            echo rawurlencode(
                                $school['slug']
                            );
                            ?>&amp;event=<?php
                            echo rawurlencode(
                                $event['slug']
                            );
                            ?>">
                            행사 신청
                        </a>
                    </p>
                </section>
            <?php } else { ?>
                <section>
                    <h2>참여 안내</h2>

                    <p>
                        이 행사는 별도의 온라인 신청 없이 안내 내용을 확인하는 행사입니다.
                    </p>
                </section>
            <?php } ?>

            <?php if (
                $current_application_mode !==
                'none'
            ) { ?>
                <section>
                    <h2>신청 확인·취소</h2>

                    <p>
                        신청할 때 입력한 연락처와 신청 비밀번호로 신청 내역을 확인하거나 취소할 수 있습니다.
                    </p>

                    <p>
                        <a
                            class="public-button"
                            href="/class-share/applications.php?school=<?php
                            echo rawurlencode(
                                $school['slug']
                            );
                            ?>&amp;event=<?php
                            echo rawurlencode(
                                $event['slug']
                            );
                            ?>">
                            신청 확인·취소
                        </a>
                    </p>
                </section>
            <?php } ?>

            <?php if ($observation_accepting) { ?>
                <section>
                    <h2>참관록 작성</h2>

                    <p>
                        행사 신청 여부와 관계없이 참관 내용을 기록할 수 있습니다.
                    </p>

                    <p>
                        <a
                            class="public-button"
                            href="/class-share/observation.php?school=<?php
                            echo rawurlencode($school['slug']);
                            ?>&amp;event=<?php
                            echo rawurlencode($event['slug']);
                            ?>">
                            참관록 작성
                        </a>
                    </p>
                </section>
            <?php } ?>
        <?php } ?>
    </main>

    <footer class="public-footer">
        <p>
            운영자 : GTKBS
            computer science teacher(경남온라인학교)
            since 2026
        </p>
    </footer>
</body>

</html>