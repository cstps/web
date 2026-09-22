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
            event.application_start_at,
            event.application_end_at,
            school.school_name,
            school.slug AS school_slug

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
    exit('해당 행사를 조회할 권한이 없습니다.');
}

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
            ) AS active_application_count,

            (
                SELECT COUNT(*)
                FROM class_share_application AS application
                WHERE application.class_id =
                      class_item.id
            ) AS total_application_count

        FROM class_share_class AS class_item

        WHERE class_item.event_id = ?

        ORDER BY
            class_item.sort_order,
            class_item.class_start_at,
            class_item.id
        ",
        $event_id
    );

if ($classes === false) {
    error_log(
        '[class-share] 수업 목록 조회 실패'
    );

    http_response_code(500);
    exit('수업 목록을 불러올 수 없습니다.');
}

$flash_message =
    isset(
        $_SESSION['class_share_admin_flash']
    )
    ? (string)$_SESSION['class_share_admin_flash']
    : '';

unset(
    $_SESSION['class_share_admin_flash']
);

$status_names =
    array(
        'draft' => '작성 중',
        'published' => '공개',
        'closed' => '신청 마감',
        'cancelled' => '취소',
        'archived' => '보관'
    );

$format_datetime =
    function ($value) {
        if ($value === null || $value === '') {
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

$page_title =
    $event['title'] .
    ' 수업 관리';

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
            전체 신청기간:
            <strong>
                <?php
                echo class_share_escape(
                    $format_datetime(
                        $event['application_start_at']
                    )
                );
                ?>
                ~
                <?php
                echo class_share_escape(
                    $format_datetime(
                        $event['application_end_at']
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

    <?php
    if (
        class_share_admin_can_edit_school(
            $school_id,
            $admin
        )
    ) {
    ?>
        <div class="admin-form-actions">
            <a
                class="admin-secondary-link"
                href="/class-share/admin/class_bulk_template.php?event_id=<?php
                                                                            echo (int)$event_id;
                                                                            ?>">
                CSV 양식 다운로드
            </a>

            <a
                class="admin-secondary-link"
                href="/class-share/admin/class_bulk_upload.php?event_id=<?php
                                                                        echo (int)$event_id;
                                                                        ?>">
                CSV 일괄 등록
            </a>

            <a
                class="admin-primary-link"
                href="/class-share/admin/class_form.php?event_id=<?php
                                                                    echo (int)$event_id;
                                                                    ?>">
                수업 추가
            </a>
        </div>
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
    <?php if (count($classes) === 0) { ?>
        <div class="admin-empty">
            <strong>
                등록된 수업이 없습니다.
            </strong>

            <p>
                수업 추가를 눌러 첫 수업을 등록하세요.
            </p>
        </div>
    <?php } else { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>순서</th>
                        <th>교과</th>
                        <th>수업명</th>
                        <th>교사</th>
                        <th>수업일시</th>
                        <th>장소</th>
                        <th>신청</th>
                        <th>상태</th>
                        <th>관리</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($classes as $class_item) { ?>
                        <?php
                        $status =
                            (string)$class_item['status'];

                        $status_name =
                            isset($status_names[$status])
                            ? $status_names[$status]
                            : $status;
                        ?>

                        <tr>
                            <td>
                                <?php
                                echo
                                (int)$class_item['sort_order'];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $class_item['subject']
                                );
                                ?>
                            </td>

                            <td>
                                <strong>
                                    <?php
                                    echo class_share_escape(
                                        $class_item['title']
                                    );
                                    ?>
                                </strong>

                                <div class="admin-muted">
                                    <?php
                                    echo class_share_escape(
                                        $class_item['target']
                                    );
                                    ?>
                                </div>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $class_item['teacher_name']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $format_datetime(
                                        $class_item['class_start_at']
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $class_item['place']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo
                                (int)$class_item['active_application_count'];
                                ?>
                                /
                                <?php
                                echo
                                (int)$class_item['capacity'];
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
                                <span class="admin-muted">
                                    준비 중
                                </span>
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
