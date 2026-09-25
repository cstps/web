<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

$school_id =
    isset($_GET['school_id'])
    ? (int)$_GET['school_id']
    : 0;

if ($school_id <= 0) {
    http_response_code(400);
    exit('학교 번호가 올바르지 않습니다.');
}

if (
    !class_share_admin_can_view_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 학교를 조회할 권한이 없습니다.');
}

$school_rows =
    pdo_query(
        "
        SELECT
            id,
            school_name,
            slug,
            status
        FROM class_share_school
        WHERE id = ?
        LIMIT 1
        ",
        $school_id
    );

if ($school_rows === false) {
    http_response_code(500);
    exit('학교 정보를 불러올 수 없습니다.');
}

if (!isset($school_rows[0])) {
    http_response_code(404);
    exit('학교를 찾을 수 없습니다.');
}

$school =
    $school_rows[0];

$events =
    pdo_query(
        "
        SELECT
            event.id,
            event.slug,
            event.title,
            event.event_type,
            event.application_mode,
            event.academic_year,
            event.event_start_at,
            event.event_end_at,
            event.status,
            event.created_at,

            (
                SELECT COUNT(*)
                FROM class_share_class AS class_item
                WHERE class_item.event_id = event.id
            ) AS class_count,

            (
                SELECT COUNT(*)
                FROM class_share_notice AS notice
                WHERE notice.event_id = event.id
            ) AS notice_count,

            (
                SELECT COUNT(*)
                FROM class_share_application AS application
                WHERE application.event_id = event.id
            ) AS application_count,

            (
                SELECT COUNT(*)
                FROM class_share_application AS application
                WHERE application.event_id = event.id
                  AND application.status IN (
                      'applied',
                      'approved',
                      'waiting'
                  )
            ) AS active_application_count

        FROM class_share_event AS event

        WHERE event.school_id = ?

        ORDER BY
            event.academic_year DESC,

            CASE event.status
                WHEN 'published' THEN 1
                WHEN 'draft' THEN 2
                WHEN 'closed' THEN 3
                ELSE 4
            END,

            event.id DESC
        ",
        $school_id
    );

if ($events === false) {
    error_log(
        '[class-share] 행사 목록 조회 실패'
    );

    http_response_code(500);
    exit('행사 목록을 불러올 수 없습니다.');
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
        'closed' => '종료',
        'archived' => '보관'
    );

$page_title =
    $school['school_name'] .
    ' 행사 관리';

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
            학교별 연도 행사와 공개 상태를 관리합니다.
        </p>

        <a
            class="admin-back-link"
            href="/class-share/admin/schools.php">
            ← 학교 목록
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
        <a
            class="admin-primary-link"
            href="/class-share/admin/event_form.php?school_id=<?php
                                                                echo (int)$school_id;
                                                                ?>">
            행사 추가
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
    <?php if (count($events) === 0) { ?>
        <div class="admin-empty">
            <strong>
                등록된 행사가 없습니다.
            </strong>

            <p>
                행사 추가를 눌러 첫 행사를 등록하세요.
            </p>
        </div>
    <?php } else { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>학년도</th>
                        <th>행사명</th>
                        <th>행사 유형</th>
                        <th>신청 방식</th>
                        <th>공개 주소</th>
                        <th>프로그램</th>
                        <th>공지</th>
                        <th>유효 / 전체</th>
                        <th>상태</th>
                        <th>관리</th>
                    </tr>
                </thead>

                <?php
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
                        'none' => '안내만',
                        'event' => '행사 직접 신청',
                        'program' => '프로그램 선택'
                    );
                ?>

                <tbody>
                    <?php
                    $can_edit_events =
                        class_share_admin_can_edit_school(
                            $school_id,
                            $admin
                        );
                    ?>

                    <?php foreach ($events as $event) { ?>
                        <?php
                        $status =
                            (string)$event['status'];

                        $status_name =
                            isset($status_names[$status])
                            ? $status_names[$status]
                            : $status;

                        $event_type =
                            (string)$event[
                                'event_type'
                            ];

                        $event_type_name =
                            isset(
                                $event_type_names[
                                    $event_type
                                ]
                            )
                            ? $event_type_names[
                                $event_type
                            ]
                            : $event_type;

                        $application_mode =
                            (string)$event[
                                'application_mode'
                            ];

                        $application_mode_name =
                            isset(
                                $application_mode_names[
                                    $application_mode
                                ]
                            )
                            ? $application_mode_names[
                                $application_mode
                            ]
                            : $application_mode;
                        ?>

                        <tr>
                            <td>
                                <?php
                                echo
                                (int)$event['academic_year'];
                                ?>
                            </td>

                            <td>
                                <strong>
                                    <?php
                                    echo class_share_escape(
                                        $event['title']
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>
                                <span class="admin-status-badge">
                                    <?php
                                    echo class_share_escape(
                                        $event_type_name
                                    );
                                    ?>
                                </span>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $application_mode_name
                                );
                                ?>
                            </td>

                            <td>
                                <code>
                                    /class-share/<?php
                                                    echo class_share_escape(
                                                        $school['slug']
                                                    );
                                                    ?>/<?php
                                                        echo class_share_escape(
                                                            $event['slug']
                                                        );
                                                        ?>
                                </code>
                            </td>

                            <td>
                                <?php
                                if (
                                    $application_mode ===
                                    'program'
                                ) {
                                    echo
                                    (int)$event['class_count'];
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>

                            <td>
                                <?php
                                echo
                                (int)$event['notice_count'];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)$event[
                                    'active_application_count'
                                ];
                                ?>
                                /
                                <?php
                                echo (int)$event[
                                    'application_count'
                                ];
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
                                <div class="admin-table-actions">
                                    <?php if ($can_edit_events) { ?>
                                        <a
                                            class="admin-table-action"
                                            href="/class-share/admin/event_edit.php?event_id=<?php
                                            echo (int)$event['id'];
                                            ?>">
                                            행사 수정
                                        </a>
                                    <?php } ?>

                                    <?php if (
                                        $application_mode ===
                                        'program'
                                    ) { ?>
                                        <a
                                            class="admin-table-action"
                                            href="/class-share/admin/classes.php?event_id=<?php
                                                                                            echo (int)$event['id'];
                                                                                            ?>">
                                            프로그램 관리
                                        </a>
                                    <?php } ?>

                                    <a
                                        class="admin-table-action"
                                        href="/class-share/admin/notices.php?event_id=<?php
                                                                                        echo (int)$event['id'];
                                                                                        ?>">
                                        공지 관리
                                    </a>

                                    <a
                                        class="admin-table-action"
                                        href="/class-share/admin/applications.php?event_id=<?php
                                        echo (int)$event['id'];
                                        ?>">
                                        신청자 관리
                                    </a>
                                </div>
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
