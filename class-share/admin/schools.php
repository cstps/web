<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

if (
    !class_share_admin_is_super_admin(
        $admin
    )
) {
    http_response_code(403);
    exit('학교 관리 권한이 없습니다.');
}

$schools =
    pdo_query(
        "
        SELECT
            school.id,
            school.school_code,
            school.school_name,
            school.slug,
            school.page_title,
            school.status,
            school.created_at,

            (
                SELECT COUNT(*)
                FROM class_share_event AS event
                WHERE event.school_id = school.id
            ) AS event_count,

            (
                SELECT COUNT(*)
                FROM class_share_admin_school AS assignment
                WHERE assignment.school_id = school.id
                  AND assignment.active = 1
            ) AS admin_count

        FROM class_share_school AS school

        ORDER BY
            CASE school.status
                WHEN 'active' THEN 1
                WHEN 'inactive' THEN 2
                ELSE 3
            END,
            school.school_name,
            school.id
        "
    );

if ($schools === false) {
    error_log(
        '[class-share] 학교 목록 조회 실패'
    );

    http_response_code(500);
    exit('학교 목록을 불러올 수 없습니다.');
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
        'active' => '운영 중',
        'inactive' => '비활성',
        'archived' => '보관'
    );

$page_title =
    '학교 관리';

$active_menu =
    'schools';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
<div class="admin-toolbar">
    <p class="admin-muted">
        학교별 공개 주소와 운영 상태를 관리합니다.
    </p>

    <a
        class="admin-primary-link"
        href="/class-share/admin/school_form.php">
        학교 추가
    </a>
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
    <?php if (count($schools) === 0) { ?>
        <div class="admin-empty">
            <strong>
                등록된 학교가 없습니다.
            </strong>

            <p>
                ‘학교 추가’를 눌러 첫 학교를 등록하세요.
            </p>
        </div>
    <?php } else { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>학교명</th>
                        <th>학교 코드</th>
                        <th>공개 주소</th>
                        <th>행사 수</th>
                        <th>관리자 수</th>
                        <th>상태</th>
                        <th>관리</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($schools as $school) { ?>
                        <?php
                        $status =
                            (string)$school['status'];

                        $status_name =
                            isset($status_names[$status])
                            ? $status_names[$status]
                            : $status;
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?php
                                    echo class_share_escape(
                                        $school['school_name']
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $school['school_code'] !== null
                                        ? $school['school_code']
                                        : '-'
                                );
                                ?>
                            </td>

                            <td>
                                <code>
                                    /class-share/<?php
                                                    echo class_share_escape(
                                                        $school['slug']
                                                    );
                                                    ?>
                                </code>
                            </td>

                            <td>
                                <?php
                                echo (int)$school['event_count'];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)$school['admin_count'];
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
                                <a
                                    class="admin-table-action"
                                    href="/class-share/admin/school_form.php?id=<?php
                                                                                echo (int)$school['id'];
                                                                                ?>">
                                    수정
                                </a>
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
