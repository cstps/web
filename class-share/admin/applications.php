<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

$event_id =
    isset($_GET['event_id'])
    ? (int)$_GET['event_id']
    : 0;

if ($event_id <= 0) {
    http_response_code(400);
    exit('행사 번호가 올바르지 않습니다.');
}

$event_rows =
    pdo_query(
        "
        SELECT
            event.id,
            event.school_id,
            event.title,
            event.academic_year,
            event.application_mode,
            event.application_capacity,
            event.status,
            event.event_start_at,
            event.event_end_at,
            school.school_name

        FROM class_share_event AS event

        INNER JOIN class_share_school AS school
            ON school.id = event.school_id

        WHERE event.id = ?

        LIMIT 1
        ",
        $event_id
    );

if ($event_rows === false) {
    http_response_code(500);
    exit('행사 정보를 불러올 수 없습니다.');
}

if (!isset($event_rows[0])) {
    http_response_code(404);
    exit('행사를 찾을 수 없습니다.');
}

$event =
    $event_rows[0];

$school_id =
    (int)$event['school_id'];

if (
    !class_share_admin_can_view_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 행사의 신청자를 조회할 권한이 없습니다.');
}

$can_export =
    class_share_admin_can_view_sensitive_school(
        $school_id,
        $admin
    );

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

$requested_status =
    isset($_GET['status'])
    ? trim((string)$_GET['status'])
    : '';

$status_filter =
    array_key_exists(
        $requested_status,
        $status_names
    )
    ? $requested_status
    : '';

$page =
    isset($_GET['page'])
    ? (int)$_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

$per_page =
    50;

$status_counts =
    array(
        'applied' => 0,
        'approved' => 0,
        'waiting' => 0,
        'rejected' => 0,
        'attended' => 0,
        'absent' => 0,
        'cancelled' => 0
    );

$summary_rows =
    pdo_query(
        "
        SELECT
            status,
            COUNT(*) AS application_count

        FROM class_share_application

        WHERE event_id = ?

        GROUP BY status
        ",
        $event_id
    );

if ($summary_rows === false) {
    http_response_code(500);
    exit('신청자 집계를 불러올 수 없습니다.');
}

foreach ($summary_rows as $summary_row) {
    $summary_status =
        (string)$summary_row['status'];

    if (
        array_key_exists(
            $summary_status,
            $status_counts
        )
    ) {
        $status_counts[$summary_status] =
            (int)$summary_row[
                'application_count'
            ];
    }
}

$total_count =
    array_sum(
        $status_counts
    );

$active_count =
    $status_counts['applied'] +
    $status_counts['approved'] +
    $status_counts['waiting'];

if ($status_filter === '') {
    $filtered_count =
        $total_count;
} else {
    $filtered_count =
        $status_counts[
            $status_filter
        ];
}

$total_pages =
    max(
        1,
        (int)ceil(
            $filtered_count /
            $per_page
        )
    );

if ($page > $total_pages) {
    $page =
        $total_pages;
}

$offset =
    ($page - 1) *
    $per_page;

$status_condition =
    $status_filter === ''
    ? ''
    : ' AND application.status = ?';

$application_sql =
    "
    SELECT
        application.id,
        application.application_code,
        application.application_scope,
        application.class_id,
        application.applicant_name,
        application.applicant_school,
        application.phone_last4,
        application.privacy_destroyed_at,
        application.status,
        application.created_at,
        application.cancelled_at,
        application.processed_by,
        application.admin_note,

        class_item.title AS program_title

    FROM class_share_application AS application

    LEFT JOIN class_share_class AS class_item
        ON class_item.id =
           application.class_id

    WHERE application.event_id = ?
    " .
    $status_condition .
    "
    ORDER BY
        application.created_at DESC,
        application.id DESC

    LIMIT " .
    (int)$per_page .
    "

    OFFSET " .
    (int)$offset;

