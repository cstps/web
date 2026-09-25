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
    exit('관리자 계정을 조회할 권한이 없습니다.');
}

$admins =
    pdo_query(
        "
        SELECT
            id,
            login_id,
            display_name,
            email,
            is_super_admin,
            status,
            failed_login_count,
            locked_until,
            last_login_at,
            password_changed_at,
            created_at,
            updated_at

        FROM class_share_admin

        ORDER BY
            is_super_admin DESC,
            CASE status
                WHEN 'active' THEN 1
                WHEN 'locked' THEN 2
                ELSE 3
            END,
            display_name,
            id
        "
    );

if ($admins === false) {
    http_response_code(500);
    exit('관리자 계정 목록을 불러올 수 없습니다.');
}

$assignment_rows =
    pdo_query(
        "
        SELECT
            assignment.admin_id,
            assignment.school_id,
            assignment.role,
            assignment.active,
            school.school_name

        FROM class_share_admin_school AS assignment

        INNER JOIN class_share_school AS school
            ON school.id =
               assignment.school_id

        ORDER BY
            assignment.admin_id,
            assignment.active DESC,
            school.school_name
        "
    );

if ($assignment_rows === false) {
    http_response_code(500);
    exit('학교 배정 목록을 불러올 수 없습니다.');
}

$assignments_by_admin =
    array();

foreach ($assignment_rows as $assignment) {
    $assigned_admin_id =
        (int)$assignment['admin_id'];

    if (
        !isset(
            $assignments_by_admin[
                $assigned_admin_id
            ]
        )
    ) {
        $assignments_by_admin[
            $assigned_admin_id
        ] =
            array();
    }

    $assignments_by_admin[
        $assigned_admin_id
    ][] =
        $assignment;
}

$status_names =
    array(
        'active' => '활성',
        'locked' => '잠금',
        'disabled' => '비활성'
    );

$role_names =
    array(
        'school_admin' => '학교 관리자',
        'editor' => '편집자',
        'viewer' => '조회자'
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

        return $timestamp === false
            ? (string)$value
            : date(
                'Y-m-d H:i',
                $timestamp
            );
    };

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

$page_title =
    '관리자 계정';

$active_menu =
    'admins';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
<div class="admin-toolbar">
    <div>
        <p class="admin-muted">
            학교 행사 관리자 계정과 학교별 역할을 관리합니다.
        </p>
    </div>

    <a class="admin-primary-link"
       href="/class-share/admin/admin_form.php">
        관리자 추가
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
    <?php if (count($admins) === 0) { ?>
        <div class="admin-empty">
            <strong>관리자 계정이 없습니다.</strong>
        </div>
    <?php } else { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>로그인 ID</th>
                        <th>표시 이름</th>
                        <th>이메일</th>
                        <th>구분</th>
                        <th>배정 학교·역할</th>
                        <th>상태</th>
                        <th>최근 로그인</th>
                        <th>생성일</th>
                        <th>관리</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($admins as $account) { ?>
                        <?php
                        $account_id =
                            (int)$account['id'];

                        $account_status =
                            (string)$account['status'];

                        $assignments =
                            isset(
                                $assignments_by_admin[
                                    $account_id
                                ]
                            )
                            ? $assignments_by_admin[
                                $account_id
                            ]
                            : array();
                        ?>

                        <tr>
                            <td>
                                <code>
                                    <?php
                                    echo class_share_escape(
                                        $account[
                                            'login_id'
                                        ]
                                    );
                                    ?>
                                </code>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $account[
                                        'display_name'
                                    ]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $account['email'] === null
                                    ? '-'
                                    : $account['email']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)$account[
                                    'is_super_admin'
                                ] === 1
                                    ? '최고관리자'
                                    : '학교별 관리자';
                                ?>
                            </td>

                            <td>
                                <?php if (count($assignments) === 0) { ?>
                                    <span class="admin-muted">-</span>
                                <?php } else { ?>
                                    <?php foreach ($assignments as $assignment) { ?>
                                        <div>
                                            <?php
                                            echo class_share_escape(
                                                $assignment[
                                                    'school_name'
                                                ]
                                            );
                                            ?>
                                            ·
                                            <?php
                                            $role =
                                                (string)$assignment[
                                                    'role'
                                                ];

                                            echo class_share_escape(
                                                isset($role_names[$role])
                                                ? $role_names[$role]
                                                : $role
                                            );

                                            if (
                                                (int)$assignment[
                                                    'active'
                                                ] !== 1
                                            ) {
                                                echo ' · 해제됨';
                                            }
                                            ?>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </td>

                            <td>
                                <span class="admin-status-badge">
                                    <?php
                                    echo class_share_escape(
                                        isset(
                                            $status_names[
                                                $account_status
                                            ]
                                        )
                                        ? $status_names[
                                            $account_status
                                        ]
                                        : $account_status
                                    );
                                    ?>
                                </span>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $format_datetime(
                                        $account[
                                            'last_login_at'
                                        ]
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $format_datetime(
                                        $account[
                                            'created_at'
                                        ]
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <a
                                    class="admin-table-action"
                                    href="/class-share/admin/admin_edit.php?admin_id=<?php
                                    echo $account_id;
                                    ?>">
                                    계정 수정
                                </a>

                                <?php if (
                                    (int)$account[
                                        'is_super_admin'
                                    ] !== 1
                                ) { ?>
                                    <a
                                        class="admin-table-action"
                                        href="/class-share/admin/admin_assignment.php?admin_id=<?php
                                        echo $account_id;
                                        ?>">
                                        학교 권한 관리
                                    </a>
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
