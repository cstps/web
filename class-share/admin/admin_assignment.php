<?php

require_once(__DIR__ . '/include/admin_init.php');

$admin = class_share_admin_require_login();

if (!class_share_admin_is_super_admin($admin)) {
    http_response_code(403);
    exit('관리자 권한을 배정할 권한이 없습니다.');
}

$target_admin_id = isset($_GET['admin_id'])
    ? (int)$_GET['admin_id']
    : 0;

if ($target_admin_id <= 0) {
    http_response_code(400);
    exit('관리자 번호가 올바르지 않습니다.');
}

$target_rows = pdo_query(
    "
    SELECT
        id,
        login_id,
        display_name,
        is_super_admin,
        status
    FROM class_share_admin
    WHERE id = ?
    LIMIT 1
    ",
    $target_admin_id
);

if ($target_rows === false) {
    http_response_code(500);
    exit('관리자 계정을 불러올 수 없습니다.');
}

if (!isset($target_rows[0])) {
    http_response_code(404);
    exit('관리자 계정을 찾을 수 없습니다.');
}

$target_admin = $target_rows[0];

if ((int)$target_admin['is_super_admin'] === 1) {
    http_response_code(400);
    exit('최고관리자는 학교별 역할을 배정하지 않습니다.');
}

$schools = pdo_query(
    "
    SELECT
        school.id,
        school.school_name,
        school.status,
        assignment.role,
        assignment.active AS assignment_active
    FROM class_share_school AS school
    LEFT JOIN class_share_admin_school AS assignment
        ON assignment.school_id = school.id
       AND assignment.admin_id = ?
    ORDER BY
        CASE school.status
            WHEN 'active' THEN 1
            WHEN 'inactive' THEN 2
            ELSE 3
        END,
        school.school_name,
        school.id
    ",
    $target_admin_id
);

if ($schools === false) {
    http_response_code(500);
    exit('학교 목록을 불러올 수 없습니다.');
}

$status_names = array(
    'active' => '활성',
    'locked' => '잠금',
    'disabled' => '비활성'
);

$school_status_names = array(
    'active' => '운영 중',
    'inactive' => '비활성',
    'archived' => '보관'
);

$role_names = array(
    'school_admin' => '학교 관리자',
    'editor' => '편집자',
    'viewer' => '조회자'
);

$page_title = '학교 권한 관리';
$active_menu = 'admins';

require_once(__DIR__ . '/include/admin_layout_start.php');

?>
<div class="admin-toolbar">
    <div>
        <p class="admin-muted">
            <strong><?php
                echo class_share_escape(
                    $target_admin['display_name']
                );
            ?></strong>
            · <?php
                echo class_share_escape(
                    $target_admin['login_id']
                );
            ?>
            · <?php
                $target_status =
                    (string)$target_admin['status'];

                echo class_share_escape(
                    isset($status_names[$target_status])
                    ? $status_names[$target_status]
                    : $target_status
                );
            ?>
        </p>

        <a class="admin-back-link"
           href="/class-share/admin/admins.php">
            ← 관리자 목록
        </a>
    </div>
</div>

<section class="admin-panel">
    <p class="admin-muted">
        학교 관리자는 학교 설정과 권한을 관리하고,
        편집자는 행사와 신청 자료를 편집하며,
        조회자는 자료만 조회합니다.
    </p>

    <?php if (count($schools) === 0) { ?>
        <div class="admin-empty">
            <strong>배정할 학교가 없습니다.</strong>
        </div>
    <?php } else { ?>
        <form method="post"
              action="/class-share/admin/admin_assignment_save.php">
            <?php echo class_share_admin_csrf_input(); ?>

            <input type="hidden"
                   name="admin_id"
                   value="<?php echo (int)$target_admin_id; ?>">

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>학교명</th>
                            <th>학교 상태</th>
                            <th>배정 역할</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schools as $school) { ?>
                            <?php
                            $school_id = (int)$school['id'];
                            $selected_role =
                                (int)$school['assignment_active'] === 1
                                ? (string)$school['role']
                                : '';
                            $school_status =
                                (string)$school['status'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php
                                        echo class_share_escape(
                                            $school['school_name']
                                        );
                                    ?></strong>
                                </td>
                                <td><?php
                                    echo class_share_escape(
                                        isset(
                                            $school_status_names[
                                                $school_status
                                            ]
                                        )
                                        ? $school_status_names[
                                            $school_status
                                        ]
                                        : $school_status
                                    );
                                ?></td>
                                <td>
                                    <div class="admin-field">
                                        <select
                                            name="roles[<?php echo $school_id; ?>]"
                                            aria-label="<?php
                                            echo class_share_escape(
                                                $school['school_name']
                                            );
                                            ?> 역할">
                                            <option value="">
                                                배정 없음
                                            </option>
                                            <?php foreach (
                                                $role_names as
                                                $role => $role_name
                                            ) { ?>
                                                <option
                                                    value="<?php
                                                    echo $role;
                                                    ?>"<?php
                                                    echo $selected_role === $role
                                                    ? ' selected'
                                                    : '';
                                                    ?>><?php
                                                    echo $role_name;
                                                    ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-form-actions">
                <a class="admin-secondary-link"
                   href="/class-share/admin/admins.php">
                    취소
                </a>

                <button type="submit"
                        class="admin-submit-button">
                    학교 권한 저장
                </button>
            </div>
        </form>
    <?php } ?>
</section>

<?php

require_once(__DIR__ . '/include/admin_layout_end.php');