if ($status_filter === '') {
    $applications =
        pdo_query(
            $application_sql,
            $event_id
        );
} else {
    $applications =
        pdo_query(
            $application_sql,
            $event_id,
            $status_filter
        );
}

if ($applications === false) {
    error_log(
        '[class-share] 신청자 목록 조회 실패'
    );

    http_response_code(500);
    exit('신청자 목록을 불러올 수 없습니다.');
}

$format_datetime =
    function ($value) {
        if (
            $value === null ||
            $value === ''
        ) {
            return '-';
        }

        $timestamp =
            strtotime(
                (string)$value
            );

        if ($timestamp === false) {
            return (string)$value;
        }

        return date(
            'Y-m-d H:i',
            $timestamp
        );
    };

$build_page_url =
    function ($target_page) use (
        $event_id,
        $status_filter
    ) {
        $url =
            '/class-share/admin/applications.php?event_id=' .
            rawurlencode(
                (string)$event_id
            );

        if ($status_filter !== '') {
            $url .=
                '&status=' .
                rawurlencode(
                    $status_filter
                );
        }

        return
            $url .
            '&page=' .
            rawurlencode(
                (string)$target_page
            );
    };

$page_title =
    $event['title'] .
    ' 신청자 관리';

$active_menu =
    'schools';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
<div class="admin-toolbar">
    <div>
        <p class="admin-muted">
            <?php
            echo class_share_escape(
                $event['school_name']
            );
            ?>
            ·
            <?php
            echo (int)$event['academic_year'];
            ?>학년도
        </p>

        <p class="admin-muted">
            행사 운영:
            <strong>
                <?php
                echo class_share_escape(
                    $format_datetime(
                        $event['event_start_at']
                    )
                );
                ?>
                ~
                <?php
                echo class_share_escape(
                    $format_datetime(
                        $event['event_end_at']
                    )
                );
                ?>
            </strong>
        </p>

        <p class="admin-muted">
            전체
            <strong>
                <?php echo $total_count; ?>명
            </strong>
            · 유효
            <strong>
                <?php echo $active_count; ?>명
            </strong>
            · 취소
            <strong>
                <?php
                echo $status_counts[
                    'cancelled'
                ];
                ?>명
            </strong>
        </p>

        <a
            class="admin-back-link"
            href="/class-share/admin/events.php?school_id=<?php
            echo (int)$school_id;
            ?>">
            ← 행사 목록
        </a>
    </div>

    <?php if ($can_export) { ?>
        <form
            method="post"
            action="/class-share/admin/applications_export.php"
            class="admin-inline-form">

            <?php
            echo class_share_admin_csrf_input();
            ?>

            <input
                type="hidden"
                name="event_id"
                value="<?php echo (int)$event_id; ?>">

            <input
                type="hidden"
                name="status"
                value="<?php
                echo class_share_escape(
                    $status_filter
                );
                ?>">

            <button
                type="submit"
                class="admin-submit-button">
                신청자 CSV 내려받기
            </button>
        </form>
    <?php } ?>
</div>

<section class="admin-panel">
    <form
        method="get"
        action="/class-share/admin/applications.php"
        class="admin-filter-form">

        <input
            type="hidden"
            name="event_id"
            value="<?php echo (int)$event_id; ?>">

            <div class="admin-field">
                <label for="status">
                    신청 상태
                </label>

                <select
                    id="status"
                    name="status">

                    <option value="">
                        전체 (<?php echo $total_count; ?>)
                    </option>

                    <?php foreach ($status_names as $value => $label) { ?>
                        <option
                            value="<?php
                            echo class_share_escape(
                                $value
                            );
                            ?>"<?php
                            echo $status_filter === $value
                                ? ' selected'
                                : '';
                            ?>>
                            <?php
                            echo class_share_escape(
                                $label
                            );
                            ?>
                            (<?php
                            echo $status_counts[$value];
                            ?>)
                        </option>
                    <?php } ?>
                </select>
            </div>

        <div class="admin-form-actions">
            <button
                class="admin-submit-button"
                type="submit">
                필터 적용
            </button>
        </div>
    </form>

    <p class="admin-muted">
        선택한 조건:
        <strong>
            <?php
            echo $status_filter === ''
                ? '전체'
                : class_share_escape(
                    $status_names[
                        $status_filter
                    ]
                );
            ?>
        </strong>
        ·
        <?php echo $filtered_count; ?>건
    </p>
