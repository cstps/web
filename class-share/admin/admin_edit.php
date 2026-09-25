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
    exit('관리자 계정을 수정할 권한이 없습니다.');
}

$target_admin_id =
    isset($_GET['admin_id'])
    ? (int)$_GET['admin_id']
    : 0;

if ($target_admin_id <= 0) {
    http_response_code(400);
    exit('관리자 번호가 올바르지 않습니다.');
}

$rows =
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
            created_at

        FROM class_share_admin

        WHERE id = ?

        LIMIT 1
        ",
        $target_admin_id
    );

if ($rows === false) {
    http_response_code(500);
    exit('관리자 계정을 불러올 수 없습니다.');
}

if (!isset($rows[0])) {
    http_response_code(404);
    exit('관리자 계정을 찾을 수 없습니다.');
}

$target_admin =
    $rows[0];

$is_self =
    (int)$admin['id'] ===
    $target_admin_id;

$status_names =
    array(
        'active' => '활성',
        'locked' => '잠금',
        'disabled' => '비활성'
    );

$target_status =
    (string)$target_admin['status'];

$page_title =
    '관리자 계정 수정';

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
            관리자 기본정보와 계정 상태를 관리합니다.
        </p>

        <a
            class="admin-back-link"
            href="/class-share/admin/admins.php">
            ← 관리자 목록
        </a>
    </div>
</div>

<section class="admin-panel">
    <form
        method="post"
        action="/class-share/admin/admin_update.php">

        <?php
        echo class_share_admin_csrf_input();
        ?>

        <input
            type="hidden"
            name="admin_id"
            value="<?php echo $target_admin_id; ?>">

        <div class="admin-form-grid">
            <div class="admin-field">
                <label>관리자 아이디</label>

                <p>
                    <code><?php
                    echo class_share_escape(
                        $target_admin['login_id']
                    );
                    ?></code>
                </p>
            </div>

            <div class="admin-field">
                <label>계정 구분</label>

                <p>
                    <?php
                    echo (int)$target_admin[
                        'is_super_admin'
                    ] === 1
                        ? '최고관리자'
                        : '학교별 관리자';
                    ?>
                </p>
            </div>

            <div class="admin-field">
                <label for="display_name">
                    관리자 이름 *
                </label>

                <input
                    type="text"
                    id="display_name"
                    name="display_name"
                    required
                    minlength="2"
                    maxlength="60"
                    value="<?php
                    echo class_share_escape(
                        $target_admin['display_name']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="email">
                    이메일
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="255"
                    value="<?php
                    echo class_share_escape(
                        $target_admin['email'] === null
                        ? ''
                        : $target_admin['email']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="status">
                    계정 상태
                </label>

                <?php if ($is_self) { ?>
                    <p>
                        <?php
                        echo class_share_escape(
                            isset(
                                $status_names[
                                    $target_status
                                ]
                            )
                            ? $status_names[
                                $target_status
                            ]
                            : $target_status
                        );
                        ?>
                    </p>

                    <input
                        type="hidden"
                        name="status"
                        value="<?php
                        echo class_share_escape(
                            $target_status
                        );
                        ?>">

                    <small>
                        현재 로그인한 자신의 계정 상태는 변경할 수 없습니다.
                    </small>
                <?php } else { ?>
                    <select
                        id="status"
                        name="status"
                        required>
                        <?php foreach (
                            $status_names as
                            $status => $status_name
                        ) { ?>
                            <option
                                value="<?php
                                echo $status;
                                ?>"<?php
                                echo $target_status === $status
                                ? ' selected'
                                : '';
                                ?>>
                                <?php echo $status_name; ?>
                            </option>
                        <?php } ?>
                    </select>

                    <?php if (
                        $target_status === 'locked'
                    ) { ?>
                        <small>
                            활성으로 변경하면 로그인 잠금과 실패 횟수를 초기화합니다.
                        </small>
                    <?php } ?>
                <?php } ?>
            </div>

            <div class="admin-field">
                <label>로그인 실패 횟수</label>
                <p>
                    <?php
                    echo (int)$target_admin[
                        'failed_login_count'
                    ];
                    ?>회
                </p>
            </div>

            <div class="admin-field">
                <label>잠금 종료일시</label>
                <p>
                    <?php
                    echo class_share_escape(
                        $target_admin[
                            'locked_until'
                        ] === null
                        ? '-'
                        : $target_admin[
                            'locked_until'
                        ]
                    );
                    ?>
                </p>
            </div>
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-secondary-link"
                href="/class-share/admin/admins.php">
                취소
            </a>

            <button
                type="submit"
                class="admin-submit-button">
                계정 정보 저장
            </button>
        </div>
    </form>
</section>

<section class="admin-panel admin-panel-spaced">
    <h2>비밀번호 재설정</h2>

    <p class="admin-muted">
        비밀번호를 재설정하면 이 관리자의 기존 로그인 세션이 모두 무효화됩니다.
        잠금 계정은 활성 상태로 전환되지만 비활성 계정은 비활성 상태를 유지합니다.
    </p>

    <form
        method="post"
        action="/class-share/admin/admin_password_reset.php"
        autocomplete="off">

        <?php
        echo class_share_admin_csrf_input();
        ?>

        <input
            type="hidden"
            name="admin_id"
            value="<?php echo $target_admin_id; ?>">

        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="new_password">
                    새 비밀번호 *
                </label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    required
                    minlength="12"
                    maxlength="128"
                    autocomplete="new-password">

                <small>
                    12~128자로 입력합니다.
                </small>
            </div>

            <div class="admin-field">
                <label for="new_password_confirmation">
                    새 비밀번호 확인 *
                </label>

                <input
                    type="password"
                    id="new_password_confirmation"
                    name="new_password_confirmation"
                    required
                    minlength="12"
                    maxlength="128"
                    autocomplete="new-password">
            </div>
        </div>

        <div class="admin-form-actions">
            <button
                type="submit"
                class="admin-submit-button">
                비밀번호 재설정
            </button>
        </div>
    </form>
</section>

<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
