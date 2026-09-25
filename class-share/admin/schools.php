<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

$is_super_admin =
    class_share_admin_is_super_admin(
        $admin
    );

if ($is_super_admin) {
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

                NULL AS assigned_role,

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
} else {
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

                assignment.role AS assigned_role,

                (
                    SELECT COUNT(*)
                    FROM class_share_event AS event
                    WHERE event.school_id = school.id
                ) AS event_count,

                (
                    SELECT COUNT(*)
                    FROM class_share_admin_school AS school_assignment
                    WHERE school_assignment.school_id = school.id
                      AND school_assignment.active = 1
                ) AS admin_count

            FROM class_share_admin_school AS assignment

            INNER JOIN class_share_school AS school
                ON school.id =
                   assignment.school_id

            WHERE assignment.admin_id = ?
              AND assignment.active = 1

            ORDER BY
                CASE school.status
                    WHEN 'active' THEN 1
                    WHEN 'inactive' THEN 2
                    ELSE 3
                END,
                school.school_name,
                school.id
            ",
            (int)$admin['id']
        );
}

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

$role_names =
    array(
        'school_admin' => '학교 관리자',
        'editor' => '편집자',
        'viewer' => '조회자'
    );

$page_title =
    '학교·행사';

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

    <?php if ($is_super_admin) { ?>
        <a
            class="admin-primary-link"
            href="/class-share/admin/school_form.php">
            학교 추가
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
    <?php if (count($schools) === 0) { ?>
        <div class="admin-empty">
            <strong>
                등록된 학교가 없습니다.
            </strong>

            <p>
                <?php if ($is_super_admin) { ?>
                    ‘학교 추가’를 눌러 첫 학교를 등록하세요.
                <?php } else { ?>
                    현재 계정에 배정된 학교가 없습니다.
                    최고관리자에게 학교 배정을 요청해 주세요.
                <?php } ?>
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
                        <th>내 역할</th>
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

                        $assigned_role =
                            $school['assigned_role'] === null
                            ? ''
                            : (string)$school['assigned_role'];

                        $role_name =
                            $is_super_admin
                            ? '최고관리자'
                            : (
                                isset($role_names[$assigned_role])
                                ? $role_names[$assigned_role]
                                : $assigned_role
                            );
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
                                <?php
                                echo class_share_escape(
                                    $role_name
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
                                <div class="admin-table-actions">
                                    <a
                                        class="admin-table-action"
                                        href="/class-share/admin/events.php?school_id=<?php
                                                                                        echo (int)$school['id'];
                                                                                        ?>">
                                        행사 관리
                                    </a>

                                    <?php if (
                                        class_share_admin_can_manage_school(
                                            (int)$school['id'],
                                            $admin
                                        )
                                    ) { ?>
                                        <a
                                            class="admin-table-action"
                                            href="/class-share/admin/school_form.php?id=<?php
                                            echo (int)$school['id'];
                                            ?>">
                                            학교 수정
                                        </a>
                                    <?php } ?>
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