</section>

<section class="admin-panel">
    <?php if (count($applications) === 0) { ?>
        <div class="admin-empty">
            <strong>신청 내역이 없습니다.</strong>

            <p>
                아직 이 행사에 접수된 신청이 없습니다.
            </p>
        </div>
    <?php } else { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>관리</th>
                        <th>신청일시</th>
                        <th>신청 고유번호</th>
                        <th>구분</th>
                        <th>행사·프로그램</th>
                        <th>성명</th>
                        <th>소속</th>
                        <th>연락처</th>
                        <th>상태</th>
                        <th>취소일시</th>
                    </tr>
                </thead>

                <tbody>
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

                        $is_program =
                            (string)$application[
                                'application_scope'
                            ] === 'program';

                        $privacy_destroyed =
                            $application[
                                'privacy_destroyed_at'
                            ] !== null;

                        $application_code_display =
                            $privacy_destroyed
                            ? '—'
                            : (string)$application[
                                'application_code'
                            ];

                        $applicant_name_display =
                            $privacy_destroyed
                            ? '개인정보 파기 완료'
                            : (string)$application[
                                'applicant_name'
                            ];

                        $applicant_school_display =
                            $privacy_destroyed
                            ? '—'
                            : (string)$application[
                                'applicant_school'
                            ];

                        $phone_display =
                            $privacy_destroyed
                            ? '—'
                            : '***-****-' .
                                (string)$application[
                                    'phone_last4'
                                ];
                        ?>

                        <tr>
                            <td>
                                <a
                                    class="admin-table-action"
                                    href="/class-share/admin/application_edit.php?application_id=<?php
                                    echo (int)$application[
                                        'id'
                                    ];
                                    ?>">
                                    상세
                                </a>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $format_datetime(
                                        $application[
                                            'created_at'
                                        ]
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <code>
                                    <?php
                                    echo class_share_escape(
                                        $application_code_display
                                    );
                                    ?>
                                </code>
                            </td>

                            <td>
                                <?php
                                echo $is_program
                                    ? '프로그램'
                                    : '행사';
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $is_program &&
                                    $application[
                                        'program_title'
                                    ] !== null
                                    ? $application[
                                        'program_title'
                                    ]
                                    : $event['title']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $applicant_name_display
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $applicant_school_display
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $phone_display
                                );
                                ?>
                            </td>

                            <td>
                                <span class="admin-status-badge">
                                    <?php
                                    echo class_share_escape(
                                        $status_name
                                    );
                                    ?>
                                </span>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $format_datetime(
                                        $application[
                                            'cancelled_at'
                                        ]
                                    )
                                );
                                ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1) { ?>
            <div class="admin-toolbar">
                <p class="admin-muted">
                    <?php echo $page; ?>
                    /
                    <?php echo $total_pages; ?>
                    페이지
                </p>

                <div class="admin-table-actions">
                    <?php if ($page > 1) { ?>
                        <a
                            class="admin-table-action"
                            href="<?php
                            echo class_share_escape(
                                $build_page_url(
                                    $page - 1
                                )
                            );
                            ?>">
                            ← 이전
                        </a>
                    <?php } ?>

                    <?php if ($page < $total_pages) { ?>
                        <a
                            class="admin-table-action"
                            href="<?php
                            echo class_share_escape(
                                $build_page_url(
                                    $page + 1
                                )
                            );
                            ?>">
                            다음 →
                        </a>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
