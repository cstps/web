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

$school_assignments =
    array();

if (!$is_super_admin) {
    $school_assignments =
        pdo_query(
            "
            SELECT
                school.school_name,
                assignment.role

            FROM class_share_admin_school AS assignment

            INNER JOIN class_share_school AS school
                ON school.id =
                   assignment.school_id

            WHERE assignment.admin_id = ?
              AND assignment.active = 1

            ORDER BY
                school.school_name,
                school.id
            ",
            (int)$admin['id']
        );

    if ($school_assignments === false) {
        http_response_code(500);
        exit('관리자 학교 권한을 불러올 수 없습니다.');
    }
}

$role_names =
    array(
        'school_admin' =>
            '학교 관리자',

        'editor' =>
            '편집자',

        'viewer' =>
            '조회자'
    );

$page_title =
    '대시보드';

$active_menu =
    'dashboard';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
<section class="admin-panel">
    <h2>
        관리자 로그인 정보
    </h2>

    <div class="admin-field">
        <label>관리자 이름</label>

        <p>
            <?php
            echo class_share_escape(
                $admin['display_name']
            );
            ?>
        </p>
    </div>

    <div class="admin-field">
        <label>관리자 아이디</label>

        <p>
            <?php
            echo class_share_escape(
                $admin['login_id']
            );
            ?>
        </p>
    </div>

    <div class="admin-field">
        <label>권한</label>

        <p>
            <?php if ($is_super_admin) { ?>
                최고관리자
            <?php } elseif (
                count($school_assignments) === 0
            ) { ?>
                <span class="admin-muted">
                    배정된 학교 없음
                </span>
            <?php } else { ?>
                <?php foreach (
                    $school_assignments as
                    $assignment
                ) { ?>
                    <span>
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
                            (string)$assignment['role'];

                        echo class_share_escape(
                            isset($role_names[$role])
                            ? $role_names[$role]
                            : $role
                        );
                        ?>
                    </span>
                    <br>
                <?php } ?>
            <?php } ?>
        </p>
    </div>
</section>

<section class="admin-panel admin-panel-spaced">

    <h2>
        학교 행사 관리
    </h2>

    <p class="admin-muted">
        학교를 등록한 후 학교별 행사, 수업,
        공지사항과 참관 신청내역을 관리할 수 있습니다.
    </p>

    <?php
    if ($is_super_admin) {
    ?>
        <p class="admin-muted">
            왼쪽의 ‘학교·행사’ 메뉴에서
            첫 번째 학교를 등록할 수 있습니다.
        </p>
    <?php } ?>
</section>

<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
