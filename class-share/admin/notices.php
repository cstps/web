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
    exit('해당 행사의 공지를 조회할 권한이 없습니다.');
}

$notices =
    pdo_query(
        "
        SELECT
            id,
            title,
            important,
            status,
            published_at,
            sort_order,
            created_by,
            updated_by,
            created_at,
            updated_at

        FROM class_share_notice

        WHERE event_id = ?

        ORDER BY
            important DESC,
            sort_order,
            published_at DESC,
            id DESC
        ",
        $event_id
    );

if ($notices === false) {
    error_log(
        '[class-share] 공지 목록 조회 실패'
    );

    http_response_code(500);
    exit('공지 목록을 불러올 수 없습니다.');
}

$flash_message =
    isset(
        $_SESSION[
            'class_share_admin_flash'
        ]
    )
    ? (string)$_SESSION[
        'class_share_admin_flash'
    ]
    : '';

unset(
    $_SESSION[
        'class_share_admin_flash'
    ]
);

$status_names =
    array(
        'draft' => '작성 중',
        'published' => '공개',
        'archived' => '보관'
    );

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

$can_add_notice =
    class_share_admin_can_edit_school(
        $school_id,
        $admin
    ) &&
    !in_array(
        (string)$event['status'],
        array(
            'cancelled',
            'archived'
        ),
        true
    );

$page_title =
    $event['title'] .
    ' 공지 관리';

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

        <a
            class="admin-back-link"
            href="/class-share/admin/events.php?school_id=<?php
            echo (int)$school_id;
            ?>">
            ← 행사 목록
        </a>
    </div>

    <?php if ($can_add_notice) { ?>
        <a
            class="admin-primary-link"
            href="/class-share/admin/notice_form.php?event_id=<?php
            echo (int)$event_id;
            ?>">
            공지 추가
        </a>
    <?php } ?>
</div>

<?php if ($flash_message !== '') { ?>
    <div
        class="admin-flash"
        role="status">

        <?php
        echo class_share_escape(
            $flash_message
        );
        ?>
    </div>
<?php } ?>

<section class="admin-panel">
    <?php if (count($notices) === 0) { ?>
        <div class="admin-empty">
            <strong>
                등록된 공지가 없습니다.
            </strong>

            <p>
                공지 추가를 눌러 첫 공지를 작성하세요.
            </p>
        </div>
    <?php } else { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>순서</th>
                        <th>중요</th>
                        <th>제목</th>
                        <th>상태</th>
                        <th>게시일</th>
                        <th>최근 수정</th>
                        <th>관리</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($notices as $notice) { ?>
                        <?php
                        $status =
                            (string)$notice['status'];

                        $status_name =
                            isset($status_names[$status])
                            ? $status_names[$status]
                            : $status;
                        ?>

                        <tr>
                            <td>
                                <?php
                                echo (int)$notice[
                                    'sort_order'
                                ];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)$notice[
                                    'important'
                                ] === 1
                                ? '중요'
                                : '-';
                                ?>
                            </td>

                            <td>
                                <strong>
                                    <?php
                                    echo class_share_escape(
                                        $notice['title']
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $status_name
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $format_datetime(
                                        $notice[
                                            'published_at'
                                        ]
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $format_datetime(
                                        $notice[
                                            'updated_at'
                                        ]
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                if (
                                    class_share_admin_can_edit_school(
                                        $school_id,
                                        $admin
                                    )
                                ) {
                                    ?>
                                    <a
                                        class="admin-table-action"
                                        href="/class-share/admin/notice_edit.php?notice_id=<?php
                                        echo (int)$notice['id'];
                                        ?>">
                                        수정
                                    </a>
                                <?php } else { ?>
                                    <span class="admin-muted">
                                        -
                                    </span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
